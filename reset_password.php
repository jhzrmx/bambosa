<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/password_reset.php';

if (is_logged_in()) {
    redirect_to_dashboard();
}

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$errors = [];
$success = false;

// Checked up front so the form isn't even shown for an already-dead link —
// but consume_password_reset() re-checks validity again at submit time
// regardless (see its doc comment for why: time can pass between this
// check and the form being submitted).
$tokenLooksValid = $token !== '' && verify_password_reset_token($pdo, $token) !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword === '' || $confirmPassword === '') {
        $errors[] = 'Please fill in both password fields.';
    } elseif (strlen($newPassword) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        if (consume_password_reset($pdo, $token, $newPassword)) {
            $success = true;
        } else {
            $errors[] = 'This reset link is invalid, expired, or has already been used. Please request a new one.';
            $tokenLooksValid = false;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reset Password · BAMBOSA</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<header class="navbar">
  <div class="brand">🌾 BAMBOSA</div>
  <nav>
    <a href="<?= base_url('login.php') ?>" class="btn-outline">Log In</a>
  </nav>
</header>

<div class="auth-wrapper">
  <div class="auth-card">
    <h2>Reset your password</h2>

    <?php if ($success): ?>
      <div class="alert alert-success">
        Your password has been reset. You can now log in with your new password.
      </div>
      <div class="auth-footer-link">
        <a href="<?= base_url('login.php') ?>">Go to log in</a>
      </div>

    <?php elseif (!$tokenLooksValid): ?>
      <div class="alert alert-error">
        This reset link is invalid, expired, or has already been used.
      </div>
      <div class="auth-footer-link">
        <a href="<?= base_url('forgot_password.php') ?>">Request a new reset link</a>
      </div>

    <?php else: ?>
      <p class="subtitle">Choose a new password for your account.</p>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
          <?php foreach ($errors as $err): ?>
            <div><?= htmlspecialchars($err) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?= base_url('reset_password.php') ?>" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <div class="form-group">
          <label for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
        </div>

        <div class="form-group">
          <label for="confirm_password">Confirm New Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
        </div>

        <p class="muted-note">Must be at least 8 characters.</p>

        <button type="submit" class="btn-full">Reset Password</button>
      </form>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
