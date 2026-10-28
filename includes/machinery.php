<?php
/**
 * Machinery Reservation System data access functions.
 * Requires $pdo from config/database.php to already be loaded.
 *
 * MACHINERY GROUPS — why this exists:
 * Before this feature, a member reserved one SPECIFIC machine. If an
 * association owned three interchangeable hand tractors and a member
 * happened to pick the one that was already booked, the officer's only
 * option was to reject the whole request — even though a sibling tractor
 * sat free the entire time — forcing the member to resubmit and guess
 * again. Machinery groups fix this: a member now reserves a GROUP (e.g.
 * "Hand Tractors"), and an officer assigns whichever specific unit is
 * actually free at approval time.
 *
 * A machine's group_id is nullable — a machine with no group behaves
 * exactly as before this feature existed (direct reservation, direct
 * approval, no group-related steps at all). Grouping is opt-in per
 * machine, not a requirement.
 *
 * A reservation therefore targets EITHER a group OR a specific machine,
 * tracked by machinery_reservations.group_id / .machinery_id:
 * - Standalone machine (no group): machinery_id is set at request time,
 *   group_id stays NULL. Approval just confirms that same unit.
 * - Grouped machine: group_id is set at request time, machinery_id
 *   starts NULL. Approval is a two-step flow — see
 *   officer/reservation_assign.php — where the officer picks which
 *   specific unit in the group to actually hand over, which is when
 *   machinery_id finally gets filled in.
 */

require_once __DIR__ . '/db_helpers.php';

/**
 * Maps a 0-100 damage level to a CSS severity class for the condition bar
 * (green/amber/red), so the visual matches the same thresholds everywhere
 * the damage slider is displayed rather than each page picking its own
 * cutoffs. Thresholds: 0-33 low (green), 34-66 moderate (amber), 67-100
 * severe (red).
 */
function damage_severity_class(int $damageLevel): string
{
    if ($damageLevel >= 67) {
        return 'severe';
    }
    if ($damageLevel >= 34) {
        return 'moderate';
    }
    return 'low';
}

/**
 * All machinery groups, optionally with a count of member-visible
 * available units in each (used to decide which groups to show on the
 * member reservation form — see get_available_machinery_groups()).
 */
function get_all_machinery_groups(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM machinery_groups ORDER BY group_name')->fetchAll();
}

function get_machinery_group_by_id(PDO $pdo, int $groupId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM machinery_groups WHERE group_id = :id');
    $stmt->execute(['id' => $groupId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_machinery_group(PDO $pdo, string $name, ?string $description): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO machinery_groups (group_name, description) VALUES (:name, :description)'
    );
    $stmt->execute(['name' => $name, 'description' => $description ?: null]);
    return (int) $pdo->lastInsertId();
}

function update_machinery_group(PDO $pdo, int $groupId, string $name, ?string $description): void
{
    $stmt = $pdo->prepare(
        'UPDATE machinery_groups SET group_name = :name, description = :description WHERE group_id = :id'
    );
    $stmt->execute(['name' => $name, 'description' => $description ?: null, 'id' => $groupId]);
}

/**
 * Deletes a machinery group. Machines that belonged to it are NOT
 * deleted — the FK is ON DELETE SET NULL, so they simply become
 * standalone machines again (same as if an officer had manually cleared
 * their group assignment). This matches the principle used elsewhere in
 * this codebase (e.g. clearing a sector from a farm lot) of never
 * cascading a delete into unrelated records the officer didn't
 * explicitly ask to remove.
 */
function delete_machinery_group(PDO $pdo, int $groupId): void
{
    $stmt = $pdo->prepare('DELETE FROM machinery_groups WHERE group_id = :id');
    $stmt->execute(['id' => $groupId]);
}

/** How many machines currently belong to a group (shown on the groups list so an officer knows what deleting one affects). */
function count_machinery_in_group(PDO $pdo, int $groupId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM machinery WHERE group_id = :id');
    $stmt->execute(['id' => $groupId]);
    return (int) $stmt->fetch()['cnt'];
}

/** How many machines in a group are currently marked available (is_available = 1). */
function count_available_machinery_in_group(PDO $pdo, int $groupId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM machinery WHERE group_id = :id AND is_available = 1');
    $stmt->execute(['id' => $groupId]);
    return (int) $stmt->fetch()['cnt'];
}

/**
 * All machinery groups that currently have at least one available
 * (is_available = 1) unit — used to populate the member reservation
 * form's group dropdown. A group with zero available units (all broken
 * down / marked unavailable) is hidden entirely, per the client's
 * request, rather than shown with nothing bookable in it. This is a
 * baseline filter only — it does NOT check date-range conflicts, since
 * the member hasn't picked dates yet at the point this list is built;
 * the real per-date stock check happens at submission
 * (get_available_machinery_in_group()) and again at approval time.
 */
function get_available_machinery_groups(PDO $pdo): array
{
    return $pdo->query(
        "SELECT g.*, COUNT(m.machinery_id) AS available_count
         FROM machinery_groups g
         JOIN machinery m ON m.group_id = g.group_id AND m.is_available = 1
         GROUP BY g.group_id
         ORDER BY g.group_name"
    )->fetchAll();
}

/**
 * All machinery, optionally filtered to only available ones and/or by a
 * search term (matched against name/description), optionally paginated.
 * Includes the machine's group name (if any) joined in.
 *
 * $limit/$offset: optional pagination window (both required together, or
 * omit both to get every matching row).
 */
function get_all_machinery(PDO $pdo, bool $onlyAvailable = false, ?string $search = null, ?int $limit = null, ?int $offset = null): array
{
    list($where, $params) = build_machinery_filter($onlyAvailable, $search);
    $sql = "SELECT m.*, g.group_name
            FROM machinery m
            LEFT JOIN machinery_groups g ON g.group_id = m.group_id
            WHERE $where
            ORDER BY m.name";
    return db_fetch_all($pdo, $sql, $params, $limit, $offset);
}

/** Count of machinery matching an optional availability/search filter (for pagination totals). */
function count_machinery(PDO $pdo, bool $onlyAvailable = false, ?string $search = null): int
{
    list($where, $params) = build_machinery_filter($onlyAvailable, $search);
    return db_count(
        $pdo,
        "SELECT COUNT(*) AS cnt
         FROM machinery m
         LEFT JOIN machinery_groups g ON g.group_id = m.group_id
         WHERE $where",
        $params
    );
}

/** Shared WHERE clause + params for get_all_machinery() / count_machinery(). */
function build_machinery_filter(bool $onlyAvailable, ?string $search): array
{
    $where  = ['1=1'];
    $params = [];

    if ($onlyAvailable) {
        $where[] = 'm.is_available = 1';
    }

    list($searchSql, $searchParams) = db_like_clause(['m.name', 'm.description', 'g.group_name'], $search);
    if ($searchSql !== '') {
        $where[]  = $searchSql;
        $params  += $searchParams;
    }

    return [implode(' AND ', $where), $params];
}

function get_machinery_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT m.*, g.group_name
         FROM machinery m
         LEFT JOIN machinery_groups g ON g.group_id = m.group_id
         WHERE m.machinery_id = :id'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_machinery(PDO $pdo, string $name, ?string $description, ?int $groupId = null, int $damageLevel = 0): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO machinery (name, description, group_id, damage_level) VALUES (:name, :description, :group_id, :damage_level)'
    );
    $stmt->execute([
        'name'         => $name,
        'description'  => $description ?: null,
        'group_id'     => $groupId,
        'damage_level' => $damageLevel,
    ]);
}

function update_machinery(PDO $pdo, int $id, string $name, ?string $description, bool $isAvailable, ?int $groupId = null, ?int $damageLevel = null): void
{
    // $damageLevel is optional here (not just nullable) so callers that
    // only ever touch name/description/availability/group — if any exist
    // — aren't forced to also know/re-supply the current damage value.
    // officer/machinery.php always passes it explicitly, since it's a
    // field right there on the same edit form.
    if ($damageLevel !== null) {
        $stmt = $pdo->prepare(
            'UPDATE machinery SET name = :name, description = :description, is_available = :avail,
                group_id = :group_id, damage_level = :damage_level
             WHERE machinery_id = :id'
        );
        $stmt->execute([
            'name'         => $name,
            'description'  => $description ?: null,
            'avail'        => $isAvailable ? 1 : 0,
            'group_id'     => $groupId,
            'damage_level' => $damageLevel,
            'id'           => $id,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'UPDATE machinery SET name = :name, description = :description, is_available = :avail, group_id = :group_id
             WHERE machinery_id = :id'
        );
        $stmt->execute([
            'name'        => $name,
            'description' => $description ?: null,
            'avail'       => $isAvailable ? 1 : 0,
            'group_id'    => $groupId,
            'id'          => $id,
        ]);
    }
}

function delete_machinery(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM machinery WHERE machinery_id = :id');
    $stmt->execute(['id' => $id]);
}

/**
 * All reservations, newest first, joined with machinery name (or group
 * name, for a still-unassigned group request) and requester name.
 * Optional status filter and/or search term (matched against machinery
 * name, group name, requester name, and purpose), optionally paginated.
 *
 * $limit/$offset: optional pagination window (both required together, or
 * omit both to get every matching row).
 */
function get_all_reservations(PDO $pdo, ?string $status = null, ?string $search = null, ?int $limit = null, ?int $offset = null): array
{
    list($where, $params) = build_reservation_filter($status, $search);
    $sql = "SELECT r.*, m.name AS machinery_name, g.group_name, u.full_name AS requester_name
            FROM machinery_reservations r
            LEFT JOIN machinery m ON m.machinery_id = r.machinery_id
            LEFT JOIN machinery_groups g ON g.group_id = r.group_id
            JOIN users u ON u.user_id = r.user_id
            WHERE $where
            ORDER BY r.start_date DESC, r.created_at DESC";
    return db_fetch_all($pdo, $sql, $params, $limit, $offset);
}

/** Count of reservations matching an optional status/search filter (for pagination totals). */
function count_reservations(PDO $pdo, ?string $status = null, ?string $search = null): int
{
    list($where, $params) = build_reservation_filter($status, $search);
    return db_count(
        $pdo,
        "SELECT COUNT(*) AS cnt
         FROM machinery_reservations r
         LEFT JOIN machinery m ON m.machinery_id = r.machinery_id
         LEFT JOIN machinery_groups g ON g.group_id = r.group_id
         JOIN users u ON u.user_id = r.user_id
         WHERE $where",
        $params
    );
}

/** Shared WHERE clause + params for get_all_reservations() / count_reservations(). */
function build_reservation_filter(?string $status, ?string $search): array
{
    $where  = ['1=1'];
    $params = [];

    if ($status !== null && $status !== '') {
        $where[] = 'r.status = :status';
        $params['status'] = $status;
    }

    list($searchSql, $searchParams) = db_like_clause(['m.name', 'g.group_name', 'u.full_name', 'r.purpose'], $search);
    if ($searchSql !== '') {
        $where[]  = $searchSql;
        $params  += $searchParams;
    }

    return [implode(' AND ', $where), $params];
}

/**
 * Reservations belonging to one member, newest first. Optional search
 * term (matched against machine name, group name, and purpose) and
 * pagination, same pattern as get_all_reservations() — see
 * member/machinery_history.php for the search/pagination use, and
 * member/dashboard.php for the simple unpaginated "just give me
 * everything to count" use (both $search and $limit/$offset default to
 * null, so that call site needed no changes).
 *
 * $limit/$offset: optional pagination window (both required together, or
 * omit both to get every matching row).
 */
function get_reservations_by_user(PDO $pdo, int $userId, ?string $search = null, ?int $limit = null, ?int $offset = null): array
{
    list($where, $params) = build_user_reservation_filter($userId, $search);
    $sql = "SELECT r.*, m.name AS machinery_name, g.group_name
            FROM machinery_reservations r
            LEFT JOIN machinery m ON m.machinery_id = r.machinery_id
            LEFT JOIN machinery_groups g ON g.group_id = r.group_id
            WHERE $where
            ORDER BY r.start_date DESC, r.created_at DESC";
    return db_fetch_all($pdo, $sql, $params, $limit, $offset);
}

/** Count of a member's reservations matching an optional search filter (for pagination totals). */
function count_reservations_by_user(PDO $pdo, int $userId, ?string $search = null): int
{
    list($where, $params) = build_user_reservation_filter($userId, $search);
    return db_count(
        $pdo,
        "SELECT COUNT(*) AS cnt
         FROM machinery_reservations r
         LEFT JOIN machinery m ON m.machinery_id = r.machinery_id
         LEFT JOIN machinery_groups g ON g.group_id = r.group_id
         WHERE $where",
        $params
    );
}

/** Shared WHERE clause + params for get_reservations_by_user() / count_reservations_by_user(). */
function build_user_reservation_filter(int $userId, ?string $search): array
{
    $where  = ['r.user_id = :uid'];
    $params = ['uid' => $userId];

    list($searchSql, $searchParams) = db_like_clause(['m.name', 'g.group_name', 'r.purpose'], $search);
    if ($searchSql !== '') {
        $where[]  = $searchSql;
        $params  += $searchParams;
    }

    return [implode(' AND ', $where), $params];
}

function get_reservation_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT r.*, m.name AS machinery_name, g.group_name
         FROM machinery_reservations r
         LEFT JOIN machinery m ON m.machinery_id = r.machinery_id
         LEFT JOIN machinery_groups g ON g.group_id = r.group_id
         WHERE r.reservation_id = :id'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Checks whether a machinery item already has an approved or pending
 * reservation overlapping the given date range. Used to warn/prevent
 * obvious double-booking (simple overlap check, not a full conflict engine).
 */
function has_overlapping_reservation(PDO $pdo, int $machineryId, string $startDate, string $endDate, ?int $excludeReservationId = null): bool
{
    $sql = "SELECT COUNT(*) AS cnt
            FROM machinery_reservations
            WHERE machinery_id = :machinery_id
              AND status IN ('pending','approved')
              AND start_date <= :end_date
              AND end_date >= :start_date";
    $params = [
        'machinery_id' => $machineryId,
        'start_date'   => $startDate,
        'end_date'     => $endDate,
    ];

    if ($excludeReservationId) {
        $sql .= ' AND reservation_id != :exclude_id';
        $params['exclude_id'] = $excludeReservationId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetch()['cnt'] > 0;
}

/**
 * Every unit in a machinery group that is BOTH marked available
 * (is_available = 1) AND has no pending/approved reservation overlapping
 * the given date range. This is the real, date-aware stock check — used
 * both when a member submits a group request (to warn if the group looks
 * fully booked for those exact dates) and, more importantly, when an
 * officer opens the approval/assignment screen (to know which specific
 * units can actually be offered, and whether approval is even possible
 * at all).
 *
 * $excludeReservationId lets an officer re-check availability while
 * looking at the very reservation they're about to approve, without that
 * reservation's own (still-group-only, no machinery_id yet) row
 * interfering — in practice a group-only pending reservation has
 * machinery_id IS NULL so it never matches the overlap check anyway, but
 * the parameter is accepted for symmetry with has_overlapping_reservation()
 * and in case this is ever called while re-evaluating an already-assigned
 * reservation (e.g. reassigning a unit before final approval).
 */
function get_available_machinery_in_group(PDO $pdo, int $groupId, string $startDate, string $endDate, ?int $excludeReservationId = null): array
{
    $sql = "SELECT m.*
            FROM machinery m
            WHERE m.group_id = :group_id
              AND m.is_available = 1
              AND NOT EXISTS (
                  SELECT 1 FROM machinery_reservations r
                  WHERE r.machinery_id = m.machinery_id
                    AND r.status IN ('pending', 'approved')
                    AND r.start_date <= :end_date
                    AND r.end_date >= :start_date";
    $params = [
        'group_id'   => $groupId,
        'start_date' => $startDate,
        'end_date'   => $endDate,
    ];

    if ($excludeReservationId) {
        $sql .= ' AND r.reservation_id != :exclude_id';
        $params['exclude_id'] = $excludeReservationId;
    }

    $sql .= ') ORDER BY m.name';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Creates a reservation. Pass exactly one of $machineryId (a specific,
 * ungrouped machine) or $groupId (a machinery group — the common case
 * once groups are in use) — see this file's top comment for why a
 * reservation targets one or the other, never both, at request time.
 */
function create_reservation(PDO $pdo, ?int $machineryId, ?int $groupId, int $userId, string $startDate, string $endDate, ?string $purpose): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO machinery_reservations (machinery_id, group_id, user_id, start_date, end_date, purpose)
         VALUES (:machinery_id, :group_id, :user_id, :start_date, :end_date, :purpose)'
    );
    $stmt->execute([
        'machinery_id' => $machineryId,
        'group_id'     => $groupId,
        'user_id'      => $userId,
        'start_date'   => $startDate,
        'end_date'     => $endDate,
        'purpose'      => $purpose ?: null,
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Approves a group-targeted reservation by assigning it a specific unit
 * (this is the officer's "which tractor am I actually handing over" step
 * — see officer/reservation_assign.php). Re-validates that the chosen
 * unit is still actually free for these dates at the moment of approval
 * (not just when the assignment page was first opened), since time can
 * pass and someone else's request could be approved first — returns
 * false without changing anything if it's no longer available, so the
 * caller can show a fresh error instead of silently double-booking a unit.
 */
function assign_and_approve_reservation(PDO $pdo, int $reservationId, int $machineryId): bool
{
    $reservation = get_reservation_by_id($pdo, $reservationId);
    if (!$reservation || $reservation['status'] !== 'pending') {
        return false;
    }

    if (has_overlapping_reservation($pdo, $machineryId, $reservation['start_date'], $reservation['end_date'], $reservationId)) {
        return false;
    }

    $stmt = $pdo->prepare(
        "UPDATE machinery_reservations
         SET machinery_id = :machinery_id, status = 'approved', rejection_remark = NULL
         WHERE reservation_id = :id"
    );
    $stmt->execute(['machinery_id' => $machineryId, 'id' => $reservationId]);
    return true;
}

/**
 * Approves a STANDALONE (non-grouped) reservation directly — the
 * machinery_id was already fixed at request time, so there's no unit to
 * choose, just a status flip. Still re-validated against overlaps for
 * safety, same reasoning as assign_and_approve_reservation().
 */
function approve_standalone_reservation(PDO $pdo, int $reservationId): bool
{
    $reservation = get_reservation_by_id($pdo, $reservationId);
    if (!$reservation || $reservation['status'] !== 'pending' || $reservation['machinery_id'] === null) {
        return false;
    }

    if (has_overlapping_reservation($pdo, (int) $reservation['machinery_id'], $reservation['start_date'], $reservation['end_date'], $reservationId)) {
        return false;
    }

    $stmt = $pdo->prepare("UPDATE machinery_reservations SET status = 'approved', rejection_remark = NULL WHERE reservation_id = :id");
    $stmt->execute(['id' => $reservationId]);
    return true;
}

/**
 * Rejects a reservation with a required remark — per the client's
 * request, rejecting (as opposed to just letting a request expire) always
 * records WHY, so the member knows what happened instead of just seeing
 * "rejected" with no explanation.
 */
function reject_reservation(PDO $pdo, int $reservationId, string $remark): void
{
    $stmt = $pdo->prepare(
        "UPDATE machinery_reservations SET status = 'rejected', rejection_remark = :remark WHERE reservation_id = :id"
    );
    $stmt->execute(['remark' => $remark, 'id' => $reservationId]);
}

/**
 * Edits the remark on an ALREADY-rejected reservation (e.g. to fix a typo or
 * give the member a clearer reason). Only touches rows whose status is
 * 'rejected' — the status itself is never changed. Returns false if the
 * reservation isn't currently rejected.
 */
function update_rejection_remark(PDO $pdo, int $reservationId, string $remark): bool
{
    $stmt = $pdo->prepare(
        "UPDATE machinery_reservations SET rejection_remark = :remark
         WHERE reservation_id = :id AND status = 'rejected'"
    );
    $stmt->execute(['remark' => $remark, 'id' => $reservationId]);
    // rowCount() is 0 when the remark is unchanged, so check the status explicitly.
    $check = $pdo->prepare("SELECT 1 FROM machinery_reservations WHERE reservation_id = :id AND status = 'rejected'");
    $check->execute(['id' => $reservationId]);
    return (bool) $check->fetchColumn();
}

/** Cancels a previously-approved reservation (officer action, e.g. equipment broke down after approval). */
function cancel_reservation(PDO $pdo, int $reservationId): void
{
    $stmt = $pdo->prepare("UPDATE machinery_reservations SET status = 'cancelled' WHERE reservation_id = :id");
    $stmt->execute(['id' => $reservationId]);
}

