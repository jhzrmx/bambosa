<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/machinery.php';
require_once __DIR__ . '/../includes/pagination.php';

require_role('officer');

$statusFilter = $_GET['status'] ?? '';
$statusFilter = in_array($statusFilter, ['pending', 'approved', 'rejected', 'cancelled'], true) ? $statusFilter : null;
$searchTerm = get_search_term();
$rejectErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    $reservationId = (int) ($_POST['reservation_id'] ?? 0);
    $action = $_POST['reservation_action'] ?? '';

    if ($reservationId > 0 && $action === 'approve_standalone') {
        // Only reachable for a reservation that already targets a specific
        // machine directly (no group) — group-targeted requests are routed
        // through officer/reservation_assign.php instead, since those need
        // a specific unit chosen first. approve_standalone_reservation()
        // itself re-checks this and re-validates for overlaps regardless.
        if (!approve_standalone_reservation($pdo, $reservationId)) {
            header('Location: ' . base_url('officer/reservations.php?flash=approve_failed' . ($statusFilter ? '&status=' . $statusFilter : '')));
            exit;
        }
        header('Location: ' . base_url('officer/reservations.php?flash=updated' . ($statusFilter ? '&status=' . $statusFilter : '')));
        exit;
    }

    if ($reservationId > 0 && $action === 'reject') {
        $remark = trim($_POST['rejection_remark'] ?? '');
        if ($remark === '') {
            $rejectErrors[$reservationId] = 'A remark is required when rejecting a request, so the member knows why.';
        } else {
            reject_reservation($pdo, $reservationId, $remark);
            header('Location: ' . base_url('officer/reservations.php?flash=updated' . ($statusFilter ? '&status=' . $statusFilter : '')));
            exit;
        }
    }

    if ($reservationId > 0 && $action === 'cancel') {
        cancel_reservation($pdo, $reservationId);
        header('Location: ' . base_url('officer/reservations.php?flash=updated' . ($statusFilter ? '&status=' . $statusFilter : '')));
        exit;
    }
}

$totalReservations = count_reservations($pdo, $statusFilter, $searchTerm);
$pagination = build_pagination($totalReservations, get_current_page());
$reservations = get_all_reservations($pdo, $statusFilter, $searchTerm, $pagination['per_page'], $pagination['offset']);
$flash = $_GET['flash'] ?? null;
$activeNav = 'reservations';
$pageBaseUrl = base_url('officer/reservations.php' . ($statusFilter ? '?status=' . $statusFilter : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
<link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
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
    <?php elseif ($flash === 'approve_failed'): ?>
      <div class="alert alert-error">Couldn't approve — that machine is no longer available for these dates. Please pick a different one or reject the request.</div>
    <?php endif; ?>

    <div class="filter-tabs">
      <a href="<?= base_url('officer/reservations.php') ?>" class="<?= $statusFilter === null ? 'active' : '' ?>">All</a>
      <a href="<?= base_url('officer/reservations.php?status=pending') ?>" class="<?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
      <a href="<?= base_url('officer/reservations.php?status=approved') ?>" class="<?= $statusFilter === 'approved' ? 'active' : '' ?>">Approved</a>
      <a href="<?= base_url('officer/reservations.php?status=rejected') ?>" class="<?= $statusFilter === 'rejected' ? 'active' : '' ?>">Rejected</a>
      <a href="<?= base_url('officer/reservations.php?status=cancelled') ?>" class="<?= $statusFilter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
    </div>

    <?php render_search_box($pageBaseUrl, $searchTerm, 'Search by machinery, requester, or purpose…', ['status' => $statusFilter]); ?>

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
          <tr><td colspan="6" class="empty-row">No reservation requests found<?= $searchTerm ? ' for "' . htmlspecialchars($searchTerm) . '"' : '' ?>.</td></tr>
        <?php else: foreach ($reservations as $r): ?>
          <?php
            $isGroupRequest = $r['group_id'] !== null && $r['machinery_id'] === null;
            $hasConflict = $r['machinery_id'] !== null && in_array($r['status'], ['pending', 'approved'], true)
                && has_overlapping_reservation($pdo, (int) $r['machinery_id'], $r['start_date'], $r['end_date'], (int) $r['reservation_id']);
            // For a still-unassigned group request, "no stock" means zero
            // units in that group are free for these dates right now — the
            // hard stop that determines whether Approve can even be offered.
            $groupHasNoStock = $isGroupRequest && $r['status'] === 'pending'
                && empty(get_available_machinery_in_group($pdo, (int) $r['group_id'], $r['start_date'], $r['end_date']));
          ?>
          <tr>
            <td>
              <?php if ($r['machinery_name']): ?>
                <?= htmlspecialchars($r['machinery_name']) ?>
                <?php if ($r['group_name']): ?>
                  <span class="muted-note">(<?= htmlspecialchars($r['group_name']) ?>)</span>
                <?php endif; ?>
              <?php elseif ($r['group_name']): ?>
                <?= htmlspecialchars($r['group_name']) ?> <span class="muted-note">(pool — no unit assigned yet)</span>
              <?php else: ?>
                <span class="muted-note">—</span>
              <?php endif; ?>
              <?php if ($hasConflict): ?>
                <span class="status-badge status-pending" title="Overlaps another pending/approved reservation for this machine">⚠ Overlap</span>
              <?php endif; ?>
              <?php if ($groupHasNoStock): ?>
                <span class="status-badge status-inactive" title="Every unit in this group is already booked for these dates">No stock available</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($r['requester_name']) ?></td>
            <td><?= htmlspecialchars($r['start_date']) ?> &rarr; <?= htmlspecialchars($r['end_date']) ?></td>
            <td><?= htmlspecialchars($r['purpose'] ?? '—') ?></td>
            <td>
              <span class="status-badge status-<?= $r['status'] === 'approved' ? 'active' : ($r['status'] === 'pending' ? 'pending' : 'inactive') ?>"><?= htmlspecialchars($r['status']) ?></span>
              <?php if ($r['status'] === 'rejected' && $r['rejection_remark']): ?>
                <div class="muted-note" style="margin-top:0.2rem;"><?= htmlspecialchars($r['rejection_remark']) ?></div>
              <?php endif; ?>
            </td>
            <td class="actions-cell">
              <?php if ($r['status'] === 'pending'): ?>
                <?php if ($isGroupRequest): ?>
                  <?php if ($groupHasNoStock): ?>
                    <span class="muted-note" title="No units available — reject with a remark instead">Approve unavailable</span>
                  <?php else: ?>
                    <a href="<?= base_url('officer/reservation_assign.php?id=' . (int) $r['reservation_id']) ?>" class="link-btn approve">Assign &amp; Approve</a>
                  <?php endif; ?>
                <?php else: ?>
                  <form method="POST" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="reservation_id" value="<?= (int) $r['reservation_id'] ?>">
                    <input type="hidden" name="reservation_action" value="approve_standalone">
                    <button type="submit" class="link-btn approve">Approve</button>
                  </form>
                <?php endif; ?>

                <button type="button" class="link-btn deactivate" onclick="document.getElementById('rejectRow<?= (int) $r['reservation_id'] ?>').style.display='table-row';">Reject</button>
              <?php elseif ($r['status'] === 'approved'): ?>
                <form method="POST" class="inline-form" onsubmit="return confirm('Cancel this approved reservation?');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="reservation_id" value="<?= (int) $r['reservation_id'] ?>">
                  <input type="hidden" name="reservation_action" value="cancel">
                  <button type="submit" class="link-btn deactivate">Cancel</button>
                </form>
              <?php else: ?>
                <span class="muted-note">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php if ($r['status'] === 'pending'): ?>
            <tr id="rejectRow<?= (int) $r['reservation_id'] ?>" class="reject-remark-row" style="display:<?= isset($rejectErrors[$r['reservation_id']]) ? 'table-row' : 'none' ?>;">
              <td colspan="6">
                <form method="POST" class="inline-form" style="display:flex; gap:0.6rem; align-items:flex-start; flex-wrap:wrap;">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <input type="hidden" name="reservation_id" value="<?= (int) $r['reservation_id'] ?>">
                  <input type="hidden" name="reservation_action" value="reject">
                  <div class="form-group" style="flex:1; min-width:220px; margin-bottom:0;">
                    <input type="text" name="rejection_remark" placeholder="Reason for rejecting (required) — e.g. equipment under repair"
                           value="<?= isset($rejectErrors[$r['reservation_id']]) ? htmlspecialchars($_POST['rejection_remark'] ?? '') : '' ?>">
                    <?php if (isset($rejectErrors[$r['reservation_id']])): ?>
                      <p class="muted-note" style="color:var(--clr-error, #b3261e); margin:0.3rem 0 0;"><?= htmlspecialchars($rejectErrors[$r['reservation_id']]) ?></p>
                    <?php endif; ?>
                  </div>
                  <button type="submit" class="link-btn deactivate">Confirm Reject</button>
                  <button type="button" class="link-btn" onclick="document.getElementById('rejectRow<?= (int) $r['reservation_id'] ?>').style.display='none';">Cancel</button>
                </form>
              </td>
            </tr>
          <?php endif; ?>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>

    <?php render_pagination_controls($pagination, $pageBaseUrl . ($searchTerm ? (str_has($pageBaseUrl, '?') ? '&' : '?') . 'q=' . urlencode($searchTerm) : '')); ?>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
