/* Announcement Bar Pro v2.3.0 — runs synchronously right after the bar markup (no inline script, CSP-friendly).
   Before the first paint it: keeps a single bar, moves a top bar to the top of <body> when the theme made it
   render in the footer, hides it while automatic colours / styles are worked out, removes messages the visitor
   closed, gives every A/B-tested message this browser's variant and starts with a message meant for this screen
   size (data-device, breakpoint 768 px) that is inside its weekly schedule (data-off). */
(function () {
  try {
    var bars = document.querySelectorAll('#abp-bar');
    var bar = bars[0];
    if (!bar) { return; }

    // A theme that renders the header twice must not show two bars.
    for (var b = 1; b < bars.length; b++) { bars[b].parentNode.removeChild(bars[b]); }

    // Footer fallback (theme header without <body>): a top bar still belongs at the top of the page.
    if (bar.getAttribute('data-fallback') === '1' && !/\babp-pos-bottom\b/.test(bar.className) && document.body) {
      document.body.insertBefore(bar, document.body.firstChild);
    }

    var items = bar.querySelectorAll('.abp-item'), i;

    // Automatic colours and the derived styles are painted by the main script; hide until then (CSS reveals
    // the bar after 2 s anyway, so a blocked script can never hide it for good).
    var styled = false;
    for (i = 0; i < items.length; i++) { if ((items[i].getAttribute('data-style') || 'solid') !== 'solid') { styled = true; } }
    if (bar.getAttribute('data-color') === 'auto' || styled) { bar.className += ' abp-auto-pending'; }

    // A/B test: a stable random variant per browser (localStorage "abp_ab"); without storage everyone sees A.
    // Nothing is stored (everyone sees A) with Do Not Track / Global Privacy Control, or when the shop's consent
    // tool sets window.abpConsent = false before this script.
    var ab = null;
    var abOff = (function (d) { return d === '1' || d === 'yes'; })(navigator.doNotTrack || window.doNotTrack || navigator.msDoNotTrack) || navigator.globalPrivacyControl === true || window.abpConsent === false;
    if (!abOff) try { ab = JSON.parse(window.localStorage.getItem('abp_ab') || '{}'); if (!ab || typeof ab !== 'object') { ab = {}; } } catch (e0) { ab = null; }
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

    var now = Date.now();
    var read = function (name) {
      try {
        var v = JSON.parse(window[name].getItem('abp_dismiss2') || 'null');
        if (!v || !Array.isArray(v.k) || (v.t && v.t <= now)) { return []; }
        return v.k.filter(function (k) { return typeof k === 'string' && /^[0-9a-f]{12}$/.test(k); });
      } catch (e) { return []; }
    };
    var keys = bar.getAttribute('data-dismissible') === '1' ? read('localStorage').concat(read('sessionStorage')) : [];
    var mobile = window.matchMedia ? window.matchMedia('(max-width: 767px)').matches : window.innerWidth < 768;
    var fits = function (el) { var d = el.getAttribute('data-device'); return !el.hasAttribute('data-off') && (!d || d === 'all' || (d === 'mobile') === mobile); };

    var left = 0, first = null;
    for (i = 0; i < items.length; i++) {
      if (keys.length && keys.indexOf(items[i].getAttribute('data-key')) !== -1) {
        items[i].parentNode.removeChild(items[i]);
      } else {
        left++;
        if (!first && fits(items[i])) { first = items[i]; }
      }
    }
    if (!left) {
      bar.style.display = 'none';
      bar.setAttribute('data-dismissed', '1');
      return;
    }
    // Nothing for this screen size / time: stay hidden (the main script shows the bar when that changes).
    if (!first) { bar.style.display = 'none'; return; }
    var active = bar.querySelector('.abp-item.abp-active');
    if (!active || !fits(active)) {
      if (active) { active.className = active.className.replace(/(^|\s)abp-active(?=\s|$)/g, ' '); }
      first.className += ' abp-active';
      active = first;
    }
    bar.style.backgroundColor = active.style.backgroundColor;
    bar.style.color = active.style.color;
  } catch (e) { /* never break the page */ }
})();
