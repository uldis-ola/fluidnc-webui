# Announcement Bar Pro — WordPress / WooCommerce edition 1.0.0

Two plugins, both GPL-2.0-or-later, seller 3DP SIA, support extension@3dp.lv:

| Folder | What | Where it is published | Text domain |
|---|---|---|---|
| `announcement-bar-pro/` | free plugin: top bar, rotating / stacked messages, schedules, countdowns, WooCommerce free-shipping progress, buttons, icons, 3 styles, device targeting, live preview, hooks for add-ons | wordpress.org | `announcement-bar-pro` |
| `announcement-bar-pro-premium/` | Premium add-on: every other feature + licence key (local check, see `LICENSING.md`) + optional Cloudflare Turnstile | sold by 3DP SIA | `announcement-bar-pro-premium` |

The add-on declares `Requires Plugins: announcement-bar-pro` (WordPress 6.5+) and, on older WordPress, shows an admin
notice when the free plugin is missing, inactive or the old single build (no `ABPRO_API` constant). Its classes live in
the namespace `AnnouncementBarPro\Premium`; it loads on `plugins_loaded` (priority 5), after the free plugin.

## Name and slug (recommendation)

Keep the slug, folder and text domain **`announcement-bar-pro`** and the display name **"Announcement Bar Pro"** for the
wordpress.org plugin:

- The wordpress.org guidelines have no rule against "Pro" in a name (the naming rules are about trademarks and
  misleading names); what guideline 5 forbids is locked or trial code — the free plugin has none.
- Only the same folder lets existing sites of the single-plugin build 1.0.0 update in place (WordPress identifies a
  plugin by folder / main file), keeps the translations' text domain and needs no migration.
- The display name can be changed later at any time; the slug cannot. If the review team asks for a name without
  "Pro", change only `Plugin Name` / the readme title (e.g. "Announcement Bar by 3DP SIA") and keep the slug.
- Before submitting, check that the slug `announcement-bar-pro` is still free on wordpress.org (not checked from here).

## Hooks for add-ons (API version `ABPRO_API` = 1)

Without an add-on that returns `true` from `abpro_handles_extras`, the free plugin hides messages that narrow their
audience with rules it does not check (`Options::narrows()`: unknown options, box placements, other types, page /
role / product rules) and ignores options that only change the look. Columns, options and texts it has no field for
keep their stored values on save.

| Area | Filters | Actions |
|---|---|---|
| Settings | `abpro_settings_defaults`, `abpro_setting_choices` (position, mode, style), `abpro_effective_settings`, `abpro_validate_settings` | `abpro_settings_top`, `abpro_settings_bar`, `abpro_settings_cards` |
| Admin | `abpro_admin_tabs`, `abpro_admin_notices`, `abpro_list_columns`, `abpro_message_badges`, `abpro_blank_message`, `abpro_message_types`, `abpro_style_choices`, `abpro_message_from_post` | `abpro_admin_assets`, `abpro_admin_header`, `abpro_list_cell`, `abpro_list_actions`, `abpro_list_empty_actions`, `abpro_message_action`, `abpro_form_texts`, `abpro_form_look`, `abpro_form_goal`, `abpro_form_schedule`, `abpro_form_audience`, `abpro_form_cards`, `abpro_form_preview_tools` |
| Storefront | `abpro_handles_extras`, `abpro_placements`, `abpro_message_targets`, `abpro_item_row`, `abpro_goal`, `abpro_variant_names`, `abpro_countdown`, `abpro_item`, `abpro_need`, `abpro_container`, `abpro_common`, `abpro_container_class`, `abpro_container_attrs`, `abpro_container_controls`, `abpro_item_attrs`, `abpro_item_html`, `abpro_visitor_place`, `abpro_client_ip` | `abpro_enqueue` |
| Endpoints | `abpro_cart_row`, `abpro_preview_row`, `abpro_preview_css`, `abpro_placement_label` | — (REST namespace `announcement-bar-pro/v1` is shared; `Endpoints::same_origin()` / `limited()` are public) |

Admin JS: `window.abpAdminApi` (`addDeps`, `addParams`, `confirm`, `preview`, `render`, `dependents`). The admin form and
settings page field helpers (`input`, `select`, `checkbox`, `textarea`, `group`, `card`, `row`, `number`, `check` …) are
public for add-ons. `Frontend::inline_html( $placement )` prints the box of an add-on placement.

## Shared with the OpenCart editions (2.4.1)

- `announcement-bar-pro/assets/js/announcement-bar-pro.js`, `announcement-bar-pro-pre.js`,
  `assets/css/announcement-bar-pro.css` are **byte-identical** to the OpenCart 3 / OpenCart 4 storefront files of
  2.4.1 (LF line endings; `build.sh` here and `announcement-bar-pro-universal/build.sh` both compare them). They contain
  the `cartAgain` fix (a cart change during a running cart refresh is refreshed afterwards) and the Cloudflare Turnstile
  client. The script is a generic runner of `data-*` attributes: features start only when the markup asks for them, and
  the free plugin never prints the markup of premium features, so nothing in it is locked (it also carries no licence
  logic). Keeping one file for all three editions was chosen over a trimmed free copy so that fixes reach every edition.
- `announcement-bar-pro-premium/includes/class-features.php` is the port of the OpenCart `features.php` (the file
  header lists every intended difference: ISO country codes, state codes, attachment IDs, site time zone, SVG icons);
  the free plugin keeps only `class-sanitizer.php` (port of `sanitizer.php`) and the small `class-options.php`.
- WordPress-only: `assets/js/announcement-bar-pro-wp.js` (WooCommerce events, cart / checkout blocks, WP Consent API),
  `assets/css/announcement-bar-pro-wp.css`, inline SVG icons (`includes/class-icons.php`).

## Build and checks

```bash
bash build.sh                       # dist/announcement-bar-pro-1.0.0.zip + dist/announcement-bar-pro-premium-1.0.0.zip
php tests/unit/run.php              # options, sanitizer, features, licence, Turnstile (mocked), tokens, no premium code in the free plugin
phpcs --standard=phpcs.xml.dist          # free plugin: WordPress Coding Standards 3 + PHPCompatibilityWP 7.4+
phpcs --standard=phpcs-premium.xml.dist  # add-on
wp plugin check announcement-bar-pro     # Plugin Check (in the local test shop)
bash tests/e2e/run-wp.sh            # local WP + WooCommerce (abp-testenv/scripts/wpenv.sh), Playwright: free alone,
                                    # free + Premium (incl. Turnstile with Cloudflare's dummy keys, re-subscribe rule),
                                    # add-on off again, then run-upgrade.sh (single build 1.0.0 → free → + add-on)
```
