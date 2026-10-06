<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';
require_once __DIR__ . '/../includes/admin_users.php';
require_once __DIR__ . '/../includes/pagination.php';

require_role('admin');

$selfId = (int) $_SESSION['user_id'];

$roleFilter   = $_GET['role'] ?? '';
$roleFilter   = in_array($roleFilter, USER_ROLES, true) ? $roleFilter : null;
$statusFilter = $_GET['status'] ?? '';
$statusFilter = in_array($statusFilter, USER_STATUSES, true) ? $statusFilter : null;
$searchTerm   = get_search_term();

$total      = count_users($pdo, $roleFilter, $statusFilter, $searchTerm);
$pagination = build_pagination($total, get_current_page());
$users      = get_users($pdo, $roleFilter, $statusFilter, $searchTerm, $pagination['per_page'], $pagination['offset']);

// One-shot error message set by admin/user_delete.php
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
$flash = $_GET['flash'] ?? null;

/** Builds a filter-tab URL keeping the *other* filter. */
function users_tab_url(?string $role, ?string $status): string
{
    $q = array_filter(['role' => $role, 'status' => $status]);
    return base_url('admin/users.php' . ($q ? '?' . http_build_query($q) : ''));
}

$activeNav = 'users';
$baseQuery = array_filter(['role' => $roleFilter, 'status' => $statusFilter]);
$pageBaseUrl = base_url('admin/users.php' . ($baseQuery ? '?' . http_build_query($baseQuery) : ''));
$pageUrlWithSearch = $pageBaseUrl . ($searchTerm ? (str_has($pageBaseUrl, '?') ? '&' : '?') . 'q=' . urlencode($searchTerm) : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
<link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<title>User Management · BAMBOSA Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/admin_sidebar.php'; ?>

  <main class="dash-main">
    <h1>User Management</h1>
    <p>Add, edit, or delete admin, officer, and member accounts.</p>

    <?php if ($flash === 'created'): ?>
      <div class="alert alert-success">Account created.</div>
    <?php elseif ($flash === 'updated'): ?>
      <div class="alert alert-success">Account updated.</div>
    <?php elseif ($flash === 'deleted'): ?>
      <div class="alert alert-success">Account deleted.</div>
    <?php endif; ?>

    <?php if ($flashError): ?>
      <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>

    <a href="<?= base_url('admin/user_edit.php') ?>"><button type="button" class="btn-full" style="max-width:220px;">Add New Account</button></a>

    <div class="filter-tabs wrap">
      <a href="<?= users_tab_url(null, $statusFilter) ?>" class="<?= $roleFilter === null ? 'active' : '' ?>">All Roles</a>
      <a href="<?= users_tab_url('admin', $statusFilter) ?>" class="<?= $roleFilter === 'admin' ? 'active' : '' ?>">Admins</a>
      <a href="<?= users_tab_url('officer', $statusFilter) ?>" class="<?= $roleFilter === 'officer' ? 'active' : '' ?>">Officers</a>
      <a href="<?= users_tab_url('member', $statusFilter) ?>" class="<?= $roleFilter === 'member' ? 'active' : '' ?>">Members</a>
    </div>
    <div class="filter-tabs wrap" style="margin-top:-0.6rem;">
      <a href="<?= users_tab_url($roleFilter, null) ?>" class="<?= $statusFilter === null ? 'active' : '' ?>">Any Status</a>
      <a href="<?= users_tab_url($roleFilter, 'pending') ?>" class="<?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
      <a href="<?= users_tab_url($roleFilter, 'active') ?>" class="<?= $statusFilter === 'active' ? 'active' : '' ?>">Active</a>
      <a href="<?= users_tab_url($roleFilter, 'inactive') ?>" class="<?= $statusFilter === 'inactive' ? 'active' : '' ?>">Inactive</a>
    </div>

    <?php render_search_box(base_url('admin/users.php'), $searchTerm, 'Search by name, email, or contact number…', ['role' => $roleFilter, 'status' => $statusFilter]); ?>

    <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Contact</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr><td colspan="6" class="empty-row">No accounts found<?= $searchTerm ? ' for "' . htmlspecialchars($searchTerm) . '"' : ' for this filter' ?>.</td></tr>
        <?php else: foreach ($users as $u):
            $isSelf = (int) $u['user_id'] === $selfId;
            if ($u['role'] === 'member') {
                $deleteWarning = "Delete this member? Their farm lots, reservations, and ISF bills/payments will be permanently deleted too.";
            } else {
                $deleteWarning = "Delete this " . $u['role'] . " account? This cannot be undone.";
            }
        ?>
          <tr>
            <td><?= htmlspecialchars($u['full_name']) ?><?php if ($isSelf): ?><span class="you-tag">(you)</span><?php endif; ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><span class="role-badge role-<?= htmlspecialchars($u['role']) ?>"><?= htmlspecialchars($u['role']) ?></span></td>
            <td><?= htmlspecialchars($u['contact_number'] ?? '—') ?></td>
            <td><span class="status-badge status-<?= htmlspecialchars($u['status']) ?>"><?= htmlspecialchars($u['status']) ?></span></td>
            <td class="actions-cell">
              <?php if ($isSelf): ?>
                <a href="<?= base_url('admin/profile.php') ?>" class="link-btn">Edit in My Profile</a>
              <?php else: ?>
                <a href="<?= base_url('admin/user_edit.php?id=' . (int) $u['user_id']) ?>" class="link-btn">Edit</a>
                <form method="POST" action="<?= base_url('admin/user_delete.php') ?>" class="inline-form"
                      onsubmit="return confirm(<?= htmlspecialchars(json_encode($deleteWarning), ENT_QUOTES) ?>);">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
                  <button type="submit" class="link-btn deactivate">Delete</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>

    <?php render_pagination_controls($pagination, $pageUrlWithSearch); ?>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
