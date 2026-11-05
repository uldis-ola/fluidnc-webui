/*
 * OpenCart 3D Product Viewer — novieto skatītāju produkta lapā neatkarīgi no tēmas.
 *
 * Galerijas atrašana (secībā):
 *   1) CSS selektors no moduļa iestatījumiem (ja norādīts un atrasts);
 *   2) automātiski: lapā atrod ŠĪ produkta attēlus pēc faila nosaukuma (src, data-src, srcset, href,
 *      data-zoom-image, background-image u.c.) un kāpj uz augšu līdz konteineram, kurā nav produkta
 *      nosaukuma, cenas, pirkšanas pogas un gara teksta;
 *   3) zināmie selektori (.thumbnails u.c.);
 *   4) ja attēlu nav: lapas galvenā kolonna blakus produkta informācijai, citādi pirms virsraksta.
 * Ja model-viewer neielādējas vai modelis ir bojāts, galerija tiek atjaunota.
 */
(function () {
  'use strict';

  var READY_TIMEOUT = 15000;
  var KNOWN_GALLERIES = [
    '.thumbnails', '.product-gallery', '.product-images', '.product-image', '.image-additional',
    '#product-gallery', '.main-image', '.product-left .image', '.swiper-gallery', '.product-media'
  ];
  var INFO_SELECTORS = [
    '#button-cart', '[name="quantity"]', 'button[onclick*="cart.add"]', '.price-new', '.product-price',
    '[itemprop="price"]', '[itemprop="offers"]', '#product .btn-primary'
  ];

  function $(selector, root) {
    if (!selector) { return null; }
    try { return (root || document).querySelector(selector); } catch (e) { return null; }
  }

  function $$(selector, root) {
    try { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); } catch (e) { return []; }
  }

  function visible(el) {
    if (!el || !el.getBoundingClientRect) { return false; }
    var r = el.getBoundingClientRect();
    var cs = window.getComputedStyle(el);
    return r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none';
  }

  function area(el) {
    var r = el.getBoundingClientRect();
    return r.width * r.height;
  }

  /* ---------------------------------------------------------------- produkta informācija */

  function productTitle(box) {
    var alt = (box.querySelector('model-viewer') || {}).getAttribute ? box.querySelector('model-viewer').getAttribute('alt') : '';
    var h1s = $$('h1').filter(function (h) { return !box.contains(h); });
    var match = h1s.filter(function (h) { return alt && h.textContent.trim().indexOf(alt.trim()) !== -1; });
    return match[0] || h1s[0] || null;
  }

  function infoElements(box) {
    var list = [];
    var title = productTitle(box);
    if (title) { list.push(title); }
    INFO_SELECTORS.forEach(function (s) {
      $$(s).forEach(function (el) {
        if (!box.contains(el) && list.indexOf(el) === -1) { list.push(el); }
      });
    });
    return list;
  }

  function containsAny(container, elements) {
    for (var i = 0; i < elements.length; i++) {
      if (container !== elements[i] && container.contains(elements[i])) { return true; }
    }
    return false;
  }

  function textLength(el) {
    return (el.textContent || '').replace(/\s+/g, ' ').trim().length;
  }

  /* ---------------------------------------------------------------- attēlu atpazīšana */

  function urlsOf(el) {
    var urls = [];
    var attrs = ['src', 'href', 'data-src', 'data-lazy', 'data-lazy-src', 'data-original', 'data-image',
      'data-zoom-image', 'data-large', 'data-full', 'data-big', 'data-thumb', 'data-srcset', 'srcset', 'poster'];
    attrs.forEach(function (name) {
      var v = el.getAttribute && el.getAttribute(name);
      if (v) {
        v.split(',').forEach(function (part) { urls.push(part.trim().split(/\s+/)[0]); });
      }
    });
    var bg = el.style && el.style.backgroundImage;
    if (bg && bg.indexOf('url(') !== -1) {
      urls.push(bg.replace(/^.*url\(["']?/, '').replace(/["']?\).*$/, ''));
    }
    return urls;
  }

  function fileStem(url) {
    var name = (url || '').split('#')[0].split('?')[0].split('/').pop() || '';
    try { name = decodeURIComponent(name); } catch (e) { /* atstāj kā ir */ }
    return name.toLowerCase();
  }

  function matchesKeys(el, keys) {
    var urls = urlsOf(el);
    for (var i = 0; i < urls.length; i++) {
      var stem = fileStem(urls[i]);
      for (var k = 0; k < keys.length; k++) {
        // apple_cinema_30-500x500.jpg, apple_cinema_30-1000x1000w.webp, apple_cinema_30.jpg
        if (stem.indexOf(keys[k]) === 0 && /^[-_.@]|^$/.test(stem.substr(keys[k].length, 1))) { return true; }
      }
    }
    return false;
  }

  function findAutoGallery(box, keys) {
    if (!keys.length) { return null; }

    var skip = 'header, footer, nav, #product-3d, [class*="related"], [id*="related"], [class*="viewed"], ' +
      '[class*="cart"], .dropdown-menu, .modal, script, noscript';
    var candidates = $$('img, a[href], picture source, [data-src], [data-zoom-image], [data-image], [style*="background"]')
      .filter(function (el) {
        return !(el.closest && el.closest(skip)) && matchesKeys(el, keys);
      });

    if (!candidates.length) { return null; }

    // lielākais redzamais attēls = galvenais attēls
    var best = null;
    var bestArea = -1;
    candidates.forEach(function (el) {
      var target = el.tagName === 'A' && el.querySelector('img') ? el.querySelector('img') : el;
      var a = visible(target) ? area(target) : 0;
      if (a > bestArea) { bestArea = a; best = target; }
    });
    if (!best) { best = candidates[0]; }

    var info = infoElements(box);
    var node = best;
    var parent = node.parentElement;

    while (parent && parent !== document.body && parent.tagName !== 'MAIN' &&
           !containsAny(parent, info) && textLength(parent) < 200 && parent.id !== 'content') {
      node = parent;
      parent = node.parentElement;
    }

    return node === best && best.tagName === 'IMG' && best.parentElement ? best.parentElement : node;
  }

  /* ---------------------------------------------------------------- rezerves vieta */

  function fallbackSpot(box) {
    var info = infoElements(box);
    var anchor = info[0] || $('#content') || $('main') || document.body;

    // atrod rindu/flex konteineru, kur produkta info ir vienā kolonnā, un ievieto otrā (galvenajā) kolonnā
    var node = anchor;
    while (node && node.parentElement && node.parentElement !== document.body) {
      var parent = node.parentElement;
      var siblings = Array.prototype.slice.call(parent.children).filter(function (c) {
        return c !== node && c !== box && c.tagName !== 'SCRIPT' && c.tagName !== 'STYLE' &&
          info.indexOf(c) === -1 && !containsAny(c, info) && c.getBoundingClientRect().width > 0;
      });
      var nr = node.getBoundingClientRect();
      // kolonna = tajā pašā augstumā UN horizontāli blakus (nevis virs/zem)
      var side = siblings.filter(function (c) {
        var cr = c.getBoundingClientRect();
        return Math.abs(cr.top - nr.top) < 40 && (cr.right <= nr.left + 5 || cr.left >= nr.right - 5);
      });
      if (side.length) {
        // kreisākā kolonna
        side.sort(function (x, y) { return x.getBoundingClientRect().left - y.getBoundingClientRect().left; });
        return { mode: 'inside-start', el: side[0] };
      }
      node = parent;
      if (node.id === 'content' || node.tagName === 'MAIN') { break; }
    }

    if (info[0] && info[0].parentNode) {
      return { mode: 'before', el: info[0] };
    }
    return { mode: 'inside-start', el: anchor };
  }

  function isFlexOrGrid(el) {
    if (!el) { return false; }
    var d = window.getComputedStyle(el).display;
    return d.indexOf('flex') !== -1 || d.indexOf('grid') !== -1;
  }

  /**
   * Ja tēmas kolonna ir par šauru (telefonā), pārvietojam skatītāju pilnā platumā virs produkta bloka
   * un "replace" režīmā paslēpjam visu galerijas kolonnu.
   */
  function widenIfNarrow(box, gallery, hidden, mode) {
    var minWidth = Math.min(300, window.innerWidth * 0.75);
    if (box.getBoundingClientRect().width >= minWidth) { return false; }

    var node = box.parentElement;
    while (node && node.parentElement && node.parentElement !== document.body &&
           node.getBoundingClientRect().width < window.innerWidth * 0.85) {
      node = node.parentElement;
    }
    if (!node || node === document.body || node === box.parentElement) { return false; }

    node.parentNode.insertBefore(box, node);
    if (mode === 'replace' && gallery && !gallery.contains(box)) {
      hidden.length = 0;
      hidden.push(gallery);
    }
    return true;
  }

  /** true, ja elementam blakus (tajā pašā augstumā) ir citas redzamas kolonnas */
  function isLayoutColumn(el) {
    var parent = el.parentElement;
    if (!parent || !visible(el)) { return false; }
    var r = el.getBoundingClientRect();
    return Array.prototype.slice.call(parent.children).some(function (c) {
      if (c === el || !visible(c)) { return false; }
      var cr = c.getBoundingClientRect();
      return Math.abs(cr.top - r.top) < 40 && (cr.left >= r.right - 5 || cr.right <= r.left + 5);
    });
  }

  /* ---------------------------------------------------------------- ievietošana */

  function insert(box, el, how) {
    if (how === 'after') {
      el.parentNode.insertBefore(box, el.nextSibling);
    } else if (how === 'inside-start') {
      el.insertBefore(box, el.firstChild);
    } else if (how === 'inside-end') {
      el.appendChild(box);
    } else {
      el.parentNode.insertBefore(box, el);
    }
  }

  function place(final) {
    var box = document.getElementById('product-3d');
    if (!box || box.getAttribute('data-placed') === '1') { return; }

    var mode = box.getAttribute('data-placement') || 'replace';
    var viewer = box.querySelector('model-viewer');
    var keys = (box.getAttribute('data-image-keys') || '').split('|').filter(function (k) { return k.length >= 3; });
    var hidden = [];       // elementi, ko paslēpām (lai varētu atjaunot)
    var gallery = null;
    var source = 'manual';

    if (mode !== 'manual') {
      gallery = $(box.getAttribute('data-selector'));
      source = 'selector';
      if (!gallery) { gallery = findAutoGallery(box, keys); source = 'auto'; }
      if (!gallery) {
        for (var i = 0; i < KNOWN_GALLERIES.length && !gallery; i++) {
          gallery = $(KNOWN_GALLERIES[i]);
          if (gallery && box.contains(gallery)) { gallery = null; }
        }
        source = 'known';
      }

      // galerija vēl nav uzzīmēta (JS slaideris) - mēģinām vēlreiz pēc window.load
      if (!gallery && !final && keys.length && document.readyState !== 'complete') {
        window.addEventListener('load', function () { place(true); });
        return;
      }

      box.setAttribute('data-placed', '1');

      if (gallery) {
        var isList = /^(UL|OL|TABLE|TBODY|TR|PICTURE)$/.test(gallery.tagName);
        if (!isList && (isLayoutColumn(gallery) || isFlexOrGrid(gallery.parentElement))) {
          // galerija = režģa kolonna: ievietojam kolonnas iekšpusē, lai nesalauztu izkārtojumu
          insert(box, gallery, mode === 'after' ? 'inside-end' : 'inside-start');
          if (mode === 'replace') {
            Array.prototype.slice.call(gallery.children).forEach(function (c) {
              if (c !== box && c.tagName !== 'SCRIPT' && c.tagName !== 'STYLE') { hidden.push(c); }
            });
          }
          source += ':column';
        } else {
          insert(box, gallery, mode === 'after' ? 'after' : 'before');
          if (mode === 'replace') {
            hidden.push(gallery);
          }
        }
        box.setAttribute('data-gallery', source + ':' + gallery.tagName.toLowerCase() +
          (gallery.className && typeof gallery.className === 'string' ? '.' + gallery.className.trim().split(/\s+/).join('.') : '') +
          (isList ? ':list' : ''));
      } else {
        var spot = fallbackSpot(box);
        insert(box, spot.el, spot.mode);
        box.setAttribute('data-gallery', 'fallback');
      }
    } else {
      box.setAttribute('data-placed', '1');
    }

    // pārlūks bez Custom Elements / ES moduļiem: atstājam tikai galeriju
    if (!window.customElements || !('noModule' in document.createElement('script'))) {
      if (box.parentNode) { box.parentNode.removeChild(box); }
      return;
    }

    box.hidden = false;
    var settled = false;

    // šaurai kolonnai (telefons, pagriešana) - pārbauda tagad, pēc 'load' un pie izmēra maiņas
    function checkWidth() {
      if (mode === 'manual' || box.getAttribute('data-widened') === '1' || !box.parentNode) { return; }
      if (widenIfNarrow(box, gallery, hidden, mode)) {
        box.setAttribute('data-widened', '1');
        box.setAttribute('data-gallery', (box.getAttribute('data-gallery') || '') + ':widened');
        if (settled) { hideGallery(); }
      }
    }
    var resizeTimer = null;
    checkWidth();
    window.addEventListener('load', checkWidth);
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(checkWidth, 200);
    });

    function restore() {
      hidden.forEach(function (el) {
        el.style.display = el.getAttribute('data-product-3d-display') || '';
        el.removeAttribute('data-product-3d-hidden');
      });
    }

    function fail() {
      restore();
      if (box.parentNode) { box.parentNode.removeChild(box); }
      document.dispatchEvent(new CustomEvent('product3d:failed', { detail: { mode: mode } }));
    }

    function hideGallery() {
      // ja pēc paplašināšanas paslēpjamie elementi mainījās - iepriekš paslēptos atjaunojam
      $$('[data-product-3d-hidden]').forEach(function (el) {
        if (hidden.indexOf(el) === -1) {
          el.style.display = el.getAttribute('data-product-3d-display') || '';
          el.removeAttribute('data-product-3d-hidden');
        }
      });
      hidden.forEach(function (el) {
        if (el.getAttribute('data-product-3d-hidden') !== '1') {
          el.setAttribute('data-product-3d-display', el.style.display || '');
          el.style.display = 'none';
          el.setAttribute('data-product-3d-hidden', '1');
        }
      });
    }

    function ok() {
      if (settled) { return; }
      settled = true;
      hideGallery();
      document.dispatchEvent(new CustomEvent('product3d:placed', {
        detail: { mode: mode, gallery: gallery, source: box.getAttribute('data-gallery') }
      }));
    }

    if (viewer) {
      viewer.addEventListener('error', fail);
    }

    var timer = setTimeout(function () {
      if (!window.customElements.get('model-viewer')) { fail(); }
    }, READY_TIMEOUT);

    window.customElements.whenDefined('model-viewer').then(function () {
      clearTimeout(timer);
      ok();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { place(false); });
  } else {
    place(false);
  }
})();
