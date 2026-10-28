/**
 * Shared helpers, exposed as window.BAMBOSA (this file is loaded on every
 * dashboard page, before any page-specific script):
 *   BAMBOSA.escapeHtml(str)   HTML-escapes text before it goes into innerHTML
 *   BAMBOSA.createMap(id)     Leaflet map on #id with the OpenStreetMap tile
 *                             layer already added (Leaflet must be loaded first)
 */
window.BAMBOSA = window.BAMBOSA || {};

window.BAMBOSA.escapeHtml = function (str) {
  var div = document.createElement('div');
  div.textContent = str == null ? '' : String(str);
  return div.innerHTML;
};

window.BAMBOSA.createMap = function (elementId) {
  var map = L.map(elementId);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);
  return map;
};

/**
 * Mobile sidebar drawer toggle.
 * Expects: #dashSidebar (the drawer), #dashOverlay (the backdrop),
 * and any element with [data-drawer-toggle] or [data-drawer-close].
 */
(function () {
  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    var sidebar = document.getElementById('dashSidebar');
    var overlay = document.getElementById('dashOverlay');

    if (!sidebar || !overlay) return;

    function openDrawer() {
      sidebar.classList.add('open');
      overlay.classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
      sidebar.classList.remove('open');
      overlay.classList.remove('open');
      document.body.style.overflow = '';
    }

    document.querySelectorAll('[data-drawer-toggle]').forEach(function (btn) {
      btn.addEventListener('click', openDrawer);
    });

    document.querySelectorAll('[data-drawer-close]').forEach(function (btn) {
      btn.addEventListener('click', closeDrawer);
    });

    overlay.addEventListener('click', closeDrawer);

    // Close the drawer automatically if the viewport is resized back to desktop width
    window.addEventListener('resize', function () {
      if (window.innerWidth > 880) closeDrawer();
    });

    // Close drawer when a nav link inside it is tapped (mobile navigation UX)
    sidebar.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', closeDrawer);
    });
  });
})();
