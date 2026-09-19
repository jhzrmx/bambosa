<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';
require_once __DIR__ . '/../includes/sector_boundaries.php';
require_once __DIR__ . '/../includes/farm_lot_boundaries.php';

require_role('officer');

$memberId = (int) ($_GET['id'] ?? 0);
$member = $memberId ? get_user_by_id($pdo, $memberId) : null;

if (!$member || $member['role'] !== 'member') {
    header('Location: ' . base_url('officer/members.php'));
    exit;
}

$errors = [];
$isAddingOrEditing = isset($_GET["new"]) || isset($_GET["edit_lot"]);
$editingLot = null;
$pendingAssignmentPreview = null; // set when auto-assign would overwrite an existing sector, awaiting confirmation

// Load a lot into the form for editing if ?edit_lot=ID is present
if (isset($_GET['edit_lot'])) {
    $editingLot = get_farm_lot($pdo, (int) $_GET['edit_lot']);
    // Make sure that lot actually belongs to this member
    if ($editingLot && (int) $editingLot['user_id'] !== $memberId) {
        $editingLot = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_lot') {

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $lotId       = (int) ($_POST['lot_id'] ?? 0);
    $lotName     = trim($_POST['lot_name'] ?? '');
    $sectorId    = (int) ($_POST['sector_id'] ?? 0) ?: null;
    $landArea    = $_POST['land_area_ha'] ?? '';
    $notes       = trim($_POST['notes'] ?? '');
    $autoAssign  = isset($_POST['auto_assign_sector']);
    $confirmedOverwrite = isset($_POST['confirm_overwrite_sector']);
    $boundaryPointsRaw = $_POST['boundary_points'] ?? '';

    if ($lotName === '') {
        $errors[] = 'Lot name/label is required.';
    }
    if (!is_numeric($landArea) || (float) $landArea <= 0) {
        $errors[] = 'Land area must be a positive number (in hectares).';
    }

    // The polygon comes in as a JSON string in a hidden field (drawn
    // client-side by assets/js/farm-lot-boundary.js), decoded here rather
    // than via a separate AJAX call — see includes/farm_lot_boundaries.php's
    // top comment for why this is one form submit instead of two steps.
    $boundary = null;
    if (empty($errors) && $boundaryPointsRaw !== '') {
        $decodedPoints = json_decode($boundaryPointsRaw, true);
        if (!is_array($decodedPoints)) {
            $errors[] = 'The drawn boundary could not be read. Please redraw it and try again.';
        } else {
            try {
                $boundary = normalize_and_center_boundary($decodedPoints);
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }
    } else {
        $boundary = normalize_and_center_boundary(null); // no polygon drawn — clears lat/lng/boundary
    }

    if (empty($errors)) {
        $hasCoords = $boundary['lat'] !== null && $boundary['lng'] !== null;

        // If auto-assign is requested and the polygon gives us a centroid,
        // check what it would do BEFORE saving, so we can ask for
        // confirmation when it would overwrite a sector the officer
        // already picked manually.
        if ($autoAssign && $hasCoords && !$confirmedOverwrite) {
            $fakeLot = [
                'sector_id' => $sectorId,
                'latitude'  => $boundary['lat'],
                'longitude' => $boundary['lng'],
            ];
            $preview = preview_auto_assignment_for_lot($pdo, $fakeLot);

            // Only interrupt with a confirmation step if it would actually
            // change an EXISTING sector assignment. If the lot currently has
            // no sector, or the match agrees with the current one, just proceed.
            if ($preview !== null && $preview['would_change'] && $sectorId !== null) {
                $pendingAssignmentPreview = $preview;
                // Stash the submitted form values so the confirmation step
                // can re-render the form with everything the officer typed —
                // including the drawn boundary, so it isn't lost.
                $editingLot = [
                    'lot_id'           => $lotId,
                    'lot_name'         => $lotName,
                    'sector_id'        => $sectorId,
                    'land_area_ha'     => $landArea,
                    'latitude'         => $boundary['lat'],
                    'longitude'        => $boundary['lng'],
                    'boundary_geojson' => $boundary['geojson'],
                    'notes'            => $notes,
                ];
            }
        }

        if ($pendingAssignmentPreview === null) {
            $data = [
                'user_id'          => $memberId,
                'sector_id'        => $sectorId,
                'lot_name'         => $lotName,
                'land_area_ha'     => (float) $landArea,
                'latitude'         => $boundary['lat'],
                'longitude'        => $boundary['lng'],
                'boundary_geojson' => $boundary['geojson'],
                'notes'            => $notes,
            ];

            if ($lotId > 0) {
                update_farm_lot($pdo, $lotId, $data);
            } else {
                $lotId = create_farm_lot($pdo, $data);
            }

            // Run auto-assignment AFTER the save, using the now-current
            // coordinates, so the sector on record reflects the polygon
            // match. This also covers the "no existing sector, or officer
            // already agreed via confirmation" paths from above.
            if ($autoAssign && $hasCoords) {
                apply_auto_assignment_for_lot($pdo, $lotId, (int) $_SESSION['user_id']);
            }

            header('Location: ' . base_url('officer/member_view.php?id=' . $memberId . '&flash=lot_saved'));
            exit;
        }
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_lot') {
    if (csrf_verify($_POST['csrf_token'] ?? null)) {
        $lotId = (int) ($_POST['lot_id'] ?? 0);
        $lot = get_farm_lot($pdo, $lotId);
        if ($lot && (int) $lot['user_id'] === $memberId) {
            delete_farm_lot($pdo, $lotId);
        }
    }
    header('Location: ' . base_url('officer/member_view.php?id=' . $memberId . '&flash=lot_deleted'));
    exit;
}

$lots = get_farm_lots_by_user($pdo, $memberId);
$sectors = get_sectors($pdo);
$totalArea = get_total_land_area($pdo, $memberId);
$flash = $_GET['flash'] ?? null;
$activeNav = 'members';

// Sensible default center for the boundary-drawing map: use the lot being
// edited if it already has a computed centroid, otherwise the average of
// all other farm lots' coordinates, otherwise fall back to a wide generic
// view in the JS itself (no data to anchor a guess to).
$mapDefaultCenter = null;
if ($editingLot && $editingLot['latitude'] !== null && $editingLot['longitude'] !== null) {
    $mapDefaultCenter = ['lat' => (float) $editingLot['latitude'], 'lng' => (float) $editingLot['longitude']];
} else {
    $mapDefaultCenter = get_average_farm_lot_location($pdo);
}

// Existing boundary polygon for this lot, if any — pre-loads into the
// drawing widget when editing. A brand-new lot naturally has none yet.
//
// array_key_exists (not isset) matters here: during the pending
// sector-overwrite confirmation step, $editingLot is rebuilt from the
// just-submitted form values (see the 'boundary_points' handling above),
// and 'boundary_geojson' is explicitly set to null when no boundary was
// drawn — isset() would treat that null as "key absent" and incorrectly
// fall through to re-fetching the lot's OLD boundary from the database,
// silently reviving a boundary the officer just cleared.
if ($editingLot && array_key_exists('boundary_geojson', $editingLot)) {
    // We're re-rendering the just-submitted (not yet saved) form values.
    $editingLotBoundary = $editingLot['boundary_geojson']
        ? json_decode($editingLot['boundary_geojson'], true)
        : null;
} elseif ($editingLot && (int) ($editingLot['lot_id'] ?? 0) > 0) {
    // Normal "edit an existing, already-saved lot" load.
    $editingLotBoundary = get_farm_lot_boundary($pdo, (int) $editingLot['lot_id']);
} else {
    $editingLotBoundary = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($member['full_name']) ?> · BAMBOSA Officer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/officer_sidebar.php'; ?>

  <main class="dash-main">
    <a href="<?= base_url('officer/members.php') ?>" class="back-link">&larr; Back to Members</a>
    <h1><?= htmlspecialchars($member['full_name']) ?></h1>

    <div class="profile-summary">
      <div><span class="label">Email:</span> <?= htmlspecialchars($member['email']) ?></div>
      <div><span class="label">Contact:</span> <?= htmlspecialchars($member['contact_number'] ?? '—') ?></div>
      <div><span class="label">Status:</span> <span class="status-badge status-<?= htmlspecialchars($member['status']) ?>"><?= htmlspecialchars($member['status']) ?></span></div>
      <div><span class="label">Total Registered Land Area:</span> <?= number_format($totalArea, 2) ?> ha</div>
    </div>

    <?php if ($flash === 'lot_saved'): ?>
      <div class="alert alert-success">Farm lot saved successfully.</div>
    <?php elseif ($flash === 'lot_deleted'): ?>
      <div class="alert alert-success">Farm lot removed.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>
	
	<?php if (!$isAddingOrEditing): ?>
      <h2 class="section-heading">Farm Lots</h2>
      <div class="table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th>Lot Name</th>
            <th>Sector</th>
            <th>Land Area (ha)</th>
            <th>Boundary</th>
            <th>Notes</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($lots)): ?>
            <tr><td colspan="6" class="empty-row">No farm lots registered yet.</td></tr>
          <?php else: foreach ($lots as $lot): ?>
            <tr>
              <td><?= htmlspecialchars($lot['lot_name']) ?></td>
              <td><?= htmlspecialchars($lot['sector_name'] ?? '—') ?></td>
              <td><?= number_format($lot['land_area_ha'], 2) ?></td>
              <td>
                <?php if (!empty($lot['boundary_geojson'])): ?>
                  <span class="status-badge status-active" title="Center point: <?= htmlspecialchars($lot['latitude']) ?>, <?= htmlspecialchars($lot['longitude']) ?>">Drawn</span>
                <?php else: ?>
                  <span class="status-badge status-inactive">Not drawn</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($lot['notes'] ?? '—') ?></td>
              <td class="actions-cell">
                <a href="<?= base_url('officer/member_view.php?id=' . $memberId . '&edit_lot=' . (int) $lot['lot_id']) ?>" class="link-btn">Edit</a>
                <form method="POST" class="inline-form" onsubmit="return confirm('Remove this farm lot record?');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete_lot">
                  <input type="hidden" name="lot_id" value="<?= (int) $lot['lot_id'] ?>">
                  <button type="submit" class="link-btn deactivate">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
      </div>
	  <a href="<?= base_url('officer/member_view.php?id=' . $memberId . '&new=') ?>"><button type="button" class="btn-full" style="max-width:220px; margin-top:24px;">Add New Farm Lot</button></a>
	<?php endif; ?>
	
	<?php if ($isAddingOrEditing): ?>
      <h2 class="section-heading"><?= $editingLot ? 'Edit Farm Lot' : 'Add New Farm Lot' ?></h2>
	  
      <form method="POST" action="<?= base_url('officer/member_view.php?id=' . $memberId) ?>" class="inline-card-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_lot">
        <input type="hidden" name="lot_id" value="<?= $editingLot ? (int) $editingLot['lot_id'] : 0 ?>">
	  
        <div class="form-row">
          <div class="form-group">
            <label for="lot_name">Lot Name / Label</label>
            <input type="text" id="lot_name" name="lot_name" required
                   value="<?= htmlspecialchars($editingLot['lot_name'] ?? '') ?>" placeholder="e.g. Lot 1 - Riverside">
          </div>
          <div class="form-group">
            <label for="sector_id">Sector</label>
            <select id="sector_id" name="sector_id">
              <option value="">— Unassigned —</option>
              <?php foreach ($sectors as $s): ?>
                <option value="<?= (int) $s['sector_id'] ?>"
                  <?= ($editingLot['sector_id'] ?? null) == $s['sector_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($s['sector_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
	  
        <div class="form-row">
          <div class="form-group">
            <label for="land_area_ha">Land Area (hectares)</label>
            <input type="number" step="0.01" min="0.01" id="land_area_ha" name="land_area_ha" required
                   value="<?= htmlspecialchars($editingLot['land_area_ha'] ?? '') ?>">
          </div>
        </div>
	  
        <div class="form-group">
          <label>Farm Lot Boundary</label>
          <p class="muted-note" style="margin:0 0 0.5rem;">
            Draw the lot's actual shape on the map below (click to place each corner,
            click the first point again to close the polygon). This is used to compute the
            lot's center point automatically — for the map views and for sector
            auto-assignment below — so there's no separate coordinate field to fill in.
            The boundary is saved together with the rest of this form; there's no separate
            save step.
          </p>

          <div id="lotBoundaryAlert"></div>
          <div id="lotBoundaryMap" class="map-container map-container-small"></div>
          <p id="lotBoundaryStatus" class="muted-note" style="margin-top:0.5rem;">
            <?= $editingLotBoundary ? 'This lot has a drawn boundary. Edit it above if needed, or draw a new one to replace it.' : 'No boundary drawn yet — this lot will have no map location until one is drawn.' ?>
          </p>
          <button type="button" id="btnClearBoundaryDraft" class="link-btn deactivate" style="font-size:0.85rem;">
            Clear drawn boundary
          </button>
          <input type="hidden" id="boundary_points" name="boundary_points" value="<?= $editingLotBoundary ? htmlspecialchars(json_encode($editingLotBoundary, JSON_UNESCAPED_SLASHES)) : '' ?>">
        </div>
	  
        <div class="form-group">
          <label>
            <input type="checkbox" name="auto_assign_sector" style="width:auto; display:inline-block;"
              <?= isset($_POST['auto_assign_sector']) || $pendingAssignmentPreview ? 'checked' : '' ?>>
            Auto-assign sector from drawn sector boundaries, based on the boundary's center point
          </label>
          <p class="muted-note" style="margin:0.3rem 0 0;">
            If checked and the drawn boundary's center point falls inside a sector boundary
            drawn on the <a href="<?= base_url('officer/sector_boundaries.php') ?>">Sector Boundaries map</a>,
            the sector above will be set automatically when you save. Requires a boundary to
            be drawn above — there's nothing to check against without one.
          </p>
        </div>
	  
        <div class="form-group">
          <label for="notes">Notes (optional)</label>
          <input type="text" id="notes" name="notes" value="<?= htmlspecialchars($editingLot['notes'] ?? '') ?>">
        </div>
	  
        <?php if ($pendingAssignmentPreview): ?>
          <div class="alert alert-error">
            <strong>Heads up:</strong> this boundary's center point falls inside
            "<?= htmlspecialchars($pendingAssignmentPreview['matched_sector']['sector_name']) ?>",
            which is different from the sector currently selected above.
            <?php if ($pendingAssignmentPreview['match_count'] > 1): ?>
              (It also overlaps with <?= $pendingAssignmentPreview['match_count'] - 1 ?> other drawn
              sector boundary/boundaries — "<?= htmlspecialchars($pendingAssignmentPreview['matched_sector']['sector_name']) ?>"
              was used since sector boundaries currently overlap here.)
            <?php endif; ?>
            Saving with auto-assign checked will change this lot's sector. Confirm to proceed,
            or uncheck auto-assign / adjust the sector manually above.
          </div>
          <input type="hidden" name="confirm_overwrite_sector" value="1">
          <button type="submit" class="btn-full" style="max-width:260px; background:var(--clr-error);">
            Confirm — Overwrite Sector &amp; Save
          </button>
          <a href="<?= base_url('officer/member_view.php?id=' . $memberId) ?>" style="margin-left:1rem;">Cancel</a>
        <?php else: ?>
          <button type="submit" class="btn-full" style="max-width:220px;">
            <?= $editingLot ? 'Update Lot' : 'Add Lot' ?>
          </button>
          <a href="<?= base_url('officer/member_view.php?id=' . $memberId) ?>" style="margin-left:1rem;">Cancel</a>
        <?php endif; ?>
      </form>
	<?php endif; ?>
  </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
<script>
  window.BAMBOSA_FARM_LOT_BOUNDARY = {
    boundary: <?= $editingLotBoundary ? json_encode($editingLotBoundary, JSON_UNESCAPED_SLASHES) : 'null' ?>,
    hiddenInputId: 'boundary_points',
    clearButtonId: 'btnClearBoundaryDraft',
    statusElementId: 'lotBoundaryStatus',
    defaultCenter: <?= $mapDefaultCenter ? json_encode($mapDefaultCenter) : 'null' ?>
  };
</script>
<script src="<?= base_url('assets/js/farm-lot-boundary.js') ?>"></script>
</body>
</html>
