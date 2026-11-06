# Changelog (universal package)

## 2.2.0 — 2026-11-07
- **Phone text (optional, per language):** a short text that replaces the normal text on screens narrower than 768 px. Both texts are rendered and a CSS media query switches them. Same sanitizing and HTML-mode rules as the text, `{countdown}` works in it, and it is part of the per-message dismiss key (messages without a phone text keep their 2.1.x key, so closed messages stay closed after the upgrade).
- **Device targeting per message:** all devices / computers only (768 px and wider) / phones only. Messages for the other screen size are hidden by CSS and left out of rotation, marquee and stack; the script rebuilds the bar when the viewport crosses 768 px (rotation, resize). With nothing left, the bar hides and header offsets are restored exactly as when it is closed.
- **Statistics (counters only):** views and clicks per message, CTR in the admin list, "Reset statistics" per message. The storefront sends one view per message per browser session when the message is really shown, and one click when its button or a link in it is used (`navigator.sendBeacon`, fallback `fetch` keepalive). Nothing is sent when the visitor has Do Not Track or Global Privacy Control on. The endpoint (`…/track`) accepts only POST `id` + `type` (view/click), counts only enabled messages, at most one view and one click per message and 100 events per OpenCart session, always answers with an empty 204 and never outputs errors. No personal data is stored and the module sets no cookies of its own.
- **Duplicate** a message from the list: all languages and settings are copied, the copy is switched off and starts with zero statistics.
- DB schema 6: new columns `abp_message.device`, `views`, `clicks` and `abp_message_description.mobile_text`. Upgraded shops get them through an idempotent migration (`SHOW COLUMNS` check before each `ALTER TABLE … ADD COLUMN`), run when a user with *modify* permission opens the module page. Until then the storefront keeps working with the 2.1 columns.
- `mobile_text` is `TEXT NOT NULL` without a `DEFAULT`: MySQL refuses a literal default on `TEXT` columns. Existing rows get `''` when the column is added and every insert sets the value.
- New admin strings in English, Latvian and Russian.
- Hardening after review and real-shop tests: the statistics endpoint never touches the visitor's session (no race with cart or login), counts only same-origin requests and messages inside their date window; older browsers' Do-Not-Track values are honoured; no width gap between the phone and computer rules; a double click on Duplicate creates one copy (both versions). OpenCart 3: Duplicate and Reset are plain buttons that post their own form (the admin's "submit every form" helper for IE submitted the settings form instead).
- Tested on real OpenCart: 3.0.5.1 upgrade 2.1.2 → 2.2.0 with 2 existing messages (migration, idempotency, phone text, device, statistics, duplicate, reset, error log) 22/22; 4.1.0.4 fresh install 15/15.

## 2.1.2 — 2026-11-06
- **Readable automatic colours:** the text colour is now the one with the higher WCAG contrast (white or near-black), not a fixed brightness threshold. On the OpenCart 4 default blue this changes white text (3.2:1) to dark text (5.9:1).
- When neither text colour reaches 4.5:1 (mid-tone theme colours), the bar background is darkened or lightened just enough to reach it. Checked over 4,913 colours: lowest contrast 4.50:1.
- Custom colours are not changed.
- README: correct upgrade steps for OpenCart 4 (its installer refuses a package that is still installed; checked on 4.1.0.4).

## 2.1.1 — 2026-11-06
- Author and link set in `install.xml` (OpenCart 3) and `install.json` (OpenCart 4): author `3dp.lv`, link `https://3dp.lv`. They replace the placeholders.
- No code changes. Only the version number changed (file headers, `VERSION` constants, asset `?v=` key).

## 2.1.0 — 2026-11-06
Supersedes all earlier packages (2.0.x for OpenCart 3 and for OpenCart 4).

- **One package for every version:** the same `announcement_bar_pro.ocmod.zip` installs on OpenCart 3.0.x and 4.0.x/4.1.x. Each installer picks its own files.
- **Automatic theme adaptation**, with no settings needed:
  - Fixed or sticky headers move below the bar while it is visible.
  - Bottom fixed elements (cookie banners, mobile tab bars) lift a bottom bar.
  - The bar's z-index goes above the theme's fixed bars.
  - Sticky top and the bottom bar work with absolutely positioned page wrappers.
  - Missing Font Awesome drops the icons.
  - RTL languages are mirrored.
- **Footer fallback:** themes whose header template has no `<body>` still get the bar. A second event is registered automatically, also on upgrade.
- **Automatic colours** (new "Colours" setting, default for new installs): the theme's main colour with readable text. Upgraded shops keep their custom colours.
- **Works out of the box:** a fresh install with no messages is switched on.
- A theme that renders the header twice shows only one bar.
- The OpenCart 3 files cannot be called directly when they sit unused inside an OpenCart 4 shop.

Earlier history: see `announcement-bar-pro/CHANGELOG.md` and `announcement-bar-pro-oc4/CHANGELOG.md`.
