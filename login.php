<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

if (is_logged_in()) {
    redirect_to_dashboard();
}

$errors = [];
$old_email = '';
$just_registered = isset($_GET['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $old_email = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';

    if ($old_email === '' || $password === '') {
        $errors[] = 'Please enter both email and password.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $old_email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $errors[] = 'Incorrect email or password.';
            } elseif ($user['status'] === 'pending') {
                $errors[] = 'Your account is pending approval by an association officer.';
            } elseif ($user['status'] === 'inactive') {
                $errors[] = 'Your account has been deactivated. Please contact an officer.';
            } else {
                // Prevent session fixation
                session_regenerate_id(true);

                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role']      = $user['role'];

                // Optional login audit
                try {
                    $log = $pdo->prepare(
                        'INSERT INTO login_logs (user_id, ip_address) VALUES (:uid, :ip)'
                    );
                    $log->execute([
                        'uid' => $user['user_id'],
                        'ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
                    ]);
                } catch (PDOException $e) {
                    error_log('Login log error: ' . $e->getMessage());
                }

                redirect_to_dashboard();
            }
        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Log In · BAMBOSA</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<header class="navbar">
  <div class="brand">🌾 BAMBOSA</div>
  <nav>
    <a href="<?= base_url('signup.php') ?>" class="btn-solid">Sign Up</a>
  </nav>
</header>

<div class="auth-wrapper">
  <div class="auth-card">
    <h2>Welcome back</h2>
    <p class="subtitle">Log in to access your BAMBOSA dashboard.</p>

    <?php if ($just_registered && empty($errors)): ?>
      <div class="alert alert-success">
        Account created! An officer will review your registration before you can log in.
      </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $err): ?>
          <div><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= base_url('login.php') ?>" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" required
               value="<?= htmlspecialchars($old_email) ?>">
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>

      <button type="submit" class="btn-full">Log In</button>
    </form>

    <div class="auth-footer-link">
      Don't have an account? <a href="<?= base_url('signup.php') ?>">Sign up</a>
    </div>
  </div>
</div>

</body>
</html>
