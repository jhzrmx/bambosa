<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';
require_once __DIR__ . '/../includes/machinery.php';
require_once __DIR__ . '/../includes/schedules.php';
require_once __DIR__ . '/../includes/billing.php';

require_role('officer'); // only officers may view this page

$totalMembers      = count(get_all_members($pdo));
$pendingCount      = count(get_all_members($pdo, 'pending'));
$pendingReservations = count(get_all_reservations($pdo, 'pending'));
$upcomingSchedules   = count(get_all_schedules($pdo, true));
$mappedLotCount      = count(get_mapped_farm_lots($pdo));

$totalOutstanding = (float) $pdo->query(
    "SELECT COALESCE(SUM(amount_due - amount_paid), 0) AS total FROM isf_bills WHERE status != 'paid'"
)->fetch()['total'];

$activeNav = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Officer Dashboard · BAMBOSA</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/officer_sidebar.php'; ?>

  <main class="dash-main">
    <h1>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></h1>
    <p>This is your officer dashboard.</p>

    <div class="card-grid">
      <a href="<?= base_url('officer/members.php') ?>" class="stat-card" style="display:block; text-decoration:none; color:inherit;">
        <div class="label">Registered Members</div>
        <div class="value"><?= $totalMembers ?></div>
      </a>
      <a href="<?= base_url('officer/members.php?status=pending') ?>" class="stat-card" style="display:block; text-decoration:none; color:inherit;">
        <div class="label">Pending Approvals</div>
        <div class="value"><?= $pendingCount ?></div>
      </a>
      <a href="<?= base_url('officer/billing_periods.php') ?>" class="stat-card" style="display:block; text-decoration:none; color:inherit;">
        <div class="label">Total Outstanding ISF</div>
        <div class="value" style="font-size:1.3rem;">₱<?= number_format($totalOutstanding, 2) ?></div>
      </a>
      <a href="<?= base_url('officer/reservations.php?status=pending') ?>" class="stat-card" style="display:block; text-decoration:none; color:inherit;">
        <div class="label">Pending Reservations</div>
        <div class="value"><?= $pendingReservations ?></div>
      </a>
      <a href="<?= base_url('officer/schedules.php') ?>" class="stat-card" style="display:block; text-decoration:none; color:inherit;">
        <div class="label">Upcoming Water Releases</div>
        <div class="value"><?= $upcomingSchedules ?></div>
      </a>
      <a href="<?= base_url('officer/map.php') ?>" class="stat-card" style="display:block; text-decoration:none; color:inherit;">
        <div class="label">Farm Lots Mapped</div>
        <div class="value"><?= $mappedLotCount ?></div>
      </a>
    </div>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
