# Changelog — Announcement Bar Pro for OpenCart 1.5.x

## 2.4.2 — 2026-11-08
- **Shared fixes (every OpenCart edition, byte-identical shared files):** `Features::tsVerify()` (Cloudflare Turnstile) now also catches `\Exception`, so a throwing transport fails closed on PHP 5.6 too (PHP 5.6 has no `\Throwable`); `Features::imagePath()` also accepts OpenCart 1.5 image-manager paths (`data/…`) besides `catalog/…`; the storefront script refreshes the cart amounts and the mini-cart box also after OpenCart 1.5's mini-cart reload (`route=module/cart`).
- OpenCart 1.5: images are stored as the 1.5 image manager gives them (`data/…`; the `catalog/…` mapping is gone), and the adapter script `announcement_bar_pro_oc15.js` is removed — the shared script handles the 1.5 mini-cart reload (`route=module/cart`). Upload the new files; the old `announcement_bar_pro_oc15.js` on the server is unused and may be deleted.
- **OpenCart 4 compatibility fixes** (from the 2026-11-08 compatibility pass, OpenCart 4 package): OpenCart 4.0.0.x–4.0.1.x send the double opt-in e-mail through the shop's SMTP settings (their `Mail` options are public properties); OpenCart 4.1.0.0 gets the module's own category search in the message form (its core category autocomplete matches whole names only).
- **New editions with the same features:** OpenCart 2.0.0.0–2.3.0.2 (`announcement-bar-pro-oc2`, two packages: 2.0–2.2 and 2.3, PHP 5.6+) and OpenCart 1.5.4–1.5.6.4 (`announcement-bar-pro-oc15`, vQmod, PHP 5.6+).
- Schema stays 8: upgrading 2.4.1 → 2.4.2 replaces files only. Tested: `COMPATIBILITY.md` of the universal package.
- **Review fixes (pre-release):** Cloudflare Turnstile — `Features::tsVerify()` also checks the `hostname` of Cloudflare's answer against the shop's host (from the shop's configured URL, never the request's Host header; `www.` and letter case ignored) and refuses a token solved on another site (`wrong-hostname`, logged). Not checked when Cloudflare names no hostname, or when the shop runs on a local development host (localhost, `*.localhost`, 127.x, ::1) — there Cloudflare's test keys answer `example.com`.

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
