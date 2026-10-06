<?php
/**
 * Authentication / authorization helpers.
 * Requires session.php to already be included.
 *
 * base_url() lives in includes/base_path_utils.php — pulled in here
 * because require_login() and redirect_to_dashboard() below both need it
 * to build their redirect URLs.
 */

require_once __DIR__ . '/base_path_utils.php';

/**
 * Returns true if a user is currently logged in.
 */
function is_logged_in(): bool
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    sync_session_with_db();
    return isset($_SESSION['user_id']);
}

/**
 * Re-checks the logged-in user against the database once per request, so
 * account changes made by an admin take effect immediately instead of only
 * at the user's next login:
 *   - account deleted, or no longer 'active'  -> session is ended
 *   - role changed (e.g. officer -> member)   -> session role is updated
 * Skipped on pages that haven't loaded config/database.php ($pdo unset);
 * those are public pages, and every protected page loads it. Fails open on
 * a database error (logged) rather than logging everyone out.
 */
function sync_session_with_db(): void
{
    static $done = false;
    if ($done || !isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
        return;
    }
    $done = true;

    try {
        $stmt = $GLOBALS['pdo']->prepare('SELECT role, status, full_name FROM users WHERE user_id = :id');
        $stmt->execute(['id' => (int) $_SESSION['user_id']]);
        $row = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Session sync error: ' . $e->getMessage());
        return;
    }

    if (!$row || $row['status'] !== 'active') {
        $_SESSION = [];
        return;
    }

    $_SESSION['role']      = $row['role'];
    $_SESSION['full_name'] = $row['full_name'];
}

/**
 * Returns the logged-in user's role, or null if not logged in.
 */
function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

/**
 * Block access unless the user is logged in.
 * Redirects to login page otherwise.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . base_url('login.php'));
        exit;
    }
}

/**
 * Block access unless the user is logged in AND has the given role.
 * Redirects unauthorized users back to their own dashboard (not login),
 * since they ARE authenticated, just not authorized for this page.
 */
function require_role(string $role): void
{
    require_login();

    if (current_role() !== $role) {
        redirect_to_dashboard();
    }
}

/**
 * Sends a logged-in user to the correct dashboard based on role.
 * Used after login, and as a fallback when someone hits the wrong area.
 */
function redirect_to_dashboard(): void
{
    $role = current_role();

    if ($role === 'admin') {
        header('Location: ' . base_url('admin/dashboard.php'));
    } elseif ($role === 'officer') {
        header('Location: ' . base_url('officer/dashboard.php'));
    } elseif ($role === 'member') {
        header('Location: ' . base_url('member/dashboard.php'));
    } else {
        header('Location: ' . base_url('login.php'));
    }
    exit;
}

/**
 * Basic CSRF token helpers to protect the login/signup forms.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool
{
    return isset($_SESSION['csrf_token']) && $token !== null
        && hash_equals($_SESSION['csrf_token'], $token);
}
