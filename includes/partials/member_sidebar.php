<?php
/**
 * Member sidebar partial.
 * Expects $activeNav to be set by the including page to one of:
 * 'dashboard', 'profile', 'billing', 'schedule', 'machinery', 'map'
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
  <p class="role-tag">Member Portal</p>

  <a href="<?= base_url('member/dashboard.php') ?>" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-view-dashboard-outline" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M19,5V7H15V5H19M9,5V11H5V5H9M19,13V19H15V13H19M9,17V19H5V17H9M21,3H13V9H21V3M11,3H3V13H11V3M21,11H13V21H21V11M11,15H3V21H11V15Z" />
	</svg>
	<span>Dashboard</span
  </a>
  <a href="<?= base_url('member/profile.php') ?>" class="<?= $activeNav === 'profile' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-account-outline" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M12,4A4,4 0 0,1 16,8A4,4 0 0,1 12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4M12,6A2,2 0 0,0 10,8A2,2 0 0,0 12,10A2,2 0 0,0 14,8A2,2 0 0,0 12,6M12,13C14.67,13 20,14.33 20,17V20H4V17C4,14.33 9.33,13 12,13M12,14.9C9.03,14.9 5.9,16.36 5.9,17V18.1H18.1V17C18.1,16.36 14.97,14.9 12,14.9Z" />
	</svg>
	<span>My Profile &amp; Farm Lot</span>
  </a>
  <a href="<?= base_url('member/billing.php') ?>" class="<?= $activeNav === 'billing' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-receipt" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M3,22L4.5,20.5L6,22L7.5,20.5L9,22L10.5,20.5L12,22L13.5,20.5L15,22L16.5,20.5L18,22L19.5,20.5L21,22V2L19.5,3.5L18,2L16.5,3.5L15,2L13.5,3.5L12,2L10.5,3.5L9,2L7.5,3.5L6,2L4.5,3.5L3,2M18,9H6V7H18M18,13H6V11H18M18,17H6V15H18V17Z" />
	</svg>
	<span>My Billing / ISF</span>
  </a>
  <a href="<?= base_url('member/schedule.php') ?>" class="<?= $activeNav === 'schedule' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-rotate-right" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M16.89,15.5L18.31,16.89C19.21,15.73 19.76,14.39 19.93,13H17.91C17.77,13.87 17.43,14.72 16.89,15.5M13,17.9V19.92C14.39,19.75 15.74,19.21 16.9,18.31L15.46,16.87C14.71,17.41 13.87,17.76 13,17.9M19.93,11C19.76,9.61 19.21,8.27 18.31,7.11L16.89,8.53C17.43,9.28 17.77,10.13 17.91,11M15.55,5.55L11,1V4.07C7.06,4.56 4,7.92 4,12C4,16.08 7.05,19.44 11,19.93V17.91C8.16,17.43 6,14.97 6,12C6,9.03 8.16,6.57 11,6.09V10L15.55,5.55Z" />
	</svg>
	<span>Rotation Status</span>
  </a>
  <a href="<?= base_url('member/machinery.php') ?>" class="<?= $activeNav === 'machinery' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-tractor" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M5,4V11.26C3.2,11.9 2,13.6 2,15.5C2,18 4,20 6.5,20C8.79,20 10.71,18.28 10.97,16H15.17C15.06,16.32 15,16.66 15,17A3,3 0 0,0 18,20A3,3 0 0,0 21,17C21,16.66 20.94,16.32 20.82,16H22V13C22,11.89 21.11,11 20,11H15.04L13.65,4H5M7,6H12L13,11V14H10.74C10.16,12.38 8.71,11.23 7,11.03V6M6.5,13.25A2.25,2.25 0 0,1 8.75,15.5A2.25,2.25 0 0,1 6.5,17.75A2.25,2.25 0 0,1 4.25,15.5A2.25,2.25 0 0,1 6.5,13.25M18,15.5A1.5,1.5 0 0,1 19.5,17A1.5,1.5 0 0,1 18,18.5A1.5,1.5 0 0,1 16.5,17A1.5,1.5 0 0,1 18,15.5Z" />
	</svg>
	<span>Reserve Machinery</span>
  </a>
  <a href="<?= base_url('member/map.php') ?>" class="<?= $activeNav === 'map' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-map" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M15,19L9,16.89V5L15,7.11M20.5,3C20.44,3 20.39,3 20.34,3L15,5.1L9,3L3.36,4.9C3.15,4.97 3,5.15 3,5.38V20.5A0.5,0.5 0 0,0 3.5,21C3.55,21 3.61,21 3.66,20.97L9,18.9L15,21L20.64,19.1C20.85,19 21,18.85 21,18.62V3.5A0.5,0.5 0 0,0 20.5,3Z" />
	</svg>
	<span>My Farm Map</span>
  </a>

  <form method="POST" action="<?= base_url('logout.php') ?>">
    <button type="submit" class="logout-btn">Log Out</button>
  </form>
</aside>
