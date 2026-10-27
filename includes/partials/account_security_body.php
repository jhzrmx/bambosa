<?php
/**
 * Body of the Account Security page, shared by admin/, officer/ and
 * member/security.php. Expects from the including page:
 *   $securityUrl     full URL of the including page (base_url(...))
 *   $passwordErrors  array of error strings
 *   $passwordSaved   bool
 *   $showFailedOnly  bool (login history filter)
 *   $failedSummary   result of get_user_failed_login_summary()
 *   $logs, $pagination  this page of the user's login history
 */
$historyBaseUrl = $securityUrl . ($showFailedOnly ? '?show=failed' : '');
?>
<h1>Account Security</h1>
<p>Change your password and review every sign-in attempt on your account.</p>

<h2 class="section-heading">Change Password</h2>

<?php if ($passwordSaved): ?>
  <div class="alert alert-success">Password updated successfully.</div>
<?php endif; ?>

<?php if (!empty($passwordErrors)): ?>
  <div class="alert alert-error">
    <?php foreach ($passwordErrors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="POST" action="<?= htmlspecialchars($securityUrl) ?>" class="inline-card-form">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

  <div class="form-group">
    <label for="current_password">Current Password</label>
    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="new_password">New Password</label>
      <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
    </div>
    <div class="form-group">
      <label for="confirm_password">Confirm New Password</label>
      <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
    </div>
  </div>

  <p class="muted-note">Must be at least 8 characters.</p>

  <button type="submit" class="btn-full" style="max-width:220px;">Update Password</button>
</form>

<h2 class="section-heading">Login Activity</h2>

<?php if ($failedSummary['recent'] > 0): ?>
  <div class="alert alert-error">
    <strong><?= (int) $failedSummary['recent'] ?> failed sign-in attempt<?= $failedSummary['recent'] === 1 ? '' : 's' ?></strong>
    on your account in the last 30 days (most recent:
    <?= htmlspecialchars(date('M j, Y g:i A', strtotime($failedSummary['last_failed']))) ?>).
    If you don't recognize them, change your password above.
  </div>
<?php elseif ($failedSummary['last_failed'] !== null): ?>
  <p class="muted-note">No failed sign-in attempts in the last 30 days (last one:
    <?= htmlspecialchars(date('M j, Y g:i A', strtotime($failedSummary['last_failed']))) ?>).</p>
<?php else: ?>
  <p class="muted-note">No failed sign-in attempts have been recorded on your account.</p>
<?php endif; ?>

<p>
  <?php if ($showFailedOnly): ?>
    <a href="<?= htmlspecialchars($securityUrl) ?>">All attempts</a> &middot; <strong>Failed only</strong>
  <?php else: ?>
    <strong>All attempts</strong> &middot; <a href="<?= htmlspecialchars($securityUrl . '?show=failed') ?>">Failed only</a>
  <?php endif; ?>
</p>

<div class="table-scroll">
<table class="data-table">
  <thead>
    <tr>
      <th>Date &amp; Time</th>
      <th>IP Address</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($logs)): ?>
      <tr><td colspan="3" class="empty-row"><?= $showFailedOnly ? 'No failed attempts recorded.' : 'No login activity recorded yet.' ?></td></tr>
    <?php else: foreach ($logs as $log): ?>
      <tr>
        <td><?= htmlspecialchars(date('M j, Y g:i:s A', strtotime($log['login_time']))) ?></td>
        <td><?= !empty($log['ip_address']) ? htmlspecialchars($log['ip_address']) : '—' ?></td>
        <td>
          <?php $isOk = $log['status'] === 'Success'; ?>
          <span class="login-status <?= $isOk ? 'login-status-ok' : 'login-status-fail' ?>"><?= htmlspecialchars($log['status']) ?></span>
        </td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>
</div>

<?php render_pagination_controls($pagination, $historyBaseUrl); ?>
