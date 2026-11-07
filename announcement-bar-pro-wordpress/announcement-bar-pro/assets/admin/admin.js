/* Announcement Bar Pro — admin screens: language tabs, dependent fields, live preview (sandboxed frame with the real
   storefront script and styles, rendered by the server), contrast check, media picker, confirmations. */
(function () {
  'use strict';

  var cfg = window.abpAdmin || {};
  var t = cfg.l10n || {};
  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  /* ---------------- Confirmations (list, subscribers) ---------------- */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    var btn = form.querySelector('.abp-confirm-delete, .abp-confirm-useb');
    var msg = btn ? (btn.classList.contains('abp-confirm-delete') ? t.confirmDelete : t.confirmUseB) : (form.classList.contains('abp-confirm-erase') ? t.confirmErase : '');
    if (msg && !window.confirm(msg)) { e.preventDefault(); }
  });

  var form = document.getElementById('abp-form');
  if (!form) { return; }

  /* ---------------- Language tabs (WAI-ARIA tabs: arrows, Home, End) ---------------- */
  var tabs = $$('.abp-langs [role="tab"]', form);
  function selectTab(tab, focus) {
    tabs.forEach(function (b) {
      var on = b === tab;
      b.classList.toggle('active', on);
      b.setAttribute('aria-selected', on ? 'true' : 'false');
      b.tabIndex = on ? 0 : -1;
      var pane = document.getElementById(b.getAttribute('aria-controls'));
      if (pane) { pane.hidden = !on; }
    });
    if (focus) { tab.focus(); }
    var pv = document.getElementById('abp-pv-locale');
    if (pv && pv.value !== tab.getAttribute('data-locale')) { pv.value = tab.getAttribute('data-locale'); preview(); }
  }
  tabs.forEach(function (tab, i) {
    tab.addEventListener('click', function () { selectTab(tab, false); });
    tab.addEventListener('keydown', function (e) {
      var to = null;
      if (e.key === 'ArrowRight') { to = tabs[(i + 1) % tabs.length]; } else if (e.key === 'ArrowLeft') { to = tabs[(i - 1 + tabs.length) % tabs.length]; } else if (e.key === 'Home') { to = tabs[0]; } else if (e.key === 'End') { to = tabs[tabs.length - 1]; }
      if (to) { e.preventDefault(); selectTab(to, true); }
    });
  });
  // Open the first language that has an error.
  var bad = tabs.filter(function (b) { return b.classList.contains('abp-has-error'); })[0];
  if (bad) { selectTab(bad, false); }

  /* ---------------- Dependent fields ---------------- */
  function val(name) {
    var el = form.elements[name];
    if (!el) { return ''; }
    if (el.type === 'checkbox') { return el.checked ? '1' : ''; }
    return el.value || '';
  }
  function dependents() {
    var type = val('type') || 'standard';
    var state = {
      goal: type !== 'standard',
      fs: type === 'free_shipping',
      'fs-own': type === 'free_shipping' && !val('opt[fs_wc]'),
      tiers: type === 'tiers',
      ab: !!val('ab_test'),
      email: !!val('opt[email]'),
      pages: val('page_mode') === 'include' || val('page_mode') === 'exclude',
      countries: val('opt[countries_mode]') !== '' && val('opt[countries_mode]') !== 'all',
      'trigger-value': val('opt[trigger]') === 'delay' || val('opt[trigger]') === 'scroll',
      'recur-weekly': val('opt[recur]') === 'weekly',
      'recur-daily': val('opt[recur]') === 'daily'
    };
    $$('[data-show]').forEach(function (el) {
      var key = el.getAttribute('data-show');
      if (Object.prototype.hasOwnProperty.call(state, key)) { el.hidden = !state[key]; }
    });
  }

  /* ---------------- Contrast (WCAG 2.x) ---------------- */
  function lum(hex) {
    var m = /^#([0-9a-f]{6})$/i.exec(hex || '');
    if (!m) { return null; }
    var n = parseInt(m[1], 16), c = [(n >> 16) & 255, (n >> 8) & 255, n & 255].map(function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  }
  function contrast() {
    var out = document.getElementById('abp-contrast'), a = lum(val('bg_color')), b = lum(val('text_color'));
    if (!out || a === null || b === null) { return; }
    var ratio = (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
    out.textContent = (out.getAttribute('data-label') || 'Contrast') + ' ' + ratio.toFixed(1) + ':1 — ' + (ratio >= 4.5 ? (out.getAttribute('data-ok') || 'readable') : (out.getAttribute('data-low') || 'too low, choose stronger colours'));
    out.classList.toggle('abp-contrast-low', ratio < 4.5);
  }

  /* ---------------- Live preview ---------------- */
  var frame = document.getElementById('abp-preview-frame'), note = document.getElementById('abp-pv-note'), wrap = document.getElementById('abp-preview-wrap');
  var pv = { device: 'desktop', variant: 'a' }, timer = null, seq = 0, lastDoc = '';
  function render() {
    if (!frame || !cfg.preview || !window.fetch || !window.FormData) { return; }
    var fd = new FormData(form), my = ++seq;
    fd.delete('_wpnonce');
    fd.delete('action');
    fd.append('pv_device', pv.device);
    fd.append('pv_variant', pv.variant);
    var loc = document.getElementById('abp-pv-locale');
    if (loc) { fd.append('pv_locale', loc.value); }
    var cart = document.getElementById('abp-pv-cart');
    if (cart) { fd.append('pv_cart', cart.value || '0'); }
    window.fetch(cfg.preview, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce, Accept: 'application/json' } })
      .then(function (r) { if (!r.ok) { throw new Error(String(r.status)); } return r.json(); })
      .then(function (j) {
        if (my !== seq) { return; }
        if (wrap) { wrap.classList.toggle('abp-pv-phone', pv.device === 'mobile'); }
        if (j.html !== lastDoc) { lastDoc = j.html; frame.srcdoc = j.html; }
        if (note) { note.textContent = j.shown ? '' : (t.notShown || ''); }
      })
      .catch(function () { if (note && my === seq) { note.textContent = t.previewError || ''; } });
  }
  function preview() { clearTimeout(timer); timer = setTimeout(function () { dependents(); contrast(); render(); }, 250); }

  $$('[data-pv-device]').forEach(function (b) {
    b.addEventListener('click', function () {
      pv.device = b.getAttribute('data-pv-device');
      $$('[data-pv-device]').forEach(function (x) { var on = x === b; x.classList.toggle('active', on); x.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      render();
    });
  });
  $$('[data-pv-variant]').forEach(function (b) {
    b.addEventListener('click', function () {
      pv.variant = b.getAttribute('data-pv-variant');
      $$('[data-pv-variant]').forEach(function (x) { var on = x === b; x.classList.toggle('active', on); x.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      render();
    });
  });
  ['abp-pv-locale', 'abp-pv-cart'].forEach(function (id) { var el = document.getElementById(id); if (el) { el.addEventListener('input', preview); el.addEventListener('change', preview); } });
  form.addEventListener('input', preview);
  form.addEventListener('change', preview);
  // selectWoo (WooCommerce's enhanced selects) reports changes through jQuery only.
  if (window.jQuery) { window.jQuery(form).on('change', 'select', preview); }

  /* ---------------- Background image (media library) ---------------- */
  var pick = document.getElementById('abp-image-pick'), clear = document.getElementById('abp-image-clear');
  var input = document.getElementById('abp-image'), thumb = document.getElementById('abp-image-thumb'), media = null;
  if (pick && window.wp && window.wp.media) {
    pick.addEventListener('click', function () {
      if (!media) {
        media = window.wp.media({ title: t.chooseImage, button: { text: t.useImage }, library: { type: 'image' }, multiple: false });
        media.on('select', function () {
          var a = media.state().get('selection').first().toJSON();
          input.value = String(a.id);
          thumb.src = (a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url);
          thumb.hidden = false;
          clear.hidden = false;
          preview();
        });
      }
      media.open();
    });
  }
  if (clear) {
    clear.addEventListener('click', function () { input.value = '0'; thumb.hidden = true; thumb.removeAttribute('src'); clear.hidden = true; pick.focus(); preview(); });
  }

  /* ---------------- Free-shipping rows by country / state ---------------- */
  var add = document.getElementById('abp-zone-add'), rows = document.getElementById('abp-zone-rows');
  if (add && rows) {
    add.addEventListener('click', function () {
      var last = rows.querySelector('tr:last-child');
      if (!last) { return; }
      var copy = last.cloneNode(true), n = rows.querySelectorAll('tr').length;
      $$('select, input', copy).forEach(function (el) {
        el.name = el.name.replace(/\[fs_zones\]\[\d+\]/, '[fs_zones][' + n + ']');
        el.value = '';
      });
      rows.appendChild(copy);
      copy.querySelector('select').focus();
    });
  }

  /* ---------------- Start ---------------- */
  var c = document.getElementById('abp-contrast');
  if (c && cfg.l10n) { c.setAttribute('data-label', cfg.l10n.contrast || 'Contrast'); c.setAttribute('data-ok', cfg.l10n.contrastOk || 'readable'); c.setAttribute('data-low', cfg.l10n.contrastLow || 'too low'); }
  dependents();
  contrast();
  render();
})();
