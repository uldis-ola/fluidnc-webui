# Announcement Bar Pro — OpenCart 3.0.x

Paziņojumu josla veikala augšā vai apakšā: akcijas, piegādes nosacījumi, svarīgas ziņas.
*An announcement bar for the top or bottom of every store page: promotions, shipping terms, notices.*

- **Versija / Version:** 2.0.0
- **Saderība / Compatibility:** OpenCart 3.0.x (tested on 3.0.5.1), PHP 7.3+ (pārbaudīts / tested: PHP 8.3)
- **Valodas / Languages:** English, Latviešu, Русский
- **Licence / License:** GPL-3.0 (see `LICENSE.txt`)

---

## LV

### Iespējas
- Neierobežots paziņojumu skaits, katram savs teksts katrā valodā, krāsas, ikona (Font Awesome 4), saite vai poga.
- Attēlošana: **mainīgi** (pa vienam), **skrejošā rinda**, **visi reizē**.
- **Laika plānošana:** sākuma/beigu datums un laiks; **atpakaļskaitīšana** līdz beigām (`{countdown}` tekstā).
- **Redzamība:** pa veikaliem (multistore), klientu grupām, lapām (visas / sākumlapa / tikai norādītās / visas izņemot; `product/*`).
- **Aizvēršanas poga (×)**, ko pārlūks atceras N dienas vai līdz cilnes aizvēršanai.
- Novietojums augšā (var būt piestiprināts ritinot) vai apakšā; fonta izmērs, atkāpe.
- Pēc izvēles vienkāršs HTML (b, strong, i, em, u, s, br, span, a); viss pārējais tiek izņemts.
- Bez core failu labojumiem: josla tiek ievietota ar OpenCart notikumu (event).
- Bez ārējiem CDN, viss lokāli.

### Instalēšana
1. **Pirms instalēšanas izveido failu un datubāzes rezerves kopiju.**
2. Admin → *Extensions → Installer* → augšupielādē `announcement_bar_pro_v2.0.0.ocmod.zip`.
3. *Extensions → Modifications* → **Refresh**.
4. *Extensions → Extensions → Modules* → **Announcement Bar Pro** → **Install** (zaļais +) → **Edit**.
5. Cilnē *Iestatījumi* ieslēdz moduli un saglabā; cilnē *Paziņojumi* pievieno paziņojumus.

### Jaunināšana no 1.0.0
Augšupielādē v2.0.0 virs v1 (2. un 3. solis), tad vienreiz atver moduļa lapu adminā.
v1 teksts un krāsas automātiski kļūst par pirmo paziņojumu. Līdz tam veikalā turpina rādīties v1 teksts.

### Atinstalēšana
*Modules → Announcement Bar Pro → Uninstall.* Tiek noņemts notikums un iestatījumi.
Paziņojumi **saglabājas**, ja vien *Iestatījumi → Papildus → Dzēst paziņojumus atinstalējot* nav atzīmēts.

### Lapu maršruti (piemēri)
| Lapa | Maršruts |
|---|---|
| Sākumlapa | `common/home` |
| Produkts | `product/product` |
| Kategorija | `product/category` |
| Akcijas | `product/special` |
| Grozs / noformēšana | `checkout/*` |
| Konts | `account/*` |

### Piezīmes
- Josla tiek ievietota tūlīt pēc `<body>` taga galvenes veidnē (`common/header`). Ja tēmai galvenē nav `<body>`, josla neparādīsies.
- Font Awesome 4 ir noklusētajā OpenCart 3 tēmā. Ja tava tēma to neielādē, ikonas nebūs redzamas (teksts strādās).
- Datumi tiek salīdzināti veikala laika joslā.

---

## EN

### Features
- Unlimited messages, each with per-language text, colours, icon (Font Awesome 4), link or button.
- Display modes: **rotate**, **marquee**, **stacked**.
- **Scheduling** (start/end date-time) and **countdown** to end (`{countdown}` placeholder).
- **Visibility** by store, customer group and page (all / home / include / exclude routes, `product/*` wildcard).
- **Close button (×)** remembered for N days or the browser session.
- Top (optionally sticky) or bottom (fixed) position, font size, padding.
- Optional basic HTML via a whitelist sanitizer.
- No core file edits (OpenCart event). No external CDN.

### Install
1. **Back up files and database first.**
2. Admin → *Extensions → Installer* → upload `announcement_bar_pro_v2.0.0.ocmod.zip`.
3. *Extensions → Modifications* → **Refresh**.
4. *Extensions → Extensions → Modules* → **Announcement Bar Pro** → **Install** → **Edit**.
5. Enable it in *Settings*, add messages in *Messages*.

### Upgrade from 1.0.0
Upload v2.0.0 over v1, then open the module page in admin once — the v1 text is migrated automatically.

### Uninstall
Removes the event and settings. Messages are kept unless *Settings → Advanced → Delete messages on uninstall* is ticked.

### Security
- `modify` permission is checked on every save and delete. Every admin URL needs a valid `user_token`.
- All input is validated against a whitelist: colours `#RRGGBB`, links `https://`, `/path` or `index.php?route=` only, icon `fa-*`, routes `[a-z0-9_/]`, dates.
- All SQL values are cast to `int` or escaped with `$this->db->escape()`.
- Output is escaped in Twig. HTML mode is sanitized on save and again on render.

---

## Build

```bash
./build.sh   # → dist/announcement_bar_pro_v<version>.ocmod.zip
```

## Files
```
upload/admin/controller/extension/module/announcement_bar_pro.php
upload/admin/model/extension/module/announcement_bar_pro.php
upload/admin/view/template/extension/module/announcement_bar_pro.twig
upload/admin/view/template/extension/module/announcement_bar_pro_form.twig
upload/admin/language/{en-gb,lv-lv,ru-ru}/extension/module/announcement_bar_pro.php
upload/catalog/controller/extension/module/announcement_bar_pro.php
upload/catalog/model/extension/module/announcement_bar_pro.php
upload/catalog/language/{en-gb,lv-lv,ru-ru}/extension/module/announcement_bar_pro.php
upload/catalog/view/theme/default/template/extension/module/announcement_bar_pro.twig
upload/catalog/view/javascript/announcement_bar_pro/announcement_bar_pro.{js,css}
upload/system/library/announcementbarpro/sanitizer.php
install.xml
```
