<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';
require_once __DIR__ . '/../includes/pagination.php';

require_role('officer');

$statusFilter = $_GET['status'] ?? '';
$statusFilter = in_array($statusFilter, ['active', 'inactive', 'pending'], true) ? $statusFilter : null;
$searchTerm = get_search_term();

$totalMembers = count_members($pdo, $statusFilter, $searchTerm);
$pagination = build_pagination($totalMembers, get_current_page());
$members = get_all_members($pdo, $statusFilter, $searchTerm, $pagination['per_page'], $pagination['offset']);

// Attach total land area per member for quick reference in the table
foreach ($members as &$m) {
    $m['total_land_area'] = get_total_land_area($pdo, (int) $m['user_id']);
}
unset($m);

$flash = $_GET['flash'] ?? null;
$activeNav = 'members';
$pageBaseUrl = base_url('officer/members.php' . ($statusFilter ? '?status=' . $statusFilter : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Members · BAMBOSA Officer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/officer_sidebar.php'; ?>

  <main class="dash-main">
    <h1>Member Management</h1>
    <p>View registered members, approve pending sign-ups, and manage farm lot records.</p>

    <?php if ($flash === 'status_updated'): ?>
      <div class="alert alert-success">Member status updated.</div>
    <?php elseif ($flash === 'lot_saved'): ?>
      <div class="alert alert-success">Farm lot saved.</div>
    <?php elseif ($flash === 'lot_deleted'): ?>
      <div class="alert alert-success">Farm lot removed.</div>
    <?php endif; ?>

    <div class="filter-tabs">
      <a href="<?= base_url('officer/members.php') ?>" class="<?= $statusFilter === null ? 'active' : '' ?>">All</a>
      <a href="<?= base_url('officer/members.php?status=pending') ?>" class="<?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
      <a href="<?= base_url('officer/members.php?status=active') ?>" class="<?= $statusFilter === 'active' ? 'active' : '' ?>">Active</a>
      <a href="<?= base_url('officer/members.php?status=inactive') ?>" class="<?= $statusFilter === 'inactive' ? 'active' : '' ?>">Inactive</a>
    </div>

    <?php render_search_box($pageBaseUrl, $searchTerm, 'Search by name, email, or contact number…', ['status' => $statusFilter]); ?>

    <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Contact</th>
          <th>Total Land Area (ha)</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($members)): ?>
          <tr><td colspan="6" class="empty-row">No members found<?= $searchTerm ? ' for "' . htmlspecialchars($searchTerm) . '"' : ' for this filter' ?>.</td></tr>
        <?php else: foreach ($members as $m): ?>
          <tr>
            <td><?= htmlspecialchars($m['full_name']) ?></td>
            <td><?= htmlspecialchars($m['email']) ?></td>
            <td><?= htmlspecialchars($m['contact_number'] ?? '—') ?></td>
            <td><?= number_format($m['total_land_area'], 2) ?></td>
            <td><span class="status-badge status-<?= htmlspecialchars($m['status']) ?>"><?= htmlspecialchars($m['status']) ?></span></td>
            <td class="actions-cell">
              <a href="<?= base_url('officer/member_view.php?id=' . (int) $m['user_id']) ?>" class="link-btn">View / Manage Lots</a>

              <?php if ($m['status'] === 'pending'): ?>
                <form method="POST" action="<?= base_url('officer/update_status.php') ?>" class="inline-form">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="user_id" value="<?= (int) $m['user_id'] ?>">
                  <input type="hidden" name="status" value="active">
                  <button type="submit" class="link-btn approve">Approve</button>
                </form>
              <?php elseif ($m['status'] === 'active'): ?>
                <form method="POST" action="<?= base_url('officer/update_status.php') ?>" class="inline-form">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="user_id" value="<?= (int) $m['user_id'] ?>">
                  <input type="hidden" name="status" value="inactive">
                  <button type="submit" class="link-btn deactivate">Deactivate</button>
                </form>
              <?php else: ?>
                <form method="POST" action="<?= base_url('officer/update_status.php') ?>" class="inline-form">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="user_id" value="<?= (int) $m['user_id'] ?>">
                  <input type="hidden" name="status" value="active">
                  <button type="submit" class="link-btn approve">Reactivate</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>

    <?php render_pagination_controls($pagination, $pageBaseUrl . ($searchTerm ? (str_has($pageBaseUrl, '?') ? '&' : '?') . 'q=' . urlencode($searchTerm) : '')); ?>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
