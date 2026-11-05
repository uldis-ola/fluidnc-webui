# Changelog

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
