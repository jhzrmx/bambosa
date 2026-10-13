<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/machinery.php';
require_once __DIR__ . '/../includes/pagination.php';

require_role('officer');

$errors = [];
$isAddingOrEditing = isset($_GET["new"]) || isset($_GET["edit"]);
$editingGroup = null;

if (isset($_GET['edit'])) {
    $editingGroup = get_machinery_group_by_id($pdo, (int) $_GET['edit']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $id          = (int) ($_POST['group_id'] ?? 0);
    $name        = trim($_POST['group_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $errors[] = 'Group name is required.';
    }

    if (empty($errors)) {
        try {
            if ($id > 0) {
                update_machinery_group($pdo, $id, $name, $description);
            } else {
                create_machinery_group($pdo, $name, $description);
            }
            header('Location: ' . base_url('officer/machinery_groups.php?flash=saved'));
            exit;
        } catch (PDOException $e) {
            // Most likely cause: the UNIQUE constraint on group_name.
            $errors[] = 'A machinery group with that name already exists.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (csrf_verify($_POST['csrf_token'] ?? null)) {
        delete_machinery_group($pdo, (int) ($_POST['group_id'] ?? 0));
    }
    header('Location: ' . base_url('officer/machinery_groups.php?flash=deleted'));
    exit;
}

$groups = get_all_machinery_groups($pdo);
foreach ($groups as &$g) {
    $g['machine_count'] = count_machinery_in_group($pdo, (int) $g['group_id']);
    $g['available_count'] = count_available_machinery_in_group($pdo, (int) $g['group_id']);
}
unset($g);

$flash = $_GET['flash'] ?? null;
$activeNav = 'machinery';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
<link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<title>Machinery Groups · BAMBOSA Officer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/officer_sidebar.php'; ?>

  <main class="dash-main">
    <h1>Machinery Groups</h1>
    <p>
      Group interchangeable machines (e.g. several identical hand tractors) into one pool.
      Members reserve the group as a whole instead of guessing a specific unit; you assign
      which physical machine to hand over when approving a request. Manage individual
      machines and assign them to a group on the <a href="<?= base_url('officer/machinery.php') ?>">Machinery page</a>.
    </p>

    <?php if ($flash === 'saved'): ?>
      <div class="alert alert-success">Machinery group saved.</div>
    <?php elseif ($flash === 'deleted'): ?>
      <div class="alert alert-success">Machinery group removed. Any machines that belonged to it are now standalone (ungrouped) rather than deleted.</div>
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
            <th>Group Name</th>
            <th>Description</th>
            <th>Machines</th>
            <th>Available Machines</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($groups)): ?>
            <tr><td colspan="5" class="empty-row">No machinery groups yet.</td></tr>
          <?php else: foreach ($groups as $g): ?>
            <tr>
              <td><?= htmlspecialchars($g['group_name']) ?></td>
              <td><?= htmlspecialchars($g['description'] ?? '—') ?></td>
              <td><?= (int) $g['machine_count'] ?></td>
              <td>
                <span class="status-badge <?= (int) $g['available_count'] > 0 ? 'status-active' : 'status-inactive' ?>"
                      title="Machines in this group currently marked available">
                  <?= (int) $g['available_count'] ?>
                </span>
              </td>
              <td class="actions-cell">
                <a href="<?= base_url('officer/machinery_groups.php?edit=' . (int) $g['group_id']) ?>" class="link-btn">Edit</a>
                <?php
                  $confirmMsg = $g['machine_count'] > 0
                      ? "This group has {$g['machine_count']} machine(s) assigned to it. Deleting the group will NOT delete those machines — they'll just become standalone (ungrouped). Continue?"
                      : 'Delete this machinery group?';
                ?>
                <form method="POST" class="inline-form" onsubmit="return confirm('<?= htmlspecialchars($confirmMsg, ENT_QUOTES) ?>');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="group_id" value="<?= (int) $g['group_id'] ?>">
                  <button type="submit" class="link-btn deactivate">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
      </div>

	  <a href="<?= base_url('officer/machinery_groups.php?new=') ?>"><button type="button" class="btn-full" style="max-width:220px; margin-top:24px;">Add New Group</button></a>
	<?php endif; ?>
	
	<?php if ($isAddingOrEditing): ?>
      <h2 class="section-heading"><?= $editingGroup ? 'Edit Machinery Group' : 'Add New Machinery Group' ?></h2>
	  
      <form method="POST" action="<?= base_url('officer/machinery_groups.php') ?>" class="inline-card-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="group_id" value="<?= $editingGroup ? (int) $editingGroup['group_id'] : 0 ?>">
	  
        <div class="form-group">
          <label for="group_name">Group Name</label>
          <input type="text" id="group_name" name="group_name" required
                 value="<?= htmlspecialchars($editingGroup['group_name'] ?? '') ?>" placeholder="e.g. Hand Tractors">
        </div>
	  
        <div class="form-group">
          <label for="description">Description (optional)</label>
          <input type="text" id="description" name="description"
                 value="<?= htmlspecialchars($editingGroup['description'] ?? '') ?>" placeholder="e.g. Kubota-type hand tractors for tilling">
        </div>
	  
        <button type="submit" class="btn-full" style="max-width:220px;">
          <?= $editingGroup ? 'Update Group' : 'Add Group' ?>
        </button>
        <a href="<?= base_url('officer/machinery_groups.php') ?>" style="margin-left:1rem;">Cancel</a>
      </form>
	<?php endif; ?>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
