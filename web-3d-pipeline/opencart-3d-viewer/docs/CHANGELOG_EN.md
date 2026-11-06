# Changelog — 3D Product Viewer for OpenCart

## 1.2.0 — 2026-11-06
- System check panel on the settings page (OpenCart/PHP version, events, database table, upload folder,
  bundled viewer files, server upload limit) with hints for each problem.
- Neutral help texts.
- Verified on OpenCart 3.0.3.8 (PHP 7.4), 3.0.4.1 and 3.0.5.x (PHP 8.3).

## 1.1.0 — 2026-11-05
- Works with any theme: automatic gallery detection by the product's own image file names
  (src, lazy-load attributes, srcset, links, zoom attributes, background images, any size or format).
- Grid/flex galleries: the viewer fills the whole gallery; narrow mobile columns switch to full width.
- Products without images: viewer placed in the main column.
- Viewer is added to the finished page (works with themes that do not print `{{ footer }}`).
- Manual placement falls back to automatic when the theme does not print `{{ product_3d_html }}`.
- Short descriptions next to the gallery are never hidden; logo `<h1>` is not mistaken for the product title.
- Language fallback by language code prefix (e.g. `lv` → `lv-lv`).

## 1.0.1 — 2026-11-05
- Security: install/uninstall routes require *modify* permission on extensions.
- Stricter upload validation (whole-file scan, image type must match extension, USDZ and external-URI checks,
  atomic file names).
- `.htaccess` for the upload folder: no directory listing, `nosniff`, Apache 2.2/2.4 compatible.
- The photo gallery is restored if the 3D viewer cannot load.
- Copying a product copies its 3D model; an invalid path on save never removes an existing model.

## 1.0.0 — 2026-11-05
- First release.
