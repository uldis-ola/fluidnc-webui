# Announcement Bar Pro — one package for OpenCart 3 and 4

Paziņojumu josla veikala augšā vai apakšā: akcijas, piegādes nosacījumi, svarīgas ziņas.
*An announcement bar for the top or bottom of every store page.*

- **Versija / Version:** 2.2.0
- **Viena paka visām versijām / One package for all versions:** `announcement_bar_pro.ocmod.zip`
  - OpenCart **3.0.x** (tested 3.0.5.1; code uses only APIs present since 3.0.0.0), PHP 7.3+
  - OpenCart **4.0.x / 4.1.x** (tested 4.0.2.3 and 4.1.0.4; 4.0.0–4.0.1 handled in code, not runtime-tested), PHP 8.0+
- **Valodas / Languages:** English, Latviešu, Русский. Other languages get the English labels.
- **Licence / License:** GPL-3.0

The source code is in the repository folders `announcement-bar-pro` (OpenCart 3) and `announcement-bar-pro-oc4` (OpenCart 4). `build.sh` combines them into this package.

---

## LV

### Instalēšana (vienāda visām versijām)
1. **Pirms instalēšanas izveidojiet failu un datubāzes rezerves kopiju.**
2. Admin → *Extensions → Installer* → augšupielādējiet `announcement_bar_pro.ocmod.zip`. Faila nosaukumu **nemainiet**.
   - OpenCart 4: sarakstā pie *Announcement Bar Pro* nospiediet zaļo **Install**.
   - OpenCart 3: *Extensions → Modifications* → **Refresh** (nav obligāti).
3. *Extensions → Extensions → Modules* → **Announcement Bar Pro** → **Install** → **Edit**.
4. Pievienojiet pirmo paziņojumu. Svaigā instalācijā modulis jau ir ieslēgts, un krāsas ir „Automātiski”.

Paka pati nosaka OpenCart versiju: OpenCart 3 ņem tikai savus failus (`upload/` + `install.xml`), OpenCart 4 tikai savus (`install.json` + `admin/`, `catalog/`, `system/`).

### Automātiska pielāgošanās jebkurai tēmai
Nekas nav jāiestata:
- **Fiksētas un piestiprinātas galvenes** (piem., izvēlne, kas paliek redzama ritinot) tiek pabīdītas zem joslas, kamēr josla ir redzama. Ja tēma jau atstāj vietu zem galvenes, galvene paliek savā vietā.
- **Tēmas bez `<body>` galvenes veidnē:** josla tiek ievietota caur footer un pirms lapas attēlošanas pārvietota uz augšu.
- **Apakšējie fiksētie elementi** (sīkdatņu josla, mobilā izvēlne apakšā): apakšējā josla paceļas virs tiem.
- **Lapas ietinēji ar `position:absolute`** (piem., OpenCart 4.1): piestiprinātā josla un apakšējā josla darbojas arī tur.
- **Krāsas „Automātiski”:** josla paņem tēmas galveno krāsu (pogas, izvēlne) un izvēlas labi lasāmu teksta krāsu. „Pielāgotas” režīmā katram paziņojumam ir savas krāsas.
- **Font Awesome:** ja tēmai to nav, ikonas tiek izlaistas, nevis rādītas kā tukši kvadrātiņi.
- **RTL valodas** (arābu, ivrits): aizvēršanas poga un atstarpes tiek apgrieztas.
- **Fonts** tiek mantots no tēmas.

### Iespējas
- Neierobežots paziņojumu skaits; teksts katrā valodā; ikona, saite vai poga.
- Režīmi: mainīgi, skrejošā rinda, visi reizē. Novietojums augšā (var būt piestiprināts) vai apakšā.
- Laika plānošana, „līdz dienas beigām”, atpakaļskaitīšana `{countdown}` (servera laikā).
- Redzamība pa veikaliem, klientu grupām un lapām (`product/*`).
- Aizvēršana tiek atcerēta katram paziņojumam. Atgriežas tikai jauns paziņojums vai paziņojums ar mainītu tekstu, pogu, saiti vai ikonu.
- Valodai bez teksta tiek rādīts veikala noklusētās valodas teksts.
- Core faili netiek mainīti, nav inline skriptu, nav ārēju CDN.

### Jaunums 2.2.0
- **Teksts telefonam (neobligāts):** katrā valodā var ierakstīt īsāku tekstu, ko rāda ekrānos, kas šaurāki par 768 px. Tukšs = visur parastais teksts. Darbojas tie paši HTML noteikumi un `{countdown}`.
- **Ierīces:** katram paziņojumam — visas ierīces, tikai datori (768 px un platāki) vai tikai telefoni. Pagriežot telefonu vai mainot loga platumu, josla pārkārtojas pati; ja nevienam paziņojumam šis ekrāna izmērs neder, josla netiek rādīta.
- **Statistika:** paziņojumu sarakstā ir skatījumi, klikšķi un CTR (%), katram paziņojumam poga „Atiestatīt statistiku”.
- **Dublēt:** paziņojuma kopija ar visām valodām un iestatījumiem; kopija ir izslēgta un ar nulles statistiku.

### Privātums (statistika)
Statistika ir tikai skaitītāji datubāzē (skatījumi un klikšķi katram paziņojumam). Netiek saglabāti personas dati — ne IP adreses, ne lietotāja aģents, ne klienta ID — un modulis neliek savas sīkdatnes. Lai vienu apmeklētāju neskaitītu vairākkārt, tiek izmantota pārlūka `sessionStorage` un veikala esošā OpenCart sesija (katram paziņojumam ne vairāk kā viens skatījums un viens klikšķis sesijā, kopā ne vairāk kā 100 notikumi). Ja apmeklētāja pārlūkā ieslēgts **Do Not Track** vai **Global Privacy Control**, nekas netiek sūtīts.

### Jaunināšana
- **OpenCart 3:** augšupielādējiet jauno paku virs vecās (jebkuras iepriekšējās versijas), *Modifications → Refresh* un vienreiz atveriet moduļa lapu. Iestatījumi un paziņojumi paliek. Atverot moduļa lapu, datubāzei tiek pievienotas 2.2.0 kolonnas (vajag tiesības *modify*); līdz tam josla strādā kā 2.1 versijā, bez statistikas.
- **OpenCart 4:** instalētājs nepieņem paku, kamēr vecā ir instalēta. Secība: *Modules → Announcement Bar Pro → Uninstall* → *Installer → Announcement Bar Pro → Uninstall* (sarkanā) un **Delete** → augšupielādēt jauno → **Install** → *Modules → Install*. Paziņojumi paliek (ja nav ieslēgts „Dzēst paziņojumus atinstalējot”), bet iestatījumi atgriežas noklusētie: modulis ir **izslēgts** un krāsas „Pielāgotas” — ieslēdziet tos atkārtoti *Iestatījumos*.
- Ja iepriekšējā versija bija instalēta, krāsas paliek „Pielāgotas”, un rezerves notikums tiek pievienots automātiski.

### Atinstalēšana
*Modules → Announcement Bar Pro → Uninstall* noņem notikumus un iestatījumus. Paziņojumi paliek, ja vien nav atzīmēts *Iestatījumi → Papildus → Dzēst paziņojumus atinstalējot*. Paku no *Extensions → Installer* noņemiet tikai pēc tam.

### Ierobežojumi
- OpenCart 2.x netiek atbalstīts.
- Krāsas tiek iestatītas ar `style` atribūtiem, tāpēc CSP politikai jāatļauj `style-src 'unsafe-inline'`.
- Ja moduļa veidne tiek labota caur *Theme Editor* un tajā ir Twig kļūda, OpenCart pats aptur lapas izvadi.
- OpenCart 3 atinstalējot noņem moduļa tiesības visām lietotāju grupām.
- Pirms publicēšanas Marketplace `install.json`/`install.xml` laukos *author* un *link* ierakstiet savus datus.

## EN

### Install (same on every version)
1. **Back up files and database first.**
2. Admin → *Extensions → Installer* → upload `announcement_bar_pro.ocmod.zip`. Do **not** rename the file.
   - OpenCart 4: click the green **Install** next to *Announcement Bar Pro* in the installer list.
   - OpenCart 3: *Extensions → Modifications* → **Refresh** (optional).
3. *Extensions → Extensions → Modules* → **Announcement Bar Pro** → **Install** → **Edit**.
4. Add your first message. A fresh install is already enabled, with colours set to Automatic.

### What is new in 2.2.0
- **Phone text (optional):** per language, a shorter text shown on screens narrower than 768 px. Empty = the normal text everywhere. Same HTML rules, `{countdown}` works.
- **Devices:** per message — all devices, computers only (768 px and wider) or phones only. The bar rearranges itself when a phone is rotated or the window is resized; when no message fits the screen size, the bar is not shown.
- **Statistics:** views, clicks and CTR (%) in the message list, with "Reset statistics" per message.
- **Duplicate:** copies a message with all languages and settings; the copy is switched off and starts with zero statistics.

### Privacy (statistics)
Statistics are plain counters in the database (views and clicks per message). No personal data is stored — no IP address, user agent or customer ID — and the module sets no cookies of its own. To avoid counting one visitor several times it uses the browser's `sessionStorage` and the shop's existing OpenCart session (at most one view and one click per message per session, 100 events in total). Nothing is sent when the visitor's browser has **Do Not Track** or **Global Privacy Control** switched on.

### Upgrade
- **OpenCart 3:** upload the new package over the old one, *Modifications → Refresh*, open the module page once. Settings and messages stay. Opening the module page adds the 2.2.0 columns to the database (needs *modify* permission); until then the bar works as in 2.1, without statistics.
- **OpenCart 4:** the installer refuses a package that is still installed. Order: *Modules → Announcement Bar Pro → Uninstall* → *Installer → Announcement Bar Pro → Uninstall* (red) and **Delete** → upload the new one → **Install** → *Modules → Install*. Messages stay (unless "Delete messages on uninstall" is on), but settings return to defaults: the module is **off** and colours are Custom — switch them back in *Settings*.

### Automatic theme adaptation
- **Fixed or sticky headers** are moved below the bar while the bar is on screen. A header the theme already pads content for stays where it is.
- **Themes without `<body>` in the header template:** the bar is injected through the footer and moved to the top before the first paint.
- **Bottom fixed elements** (cookie banners, mobile tab bars) lift a bottom bar above them.
- **Absolutely positioned page wrappers** (e.g. OpenCart 4.1): sticky top and the bottom bar still work.
- **Automatic colours** use the theme's main colour with readable text. **Custom** colours are set per message.
- Missing **Font Awesome** drops the icons. **RTL** languages are mirrored. The font is inherited from the theme.

### Security
- `modify` permission is checked on every save, delete, duplicate and statistics reset; every admin URL carries `user_token`.
- The public statistics endpoint accepts only POST `id` (integer) and `type` (`view`/`click`), counts only enabled messages and always returns an empty 204.
- All input is whitelist-validated, and every SQL value is cast or escaped.
- HTML mode uses a whitelist sanitizer on save and again on render.
- Twig runs with autoescape off in OpenCart, so output is escaped explicitly.

## Build
```bash
./build.sh   # → dist/v<version>/announcement_bar_pro.ocmod.zip
```
