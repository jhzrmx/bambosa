<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

if (is_logged_in()) {
    redirect_to_dashboard();
}

$errors = [];
$old = ['full_name' => '', 'email' => '', 'contact_number' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $old['full_name']      = trim($_POST['full_name'] ?? '');
    $old['email']          = trim($_POST['email'] ?? '');
    $old['contact_number'] = trim($_POST['contact_number'] ?? '');
    $password              = $_POST['password'] ?? '';
    $confirm_password      = $_POST['confirm_password'] ?? '';

    // --- Validation ---
    if ($old['full_name'] === '') {
        $errors[] = 'Full name is required.';
    }

    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    // Every self-registered account is a "member" by default.
    // Officer accounts are provisioned directly by an administrator (not via public signup).
    $role = 'member';

    if (empty($errors)) {
        try {
            // Check for existing email
            $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email');
            $stmt->execute(['email' => $old['email']]);

            if ($stmt->fetch()) {
                $errors[] = 'An account with that email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare(
                    'INSERT INTO users (full_name, email, password_hash, role, contact_number, status)
                     VALUES (:full_name, :email, :password_hash, :role, :contact_number, :status)'
                );
                $stmt->execute([
                    'full_name'      => $old['full_name'],
                    'email'          => $old['email'],
                    'password_hash'  => $hash,
                    'role'           => $role,
                    'contact_number' => $old['contact_number'] ?: null,
                    // New members start "pending" until an officer verifies/approves them.
                    // Change to 'active' below if you want instant access instead.
                    'status'         => 'pending',
                ]);

                header('Location: ' . base_url('login.php?registered=1'));
                exit;
            }
        } catch (PDOException $e) {
            error_log('Signup error: ' . $e->getMessage());
            $errors[] = 'Something went wrong while creating your account. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sign Up · BAMBOSA</title>
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
    <h2>Create your account</h2>
    <p class="subtitle">Register as a BAMBOSA member to access irrigation services.</p>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $err): ?>
          <div><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= base_url('signup.php') ?>" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

      <div class="form-group">
        <label for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name" required
               value="<?= htmlspecialchars($old['full_name']) ?>">
      </div>

      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" required
               value="<?= htmlspecialchars($old['email']) ?>">
      </div>

      <div class="form-group">
        <label for="contact_number">Contact Number (optional)</label>
        <input type="text" id="contact_number" name="contact_number"
               value="<?= htmlspecialchars($old['contact_number']) ?>">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required minlength="8">
        </div>
        <div class="form-group">
          <label for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
        </div>
      </div>

      <button type="submit" class="btn-full">Create Account</button>
    </form>

    <div class="auth-footer-link">
      Already have an account? <a href="<?= base_url('login.php') ?>">Log in</a>
    </div>
  </div>
</div>

</body>
</html>
