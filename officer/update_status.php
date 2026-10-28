<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';
require_once __DIR__ . '/../includes/mailer.php';

require_role('officer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
    header('Location: ' . base_url('officer/members.php'));
    exit;
}

$userId = (int) ($_POST['user_id'] ?? 0);
$status = $_POST['status'] ?? '';

if ($userId > 0 && in_array($status, USER_STATUSES, true)) {
    try {
        // Look up the previous status BEFORE updating, so the activation
        // email only fires on an actual pending -> active transition, not
        // every time an officer re-saves an already-active member (e.g.
        // toggling inactive -> active -> inactive shouldn't spam an email
        // each time; only a genuine "your account just got approved"
        // moment should).
        $member = get_user_by_id($pdo, $userId);
        // Officers manage MEMBER accounts only; admin/officer accounts are
        // changed by an admin (admin/users.php).
        if (!$member || $member['role'] !== 'member') {
            header('Location: ' . base_url('officer/members.php'));
            exit;
        }
        $wasPending = $member && $member['status'] === 'pending';

        update_user_status($pdo, $userId, $status);

        if ($wasPending && $status === 'active' && $member) {
            // Email failure here must never affect the status change
            // itself — the account is already active in the database by
            // this point regardless of what happens next. send_email()
            // already catches and logs its own errors, so this is
            // intentionally "fire and forget" from this page's point of view.
            send_account_activation_email($member, 'an association officer');
        }

        header('Location: ' . base_url('officer/members.php?flash=status_updated'));
        exit;
    } catch (Exception $e) {
        error_log('Status update error: ' . $e->getMessage());
    }
}

header('Location: ' . base_url('officer/members.php'));
exit;
