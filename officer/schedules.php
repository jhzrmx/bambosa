<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';
require_once __DIR__ . '/../includes/schedules.php';
require_once __DIR__ . '/../includes/pagination.php';

require_role('officer');

$errors = [];
$isAddingOrEditing = isset($_GET["new"]) || isset($_GET["edit"]);
$editingSchedule = null;

if (isset($_GET['edit'])) {
    $editingSchedule = get_schedule_by_id($pdo, (int) $_GET['edit']);
}

// Sector IDs already sharing this schedule's group (siblings), used both
// to pre-check the "other sectors" list when editing and, together with
// the primary sector, as the full "wanted" set update_schedule_group()
// reconciles against.
$editingSiblingSectorIds = $editingSchedule
    ? get_sibling_schedule_sector_ids($pdo, (int) $editingSchedule['schedule_id'])
    : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $id        = (int) ($_POST['schedule_id'] ?? 0);
    $sectorId  = (int) ($_POST['sector_id'] ?? 0);
    $startTime = $_POST['start_time'] ?? '';
    $endTime   = $_POST['end_time'] ?? '';
    $notes     = trim($_POST['notes'] ?? '');
    $applyToOtherSectors = isset($_POST['apply_to_other_sectors']);
    $otherSectorIds = $applyToOtherSectors
        ? array_map('intval', $_POST['other_sector_ids'] ?? [])
        : [];

    if ($sectorId <= 0) {
        $errors[] = 'Please select a sector.';
    }
    if (!$startTime || !$endTime) {
        $errors[] = 'Please provide both a start and end time.';
    } elseif ($endTime <= $startTime) {
        $errors[] = 'End time must be after the start time.';
    }

    if (empty($errors)) {
        // The full set of sectors this schedule should apply to: the
        // primary sector picked in the dropdown, plus any additional
        // sectors checked under "also create/keep this schedule for
        // other sectors" (only meaningful when that checkbox is on).
        $allSectorIds = array_unique(array_merge([$sectorId], $otherSectorIds));

        // When editing, build a sector_id => schedule_id map of whatever
        // rows currently make up this schedule's group (or just itself,
        // if it isn't grouped), so the conflict check below can exclude
        // each sector's OWN existing row rather than flagging a sibling
        // as "conflicting" with itself.
        $existingRowBySector = [];
        if ($id > 0) {
            $existingRowBySector[$sectorId] = $id;
            if (!empty($editingSiblingSectorIds)) {
                $currentSchedule = get_schedule_by_id($pdo, $id);
                if ($currentSchedule && $currentSchedule['group_id'] !== null) {
                    $siblingStmt = $pdo->prepare(
                        'SELECT schedule_id, sector_id FROM rotation_schedules WHERE group_id = :gid'
                    );
                    $siblingStmt->execute(['gid' => $currentSchedule['group_id']]);
                    foreach ($siblingStmt->fetchAll() as $row) {
                        $existingRowBySector[(int) $row['sector_id']] = (int) $row['schedule_id'];
                    }
                }
            }
        }

        // Conflict check against every sector this will end up covering,
        // not just the primary one, so the warning doesn't miss a
        // conflict hiding in one of the "other sectors". Each sector
        // excludes its own existing row (if any) so an unchanged sibling
        // doesn't flag as conflicting with itself.
        $hasConflict = false;
        foreach ($allSectorIds as $checkSectorId) {
            $excludeId = $existingRowBySector[$checkSectorId] ?? null;
            if (has_overlapping_schedule($pdo, $checkSectorId, $startTime, $endTime, $excludeId)) {
                $hasConflict = true;
                break;
            }
        }

        if ($id > 0) {
            update_schedule_group($pdo, $id, $sectorId, $allSectorIds, $startTime, $endTime, $notes, (int) $_SESSION['user_id']);
        } elseif (count($allSectorIds) > 1) {
            create_schedule_group($pdo, $allSectorIds, $startTime, $endTime, $notes, (int) $_SESSION['user_id']);
        } else {
            create_schedule($pdo, $sectorId, $startTime, $endTime, $notes, (int) $_SESSION['user_id']);
        }
        header('Location: ' . base_url('officer/schedules.php?flash=' . ($hasConflict ? 'saved_conflict' : 'saved')));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (csrf_verify($_POST['csrf_token'] ?? null)) {
        delete_schedule($pdo, (int) ($_POST['schedule_id'] ?? 0));
    }
    header('Location: ' . base_url('officer/schedules.php?flash=deleted'));
    exit;
}

$sectors = get_sectors($pdo);
$searchTerm = get_search_term();
$totalSchedules = count_schedules($pdo, false, $searchTerm);
$pagination = build_pagination($totalSchedules, get_current_page());
$schedules = get_all_schedules($pdo, false, $searchTerm, $pagination['per_page'], $pagination['offset']);
$flash = $_GET['flash'] ?? null;
$activeNav = 'schedules';
$pageBaseUrl = base_url('officer/schedules.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
<link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<title>Rotation Scheduling · BAMBOSA Officer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/officer_sidebar.php'; ?>

  <main class="dash-main">
    <h1>Rotation Scheduling</h1>
    <p>Post water release windows for each sector. Members will see the schedule relevant to their own farm lot's sector.</p>

    <?php if ($flash === 'saved'): ?>
      <div class="alert alert-success">Schedule saved.</div>
    <?php elseif ($flash === 'saved_conflict'): ?>
      <div class="alert alert-error">Schedule saved — heads up: this sector already has another schedule entry that overlaps this time window. Double-check both entries are intentional.</div>
    <?php elseif ($flash === 'deleted'): ?>
      <div class="alert alert-success">Schedule removed.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>
	
	<?php if (!$isAddingOrEditing): ?>
      <?php render_search_box($pageBaseUrl, $searchTerm, 'Search by sector or notes…'); ?>

      <div class="table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th>Sector</th>
            <th>Status</th>
            <th>Start</th>
            <th>End</th>
            <th>Notes</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($schedules)): ?>
            <tr><td colspan="6" class="empty-row">No rotation schedules found<?= $searchTerm ? ' for "' . htmlspecialchars($searchTerm) . '"' : ' yet' ?>.</td></tr>
          <?php else: foreach ($schedules as $s): ?>
            <?php $hasConflict = has_overlapping_schedule($pdo, (int) $s['sector_id'], $s['start_time'], $s['end_time'], (int) $s['schedule_id']); ?>
            <tr>
              <td>
                <?= htmlspecialchars($s['sector_name']) ?>
                <?php if ($hasConflict): ?>
                  <span class="status-badge status-pending" title="Overlaps another schedule entry in this sector">⚠ Overlap</span>
                <?php endif; ?>
                <?php if (!empty($s['group_id'])): ?>
                  <span class="status-badge status-active" title="This schedule is shared with one or more other sectors">Shared</span>
                <?php endif; ?>
              </td>
              <td>
                <?php
                  $timeStatus = get_schedule_time_status($s['start_time'], $s['end_time']);
                  $statusLabels = ['ongoing' => 'Happening Now', 'upcoming' => 'Upcoming', 'finished' => 'Finished'];
                  $statusClasses = ['ongoing' => 'status-active', 'upcoming' => 'status-upcoming', 'finished' => 'status-finished'];
                ?>
                <span class="status-badge <?= $statusClasses[$timeStatus] ?>"><?= $statusLabels[$timeStatus] ?></span>
              </td>
              <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($s['start_time']))) ?></td>
              <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($s['end_time']))) ?></td>
              <td><?= htmlspecialchars($s['notes'] ?? '—') ?></td>
              <td class="actions-cell">
                <a href="<?= base_url('officer/schedules.php?edit=' . (int) $s['schedule_id']) ?>" class="link-btn">Edit</a>
                <form method="POST" class="inline-form" onsubmit="return confirm('Delete this schedule entry?');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="schedule_id" value="<?= (int) $s['schedule_id'] ?>">
                  <button type="submit" class="link-btn deactivate">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
      </div>

      <?php render_pagination_controls($pagination, $pageBaseUrl . ($searchTerm ? '?q=' . urlencode($searchTerm) : '')); ?>

	  <a href="<?= base_url('officer/schedules.php?new=') ?>"><button type="button" class="btn-full" style="max-width:220px; margin-top:24px;">Add New Schedule</button></a>
	<?php endif; ?>
	
	<?php if ($isAddingOrEditing): ?>
      <h2 class="section-heading"><?= $editingSchedule ? 'Edit Schedule' : 'Add New Schedule' ?></h2>
	  
      <form method="POST" action="<?= base_url('officer/schedules.php') ?>" class="inline-card-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="schedule_id" value="<?= $editingSchedule ? (int) $editingSchedule['schedule_id'] : 0 ?>">
	  
        <div class="form-group">
          <label for="sector_id">Sector</label>
          <select id="sector_id" name="sector_id" required>
            <option value="">— Select —</option>
            <?php foreach ($sectors as $sec): ?>
              <option value="<?= (int) $sec['sector_id'] ?>"
                <?= ($editingSchedule['sector_id'] ?? null) == $sec['sector_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($sec['sector_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>
            <input type="checkbox" id="apply_to_other_sectors" name="apply_to_other_sectors"
              style="width:auto; display:inline-block;" <?= !empty($editingSiblingSectorIds) ? 'checked' : '' ?>>
            Also <?= $editingSchedule ? 'apply this same schedule to' : 'create this same schedule for' ?> other sectors
          </label>
          <p class="muted-note" style="margin:0.3rem 0 0;">
            <?= $editingSchedule
                ? 'Checked sectors below will be kept in sync with the start/end time and notes above. Unchecking a sector that currently shares this schedule will ungroup it — that sector keeps its own schedule entry (unchanged), it just stops being kept in sync with this one. Delete it separately from the list below if you actually want it gone.'
                : 'Check any additional sectors that should get this exact same start/end time and notes, saved as separate entries you can still edit individually later.' ?>
          </p>
          <div id="otherSectorsList" class="checkbox-list" style="<?= empty($editingSiblingSectorIds) ? 'display:none;' : '' ?>">
            <?php foreach ($sectors as $sec): ?>
              <label class="checkbox-list-item" id="otherSectorItem<?= (int) $sec['sector_id'] ?>">
                <input type="checkbox" name="other_sector_ids[]" value="<?= (int) $sec['sector_id'] ?>"
                  class="other-sector-checkbox"
                  data-sector-id="<?= (int) $sec['sector_id'] ?>"
                  <?= in_array((int) $sec['sector_id'], $editingSiblingSectorIds, true) ? 'checked' : '' ?>>
                <?= htmlspecialchars($sec['sector_name']) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
	  
        <div class="form-row">
          <div class="form-group">
            <label for="start_time">Start Date &amp; Time</label>
            <input type="datetime-local" id="start_time" name="start_time" required
                   value="<?= $editingSchedule ? htmlspecialchars(str_replace(' ', 'T', substr($editingSchedule['start_time'], 0, 16))) : '' ?>">
          </div>
          <div class="form-group">
            <label for="end_time">End Date &amp; Time</label>
            <input type="datetime-local" id="end_time" name="end_time" required
                   value="<?= $editingSchedule ? htmlspecialchars(str_replace(' ', 'T', substr($editingSchedule['end_time'], 0, 16))) : '' ?>">
          </div>
        </div>
	  
        <div class="form-group">
          <label for="notes">Notes (optional)</label>
          <input type="text" id="notes" name="notes" value="<?= htmlspecialchars($editingSchedule['notes'] ?? '') ?>">
        </div>
	  
        <button type="submit" class="btn-full" style="max-width:220px;">
          <?= $editingSchedule ? 'Update Schedule' : 'Add Schedule' ?>
        </button>
        <a href="<?= base_url('officer/schedules.php') ?>" style="margin-left:1rem;">Cancel</a>
      </form>
	<?php endif; ?>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
<?php if ($isAddingOrEditing && !empty($sectors)): ?>
<script>
(function () {
  var toggleCheckbox = document.getElementById('apply_to_other_sectors');
  var otherSectorsList = document.getElementById('otherSectorsList');
  var sectorSelect = document.getElementById('sector_id');
  if (!toggleCheckbox || !otherSectorsList || !sectorSelect) return;

  function updatePrimarySectorExclusion() {
    // The sector currently chosen in the main dropdown can't also be
    // picked in the "other sectors" list — that would mean applying the
    // schedule to the same sector twice. Disable (and uncheck) whichever
    // checkbox matches the current dropdown selection, re-enable the rest.
    var primaryId = sectorSelect.value;
    document.querySelectorAll('.other-sector-checkbox').forEach(function (cb) {
      var item = document.getElementById('otherSectorItem' + cb.dataset.sectorId);
      var isPrimary = cb.dataset.sectorId === primaryId;
      cb.disabled = isPrimary;
      if (isPrimary) cb.checked = false;
      if (item) item.classList.toggle('is-disabled', isPrimary);
    });
  }

  function updateListVisibility() {
    otherSectorsList.style.display = toggleCheckbox.checked ? 'flex' : 'none';
  }

  toggleCheckbox.addEventListener('change', updateListVisibility);
  sectorSelect.addEventListener('change', updatePrimarySectorExclusion);

  // Initial state on page load (handles both a fresh "Add" form and an
  // "Edit" form that's re-rendering with the checkbox already checked).
  updateListVisibility();
  updatePrimarySectorExclusion();
})();
</script>
<?php endif; ?>
</body>
</html>
