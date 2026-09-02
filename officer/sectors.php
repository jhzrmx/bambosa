<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';

require_role('officer');

$errors = [];
$editingSector = null;

if (isset($_GET['edit'])) {
    $editingSector = get_sector_by_id($pdo, (int) $_GET['edit']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $id          = (int) ($_POST['sector_id'] ?? 0);
    $name        = trim($_POST['sector_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $errors[] = 'Sector name is required.';
    } elseif (sector_name_exists($pdo, $name, $id > 0 ? $id : null)) {
        $errors[] = 'A sector with that name already exists.';
    }

    if (empty($errors)) {
        if ($id > 0) {
            update_sector($pdo, $id, $name, $description);
        } else {
            create_sector($pdo, $name, $description);
        }
        header('Location: ' . base_url('officer/sectors.php?flash=saved'));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (csrf_verify($_POST['csrf_token'] ?? null)) {
        delete_sector($pdo, (int) ($_POST['sector_id'] ?? 0));
    }
    header('Location: ' . base_url('officer/sectors.php?flash=deleted'));
    exit;
}

$sectors = get_sectors_with_usage($pdo);
$flash = $_GET['flash'] ?? null;
$activeNav = 'sectors';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sectors · BAMBOSA Officer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/officer_sidebar.php'; ?>

  <main class="dash-main">
    <h1>Sector Management</h1>
    <p>Manage the irrigation sectors used to group farm lots, water rotation schedules, and (soon) billing rates.</p>

    <?php if ($flash === 'saved'): ?>
      <div class="alert alert-success">Sector saved.</div>
    <?php elseif ($flash === 'deleted'): ?>
      <div class="alert alert-success">Sector removed.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>Sector Name</th>
          <th>Description</th>
          <th>Farm Lots</th>
          <th>Schedules</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($sectors)): ?>
          <tr><td colspan="5" class="empty-row">No sectors created yet.</td></tr>
        <?php else: foreach ($sectors as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['sector_name']) ?></td>
            <td><?= htmlspecialchars($s['description'] ?? '—') ?></td>
            <td><?= (int) $s['lot_count'] ?></td>
            <td><?= (int) $s['schedule_count'] ?></td>
            <td class="actions-cell">
              <a href="<?= base_url('officer/sectors.php?edit=' . (int) $s['sector_id']) ?>" class="link-btn">Edit</a>
              <?php
                $hasDependents = ((int) $s['lot_count'] > 0) || ((int) $s['schedule_count'] > 0);
                $confirmMsg = $hasDependents
                    ? "This sector has {$s['lot_count']} farm lot(s) and {$s['schedule_count']} schedule(s) linked to it. Deleting it will unassign those farm lots and permanently remove those schedules. Continue?"
                    : 'Delete this sector?';
              ?>
              <form method="POST" class="inline-form" onsubmit="return confirm('<?= htmlspecialchars($confirmMsg, ENT_QUOTES) ?>');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="sector_id" value="<?= (int) $s['sector_id'] ?>">
                <button type="submit" class="link-btn deactivate">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>

    <h2 class="section-heading"><?= $editingSector ? 'Edit Sector' : 'Add New Sector' ?></h2>

    <form method="POST" action="<?= base_url('officer/sectors.php') ?>" class="inline-card-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="sector_id" value="<?= $editingSector ? (int) $editingSector['sector_id'] : 0 ?>">

      <div class="form-group">
        <label for="sector_name">Sector Name</label>
        <input type="text" id="sector_name" name="sector_name" required
               value="<?= htmlspecialchars($editingSector['sector_name'] ?? '') ?>" placeholder="e.g. Sector D">
      </div>

      <div class="form-group">
        <label for="description">Description (optional)</label>
        <input type="text" id="description" name="description"
               value="<?= htmlspecialchars($editingSector['description'] ?? '') ?>" placeholder="e.g. Northeast Bikal irrigation zone">
      </div>

      <button type="submit" class="btn-full" style="max-width:220px;">
        <?= $editingSector ? 'Update Sector' : 'Add Sector' ?>
      </button>
      <?php if ($editingSector): ?>
        <a href="<?= base_url('officer/sectors.php') ?>" style="margin-left:1rem;">Cancel</a>
      <?php endif; ?>
    </form>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
