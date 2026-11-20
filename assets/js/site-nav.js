/**
 * FLX Local Media - primary navigation behaviour.
 *
 * Markup is printed by header.php:
 *   #fx-burger                    menu button (phones and tablets)
 *   #fx-nav                       the nav; gets .is-open as a drawer on phones
 *   [data-fx-item]                one top-level item, holds a .fx-nav__btn and a .fx-panel
 *
 * Desktop (1080px and up): a panel opens on mouse hover, on click, or from the
 * keyboard (Enter, Space, Arrow Down). Escape closes it and returns focus to its
 * button. Leaving the item with the mouse or the Tab key closes a panel that was
 * opened by hover or focus; a panel opened by click stays until you click away.
 * Phones: the same buttons are accordions inside the drawer.
 *
 * The glass bar gets .is-scrolled once the page has moved, which makes the glass
 * a little more opaque (site-bold.css).
 *
 * Without this script, header.php still prints every link, and style
 * site-bold.css opens a panel on hover (html:not(.fx-js)).
 */
(function () {
  'use strict';

  var root = document.documentElement;
  var nav = document.getElementById('fx-nav');
  var burger = document.getElementById('fx-burger');
  if (!nav) return;

  root.classList.add('fx-js');

  var desktop = window.matchMedia ? window.matchMedia('(min-width: 1080px)') : { matches: true };
  var items = [].slice.call(nav.querySelectorAll('[data-fx-item]'));
  var hoverOpen = null;
  var closeTimer = null;

  function btnOf(item) {
    return item.querySelector('.fx-nav__btn');
  }

  function isOpen(item) {
    return item.classList.contains('is-open');
  }

  function setOpen(item, open, byHover) {
    var btn = btnOf(item);
    if (!btn) return;
    item.classList.toggle('is-open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    item.__byHover = open && !!byHover;
  }

  function closeAll(except) {
    items.forEach(function (item) {
      if (item !== except && isOpen(item)) setOpen(item, false);
    });
  }

  /* ---------- Glass gets more solid on scroll ---------- */

  var header = document.getElementById('site-header');
  var scrollTick = false;

  function markScrolled() {
    scrollTick = false;
    if (header) header.classList.toggle('is-scrolled', (window.pageYOffset || document.documentElement.scrollTop || 0) > 8);
  }

  window.addEventListener('scroll', function () {
    if (scrollTick) return;
    scrollTick = true;
    window.requestAnimationFrame(markScrolled);
  }, { passive: true });
  markScrolled();

  /* ---------- Phone drawer ---------- */

  function setDrawer(open) {
    if (!burger) return;
    nav.classList.toggle('is-open', open);
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    burger.setAttribute('aria-label', open ? 'Close menu' : 'Menu');
    document.body.classList.toggle('fx-nav-open', open);
    if (open && !items.some(isOpen) && items.length) setOpen(items[0], true);
  }

  if (burger) {
    burger.addEventListener('click', function () {
      setDrawer(burger.getAttribute('aria-expanded') !== 'true');
    });
  }

  /* ---------- Items ---------- */

  items.forEach(function (item) {
    var btn = btnOf(item);
    if (!btn) return;

    btn.addEventListener('click', function () {
      if (desktop.matches && isOpen(item) && item.__byHover) {
        // Opened by hover, then clicked: keep it open until the visitor clicks away.
        item.__byHover = false;
        return;
      }
      var open = !isOpen(item);
      closeAll(item);
      setOpen(item, open, false);
    });

    btn.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        closeAll(item);
        setOpen(item, true, false);
        var first = item.querySelector('.fx-panel a');
        if (first) first.focus();
      }
    });

    // Mouse hover, desktop only. Touch screens use the click above.
    item.addEventListener('pointerenter', function (e) {
      if (!desktop.matches || e.pointerType !== 'mouse') return;
      window.clearTimeout(closeTimer);
      hoverOpen = item;
      closeAll(item);
      if (!isOpen(item)) setOpen(item, true, true);
    });

    item.addEventListener('pointerleave', function (e) {
      if (!desktop.matches || e.pointerType !== 'mouse') return;
      if (!item.__byHover) return;
      window.clearTimeout(closeTimer);
      closeTimer = window.setTimeout(function () {
        if (hoverOpen === item && item.__byHover) setOpen(item, false);
      }, 160);
    });

    // Tabbing out of an open panel closes it.
    item.addEventListener('focusout', function (e) {
      if (!desktop.matches || !isOpen(item) || item.__byHover) return;
      var next = e.relatedTarget;
      if (next && !item.contains(next)) setOpen(item, false);
    });
  });

  /* ---------- Escape and click-away ---------- */

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape' && e.key !== 'Esc') return;
    var open = items.filter(isOpen);
    if (desktop.matches) {
      if (!open.length) return;
      var focused = document.activeElement;
      open.forEach(function (item) {
        setOpen(item, false);
        if (focused && item.contains(focused)) {
          var btn = btnOf(item);
          if (btn) btn.focus();
        }
      });
    } else if (burger && burger.getAttribute('aria-expanded') === 'true') {
      setDrawer(false);
      burger.focus();
    }
  });

  document.addEventListener('click', function (e) {
    if (!desktop.matches) return;
    if (nav.contains(e.target)) return;
    closeAll();
  });

  /* ---------- Resize: leave the drawer behind when the bar appears ---------- */

  function onResize() {
    if (desktop.matches) {
      if (burger) {
        nav.classList.remove('is-open');
        burger.setAttribute('aria-expanded', 'false');
        burger.setAttribute('aria-label', 'Menu');
      }
      document.body.classList.remove('fx-nav-open');
      closeAll();
    }
  }

  if (desktop.addEventListener) {
    desktop.addEventListener('change', onResize);
  } else if (desktop.addListener) {
    desktop.addListener(onResize);
  }
})();
