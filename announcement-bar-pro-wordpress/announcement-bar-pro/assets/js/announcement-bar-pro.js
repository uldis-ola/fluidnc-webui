/* Announcement Bar Pro v2.4.0 — no dependencies. Adapts itself to the shop theme (see "Automatic adaptation").
   Runs every bar (top / bottom) and box (cart page, product page, mini-cart) on the page; features start only when a
   message uses them (triggers, auto-hide, forms, goals, GA4). */
(function () {
  'use strict';

  if (window.AnnouncementBarPro) { return; } // loaded twice (e.g. inside an AJAX fragment): keep the first copy

  /* ---------------- Shared: privacy, statistics, GA4 ---------------- */
  // Nothing is sent or stored for statistics when the visitor asks not to be tracked (Do Not Track / Global Privacy
  // Control) or the shop's consent tool sets window.abpConsent = false.
  // "Wait for cookie consent" (setting; data-consent="1" on the bars): until the shop's consent tool sets
  // window.abpConsent = true, nothing is stored, read or sent (A/B choice, visitor memory, campaign, statistics, GA4).
  function waitConsent() { return window.abpConsent !== true && !!document.querySelector('.abp[data-consent="1"]'); }
  function privacyOff() {
    var d = navigator.doNotTrack || window.doNotTrack || navigator.msDoNotTrack;
    return d === '1' || d === 'yes' || navigator.globalPrivacyControl === true || window.abpConsent === false || waitConsent();
  }
  var reduced = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  function variant(el) { return el && el.getAttribute('data-variant') === 'b' ? 'b' : 'a'; }
  function inDoc(el) { return !!el && document.documentElement.contains(el); }

  /* Statistics (counters only): one "view" per message per browser session when it is really shown, one "click"
     when its button or a link in it is used, one "copy" when its coupon code is copied; A/B messages report their
     variant. No cookies or storage besides sessionStorage "abp_track". Every failure is silent. */
  var sent = {}, letter = { view: 'v', click: 'c', copy: 'p' };
  function seenInSession(key) {
    var list = [];
    try { list = JSON.parse(sessionStorage.getItem('abp_track') || '[]'); if (!Array.isArray(list)) { list = []; } } catch (e1) { list = []; }
    if (sent[key] || list.indexOf(key) !== -1) { return true; }
    sent[key] = 1;
    list.push(key);
    try { sessionStorage.setItem('abp_track', JSON.stringify(list.slice(-200))); } catch (e2) { /* storage blocked: memory only */ }
    return false;
  }
  function track(bar, el, type) {
    try {
      var id = el && parseInt(el.getAttribute('data-id'), 10), url = bar && bar.getAttribute('data-track');
      if (!id || id < 1 || !letter[type] || privacyOff()) { return; }
      ga(bar, el, 'abp_' + type);
      if (!url || seenInSession(letter[type] + id)) { return; }
      var fd = new FormData();
      fd.append('id', String(id));
      fd.append('type', type);
      fd.append('v', variant(el));
      if (!(navigator.sendBeacon && navigator.sendBeacon(url, fd)) && window.fetch) {
        window.fetch(url, { method: 'POST', body: fd, keepalive: true, credentials: 'same-origin' }).catch(function () {});
      }
    } catch (e) { /* statistics must never break the bar */ }
  }
  /* GA4: only when the shop already loads it (gtag or a GTM dataLayer). Message number, version and placement only. */
  function ga(bar, el, name) {
    try {
      if (!bar || bar.getAttribute('data-ga') !== '1' || privacyOff()) { return; }
      var id = parseInt(el.getAttribute('data-id'), 10);
      if (!id || seenInSession('g' + name.slice(4, 6) + id)) { return; }
      var params = { abp_message_id: id, abp_variant: variant(el), abp_placement: bar.getAttribute('data-place') || '' };
      if (typeof window.gtag === 'function') { window.gtag('event', name, params); } else if (window.dataLayer && typeof window.dataLayer.push === 'function') {
        var ev = { event: name };
        for (var k in params) { if (Object.prototype.hasOwnProperty.call(params, k)) { ev[k] = params[k]; } }
        window.dataLayer.push(ev);
      }
    } catch (e) { /* analytics must never break the bar */ }
  }

  var instances = [];

  function init(bar) {
    if (!bar || bar.getAttribute('data-dismissed') === '1' || bar.abpInit) { return; }
    bar.abpInit = true;

    var inline = bar.getAttribute('data-abp') === 'inline';
    var mode = inline ? 'stack' : bar.getAttribute('data-mode');
    var timer = null, tickTimer = null, index = 0, paused = false, userPaused = false;
    // Offset between server and visitor clock, so a wrong PC clock cannot shift countdowns/expiry/schedules.
    var skew = (parseInt(bar.getAttribute('data-now'), 10) || Date.now()) - Date.now();
    function serverNow() { return Date.now() + skew; }
    // Store time zone offset (seconds) for the weekly schedule and cut-off times.
    var tz = parseInt(bar.getAttribute('data-tz'), 10) || 0;

    if (mode === 'marquee' && reduced) {
      mode = 'rotate';
      bar.className = bar.className.replace('abp-mode-marquee', 'abp-mode-rotate');
    }

    /* ---------------- A/B variant (normally set by the pre-script; here only if it did not run) ---------------- */
    Array.prototype.forEach.call(bar.querySelectorAll('.abp-item[data-ab="1"]'), function (el) {
      if (!el.hasAttribute('data-variant')) { el.setAttribute('data-variant', 'a'); }
    });

    /* ---------------- Weekly schedule (store time) ----------------
       data-wd = ISO weekdays (1 = Monday … 7 = Sunday), data-t1/data-t2 = daily window HH:MM; a window that
       crosses midnight belongs to the day it starts on. Messages outside are marked data-off (hidden by CSS). */
    function minutes(t) { var m = /^(\d{2}):(\d{2})$/.exec(t || ''); return m ? parseInt(m[1], 10) * 60 + parseInt(m[2], 10) : -1; }
    function isOpen(el) {
      var wd = el.getAttribute('data-wd') || '', t1 = minutes(el.getAttribute('data-t1')), t2 = minutes(el.getAttribute('data-t2'));
      if (!wd && t1 < 0) { return true; }
      var d = new Date(serverNow() + tz * 1000), day = d.getUTCDay() || 7, prev = day === 1 ? 7 : day - 1, min = d.getUTCHours() * 60 + d.getUTCMinutes();
      var has = function (x) { return !wd || wd.indexOf(String(x)) !== -1; };
      if (t1 < 0 || t2 < 0 || t1 === t2) { return has(day); }
      if (t1 < t2) { return has(day) && min >= t1 && min < t2; }
      return (min >= t1 && has(day)) || (min < t2 && has(prev));
    }
    function scheduled() { return bar.querySelectorAll('.abp-item[data-wd], .abp-item[data-t1]'); }
    // Returns true when any message switched on or off.
    function applySchedule() {
      var changed = false;
      Array.prototype.forEach.call(scheduled(), function (el) {
        var off = !isOpen(el);
        if (off !== el.hasAttribute('data-off')) { changed = true; if (off) { el.setAttribute('data-off', '1'); } else { el.removeAttribute('data-off'); } }
      });
      return changed;
    }
    // Next daily / weekly cut-off after now (store time): "d|14:00|67" = daily at 14:00 except Sat+Sun, "w|14:00|5".
    function nextCutoff(rule, now) {
      var m = /^([dw])\|(\d{2}):(\d{2})\|([1-7]*)$/.exec(rule || '');
      if (!m) { return 0; }
      var local = new Date(now + tz * 1000);
      for (var i = 0; i < 8; i++) {
        var at = Date.UTC(local.getUTCFullYear(), local.getUTCMonth(), local.getUTCDate() + i, parseInt(m[2], 10), parseInt(m[3], 10)) - tz * 1000;
        var dow = new Date(at + tz * 1000).getUTCDay() || 7;
        if (at > now && (m[1] === 'w' ? m[4] === String(dow) : m[4].indexOf(String(dow)) === -1)) { return at; }
      }
      return 0;
    }

    /* ---------------- Which messages may show ----------------
       Device (768 px, like the CSS), schedule (data-off), cart conditions (data-coff), campaign / visitor rules
       (data-uoff, decided by the pre-script), triggers not fired yet (data-toff); then "Messages per bar" (data-max). */
    var mq = window.matchMedia ? window.matchMedia('(max-width: 767px)') : null;
    var closed = false, deviceHidden = false;
    var max = parseInt(bar.getAttribute('data-max'), 10) || 0;
    function isMobile() { return mq ? mq.matches : window.innerWidth < 768; }
    function fits(el) { var d = el.getAttribute('data-device'); return !el.hasAttribute('data-off') && !el.hasAttribute('data-coff') && !el.hasAttribute('data-uoff') && !el.hasAttribute('data-toff') && (!d || d === 'all' || (d === 'mobile') === isMobile()); }
    function allItems() { return bar.querySelectorAll('.abp-item'); }
    function items() {
      var list = Array.prototype.filter.call(allItems(), fits);
      Array.prototype.forEach.call(allItems(), function (el) { var over = max > 0 && list.indexOf(el) >= max; if (over !== el.hasAttribute('data-over')) { if (over) { el.setAttribute('data-over', '1'); } else { el.removeAttribute('data-over'); } } });
      return max > 0 ? list.slice(0, max) : list;
    }
    function hidden() { return bar.style.display === 'none' || bar.classList.contains('abp-min'); }

    // Views: the active message (rotate) or every message on screen (marquee, stack).
    function trackShown() {
      if (hidden()) { return; }
      var list = items();
      if (mode === 'rotate') { track(bar, list[index], 'view'); } else { list.forEach(function (el) { track(bar, el, 'view'); }); }
    }

    /* ---------------- Automatic adaptation to the theme (bars only) ----------------
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
      if (inline) { return; }
      lastScan = Date.now();
      var list = candidates(), vw = window.innerWidth, vh = window.innerHeight, top = [], bottom = [];
      var known = cache.top.map(function (e) { return e.el; });
      for (var i = 0; i < list.length; i++) {
        var el = list[i];
        if (el === bar || bar.contains(el) || el.contains(bar) || (el.className && /abp-(spacer|bar|tab)/.test(String(el.className)))) { continue; }
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
      if (inline) { return; }
      if (hidden()) { restoreEdges(); return; }
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
      if (tab) { tab.style.zIndex = bar.style.zIndex; }
    }

    /* ---------------- Colours and styles ----------------
       Every style keeps WCAG contrast of at least 4.5:1 between the text and what is behind it. */
    function parseRgb(c) {
      var m = /rgba?\(([^)]+)\)/.exec(c || '');
      if (!m) { return null; }
      var p = m[1].split(/[\s,\/]+/).filter(function (x) { return x !== ''; }).map(parseFloat);
      if (p.length < 3 || (p.length > 3 && p[3] === 0)) { return null; }
      return p.slice(0, 3);
    }
    function hexRgb(h) { return /^#[0-9a-f]{6}$/i.test(h || '') ? [1, 3, 5].map(function (i) { return parseInt(h.substr(i, 2), 16); }) : null; }
    function css(rgb) { return 'rgb(' + rgb.map(function (v) { return Math.max(0, Math.min(255, Math.round(v))); }).join(',') + ')'; }
    function rgba(rgb, a) { return 'rgba(' + rgb.map(function (v) { return Math.max(0, Math.min(255, Math.round(v))); }).join(',') + ',' + a + ')'; }
    function mix(a, b, t) { return a.map(function (v, i) { return v + (b[i] - v) * t; }); }
    var WHITE = [255, 255, 255], BLACK = [0, 0, 0], INK = [17, 17, 17];

    function luminance(rgb) {
      var c = rgb.map(function (v) { v = Math.max(0, Math.min(255, v)) / 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
      return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
    }

    function contrast(l1, l2) {
      return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
    }
    function ratio(a, b) { return contrast(luminance(a), luminance(b)); }

    // Text colour with the higher WCAG contrast (white or #111111). If even that stays below 4.5:1
    // (mid-tone theme colours), the background is darkened or lightened just enough to reach it.
    function readable(rgb) {
      var dark = luminance(INK), bg = rgb.slice(), fg, toward;
      fg = contrast(luminance(bg), 1) >= contrast(luminance(bg), dark) ? '#ffffff' : '#111111';
      toward = fg === '#ffffff' ? 0 : 255;
      for (var i = 0; i < 20 && contrast(luminance(bg), fg === '#ffffff' ? 1 : dark) < 4.5; i++) {
        bg = bg.map(function (v) { return v + (toward - v) * 0.08; });
      }
      return { bg: bg, fg: fg };
    }
    // Moves colour c toward target until it reaches the wanted contrast against every colour in "against".
    function push(c, target, against, want) {
      var out = c.slice();
      for (var i = 0; i < 40 && against.some(function (a) { return ratio(out, a) < want; }); i++) { out = mix(out, target, 0.08); }
      return out;
    }

    var glassOk = !!(window.CSS && CSS.supports && (CSS.supports('backdrop-filter', 'blur(2px)') || CSS.supports('-webkit-backdrop-filter', 'blur(2px)')));

    // Computes the look of one message: base colour (theme colour in Automatic mode, else its own) + style.
    function skinOf(base, ownFg, style) {
      var s = readable(base), fg = ownFg ? ownFg : (s.fg === '#ffffff' ? WHITE : INK), bg = ownFg ? base : s.bg;
      var light = luminance(fg) > luminance(bg);
      var out = { bg: css(bg), image: '', fg: css(fg), btnBg: css(fg), btnFg: css(bg), accent: css(fg), glass: false };
      if (style === 'gradient') {
        // Custom colours below 4.5:1: the first stop is moved away from the text colour just enough.
        if (ratio(fg, bg) < 4.5) { bg = push(bg, light ? BLACK : WHITE, [fg], 4.5); out.bg = css(bg); out.btnFg = css(bg); }
        // Second stop: same hue family, moved away from the text colour (only raises the contrast).
        var c2 = mix(bg, light ? BLACK : WHITE, 0.3);
        c2 = [c2[0] * 0.92 + c2[2] * 0.08, c2[1], c2[2] * 0.9 + c2[0] * 0.1];
        if (ratio(fg, c2) < Math.max(4.5, ratio(fg, bg) - 0.01)) { c2 = mix(bg, light ? BLACK : WHITE, 0.3); }
        out.image = 'linear-gradient(100deg,' + css(bg) + ' 0%,' + css(mix(bg, c2, 0.5)) + ' 55%,' + css(c2) + ' 100%)';
      } else if (style === 'soft' || style === 'minimal') {
        var tint = style === 'soft' ? mix(base, WHITE, 0.86) : mix(base, WHITE, 0.965);
        var text = push(style === 'soft' ? mix(base, BLACK, 0.55) : mix(base, BLACK, 0.78), BLACK, [tint], 4.5);
        var solid = readable(base);
        out.bg = css(tint); out.fg = css(text);
        out.accent = css(push(base, BLACK, [tint], 3));
        out.btnBg = css(solid.bg); out.btnFg = solid.fg;
      } else if (style === 'glass' && glassOk) {
        // Translucent: the page behind can be white or black — the text must stay readable on both.
        var a = 0.8, worst = function (c) { return [mix(c, WHITE, 1 - a), mix(c, BLACK, 1 - a)]; };
        var cand = [WHITE, INK].map(function (f) {
          var c = base.slice();
          for (var i = 0; i < 30 && worst(c).some(function (w) { return ratio(f, w) < 4.5; }); i++) { c = mix(c, f === WHITE ? BLACK : WHITE, 0.08); }
          return { f: f, c: c, d: Math.abs(luminance(c) - luminance(base)) };
        });
        var pick = cand[0].d <= cand[1].d ? cand[0] : cand[1];
        if (ownFg && !worst(base).some(function (w) { return ratio(ownFg, w) < 4.5; })) { pick = { f: ownFg, c: base }; }
        out.bg = rgba(pick.c, a); out.fg = css(pick.f); out.btnBg = css(pick.f); out.btnFg = css(pick.c); out.accent = css(pick.f); out.glass = true;
      }
      return out;
    }

    var theme = null; // theme colour (Automatic mode), read after load
    function paintItem(el) {
      var style = el.getAttribute('data-style') || 'solid', auto = bar.getAttribute('data-color') === 'auto';
      var base = auto && theme ? theme : hexRgb(el.getAttribute('data-c')), own = auto ? null : hexRgb(el.getAttribute('data-t'));
      if (!base || el.hasAttribute('data-img')) { return; } // a background image keeps its own colour overlay
      if (!auto && style === 'solid') { return; } // custom colours are not changed
      var k = skinOf(base, own, style), st = el.style;
      st.backgroundColor = k.bg; st.backgroundImage = k.image; st.color = k.fg;
      st.setProperty('--abp-btn-bg', k.btnBg); st.setProperty('--abp-btn-fg', k.btnFg); st.setProperty('--abp-accent', k.accent);
      el.abpGlass = k.glass;
    }
    function paintAll() { Array.prototype.forEach.call(allItems(), paintItem); var list = items(); paintBar(list[index] || list[0]); }

    // The theme's main colour: primary buttons, then the menu / navbar, then the link colour.
    function themeColour() {
      var probes = ['.btn-primary', '#menu', '.navbar-inverse', '#top-menu', '.main-menu', '.navbar', 'header nav', 'button[type="submit"]', '.btn', 'header'];
      for (var i = 0; i < probes.length; i++) {
        var els = document.querySelectorAll(probes[i]);
        for (var j = 0; j < els.length && j < 6; j++) {
          if (els[j].closest && els[j].closest('.abp')) { continue; }
          if (!els[j].getBoundingClientRect().width) { continue; }
          var rgb = parseRgb(window.getComputedStyle(els[j]).backgroundColor);
          if (rgb) { var l = luminance(rgb); if (l < 0.8 && l > 0.005) { return rgb; } }
        }
      }
      var links = document.querySelectorAll('a[href]');
      for (var k = 0; k < links.length && k < 40; k++) {
        if (links[k].closest && links[k].closest('.abp')) { continue; }
        var c = parseRgb(window.getComputedStyle(links[k]).color);
        if (c && luminance(c) < 0.5) { return c; }
      }
      return null;
    }

    function applyAutoColours() {
      if (bar.getAttribute('data-color') === 'auto') { theme = themeColour(); }
      paintAll();
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
      bar.style.backgroundImage = item.style.backgroundImage || '';
      bar.style.color = item.style.color;
      bar.classList.toggle('abp-glass', !!item.abpGlass);
      if (tab) { tab.style.backgroundColor = item.style.backgroundColor; tab.style.color = item.style.color; }
    }

    // Some themes put the whole page in an absolutely positioned wrapper (OpenCart 4.1: #container).
    // Then <body> is short, CSS "sticky" cannot work and body padding does not reach the page bottom.
    function wrappers() {
      var out = [], kids = document.body.children;
      for (var i = 0; i < kids.length; i++) {
        if (!/(^|\s)abp(-spacer|-tab)?(\s|$)/.test(String(kids[i].className)) && window.getComputedStyle(kids[i]).position === 'absolute' && kids[i].offsetHeight > window.innerHeight / 2) { out.push(kids[i]); }
      }
      return out;
    }

    function fitBody() {
      if (inline) { return; }
      var hide = hidden();
      if (bar.classList.contains('abp-pos-bottom')) {
        document.body.style.paddingBottom = hide ? '' : bar.offsetHeight + 'px';
        // With an absolutely positioned page wrapper, body padding does not extend the page: add an
        // invisible block below the wrapper instead, so the footer can scroll above the fixed bar.
        var ws = wrappers(), end = 0;
        ws.forEach(function (w) { end = Math.max(end, w.offsetTop + w.offsetHeight); });
        var sp = document.getElementById('abp-bottom-spacer');
        if (ws.length && !hide) {
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
        bar.classList.toggle('abp-pinned', broken && !hide);
        var spacer = bar.nextElementSibling && bar.nextElementSibling.className === 'abp-spacer' ? bar.nextElementSibling : null;
        if (broken && !hide) {
          if (!spacer) { spacer = document.createElement('div'); spacer.className = 'abp-spacer'; bar.parentNode.insertBefore(spacer, bar.nextSibling); }
          spacer.style.height = bar.offsetHeight + 'px';
        } else if (spacer) {
          spacer.parentNode.removeChild(spacer);
        }
      }
    }

    // Rotate mode: reserve the height of the tallest message so the page does not jump on every change.
    function stabilise() {
      var trackEl = bar.querySelector('.abp-track');
      if (!trackEl) { return; }
      trackEl.style.minHeight = '';
      if (mode !== 'rotate') { return; }
      var list = items(), maxH = 0, min = Infinity;
      for (var i = 0; i < list.length; i++) {
        var was = list[i].style.display;
        list[i].style.display = 'block';
        var h = list[i].offsetHeight;
        maxH = Math.max(maxH, h); min = Math.min(min, h);
        list[i].style.display = was;
      }
      // Reserve the tallest height only when messages are similar; one very long message must not
      // turn every short one into a huge bar (e.g. on phones).
      if (maxH && maxH <= min * 2.5) { trackEl.style.minHeight = maxH + 'px'; }
    }

    var navs = bar.querySelectorAll('.abp-nav');
    function show(i, back) {
      var list = items();
      if (!list.length) { return; }
      index = (i + list.length) % list.length;
      bar.classList.toggle('abp-back', !!back);
      var all = allItems();
      for (var n = 0; n < all.length; n++) {
        if (all[n] === list[index]) { all[n].classList.add('abp-active'); } else { all[n].classList.remove('abp-active'); }
      }
      for (var b = 0; b < navs.length; b++) { navs[b].hidden = mode !== 'rotate' || list.length < 2; }
      if (pauseBtn) { pauseBtn.hidden = !(mode === 'marquee' || (mode === 'rotate' && list.length > 1)); }
      paintBar(list[index]);
      fitBody();
      trackShown();
    }

    function stopRotate() { if (timer) { clearInterval(timer); timer = null; } }

    function startRotate() {
      stopRotate();
      if (paused || userPaused || mode !== 'rotate' || items().length < 2 || hidden()) { return; }
      var ms = Math.max(2, parseInt(bar.getAttribute('data-interval'), 10) || 5) * 1000;
      timer = setInterval(function () { if (!inDoc(bar)) { stopRotate(); return; } show(index + 1); }, ms);
    }

    function startMarquee() {
      if (mode !== 'marquee') { return; }
      var trackEl = bar.querySelector('.abp-track'), view = bar.querySelector('.abp-viewport');
      if (!trackEl || !view) { return; }
      trackEl.classList.remove('abp-run');
      var speed = Math.max(20, parseInt(bar.getAttribute('data-speed'), 10) || 60);
      var from = view.clientWidth, to = trackEl.scrollWidth;
      trackEl.style.setProperty('--abp-from', from + 'px');
      trackEl.style.setProperty('--abp-to', -to + 'px');
      trackEl.style.setProperty('--abp-duration', ((from + to) / speed).toFixed(2) + 's');
      void trackEl.offsetWidth; // restart animation
      trackEl.classList.add('abp-run');
    }

    function relayout() { rescan(); stabilise(); show(Math.min(index, Math.max(items().length - 1, 0))); startRotate(); startMarquee(); fitBody(); }

    function startTick() {
      if (!tickTimer && bar.querySelector('.abp-countdown, .abp-item[data-end], .abp-item[data-wd], .abp-item[data-t1]')) { tickTimer = setInterval(tick, 1000); }
    }

    function hideBar() {
      stopRotate();
      bar.style.display = 'none';
      fitBody();
      restoreEdges();
    }

    // Something changed which messages may be shown (screen size crossed 768 px, schedule, expiry, cart, trigger):
    // rebuild from the messages that fit now, hide the bar when none is left, show it again when one is.
    function refreshVisible() {
      if (closed) { return; }
      if (!items().length) {
        if (bar.style.display !== 'none') { deviceHidden = true; hideBar(); }
        return;
      }
      if (deviceHidden || bar.style.display === 'none') {
        deviceHidden = false;
        bar.style.display = '';
      }
      index = 0;
      relayout();
      armAutohide();
    }

    function pad(n) { return (n < 10 ? '0' : '') + n; }

    // Countdowns, weekly schedules and removal of messages whose end time passed while the page is open.
    // A daily / weekly cut-off (data-recur) starts again for the next cut-off instead.
    function tick() {
      if (!inDoc(bar)) { clearInterval(tickTimer); tickTimer = null; return; }
      var now = serverNow(), changed = applySchedule(), list = allItems();
      for (var i = list.length - 1; i >= 0; i--) {
        var end = parseInt(list[i].getAttribute('data-end'), 10);
        if (end && end <= now) { list[i].parentNode.removeChild(list[i]); changed = true; }
      }
      var nodes = bar.querySelectorAll('.abp-countdown');
      for (var c = 0; c < nodes.length; c++) {
        var cEnd = parseInt(nodes[c].getAttribute('data-end'), 10), left = Math.floor((cEnd - now) / 1000);
        if ((!cEnd || left <= 0) && nodes[c].getAttribute('data-recur')) {
          cEnd = nextCutoff(nodes[c].getAttribute('data-recur'), now);
          if (cEnd) { nodes[c].setAttribute('data-end', String(cEnd)); left = Math.floor((cEnd - now) / 1000); }
        }
        if (!cEnd || left <= 0) {
          var item = nodes[c].closest ? nodes[c].closest('.abp-item') : null;
          if (item && item.parentNode) { item.parentNode.removeChild(item); changed = true; }
          continue;
        }
        var d = Math.floor(left / 86400), h = Math.floor(left % 86400 / 3600), m = Math.floor(left % 3600 / 60), s = left % 60;
        nodes[c].textContent = (d > 0 ? d + (bar.getAttribute('data-days-label') || 'd') + ' ' : '') + pad(h) + ':' + pad(m) + ':' + pad(s);
      }
      if (changed) { refreshVisible(); }
      if (!bar.querySelector('.abp-countdown, .abp-item[data-end], .abp-item[data-wd], .abp-item[data-t1]') && tickTimer) { clearInterval(tickTimer); tickTimer = null; }
    }

    // Keyboard users: continue at the first focusable element after the bar, not at <body>.
    function focusAfter(focusWasInside) {
      if (!focusWasInside) { return; }
      var next = document.querySelectorAll('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
      for (var k2 = 0; k2 < next.length; k2++) {
        if (!bar.contains(next[k2]) && (bar.compareDocumentPosition(next[k2]) & 4) && next[k2].offsetParent !== null) { next[k2].focus(); break; }
      }
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
          var list = items(), keys = prev.slice(); // only what this screen size showed
          for (var j = 0; j < list.length; j++) { var k = list[j].getAttribute('data-key'); if (k && keys.indexOf(k) === -1) { keys.push(k); } }
          keep.setItem('abp_dismiss2', JSON.stringify({ k: keys.slice(-200), t: days > 0 ? now + days * 86400000 : 0 }));
          drop.removeItem('abp_dismiss2');
          keep.removeItem('abp_dismiss'); drop.removeItem('abp_dismiss'); // pre-2.0.3 format
        } catch (e) { /* storage blocked: bar just hides for this page view */ }
        var focusWasInside = bar.contains(document.activeElement);
        closed = true;
        if (tickTimer) { clearInterval(tickTimer); tickTimer = null; }
        clearTimeout(minTimer);
        if (tab) { tab.hidden = true; }
        hideBar();
        focusAfter(focusWasInside);
      });
    }

    // Previous / next message (rotate mode). Buttons: reachable with the keyboard, labelled for screen readers.
    Array.prototype.forEach.call(navs, function (btn) {
      btn.addEventListener('click', function () {
        var back = btn.classList.contains('abp-prev');
        show(index + (back ? -1 : 1), back);
        startRotate();
      });
    });

    // Coupon: copy the code (Clipboard API, fallback execCommand), visible + announced "Copied!".
    function copyText(text, done) {
      var fallback = function () {
        var ok = false;
        try {
          var ta = document.createElement('textarea');
          ta.value = text; ta.setAttribute('readonly', ''); ta.style.cssText = 'position:fixed;top:0;left:0;width:1px;height:1px;opacity:0;';
          document.body.appendChild(ta); ta.select(); ta.setSelectionRange(0, text.length);
          ok = document.execCommand('copy');
          document.body.removeChild(ta);
        } catch (e) { ok = false; }
        done(ok);
      };
      if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext !== false) {
        navigator.clipboard.writeText(text).then(function () { done(true); }, fallback);
      } else { fallback(); }
    }
    bar.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('.abp-copy') : null;
      if (!btn || !bar.contains(btn)) { return; }
      e.preventDefault();
      var code = btn.getAttribute('data-code') || '';
      if (!/^[A-Z0-9_-]{1,32}$/.test(code)) { return; }
      copyText(code, function (ok) {
        var label = btn.querySelector('.abp-copy-label'), live = btn.parentNode.querySelector('.abp-sr');
        var msg = ok ? (btn.getAttribute('data-copied') || 'Copied!') : code;
        if (ok) {
          btn.classList.add('abp-copied');
          if (label) { label.textContent = btn.getAttribute('data-copied') || 'Copied!'; }
          track(bar, btn.closest('.abp-item'), 'copy');
          clearTimeout(btn.abpT);
          btn.abpT = setTimeout(function () { btn.classList.remove('abp-copied'); if (label) { label.textContent = btn.getAttribute('data-label') || 'Copy'; } }, 2200);
        }
        if (live) { live.textContent = ''; setTimeout(function () { live.textContent = msg; }, 30); }
      });
    });

    // Pause on mouse hover / keyboard focus; a tap on touch screens toggles pause (no stuck mouseenter).
    function pointerIsMouse(e) { return !e.pointerType || e.pointerType === 'mouse'; }
    var touchTimer = null;
    function pause() { paused = true; bar.classList.add('abp-paused'); stopRotate(); }
    function resume() {
      if (touchTimer) { clearTimeout(touchTimer); touchTimer = null; }
      paused = false;
      if (userPaused) { return; } // the pause button wins until it is pressed again
      bar.classList.remove('abp-paused');
      startRotate();
    }
    // Touch: a tap pauses for at most 8 s; a tap anywhere outside the bar resumes at once.
    function touchPause() { pause(); if (touchTimer) { clearTimeout(touchTimer); } touchTimer = setTimeout(resume, 8000); }

    if (!inline) {
      if (window.PointerEvent) {
        bar.addEventListener('pointerenter', function (e) { if (pointerIsMouse(e)) { pause(); } });
        bar.addEventListener('pointerleave', function (e) { if (pointerIsMouse(e)) { resume(); } });
        bar.addEventListener('pointerup', function (e) {
          if (!pointerIsMouse(e) && !(e.target.closest && e.target.closest('a, button, input, label, form'))) { if (paused) { resume(); } else { touchPause(); } }
        });
        document.addEventListener('pointerdown', function (e) { if (paused && touchTimer && !bar.contains(e.target)) { resume(); } }, true);
      } else {
        bar.addEventListener('mouseenter', pause);
        bar.addEventListener('mouseleave', resume);
      }
      bar.addEventListener('focusin', pause);
      bar.addEventListener('focusout', function () { setTimeout(function () { if (!bar.contains(document.activeElement)) { resume(); } }, 0); });
    }

    /* ---------------- Pause / play button (WCAG 2.2.2: moving content can be stopped) ---------------- */
    var pauseBtn = bar.querySelector('.abp-pause');
    if (pauseBtn) {
      pauseBtn.addEventListener('click', function () {
        userPaused = !userPaused;
        var label = bar.getAttribute(userPaused ? 'data-l-play' : 'data-l-pause') || '';
        pauseBtn.setAttribute('aria-pressed', userPaused ? 'true' : 'false');
        if (label) { pauseBtn.setAttribute('aria-label', label); pauseBtn.title = label; }
        bar.classList.toggle('abp-user-paused', userPaused);
        if (userPaused) { pause(); } else { resume(); }
      });
    }

    /* ---------------- Auto-hide: fold into a small tab after N seconds (bars only) ----------------
       Not while the mouse / keyboard focus is on the bar; stays folded for the rest of the visit (sessionStorage
       "abp_min"); the tab (a real button, labelled, aria-expanded) opens it again. */
    var autohide = inline ? 0 : parseInt(bar.getAttribute('data-autohide'), 10) || 0, tab = null, minTimer = null, armed = false;
    function minList() { try { var v = JSON.parse(sessionStorage.getItem('abp_min') || '[]'); return Array.isArray(v) ? v : []; } catch (e) { return []; } }
    function saveMin(on) {
      try {
        var place = bar.getAttribute('data-place'), list = minList().filter(function (p) { return p !== place; });
        if (on) { list.push(place); }
        sessionStorage.setItem('abp_min', JSON.stringify(list));
      } catch (e) { /* storage blocked */ }
    }
    function makeTab() {
      if (tab || !autohide) { return; }
      tab = document.createElement('button');
      tab.type = 'button';
      tab.className = 'abp-tab abp-tab-' + (isTop ? 'top' : 'bottom') + (isTop && !bar.classList.contains('abp-sticky') ? '' : ' abp-tab-fixed');
      tab.setAttribute('aria-expanded', 'false');
      if (bar.id) { tab.setAttribute('aria-controls', bar.id); }
      tab.setAttribute('aria-label', bar.getAttribute('data-l-show') || 'Show');
      tab.title = bar.getAttribute('data-l-show') || '';
      tab.innerHTML = '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M2.5 6.2v3.6h2.2l4.6 3V3.2l-4.6 3H2.5zM11.4 5.4a3.6 3.6 0 0 1 0 5.2M12.9 3.8a5.8 5.8 0 0 1 0 8.4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
      tab.hidden = true;
      bar.parentNode.insertBefore(tab, bar.nextSibling);
      tab.addEventListener('click', expand);
    }
    function minimize() {
      if (closed || bar.style.display === 'none') { return; }
      var focusWasInside = bar.contains(document.activeElement);
      makeTab();
      bar.classList.add('abp-min');
      stopRotate();
      fitBody();
      restoreEdges();
      if (tab) { tab.hidden = false; paintBar(items()[index]); }
      saveMin(true);
      if (focusWasInside && tab) { tab.focus(); }
    }
    function expand() {
      bar.classList.remove('abp-min');
      if (tab) { tab.hidden = true; tab.setAttribute('aria-expanded', 'true'); }
      saveMin(false);
      armed = true; // opened by the visitor: not folded again on this page
      relayout();
      var first = bar.querySelector('.abp-item.abp-active a[href], .abp-item.abp-active button, .abp-item.abp-active input, .abp-close');
      if (first) { first.focus(); }
    }
    function armAutohide() {
      if (!autohide || armed || closed || hidden()) { return; }
      armed = true;
      var wait = function () { minTimer = setTimeout(function () { if (paused || userPaused) { wait(); } else { minimize(); } }, autohide * 1000); };
      wait();
    }
    if (autohide && bar.classList.contains('abp-min')) {
      // Folded earlier in this visit (the pre-script added abp-min before the first paint).
      makeTab();
      armed = true;
      tab.hidden = false;
    }

    /* ---------------- Triggers: after a delay, after scrolling, on exit intent ----------------
       Once fired, the message shows at once on the next pages of this visit (sessionStorage "abp_trig"). */
    function fired(el) {
      try {
        var list = JSON.parse(sessionStorage.getItem('abp_trig') || '[]');
        list = Array.isArray(list) ? list : [];
        if (list.indexOf(el.getAttribute('data-id')) === -1) { list.push(el.getAttribute('data-id')); }
        sessionStorage.setItem('abp_trig', JSON.stringify(list.slice(-100)));
      } catch (e) { /* storage blocked: only this page */ }
    }
    function appear(el) {
      if (closed || !el.hasAttribute('data-toff') || !inDoc(el)) { return; }
      var wasHidden = bar.style.display === 'none';
      el.removeAttribute('data-toff');
      fired(el);
      refreshVisible();
      var list = items(), i = list.indexOf(el);
      if (mode === 'rotate' && i > 0) { show(i); startRotate(); }
      if (wasHidden && !hidden() && !reduced) {
        bar.classList.add('abp-enter');
        setTimeout(function () { bar.classList.remove('abp-enter'); }, 700);
      }
    }
    Array.prototype.forEach.call(bar.querySelectorAll('.abp-item[data-trig][data-toff]'), function (el) {
      var t = String(el.getAttribute('data-trig')).split(':'), v = parseInt(t[1], 10) || 0;
      if (t[0] === 'delay') {
        setTimeout(function () { appear(el); }, Math.max(1, v) * 1000);
      } else if (t[0] === 'scroll') {
        var onScroll = function () {
          var room = document.documentElement.scrollHeight - window.innerHeight, pct = room > 0 ? (window.pageYOffset || document.documentElement.scrollTop) * 100 / room : 100;
          if (pct >= v) { window.removeEventListener('scroll', onScroll); appear(el); }
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        setTimeout(onScroll, 400);
      } else if (t[0] === 'exit' && window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        // Exit intent: the mouse leaves the window through the top edge (desktop only).
        var onOut = function (e) { if (!e.relatedTarget && e.clientY <= 0) { document.removeEventListener('mouseout', onOut); appear(el); } };
        setTimeout(function () { document.addEventListener('mouseout', onOut); }, 1500);
      }
    });

    var resizeTimer = null;
    window.addEventListener('resize', function () {
      if (!inDoc(bar)) { return; }
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(relayout, 200);
    });

    // While scrolling only the cached elements are updated; the page is re-scanned at most once a second
    // (headers that become fixed only after scrolling).
    if (!inline) {
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
    }

    // Clicks on the button, the whole-message link or a link inside the text (not the privacy link of a form).
    bar.addEventListener('click', function (e) {
      var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
      if (a && bar.contains(a) && !a.closest('.abp-form')) { track(bar, a.closest('.abp-item'), 'click'); }
    });

    // Crossing the 768 px breakpoint (rotation, resize): rebuild from the messages meant for this screen size.
    if (mq) {
      var onMq = function () { if (inDoc(bar)) { refreshVisible(); } };
      if (mq.addEventListener) { mq.addEventListener('change', onMq); } else if (mq.addListener) { mq.addListener(onMq); }
    }

    applySchedule();
    tick();
    if (!items().length) {
      deviceHidden = true;
      hideBar();
    } else {
      bar.style.display = '';
    }
    startTick();
    // Custom colours: paint the styles now (no wait); Automatic colours need the theme's final CSS (load).
    if (bar.getAttribute('data-color') !== 'auto') { applyAutoColours(); }
    relayout();
    armAutohide();

    var api = {
      bar: bar,
      refresh: refreshVisible,
      trackShown: trackShown,
      relayout: function () { checkIcons(); stabilise(); fitBody(); startTick(); if (mode === 'marquee') { startMarquee(); } },
      afterLoad: function () { checkIcons(); applyAutoColours(); relayout(); }
    };
    bar.abpApi = api;
    instances.push(api);
    if (loaded) { api.afterLoad(); }
  }

  /* ---------------- Free shipping, goals and cart conditions ----------------
     After OpenCart's own AJAX cart requests (add / remove / update, both versions) the amounts are fetched from a
     small read-only endpoint (same-origin GET, JSON) and every bar and box is updated in place. */
  var cartTimer = null, cartBusy = false, cartAgain = false;
  function cartItems() { return document.querySelectorAll('.abp .abp-item[data-fs="1"], .abp .abp-item[data-cc="1"]'); }
  function refreshCart() {
    var holder = document.querySelector('.abp[data-cart]'), url = holder ? holder.getAttribute('data-cart') : '';
    var ids = [];
    Array.prototype.forEach.call(cartItems(), function (el) { var id = el.getAttribute('data-id'); if (/^\d+$/.test(id) && ids.indexOf(id) === -1) { ids.push(id); } });
    if (cartBusy) { cartAgain = true; return; } // a change during a refresh: refresh once more afterwards
    if (!url || !ids.length || !window.fetch) { return; }
    cartBusy = true;
    window.fetch(url + (url.indexOf('?') === -1 ? '?' : '&') + 'ids=' + ids.slice(0, 20).join(','), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (json) {
        cartBusy = false;
        if (cartAgain) { cartAgain = false; queueCart(); }
        if (!json || typeof json.m !== 'object' || !json.m) { return; }
        var touched = [];
        Array.prototype.forEach.call(cartItems(), function (el) {
          var row = json.m[String(parseInt(el.getAttribute('data-id'), 10))], bar = el.closest('.abp');
          if (!row || typeof row !== 'object') {
            // The cart conditions no longer hold (the server left the message out).
            if (el.getAttribute('data-cc') === '1' && !el.hasAttribute('data-coff')) { el.setAttribute('data-coff', '1'); touched.push(bar); }
            return;
          }
          ['a', 'b'].forEach(function (v) {
            var txt = el.querySelector('.abp-v-' + v + ' .abp-txt');
            if (txt && typeof row[v] === 'string') { txt.innerHTML = row[v]; } // server-sanitized HTML
          });
          var fill = el.querySelector('.abp-progress-fill'), p = Math.max(0, Math.min(100, parseFloat(row.p) || 0));
          if (fill) { fill.style.width = p + '%'; }
          var prog = el.querySelector('.abp-progress');
          if (prog) {
            prog.setAttribute('data-p', String(Math.round(p)));
            var k = parseInt(row.k, 10) || 0;
            Array.prototype.forEach.call(prog.querySelectorAll('.abp-step'), function (s, i) { s.classList.toggle('abp-step-done', i < k); });
            if (prog.hasAttribute('data-k')) { prog.setAttribute('data-k', String(k)); }
          }
          if (row.done) { el.setAttribute('data-done', '1'); } else { el.removeAttribute('data-done'); }
          if (el.getAttribute('data-cc') === '1' && (!!row.off) !== el.hasAttribute('data-coff')) {
            if (row.off) { el.setAttribute('data-coff', '1'); } else { el.removeAttribute('data-coff'); }
            touched.push(bar);
          }
          if (touched.indexOf(bar) === -1) { touched.push(bar); }
        });
        touched.forEach(function (bar) {
          if (bar && bar.abpApi) { if (bar.style.display === 'none' || bar.querySelector('.abp-item[data-coff], .abp-item[data-cc]')) { bar.abpApi.refresh(); } bar.abpApi.relayout(); }
        });
      })
      .catch(function () { cartBusy = false; if (cartAgain) { cartAgain = false; queueCart(); } });
  }
  function queueCart() { clearTimeout(cartTimer); cartTimer = setTimeout(refreshCart, 300); }

  /* ---------------- E-mail sign-up (newsletter, double opt-in) ----------------
     Checked here first (address, consent box), then sent with the page's form token; the answer replaces the form. */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.classList || !form.classList.contains('abp-form')) { return; }
    e.preventDefault();
    var email = form.querySelector('.abp-email'), consent = form.querySelector('input[name="consent"]'), msg = form.querySelector('.abp-form-msg');
    var btn = form.querySelector('.abp-submit'), item = form.closest('.abp-item'), bar = form.closest('.abp');
    var say = function (text, field) {
      form.classList.toggle('abp-form-error', !!field);
      if (email) { email.setAttribute('aria-invalid', field === 'email' ? 'true' : 'false'); }
      if (consent) { consent.setAttribute('aria-invalid', field === 'consent' ? 'true' : 'false'); }
      if (msg) { msg.textContent = ''; setTimeout(function () { msg.textContent = text || ''; }, 30); }
      if (field === 'email' && email) { email.focus(); } else if (field === 'consent' && consent) { consent.focus(); }
    };
    var value = email ? email.value.trim() : '';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value) || value.length > 96) { say(form.getAttribute('data-err-email'), 'email'); return; }
    if (!consent || !consent.checked) { say(form.getAttribute('data-err-consent'), 'consent'); return; }
    if (form.abpBusy || !window.fetch || !window.FormData) { return; }
    form.abpBusy = true;
    if (btn) { btn.disabled = true; }
    say(form.getAttribute('data-sending'), '');
    var fd = new FormData(form);
    fd.set('email', value);
    fd.append('tok', form.getAttribute('data-tok') || '');
    fd.append('id', item ? item.getAttribute('data-id') : '');
    window.fetch(form.getAttribute('action'), { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        form.abpBusy = false;
        if (btn) { btn.disabled = false; }
        if (j && j.ok) {
          var done = document.createElement('span');
          done.className = 'abp-form-done';
          done.setAttribute('role', 'status');
          done.tabIndex = -1;
          done.textContent = j.msg || form.getAttribute('data-success') || '';
          form.parentNode.replaceChild(done, form);
          done.focus();
          ga(bar, item, 'abp_subscribe');
          if (bar && bar.abpApi) { bar.abpApi.relayout(); }
        } else {
          say((j && j.error) || form.getAttribute('data-err-failed'), (j && j.field) || 'form');
        }
      })
      .catch(function () {
        form.abpBusy = false;
        if (btn) { btn.disabled = false; }
        say(form.getAttribute('data-err-failed'), 'form');
      });
  }, true);

  /* ---------------- Start ---------------- */
  var loaded = false; // set by afterLoad(); boxes started later (mini-cart reload) run their load step at once
  function scan() {
    try { if (window.abpPre) { window.abpPre(); } } catch (e) { /* boxes still start without it */ }
    Array.prototype.forEach.call(document.querySelectorAll('.abp[data-abp], #abp-bar'), function (bar) {
      try { init(bar); } catch (e) { /* one broken box must not stop the others */ }
    });
  }
  var hooked = false;
  function hookAjax() {
    if (hooked || !window.jQuery) { return; }
    hooked = true;
    window.jQuery(document).ajaxComplete(function (e, xhr, settings) {
      var url = settings && typeof settings.url === 'string' ? settings.url : '';
      if (/route=(checkout|common)(\/|%2F)cart|(checkout|common)\/cart[\/.|]/i.test(url)) {
        queueCart();
        setTimeout(scan, 60); // the mini-cart drop-down is reloaded with a new box
      }
    });
  }

  /* "Wait for cookie consent": the shop's consent tool sets window.abpConsent = true and fires "abp:consent" (on
     document or window). Then A/B variants and visitor rules are decided, and the messages on screen are counted.
     Without the event the value is checked again at load and on every statistics call. */
  var consentSeen = false;
  function consentGiven() {
    if (consentSeen || window.abpConsent !== true || !document.querySelector('.abp[data-consent="1"]')) { return; }
    consentSeen = true;
    try { if (window.abpPre && window.abpPre.consent) { window.abpPre.consent(); } } catch (e) { /* bars keep working */ }
    instances.forEach(function (api) {
      try { if (inDoc(api.bar)) { api.refresh(); api.trackShown(); } } catch (e2) { /* one broken box must not stop the others */ }
    });
  }
  document.addEventListener('abp:consent', consentGiven);
  window.addEventListener('abp:consent', consentGiven);

  window.AnnouncementBarPro = { scan: scan, refreshCart: queueCart, consent: consentGiven };

  function start() {
    scan();
    hookAjax();
  }
  // Web fonts, icons and theme colours are final only after load.
  function afterLoad() { loaded = true; hookAjax(); instances.forEach(function (api) { if (inDoc(api.bar)) { api.afterLoad(); } }); consentGiven(); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
  if (document.readyState === 'complete') { afterLoad(); } else { window.addEventListener('load', afterLoad); }
  // Back/forward cache: the cart may have changed on another page.
  window.addEventListener('pageshow', function (e) { if (e.persisted) { queueCart(); } });
})();
