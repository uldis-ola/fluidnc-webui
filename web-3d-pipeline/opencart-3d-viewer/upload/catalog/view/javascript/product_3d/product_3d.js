/* OpenCart 3D Product Viewer — novieto skatītāju pie produkta galerijas (bez tēmas failu labošanas) */
(function () {
  'use strict';

  var READY_TIMEOUT = 15000;

  function query(selector) {
    if (!selector) { return null; }
    try { return document.querySelector(selector); } catch (e) { return null; }
  }

  function closest(el, selector) {
    while (el && el.nodeType === 1) {
      if (el.matches && el.matches(selector)) { return el; }
      el = el.parentElement;
    }
    return null;
  }

  /** Ja galerija nav atrasta (piem. produktam nav attēlu): produkta lapas kreisā/pirmā kolonna */
  function fallbackSpot() {
    var h1 = document.querySelector('#content h1');
    var row = h1 ? closest(h1, '.row') : document.querySelector('#content .row');

    if (row && row.firstElementChild && row.firstElementChild.contains(h1) === false) {
      return { parent: row.firstElementChild, before: row.firstElementChild.firstChild };
    }
    if (h1) {
      return { parent: h1.parentNode, before: h1 };
    }
    var content = document.querySelector('#content') || document.querySelector('main') || document.body;
    return { parent: content, before: content.firstChild };
  }

  function place() {
    var box = document.getElementById('product-3d');
    if (!box || box.getAttribute('data-placed') === '1') { return; }
    box.setAttribute('data-placed', '1');

    var mode = box.getAttribute('data-placement') || 'replace';
    var viewer = box.querySelector('model-viewer');
    var target = mode === 'manual' ? null : query(box.getAttribute('data-selector'));

    if (mode !== 'manual') {
      if (target) {
        target.parentNode.insertBefore(box, mode === 'after' ? target.nextSibling : target);
      } else {
        var spot = fallbackSpot();
        spot.parent.insertBefore(box, spot.before);
      }
    }

    // pārlūks bez Custom Elements / ES moduļiem: atstājam tikai galeriju
    if (!window.customElements || !('noModule' in document.createElement('script'))) {
      box.parentNode && box.parentNode.removeChild(box);
      return;
    }

    box.hidden = false;
    var settled = false;

    function fail() {
      if (settled) { return; }
      settled = true;
      if (target) { target.style.display = ''; }   // galerija atpakaļ
      box.parentNode && box.parentNode.removeChild(box);
      document.dispatchEvent(new CustomEvent('product3d:failed', { detail: { mode: mode } }));
    }

    function ok() {
      if (settled) { return; }
      settled = true;
      // "replace": galeriju slēpjam, kad model-viewer skripts strādā; ja modelis neielādējas ('error'), atjaunojam
      if (mode === 'replace' && target) {
        target.style.display = 'none';
        target.setAttribute('data-product-3d-hidden', '1');
      }
      document.dispatchEvent(new CustomEvent('product3d:placed', { detail: { mode: mode, target: target } }));
    }

    if (viewer) {
      viewer.addEventListener('error', function () { settled = false; fail(); });
    }

    // ja model-viewer skripts netiek ielādēts (bloķēts, CSP, tīkls) - atjaunojam galeriju
    var timer = setTimeout(function () {
      if (!window.customElements.get('model-viewer')) { fail(); }
    }, READY_TIMEOUT);

    window.customElements.whenDefined('model-viewer').then(function () {
      clearTimeout(timer);
      ok();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', place);
  } else {
    place();
  }
})();
