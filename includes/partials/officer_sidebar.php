<?php
/**
 * Officer sidebar partial.
 * Expects $activeNav to be set by the including page to one of:
 * 'dashboard', 'profile', 'security', 'members', 'sectors', 'boundaries', 'billing', 'schedules', 'machinery', 'reservations', 'map'
 * Renders: mobile topbar + overlay + the sidebar drawer itself.
 * The including page must open <div class="dash-shell"> before including
 * this, and close it (plus <main class="dash-main">) after.
 *
 * Pending-reservation badge: computed here (not passed in by the
 * including page) so every officer page shows an up-to-date count without
 * each page needing to remember to calculate and pass it along.
 * includes/machinery.php is require_once'd here for the same reason —
 * not every page that includes this sidebar already loads it.
 */
require_once __DIR__ . '/../machinery.php';
$activeNav = $activeNav ?? '';
$pendingReservationCount = isset($pdo) ? count_reservations($pdo, 'pending') : 0;
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
  <p class="role-tag">Officer Portal</p>

  <a href="<?= base_url('officer/dashboard.php') ?>" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-view-dashboard-outline" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M19,5V7H15V5H19M9,5V11H5V5H9M19,13V19H15V13H19M9,17V19H5V17H9M21,3H13V9H21V3M11,3H3V13H11V3M21,11H13V21H21V11M11,15H3V21H11V15Z" />
	</svg>
	<span>Dashboard</span>
  </a>
  <a href="<?= base_url('officer/profile.php') ?>" class="<?= $activeNav === 'profile' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-account-outline" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M12,4A4,4 0 0,1 16,8A4,4 0 0,1 12,12A4,4 0 0,1 8,8A4,4 0 0,1 12,4M12,6A2,2 0 0,0 10,8A2,2 0 0,0 12,10A2,2 0 0,0 14,8A2,2 0 0,0 12,6M12,13C14.67,13 20,14.33 20,17V20H4V17C4,14.33 9.33,13 12,13M12,14.9C9.03,14.9 5.9,16.36 5.9,17V18.1H18.1V17C18.1,16.36 14.97,14.9 12,14.9Z" />
	</svg>
	<span>My Profile</span>
  </a>
  <a href="<?= base_url('officer/security.php') ?>" class="<?= $activeNav === 'security' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-account-lock-outline" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M22 17V16.5C22 15.12 20.88 14 19.5 14S17 15.12 17 16.5V17C16.45 17 16 17.45 16 18V22C16 22.55 16.45 23 17 23H22C22.55 23 23 22.55 23 22V18C23 17.45 22.55 17 22 17M21 17H18V16.5C18 15.67 18.67 15 19.5 15S21 15.67 21 16.5V17M14 20H4V17C4 14.33 9.33 13 12 13C13.08 13 14.6 13.22 16 13.66C15.61 14.15 15.33 14.74 15.16 15.38C14.19 15.1 13.09 14.9 12 14.9C9.03 14.9 5.9 16.36 5.9 17V18.1H14V20M12 12C14.21 12 16 10.21 16 8S14.21 4 12 4 8 5.79 8 8 9.79 12 12 12M12 6C13.11 6 14 6.9 14 8S13.11 10 12 10 10 9.11 10 8 10.9 6 12 6Z" />
	</svg>
	<span>Account Security</span>
  </a>
  <a href="<?= base_url('officer/members.php') ?>" class="<?= $activeNav === 'members' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-account-multiple-outline" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M13.07 10.41A5 5 0 0 0 13.07 4.59A3.39 3.39 0 0 1 15 4A3.5 3.5 0 0 1 15 11A3.39 3.39 0 0 1 13.07 10.41M5.5 7.5A3.5 3.5 0 1 1 9 11A3.5 3.5 0 0 1 5.5 7.5M7.5 7.5A1.5 1.5 0 1 0 9 6A1.5 1.5 0 0 0 7.5 7.5M16 17V19H2V17S2 13 9 13 16 17 16 17M14 17C13.86 16.22 12.67 15 9 15S4.07 16.31 4 17M15.95 13A5.32 5.32 0 0 1 18 17V19H22V17S22 13.37 15.94 13Z" />
	</svg>
	<span>Member Management</span>
  </a>
  <a href="<?= base_url('officer/sectors.php') ?>" class="<?= $activeNav === 'sectors' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-sprinkler-variant" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M10 10H14V22H10V10M7 9H9V7H7V9M4 8H6V6H4V8M4 11H6V9H4V11M1 13H3V11H1V13M1 7H3V5H1V7M1 10H3V8H1V10M18 11H20V9H18V11M21 10H23V8H21V10M21 5V7H23V5H21M21 13H23V11H21V13M15 9H17V7H15V9M18 8H20V6H18V8M10 7H10.33L11 9H13L13.67 7H14V6H10V7Z" />
	</svg>
	<span>Sector Management</span>
  </a>
  <a href="<?= base_url('officer/sector_boundaries.php') ?>" class="<?= $activeNav === 'boundaries' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-group" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M1,1V5H2V19H1V23H5V22H19V23H23V19H22V5H23V1H19V2H5V1M5,4H19V5H20V19H19V20H5V19H4V5H5M6,6V14H9V18H18V9H14V6M8,8H12V12H8M14,11H16V16H11V14H14" />
	</svg>
	<span>Sector Boundaries</span>
  </a>
  <a href="<?= base_url('officer/billing_periods.php') ?>" class="<?= $activeNav === 'billing' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-receipt" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M3,22L4.5,20.5L6,22L7.5,20.5L9,22L10.5,20.5L12,22L13.5,20.5L15,22L16.5,20.5L18,22L19.5,20.5L21,22V2L19.5,3.5L18,2L16.5,3.5L15,2L13.5,3.5L12,2L10.5,3.5L9,2L7.5,3.5L6,2L4.5,3.5L3,2M18,9H6V7H18M18,13H6V11H18M18,17H6V15H18V17Z" />
	</svg>
	<span>Billing &amp; ISF</span>
  </a>
  <a href="<?= base_url('officer/schedules.php') ?>" class="<?= $activeNav === 'schedules' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-rotate-right" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M16.89,15.5L18.31,16.89C19.21,15.73 19.76,14.39 19.93,13H17.91C17.77,13.87 17.43,14.72 16.89,15.5M13,17.9V19.92C14.39,19.75 15.74,19.21 16.9,18.31L15.46,16.87C14.71,17.41 13.87,17.76 13,17.9M19.93,11C19.76,9.61 19.21,8.27 18.31,7.11L16.89,8.53C17.43,9.28 17.77,10.13 17.91,11M15.55,5.55L11,1V4.07C7.06,4.56 4,7.92 4,12C4,16.08 7.05,19.44 11,19.93V17.91C8.16,17.43 6,14.97 6,12C6,9.03 8.16,6.57 11,6.09V10L15.55,5.55Z" />
	</svg>
	<span>Rotation Scheduling</span>
  </a>
  <a href="<?= base_url('officer/machinery.php') ?>" class="<?= $activeNav === 'machinery' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-tractor" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M5,4V11.26C3.2,11.9 2,13.6 2,15.5C2,18 4,20 6.5,20C8.79,20 10.71,18.28 10.97,16H15.17C15.06,16.32 15,16.66 15,17A3,3 0 0,0 18,20A3,3 0 0,0 21,17C21,16.66 20.94,16.32 20.82,16H22V13C22,11.89 21.11,11 20,11H15.04L13.65,4H5M7,6H12L13,11V14H10.74C10.16,12.38 8.71,11.23 7,11.03V6M6.5,13.25A2.25,2.25 0 0,1 8.75,15.5A2.25,2.25 0 0,1 6.5,17.75A2.25,2.25 0 0,1 4.25,15.5A2.25,2.25 0 0,1 6.5,13.25M18,15.5A1.5,1.5 0 0,1 19.5,17A1.5,1.5 0 0,1 18,18.5A1.5,1.5 0 0,1 16.5,17A1.5,1.5 0 0,1 18,15.5Z" />
	</svg>
	<span>Machinery Inventory</span>
  </a>
  <a href="<?= base_url('officer/reservations.php') ?>" class="<?= $activeNav === 'reservations' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-clipboard-list-outline" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M19,3H14.82C14.4,1.84 13.3,1 12,1C10.7,1 9.6,1.84 9.18,3H5C3.9,3 3,3.9 3,5V19C3,20.1 3.9,21 5,21H19C20.1,21 21,20.1 21,19V5C21,3.9 20.1,3 19,3M12,3C12.55,3 13,3.45 13,4C13,4.55 12.55,5 12,5C11.45,5 11,4.55 11,4C11,3.45 11.45,3 12,3M7,7H17V9H7V7M7,11H17V13H7V11M7,15H14V17H7V15Z" />
	</svg>
	<span>Reservation Requests</span>
	<?php if ($pendingReservationCount > 0): ?>
	  <span class="nav-badge"><?= $pendingReservationCount > 99 ? '99+' : $pendingReservationCount ?></span>
	<?php endif; ?>
  </a>
  <a href="<?= base_url('officer/map.php') ?>" class="<?= $activeNav === 'map' ? 'active' : '' ?>">
	<svg version="1.1" id="mdi-map" fill="white" width="24" height="24" viewBox="0 0 24 24">
	  <path d="M15,19L9,16.89V5L15,7.11M20.5,3C20.44,3 20.39,3 20.34,3L15,5.1L9,3L3.36,4.9C3.15,4.97 3,5.15 3,5.38V20.5A0.5,0.5 0 0,0 3.5,21C3.55,21 3.61,21 3.66,20.97L9,18.9L15,21L20.64,19.1C20.85,19 21,18.85 21,18.62V3.5A0.5,0.5 0 0,0 20.5,3Z" />
	</svg>
	<span>Member Map</span>
  </a>

  <form method="POST" action="<?= base_url('logout.php') ?>">
    <button type="submit" class="logout-btn">Log Out</button>
  </form>
</aside>
