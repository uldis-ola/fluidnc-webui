# Changelog (universal package)

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
