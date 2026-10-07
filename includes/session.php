<?php
/**
 * Session bootstrap.
 * Include this at the very top of EVERY page (before any HTML output).
 *
 * Base-path detection (BAMBOSA_BASE_PATH) and the base_url() helper live
 * in includes/base_path_utils.php — pulled in here because the session
 * cookie path needs to be scoped to the app's base path rather than
 * always '/'. See that file for the full explanation.
 */

require_once __DIR__ . '/base_path_utils.php';

// Apply the configured application timezone to PHP (no-op until
// config/timezone.php is configured). The MySQL half is applied in
// config/database.php once a connection exists.
require_once __DIR__ . '/timezone.php';
apply_php_timezone();

if (session_status() === PHP_SESSION_NONE) {
    // Harden session cookie settings. Cookie path is scoped to the app's
    // own base path (e.g. '/bambosa') rather than always '/', so the
    // session behaves correctly whether the app lives at the domain root
    // or in a subdirectory.
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BAMBOSA_BASE_PATH !== '' ? BAMBOSA_BASE_PATH : '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // 'secure' => true, // enable when served over HTTPS
    ]);
    session_start();
}
