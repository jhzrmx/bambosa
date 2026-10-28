<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';
require_once __DIR__ . '/../includes/sector_boundaries.php';
require_once __DIR__ . '/../includes/farm_lot_boundaries.php';

require_role('officer');

$sectorsWithBoundaries = get_sectors_with_boundaries($pdo);
$lotShapes = array_values(array_filter(
    get_farm_lots_with_boundaries($pdo),
    fn($lb) => count($lb['boundary']) >= 3
));
$unmappedCount = count_farm_lots_without_boundary($pdo);

// Build a simple, stable color per sector name so the same sector always
// gets the same color across page loads.
$palette = ['#1f7a4d', '#2e9cca', '#c0873a', '#8e44ad', '#c0392b', '#16a085'];
$sectorColors = [];
$colorIndex = 0;
foreach ($lotShapes as $lot) {
    $key = $lot['sector_name'] ?? 'Unassigned';
    if (!isset($sectorColors[$key])) {
        $sectorColors[$key] = $palette[$colorIndex % count($palette)];
        $colorIndex++;
    }
}
// Make sure every sector that has a boundary also gets a color, even if it
// currently has no farm lots drawn (so its polygon still renders sensibly).
foreach ($sectorsWithBoundaries as $sb) {
    if (!isset($sectorColors[$sb['sector_name']])) {
        $sectorColors[$sb['sector_name']] = $palette[$colorIndex % count($palette)];
        $colorIndex++;
    }
}

// Sector boundary polygons (context layer).
$boundaryPolygons = array_map(function ($sb) use ($sectorColors) {
    return [
        'name'     => $sb['sector_name'],
        'color'    => $sectorColors[$sb['sector_name']],
        'boundary' => $sb['boundary'],
    ];
}, $sectorsWithBoundaries);

// Farm lots are drawn as their boundary polygons (boundary_geojson) — the
// latitude/longitude columns are only the precomputed centroid of these
// shapes, so they are no longer plotted as pins. Each shape stays clickable
// and links to its owner's record, exactly like the old pin did.
$lotBoundaryPolygons = array_map(function ($lb) use ($sectorColors) {
    $sectorName = $lb['sector_name'] ?? 'Unassigned';
    return [
        'lotName'   => $lb['lot_name'],
        'owner'     => $lb['owner_name'],
        'sector'    => $sectorName,
        'area'      => (float) $lb['land_area_ha'],
        'color'     => $sectorColors[$sectorName],
        'boundary'  => $lb['boundary'],
        'ownerLink' => base_url('officer/member_view.php?id=' . (int) $lb['user_id']),
    ];
}, $lotShapes);

$activeNav = 'map';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
<link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<title>Member Map · BAMBOSA Officer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/officer_sidebar.php'; ?>

  <main class="dash-main">
    <h1>Member Farm Map</h1>
    <p>
      A geographic view of registered farm lots across Barangay Bikal, drawn as the boundary
      shapes officers mapped on each member's <a href="<?= base_url('officer/members.php') ?>">farm lot record</a>,
      with any drawn <a href="<?= base_url('officer/sector_boundaries.php') ?>">sector boundaries</a> shown for context.
      This is the platform's web-facing equivalent of the QGIS mapping described in the
      project scope — each lot's boundary is drawn once and plotted here automatically.
    </p>

    <?php if ($unmappedCount > 0): ?>
      <div class="alert alert-error">
        <?= $unmappedCount ?> farm lot<?= $unmappedCount === 1 ? '' : 's' ?> 
        <?= $unmappedCount === 1 ? 'has' : 'have' ?> no boundary drawn yet and can't be shown on the map.
        Draw its boundary when editing a farm lot on a member's page to include it.
      </div>
    <?php endif; ?>

    <?php if (empty($lotBoundaryPolygons)): ?>
      <p class="muted-note">No farm lots with a drawn boundary yet. Once officers draw a boundary for a farm lot, it will appear here.</p>
    <?php else: ?>

      <div class="map-legend">
        <?php foreach ($sectorColors as $sectorName => $color): ?>
          <span class="legend-item">
            <span class="legend-swatch" style="background:<?= htmlspecialchars($color) ?>;"></span>
            <?= htmlspecialchars($sectorName) ?>
          </span>
        <?php endforeach; ?>
      </div>

      <div id="farmMap" class="map-container"></div>

      <p class="muted-note" style="margin-top:0.75rem;">
        <?= count($lotBoundaryPolygons) ?> farm lot<?= count($lotBoundaryPolygons) === 1 ? '' : 's' ?> shown, colored by sector.
        Tap a lot for details, or tap "View member" to open that member's record.
      </p>
    <?php endif; ?>
  </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
<?php if (!empty($lotBoundaryPolygons)): // the #farmMap div only exists when there are lot shapes to draw ?>
<script>
(function () {
  var boundaries = <?= json_encode($boundaryPolygons, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var lotBoundaries = <?= json_encode($lotBoundaryPolygons, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

  var map = BAMBOSA.createMap('farmMap');
  var escapeHtml = BAMBOSA.escapeHtml;

  var fitTargets = [];

  // Draw sector boundaries first so the farm lot shapes render on top of them.
  boundaries.forEach(function (b) {
    if (!b.boundary || b.boundary.length < 3) return;
    var poly = L.polygon(b.boundary, {
      color: b.color,
      weight: 2,
      opacity: 0.7,
      fillOpacity: 0.1,
      dashArray: '4,4'
    }).bindTooltip(b.name, { sticky: true });
    poly.addTo(map);
    b.boundary.forEach(function (pt) { fitTargets.push(pt); });
  });

  // Farm lots: their drawn boundary, filled with the lot's sector color.
  // Clicking one opens the same details popup the old pin had, including
  // the "View member" link to the owner's record.
  lotBoundaries.forEach(function (lb) {
    if (!lb.boundary || lb.boundary.length < 3) return;
    var poly = L.polygon(lb.boundary, {
      color: '#1b2b23',
      weight: 1.5,
      fillColor: lb.color,
      fillOpacity: 0.55
    }).addTo(map);

    var popupHtml =
      '<div class="map-popup">' +
        '<strong>' + escapeHtml(lb.lotName) + '</strong><br>' +
        'Owner: ' + escapeHtml(lb.owner) + '<br>' +
        'Sector: ' + escapeHtml(lb.sector) + '<br>' +
        'Area: ' + lb.area.toFixed(2) + ' ha<br>' +
        '<a href="' + escapeHtml(lb.ownerLink) + '">View member</a>' +
      '</div>';

    poly.bindPopup(popupHtml);
    lb.boundary.forEach(function (pt) { fitTargets.push(pt); });
  });

  if (fitTargets.length > 0) {
    map.fitBounds(L.latLngBounds(fitTargets).pad(0.15));
  } else {
    // No farm lots plotted yet — default to Barangay Bikal, Libmanan, Camarines Sur.
    map.setView([13.723053, 122.986048], 14);
  }
})();
</script>
<?php endif; ?>
</body>
</html>
