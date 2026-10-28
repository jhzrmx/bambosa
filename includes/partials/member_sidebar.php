<?php
/**
 * Member sidebar partial.
 * Expects $activeNav to be set by the including page to one of:
 * 'dashboard', 'profile', 'security', 'billing', 'schedule', 'machinery', 'machinery_history', 'map'
 * Renders: mobile topbar + overlay + the sidebar drawer itself (markup lives
 * in includes/sidebar.php).
 * The including page must open <div class="dash-shell"> before including
 * this, and close it (plus <main class="dash-main">) after.
 */
require_once __DIR__ . '/../sidebar.php';
$activeNav = $activeNav ?? '';

render_sidebar('Member Portal', [
    ['key' => 'dashboard', 'url' => 'member/dashboard.php', 'icon' => 'mdi-view-dashboard-outline', 'label' => 'Dashboard'],
    ['key' => 'profile', 'url' => 'member/profile.php', 'icon' => 'mdi-account-outline', 'label' => 'My Profile &amp; Farm Lot'],
    ['key' => 'security', 'url' => 'member/security.php', 'icon' => 'mdi-account-lock-outline', 'label' => 'Account Security'],
    ['key' => 'billing', 'url' => 'member/billing.php', 'icon' => 'mdi-receipt', 'label' => 'My Billing / ISF'],
    ['key' => 'schedule', 'url' => 'member/schedule.php', 'icon' => 'mdi-rotate-right', 'label' => 'Rotation Status'],
    ['key' => 'machinery', 'url' => 'member/machinery.php', 'icon' => 'mdi-tractor', 'label' => 'Reserve Machinery'],
    ['key' => 'machinery_history', 'url' => 'member/machinery_history.php', 'icon' => 'mdi-clipboard-list-outline', 'label' => 'Reservation History'],
    ['key' => 'map', 'url' => 'member/map.php', 'icon' => 'mdi-map', 'label' => 'My Farm Map'],
], $activeNav);
