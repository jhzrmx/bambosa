<?php
/**
 * Admin sidebar partial.
 * Expects $activeNav to be set by the including page to one of:
 * 'dashboard', 'profile', 'security', 'users', 'login_logs', 'system_clock'
 * Renders: mobile topbar + overlay + the sidebar drawer itself (markup lives
 * in includes/sidebar.php).
 * The including page must open <div class="dash-shell"> before including
 * this, and close it (plus <main class="dash-main">) after.
 */
require_once __DIR__ . '/../sidebar.php';
$activeNav = $activeNav ?? '';

render_sidebar('Admin Portal', [
    ['key' => 'dashboard', 'url' => 'admin/dashboard.php', 'icon' => 'mdi-view-dashboard-outline', 'label' => 'Dashboard'],
    ['key' => 'profile', 'url' => 'admin/profile.php', 'icon' => 'mdi-account-outline', 'label' => 'My Profile'],
    ['key' => 'security', 'url' => 'admin/security.php', 'icon' => 'mdi-account-lock-outline', 'label' => 'Account Security'],
    ['key' => 'users', 'url' => 'admin/users.php', 'icon' => 'mdi-account-multiple-outline', 'label' => 'User Management'],
    ['key' => 'login_logs', 'url' => 'admin/login_logs.php', 'icon' => 'mdi-history', 'label' => 'User Login Logs'],
    ['key' => 'system_clock', 'url' => 'admin/system_clock.php', 'icon' => 'mdi-timer-outline', 'label' => 'System Clock'],
], $activeNav);
