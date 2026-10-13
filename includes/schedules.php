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

/**
 * Sector IDs of every OTHER schedule row sharing the same group_id as the
 * given schedule (i.e. sibling entries created together via "also create
 * this schedule for other sectors"). Returns an empty array if the
 * schedule has no group_id (it was created standalone) or is the only
 * member of its group. Used to pre-check the right sectors when editing a
 * schedule that's part of a multi-sector batch.
 */
function get_sibling_schedule_sector_ids(PDO $pdo, int $scheduleId): array
{
    $schedule = get_schedule_by_id($pdo, $scheduleId);
    if (!$schedule || $schedule['group_id'] === null) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT sector_id FROM rotation_schedules WHERE group_id = :group_id AND schedule_id != :id'
    );
    $stmt->execute(['group_id' => $schedule['group_id'], 'id' => $scheduleId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'sector_id'));
}

/**
 * Creates one schedule row per sector in $sectorIds, all sharing the same
 * start/end/notes and a common group_id — this is what powers "also
 * create this same schedule for other sectors" on the create form.
 *
 * $sectorIds must have at least one sector. When there's only one, this
 * behaves the same as a plain create_schedule() call except it still sets
 * a group_id — which is harmless (a "group" of one just means editing it
 * later finds no siblings, same as a schedule that was never grouped).
 *
 * Returns the list of newly-created schedule_ids, in the same order as
 * $sectorIds.
 */
function create_schedule_group(PDO $pdo, array $sectorIds, string $startTime, string $endTime, ?string $notes, ?int $createdBy): array
{
    $sectorIds = array_values(array_unique(array_map('intval', $sectorIds)));
    if (empty($sectorIds)) {
        throw new InvalidArgumentException('At least one sector is required.');
    }

    // A single shared tag for this batch. MAX(group_id)+1 is simple and
    // sufficient here — group_id has no uniqueness constraint of its own
    // and only needs to be distinct enough to tell batches apart, not
    // globally unique like a UUID would be.
    $groupId = (int) $pdo->query('SELECT COALESCE(MAX(group_id), 0) + 1 AS next_id FROM rotation_schedules')->fetch()['next_id'];

    $ids = [];
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO rotation_schedules (sector_id, start_time, end_time, notes, group_id, created_by)
             VALUES (:sector_id, :start_time, :end_time, :notes, :group_id, :created_by)'
        );
        foreach ($sectorIds as $sectorId) {
            $stmt->execute([
                'sector_id'  => $sectorId,
                'start_time' => $startTime,
                'end_time'   => $endTime,
                'notes'      => $notes ?: null,
                'group_id'   => $groupId,
                'created_by' => $createdBy,
            ]);
            $ids[] = (int) $pdo->lastInsertId();
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    return $ids;
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

/**
 * Updates a schedule AND keeps its sector group in sync with
 * $wantedSectorIds (the full set of sectors that should share this
 * schedule after saving — including the "primary" one shown in the form).
 *
 * How the group is reconciled:
 * - Sectors already in the group that are still wanted: their row's
 *   start/end/notes are updated in place (schedule_id stays the same).
 * - Sectors newly added to $wantedSectorIds: a new row is created for
 *   each, joining the same group_id.
 * - Sectors that were in the group but are no longer in
 *   $wantedSectorIds: their row is UNGROUPED, not deleted — its group_id
 *   is cleared back to NULL and the row is otherwise left exactly as it
 *   was (last-synced start/end/notes). Unchecking a sector means "this
 *   schedule is no longer shared with that sector," not "delete that
 *   sector's schedule outright" — the officer can still edit or delete it
 *   directly afterwards, as a normal standalone entry, if that's what
 *   they actually meant to do.
 * - If $wantedSectorIds ends up being just the one original sector (no
 *   group), the schedule's group_id is cleared back to NULL so it goes
 *   back to being a standalone entry.
 *
 * $primarySectorId is which sector's row is $id itself (needed so we
 * know which existing row to treat as "the one already open in the
 * form" versus siblings).
 */
function update_schedule_group(PDO $pdo, int $id, int $primarySectorId, array $wantedSectorIds, string $startTime, string $endTime, ?string $notes, ?int $updatedBy): void
{
    $wantedSectorIds = array_values(array_unique(array_map('intval', $wantedSectorIds)));
    if (!in_array($primarySectorId, $wantedSectorIds, true)) {
        $wantedSectorIds[] = $primarySectorId;
    }

    $schedule = get_schedule_by_id($pdo, $id);
    if (!$schedule) {
        throw new InvalidArgumentException('Schedule not found.');
    }

    // Every row currently sharing this schedule's group (or just itself,
    // if it has no group yet) — this is the "before" picture we're
    // reconciling against $wantedSectorIds.
    $existingRows = $schedule['group_id'] !== null
        ? (function () use ($pdo, $schedule) {
            $stmt = $pdo->prepare('SELECT schedule_id, sector_id FROM rotation_schedules WHERE group_id = :gid');
            $stmt->execute(['gid' => $schedule['group_id']]);
            return $stmt->fetchAll();
        })()
        : [['schedule_id' => $id, 'sector_id' => $primarySectorId]];

    $existingBySector = [];
    foreach ($existingRows as $row) {
        $existingBySector[(int) $row['sector_id']] = (int) $row['schedule_id'];
    }

    $pdo->beginTransaction();
    try {
        // Decide the group_id every surviving/new row should share.
        if (count($wantedSectorIds) <= 1) {
            $groupId = null; // back to a standalone schedule
        } elseif ($schedule['group_id'] !== null) {
            $groupId = (int) $schedule['group_id']; // keep the existing tag
        } else {
            $groupId = (int) $pdo->query('SELECT COALESCE(MAX(group_id), 0) + 1 AS next_id FROM rotation_schedules')->fetch()['next_id'];
        }

        // Update or create a row for every wanted sector.
        $updateStmt = $pdo->prepare(
            'UPDATE rotation_schedules
             SET start_time = :start_time, end_time = :end_time, notes = :notes, group_id = :group_id
             WHERE schedule_id = :id'
        );
        $insertStmt = $pdo->prepare(
            'INSERT INTO rotation_schedules (sector_id, start_time, end_time, notes, group_id, created_by)
             VALUES (:sector_id, :start_time, :end_time, :notes, :group_id, :created_by)'
        );

        foreach ($wantedSectorIds as $sectorId) {
            $params = [
                'start_time' => $startTime,
                'end_time'   => $endTime,
                'notes'      => $notes ?: null,
                'group_id'   => $groupId,
            ];
            if (isset($existingBySector[$sectorId])) {
                $updateStmt->execute($params + ['id' => $existingBySector[$sectorId]]);
            } else {
                $insertStmt->execute([
                    'sector_id'  => $sectorId,
                    'start_time' => $startTime,
                    'end_time'   => $endTime,
                    'notes'      => $notes ?: null,
                    'group_id'   => $groupId,
                    'created_by' => $updatedBy,
                ]);
            }
        }

        // Ungroup (not delete) rows for sectors that were in the group but
        // are no longer wanted — unchecking a sector means "this schedule
        // is no longer shared with that sector," not "delete that sector's
        // schedule entirely." The row is left exactly as it last was
        // (whatever start/end/notes it had before this edit) and simply
        // goes back to being a standalone entry, same as any schedule that
        // was never part of a group. The officer can still edit or delete
        // it directly afterwards if that's what they actually wanted.
        $ungroupStmt = $pdo->prepare('UPDATE rotation_schedules SET group_id = NULL WHERE schedule_id = :id');
        foreach ($existingBySector as $sectorId => $scheduleId) {
            if (!in_array($sectorId, $wantedSectorIds, true)) {
                $ungroupStmt->execute(['id' => $scheduleId]);
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
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

/**
 * Where a schedule stands relative to RIGHT NOW, comparing the full
 * date and time:
 *   'finished'  — its end date/time has already passed
 *   'upcoming'  — its start date/time is still ahead
 *   'ongoing'   — now falls within start..end (inclusive)
 * "Now" is read in the application timezone (see config/timezone.php), the
 * same clock MySQL's DATETIME values are interpreted in once it is set.
 * $now is injectable ('Y-m-d H:i:s') for testing; defaults to the current time.
 */
function get_schedule_time_status(string $startTime, string $endTime, ?string $now = null): string
{
    $now   = $now ?? date('Y-m-d H:i:s');
    $start = date('Y-m-d H:i:s', strtotime($startTime));
    $end   = date('Y-m-d H:i:s', strtotime($endTime));

    if ($end < $now) {
        return 'finished';
    }
    if ($start > $now) {
        return 'upcoming';
    }
    return 'ongoing';
}
