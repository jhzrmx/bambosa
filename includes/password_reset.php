<?php
/**
 * Forgot-password token handling.
 * Requires $pdo from config/database.php, and includes/mailer.php, to
 * already be loaded.
 *
 * SECURITY MODEL:
 * - The raw token is a cryptographically random 32-byte value
 *   (random_bytes, never a guessable sequence), sent to the user only
 *   inside the emailed link.
 * - Only the token's SHA-256 hash is ever stored in the database — same
 *   principle as password_hash() for passwords, so a database
 *   compromise alone doesn't hand over usable reset links. Lookups hash
 *   the incoming token and compare hashes, and hash_equals() is used for
 *   the final comparison to avoid timing attacks.
 * - Tokens expire after PASSWORD_RESET_TTL_MINUTES and are single-use
 *   (marked used_at once consumed) — a leaked/intercepted link has a
 *   limited window and can't be replayed.
 * - Requesting a reset NEVER reveals whether the email exists in the
 *   system. The calling page (forgot_password.php) always shows the same
 *   "if that email is registered, a reset link has been sent" message
 *   regardless of whether the account exists, is pending, or is
 *   deactivated — this prevents using the forgot-password form to
 *   enumerate valid member emails. See request_password_reset()'s
 *   comment for exactly what does/doesn't get emailed and why.
 */

const PASSWORD_RESET_TTL_MINUTES = 30;

/**
 * Handles a forgot-password request for the given email address.
 *
 * Always returns void and never reveals to the CALLER (let alone the end
 * user) whether the email matched an account — that check happens
 * entirely inside this function, and the email is only actually sent
 * when there's a real, active-or-pending account to send it to. A
 * suspended ('inactive') account still gets silently skipped (no email,
 * no error) since a forgotten password isn't the fix for a deactivated
 * account — that requires an officer's attention regardless.
 *
 * This function itself never throws for "email not found" — that's
 * normal, expected input, not an error condition. It also doesn't return
 * a boolean, specifically to avoid tempting a caller into branching UI
 * behavior on the result (which would reintroduce the enumeration risk
 * this whole design is trying to avoid).
 */
function request_password_reset(PDO $pdo, string $email): void
{
    $stmt = $pdo->prepare('SELECT user_id, full_name, email, status FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] === 'inactive') {
        return; // silently no-op — see function comment above
    }

    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + PASSWORD_RESET_TTL_MINUTES * 60);

    $insert = $pdo->prepare(
        'INSERT INTO password_resets (user_id, token_hash, expires_at, requested_ip)
         VALUES (:user_id, :token_hash, :expires_at, :ip)'
    );
    $insert->execute([
        'user_id'    => $user['user_id'],
        'token_hash' => $tokenHash,
        'expires_at' => $expiresAt,
        'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    // Full URL (not just a path): an email client can't resolve a relative link.
    $resetUrl = absolute_url('reset_password.php') . '?token=' . urlencode($rawToken);

    $bodyHtml = '
        <p style="margin:0 0 16px; color:#1b2b23; font-size:15px;">Hi ' . htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') . ',</p>
        <p style="margin:0 0 16px; color:#1b2b23; font-size:15px;">We received a request to reset your BAMBOSA account password. Click the button below to choose a new one:</p>
        <p style="margin:0 0 24px; text-align:center;">
          <a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" style="background:#1f7a4d; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:bold; display:inline-block;">Reset Password</a>
        </p>
        <p style="margin:0 0 8px; color:#7a8c81; font-size:13px;">This link expires in ' . PASSWORD_RESET_TTL_MINUTES . ' minutes and can only be used once.</p>
        <p style="margin:0; color:#7a8c81; font-size:13px;">If you didn\'t request this, you can safely ignore this email — your password will not be changed.</p>
    ';

    send_email(
        $user['email'],
        $user['full_name'],
        'Reset your BAMBOSA password',
        render_email_layout('Reset your password', $bodyHtml)
    );
}

/**
 * Looks up a raw token (as submitted from the reset-password form/link)
 * and returns the associated user_id if it's valid — meaning it exists,
 * hasn't expired, and hasn't already been used. Returns null for any
 * invalid case, deliberately without distinguishing WHY it's invalid
 * (expired vs. already used vs. never existed) in the return value, so
 * the reset-password page can show one generic "this link is invalid or
 * has expired" message rather than leaking which case applies.
 */
function verify_password_reset_token(PDO $pdo, string $rawToken): ?int
{
    if ($rawToken === '') {
        return null;
    }

    $tokenHash = hash('sha256', $rawToken);

    $stmt = $pdo->prepare(
        'SELECT reset_id, user_id, token_hash, expires_at, used_at
         FROM password_resets WHERE token_hash = :hash LIMIT 1'
    );
    $stmt->execute(['hash' => $tokenHash]);
    $row = $stmt->fetch();

    if (!$row || !hash_equals($row['token_hash'], $tokenHash)) {
        return null;
    }
    if ($row['used_at'] !== null) {
        return null;
    }
    if (strtotime($row['expires_at']) < time()) {
        return null;
    }

    return (int) $row['user_id'];
}

/**
 * Consumes a verified token (marks it used) and sets the user's new
 * password in one call, so a token can never be used to set two
 * different passwords via a race condition or a reused browser tab.
 *
 * Caller MUST have already validated $rawToken via
 * verify_password_reset_token() and re-checks it here regardless, since
 * time may have passed between the two calls (e.g. a slow form submit
 * right as the token expires).
 *
 * Returns true if the password was changed, false if the token turned
 * out to be invalid/expired/already used at the moment of consumption.
 */
function consume_password_reset(PDO $pdo, string $rawToken, string $newPassword): bool
{
    $userId = verify_password_reset_token($pdo, $rawToken);
    if ($userId === null) {
        return false;
    }

    $tokenHash = hash('sha256', $rawToken);

    $pdo->beginTransaction();
    try {
        // Mark used FIRST and only inside this transaction, so a
        // concurrent second request with the same token can't also pass
        // verify_password_reset_token() before this one commits.
        $markUsed = $pdo->prepare(
            "UPDATE password_resets SET used_at = NOW()
             WHERE token_hash = :hash AND used_at IS NULL AND expires_at >= NOW()"
        );
        $markUsed->execute(['hash' => $tokenHash]);

        if ($markUsed->rowCount() === 0) {
            // Someone else consumed it (or it expired) between our check
            // above and this update — treat as invalid, same as any other
            // failure case.
            $pdo->rollBack();
            return false;
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateUser = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE user_id = :id');
        $updateUser->execute(['hash' => $newHash, 'id' => $userId]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('consume_password_reset(): ' . $e->getMessage());
        return false;
    }
}
