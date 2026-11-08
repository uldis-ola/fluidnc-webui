/* Announcement Bar Pro Embed — cart adapter for Shopify (optional; only for free-shipping progress messages).
   Paste it into theme.liquid inside <script>…</script> (or upload it as a theme asset and load it with
   {{ 'cart-adapter-shopify.js' | asset_url | script_tag }}), next to the bar's own script.

   What it does: reads the cart from your shop's own /cart.js (the same Shopify store, no third party) when the page
   loads and after every AJAX cart change the theme makes (/cart/add, /cart/change, /cart/update, /cart/clear), and
   passes {subtotal, currency, count} to the bar. It wraps window.fetch and XMLHttpRequest only to notice those cart
   URLs; every request is passed through unchanged.
   Amount used: total_price (after discounts, before shipping and taxes), in the cart's (presentment) currency. To use
   the subtotal before cart discounts instead, change total_price to items_subtotal_price below. */
(function () {
  'use strict';
  var root = (window.Shopify && window.Shopify.routes && window.Shopify.routes.root) || '/';
  var CART = /\/cart\/(add|change|update|clear)(\.js)?(\?|$)/;
  function send(c) {
    var cart = { subtotal: (c.total_price || 0) / 100, currency: c.currency, count: c.item_count };
    if (window.ABP && window.ABP.setCart) { window.ABP.setCart(cart); } else { window.abpCart = cart; } // the bar reads it when it starts
  }
  function load() {
    if (!window.fetch) { return; }
    fetchCart(root + 'cart.js', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (c) { if (c) { send(c); } })
      .catch(function () { /* no cart: free-shipping messages stay hidden */ });
  }
  var later = function () { setTimeout(load, 60); };
  var fetchCart = window.fetch ? window.fetch.bind(window) : null;
  if (window.fetch) {
    var original = window.fetch;
    window.fetch = function (input) {
      var p = original.apply(this, arguments);
      try {
        var u = typeof input === 'string' ? input : input && input.url;
        if (u && CART.test(u)) { p.then(later, function () {}); }
      } catch (e) { /* never break the theme */ }
      return p;
    };
  }
  var open = XMLHttpRequest.prototype.open;
  XMLHttpRequest.prototype.open = function (method, url) {
    try { if (CART.test(String(url))) { this.addEventListener('loadend', later); } } catch (e) { /* never break the theme */ }
    return open.apply(this, arguments);
  };
  document.addEventListener('cart:updated', later); // themes that announce their own cart changes
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', load); } else { load(); }
})();
