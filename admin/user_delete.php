<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';
require_once __DIR__ . '/../includes/admin_users.php';

require_role('admin');

$back = base_url('admin/users.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
    header('Location: ' . $back);
    exit;
}

$targetId = (int) ($_POST['user_id'] ?? 0);
$target   = $targetId > 0 ? get_user_by_id($pdo, $targetId) : null;

if (!$target) {
    $_SESSION['flash_error'] = 'That account no longer exists.';
    header('Location: ' . $back);
    exit;
}

$guardError = user_change_guard_error($pdo, $target, (int) $_SESSION['user_id'], null, null, true);
if ($guardError !== null) {
    $_SESSION['flash_error'] = $guardError;
    header('Location: ' . $back);
    exit;
}

try {
    delete_user($pdo, $targetId);
    header('Location: ' . $back . '?flash=deleted');
} catch (PDOException $e) {
    error_log('Admin delete user error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Could not delete that account. Please try again.';
    header('Location: ' . $back);
}
exit;
