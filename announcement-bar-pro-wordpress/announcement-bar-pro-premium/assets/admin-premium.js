/* Announcement Bar Pro — Premium: dependent fields of the premium form parts, A / B switch of the preview, media
   picker of the background image, free-shipping rows by country, confirmations. Uses window.abpAdminApi of the
   free plugin's admin script. */
(function () {
  'use strict';

  var api = window.abpAdminApi;
  var cfg = window.abpPremium || {};
  var t = cfg.l10n || {};
  if (!api) { return; }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  api.confirm('.abp-confirm-useb', t.confirmUseB || '');
  api.confirm('.abp-confirm-erase', t.confirmErase || '');

  var form = document.getElementById('abp-form');
  if (!form) { return; }

  /* ---------------- Dependent fields ---------------- */
  api.addDeps(function (val, type) {
    return {
      tiers: type === 'tiers',
      ab: !!val('ab_test'),
      email: !!val('opt[email]'),
      pages: val('page_mode') === 'include' || val('page_mode') === 'exclude',
      countries: val('opt[countries_mode]') !== '' && val('opt[countries_mode]') !== 'all',
      'trigger-value': val('opt[trigger]') === 'delay' || val('opt[trigger]') === 'scroll',
      'recur-weekly': val('opt[recur]') === 'weekly',
      'recur-daily': val('opt[recur]') === 'daily'
    };
  });

  /* ---------------- Preview: variant A / B ---------------- */
  var variant = 'a';
  api.addParams(function (fd) { fd.append('pv_variant', variant); });
  $$('[data-pv-variant]').forEach(function (b) {
    b.addEventListener('click', function () {
      variant = b.getAttribute('data-pv-variant');
      $$('[data-pv-variant]').forEach(function (x) { var on = x === b; x.classList.toggle('active', on); x.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      api.render();
    });
  });

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
          api.preview();
        });
      }
      media.open();
    });
  }
  if (clear) {
    clear.addEventListener('click', function () { input.value = '0'; thumb.hidden = true; thumb.removeAttribute('src'); clear.hidden = true; pick.focus(); api.preview(); });
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

  api.dependents();
  api.render();
})();
