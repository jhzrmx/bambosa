<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';
require_once __DIR__ . '/../includes/sector_boundaries.php';

require_role('officer');

$sectors = get_sectors($pdo);
$sectorsWithBoundaries = get_sectors_with_boundaries($pdo);
$mappedLots = get_mapped_farm_lots($pdo);
$recentAssignments = get_recent_auto_assignments($pdo, 15);

// Build a stable color per sector (same palette convention as officer/map.php).
$palette = ['#1f7a4d', '#2e9cca', '#c0873a', '#8e44ad', '#c0392b', '#16a085'];
$sectorColors = [];
foreach ($sectors as $i => $s) {
    $sectorColors[(int) $s['sector_id']] = $palette[$i % count($palette)];
}

$sectorsJson = array_map(function ($s) use ($sectorColors) {
    return [
        'id'          => (int) $s['sector_id'],
        'name'        => $s['sector_name'],
        'color'       => $sectorColors[(int) $s['sector_id']],
        'hasBoundary' => false,
        'boundary'    => null,
    ];
}, $sectors);

// Merge in boundary data for sectors that have one drawn.
$byId = [];
foreach ($sectorsJson as $i => $s) {
    $byId[$s['id']] = $i;
}
foreach ($sectorsWithBoundaries as $sb) {
    $id = (int) $sb['sector_id'];
    if (isset($byId[$id])) {
        $sectorsJson[$byId[$id]]['hasBoundary'] = true;
        $sectorsJson[$byId[$id]]['boundary'] = $sb['boundary']; // array of [lat, lng]
    }
}

$lotsJson = array_map(function ($lot) {
    return [
        'lotId'    => (int) $lot['lot_id'],
        'lotName'  => $lot['lot_name'],
        'owner'    => $lot['owner_name'],
        'lat'      => (float) $lot['latitude'],
        'lng'      => (float) $lot['longitude'],
        'sectorId' => $lot['sector_id'] !== null ? (int) $lot['sector_id'] : null,
    ];
}, $mappedLots);

$activeNav = 'boundaries';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sector Boundaries · BAMBOSA Officer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/officer_sidebar.php'; ?>

  <main class="dash-main">
    <h1>Sector Boundaries</h1>
    <p>
      Draw a polygon for each sector's boundary. Farm lots whose coordinates fall inside
      a boundary can be auto-assigned to that sector — no GIS software or exported
      boundary file needed. This is a stand-in for a real QGIS-drawn boundary dataset,
      using the same point-in-polygon logic a GIS spatial join would use.
    </p>

    <div id="boundaryAlert"></div>

    <div class="form-group" style="max-width:320px;">
      <label for="sectorSelect">Sector to edit</label>
      <select id="sectorSelect">
        <?php foreach ($sectorsJson as $s): ?>
          <option value="<?= (int) $s['id'] ?>">
            <?= htmlspecialchars($s['name']) ?><?= $s['hasBoundary'] ? ' (boundary drawn)' : ' (no boundary yet)' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <?php if (empty($sectorsJson)): ?>
      <p class="muted-note">
        No sectors exist yet. <a href="<?= base_url('officer/sectors.php') ?>">Create a sector</a> first, then come back here to draw its boundary.
      </p>
    <?php else: ?>

      <div class="map-legend" id="boundaryLegend"></div>

      <div class="form-group" style="margin-bottom:0.6rem;">
        <label>
          <input type="checkbox" id="toggleLotMarkers" style="width:auto; display:inline-block;" checked>
          Show farm lots on map
        </label>
      </div>

      <div id="boundaryMap" class="map-container"></div>

      <div class="inline-card-form" style="margin-top:1.2rem;">
        <p id="matchCountText" class="muted-note" style="margin-top:0;">
          Draw or edit a polygon above, then click "Preview Matches" to see how many farm lots fall inside it.
        </p>

        <div class="btn-row">
          <button type="button" id="btnPreview" class="btn-full">Preview Matches</button>
          <button type="button" id="btnSave" class="btn-full">Save Boundary</button>
          <button type="button" id="btnClear" class="btn-full" style="background:var(--clr-error);">Clear Boundary</button>
        </div>

        <div class="form-group" style="margin-top:1rem;">
          <label>
            <input type="checkbox" id="runAssignmentOnSave" style="width:auto; display:inline-block;" checked>
            Run auto-assignment for all farm lots after saving this boundary
          </label>
          <p class="muted-note" style="margin:0.3rem 0 0;">
            Only farm lots whose sector would actually change are updated; lots already
            correctly assigned are left alone. Overlapping sector boundaries are allowed —
            when a lot matches more than one, the first matching sector is used.
          </p>
        </div>
      </div>

      <h2 class="section-heading">Recent Auto-Assignments</h2>
      <p class="muted-note">A log of farm lots whose sector was changed automatically by boundary matching (as opposed to a manual sector edit).</p>

      <div class="table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th>When</th>
            <th>Farm Lot</th>
            <th>Owner</th>
            <th>Previous Sector</th>
            <th>New Sector</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentAssignments)): ?>
            <tr><td colspan="5" class="empty-row">No auto-assignments have run yet.</td></tr>
          <?php else: foreach ($recentAssignments as $a): ?>
            <tr>
              <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($a['assigned_at']))) ?></td>
              <td><?= htmlspecialchars($a['lot_name']) ?></td>
              <td><?= htmlspecialchars($a['owner_name']) ?></td>
              <td><?= htmlspecialchars($a['previous_sector_name'] ?? '— (unassigned)') ?></td>
              <td><?= htmlspecialchars($a['new_sector_name']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
      </div>

    <?php endif; ?>
  </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
<?php if (!empty($sectorsJson)): ?>
<script>
  window.BAMBOSA_SECTOR_BOUNDARIES = {
    sectors: <?= json_encode($sectorsJson, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    lots: <?= json_encode($lotsJson, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    csrfToken: <?= json_encode(csrf_token()) ?>,
    saveUrl: <?= json_encode(base_url('officer/sector_boundary_save.php')) ?>,
    previewUrl: <?= json_encode(base_url('officer/sector_boundary_preview.php')) ?>
  };
</script>
<script src="<?= base_url('assets/js/sector-boundaries.js') ?>"></script>
<?php endif; ?>
</body>
</html>
