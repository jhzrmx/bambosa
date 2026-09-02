<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/machinery.php';
require_once __DIR__ . '/../includes/schedules.php';
require_once __DIR__ . '/../includes/billing.php';

require_role('member'); // only members may view this page

$userId = (int) $_SESSION['user_id'];

$mySectorIds = get_member_sector_ids($pdo, $userId);
$nextRelease = null;
foreach ($mySectorIds as $sectorId) {
    $upcoming = get_schedules_by_sector($pdo, (int) $sectorId, true);
    if (!empty($upcoming) && ($nextRelease === null || strtotime($upcoming[0]['start_time']) < strtotime($nextRelease['start_time']))) {
        $nextRelease = $upcoming[0];
    }
}

$myReservations = get_reservations_by_user($pdo, $userId);
$activeReservationCount = count(array_filter($myReservations, fn($r) => in_array($r['status'], ['pending', 'approved'], true)));
$outstandingBalance = get_outstanding_balance_for_user($pdo, $userId);
$activeNav = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Member Dashboard · BAMBOSA</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/member_sidebar.php'; ?>

  <main class="dash-main">
    <h1>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></h1>
    <p>This is your member dashboard.</p>

    <div class="card-grid">
      <a href="<?= base_url('member/billing.php') ?>" class="stat-card" style="display:block; text-decoration:none; color:inherit;">
        <div class="label">Outstanding Balance</div>
        <div class="value" style="font-size:1.3rem;">₱<?= number_format($outstandingBalance, 2) ?></div>
      </a>
      <a href="<?= base_url('member/schedule.php') ?>" class="stat-card" style="display:block; text-decoration:none; color:inherit;">
        <div class="label">Next Water Release</div>
        <div class="value" style="font-size:1.1rem;">
          <?= $nextRelease ? htmlspecialchars(date('M j, g:i A', strtotime($nextRelease['start_time']))) : 'None scheduled' ?>
        </div>
      </a>
      <a href="<?= base_url('member/machinery.php') ?>" class="stat-card" style="display:block; text-decoration:none; color:inherit;">
        <div class="label">Active Reservations</div>
        <div class="value"><?= $activeReservationCount ?></div>
      </a>
    </div>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
