# Changelog (OpenCart 4 package)

## 2.4.0 — 2026-11-07
Several bars + inline boxes with priority, country / cart / campaign / new-or-returning targeting, per-country and per-currency free-shipping amounts, OpenCart coupons, tiered goals, triggers, recurring countdown, e-mail sign-up with double opt-in, background image, system fonts, custom CSS, auto-hide, pause button, 13 templates, GA4 events; DB schema 8. Pre-release review fixes: e-mail header injection through quoted addresses closed, CloudFlare headers trusted only from CloudFlare IP ranges, no account newsletter changes or re-subscriptions without double opt-in, 3 confirmation mails per address per day, sign-up limits in Settings, coupon hidden on its end date (as OpenCart), Wait for cookie consent setting, cheaper cart endpoint. Full list: `CHANGELOG.md` of the universal package (`announcement-bar-pro-universal`).

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
Fixes from the second round of independent reviews:
- **Security/stability:** the HTML sanitizer could loop forever on `xml:lang` or `xmlns` attributes. Elements are now rebuilt, and nested links are unwrapped.
- English labels are loaded first, so admin and storefront languages without a module translation no longer show raw keys.
- On OpenCart 4.0.0.x the sanitizer is loaded with a `require_once` fallback, and the language flag is built without `language.image`.
- Opening the module page after Uninstall redirects to the extension list instead of recreating tables and settings.
- Emoji are stored as-is, because OpenCart 4 uses utf8mb4.
- Countdown and live expiry use the server clock. The dismiss key comes from the content. A touch pause resumes automatically.
- A `<` followed by a space is kept in HTML mode. Non-numeric store and group IDs are rejected. The http(s) link error text is updated.
- Language texts: a clearer missing-language hint, a consistent Latvian register, and a duplicate key removed. The "required" marker is removed from the language rows (one language is enough).

## 2.0.3 — 2026-11-05
- First OpenCart 4.0.x / 4.1.x release, with the same features as the OpenCart 3 package 2.0.3.
- Admin uses Bootstrap 5 with AJAX saving and OpenCart 4 field errors. Icons are Font Awesome 6. Dates use `datetime-local`.
- The event action uses `.` on 4.0.2.0+ and `|` on 4.0.0.x–4.0.1.x.
- The default language is read from `config_language_catalog` on 4.1, or `config_language` on 4.0.
- The OC4 default theme's empty `#alert` toast container no longer blocks the close button.
