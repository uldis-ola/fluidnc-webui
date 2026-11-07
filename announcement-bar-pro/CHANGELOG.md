# Changelog

## 2.4.1 — 2026-11-07
Optional **Cloudflare Turnstile** anti-bot check of the e-mail sign-up form (site key + secret key in the settings; on only with both; the secret is never printed into any page, the storefront or an export; server-side `siteverify` check, fail closed). **Re-subscribe rule:** a new sign-up of an unsubscribed address no longer overwrites the unsubscribe record — with double opt-in only a new confirmation token is stored and mailed, and only the owner's confirmation subscribes the address again. Storefront script: a cart change during a running cart refresh is no longer lost (`cartAgain`); the storefront JS / CSS is byte-identical to the WordPress edition. Schema stays 8. Full list: `CHANGELOG.md` of the universal package.

## 2.4.0 — 2026-11-07
Several bars + inline boxes with priority, country / cart / campaign / new-or-returning targeting, per-country and per-currency free-shipping amounts, OpenCart coupons, tiered goals, triggers, recurring countdown, e-mail sign-up with double opt-in, background image, system fonts, custom CSS, auto-hide, pause button, 13 templates, GA4 events; DB schema 8. Pre-release review fixes: e-mail header injection through quoted addresses closed, CloudFlare headers trusted only from CloudFlare IP ranges, no account newsletter changes or re-subscriptions without double opt-in, 3 confirmation mails per address per day, sign-up limits in Settings, coupon hidden on its end date (as OpenCart), Wait for cookie consent setting, cheaper cart endpoint. Full list: `CHANGELOG.md` of the universal package (`announcement-bar-pro-universal`).
- Fixed: the privacy-policy link in the e-mail sign-up form was HTML-escaped twice (`&amp;amp;`), so it could open the wrong page.

## 2.0.5 — 2026-11-05
Fixes from the black-box test on OpenCart 4.1.0.4 (all apply to both packages):
- Sticky top works on themes that wrap the page in an absolutely positioned container (OpenCart 4.1 `#container`). The bar is pinned with `position:fixed` plus a spacer when CSS sticky cannot work.
- Bottom position: the extra bottom space is also added to such a wrapper, so the end of the footer stays reachable.
- Visible keyboard focus on the close button, button and links, even where the theme removes `button:focus` outlines.
- HTML mode: an unclosed `<svg>`, `<math>`, `<form>` or `<button>` no longer swallows the rest of the text; these tags are unwrapped.
- Plain mode: text such as "x < y and y > z" is no longer taken for a tag.
- A message limited to stores or customer groups that were later deleted no longer silently becomes "all stores/groups" on the next save. The form warns and asks for a choice.
- A non-numeric `message_id` is rejected.
- Delete with nothing selected shows an error, and a successful delete shows a confirmation (OC4).
- Rotation reserves the tallest message height only when messages are of similar height, so one very long message no longer makes short ones huge on phones.

## 2.0.4 — 2026-11-05
Supersedes: 2.0.3 and older. Fixes from the second round of independent reviews.

- **Security/stability:** the HTML sanitizer could loop forever on attributes such as `xml:lang` or `xmlns`, which could hang every storefront request. It now rebuilds each allowed element with only allowed attributes. Nested links are unwrapped.
- Re-saving a message without content changes no longer re-shows it to visitors who closed it. The dismiss key now comes from the text, button, link and icon.
- Countdown and live expiry use the server clock (`data-now`), not the visitor's.
- A tap on a touch screen pauses rotation for at most 8 s, and a tap outside the bar resumes it.
- A `<` that cannot start a tag ("x < 5") is kept in HTML mode.
- Non-numeric store and customer-group IDs are rejected.
- The link error mentions http(s). The allow-HTML help tooltip no longer renders raw tags.
- Dismiss data in browser storage is type-checked.
- Latvian texts now use a consistent formal register. The missing-language hint is clearer.

## 2.0.3 — 2026-11-05
Supersedes: 2.0.2 and older. An OpenCart 4.0.x / 4.1.x version with the same features is a separate package (`announcement-bar-pro-oc4`).

- Closing is now remembered per message (localStorage key `abp_dismiss2`). A message that expires or is switched off no longer brings back a bar the visitor closed; new or edited messages still show.
- No inline script: the pre-paint dismiss check moved to `announcement_bar_pro_pre.js` (CSP-friendly).
- New "Until the end of that day (23:59:59)" checkbox on the end date, so the date picker's 00:00 no longer cuts off the last day.

## 2.0.2 — 2026-11-05
Supersedes: 2.0.1, 2.0.0, 1.0.0. Fixes from three independent reviews (security audit, OpenCart code review, black-box test).

- Messages fall back to the store's default-language text when the visitor's language has none. The admin list flags missing languages and never shows blank rows.
- An end date without a time now means the end of that day (23:59:59).
- Plain-text mode keeps text such as "a<b" and turns `<br>`/block tags into spaces, so words are no longer glued together.
- Malformed HTML (stray `</div>`) no longer wipes the whole text.
- Links are restricted to RFC 3986 characters, without quotes, `<`, `>`, `{`, `}` or apostrophe. IDN domains are converted to punycode.
- The bar is injected after the real `<body>` tag, never inside comments or scripts. A regex failure leaves the header untouched.
- The sanitizer is no longer registered under the generic registry name `sanitizer`. Catalog language keys are prefixed `abp_`.
- The SQL limit is now 500, applied before targeting filters.
- Text over 20,000 bytes is rejected before parsing.
- Saved store and customer-group IDs must exist.
- More than 50 routes now gives an error instead of silent truncation.
- Editing a deleted message shows "not found" instead of "success". The date picker uses the admin language.
- Storefront fixes:
  - Icons scale with the font size.
  - Rotation no longer makes the page jump.
  - Bottom padding follows the bar height.
  - A tap on touch screens no longer stops rotation for good.
  - A message whose end time passes disappears while the page is open.
  - Focus moves to the next element after the bar is closed.
  - The marquee pauses on keyboard focus.
  - The close button no longer overlaps long button text on mobile.
- The dismiss check is skipped when the close button is disabled.
- The admin form warns about low colour contrast. Colour pickers have labels.
- Translation fixes (lv, ru). README updated: http(s), language fallback, uninstall before removal, CSP note.

## 2.0.1 — 2026-11-05
Supersedes: 2.0.0, 1.0.0 (upload over the old version, then open the module page once in admin).

Fixes from an independent code review and extended testing:
- Emoji and other 4-byte characters were stored as "????". OpenCart's DB link uses 3-byte utf8, so they are now saved as numeric entities.
- Text was double-encoded (shown as `&amp;`) when "Allow HTML" was on and the text had no tags, or when HTML was switched off later.
- Closing the bar now applies on all pages. Before, it only applied to the page where it was closed.
- A stale localStorage entry no longer overrides a sessionStorage dismiss.
- Upgrading from v1 no longer creates an empty message when v1 had no text.
- The storefront guard now catches `\Throwable`, so PHP errors cannot break the shop page.
- Array values in POST fields no longer raise "Array to string conversion" notices.
- Users without "modify" permission can no longer trigger table creation or migration.
- Invalid UTF-8 no longer makes a message silently disappear.
- Links with non-ASCII paths (e.g. `/akcija-šodien`) are now accepted and percent-encoded.
- `{countdown}` is replaced only in text, never inside a tag attribute.
- Schema 3: `link_text` changed to TEXT.

## 2.0.0 — 2026-11-05
Supersedes: 1.0.0 (upgrade in place — upload over v1, then open the module once in admin; the v1 text is migrated to the first message automatically).

- Multiple messages with own text (per language), colours, icon, link / button, sort order.
- Display modes: rotate, marquee (scrolling line), stacked.
- Scheduling: start / end date-time, optional countdown timer (`{countdown}` placeholder).
- Visibility per message: stores, customer groups, pages (all / home / include / exclude routes, `product/*` wildcard).
- Close button (×) remembered for N days (localStorage) or the browser session.
- Position top / bottom (fixed), sticky top, font size, padding.
- Optional basic HTML (whitelist sanitizer: b, strong, i, em, u, s, br, span, a[href]).
- Own DB tables `{prefix}abp_message`, `{prefix}abp_message_description`; kept on uninstall unless "Delete messages on uninstall" is ticked.
- Catalog/admin languages: en-gb, lv-lv, ru-ru.
- Local assets only (no CDN).

## 1.0.0 — 2026-11-05
- Pirmā versija / Initial release (OpenCart 3.0.x).
- Iestatījumi: statuss, teksts katrai valodai, fona krāsa, teksta krāsa, priekšskatījums.
- Ievietošana caur event `catalog/view/common/header/after` (bez core failu labojumiem).
- Supersedes: nav (pirmā versija).
