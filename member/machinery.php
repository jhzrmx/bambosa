<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/machinery.php';

require_role('member');

$userId = (int) $_SESSION['user_id'];
$errors = [];
$conflictWarning = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $machineryId = (int) ($_POST['machinery_id'] ?? 0);
    $startDate   = $_POST['start_date'] ?? '';
    $endDate     = $_POST['end_date'] ?? '';
    $purpose     = trim($_POST['purpose'] ?? '');

    $today = date('Y-m-d');

    if ($machineryId <= 0) {
        $errors[] = 'Please select a machine.';
    }
    if (!$startDate || !$endDate) {
        $errors[] = 'Please provide both a start and end date.';
    } elseif ($startDate < $today) {
        $errors[] = 'Start date cannot be in the past.';
    } elseif ($endDate < $startDate) {
        $errors[] = 'End date cannot be before the start date.';
    }

    if (empty($errors)) {
        if (has_overlapping_reservation($pdo, $machineryId, $startDate, $endDate)) {
            // Not a hard block — just an informational warning, since the
            // officer makes the final approval call.
            $conflictWarning = 'Heads up: this machine already has a pending or approved reservation that overlaps your requested dates. You can still submit — the officer will review availability.';
        }

        create_reservation($pdo, $machineryId, $userId, $startDate, $endDate, $purpose);
        header('Location: ' . base_url('member/machinery.php?flash=requested'));
        exit;
    }
}

$machineryList = get_all_machinery($pdo, true); // only available equipment
$myReservations = get_reservations_by_user($pdo, $userId);
$flash = $_GET['flash'] ?? null;
$activeNav = 'machinery';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reserve Machinery · BAMBOSA Member</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/member_sidebar.php'; ?>

  <main class="dash-main">
    <h1>Reserve Machinery</h1>
    <p>Submit a request to borrow farming equipment. An officer will review and approve or reject it.</p>

    <?php if ($flash === 'requested'): ?>
      <div class="alert alert-success">Your reservation request has been submitted and is pending officer approval.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($conflictWarning): ?>
      <div class="alert alert-error"><?= htmlspecialchars($conflictWarning) ?></div>
    <?php endif; ?>

    <h2 class="section-heading">New Reservation Request</h2>

    <?php if (empty($machineryList)): ?>
      <p class="muted-note">No machinery is currently available for reservation.</p>
    <?php else: ?>
      <form method="POST" action="<?= base_url('member/machinery.php') ?>" class="inline-card-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="request">

        <div class="form-group">
          <label for="machinery_id">Machine</label>
          <select id="machinery_id" name="machinery_id" required>
            <option value="">— Select —</option>
            <?php foreach ($machineryList as $m): ?>
              <option value="<?= (int) $m['machinery_id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="start_date">Start Date</label>
            <input type="date" id="start_date" name="start_date" required min="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label for="end_date">End Date</label>
            <input type="date" id="end_date" name="end_date" required min="<?= date('Y-m-d') ?>">
          </div>
        </div>

        <div class="form-group">
          <label for="purpose">Purpose (optional)</label>
          <input type="text" id="purpose" name="purpose" placeholder="e.g. Land preparation for wet season">
        </div>

        <button type="submit" class="btn-full" style="max-width:220px;">Submit Request</button>
      </form>
    <?php endif; ?>

    <h2 class="section-heading">My Reservation History</h2>

    <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>Machine</th>
          <th>Dates</th>
          <th>Purpose</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($myReservations)): ?>
          <tr><td colspan="4" class="empty-row">You haven't made any reservation requests yet.</td></tr>
        <?php else: foreach ($myReservations as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['machinery_name']) ?></td>
            <td><?= htmlspecialchars($r['start_date']) ?> &rarr; <?= htmlspecialchars($r['end_date']) ?></td>
            <td><?= htmlspecialchars($r['purpose'] ?? '—') ?></td>
            <td><span class="status-badge status-<?= $r['status'] === 'approved' ? 'active' : ($r['status'] === 'pending' ? 'pending' : 'inactive') ?>"><?= htmlspecialchars($r['status']) ?></span></td>
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
