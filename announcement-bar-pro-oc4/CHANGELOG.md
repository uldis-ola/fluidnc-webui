# Changelog (OpenCart 4 package)

## 2.0.3 — 2026-11-05
- First OpenCart 4.0.x / 4.1.x release, with the same features as the OpenCart 3 package 2.0.3.
- Admin uses Bootstrap 5 with AJAX saving and OpenCart 4 field errors. Icons are Font Awesome 6. Dates use `datetime-local`.
- The event action uses `.` on 4.0.2.0+ and `|` on 4.0.0.x–4.0.1.x.
- The default language is read from `config_language_catalog` on 4.1, or `config_language` on 4.0.
- The OC4 default theme's empty `#alert` toast container no longer blocks the close button.
