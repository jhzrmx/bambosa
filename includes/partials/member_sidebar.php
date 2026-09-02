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

  <a href="<?= base_url('member/dashboard.php') ?>" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
  <a href="<?= base_url('member/profile.php') ?>" class="<?= $activeNav === 'profile' ? 'active' : '' ?>">My Profile &amp; Farm Lot</a>
  <a href="<?= base_url('member/billing.php') ?>" class="<?= $activeNav === 'billing' ? 'active' : '' ?>">My Billing / ISF</a>
  <a href="<?= base_url('member/schedule.php') ?>" class="<?= $activeNav === 'schedule' ? 'active' : '' ?>">Rotation Status</a>
  <a href="<?= base_url('member/machinery.php') ?>" class="<?= $activeNav === 'machinery' ? 'active' : '' ?>">Reserve Machinery</a>
  <a href="<?= base_url('member/map.php') ?>" class="<?= $activeNav === 'map' ? 'active' : '' ?>">My Farm Map</a>

  <form method="POST" action="<?= base_url('logout.php') ?>">
    <button type="submit" class="logout-btn">Log Out</button>
  </form>
</aside>
