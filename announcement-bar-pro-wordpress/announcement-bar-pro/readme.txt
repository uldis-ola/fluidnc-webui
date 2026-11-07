=== Announcement Bar Pro ===
Contributors: 3dpsia
Tags: announcement bar, notification bar, free shipping bar, countdown, woocommerce
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Announcement bars with rotating messages, schedules, countdowns and a WooCommerce free-shipping progress bar. Fast, accessible, privacy-friendly.

== Description ==

Announcement Bar Pro adds a clean bar to the top of your site for delivery notes, sales, opening hours or any short message. With WooCommerce it also shows how much is still missing for free shipping and updates while the visitor shops.

= Free features =

* Top bar with as many messages as you like: rotating one at a time, or all of them stacked.
* Schedule: start and end date, days of the week and a daily time window (in your site's time zone).
* Countdown timer to the end of an offer, placed anywhere in the text with `{countdown}`.
* WooCommerce free-shipping progress: a fixed amount, or the minimum amount of WooCommerce's own "Free shipping" method for the visitor's shipping zone. Updates after add-to-cart, cart changes, the cart and checkout blocks.
* Button or link, icon (built-in SVG icons), colours with a contrast check, three styles (solid, soft, minimal).
* Device targeting: all devices, computers only or phones only, plus a shorter text for phones.
* Visitors can close the bar; closed messages stay closed for the number of days you choose.
* Live preview in the admin, using the real bar script and styles.
* Messages in several languages (works with multilingual plugins that switch the WordPress locale).

= Pro features (licence key) =

* A bottom bar and inline boxes on the cart page (classic page and cart block), under the product price and in the classic mini-cart — each message with its own placement and priority.
* Free-shipping amounts by country, state or currency; reward tiers with several goals and labels.
* Cart conditions (subtotal, number of items, products, categories), country targeting, UTM campaign / referrer and new or returning visitor rules, user roles, page rules, product and category pages.
* Triggers: after a delay, after scrolling, or on exit intent.
* Recurring daily / weekly cut-off countdowns ("Order within 02:14:05 for same-day dispatch").
* WooCommerce coupons with a copy button — the message hides itself when the coupon expires or is used up.
* A/B tests with views, clicks, CTR and code copies per variant.
* GDPR e-mail sign-up with consent box, double opt-in, subscriber list, CSV export, erasure and the WordPress personal-data exporter / eraser.
* Templates gallery, background image from the media library, fonts, custom CSS (cleaned on save), auto-hide, pause button, GA4 events, "wait for cookie consent" (WP Consent API).

The free edition is fully usable on its own; nothing in it expires.

= Privacy =

* The plugin does not call any outside service, loads no fonts or scripts from elsewhere and sets no cookies.
* Closed messages, the A/B variant and the campaign of a visit are remembered in the visitor's browser (local / session storage) only. With "Do Not Track", "Global Privacy Control" or before consent (when "wait for cookie consent" is on), nothing is counted or sent and no A/B variant, visit or campaign is remembered — only a message the visitor closes stays closed, because they asked for it.
* Statistics are totals per message — no personal data. IP addresses are never stored; a keyed hash is kept for at most two days to rate-limit the public endpoints.
* E-mail sign-ups store the address, language, the consent text shown and the dates. A suggested text for your privacy policy is added under Settings → Privacy.

= Accessibility =

Bars are labelled regions, every button has a visible focus and a text label, rotation pauses on hover and focus and can be paused with a button (Pro), motion respects "reduce motion", and the admin checks the colour contrast (WCAG AA, 4.5:1).

== Installation ==

1. Upload the plugin through Plugins → Add New → Upload, or copy the `announcement-bar-pro` folder to `wp-content/plugins`.
2. Activate it. On a multisite network you can activate it for the whole network; every site keeps its own messages.
3. Open **Announcement Bar → Add message**, write your text and watch the live preview.
4. Pro: enter your licence key under **Announcement Bar → Settings → Licence**.

== Frequently Asked Questions ==

= Does it work without WooCommerce? =

Yes. Announcements, schedules, countdowns, styles, targeting and the e-mail sign-up work on any WordPress site. Free-shipping progress, reward tiers, coupons, cart conditions, country rules and the cart / product / mini-cart boxes need WooCommerce and are hidden without it.

= Where does the bar appear? =

Right after the opening `<body>` tag through the `wp_body_open` hook, which block themes and current classic themes support. With an older theme without that hook the bar is printed in the footer and moved to the top by its script.

= Does it work with the cart and checkout blocks? =

Yes. The free-shipping progress updates when the cart block changes the cart, and the cart box (Pro) is placed above the cart block. Nothing is added to the checkout.

= Is it compatible with HPOS (custom order tables)? =

Yes. The plugin never reads or writes orders and declares compatibility with WooCommerce's custom order tables and cart / checkout blocks.

= Does the licence check send data anywhere? =

No. Version 1.0.0 checks the key's format on your site only. If a later version adds online activation, it will only contact the licence server when you press "Activate" and will say so on the screen.

= What happens to Pro messages if the licence is removed? =

They stay saved. Messages that use Pro targeting (pages, roles, countries, cart rules, campaigns, triggers, boxes, tiers) are not shown, so they never reach more people than intended; Pro looks (gradient, image, font, A/B variant B, coupon, sign-up form) fall back to the free look.

= Does page caching break the free-shipping amount? =

The amount is printed with the page and refreshed in the browser after every cart change and once on load when WooCommerce's cart cookie says the cart is not empty, so a cached page corrects itself.

= Can I translate the plugin? =

Yes. It uses the text domain `announcement-bar-pro` and ships with English, Latvian and Russian. The message texts themselves are written per language in the message form.

= How do I remove all data? =

Tick "Delete all messages, statistics, subscribers and settings when the plugin is deleted" under Settings → Data, then delete the plugin. Without that tick everything stays in the database.

== Screenshots ==

1. A rotating top bar with a countdown and a button (desktop).
2. Free-shipping progress in the top bar, updated after add-to-cart.
3. The same bar on a phone, with a shorter phone text.
4. The message form with the live preview.
5. The message list with statistics per variant (Pro).
6. The templates gallery (Pro).
7. A coupon box under the product price and on the cart page (Pro).
8. The e-mail sign-up with consent box (Pro).

== Changelog ==

= 1.0.0 =
* First release of the WordPress / WooCommerce edition: top bar, rotating or stacked messages, schedules, countdowns, free-shipping progress, buttons, icons, styles, device targeting and live preview; Pro: bottom bar and inline boxes, priorities, country / state / currency amounts, reward tiers, cart, country, campaign, visitor, role and page targeting, triggers, recurring countdowns, WooCommerce coupons, A/B tests with statistics, GDPR e-mail sign-up, templates, background images, fonts, custom CSS, auto-hide, pause button, GA4 events and cookie-consent waiting.

== Upgrade Notice ==

= 1.0.0 =
First release.
