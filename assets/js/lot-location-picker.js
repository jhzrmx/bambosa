/**
 * Farm lot location picker.
 * Requires Leaflet (loaded before this script) and the
 * window.BAMBOSA_LOT_LOCATION_PICKER config object (set inline by
 * officer/member_view.php).
 *
 * Behavior:
 * - Click the map to drop a pin there; the lat/lng text inputs fill in
 *   automatically.
 * - Drag an existing pin to move it; inputs update to match.
 * - Typing directly into the lat/lng inputs also moves (or creates) the
 *   pin, so the two stay in sync regardless of which one the officer uses.
 * - A "Clear pin / coordinates" button removes the pin and empties both
 *   inputs, for lots that shouldn't have a location yet.
 */
(function () {
  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    var config = window.BAMBOSA_LOT_LOCATION_PICKER;
    var mapEl = config ? document.getElementById(config.mapElementId) : null;
    if (!config || !mapEl) return;

    var latInput = document.getElementById(config.latInputId);
    var lngInput = document.getElementById(config.lngInputId);
    var clearBtn = document.getElementById(config.clearButtonId);

    var map = L.map(config.mapElementId);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    var marker = null;

    function setPin(lat, lng, panTo) {
      var latLng = [lat, lng];
      if (marker) {
        marker.setLatLng(latLng);
      } else {
        marker = L.marker(latLng, { draggable: true }).addTo(map);
        marker.on('drag', function (e) {
          updateInputsFromLatLng(e.target.getLatLng());
        });
        marker.on('dragend', function (e) {
          updateInputsFromLatLng(e.target.getLatLng());
        });
      }
      if (panTo) {
        map.panTo(latLng);
      }
    }

    function removePin() {
      if (marker) {
        map.removeLayer(marker);
        marker = null;
      }
    }

    function updateInputsFromLatLng(latLng) {
      latInput.value = latLng.lat.toFixed(7);
      lngInput.value = latLng.lng.toFixed(7);
    }

    function updatePinFromInputs(panTo) {
      var lat = parseFloat(latInput.value);
      var lng = parseFloat(lngInput.value);
      if (isFinite(lat) && isFinite(lng) && latInput.value.trim() !== '' && lngInput.value.trim() !== '') {
        setPin(lat, lng, panTo);
      } else if (latInput.value.trim() === '' && lngInput.value.trim() === '') {
        removePin();
      }
      // If only one of the two fields is filled in, leave the pin alone —
      // an incomplete pair isn't a valid location yet.
    }

    // Clicking the map drops (or moves) the pin there.
    map.on('click', function (e) {
      setPin(e.latlng.lat, e.latlng.lng, false);
      updateInputsFromLatLng(e.latlng);
    });

    // Typing directly into either coordinate field keeps the pin in sync.
    // Debounced slightly via 'input' + a microtask-ish delay is unnecessary
    // here since parseFloat on a partial number just fails harmlessly until
    // the value is a complete, valid number.
    latInput.addEventListener('input', function () { updatePinFromInputs(false); });
    lngInput.addEventListener('input', function () { updatePinFromInputs(false); });

    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        latInput.value = '';
        lngInput.value = '';
        removePin();
      });
    }

    // ---- Initial state ----
    if (config.initialLat !== null && config.initialLng !== null) {
      setPin(config.initialLat, config.initialLng, false);
      map.setView([config.initialLat, config.initialLng], 16);
    } else if (config.defaultCenter) {
      map.setView([config.defaultCenter.lat, config.defaultCenter.lng], 13);
    } else {
      // No lot coordinates exist anywhere yet — default to Barangay Bikal,
      // Libmanan, Camarines Sur, where this association operates.
      map.setView([13.723053, 122.986048], 14);
    }
  });
})();
