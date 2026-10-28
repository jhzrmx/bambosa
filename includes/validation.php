<?php
/**
 * Shared account rules, defined once so every page and data function agrees:
 * the allowed roles and statuses, the minimum password length, the email
 * length limit, and the helpers that apply them. No dependencies.
 * PHP 7.4-compatible.
 */

const USER_ROLES    = ['admin', 'officer', 'member'];
const USER_STATUSES = ['active', 'inactive', 'pending'];

const PASSWORD_MIN_LENGTH = 8;
const EMAIL_MAX_LENGTH    = 150;   // users.email is VARCHAR(150)

/** True for a well-formed email address that fits in users.email. */
function is_valid_email(string $email): bool
{
    return strlen($email) <= EMAIL_MAX_LENGTH
        && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Error message if $password is shorter than PASSWORD_MIN_LENGTH, else null.
 * $label starts the sentence, e.g. 'Password' or 'New password'.
 */
function password_length_error(string $password, string $label = 'Password'): ?string
{
    return strlen($password) < PASSWORD_MIN_LENGTH
        ? $label . ' must be at least ' . PASSWORD_MIN_LENGTH . ' characters.'
        : null;
}

/** Hashes a password for storage in users.password_hash. */
function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}
