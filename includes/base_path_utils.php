<?php
/**
 * Base path utilities.
 *
 * Establishes BAMBOSA_BASE_PATH, the app's root URL path as seen by the
 * browser — e.g. '' if served from the domain root, or '/bambosa' if
 * served from a subdirectory like http://localhost/bambosa/ — and provides
 * base_url(), the helper every page uses to build links, form actions,
 * asset URLs, and redirects so the app keeps working no matter what folder
 * it's installed in.
 *
 * This file has no dependencies of its own (safe to include first) and is
 * required by both includes/session.php (needs BAMBOSA_BASE_PATH to scope
 * the session cookie path) and includes/auth.php (needs base_url() for
 * require_login()/redirect_to_dashboard()). Both of those already
 * require_once this file, so any page that includes session.php and/or
 * auth.php gets this automatically — you don't need to add another
 * require_once line to existing or new pages.
 */

if (!defined('BAMBOSA_BASE_PATH')) {
    // SCRIPT_NAME is the URL path to the currently executing script as the
    // browser sees it, e.g. '/officer/dashboard.php' at the domain root, or
    // '/bambosa/officer/dashboard.php' when installed in a subdirectory.
    //
    // Every page in this app lives either directly in the project root
    // (index.php, login.php, ...) or exactly one folder below it (admin/,
    // officer/, member/). So the base path is SCRIPT_NAME with either one or two
    // path segments stripped off the end, depending on folder depth.
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = str_replace('\\', '/', dirname($scriptName)); // e.g. '/bambosa/officer' or '/bambosa' or '/'

    if (preg_match('#/(admin|officer|member)$#', $dir)) {
        // Strip the admin/officer/member segment to land on the project root.
        $basePath = preg_replace('#/(admin|officer|member)$#', '', $dir);
    } else {
        $basePath = $dir;
    }

    // dirname() returns '/' or '\' or '.' for root-level scripts depending
    // on platform; normalize all of those to an empty base path.
    if ($basePath === '/' || $basePath === '\\' || $basePath === '.') {
        $basePath = '';
    }

    define('BAMBOSA_BASE_PATH', rtrim($basePath, '/'));
}

/**
 * Builds an absolute-path URL scoped to wherever this app is installed.
 * Use this instead of hardcoding a leading-slash path like "/login.php",
 * so the app keeps working whether it's served from the domain root
 * (http://host/) or a subdirectory (http://host/bambosa/).
 *
 * Usage: base_url('login.php'), base_url('officer/dashboard.php'),
 * base_url('assets/css/style.css'). Always pass the path WITHOUT a
 * leading slash.
 *
 * Note: if a future page is added two folders deep (this app currently
 * only has pages at the root or one folder deep under admin/,
 * officer/ or member/), the admin|officer|member regex above would need to account for the
 * extra segment.
 */
function base_url(string $path = ''): string
{
    $base = defined('BAMBOSA_BASE_PATH') ? BAMBOSA_BASE_PATH : '';
    $path = ltrim($path, '/');
    return ($path === '') ? ($base !== '' ? $base . '/' : '/') : $base . '/' . $path;
}
