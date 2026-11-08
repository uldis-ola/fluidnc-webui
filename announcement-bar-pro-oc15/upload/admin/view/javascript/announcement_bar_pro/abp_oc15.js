/* Announcement Bar Pro v2.4.1 — OpenCart 1.5 admin adapter. The shared admin script (abp_admin.js) needs no framework;
   this file adds what Bootstrap does in the newer admins: tabs, closable alerts, the date + time picker (jQuery UI
   timepicker of OpenCart 1.5) and OpenCart 1.5's image manager (jQuery UI dialog). jQuery 1.7 is only used where
   OpenCart 1.5 itself needs it (dialog, picker). */
(function () {
  'use strict';
  var root = document.querySelector('.abp15');
  if (!root) { return; }

  function closest(el, sel) {
    while (el && el.nodeType === 1) {
      if ((el.matches || el.msMatchesSelector || el.webkitMatchesSelector).call(el, sel)) { return el; }
      el = el.parentNode;
    }
    return null;
  }

  /* ---------------- Tabs (data-toggle="tab") ---------------- */
  function showTab(link) {
    var id = link.getAttribute('href');
    var pane = id && id.charAt(0) === '#' ? document.getElementById(id.slice(1)) : null;
    if (!pane) { return; }
    var nav = closest(link, '.nav-tabs');
    Array.prototype.forEach.call(nav.querySelectorAll('li'), function (li) { li.classList.remove('active'); });
    Array.prototype.forEach.call(nav.querySelectorAll('[data-toggle="tab"]'), function (a) { a.setAttribute('aria-selected', 'false'); a.setAttribute('tabindex', '-1'); });
    link.parentNode.classList.add('active');
    link.setAttribute('aria-selected', 'true');
    link.removeAttribute('tabindex');
    Array.prototype.forEach.call(pane.parentNode.children, function (p) { if (p.classList.contains('tab-pane')) { p.classList.remove('active'); } });
    pane.classList.add('active');
  }
  Array.prototype.forEach.call(root.querySelectorAll('.nav-tabs'), function (nav) {
    nav.setAttribute('role', 'tablist');
    Array.prototype.forEach.call(nav.querySelectorAll('[data-toggle="tab"]'), function (a) {
      var active = a.parentNode.classList.contains('active');
      a.setAttribute('role', 'tab');
      a.setAttribute('aria-selected', active ? 'true' : 'false');
      if (!active) { a.setAttribute('tabindex', '-1'); }
    });
  });
  root.addEventListener('click', function (e) {
    var link = closest(e.target, '[data-toggle="tab"]');
    if (link) { e.preventDefault(); showTab(link); return; }
    var close = closest(e.target, '[data-dismiss="alert"]');
    if (close) { e.preventDefault(); var box = closest(close, '.alert'); if (box) { box.parentNode.removeChild(box); } }
  });
  root.addEventListener('keydown', function (e) {
    var link = closest(e.target, '[data-toggle="tab"]');
    if (!link || (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft')) { return; }
    var all = Array.prototype.slice.call(closest(link, '.nav-tabs').querySelectorAll('[data-toggle="tab"]'));
    var next = all[(all.indexOf(link) + (e.key === 'ArrowRight' ? 1 : all.length - 1)) % all.length];
    showTab(next);
    next.focus();
    e.preventDefault();
  });
  // A validation error on a hidden tab: show the tab that has it.
  var firstError = root.querySelector('.tab-pane .text-danger, .tab-pane .has-error');
  if (firstError) {
    var pane = closest(firstError, '.tab-pane');
    var link = pane && root.querySelector('[data-toggle="tab"][href="#' + pane.id + '"]');
    if (link && !pane.classList.contains('active')) { showTab(link); }
  }

  var $ = window.jQuery;

  /* ---------------- Date + time (YYYY-MM-DD HH:mm) ---------------- */
  if ($ && $.fn.datetimepicker) {
    $('.abp15 .datetime input[type=text]').each(function () {
      var input = $(this);
      input.datetimepicker({ dateFormat: 'yy-mm-dd', timeFormat: 'hh:mm', separator: ' ' });
      input.closest('.datetime').find('.input-group-btn button').on('click', function () { input.datetimepicker('show'); });
    });
  } else {
    Array.prototype.forEach.call(root.querySelectorAll('.datetime .input-group-btn button'), function (b) {
      b.addEventListener('click', function () { var i = closest(b, '.datetime').querySelector('input'); if (i) { i.focus(); } });
    });
  }

  /* ---------------- Image manager (OpenCart 1.5: common/filemanager in a jQuery UI dialog) ---------------- */
  Array.prototype.forEach.call(root.querySelectorAll('[data-abp15-image]'), function (thumb) {
    var field = thumb.getAttribute('data-abp15-image');
    var input = document.getElementById(field);
    var img = thumb.querySelector('img');
    thumb.addEventListener('click', function (e) {
      e.preventDefault();
      if (!$ || !$.fn.dialog) { return; }
      $('#dialog').remove();
      $('#content').prepend('<div id="dialog" style="padding: 3px 0px 0px 0px;"><iframe src="' + thumb.getAttribute('data-manager') + '&field=' + encodeURIComponent(field) + '" style="padding:0; margin: 0; display: block; width: 100%; height: 100%;" frameborder="no" scrolling="auto"></iframe></div>');
      $('#dialog').dialog({
        title: thumb.getAttribute('data-title') || '',
        close: function () {
          if (input && input.value) {
            $.ajax({
              url: thumb.getAttribute('data-thumb-url') + '&image=' + encodeURIComponent(input.value),
              dataType: 'text',
              success: function (src) { if (img && src) { img.src = src; } }
            });
          }
        },
        bgiframe: false, width: 800, height: 400, resizable: false, modal: false
      });
    });
  });
  Array.prototype.forEach.call(root.querySelectorAll('[data-abp15-clear]'), function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-abp15-clear'));
      var img = root.querySelector('[data-abp15-image="' + btn.getAttribute('data-abp15-clear') + '"] img');
      if (input) { input.value = ''; }
      if (img && img.getAttribute('data-placeholder')) { img.src = img.getAttribute('data-placeholder'); }
    });
  });
})();
