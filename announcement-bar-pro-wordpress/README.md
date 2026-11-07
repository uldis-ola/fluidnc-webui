# Announcement Bar Pro — WordPress / WooCommerce edition 1.0.0

Plugin folder: `announcement-bar-pro/` (slug and text domain `announcement-bar-pro`). Seller: 3DP SIA, support extension@3dp.lv.
Licence: GPL-2.0-or-later. Pro unlock: see `LICENSING.md`.

## Shared with the OpenCart editions (2.4.0)
- `assets/js/announcement-bar-pro.js`, `announcement-bar-pro-pre.js`, `assets/css/announcement-bar-pro.css` — the OpenCart
  storefront files. One change (to bring back into OpenCart 2.4.1): a cart change during a running cart refresh is no longer
  dropped (`cartAgain` in `refreshCart()`).
- `includes/class-sanitizer.php`, `includes/class-features.php` — ports of `sanitizer.php` / `features.php`; the file headers
  list every intended difference (ISO country codes, state codes, attachment IDs, site time zone, SVG icons).
- WordPress-only: `assets/js/announcement-bar-pro-wp.js` (WooCommerce events, cart / checkout blocks, WP Consent API),
  `assets/css/announcement-bar-pro-wp.css`, inline SVG icons (`includes/class-icons.php`).

## Build and checks
```bash
bash build.sh                                  # dist/announcement-bar-pro-1.0.0.zip
php tests/unit/run.php                         # sanitizer, features, e-mail injection guard, licence (60 cases)
phpcs --standard=phpcs.xml.dist                # WordPress Coding Standards 3 + PHPCompatibilityWP 7.4+
bash tests/e2e/run-wp.sh                       # local WP + WooCommerce (abp-testenv/scripts/wpenv.sh), Playwright
```
