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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $id        = (int) ($_POST['schedule_id'] ?? 0);
    $sectorId  = (int) ($_POST['sector_id'] ?? 0);
    $startTime = $_POST['start_time'] ?? '';
    $endTime   = $_POST['end_time'] ?? '';
    $notes     = trim($_POST['notes'] ?? '');

    if ($sectorId <= 0) {
        $errors[] = 'Please select a sector.';
    }
    if (!$startTime || !$endTime) {
        $errors[] = 'Please provide both a start and end time.';
    } elseif ($endTime <= $startTime) {
        $errors[] = 'End time must be after the start time.';
    }

    if (empty($errors)) {
        $hasConflict = has_overlapping_schedule($pdo, $sectorId, $startTime, $endTime, $id > 0 ? $id : null);

        if ($id > 0) {
            update_schedule($pdo, $id, $sectorId, $startTime, $endTime, $notes);
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
            <th>Start</th>
            <th>End</th>
            <th>Notes</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($schedules)): ?>
            <tr><td colspan="5" class="empty-row">No rotation schedules found<?= $searchTerm ? ' for "' . htmlspecialchars($searchTerm) . '"' : ' yet' ?>.</td></tr>
          <?php else: foreach ($schedules as $s): ?>
            <?php $hasConflict = has_overlapping_schedule($pdo, (int) $s['sector_id'], $s['start_time'], $s['end_time'], (int) $s['schedule_id']); ?>
            <tr>
              <td>
                <?= htmlspecialchars($s['sector_name']) ?>
                <?php if ($hasConflict): ?>
                  <span class="status-badge status-pending" title="Overlaps another schedule entry in this sector">⚠ Overlap</span>
                <?php endif; ?>
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
</body>
</html>
