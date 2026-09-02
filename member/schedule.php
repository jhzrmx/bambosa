<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/schedules.php';

require_role('member');

$userId = (int) $_SESSION['user_id'];
$mySectorIds = get_member_sector_ids($pdo, $userId);

$mySchedules = [];
foreach ($mySectorIds as $sectorId) {
    $mySchedules = array_merge($mySchedules, get_schedules_by_sector($pdo, (int) $sectorId, true));
}
// Sort combined results chronologically since we merged per-sector queries
usort($mySchedules, fn($a, $b) => strtotime($a['start_time']) <=> strtotime($b['start_time']));

$allUpcoming = get_all_schedules($pdo, true);
$activeNav = 'schedule';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Rotation Status · BAMBOSA Member</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/member_sidebar.php'; ?>

  <main class="dash-main">
    <h1>Water Rotation Status</h1>

    <?php if (empty($mySectorIds)): ?>
      <div class="alert alert-error">
        You don't have a farm lot assigned to a sector yet. Contact an officer to have
        your farm lot registered so you can see your sector's schedule here.
      </div>
    <?php else: ?>
      <h2 class="section-heading">Your Sector's Upcoming Schedule</h2>
      <div class="table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th>Sector</th>
            <th>Start</th>
            <th>End</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($mySchedules)): ?>
            <tr><td colspan="4" class="empty-row">No upcoming water release scheduled for your sector.</td></tr>
          <?php else: foreach ($mySchedules as $s): ?>
            <tr>
              <td><?= htmlspecialchars($s['sector_name']) ?></td>
              <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($s['start_time']))) ?></td>
              <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($s['end_time']))) ?></td>
              <td><?= htmlspecialchars($s['notes'] ?? '—') ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>

    <h2 class="section-heading">All Sectors — Upcoming Schedule</h2>
    <p class="muted-note">For reference, here's the full rotation schedule across Barangay Bikal.</p>
    <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>Sector</th>
          <th>Start</th>
          <th>End</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($allUpcoming)): ?>
          <tr><td colspan="4" class="empty-row">No upcoming schedules posted.</td></tr>
        <?php else: foreach ($allUpcoming as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['sector_name']) ?></td>
            <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($s['start_time']))) ?></td>
            <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($s['end_time']))) ?></td>
            <td><?= htmlspecialchars($s['notes'] ?? '—') ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
