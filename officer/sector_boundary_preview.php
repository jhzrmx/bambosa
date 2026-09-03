<?php
/**
 * AJAX endpoint used by assets/js/sector-boundaries.js while the officer is
 * actively drawing/editing a polygon (before saving), to show a live count
 * of "N farm lots would be inside this boundary". POST only. Returns JSON.
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';
require_once __DIR__ . '/../includes/sector_boundaries.php';

header('Content-Type: application/json');

if (!is_logged_in() || current_role() !== 'officer') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not authorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || !csrf_verify($input['csrf_token'] ?? null)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'error' => 'Invalid or expired form session. Please reload the page.']);
    exit;
}

$points = $input['points'] ?? null;

if (!is_array($points) || count($points) < 3) {
    echo json_encode(['ok' => true, 'matched_count' => 0, 'matched_lots' => []]);
    exit;
}

// Normalize points the same way save_sector_boundary() would, without persisting.
try {
    $polygon = array_map(function ($p) {
        if (!is_array($p) || count($p) < 2) {
            throw new InvalidArgumentException('Invalid point.');
        }
        return [(float) $p[0], (float) $p[1]];
    }, array_values($points));
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid polygon points.']);
    exit;
}

$lots = $pdo->query(
    "SELECT fl.lot_id, fl.lot_name, fl.latitude, fl.longitude, u.full_name AS owner_name
     FROM farm_lots fl
     JOIN users u ON u.user_id = fl.user_id
     WHERE fl.latitude IS NOT NULL AND fl.longitude IS NOT NULL"
)->fetchAll();

$matched = [];
foreach ($lots as $lot) {
    if (point_in_polygon([(float) $lot['latitude'], (float) $lot['longitude']], $polygon)) {
        $matched[] = [
            'lot_id'   => (int) $lot['lot_id'],
            'lot_name' => $lot['lot_name'],
            'owner'    => $lot['owner_name'],
        ];
    }
}

echo json_encode([
    'ok'            => true,
    'matched_count' => count($matched),
    'matched_lots'  => $matched,
]);
