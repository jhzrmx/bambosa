<?php
/**
 * Admin-only account management data access (add / edit / delete / list
 * accounts of any role). Used by admin/users.php, admin/user_edit.php and
 * admin/user_delete.php. PHP 7.4-compatible.
 *
 * Builds on helpers already in includes/members.php (get_user_by_id(),
 * email_in_use()) — require that file alongside this one.
 */

const USER_ROLES    = ['admin', 'officer', 'member'];
const USER_STATUSES = ['active', 'inactive', 'pending'];

/** Builds the shared WHERE clause + params for list/count queries. */
function build_user_filter(?string $role, ?string $status, ?string $search): array
{
    $where  = ['1=1'];
    $params = [];

    if ($role !== null && in_array($role, USER_ROLES, true)) {
        $where[] = 'role = :role';
        $params['role'] = $role;
    }
    if ($status !== null && in_array($status, USER_STATUSES, true)) {
        $where[] = 'status = :status';
        $params['status'] = $status;
    }
    if ($search !== null && $search !== '') {
        $where[] = '(full_name LIKE :s1 OR email LIKE :s2 OR contact_number LIKE :s3)';
        $params['s1'] = '%' . $search . '%';
        $params['s2'] = '%' . $search . '%';
        $params['s3'] = '%' . $search . '%';
    }
    return [implode(' AND ', $where), $params];
}

/** All user accounts (any role), optionally filtered and paginated. */
function get_users(PDO $pdo, ?string $role = null, ?string $status = null, ?string $search = null, ?int $limit = null, ?int $offset = null): array
{
    list($where, $params) = build_user_filter($role, $status, $search);
    $sql = "SELECT * FROM users WHERE $where ORDER BY FIELD(role,'admin','officer','member'), full_name";
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

function count_users(PDO $pdo, ?string $role = null, ?string $status = null, ?string $search = null): int
{
    list($where, $params) = build_user_filter($role, $status, $search);
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM users WHERE $where");
    $stmt->execute($params);
    return (int) $stmt->fetch()['cnt'];
}

/** ['admin' => n, 'officer' => n, 'member' => n] */
function count_users_by_role(PDO $pdo): array
{
    $counts = array_fill_keys(USER_ROLES, 0);
    foreach ($pdo->query('SELECT role, COUNT(*) AS cnt FROM users GROUP BY role')->fetchAll() as $row) {
        $counts[$row['role']] = (int) $row['cnt'];
    }
    return $counts;
}

/** Number of ACTIVE admins, optionally not counting one user. */
function count_active_admins(PDO $pdo, int $excludeUserId = 0): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM users WHERE role = 'admin' AND status = 'active' AND user_id <> :id");
    $stmt->execute(['id' => $excludeUserId]);
    return (int) $stmt->fetch()['cnt'];
}

/**
 * Safety rules for changing/deleting an account. Returns an error message,
 * or null if the change is allowed.
 *   - Admins can't delete, demote or deactivate THEMSELVES here (lock-out
 *     protection; their own details are edited via My Profile).
 *   - The last active admin can never be deleted, demoted or deactivated.
 * $newRole / $newStatus are null when not changing (or when deleting).
 */
function user_change_guard_error(PDO $pdo, array $target, int $actingUserId, ?string $newRole, ?string $newStatus, bool $deleting): ?string
{
    $isSelf = (int) $target['user_id'] === $actingUserId;

    if ($isSelf && $deleting) {
        return 'You cannot delete your own account.';
    }
    if ($isSelf && (($newRole !== null && $newRole !== $target['role']) || ($newStatus !== null && $newStatus !== $target['status']))) {
        return 'You cannot change your own role or status.';
    }

    $losesAdminAccess = $target['role'] === 'admin' && $target['status'] === 'active' && (
        $deleting
        || ($newRole !== null && $newRole !== 'admin')
        || ($newStatus !== null && $newStatus !== 'active')
    );
    if ($losesAdminAccess && count_active_admins($pdo, (int) $target['user_id']) === 0) {
        return 'This is the last active admin account, so it cannot be removed, demoted or deactivated.';
    }
    return null;
}

/** Creates an account. Returns the new user_id. Throws PDOException on duplicate email. */
function create_user(PDO $pdo, string $fullName, string $email, string $password, string $role, ?string $contact, string $status): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO users (full_name, email, password_hash, role, contact_number, status)
         VALUES (:full_name, :email, :hash, :role, :contact, :status)'
    );
    $stmt->execute([
        'full_name' => $fullName,
        'email'     => $email,
        'hash'      => password_hash($password, PASSWORD_DEFAULT),
        'role'      => $role,
        'contact'   => $contact ?: null,
        'status'    => $status,
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Updates any account. $newPassword = null/'' leaves the password unchanged.
 * Throws PDOException on duplicate email.
 */
function admin_update_user(PDO $pdo, int $userId, string $fullName, string $email, ?string $contact, string $role, string $status, ?string $newPassword = null): void
{
    $sql = 'UPDATE users SET full_name = :full_name, email = :email, contact_number = :contact,
                             role = :role, status = :status'
         . ($newPassword ? ', password_hash = :hash' : '')
         . ' WHERE user_id = :id';
    $params = [
        'full_name' => $fullName,
        'email'     => $email,
        'contact'   => $contact ?: null,
        'role'      => $role,
        'status'    => $status,
        'id'        => $userId,
    ];
    if ($newPassword) {
        $params['hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    $pdo->prepare($sql)->execute($params);
}

/**
 * Permanently deletes an account. Database cascades also remove that user's
 * farm lots, reservations, ISF bills/payments, login logs and reset tokens;
 * records they merely created/recorded (schedules, machinery, payments)
 * are kept with the author set to NULL.
 */
function delete_user(PDO $pdo, int $userId): void
{
    $pdo->prepare('DELETE FROM users WHERE user_id = :id')->execute(['id' => $userId]);
}
