# Announcement Bar Pro — compatibility matrix

Only what was actually installed and tested is listed. Everything else is **not tested**.

## How it was tested (2026-11-08)

- Windows 11, official PHP Windows NTS builds (php.net) with PHP's built-in web server, Playwright Chromium.
- Databases: MariaDB 11.4.4 and MySQL 8.4.13 (community, default settings: strict SQL mode, `caching_sha2_password`).
- Every OpenCart row: a **fresh OpenCart install** (OpenCart's own CLI installer; its web installer for 4.0.0.x–4.0.1.x,
  whose CLI installer does not run), then `tests/e2e/abp24-e2e.js` (the 2.4.x end-to-end test through the real admin and
  storefront at 1366 and 390 px: every feature, sign-up with double opt-in through the shop's SMTP settings, Cloudflare
  Turnstile with Cloudflare's test keys, no JavaScript errors, no new PHP error-log entries, contrast, no horizontal scroll).
  - OpenCart 3: install 2.3.0 with messages → upgrade to 2.4.0 → upgrade to 2.4.1 through the Extension Installer
    (165 checks). OpenCart 4: fresh install of 2.4.1 (150 checks).
- WordPress rows: fresh WordPress + WooCommerce + Storefront, then `announcement-bar-pro-wordpress/tests/e2e/wp-e2e.js`
  (free plugin alone, then the Premium add-on with a licence key, block theme with cart / checkout / Mini-Cart blocks,
  licence removed, add-on deactivated; ~190 checks). The upgrade test from the single-plugin build 1.0.0 was not part of
  these runs.
- Scripts of the test environment: `abp-testenv/compat/` (own MariaDB :3313, MySQL 8 :3308, ports 8160–8169).

## 2.4.2 final — runs after the review fixes (commit 357272a, 2026-11-08)

The release build. Same method and test environment as the runs below; every row is a fresh shop. These numbers
replace the earlier 2.4.2 rows for the same platform (the end-to-end tests gained checks for the review fixes).

| Edition | Platform | PHP | Test | Result |
|---|---|---|---|---|
| OpenCart 3 (universal zip) | 3.0.5.1 | 8.3 | `abp24-e2e.js`: 2.3.0 → 2.4.0 → 2.4.1 → **2.4.2** upgrades | ✅ 169/169 |
| OpenCart 4 (universal zip) | 4.1.0.4 | ¹ | `abp24-e2e.js`: fresh install of 2.4.2 | ✅ 150/150 |
| OpenCart 2.0–2.2 zip | 2.0.3.1 | ¹ | `announcement-bar-pro-oc2/tests/e2e/abp-oc2-e2e.js` | ✅ 140/140 |
| OpenCart 2.0–2.2 zip | 2.1.0.2 | ¹ | same | ✅ 140/140 |
| OpenCart 2.3 zip | 2.3.0.2 | ¹ | same | ✅ 140/140 |
| OpenCart 1.5 zip (vQmod 2.6.6) | 1.5.6.4 | 5.6 | `announcement-bar-pro-oc15/tests/e2e/abp15-e2e.js` | ✅ 177/177 |
| WordPress free 1.0.1 + Premium 1.0.1 | WordPress + WooCommerce latest | ¹ | `wp-e2e.js` | ✅ 191/191 |
| Embed 1.0.1 | static pages, **Chromium only** | — | `announcement-bar-pro-embed/tests/e2e/e2e.js` | ✅ 105/105 |

¹ The PHP version of these reruns is not recorded in this table. The earlier runs of the same platform (below) used:
OpenCart 4.1.0.4 PHP 8.3.36; OpenCart 2.0.3.1 / 2.1.0.2 PHP 5.6.40; OpenCart 2.3.0.2 PHP 7.4.33; WordPress 7.1.4 +
WooCommerce 11.2.2 PHP 8.3.36.

Unit tests (2.4.2 final): universal `schema_test.php` **249/249**; OpenCart 2.x `schema_test.php` **143/143** and
`oc2_test.php` passed; OpenCart 1.5 `schema15_test.php` **150/150**; WordPress `run.php` **83/83**; embed unit **151/151**.

Not re-run with the final build (their last result is in the sections below): OpenCart 3.0.3.8, 4.0.x, 4.1.0.0,
2.0.0.0, 2.2.0.0, 1.5.5.1, WordPress 6.2 / 6.5, multisite.

## 2.4.2 — first runs of 2026-11-08 (OpenCart 2.4.2, WordPress 1.0.1, Embed 1.0.1)

Same method as below, own test instances (`abp-testenv/run242/`: MariaDB 11.4.4 on :3314 with its own datadir and global
`sql_mode=NO_ENGINE_SUBSTITUTION` — OpenCart 3 / 4 and WordPress set their own session SQL mode; PHP built-in servers
on :8170–8178, SMTP sinks :8185–8189). Every row is a fresh shop.

| Edition | Platform | PHP | Test | Result |
|---|---|---|---|---|
| OpenCart 3 (universal zip) | 3.0.5.1 | 8.3.36 | `abp24-e2e.js`: 2.3.0 → 2.4.0 → 2.4.1 → **2.4.2** upgrades through the Extension Installer | ✅ 169/169 (an earlier attempt stopped at the double opt-in mail: the test env had pointed its SMTP sink at another run's mail folder — env error, fixed) |
| OpenCart 4 (universal zip) | 4.1.0.4 | 8.3.36 | `abp24-e2e.js`: fresh install of 2.4.2 | ✅ 150/150 |
| OpenCart 4 (universal zip) | 4.0.2.3 | 8.1.34 | `abp24-e2e.js`: fresh install of 2.4.2 | ✅ 150/150 |
| OpenCart 2.3 zip | 2.3.0.2 | 7.4.33 | `announcement-bar-pro-oc2/tests/e2e/abp-oc2-e2e.js` | ✅ 136/136 |
| OpenCart 2.0–2.2 zip | 2.0.3.1 | 5.6.40 | same | ✅ 136/136 (first run 135/136: one database read of the test returned nothing while three other shops were being installed; fresh-shop rerun clean) |
| OpenCart 2.0–2.2 zip | 2.0.0.0 | 5.6.40 | same, with the fixed test (modification check on 2.0.0.0) | ✅ 136/136 |
| OpenCart 1.5 zip (vQmod 2.6.6) | 1.5.6.4 | 5.6.40 | `announcement-bar-pro-oc15/tests/e2e/abp15-e2e.js` (images stored as `data/…`, no adapter script) | ✅ 177/177 |
| WordPress free 1.0.1 + Premium 1.0.1 | WordPress 7.1.4, WooCommerce 11.2.2 | 8.3.36 | `wp-e2e.js` | ✅ 191/191 |
| WordPress free 1.0.1 + Premium 1.0.1 | WordPress 6.5, WooCommerce 9.3.3 | 7.4.33 | `wp-e2e.js` | ✅ 191/191 with Twenty Twenty-Four as block theme (`BLOCKTHEME=twentytwentyfour`; two runs started without it failed the 2 block-theme checks, since 6.5 has no Twenty Twenty-Five) |
| Embed 1.0.1 | static pages, **Chromium only** | — | `announcement-bar-pro-embed/tests/e2e/e2e.js` (basic, rules, CSP, nonce, XSS, Shopify adapter, Configurator, all-in-one snippet) | ✅ 101/101 |

Unit tests (2.4.2): universal `schema_test.php` 243/243 on PHP 7.4 / 8.0 / 8.1 / 8.2 / 8.3; OpenCart 2.x `schema_test.php`
140/140 on PHP 5.6 / 7.4 / 8.3 and `oc2_test.php` passed; OpenCart 1.5 `run.sh` 147/147 on PHP 5.6 and 7.4 (bare and with
ini); WordPress `run.php` 80/80 on PHP 7.4 and 8.3; embed unit 151/151; demo `tests/demo/test.js` 64/64.
`php -l`: OpenCart 3 on PHP 7.4–8.3, OpenCart 4 on 8.0–8.3, OpenCart 2.x and 1.5 on 5.6, 7.4 and 8.0–8.3, WordPress on
7.4–8.3 — no errors.

## OpenCart 1.5 and 2.x editions — 2.4.1 runs (kit reports, 2026-11-08)

| Edition | OpenCart | PHP | Database | Result |
|---|---|---|---|---|
| OpenCart 1.5 (vQmod 2.6.6) | 1.5.6.4 | 5.6.40 | MariaDB 11.4 | ✅ 177/177 |
| OpenCart 1.5 (vQmod 2.6.6) | 1.5.6.4 | 7.4.33 (OpenSSL `encryption.php`, 1.5 needs mcrypt) | MariaDB 11.4 | ✅ 177/177 |
| OpenCart 1.5 (vQmod 2.6.6) | 1.5.5.1 | 5.6.40 | MariaDB 11.4 | ✅ 177/177 |
| OpenCart 2.0–2.2 zip | 2.0.0.0 | 5.6.40 | MariaDB 11.4 | ⚠️ 135/136 — test issue (modification check), fixed in the test; 2.4.2 rerun 136/136 |
| OpenCart 2.0–2.2 zip | 2.0.3.1 | 5.6.40 | MariaDB 11.4 | ✅ 136/136 |
| OpenCart 2.0–2.2 zip | 2.1.0.2 | 5.6.40 | MariaDB 11.4 | ✅ 136/136 |
| OpenCart 2.0–2.2 zip | 2.2.0.0 | 7.4.33 | MariaDB 11.4 | ✅ 136/136 |
| OpenCart 2.3 zip | 2.3.0.2 | 7.4.33 | MariaDB 11.4 | ✅ 136/136 |

PHP minimum of these editions: **5.6** (the shared library uses array class constants). PHP 5.4 / 5.5 are not supported
and were not tested. Not tested: OpenCart 1.5.4.x, 2.1.0.1, 2.3.0.0–2.3.0.1, PHP 8.x with these editions.

## OpenCart — package `announcement_bar_pro.ocmod.zip` 2.4.1

| OpenCart | PHP | Database | Result | Date |
|---|---|---|---|---|
| 3.0.3.8 | 7.4.33 | MariaDB 11.4 | ✅ 165/165 | 2026-11-08 |
| 3.0.3.8 | 8.0.30 | MariaDB 11.4 | ✅ 165/165 (first run 164/165: one database read of the test itself returned nothing under load; the feature was confirmed by the next check and in the database; rerun clean) | 2026-11-08 |
| 3.0.5.1 | 8.1.34 | MariaDB 11.4 | ✅ 165/165 | 2026-11-08 |
| 3.0.5.1 | 8.2.35 | MariaDB 11.4 | ✅ 165/165 | 2026-11-08 |
| 3.0.5.1 | 8.3.36 | MariaDB 11.4 | ✅ 165/165 | 2026-11-08 |
| 3.0.5.1 | 8.3.36 | **MySQL 8.4.13** | ✅ 165/165 | 2026-11-08 |
| 4.0.0.0 | 8.0.30 | MariaDB 11.4 | ✅ 150/150 | 2026-11-08 |
| 4.0.0.0 | 8.1.34 | MariaDB 11.4 | ✅ 150/150 ¹ | 2026-11-08 |
| 4.0.1.1 | 8.0.30 | MariaDB 11.4 | ✅ 150/150 ¹ | 2026-11-08 |
| 4.0.1.1 | 8.1.34 | MariaDB 11.4 | ✅ 150/150 ¹ | 2026-11-08 |
| 4.0.2.3 | 8.0.30 | MariaDB 11.4 | ✅ 150/150 ¹ | 2026-11-08 |
| 4.0.2.3 | 8.1.34 | MariaDB 11.4 | ✅ 150/150 ¹ | 2026-11-08 |
| 4.1.0.0 | 8.0.30 | MariaDB 11.4 | ✅ 150/150 after fix 2 (the run before it stopped at the category picker) | 2026-11-08 |
| 4.1.0.0 | 8.1.34 | MariaDB 11.4 | ✅ 150/150 | 2026-11-08 |
| 4.1.0.4 | 8.1.34 | MariaDB 11.4 | ✅ 150/150 | 2026-11-08 |
| 4.1.0.4 | 8.3.36 | MariaDB 11.4 | ✅ 150/150 | 2026-11-08 |

All OpenCart 4.0.0.x–4.0.1.x rows ran with fix 1 (before it the double opt-in e-mail was not sent there).
¹ Ran before fix 2 (own category search); fix 2 was then tested on 4.0.0.0 / PHP 8.0 (`|` separator), 4.1.0.0 and 4.1.0.4.

4.1.0.4 is the newest OpenCart 4 release on github.com/opencart/opencart/releases (checked 2026-11-08).

**Not possible / not tested**

- OpenCart's own PHP minimum (checked in its `system/startup.php`): 3.0.5.1 needs PHP 8.1+, 4.0.x 8.0+, 4.1.0.0 8.0.2+,
  4.1.0.4 8.1+. So 3.0.5.1 on PHP 7.4 / 8.0 and 4.1.0.4 on PHP 8.0 cannot run at all.
- OpenCart 4.0.0.0 on PHP 8.3: OpenCart's own core prints "Creation of dynamic property" deprecation notices and its
  web installer stops on them ("headers already sent") — OpenCart, not the module. Use PHP 8.0 / 8.1 for 4.0.0.x–4.0.1.x.
- Not tested: OpenCart 3.0.0.0–3.0.3.7, 3.0.3.9–3.0.5.0, 4.0.1.0, 4.0.2.0–4.0.2.2, 4.1.0.1–4.1.0.3; PHP 7.3 and older;
  MySQL 5.7 / MariaDB 10.x; MySQL 8 with OpenCart 4; Apache / nginx / LiteSpeed (PHP's built-in server only);
  multi-store setups.

## WordPress — `announcement-bar-pro` 1.0.0 + `announcement-bar-pro-premium` 1.0.0

| WordPress | WooCommerce | PHP | Database | Result | Date |
|---|---|---|---|---|---|
| 6.2 | 8.2.2 | 8.0.30 | MariaDB 11.4 | ✅ 175/176 ² — cart / checkout block checks skipped (WooCommerce 8.2 makes shortcode pages); the 1 failure was the trigger timing check of the test (see ³) | 2026-11-08 |
| 6.2 | 8.2.2 | 7.4.33 | MariaDB 11.4 | ⚠️ 170/176 with an earlier test version — failures were test timing on the slow test server (preview countdown read after 0.9 s, trigger, Mini-Cart block checks before the drawer had moved the box) and the Mini-Cart box sticking out of the drawer (fixed in CSS afterwards); not re-run with the final version | 2026-11-08 |
| 6.5 | 9.3.3 | 7.4.33 | MariaDB 11.4 | ✅ 191/191 (final version of code and test) | 2026-11-08 |
| 6.5 | 9.3.3 | 8.0.30 | MariaDB 11.4 | ⚠️ 185/191 with the first test version (Mini-Cart block test timing, trigger timing, `abp-paused` counted as a pause button — all test issues, fixed); not re-run | 2026-11-08 |
| 6.5 | 9.3.3 | 8.0.30 | **MySQL 8.4.13** | ⚠️ 186/191 with an earlier test version (same test issues; all sign-up / subscriber / statistics checks passed); not re-run | 2026-11-08 |
| 7.1.4 (latest) | 11.2.2 (latest) | 8.3.36 | MariaDB 11.4 | ✅ 191/191 (final version of code and test) | 2026-11-08 |

² WordPress' own minimum of the plugins (`Requires at least: 6.2`, `Requires PHP: 7.4`) is covered by these rows.
³ "Trigger after 2 s" was a test problem: it cleared the visit's memory while the page's own 2 s trigger had not fired
yet, so the reloaded page showed the message at once (correct: a trigger fires once per visit). The final test waits for
that trigger first and then watches the attribute from the first parsed byte of the reload (final test run: WordPress 7.1, passed).

**Multisite** (WordPress 6.5, WooCommerce 9.3.3, PHP 8.0.30, MariaDB 11.4, subdirectory network, `abp-testenv/compat/ms-test.sh`):
✅ 14/14 — network activation of both plugins (tables and settings on every site), a site created after the network
activation gets its own tables and settings (`wp_initialize_site`), settings and messages are per site (each storefront
shows only its own messages), network uninstall of both plugins removes the data only on the site whose "Delete all
messages and settings when the plugin is deleted" is on and keeps it on the others, nothing in `debug.log` from the
plugins. WordPress multisite does not support a port in the site address, so the network ran as `localhost` and the
storefront checks were HTML checks (no browser).

**WooCommerce Mini-Cart block:** supported by the Premium add-on (fix 3): the mini-cart box appears in the block's drawer
(filled cart: above the product list; empty cart: at the top), on WooCommerce 8.2 (React drawer), 9.3 and 11.2
(Interactivity API drawer). The classic mini-cart keeps its server-side box.

**Not tested:** WordPress 6.3, 6.4, 6.6–7.0; WooCommerce 8.0–8.1, 8.3–9.2, 9.4–11.1; the cart / checkout **blocks** on
WooCommerce 8.2 (it creates shortcode cart / checkout pages; those checks were skipped there); PHP 8.1 / 8.2 with
WordPress; MySQL 8 with WordPress 6.2 / latest; subdomain multisite; other themes than Storefront, Twenty Twenty-Three,
Twenty Twenty-Four and Twenty Twenty-Five.

## Fixes made in this pass

1. **OpenCart 4.0.0.x–4.0.1.x:** the double opt-in e-mail ignored the shop's SMTP settings (their `Mail` class takes
   only the engine in its constructor and reads the options from public properties). The options are now also set as
   properties on those versions (`catalog/controller/module/announcement_bar_pro.php`).
2. **OpenCart 4.1.0.0:** its `catalog/category.autocomplete` matches whole category names only, so the category picker of
   the message form found nothing while typing. The module now has its own category search (`…|categories` /
   `….categories`: names containing the text, shown with their path, 10 at most).
3. **WordPress Premium add-on:** the mini-cart box also in the WooCommerce Mini-Cart block (`assets/minicart-block.js`,
   footer `<template>`; style in the free plugin's `announcement-bar-pro-wp.css`).
4. Tests: `abp24-e2e.js` handles OpenCart 4.0.0.x–4.0.1.x (`|` route separator, currency links, the NOT NULL
   `customer.wishlist` column of 4.0.0.x) and takes `SMTPPORT`; `wp-e2e.js` takes `BLOCKTHEME`, checks the Mini-Cart
   block, skips cart / checkout block checks when WooCommerce created shortcode pages, and no longer counts WordPress.org
   connection warnings or the `abp-paused` class (rotation paused under the mouse) as failures.
