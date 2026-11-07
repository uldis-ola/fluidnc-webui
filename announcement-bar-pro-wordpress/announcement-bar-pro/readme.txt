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

Every feature below is included and works without limits, accounts or keys.

* Top bar with as many messages as you like: rotating one at a time, or all of them stacked.
* Schedule: start and end date, days of the week and a daily time window (in your site's time zone).
* Countdown timer to the end of an offer, placed anywhere in the text with `{countdown}`.
* WooCommerce free-shipping progress: a fixed amount, or the minimum amount of WooCommerce's own "Free shipping" method for the visitor's shipping zone. Updates after add-to-cart, cart changes, the cart and checkout blocks.
* Button or link, icon (built-in SVG icons), colours with a contrast check, three styles (solid, soft, minimal).
* Device targeting: all devices, computers only or phones only, plus a shorter text for phones.
* Visitors can close the bar; closed messages stay closed for the number of days you choose.
* Live preview in the admin, using the real bar script and styles.
* Messages in several languages (works with multilingual plugins that switch the WordPress locale).
* Sticky bar, slide or fade effect, arrows, automatic colours that match your theme.

A separate paid add-on by the same author ("Announcement Bar Pro — Premium", not hosted on WordPress.org) adds more placements, targeting, A/B tests and an e-mail sign-up; this plugin does not need it and never asks for it.

For developers: the plugin has documented actions and filters (placements, visibility rules, message fields, settings, storefront markup), so add-ons can extend the bar without changing its code.

= Privacy =

* The plugin does not call any outside service, loads no fonts or scripts from elsewhere and sets no cookies.
* Closed messages are remembered in the visitor's browser (local storage) only, so they stay closed.
* No personal data is stored. The cart refresh endpoint is rate-limited with a keyed hash of the visitor's IP address that is kept for at most two days; the IP address itself is never stored.

= Accessibility =

Bars are labelled regions, every button has a visible focus and a text label, rotation pauses on hover and focus, motion respects "reduce motion", and the admin checks the colour contrast (WCAG AA, 4.5:1).

== Installation ==

1. Install the plugin through Plugins → Add New, or upload the `announcement-bar-pro` folder to `wp-content/plugins`.
2. Activate it. On a multisite network you can activate it for the whole network; every site keeps its own messages.
3. Open **Announcement Bar → Add message**, write your text and watch the live preview.

== Frequently Asked Questions ==

= Does it work without WooCommerce? =

Yes. Announcements, schedules, countdowns, styles and device targeting work on any WordPress site. The free-shipping progress needs WooCommerce and is hidden without it.

= Where does the bar appear? =

Right after the opening `<body>` tag through the `wp_body_open` hook, which block themes and current classic themes support. With an older theme without that hook the bar is printed in the footer and moved to the top by its script.

= Does it work with the cart and checkout blocks? =

Yes. The free-shipping progress updates when the cart block changes the cart. Nothing is added to the checkout.

= Is it compatible with HPOS (custom order tables)? =

Yes. The plugin never reads or writes orders and declares compatibility with WooCommerce's custom order tables and cart / checkout blocks.

= Does page caching break the free-shipping amount? =

The amount is printed with the page and refreshed in the browser after every cart change and once on load when WooCommerce's cart cookie says the cart is not empty, so a cached page corrects itself.

= I used an earlier build that had more features. What happens to those messages? =

Nothing is deleted: all messages, their settings and statistics stay in the database. Messages that only change the look (for example a second bar position or a style this plugin does not have) are shown with this plugin's look. Messages that narrow their audience with rules this plugin does not check (pages, user roles, countries, cart rules, campaigns, triggers) stay hidden, so they never reach more people than intended; the message list marks them.

= Can I translate the plugin? =

Yes. It uses the text domain `announcement-bar-pro` and ships with English, Latvian and Russian. The message texts themselves are written per language in the message form.

= How do I remove all data? =

Tick "Delete all messages and settings when the plugin is deleted" under Settings → Data, then delete the plugin. Without that tick everything stays in the database.

== Screenshots ==

1. A rotating top bar with a countdown and a button (desktop).
2. Free-shipping progress in the top bar, updated after add-to-cart.
3. The same bar on a phone, with a shorter phone text.
4. The message form with the live preview.
5. The message list.
6. The settings screen.

== Changelog ==

= 1.0.0 =
* First release on WordPress.org: top bar, rotating or stacked messages, schedules, countdowns, WooCommerce free-shipping progress, buttons, icons, three styles, device targeting, closable bars, live preview, several languages, hooks for add-ons.

== Upgrade Notice ==

= 1.0.0 =
First release.
