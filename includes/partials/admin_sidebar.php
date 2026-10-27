<?php
/**
 * Admin sidebar partial.
 * Expects $activeNav to be set by the including page to one of:
 * 'dashboard', 'profile', 'security', 'users', 'login_logs', 'system_clock'
 * Renders: mobile topbar + overlay + the sidebar drawer itself.
 * The including page must open <div class="dash-shell"> before including
 * this, and close it (plus <main class="dash-main">) after.
 */
$activeNav = $activeNav ?? '';
?>
<div class="dash-topbar">
  <div class="brand">🌾 BAMBOSA</div>
  <button type="button" class="menu-toggle" data-drawer-toggle aria-label="Open menu">☰</button>
</div>

<div class="dash-overlay" id="dashOverlay"></div>

<aside class="dash-sidebar" id="dashSidebar">
  <div class="drawer-close">
    <span>🌾 BAMBOSA</span>
    <button type="button" data-drawer-close aria-label="Close menu">✕</button>
  </div>

  <h2>🌾 BAMBOSA</h2>
  <p class="role-tag">Admin Portal</p>

  <a href="<?= base_url('admin/dashboard.php') ?>" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-view-dashboard-outline" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M19,5V7H15V5H19M9,5V11H5V5H9M19,13V19H15V13H19M9,17V19H5V17H9M21,3H13V9H21V3M11,3H3V13H11V3M21,11H13V21H21V11M11,15H3V21H11V15Z" />
	</svg>
	<span>Dashboard</span>
  </a>
  <a href="<?= base_url('admin/profile.php') ?>" class="<?= $activeNav === 'profile' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-account-outline" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M12,4A4,4 0 0,1 16,8A4,4 0 0,1 12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4M12,6A2,2 0 0,0 10,8A2,2 0 0,0 12,10A2,2 0 0,0 14,8A2,2 0 0,0 12,6M12,13C14.67,13 20,14.33 20,17V20H4V17C4,14.33 9.33,13 12,13M12,14.9C9.03,14.9 5.9,16.36 5.9,17V18.1H18.1V17C18.1,16.36 14.97,14.9 12,14.9Z" />
	</svg>
	<span>My Profile</span>
  </a>
  <a href="<?= base_url('admin/security.php') ?>" class="<?= $activeNav === 'security' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-account-lock-outline" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M22 17V16.5C22 15.12 20.88 14 19.5 14S17 15.12 17 16.5V17C16.45 17 16 17.45 16 18V22C16 22.55 16.45 23 17 23H22C22.55 23 23 22.55 23 22V18C23 17.45 22.55 17 22 17M21 17H18V16.5C18 15.67 18.67 15 19.5 15S21 15.67 21 16.5V17M14 20H4V17C4 14.33 9.33 13 12 13C13.08 13 14.6 13.22 16 13.66C15.61 14.15 15.33 14.74 15.16 15.38C14.19 15.1 13.09 14.9 12 14.9C9.03 14.9 5.9 16.36 5.9 17V18.1H14V20M12 12C14.21 12 16 10.21 16 8S14.21 4 12 4 8 5.79 8 8 9.79 12 12 12M12 6C13.11 6 14 6.9 14 8S13.11 10 12 10 10 9.11 10 8 10.9 6 12 6Z" />
	</svg>
	<span>Account Security</span>
  </a>
  <a href="<?= base_url('admin/users.php') ?>" class="<?= $activeNav === 'users' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-account-multiple-outline" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M13.07 10.41A5 5 0 0 0 13.07 4.59A3.39 3.39 0 0 1 15 4A3.5 3.5 0 0 1 15 11A3.39 3.39 0 0 1 13.07 10.41M5.5 7.5A3.5 3.5 0 1 1 9 11A3.5 3.5 0 0 1 5.5 7.5M7.5 7.5A1.5 1.5 0 1 0 9 6A1.5 1.5 0 0 0 7.5 7.5M16 17V19H2V17S2 13 9 13 16 17 16 17M14 17C13.86 16.22 12.67 15 9 15S4.07 16.31 4 17M15.95 13A5.32 5.32 0 0 1 18 17V19H22V17S22 13.37 15.94 13Z" />
	</svg>
	<span>User Management</span>
  </a>
  <a href="<?= base_url('admin/login_logs.php') ?>" class="<?= $activeNav === 'login_logs' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-history" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M13.5,8H12V13L16.28,15.54L17,14.33L13.5,12.25V8M13,3A9,9 0 0,0 4,12H1L4.96,16.03L9,12H6A7,7 0 0,1 13,5A7,7 0 0,1 20,12A7,7 0 0,1 13,19C11.07,19 9.32,18.21 8.06,16.94L6.64,18.36C8.27,20 10.5,21 13,21A9,9 0 0,0 22,12A9,9 0 0,0 13,3" />
	</svg>
	<span>User Login Logs</span>
  </a>
  <a href="<?= base_url('admin/system_clock.php') ?>" class="<?= $activeNav === 'system_clock' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-timer-outline" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M12,20A7,7 0 0,1 5,13A7,7 0 0,1 12,6A7,7 0 0,1 19,13A7,7 0 0,1 12,20M19.03,7.39L20.45,5.97C20,5.46 19.55,5 19.04,4.56L17.62,6C16.07,4.74 14.12,4 12,4A9,9 0 0,0 3,13A9,9 0 0,0 12,22C17,22 21,17.97 21,13C21,10.88 20.26,8.93 19.03,7.39M11,14H13V8H11M15,1H9V3H15V1Z" />
	</svg>
	<span>System Clock</span>
  </a>

  <form method="POST" action="<?= base_url('logout.php') ?>">
    <button type="submit" class="logout-btn">Log Out</button>
  </form>
</aside>
