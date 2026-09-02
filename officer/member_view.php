<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';

require_role('officer');

$memberId = (int) ($_GET['id'] ?? 0);
$member = $memberId ? get_user_by_id($pdo, $memberId) : null;

if (!$member || $member['role'] !== 'member') {
    header('Location: ' . base_url('officer/members.php'));
    exit;
}

$errors = [];
$editingLot = null;

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

    $lotId      = (int) ($_POST['lot_id'] ?? 0);
    $lotName    = trim($_POST['lot_name'] ?? '');
    $sectorId   = (int) ($_POST['sector_id'] ?? 0) ?: null;
    $landArea   = $_POST['land_area_ha'] ?? '';
    $latitude   = trim($_POST['latitude'] ?? '');
    $longitude  = trim($_POST['longitude'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    if ($lotName === '') {
        $errors[] = 'Lot name/label is required.';
    }
    if (!is_numeric($landArea) || (float) $landArea <= 0) {
        $errors[] = 'Land area must be a positive number (in hectares).';
    }
    if ($latitude !== '' && !is_numeric($latitude)) {
        $errors[] = 'Latitude must be numeric.';
    }
    if ($longitude !== '' && !is_numeric($longitude)) {
        $errors[] = 'Longitude must be numeric.';
    }

    if (empty($errors)) {
        $data = [
            'user_id'      => $memberId,
            'sector_id'    => $sectorId,
            'lot_name'     => $lotName,
            'land_area_ha' => (float) $landArea,
            'latitude'     => $latitude !== '' ? (float) $latitude : null,
            'longitude'    => $longitude !== '' ? (float) $longitude : null,
            'notes'        => $notes,
        ];

        if ($lotId > 0) {
            update_farm_lot($pdo, $lotId, $data);
        } else {
            create_farm_lot($pdo, $data);
        }

        header('Location: ' . base_url('officer/member_view.php?id=' . $memberId . '&flash=lot_saved'));
        exit;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($member['full_name']) ?> · BAMBOSA Officer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
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

    <h2 class="section-heading">Farm Lots</h2>

    <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>Lot Name</th>
          <th>Sector</th>
          <th>Land Area (ha)</th>
          <th>Coordinates</th>
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
              <?= $lot['latitude'] !== null ? htmlspecialchars($lot['latitude']) . ', ' . htmlspecialchars($lot['longitude']) : '—' ?>
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

      <div class="form-row">
        <div class="form-group">
          <label for="latitude">Latitude (optional — for QGIS mapping)</label>
          <input type="text" id="latitude" name="latitude"
                 value="<?= htmlspecialchars($editingLot['latitude'] ?? '') ?>" placeholder="e.g. 13.7480">
        </div>
        <div class="form-group">
          <label for="longitude">Longitude (optional — for QGIS mapping)</label>
          <input type="text" id="longitude" name="longitude"
                 value="<?= htmlspecialchars($editingLot['longitude'] ?? '') ?>" placeholder="e.g. 122.9430">
        </div>
      </div>

      <div class="form-group">
        <label for="notes">Notes (optional)</label>
        <input type="text" id="notes" name="notes" value="<?= htmlspecialchars($editingLot['notes'] ?? '') ?>">
      </div>

      <button type="submit" class="btn-full" style="max-width:220px;">
        <?= $editingLot ? 'Update Lot' : 'Add Lot' ?>
      </button>
      <?php if ($editingLot): ?>
        <a href="<?= base_url('officer/member_view.php?id=' . $memberId) ?>" style="margin-left:1rem;">Cancel</a>
      <?php endif; ?>
    </form>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
