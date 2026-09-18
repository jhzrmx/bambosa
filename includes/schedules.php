<?php
/**
 * Digital Rotation Scheduling data access functions.
 * Requires $pdo from config/database.php to already be loaded.
 */

/**
 * All schedules, joined with sector name, soonest first. Optionally only
 * upcoming ones, and/or a search term (matched against sector name and
 * notes), optionally paginated.
 *
 * $limit/$offset: optional pagination window (both required together, or
 * omit both to get every matching row).
 */
function get_all_schedules(PDO $pdo, bool $upcomingOnly = false, ?string $search = null, ?int $limit = null, ?int $offset = null): array
{
    $sql = 'SELECT rs.*, s.sector_name
            FROM rotation_schedules rs
            JOIN sectors s ON s.sector_id = rs.sector_id
            WHERE 1=1';
    $params = [];

    if ($upcomingOnly) {
        $sql .= ' AND rs.end_time >= NOW()';
    }

    if ($search) {
        $sql .= ' AND (s.sector_name LIKE :search1 OR rs.notes LIKE :search2)';
        $params['search1'] = '%' . $search . '%';
        $params['search2'] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY rs.start_time ASC';

    if ($limit !== null && $offset !== null) {
        $sql .= ' LIMIT :limit OFFSET :offset';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    if ($limit !== null && $offset !== null) {
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Count of schedules matching an optional upcoming/search filter (for pagination totals). */
function count_schedules(PDO $pdo, bool $upcomingOnly = false, ?string $search = null): int
{
    $sql = 'SELECT COUNT(*) AS cnt
            FROM rotation_schedules rs
            JOIN sectors s ON s.sector_id = rs.sector_id
            WHERE 1=1';
    $params = [];

    if ($upcomingOnly) {
        $sql .= ' AND rs.end_time >= NOW()';
    }

    if ($search) {
        $sql .= ' AND (s.sector_name LIKE :search1 OR rs.notes LIKE :search2)';
        $params['search1'] = '%' . $search . '%';
        $params['search2'] = '%' . $search . '%';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();
    return (int) $stmt->fetch()['cnt'];
}

/** Schedules for one specific sector (e.g. to show a member only their own sector's schedule). */
function get_schedules_by_sector(PDO $pdo, int $sectorId, bool $upcomingOnly = true): array
{
    $sql = 'SELECT rs.*, s.sector_name
            FROM rotation_schedules rs
            JOIN sectors s ON s.sector_id = rs.sector_id
            WHERE rs.sector_id = :sector_id';
    if ($upcomingOnly) {
        $sql .= ' AND rs.end_time >= NOW()';
    }
    $sql .= ' ORDER BY rs.start_time ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['sector_id' => $sectorId]);
    return $stmt->fetchAll();
}

/** Distinct sector IDs a given member has farm lots in (used to filter their schedule view). */
function get_member_sector_ids(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT DISTINCT sector_id FROM farm_lots WHERE user_id = :uid AND sector_id IS NOT NULL'
    );
    $stmt->execute(['uid' => $userId]);
    return array_column($stmt->fetchAll(), 'sector_id');
}

function get_schedule_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM rotation_schedules WHERE schedule_id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_schedule(PDO $pdo, int $sectorId, string $startTime, string $endTime, ?string $notes, ?int $createdBy): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO rotation_schedules (sector_id, start_time, end_time, notes, created_by)
         VALUES (:sector_id, :start_time, :end_time, :notes, :created_by)'
    );
    $stmt->execute([
        'sector_id'  => $sectorId,
        'start_time' => $startTime,
        'end_time'   => $endTime,
        'notes'      => $notes ?: null,
        'created_by' => $createdBy,
    ]);
    return (int) $pdo->lastInsertId();
}

function update_schedule(PDO $pdo, int $id, int $sectorId, string $startTime, string $endTime, ?string $notes): void
{
    $stmt = $pdo->prepare(
        'UPDATE rotation_schedules SET sector_id = :sector_id, start_time = :start_time,
            end_time = :end_time, notes = :notes
         WHERE schedule_id = :id'
    );
    $stmt->execute([
        'sector_id'  => $sectorId,
        'start_time' => $startTime,
        'end_time'   => $endTime,
        'notes'      => $notes ?: null,
        'id'         => $id,
    ]);
}

function delete_schedule(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM rotation_schedules WHERE schedule_id = :id');
    $stmt->execute(['id' => $id]);
}

/**
 * Checks whether a sector already has a rotation schedule entry
 * overlapping the given time window. Used to warn an officer at
 * create/edit time — same "flag, don't block" philosophy as
 * has_overlapping_reservation() in includes/machinery.php, since two
 * overlapping release windows for the same sector may be intentional
 * (e.g. a correction/extension) and the officer makes the final call.
 */
function has_overlapping_schedule(PDO $pdo, int $sectorId, string $startTime, string $endTime, ?int $excludeScheduleId = null): bool
{
    $sql = 'SELECT COUNT(*) AS cnt
            FROM rotation_schedules
            WHERE sector_id = :sector_id
              AND start_time <= :end_time
              AND end_time >= :start_time';
    $params = [
        'sector_id'  => $sectorId,
        'start_time' => $startTime,
        'end_time'   => $endTime,
    ];

    if ($excludeScheduleId) {
        $sql .= ' AND schedule_id != :exclude_id';
        $params['exclude_id'] = $excludeScheduleId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetch()['cnt'] > 0;
}
