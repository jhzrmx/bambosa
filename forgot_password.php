<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/password_reset.php';

if (is_logged_in()) {
    redirect_to_dashboard();
}

$errors = [];
$submitted = false;
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $oldEmail = trim($_POST['email'] ?? '');

    if (!filter_var($oldEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($errors)) {
        try {
            // request_password_reset() handles the "does this email even
            // exist" check internally and never reveals the answer — see
            // its doc comment in includes/password_reset.php. $submitted
            // is set to true regardless of what happens inside it, and the
            // page below shows the same message either way.
            request_password_reset($pdo, $oldEmail);
        } catch (Exception $e) {
            // Even an unexpected error here (e.g. a DB hiccup) should not
            // reveal anything different to the user than the success case —
            // log it for the officer/admin to notice, but still show the
            // same generic confirmation.
            error_log('Forgot password error: ' . $e->getMessage());
        }
        $submitted = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot Password · BAMBOSA</title>
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
    <h2>Forgot your password?</h2>
    <p class="subtitle">Enter your account email and we'll send you a link to reset it.</p>

    <?php if ($submitted): ?>
      <div class="alert alert-success">
        If that email address is registered with BAMBOSA, a password reset link has been sent.
        It expires in <?= PASSWORD_RESET_TTL_MINUTES ?> minutes. Please check your inbox (and spam folder).
      </div>
      <div class="auth-footer-link">
        <a href="<?= base_url('login.php') ?>">Back to log in</a>
      </div>
    <?php else: ?>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
          <?php foreach ($errors as $err): ?>
            <div><?= htmlspecialchars($err) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?= base_url('forgot_password.php') ?>" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" required
                 value="<?= htmlspecialchars($oldEmail) ?>">
        </div>

        <button type="submit" class="btn-full">Send Reset Link</button>
      </form>

      <div class="auth-footer-link">
        <a href="<?= base_url('login.php') ?>">Back to log in</a>
      </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
