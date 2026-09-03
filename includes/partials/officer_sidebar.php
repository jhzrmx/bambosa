<?php
/**
 * Officer sidebar partial.
 * Expects $activeNav to be set by the including page to one of:
 * 'dashboard', 'members', 'sectors', 'boundaries', 'billing', 'schedules', 'machinery', 'map'
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
  <p class="role-tag">Officer Portal</p>

  <a href="<?= base_url('officer/dashboard.php') ?>" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
  <a href="<?= base_url('officer/members.php') ?>" class="<?= $activeNav === 'members' ? 'active' : '' ?>">Member Management</a>
  <a href="<?= base_url('officer/sectors.php') ?>" class="<?= $activeNav === 'sectors' ? 'active' : '' ?>">Sector Management</a>
  <a href="<?= base_url('officer/sector_boundaries.php') ?>" class="<?= $activeNav === 'boundaries' ? 'active' : '' ?>">Sector Boundaries</a>
  <a href="<?= base_url('officer/billing_periods.php') ?>" class="<?= $activeNav === 'billing' ? 'active' : '' ?>">Billing &amp; ISF</a>
  <a href="<?= base_url('officer/schedules.php') ?>" class="<?= $activeNav === 'schedules' ? 'active' : '' ?>">Rotation Scheduling</a>
  <a href="<?= base_url('officer/machinery.php') ?>" class="<?= $activeNav === 'machinery' ? 'active' : '' ?>">Machinery Reservation</a>
  <a href="<?= base_url('officer/map.php') ?>" class="<?= $activeNav === 'map' ? 'active' : '' ?>">Member Map</a>

  <form method="POST" action="<?= base_url('logout.php') ?>">
    <button type="submit" class="logout-btn">Log Out</button>
  </form>
</aside>
