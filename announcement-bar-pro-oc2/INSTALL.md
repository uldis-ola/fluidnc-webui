# Announcement Bar Pro 2.4.2 — installation (OpenCart 2.0.x–2.3.x)

**Back up your files and database first.** Requirements: OpenCart 2.0.0.0–2.3.0.2, PHP 5.6 or newer
(OpenSSL extension on PHP 5.6).

Pick the package for your OpenCart version (*System › Maintenance* or the admin footer shows it):

- OpenCart **2.0.x, 2.1.x, 2.2.x** → `announcement_bar_pro_oc2.0-2.2_v2.4.2.ocmod.zip`
- OpenCart **2.3.x** → `announcement_bar_pro_oc2.3_v2.4.2.ocmod.zip`

## A. With the Extension Installer (FTP set up in OpenCart)

OpenCart 2.x copies the files of a package over FTP: *System › Settings › your store › FTP* must be filled in and enabled.

1. *Extensions › Extension Installer* → upload the `.ocmod.zip`.
2. *Extensions › Modifications* → **Refresh** (blue button, top right).
3. Install the module:
   - 2.0–2.2: *Extensions › Modules* → **Announcement Bar Pro** → **Install** (green +) → **Edit**.
   - 2.3: *Extensions › Extensions* → choose **Modules** → **Announcement Bar Pro** → **Install** → **Edit**.
4. A fresh install is switched on. Add the first message in the **Messages** tab (or pick a template).

## B. Without FTP (most hosts)

1. Unzip the package on your computer.
2. Upload the **contents** of the `upload` folder into the OpenCart root (where `index.php` and `admin/` are) with
   your hosting file manager or an FTP/SFTP program. Folders are merged; no OpenCart file is replaced.
3. *Extensions › Extension Installer* → upload `announcement_bar_pro.ocmod.xml` from the package (an `.ocmod.xml`
   needs no FTP). On **2.0 / 2.1 this step is required** — it adds the storefront hook; on 2.2 / 2.3 it only lists
   the module under *Modifications*.
4. *Extensions › Modifications* → **Refresh**.
5. Install the module (step A3).

## Upgrade from an older 2.x package

Upload the new files over the old ones (A or B), refresh the modifications, then open the module page once as an
administrator: the database is updated automatically (messages, settings and statistics are kept).

## Uninstall

*Modules › Announcement Bar Pro › Uninstall* removes the events and settings. Messages and sign-ups are kept unless
*Settings › Advanced › Delete messages on uninstall* is ticked. To remove the files, delete the paths listed in
`README.md` and, on 2.0/2.1, the modification in *Extensions › Modifications*, then **Refresh**.

## If the bar does not show

- **OpenCart 2.0.x:** *Modifications › Refresh* switches the store to maintenance mode and leaves it on (an OpenCart
  2.0 bug, not caused by this module). Administrators still see the shop; visitors see the maintenance page. Switch it
  off in *System › Settings › your store › Server › Maintenance Mode*.

- The module is **switched on** (Settings tab) and at least one message is enabled and in its date range.
- 2.0 / 2.1: *Extensions › Modifications* lists "Announcement Bar Pro" and you pressed **Refresh** after installing.
- 2.2 / 2.3: the module is installed (the events are added on *Install*).
- A message limited to stores, groups, pages, countries or a cart condition only shows where those match.

Support: **extension@3dp.lv** (3DP SIA). Please include the OpenCart and PHP versions and the theme name.
