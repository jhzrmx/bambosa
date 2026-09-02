<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';

require_role('officer');

$mappedLots = get_mapped_farm_lots($pdo);
$unmappedCount = count_unmapped_farm_lots($pdo);

// Build a simple, stable color per sector name so the same sector always
// gets the same marker color across page loads.
$palette = ['#1f7a4d', '#2e9cca', '#c0873a', '#8e44ad', '#c0392b', '#16a085'];
$sectorColors = [];
$colorIndex = 0;
foreach ($mappedLots as $lot) {
    $key = $lot['sector_name'] ?? 'Unassigned';
    if (!isset($sectorColors[$key])) {
        $sectorColors[$key] = $palette[$colorIndex % count($palette)];
        $colorIndex++;
    }
}

// Prepare a lightweight JSON payload for the map — only what Leaflet needs.
$mapPoints = array_map(function ($lot) use ($sectorColors) {
    $sectorName = $lot['sector_name'] ?? 'Unassigned';
    return [
        'lat'       => (float) $lot['latitude'],
        'lng'       => (float) $lot['longitude'],
        'lotName'   => $lot['lot_name'],
        'owner'     => $lot['owner_name'],
        'sector'    => $sectorName,
        'area'      => (float) $lot['land_area_ha'],
        'color'     => $sectorColors[$sectorName],
        'ownerLink' => base_url('officer/member_view.php?id=' . (int) $lot['user_id']),
    ];
}, $mappedLots);

$activeNav = 'map';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
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
      A geographic view of registered farm lots across Barangay Bikal, built from the
      coordinates recorded on each member's <a href="<?= base_url('officer/members.php') ?>">farm lot record</a>.
      This is the platform's web-facing equivalent of the QGIS mapping described in the
      project scope — coordinates are entered once per lot and plotted here automatically.
    </p>

    <?php if ($unmappedCount > 0): ?>
      <div class="alert alert-error">
        <?= $unmappedCount ?> farm lot<?= $unmappedCount === 1 ? '' : 's' ?> 
        <?= $unmappedCount === 1 ? 'has' : 'have' ?> no coordinates yet and can't be shown on the map.
        Add latitude/longitude when editing a farm lot on a member's page to include it.
      </div>
    <?php endif; ?>

    <?php if (empty($mappedLots)): ?>
      <p class="muted-note">No farm lots with coordinates yet. Once officers add latitude/longitude to a farm lot, it will appear here.</p>
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
        <?= count($mappedLots) ?> farm lot<?= count($mappedLots) === 1 ? '' : 's' ?> shown.
        Tap a marker for details, or tap "View member" to open that member's record.
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

  var map = L.map('farmMap');
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);

  var markers = [];
  points.forEach(function (p) {
    var marker = L.circleMarker([p.lat, p.lng], {
      radius: 9,
      color: '#1b2b23',
      weight: 1.5,
      fillColor: p.color,
      fillOpacity: 0.85
    }).addTo(map);

    var popupHtml =
      '<div class="map-popup">' +
        '<strong>' + escapeHtml(p.lotName) + '</strong><br>' +
        'Owner: ' + escapeHtml(p.owner) + '<br>' +
        'Sector: ' + escapeHtml(p.sector) + '<br>' +
        'Area: ' + p.area.toFixed(2) + ' ha<br>' +
        '<a href="' + escapeHtml(p.ownerLink) + '">View member</a>' +
      '</div>';

    marker.bindPopup(popupHtml);
    markers.push(marker);
  });

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  if (markers.length === 1) {
    map.setView([points[0].lat, points[0].lng], 15);
  } else {
    var group = L.featureGroup(markers);
    map.fitBounds(group.getBounds().pad(0.15));
  }
})();
</script>
<?php endif; ?>
</body>
</html>
