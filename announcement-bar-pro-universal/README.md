# Announcement Bar Pro — one package for OpenCart 3 and 4

Paziņojumu josla veikala augšā vai apakšā: akcijas, piegādes nosacījumi, svarīgas ziņas.
*An announcement bar for the top or bottom of every store page.*

- **Versija / Version:** 2.3.0 "Pro Max"
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

### Iespēju pārskats
| | |
|---|---|
| **Paziņojumi** | Neierobežots skaits, teksts katrā valodā + īsāks teksts telefonam, ikona, poga ar saiti (aizpildīta / ar apmali / saite), `{countdown}` |
| **Bezmaksas piegāde** | „Vēl {remaining} līdz bezmaksas piegādei” — atlikums no groza summas, apmeklētāja valūtā, ar progresa joslu; mainās uzreiz pēc AJAX „Grozā” bez lapas pārlādes; summu sasniedzot — sava teksta ziņa |
| **Atlaides kods** | Kods ar pogu „Kopēt” (starpliktuve, redzams un ekrāna lasītājam paziņots „Nokopēts!”), kopēšanas skaitītājs |
| **A/B tests** | Otra teksta versija (B); katrs apmeklētājs saņem A vai B un to patur; skatījumi, klikšķi un kopēšana katrai versijai; pēc 100 skatījumiem labākā atzīmēta; „Izmantot B kā galveno” |
| **Grafiks** | Sākuma / beigu datums, nedēļas dienas un diennakts laiks (arī pāri pusnaktij) veikala laikā — pārbauda serveris un lapā atvērtais skripts |
| **Kur rādīt** | Veikali, klientu grupas, lapas (`product/*`), ierīces, izvēlētas kategorijas un preces (arī preču lapas no izvēlētajām kategorijām) |
| **Izskats** | 5 stili — vienkrāsas, krāsu pāreja, maigs, minimālistisks, stikls — visi ar kontrastu vismaz 4,5:1; izgaišana vai slīdēšana, bultiņas, `prefers-reduced-motion` |
| **Admin** | Sadaļās sakārtota forma ar dzīvo priekšskatījumu (veikala īstais skripts un stili, dators / telefons, A/B, groza stāvoklis), saraksts ar krāsu paraugu, statusiem, ikonām un CTR, eksports / imports (JSON) |
| **Statistika** | Skatījumi, klikšķi, kopēšana, CTR; bez personas datiem, ievēro Do Not Track / GPC |

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

### Jaunums 2.3.0 „Pro Max”
- **Bezmaksas piegādes progress:** paziņojuma veids ar summu (veikala pamatvalūtā), tekstu ar `{remaining}` un tekstu sasniegšanai katrā valodā, neobligātu progresa joslu. Atlikumu aprēķina serveris no groza summas (bez nodokļiem) un formatē apmeklētāja valūtā; pēc OpenCart AJAX groza pieprasījumiem (pievienot / dzēst / mainīt, abās versijās) to atjauno neliels lasīšanas JSON pieprasījums tajā pašā domēnā.
- **Atlaides kods ar „Kopēt”:** A–Z, 0–9, - un _, līdz 32 zīmēm (glabā ar lielajiem burtiem); kopēšanas skaitītājs statistikā.
- **A/B tests:** teksts B, teksts B telefonam un pogas teksts B. Versija tiek izlozēta vienreiz katrai pārlūkprogrammai (`localStorage`, bez tās — A). Sarakstā abi CTR, labākais atzīmēts, kad katrai versijai ir vismaz 100 skatījumu; „Izmantot B kā galveno” pārkopē B uz A un izslēdz testu.
- **Nedēļas grafiks:** dienas un laika logs HH:MM–HH:MM (var pāriet pāri pusnaktij) veikala laika joslā; kopā ar sākuma / beigu datumu. Atvērtā lapā skripts paziņojumu parāda un paslēpj pats.
- **Kategorijas un preces:** izvēle ar automātisko meklēšanu (katras OpenCart versijas savs meklēšanas maršruts). Paziņojums redzams tikai šajās kategoriju lapās, šo preču lapās un to preču lapās, kas ir izvēlētajās kategorijās.
- **Eksports / imports (JSON):** teksti pēc valodas koda; imports līdz 1 MB, visas pārbaudes kā formā, valodas, kuru veikalā nav, tiek izlaistas; importētie paziņojumi ir izslēgti.
- **Dizains:** 5 joslas stili (moduļa iestatījums + katram paziņojumam savs), pogas kā „pill”, SVG aizvēršanas ikona, koda „čips” ar raustītu apmali, gluda progresa josla, izgaišana / slīdēšana, bultiņas. Admin: sadaļas un dzīvais priekšskatījums.

### Privātums (statistika)
Statistika ir tikai skaitītāji datubāzē (skatījumi, klikšķi un koda kopēšana katram paziņojumam un A/B versijai). A/B versijas izvēle tiek glabāta tikai apmeklētāja pārlūkā (`localStorage` `abp_ab`: paziņojuma numurs → `a`/`b`). Netiek saglabāti personas dati — ne IP adreses, ne lietotāja aģents, ne klienta ID — un modulis neliek savas sīkdatnes. Lai vienu apmeklētāju neskaitītu vairākkārt, tiek izmantota pārlūka `sessionStorage` un veikala esošā OpenCart sesija (katram paziņojumam ne vairāk kā viens skatījums un viens klikšķis sesijā, kopā ne vairāk kā 100 notikumi). Ja apmeklētāja pārlūkā ieslēgts **Do Not Track** vai **Global Privacy Control**, nekas netiek sūtīts.

### Jaunināšana
- **OpenCart 3:** augšupielādējiet jauno paku virs vecās (jebkuras iepriekšējās versijas), *Modifications → Refresh* un vienreiz atveriet moduļa lapu. Iestatījumi un paziņojumi paliek. Atverot moduļa lapu, datubāzei tiek pievienotas trūkstošās 2.2.0 un 2.3.0 kolonnas (vajag tiesības *modify*; atkārtota atvēršana neko nemaina); līdz tam josla strādā kā iepriekšējā versijā. Paziņojumi, iestatījumi un aizvērto paziņojumu atslēgas paliek; esošo paziņojumu pogas paliek „ar apmali”, kā līdz šim.
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

### Feature overview
| | |
|---|---|
| **Messages** | Unlimited, text per language + a shorter phone text, icon, button with link (filled / outline / link), `{countdown}` |
| **Free shipping** | "Add {remaining} more for free shipping" — remaining amount from the cart subtotal, in the visitor's currency, with a progress bar; updates right after an AJAX add-to-cart without a page reload; its own text once reached |
| **Coupon code** | Code chip with a Copy button (clipboard, visible and announced "Copied!"), copy counter |
| **A/B test** | A second version (B) of the text; every visitor gets A or B and keeps it; views, clicks and copies per version; the better one is marked after 100 views each; "Use B as the main text" |
| **Schedule** | Start / end date, weekdays and a daily time window (may cross midnight) in store time — checked by the server and by the open page |
| **Targeting** | Stores, customer groups, pages (`product/*`), devices, chosen categories and products (including product pages in the chosen categories) |
| **Design** | 5 styles — Solid, Gradient, Soft, Minimal, Glass — all with contrast of at least 4.5:1; fade or slide, prev/next arrows, `prefers-reduced-motion` |
| **Admin** | Form in sections with a live preview (the shop's real script and styles; computer / phone, A/B, cart state), list with colour swatch, status badges, icons and CTR, JSON export / import |
| **Statistics** | Views, clicks, copies, CTR; no personal data, honours Do Not Track / GPC |

### What is new in 2.3.0 "Pro Max"
- **Free-shipping progress:** message type with an amount (store default currency), a per-language text with `{remaining}` and a per-language text for when it is reached, optional progress bar. The server computes the remaining amount from the cart subtotal (without tax) and formats it in the visitor's currency; after OpenCart's own AJAX cart requests (add / remove / update, both versions) a small read-only same-origin JSON request updates it.
- **Coupon code with Copy:** A–Z, 0–9, - and _, up to 32 (stored uppercase); copies are counted.
- **A/B test:** text B, phone text B and button text B. The version is drawn once per browser (`localStorage`; without it: A). The list shows both CTRs and marks the better one once each version has 100 views; "Use B as the main text" copies B into A and ends the test.
- **Weekly schedule:** weekdays and a daily window HH:MM–HH:MM (may cross midnight) in the store's time zone, together with start / end dates. An open page shows and hides the message by itself.
- **Categories and products:** chosen with autocomplete (each OpenCart version's own catalog autocomplete route). The message shows only on those category pages, on those products' pages and on pages of products in those categories.
- **Export / import (JSON):** texts keyed by language code; import up to 1 MB, every message validated like the form, unknown languages skipped, imported messages switched off.
- **Design:** 5 bar styles (module setting + per-message override), pill buttons, SVG close icon, dashed coupon chip, smooth progress bar, fade / slide, arrows. Admin in sections with a live preview.

### What was new in 2.2.0
- **Phone text (optional):** per language, a shorter text shown on screens narrower than 768 px. Empty = the normal text everywhere. Same HTML rules, `{countdown}` works.
- **Devices:** per message — all devices, computers only (768 px and wider) or phones only. The bar rearranges itself when a phone is rotated or the window is resized; when no message fits the screen size, the bar is not shown.
- **Statistics:** views, clicks and CTR (%) in the message list, with "Reset statistics" per message.
- **Duplicate:** copies a message with all languages and settings; the copy is switched off and starts with zero statistics.

### Privacy (statistics)
Statistics are plain counters in the database (views, clicks and coupon copies per message and A/B version). The A/B choice is kept only in the visitor's browser (`localStorage` `abp_ab`: message number → `a`/`b`). No personal data is stored — no IP address, user agent or customer ID — and the module sets no cookies of its own. To avoid counting one visitor several times it uses the browser's `sessionStorage` and the shop's existing OpenCart session (at most one view and one click per message per session, 100 events in total). Nothing is sent when the visitor's browser has **Do Not Track** or **Global Privacy Control** switched on.

### Upgrade
- **OpenCart 3:** upload the new package over the old one, *Modifications → Refresh*, open the module page once. Settings and messages stay. Opening the module page adds the missing 2.2.0 and 2.3.0 columns (needs *modify* permission; opening it again changes nothing); until then the bar works as in the previous version. Messages, settings and the keys of closed messages stay; existing buttons keep their outline look.
- **OpenCart 4:** the installer refuses a package that is still installed. Order: *Modules → Announcement Bar Pro → Uninstall* → *Installer → Announcement Bar Pro → Uninstall* (red) and **Delete** → upload the new one → **Install** → *Modules → Install*. Messages stay (unless "Delete messages on uninstall" is on), but settings return to defaults: the module is **off** and colours are Custom — switch them back in *Settings*.

### Automatic theme adaptation
- **Fixed or sticky headers** are moved below the bar while the bar is on screen. A header the theme already pads content for stays where it is.
- **Themes without `<body>` in the header template:** the bar is injected through the footer and moved to the top before the first paint.
- **Bottom fixed elements** (cookie banners, mobile tab bars) lift a bottom bar above them.
- **Absolutely positioned page wrappers** (e.g. OpenCart 4.1): sticky top and the bottom bar still work.
- **Automatic colours** use the theme's main colour with readable text. **Custom** colours are set per message.
- Missing **Font Awesome** drops the icons. **RTL** languages are mirrored. The font is inherited from the theme.

### Security
- `modify` permission is checked on every save, delete, duplicate, statistics reset, "Use B" and import; every admin URL carries `user_token`. Write buttons ignore double clicks.
- The public statistics endpoint accepts only POST `id` (integer), `type` (`view`/`click`/`copy`) and `v` (`a`/`b`) from the shop's own pages, counts only enabled messages inside their date window and always returns an empty 204. It never writes the session.
- The free-shipping endpoint is a read-only GET (`ids`, max. 20) that answers same-origin requests only, with JSON built from server-sanitized HTML; it never outputs errors.
- Import never trusts the file: at most 1 MB, our export format only, every message goes through the form's validation and sanitizing and is created switched off; `modify` permission and `user_token` are required.
- All input is whitelist-validated, and every SQL value is cast or escaped.
- HTML mode uses a whitelist sanitizer on save and again on render.
- Twig runs with autoescape off in OpenCart, so output is escaped explicitly.

## Build
```bash
./build.sh   # → dist/v<version>/announcement_bar_pro.ocmod.zip
```
