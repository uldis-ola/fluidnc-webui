/* Announcement Bar Pro Embed — generic cart adapter (optional; only for free-shipping progress messages).
   The bar never reads your cart by itself. Your shop's own code tells it what is in the cart:

     abpSetCart({ subtotal: 42.50, currency: 'EUR', count: 3 });

   - subtotal: number in the currency's main unit (42.50, not 4250 cents), the amount your free-shipping rule uses;
   - currency: ISO code (EUR, USD, GBP …); the message needs a threshold for that currency, amounts are never converted;
   - count:    number of items (optional).
   Call it once when the page loads (if you know the cart then) and again after every cart change. Before the first
   call, free-shipping messages stay hidden. It may run before or after the bar's script. */
window.abpSetCart = function (cart) {
  if (window.ABP && window.ABP.setCart) { window.ABP.setCart(cart); } else { window.abpCart = cart; } // the bar reads it when it starts
};

/* Example 1 — the cart total is printed into the page by your platform's template (replace the placeholders):
   abpSetCart({ subtotal: {{ cart_subtotal }}, currency: '{{ currency_code }}', count: {{ cart_count }} });

   Example 2 — your platform has a JSON endpoint for the cart (same site); adapt the field names:
   fetch('/api/cart', { credentials: 'same-origin' })
     .then(function (r) { return r.json(); })
     .then(function (c) { abpSetCart({ subtotal: c.subtotal, currency: c.currency, count: c.items.length }); });

   Example 3 — your "add to cart" code already knows the new total:
   document.addEventListener('my-shop:cart-changed', function (e) { abpSetCart(e.detail); }); */
