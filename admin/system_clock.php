<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/timezone.php';

require_role('admin');

$clock = get_timezone_status($pdo);

$activeNav = 'system_clock';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
<link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<title>System Clock · BAMBOSA Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/admin_sidebar.php'; ?>

  <main class="dash-main">
    <h1>System Clock</h1>
    <p>PHP (the web server) and MySQL (the database) should show the same time. The timezone is set in <code>config/timezone.php</code>.</p>

    <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr><th></th><th>Timezone</th><th>Current time</th></tr>
      </thead>
      <tbody>
        <tr>
          <td>PHP (web server)</td>
          <td><?= htmlspecialchars($clock['php_timezone']) ?></td>
          <td><?= htmlspecialchars($clock['php_now']) ?></td>
        </tr>
        <tr>
          <td>MySQL (database)</td>
          <td><?= htmlspecialchars($clock['db_timezone']) ?></td>
          <td><?= htmlspecialchars($clock['db_now']) ?></td>
        </tr>
      </tbody>
    </table>
    </div>
    <?php if ($clock['configured'] === null): ?>
      <?php if (!$clock['in_sync']): ?>
        <div class="alert alert-error" style="margin-top:0.8rem;">
          PHP and MySQL are currently on <strong>different clocks</strong>, which can cause
          wrong expiry times and shifted dates.
        </div>
      <?php endif; ?>
      <p class="muted-note">
        <code>APP_TIMEZONE</code> is empty, so timezone syncing is switched off and PHP and MySQL are using whatever the server gave them.
        Set it in <code>config/timezone.php</code> (e.g. <code>'Asia/Manila'</code>) to keep them in sync.
      </p>
    <?php elseif (!$clock['in_sync']): ?>
      <div class="alert alert-error" style="margin-top:0.8rem;">
        PHP and MySQL are showing different times even though <code><?= htmlspecialchars($clock['configured']) ?></code>
        is configured. Check the PHP error log and the MySQL server's timezone support.
      </div>
    <?php else: ?>
      <p class="muted-note">In sync — both use <strong><?= htmlspecialchars($clock['configured']) ?></strong> (set in <code>config/timezone.php</code>).</p>
    <?php endif; ?>

    <div class="btn-row" style="margin-top:1rem;">
      <a href="<?= base_url('admin/system_clock.php') ?>"><button type="button" class="btn-full" style="max-width:220px;">Refresh</button></a>
    </div>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
