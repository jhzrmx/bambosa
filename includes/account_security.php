<?php
/**
 * "Account Security" page logic shared by admin/, officer/ and member/
 * security.php: the change-password form handler and the logged-in user's
 * own login history (successful + failed attempts from login_logs).
 *
 * Requires $pdo, includes/auth.php (csrf_*) and includes/members.php
 * (change_user_password). PHP 7.4-compatible.
 */

/**
 * Handles the change-password POST, if any.
 * Returns [array $errors, bool $saved]; both empty/false on a plain GET.
 */
function handle_password_change(PDO $pdo, int $userId): array
{
    $errors = [];
    $saved  = false;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return [$errors, $saved];
    }

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $errors[] = 'Please fill in all three password fields.';
    } elseif (strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = 'New password and confirmation do not match.';
    }

    if (empty($errors)) {
        if (change_user_password($pdo, $userId, $currentPassword, $newPassword)) {
            $saved = true;
        } else {
            $errors[] = 'Current password is incorrect.';
        }
    }

    return [$errors, $saved];
}

/** Number of this user's login_logs rows (all, or failed only). */
function count_user_login_history(PDO $pdo, int $userId, bool $failedOnly = false): int
{
    $sql = 'SELECT COUNT(*) AS cnt FROM login_logs WHERE user_id = :uid';
    if ($failedOnly) {
        $sql .= " AND status <> 'Success'";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['uid' => $userId]);
    return (int) $stmt->fetch()['cnt'];
}

/** This user's login attempts, newest first (paged). */
function get_user_login_history(PDO $pdo, int $userId, bool $failedOnly, int $limit, int $offset): array
{
    $sql = 'SELECT log_id, login_time, ip_address, status
            FROM login_logs
            WHERE user_id = :uid';
    if ($failedOnly) {
        $sql .= " AND status <> 'Success'";
    }
    $sql .= ' ORDER BY login_time DESC, log_id DESC LIMIT :limit OFFSET :offset';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue('uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Failed attempts against this account in the last $days days, plus the
 * time of the most recent failed attempt ever (null if none).
 * Returns ['recent' => int, 'last_failed' => ?string].
 */
function get_user_failed_login_summary(PDO $pdo, int $userId, int $days = 30): array
{
    $stmt = $pdo->prepare(
        "SELECT SUM(login_time >= (NOW() - INTERVAL :days DAY)) AS recent,
                MAX(login_time) AS last_failed
         FROM login_logs
         WHERE user_id = :uid AND status <> 'Success'"
    );
    $stmt->bindValue('days', $days, PDO::PARAM_INT);
    $stmt->bindValue('uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();
    return [
        'recent'      => (int) ($row['recent'] ?? 0),
        'last_failed' => $row['last_failed'] ?? null,
    ];
}
