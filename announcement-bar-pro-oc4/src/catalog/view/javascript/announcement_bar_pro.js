/* Announcement Bar Pro v2.1.2 — no dependencies. Adapts itself to the shop theme (see "Automatic adaptation"). */
(function () {
  'use strict';

  function init() {
    var bar = document.getElementById('abp-bar');
    if (!bar || bar.getAttribute('data-dismissed') === '1') { return; }

    var mode = bar.getAttribute('data-mode');
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var timer = null, tickTimer = null, index = 0, paused = false;
    // Offset between server and visitor clock, so a wrong PC clock cannot shift countdowns/expiry.
    var skew = (parseInt(bar.getAttribute('data-now'), 10) || Date.now()) - Date.now();
    function serverNow() { return Date.now() + skew; }

    if (mode === 'marquee' && reduced) {
      mode = 'rotate';
      bar.className = bar.className.replace('abp-mode-marquee', 'abp-mode-rotate');
    }

    function items() { return bar.querySelectorAll('.abp-item'); }

    /* ---------------- Automatic adaptation to the theme ----------------
       Works on any theme without settings:
       - fixed / sticky headers stuck to the top are moved down below the bar while the bar is on screen;
       - fixed elements stuck to the bottom (cookie banners, mobile tab bars) lift a bottom bar above them;
       - the bar stacks above those elements (z-index);
       - "Automatic" colours take the theme's main colour and a readable text colour;
       - icons are dropped when the theme has no Font Awesome (no empty squares). */
    var isTop = !bar.classList.contains('abp-pos-bottom');
    var cache = { top: [], bottom: [] }, lastScan = 0;

    function px(v) { var n = parseFloat(v); return isNaN(n) ? 0 : n; }

    function candidates() {
      return document.querySelectorAll('body > *, body > * > *, header, nav, [class*="sticky"], [class*="fixed"], [id*="header"], [class*="header"], [class*="navbar"], [class*="cookie"], [id*="cookie"], [class*="consent"]');
    }

    // Find wide fixed/sticky bars resting near the top (a stack of them is fine) or on the bottom edge.
    // Runs on load, resize and at most once a second while scrolling — never on every frame.
    function rescan() {
      lastScan = Date.now();
      var list = candidates(), vw = window.innerWidth, vh = window.innerHeight, top = [], bottom = [];
      var known = cache.top.map(function (e) { return e.el; });
      for (var i = 0; i < list.length; i++) {
        var el = list[i];
        if (el === bar || bar.contains(el) || el.contains(bar) || (el.className && String(el.className).indexOf('abp-spacer') !== -1)) { continue; }
        var k = known.indexOf(el);
        if (k !== -1) { top.push(cache.top[k]); continue; } // keep what we already moved (to restore it later)
        var cs = window.getComputedStyle(el);
        if ((cs.position !== 'fixed' && cs.position !== 'sticky') || cs.display === 'none' || cs.visibility === 'hidden') { continue; }
        var r = el.getBoundingClientRect();
        if (r.width < vw * 0.5 || r.height <= 0 || r.height > vh * 0.6 || r.bottom <= 0) { continue; }
        if (cs.top !== 'auto' && px(cs.top) <= vh * 0.25 && r.top <= px(cs.top) + 2) {
          top.push({ el: el, orig: px(cs.top), h: r.height, inline: el.style.getPropertyValue('top'), prio: el.style.getPropertyPriority('top'), last: null, z: parseInt(cs.zIndex, 10) });
        } else if (cs.position === 'fixed' && cs.bottom !== 'auto' && px(cs.bottom) <= 2) {
          bottom.push({ el: el, z: parseInt(cs.zIndex, 10) });
        }
      }
      // Drop nested elements (the outer one moves them all).
      cache.top = top.filter(function (a) { return !top.some(function (b) { return b !== a && b.el.contains(a.el); }); });
      cache.bottom = bottom;
    }

    function restoreEdges() {
      cache.top.forEach(function (e) {
        if (e.last === null) { return; }
        e.el.style.removeProperty('top');
        if (e.inline) { e.el.style.setProperty('top', e.inline, e.prio); }
        e.last = null;
      });
      bar.style.bottom = '';
    }

    function adaptEdges() {
      if (bar.style.display === 'none') { restoreEdges(); return; }
      var maxZ = 0;
      if (isTop) {
        // Reads first, writes after (no layout thrashing).
        var br = bar.getBoundingClientRect(), below = Math.max(0, Math.round(br.bottom));
        // Move the header stack only if part of it actually sits over the bar; a header the theme already
        // pads content for leaves the bar visible underneath it and stays where it is.
        var covers = br.bottom > 0 && cache.top.some(function (e) { return e.orig < br.bottom - 1 && e.orig + e.h > br.top + 1; });
        cache.top.forEach(function (e) {
          if (!isNaN(e.z)) { maxZ = Math.max(maxZ, e.z); }
          var want = covers ? e.orig + below : null;
          if (want === e.last) { return; }
          if (want === null) {
            e.el.style.removeProperty('top');
            if (e.inline) { e.el.style.setProperty('top', e.inline, e.prio); }
          } else {
            // Plain inline value (no !important): theme scripts that hide the header on scroll still win.
            e.el.style.setProperty('top', want + 'px');
          }
          e.last = want;
        });
      } else {
        var lift = 0, vh = window.innerHeight;
        cache.bottom.forEach(function (e) {
          var r = e.el.getBoundingClientRect();
          if (r.height > 0 && r.top < vh && window.getComputedStyle(e.el).display !== 'none') { lift = Math.max(lift, Math.round(vh - r.top)); }
          if (!isNaN(e.z)) { maxZ = Math.max(maxZ, e.z); }
        });
        bar.style.bottom = lift ? lift + 'px' : '';
      }
      // Stay above the theme's fixed bars, but below modal windows (Bootstrap backdrop 1040+).
      bar.style.zIndex = maxZ >= 1030 ? String(Math.min(maxZ + 1, 1039)) : '';
    }

    function parseRgb(c) {
      var m = /rgba?\(([^)]+)\)/.exec(c || '');
      if (!m) { return null; }
      var p = m[1].split(/[\s,\/]+/).filter(function (x) { return x !== ''; }).map(parseFloat);
      if (p.length < 3 || (p.length > 3 && p[3] === 0)) { return null; }
      return p.slice(0, 3);
    }

    function luminance(rgb) {
      var c = rgb.map(function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
      return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
    }

    function contrast(l1, l2) {
      return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
    }

    // Text colour with the higher WCAG contrast (white or #111111). If even that stays below 4.5:1
    // (mid-tone theme colours), the background is darkened or lightened just enough to reach it.
    function readable(rgb) {
      var dark = luminance([17, 17, 17]), bg = rgb.slice(), fg, toward;
      fg = contrast(luminance(bg), 1) >= contrast(luminance(bg), dark) ? '#ffffff' : '#111111';
      toward = fg === '#ffffff' ? 0 : 255;
      for (var i = 0; i < 20 && contrast(luminance(bg), fg === '#ffffff' ? 1 : dark) < 4.5; i++) {
        bg = bg.map(function (v) { return v + (toward - v) * 0.08; });
      }
      return { bg: bg, fg: fg };
    }

    // The theme's main colour: primary buttons, then the menu / navbar, then the link colour.
    function themeColour() {
      var probes = ['.btn-primary', '#menu', '.navbar-inverse', '#top-menu', '.main-menu', '.navbar', 'header nav', 'button[type="submit"]', '.btn', 'header'];
      for (var i = 0; i < probes.length; i++) {
        var els = document.querySelectorAll(probes[i]);
        for (var j = 0; j < els.length && j < 6; j++) {
          if (bar.contains(els[j]) || !els[j].getBoundingClientRect().width) { continue; }
          var rgb = parseRgb(window.getComputedStyle(els[j]).backgroundColor);
          if (rgb) { var l = luminance(rgb); if (l < 0.8 && l > 0.005) { return rgb; } }
        }
      }
      var links = document.querySelectorAll('a[href]');
      for (var k = 0; k < links.length && k < 40; k++) {
        if (bar.contains(links[k])) { continue; }
        var c = parseRgb(window.getComputedStyle(links[k]).color);
        if (c && luminance(c) < 0.5) { return c; }
      }
      return null;
    }

    function applyAutoColours() {
      if (bar.getAttribute('data-color') !== 'auto') { return; }
      var rgb = themeColour();
      if (rgb) {
        var pick = readable(rgb);
        var bg = 'rgb(' + pick.bg.map(Math.round).join(',') + ')';
        var fg = pick.fg;
        var list = items();
        for (var i = 0; i < list.length; i++) { list[i].style.backgroundColor = bg; list[i].style.color = fg; }
        bar.querySelectorAll('.abp-link, .abp-btn').forEach(function (a) { a.style.color = fg; a.style.borderColor = fg; });
        paintBar(list[index] || list[0]);
      }
      bar.classList.remove('abp-auto-pending');
    }

    // Font Awesome missing in the theme → remove icons instead of showing empty boxes.
    function checkIcons() {
      bar.querySelectorAll('i[class*="fa-"]').forEach(function (icon) {
        var font = window.getComputedStyle(icon).fontFamily || '', before = window.getComputedStyle(icon, '::before').content;
        // Keep it if any icon font styles it or its ::before has a glyph (FA 4/5/6, other icon fonts, SVG kits).
        if (/awesome|icon/i.test(font) || (before && before !== 'none' && before !== 'normal' && before !== '""')) { return; }
        icon.parentNode.removeChild(icon);
      });
    }

    function paintBar(item) {
      if (!item) { return; }
      bar.style.backgroundColor = item.style.backgroundColor;
      bar.style.color = item.style.color;
      var close = bar.querySelector('.abp-close');
      if (close) { close.style.color = item.style.color; }
    }

    // Some themes put the whole page in an absolutely positioned wrapper (OpenCart 4.1: #container).
    // Then <body> is short, CSS "sticky" cannot work and body padding does not reach the page bottom.
    function wrappers() {
      var out = [], kids = document.body.children;
      for (var i = 0; i < kids.length; i++) {
        if (kids[i] !== bar && kids[i].className.indexOf('abp-spacer') === -1 && window.getComputedStyle(kids[i]).position === 'absolute' && kids[i].offsetHeight > window.innerHeight / 2) { out.push(kids[i]); }
      }
      return out;
    }

    function fitBody() {
      var hidden = bar.style.display === 'none';
      if (bar.classList.contains('abp-pos-bottom')) {
        document.body.style.paddingBottom = hidden ? '' : bar.offsetHeight + 'px';
        // With an absolutely positioned page wrapper, body padding does not extend the page: add an
        // invisible block below the wrapper instead, so the footer can scroll above the fixed bar.
        var ws = wrappers(), end = 0;
        ws.forEach(function (w) { end = Math.max(end, w.offsetTop + w.offsetHeight); });
        var sp = document.getElementById('abp-bottom-spacer');
        if (ws.length && !hidden) {
          if (!sp) { sp = document.createElement('div'); sp.id = 'abp-bottom-spacer'; sp.className = 'abp-spacer'; sp.setAttribute('aria-hidden', 'true'); document.body.appendChild(sp); }
          sp.style.cssText = 'position:absolute;left:0;width:1px;pointer-events:none;top:' + end + 'px;height:' + bar.offsetHeight + 'px;';
          if (window.ResizeObserver && !bar.abpRo) { bar.abpRo = new ResizeObserver(function () { fitBody(); }); ws.forEach(function (w) { bar.abpRo.observe(w); }); }
        } else if (sp) {
          sp.parentNode.removeChild(sp);
        }
      }
      adaptEdges();
      if (bar.classList.contains('abp-sticky')) {
        // CSS sticky works only when <body> is as tall as the page; otherwise pin the bar and keep its space.
        var broken = document.body.offsetHeight < document.documentElement.scrollHeight - 2 || wrappers().length > 0;
        bar.classList.toggle('abp-pinned', broken && !hidden);
        var spacer = bar.nextElementSibling && bar.nextElementSibling.className === 'abp-spacer' ? bar.nextElementSibling : null;
        if (broken && !hidden) {
          if (!spacer) { spacer = document.createElement('div'); spacer.className = 'abp-spacer'; bar.parentNode.insertBefore(spacer, bar.nextSibling); }
          spacer.style.height = bar.offsetHeight + 'px';
        } else if (spacer) {
          spacer.parentNode.removeChild(spacer);
        }
      }
    }

    // Rotate mode: reserve the height of the tallest message so the page does not jump on every change.
    function stabilise() {
      var track = bar.querySelector('.abp-track');
      if (!track) { return; }
      track.style.minHeight = '';
      if (mode !== 'rotate') { return; }
      var list = items(), max = 0;
      for (var i = 0; i < list.length; i++) {
        var was = list[i].style.display;
        list[i].style.display = 'block';
        max = Math.max(max, list[i].offsetHeight);
        list[i].style.display = was;
      }
      // Reserve the tallest height only when messages are similar; one very long message must not
      // turn every short one into a huge bar (e.g. on phones).
      var min = Infinity;
      for (var j = 0; j < list.length; j++) { var was2 = list[j].style.display; list[j].style.display = 'block'; min = Math.min(min, list[j].offsetHeight); list[j].style.display = was2; }
      if (max && max <= min * 2.5) { track.style.minHeight = max + 'px'; }
    }

    function show(i) {
      var list = items();
      if (!list.length) { return; }
      index = (i + list.length) % list.length;
      for (var n = 0; n < list.length; n++) {
        if (n === index) { list[n].classList.add('abp-active'); } else { list[n].classList.remove('abp-active'); }
      }
      paintBar(list[index]);
      fitBody();
    }

    function stopRotate() { if (timer) { clearInterval(timer); timer = null; } }

    function startRotate() {
      stopRotate();
      if (paused || mode !== 'rotate' || items().length < 2) { return; }
      var ms = Math.max(2, parseInt(bar.getAttribute('data-interval'), 10) || 5) * 1000;
      timer = setInterval(function () { show(index + 1); }, ms);
    }

    function startMarquee() {
      if (mode !== 'marquee') { return; }
      var track = bar.querySelector('.abp-track'), view = bar.querySelector('.abp-viewport');
      if (!track || !view) { return; }
      track.classList.remove('abp-run');
      var speed = Math.max(20, parseInt(bar.getAttribute('data-speed'), 10) || 60);
      var from = view.clientWidth, to = track.scrollWidth;
      track.style.setProperty('--abp-from', from + 'px');
      track.style.setProperty('--abp-to', -to + 'px');
      track.style.setProperty('--abp-duration', ((from + to) / speed).toFixed(2) + 's');
      void track.offsetWidth; // restart animation
      track.classList.add('abp-run');
    }

    function relayout() { rescan(); stabilise(); show(Math.min(index, Math.max(items().length - 1, 0))); startRotate(); startMarquee(); fitBody(); }

    function hideBar() {
      stopRotate();
      if (tickTimer) { clearInterval(tickTimer); tickTimer = null; }
      bar.style.display = 'none';
      fitBody();
      restoreEdges();
    }

    function pad(n) { return (n < 10 ? '0' : '') + n; }

    // Countdowns + removal of messages whose end time passed while the page is open.
    function tick() {
      var now = serverNow(), changed = false, list = items();
      for (var i = list.length - 1; i >= 0; i--) {
        var end = parseInt(list[i].getAttribute('data-end'), 10);
        if (end && end <= now) { list[i].parentNode.removeChild(list[i]); changed = true; }
      }
      var nodes = bar.querySelectorAll('.abp-countdown');
      for (var c = 0; c < nodes.length; c++) {
        var cEnd = parseInt(nodes[c].getAttribute('data-end'), 10), left = Math.floor((cEnd - now) / 1000);
        if (!cEnd || left <= 0) {
          var item = nodes[c].closest ? nodes[c].closest('.abp-item') : null;
          if (item && item.parentNode) { item.parentNode.removeChild(item); changed = true; }
          continue;
        }
        var d = Math.floor(left / 86400), h = Math.floor(left % 86400 / 3600), m = Math.floor(left % 3600 / 60), s = left % 60;
        nodes[c].textContent = (d > 0 ? d + (bar.getAttribute('data-days-label') || 'd') + ' ' : '') + pad(h) + ':' + pad(m) + ':' + pad(s);
      }
      if (changed) {
        if (!items().length) { hideBar(); return; }
        index = 0; relayout();
      }
      if (!bar.querySelector('.abp-countdown, .abp-item[data-end]') && tickTimer) { clearInterval(tickTimer); tickTimer = null; }
    }

    var close = bar.querySelector('.abp-close');
    if (close) {
      close.addEventListener('click', function () {
        var days = parseInt(bar.getAttribute('data-days'), 10) || 0, now = Date.now();
        try {
          var keep = days > 0 ? localStorage : sessionStorage, drop = days > 0 ? sessionStorage : localStorage;
          // Remember each closed message (merged with earlier ones), so messages that expire or are
          // switched off do not bring the bar back; only new or edited messages do.
          var prev = [];
          try { var v = JSON.parse(keep.getItem('abp_dismiss2') || 'null'); if (v && Array.isArray(v.k) && (!v.t || v.t > now)) { prev = v.k.filter(function (k) { return typeof k === 'string' && /^[0-9a-f]{12}$/.test(k); }); } } catch (e1) { prev = []; }
          var list = items(), keys = prev.slice();
          for (var j = 0; j < list.length; j++) { var k = list[j].getAttribute('data-key'); if (k && keys.indexOf(k) === -1) { keys.push(k); } }
          keep.setItem('abp_dismiss2', JSON.stringify({ k: keys.slice(-200), t: days > 0 ? now + days * 86400000 : 0 }));
          drop.removeItem('abp_dismiss2');
          keep.removeItem('abp_dismiss'); drop.removeItem('abp_dismiss'); // pre-2.0.3 format
        } catch (e) { /* storage blocked: bar just hides for this page view */ }
        var focusWasInside = bar.contains(document.activeElement);
        hideBar();
        // Keyboard users: continue at the first focusable element after the bar, not at <body>.
        if (focusWasInside) {
          var next = document.querySelectorAll('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
          for (var k = 0; k < next.length; k++) {
            if (!bar.contains(next[k]) && (bar.compareDocumentPosition(next[k]) & 4) && next[k].offsetParent !== null) { next[k].focus(); break; }
          }
        }
      });
    }

    // Pause on mouse hover / keyboard focus; a tap on touch screens toggles pause (no stuck mouseenter).
    function pointerIsMouse(e) { return !e.pointerType || e.pointerType === 'mouse'; }
    var touchTimer = null;
    function pause() { paused = true; bar.classList.add('abp-paused'); stopRotate(); }
    function resume() { if (touchTimer) { clearTimeout(touchTimer); touchTimer = null; } paused = false; bar.classList.remove('abp-paused'); startRotate(); }
    // Touch: a tap pauses for at most 8 s; a tap anywhere outside the bar resumes at once.
    function touchPause() { pause(); if (touchTimer) { clearTimeout(touchTimer); } touchTimer = setTimeout(resume, 8000); }

    if (window.PointerEvent) {
      bar.addEventListener('pointerenter', function (e) { if (pointerIsMouse(e)) { pause(); } });
      bar.addEventListener('pointerleave', function (e) { if (pointerIsMouse(e)) { resume(); } });
      bar.addEventListener('pointerup', function (e) {
        if (!pointerIsMouse(e) && !(e.target.closest && e.target.closest('a, button'))) { if (paused) { resume(); } else { touchPause(); } }
      });
      document.addEventListener('pointerdown', function (e) { if (paused && touchTimer && !bar.contains(e.target)) { resume(); } }, true);
    } else {
      bar.addEventListener('mouseenter', pause);
      bar.addEventListener('mouseleave', resume);
    }
    bar.addEventListener('focusin', pause);
    bar.addEventListener('focusout', function () { setTimeout(function () { if (!bar.contains(document.activeElement)) { resume(); } }, 0); });

    var resizeTimer = null;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(relayout, 200);
    });

    // While scrolling only the cached elements are updated; the page is re-scanned at most once a second
    // (headers that become fixed only after scrolling).
    var edgeQueued = false, scanTimer = null;
    window.addEventListener('scroll', function () {
      if (edgeQueued) { return; }
      edgeQueued = true;
      window.requestAnimationFrame(function () {
        edgeQueued = false;
        var wait = 1000 - (Date.now() - lastScan);
        if (wait <= 0) { rescan(); } else if (!scanTimer) {
          scanTimer = setTimeout(function () { scanTimer = null; rescan(); adaptEdges(); }, wait);
        }
        adaptEdges();
      });
    }, { passive: true });

    tick();
    if (bar.querySelector('.abp-countdown, .abp-item[data-end]')) { tickTimer = setInterval(tick, 1000); }
    relayout();

    function afterLoad() { checkIcons(); applyAutoColours(); relayout(); }
    // Web fonts, icons and theme colours are final only after load.
    if (document.readyState === 'complete') { afterLoad(); } else { window.addEventListener('load', afterLoad); }
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
