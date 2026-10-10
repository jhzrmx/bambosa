<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/ip_lookup.php';

require_role('admin');

$address = trim((string) ($_GET['address'] ?? ''));

$state  = 'invalid';   // invalid | private | error | ok
$info   = null;
$error  = null;
$cached = false;

if (is_valid_ip($address)) {
    if (!is_public_ip($address)) {
        $state = 'private';
    } else {
        $result = lookup_ip($address);
        if ($result['ok']) {
            $state  = 'ok';
            $info   = $result['data'];
            $cached = $result['cached'];
        } else {
            $state = 'error';
            $error = $result['error'];
        }
    }
}

$hasCoords = $state === 'ok' && isset($info['lat'], $info['lon']) && is_numeric($info['lat']) && is_numeric($info['lon']);

// "City, Region, Country" without empty parts
$placeParts = [];
if ($state === 'ok') {
    foreach (['city', 'regionName', 'country'] as $k) {
        if (!empty($info[$k])) {
            $placeParts[] = $info[$k];
        }
    }
}
$place = implode(', ', $placeParts);

/** Renders one label/value row; skipped when the value is empty. */
function ip_row(string $label, ?string $value): void
{
    if ($value === null || trim($value) === '') {
        return;
    }
    echo '<dt>' . htmlspecialchars($label) . '</dt><dd>' . htmlspecialchars($value) . '</dd>';
}

/** Yes/No badge row. */
function ip_flag_row(string $label, $flag): void
{
    $yes = (bool) $flag;
    echo '<dt>' . htmlspecialchars($label) . '</dt><dd><span class="ip-flag ' . ($yes ? 'ip-flag-yes' : 'ip-flag-no') . '">'
        . ($yes ? 'Yes' : 'No') . '</span></dd>';
}

$activeNav = 'login_logs';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
<link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<title>IP Address Details · BAMBOSA Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/admin_sidebar.php'; ?>

  <main class="dash-main">
    <p style="margin-bottom:0.4rem;"><a href="<?= base_url('admin/login_logs.php') ?>">&larr; Back to User Login Logs</a></p>
    <h1>IP Address Details</h1>

    <?php if ($state === 'invalid'): ?>
      <div class="alert alert-error">
        <?= $address === '' ? 'No IP address was given.' : 'That is not a valid IP address.' ?>
        Open this page by clicking an IP address in the
        <a href="<?= base_url('admin/login_logs.php') ?>">User Login Logs</a>.
      </div>

    <?php elseif ($state === 'private'): ?>
      <div class="ip-card">
        <div class="ip-address"><?= htmlspecialchars($address) ?></div>
        <p>
          This is a <strong>local or private address</strong> (for example localhost, or a device on a
          home/office network), so it has no public location or ISP to look up. You'll normally see
          these when the system is used on the same computer or local network that hosts it.
        </p>
      </div>

    <?php elseif ($state === 'error'): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <div class="ip-card">
        <div class="ip-address"><?= htmlspecialchars($address) ?></div>
        <p><a href="<?= base_url('admin/ip.php?address=' . urlencode($address)) ?>">Try again</a></p>
      </div>

    <?php else: ?>
      <div class="ip-layout">
        <div class="ip-map-col">
          <?php if ($hasCoords): ?>
            <div id="ipMap" class="map-container ip-map"></div>
          <?php else: ?>
            <div class="map-container ip-map map-container-disabled">
              <p class="muted-note" style="margin:0; padding:1rem;">No map coordinates are available for this address.</p>
            </div>
          <?php endif; ?>
        </div>

        <div class="ip-info-col">
          <div class="ip-card">
            <div class="ip-address"><?= htmlspecialchars($info['query'] ?? $address) ?></div>
            <?php if ($place !== ''): ?>
              <div class="ip-place"><?= htmlspecialchars($place) ?></div>
            <?php endif; ?>

            <h2>Location</h2>
            <dl class="ip-rows">
              <?php
              ip_row('Country', !empty($info['country']) ? $info['country'] . (!empty($info['countryCode']) ? ' (' . $info['countryCode'] . ')' : '') : null);
              ip_row('Continent', $info['continent'] ?? null);
              ip_row('Region', !empty($info['regionName']) ? $info['regionName'] . (!empty($info['region']) ? ' (' . $info['region'] . ')' : '') : null);
              ip_row('City', $info['city'] ?? null);
              ip_row('District', $info['district'] ?? null);
              ip_row('ZIP / Postal code', $info['zip'] ?? null);
              ip_row('Coordinates', $hasCoords ? ((float) $info['lat']) . ', ' . ((float) $info['lon']) : null);
              ip_row('Timezone', !empty($info['timezone']) ? $info['timezone'] . (isset($info['offset']) ? ' (' . format_utc_offset((int) $info['offset']) . ')' : '') : null);
              ip_row('Local time there', isset($info['offset']) ? gmdate('M j, Y g:i A', time() + (int) $info['offset']) : null);
              ip_row('Currency', $info['currency'] ?? null);
              ?>
            </dl>

            <h2>Network</h2>
            <dl class="ip-rows">
              <?php
              ip_row('ISP', $info['isp'] ?? null);
              ip_row('Organization', $info['org'] ?? null);
              ip_row('AS number / name', trim(($info['as'] ?? '') . ((!empty($info['asname'])) ? ' — ' . $info['asname'] : '')) ?: null);
              ip_row('Reverse DNS', $info['reverse'] ?? null);
              ip_flag_row('Mobile network', $info['mobile'] ?? false);
              ip_flag_row('Proxy / VPN', $info['proxy'] ?? false);
              ip_flag_row('Hosting / data center', $info['hosting'] ?? false);
              ?>
            </dl>
          </div>

          <p class="muted-note">
            IP geolocation is approximate (usually accurate to the city or ISP, not a street address).
            Data from <a href="https://ip-api.com" target="_blank" rel="noopener">ip-api.com</a><?= $cached ? ' (cached, up to 24 hours old)' : '' ?>.
          </p>
        </div>
      </div>
    <?php endif; ?>
  </main>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
<?php if ($hasCoords): ?>
<script>
(function () {
  var lat = <?= json_encode((float) $info['lat']) ?>;
  var lon = <?= json_encode((float) $info['lon']) ?>;
  var label = <?= json_encode($place !== '' ? $place : (string) $address, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

  var map = L.map('ipMap').setView([lat, lon], 10);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);

  // IP geolocation is only city-level, so show an approximate-area circle
  // as well as the point itself.
  L.circle([lat, lon], { radius: 5000, color: '#2e9cca', weight: 1, fillColor: '#2e9cca', fillOpacity: 0.15 }).addTo(map);
  var pin = L.circleMarker([lat, lon], { radius: 8, color: '#155c39', weight: 2, fillColor: '#1f7a4d', fillOpacity: 0.95 }).addTo(map);

  var popup = document.createElement('div');
  popup.className = 'map-popup';
  var strong = document.createElement('strong');
  strong.textContent = label;           // textContent: never interpreted as HTML
  popup.appendChild(strong);
  pin.bindPopup(popup);
})();
</script>
<?php endif; ?>
</body>
</html>
