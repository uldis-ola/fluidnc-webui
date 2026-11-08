# Changelog — OpenCart 2.0.x–2.3.x edition

## 2.4.2 — 2026-11-08
- **Shared fixes (every OpenCart edition, byte-identical shared files):** `Features::tsVerify()` (Cloudflare Turnstile) now also catches `\Exception`, so a throwing transport fails closed on PHP 5.6 too (PHP 5.6 has no `\Throwable`); `Features::imagePath()` also accepts OpenCart 1.5 image-manager paths (`data/…`) besides `catalog/…`; the storefront script refreshes the cart amounts and the mini-cart box also after OpenCart 1.5's mini-cart reload (`route=module/cart`).
- OpenCart 2.x: the catalog's own never-throwing Turnstile transport wrapper is gone (the shared library now catches `\Exception` itself).
- **OpenCart 4 compatibility fixes** (from the 2026-11-08 compatibility pass, OpenCart 4 package): OpenCart 4.0.0.x–4.0.1.x send the double opt-in e-mail through the shop's SMTP settings (their `Mail` options are public properties); OpenCart 4.1.0.0 gets the module's own category search in the message form (its core category autocomplete matches whole names only).
- **New editions with the same features:** OpenCart 2.0.0.0–2.3.0.2 (`announcement-bar-pro-oc2`, two packages: 2.0–2.2 and 2.3, PHP 5.6+) and OpenCart 1.5.4–1.5.6.4 (`announcement-bar-pro-oc15`, vQmod, PHP 5.6+).
- Schema stays 8: upgrading 2.4.1 → 2.4.2 replaces files only. Tested: `COMPATIBILITY.md` of the universal package.

## 2.4.1 — 2026-11-08
First release for OpenCart 2.0.0.0 – 2.3.0.2, with the same features as the OpenCart 3 / 4 editions 2.4.1
(several bars and inline boxes with priority, targeting, tiered goals, coupons, triggers, A/B test with statistics,
GDPR double opt-in sign-up with subscriber list and CSV export, Cloudflare Turnstile, templates, custom CSS, GA4,
"wait for cookie consent"; EN / LV / RU). Database schema 8, shared with the other editions.

OpenCart 2.x specifics:
- Two packages: 2.0–2.2 (entry file in `admin/controller/module/`) and 2.3 (without it — on 2.3 that file would
  redirect every module call through OpenCart's compatibility event).
- 2.2 / 2.3: view events with a theme wildcard (`catalog/view/*/common/header/after` …), because OpenCart's theme
  event has already turned the view route into `<theme>/template/...`.
- 2.0 / 2.1: no view events — `install.xml` hands each storefront template from the loader to the same handlers.
- Admin and storefront templates are PHP `.tpl` files generated from the OpenCart 3 Twig templates
  (`tools/twig2tpl.py`, checked by `build.sh`).
- Version adapter `AbpOc2` (`system/library/announcementbarpro/oc2.php`): routes and token per version, customer
  group model (`sale/` in 2.0), Free Shipping keys (`free_*`), mail settings (2.0.0.0 `config_mail` array,
  2.0.2+ `config_mail_*`), language flags, theme directory; PHP 5.6 polyfills for `random_bytes` and `intdiv`.
- Language files for every 2.x language folder (`english`/`en-gb`, `latvian`/`lv-lv`, `russian`/`ru-ru`); the texts
  live once in `system/library/announcementbarpro/language/`.
- PHP 5.6: exceptions are caught as `Exception` as well as `Throwable`; the Turnstile transport never throws.
