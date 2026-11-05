/* Announcement Bar Pro v2.0.4 — no dependencies */
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

    function paintBar(item) {
      if (!item) { return; }
      bar.style.backgroundColor = item.style.backgroundColor;
      bar.style.color = item.style.color;
      var close = bar.querySelector('.abp-close');
      if (close) { close.style.color = item.style.color; }
    }

    function fitBody() {
      if (bar.classList.contains('abp-pos-bottom')) {
        document.body.style.paddingBottom = bar.style.display === 'none' ? '' : bar.offsetHeight + 'px';
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
      if (max) { track.style.minHeight = max + 'px'; }
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

    function relayout() { stabilise(); show(Math.min(index, Math.max(items().length - 1, 0))); startRotate(); startMarquee(); fitBody(); }

    function hideBar() {
      stopRotate();
      if (tickTimer) { clearInterval(tickTimer); tickTimer = null; }
      bar.style.display = 'none';
      fitBody();
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

    tick();
    if (bar.querySelector('.abp-countdown, .abp-item[data-end]')) { tickTimer = setInterval(tick, 1000); }
    relayout();
    // Web fonts / icons can change heights after load.
    window.addEventListener('load', relayout);
  }

  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
