/* Announcement Bar Pro v2.0.1 — no dependencies */
(function () {
  'use strict';

  function init() {
    var bar = document.getElementById('abp-bar');
    if (!bar || bar.getAttribute('data-dismissed') === '1') { return; }

    var mode = bar.getAttribute('data-mode');
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var timer = null, cdTimer = null, index = 0;

    if (mode === 'marquee' && reduced) {
      mode = 'rotate';
      bar.className = bar.className.replace('abp-mode-marquee', 'abp-mode-rotate');
    }

    function items() { return bar.querySelectorAll('.abp-item'); }

    function paintBar(item) {
      if (!item) { return; }
      bar.style.backgroundColor = item.style.backgroundColor;
      bar.style.color = item.style.color;
      var close = bar.querySelector('.abp-close');
      if (close) { close.style.color = item.style.color; }
    }

    function show(i) {
      var list = items();
      if (!list.length) { return; }
      index = (i + list.length) % list.length;
      for (var n = 0; n < list.length; n++) { list[n].classList.toggle('abp-active', n === index); }
      paintBar(list[index]);
    }

    function startRotate() {
      stopRotate();
      if (mode !== 'rotate' || items().length < 2) { return; }
      var ms = Math.max(2, parseInt(bar.getAttribute('data-interval'), 10) || 5) * 1000;
      timer = setInterval(function () { show(index + 1); }, ms);
    }

    function stopRotate() { if (timer) { clearInterval(timer); timer = null; } }

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

    function fitBody() {
      if (bar.classList.contains('abp-pos-bottom')) {
        document.body.style.paddingBottom = bar.style.display === 'none' ? '' : bar.offsetHeight + 'px';
      }
    }

    function hideBar() {
      stopRotate();
      if (cdTimer) { clearInterval(cdTimer); }
      bar.style.display = 'none';
      fitBody();
    }

    function pad(n) { return (n < 10 ? '0' : '') + n; }

    function tick() {
      var nodes = bar.querySelectorAll('.abp-countdown');
      if (!nodes.length) { if (cdTimer) { clearInterval(cdTimer); } return; }
      var now = Date.now(), changed = false;
      for (var i = 0; i < nodes.length; i++) {
        var end = parseInt(nodes[i].getAttribute('data-end'), 10), left = Math.floor((end - now) / 1000);
        if (!end || left <= 0) {
          var item = nodes[i].closest('.abp-item');
          if (item && item.parentNode) { item.parentNode.removeChild(item); changed = true; }
          continue;
        }
        var d = Math.floor(left / 86400), h = Math.floor(left % 86400 / 3600), m = Math.floor(left % 3600 / 60), s = left % 60;
        nodes[i].textContent = (d > 0 ? d + (bar.getAttribute('data-days-label') || 'd') + ' ' : '') + pad(h) + ':' + pad(m) + ':' + pad(s);
      }
      if (changed) {
        if (!items().length) { hideBar(); return; }
        show(0); startRotate(); startMarquee(); fitBody();
      }
    }

    var close = bar.querySelector('.abp-close');
    if (close) {
      close.addEventListener('click', function () {
        var days = parseInt(bar.getAttribute('data-days'), 10) || 0;
        var value = JSON.stringify({ h: bar.getAttribute('data-hash'), t: days > 0 ? Date.now() + days * 86400000 : 0 });
        try {
          var keep = days > 0 ? localStorage : sessionStorage, drop = days > 0 ? sessionStorage : localStorage;
          keep.setItem('abp_dismiss', value);
          drop.removeItem('abp_dismiss');
        } catch (e) { /* storage blocked: bar just hides for this page view */ }
        hideBar();
      });
    }

    bar.addEventListener('mouseenter', stopRotate);
    bar.addEventListener('mouseleave', startRotate);
    bar.addEventListener('focusin', stopRotate);
    bar.addEventListener('focusout', startRotate);

    var resizeTimer = null;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () { startMarquee(); fitBody(); }, 200);
    });

    tick();
    if (bar.querySelector('.abp-countdown')) { cdTimer = setInterval(tick, 1000); }
    show(0);
    startRotate();
    startMarquee();
    fitBody();
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
