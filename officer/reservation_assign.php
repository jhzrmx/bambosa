<?php
/**
 * "Which specific unit am I handing over?" step for approving a
 * GROUP-targeted reservation request. A member reserves a machinery
 * group (e.g. "Hand Tractors"); this page is where an officer picks
 * exactly which physical unit in that group to actually assign before
 * the request becomes approved.
 *
 * Only reachable for a reservation that's still pending AND targets a
 * group (machinery_id IS NULL, group_id IS NOT NULL) — anything else
 * redirects back to the reservations list, since there's nothing to
 * assign otherwise (a standalone request is approved directly from that
 * list; an already-decided request has nothing left to assign).
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/machinery.php';

require_role('officer');

$reservationId = (int) ($_GET['id'] ?? $_POST['reservation_id'] ?? 0);
$reservation = $reservationId > 0 ? get_reservation_by_id($pdo, $reservationId) : null;

if (!$reservation || $reservation['status'] !== 'pending' || $reservation['group_id'] === null || $reservation['machinery_id'] !== null) {
    header('Location: ' . base_url('officer/reservations.php'));
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $machineryId = (int) ($_POST['machinery_id'] ?? 0);

    if ($machineryId <= 0) {
        $errors[] = 'Please select which unit to assign.';
    }

    if (empty($errors)) {
        if (assign_and_approve_reservation($pdo, $reservationId, $machineryId)) {
            header('Location: ' . base_url('officer/reservations.php?flash=updated'));
            exit;
        }
        // Someone else grabbed that unit (or it became unavailable) between
        // this page loading and the form being submitted — re-show the
        // page with fresh availability rather than silently double-booking.
        $errors[] = 'That unit is no longer available for these dates. Please choose another, or reject the request if none are free.';
    }
}

$availableUnits = get_available_machinery_in_group($pdo, (int) $reservation['group_id'], $reservation['start_date'], $reservation['end_date'], $reservationId);
$group = get_machinery_group_by_id($pdo, (int) $reservation['group_id']);
$activeNav = 'reservations';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
<link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<title>Assign Machinery · BAMBOSA Officer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/officer_sidebar.php'; ?>

  <main class="dash-main">
    <h1>Assign Machinery &amp; Approve</h1>
    <p>
      <?= htmlspecialchars($reservation['requester_name'] ?? '') ?>
      requested <strong><?= htmlspecialchars($group['group_name'] ?? 'this group') ?></strong>
      for <?= htmlspecialchars($reservation['start_date']) ?> &rarr; <?= htmlspecialchars($reservation['end_date']) ?>.
      Pick which specific unit to hand over.
    </p>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (empty($availableUnits)): ?>
      <div class="alert alert-error">
        Every unit in this group is now booked for these dates — there's nothing left to
        assign. Go back and reject this request with a remark instead.
      </div>
      <a href="<?= base_url('officer/reservations.php') ?>"><button type="button" class="btn-full" style="max-width:220px;">Back to Reservations</button></a>
    <?php else: ?>
      <form method="POST" action="<?= base_url('officer/reservation_assign.php?id=' . $reservationId) ?>" class="inline-card-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="assign">
        <input type="hidden" name="reservation_id" value="<?= $reservationId ?>">

        <div class="form-group">
          <label>Available Units</label>
          <?php foreach ($availableUnits as $unit): ?>
            <label class="checkbox-list-item" style="display:flex; align-items:center; gap:0.6rem; padding:0.5rem 0; border-bottom:1px solid var(--clr-border);">
              <input type="radio" name="machinery_id" value="<?= (int) $unit['machinery_id'] ?>" required style="width:auto;">
              <span>
                <strong><?= htmlspecialchars($unit['name']) ?></strong>
                <?php if ($unit['description']): ?> — <?= htmlspecialchars($unit['description']) ?><?php endif; ?>
                <br>
                <span class="muted-note">Condition: <?= (int) $unit['damage_level'] ?>% damaged</span>
              </span>
            </label>
          <?php endforeach; ?>
        </div>

        <button type="submit" class="btn-full" style="max-width:220px; margin-top:1rem;">Assign &amp; Approve</button>
        <a href="<?= base_url('officer/reservations.php') ?>" style="margin-left:1rem;">Cancel</a>
      </form>
    <?php endif; ?>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
