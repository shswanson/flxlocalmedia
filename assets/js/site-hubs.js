/**
 * FLX Local Media - stations hub and homepage behaviour.
 *
 * Reads window.flxlmHubs (wp_localize_script):
 *   minSongSeconds  tracks shorter than this are treated as ads and hidden
 *   contoursUrl     coverage GeoJSON (property parent_station = call sign)
 *   footprintUrl    one dissolved outline of all stations (the default map view)
 *   stations        [ { call, name, freq, url, color, dash } ] for the map
 *
 * Markup hooks (each feature skips when its element is absent):
 *   .hb-np[data-np]   "on air now" line: data-np is the FLDN now-playing URL
 *   #hub-map          Leaflet coverage map; .hb-legend a[data-legend-call], .hb-tiles .hb-tile
 *
 * Every feature fails quietly: the page keeps the text it was rendered with.
 */
(function () {
  'use strict';

  var cfg = window.flxlmHubs || {};

  /* ------------------------------------------------------------------ */
  /*  On air now. Songs under minSongSeconds (ads, IDs) are not shown.   */
  /*  WGVA and WAUB return the host or show instead of a song.           */
  /* ------------------------------------------------------------------ */

  function initNowPlaying() {
    var rows = [].slice.call(document.querySelectorAll('.hb-np[data-np]'));
    if (!rows.length || !window.fetch) return;

    function update(row) {
      var txt = row.querySelector('.hb-np__txt');
      if (!txt) return;
      fetch(row.getAttribute('data-np'), { cache: 'no-store' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d) return;
          var n = d.now;
          if (n && n.title) {
            var min = cfg.minSongSeconds || 90;
            if (!(n.duration >= min)) return;
            var age = (Date.now() - Date.parse(n.started_at)) / 1000;
            if (!(age >= 0 && age < n.duration + 120)) return;
            set(txt, String(n.title) + (n.artist ? ' · ' + String(n.artist) : ''));
            return;
          }
          var o = d.onair;
          if (o && o.name) {
            var host = String(o.name).split(/\s*-\s*/)[0] || String(o.name);
            set(txt, host + (o.show_time ? ' · ' + String(o.show_time) : ''));
          }
        })
        .catch(function () {});
    }

    function set(el, text) {
      el.textContent = text;
      el.title = text;
    }

    function all() {
      if (document.hidden) return;
      rows.forEach(update);
    }

    all();
    setInterval(all, 60000);
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) all();
    });
  }

  /* ------------------------------------------------------------------ */
  /*  Coverage map: one footprint; a station lights up on hover/tap.        */
  /* ------------------------------------------------------------------ */

  var cities = [
    { name: 'Geneva',       lat: 42.8690, lng: -76.9778, dir: 'bottom' },
    { name: 'Auburn',       lat: 42.9317, lng: -76.5661, dir: 'top' },
    { name: 'Penn Yan',     lat: 42.6612, lng: -77.0536, dir: 'bottom' },
    { name: 'Canandaigua',  lat: 42.8873, lng: -77.2814, dir: 'top' },
    { name: 'Watkins Glen', lat: 42.3809, lng: -76.8724 },
    { name: 'Elmira',       lat: 42.0898, lng: -76.8077 },
    { name: 'Rochester',    lat: 43.1566, lng: -77.6088 },
    { name: 'Ithaca',       lat: 42.4440, lng: -76.5019 },
    { name: 'Syracuse',     lat: 43.0481, lng: -76.1474, dir: 'top' }
  ];

  // Tooltip offset per direction: a few pixels clear of the dot.
  var OFFSETS = { right: [6, 0], left: [-6, 0], top: [0, -5], bottom: [0, 5] };

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function initMap() {
    var el = document.getElementById('hub-map');
    if (!el || !window.L || !cfg.stations || !cfg.stations.length) return;

    var byCall = {};
    cfg.stations.forEach(function (s) { byCall[String(s.call).toUpperCase()] = s; });

    var map = L.map(el, { scrollWheelZoom: false, attributionControl: true, zoomSnap: 0.25 }).setView([42.75, -77.0], 9);

    L.tileLayer('https://services.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}', {
      attribution: 'Tiles &copy; Esri, HERE, Garmin, USGS',
      maxZoom: 16
    }).addTo(map);

    // No Esri reference-label tiles here: our own town markers below carry the names,
    // so a town is never printed twice.

    var layers = {};      // per-station outlines, drawn only while a station is highlighted
    var footprint = null; // one outline of everything we cover
    var active = '';      // call sign currently highlighted
    var pinned = false;   // true after a click or tap: hover no longer changes it
    var lastPointer = 'mouse';

    var cs = window.getComputedStyle(el);
    var NAVY = (cs.getPropertyValue('--hb-navy') || '').trim() || '#003b5b';
    var GREY = (cs.getPropertyValue('--hb-map-grey') || '').trim() || '#8a9ba4';

    function fetchJson(url) {
      return fetch(url).then(function (r) { return r.json(); });
    }

    // Station tiles and legend entries that belong to one station.
    var targets = {};
    // node = the link that receives the pointer; mark = the element that shows it is active.
    function addTarget(call, node, mark) {
      (targets[call] = targets[call] || []).push({ node: node, mark: mark || node });
    }
    [].slice.call(document.querySelectorAll('.hb-legend a[data-legend-call]')).forEach(function (a) {
      addTarget(String(a.getAttribute('data-legend-call')).toUpperCase(), a);
    });
    [].slice.call(document.querySelectorAll('.hb-tiles .hb-tile')).forEach(function (tile) {
      var link = tile.querySelector('.hb-tile__link');
      if (!link) return;
      var href = link.getAttribute('href');
      Object.keys(byCall).forEach(function (call) {
        if (byCall[call].url === href) addTarget(call, link, tile);
      });
    });

    function paint() {
      if (!footprint) return;
      footprint.setStyle(active
        ? { color: GREY, weight: 2, opacity: 0.9, fillColor: GREY, fillOpacity: 0.1 }
        : { color: NAVY, weight: 2.5, opacity: 0.95, fillColor: NAVY, fillOpacity: 0.18 });
      Object.keys(layers).forEach(function (call) {
        if (call === active) {
          layers[call].addTo(map).bringToFront();
        } else if (map.hasLayer(layers[call])) {
          map.removeLayer(layers[call]);
        }
      });
      Object.keys(targets).forEach(function (call) {
        targets[call].forEach(function (n) { n.mark.classList.toggle('is-map-active', call === active); });
      });
    }

    function show(call) { active = call; paint(); }
    function clear() { active = ''; pinned = false; paint(); }

    Object.keys(byCall).forEach(function (call) {
      (targets[call] || []).forEach(function (t) {
        var node = t.node;
        node.addEventListener('pointerdown', function (e) { lastPointer = e.pointerType || 'mouse'; });
        node.addEventListener('mouseenter', function () { if (!pinned && lastPointer === 'mouse') show(call); });
        node.addEventListener('mouseleave', function () { if (!pinned) clear(); });
        node.addEventListener('focusin', function () { if (!pinned) show(call); });
        node.addEventListener('focusout', function () { if (!pinned) clear(); });
        node.addEventListener('click', function (e) {
          // A mouse click goes straight to the station (hover already shows it on the map).
          // On touch the first tap highlights the station on the map and the second tap opens it.
          if (lastPointer !== 'mouse' && !(pinned && active === call)) {
            e.preventDefault();
            pinned = true;
            show(call);
          }
        });
      });
    });

    map.on('click', clear); // tapping the map itself restores the default view
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') clear(); });

    Promise.all([fetchJson(cfg.footprintUrl), fetchJson(cfg.contoursUrl)])
      .then(function (res) {
        var foot = res[0];
        var data = res[1];
        if (!foot || !foot.features || !data || !data.features) return;

        footprint = L.geoJSON(foot, { interactive: false }).addTo(map);

        L.geoJSON(data, {
          interactive: false,
          filter: function (f) {
            return !!byCall[String((f.properties || {}).parent_station || '').toUpperCase()];
          },
          style: function (f) {
            var s = byCall[String(f.properties.parent_station).toUpperCase()];
            return { color: s.color, weight: 2.5, opacity: 1, fillColor: s.color, fillOpacity: 0.22 };
          },
          onEachFeature: function (f, layer) {
            var call = String(f.properties.parent_station).toUpperCase();
            layers[call] = layer;
          }
        });

        paint();
        map.fitBounds(footprint.getBounds(), { padding: [12, 12], maxZoom: 16 });
        addCities();
      })
      .catch(function () {});

    function addCities() {
      var icon = L.divIcon({ className: 'hb-map-city', iconSize: [6, 6], iconAnchor: [3, 3] });
      cities.forEach(function (c) {
        L.marker([c.lat, c.lng], { icon: icon, interactive: false })
          .addTo(map)
          .bindTooltip(c.name, { permanent: true, direction: c.dir || 'right', offset: OFFSETS[c.dir || 'right'], className: 'hb-map-label' });
      });
    }
  }

  initNowPlaying();
  initMap();
})();
