<?php
/**
 * Router for PHP's built-in web server (used by serve.bat). The built-in
 * server ignores .htaccess, so without this it would show its own plain
 * "Not Found" text and happily serve files that Apache's
 * "Require all denied" rules protect (config/, includes/, sql/ ...).
 *
 * This script gives the built-in server the same behaviour as Apache here:
 *   - missing files        -> error/404.html (status 404)
 *   - malformed paths      -> error/400.html (status 400)
 *   - .htaccess/.htpasswd files and paths inside a folder whose .htaccess says "Require all denied"
 *                          -> error/403.html (status 403)
 *   - everything else      -> served normally (return false)
 *
 * Under Apache this file is never used as a router; if someone requests it
 * directly it just answers with the 404 page.
 */

function bambosa_send_error_page($code)
{
    http_response_code($code);
    header('Content-Type: text/html; charset=UTF-8');
    readfile(__DIR__ . '/' . $code . '.html');
    exit;
}

if (PHP_SAPI !== 'cli-server') {
    bambosa_send_error_page(404);
}

$root    = realpath(__DIR__ . '/..');
$uriPath = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uriPath === '' || strpos($uriPath, "\0") !== false || strpos($uriPath, '..') !== false) {
    bambosa_send_error_page(400);
}

$target = realpath($root . $uriPath);
if ($target === false || strpos($target, $root) !== 0) {
    bambosa_send_error_page(404);
}

// Apache never serves .htaccess / .htpasswd files; neither should we.
if (strpos(basename($target), '.ht') === 0) {
    bambosa_send_error_page(403);
}

// Emulate Apache's per-folder "Require all denied" (e.g. config/.htaccess).
$dir = is_dir($target) ? $target : dirname($target);
while (strpos($dir, $root) === 0) {
    $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (is_file($htaccess) && preg_match('/^\s*Require\s+all\s+denied/mi', (string) file_get_contents($htaccess))) {
        bambosa_send_error_page(403);
    }
    if ($dir === $root) {
        break;
    }
    $dir = dirname($dir);
}

return false; // let the built-in server serve the file / run the script as usual
