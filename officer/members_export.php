<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';
require_once __DIR__ . '/../includes/members_export.php';

require_role('officer');

$sectors   = get_sectors($pdo);
$sectorMap = [];
foreach ($sectors as $s) {
    $sectorMap[(int) $s['sector_id']] = $s['sector_name'];
}
$xlsxAvailable = xlsx_library_path() !== null;

// Current form values (also the defaults on first visit)
$format  = ($_GET['format'] ?? 'xlsx') === 'csv' ? 'csv' : 'xlsx';
$layout  = ($_GET['layout'] ?? 'member') === 'lot' ? 'lot' : 'member';
$status  = $_GET['status'] ?? '';
$status  = in_array($status, MEMBER_EXPORT_STATUSES, true) ? $status : '';
$sectorP = (string) ($_GET['sector'] ?? '');   // '' = all, 'none' = unassigned, or a sector id
if ($sectorP !== '' && $sectorP !== 'none' && !isset($sectorMap[(int) $sectorP])) {
    $sectorP = '';
}
if (!$xlsxAvailable) {
    $format = 'csv';
}

$errors = [];

if (isset($_GET['export'])) {
    $sectorFilter = $sectorP === '' ? null : ($sectorP === 'none' ? 0 : (int) $sectorP);
    $rows = get_members_for_export($pdo, $layout, $sectorFilter, $status === '' ? null : $status);

    if (empty($rows)) {
        $errors[] = 'No members match these filters, so there is nothing to export.';
    } else {
        $sectorLabel = $sectorFilter === null ? 'all-sectors'
            : ($sectorFilter === 0 ? 'no-sector' : $sectorMap[$sectorFilter]);
        $slug = trim(preg_replace('/[^A-Za-z0-9]+/', '-', $sectorLabel), '-');
        $filename = 'bambosa_members_' . ($slug !== '' ? $slug : 'export') . '_' . date('Ymd_His');

        if ($format === 'xlsx') {
            $filterSummary = 'Sector: ' . ($sectorFilter === null ? 'All sectors' : ($sectorFilter === 0 ? 'No sector assigned' : $sectorMap[$sectorFilter]))
                . '  |  Status: ' . ($status === '' ? 'All statuses' : ucfirst($status));
            if (output_members_xlsx($rows, $layout, $filename, 'Members', $filterSummary)) {
                exit;
            }
            $errors[] = 'Excel export is unavailable (the XLSX library is missing). Please choose CSV.';
        } else {
            output_members_csv($rows, $layout, $filename);
            exit;
        }
    }
}

$activeNav = 'members';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
<link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<title>Export Members · BAMBOSA Officer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/officer_sidebar.php'; ?>

  <main class="dash-main">
    <p style="margin-bottom:0.4rem;"><a href="<?= base_url('officer/members.php') ?>">&larr; Back to Member Management</a></p>
    <h1>Export Members</h1>
    <p>Download member records as an Excel (.xlsx) or CSV file. Choose which members and sector to include.</p>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $err): ?>
          <div><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!$xlsxAvailable): ?>
      <div class="alert alert-error">The Excel library (<code>vendor/shuchkin/simplexlsxgen</code>) was not found, so only CSV export is available.</div>
    <?php endif; ?>

    <form method="GET" action="<?= base_url('officer/members_export.php') ?>" class="inline-card-form">
      <input type="hidden" name="export" value="1">

      <div class="form-row">
        <div class="form-group">
          <label for="sector">Sector</label>
          <select id="sector" name="sector">
            <option value="">All sectors</option>
            <?php foreach ($sectors as $s): ?>
              <option value="<?= (int) $s['sector_id'] ?>" <?= $sectorP === (string) $s['sector_id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['sector_name']) ?></option>
            <?php endforeach; ?>
            <option value="none" <?= $sectorP === 'none' ? 'selected' : '' ?>>No sector assigned</option>
          </select>
        </div>

        <div class="form-group">
          <label for="status">Member status</label>
          <select id="status" name="status">
            <option value="">All statuses</option>
            <?php foreach (MEMBER_EXPORT_STATUSES as $st): ?>
              <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="layout">Rows</label>
          <select id="layout" name="layout">
            <option value="member" <?= $layout === 'member' ? 'selected' : '' ?>>One row per member (lot count and total area)</option>
            <option value="lot" <?= $layout === 'lot' ? 'selected' : '' ?>>One row per farm lot (lot, sector, area, coordinates, notes)</option>
          </select>
        </div>

        <div class="form-group">
          <label for="format">File format</label>
          <select id="format" name="format">
            <option value="xlsx" <?= $format === 'xlsx' ? 'selected' : '' ?> <?= $xlsxAvailable ? '' : 'disabled' ?>>Excel (.xlsx)</option>
            <option value="csv" <?= $format === 'csv' ? 'selected' : '' ?>>CSV (.csv)</option>
          </select>
        </div>
      </div>

      <p class="muted-note">
        When a sector is chosen, only members with a farm lot in that sector are included, and the
        lot count, area and sectors are counted for that sector only. With <em>All sectors</em>,
        members without any farm lot are included too.
        In CSV files Excel may drop the leading zero of contact numbers; the .xlsx file keeps them as typed.
      </p>

      <button type="submit" class="btn-full" style="max-width:220px;">Download</button>
      <a href="<?= base_url('officer/members.php') ?>" style="margin-left:1rem;">Cancel</a>
    </form>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
