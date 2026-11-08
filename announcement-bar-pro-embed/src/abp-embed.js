/* Announcement Bar Pro Embed — builds the bars from a JSON config with DOM methods (createElement, textContent,
   CSSOM styles: no innerHTML of config text, no eval, no inline style attributes), then hands them to the shared
   storefront scripts (window.abpPre + window.AnnouncementBarPro), the same files as the OpenCart and WordPress editions.
   The only innerHTML is the fixed icon markup below (constants, like the storefront script's own tab icon).
   No server of ours: the only requests are this script's own stylesheet and, when data-config is a URL, that JSON file. */
(function () {
  'use strict';

  if (window.ABP && window.ABP.version) { return; } // included twice: keep the first
  var VERSION = '1.0.0', C = ABPCore, doc = document;
  var me = doc.currentScript;
  var opt = function (k) { return me ? me.getAttribute('data-' + k) : null; };
  var nonce = (me && me.nonce) || opt('nonce') || '';
  var cfg = null, ctx = null, cart = null, bars = [];
  function warn(t) { try { if (window.console) { console.warn('Announcement Bar Pro: ' + t); } } catch (e) { /* no console */ } }

  /* ---------------- Small DOM helpers ---------------- */
  function el(tag, cls, attrs, kids) {
    var e = doc.createElement(tag);
    if (cls) { e.className = cls; }
    for (var k in attrs || {}) { if (attrs[k] !== null && attrs[k] !== undefined && attrs[k] !== false) { e.setAttribute(k, attrs[k] === true ? '1' : String(attrs[k])); } }
    (kids || []).forEach(function (c) { if (c !== null && c !== undefined && c !== '') { e.appendChild(typeof c === 'string' ? doc.createTextNode(c) : c); } });
    return e;
  }
  // Icons: fixed markup (the same SVG as the OpenCart / WordPress template), never built from config text.
  var SV = '<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"', ST = ' fill="none" stroke="currentColor" stroke-width="';
  function svg(cls, inner) { return SV + (cls ? ' class="' + cls + '"' : '') + '>' + inner + '</svg>'; }
  function line(d, w) { return '<path d="' + d + '"' + ST + (w || '1.8') + '" stroke-linecap="round" stroke-linejoin="round"/>'; }
  function icon(e, html) { e.innerHTML = html; return e; }
  // Node tree of the allow-list (core.parseHtml) → DOM. Text is always a text node.
  function build(nodes, parent, inLink) {
    nodes.forEach(function (n) {
      if (typeof n === 'string') { parent.appendChild(doc.createTextNode(n)); return; }
      if (n.t === 'cd') { parent.appendChild(el('span', 'abp-countdown', { 'data-end': n.end || null, 'data-recur': n.recur || null })); return; }
      if (n.t === 'rem' || n.t === 'goal') { parent.appendChild(el('span', 'abp-' + (n.t === 'rem' ? 'remaining' : 'goal'), null, [n.v])); return; }
      var a = n.t === 'a', u = a && !inLink && C.url(n.href); // checked again: the tree may come from anywhere
      if (!C.ALLOWED[n.t] || (a && !u)) { build(n.c, parent, inLink); return; } // unwrapped: its text stays
      var e = el(n.t);
      if (u) { e.setAttribute('href', u); if (n.blank) { e.setAttribute('target', '_blank'); e.setAttribute('rel', 'noopener noreferrer'); } }
      build(n.c, e, inLink || a);
      parent.appendChild(e);
    });
  }
  function textOf(v) {
    var t = el('span', 'abp-txt');
    if (v.m.length) {
      var d = el('span', 'abp-text abp-text-d'), m = el('span', 'abp-text abp-text-m');
      build(v.d, d); build(v.m, m); t.appendChild(d); t.appendChild(m);
    } else { var x = el('span', 'abp-text'); build(v.d, x); t.appendChild(x); }
    return t;
  }
  // Visitor-facing labels: settings.labels[lang][key], else English (only ever used as text / attribute values).
  function label(k) { var l = cfg.settings.labels, v = (l[ctx.lang] || {})[k] || (l[cfg.settings.default_language] || {})[k]; return typeof v === 'string' && v.trim() ? v.slice(0, 80) : C.LABELS[k]; }

  /* ---------------- One message (same markup as the OpenCart / WordPress template) ---------------- */
  function item(it, first, s) {
    var m = it.m, vis = m.visitor !== 'all' && m.visitor, e = el('div', 'abp-item' + (first ? ' abp-active' : ''), {
      'data-id': m.id, 'data-key': it.key, 'data-style': m.style, 'data-c': m.bg_color, 'data-t': m.text_color,
      'data-device': m.device !== 'all' && m.device, 'data-end': it.end || null,
      'data-fs': it.fs, 'data-done': it.fs && it.goal && it.goal.done, 'data-wd': m.weekdays || null,
      'data-t1': m.time_start || null, 'data-t2': m.time_end || null, 'data-font': m.font || null, 'data-img': !!m.image,
      'data-utm': it.utm || null, 'data-vis': vis, 'data-uoff': !!(it.utm || vis), 'data-trig': it.trig || null, 'data-toff': !!it.trig,
      'data-coff': it.fs && !it.goal // free shipping waits for the cart (ABP.setCart)
    });
    var st = e.style;
    st.backgroundColor = m.bg_color; st.color = m.text_color; st.paddingTop = st.paddingBottom = s.padding + 'px';
    st.setProperty('--abp-btn-bg', m.text_color); st.setProperty('--abp-btn-fg', m.bg_color); st.setProperty('--abp-accent', m.text_color);
    if (m.image) {
      var h = m.bg_color, tint = 'rgba(' + [1, 3, 5].map(function (i) { return parseInt(h.substr(i, 2), 16); }).join(',') + ',' + (m.overlay / 100).toFixed(2) + ')';
      st.backgroundImage = 'linear-gradient(' + tint + ',' + tint + '),url("' + m.image + '")'; // URL checked: no quotes, parentheses kept inside quotes
      st.backgroundSize = 'cover'; st.backgroundPosition = 'center';
    }
    var inner = el('div', 'abp-inner');
    [it.v].forEach(function (v) {
      var lk = { href: m.link, target: m.new_tab && '_blank', rel: m.new_tab && 'noopener noreferrer' }, body = v.wrap ? el('a', 'abp-link abp-body', lk) : el('span', 'abp-body');
      if (m.icon) { body.appendChild(el('span', 'abp-ico', { 'aria-hidden': 'true' }, [m.icon])); body.appendChild(doc.createTextNode(' ')); }
      body.appendChild(textOf(v));
      var wrap = el('span', 'abp-v abp-v-a', null, [body]);
      if (m.link && v.link_text) { wrap.appendChild(el('a', 'abp-btn abp-btn-' + m.button_style, lk, [v.link_text])); }
      inner.appendChild(wrap);
    });
    if (m.coupon) {
      inner.appendChild(el('span', 'abp-coupon', null, [
        el('span', 'abp-code', null, [m.coupon]),
        (function () {
          var c = icon(el('button', 'abp-copy', { type: 'button', 'data-code': m.coupon, 'data-label': label('copy'), 'data-copied': label('copied'), 'aria-label': label('copy_code').replace('%s', m.coupon) }),
            svg('abp-cp', '<rect x="5.2" y="5.2" width="8.6" height="8.6" rx="1.8"' + ST + '1.5"/>' + line('M10.6 3.1V3A1.5 1.5 0 0 0 9.1 1.5H3A1.5 1.5 0 0 0 1.5 3v6.1a1.5 1.5 0 0 0 1.5 1.5h.1', '1.5')) + svg('abp-ok', line('M3 8.6l3.3 3.1L13 4.6', '1.9')));
          c.appendChild(el('span', 'abp-copy-label', null, [label('copy')])); // label text: a text node
          return c;
        })(),
        el('span', 'abp-sr', { role: 'status', 'aria-live': 'polite' })
      ]));
    }
    e.appendChild(inner);
    if (it.fs && m.progress_bar) {
      var fill = el('span', 'abp-progress-fill');
      fill.style.width = (it.goal ? it.goal.pct : 0) + '%';
      e.appendChild(el('span', 'abp-progress', { 'aria-hidden': 'true', 'data-p': it.goal ? it.goal.pct : 0 }, [fill]));
    }
    return e;
  }

  /* ---------------- One bar ---------------- */
  function bar(place, items, id) {
    var s = cfg.settings, mode = s.mode, n = items.length;
    var arrows = mode === 'rotate' && n > 1 && s.arrows, pause = s.pause_button && ((mode === 'rotate' && n > 1) || mode === 'marquee');
    var b = el('div', 'abp abp-bar abp-pos-' + place + (place === 'top' && s.sticky ? ' abp-sticky' : '') + ' abp-mode-' + mode + ' abp-tr-' + s.transition +
      (s.dismissible ? ' abp-has-close' : '') + (arrows ? ' abp-has-nav' : '') + (pause ? ' abp-has-pause' : '') + (s.font ? ' abp-font-' + s.font : ''), {
      id: id, 'data-abp': 'bar', 'data-place': place, role: 'region', 'aria-label': label('label'), 'data-days': s.dismiss_days, 'data-interval': s.interval,
      'data-speed': s.speed, 'data-mode': mode, 'data-tz': ctx.tzSec, 'data-color': s.color_mode, 'data-dismissible': s.dismissible,
      'data-days-label': label('days'), 'data-max': s.max_messages || null, 'data-autohide': s.autohide || null,
      'data-l-show': s.autohide ? label('show') : null, 'data-l-pause': pause ? label('pause') : null, 'data-l-play': pause ? label('play') : null,
      'data-ga': s.ga4, 'data-consent': s.consent_wait
    });
    b.style.fontSize = s.font_size + 'px'; b.style.backgroundColor = items[0].m.bg_color; b.style.color = items[0].m.text_color;
    var track = el('div', 'abp-track');
    items.forEach(function (it, i) { track.appendChild(item(it, i === 0, s)); });
    b.appendChild(el('div', 'abp-viewport', null, [track]));
    var btn = function (cls, lab, html) { b.appendChild(icon(el('button', cls, { type: 'button', 'aria-label': lab, title: lab, 'aria-pressed': cls === 'abp-pause' ? 'false' : null }), html)); };
    if (pause) { btn('abp-pause', label('pause'), svg('abp-i-pause', line('M5.5 3.5v9m5-9v9', '2')) + svg('abp-i-play', '<path d="M5 3.2v9.6L12.6 8z" fill="currentColor"/>')); }
    if (arrows) { btn('abp-nav abp-prev', label('prev'), svg('', line('M10 3 5 8l5 5'))); btn('abp-nav abp-next', label('next'), svg('', line('m6 3 5 5-5 5'))); }
    if (s.dismissible) { btn('abp-close', label('close'), svg('', line('M3.5 3.5l9 9m0-9-9 9'))); }
    return b;
  }

  /* ---------------- Render ---------------- */
  function context() {
    var s = cfg.settings, now = Date.now(), loc = window.location;
    var tz = s.timezone || (window.Intl && Intl.DateTimeFormat().resolvedOptions().timeZone) || 'UTC';
    s.timezone = tz;
    return { now: now, tzSec: C.tzOffset(tz, now), cart: cart, loc: loc,
      lang: C.pageLang(doc.documentElement.getAttribute('lang'), loc, s.languages, s.default_language) };
  }
  function render() {
    ctx = context();
    var groups = C.select(cfg, ctx), list = [];
    ['top', 'bottom'].forEach(function (place) {
      if (!groups[place].length) { return; }
      var b = bar(place, groups[place], list.length ? 'abp-bar-bottom' : 'abp-bar'); // ids as in the other editions
      if (place === 'top') { doc.body.insertBefore(b, doc.body.firstChild); } else { doc.body.appendChild(b); }
      list.push(b);
    });
    bars = list;
    if (cfg.settings.custom_css) { addCss(cfg.settings.custom_css); }
    try { if (window.AnnouncementBarPro) { window.AnnouncementBarPro.scan(); } } catch (e) { warn(e.message); }
  }
  // Custom CSS (already filtered): with the script's nonce when the page has a Content-Security-Policy, else a
  // constructed stylesheet (needs no 'unsafe-inline'), else a <style> element.
  function addCss(text) {
    if (!nonce && doc.adoptedStyleSheets && window.CSSStyleSheet) {
      try { var sh = new CSSStyleSheet(); sh.replaceSync(text); doc.adoptedStyleSheets = doc.adoptedStyleSheets.concat([sh]); return; } catch (e) { /* fall back */ }
    }
    var st = el('style', '', { nonce: nonce || null });
    st.textContent = text;
    (doc.head || doc.documentElement).appendChild(st);
  }

  /* ---------------- Cart adapter: free-shipping progress ----------------
     The page tells the bar what is in the cart: ABP.setCart({subtotal: 42.5, currency: 'EUR', count: 2}). Nothing is
     fetched by this script. Until the first call, free-shipping messages stay hidden. */
  function setCart(c) {
    if (!c || typeof c !== 'object' || isNaN(+c.subtotal)) { warn('setCart: {subtotal, currency, count}'); return; }
    cart = { subtotal: Math.max(0, +c.subtotal), currency: /^[A-Za-z]{3}$/.test(c.currency) ? c.currency.toUpperCase() : '' }; // '' = the config's currency
    if (!cfg || !ctx) { return; }
    ctx.cart = cart; ctx.now = Date.now();
    var groups = C.select(cfg, ctx);
    bars.forEach(function (b) {
      if (!doc.documentElement.contains(b)) { return; }
      var fresh = {};
      (groups[b.getAttribute('data-place')] || []).forEach(function (it) { fresh[it.id] = it; });
      Array.prototype.forEach.call(b.querySelectorAll('.abp-item[data-fs="1"]'), function (e) {
        var it = fresh[e.getAttribute('data-id')], g = it && it.goal, t = e.querySelector('.abp-txt'), fill = e.querySelector('.abp-progress-fill');
        if (!g) { e.setAttribute('data-coff', '1'); return; } // no amount for this currency
        e.removeAttribute('data-coff');
        if (t) { t.parentNode.replaceChild(textOf(it.v), t); }
        if (g.done) { e.setAttribute('data-done', '1'); } else { e.removeAttribute('data-done'); }
        if (fill) { fill.style.width = g.pct + '%'; fill.parentNode.setAttribute('data-p', String(g.pct)); }
      });
      if (b.abpApi) { b.abpApi.refresh(); b.abpApi.relayout(); }
    });
  }

  /* ---------------- Start ---------------- */
  function loadCss(done) {
    var href = opt('css');
    if (href === 'none') { done(); return; }
    href = href ? C.url(href) : me && me.src ? me.src.replace(/\.js(\?|#|$)/, '.css$1') : '';
    if (!href) { done(); return; }
    var ok = false, fin = function () { if (!ok) { ok = true; done(); } };
    var l = el('link', '', { rel: 'stylesheet', href: href, nonce: nonce || null });
    l.onload = fin; l.onerror = function () { warn('no CSS ' + href); fin(); };
    setTimeout(fin, 3000);
    (doc.head || doc.documentElement).appendChild(l);
  }
  function loadConfig(done) {
    var src = opt('config') || '#abp-config';
    if (window.abpConfig && typeof window.abpConfig === 'object' && !opt('config')) { done(window.abpConfig); return; }
    if (src.charAt(0) === '#') {
      var tag = doc.getElementById(src.slice(1));
      if (!tag) { warn('no config ' + src); return; }
      try { done(JSON.parse(tag.textContent)); } catch (e) { warn('bad JSON'); }
      return;
    }
    var u = C.url(src);
    if (!u || !window.fetch) { warn('bad config URL'); return; }
    window.fetch(u, { credentials: 'omit', headers: { Accept: 'application/json' } })
      .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
      .then(done, function (e) { warn('config: ' + e.message); });
  }
  function start() {
    var pending = 2, json = null;
    var go = function () {
      if (--pending) { return; }
      try { cfg = C.normalize(json); if (cfg.messages.length) { render(); } } catch (e) { warn(e.message); }
      if (window.abpCart) { setCart(window.abpCart); }
    };
    loadConfig(function (j) { json = j; go(); });
    loadCss(go);
  }

  window.ABP = {
    version: VERSION,
    setCart: setCart
  };
  if (doc.readyState === 'loading') { doc.addEventListener('DOMContentLoaded', start); } else { start(); }
})();
