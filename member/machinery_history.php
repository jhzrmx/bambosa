<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/machinery.php';
require_once __DIR__ . '/../includes/pagination.php';

require_role('member');

$userId = (int) $_SESSION['user_id'];

// Two independent search/pagination contexts on this one page — the
// reservation history table uses the standard ?q=/?page= params, and the
// equipment condition table uses its own ?cq=/?cpage= so searching or
// paging through one table never resets or interferes with the other.
$historySearch = get_search_term('q');
$historyPagination = build_pagination(count_reservations_by_user($pdo, $userId, $historySearch), get_current_page('page'));
$myReservations = get_reservations_by_user($pdo, $userId, $historySearch, $historyPagination['per_page'], $historyPagination['offset']);

$conditionSearch = get_search_term('cq');
$conditionPagination = build_pagination(count_machinery($pdo, true, $conditionSearch), get_current_page('cpage'));
$availableMachinery = get_all_machinery($pdo, true, $conditionSearch, $conditionPagination['per_page'], $conditionPagination['offset']);

$activeNav = 'machinery_history';
$pageBaseUrl = base_url('member/machinery_history.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
<link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<title>Reservation History · BAMBOSA Member</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/member_sidebar.php'; ?>

  <main class="dash-main">
    <h1>Reservation History</h1>
    <p>
      Your past and current machinery reservation requests, plus the condition of equipment
      currently available. Need to make a new request? Go to
      <a href="<?= base_url('member/machinery.php') ?>">Reserve Machinery</a>.
    </p>

    <h2 class="section-heading">My Reservation History</h2>

    <?php render_search_box($pageBaseUrl, $historySearch, 'Search by machine, group, or purpose…', ['cq' => $conditionSearch]); ?>

    <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>Machine</th>
          <th>Dates</th>
          <th>Purpose</th>
          <th>Status</th>
          <th>Remark</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($myReservations)): ?>
          <tr><td colspan="5" class="empty-row">
            <?= $historySearch ? 'No reservations found for "' . htmlspecialchars($historySearch) . '".' : "You haven't made any reservation requests yet." ?>
          </td></tr>
        <?php else: foreach ($myReservations as $r): ?>
          <tr>
            <td>
              <?php if ($r['machinery_name']): ?>
                <?= htmlspecialchars($r['machinery_name']) ?>
                <?php if ($r['group_name']): ?>
                  <span class="muted-note">(<?= htmlspecialchars($r['group_name']) ?>)</span>
                <?php endif; ?>
              <?php elseif ($r['group_name']): ?>
                <?= htmlspecialchars($r['group_name']) ?> <span class="muted-note">(awaiting unit assignment)</span>
              <?php else: ?>
                <span class="muted-note">—</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($r['start_date']) ?> &rarr; <?= htmlspecialchars($r['end_date']) ?></td>
            <td><?= htmlspecialchars($r['purpose'] ?? '—') ?></td>
            <td><span class="status-badge status-<?= $r['status'] === 'approved' ? 'active' : ($r['status'] === 'pending' ? 'pending' : 'inactive') ?>"><?= htmlspecialchars($r['status']) ?></span></td>
            <td><?= $r['rejection_remark'] ? htmlspecialchars($r['rejection_remark']) : '—' ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>

    <?php
      $historyPageBase = $pageBaseUrl;
      $historyPreserved = [];
      if ($historySearch) { $historyPreserved[] = 'q=' . urlencode($historySearch); }
      if ($conditionSearch) { $historyPreserved[] = 'cq=' . urlencode($conditionSearch); }
      if ($conditionPagination['current_page'] > 1) { $historyPreserved[] = 'cpage=' . $conditionPagination['current_page']; }
      if ($historyPreserved) { $historyPageBase .= '?' . implode('&', $historyPreserved); }
      render_pagination_controls($historyPagination, $historyPageBase);
    ?>

    <h2 class="section-heading">Equipment Condition</h2>
    <p class="muted-note">
      Condition/damage level for each available machine, so you know what to expect before requesting.
      For an equipment pool, this shows every unit currently in that pool — the officer will assign
      whichever one is actually free for your dates.
    </p>

    <?php render_search_box($pageBaseUrl, $conditionSearch, 'Search by machine or group name…', ['q' => $historySearch], 'cq'); ?>

    <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>Machine</th>
          <th>Type/Group</th>
          <th>Condition</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($availableMachinery)): ?>
          <tr><td colspan="3" class="empty-row">
            <?= $conditionSearch ? 'No machinery found for "' . htmlspecialchars($conditionSearch) . '".' : 'No machinery is currently available.' ?>
          </td></tr>
        <?php else: foreach ($availableMachinery as $m): ?>
          <tr>
            <td><?= htmlspecialchars($m['name']) ?></td>
            <td><?= $m['group_name'] ? htmlspecialchars($m['group_name']) : '<span class="muted-note">— (standalone)</span>' ?></td>
            <td>
              <div class="condition-bar" title="<?= (int) $m['damage_level'] ?>% damaged">
                <div class="condition-bar-fill condition-<?= damage_severity_class((int) $m['damage_level']) ?>" style="width:<?= (int) $m['damage_level'] ?>%;"></div>
              </div>
              <span class="muted-note"><?= (int) $m['damage_level'] ?>% damaged</span>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>

    <?php
      $conditionPageBase = $pageBaseUrl;
      $conditionPreserved = [];
      if ($conditionSearch) { $conditionPreserved[] = 'cq=' . urlencode($conditionSearch); }
      if ($historySearch) { $conditionPreserved[] = 'q=' . urlencode($historySearch); }
      if ($historyPagination['current_page'] > 1) { $conditionPreserved[] = 'page=' . $historyPagination['current_page']; }
      if ($conditionPreserved) { $conditionPageBase .= '?' . implode('&', $conditionPreserved); }
      render_pagination_controls($conditionPagination, $conditionPageBase, 'cpage');
    ?>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
