/* Announcement Bar Pro — Premium: box in the WooCommerce Mini-Cart BLOCK.
   The block draws its drawer in the browser (older WooCommerce: again every time it opens; newer: once, then switches
   between the filled and the empty cart), so the box is printed once in <template id="abp-minicart-block-tpl"> and a
   copy is kept in the drawer: in a filled cart above the product list, in an empty cart at the top. The copy is started
   by the shared storefront script (AnnouncementBarPro.scan) and gets the current cart amounts (refreshCart). */
(function () {
  'use strict';

  var tpl = document.getElementById('abp-minicart-block-tpl');
  if (!tpl || !('content' in tpl) || !window.MutationObserver) { return; }

  function shown(el) { return !!el && !el.hidden && !el.closest('[hidden]') && el.getClientRects().length > 0; }

  var queued = false;
  function place() {
    queued = false;
    var added = false;
    Array.prototype.forEach.call(document.querySelectorAll('.wc-block-mini-cart__drawer'), function (drawer) {
      if (drawer.classList.contains('is-loading')) { return; }
      var items = drawer.querySelector('.wc-block-mini-cart__items');
      var empty = drawer.querySelector('.wp-block-woocommerce-empty-mini-cart-contents-block');
      var parent, before;
      if (empty && shown(empty)) {
        parent = empty; before = empty.firstChild;
      } else if (items && items.parentNode) {
        parent = items.parentNode; before = items;
      } else {
        return;
      }
      var box = drawer.querySelector('.abp-inline-minicart');
      if (box) {
        // The cart became empty / filled without a new drawer: move the box to the part that is shown.
        if (box.parentNode !== parent) { parent.insertBefore(box, before === box ? box.nextSibling : before); }
        return;
      }
      parent.insertBefore(tpl.content.cloneNode(true), before);
      added = true;
    });
    if (added && window.AnnouncementBarPro) {
      window.AnnouncementBarPro.scan();
      window.AnnouncementBarPro.refreshCart();
    }
  }
  function queue() {
    if (!queued) { queued = true; setTimeout(place, 50); }
  }
  new MutationObserver(queue).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden'] });
  queue();
})();
