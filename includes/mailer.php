<?php
/**
 * Outgoing email — thin wrapper around PHPMailer.
 * Requires config/smtp.php to already be loaded (for the SMTP_* constants).
 *
 * WHY A WRAPPER INSTEAD OF CALLING PHPMAILER DIRECTLY EVERYWHERE:
 * - Every caller (forgot-password, activation notification, and anything
 *   added later) gets the same "not configured / send failed" handling
 *   for free, instead of every feature needing its own try/catch around
 *   PHPMailer and its own opinion about what to do when it fails.
 * - Callers never need to know PHPMailer exists — they just call
 *   send_email() with a recipient, subject, and body. This also means
 *   PHPMailer could be swapped for something else later without touching
 *   forgot_password.php, officer/update_status.php, etc.
 *
 * VENDORING PHPMAILER:
 * This project uses PHPMailer 6.x (core only — PHPMailer.php, SMTP.php,
 * Exception.php; no OAuth/POP3, since plain SMTP+password auth is all
 * BAMBOSA needs), vendored directly into this codebase at:
 *     vendor/phpmailer/phpmailer/src/Exception.php
 *     vendor/phpmailer/phpmailer/src/PHPMailer.php
 *     vendor/phpmailer/phpmailer/src/SMTP.php
 * (see vendor/phpmailer/phpmailer/src/VERSION.txt for exactly which
 * release and how to upgrade it later). This keeps the project
 * dependency-manager-free (consistent with the rest of BAMBOSA, which is
 * deliberately vanilla PHP with no Composer requirement) while still
 * using the real, unmodified upstream library rather than a hand-rolled
 * mail function. If those three files are ever missing (e.g. a fresh
 * checkout of just the application code without the vendor folder),
 * every function below fails gracefully (see is_mailer_available()).
 */

require_once __DIR__ . '/../config/smtp.php';

const PHPMAILER_EXCEPTION_PATH = __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
const PHPMAILER_CLASS_PATH     = __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
const PHPMAILER_SMTP_PATH      = __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

/**
 * True if PHPMailer's core files are present on disk AND SMTP is turned
 * on in config/smtp.php. Both conditions matter: someone may have set
 * SMTP_ENABLED = true but forgotten to vendor the library files (or vice
 * versa), and either way sending should not be attempted.
 */
function is_mailer_available(): bool
{
    return SMTP_ENABLED
        && file_exists(PHPMAILER_EXCEPTION_PATH)
        && file_exists(PHPMAILER_CLASS_PATH)
        && file_exists(PHPMAILER_SMTP_PATH);
}

/**
 * Sends a single HTML email. Returns true on success, false on ANY
 * failure — misconfiguration, missing library files, connection refused,
 * authentication rejected, etc. Never throws: every failure path is
 * caught here and logged via error_log(), because a broken mail server
 * should degrade a nice-to-have (an email notification) rather than
 * crash or block whatever the caller was doing (approving a member,
 * requesting a password reset).
 *
 * $to: recipient email address.
 * $toName: recipient display name (used in the "To" header; falls back
 * to the address itself if blank).
 * $subject: email subject line.
 * $htmlBody: HTML email body. A plain-text fallback is auto-generated
 * from it (tags stripped) for mail clients that prefer plain text.
 *
 * Deliberately does not accept attachments, CC/BCC, or multiple
 * recipients — BAMBOSA's email needs (a reset link, an activation notice)
 * are always "one templated message to one person," and keeping this
 * function's surface area small keeps it easy to reason about safely.
 */
function send_email(string $to, string $toName, string $subject, string $htmlBody): bool
{
    if (!is_mailer_available()) {
        error_log(
            'send_email(): SMTP is not configured (see config/smtp.php) or the PHPMailer '
            . 'library files are missing from vendor/phpmailer/phpmailer/src/ — email not sent. '
            . "Intended recipient: {$to}, subject: {$subject}"
        );
        return false;
    }

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("send_email(): refusing to send — '{$to}' is not a valid email address.");
        return false;
    }

    require_once PHPMAILER_EXCEPTION_PATH;
    require_once PHPMAILER_CLASS_PATH;
    require_once PHPMAILER_SMTP_PATH;

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->Timeout    = SMTP_TIMEOUT;
        $mail->CharSet    = 'UTF-8';

        // 'tls' = STARTTLS (typically port 587); 'ssl' = implicit TLS
        // (typically port 465). Anything else in config/smtp.php is
        // treated as "no encryption," which most providers will reject —
        // that's intentional, since silently falling back to an
        // unencrypted connection would leak the SMTP password.
        if (SMTP_ENCRYPTION === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to, $toName !== '' ? $toName : $to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = trim(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody)));

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        // Catches both PHPMailer\PHPMailer\Exception and any other
        // unexpected error (e.g. a network-level Error), so a mail
        // failure can never bubble up and break the calling page.
        error_log('send_email(): failed to send to ' . $to . ' — ' . $e->getMessage());
        return false;
    }
}

/**
 * Wraps a subject + inner HTML body in BAMBOSA's shared email layout
 * (matches the association branding used across the site) so every
 * email looks consistent without each template repeating the boilerplate.
 */
function render_email_layout(string $title, string $bodyHtml): string
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"><title>{$safeTitle}</title></head>
    <body style="margin:0; padding:0; background:#f4f7f5; font-family:Arial,Helvetica,sans-serif;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7f5; padding:24px 0;">
        <tr><td align="center">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background:#ffffff; border-radius:10px; overflow:hidden;">
            <tr><td style="background:#1f7a4d; padding:20px 28px;">
              <span style="color:#ffffff; font-size:18px; font-weight:bold;">🌾 BAMBOSA</span>
            </td></tr>
            <tr><td style="padding:28px;">
              {$bodyHtml}
            </td></tr>
            <tr><td style="padding:16px 28px; background:#fafcfa; color:#7a8c81; font-size:12px;">
              This is an automated message from BAMBOSA Irrigation Association. Please don't reply to this email.
            </td></tr>
          </table>
        </td></tr>
      </table>
    </body>
    </html>
    HTML;
}
