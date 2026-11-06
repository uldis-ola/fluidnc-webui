/* Announcement Bar Pro v2.1.0 — runs synchronously right after the bar markup (no inline script, CSP-friendly).
   Before the first paint it: keeps a single bar, moves a top bar to the top of <body> when the theme made it
   render in the footer, hides it while automatic colours are worked out, and removes messages the visitor closed. */
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

    // Automatic colours are read from the theme after the page has loaded; hide until then (CSS reveals
    // the bar after 2 s anyway, so a blocked script can never hide it for good).
    if (bar.getAttribute('data-color') === 'auto') { bar.className += ' abp-auto-pending'; }

    if (bar.getAttribute('data-dismissible') !== '1') { return; }

    var now = Date.now();
    var read = function (name) {
      try {
        var v = JSON.parse(window[name].getItem('abp_dismiss2') || 'null');
        if (!v || !Array.isArray(v.k) || (v.t && v.t <= now)) { return []; }
        return v.k.filter(function (k) { return typeof k === 'string' && /^[0-9a-f]{12}$/.test(k); });
      } catch (e) { return []; }
    };
    var keys = read('localStorage').concat(read('sessionStorage'));
    if (!keys.length) { return; }

    var items = bar.querySelectorAll('.abp-item'), left = null;
    for (var i = 0; i < items.length; i++) {
      if (keys.indexOf(items[i].getAttribute('data-key')) !== -1) {
        items[i].parentNode.removeChild(items[i]);
      } else if (!left) {
        left = items[i];
      }
    }
    if (!left) {
      bar.style.display = 'none';
      bar.setAttribute('data-dismissed', '1');
      return;
    }
    if (!bar.querySelector('.abp-item.abp-active')) { left.className += ' abp-active'; }
    bar.style.backgroundColor = left.style.backgroundColor;
    bar.style.color = left.style.color;
  } catch (e) { /* never break the page */ }
})();
