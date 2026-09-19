/**
 * Farm Lot Boundary editor.
 * Requires Leaflet + Leaflet.draw (loaded before this script) and the
 * window.BAMBOSA_FARM_LOT_BOUNDARY config object (set inline by
 * officer/member_view.php).
 *
 * Unlike the sector boundary editor (assets/js/sector-boundaries.js),
 * there is NO separate save button and NO AJAX call here. Drawing (or
 * editing, or clearing) a polygon just keeps a hidden <input> field on the
 * farm lot form in sync with the current shape as JSON; the polygon is
 * submitted and saved together with the rest of the lot's fields in one
 * normal form POST (see officer/member_view.php). This means:
 * - Drawing a boundary works the same for a brand-new lot as for editing
 *   an existing one — no "save the lot first" limitation.
 * - There's no way to draw a boundary and then accidentally leave the
 *   page without it being saved along with everything else, or vice
 *   versa (save the lot's other fields but lose the boundary) — it's all
 *   one save.
 */
(function () {
  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    var config = window.BAMBOSA_FARM_LOT_BOUNDARY;
    var mapEl = document.getElementById('lotBoundaryMap');
    if (!config || !mapEl) return;

    var hiddenInput = document.getElementById(config.hiddenInputId);
    var statusText = document.getElementById(config.statusElementId);
    var clearBtn = document.getElementById(config.clearButtonId);
    var alertBox = document.getElementById('lotBoundaryAlert');

    function clearAlert() {
      if (alertBox) alertBox.innerHTML = '';
    }

    function showAlert(message, isError) {
      if (!alertBox) return;
      alertBox.innerHTML =
        '<div class="alert ' + (isError ? 'alert-error' : 'alert-success') + '">' + message + '</div>';
    }

    // ---- Map setup ----
    var map = L.map('lotBoundaryMap');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    var editableLayerGroup = new L.FeatureGroup().addTo(map);

    if (config.boundary && config.boundary.length >= 3) {
      var existing = L.polygon(config.boundary, {
        color: '#1f7a4d',
        weight: 3,
        fillOpacity: 0.2
      });
      editableLayerGroup.addLayer(existing);
    }

    var drawControl = new L.Control.Draw({
      draw: {
        polygon: {
          allowIntersection: false,
          showArea: true,
          shapeOptions: { color: '#1f7a4d', weight: 2 }
        },
        polyline: false,
        rectangle: false,
        circle: false,
        circlemarker: false,
        marker: false
      },
      edit: {
        featureGroup: editableLayerGroup,
        remove: true
      }
    });
    map.addControl(drawControl);

    // Center the map: prefer the existing polygon, then a sensible default
    // (average of other lots' centroids, computed server-side), then a
    // wide generic fallback.
    if (config.boundary && config.boundary.length >= 3) {
      map.fitBounds(L.polygon(config.boundary).getBounds().pad(0.3));
    } else if (config.defaultCenter) {
      map.setView([config.defaultCenter.lat, config.defaultCenter.lng], 14);
    } else {
      // Barangay Bikal, Libmanan, Camarines Sur — same fallback used elsewhere.
      map.setView([13.723053, 122.986048], 14);
    }

    function getCurrentPolygonPoints() {
      var layers = editableLayerGroup.getLayers();
      if (layers.length === 0) return null;
      var latlngs = layers[0].getLatLngs()[0]; // outer ring
      return latlngs.map(function (ll) { return [ll.lat, ll.lng]; });
    }

    function syncHiddenField() {
      var points = getCurrentPolygonPoints();
      hiddenInput.value = points ? JSON.stringify(points) : '';
    }

    // ---- Event wiring — every change just re-syncs the hidden field;
    // nothing is sent to the server until the whole form is submitted. ----

    map.on(L.Draw.Event.CREATED, function (e) {
      // Only one polygon per lot: replace whatever was there before.
      editableLayerGroup.clearLayers();
      editableLayerGroup.addLayer(e.layer);
      syncHiddenField();
      clearAlert();
      if (statusText) statusText.textContent = 'Boundary drawn. It will be saved when you submit this form below.';
    });

    map.on(L.Draw.Event.EDITED, function () {
      syncHiddenField();
      if (statusText) statusText.textContent = 'Boundary edited. The change will be saved when you submit this form below.';
    });

    map.on(L.Draw.Event.DELETED, function () {
      syncHiddenField();
      if (statusText) statusText.textContent = 'Boundary removed from the map. This lot will have no map location once you save, unless you draw a new one.';
    });

    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        if (editableLayerGroup.getLayers().length === 0) {
          showAlert('There is no drawn boundary to clear.', true);
          return;
        }
        if (!confirm('Clear the drawn boundary? This lot will have no map location once you save this form, unless you draw a new one.')) {
          return;
        }
        editableLayerGroup.clearLayers();
        syncHiddenField();
        clearAlert();
        if (statusText) statusText.textContent = 'Boundary cleared. Draw a new one above if needed, or save the form to confirm removing it.';
      });
    }
  });
})();
