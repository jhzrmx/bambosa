<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/billing.php';

require_role('officer');

$periodId = (int) ($_GET['id'] ?? 0);
$period = $periodId ? get_billing_period($pdo, $periodId) : null;

if (!$period) {
    header('Location: ' . base_url('officer/billing_periods.php'));
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    if (csrf_verify($_POST['csrf_token'] ?? null)) {
        try {
            $count = generate_bills_for_period($pdo, $periodId);
            header('Location: ' . base_url('officer/billing_period_view.php?id=' . $periodId . '&flash=generated&count=' . $count));
            exit;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record_payment') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $billId       = (int) ($_POST['bill_id'] ?? 0);
    $amount       = $_POST['amount'] ?? '';
    $paymentDate  = $_POST['payment_date'] ?? '';
    $paymentNotes = trim($_POST['payment_notes'] ?? '');

    if (!is_numeric($amount) || (float) $amount <= 0) {
        $errors[] = 'Payment amount must be a positive number.';
    }
    if (!$paymentDate) {
        $errors[] = 'Please provide a payment date.';
    }

    if (empty($errors)) {
        try {
            record_payment($pdo, $billId, (float) $amount, $paymentDate, $paymentNotes, (int) $_SESSION['user_id']);
            header('Location: ' . base_url('officer/billing_period_view.php?id=' . $periodId . '&flash=payment_recorded'));
            exit;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$statusFilter = $_GET['status'] ?? '';
$statusFilter = in_array($statusFilter, ['unpaid', 'partial', 'paid'], true) ? $statusFilter : null;

$bills = get_bills_for_period($pdo, $periodId, $statusFilter);
$summary = get_period_summary($pdo, $periodId);
$flash = $_GET['flash'] ?? null;
$generatedCount = (int) ($_GET['count'] ?? 0);
$activeNav = 'billing';

// If a bill_id is present, we're opening the "record payment" mini-form for that bill inline.
$recordingBillId = isset($_GET['pay']) ? (int) $_GET['pay'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($period['period_name']) ?> · BAMBOSA Officer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/officer_sidebar.php'; ?>

  <main class="dash-main">
    <a href="<?= base_url('officer/billing_periods.php') ?>" class="back-link">&larr; Back to Billing Periods</a>
    <h1><?= htmlspecialchars($period['period_name']) ?></h1>

    <div class="profile-summary">
      <div><span class="label">Rate:</span> ₱<?= number_format((float) $period['rate_per_ha'], 2) ?> / ha</div>
      <div><span class="label">Dates:</span> <?= htmlspecialchars($period['start_date']) ?> &rarr; <?= htmlspecialchars($period['end_date']) ?></div>
      <div><span class="label">Status:</span> <span class="status-badge <?= $period['status'] === 'open' ? 'status-active' : 'status-inactive' ?>"><?= htmlspecialchars($period['status']) ?></span></div>
      <div><span class="label">Total Due:</span> ₱<?= number_format((float) $summary['total_due'], 2) ?></div>
      <div><span class="label">Total Collected:</span> ₱<?= number_format((float) $summary['total_collected'], 2) ?></div>
      <div><span class="label">Bills:</span> <?= (int) $summary['bill_count'] ?> (<?= (int) $summary['paid_count'] ?> paid, <?= (int) $summary['partial_count'] ?> partial, <?= (int) $summary['unpaid_count'] ?> unpaid)</div>
    </div>

    <?php if ($flash === 'generated'): ?>
      <div class="alert alert-success"><?= $generatedCount ?> bill(s) generated/updated for this period.</div>
    <?php elseif ($flash === 'payment_recorded'): ?>
      <div class="alert alert-success">Payment recorded.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($period['status'] === 'open'): ?>
      <form method="POST" style="margin-bottom:1.5rem;" onsubmit="return confirm('Generate bills for every registered farm lot at ₱<?= number_format((float) $period['rate_per_ha'], 2) ?>/ha? Existing bills in this period will have their amount due refreshed; recorded payments are kept.');">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="generate">
        <button type="submit" class="btn-full" style="max-width:260px;">Generate / Refresh Bills</button>
      </form>
    <?php else: ?>
      <p class="muted-note">This period is closed. Reopen it from the Billing Periods page to generate more bills.</p>
    <?php endif; ?>

    <div class="filter-tabs">
      <a href="<?= base_url('officer/billing_period_view.php?id=' . $periodId) ?>" class="<?= $statusFilter === null ? 'active' : '' ?>">All</a>
      <a href="<?= base_url('officer/billing_period_view.php?id=' . $periodId . '&status=unpaid') ?>" class="<?= $statusFilter === 'unpaid' ? 'active' : '' ?>">Unpaid</a>
      <a href="<?= base_url('officer/billing_period_view.php?id=' . $periodId . '&status=partial') ?>" class="<?= $statusFilter === 'partial' ? 'active' : '' ?>">Partial</a>
      <a href="<?= base_url('officer/billing_period_view.php?id=' . $periodId . '&status=paid') ?>" class="<?= $statusFilter === 'paid' ? 'active' : '' ?>">Paid</a>
    </div>

    <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>Member</th>
          <th>Lot</th>
          <th>Sector</th>
          <th>Area (ha)</th>
          <th>Amount Due</th>
          <th>Paid</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($bills)): ?>
          <tr><td colspan="8" class="empty-row">No bills yet. Click "Generate / Refresh Bills" above to create them.</td></tr>
        <?php else: foreach ($bills as $b): ?>
          <tr>
            <td><?= htmlspecialchars($b['owner_name']) ?></td>
            <td><?= htmlspecialchars($b['lot_name']) ?></td>
            <td><?= htmlspecialchars($b['sector_name'] ?? '—') ?></td>
            <td><?= number_format((float) $b['land_area_ha'], 2) ?></td>
            <td>₱<?= number_format((float) $b['amount_due'], 2) ?></td>
            <td>₱<?= number_format((float) $b['amount_paid'], 2) ?></td>
            <td><span class="status-badge <?= $b['status'] === 'paid' ? 'status-active' : ($b['status'] === 'partial' ? 'status-pending' : 'status-inactive') ?>"><?= htmlspecialchars($b['status']) ?></span></td>
            <td class="actions-cell">
              <?php if ($b['status'] !== 'paid'): ?>
                <a href="<?= base_url('officer/billing_period_view.php?id=' . $periodId . '&pay=' . (int) $b['bill_id']) ?>" class="link-btn">Record Payment</a>
              <?php else: ?>
                <span class="muted-note">Fully paid</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php if ($recordingBillId === (int) $b['bill_id']): ?>
            <tr>
              <td colspan="8" style="background:#fafcfa;">
                <form method="POST" class="inline-card-form" style="box-shadow:none; border:1px solid var(--clr-border); margin:0.5rem 0;">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="action" value="record_payment">
                  <input type="hidden" name="bill_id" value="<?= (int) $b['bill_id'] ?>">

                  <p style="margin-top:0;">
                    Recording payment for <strong><?= htmlspecialchars($b['owner_name']) ?></strong>
                    (<?= htmlspecialchars($b['lot_name']) ?>) — balance remaining:
                    ₱<?= number_format((float) $b['amount_due'] - (float) $b['amount_paid'], 2) ?>
                  </p>

                  <div class="form-row">
                    <div class="form-group">
                      <label for="amount_<?= (int) $b['bill_id'] ?>">Amount (₱)</label>
                      <input type="number" step="0.01" min="0.01" id="amount_<?= (int) $b['bill_id'] ?>" name="amount" required
                             max="<?= number_format((float) $b['amount_due'] - (float) $b['amount_paid'], 2, '.', '') ?>">
                    </div>
                    <div class="form-group">
                      <label for="payment_date_<?= (int) $b['bill_id'] ?>">Payment Date</label>
                      <input type="date" id="payment_date_<?= (int) $b['bill_id'] ?>" name="payment_date" required value="<?= date('Y-m-d') ?>">
                    </div>
                  </div>

                  <div class="form-group">
                    <label for="payment_notes_<?= (int) $b['bill_id'] ?>">Notes (optional)</label>
                    <input type="text" id="payment_notes_<?= (int) $b['bill_id'] ?>" name="payment_notes" placeholder="e.g. OR #1234, cash">
                  </div>

                  <button type="submit" class="btn-full" style="max-width:200px;">Save Payment</button>
                  <a href="<?= base_url('officer/billing_period_view.php?id=' . $periodId) ?>" style="margin-left:1rem;">Cancel</a>
                </form>
              </td>
            </tr>
          <?php endif; ?>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
