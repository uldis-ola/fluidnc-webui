# Changelog — Announcement Bar Pro for OpenCart 1.5.x

## 2.4.1 — 2026-11-08
First release of the OpenCart 1.5 edition (1.5.4 – 1.5.6.4, PHP 5.6+, vQmod). Same features as Announcement Bar Pro 2.4.1
for OpenCart 3 / 4: several bars and inline boxes (cart page, under the price, mini-cart) with priority, targeting (stores,
customer groups, pages, categories / products, devices, countries, cart conditions, UTM / referrer, new or returning
visitors), free-shipping progress with per-country / per-currency amounts, reward tiers, OpenCart coupons, triggers, weekly
schedule and recurring countdown, A/B test with statistics and "Use B", e-mail sign-up with GDPR double opt-in, subscriber
CSV export and erasure, Cloudflare Turnstile, 13 templates, custom CSS, GA4 events, cookie-consent wait, import / export,
texts in English, Latvian and Russian.

OpenCart 1.5 specifics:
- The bar is added through vQmod hooks (`vqmod/xml/announcement_bar_pro.xml`): header, footer marker + Response fallback,
  cart page, product page, mini-cart (`module/cart`). Each hook is idle while the module is uninstalled or off.
- Admin pages keep the card layout and live preview of the newer editions with their own scoped styles (no Bootstrap in
  OpenCart 1.5): tabs, OpenCart 1.5 image manager and date-time picker.
- PHP templates generated from the Twig templates (`tools/twig2tpl.py`).
- Font Awesome 4.7 bundled for message icons (setting "Load the icon font").
- PHP 5.6: `random_bytes()` / `intdiv()` polyfills (`compat.php`) for the shared helper library.
- Tables in utf8 like OpenCart 1.5; images from `image/data/`.
- Import accepts exports of the OpenCart 3 / 4 editions (`en-gb` language codes matched to `en`).
- Confirmation e-mails: SMTP server checked before sending, because OpenCart 1.5's Mail class ends the request on SMTP errors.
