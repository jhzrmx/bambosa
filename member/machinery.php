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

    $machineChoice = $_POST['machine_choice'] ?? '';
    $startDate   = $_POST['start_date'] ?? '';
    $endDate     = $_POST['end_date'] ?? '';
    $purpose     = trim($_POST['purpose'] ?? '');

    $today = date('Y-m-d');

    $groupId = null;
    $machineryId = null;
    if (preg_match('/^group:(\d+)$/', $machineChoice, $matches)) {
        $groupId = (int) $matches[1];
    } elseif (preg_match('/^machine:(\d+)$/', $machineChoice, $matches)) {
        $machineryId = (int) $matches[1];
    }

    if ($groupId === null && $machineryId === null) {
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
        if ($groupId !== null) {
            // Every group shown on this form already has at least one
            // available unit in general (see get_available_machinery_groups()
            // below) — but that doesn't guarantee one is free for THESE
            // specific dates. This is just an informational heads-up, same
            // spirit as the old single-machine overlap warning: the request
            // still goes in as pending either way, since more units could
            // free up (or the officer may know something this quick check
            // doesn't) before it's reviewed. The hard stop is later, at
            // approval time — see officer/reservation_assign.php — where an
            // officer literally cannot approve a group with zero free units
            // for the requested dates.
            if (empty(get_available_machinery_in_group($pdo, $groupId, $startDate, $endDate))) {
                $conflictWarning = 'Heads up: every unit of this machine type already has a pending or approved reservation that overlaps your requested dates. You can still submit — the officer will review availability, but approval may not be possible unless something opens up.';
            }
            create_reservation($pdo, null, $groupId, $userId, $startDate, $endDate, $purpose);
        } else {
            if (has_overlapping_reservation($pdo, $machineryId, $startDate, $endDate)) {
                $conflictWarning = 'Heads up: this machine already has a pending or approved reservation that overlaps your requested dates. You can still submit — the officer will review availability.';
            }
            create_reservation($pdo, $machineryId, null, $userId, $startDate, $endDate, $purpose);
        }

        header('Location: ' . base_url('member/machinery.php?flash=requested'));
        exit;
    }
}

$machineryGroups = get_available_machinery_groups($pdo); // groups with at least one available unit
$standaloneMachinery = get_all_machinery($pdo, true); // available, ungrouped machines only
$standaloneMachinery = array_values(array_filter($standaloneMachinery, fn($m) => $m['group_id'] === null));
$flash = $_GET['flash'] ?? null;
$activeNav = 'machinery';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
<link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
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

    <?php if (empty($machineryGroups) && empty($standaloneMachinery)): ?>
      <p class="muted-note">No machinery is currently available for reservation.</p>
    <?php else: ?>
      <form method="POST" action="<?= base_url('member/machinery.php') ?>" class="inline-card-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="request">

        <div class="form-group">
          <label for="machine_choice">Machine</label>
          <select id="machine_choice" name="machine_choice" required>
            <option value="">— Select —</option>
            <?php if (!empty($machineryGroups)): ?>
              <optgroup label="Equipment pools (any available unit)">
                <?php foreach ($machineryGroups as $g): ?>
                  <option value="group:<?= (int) $g['group_id'] ?>">
                    <?= htmlspecialchars($g['group_name']) ?> (<?= (int) $g['available_count'] ?> unit<?= $g['available_count'] === 1 ? '' : 's' ?> available)
                  </option>
                <?php endforeach; ?>
              </optgroup>
            <?php endif; ?>
            <?php if (!empty($standaloneMachinery)): ?>
              <optgroup label="Standalone Machinery">
                <?php foreach ($standaloneMachinery as $m): ?>
                  <option value="machine:<?= (int) $m['machinery_id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endif; ?>
          </select>
          <p class="muted-note" style="margin:0.3rem 0 0;">
            Equipment pools mean an officer will assign whichever specific unit is free
            when reviewing your request — you don't need to guess which one. Standalone
            machinery is reserved directly, since there's only one of that kind.
          </p>
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

    <p class="muted-note">
      Want to check a machine's condition before requesting, or see your past requests?
      Visit <a href="<?= base_url('member/machinery_history.php') ?>">Reservation History</a>.
    </p>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
