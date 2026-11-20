/**
 * FLX Local Media - station page behaviour ("option D").
 *
 * Reads window.flxlmStationPage (wp_localize_script):
 *   nowPlayingUrl   FLDN now-playing endpoint for this station ('' = none)
 *   streamUrl       https live stream ('' = none)
 *   profileSeconds  length of the profile clip, for the fallback timer text
 *   minSongSeconds  tracks shorter than this are treated as ads and hidden
 *
 * Markup hooks (all optional, each feature skips when its element is absent):
 *   [data-profile-play] + #st-profile-audio + [data-profile-time]   profile clip
 *   [data-live-play]                                                live stream buttons
 *   #st-onair-song / #st-onair-artist                               now playing text
 *   #st-aside nav a[href^="#"]                                      table of contents
 */
(function () {
  'use strict';

  var cfg = window.flxlmStationPage || {};
  var liveAudio = null;
  var profileAudio = document.getElementById('st-profile-audio');

  function $all(sel) {
    return [].slice.call(document.querySelectorAll(sel));
  }

  function clock(sec) {
    sec = Math.max(0, Math.round(sec || 0));
    var s = sec % 60;
    return Math.floor(sec / 60) + ':' + (s < 10 ? '0' : '') + s;
  }

  /* ------------------------------------------------------------------ */
  /*  Now playing. Songs under minSongSeconds (ads, IDs) are hidden.     */
  /*  WGVA and WAUB return the host or show instead of a song.           */
  /*  Any failure keeps the text the page was rendered with.             */
  /* ------------------------------------------------------------------ */

  function initNowPlaying() {
    var songEl = document.getElementById('st-onair-song');
    var artistEl = document.getElementById('st-onair-artist');
    if (!songEl || !cfg.nowPlayingUrl || !window.fetch) return;

    function show(title, sub) {
      songEl.textContent = title;
      songEl.title = title;
      if (artistEl) {
        artistEl.textContent = sub || '';
        artistEl.title = sub || '';
      }
    }

    fetch(cfg.nowPlayingUrl, { cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d) return;
        var n = d.now;
        if (n && n.title) {
          var min = cfg.minSongSeconds || 90;
          if (!(n.duration >= min)) return;
          var age = (Date.now() - Date.parse(n.started_at)) / 1000;
          if (!(age >= 0 && age < n.duration + 120)) return;
          show(String(n.title), n.artist ? String(n.artist) : '');
          return;
        }
        var o = d.onair;
        if (o && o.name) {
          // "Mark Levin-Weeknights 6pm": show the host or show name, then the time slot.
          var host = String(o.name).split(/\s*-\s*/)[0] || String(o.name);
          show(host, o.show_time ? String(o.show_time) : '');
        }
      })
      .catch(function () {});
  }

  /* ------------------------------------------------------------------ */
  /*  Live stream. One shared Audio object, any number of buttons.       */
  /* ------------------------------------------------------------------ */

  function initLive() {
    var buttons = $all('[data-live-play]');
    if (!buttons.length || !cfg.streamUrl) return;

    buttons.forEach(function (btn) {
      var small = btn.querySelector('small');
      btn.__idleSmall = small ? small.textContent : '';
    });

    function paint(state) {
      buttons.forEach(function (btn) {
        var ic = btn.querySelector('.ic');
        var small = btn.querySelector('small');
        btn.setAttribute('aria-pressed', state === 'playing' ? 'true' : 'false');
        if (ic) {
          ic.textContent = state === 'playing' ? '❚❚' : '▶';
          if (small) {
            small.textContent = state === 'connecting' ? 'Connecting…'
              : state === 'playing' ? 'Playing now, tap to pause'
              : state === 'error' ? 'Stream unavailable'
              : btn.__idleSmall;
          }
        } else {
          btn.textContent = state === 'connecting' ? 'Connecting…'
            : state === 'playing' ? '❚❚ Pause'
            : state === 'error' ? 'Stream unavailable'
            : '▶ Listen live';
        }
      });
    }

    function ensureAudio() {
      if (liveAudio) return liveAudio;
      liveAudio = new Audio(cfg.streamUrl);
      liveAudio.preload = 'none';
      liveAudio.addEventListener('playing', function () { paint('playing'); });
      liveAudio.addEventListener('pause', function () { paint('idle'); });
      liveAudio.addEventListener('error', function () { paint('error'); });
      return liveAudio;
    }

    buttons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var a = ensureAudio();
        if (a.paused) {
          if (profileAudio) profileAudio.pause();
          paint('connecting');
          a.load(); // jump to the live edge, not where the stream was paused
          var p = a.play();
          if (p && p.catch) p.catch(function () { paint('idle'); });
        } else {
          a.pause();
        }
      });
    });
  }

  /* ------------------------------------------------------------------ */
  /*  Profile clip (hero button). Pauses the live stream while playing.  */
  /* ------------------------------------------------------------------ */

  function initProfile() {
    var btn = document.querySelector('[data-profile-play]');
    var timeEl = document.querySelector('[data-profile-time]');
    if (!btn || !profileAudio) return;

    var ic = btn.querySelector('.ic');
    var idle = timeEl ? timeEl.textContent : '';
    var fallbackLen = cfg.profileSeconds || 0;

    function setIcon(playing) {
      if (ic) ic.textContent = playing ? '❚❚' : '▶';
      btn.setAttribute('aria-pressed', playing ? 'true' : 'false');
    }

    btn.addEventListener('click', function () {
      if (profileAudio.paused) {
        if (liveAudio) liveAudio.pause();
        var p = profileAudio.play();
        if (p && p.catch) p.catch(function () { setIcon(false); });
      } else {
        profileAudio.pause();
      }
    });
    profileAudio.addEventListener('play', function () { setIcon(true); });
    profileAudio.addEventListener('pause', function () { setIcon(false); });
    profileAudio.addEventListener('timeupdate', function () {
      if (timeEl) timeEl.textContent = clock(profileAudio.currentTime) + ' / ' + clock(profileAudio.duration || fallbackLen);
    });
    profileAudio.addEventListener('ended', function () {
      setIcon(false);
      if (timeEl) timeEl.textContent = idle;
    });
  }

  /* ------------------------------------------------------------------ */
  /*  Table of contents: highlight the section in view. On phones the    */
  /*  fixed call-to-action steps aside while the site footer is showing. */
  /* ------------------------------------------------------------------ */

  function initToc() {
    var links = $all('#st-aside nav a[href^="#"]').filter(function (a) { return a.getAttribute('href').length > 1; });
    var secs = links.map(function (a) { return document.getElementById(a.getAttribute('href').slice(1)); });
    var ticking = false;

    function update() {
      ticking = false;
      var y = window.innerHeight * 0.35;
      var cur = -1;
      secs.forEach(function (sec, i) {
        if (sec && sec.getBoundingClientRect().top <= y) cur = i;
      });
      links.forEach(function (a, i) { a.classList.toggle('active', i === cur); });
    }

    function onScroll() {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(update);
      }
    }

    if (links.length) {
      window.addEventListener('scroll', onScroll, { passive: true });
      window.addEventListener('resize', onScroll);
      update();
    }

    var aside = document.getElementById('st-aside');
    var below = $all('.site-footer, .cta-banner');
    if (aside && below.length && 'IntersectionObserver' in window) {
      var seen = [];
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          var i = seen.indexOf(e.target);
          if (e.isIntersecting && i < 0) seen.push(e.target);
          if (!e.isIntersecting && i >= 0) seen.splice(i, 1);
        });
        aside.classList.toggle('is-hidden', seen.length > 0);
      });
      below.forEach(function (el) { io.observe(el); });
    }
  }

  function init() {
    initNowPlaying();
    initLive();
    initProfile();
    initToc();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
