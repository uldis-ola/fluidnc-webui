# 3D Product Viewer for OpenCart 3.0.x — User Guide

Version 1.2.1

Show an interactive 3D model (.glb / .gltf) on your product pages. Customers can rotate and zoom
the product, and on supported phones place it in their room with augmented reality (AR).
The viewer is Google's open-source `<model-viewer>`, bundled with the module.

---

## 1. Requirements

| | |
|---|---|
| OpenCart | 3.0.x (tested on 3.0.3.8, 3.0.4.1 and 3.0.5.x) |
| PHP | 7.3 or newer (tested on 7.4 and 8.3) |
| Database | MySQL 5.6+ / MariaDB 10.x with InnoDB |
| PHP extensions | `json` (standard); `zip` is only needed by the OpenCart Extension Installer itself |
| Theme | Any OpenCart 3 theme (see section 6) |
| AR | HTTPS on your store; Android with Google Play Services for AR, or iOS Safari |

The module does **not** modify OpenCart core files or your theme files. It uses the OpenCart event
system only, so there is nothing to refresh under *Extensions → Modifications*.

---

## 2. Installation

> Make a backup of your files and database before installing any extension.

1. In the admin go to **Extensions → Installer** and upload `product_3d_viewer_v1.2.1.ocmod.zip`.
   Wait for the green *Success* message.
2. Go to **Extensions → Extensions**, choose **Modules** in the filter, find **3D Product Viewer**
   and click the green **+** (Install).
   This creates the table `oc_product_3d`, registers the events, creates the folder
   `image/catalog/3d/` and saves default settings.
3. Click the pencil (**Edit**), review the settings (section 3) and click **Save**.
4. Check the **System check** panel on the settings page: every line should show **OK**.

**Manual installation (FTP):** copy the contents of the `upload/` folder to your store root, then
continue with step 2.

**Updating from an older version:** upload the new package with the Installer, then on
*Extensions → Modules* click **Uninstall** and **Install** for 3D Product Viewer. Your product–model
links are kept. Open the settings and click **Save** once.

---

## 3. Settings

*Extensions → Modules → 3D Product Viewer → Edit* (also under *Catalog → 3D Product Viewer*).

| Setting | Default | What it does |
|---|---|---|
| Status | Enabled | Turns the viewer on/off for the whole store. |
| Placement on product page | Replace the image gallery | *Replace* hides the photo gallery and shows the 3D viewer in its place. *Above* / *Below* keep the photos. *Manual* lets your theme print the viewer where you want (section 7). |
| Gallery CSS selector | *(empty)* | Leave empty: the module finds the gallery automatically in any theme. Enter a selector only to force a specific place. |
| model-viewer source | Local files | Local files make no requests to other servers (privacy/GDPR, strict CSP). *Google CDN* is optional. |
| Viewer height | 480 px | Height on desktop; on phones the viewer is limited to 75 % of the screen height. |
| Background colour | #f5f6f8 | Any hex colour or `transparent`. |
| Shadow intensity / Exposure | 1 / 1 | Lighting of the model. |
| Default camera angle | automatic | e.g. `30deg 75deg auto` (horizontal, vertical, distance). |
| AR button | On | Shows *View in your space* on devices that support AR. |
| Auto-rotate | On | Slowly turns the model until the customer touches it. |
| Rotate / zoom | On | Mouse and touch controls. |
| Use main product image as poster | On | The product photo is shown while the 3D model loads. |
| Upload folder | catalog/3d/ | Inside `image/`. |
| Max. file size | 30 MB | Also limited by PHP `upload_max_filesize` / `post_max_size` (shown on the settings page). |
| Delete 3D links on uninstall | Off | When on, uninstalling also drops `oc_product_3d`. Uploaded files are never deleted. |

### System check

The settings page shows the OpenCart and PHP version, the number of registered events, the database
table, whether the upload folder is writable, whether the bundled viewer files are present, and the
server upload limit. If a line says **Check**, the hint below the table tells you what to do.

---

## 4. Adding a 3D model to a product

1. **Catalog → Products → Edit** a product and open the **3D model** tab.
2. Click **Upload** next to *3D model* and choose a `.glb` file (or a self-contained `.gltf`).
   A preview appears on the right.
3. Optional:
   - **iOS AR file (.usdz):** gives the best AR result on iPhone/iPad. Without it, iOS creates one automatically from the .glb.
   - **Poster image:** a picture shown while the model loads.
   - **Placement, camera angle and auto-rotate** for this product only. *Do not show for this product* hides the viewer without removing the file.
4. Click **Save** (the product's own save button).

Files you uploaded by FTP into `image/catalog/3d/` can be picked with **Choose existing**.

Copying a product (*Copy* button in the product list) copies its 3D model link too.
Deleting a product removes its 3D link; the model file stays on the server.

### Preparing models

- **Format:** `.glb` (binary glTF 2.0) is recommended. A `.gltf` must be self-contained: no separate `.bin` or image files.
- **Size:** keep files under ~5 MB. Use Draco or Meshopt compression and textures of 2048 px or less. Both compressions are supported by the bundled decoders.
- **Scale:** glTF uses metres. A model exported in centimetres appears 100× too big in AR.
- **Orientation:** Y is up. Put the model on the ground (Y = 0) so it stands on the floor in AR.
- **Free tools:** Blender (export glTF 2.0), gltf.report, gltf-transform, gltfpack.

### Upload checks

The module checks the content of every upload, not only the extension:
- `.glb`: glTF 2.0 header, matching length, no references to external URLs;
- `.gltf`: valid glTF 2.0 JSON with all data embedded;
- `.usdz`: the first file in the archive must be a USD file;
- posters: the image type must match the file extension.

File names are cleaned, and existing files are never overwritten.

---

## 5. What customers see

- **Desktop:** the viewer appears in the gallery area. Customers drag to rotate and scroll to zoom.
- **Phones:** the same viewer, full width when the theme's gallery column is narrow. A *View in your space (AR)* button appears on supported devices.
- **If the 3D viewer cannot load** (old browser, blocked script, damaged file): the normal product photos are shown again automatically.
- **Products without a 3D model** are not changed at all.

Texts on the storefront are in English, Latvian, Russian, German, Lithuanian, Estonian and Polish. Other languages fall back to English.
You can add your own translation by copying
`catalog/language/en-gb/extension/module/product_3d.php` into your language folder.

---

## 6. Themes

The module does not edit theme files. The viewer is added to the finished product page, and a small
script places it next to the product gallery:

1. **CSS selector:** if you entered one in the settings, it is used.
2. **Automatic detection:** the script finds this product's images on the page by their file names. It recognises `src`, lazy-load attributes (`data-src`, `srcset`), links, zoom attributes and background images, in any size and format (also WebP). From the largest image it moves up to the block that holds the gallery but not the product title, price, cart button or description.
3. **Common gallery selectors:** `.thumbnails`, `.product-gallery` and others.
4. **Products without images:** the main column next to the product information.

Tested with:
- the default theme;
- flex and grid layouts;
- lazy-loaded sliders;
- background-image galleries;
- themes without `{{ footer }}`;
- one-column themes;
- themes that use `<h1>` for the logo;

on desktop and phone.

If the viewer appears in the wrong place in your theme:
- **Enter a CSS selector** for the gallery in the settings. Right-click the main product image → *Inspect*, and use the class of the gallery container, e.g. `.product-gallery`.
- **Or use Manual placement** (section 7).

---

## 7. Manual placement (theme developers)

Set *Placement* to **Manual**, then print the viewer in your theme's
`product/product.twig` where you want it:

```twig
{{ product_3d_html }}
```

Also available in `product/product.twig`:
- `{{ model_3d }}` — the model URL;
- `{{ product_3d }}` — array with `src`, `ios_src`, `poster`, `placement`, `height` and more.

If the theme does not print `{{ product_3d_html }}`, the viewer is added automatically.

To change the viewer markup, copy
`catalog/view/theme/default/template/extension/module/product_3d.twig` into your theme folder.

JavaScript events (for analytics):
- `document.addEventListener('product3d:placed', …)`;
- `document.addEventListener('product3d:failed', …)`.

---

## 8. Server notes

- **Apache:** the module creates `image/catalog/3d/.htaccess`. It sets the correct MIME types for `.glb`, `.gltf` and `.usdz`, disables directory listing, sends `nosniff` and blocks script execution. It works with Apache 2.2 and 2.4.
- **Nginx:** add to your server block:
  ```nginx
  location ~* \.(glb|gltf|usdz)$ {
      types { model/gltf-binary glb; model/gltf+json gltf; model/vnd.usdz+zip usdz; }
      add_header X-Content-Type-Options nosniff;
  }
  ```
- **Domain:** your store URL in `config.php` must match the address customers use (www / non-www, http / https). Otherwise browsers block the viewer (and many other parts of OpenCart). The module then keeps showing the normal photos.
- **CDN mode:** loads scripts from `ajax.googleapis.com` and `cdn.jsdelivr.net`. If your site uses a Content-Security-Policy, keep the default *Local files*.

---

## 9. Uninstalling

*Extensions → Modules → 3D Product Viewer → Uninstall* removes the events and settings.
- The table `oc_product_3d` is kept, so a later re-install restores all product links. It is removed only when *Delete 3D links on uninstall* is enabled.
- Uploaded model files in `image/` are never deleted by the module.

---

## 10. Troubleshooting

| Problem | Solution |
|---|---|
| No "3D model" tab on the product form | Check that the module status is *Enabled* and the System check shows 8/8 events. If not, Uninstall + Install. |
| Upload says the file is too large | Raise PHP `upload_max_filesize` and `post_max_size`, or compress the model. |
| "Invalid .glb file" | The file is not a glTF 2.0 binary. Re-export from Blender as *glTF Binary (.glb)*. |
| "References external files" | Your .gltf has separate .bin or texture files. Export as .glb instead. |
| Viewer shows on the wrong spot | Set the gallery CSS selector, or use Manual placement. |
| Photos are shown, no 3D | Open the browser console (F12). A domain mismatch or a blocked script is the usual cause (section 8). |
| AR button missing | AR needs HTTPS and a supported phone. It is not shown on desktop. |
| Model is huge or tiny in AR | The model was exported in centimetres or millimetres. Re-export in metres. |

---

## 11. Third-party components

The package includes, unmodified, under their own licences (see
`catalog/view/javascript/product_3d/THIRD_PARTY_LICENSES.txt`):

- Google `<model-viewer>` 4.0.0 — Apache License 2.0 (includes three.js, MIT)
- meshoptimizer decoder 0.21.0 — MIT
- Draco decoder and Basis Universal transcoder (from three.js 0.169.0) — Apache License 2.0
