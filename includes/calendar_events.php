<?php
/**
 * Dashboard calendar data access.
 *
 * Pulls together rotation schedules (water release windows) and machinery
 * reservations into a single list of "events" for a given month, so the
 * dashboard calendar widget has one simple shape to render regardless of
 * which table an event actually came from.
 *
 * Each event is an associative array:
 *   [
 *     'date'  => 'YYYY-MM-DD',              // the calendar day this event is shown on
 *     'type'  => 'schedule' | 'reservation', // which system it came from
 *     'title' => string,                     // short label for the day cell / list
 *     'detail'=> string,                     // longer description for the day popover
 *     'time'  => string|null,                // human-readable time range, or null for all-day
 *     'status'=> string|null,                // reservation status, or null for schedules
 *   ]
 *
 * A multi-day event (a reservation spanning several days, or a schedule
 * window that crosses midnight) gets one row per calendar day it touches,
 * so it shows up correctly on every day of its span.
 *
 * Requires $pdo from config/database.php to already be loaded.
 */

/**
 * Rotation schedule windows that overlap the given month, optionally
 * restricted to a set of sector IDs (used to show a member only their own
 * sector's releases). Pass null for $sectorIds to get every sector
 * (used for officers).
 */
function get_schedule_events_for_month(PDO $pdo, int $year, int $month, ?array $sectorIds = null): array
{
    $monthStart = sprintf('%04d-%02d-01', $year, $month);
    $monthEnd = date('Y-m-d', strtotime($monthStart . ' +1 month -1 day'));

    $sql = "SELECT rs.schedule_id, rs.start_time, rs.end_time, rs.notes, s.sector_name
            FROM rotation_schedules rs
            JOIN sectors s ON s.sector_id = rs.sector_id
            WHERE DATE(rs.start_time) <= :month_end AND DATE(rs.end_time) >= :month_start";
    $params = [
        'month_start' => $monthStart,
        'month_end' => $monthEnd,
    ];

    if ($sectorIds !== null) {
        if (empty($sectorIds)) {
            return []; // member has no sectors assigned yet — nothing to show
        }
        $placeholders = [];
        foreach ($sectorIds as $i => $sid) {
            $key = 'sector' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $sid;
        }
        $sql .= ' AND rs.sector_id IN (' . implode(',', $placeholders) . ')';
    }

    $sql .= ' ORDER BY rs.start_time ASC';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $events = [];
    foreach ($rows as $row) {
        $events = array_merge($events, expand_event_across_days(
            $row['start_time'],
            $row['end_time'],
            $monthStart,
            $monthEnd,
            [
                'type'   => 'schedule',
                'title'  => 'Water release: ' . $row['sector_name'],
                'detail' => $row['sector_name'] . ' irrigation window'
                    . ($row['notes'] ? ' — ' . $row['notes'] : ''),
                'status' => null,
            ]
        ));
    }
    return $events;
}

/**
 * Machinery reservations that overlap the given month. Only pending and
 * approved reservations are shown (rejected/cancelled ones are noise on a
 * "what's happening" calendar). Optionally restricted to one member's own
 * reservations (used on the member dashboard); pass null for $userId to
 * get everyone's (used for officers).
 */
function get_reservation_events_for_month(PDO $pdo, int $year, int $month, ?int $userId = null): array
{
    $monthStart = sprintf('%04d-%02d-01', $year, $month);
    $monthEnd = date('Y-m-d', strtotime($monthStart . ' +1 month -1 day'));

    $sql = "SELECT r.reservation_id, r.start_date, r.end_date, r.purpose, r.status,
                   m.name AS machinery_name, u.full_name AS requester_name
            FROM machinery_reservations r
            JOIN machinery m ON m.machinery_id = r.machinery_id
            JOIN users u ON u.user_id = r.user_id
            WHERE r.status IN ('pending', 'approved')
              AND r.start_date <= :month_end AND r.end_date >= :month_start";
    $params = [
        'month_start' => $monthStart,
        'month_end' => $monthEnd,
    ];

    if ($userId !== null) {
        $sql .= ' AND r.user_id = :user_id';
        $params['user_id'] = $userId;
    }

    $sql .= ' ORDER BY r.start_date ASC';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $events = [];
    foreach ($rows as $row) {
        $label = $row['machinery_name'] . ($userId === null ? ' — ' . $row['requester_name'] : '');
        $events = array_merge($events, expand_event_across_days(
            $row['start_date'],
            $row['end_date'],
            $monthStart,
            $monthEnd,
            [
                'type'   => 'reservation',
                'title'  => $label,
                'detail' => $row['machinery_name']
                    . ($userId === null ? ' reserved by ' . $row['requester_name'] : ' reservation')
                    . ($row['purpose'] ? ' — ' . $row['purpose'] : '')
                    . ' (' . $row['status'] . ')',
                'status' => $row['status'],
            ]
        ));
    }
    return $events;
}

/**
 * Combines schedule + reservation events for a month into the shape the
 * calendar widget expects, keyed by day-of-month for quick lookup while
 * rendering the grid.
 *
 * $sectorIds: pass an array to restrict schedules to those sectors (member
 * view), or null for all sectors (officer view).
 * $userId: pass a user ID to restrict reservations to that member's own
 * requests (member view), or null for everyone's (officer view).
 *
 * Returns ['events_by_day' => [1 => [...events], 2 => [...], ...], 'events' => [...flat list]]
 */
function get_calendar_events_for_month(PDO $pdo, int $year, int $month, ?array $sectorIds, ?int $userId): array
{
    $scheduleEvents = get_schedule_events_for_month($pdo, $year, $month, $sectorIds);
    $reservationEvents = get_reservation_events_for_month($pdo, $year, $month, $userId);
    $allEvents = array_merge($scheduleEvents, $reservationEvents);

    // Sort combined list by date so a day's events render in date order.
    usort($allEvents, fn($a, $b) => strcmp($a['date'], $b['date']));

    $byDay = [];
    foreach ($allEvents as $event) {
        $day = (int) date('j', strtotime($event['date']));
        $byDay[$day][] = $event;
    }

    return [
        'events_by_day' => $byDay,
        'events' => $allEvents,
    ];
}

/**
 * Splits a start/end range into one event entry per calendar day it
 * touches, clipped to the given month bounds, so a multi-day reservation
 * or schedule window shows up on every day it spans rather than just its
 * start day.
 *
 * $meta: the event fields to copy onto every generated day (type, title,
 * detail, status) — 'date' and 'time' are filled in per-day by this
 * function.
 */
function expand_event_across_days(string $start, string $end, string $monthStart, string $monthEnd, array $meta): array
{
    $startDay = date('Y-m-d', strtotime($start));
    $endDay = date('Y-m-d', strtotime($end));

    // Clip to the visible month so we don't generate rows for days outside it.
    $rangeStart = max($startDay, $monthStart);
    $rangeEnd = min($endDay, $monthEnd);

    $isTimed = strpos($start, ':') !== false || strpos($end, ':') !== false;

    $events = [];
    $cursor = $rangeStart;
    while ($cursor <= $rangeEnd) {
        $time = null;
        if ($isTimed) {
            if ($startDay === $endDay) {
                $time = date('g:i A', strtotime($start)) . ' – ' . date('g:i A', strtotime($end));
            } elseif ($cursor === $startDay) {
                $time = 'From ' . date('g:i A', strtotime($start));
            } elseif ($cursor === $endDay) {
                $time = 'Until ' . date('g:i A', strtotime($end));
            } else {
                $time = 'All day (continued)';
            }
        }

        $events[] = array_merge($meta, [
            'date' => $cursor,
            'time' => $time,
        ]);

        $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
    }
    return $events;
}
