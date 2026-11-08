/* Announcement Bar Pro v2.4.2 — admin script, shared by the OpenCart 3 and OpenCart 4 packages (no framework needed).
   List: import / export, "Use B", erase a newsletter address. Form: templates, language switcher, sections that depend
   on other fields, weekday pills, autocomplete chips (categories, products, coupons, countries), rows per country /
   region, image manager and the LIVE PREVIEW, which loads the shop's real bar script and styles into a sandboxed
   frame (no access to the admin page). */
(function () {
  'use strict';

  var cfgEl = document.getElementById('abp-config');
  if (!cfgEl) { return; }
  var cfg = {};
  try { cfg = JSON.parse(cfgEl.textContent || '{}') || {}; } catch (e) { cfg = {}; }

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function on(el, ev, fn) { if (el) { el.addEventListener(ev, fn); } }
  function alertBox(type, text) {
    var box = $('#abp-alerts');
    if (!box) { window.alert(text); return; }
    var div = document.createElement('div');
    div.className = 'alert alert-' + type + ' abp-alert';
    div.setAttribute('role', 'alert');
    div.textContent = text;
    box.insertBefore(div, box.firstChild);
    div.scrollIntoView({ block: 'nearest' });
  }
  // POST (FormData) → JSON; one request at a time per button (double clicks do nothing).
  function post(url, body, done) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', url);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function () { var json = null; try { json = JSON.parse(xhr.responseText); } catch (e) { json = null; } done(json); };
    xhr.onerror = function () { done(null); };
    xhr.send(body);
  }

  /* ================================ LIST ================================ */
  if (cfg.page === 'list') {
    var listBusy = false;
    $$('[data-abp-use-b]').forEach(function (btn) {
      on(btn, 'click', function () {
        if (listBusy || btn.disabled) { return; }
        if (!window.confirm((cfg.confirm && cfg.confirm.use_b) || '?')) { return; }
        listBusy = true; btn.disabled = true;
        var fd = new FormData(); fd.append('message_id', btn.getAttribute('data-id'));
        post(btn.getAttribute('data-abp-use-b'), fd, function (json) {
          if (json && json.success) { window.location = btn.getAttribute('data-reload'); return; }
          listBusy = false; btn.disabled = false;
          alertBox('danger', (json && json.error) || 'Error');
        });
      });
    });

    var imp = $('[data-abp-import]');
    if (imp) {
      var file = document.createElement('input');
      file.type = 'file'; file.accept = '.json,application/json'; file.hidden = true; file.style.display = 'none'; file.setAttribute('aria-hidden', 'true');
      document.body.appendChild(file);
      on(imp, 'click', function () { if (!imp.disabled) { file.value = ''; file.click(); } });
      on(file, 'change', function () {
        var f = file.files && file.files[0];
        if (!f) { return; }
        if (f.size > 1048576) { alertBox('danger', cfg.text.error_import_file); return; }
        imp.disabled = true;
        var label = imp.innerHTML; imp.textContent = cfg.text.importing;
        var fd = new FormData(); fd.append('file', f, f.name);
        post(imp.getAttribute('data-abp-import'), fd, function (json) {
          if (json && json.success) { window.location = imp.getAttribute('data-reload'); return; }
          imp.disabled = false; imp.innerHTML = label;
          alertBox('danger', (json && json.error) || cfg.text.error_import_file);
        });
      });
    }

    // Newsletter: erase every sign-up of one address (right to erasure).
    var erase = $('[data-abp-erase]'), eraseInput = $('#input-erase-email');
    on(erase, 'click', function () {
      var email = eraseInput ? eraseInput.value.trim() : '';
      if (!email || erase.disabled) { if (eraseInput) { eraseInput.focus(); } return; }
      if (!window.confirm((cfg.confirm && cfg.confirm.erase) || '?')) { return; }
      erase.disabled = true;
      var fd = new FormData(); fd.append('email', email);
      post(erase.getAttribute('data-abp-erase'), fd, function (json) {
        erase.disabled = false;
        if (json && json.success) { alertBox('success', json.success); if (eraseInput) { eraseInput.value = ''; } return; }
        alertBox('danger', (json && json.error) || 'Error');
      });
    });
    on(eraseInput, 'keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); if (erase) { erase.click(); } } });
    return;
  }

  if (cfg.page !== 'form') { return; }
  var form = $('#form-message');
  if (!form) { return; }

  /* ---------------- helpers for fields ---------------- */
  function field(name) { return form.querySelector('[name="' + name + '"]'); }
  function val(name) {
    var els = form.querySelectorAll('[name="' + name + '"]'), out = '';
    for (var i = 0; i < els.length; i++) {
      var el = els[i];
      if (el.type === 'checkbox' || el.type === 'radio') { if (el.checked) { out = el.value; } } else if (el.type !== 'hidden' || !out) { out = el.value; }
    }
    return out;
  }
  function checked(name) { var els = form.querySelectorAll('[name="' + name + '"]'); for (var i = 0; i < els.length; i++) { if ((els[i].type === 'checkbox' || els[i].type === 'radio') ? els[i].checked && els[i].value !== '0' : els[i].type === 'hidden' && els[i].value !== '' && els[i].value !== '0') { return true; } } return false; }

  /* ---------------- language switcher (all per-language fields at once) ---------------- */
  var lang = '';
  function setLang(id) {
    lang = String(id);
    $$('.abp-lang-pane').forEach(function (p) { p.hidden = p.getAttribute('data-lang') !== lang; });
    $$('[data-abp-lang]').forEach(function (b) { var a = b.getAttribute('data-abp-lang') === lang; b.classList.toggle('active', a); b.setAttribute('aria-pressed', a ? 'true' : 'false'); });
    preview();
  }
  $$('[data-abp-lang]').forEach(function (b) { on(b, 'click', function () { setLang(b.getAttribute('data-abp-lang')); }); });
  function firstError() {
    return $$('.text-danger, .invalid-feedback.d-block', form).filter(function (e) { return e.textContent.trim() !== ''; })[0] || null;
  }
  // Open the language and section of the first error (OpenCart 3: on load; OpenCart 4: after the AJAX save).
  function showError() {
    var err = firstError();
    if (!err) { return; }
    var pane = err.closest('.abp-lang-pane');
    if (pane) { setLang(pane.getAttribute('data-lang')); }
    var card = err.closest('.abp-card');
    if (card) { card.hidden = false; }
    err.scrollIntoView({ block: 'center' });
  }
  var firstLang = $('[data-abp-lang]');
  setLang(firstLang ? firstLang.getAttribute('data-abp-lang') : '');

  /* ---------------- dependent sections: data-abp-show="name=value" (or "name" for a ticked checkbox) ---------------- */
  function dependents() {
    $$('[data-abp-show]').forEach(function (el) {
      var rule = el.getAttribute('data-abp-show'), m = /^([^=!]+)(!?=)(.*)$/.exec(rule), show;
      if (m) { var values = m[3].split('|'); show = values.indexOf(val(m[1])) !== -1; if (m[2] === '!=') { show = !show; } } else if (rule.charAt(0) === '!') { show = !checked(rule.slice(1)); } else { show = checked(rule); }
      el.hidden = !show;
    });
  }

  /* ---------------- colour pickers, coupon, icon ---------------- */
  var hex = /^#[0-9a-fA-F]{6}$/;
  $$('.abp-color').forEach(function (g) {
    var picker = $('input[type=color]', g), text = $('input[type=text]', g);
    on(picker, 'input', function () { text.value = picker.value; text.dispatchEvent(new Event('input', { bubbles: true })); });
    on(text, 'input', function () { if (hex.test(text.value)) { picker.value = text.value; } });
  });
  var coupon = field('coupon');
  on(coupon, 'input', function () { var p = coupon.selectionStart; coupon.value = coupon.value.toUpperCase(); try { coupon.setSelectionRange(p, p); } catch (e) { /* type */ } });
  function iconClass(v) {
    v = String(v || '').trim().replace(/\s+/g, ' ');
    if (cfg.oc === 3) { return /^fa-[a-z0-9-]{1,40}$/.test(v) ? 'fa ' + v : ''; }
    if (!/^(fa-(solid|regular|brands) )?fa-[a-z0-9-]{1,40}$/.test(v)) { return ''; }
    return /^fa-(solid|regular|brands) /.test(v) ? v : 'fa-solid ' + v;
  }
  function luminance(h) {
    return [1, 3, 5].map(function (i) { var v = parseInt(h.substr(i, 2), 16) / 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); })
      .reduce(function (s, v, i) { return s + v * [0.2126, 0.7152, 0.0722][i]; }, 0);
  }
  function contrast(a, b) { var x = luminance(a), y = luminance(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); }

  /* ---------------- autocomplete chips (categories / products) ---------------- */
  function decode(s) { var t = document.createElement('textarea'); t.innerHTML = String(s == null ? '' : s); return t.value; } // text only, never inserted as HTML
  $$('.abp-chips').forEach(function (box) {
    var type = box.getAttribute('data-abp-ac'), name = box.getAttribute('data-name'), input = $('.abp-ac-input', box), menu = $('.abp-ac-menu', box);
    var url = cfg.autocomplete && cfg.autocomplete[type], local = cfg.lists && cfg.lists[type], single = box.getAttribute('data-max') === '1';
    var timer = null, results = [], active = -1, seq = 0;
    function ids() { return $$('input[type=hidden]', box).map(function (i) { return i.value; }); }
    function addChip(id, label) {
      id = String(parseInt(id, 10));
      if (!/^\d+$/.test(id) || id === '0' || ids().indexOf(id) !== -1) { return; }
      if (single) { $$('.abp-chip', box).forEach(function (c) { c.parentNode.removeChild(c); }); }
      var chip = document.createElement('span'); chip.className = 'abp-chip';
      var txt = document.createElement('span'); txt.className = 'abp-chip-text'; txt.textContent = label; chip.appendChild(txt);
      var h = document.createElement('input'); h.type = 'hidden'; h.name = name; h.value = id; chip.appendChild(h);
      var x = document.createElement('button'); x.type = 'button'; x.className = 'abp-chip-x'; x.setAttribute('aria-label', (cfg.text.remove || 'Remove') + ': ' + label); x.innerHTML = '&times;'; chip.appendChild(x);
      box.insertBefore(chip, input);
      form.dispatchEvent(new Event('change'));
    }
    function close() { menu.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; }
    function render() {
      menu.innerHTML = '';
      if (!results.length) { var none = document.createElement('div'); none.className = 'abp-ac-none'; none.textContent = cfg.text.no_results; menu.appendChild(none); }
      results.forEach(function (r, i) {
        var o = document.createElement('div'); o.className = 'abp-ac-opt' + (i === active ? ' active' : ''); o.setAttribute('role', 'option'); o.textContent = r.name;
        o.addEventListener('mousedown', function (e) { e.preventDefault(); pick(i); });
        menu.appendChild(o);
      });
      menu.hidden = false; input.setAttribute('aria-expanded', 'true');
    }
    function pick(i) { var r = results[i]; if (r) { addChip(r.id, r.name); } input.value = ''; close(); input.focus(); }
    function search() {
      var q = input.value.trim(), my = ++seq;
      if (local && q.length >= 1) {
        // Short lists (countries) come with the page: searched here, by name or ISO code.
        var lq = q.toLowerCase();
        results = local.filter(function (r) { return String(r.name).toLowerCase().indexOf(lq) !== -1 || String(r.iso || '').toLowerCase() === lq; }).slice(0, 10).map(function (r) { return { id: r.id, name: r.name }; });
        active = results.length ? 0 : -1; render();
        return;
      }
      if (!url || q.length < 1) { close(); return; }
      var xhr = new XMLHttpRequest();
      xhr.open('GET', url + '&filter_name=' + encodeURIComponent(q));
      xhr.onload = function () {
        if (my !== seq) { return; }
        var list = []; try { list = JSON.parse(xhr.responseText); } catch (e) { list = []; }
        results = (Array.isArray(list) ? list : []).map(function (r) { return { id: r[type + '_id'], name: decode(r.name) }; }).filter(function (r) { return /^\d+$/.test(String(r.id)); }).slice(0, 10);
        active = results.length ? 0 : -1; render();
      };
      xhr.send();
    }
    on(input, 'input', function () { clearTimeout(timer); timer = setTimeout(search, 220); });
    on(input, 'keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); if (!menu.hidden && active >= 0) { pick(active); } return; }
      if (e.key === 'Escape') { close(); return; }
      if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && results.length) { e.preventDefault(); active = (active + (e.key === 'ArrowDown' ? 1 : -1) + results.length) % results.length; render(); }
      if (e.key === 'Backspace' && input.value === '') { var last = $$('.abp-chip', box).pop(); if (last) { last.parentNode.removeChild(last); form.dispatchEvent(new Event('change')); } }
    });
    on(input, 'blur', function () { setTimeout(close, 120); });
    on(box, 'click', function (e) {
      var x = e.target.closest('.abp-chip-x');
      if (x) { var chip = x.closest('.abp-chip'); chip.parentNode.removeChild(chip); form.dispatchEvent(new Event('change')); input.focus(); return; }
      if (e.target === box) { input.focus(); }
    });
  });

  /* ---------------- Free shipping per country / region: zones of the chosen country, more rows ---------------- */
  function loadZones(countrySel) {
    var row = countrySel.closest('tr'), zoneSel = row && $('.abp-zone-zone', row), id = countrySel.value;
    if (!zoneSel) { return; }
    var keep = zoneSel.value;
    zoneSel.length = 1; // "All regions"
    if (!cfg.zones || !/^\d+$/.test(id) || id === '0') { return; }
    var xhr = new XMLHttpRequest();
    xhr.open('GET', cfg.zones + '&country_id=' + encodeURIComponent(id));
    xhr.onload = function () {
      var list = []; try { list = JSON.parse(xhr.responseText); } catch (e) { list = []; }
      (Array.isArray(list) ? list : []).forEach(function (z) { var o = document.createElement('option'); o.value = String(parseInt(z.id, 10)); o.textContent = decode(z.name); zoneSel.appendChild(o); });
      zoneSel.value = keep && zoneSel.querySelector('option[value="' + keep + '"]') ? keep : '0';
    };
    xhr.send();
  }
  on(form, 'change', function (e) { if (e.target && e.target.classList && e.target.classList.contains('abp-zone-country')) { loadZones(e.target); } });
  $$('[data-abp-add-row]').forEach(function (btn) {
    on(btn, 'click', function () {
      var body = $(btn.getAttribute('data-abp-add-row') + ' tbody'), rows = body ? body.querySelectorAll('tr') : [];
      if (!rows.length || rows.length >= 50) { return; }
      var copy = rows[rows.length - 1].cloneNode(true), n = rows.length;
      $$('select, input', copy).forEach(function (el) {
        el.name = el.name.replace(/\[\d+\]/, '[' + n + ']');
        if (el.tagName === 'SELECT') { el.selectedIndex = 0; if (el.classList.contains('abp-zone-zone')) { el.length = 1; } } else { el.value = ''; }
      });
      body.appendChild(copy);
      var first = $('select', copy); if (first) { first.focus(); }
    });
  });

  /* ---------------- Background image (image manager writes the hidden field) + overlay ---------------- */
  var imageInput = $('#input-image'), imageLast = imageInput ? imageInput.value : '';
  if (imageInput) {
    setInterval(function () { if (imageInput.value !== imageLast) { imageLast = imageInput.value; preview(); } }, 600);
  }
  var overlay = $('#input-overlay'), overlayOut = $('#abp-overlay-out');
  on(overlay, 'input', function () { if (overlayOut) { overlayOut.textContent = overlay.value; } });

  /* ---------------- Templates: one click fills a new message ---------------- */
  var tplBox = $('#abp-templates'), dirty = false;
  on(form, 'input', function () { dirty = true; });
  function setField(name, value) {
    var els = form.querySelectorAll('[name="' + name + '"]');
    for (var i = 0; i < els.length; i++) {
      var el = els[i];
      if (el.type === 'checkbox' || el.type === 'radio') { el.checked = Array.isArray(value) ? value.indexOf(el.value) !== -1 || value.indexOf(parseInt(el.value, 10)) !== -1 : String(el.value) === String(value); } else { el.value = value == null ? '' : value; }
      if (el.type === 'text' && el.closest('.abp-color')) { var pick = $('input[type=color]', el.closest('.abp-color')); if (pick && hex.test(el.value)) { pick.value = el.value; } }
    }
  }
  function applyTemplate(t) {
    var d = new Date(Date.now() + (t.end_days || 0) * 86400000), p2 = function (n) { return (n < 10 ? '0' : '') + n; };
    setField('type', t.type || 'standard');
    setField('threshold', t.threshold || '');
    setField('icon', t.icon || '');
    setField('bg_color', t.bg || '#222222');
    setField('text_color', t.fg || '#ffffff');
    setField('style', t.style || '');
    setField('coupon', t.coupon || '');
    setField('countdown', t.countdown ? '1' : '');
    setField('date_end', t.end_days ? d.getFullYear() + '-' + p2(d.getMonth() + 1) + '-' + p2(d.getDate()) + ' 23:59' : '');
    setField('end_all_day', t.end_days ? '1' : '');
    setField('opt[recur]', t.recur || '');
    setField('opt[recur_time]', t.recur_time || '');
    setField('opt[recur_skip][]', String(t.recur_skip || '').split(''));
    setField('opt[email]', t.email ? '1' : '');
    setField('opt[trigger]', t.trigger || 'none');
    setField('placement', t.placement || '');
    setField('weekdays[]', (t.weekdays || []).map(String));
    var tiers = form.querySelectorAll('[name="opt[tiers][]"]');
    for (var i = 0; i < tiers.length; i++) { tiers[i].value = t.tiers && t.tiers[i] ? t.tiers[i] : ''; }
    Object.keys(t.texts || {}).forEach(function (lid) {
      var x = t.texts[lid];
      ['text', 'success_text', 'link_text'].forEach(function (f) { setField('message_description[' + lid + '][' + f + ']', x[f] || ''); });
      var labels = form.querySelectorAll('[name="message_description[' + lid + '][tier_label][]"]');
      for (var j = 0; j < labels.length; j++) { labels[j].value = x.tier_label && x.tier_label[j] ? x.tier_label[j] : ''; }
    });
    dirty = false;
    form.dispatchEvent(new Event('change'));
  }
  if (tplBox && Array.isArray(cfg.templates)) {
    cfg.templates.forEach(function (t) {
      var b = document.createElement('button'), sw = document.createElement('span'), name = document.createElement('span');
      b.type = 'button'; b.className = 'abp-tpl'; b.setAttribute('role', 'listitem');
      sw.className = 'abp-tpl-sw'; sw.style.background = t.bg || '#222'; sw.style.color = t.fg || '#fff';
      var first = t.texts && t.texts[Object.keys(t.texts)[0]];
      sw.textContent = first ? String(first.text || '').replace(/\{[a-z]+\}/g, '…') : '';
      if (t.icon) { var ic = document.createElement('i'); ic.className = iconClass(t.icon) || ''; ic.setAttribute('aria-hidden', 'true'); sw.insertBefore(ic, sw.firstChild); }
      name.className = 'abp-tpl-name'; name.textContent = t.name;
      b.appendChild(sw); b.appendChild(name);
      on(b, 'click', function () {
        if (dirty && !window.confirm(cfg.text.confirm_template || '?')) { return; }
        applyTemplate(t);
        $$('.abp-tpl', tplBox).forEach(function (o) { o.classList.toggle('abp-tpl-on', o === b); o.setAttribute('aria-current', o === b ? 'true' : 'false'); });
      });
      tplBox.appendChild(b);
    });
  }

  /* ---------------- LIVE PREVIEW ---------------- */
  var frame = $('#abp-preview-frame'), pv = { device: 'desktop', variant: 'a', cart: 'below' };
  $$('[data-abp-pv]').forEach(function (b) {
    on(b, 'click', function () {
      var k = b.getAttribute('data-abp-pv'), v = b.getAttribute('data-value');
      pv[k] = v;
      $$('[data-abp-pv="' + k + '"]').forEach(function (o) { var a = o === b; o.classList.toggle('active', a); o.setAttribute('aria-pressed', a ? 'true' : 'false'); });
      preview();
    });
  });
  function plain(s) { return String(s || '').replace(/<br\s*\/?>/gi, ' ').replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim(); }
  function money(n) {
    var c = cfg.currency || {}, d = c.decimals || 0, parts = Math.abs(n).toFixed(d).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, c.thousand || '');
    return (c.left || '') + parts.join(c.point || '.') + (c.right || '');
  }
  function langVal(f) { return val('message_description[' + lang + '][' + f + ']'); }
  function langList(f) { return $$('[name="message_description[' + lang + '][' + f + '][]"]').map(function (el) { return el.value; }); }
  // Next daily / weekly cut-off for the preview countdown (admin clock; the shop uses its own).
  function nextCut() {
    var t = /^(\d{2}):(\d{2})$/.exec(val('opt[recur_time]') || ''), now = new Date();
    if (!t) { return 0; }
    var skip = $$('[name="opt[recur_skip][]"]').filter(function (c) { return c.checked; }).map(function (c) { return c.value; }).join(''), weekly = val('opt[recur]') === 'weekly';
    for (var i = 0; i < 8; i++) {
      var d = new Date(now.getFullYear(), now.getMonth(), now.getDate() + i, parseInt(t[1], 10), parseInt(t[2], 10)), dow = String(d.getDay() || 7);
      if (d > now && (weekly ? dow === String(val('opt[recur_day]')) : skip.indexOf(dow) === -1)) { return d.getTime(); }
    }
    return 0;
  }
  function state() {
    var ab = checked('ab_test'), b = ab && pv.variant === 'b', type = val('type'), fs = type === 'free_shipping' || type === 'tiers';
    var t = plain(b ? langVal('text_b') : langVal('text')), m = plain(b ? langVal('mobile_text_b') : langVal('mobile_text'));
    var bt = plain(b ? (langVal('link_text_b') || langVal('link_text')) : langVal('link_text'));
    var amount = checked('opt[fs_oc]') ? (cfg.oc_free || 0) : parseFloat(String(val('threshold')).replace(',', '.')) || 0;
    var done = fs && pv.cart === 'done', success = plain(langVal('success_text'));
    // Tiered goals: rows with an amount; the preview shows the first tier as the next goal.
    var tierAmounts = $$('[name="opt[tiers][]"]').map(function (el) { return parseFloat(String(el.value).replace(',', '.')) || 0; });
    var labels = langList('tier_label'), texts = langList('tier_text'), steps = [], top = 0;
    tierAmounts.forEach(function (a, i) { if (a > 0) { steps.push({ a: a, label: plain(labels[i] || ''), text: plain(texts[i] || '') }); top = Math.max(top, a); } });
    if (type === 'tiers') { amount = steps.length ? steps[0].a : 0; }
    var bar = cfg.bar || {};
    var style = val('style') || bar.style || 'solid';
    var end = 0, de = String(val('date_end') || '').replace('T', ' ');
    if (/^\d{4}-\d{2}-\d{2}/.test(de)) { end = new Date(de.length > 10 ? de.replace(' ', 'T') : de + 'T23:59:59').getTime() || 0; }
    if (!end || end < Date.now()) { end = Date.now() + (2 * 86400 + 5 * 3600 + 17 * 60) * 1000; }
    var recur = val('opt[recur]'), cut = recur ? nextCut() : 0;
    var coupon = val('coupon'), chip = $('#input-coupon-id .abp-chip-text');
    if (chip) { coupon = String(chip.textContent).split(' ')[0]; }
    var img = val('opt[image]'), bg = hex.test(val('bg_color')) ? val('bg_color') : '#222222';
    var ov = Math.max(0, Math.min(90, parseInt(val('opt[overlay]'), 10) || 0)) / 100, rgb = [1, 3, 5].map(function (i) { return parseInt(bg.substr(i, 2), 16); }).join(',');
    var tierText = type === 'tiers' && steps[0] && steps[0].text && !done ? steps[0].text : '';
    var text = tierText || t;
    return {
      type: type, steps: type === 'tiers' ? steps.map(function (s) { return { at: top ? Math.round(s.a * 100 / top) : 0, label: s.label, done: done }; }) : [],
      goal: type === 'tiers' && steps[0] ? steps[0].label : '', recur: recur && cut ? (recur === 'weekly' ? 'w|' + val('opt[recur_time]') + '|' + val('opt[recur_day]') : 'd|' + val('opt[recur_time]') + '|') : '',
      email: checked('opt[email]') ? { ph: plain(langVal('email_placeholder')) || cfg.text.email_placeholder, btn: plain(langVal('email_button')) || cfg.text.email_button, consent: plain(langVal('email_consent')) || cfg.text.email_consent } : null,
      image: img && cfg.image_url ? 'background-image:linear-gradient(rgba(' + rgb + ',' + ov + '),rgba(' + rgb + ',' + ov + ')),url(&quot;' + esc(cfg.image_url + img) + '&quot;);background-size:cover;background-position:center;' : '',
      font: val('opt[font]'), placement: val('placement') || (cfg.bar && cfg.bar.position) || 'top', trigger: val('opt[trigger]'), triggerValue: val('opt[trigger_value]'),
      text: text || (cfg.text.sample || 'Example'), mobile: tierText ? '' : m, button: val('link') ? bt : '', icon: iconClass(val('icon')),
      coupon: /^[A-Z0-9_-]{1,32}$/.test(coupon) ? coupon : '', btnStyle: val('button_style') || 'filled', style: style,
      bg: bg, fg: hex.test(val('text_color')) ? val('text_color') : '#ffffff',
      countdown: checked('countdown') ? (recur ? cut : end) : 0, fs: fs, done: done && success !== '', success: success,
      remaining: money(amount * 0.6), pct: done ? 100 : (type === 'tiers' && top ? Math.round(amount * 0.4 * 100 / top) : 40), progress: checked('progress_bar'), bar: bar
    };
  }
  function barHtml(s) {
    var b = s.bar, cd = s.countdown ? '<span class="abp-countdown" data-end="' + s.countdown + '"' + (s.recur ? ' data-recur="' + esc(s.recur) + '"' : '') + '></span>' : '';
    var fill = function (t) {
      var x = esc(t);
      if (s.fs) { x = x.replace('{remaining}', '<span class="abp-remaining">' + esc(s.remaining) + '</span>').replace('{goal}', '<span class="abp-goal">' + esc(s.goal) + '</span>'); }
      if (cd && x.indexOf('{countdown}') !== -1) { x = x.replace('{countdown}', cd); return { h: x, cd: true }; }
      return { h: x.replace(/\{countdown\}/g, ''), cd: false };
    };
    var d = s.done ? { h: esc(s.success), cd: false } : fill(s.text), m = s.done || !s.mobile ? null : fill(s.mobile);
    var txt = m ? '<span class="abp-text abp-text-d">' + d.h + (cd && !d.cd ? ' ' + cd : '') + '</span><span class="abp-text abp-text-m">' + m.h + (cd && !m.cd ? ' ' + cd : '') + '</span>'
      : '<span class="abp-text">' + d.h + '</span>' + (cd && !d.cd && !s.done ? ' ' + cd : '');
    var icon = s.icon ? '<i class="' + esc(s.icon) + ' abp-ico" aria-hidden="true"></i> ' : '';
    var btn = s.button ? '<a class="abp-btn abp-btn-' + esc(s.btnStyle) + '" href="#" onclick="return false">' + esc(s.button) + '</a>' : '';
    var copy = s.coupon ? '<span class="abp-coupon"><span class="abp-code">' + esc(s.coupon) + '</span><button type="button" class="abp-copy" data-code="' + esc(s.coupon) + '" data-label="' + esc(cfg.text.copy) + '" data-copied="✓"><span class="abp-copy-label">' + esc(cfg.text.copy) + '</span></button><span class="abp-sr" aria-live="polite"></span></span>' : '';
    var steps = s.steps.length ? s.steps.map(function (st, i) { return '<span class="abp-step' + (st.done ? ' abp-step-done' : '') + (i === s.steps.length - 1 ? ' abp-step-last' : '') + '" style="left:' + st.at + '%;"><span class="abp-step-label">' + esc(st.label) + '</span></span>'; }).join('') : '';
    var prog = s.fs && s.progress ? '<span class="abp-progress' + (steps ? ' abp-steps' : '') + '"><span class="abp-progress-fill" style="width:' + s.pct + '%"></span>' + steps + '</span>' : '';
    var form = s.email ? '<form class="abp-form" action="#" onsubmit="return false" novalidate><span class="abp-fields"><input type="email" class="abp-email" placeholder="' + esc(s.email.ph) + '" aria-label="' + esc(s.email.ph) + '"><button type="submit" class="abp-submit">' + esc(s.email.btn) + '</button></span><label class="abp-consent"><input type="checkbox"> <span>' + esc(s.email.consent) + (b.privacy ? ' <a href="#" onclick="return false">' + esc(cfg.text.privacy) + '</a>' : '') + '</span></label></form>' : '';
    var inline = /^(cart|product|minicart)$/.test(s.placement), bottom = s.placement === 'bottom';
    var close = b.dismissible && !inline ? '<button type="button" class="abp-close" aria-label="' + esc(cfg.text.close) + '"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 3.5l9 9m0-9-9 9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button>' : '';
    var pad = (b.padding == null ? 8 : b.padding) + 'px';
    var cls = inline ? 'abp abp-inline abp-inline-' + s.placement + ' abp-mode-stack' : 'abp abp-bar abp-pos-' + (bottom ? 'bottom' : 'top') + ' abp-mode-rotate abp-tr-' + esc(b.transition || 'fade') + (b.dismissible ? ' abp-has-close' : '');
    return '<div id="abp-bar" class="' + cls + (b.font ? ' abp-font-' + esc(b.font) : '') + '" data-abp="' + (inline ? 'inline' : 'bar') + '" data-place="' + esc(s.placement) + '" data-mode="' + (inline ? 'stack' : 'rotate') + '" data-interval="5" data-now="' + Date.now() + '" data-tz="' + (-new Date().getTimezoneOffset() * 60) + '" data-color="custom" data-dismissible="0" data-days-label="d" style="font-size:' + (b.font_size || 14) + 'px;background-color:' + s.bg + ';color:' + s.fg + ';">'
      + '<div class="abp-viewport"><div class="abp-track"><div class="abp-item abp-active" data-id="0" data-key="000000000000" data-style="' + esc(s.style) + '" data-c="' + s.bg + '" data-t="' + s.fg + '"' + (s.fs ? ' data-fs="1"' : '') + (s.font ? ' data-font="' + esc(s.font) + '"' : '') + (s.image ? ' data-img="1"' : '') + ' style="background-color:' + s.bg + ';color:' + s.fg + ';--abp-btn-bg:' + s.fg + ';--abp-btn-fg:' + s.bg + ';--abp-accent:' + s.fg + ';padding-top:' + pad + ';padding-bottom:' + pad + ';' + s.image + '">'
      + '<div class="abp-inner"><span class="abp-v abp-v-a"><span class="abp-body">' + icon + '<span class="abp-txt">' + txt + '</span></span>' + btn + '</span>' + copy + form + '</div>' + prog + '</div></div></div>' + close + '</div>';
  }
  var page = '<style>html,body{margin:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;color:#1f2937}'
    + '.pv-head{display:flex;align-items:center;gap:14px;padding:14px 18px;background:#fff;border-bottom:1px solid #e5e7eb}.pv-logo{width:84px;height:18px;border-radius:5px;background:#d1d5db}.pv-nav{flex:1;display:flex;gap:10px;justify-content:flex-end}.pv-nav i{display:block;width:46px;height:9px;border-radius:5px;background:#e5e7eb}'
    + '.pv-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px;padding:16px 18px}.pv-card{height:70px;border-radius:10px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.05)}'
    + '.pv-inline{padding:14px 18px 0;max-width:640px}.pv-cap{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#6b7280;margin-bottom:6px}</style>';
  var skeleton = '<div class="pv-head"><div class="pv-logo"></div><div class="pv-nav"><i></i><i></i><i></i></div></div><div class="pv-grid"><div class="pv-card"></div><div class="pv-card"></div><div class="pv-card"></div><div class="pv-card"></div><div class="pv-card"></div><div class="pv-card"></div></div>';
  var pvTimer = null, lastDoc = '';
  // The preview frame is sandboxed (opaque origin), so the browser refuses the shop's icon font there without a CORS
  // header. Load the Font Awesome CSS here (same origin), embed its woff2 fonts as data: URLs and pass the CSS inline.
  var faCss = null;
  function loadFa() {
    if (!cfg.assets || !cfg.assets.fa || !window.fetch || !window.FileReader || faCss !== null) { return; }
    faCss = '';
    var base = new URL(cfg.assets.fa, document.baseURI);
    fetch(base.href, { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.text() : ''; }).then(function (css) {
      if (!css) { return; }
      var urls = [], re = /url\(\s*['"]?([^'")]+\.woff2)(?:[?#][^'")]*)?['"]?\s*\)/g, m;
      while ((m = re.exec(css)) && urls.length < 8) { if (urls.indexOf(m[1]) < 0) { urls.push(m[1]); } }
      return Promise.all(urls.map(function (u) {
        var abs = new URL(u, base);
        if (abs.origin !== location.origin) { return null; }
        return fetch(abs.href, { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.blob() : null; }).then(function (b) {
          if (!b) { return null; }
          return new Promise(function (ok) { var fr = new FileReader(); fr.onload = function () { ok([u, String(fr.result).replace(/^data:[^;,]*/, 'data:font/woff2')]); }; fr.onerror = function () { ok(null); }; fr.readAsDataURL(b); });
        });
      })).then(function (pairs) {
        var out = css.replace(/url\(\s*['"]?([^'")]+?)(?:[?#][^'")]*)?['"]?\s*\)/g, function (all, u) {
          for (var i = 0; i < pairs.length; i++) { if (pairs[i] && pairs[i][0] === u) { return 'url(' + pairs[i][1] + ')'; } }
          return /^data:/.test(u) ? all : 'url(' + new URL(u, base).href + ')';
        });
        if (pairs.some(function (p) { return p; })) { faCss = out.replace(/<\/style/gi, ''); lastDoc = ''; render(); }
      });
    })['catch'](function () { /* keep the <link>: icons may stay empty, the preview still works */ });
  }
  function render() {
    if (!frame || !cfg.assets) { return; }
    loadFa();
    var s = state();
    var bh = barHtml(s), inlinePv = /^(cart|product|minicart)$/.test(s.placement);
    var doc = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="' + esc(cfg.assets.css) + '">'
      + (faCss ? '<style>' + faCss + '</style>' : '') /* icon font only once embedded: a <link> hits CORS in the sandboxed frame */ + page + '</head><body>' + (inlinePv ? '' : bh)
      + '<script src="' + esc(cfg.assets.pre) + '"><\/script>' + skeleton + '<script src="' + esc(cfg.assets.js) + '"><\/script></body></html>';
    // Inline boxes are shown in a mock content column; a bottom bar sits at the bottom of the frame.
    if (inlinePv) {
      doc = doc.replace('<div class="pv-grid">', '<div class="pv-inline"><div class="pv-cap">' + esc(cfg.text.placement_inline || '') + '</div>' + bh + '</div><div class="pv-grid">');
    }
    var wrap = $('#abp-preview-wrap');
    if (wrap) { wrap.classList.toggle('abp-pv-phone', pv.device === 'phone'); }
    if (doc !== lastDoc) { lastDoc = doc; frame.srcdoc = doc; }
    var note = $('#abp-contrast');
    if (note) { note.hidden = !(s.style === 'solid' && hex.test(s.bg) && hex.test(s.fg) && contrast(s.bg, s.fg) < 4.5); }
    var ip = $('#abp-icon-preview');
    if (ip) { ip.className = s.icon || (cfg.oc === 3 ? 'fa fa-question' : 'fa-solid fa-question'); }
    $$('[data-abp-pv="variant"]').forEach(function (x) { x.disabled = !checked('ab_test'); });
    if (!checked('ab_test') && pv.variant === 'b') { pv.variant = 'a'; $$('[data-abp-pv="variant"]').forEach(function (x) { x.classList.toggle('active', x.getAttribute('data-value') === 'a'); }); }
    var cartTools = $('#abp-pv-cart');
    if (cartTools) { cartTools.hidden = !s.fs; }
    var extra = $('#abp-pv-extra'), opt = $('#input-trigger');
    if (extra) { extra.textContent = s.trigger && s.trigger !== 'none' && opt && cfg.text.trigger_note ? cfg.text.trigger_note.replace('%s', (opt.options[opt.selectedIndex] ? opt.options[opt.selectedIndex].text.toLowerCase() : '') + (s.triggerValue && s.trigger !== 'exit' ? ' (' + s.triggerValue + (s.trigger === 'scroll' ? ' %' : ' s') + ')' : '')) : ''; }
    if (wrap) { wrap.classList.toggle('abp-pv-bottom', s.placement === 'bottom'); }
  }
  function preview() { clearTimeout(pvTimer); pvTimer = setTimeout(function () { dependents(); render(); }, 150); }

  on(form, 'input', preview);
  on(form, 'change', preview);
  dependents();
  render();
  showError();

  // OpenCart 4 saves with AJAX: after a validation error show the language / section of the first error.
  if (window.jQuery) { window.jQuery(document).ajaxComplete(function () { setTimeout(showError, 80); }); }
})();
