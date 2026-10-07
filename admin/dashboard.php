<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';
require_once __DIR__ . '/../includes/admin_users.php';

require_role('admin'); // only admins may view this page

$roleCounts   = count_users_by_role($pdo);
$pendingCount = count_users($pdo, null, 'pending');
$inactiveCount = count_users($pdo, null, 'inactive');

$activeNav = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
<link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<title>Admin Dashboard · BAMBOSA</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/admin_sidebar.php'; ?>

  <main class="dash-main">
    <h1>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></h1>
    <p>This is your admin dashboard. Admins manage every officer and member account.</p>

    <div class="card-grid">
      <a href="<?= base_url('admin/users.php?role=admin') ?>" class="stat-card" style="display:block; text-decoration:none; color:inherit;">
        <div class="label">Admins</div>
        <div class="value"><?= $roleCounts['admin'] ?></div>
      </a>
      <a href="<?= base_url('admin/users.php?role=officer') ?>" class="stat-card" style="display:block; text-decoration:none; color:inherit;">
        <div class="label">Officers</div>
        <div class="value"><?= $roleCounts['officer'] ?></div>
      </a>
      <a href="<?= base_url('admin/users.php?role=member') ?>" class="stat-card" style="display:block; text-decoration:none; color:inherit;">
        <div class="label">Members</div>
        <div class="value"><?= $roleCounts['member'] ?></div>
      </a>
      <a href="<?= base_url('admin/users.php?status=pending') ?>" class="stat-card" style="display:block; text-decoration:none; color:inherit;">
        <div class="label">Pending Approvals</div>
        <div class="value"><?= $pendingCount ?></div>
      </a>
      <a href="<?= base_url('admin/users.php?status=inactive') ?>" class="stat-card" style="display:block; text-decoration:none; color:inherit;">
        <div class="label">Deactivated Accounts</div>
        <div class="value"><?= $inactiveCount ?></div>
      </a>
    </div>

    <h2 class="section-heading">Quick actions</h2>
    <div class="btn-row">
      <a href="<?= base_url('admin/user_edit.php') ?>"><button type="button" class="btn-full" style="max-width:220px;">Add New Account</button></a>
      <a href="<?= base_url('admin/users.php') ?>"><button type="button" class="btn-full" style="max-width:220px;">Manage Accounts</button></a>
      <a href="<?= base_url('admin/login_logs.php') ?>"><button type="button" class="btn-full" style="max-width:220px;">View Login Logs</button></a>
    </div>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
