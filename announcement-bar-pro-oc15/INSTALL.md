# Announcement Bar Pro 2.4.2 for OpenCart 1.5.x — Installation

Requirements: OpenCart 1.5.4 – 1.5.6.4, **PHP 5.6 or newer** (7.x recommended), **vQmod 2.4+**, MySQL 5.1+ / MariaDB.
Support: 3DP SIA, extension@3dp.lv

## 1. Back up
Make a copy of the shop files and the database before installing any extension.

## 2. vQmod (skip if your shop already has it)
OpenCart 1.5 has no event system; the bar is added with vQmod (it changes no core file on disk).

1. Download *vQmod for OpenCart* from https://github.com/vqmod/vqmod/releases (e.g. `vqmod-2.6.x-opencart.zip`).
2. Upload its `vqmod` folder to the shop's root folder (next to `index.php`, `admin/`, `catalog/`).
3. Make `vqmod/` and `vqmod/vqcache/` writable by the web server (e.g. 755 or 775; `index.php` and `admin/index.php` must be
   writable while you run the installer).
4. Open `https://your-shop/vqmod/install/` in the browser. It must say **"VQMOD HAS BEEN INSTALLED ON YOUR SYSTEM!"**
   (or "already installed").

## 3. Upload the module (FTP)
Upload the **contents of the `upload/` folder** of this package to the shop's root folder, keeping the folders
(`admin/…`, `catalog/…`, `system/…`, `vqmod/xml/announcement_bar_pro.xml`). No existing file is overwritten.

Own theme? The storefront template is `catalog/view/theme/default/template/module/announcement_bar_pro.tpl`; OpenCart 1.5
uses it for every theme unless the theme has its own copy.

## 4. Install and set up
1. Admin → *Extensions → Modules* → **Announcement Bar Pro** → **Install**, then **Edit**.
2. A fresh install is switched on. Add the first message (*Messages → +*), or start from a template.
3. *Settings* tab: position, display mode, colours, e-mail sign-up options, GA4, cookie-consent wait, Turnstile keys,
   **Load the icon font** (switch off if your theme already loads Font Awesome 4).

The database tables are created on install (and checked whenever the module page is opened; repeated runs change
nothing). The schema number is written last, so an interrupted install simply completes on the next visit.

## Upgrading
Upload the new `upload/` files over the old ones (step 3), then open the module page once in the admin.
If you change the vQmod file, vQmod rebuilds its cache by itself (or delete the files in `vqmod/vqcache/`).

## Uninstalling
*Extensions → Modules → Announcement Bar Pro → Uninstall.* Settings are removed; messages and sign-ups are kept unless
*Settings → Delete data on uninstall* was ticked. To remove the module completely, then delete the files of `upload/` and
`vqmod/xml/announcement_bar_pro.xml` (the hooks do nothing while the module is uninstalled).

## Checks if the bar does not show
- `vqmod/vqcache/` contains `vq2-catalog_controller_common_header.php` after a storefront visit (vQmod works) and
  `vqmod/logs/` has no error about `announcement_bar_pro.xml`.
- The module is switched on, the message is enabled, inside its dates and matches the page / store / customer group.
- Language folders: OpenCart 1.5 uses the language *directory* (`english`, `latvian`, `russian`); copy the language file of
  the module into your pack's folder if it is named differently (other languages fall back to English).
- Mail for double opt-in: *System → Settings → Mail* must be set (SMTP or Mail) and the store e-mail filled in.
- Cloudflare Turnstile needs outgoing HTTPS from PHP (curl or `allow_url_fopen`) with a CA certificate bundle.
- OpenCart 1.5 on PHP 7.2+ needs the usual `system/library/encryption.php` replacement (mcrypt is gone); this is a core
  OpenCart requirement, not one of this module.
