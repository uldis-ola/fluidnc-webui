# Licence key — how it works (WordPress edition)

## Two plugins (since the wordpress.org split)

- **`announcement-bar-pro`** — the free plugin for wordpress.org. It holds no licence code, no locked or disabled
  fields, no "Pro" badges and no upsell beyond one honest line in its `readme.txt` (wordpress.org guideline 5,
  "trialware"). `tests/unit/run.php` checks its source for licence / premium code; `build.sh` refuses to build when that
  check fails. It offers hooks (README.md, "Hooks for add-ons") that any add-on may use.
- **`announcement-bar-pro-premium`** — the paid add-on sold by 3DP SIA (GPL-2.0-or-later, not for wordpress.org). It
  holds every premium feature and the licence check. Without a valid key it registers only the licence card, the
  subscriber screen (export / erasure stay possible) and the privacy tools.

## Version 1.0.0 of the add-on (now)

- The premium features unlock with a licence key under **Announcement Bar → Settings → Premium licence**.
- The check is **local only**: `Local_Licence_Provider` (`announcement-bar-pro-premium/includes/class-local-licence-provider.php`)
  accepts keys of the form `ABP1-XXXX-XXXX-XXXX-XXXX` (alphabet `23456789ABCDEFGHJKLMNPQRSTUVWXYZ`, last character =
  weighted checksum of the first 15). It catches typos; it is not copy protection (the code is GPL — nobody can be
  stopped from editing it).
- No network request is made for the licence, ever. The key and its status are stored in the option `abpro_licence`
  (masked on screen) — the same option as the single-plugin build 1.0.0, so a key entered there keeps working.
- `Licence::is_pro()` is the single switch; the filter `abpro_is_pro` can override it (tests, agencies).
- Without a licence, stored premium data is kept. The free plugin then hides messages that narrow the audience
  (pages, roles, countries, cart rules, campaigns, triggers, boxes, tiers) and shows the others with its own look.

## Generating keys (vendor side)

Any 15 characters from the alphabet + `Local_Licence_Provider::checksum( $body )`. Keep a list of issued keys in the
shop (order → key) so support can look them up. Keys are not secret once sold.

## Later: remote activation (not built yet)

1. A licence server (own small service or a licensing SaaS) issues keys per order and plan (number of sites).
2. A `Remote_Licence_Provider implements Licence_Provider` (namespace `AnnouncementBarPro\Premium`) is registered with the
   filter `abpro_licence_provider`.
   - `activate( $key )`: one HTTPS `wp_remote_post()` to the licence server **only when the admin presses "Activate"**,
     with the key and the site URL (nothing else). The screen says so next to the button.
   - `deactivate( $key )`: frees the site slot when "Remove key" is pressed.
   - `status( $key )`: no network — reads the cached answer (e.g. a signed token with an expiry, checked with a public key).
   - Optional weekly re-check by WP-Cron only if the admin agreed; failures never lock features immediately (grace period).
3. Updates of the add-on: through the same server, only for active keys (e.g. a small update checker on
   `pre_set_site_transient_update_plugins` for `announcement-bar-pro-premium` only — never for the free plugin, which
   updates from wordpress.org).

## Upgrade path from the single-plugin build 1.0.0

The free plugin keeps the folder, main file, text domain, options (`abpro_settings`, `abpro_secret`, `abpro_schema`) and
tables (`abpro_messages`, `abpro_message_texts`, `abpro_rate`) of the single build; the add-on uses the same
`abpro_licence` option and `abpro_subscribers` table (it creates the table only if it is missing). Replacing the single
build by the free plugin and then activating the add-on keeps every message, statistic, subscriber, setting and the
licence key (tested in `tests/e2e/wp-upgrade-e2e.js`).
