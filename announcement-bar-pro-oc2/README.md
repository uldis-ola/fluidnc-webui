# Announcement Bar Pro — OpenCart 2.0.x–2.3.x

An announcement bar for the top or bottom of every store page, plus inline boxes on the cart page, under the product
price and in the mini-cart: promotions, shipping terms, sign-up forms, notices.

- **Version:** 2.4.2 (same features as the OpenCart 3 and 4 editions 2.4.2)
- **Compatibility:** OpenCart 2.0.0.0 – 2.3.0.2. Tested on 2.0.3.1, 2.1.0.2, 2.2.0.0 and 2.3.0.2.
- **PHP:** 5.6 or newer (tested on PHP 5.6.40 and 7.4.33). PHP 5.4 / 5.5 are not supported: the shared feature
  library uses array class constants (PHP 5.6). PHP 7.0+ functions it needs (`random_bytes`, `intdiv`) are provided by
  the module's own adapter on PHP 5.6 (`random_bytes` needs the OpenSSL extension).
- **Languages:** English, Latviešu, Русский
- **License:** GPL-3.0 (see `LICENSE.txt`)
- **Seller and support:** 3DP SIA — extension@3dp.lv

## Two packages

| OpenCart | Package |
|---|---|
| 2.0.0.0 – 2.2.0.0 | `announcement_bar_pro_oc2.0-2.2_v2.4.2.ocmod.zip` |
| 2.3.0.0 – 2.3.0.2 | `announcement_bar_pro_oc2.3_v2.4.2.ocmod.zip` |

Why two: OpenCart 2.0–2.2 open modules from `admin/controller/module/`, 2.3 from `admin/controller/extension/module/`, and each folder breaks the other side: OpenCart 2.0.x resolves routes folder-first, so an `admin/controller/extension/module/` folder hides its own Extensions › Modules page.
The 2.0–2.2 package needs an entry file in `admin/controller/module/`; on 2.3 that same file makes OpenCart's
compatibility layer (`event/compatibility`) send every call of the module (install, save, AJAX) to the old
`module/...` page, so the 2.3 package must not contain it. Everything else is identical.

Installation and upgrade steps: `INSTALL.md` (English) / `INSTALL.lv.md` (latviski).

## Features

- Unlimited messages; several bars at once (top and bottom) and inline boxes (cart page, under the product price,
  mini-cart) with a **priority** per message.
- Rotate, marquee or stacked display; arrows, pause button, auto-hide with a reopen tab, close button remembered for N days.
- **Scheduling:** start / end date-time, weekly days and hours, countdown to the end, **recurring countdown**
  (daily / weekly cut-off time).
- **Targeting:** stores, customer groups, pages (routes with `*`), device, categories / products, **countries**
  (shipping address, account address, optional CloudFlare country header, store-country fallback), cart value /
  quantity / products / categories, UTM campaign and referrer, **new or returning visitors**.
- **Free-shipping goal** with progress bar (own amount, per country / region, per currency, or OpenCart's Free
  Shipping amount) and **tiered goals** (up to 5 rewards with markers).
- **Coupons:** a code with a copy button, or an OpenCart coupon (hidden automatically when disabled, expired or used up).
- **Triggers:** after a delay, after scrolling, on exit intent.
- **A/B test** with views, clicks, copies, CTR per variant and "use B".
- **E-mail sign-up** with GDPR consent box and privacy link, **double opt-in**, subscriber list, CSV export and
  erase in the settings, rate limits, honeypot, optional **Cloudflare Turnstile**.
- 13 ready-made **templates**, live preview in the admin form (desktop / phone, A / B), background image with
  overlay, system fonts, button styles, 5 bar styles, automatic text colour for contrast, **custom CSS** (sanitised).
- **GA4** events (`abp_view`, `abp_click`, `abp_copy`, `abp_signup`) through `gtag` or `dataLayer`; nothing is sent
  with Global Privacy Control; optional **"Wait for cookie consent"** (`window.abpConsent = true` + `abp:consent` event).
- Import / export of messages (JSON), duplicate, statistics reset.
- Basic HTML in texts (whitelist sanitiser), emoji, per-language texts with fallback to the default language.

## How it hooks into OpenCart 2.x

- **2.2 / 2.3:** six view events (`catalog/view/*/common/header/after`, footer, cart, product, mini-cart and a
  fallback on every view) registered on *Install* and removed on *Uninstall*. No core file is changed.
- **2.0 / 2.1** have no view events. `install.xml` (OCMOD) adds one call to the storefront template loader
  (`system/engine/loader.php`) that hands each finished template to the same handlers. It runs only in the
  storefront, only while the module files exist, and leaves the page unchanged when the module is off or fails.
  *Extensions › Modifications › Refresh* is needed after installing.
- Templates are plain PHP `.tpl` files generated from the OpenCart 3 Twig templates (`tools/twig2tpl.py`), so the
  markup is the same in every edition. The storefront script and styles, the admin script and styles and the
  feature library are byte-identical to the OpenCart 3 edition (checked by `build.sh`).

## Notes

- Tables: `oc_abp_message`, `oc_abp_message_description`, `oc_abp_subscriber`, `oc_abp_rate`. Settings code
  `module_announcement_bar_pro` (same as OpenCart 3, so exports move between editions).
- The schema migration runs when the module page is opened by a user with *modify* permission; it is idempotent and
  writes the schema number last.
- OpenCart 2.x sends mail with its own Mail class: if the SMTP server refuses the message, OpenCart 2.0–2.2 stop
  the request (core behaviour). Test the shop's mail settings before switching double opt-in on.
- The bar is placed right after `<body>` in the header template; themes without `<body>` in the header get the bar
  through the footer fallback (2.2 / 2.3, and 2.0 / 2.1 through the loader hook).
- Font Awesome 4 icons (as in the default 2.x theme).
- Modern browsers only; Internet Explorer 11 is not supported.

## Development

```bash
./build.sh                      # checks + dist/*.ocmod.zip (both packages)
php tests/schema_test.php       # unit tests, no database (PHP 5.6 and 7.x)
php tests/oc2_test.php          # OpenCart 2.x adapter tests (versions 2.0.0.0 … 2.3.0.2)
python tools/twig2tpl.py ../announcement-bar-pro/upload/<path>.twig upload/<path>.tpl   # after a Twig change
```

## Files

```
upload/admin/controller/module/announcement_bar_pro.php                (2.0–2.2 package only)
upload/admin/controller/extension/module/announcement_bar_pro.php      (2.3 package only)
upload/admin/model/extension/module/announcement_bar_pro.php
upload/admin/view/template/extension/module/announcement_bar_pro.tpl, announcement_bar_pro_form.tpl
upload/admin/view/javascript/announcement_bar_pro/abp_admin.{js,css}
upload/admin/language/<folder>/extension/module/announcement_bar_pro.php   (+ <folder>/module/ in the 2.0–2.2 package)
upload/catalog/controller/extension/module/announcement_bar_pro.php
upload/catalog/model/extension/module/announcement_bar_pro.php
upload/catalog/view/theme/default/template/extension/module/announcement_bar_pro.tpl, announcement_bar_pro_page.tpl
upload/catalog/view/javascript/announcement_bar_pro/announcement_bar_pro.{js,css}, announcement_bar_pro_pre.js
upload/catalog/language/<folder>/extension/module/announcement_bar_pro.php
upload/system/library/announcementbarpro/   (admin_controller.php, oc2.php, features.php, sanitizer.php, language/)
install.xml
```
`<folder>`: 2.0/2.1 `english`, `latvian`, `russian`; 2.2/2.3 `en-gb`, `lv-lv`, `ru-ru`.
