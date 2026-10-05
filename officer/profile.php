<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';

require_role('officer');

$userId = (int) $_SESSION['user_id'];
$errors = [];
$saved = false;
$passwordErrors = [];
$passwordSaved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'password') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $passwordErrors[] = 'Invalid form submission. Please try again.';
    }

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $passwordErrors[] = 'Please fill in all three password fields.';
    } elseif (strlen($newPassword) < 8) {
        $passwordErrors[] = 'New password must be at least 8 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $passwordErrors[] = 'New password and confirmation do not match.';
    }

    if (empty($passwordErrors)) {
        if (change_user_password($pdo, $userId, $currentPassword, $newPassword)) {
            $passwordSaved = true;
        } else {
            $passwordErrors[] = 'Current password is incorrect.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $contact  = trim($_POST['contact_number'] ?? '');
    $email    = trim($_POST['email'] ?? '');

    $current      = get_user_by_id($pdo, $userId);
    $emailChanged = $current && strcasecmp($email, $current['email']) !== 0;

    if ($fullName === '') {
        $errors[] = 'Full name cannot be empty.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $errors[] = 'Please enter a valid email address.';
    } elseif ($emailChanged) {
        if (email_in_use($pdo, $email, $userId)) {
            $errors[] = 'That email address is already used by another account.';
        } elseif (!verify_user_password($pdo, $userId, $_POST['email_current_password'] ?? '')) {
            // Email is the login + password-reset address, so confirm identity first.
            $errors[] = 'Enter your current password to change your email address.';
        }
    }

    if (empty($errors)) {
        try {
            update_user_profile($pdo, $userId, $fullName, $contact, $emailChanged ? $email : null);
            $_SESSION['full_name'] = $fullName; // keep sidebar greeting in sync
            $saved = true;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'That email address is already used by another account.';
            } else {
                throw $e;
            }
        }
    }
}

$user = get_user_by_id($pdo, $userId);
$activeNav = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
<link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<title>My Profile · BAMBOSA Officer</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/officer_sidebar.php'; ?>

  <main class="dash-main">
    <h1>My Profile</h1>

    <?php if ($saved): ?>
      <div class="alert alert-success">Profile updated successfully.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h2 class="section-heading">Account Information</h2>
    <form method="POST" action="<?= base_url('officer/profile.php') ?>" class="inline-card-form">
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
          <input type="email" id="email" name="email" required maxlength="150" autocomplete="email"
                 data-original="<?= htmlspecialchars($user['email']) ?>"
                 value="<?= htmlspecialchars($_SERVER['REQUEST_METHOD'] === 'POST' && !$saved && isset($_POST['email']) && ($_POST['form'] ?? '') !== 'password' ? $_POST['email'] : $user['email']) ?>">
        </div>
        <div class="form-group" id="emailPasswordGroup" style="display:none;">
          <label for="email_current_password">Current Password</label>
          <input type="password" id="email_current_password" name="email_current_password" autocomplete="current-password">
        </div>
      </div>
      <p class="muted-note" style="margin-top:0;">This is the email you log in with and where password reset links are sent. Changing it requires your current password.</p>

      <button type="submit" class="btn-full" style="max-width:220px;">Save Changes</button>
    </form>

    <h2 class="section-heading">Change Password</h2>

    <?php if ($passwordSaved): ?>
      <div class="alert alert-success">Password updated successfully.</div>
    <?php endif; ?>

    <?php if (!empty($passwordErrors)): ?>
      <div class="alert alert-error">
        <?php foreach ($passwordErrors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= base_url('officer/profile.php') ?>" class="inline-card-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="form" value="password">

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
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
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
</body>
</html>
