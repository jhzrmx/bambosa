<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';
require_once __DIR__ . '/../includes/account_profile.php';

require_role('officer');

$userId = (int) $_SESSION['user_id'];
list($errors, $saved) = handle_profile_update($pdo, $userId);
$user        = get_user_by_id($pdo, $userId);
$profileUrl  = base_url('officer/profile.php');
$securityUrl = base_url('officer/security.php');
$activeNav   = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
<link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<title>My Profile · BAMBOSA Officer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/officer_sidebar.php'; ?>

  <main class="dash-main">
    <h1>My Profile</h1>

    <?php include __DIR__ . '/../includes/partials/profile_form_body.php'; ?>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
