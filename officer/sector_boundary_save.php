<?php
/**
 * AJAX endpoint used by assets/js/sector-boundaries.js.
 * POST only. Returns JSON. Never renders HTML.
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/sector_boundaries.php';

header('Content-Type: application/json');

// Manual JSON-friendly auth/role check (require_role() would redirect with
// a Location header, which isn't useful to a fetch() caller expecting JSON).
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

$sectorId = (int) ($input['sector_id'] ?? 0);
$points   = $input['points'] ?? null; // array of [lat, lng] pairs, or null/[] to clear
$runAssignment = !empty($input['run_assignment']);

if ($sectorId <= 0) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Sector not found.']);
    exit;
}

try {
    save_sector_boundary($pdo, $sectorId, $points);

    $response = ['ok' => true];

    if ($runAssignment) {
        $summary = run_auto_assignment_for_all_lots($pdo, (int) $_SESSION['user_id']);
        $response['assignment'] = $summary;
    }

    echo json_encode($response);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    error_log('Sector boundary save error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Something went wrong while saving the boundary.']);
}
