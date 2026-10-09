<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/pagination.php';

require_role('officer');

$errors = [];
$isAddingOrEditing = isset($_GET["new"]) || isset($_GET["edit"]);
$editingPeriod = null;

if (isset($_GET['edit'])) {
    $editingPeriod = get_billing_period($pdo, (int) $_GET['edit']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $id        = (int) ($_POST['period_id'] ?? 0);
    $name      = trim($_POST['period_name'] ?? '');
    $rate      = $_POST['rate_per_ha'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate   = $_POST['end_date'] ?? '';
    $notes     = trim($_POST['notes'] ?? '');

    if ($name === '') {
        $errors[] = 'Period name is required.';
    }
    if (!is_numeric($rate) || (float) $rate <= 0) {
        $errors[] = 'Rate per hectare must be a positive number.';
    }
    if (!$startDate || !$endDate) {
        $errors[] = 'Please provide both a start and end date.';
    } elseif ($endDate < $startDate) {
        $errors[] = 'End date cannot be before the start date.';
    }

    // Editing an existing period that's already closed shouldn't silently
    // change its rate underneath already-generated bills without a fresh
    // regeneration — but we still allow name/date/notes edits.
    if (empty($errors)) {
        if ($id > 0) {
            update_billing_period($pdo, $id, $name, (float) $rate, $startDate, $endDate, $notes);
        } else {
            create_billing_period($pdo, $name, (float) $rate, $startDate, $endDate, $notes, (int) $_SESSION['user_id']);
        }
        header('Location: ' . base_url('officer/billing_periods.php?flash=saved'));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    $periodId = (int) ($_POST['period_id'] ?? 0);
    if (csrf_verify($_POST['csrf_token'] ?? null) && $periodId > 0) {
        try {
            $count = generate_bills_for_period($pdo, $periodId, $genSummary);
            header('Location: ' . base_url('officer/billing_period_view.php?id=' . $periodId . '&flash=generated&count=' . $count . '&skipped=' . $genSummary['skipped'] . '&removed=' . $genSummary['removed'] . '&kept=' . $genSummary['kept_with_payments']));
            exit;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    if (csrf_verify($_POST['csrf_token'] ?? null)) {
        $periodId = (int) ($_POST['period_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';
        if (in_array($newStatus, ['open', 'closed'], true)) {
            set_billing_period_status($pdo, $periodId, $newStatus);
        }
    }
    header('Location: ' . base_url('officer/billing_periods.php?flash=status_updated'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (csrf_verify($_POST['csrf_token'] ?? null)) {
        delete_billing_period($pdo, (int) ($_POST['period_id'] ?? 0));
    }
    header('Location: ' . base_url('officer/billing_periods.php?flash=deleted'));
    exit;
}

$searchTerm = get_search_term();
$totalPeriods = count_billing_periods($pdo, $searchTerm);
$pagination = build_pagination($totalPeriods, get_current_page());
$periods = get_billing_periods($pdo, $searchTerm, $pagination['per_page'], $pagination['offset']);
$flash = $_GET['flash'] ?? null;
$activeNav = 'billing';
$pageBaseUrl = base_url('officer/billing_periods.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
<link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<title>Billing Periods · BAMBOSA Officer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/officer_sidebar.php'; ?>

  <main class="dash-main">
    <h1>ISF Billing Periods</h1>
    <p>
      Open a billing period (e.g. a cropping season) with a rate per hectare, then generate
      bills for every farm lot that is inside a sector (lots not assigned to any sector are
      not billed). Land area and rate are locked in at generation time, so later corrections
      to a farm lot don't silently change past bills.
    </p>

    <?php if ($flash === 'saved'): ?>
      <div class="alert alert-success">Billing period saved.</div>
    <?php elseif ($flash === 'deleted'): ?>
      <div class="alert alert-success">Billing period and all its bills/payments were removed.</div>
    <?php elseif ($flash === 'status_updated'): ?>
      <div class="alert alert-success">Billing period status updated.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>
	
	<?php if (!$isAddingOrEditing): ?>
      <?php render_search_box($pageBaseUrl, $searchTerm, 'Search by period name or notes…'); ?>

      <div class="table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th>Period</th>
            <th>Rate / ha</th>
            <th>Dates</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($periods)): ?>
            <tr><td colspan="5" class="empty-row">No billing periods found<?= $searchTerm ? ' for "' . htmlspecialchars($searchTerm) . '"' : ' yet' ?>.</td></tr>
          <?php else: foreach ($periods as $p): ?>
            <tr>
              <td><a href="<?= base_url('officer/billing_period_view.php?id=' . (int) $p['period_id']) ?>"><?= htmlspecialchars($p['period_name']) ?></a></td>
              <td>₱<?= number_format((float) $p['rate_per_ha'], 2) ?></td>
              <td><?= htmlspecialchars($p['start_date']) ?> &rarr; <?= htmlspecialchars($p['end_date']) ?></td>
              <td><span class="status-badge <?= $p['status'] === 'open' ? 'status-active' : 'status-inactive' ?>"><?= htmlspecialchars($p['status']) ?></span></td>
              <td class="actions-cell">
                <a href="<?= base_url('officer/billing_period_view.php?id=' . (int) $p['period_id']) ?>" class="link-btn">View Bills</a>
                <?php if ($p['status'] === 'open'): ?>
                  <a href="<?= base_url('officer/billing_periods.php?edit=' . (int) $p['period_id']) ?>" class="link-btn">Edit</a>
                  <form method="POST" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="period_id" value="<?= (int) $p['period_id'] ?>">
                    <input type="hidden" name="new_status" value="closed">
                    <button type="submit" class="link-btn deactivate" onclick="return confirm('Close this billing period? Bills can no longer be generated or regenerated once closed.');">Close</button>
                  </form>
                <?php else: ?>
                  <form method="POST" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="period_id" value="<?= (int) $p['period_id'] ?>">
                    <input type="hidden" name="new_status" value="open">
                    <button type="submit" class="link-btn approve">Reopen</button>
                  </form>
                <?php endif; ?>
                <form method="POST" class="inline-form" onsubmit="return confirm('Delete this billing period? This permanently removes ALL bills and payment records under it. This cannot be undone.');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="period_id" value="<?= (int) $p['period_id'] ?>">
                  <button type="submit" class="link-btn deactivate">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
      </div>

      <?php render_pagination_controls($pagination, $pageBaseUrl . ($searchTerm ? '?q=' . urlencode($searchTerm) : '')); ?>

	  <a href="<?= base_url('officer/billing_periods.php?new=') ?>"><button type="button" class="btn-full" style="max-width:220px; margin-top:24px;">Open New Billing Period</button></a>
	<?php endif; ?>
	
	<?php if ($isAddingOrEditing): ?>
      <h2 class="section-heading"><?= $editingPeriod ? 'Edit Billing Period' : 'Open New Billing Period' ?></h2>
	  
      <form method="POST" action="<?= base_url('officer/billing_periods.php') ?>" class="inline-card-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="period_id" value="<?= $editingPeriod ? (int) $editingPeriod['period_id'] : 0 ?>">
	  
        <div class="form-group">
          <label for="period_name">Period Name</label>
          <input type="text" id="period_name" name="period_name" required
                 value="<?= htmlspecialchars($editingPeriod['period_name'] ?? '') ?>" placeholder="e.g. Wet Season 2026">
        </div>
	  
        <div class="form-group">
          <label for="rate_per_ha">Rate per Hectare (₱)</label>
          <input type="number" step="0.01" min="0.01" id="rate_per_ha" name="rate_per_ha" required
                 value="<?= htmlspecialchars($editingPeriod['rate_per_ha'] ?? '') ?>" placeholder="e.g. 750.00">
        </div>
	  
        <div class="form-row">
          <div class="form-group">
            <label for="start_date">Start Date</label>
            <input type="date" id="start_date" name="start_date" required
                   value="<?= htmlspecialchars($editingPeriod['start_date'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label for="end_date">End Date</label>
            <input type="date" id="end_date" name="end_date" required
                   value="<?= htmlspecialchars($editingPeriod['end_date'] ?? '') ?>">
          </div>
        </div>
	  
        <div class="form-group">
          <label for="notes">Notes (optional)</label>
          <input type="text" id="notes" name="notes" value="<?= htmlspecialchars($editingPeriod['notes'] ?? '') ?>">
        </div>
	  
        <button type="submit" class="btn-full" style="max-width:220px;">
          <?= $editingPeriod ? 'Update Period' : 'Open Period' ?>
        </button>
        <a href="<?= base_url('officer/billing_periods.php') ?>" style="margin-left:1rem;">Cancel</a>
      </form>
	<?php endif; ?>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
