<?php
/**
 * JSON endpoint backing the dashboard calendar widget's month navigation.
 *
 * Lives at the project ROOT (not in includes/) on purpose:
 * includes/.htaccess has "Require all denied", blocking all direct HTTP
 * access to that folder (it's meant for library files only, loaded via
 * require_once, never hit directly). An AJAX endpoint fetched by the
 * browser needs to be somewhere publicly reachable instead — same level
 * as index.php, login.php, etc.
 *
 * Called via fetch() from assets/js/dashboard-calendar.js whenever the
 * user clicks prev/next month, so switching months doesn't require a full
 * page reload. The very first month shown on page load is instead
 * rendered server-side and embedded inline (see dashboard.php), so the
 * calendar has content immediately even if JS is slow to kick in.
 *
 * Accessible to logged-in officers and members (admins get a 403) — results are
 * scoped to what that role/user is allowed to see, same as the dashboards
 * themselves.
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/schedules.php';
require_once __DIR__ . '/includes/calendar_events.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$year = (int) ($_GET['year'] ?? date('Y'));
$month = (int) ($_GET['month'] ?? date('n'));

// Keep the requested month within a sane range so a bad/forged request
// can't be used to run expensive queries far outside any real use case.
if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid year or month']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role = current_role();

// The calendar exists on the officer and member dashboards only (admins have
// no schedules/reservations of their own to show).
if ($role !== 'officer' && $role !== 'member') {
    http_response_code(403);
    echo json_encode(['error' => 'Not available for this account type']);
    exit;
}

if ($role === 'officer') {
    $sectorIds = null; // officers see every sector's schedule
    $reservationUserId = null; // and everyone's reservations
} else {
    $sectorIds = get_member_sector_ids($pdo, $userId);
    $reservationUserId = $userId;
}

$result = get_calendar_events_for_month($pdo, $year, $month, $sectorIds, $reservationUserId);

echo json_encode([
    'year' => $year,
    'month' => $month,
    'events_by_day' => $result['events_by_day'],
]);
