=== Announcement Bar Pro — Premium ===
Contributors: 3dpsia
Tags: announcement bar, woocommerce, coupon, ab testing, newsletter
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: announcement-bar-pro
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Premium add-on for the free Announcement Bar Pro plugin. Sold and supported by 3DP SIA (extension@3dp.lv); not hosted on WordPress.org.

== Description ==

Needs the free plugin "Announcement Bar Pro" (WordPress.org), version 1.0.0 or newer. The add-on registers its features on the hooks of the free plugin; it shows a notice in the admin when the free plugin is missing, inactive or too old.

With a licence key (Announcement Bar → Settings → Premium licence):

* A bottom bar and boxes on the cart page (classic page and cart block), under the product price and in the mini-cart (classic mini-cart and the drawer of the Mini-Cart block) — each message with its own placement and priority; a scrolling ticker; gradient and glass styles.
* Free-shipping amounts by country, state or currency; reward tiers with several goals and labels.
* Cart conditions (subtotal, number of items, products, categories), country targeting, UTM campaign / referrer and new or returning visitor rules, user roles, page rules, product and category pages.
* Triggers: after a delay, after scrolling, or on exit intent.
* Recurring daily / weekly cut-off countdowns ("Order within 02:14:05 for same-day dispatch").
* WooCommerce coupons with a copy button — the message hides itself when the coupon expires or is used up.
* A/B tests with views, clicks, CTR and code copies per variant.
* GDPR e-mail sign-up with consent box, double opt-in, subscriber list, CSV export, erasure and the WordPress personal-data exporter / eraser; optional Cloudflare Turnstile anti-bot check.
* Templates gallery, background image from the media library, fonts, custom CSS (cleaned on save), auto-hide, pause button, GA4 events, "wait for cookie consent" (WP Consent API).

The subscriber list, its CSV export, erasure and the privacy tools keep working without a licence key.

= Licence check =

The key is checked on your site only (format and checksum); the add-on makes no network request for it. See LICENSING.md in the source package.

= Privacy =

* Statistics are totals per message — no personal data. IP addresses are never stored; a keyed hash is kept for at most two days to rate-limit the public endpoints.
* E-mail sign-ups store the address, language, the consent text shown and the dates. A suggested text for your privacy policy is added under Settings → Privacy.
* An address that unsubscribed stays unsubscribed: a new sign-up of it (by anybody) only sends a confirmation e-mail; only the owner of the address, by confirming it, subscribes it again. The record of the opt-out is not overwritten.
* Cloudflare Turnstile (optional, off until you enter your own site key and secret key): Cloudflare's script is loaded only on pages with a sign-up form; Cloudflare processes the visitor's IP address and browser data for the check, and your server sends the token and the visitor's IP address to Cloudflare's siteverify. With "wait for cookie consent" the script loads only after consent. The secret key is kept on the server and never printed into any page. If the check fails or Cloudflare cannot be reached, the sign-up is refused (fail closed) with a clear message.

== Installation ==

1. Install and activate the free plugin "Announcement Bar Pro" from WordPress.org.
2. Upload `announcement-bar-pro-premium.zip` under Plugins → Add New → Upload and activate it.
3. Enter your licence key under Announcement Bar → Settings → Premium licence.

Coming from the single-plugin build 1.0.0 (licence key inside the plugin)? Update that plugin to the free WordPress.org version (same folder), then install this add-on: messages, statistics, subscribers, settings and the licence key are kept (same options and tables).

== Changelog ==

= 1.0.1 =
* The help text of the placement field now names both mini-carts: the classic mini-cart and the drawer of the Mini-Cart block (English, Latvian, Russian).
* Works with the free plugin 1.0.1 (shared storefront script 2.4.2).

= 1.0.0 =
* First release as an add-on of the free plugin: every Pro feature of the single-plugin build 1.0.0, plus the optional Cloudflare Turnstile check of the e-mail sign-up, and the re-subscribe rule (an unsubscribe is never overwritten by a new sign-up; only the owner's confirmation subscribes the address again).
* Mini-cart box also in the drawer of the WooCommerce Mini-Cart block (block themes): copied in by a small script each time the drawer opens; the classic mini-cart keeps its server-side box.
