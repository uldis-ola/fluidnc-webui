# Changelog — Announcement Bar Pro Embed

## 1.0.0 — 2026-11-08
- First release of the universal JS embed: one `<script>` for any site, config as inline JSON or a JSON file.
- Runtime = the shared storefront script, pre-script and CSS of Announcement Bar Pro 2.4.1 (byte-identical to the
  OpenCart 3 / 4 and WordPress editions) + a new DOM renderer and config core (allow-list HTML parser, URL / CSS filters,
  page rules without regular expressions, time zones, free-shipping goal).
- Offline Configurator (single HTML file, file://): messages, 12 templates, languages, live preview with the real
  runtime (desktop / phone, language, cart), export as snippet A / B / all-in-one and JSON, import.
- Cart adapters: Shopify (`/cart.js` + AJAX cart changes) and generic `ABP.setCart`.
- Docs EN + LV: install for Shopify, Wix, Squarespace, Webflow, Tilda, HTML; user guide; FAQ.
