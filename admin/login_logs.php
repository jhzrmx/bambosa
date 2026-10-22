<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/login_logs.php';
require_once __DIR__ . '/../includes/pagination.php';

require_role('admin');

$searchTerm = get_search_term();
$total      = count_login_logs($pdo, $searchTerm);
$pagination = build_pagination($total, get_current_page());
$logs       = get_login_logs($pdo, $searchTerm, $pagination['per_page'], $pagination['offset']);

$pageBaseUrl = base_url('admin/login_logs.php')
    . ($searchTerm ? '?q=' . urlencode($searchTerm) : '');

$activeNav = 'login_logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
<link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<title>User Login Logs · BAMBOSA Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/admin_sidebar.php'; ?>

  <main class="dash-main">
    <h1>User Login Logs</h1>
    <p>Every login attempt, successful or failed, newest first. Repeated <em>Wrong password</em> or <em>Unknown email</em> entries from the same IP address may be someone trying to break in. Click an IP address to see where it is and who runs it.</p>

    <?php render_search_box(base_url('admin/login_logs.php'), $searchTerm, 'Search by name, email, role, IP address, status (e.g. wrong password), or date…'); ?>

    <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>Date &amp; Time</th>
          <th>User</th>
          <th>Email</th>
          <th>Role</th>
          <th>IP Address</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr><td colspan="6" class="empty-row">No login records found<?= $searchTerm ? ' for "' . htmlspecialchars($searchTerm) . '"' : ' yet' ?>.</td></tr>
        <?php else: foreach ($logs as $log): ?>
          <tr>
            <td><?= htmlspecialchars(date('M j, Y g:i:s A', strtotime($log['login_time']))) ?></td>
            <?php if ($log['user_id'] !== null): ?>
              <td><?= htmlspecialchars($log['full_name']) ?></td>
              <td><?= htmlspecialchars($log['email']) ?></td>
              <td><span class="role-badge role-<?= htmlspecialchars($log['role']) ?>"><?= htmlspecialchars($log['role']) ?></span></td>
            <?php else: ?>
              <td class="muted-note">Unknown user</td>
              <td><?= htmlspecialchars((string) $log['email_attempted']) ?></td>
              <td>—</td>
            <?php endif; ?>
            <td>
              <?php if (!empty($log['ip_address'])): ?>
                <a class="ip-link" href="<?= base_url('admin/ip.php?address=' . urlencode($log['ip_address'])) ?>" title="View details for this IP address"><?= htmlspecialchars($log['ip_address']) ?></a>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
            <td>
              <?php $isOk = $log['status'] === 'Success'; ?>
              <span class="login-status <?= $isOk ? 'login-status-ok' : 'login-status-fail' ?>"><?= htmlspecialchars($log['status']) ?></span>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>

    <?php render_pagination_controls($pagination, $pageBaseUrl); ?>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
