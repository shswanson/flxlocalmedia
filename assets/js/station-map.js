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
    { name: 'Corning',      lat: 42.1428, lng: -77.0547 },
    { name: 'Elmira',       lat: 42.0898, lng: -76.8077 },
    { name: 'Rochester',    lat: 43.1566, lng: -77.6088 },
    { name: 'Victor',       lat: 42.9826, lng: -77.4089 },
    { name: 'Dundee',       lat: 42.5237, lng: -76.9767 }
  ];

  /* ------------------------------------------------------------------ */
  /*  Initialize map                                                     */
  /* ------------------------------------------------------------------ */

  var map = L.map('station-map', {
    scrollWheelZoom: false,
    attributionControl: true
  }).setView([42.75, -77.0], 9);

  L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>, &copy; <a href="https://carto.com/">CARTO</a>',
    maxZoom: 16
  }).addTo(map);

  /* ------------------------------------------------------------------ */
  /*  Load contour data                                                  */
  /* ------------------------------------------------------------------ */

  var contoursPromise = fetch(config.contoursUrl).then(function (r) { return r.json(); });
  var extendedPromise = fetch(config.contoursExtendedUrl).then(function (r) { return r.json(); });

  Promise.all([contoursPromise, extendedPromise]).then(function (results) {
    var contours = results[0];
    var extended = results[1];

    renderExtendedContours(extended);
    renderPrimaryContours(contours);
    addCityMarkers();
  });

  /* ------------------------------------------------------------------ */
  /*  Extended contours — faded dashed outer ring for this station       */
  /* ------------------------------------------------------------------ */

  function renderExtendedContours(data) {
    if (!data || !data.features) return;

    L.geoJSON(data, {
      style: function (feature) {
        var parent = (feature.properties.parent_station || '').toUpperCase();
        if (parent !== callSign) {
          return { opacity: 0, fillOpacity: 0, weight: 0 };
        }
        return {
          color: stationColor,
          weight: 1,
          opacity: 0.3,
          fillColor: stationColor,
          fillOpacity: 0.05,
          dashArray: '4 4'
        };
      },
      interactive: false
    }).addTo(map);
  }

  /* ------------------------------------------------------------------ */
  /*  Primary contours — solid for this station, faint gray for others   */
  /* ------------------------------------------------------------------ */

  function renderPrimaryContours(data) {
    if (!data || !data.features) return;

    var bounds = L.latLngBounds();
    var hasFeatures = false;

    L.geoJSON(data, {
      style: function (feature) {
        var parent = (feature.properties.parent_station || '').toUpperCase();
        var isThis = parent === callSign;

        if (!isThis) {
          return {
            color: '#ccc',
            weight: 1,
            opacity: 0.3,
            fillColor: '#eee',
            fillOpacity: 0.05
          };
        }
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
          var label = '<strong>' + (p.call_sign || '') + '</strong>';
          if (p.frequency) label += ' ' + p.frequency + ' MHz';
          if (p.erp_kw) label += '<br>' + p.erp_kw + ' kW';
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
