<?php
/**
 * Application timezone — keeps PHP and MySQL on the SAME clock.
 * BAMBOSA Irrigation Management Platform
 *
 * WHY THIS EXISTS:
 * PHP and MySQL each have their own timezone, and out of the box they are
 * often different (PHP defaults to UTC unless php.ini says otherwise; MySQL
 * follows the server's operating-system zone). Whenever the two disagree,
 * anything that mixes them goes subtly wrong — for example a password-reset
 * link is stamped with PHP's clock but checked against MySQL's NOW(), so it
 * can expire hours early (or late); schedules compared with NOW() look
 * "already over"; login times display shifted.
 *
 * DEFAULT: 'Asia/Manila' (Philippine Standard Time, UTC+08:00, no daylight
 * saving), because BAMBOSA serves Barangay Bikal. Change APP_TIMEZONE below
 * only if the system is deployed somewhere else. Use any PHP timezone
 * identifier, e.g.
 *     'Asia/Manila'
 *     'Asia/Singapore'
 *     'UTC'
 * The full list is at https://www.php.net/manual/en/timezones.php
 * Identifiers are case-sensitive ('Asia/Manila', not 'asia/manila').
 *
 * WHAT IT DOES (see includes/timezone.php):
 *   - PHP:   date_default_timezone_set(APP_TIMEZONE) at the start of every
 *            request (includes/session.php).
 *   - MySQL: SET time_zone for each database connection
 *            (config/database.php). If your MySQL/MariaDB has no timezone
 *            tables loaded (common on a fresh XAMPP install, where named
 *            zones aren't recognised), the equivalent UTC offset such as
 *            '+08:00' is used automatically instead.
 *
 * IF IT IS EMPTY OR INVALID, NOTHING CHANGES:
 * Setting APP_TIMEZONE to an empty string ('') switches the feature off:
 * neither PHP nor MySQL is touched, and both keep whatever timezone the
 * server already gave them. A value that isn't a valid timezone identifier
 * is treated the same way (ignored, and logged in the PHP error log), so a
 * typo can never half-apply or break the site.
 *
 * You can check the result at any time on the admin "System Clock" page
 * (admin sidebar), which shows PHP's and MySQL's current time side by
 * side and warns if they disagree.
 *
 * ABOUT EXISTING DATA (relevant when upgrading an install that has been
 * running with a different timezone):
 *   - DATETIME columns (e.g. irrigation schedule start/end times) store the
 *     clock time exactly as entered — nothing is converted. They are now
 *     compared against NOW() in the configured zone, so enter and read
 *     them as local time.
 *   - TIMESTAMP columns (created_at, login_time, ...) are stored as UTC
 *     internally and DISPLAYED in the connection's zone. Setting a
 *     timezone for the first time (or changing it later) therefore changes
 *     how already-stored TIMESTAMP values are shown, by the difference
 *     between the old and new zone. Pick your zone once, early.
 *
 * SECURITY NOTE: like config/database.php and config/smtp.php, this file
 * is protected by config/.htaccess ("Require all denied").
 */

// Philippine Standard Time. Set to '' to leave PHP and MySQL timezones untouched.
const APP_TIMEZONE = 'Asia/Manila';
