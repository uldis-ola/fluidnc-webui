# Announcement Bar Pro — universal JS embed

One script tag for any website (Shopify, Wix, Squarespace, Webflow, Tilda, WordPress, static HTML, any CMS): top / bottom
announcement bars with rotation, countdowns, coupons, targeting and free-shipping progress. No server, no account,
no tracking, nothing sent to us.

*Viena skripta rinda jebkurai vietnei: paziņojumu josla augšā / apakšā ar atskaiti, kuponiem, mērķēšanu un bezmaksas
piegādes progresu. Bez servera, bez konta, bez izsekošanas.* — LV docs: `docs/lv/`.

- **Version:** 1.0.1 · **Seller / support:** 3DP SIA, extension@3dp.lv · **Licence:** MIT (see `LICENSING.md`)
- **Suggested price:** $29 one-time per domain (no licence key, no enforcement — see LICENSING.md)
- **Size:** runtime ≈ 20 KB gzipped JS (`build.sh` fails above 20 480 B) + ≈ 4 KB gzipped CSS, loaded with `defer`
- **Browsers:** current Chrome, Edge, Firefox, Safari (ES2017; tested in Chromium)

```html
<script type="application/json" id="abp-config">{ …made in the Configurator… }</script>
<script src="https://your-site.example/abp/abp-embed.min.js" defer></script>
```

## Package
| Path | What |
|---|---|
| `abp-embed/abp-embed.min.js`, `.min.css` | the runtime to upload (same folder) |
| `abp-embed/abp-embed.js`, `.css` | the same, not minified (readable source) |
| `source/` | the embed's own source (core + renderer) |
| `configurator/index.html` | offline visual Configurator (one file, works from file://) |
| `examples/` | cart adapters: Shopify (`/cart.js`) and generic |
| `docs/en`, `docs/lv` | INSTALL (Shopify, Wix, Squarespace, Webflow, Tilda, HTML, CSP), USER-GUIDE, FAQ |

## Features
- Top and/or bottom bar; several messages: rotate (fade / slide), scrolling text, all at once; priority; max per bar.
- Texts per language + shorter phone text; language from URL (`/lv/`, `?lang=`) or `<html lang>`; built-in labels in 7 languages.
- Schedule (start / end, daily window, weekdays) in the shop's time zone; countdown to the end or a recurring daily / weekly cut-off.
- Link or button (3 styles), coupon code with Copy, icon (emoji), e-mail sign-up **as a link/button to your own form**.
- Targeting: devices, page URL rules (contains / equals / `*` wildcard — never a regex), UTM / referrer, new vs returning visitor, languages.
- Triggers: delay, scroll %, exit intent; auto-fold into a tab; close with "remember for N days".
- Look: 5 styles keeping 4.5:1 contrast, automatic site colour, background image with overlay, system fonts, filtered custom CSS, pause button.
- GA4 events (only via the site's own GA4 / GTM), wait for cookie consent (`window.abpConsent` + `abp:consent`), DNT / GPC respected.
- Free-shipping progress **only with the cart adapter** `ABP.setCart({subtotal, currency, count})` — ready for Shopify; other platforms must call it themselves.
- Theme adaptation shared with the OpenCart / WordPress editions: fixed / sticky headers are moved below the bar, a bottom bar lifts above cookie banners.

## What it does not do (honestly)
No e-mail collection, no statistics dashboard, no A/B test, no tiered goals, no cart conditions, no country targeting
(they need a server — they exist in the OpenCart / WordPress editions). It never reads a cart by itself. The
Configurator's interface is in English (docs in EN and LV).

## Security
- Config text is never inserted as HTML: an allow-list parser (port of the PHP Sanitizer) turns allowed markup into DOM
  nodes; text is `textContent`. URLs: http(s) or relative only. Custom CSS: port of the PHP filter.
- No `eval` / `new Function` (checked by `build.sh`), no inline styles in markup (CSSOM only), works under
  `script-src 'self'; style-src 'self'`; optional nonce.
- The only `innerHTML` writes are fixed SVG icon constants (not config).

## Development
```
sh build.sh                       # checks shared files, minifies (esbuild), size budget, Configurator, dist/abp-embed-1.0.1.zip
node tests/unit/run.js            # sanitiser, URLs, CSS, page rules, language, time zones, selection, free shipping
NODE_PATH=<playwright> node tests/e2e/e2e.js   # local pages: static, fake Shopify + adapter, strict CSP, nonce, hostile config, rules, Configurator (file://)
```
The storefront script, pre-script and CSS are **not copied** here: `build.sh` takes them from the OpenCart 3 kit and
fails if the OpenCart 4 or WordPress copies differ, so every edition runs byte-identical storefront code.
