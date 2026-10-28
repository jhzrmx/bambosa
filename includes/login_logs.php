<?php
/**
 * Access to the login_logs table: one row per login attempt, successful or
 * not (written by login.php). Successful logins are inserted by login.php
 * itself and rely on the column default status = 'Success'; failed attempts
 * go through record_failed_login() below. The read functions feed the admin
 * "User Login Logs" page.
 * PHP 7.4-compatible.
 */

require_once __DIR__ . '/db_helpers.php';

/**
 * Records one failed login attempt and returns the status text it stored.
 *
 * $reason: 'wrong_password' | 'unknown_email' | 'pending' | 'inactive'
 *
 * "attempt #N" counts the consecutive failed attempts (wrong password or
 * unknown email) for that account since its last successful login. For an
 * email that matches no account, user_id is NULL and the typed email is
 * kept in email_attempted, so the attempts are counted per typed email.
 *
 * Never throws: a logging problem must not break the login page itself.
 */
function record_failed_login(PDO $pdo, ?int $userId, string $emailTyped, string $reason): string
{
    $status = 'Failed login';
    try {
        if ($reason === 'pending') {
            $status = 'Correct password, account pending';
        } elseif ($reason === 'inactive') {
            $status = 'Correct password, account inactive';
        } else {
            if ($userId !== null) {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) AS cnt FROM login_logs
                     WHERE user_id = :uid1
                       AND (status LIKE 'Wrong password%' OR status LIKE 'Unknown email%')
                       AND log_id > COALESCE(
                           (SELECT MAX(log_id) FROM login_logs WHERE user_id = :uid2 AND status = 'Success'), 0)"
                );
                $stmt->execute(['uid1' => $userId, 'uid2' => $userId]);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) AS cnt FROM login_logs
                     WHERE user_id IS NULL AND email_attempted = :em
                       AND status LIKE 'Unknown email%'"
                );
                $stmt->execute(['em' => mb_substr($emailTyped, 0, 150)]);
            }
            $attempt = (int) $stmt->fetch()['cnt'] + 1;
            $label   = $reason === 'unknown_email' ? 'Unknown email' : 'Wrong password';
            $status  = $label . ', attempt #' . $attempt;
        }

        $log = $pdo->prepare(
            'INSERT INTO login_logs (user_id, ip_address, status, email_attempted)
             VALUES (:uid, :ip, :status, :em)'
        );
        $log->execute([
            'uid'    => $userId,
            'ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
            'status' => $status,
            'em'     => $userId === null ? mb_substr($emailTyped, 0, 150) : null,
        ]);
    } catch (PDOException $e) {
        error_log('Failed-login log error: ' . $e->getMessage());
    }
    return $status;
}

/** Shared WHERE clause + params for the list/count queries. */
function build_login_log_filter(?string $search): array
{
    // Searchable: name, email (account's or the one typed), role, IP address,
    // status (so "wrong password" or "failed" narrows to bad attempts) and
    // the timestamp text (so "2026-10" or "2026-10-07 09" narrows by date/time).
    list($searchSql, $params) = db_like_clause(
        ['u.full_name', 'u.email', 'u.role', 'll.ip_address', 'CAST(ll.login_time AS CHAR)', 'll.status', 'll.email_attempted'],
        $search,
        's'
    );
    return [$searchSql !== '' ? $searchSql : '1=1', $params];
}

/**
 * Login log rows (newest first). Left-joined to users because a failed
 * attempt with an unknown email has no user; for those rows full_name,
 * email and role are NULL and email_attempted holds the typed email.
 */
function get_login_logs(PDO $pdo, ?string $search = null, ?int $limit = null, ?int $offset = null): array
{
    list($where, $params) = build_login_log_filter($search);
    $sql = "SELECT ll.log_id, ll.user_id, ll.login_time, ll.ip_address,
                   ll.status, ll.email_attempted,
                   u.full_name, u.email, u.role
            FROM login_logs ll
            LEFT JOIN users u ON u.user_id = ll.user_id
            WHERE $where
            ORDER BY ll.login_time DESC, ll.log_id DESC";
    return db_fetch_all($pdo, $sql, $params, $limit, $offset);
}

function count_login_logs(PDO $pdo, ?string $search = null): int
{
    list($where, $params) = build_login_log_filter($search);
    return db_count(
        $pdo,
        "SELECT COUNT(*) AS cnt
         FROM login_logs ll
         LEFT JOIN users u ON u.user_id = ll.user_id
         WHERE $where",
        $params
    );
}
