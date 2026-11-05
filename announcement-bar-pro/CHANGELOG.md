# Changelog

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
