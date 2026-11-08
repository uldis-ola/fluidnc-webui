# Announcement Bar Pro — OpenCart 3.0.x

Paziņojumu josla veikala augšā vai apakšā: akcijas, piegādes nosacījumi, svarīgas ziņas.
*An announcement bar for the top or bottom of every store page: promotions, shipping terms, notices.*

- **Versija / Version:** 2.4.1
- **Saderība / Compatibility:** OpenCart 3.0.0.0–3.0.5.1 (tested on 3.0.3.8 with PHP 7.4 / 8.0 and 3.0.5.1 with PHP 8.1 / 8.2 / 8.3 — OpenCart 3.0.5.1 itself needs PHP 8.1+), PHP 7.3+ (7.3 not tested); MariaDB 11.4 and MySQL 8.4. Matrix: `announcement-bar-pro-universal/COMPATIBILITY.md`. OpenCart 4.0.x / 4.1.x: separate package in `announcement-bar-pro-oc4`.
- **Valodas / Languages:** English, Latviešu, Русский
- **Licence / License:** GPL-3.0 (see `LICENSE.txt`)

---

> **2.4.1:** optional Cloudflare Turnstile anti-bot check of the e-mail sign-up form (site key + secret key in Settings → E-mail sign-ups; the secret is never shown; when on, Cloudflare processes the visitor's IP address and browser data on pages with a sign-up form, and with "Wait for cookie consent" its script loads only after consent), an unsubscribed address can no longer be re-subscribed by someone else (only its owner, through a new confirmation e-mail), and a cart-refresh fix in the storefront script, which is now byte-identical to the WordPress edition.
>
> **2.4.0:** several bars and inline boxes (cart page, under the price, mini-cart) with priority, country / cart / campaign / visitor targeting, tiered goals, OpenCart coupons, triggers, recurring countdown, e-mail sign-up (double opt-in), templates, GA4 events. Features, privacy notes and upgrade steps: README of the universal package.

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
2. Admin → *Extensions → Installer* → augšupielādē `announcement_bar_pro_v2.0.5.ocmod.zip`.
3. *Extensions → Modifications* → **Refresh** (modulis core failus nemaina; solis nav obligāts, bet nekaitē).
4. *Extensions → Extensions → Modules* → **Announcement Bar Pro** → **Install** (zaļais +) → **Edit**.
5. Cilnē *Iestatījumi* ieslēdz moduli un saglabā; cilnē *Paziņojumi* pievieno paziņojumus.

### Jaunināšana no 1.0.0
Augšupielādē v2.0.5 virs v1 vai v2.0.x (2. un 3. solis), tad vienreiz atver moduļa lapu adminā.
v1 teksts un krāsas automātiski kļūst par pirmo paziņojumu. Līdz tam veikalā turpina rādīties v1 teksts.

### Atinstalēšana
*Modules → Announcement Bar Pro → Uninstall.* Tiek noņemts notikums un iestatījumi.
Paziņojumi **saglabājas**, ja vien *Iestatījumi → Papildus → Dzēst paziņojumus atinstalējot* nav atzīmēts.
Pirms pakas dzēšanas no *Extensions → Installer* vispirms nospied **Uninstall** modulim — citādi notikums un tabulas paliek datubāzē.
OpenCart 3 atinstalējot noņem moduļa piekļuves tiesības **visām** lietotāju grupām; pēc atkārtotas instalēšanas tās automātiski saņem tikai instalētāja grupa (citām grupām tiesības jāpiešķir no jauna sadaļā *System → Users → User Groups*).

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
- Datumi tiek salīdzināti veikala laika joslā. Beigu datums bez laika nozīmē šīs dienas beigas (23:59:59).
- Ja paziņojumam kādā valodā nav teksta, šajā valodā tiek rādīts veikala noklusētās valodas teksts. Sarakstā adminā redzams, kurām valodām teksta trūkst.
- Viesi redz paziņojumus, kas paredzēti veikala noklusētajai klientu grupai.
- `{countdown}` tiek aizstāts tikai pirmo reizi tekstā; nākamie `{countdown}` tiek izņemti. Paziņojums ar beigu laiku pazūd arī atvērtā lapā, kad laiks beidzies.
- Aizvēršana tiek atcerēta katram paziņojumam atsevišķi: ja kāds paziņojums beidzas, tiek izslēgts vai saglabāts bez satura izmaiņām, josla neatgriežas; atgriežas tikai ar jaunu paziņojumu vai mainītu tekstu/pogu/saiti/ikonu.
- Atpakaļskaitīšana izmanto servera laiku, nevis apmeklētāja datora pulksteni.
- Modulis neizmanto iekļautus (inline) skriptus. Krāsas tiek iestatītas ar `style` atribūtiem, tāpēc CSP politikai jāatļauj `style-src 'unsafe-inline'`.
- Beigu datumam ir izvēles rūtiņa „Līdz šīs dienas beigām (23:59:59)”.
- Pēc jaunas versijas augšupielādes pagaidi dažas sekundes, pirms atver moduļa lapu. Ja serverī ir OPcache ar `validate_timestamps=0`, notīri OPcache vai pārstartē PHP; citādi PHP var izpildīt veco kodu.
- Ja moduļa veidne tiek mainīta *Design → Theme Editor* un tajā ir Twig kļūda, OpenCart pats aptur lapas izvadi. To modulis nevar notvert, tāpēc veidni labo uzmanīgi.
- Pārlūki: visi mūsdienīgie (Chrome, Firefox, Safari, Edge). Internet Explorer 11 netiek atbalstīts.

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
2. Admin → *Extensions → Installer* → upload `announcement_bar_pro_v2.0.5.ocmod.zip`.
3. *Extensions → Modifications* → **Refresh** (optional — no core files are modified).
4. *Extensions → Extensions → Modules* → **Announcement Bar Pro** → **Install** → **Edit**.
5. Enable it in *Settings*, add messages in *Messages*.

### Upgrade from 1.0.0
Upload v2.0.5 over v1 or v2.0.x, then open the module page in admin once — the v1 text is migrated automatically.

### Uninstall
Removes the event and settings. Messages are kept unless *Settings → Advanced → Delete messages on uninstall* is ticked.
Uninstall the module before removing the package in *Extensions → Installer*; otherwise its event and tables stay in the database.
OpenCart 3 removes the module's permissions from **all** user groups on uninstall; a reinstall grants them only to the installing admin's group.

### Notes
- After uploading a new version, wait a few seconds before opening the module page. If the server's OPcache has `validate_timestamps=0`, reset OPcache or restart PHP; otherwise PHP may keep running the old code.
- A Twig error in a Theme Editor override of this template makes OpenCart stop the page output, and the module cannot catch it.
- Modern browsers only. IE11 is not supported.
- A message with no text in the visitor's language shows the store's default-language text. The admin list flags missing languages.
- An end date without a time means the end of that day (23:59:59). Only the first `{countdown}` is replaced; further ones are removed.
- Guests see messages for the store's default customer group.
- Closing is remembered per message: when a message expires, is switched off or is re-saved without content changes the bar stays closed; only a new message or changed text/button/link/icon brings it back.
- Countdowns use the server's clock, not the visitor's.
- No inline scripts. Colours are set with `style` attributes, so a CSP needs `style-src 'unsafe-inline'`.
- The end date has an "Until the end of that day (23:59:59)" checkbox.

### Security
- `modify` permission is checked on every save and delete. Every admin URL needs a valid `user_token`.
- All input is validated against a whitelist: colours `#RRGGBB`, links `http(s)://`, `/path` or `index.php?route=` only, icon `fa-*`, routes `[a-z0-9_/]`, dates, and existing store / customer-group IDs.
- All SQL values are cast to `int` or escaped with `$this->db->escape()`.
- OpenCart 3 runs Twig with autoescape off, so every value is escaped explicitly (`|e`) or comes from the sanitizer. HTML mode is sanitized on save and again on render.

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
