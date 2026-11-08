/* Announcement Bar Pro v2.4.1 — OpenCart 1.5 adapter (the shared script announcement_bar_pro.js stays identical in
   every edition). OpenCart 1.5 reloads the mini-cart with jQuery ".load('index.php?route=module/cart #cart > *')";
   after such a reload the new mini-cart box is started, and the bars refresh their cart amounts. No framework needed:
   jQuery (1.7 in OpenCart 1.5) is only used when the page has it. */
(function () {
  'use strict';
  var hooked = false;
  function hook() {
    if (hooked || !window.jQuery) { return; }
    hooked = true;
    window.jQuery(document).ajaxComplete(function (e, xhr, settings) {
      var url = settings && typeof settings.url === 'string' ? settings.url : '';
      if (/route=module(\/|%2F)cart(&|$)/i.test(url) && window.AnnouncementBarPro) {
        setTimeout(function () {
          try { window.AnnouncementBarPro.scan(); window.AnnouncementBarPro.refreshCart(); } catch (err) { /* the shop keeps working */ }
        }, 60);
      }
    });
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', hook); } else { hook(); }
  window.addEventListener('load', hook);
})();
