<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/machinery.php';
require_once __DIR__ . '/../includes/schedules.php';
require_once __DIR__ . '/../includes/billing.php';
require_once __DIR__ . '/../includes/calendar_events.php';

require_role('member'); // only members may view this page

$userId = (int) $_SESSION['user_id'];

$mySectorIds = get_member_sector_ids($pdo, $userId);
$nextRelease = null;
foreach ($mySectorIds as $sectorId) {
    $upcoming = get_schedules_by_sector($pdo, (int) $sectorId, true);
    if (!empty($upcoming) && ($nextRelease === null || strtotime($upcoming[0]['start_time']) < strtotime($nextRelease['start_time']))) {
        $nextRelease = $upcoming[0];
    }
}

$myReservations = get_reservations_by_user($pdo, $userId);
$activeReservationCount = count(array_filter($myReservations, fn($r) => in_array($r['status'], ['pending', 'approved'], true)));
$outstandingBalance = get_outstanding_balance_for_user($pdo, $userId);
$activeNav = 'dashboard';

$calToday = (int) date('Y');
$calMonthToday = (int) date('n');
$calendarMonth = get_calendar_events_for_month($pdo, $calToday, $calMonthToday, $mySectorIds, $userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Member Dashboard · BAMBOSA</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/member_sidebar.php'; ?>

  <main class="dash-main">
    <h1>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></h1>
    <p>This is your member dashboard.</p>

    <div class="card-grid">
      <a href="<?= base_url('member/billing.php') ?>" class="stat-card" style="display:block; text-decoration:none; color:inherit;">
        <div class="label">Outstanding Balance</div>
        <div class="value" style="font-size:1.3rem;">₱<?= number_format($outstandingBalance, 2) ?></div>
      </a>
      <a href="<?= base_url('member/schedule.php') ?>" class="stat-card" style="display:block; text-decoration:none; color:inherit;">
        <div class="label">Next Water Release</div>
        <div class="value" style="font-size:1.1rem;">
          <?= $nextRelease ? htmlspecialchars(date('M j, g:i A', strtotime($nextRelease['start_time']))) : 'None scheduled' ?>
        </div>
      </a>
      <a href="<?= base_url('member/machinery.php') ?>" class="stat-card" style="display:block; text-decoration:none; color:inherit;">
        <div class="label">Active Reservations</div>
        <div class="value"><?= $activeReservationCount ?></div>
      </a>
    </div>

    <div class="dash-calendar" id="dashCalendar">
      <div class="cal-main">
        <div class="cal-toolbar">
          <h2 data-cal-header></h2>
          <div class="cal-nav">
            <button type="button" class="cal-nav-btn" data-cal-prev aria-label="Previous month">&larr;</button>
            <button type="button" class="cal-today-btn" data-cal-today>Today</button>
            <button type="button" class="cal-nav-btn" data-cal-next aria-label="Next month">&rarr;</button>
          </div>
        </div>
        <div class="cal-legend">
          <span class="cal-legend-item"><span class="cal-dot cal-dot-schedule"></span> Water release (your sector)</span>
          <span class="cal-legend-item"><span class="cal-dot cal-dot-reservation"></span> Your machine reservation</span>
        </div>
        <div class="cal-grid" data-cal-grid></div>
      </div>
      <div class="cal-side" data-cal-detail>
        <p class="muted-note">Click a day to see what's happening.</p>
      </div>
    </div>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
<script>
  window.BAMBOSA_CALENDAR_INITIAL = {
    year: <?= (int) $calToday ?>,
    month: <?= (int) $calMonthToday ?>,
    events_by_day: <?= json_encode($calendarMonth['events_by_day'], JSON_UNESCAPED_SLASHES) ?>,
    data_url: <?= json_encode(base_url('calendar_data.php')) ?>,
    today: { year: <?= (int) $calToday ?>, month: <?= (int) $calMonthToday ?>, day: <?= (int) date('j') ?> }
  };
</script>
<script src="<?= base_url('assets/js/dashboard-calendar.js') ?>"></script>
</body>
</html>
