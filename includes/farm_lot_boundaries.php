<?php
/**
 * Farm lot boundary (polygon) storage and helpers.
 * Requires $pdo from config/database.php to already be loaded.
 *
 * A polygon is stored as a JSON array of [lat, lng] pairs in
 * farm_lots.boundary_geojson, drawn with Leaflet.draw — same format
 * sectors.boundary_geojson uses for sector boundaries.
 *
 * There is no separate "save boundary" step or endpoint: the polygon is
 * submitted as part of the same add/edit farm lot form, in a hidden field,
 * and saved together with the rest of the lot's fields in one submit (see
 * create_farm_lot()/update_farm_lot() in includes/members.php, which both
 * call normalize_and_center_boundary() below). This avoids a separate
 * "Save Boundary" button that could be missed, and means drawing a
 * boundary works the same way for a brand-new lot as it does for editing
 * an existing one — no "save the lot first" step required.
 *
 * farm_lots.latitude/longitude are NOT independently editable — they are
 * always derived FROM the polygon, as its centroid, and are NULL when the
 * lot has no polygon. They exist purely so anything that only needs a
 * single point (sector auto-assignment's point-in-polygon test, the
 * mapped-lot lists) doesn't need to know about polygons at all.
 */

/** Get a farm lot's boundary as a PHP array of [lat, lng] pairs, or null if none drawn. */
function get_farm_lot_boundary(PDO $pdo, int $lotId): ?array
{
    $stmt = $pdo->prepare('SELECT boundary_geojson FROM farm_lots WHERE lot_id = :id');
    $stmt->execute(['id' => $lotId]);
    $row = $stmt->fetch();
    if (!$row || $row['boundary_geojson'] === null) {
        return null;
    }
    $points = json_decode($row['boundary_geojson'], true);
    return is_array($points) ? $points : null;
}

/**
 * Computes the centroid (arithmetic mean of vertices) of a polygon.
 * This is the simple vertex-average centroid, not the more precise
 * area-weighted centroid — deliberately, since farm lots are small enough
 * that the difference is negligible, and the simple version needs no
 * extra math dependency and matches what officers would eyeball as
 * "the middle of the shape" closely enough for a representative map pin
 * and for the point-in-polygon sector auto-assignment check.
 *
 * $polygon is an array of [lat, lng] pairs with at least 1 point.
 * Returns ['lat' => float, 'lng' => float].
 */
function polygon_centroid(array $polygon): array
{
    $count = count($polygon);
    $sumLat = 0.0;
    $sumLng = 0.0;
    foreach ($polygon as $point) {
        $sumLat += (float) $point[0];
        $sumLng += (float) $point[1];
    }
    return [
        'lat' => $sumLat / $count,
        'lng' => $sumLng / $count,
    ];
}

/**
 * Validates and normalizes raw polygon points (as decoded from the form's
 * hidden JSON field) into the ['geojson' => string|null, 'lat' => float|null,
 * 'lng' => float|null] shape create_farm_lot()/update_farm_lot() write
 * straight into the database — this is the single place that turns
 * "whatever the browser submitted" into clean, storable values.
 *
 * $rawPoints: decoded JSON — expected to be an array of [lat, lng] pairs,
 * or null/empty when the lot has no boundary drawn.
 *
 * Returns boundary_geojson=NULL and lat/lng=NULL when there's no polygon.
 * Throws InvalidArgumentException if points were provided but don't form
 * a valid polygon (fewer than 3, or malformed pairs) — this is a real
 * validation error, not a silent fallback, since a half-drawn polygon
 * being silently discarded would surprise the officer.
 */
function normalize_and_center_boundary(?array $rawPoints): array
{
    if ($rawPoints === null || count($rawPoints) === 0) {
        return ['geojson' => null, 'lat' => null, 'lng' => null];
    }

    if (count($rawPoints) < 3) {
        throw new InvalidArgumentException('A farm lot boundary needs at least 3 points — draw a complete polygon, or leave it blank.');
    }

    $clean = array_map(function ($p) {
        if (!is_array($p) || count($p) < 2 || !is_numeric($p[0]) || !is_numeric($p[1])) {
            throw new InvalidArgumentException('Invalid polygon point format.');
        }
        return [(float) $p[0], (float) $p[1]];
    }, array_values($rawPoints));

    $centroid = polygon_centroid($clean);

    return [
        'geojson' => json_encode($clean),
        'lat'     => $centroid['lat'],
        'lng'     => $centroid['lng'],
    ];
}

/**
 * Every farm lot that has a drawn boundary, with its polygon decoded, for
 * map display (e.g. plotting farm lot shapes alongside sector boundaries
 * on officer/map.php, or a member's own lot on member/map.php). Optionally
 * scoped to one member's own lots.
 */
function get_farm_lots_with_boundaries(PDO $pdo, ?int $userId = null): array
{
    $sql = "SELECT fl.lot_id, fl.lot_name, fl.land_area_ha, fl.boundary_geojson, fl.user_id, fl.sector_id,
                   s.sector_name, u.full_name AS owner_name
            FROM farm_lots fl
            JOIN users u ON u.user_id = fl.user_id
            LEFT JOIN sectors s ON s.sector_id = fl.sector_id
            WHERE fl.boundary_geojson IS NOT NULL";
    $params = [];
    if ($userId !== null) {
        $sql .= ' AND fl.user_id = :uid';
        $params['uid'] = $userId;
    }
    $sql .= ' ORDER BY fl.lot_name';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $decoded = json_decode($row['boundary_geojson'], true);
        $row['boundary'] = is_array($decoded) ? $decoded : [];
    }
    unset($row);

    return $rows;
}

/**
 * Number of farm lots that can't be drawn on a map because they have no
 * boundary (NULL, or fewer than 3 points). Used to flag incomplete data to
 * officers on officer/map.php.
 */
function count_farm_lots_without_boundary(PDO $pdo): int
{
    $total = (int) $pdo->query('SELECT COUNT(*) AS cnt FROM farm_lots')->fetch()['cnt'];
    $drawable = count(array_filter(
        get_farm_lots_with_boundaries($pdo),
        fn($l) => count($l['boundary']) >= 3
    ));
    return max(0, $total - $drawable);
}
