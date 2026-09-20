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
    var toggleSectorContext = config.toggleSectorContextId ? document.getElementById(config.toggleSectorContextId) : null;
    var toggleOtherLotsContext = config.toggleOtherLotsContextId ? document.getElementById(config.toggleOtherLotsContextId) : null;
    var legendBox = config.legendElementId ? document.getElementById(config.legendElementId) : null;

    function escapeHtml(str) {
      var div = document.createElement('div');
      div.appendChild(document.createTextNode(String(str)));
      return div.innerHTML;
    }

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

    // Read-only context layers — sector boundaries and other farm lots'
    // boundaries, shown dimmed/dashed purely as a visual guide while
    // drawing or adjusting this lot's own shape (e.g. "am I inside the
    // right sector?", "does this overlap a neighboring lot?"). Neither
    // layer is editable; toggled on/off via the two checkboxes above the
    // map. Sector context defaults ON (usually the most useful reference,
    // and there's normally just a few of them); other-lots context
    // defaults OFF (there could be many, and showing them all immediately
    // could clutter a small lot's own shape while it's being drawn).
    var sectorContextGroup = L.layerGroup();
    var lotContextGroup = L.layerGroup();
    var contextSectors = config.contextSectors || [];
    var contextLots = config.contextLots || [];

    contextSectors.forEach(function (s) {
      if (!s.boundary || s.boundary.length < 3) return;
      L.polygon(s.boundary, {
        color: '#2e9cca',
        weight: 1.5,
        opacity: 0.6,
        fillOpacity: 0.06,
        dashArray: '4,4'
      }).bindTooltip('Sector: ' + s.name, { sticky: true }).addTo(sectorContextGroup);
    });

    contextLots.forEach(function (lb) {
      if (!lb.boundary || lb.boundary.length < 3) return;
      L.polygon(lb.boundary, {
        color: '#8e44ad',
        weight: 1.5,
        opacity: 0.55,
        fillOpacity: 0.06,
        dashArray: '2,4'
      }).bindTooltip(lb.lotName + ' (' + lb.owner + ')', { sticky: true }).addTo(lotContextGroup);
    });

    if (toggleSectorContext && toggleSectorContext.checked) sectorContextGroup.addTo(map);
    if (toggleOtherLotsContext && toggleOtherLotsContext.checked) lotContextGroup.addTo(map);

    function renderContextLegend() {
      if (!legendBox) return;
      var items = [];
      if (contextSectors.length > 0 && toggleSectorContext && toggleSectorContext.checked) {
        items.push('<span class="legend-item"><span class="legend-swatch" style="background:#2e9cca;"></span>Sector boundaries</span>');
      }
      if (contextLots.length > 0 && toggleOtherLotsContext && toggleOtherLotsContext.checked) {
        items.push('<span class="legend-item"><span class="legend-swatch" style="background:#8e44ad;"></span>Other farm lots</span>');
      }
      legendBox.innerHTML = items.join('');
    }
    renderContextLegend();

    if (toggleSectorContext) {
      toggleSectorContext.addEventListener('change', function () {
        if (toggleSectorContext.checked) {
          sectorContextGroup.addTo(map);
        } else {
          map.removeLayer(sectorContextGroup);
        }
        renderContextLegend();
      });
    }

    if (toggleOtherLotsContext) {
      toggleOtherLotsContext.addEventListener('change', function () {
        if (toggleOtherLotsContext.checked) {
          lotContextGroup.addTo(map);
        } else {
          map.removeLayer(lotContextGroup);
        }
        renderContextLegend();
      });
    }

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
    // wide generic fallback. Deliberately does NOT fit to context layers
    // (sector/other-lot boundaries) — those are just a background
    // reference, and fitting to them would zoom out to fit sectors that
    // could be much larger than this one small lot, defeating the point
    // of a calm, predictable default view.
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
