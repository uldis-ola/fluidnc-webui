/* OpenCart 3D Product Viewer — novieto skatītāju pie produkta galerijas (bez tēmas failu labošanas) */
(function () {
  'use strict';

  function findTarget(selector) {
    var el = null;
    if (selector) {
      try { el = document.querySelector(selector); } catch (e) { el = null; }
    }
    return el;
  }

  function fallbackTarget() {
    // ja galerija nav atrasta: pirms produkta virsraksta vai #content sākumā
    return document.querySelector('#content h1') || document.querySelector('#product') ||
      document.querySelector('#content') || document.querySelector('main') || document.body;
  }

  function place() {
    var box = document.getElementById('product-3d');
    if (!box || box.getAttribute('data-placed') === '1') { return; }

    var mode = box.getAttribute('data-placement') || 'replace';
    box.setAttribute('data-placed', '1');

    if (mode === 'manual') {
      box.hidden = false;
      return;
    }

    var target = findTarget(box.getAttribute('data-selector'));

    if (target) {
      if (mode === 'after') {
        target.parentNode.insertBefore(box, target.nextSibling);
      } else {
        target.parentNode.insertBefore(box, target);
        if (mode === 'replace') {
          target.style.display = 'none';
          target.setAttribute('data-product-3d-hidden', '1');
        }
      }
    } else {
      var fb = fallbackTarget();
      if (fb === document.body || fb.id === 'content' || fb.tagName === 'MAIN') {
        fb.insertBefore(box, fb.firstChild);
      } else {
        fb.parentNode.insertBefore(box, fb);
      }
    }

    box.hidden = false;
    document.dispatchEvent(new CustomEvent('product3d:placed', { detail: { mode: mode, target: target } }));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', place);
  } else {
    place();
  }
})();
