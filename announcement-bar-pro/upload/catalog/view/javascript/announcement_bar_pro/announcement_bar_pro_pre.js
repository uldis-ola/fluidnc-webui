/* Announcement Bar Pro v2.0.4 — runs synchronously right after the bar markup (no inline script, CSP-friendly).
   Removes messages the visitor already closed, before the first paint. */
(function () {
  try {
    var bar = document.getElementById('abp-bar');
    if (!bar) { return; }
    var now = Date.now(), keys = [];
    var read = function (name) {
      try {
        var v = JSON.parse(window[name].getItem('abp_dismiss2') || 'null');
        if (!v || !Array.isArray(v.k) || (v.t && v.t <= now)) { return []; }
        return v.k.filter(function (k) { return typeof k === 'string' && /^[0-9a-f]{12}$/.test(k); });
      } catch (e) { return []; }
    };
    keys = read('localStorage').concat(read('sessionStorage'));
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
