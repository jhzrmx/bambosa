<?php
/**
 * SMTP / outgoing email configuration.
 * BAMBOSA Irrigation Management Platform
 *
 * Powers two features (see includes/mailer.php):
 *   - Forgot password (forgot_password.php / reset_password.php)
 *   - Account activation notification (sent from officer/update_status.php
 *     the moment an officer moves a member from "pending" to "active")
 *
 * HOW TO CONFIGURE:
 * 1. Set SMTP_ENABLED to true.
 * 2. Fill in SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD,
 *    SMTP_ENCRYPTION to match your email provider (see examples below).
 * 3. Set SMTP_FROM_EMAIL / SMTP_FROM_NAME to the address/name that should
 *    appear as the sender.
 *
 * If SMTP_ENABLED is left false (the default), every email-sending
 * feature fails SAFELY and SILENTLY from the end user's point of view:
 * - Forgot password still shows the same "if that email exists, a reset
 *   link has been sent" message either way (see includes/password_reset.php
 *   for why this is a deliberate anti-enumeration measure, not a bug),
 *   but no email is actually sent, and the officer/admin can find out why
 *   via the PHP error log ("SMTP is not configured...").
 * - Account activation still works normally (the member's status still
 *   changes to 'active' and they can log in) — they just don't get an
 *   email telling them so. The status-change action itself never fails
 *   or blocks just because email couldn't be sent.
 * This "degrade gracefully, never block a core feature on email" behavior
 * is intentional: email delivery is inherently unreliable (wrong
 * credentials, provider downtime, spam filters), and an irrigation
 * association's ability to approve members or reset a forgotten password
 * should never hinge on a third-party mail server being reachable.
 *
 * COMMON PROVIDER EXAMPLES (fill in your own credentials):
 *
 *   Gmail (requires a 16-character App Password, not your normal Gmail
 *   password — Google requires 2-Step Verification to be turned on
 *   first, then generate one at https://myaccount.google.com/apppasswords):
 *     SMTP_HOST       = 'smtp.gmail.com'
 *     SMTP_PORT       = 587
 *     SMTP_ENCRYPTION = 'tls'
 *     SMTP_USERNAME   = 'youraddress@gmail.com'
 *     SMTP_PASSWORD   = 'your16charapppassword'
 *
 *   Outlook / Microsoft 365:
 *     SMTP_HOST       = 'smtp.office365.com'
 *     SMTP_PORT       = 587
 *     SMTP_ENCRYPTION = 'tls'
 *
 *   A cPanel/shared-hosting mailbox (ask your host for exact values):
 *     SMTP_HOST       = 'mail.yourdomain.com'
 *     SMTP_PORT       = 465
 *     SMTP_ENCRYPTION = 'ssl'
 *
 * SECURITY NOTES:
 * - This file lives under config/, which has its own .htaccess with
 *   "Require all denied" — the same protection already in place for
 *   config/database.php — so it can never be fetched directly over HTTP
 *   regardless of what's inside it. Still treat these credentials with
 *   the same care as a database password: don't commit real credentials
 *   to a public repository, and don't paste them into chat/support
 *   tickets. Consider environment variables instead of hardcoding here
 *   if this project is ever deployed via a platform that supports them.
 * - Use an App Password / dedicated SMTP credential scoped to sending
 *   mail only, never a personal account's main password, wherever the
 *   provider supports it.
 */

// Master switch. Leave false until the settings below are filled in and
// tested — see the graceful-degradation notes above for what happens
// while this is false.
const SMTP_ENABLED = false;

const SMTP_HOST = 'smtp.example.com';
const SMTP_PORT = 587;

// 'tls' (STARTTLS, most common — port 587) or 'ssl' (implicit TLS — port
// 465). Must match what your provider expects for the port above.
const SMTP_ENCRYPTION = 'tls';

const SMTP_USERNAME = '';
const SMTP_PASSWORD = '';

// What recipients see as the sender. Many providers require
// SMTP_FROM_EMAIL to match (or at least belong to the same domain as)
// SMTP_USERNAME, or they'll silently rewrite/reject it.
const SMTP_FROM_EMAIL = 'no-reply@example.com';
const SMTP_FROM_NAME  = 'BAMBOSA Irrigation Association';

// Connection timeout in seconds for the SMTP handshake — keeps a
// misconfigured or unreachable host from hanging a page load for minutes.
const SMTP_TIMEOUT = 10;
