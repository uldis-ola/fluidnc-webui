# Changelog (OpenCart 4 package)

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
