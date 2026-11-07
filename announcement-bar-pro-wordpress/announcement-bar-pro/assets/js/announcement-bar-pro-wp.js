/* Announcement Bar Pro for WordPress — connects the shared storefront script (window.AnnouncementBarPro) to
   WooCommerce: classic cart events (jQuery), cart / mini-cart fragments, the cart and checkout blocks (Store API data
   store), and to the WP Consent API. No data leaves the site. */
(function () {
  'use strict';

  function api() { return window.AnnouncementBarPro || null; }
  function refreshCart() { var a = api(); if (a && a.refreshCart) { a.refreshCart(); } }
  function rescan() { var a = api(); if (a && a.scan) { setTimeout(function () { a.scan(); }, 60); } }

  /* Classic WooCommerce: events are triggered on document.body with jQuery. */
  if (window.jQuery) {
    window.jQuery(document.body).on('added_to_cart removed_from_cart updated_cart_totals updated_wc_div wc_cart_emptied updated_shipping_method updated_checkout', refreshCart);
    // The mini-cart HTML (with its box) is replaced by refreshed fragments: start the new box, refresh amounts.
    window.jQuery(document.body).on('wc_fragments_refreshed wc_fragments_loaded', rescan);
  }

  /* Blocks: add-to-cart buttons fire these DOM events; the cart / checkout blocks change the cart through the Store
     API, which is watched through its data store (totals or item count changed → refresh). */
  ['wc-blocks_added_to_cart', 'wc-blocks_removed_from_cart'].forEach(function (name) { document.body.addEventListener(name, refreshCart); });
  function watchStore() {
    var data = window.wp && window.wp.data;
    if (!data || typeof data.subscribe !== 'function' || typeof data.select !== 'function') { return; }
    var last = null;
    data.subscribe(function () {
      try {
        var store = data.select('wc/store/cart');
        if (!store || typeof store.getCartTotals !== 'function') { return; }
        var t = store.getCartTotals(), key = t ? [t.total_items, t.total_price, t.currency_code].join('|') : '';
        if (last !== null && key !== last) { refreshCart(); }
        last = key;
      } catch (e) { /* the store is not there on this page */ }
    });
  }

  /* WP Consent API: "Wait for cookie consent" ends once the visitor allows statistics. */
  function consent() {
    try {
      if (typeof window.wp_has_consent === 'function' && window.wp_has_consent('statistics') && window.abpConsent !== true) {
        window.abpConsent = true;
        document.dispatchEvent(new Event('abp:consent'));
      }
    } catch (e) { /* consent tool not ready */ }
  }
  document.addEventListener('wp_listen_for_consent_change', consent);

  function start() {
    watchStore();
    consent();
    // Full-page caches can serve an older cart state: refresh once when WooCommerce says the cart has items.
    if (/(^|;\s*)woocommerce_items_in_cart=1/.test(document.cookie) && document.querySelector('.abp[data-cart]')) { refreshCart(); }
  }
  if (document.readyState === 'complete') { start(); } else { window.addEventListener('load', start); }
})();
