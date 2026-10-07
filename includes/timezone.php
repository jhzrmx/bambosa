<?php
/**
 * Keeps PHP and MySQL on one timezone, driven by config/timezone.php.
 *
 *   - If APP_TIMEZONE is empty / missing / invalid → NOTHING is changed
 *     (PHP and MySQL keep whatever the server gave them).
 *   - If it is a valid identifier → PHP's default timezone and the MySQL
 *     session time_zone are both set to it.
 *
 * apply_php_timezone()   is called from includes/session.php (every page).
 * apply_app_timezone()   is called from config/database.php right after the
 *                        PDO connection is made (PHP + MySQL together).
 * PHP 7.4-compatible.
 */

/**
 * The validated timezone identifier from config/timezone.php, or null when
 * it isn't configured (or isn't a real timezone).
 */
function app_timezone(): ?string
{
    static $resolved = false; // false = not looked up yet; null/string = result
    if ($resolved !== false) {
        return $resolved;
    }
    $resolved = null;

    $configFile = __DIR__ . '/../config/timezone.php';
    if (is_file($configFile)) {
        require_once $configFile;
    }

    $configured = defined('APP_TIMEZONE') ? trim((string) APP_TIMEZONE) : '';
    if ($configured === '') {
        return $resolved; // not configured → leave everything alone
    }
    if (!in_array($configured, DateTimeZone::listIdentifiers(), true)) {
        error_log('Timezone not applied: "' . $configured . '" in config/timezone.php is not a valid PHP timezone identifier.');
        return $resolved;
    }

    $resolved = $configured;
    return $resolved;
}

/** Sets PHP's default timezone, if one is configured. */
function apply_php_timezone(): void
{
    $tz = app_timezone();
    if ($tz !== null) {
        date_default_timezone_set($tz);
    }
}

/**
 * Sets the MySQL session time_zone for this connection, if one is
 * configured. Tries the named zone first; if the server has no timezone
 * tables (typical for a fresh XAMPP/MariaDB), falls back to the matching
 * UTC offset (e.g. '+08:00') computed from PHP's own zone data.
 */
function apply_mysql_timezone(PDO $pdo): void
{
    $tz = app_timezone();
    if ($tz === null) {
        return;
    }

    try {
        $pdo->exec('SET time_zone = ' . $pdo->quote($tz));
        return;
    } catch (PDOException $e) {
        // Named zones need MySQL's tz tables — fall through to the offset form.
    }

    try {
        $offset = (new DateTime('now', new DateTimeZone($tz)))->format('P'); // e.g. +08:00
        $pdo->exec('SET time_zone = ' . $pdo->quote($offset));
    } catch (PDOException $e) {
        error_log('Could not set MySQL time_zone for "' . $tz . '": ' . $e->getMessage());
    }
}

/** PHP + MySQL together (what config/database.php calls after connecting). */
function apply_app_timezone(PDO $pdo): void
{
    apply_php_timezone();
    apply_mysql_timezone($pdo);
}

/**
 * Snapshot for the admin System Clock page (admin/system_clock.php).
 * 'in_sync' compares PHP's local clock with MySQL's NOW() — they only agree
 * when both are on the same timezone.
 */
function get_timezone_status(PDO $pdo): array
{
    $row = $pdo->query('SELECT NOW() AS db_now, @@session.time_zone AS db_tz, @@system_time_zone AS db_system_tz')->fetch();

    $phpNow = date('Y-m-d H:i:s');
    return [
        'configured'   => app_timezone(),                 // null if not configured
        'php_timezone' => date_default_timezone_get(),
        'php_now'      => $phpNow,
        'db_timezone'  => $row['db_tz'] === 'SYSTEM' ? 'SYSTEM (' . $row['db_system_tz'] . ')' : $row['db_tz'],
        'db_now'       => $row['db_now'],
        // Allow a few seconds for the two reads happening at slightly different moments.
        'in_sync'      => abs(strtotime($row['db_now']) - strtotime($phpNow)) <= 5,
    ];
}
