# Pro licence — how it works (WordPress edition)

## Version 1.0.0 (now)

- One plugin. The Pro features unlock with a licence key under **Announcement Bar → Settings → Licence**.
- The check is **local only**: `Local_Licence_Provider` (`includes/class-local-licence-provider.php`) accepts keys of the form
  `ABP1-XXXX-XXXX-XXXX-XXXX` (alphabet `23456789ABCDEFGHJKLMNPQRSTUVWXYZ`, last character = weighted checksum of the first 15).
  It catches typos; it is not copy protection (the plugin is GPL — nobody can be stopped from editing the code).
- No network request is made, ever. The key and its status are stored in the option `abpro_licence` (masked on screen).
- `Licence::is_pro()` is the single switch; the filter `abpro_is_pro` can override it (tests, agencies).
- Without a licence, stored Pro data is kept. Pro messages that narrow the audience (pages, roles, countries, cart rules,
  campaigns, triggers, inline boxes, tiers) are not shown; Pro looks fall back to the free look.

## Generating keys (vendor side)

Any 15 characters from the alphabet + `Local_Licence_Provider::checksum( $body )`. Keep a list of issued keys in the shop
(order → key) so support can look them up. Keys are not secret once sold.

## Later: remote activation (not built yet)

1. A licence server (own small service or a licensing SaaS) issues keys per order and plan (number of sites).
2. A `Remote_Licence_Provider implements Licence_Provider` is registered with the filter `abpro_licence_provider`.
   - `activate( $key )`: one HTTPS `wp_remote_post()` to the licence server **only when the admin presses "Activate"**, with
     the key and the site URL (nothing else). The screen says so next to the button.
   - `deactivate( $key )`: frees the site slot when "Remove key" is pressed.
   - `status( $key )`: no network — reads the cached answer (e.g. a signed token with an expiry, checked with a public key).
   - Optional weekly re-check by WP-Cron only if the admin agreed; failures never lock features immediately (grace period).
3. Updates of the Pro build: through the same server, only for active keys.

## WordPress.org

The wordpress.org guidelines (no. 5, "trialware") do not allow locked features inside a plugin hosted there. If the
plugin is to be listed on wordpress.org, the build must be split: the free part as "Announcement Bar Pro" (or a free
name) on wordpress.org, and the Pro features as a separate add-on sold by 3DP SIA. The code is already prepared for
that split: the gate is in one place (`Licence::is_pro()`), Pro UI is grouped in marked fieldsets.
