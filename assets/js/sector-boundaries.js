/**
 * Sector Boundaries editor.
 * Requires Leaflet + Leaflet.draw (loaded before this script) and the
 * window.BAMBOSA_SECTOR_BOUNDARIES config object (set inline by
 * officer/sector_boundaries.php).
 *
 * Model: each sector has at most one polygon (an array of [lat, lng]
 * pairs). Only one sector's polygon is being edited at a time, selected
 * via the dropdown. Switching sectors swaps which polygon is shown as
 * "active" (editable) on the map, while OTHER sectors' saved boundaries
 * are shown dimmed/read-only for spatial context (so the officer can see
 * where neighboring sectors already are while drawing a new one).
 */
(function () {
  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    var config = window.BAMBOSA_SECTOR_BOUNDARIES;
    if (!config || !document.getElementById('boundaryMap')) return;

    var sectors = config.sectors;         // [{id, name, color, hasBoundary, boundary}]
    var lots = config.lots;               // [{lotId, lotName, owner, boundary:[[lat,lng],...], sectorId}]
    var sectorSelect = document.getElementById('sectorSelect');
    var alertBox = document.getElementById('boundaryAlert');
    var legendBox = document.getElementById('boundaryLegend');
    var matchText = document.getElementById('matchCountText');
    var btnPreview = document.getElementById('btnPreview');
    var btnSave = document.getElementById('btnSave');
    var btnClear = document.getElementById('btnClear');
    var runAssignmentCheckbox = document.getElementById('runAssignmentOnSave');
    var toggleLotMarkersCheckbox = document.getElementById('toggleLotMarkers');

    var sectorsById = {};
    sectors.forEach(function (s) { sectorsById[s.id] = s; });

    // ---- Map setup ----
    var map = L.map('boundaryMap');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    // Default view: loadSectorIntoEditor() (called on initial render, below)
    // decides the actual view — zoomed to the selected sector's boundary if
    // it has one, otherwise a calm default center. No upfront fitBounds to
    // farm lot markers here anymore; that used to make the initial view
    // jump to wherever the lots' bounding box happened to be, which could
    // be a tight zoom on one corner of the map rather than a sensible
    // overview of the association's area.

    // Read-only layer for all OTHER sectors' boundaries (dimmed, for context).
    var contextLayerGroup = L.layerGroup().addTo(map);

    // Editable layer for the currently-selected sector's polygon.
    var editableLayerGroup = new L.FeatureGroup().addTo(map);

    // Farm lots, drawn as their actual boundary shapes (not lat/lng pins),
    // colored by their current sector assignment.
    var lotShapesGroup = L.layerGroup().addTo(map);
    lots.forEach(function (lot) {
      if (!lot.boundary || lot.boundary.length < 3) return;
      var color = (lot.sectorId !== null && sectorsById[lot.sectorId])
        ? sectorsById[lot.sectorId].color
        : '#647a6d';
      var shape = L.polygon(lot.boundary, {
        color: '#1b2b23',
        weight: 1.5,
        fillColor: color,
        fillOpacity: 0.55
      });
      shape.bindPopup(
        '<div class="map-popup"><strong>' + escapeHtml(lot.lotName) + '</strong><br>' +
        'Owner: ' + escapeHtml(lot.owner) + '</div>'
      );
      shape.addTo(lotShapesGroup);
    });

    // Leaflet.draw control — only lets the officer draw/edit polygons,
    // attached to the editable layer group.
    var drawControl = new L.Control.Draw({
      draw: {
        polygon: {
          allowIntersection: true,
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

    function escapeHtml(str) {
      var div = document.createElement('div');
      div.appendChild(document.createTextNode(String(str)));
      return div.innerHTML;
    }

    function showAlert(message, isError) {
      alertBox.innerHTML =
        '<div class="alert ' + (isError ? 'alert-error' : 'alert-success') + '">' + message + '</div>';
      window.scrollTo({ top: alertBox.offsetTop - 20, behavior: 'smooth' });
    }

    function clearAlert() {
      alertBox.innerHTML = '';
    }

    function renderLegend() {
      legendBox.innerHTML = sectors.map(function (s) {
        return '<span class="legend-item">' +
          '<span class="legend-swatch" style="background:' + s.color + ';"></span>' +
          escapeHtml(s.name) + (s.hasBoundary ? '' : ' <em>(no boundary)</em>') +
          '</span>';
      }).join('');
    }

    /** Redraws the read-only context layer with every sector EXCEPT the given one. */
    function renderContextLayer(excludeSectorId) {
      contextLayerGroup.clearLayers();
      sectors.forEach(function (s) {
        if (s.id === excludeSectorId || !s.boundary || s.boundary.length < 3) return;
        L.polygon(s.boundary, {
          color: s.color,
          weight: 1.5,
          opacity: 0.5,
          fillOpacity: 0.08,
          dashArray: '4,4'
        }).bindTooltip(s.name, { sticky: true }).addTo(contextLayerGroup);
      });
    }

    /** Loads the given sector's polygon (if any) into the editable layer. */
    function loadSectorIntoEditor(sectorId) {
      editableLayerGroup.clearLayers();
      var sector = sectorsById[sectorId];
      if (sector && sector.boundary && sector.boundary.length >= 3) {
        var poly = L.polygon(sector.boundary, {
          color: sector.color,
          weight: 3,
          fillOpacity: 0.2
        });
        editableLayerGroup.addLayer(poly);
        // Zoom to this sector's own boundary — a calm, predictable view
        // centered on what's actually being edited, rather than whatever
        // bounding box happens to contain every farm lot marker on the
        // map (which could be off in a corner, or force a very wide zoom
        // if lots are scattered).
        map.fitBounds(poly.getBounds().pad(0.3));
      } else {
        // No boundary drawn for this sector yet — nothing in particular
        // to zoom to, so fall back to a calm, centered default view
        // instead of fitting to farm lot markers (see setDefaultView() below).
        setDefaultView();
      }
      renderContextLayer(sectorId);
      matchText.textContent = 'Draw or edit a polygon above, then click "Preview Matches" to see how many farm lots fall inside it.';
      clearAlert();
    }

    /** Calm, predictable default view when there's no specific boundary to zoom to. */
    function setDefaultView() {
      if (config.defaultCenter) {
        map.setView([config.defaultCenter.lat, config.defaultCenter.lng], 14);
      } else {
        // Barangay Bikal, Libmanan, Camarines Sur — where this association operates.
        map.setView([13.723053, 122.986048], 14);
      }
    }

    function currentSectorId() {
      return parseInt(sectorSelect.value, 10);
    }

    /** Returns the currently-drawn polygon's points as [[lat,lng],...], or null if none. */
    function getCurrentPolygonPoints() {
      var layers = editableLayerGroup.getLayers();
      if (layers.length === 0) return null;
      // Only one polygon per sector is supported; if more than one was
      // somehow drawn, use the first and ignore the rest.
      var latlngs = layers[0].getLatLngs()[0]; // polygon rings: outer ring is index 0
      return latlngs.map(function (ll) { return [ll.lat, ll.lng]; });
    }

    function apiPost(url, body) {
      return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      }).then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok || !data.ok) {
            throw new Error(data.error || 'Request failed.');
          }
          return data;
        });
      });
    }

    // ---- Event wiring ----

    sectorSelect.addEventListener('change', function () {
      loadSectorIntoEditor(currentSectorId());
    });

    if (toggleLotMarkersCheckbox) {
      toggleLotMarkersCheckbox.addEventListener('change', function () {
        if (toggleLotMarkersCheckbox.checked) {
          map.addLayer(lotShapesGroup);
        } else {
          map.removeLayer(lotShapesGroup);
        }
      });
    }

    btnPreview.addEventListener('click', function () {
      var points = getCurrentPolygonPoints();
      if (!points || points.length < 3) {
        matchText.textContent = 'Draw a polygon with at least 3 points first.';
        return;
      }

      matchText.textContent = 'Checking…';
      apiPost(config.previewUrl, { csrf_token: config.csrfToken, points: points })
        .then(function (data) {
          if (data.matched_count === 0) {
            matchText.textContent = 'No farm lots fall inside this polygon.';
          } else {
            var names = data.matched_lots.slice(0, 5).map(function (l) {
              return escapeHtml(l.lot_name) + ' (' + escapeHtml(l.owner) + ')';
            }).join(', ');
            var more = data.matched_count > 5 ? ', and ' + (data.matched_count - 5) + ' more' : '';
            matchText.innerHTML = '<strong>' + data.matched_count + '</strong> farm lot' +
              (data.matched_count === 1 ? '' : 's') + ' would be inside this boundary: ' + names + more + '.';
          }
        })
        .catch(function (err) {
          matchText.textContent = 'Could not check matches: ' + err.message;
        });
    });

    btnSave.addEventListener('click', function () {
      var points = getCurrentPolygonPoints();
      if (!points || points.length < 3) {
        showAlert('Draw a polygon with at least 3 points before saving.', true);
        return;
      }

      var sectorId = currentSectorId();
      var runAssignment = runAssignmentCheckbox.checked;

      btnSave.disabled = true;
      btnSave.textContent = 'Saving…';

      apiPost(config.saveUrl, {
        csrf_token: config.csrfToken,
        sector_id: sectorId,
        points: points,
        run_assignment: runAssignment
      }).then(function (data) {
        var sector = sectorsById[sectorId];
        sector.boundary = points;
        sector.hasBoundary = true;

        // Update the dropdown label and legend to reflect the saved state.
        var opt = sectorSelect.querySelector('option[value="' + sectorId + '"]');
        if (opt) opt.textContent = sector.name + ' (boundary drawn)';
        renderLegend();
        renderContextLayer(sectorId);

        var message = 'Boundary saved for "' + escapeHtml(sector.name) + '".';
        if (runAssignment && data.assignment) {
          message += ' Checked ' + data.assignment.checked_count + ' farm lot(s); ' +
            data.assignment.changed_count + ' sector assignment(s) updated.';
          if (data.assignment.changed_count > 0) {
            message += ' Reload this page to see the updated log below.';
          }
        }
        showAlert(message, false);
      }).catch(function (err) {
        showAlert('Could not save boundary: ' + escapeHtml(err.message), true);
      }).finally(function () {
        btnSave.disabled = false;
        btnSave.textContent = 'Save Boundary';
      });
    });

    btnClear.addEventListener('click', function () {
      var sectorId = currentSectorId();
      var sector = sectorsById[sectorId];

      if (!sector.hasBoundary && editableLayerGroup.getLayers().length === 0) {
        showAlert('This sector has no boundary to clear.', true);
        return;
      }

      if (!confirm('Clear the boundary for "' + sector.name + '"? Farm lots currently assigned via this boundary will keep their sector until manually changed or reassigned by a different boundary.')) {
        return;
      }

      btnClear.disabled = true;

      apiPost(config.saveUrl, {
        csrf_token: config.csrfToken,
        sector_id: sectorId,
        points: null,
        run_assignment: false
      }).then(function () {
        sector.boundary = null;
        sector.hasBoundary = false;
        editableLayerGroup.clearLayers();

        var opt = sectorSelect.querySelector('option[value="' + sectorId + '"]');
        if (opt) opt.textContent = sector.name + ' (no boundary yet)';
        renderLegend();
        renderContextLayer(sectorId);
        matchText.textContent = 'Boundary cleared. Draw a new polygon above if needed.';
        showAlert('Boundary cleared for "' + escapeHtml(sector.name) + '".', false);
      }).catch(function (err) {
        showAlert('Could not clear boundary: ' + escapeHtml(err.message), true);
      }).finally(function () {
        btnClear.disabled = false;
      });
    });

    // Leaflet.draw fires these when the officer finishes drawing/editing/deleting.
    map.on(L.Draw.Event.CREATED, function (e) {
      // Only one polygon per sector: replace whatever was there before.
      editableLayerGroup.clearLayers();
      editableLayerGroup.addLayer(e.layer);
      matchText.textContent = 'Polygon drawn. Click "Preview Matches" to check, then "Save Boundary" to confirm.';
    });

    map.on(L.Draw.Event.EDITED, function () {
      matchText.textContent = 'Polygon edited. Click "Preview Matches" to check, then "Save Boundary" to confirm your changes.';
    });

    map.on(L.Draw.Event.DELETED, function () {
      matchText.textContent = 'Polygon removed from the map (not yet saved). Click "Save Boundary" to confirm, or draw a new one.';
    });

    // ---- Initial render ----
    renderLegend();
    // loadSectorIntoEditor() already sets the correct initial view itself
    // (zoomed to the selected sector's boundary if it has one, otherwise
    // the calm default center) — no separate fitBounds needed here.
    loadSectorIntoEditor(currentSectorId());
  });
})();
