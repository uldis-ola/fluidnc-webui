# Changelog — Announcement Bar Pro Embed

## 1.0.1 — 2026-11-08
- Runtime: the shared storefront script, pre-script and CSS of Announcement Bar Pro 2.4.2 (only change for the embed:
  the version in their headers; the new OpenCart 1.5 mini-cart rule needs jQuery AJAX and does not apply to embeds).
- Configurator (review fix, pre-release): an imported configuration keeps only 2-letter language codes (as the runtime),
  resets a default language that is not one of them, rebuilds the label maps without a prototype (a `__proto__` key can
  never reach `Object.prototype`), limits each message's languages to the site's, and the preview escapes the language in
  `<html lang>`. The runtime is unchanged (still within the 20 KB gzip budget).

## 1.0.0 — 2026-11-08
- First release of the universal JS embed: one `<script>` for any site, config as inline JSON or a JSON file.
- Runtime = the shared storefront script, pre-script and CSS of Announcement Bar Pro 2.4.1 (byte-identical to the
  OpenCart 3 / 4 and WordPress editions) + a new DOM renderer and config core (allow-list HTML parser, URL / CSS filters,
  page rules without regular expressions, time zones, free-shipping goal).
- Offline Configurator (single HTML file, file://): messages, 12 templates, languages, live preview with the real
  runtime (desktop / phone, language, cart), export as snippet A / B / all-in-one and JSON, import.
- Cart adapters: Shopify (`/cart.js` + AJAX cart changes) and generic `ABP.setCart`.
- Docs EN + LV: install for Shopify, Wix, Squarespace, Webflow, Tilda, HTML; user guide; FAQ.
