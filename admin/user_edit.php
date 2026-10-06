<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/members.php';
require_once __DIR__ . '/../includes/admin_users.php';

require_role('admin');

$selfId  = (int) $_SESSION['user_id'];
$editId  = (int) ($_GET['id'] ?? 0);
$editing = null;

if ($editId > 0) {
    $editing = get_user_by_id($pdo, $editId);
    if (!$editing) {
        $_SESSION['flash_error'] = 'That account no longer exists.';
        header('Location: ' . base_url('admin/users.php'));
        exit;
    }
    // Admins edit their own details (and password) via My Profile, where
    // changing the password requires the current one.
    if ((int) $editing['user_id'] === $selfId) {
        header('Location: ' . base_url('admin/profile.php'));
        exit;
    }
}

$errors = [];

// Form values (defaults for a new account / current values when editing)
$form = [
    'full_name'      => $editing['full_name'] ?? '',
    'email'          => $editing['email'] ?? '',
    'contact_number' => $editing['contact_number'] ?? '',
    'role'           => $editing['role'] ?? 'member',
    'status'         => $editing['status'] ?? 'active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $form['full_name']      = trim($_POST['full_name'] ?? '');
    $form['email']          = trim($_POST['email'] ?? '');
    $form['contact_number'] = trim($_POST['contact_number'] ?? '');
    $form['role']           = $_POST['role'] ?? '';
    $form['status']         = $_POST['status'] ?? '';
    $password               = $_POST['password'] ?? '';
    $confirmPassword        = $_POST['confirm_password'] ?? '';

    if ($form['full_name'] === '') {
        $errors[] = 'Full name cannot be empty.';
    } elseif (strlen($form['full_name']) > 150) {
        $errors[] = 'Full name must be 150 characters or fewer.';
    }

    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || strlen($form['email']) > 150) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (email_in_use($pdo, $form['email'], $editing ? (int) $editing['user_id'] : 0)) {
        $errors[] = 'That email address is already used by another account.';
    }

    if (strlen($form['contact_number']) > 20) {
        $errors[] = 'Contact number must be 20 characters or fewer.';
    }
    if (!in_array($form['role'], USER_ROLES, true)) {
        $errors[] = 'Please choose a valid role.';
    }
    if (!in_array($form['status'], USER_STATUSES, true)) {
        $errors[] = 'Please choose a valid status.';
    }

    // Password: required for a new account, optional (blank = keep) when editing.
    if (!$editing || $password !== '' || $confirmPassword !== '') {
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirmPassword) {
            $errors[] = 'Password and confirmation do not match.';
        }
    }

    if (empty($errors) && $editing) {
        $guard = user_change_guard_error($pdo, $editing, $selfId, $form['role'], $form['status'], false);
        if ($guard !== null) {
            $errors[] = $guard;
        }
    }

    if (empty($errors)) {
        try {
            if ($editing) {
                admin_update_user(
                    $pdo, (int) $editing['user_id'], $form['full_name'], $form['email'],
                    $form['contact_number'], $form['role'], $form['status'], $password !== '' ? $password : null
                );
                header('Location: ' . base_url('admin/users.php?flash=updated'));
            } else {
                create_user(
                    $pdo, $form['full_name'], $form['email'], $password,
                    $form['role'], $form['contact_number'], $form['status']
                );
                header('Location: ' . base_url('admin/users.php?flash=created'));
            }
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'That email address is already used by another account.';
            } else {
                error_log('Admin save user error: ' . $e->getMessage());
                $errors[] = 'Something went wrong while saving. Please try again.';
            }
        }
    }
}

$activeNav = 'users';
$pageTitle = $editing ? 'Edit Account' : 'Add New Account';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="<?= base_url('assets/img/favicon.png') ?>">
<link rel="shortcut icon" href="<?= base_url('assets/img/favicon.ico') ?>">
<title><?= $pageTitle ?> · BAMBOSA Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="dash-shell">
  <?php include __DIR__ . '/../includes/partials/admin_sidebar.php'; ?>

  <main class="dash-main">
    <h1><?= $pageTitle ?></h1>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $err): ?><div><?= htmlspecialchars($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= base_url('admin/user_edit.php' . ($editing ? '?id=' . (int) $editing['user_id'] : '')) ?>" class="inline-card-form" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

      <div class="form-row">
        <div class="form-group">
          <label for="full_name">Full Name</label>
          <input type="text" id="full_name" name="full_name" required maxlength="150"
                 value="<?= htmlspecialchars($form['full_name']) ?>">
        </div>
        <div class="form-group">
          <label for="contact_number">Contact Number</label>
          <input type="text" id="contact_number" name="contact_number" maxlength="20"
                 value="<?= htmlspecialchars($form['contact_number']) ?>">
        </div>
      </div>

      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" required maxlength="150"
               value="<?= htmlspecialchars($form['email']) ?>">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="role">Role</label>
          <select id="role" name="role" required>
            <?php foreach (USER_ROLES as $r): ?>
              <option value="<?= $r ?>" <?= $form['role'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="status">Status</label>
          <select id="status" name="status" required>
            <?php foreach (USER_STATUSES as $st): ?>
              <option value="<?= $st ?>" <?= $form['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <p class="muted-note" style="margin-top:0;">
        Admins manage all accounts; officers manage association records; members are farmers.
        Only <strong>active</strong> accounts can log in. Role changes take effect immediately.
      </p>

      <div class="form-row">
        <div class="form-group">
          <label for="password"><?= $editing ? 'New Password (leave blank to keep current)' : 'Password' ?></label>
          <input type="password" id="password" name="password" minlength="8" autocomplete="new-password" <?= $editing ? '' : 'required' ?>>
        </div>
        <div class="form-group">
          <label for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" minlength="8" autocomplete="new-password" <?= $editing ? '' : 'required' ?>>
        </div>
      </div>
      <p class="muted-note" style="margin-top:0;">Must be at least 8 characters.</p>

      <div class="btn-row">
        <button type="submit" class="btn-full"><?= $editing ? 'Save Changes' : 'Create Account' ?></button>
        <a href="<?= base_url('admin/users.php') ?>"><button type="button" class="btn-full" style="background:#fff; color:var(--clr-primary); border:1px solid var(--clr-primary);">Cancel</button></a>
      </div>
    </form>
  </main>
</div>

<script src="<?= base_url('assets/js/dashboard.js') ?>"></script>
</body>
</html>
