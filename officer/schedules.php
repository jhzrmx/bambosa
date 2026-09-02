<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';
require_once __DIR__ . '/../includes/schedules.php';

require_role('officer');

$errors = [];
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
        if ($id > 0) {
            update_schedule($pdo, $id, $sectorId, $startTime, $endTime, $notes);
        } else {
            create_schedule($pdo, $sectorId, $startTime, $endTime, $notes, (int) $_SESSION['user_id']);
        }
        header('Location: ' . base_url('officer/schedules.php?flash=saved'));
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
$schedules = get_all_schedules($pdo);
$flash = $_GET['flash'] ?? null;
$activeNav = 'schedules';
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
    <?php elseif ($flash === 'deleted'): ?>
      <div class="alert alert-success">Schedule removed.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

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
          <tr><td colspan="5" class="empty-row">No rotation schedules posted yet.</td></tr>
        <?php else: foreach ($schedules as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['sector_name']) ?></td>
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
      <?php if ($editingSchedule): ?>
        <a href="<?= base_url('officer/schedules.php') ?>" style="margin-left:1rem;">Cancel</a>
      <?php endif; ?>
    </form>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
