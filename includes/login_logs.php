<?php
/**
 * Read-only access to the login_logs table (one row per successful login,
 * written by login.php). Used by the admin "User Login Logs" page.
 * PHP 7.4-compatible.
 */

/** Shared WHERE clause + params for the list/count queries. */
function build_login_log_filter(?string $search): array
{
    if ($search === null || $search === '') {
        return ['1=1', []];
    }
    // Searchable: name, email, role, IP address, and the timestamp text
    // (so "2026-10" or "2026-10-07 09" narrows by date/time).
    $where = '(u.full_name LIKE :s1 OR u.email LIKE :s2 OR u.role LIKE :s3
               OR ll.ip_address LIKE :s4 OR CAST(ll.login_time AS CHAR) LIKE :s5)';
    $like = '%' . $search . '%';
    return [$where, ['s1' => $like, 's2' => $like, 's3' => $like, 's4' => $like, 's5' => $like]];
}

/** Login log rows (newest first) joined with the user who logged in. */
function get_login_logs(PDO $pdo, ?string $search = null, ?int $limit = null, ?int $offset = null): array
{
    list($where, $params) = build_login_log_filter($search);
    $sql = "SELECT ll.log_id, ll.user_id, ll.login_time, ll.ip_address,
                   u.full_name, u.email, u.role
            FROM login_logs ll
            JOIN users u ON u.user_id = ll.user_id
            WHERE $where
            ORDER BY ll.login_time DESC, ll.log_id DESC";
    if ($limit !== null && $offset !== null) {
        $sql .= ' LIMIT :limit OFFSET :offset';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    if ($limit !== null && $offset !== null) {
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

function count_login_logs(PDO $pdo, ?string $search = null): int
{
    list($where, $params) = build_login_log_filter($search);
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS cnt
         FROM login_logs ll
         JOIN users u ON u.user_id = ll.user_id
         WHERE $where"
    );
    $stmt->execute($params);
    return (int) $stmt->fetch()['cnt'];
}
