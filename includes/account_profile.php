<?php
/**
 * "My Profile" form handling shared by admin/, officer/ and member/
 * profile.php: name, contact number and email (the password lives on the
 * Account Security page, see includes/account_security.php).
 *
 * Requires $pdo, includes/auth.php (csrf_*) and includes/members.php.
 * PHP 7.4-compatible.
 */

/**
 * Handles the profile-update POST, if any.
 * Returns [array $errors, bool $saved]; both empty/false on a plain GET.
 */
function handle_profile_update(PDO $pdo, int $userId): array
{
    $errors = [];
    $saved  = false;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return [$errors, $saved];
    }

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

    if (!is_valid_email($email)) {
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

    return [$errors, $saved];
}
