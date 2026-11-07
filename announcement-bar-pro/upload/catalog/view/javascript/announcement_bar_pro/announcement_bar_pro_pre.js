/* Announcement Bar Pro v2.4.0 — runs synchronously right after the bars (no inline script, CSP-friendly).
   Before the first paint it: keeps a single bar per id, moves a top bar to the top of <body> when the theme made it
   render in the footer, hides it while automatic colours / styles are worked out, removes messages the visitor
   closed, gives every A/B-tested message this browser's variant, decides campaign / referrer / new-or-returning rules
   and triggers that already fired in this visit, and starts with a message meant for this screen size (data-device,
   breakpoint 768 px) that is inside its weekly schedule (data-off). Exposed as window.abpPre() for boxes that the
   page adds later (the main script calls it). */
(function () {
  'use strict';

  // Do Not Track, Global Privacy Control, or the shop's consent tool set window.abpConsent = false before this script.
  // "Wait for cookie consent" (setting; data-consent="1" on the bars): until the shop's consent tool sets
  // window.abpConsent = true, nothing is stored, read or sent (A/B choice, visitor memory, campaign, statistics, GA4).
  function waitConsent() { return window.abpConsent !== true && !!document.querySelector('.abp[data-consent="1"]'); }
  function privacyOff() {
    var d = navigator.doNotTrack || window.doNotTrack || navigator.msDoNotTrack;
    return d === '1' || d === 'yes' || navigator.globalPrivacyControl === true || window.abpConsent === false || waitConsent();
  }
  function storage(name) { try { var s = window[name]; s.getItem('abp'); return s; } catch (e) { return null; } }
  function readList(s, key) { try { var v = JSON.parse((s && s.getItem(key)) || '[]'); return Array.isArray(v) ? v : []; } catch (e) { return []; } }
  function host(h) { return String(h || '').toLowerCase().replace(/^www\./, ''); }

  /* Campaign of this visit: UTM values of the landing URL and the external referrer, kept in sessionStorage for
     the rest of the visit (this tab only; nothing is stored with DNT / GPC / no consent, then only the URL counts). */
  var campaign = null;
  function getCampaign() {
    if (campaign) { return campaign; }
    var q = {}, ref = '', ss = privacyOff() ? null : storage('sessionStorage'), saved = null;
    String(location.search || '').replace(/^\?/, '').split('&').forEach(function (p) {
      var i = p.indexOf('=');
      if (i > 0) { try { q[decodeURIComponent(p.slice(0, i)).toLowerCase()] = decodeURIComponent(p.slice(i + 1).replace(/\+/g, ' ')).toLowerCase().trim(); } catch (e) { /* bad encoding */ } }
    });
    try { ref = document.referrer ? host(new URL(document.referrer).hostname) : ''; } catch (e2) { ref = ''; }
    if (ref === host(location.hostname)) { ref = ''; }
    if (q.utm_source || q.utm_medium || q.utm_campaign || ref) {
      campaign = { s: q.utm_source || '', m: q.utm_medium || '', c: q.utm_campaign || '', r: ref };
      try { if (ss) { ss.setItem('abp_utm', JSON.stringify(campaign)); } } catch (e3) { /* storage full / blocked */ }
    } else {
      try { saved = ss ? JSON.parse(ss.getItem('abp_utm') || 'null') : null; } catch (e4) { saved = null; }
      campaign = saved && typeof saved === 'object' ? { s: String(saved.s || ''), m: String(saved.m || ''), c: String(saved.c || ''), r: String(saved.r || '') } : { s: '', m: '', c: '', r: '' };
    }
    return campaign;
  }
  function campaignOk(rules) {
    var c = getCampaign();
    var has = function (list, v) { return !Array.isArray(list) || !list.length || (v !== '' && list.indexOf(v) !== -1); };
    var from = function (list, h) {
      if (!Array.isArray(list) || !list.length) { return true; }
      for (var i = 0; i < list.length; i++) { if (h !== '' && (h === list[i] || h.slice(-list[i].length - 1) === '.' + list[i])) { return true; } }
      return false;
    };
    return has(rules.s, c.s) && has(rules.m, c.m) && has(rules.c, c.c) && from(rules.r, c.r);
  }

  /* New or returning visitor: one first-party localStorage value (time of the first visit), "new" for 30 minutes.
     Unknown ('') with DNT / GPC / no consent or without storage — nothing is read or stored then. */
  var visitor = null;
  function getVisitor() {
    if (visitor !== null) { return visitor; }
    visitor = '';
    var ls = privacyOff() ? null : storage('localStorage');
    if (!ls) { return visitor; }
    try {
      var now = Date.now(), first = parseInt(ls.getItem('abp_v'), 10);
      if (!first || first > now) { first = now; ls.setItem('abp_v', String(now)); }
      visitor = now - first < 1800000 ? 'new' : 'returning';
    } catch (e) { visitor = ''; }
    return visitor;
  }

  function prep(bar) {
    var items = bar.querySelectorAll('.abp-item'), i, isBar = bar.getAttribute('data-abp') !== 'inline';

    // Footer fallback (theme header without <body>): a top bar still belongs at the top of the page.
    if (isBar && bar.getAttribute('data-fallback') === '1' && !/\babp-pos-bottom\b/.test(bar.className) && document.body) {
      document.body.insertBefore(bar, document.body.firstChild);
    }

    // Automatic colours and the derived styles are painted by the main script; hide until then (CSS reveals
    // the bar after 2 s anyway, so a blocked script can never hide it for good).
    var styled = false;
    for (i = 0; i < items.length; i++) { if ((items[i].getAttribute('data-style') || 'solid') !== 'solid' && !items[i].hasAttribute('data-img')) { styled = true; } }
    if (bar.getAttribute('data-color') === 'auto' || styled) { bar.className += ' abp-auto-pending'; }

    rules(bar, items);
    prepRest(bar, items, isBar);
  }

  /* A/B variant and campaign / referrer / visitor rules of a bar's messages; run again by abpPre.consent() when
     the visitor accepts cookies later on this page ("Wait for cookie consent"). */
  function rules(bar, items) {
    var i;
    // A/B test: a stable random variant per browser (localStorage "abp_ab"); without storage everyone sees A.
    var ab = null, off = privacyOff();
    if (!off) { try { ab = JSON.parse(window.localStorage.getItem('abp_ab') || '{}'); if (!ab || typeof ab !== 'object') { ab = {}; } } catch (e0) { ab = null; } }
    var abChanged = false;
    for (i = 0; i < items.length; i++) {
      if (items[i].getAttribute('data-ab') !== '1') { continue; }
      var id = items[i].getAttribute('data-id'), v = 'a';
      if (ab) {
        v = ab[id] === 'a' || ab[id] === 'b' ? ab[id] : (Math.random() < 0.5 ? 'a' : 'b');
        if (ab[id] !== v) { ab[id] = v; abChanged = true; }
      }
      items[i].setAttribute('data-variant', v);
    }
    if (ab && abChanged) {
      try { window.localStorage.setItem('abp_ab', JSON.stringify(ab)); } catch (e1) {
        // Cannot remember the choice: show A, so the visitor does not flip between variants.
        for (i = 0; i < items.length; i++) { if (items[i].getAttribute('data-ab') === '1') { items[i].setAttribute('data-variant', 'a'); } }
      }
    }

    // Campaign / referrer / visitor rules and triggers that already fired in this visit.
    var fired = readList(storage('sessionStorage'), 'abp_trig');
    for (i = 0; i < items.length; i++) {
      var el = items[i];
      if (el.hasAttribute('data-utm') || el.hasAttribute('data-vis')) {
        var ok = true, rule = null;
        if (el.hasAttribute('data-utm')) { try { rule = JSON.parse(el.getAttribute('data-utm')); } catch (e5) { rule = null; } ok = !!rule && campaignOk(rule); }
        if (ok && el.hasAttribute('data-vis')) { ok = getVisitor() === el.getAttribute('data-vis'); }
        if (ok) { el.removeAttribute('data-uoff'); } else { el.setAttribute('data-uoff', '1'); }
      }
      if (el.hasAttribute('data-trig') && fired.indexOf(el.getAttribute('data-id')) !== -1) { el.removeAttribute('data-toff'); }
    }
  }

  function prepRest(bar, items, isBar) {
    var i, now = Date.now();
    var read = function (name) {
      try {
        var v = JSON.parse(window[name].getItem('abp_dismiss2') || 'null');
        if (!v || !Array.isArray(v.k) || (v.t && v.t <= now)) { return []; }
        return v.k.filter(function (k) { return typeof k === 'string' && /^[0-9a-f]{12}$/.test(k); });
      } catch (e) { return []; }
    };
    var keys = bar.getAttribute('data-dismissible') === '1' ? read('localStorage').concat(read('sessionStorage')) : [];
    var mobile = window.matchMedia ? window.matchMedia('(max-width: 767px)').matches : window.innerWidth < 768;
    var fits = function (e) { var d = e.getAttribute('data-device'); return !e.hasAttribute('data-off') && !e.hasAttribute('data-coff') && !e.hasAttribute('data-uoff') && !e.hasAttribute('data-toff') && (!d || d === 'all' || (d === 'mobile') === mobile); };
    var max = parseInt(bar.getAttribute('data-max'), 10) || 0;

    var left = 0, first = null, shown = 0;
    for (i = 0; i < items.length; i++) {
      if (keys.length && keys.indexOf(items[i].getAttribute('data-key')) !== -1) {
        items[i].parentNode.removeChild(items[i]);
        continue;
      }
      left++;
      items[i].removeAttribute('data-over');
      if (fits(items[i])) {
        // "Messages per bar": the rest waits (highest priority first, as the server ordered them).
        if (max && shown >= max) { items[i].setAttribute('data-over', '1'); continue; }
        shown++;
        if (!first) { first = items[i]; }
      }
    }
    if (!left) {
      bar.style.display = 'none';
      bar.setAttribute('data-dismissed', '1');
      return;
    }
    // Folded by "hide after N seconds" earlier in this visit: start folded (the main script shows the tab).
    if (isBar && bar.hasAttribute('data-autohide') && readList(storage('sessionStorage'), 'abp_min').indexOf(bar.getAttribute('data-place')) !== -1) {
      bar.className += ' abp-min';
    }
    // Nothing for this screen size / time / visitor yet: stay hidden (the main script shows the bar when that changes).
    if (!first) { bar.style.display = 'none'; return; }
    var active = bar.querySelector('.abp-item.abp-active');
    if (!active || !fits(active) || active.hasAttribute('data-over')) {
      if (active) { active.className = active.className.replace(/(^|\s)abp-active(?=\s|$)/g, ' '); }
      first.className += ' abp-active';
      active = first;
    }
    bar.style.backgroundColor = active.style.backgroundColor;
    bar.style.color = active.style.color;
  }

  function run() {
    try {
      var list = document.querySelectorAll('.abp[data-abp], #abp-bar'), seen = {}; // #abp-bar: markup of an older (cached) template
      for (var i = 0; i < list.length; i++) {
        var bar = list[i];
        // A theme that renders the header twice must not show two bars.
        if (bar.id) {
          if (seen[bar.id] && seen[bar.id] !== bar) { bar.parentNode.removeChild(bar); continue; }
          seen[bar.id] = bar;
        }
        if (bar.getAttribute('data-abp-pre') === '1') { continue; }
        bar.setAttribute('data-abp-pre', '1');
        try { prep(bar); } catch (e1) { /* one broken box must not stop the others */ }
      }
    } catch (e) { /* never break the page */ }
  }

  window.abpPre = run;
  // Consent given later on this page: forget the "no storage" answers and decide A/B and visitor rules again.
  run.consent = function () {
    try {
      campaign = null;
      visitor = null;
      var list = document.querySelectorAll('.abp[data-abp-pre="1"]');
      for (var i = 0; i < list.length; i++) {
        try { rules(list[i], list[i].querySelectorAll('.abp-item')); } catch (e1) { /* one broken box must not stop the others */ }
      }
    } catch (e) { /* never break the page */ }
  };
  run();
})();
