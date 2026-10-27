<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';
require_once __DIR__ . '/../includes/account_security.php';
require_once __DIR__ . '/../includes/pagination.php';

require_role('admin');

$userId = (int) $_SESSION['user_id'];

list($passwordErrors, $passwordSaved) = handle_password_change($pdo, $userId);

$showFailedOnly = ($_GET['show'] ?? '') === 'failed';
$failedSummary  = get_user_failed_login_summary($pdo, $userId);
$pagination     = build_pagination(count_user_login_history($pdo, $userId, $showFailedOnly), get_current_page());
$logs           = get_user_login_history($pdo, $userId, $showFailedOnly, $pagination['per_page'], $pagination['offset']);

$securityUrl = base_url('admin/security.php');
$activeNav   = 'security';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
<link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<title>Account Security · BAMBOSA Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/admin_sidebar.php'; ?>

  <main class="dash-main">
    <?php include __DIR__ . '/../includes/partials/account_security_body.php'; ?>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
