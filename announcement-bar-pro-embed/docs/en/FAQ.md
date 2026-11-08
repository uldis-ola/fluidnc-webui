# FAQ

**Does it send any data to you or to another server?**
No. The script only loads its own stylesheet and — if you choose option B — your own JSON file. There is no account,
no tracking, no licence check. GA4 events are sent only by your own GA4 / GTM, only if you switch them on.

**Which platforms are supported?**
Any site where you can add a script before `</body>`: Shopify, Wix (Custom Code), Squarespace (Code Injection),
Webflow, Tilda, WordPress, static HTML, other CMSs. Tested in Chrome on local test pages, including a page that imitates
a Shopify theme and a page with a strict Content-Security-Policy. Not tested on live Wix / Squarespace / Webflow / Tilda
sites.

**Why is my free-shipping message not shown?**
It waits for the cart: your page must call `ABP.setCart({subtotal, currency, count})` (Shopify: use
`examples/cart-adapter-shopify.js`). It also stays hidden when the cart's currency has no threshold in the message.

**Can it read my cart without the adapter?**
No. The bar has no server and does not guess how your platform stores the cart. Shopify's `/cart.js` is covered by the
ready adapter; for other platforms someone needs to write the one `setCart` call.

**Can visitors sign up for a newsletter in the bar?**
Not directly (no server to store addresses). Use a button that links to your existing sign-up form.

**The bar does not appear.**
Open the browser console and look for lines starting with "Announcement Bar Pro:". Common causes: the config tag id
differs from `data-config`, the JSON is broken (re-export it), the `.css` is not next to the `.js` (or use `data-css`),
all messages are scheduled for later / limited to other pages, languages or devices, or you closed them earlier
(remembered in this browser — try a private window).

**The bar covers my header.**
Fixed and sticky headers are moved below the bar automatically. If your theme does something unusual, set the bar to
not sticky or add custom CSS.

**Times are wrong.**
Set your shop's time zone in Bar settings (IANA name such as `Europe/Riga`, `America/New_York`). "Now" comes from
the visitor's device clock.

**Is it accessible?**
It has a labelled region, labelled buttons, a pause button for moving content, keyboard support, screen-reader
announcements for "Copied!", contrast-keeping styles and reduced-motion support. Check your own colours with Solid
style — the Configurator shows the contrast.

**Is it allowed on sites with a strict Content-Security-Policy?**
Yes with options A / B: no eval, no inline scripts or styles needed (see INSTALL.md §3).

**How big is it?**
About 20 KB gzipped JavaScript plus about 4 KB gzipped CSS, loaded with `defer` (it does not block the page).

**Do I need to update it?**
Only to get new features or fixes: replace the two files. Your config stays valid.

**Licence and support?**
MIT licence (see LICENSE.txt / LICENSING.md). Support by e-mail: extension@3dp.lv.
