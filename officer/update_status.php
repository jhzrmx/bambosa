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

if ($userId > 0 && in_array($status, ['active', 'inactive', 'pending'], true)) {
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
            $bodyHtml = '
                <p style="margin:0 0 16px; color:#1b2b23; font-size:15px;">Hi ' . htmlspecialchars($member['full_name'], ENT_QUOTES, 'UTF-8') . ',</p>
                <p style="margin:0 0 16px; color:#1b2b23; font-size:15px;">Good news — your BAMBOSA account has been approved by an association officer. You can now log in and access your dashboard.</p>
                <p style="margin:0 0 24px; text-align:center;">
                  <a href="' . htmlspecialchars(base_url('login.php'), ENT_QUOTES, 'UTF-8') . '" style="background:#1f7a4d; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:bold; display:inline-block;">Log In</a>
                </p>
            ';
            send_email(
                $member['email'],
                $member['full_name'],
                'Your BAMBOSA account is now active',
                render_email_layout('Account activated', $bodyHtml)
            );
        }

        header('Location: ' . base_url('officer/members.php?flash=status_updated'));
        exit;
    } catch (Exception $e) {
        error_log('Status update error: ' . $e->getMessage());
    }
}

header('Location: ' . base_url('officer/members.php'));
exit;
