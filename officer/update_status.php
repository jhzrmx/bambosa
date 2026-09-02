<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';

require_role('officer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
    header('Location: ' . base_url('officer/members.php'));
    exit;
}

$userId = (int) ($_POST['user_id'] ?? 0);
$status = $_POST['status'] ?? '';

if ($userId > 0 && in_array($status, ['active', 'inactive', 'pending'], true)) {
    try {
        update_user_status($pdo, $userId, $status);
        header('Location: ' . base_url('officer/members.php?flash=status_updated'));
        exit;
    } catch (Exception $e) {
        error_log('Status update error: ' . $e->getMessage());
    }
}

header('Location: ' . base_url('officer/members.php'));
exit;
