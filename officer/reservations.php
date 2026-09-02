<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/machinery.php';

require_role('officer');

$statusFilter = $_GET['status'] ?? '';
$statusFilter = in_array($statusFilter, ['pending', 'approved', 'rejected', 'cancelled'], true) ? $statusFilter : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    $reservationId = (int) ($_POST['reservation_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    if ($reservationId > 0 && in_array($newStatus, ['approved', 'rejected', 'cancelled'], true)) {
        // Note: double-booking is flagged to the officer at request time (see
        // reservations list / new-request form), but the final approve/reject
        // call is left to the officer's judgement rather than auto-blocked here.
        update_reservation_status($pdo, $reservationId, $newStatus);
        header('Location: ' . base_url('officer/reservations.php?flash=updated' . ($statusFilter ? '&status=' . $statusFilter : '')));
        exit;
    }
}

$reservations = get_all_reservations($pdo, $statusFilter);
$flash = $_GET['flash'] ?? null;
$activeNav = 'machinery';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reservations · BAMBOSA Officer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/officer_sidebar.php'; ?>

  <main class="dash-main">
    <h1>Reservation Requests</h1>
    <p>Approve or reject member requests to borrow farming machinery. Manage the equipment list on the <a href="<?= base_url('officer/machinery.php') ?>">Machinery page</a>.</p>

    <?php if ($flash === 'updated'): ?>
      <div class="alert alert-success">Reservation updated.</div>
    <?php endif; ?>

    <div class="filter-tabs">
      <a href="<?= base_url('officer/reservations.php') ?>" class="<?= $statusFilter === null ? 'active' : '' ?>">All</a>
      <a href="<?= base_url('officer/reservations.php?status=pending') ?>" class="<?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
      <a href="<?= base_url('officer/reservations.php?status=approved') ?>" class="<?= $statusFilter === 'approved' ? 'active' : '' ?>">Approved</a>
      <a href="<?= base_url('officer/reservations.php?status=rejected') ?>" class="<?= $statusFilter === 'rejected' ? 'active' : '' ?>">Rejected</a>
      <a href="<?= base_url('officer/reservations.php?status=cancelled') ?>" class="<?= $statusFilter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
    </div>

    <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>Machinery</th>
          <th>Requested By</th>
          <th>Dates</th>
          <th>Purpose</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($reservations)): ?>
          <tr><td colspan="6" class="empty-row">No reservation requests found.</td></tr>
        <?php else: foreach ($reservations as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['machinery_name']) ?></td>
            <td><?= htmlspecialchars($r['requester_name']) ?></td>
            <td><?= htmlspecialchars($r['start_date']) ?> &rarr; <?= htmlspecialchars($r['end_date']) ?></td>
            <td><?= htmlspecialchars($r['purpose'] ?? '—') ?></td>
            <td><span class="status-badge status-<?= $r['status'] === 'approved' ? 'active' : ($r['status'] === 'pending' ? 'pending' : 'inactive') ?>"><?= htmlspecialchars($r['status']) ?></span></td>
            <td class="actions-cell">
              <?php if ($r['status'] === 'pending'): ?>
                <form method="POST" class="inline-form">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="reservation_id" value="<?= (int) $r['reservation_id'] ?>">
                  <input type="hidden" name="status" value="approved">
                  <button type="submit" class="link-btn approve">Approve</button>
                </form>
                <form method="POST" class="inline-form">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="reservation_id" value="<?= (int) $r['reservation_id'] ?>">
                  <input type="hidden" name="status" value="rejected">
                  <button type="submit" class="link-btn deactivate">Reject</button>
                </form>
              <?php elseif ($r['status'] === 'approved'): ?>
                <form method="POST" class="inline-form" onsubmit="return confirm('Cancel this approved reservation?');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="reservation_id" value="<?= (int) $r['reservation_id'] ?>">
                  <input type="hidden" name="status" value="cancelled">
                  <button type="submit" class="link-btn deactivate">Cancel</button>
                </form>
              <?php else: ?>
                <span class="muted-note">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
