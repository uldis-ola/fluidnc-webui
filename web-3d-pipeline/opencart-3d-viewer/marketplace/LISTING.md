# OpenCart Marketplace listing — 3D Product Viewer

Copy the fields below into the extension form on opencart.com. Fields in [brackets] are yours to fill.

---

## Extension name
3D Product Viewer with AR (glb / gltf) — OpenCart 3.0.x

## Category
Modules (Product page / Images)

## Compatibility
OpenCart 3.0.3.8, 3.0.4.0, 3.0.4.1, 3.0.5.0, 3.0.5.1
(tested on 3.0.3.8 with PHP 7.4, 3.0.4.1 and 3.0.5.x with PHP 8.3)

> Only list the versions above. 3.0.0.0–3.0.3.7 were not tested; add them only after testing.
> 3.0.4.0 and 3.0.5.0/3.0.5.1 sit between tested versions and use the same event API.
> To be strict, list only 3.0.3.8, 3.0.4.1 and 3.0.5.x.

## Price
[your price] — one store / domain, [12] months updates, [6] months support.

## Short description (up to ~160 characters)
Show rotatable 3D product models with augmented reality on your product pages. Upload a .glb in the
product form — works with any theme, no core or theme file changes.

## Description

**Let customers turn your product around, zoom in, and place it in their room with AR — straight from
the product page.**

3D Product Viewer adds a "3D model" tab to the product edit form. Upload a .glb file, save the product, and
an interactive 3D viewer appears in the product gallery. On supported phones a "View in your space"
button opens augmented reality (Android Scene Viewer, iOS Quick Look).

**Works with your theme as it is**
- Finds the product gallery automatically in any theme: default, flex/grid layouts, sliders, lazy-loaded
  images, background-image galleries.
- No core files, no theme files, no OCMOD modifications — uses OpenCart events only.
- If the 3D viewer cannot load for any reason, your normal product photos are shown.

**Simple for the shop owner**
- Upload .glb / .gltf, optional .usdz for iPhone AR and an optional poster image.
- Live preview in the admin.
- Per product: placement, camera angle, auto-rotate, or hide the viewer.
- Pick files already uploaded by FTP.
- Placement: replace the gallery, above it, below it, or manual (`{{ product_3d_html }}`).

**Fast and private**
- Google's open-source model-viewer is bundled with the module. No requests to external servers by default
  (GDPR-friendly, works with strict Content-Security-Policy).
- Supports Draco- and Meshopt-compressed models (decoders bundled).
- Viewer files load only on product pages that have a 3D model.

**Safe**
- Uploads are checked by content, not only by extension.
- Permission and token checks on all admin actions.
- Upload folder protected against script execution.

**Built-in system check**
The settings page checks your OpenCart and PHP versions, events, database table, upload folder, and the
server upload limit — and tells you how to fix anything that is wrong.

**Languages:** English, Latvian, Russian (admin and storefront). Easy to translate.

**Clean uninstall:** removes events and settings. Your product–model links are kept for a later
re-install, unless you choose otherwise. Uploaded files are never deleted.

### Requirements
- OpenCart 3.0.x, PHP 7.3+ (tested 7.4 and 8.3), MySQL/MariaDB with InnoDB.
- HTTPS for AR on phones.
- Your 3D models in .glb or self-contained .gltf format (glTF 2.0). Recommended size: under 5 MB.

### Installation
1. Extensions → Installer → upload the .ocmod.zip.
2. Extensions → Extensions → Modules → 3D Product Viewer → Install.
3. Edit → check settings → Save.
4. Catalog → Products → Edit → "3D model" tab → Upload → Save.

A full user guide (USER_GUIDE.md) is included in the download.

### Not included
- 3D models themselves. You create or buy them (e.g. export from Blender as glTF Binary).
- Quick-view pop-ups: the viewer is shown on the full product page.
- OpenCart 4.x (separate version planned: [yes/no]).

## Tags
3d, 3d model, glb, gltf, ar, augmented reality, model-viewer, product viewer, 360, product gallery,
webxr, usdz, opencart 3

## Demo
- Storefront demo: [link — e.g. the published demo page or your own demo store]
- Admin demo: [demo store admin with a read-only demo user, if you provide one]

## Screenshots (in marketplace/screenshots/)
1. `01-storefront-desktop.png` — 3D viewer in place of the gallery (default theme)
2. `02-settings.png` — settings with system check
3. `03-product-tab.png` — "3D model" tab with upload and preview
4. `04-storefront-mobile.png` — phone view with AR button
5. `05-any-theme.png` — the same module in different themes (desktop and phone)

## Support
[support e-mail / contact form URL] — answers within [2] working days. Include your OpenCart version, theme
name and a screenshot of the System check panel.
