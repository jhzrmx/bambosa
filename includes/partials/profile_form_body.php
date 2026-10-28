<?php
/**
 * Account Information form + alerts, shared by admin/, officer/ and
 * member/profile.php. Expects from the including page:
 *   $user         the current user row (get_user_by_id())
 *   $errors       array of error strings
 *   $saved        bool
 *   $profileUrl   full URL of the including page (the form posts back to it)
 *   $securityUrl  full URL of the role's Account Security page
 */
?>
<?php if ($saved): ?>
  <div class="alert alert-success">Profile updated successfully.</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <?php foreach ($errors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<h2 class="section-heading">Account Information</h2>
<form method="POST" action="<?= htmlspecialchars($profileUrl) ?>" class="inline-card-form">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

  <div class="form-row">
    <div class="form-group">
      <label for="full_name">Full Name</label>
      <input type="text" id="full_name" name="full_name" required
             value="<?= htmlspecialchars($user['full_name']) ?>">
    </div>
    <div class="form-group">
      <label for="contact_number">Contact Number</label>
      <input type="text" id="contact_number" name="contact_number"
             value="<?= htmlspecialchars($user['contact_number'] ?? '') ?>">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label for="email">Email Address</label>
      <input type="email" id="email" name="email" required maxlength="<?= EMAIL_MAX_LENGTH ?>" autocomplete="email"
             data-original="<?= htmlspecialchars($user['email']) ?>"
             value="<?= htmlspecialchars($_SERVER['REQUEST_METHOD'] === 'POST' && !$saved && isset($_POST['email']) ? $_POST['email'] : $user['email']) ?>">
    </div>
    <div class="form-group" id="emailPasswordGroup" style="display:none;">
      <label for="email_current_password">Current Password</label>
      <input type="password" id="email_current_password" name="email_current_password" autocomplete="current-password">
    </div>
  </div>
  <p class="muted-note" style="margin-top:0;">This is the email you log in with and where password reset links are sent. Changing it requires your current password.</p>

  <button type="submit" class="btn-full" style="max-width:220px;">Save Changes</button>
</form>

<p class="muted-note">To change your password or review sign-in activity, go to <a href="<?= htmlspecialchars($securityUrl) ?>">Account Security</a>.</p>

<script>
// Show the "current password" box only when the email has been edited.
(function () {
  var email = document.getElementById('email');
  var group = document.getElementById('emailPasswordGroup');
  var pw = document.getElementById('email_current_password');
  if (!email || !group) return;
  function sync() {
    var changed = email.value.trim().toLowerCase() !== email.dataset.original.toLowerCase();
    group.style.display = changed ? '' : 'none';
    pw.required = changed;
  }
  email.addEventListener('input', sync);
  sync();
})();
</script>
