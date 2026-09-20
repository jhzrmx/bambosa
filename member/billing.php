<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/billing.php';

require_role('member');

$userId = (int) $_SESSION['user_id'];
$bills = get_bills_for_user($pdo, $userId);
$outstandingBalance = get_outstanding_balance_for_user($pdo, $userId);

// Support expanding one bill to see its payment history inline.
$viewingBillId = isset($_GET['view']) ? (int) $_GET['view'] : null;
$viewingPayments = [];
if ($viewingBillId) {
    // Confirm that bill actually belongs to this member before showing anything.
    $ownedBillIds = array_column($bills, 'bill_id');
    if (in_array($viewingBillId, $ownedBillIds, true)) {
        $viewingPayments = get_payments_for_bill($pdo, $viewingBillId);
    } else {
        $viewingBillId = null;
    }
}

$activeNav = 'billing';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
<link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<title>My Billing / ISF · BAMBOSA Member</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/member_sidebar.php'; ?>

  <main class="dash-main">
    <h1>My Billing / ISF</h1>
    <p>Your Irrigation Service Fee (ISF) bills, computed from your registered farm lot area and the rate set for each billing period.</p>

    <div class="profile-summary">
      <div><span class="label">Outstanding Balance:</span> ₱<?= number_format($outstandingBalance, 2) ?></div>
    </div>

    <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>Period</th>
          <th>Lot</th>
          <th>Sector</th>
          <th>Area (ha)</th>
          <th>Rate/ha</th>
          <th>Amount Due</th>
          <th>Paid</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($bills)): ?>
          <tr><td colspan="9" class="empty-row">No bills have been issued to you yet.</td></tr>
        <?php else: foreach ($bills as $b): ?>
          <tr>
            <td><?= htmlspecialchars($b['period_name']) ?></td>
            <td><?= htmlspecialchars($b['lot_name']) ?></td>
            <td><?= htmlspecialchars($b['sector_name'] ?? '—') ?></td>
            <td><?= number_format((float) $b['land_area_ha'], 2) ?></td>
            <td>₱<?= number_format((float) $b['rate_per_ha'], 2) ?></td>
            <td>₱<?= number_format((float) $b['amount_due'], 2) ?></td>
            <td>₱<?= number_format((float) $b['amount_paid'], 2) ?></td>
            <td><span class="status-badge <?= $b['status'] === 'paid' ? 'status-active' : ($b['status'] === 'partial' ? 'status-pending' : 'status-inactive') ?>"><?= htmlspecialchars($b['status']) ?></span></td>
            <td>
              <a href="<?= base_url('member/billing.php?view=' . (int) $b['bill_id']) ?>" class="link-btn">
                <?= $viewingBillId === (int) $b['bill_id'] ? 'Hide' : 'Payment History' ?>
              </a>
            </td>
          </tr>
          <?php if ($viewingBillId === (int) $b['bill_id']): ?>
            <tr>
              <td colspan="9" style="background:#fafcfa;">
                <?php if (empty($viewingPayments)): ?>
                  <p class="muted-note" style="margin:0.5rem 0;">No payments recorded yet for this bill.</p>
                <?php else: ?>
                  <table class="data-table" style="box-shadow:none; margin:0.5rem 0;">
                    <thead>
                      <tr><th>Date</th><th>Amount</th><th>Recorded By</th><th>Notes</th></tr>
                    </thead>
                    <tbody>
                      <?php foreach ($viewingPayments as $pmt): ?>
                        <tr>
                          <td><?= htmlspecialchars($pmt['payment_date']) ?></td>
                          <td>₱<?= number_format((float) $pmt['amount'], 2) ?></td>
                          <td><?= htmlspecialchars($pmt['recorded_by_name'] ?? '—') ?></td>
                          <td><?= htmlspecialchars($pmt['notes'] ?? '—') ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                <?php endif; ?>
              </td>
            </tr>
          <?php endif; ?>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>

    <p class="muted-note" style="margin-top:1rem;">
      To make a payment, please coordinate with an association officer. Payments are
      recorded by officers once received.
    </p>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
