/**
 * FLX Local Media — Station Coverage Map
 * Renders a Leaflet map with FM coverage contours on station pages.
 *
 * Expects:
 *   - #station-map element with data-station-call attribute
 *   - flxlmStation global (via wp_localize_script) with callSign, color, contoursUrl, contoursExtendedUrl
 */
(function () {
  'use strict';

  var config = window.flxlmStation;
  if (!config || !config.callSign) return;

  var mapEl = document.getElementById('station-map');
  if (!mapEl) return;

  var callSign = config.callSign.toUpperCase();
  var stationColor = config.color || '#512DA8';

  /* ------------------------------------------------------------------ */
  /*  Finger Lakes city markers                                          */
  /* ------------------------------------------------------------------ */

  var cities = [
    { name: 'Geneva',       lat: 42.8690, lng: -76.9778 },
    { name: 'Auburn',       lat: 42.9317, lng: -76.5661 },
    { name: 'Penn Yan',     lat: 42.6612, lng: -77.0536 },
    { name: 'Canandaigua',  lat: 42.8873, lng: -77.2814 },
    { name: 'Seneca Falls', lat: 42.9109, lng: -76.7966 },
    { name: 'Watkins Glen', lat: 42.3809, lng: -76.8724 },
    { name: 'Bath',         lat: 42.3370, lng: -77.3178 },
    { name: 'Elmira',       lat: 42.0898, lng: -76.8077 },
    { name: 'Rochester',    lat: 43.1566, lng: -77.6088 },
    { name: 'Victor',       lat: 42.9826, lng: -77.4089 },
    { name: 'Dundee',       lat: 42.5237, lng: -76.9767 },
    { name: 'Ithaca',       lat: 42.4440, lng: -76.5019 },
    { name: 'Newark',       lat: 43.0467, lng: -77.0952 },
    { name: 'Syracuse',     lat: 43.0481, lng: -76.1474 }
  ];

  /* ------------------------------------------------------------------ */
  /*  Initialize map                                                     */
  /* ------------------------------------------------------------------ */

  var map = L.map('station-map', {
    scrollWheelZoom: false,
    attributionControl: true
  }).setView([42.75, -77.0], 9);

  /* Esri light-grey canvas, base + labels. Replaces CARTO light_all, which
     started stamping "API KEY REQUIRED" diagonally across every unkeyed tile
     (we never had a CARTO key) - it was printing that across the coverage map
     on this page. Esri needs no key. Note the {z}/{y}/{x} order. */
  L.tileLayer('https://services.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}', {
    attribution: 'Tiles &copy; Esri, HERE, Garmin, USGS',
    maxZoom: 16
  }).addTo(map);

  map.createPane('mapLabels');
  map.getPane('mapLabels').style.zIndex = 650;
  map.getPane('mapLabels').style.pointerEvents = 'none';
  L.tileLayer('https://services.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Reference/MapServer/tile/{z}/{y}/{x}', {
    maxZoom: 16,
    pane: 'mapLabels',
    opacity: 0.95
  }).addTo(map);

  /* ------------------------------------------------------------------ */
  /*  Load contour data                                                  */
  /* ------------------------------------------------------------------ */

  /* One contour per station: the larger "extended" coverage area (54 dBu),
     which is where the station can typically be heard. The smaller primary
     contour and other stations' outlines are no longer drawn. */
  fetch(config.contoursExtendedUrl).then(function (r) { return r.json(); }).then(function (extended) {
    renderCoverage(extended);
    addCityMarkers();
  });

  /* ------------------------------------------------------------------ */
  /*  Coverage area: this station's extended contour, solid              */
  /* ------------------------------------------------------------------ */

  function renderCoverage(data) {
    if (!data || !data.features) return;

    var bounds = L.latLngBounds();
    var hasFeatures = false;

    L.geoJSON(data, {
      filter: function (feature) {
        return (feature.properties.parent_station || '').toUpperCase() === callSign;
      },
      style: function () {
        return {
          color: stationColor,
          weight: 2.5,
          opacity: 0.9,
          fillColor: stationColor,
          fillOpacity: 0.15
        };
      },
      onEachFeature: function (feature, layer) {
        var parent = (feature.properties.parent_station || '').toUpperCase();
        if (parent === callSign) {
          var p = feature.properties;
          var label = '<strong>' + (p.brand || p.parent_station || '') + '</strong>';
          (p.signals || []).forEach(function (s) {
            label += '<br>' + s.call_sign + (s.frequency ? ' ' + s.frequency + ' MHz' : '');
          });
          layer.bindPopup(label);
          bounds.extend(layer.getBounds());
          hasFeatures = true;
        }
      }
    }).addTo(map);

    if (hasFeatures) {
      map.fitBounds(bounds, { padding: [40, 40] });
    }
  }

  /* ------------------------------------------------------------------ */
  /*  City markers — small, unobtrusive labels                           */
  /* ------------------------------------------------------------------ */

  function addCityMarkers() {
    var cityIcon = L.divIcon({
      className: 'station-map-city-marker',
      iconSize: [6, 6],
      iconAnchor: [3, 3]
    });

    cities.forEach(function (city) {
      var marker = L.marker([city.lat, city.lng], {
        icon: cityIcon,
        interactive: false
      }).addTo(map);

      marker.bindTooltip(city.name, {
        permanent: true,
        direction: 'right',
        offset: [6, 0],
        className: 'station-map-city-label'
      });
    });
  }
})();
