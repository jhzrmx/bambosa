<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';
require_once __DIR__ . '/../includes/account_profile.php';

require_role('member');

$userId = (int) $_SESSION['user_id'];
list($errors, $saved) = handle_profile_update($pdo, $userId);
$lots      = get_farm_lots_by_user($pdo, $userId);
$totalArea = get_total_land_area($pdo, $userId);
$user        = get_user_by_id($pdo, $userId);
$profileUrl  = base_url('member/profile.php');
$securityUrl = base_url('member/security.php');
$activeNav   = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
<link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<title>My Profile · BAMBOSA Member</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/member_sidebar.php'; ?>

  <main class="dash-main">
    <h1>My Profile &amp; Farm Lot</h1>

    <?php include __DIR__ . '/../includes/partials/profile_form_body.php'; ?>

    <h2 class="section-heading">My Farm Lots</h2>
    <p class="muted-note">
      Farm lot records (land area, sector, boundary) are maintained by association
      officers to keep billing and mapping data accurate. Contact an officer if any
      information below needs correction.
    </p>

    <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>Lot Name</th>
          <th>Sector</th>
          <th>Land Area (ha)</th>
          <th>Boundary</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($lots)): ?>
          <tr><td colspan="4" class="empty-row">No farm lots have been registered for your account yet.</td></tr>
        <?php else: foreach ($lots as $lot): ?>
          <tr>
            <td><?= htmlspecialchars($lot['lot_name']) ?></td>
            <td><?= htmlspecialchars($lot['sector_name'] ?? '—') ?></td>
            <td><?= number_format($lot['land_area_ha'], 2) ?></td>
            <td>
              <?php if (!empty($lot['boundary_geojson'])): ?>
                <span class="status-badge status-active">Drawn</span>
              <?php else: ?>
                <span class="status-badge status-inactive">Not drawn</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>

    <?php if (!empty($lots)): ?>
      <p style="margin-top:1rem;"><strong>Total registered land area:</strong> <?= number_format($totalArea, 2) ?> ha</p>
    <?php endif; ?>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
