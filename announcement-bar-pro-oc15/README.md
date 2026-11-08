# Announcement Bar Pro — OpenCart 1.5.x edition

*Announcement bars and inline boxes for OpenCart 1.5: promotions, free-shipping progress, coupons, countdowns, e-mail sign-ups.*
Paziņojumu joslas un bloki OpenCart 1.5 veikalam: akcijas, bezmaksas piegādes progress, kuponi, atpakaļskaitīšana, e-pasta pieteikšanās.

- **Version:** 2.4.1 (same features as the OpenCart 3 / 4 editions 2.4.1)
- **OpenCart:** 1.5.4 – 1.5.6.4. Tested on **1.5.6.4** (PHP 5.6 and PHP 7.4) and **1.5.5.1** (PHP 5.6).
- **vQmod:** 2.4 or newer (tested with vQmod 2.6.6 for OpenCart). OpenCart 1.5 has no event system, so vQmod is needed.
- **PHP:** **5.6 minimum** (recommended: the newest PHP your OpenCart 1.5 runs on, 7.x). PHP 5.4 / 5.5 are not supported:
  the shared helper library uses constant arrays in classes (PHP 5.6). PHP 7.0 functions it calls (`random_bytes`,
  `intdiv`) are provided by `system/library/announcementbarpro/compat.php` on PHP 5.6. Needed extensions: `json`, `mbstring`
  (recommended), `dom`, `openssl`; `curl` for Cloudflare Turnstile.
- **Languages:** English, Latviešu, Русский (admin and storefront).
- **Seller / support:** 3DP SIA — extension@3dp.lv
- **License:** GPL-3.0 (`LICENSE.txt`). Font Awesome 4.7 by Dave Gandy is bundled for the message icons: font under the
  SIL OFL 1.1, CSS under the MIT License (header of `fa/css/font-awesome.min.css`).

Installation: **`INSTALL.md`** (English) · **`INSTALL.lv.md`** (latviski).

## Features (identical to 2.4.2 for OpenCart 3 / 4)

- Any number of messages; several bars at once (top + bottom) and **inline boxes** on the cart page, under the product
  price and in the mini-cart, ordered by **priority** (0–100) and a per-bar message limit.
- Display: rotating (fade / slide, arrows, pause button), scrolling ticker, all at once; sticky top bar; auto-hide with a
  small tab to open it again; close button remembered per message.
- Per-language texts (also a separate phone text), button or whole-bar link, icon (Font Awesome 4), 5 looks (solid,
  gradient, soft, minimal, glass) or automatic theme colours, background image with overlay, system fonts, custom CSS
  (sanitised), 13 ready-made templates, live preview in the admin with the shop's real bar script.
- Schedule: start / end, weekdays and time of day, `{countdown}` to the end or to a **daily / weekly cut-off**.
- Targeting: stores, customer groups, pages (routes), categories / products, devices, **countries** (address chosen in the
  visit, customer address, optional CloudFlare header, store country), **cart conditions** (subtotal, items, products,
  categories — updated after AJAX cart changes), **UTM / referrer** campaigns, new or returning visitors.
- Triggers: after a delay, after scrolling, on exit intent.
- **Free-shipping progress** with its own amount, OpenCart's Free Shipping amount, per-country / region and per-currency
  amounts; **reward tiers** (2–5 goals with labels).
- **OpenCart coupons** (1.5 coupon tables: hidden when disabled, not started, expired or used up) with a Copy button.
- **A/B test** of two texts with views, clicks, copies and CTR per variant, the better one marked, "Use B".
- **E-mail sign-up** with GDPR **double opt-in** (or single), consent text stored as proof, privacy-page link, honeypot,
  rate limits, at most 3 confirmation e-mails per address per day, unsubscribe link, customer newsletter flag only after
  a confirmed opt-in, subscriber counts, **CSV export**, erase an address. Optional **Cloudflare Turnstile**.
- **GA4** events (`abp_view`, `abp_click`, `abp_copy`, `abp_signup`) through `gtag` or `dataLayer`; Do Not Track / Global
  Privacy Control respected; **Wait for cookie consent** (`window.abpConsent = true` + `abp:consent` event).
- Import / export of messages as JSON (also between the OpenCart 1.5, 3 and 4 editions: language codes `en` / `en-gb` are matched).

## What is different in the OpenCart 1.5 edition

| | OpenCart 3 / 4 | OpenCart 1.5 |
|---|---|---|
| Injection of the bar | OpenCart events | vQmod file `vqmod/xml/announcement_bar_pro.xml` (hooks in `common/header`, `common/footer`, `checkout/cart`, `product/product`, `module/cart`, `Response::setOutput`) |
| Install | Extension Installer (zip) | upload the files by FTP, then *Extensions → Modules → Install* |
| Admin UI | Bootstrap 3 / 5 of the admin | same pages and card layout with the module's own scoped styles (`abp_oc15.css`, everything under `.abp15`); tabs, image manager (jQuery UI dialog) and date-time picker of OpenCart 1.5 via `abp_oc15.js` |
| Templates | Twig | PHP `.tpl`, generated from the Twig templates by `tools/twig2tpl.py` (Twig rules kept: missing values are empty, no PHP notices) |
| Icons | theme's Font Awesome | Font Awesome 4.7 bundled, loaded only on pages that show an icon; setting *Load the icon font* (switch off if the theme already has Font Awesome 4) |
| Images | `image/catalog/…` | `image/data/…` (the 1.5 image manager), stored as it is (the shared rules accept `data/…` since 2.4.2) |
| Settings | `module_announcement_bar_pro_*` | `announcement_bar_pro_*` in `oc_setting` (group `announcement_bar_pro`) |
| Tables | utf8mb4 | utf8 like OpenCart 1.5 itself (its DB link is utf8; emoji are stored as HTML entities, as in every edition) |
| Mini-cart refresh | `common/cart/info` | `module/cart` (jQuery `.load`), handled by the shared storefront script since 2.4.2 (no adapter script) |

The storefront script and styles (`announcement_bar_pro.js`, `_pre.js`, `.css`), the admin script and styles
(`abp_admin.js`, `abp_admin.css`) and the helper library (`features.php`, `sanitizer.php`) are byte-identical to the
other editions. The storefront script needs no jQuery (OpenCart 1.5 ships jQuery 1.7; it is only used, when present, to
notice AJAX cart changes).

## Limits on OpenCart 1.5 (honest list)

- **OpenCart 1.5's Mail class ends the request with `exit()`** when an SMTP server refuses a message. The module checks
  that the SMTP server is reachable first and, if the server still refuses, answers the sign-up form with the normal error
  (shutdown handler) and undoes the sign-up. With *Mail* (PHP `mail()`) there is no such problem.
- **OpenCart 1.5.5.x** has a broken `editSettingValue()` and a broken `mysqli` driver in some downloads; the module does
  not use `editSettingValue()`. Use the `mysql` driver on 1.5.5.x with PHP 5.x (as OpenCart's own installer does).
- OpenCart 1.5 itself needs **mcrypt** (PHP ≤ 7.1) or a patched `system/library/encryption.php` on PHP 7.2+; the module
  does not use the Encryption class.
- Themes: the bar goes after `<body>` in the header; without it, before `</body>` (footer fallback) and the script moves it
  to the top. Inline boxes need the default theme's spots (`</h1>` / `.cart-info` on the cart page, `.price` / `.options` /
  `.cart` on the product page, `#cart .content` for the mini-cart); without them that box is simply not shown.
- Turnstile needs outgoing HTTPS from PHP (curl or `allow_url_fopen` with a CA bundle).

## Files

```
upload/admin/controller/module/announcement_bar_pro.php        admin (messages, settings, import / export, sign-ups)
upload/admin/model/module/announcement_bar_pro.php             tables, idempotent migration (schema 8, written last)
upload/admin/view/template/module/announcement_bar_pro*.tpl     admin pages (generated from Twig, then adapted)
upload/admin/view/javascript/announcement_bar_pro/             abp_admin.js/.css (shared), abp_oc15.js/.css (1.5)
upload/admin/language/{english,latvian,russian}/module/        admin texts
upload/catalog/controller/module/announcement_bar_pro.php      storefront hooks + endpoints (cart, track, subscribe, confirm, unsubscribe)
upload/catalog/model/module/announcement_bar_pro.php
upload/catalog/view/theme/default/template/module/             bar + confirm / unsubscribe page (.tpl)
upload/catalog/view/javascript/announcement_bar_pro/           shared JS / CSS, 1.5 adapter CSS, fa/ (Font Awesome 4.7)
upload/catalog/language/{english,latvian,russian}/module/
upload/system/library/announcementbarpro/                      features.php, sanitizer.php (shared), compat.php, tpl.php (1.5)
upload/vqmod/xml/announcement_bar_pro.xml                      vQmod hooks
```

Language folders: OpenCart 1.5 uses the language's *directory* (`english`, and usually `latvian` / `russian` in community
packs). If your Latvian or Russian pack uses another folder name, copy the module's language file there; missing
languages fall back to English.

## Development

- `build.sh` — checks (versions, shared files byte-identical, templates up to date, PHP lint on 5.6 / 7.4 when found,
  unit tests) and builds `dist/announcement_bar_pro_oc15_v2.4.2.zip`.
- `tests/unit/schema15_test.php` (or `tests/unit/run.sh` for PHP 5.6 + 7.4) — migration, SQL whitelists, shared library and 1.5-specific checks without a database; runs on a bare PHP without php.ini
  (`php tests/unit/schema15_test.php`, PHP 5.6 and 7.x).
- `tests/e2e/abp15-e2e.js` (+ `abp15-extra.js`) — Playwright run against a real OpenCart 1.5 shop (see the file header).
- `tools/twig2tpl.py` — Twig → PHP template converter (`build.sh` checks the admin templates are current);
  `tools/mk_e2e.py` — made the first version of the E2E from the OpenCart 3 / 4 one (2.4.1); the E2E is maintained by hand since;
  `tools/port_from_oc3.py` — the mechanical part of the port (documentation).
