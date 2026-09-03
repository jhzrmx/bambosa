<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/machinery.php';

require_role('officer');

$errors = [];
$isAddingOrEditing = isset($_GET["new"]) || isset($_GET["edit"]);
$editingMachinery = null;

if (isset($_GET['edit'])) {
    $editingMachinery = get_machinery_by_id($pdo, (int) $_GET['edit']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $id          = (int) ($_POST['machinery_id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $isAvailable = isset($_POST['is_available']);

    if ($name === '') {
        $errors[] = 'Machinery name is required.';
    }

    if (empty($errors)) {
        if ($id > 0) {
            update_machinery($pdo, $id, $name, $description, $isAvailable);
        } else {
            create_machinery($pdo, $name, $description);
        }
        header('Location: ' . base_url('officer/machinery.php?flash=saved'));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (csrf_verify($_POST['csrf_token'] ?? null)) {
        delete_machinery($pdo, (int) ($_POST['machinery_id'] ?? 0));
    }
    header('Location: ' . base_url('officer/machinery.php?flash=deleted'));
    exit;
}

$machineryList = get_all_machinery($pdo);
$flash = $_GET['flash'] ?? null;
$activeNav = 'machinery';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Machinery · BAMBOSA Officer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/officer_sidebar.php'; ?>

  <main class="dash-main">
    <h1>Machinery Management</h1>
    <p>Maintain the list of farming equipment members can reserve. See <a href="<?= base_url('officer/reservations.php') ?>">reservation requests</a> to approve or reject bookings.</p>

    <?php if ($flash === 'saved'): ?>
      <div class="alert alert-success">Machinery record saved.</div>
    <?php elseif ($flash === 'deleted'): ?>
      <div class="alert alert-success">Machinery record removed.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>
	
	<?php if (!$isAddingOrEditing): ?>
      <div class="table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Description</th>
            <th>Available?</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($machineryList)): ?>
            <tr><td colspan="4" class="empty-row">No machinery added yet.</td></tr>
          <?php else: foreach ($machineryList as $m): ?>
            <tr>
              <td><?= htmlspecialchars($m['name']) ?></td>
              <td><?= htmlspecialchars($m['description'] ?? '—') ?></td>
              <td>
                <span class="status-badge <?= $m['is_available'] ? 'status-active' : 'status-inactive' ?>">
                  <?= $m['is_available'] ? 'Available' : 'Unavailable' ?>
                </span>
              </td>
              <td class="actions-cell">
                <a href="<?= base_url('officer/machinery.php?edit=' . (int) $m['machinery_id']) ?>" class="link-btn">Edit</a>
                <form method="POST" class="inline-form" onsubmit="return confirm('Delete this machinery record? Existing reservations for it will also be removed.');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="machinery_id" value="<?= (int) $m['machinery_id'] ?>">
                  <button type="submit" class="link-btn deactivate">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
      </div>
	  <a href="<?= base_url('officer/machinery.php?new=') ?>"><button type="button" class="btn-full" style="max-width:220px; margin-top:24px;">Add New Machinery</button></a>
	<?php endif; ?>
	
	<?php if ($isAddingOrEditing): ?>
      <h2 class="section-heading"><?= $editingMachinery ? 'Edit Machinery' : 'Add New Machinery' ?></h2>
	  
      <form method="POST" action="<?= base_url('officer/machinery.php') ?>" class="inline-card-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="machinery_id" value="<?= $editingMachinery ? (int) $editingMachinery['machinery_id'] : 0 ?>">
	  
        <div class="form-group">
          <label for="name">Name</label>
          <input type="text" id="name" name="name" required
                 value="<?= htmlspecialchars($editingMachinery['name'] ?? '') ?>" placeholder="e.g. Hand Tractor #2">
        </div>
	  
        <div class="form-group">
          <label for="description">Description (optional)</label>
          <input type="text" id="description" name="description"
                 value="<?= htmlspecialchars($editingMachinery['description'] ?? '') ?>">
        </div>
	  
        <div class="form-group">
          <label>
            <input type="checkbox" name="is_available" style="width:auto; display:inline-block;"
              <?= (!$editingMachinery || $editingMachinery['is_available']) ? 'checked' : '' ?>>
            Available for reservation
          </label>
        </div>
	  
        <button type="submit" class="btn-full" style="max-width:220px;">
          <?= $editingMachinery ? 'Update Machinery' : 'Add Machinery' ?>
        </button>
        <a href="<?= base_url('officer/machinery.php') ?>" style="margin-left:1rem;">Cancel</a>
      </form>
	<?php endif; ?>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
