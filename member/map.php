<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';
require_once __DIR__ . '/../includes/farm_lot_boundaries.php';

require_role('member');

$userId = (int) $_SESSION['user_id'];
$allLots = get_farm_lots_by_user($pdo, $userId);
$lotBoundaries = get_farm_lots_with_boundaries($pdo, $userId);
$boundaryByLotId = [];
foreach ($lotBoundaries as $lb) {
    if (count($lb['boundary']) >= 3) {
        $boundaryByLotId[(int) $lb['lot_id']] = $lb['boundary'];
    }
}

// A lot is "mapped" if it has a location at all — a drawn polygon, or (for
// any older/unusual record that somehow has a point but no polygon) just
// the coordinate point on its own.
$mappedLots = array_filter($allLots, fn($lot) => $lot['latitude'] !== null && $lot['longitude'] !== null);
$unmappedCount = count($allLots) - count($mappedLots);

// Polygons to draw: every mapped lot that has one.
$boundaryPolygons = [];
foreach ($mappedLots as $lot) {
    $lotId = (int) $lot['lot_id'];
    if (isset($boundaryByLotId[$lotId])) {
        $boundaryPolygons[] = [
            'lotName'  => $lot['lot_name'],
            'sector'   => $lot['sector_name'] ?? 'Unassigned',
            'area'     => (float) $lot['land_area_ha'],
            'boundary' => $boundaryByLotId[$lotId],
        ];
    }
}

// Point markers: only for mapped lots that do NOT have a polygon — once a
// lot has a drawn boundary, the boundary is shown instead of a redundant
// point marker at its centroid.
$mapPoints = [];
foreach ($mappedLots as $lot) {
    $lotId = (int) $lot['lot_id'];
    if (!isset($boundaryByLotId[$lotId])) {
        $mapPoints[] = [
            'lat'     => (float) $lot['latitude'],
            'lng'     => (float) $lot['longitude'],
            'lotName' => $lot['lot_name'],
            'sector'  => $lot['sector_name'] ?? 'Unassigned',
            'area'    => (float) $lot['land_area_ha'],
        ];
    }
}

$activeNav = 'map';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Farm Map · BAMBOSA Member</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/member_sidebar.php'; ?>

  <main class="dash-main">
    <h1>My Farm Map</h1>
    <p>A map of your registered farm lot(s), drawn as their actual shape where an officer has mapped one. Contact an officer if a boundary needs to be added or corrected.</p>

    <?php if (empty($allLots)): ?>
      <p class="muted-note">You don't have any farm lots registered yet. Contact an officer to have one added.</p>
    <?php elseif (empty($mappedLots)): ?>
      <p class="muted-note">Your farm lot(s) don't have a boundary drawn yet. Contact an officer to have your lot's shape added to the map.</p>
    <?php else: ?>

      <?php if ($unmappedCount > 0): ?>
        <div class="alert alert-error">
          <?= $unmappedCount ?> of your farm lot<?= $unmappedCount === 1 ? '' : 's' ?> 
          <?= $unmappedCount === 1 ? 'has' : 'have' ?> no boundary drawn yet and can't be shown below.
        </div>
      <?php endif; ?>

      <div id="farmMap" class="map-container map-container-small"></div>

      <p class="muted-note" style="margin-top:0.75rem;">
        <?= count($mappedLots) ?> of your farm lot<?= count($mappedLots) === 1 ? '' : 's' ?> shown.
      </p>
    <?php endif; ?>
  </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
<?php if (!empty($mappedLots)): ?>
<script>
(function () {
  var points = <?= json_encode($mapPoints, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var boundaries = <?= json_encode($boundaryPolygons, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

  var map = L.map('farmMap');
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);

  var fitTargets = [];

  boundaries.forEach(function (b) {
    if (!b.boundary || b.boundary.length < 3) return;
    var poly = L.polygon(b.boundary, {
      color: '#1f7a4d',
      weight: 2,
      opacity: 0.7,
      fillOpacity: 0.15
    });
    var boundaryPopupHtml =
      '<div class="map-popup">' +
        '<strong>' + escapeHtml(b.lotName) + '</strong><br>' +
        'Sector: ' + escapeHtml(b.sector) + '<br>' +
        'Area: ' + b.area.toFixed(2) + ' ha' +
      '</div>';
    poly.bindPopup(boundaryPopupHtml);
    poly.addTo(map);
    b.boundary.forEach(function (pt) { fitTargets.push(pt); });
  });

  var markers = [];
  points.forEach(function (p) {
    var marker = L.circleMarker([p.lat, p.lng], {
      radius: 9,
      color: '#1b2b23',
      weight: 1.5,
      fillColor: '#1f7a4d',
      fillOpacity: 0.85
    }).addTo(map);

    var pointPopupHtml =
      '<div class="map-popup">' +
        '<strong>' + escapeHtml(p.lotName) + '</strong><br>' +
        'Sector: ' + escapeHtml(p.sector) + '<br>' +
        'Area: ' + p.area.toFixed(2) + ' ha' +
      '</div>';

    marker.bindPopup(pointPopupHtml);
    markers.push(marker);
    fitTargets.push([p.lat, p.lng]);
  });

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  if (fitTargets.length === 1) {
    map.setView(fitTargets[0], 15);
  } else if (fitTargets.length > 1) {
    map.fitBounds(L.latLngBounds(fitTargets).pad(0.15));
  }
})();
</script>
<?php endif; ?>
</body>
</html>
