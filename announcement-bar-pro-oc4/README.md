# Announcement Bar Pro — OpenCart 4.0.x / 4.1.x

Paziņojumu josla veikala augšā vai apakšā: akcijas, piegādes nosacījumi, svarīgas ziņas.
*An announcement bar for the top or bottom of every store page.*

- **Versija / Version:** 2.0.3 (same features as the OpenCart 3 package 2.0.3)
- **Saderība / Compatibility:** OpenCart 4.0.2.0 – 4.1.0.x (tested on 4.0.2.3 and 4.1.0.4), PHP 8.0+ (tested: 8.3). 4.0.0.x–4.0.1.x are handled in code (route separator `|`) but not tested.
- **Valodas / Languages:** English, Latviešu, Русский
- **Licence / License:** GPL-3.0 (see `LICENSE.txt`)
- OpenCart 3.0.x: use the separate package in `announcement-bar-pro`.

## LV

### Instalēšana
1. **Pirms instalēšanas izveido failu un datubāzes rezerves kopiju.**
2. Admin → *Extensions → Installer* → **Upload** → izvēlies `announcement_bar_pro.ocmod.zip`.
   Faila nosaukumu **nemaini**. OpenCart 4 paplašinājuma kodu ņem no faila nosaukuma.
3. Sarakstā pie *Announcement Bar Pro* nospied zaļo **Install**.
4. *Extensions → Extensions → Modules* → **Announcement Bar Pro** → **Install** (zaļais +) → **Edit**.
5. Cilnē *Iestatījumi* ieslēdz moduli un saglabā; cilnē *Paziņojumi* pievieno paziņojumus.

### Iespējas
Tās pašas, kas OC3 versijai:
- vairāki paziņojumi ar tekstu katrā valodā;
- trīs attēlošanas režīmi: mainīgi, skrejošā rinda, visi reizē;
- laika plānošana un atpakaļskaitīšana (`{countdown}`);
- redzamība pa veikaliem, klientu grupām un lapām;
- aizvēršanas poga, kas atceras katru paziņojumu atsevišķi;
- novietojums augšā vai apakšā;
- vienkāršs HTML pēc izvēles.

Ikonas ir Font Awesome 6, piemēram, `fa-truck` vai `fa-regular fa-bell`.

### Atinstalēšana
- *Modules → Announcement Bar Pro → Uninstall* noņem notikumu un iestatījumus. Paziņojumi paliek, ja vien nav atzīmēts *Iestatījumi → Papildus → Dzēst paziņojumus atinstalējot*.
- Pēc tam paku var noņemt *Extensions → Installer* sadaļā.

### Piezīmes
- OC4 noklusētajā tēmā tukšais paziņojumu konteiners `#alert` pārklāj augšējo labo stūri. Modulis tam izslēdz klikšķus, kamēr konteiners ir tukšs (`#alert:empty{pointer-events:none}`), lai aizvēršanas poga strādātu.
- Ja paziņojumam nav teksta apmeklētāja valodā, tiek rādīts veikala noklusētās valodas teksts.
- Datumi ir veikala laika joslā. Beigu datumam ir izvēles rūtiņa „Līdz šīs dienas beigām”.
- Modulis neizmanto inline skriptus. Krāsas tiek iestatītas ar `style` atribūtiem, tāpēc CSP politikai jāatļauj `style-src 'unsafe-inline'`.
- Pirms publicēšanas `install.json` ieraksti savu `author` un `link`.

## EN

### Install
1. **Back up files and database first.**
2. Admin → *Extensions → Installer* → **Upload** `announcement_bar_pro.ocmod.zip`.
   **Do not rename the file**: OpenCart 4 takes the extension code from the file name.
3. Click the green **Install** next to *Announcement Bar Pro* in the installer list.
4. *Extensions → Extensions → Modules* → **Announcement Bar Pro** → **Install** → **Edit**.

### Notes
- The OC4 default theme's empty, fixed `#alert` toast container covers the top-right corner. The module disables pointer events on it while it is empty, so the close button can be clicked.
- A message with no text in the visitor's language shows the store's default-language text.
- Only the first `{countdown}` in a message is replaced.
- Guests see messages for the store's default customer group.
- No inline scripts. Colours are set with `style` attributes, so a CSP needs `style-src 'unsafe-inline'`.
- Set your own `author` and `link` in `install.json` before publishing.

### Security
- Every save and delete checks `modify` permission. All admin URLs carry `user_token`.
- Input is validated against whitelists: colours `#RRGGBB`; links `http(s)://`, `/path` or `index.php?route=`; icons `fa-*`; routes `[a-z0-9_/.]`; existing store and customer-group IDs.
- Every SQL value is cast or escaped.
- Twig runs with autoescape off in OpenCart 4, so output is escaped explicitly with `|e` or comes from the sanitizer.

## Build
```bash
./build.sh   # → dist/v<version>/announcement_bar_pro.ocmod.zip
```
