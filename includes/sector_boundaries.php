<?php
/**
 * Sector boundary (polygon) storage and auto-assignment logic.
 * Requires $pdo from config/database.php to already be loaded.
 *
 * Officers draw a sector's boundary as a polygon on the map (see
 * officer/sector_boundaries.php + assets/js/sector-boundaries.js). Farm
 * lots whose coordinates fall inside that polygon can then be assigned to
 * the sector automatically, using the standard ray-casting point-in-polygon
 * test — the same fundamental technique a GIS tool like QGIS uses for a
 * spatial join, just implemented directly here since we don't have a real
 * QGIS-exported boundary dataset to import yet.
 *
 * A polygon is stored as a JSON array of [lat, lng] pairs in
 * sectors.boundary_geojson, e.g. [[13.748,122.943],[13.750,122.945],...].
 * This is intentionally simple (not full GeoJSON Feature/Geometry
 * wrapping) since Leaflet.draw's polygon layer gives us exactly this
 * vertex list directly, and PHP only ever needs the raw points for the
 * point-in-polygon test.
 */

/** Get every sector that has a drawn boundary, with its polygon decoded, for map display. */
function get_sectors_with_boundaries(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT sector_id, sector_name, description, boundary_geojson
         FROM sectors WHERE boundary_geojson IS NOT NULL
         ORDER BY sector_name"
    )->fetchAll();

    foreach ($rows as &$row) {
        $decoded = json_decode($row['boundary_geojson'], true);
        $row['boundary'] = is_array($decoded) ? $decoded : [];
    }
    unset($row);

    return $rows;
}

/**
 * Save (or clear) a sector's boundary polygon.
 * $points is an array of [lat, lng] pairs, or null/empty to clear the
 * boundary entirely. Requires at least 3 points to form a polygon.
 */
function save_sector_boundary(PDO $pdo, int $sectorId, ?array $points): void
{
    if ($points === null || count($points) === 0) {
        $stmt = $pdo->prepare('UPDATE sectors SET boundary_geojson = NULL WHERE sector_id = :id');
        $stmt->execute(['id' => $sectorId]);
        return;
    }

    if (count($points) < 3) {
        throw new InvalidArgumentException('A polygon needs at least 3 points.');
    }

    // Normalize to plain [lat, lng] float pairs before storing, so we never
    // persist anything unexpected (e.g. extra object keys from the client).
    $clean = array_map(function ($p) {
        if (!is_array($p) || count($p) < 2) {
            throw new InvalidArgumentException('Invalid polygon point format.');
        }
        return [(float) $p[0], (float) $p[1]];
    }, array_values($points));

    $stmt = $pdo->prepare('UPDATE sectors SET boundary_geojson = :geojson WHERE sector_id = :id');
    $stmt->execute([
        'geojson' => json_encode($clean),
        'id'      => $sectorId,
    ]);
}

/**
 * Standard ray-casting point-in-polygon test.
 * $point is [lat, lng]; $polygon is an array of [lat, lng] pairs forming a
 * closed ring (does not need to explicitly repeat the first point at the
 * end — this works either way). Returns true if the point lies inside the
 * polygon. Points exactly on an edge may resolve either way depending on
 * floating-point rounding, which is an accepted tradeoff of this classic
 * algorithm and not a practical concern for farm-lot-sized coordinates.
 */
function point_in_polygon(array $point, array $polygon): bool
{
    [$px, $py] = [$point[0], $point[1]];
    $inside = false;
    $n = count($polygon);

    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        $xi = $polygon[$i][0];
        $yi = $polygon[$i][1];
        $xj = $polygon[$j][0];
        $yj = $polygon[$j][1];

        $intersects = (($yi > $py) !== ($yj > $py))
            && ($px < ($xj - $xi) * ($py - $yi) / ($yj - $yi) + $xi);

        if ($intersects) {
            $inside = !$inside;
        }
    }

    return $inside;
}

/**
 * Finds every sector whose polygon contains the given point. Returns an
 * array of sector rows (sector_id, sector_name, ...). Since overlapping
 * polygons are allowed, this can return more than one match — callers
 * that need a single sector should use find_first_matching_sector()
 * instead and surface the "matches N sectors" case to the officer.
 */
function find_matching_sectors(PDO $pdo, float $lat, float $lng): array
{
    $sectors = get_sectors_with_boundaries($pdo);
    $matches = [];

    foreach ($sectors as $sector) {
        if (count($sector['boundary']) >= 3 && point_in_polygon([$lat, $lng], $sector['boundary'])) {
            $matches[] = $sector;
        }
    }

    return $matches;
}

/**
 * Checks a single farm lot's coordinates against all drawn sector
 * polygons and returns what auto-assignment WOULD do, without writing
 * anything — used to preview the effect and to detect the
 * "already assigned to a different sector" case that needs officer
 * confirmation before overwriting.
 *
 * Returns null if the lot has no coordinates or matches no polygon.
 * Otherwise returns an array with the matched sector(s), whether this
 * differs from the lot's current sector, and how many sectors matched
 * (>1 means overlapping polygons — allowed, but flagged to the officer).
 */
function preview_auto_assignment_for_lot(PDO $pdo, array $lot): ?array
{
    if ($lot['latitude'] === null || $lot['longitude'] === null) {
        return null;
    }

    $matches = find_matching_sectors($pdo, (float) $lot['latitude'], (float) $lot['longitude']);
    if (empty($matches)) {
        return null;
    }

    // If multiple polygons overlap at this point, we still need to pick
    // one to assign; the first match (by sector name order) is used, and
    // the overlap is surfaced via match_count so the UI can warn about it.
    $chosen = $matches[0];

    return [
        'matched_sector'   => $chosen,
        'match_count'      => count($matches),
        'all_matches'      => $matches,
        'current_sector_id' => $lot['sector_id'],
        'would_change'     => (int) $chosen['sector_id'] !== (int) ($lot['sector_id'] ?? 0),
    ];
}

/**
 * Applies auto-assignment for a single farm lot: sets its sector_id to
 * whatever polygon match is found (if any), logs the change to
 * sector_auto_assignments, and returns the preview info that was applied
 * (or null if no polygon matched, in which case nothing is changed).
 * Callers are responsible for confirming with the officer beforehand when
 * would_change is true and the lot already had a different sector.
 */
function apply_auto_assignment_for_lot(PDO $pdo, int $lotId, ?int $assignedBy): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM farm_lots WHERE lot_id = :id');
    $stmt->execute(['id' => $lotId]);
    $lot = $stmt->fetch();
    if (!$lot) {
        return null;
    }

    $preview = preview_auto_assignment_for_lot($pdo, $lot);
    if ($preview === null) {
        return null;
    }

    $newSectorId = (int) $preview['matched_sector']['sector_id'];
    $previousSectorId = $lot['sector_id'] !== null ? (int) $lot['sector_id'] : null;

    if ($newSectorId === $previousSectorId) {
        // Already correctly assigned; nothing to do, nothing to log.
        return $preview;
    }

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE farm_lots SET sector_id = :sector_id WHERE lot_id = :lot_id');
        $update->execute(['sector_id' => $newSectorId, 'lot_id' => $lotId]);

        $log = $pdo->prepare(
            'INSERT INTO sector_auto_assignments (lot_id, previous_sector_id, new_sector_id, assigned_by)
             VALUES (:lot_id, :previous_sector_id, :new_sector_id, :assigned_by)'
        );
        $log->execute([
            'lot_id'             => $lotId,
            'previous_sector_id' => $previousSectorId,
            'new_sector_id'      => $newSectorId,
            'assigned_by'        => $assignedBy,
        ]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    return $preview;
}

/**
 * Runs auto-assignment across every farm lot that has coordinates,
 * against every sector with a drawn boundary. Used after an officer draws
 * or edits a polygon (to catch lots that now fall inside/outside it) and
 * as a manual "re-run for all lots" maintenance action.
 *
 * Only lots that WOULD change are actually written — lots already
 * correctly assigned, or with no polygon match at all, are left alone.
 * Returns a summary: how many lots were changed, and the list of changes
 * for display (previous sector name, new sector name, lot name, owner).
 */
function run_auto_assignment_for_all_lots(PDO $pdo, ?int $assignedBy, bool $onlyChanged = true): array
{
    $lots = $pdo->query(
        "SELECT * FROM farm_lots WHERE latitude IS NOT NULL AND longitude IS NOT NULL"
    )->fetchAll();

    $changes = [];

    foreach ($lots as $lot) {
        $preview = preview_auto_assignment_for_lot($pdo, $lot);
        if ($preview === null) {
            continue;
        }
        if ($onlyChanged && !$preview['would_change']) {
            continue;
        }

        $result = apply_auto_assignment_for_lot($pdo, (int) $lot['lot_id'], $assignedBy);
        if ($result !== null && $result['would_change']) {
            $changes[] = [
                'lot_id'          => (int) $lot['lot_id'],
                'lot_name'        => $lot['lot_name'],
                'user_id'         => (int) $lot['user_id'],
                'previous_sector_id' => $lot['sector_id'],
                'new_sector_id'   => (int) $result['matched_sector']['sector_id'],
                'new_sector_name' => $result['matched_sector']['sector_name'],
                'match_count'     => $result['match_count'],
            ];
        }
    }

    return [
        'checked_count' => count($lots),
        'changed_count' => count($changes),
        'changes'       => $changes,
    ];
}

/** Recent auto-assignment log entries, joined with lot/sector names, most recent first. */
function get_recent_auto_assignments(PDO $pdo, int $limit = 50): array
{
    $limit = (int) max(1, min($limit, 200));
    return $pdo->query(
        "SELECT saa.*, fl.lot_name, u.full_name AS owner_name,
                ns.sector_name AS new_sector_name, ps.sector_name AS previous_sector_name
         FROM sector_auto_assignments saa
         JOIN farm_lots fl ON fl.lot_id = saa.lot_id
         JOIN users u ON u.user_id = fl.user_id
         JOIN sectors ns ON ns.sector_id = saa.new_sector_id
         LEFT JOIN sectors ps ON ps.sector_id = saa.previous_sector_id
         ORDER BY saa.assigned_at DESC
         LIMIT $limit"
    )->fetchAll();
}
