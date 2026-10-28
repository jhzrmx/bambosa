<?php
/**
 * Officer sidebar partial.
 * Expects $activeNav to be set by the including page to one of:
 * 'dashboard', 'profile', 'security', 'members', 'sectors', 'boundaries', 'billing', 'schedules', 'machinery', 'reservations', 'map'
 * Renders: mobile topbar + overlay + the sidebar drawer itself (markup lives
 * in includes/sidebar.php).
 * The including page must open <div class="dash-shell"> before including
 * this, and close it (plus <main class="dash-main">) after.
 *
 * Pending-reservation badge: computed here (not passed in by the
 * including page) so every officer page shows an up-to-date count without
 * each page needing to remember to calculate and pass it along.
 * includes/machinery.php is require_once'd here for the same reason —
 * not every page that includes this sidebar already loads it.
 */
require_once __DIR__ . '/../sidebar.php';
require_once __DIR__ . '/../machinery.php';
$activeNav = $activeNav ?? '';
$pendingReservationCount = isset($pdo) ? count_reservations($pdo, 'pending') : 0;

render_sidebar('Officer Portal', [
    ['key' => 'dashboard', 'url' => 'officer/dashboard.php', 'icon' => 'mdi-view-dashboard-outline', 'label' => 'Dashboard'],
    ['key' => 'profile', 'url' => 'officer/profile.php', 'icon' => 'mdi-account-outline', 'label' => 'My Profile'],
    ['key' => 'security', 'url' => 'officer/security.php', 'icon' => 'mdi-account-lock-outline', 'label' => 'Account Security'],
    ['key' => 'members', 'url' => 'officer/members.php', 'icon' => 'mdi-account-multiple-outline', 'label' => 'Member Management'],
    ['key' => 'sectors', 'url' => 'officer/sectors.php', 'icon' => 'mdi-sprinkler-variant', 'label' => 'Sector Management'],
    ['key' => 'boundaries', 'url' => 'officer/sector_boundaries.php', 'icon' => 'mdi-group', 'label' => 'Sector Boundaries'],
    ['key' => 'billing', 'url' => 'officer/billing_periods.php', 'icon' => 'mdi-receipt', 'label' => 'Billing &amp; ISF'],
    ['key' => 'schedules', 'url' => 'officer/schedules.php', 'icon' => 'mdi-rotate-right', 'label' => 'Rotation Scheduling'],
    ['key' => 'machinery', 'url' => 'officer/machinery.php', 'icon' => 'mdi-tractor', 'label' => 'Machinery Inventory'],
    ['key' => 'reservations', 'url' => 'officer/reservations.php', 'icon' => 'mdi-clipboard-list-outline', 'label' => 'Reservation Requests', 'badge' => $pendingReservationCount],
    ['key' => 'map', 'url' => 'officer/map.php', 'icon' => 'mdi-map', 'label' => 'Member Map'],
], $activeNav);
