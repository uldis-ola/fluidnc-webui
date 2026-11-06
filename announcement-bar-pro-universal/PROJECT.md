# Announcement Bar Pro — pilns projekta apraksts (nodošanai citam čatam)

> Šis fails ir domāts, lai jaunā Claude Code čatā varētu turpināt darbu bez iepriekšējās sarunas.
> Iedod jaunajam čatam šo failu un projekta arhīvu (vai repozitoriju).

## 1. Kas tas ir

Paziņojumu josla (announcement bar) OpenCart veikalam: josla lapas augšā vai apakšā ar akcijām,
piegādes info, atpakaļskaitīšanu u. tml. Viena instalācijas paka der **OpenCart 3.0.x, 4.0.x un 4.1.x**.
Josla automātiski pielāgojas veikala tēmai.

- Versija: **2.2.0**, DB shēma **6**
- Licence: GPL-3.0 (LICENSE.txt no OpenCart pirmkoda)
- Valodas: LV, EN, RU (admin + veikals). Ja veikala valodai tulkojuma nav → angļu.
- Marketplace-gatavs. Autors `3dp.lv`, saite `https://3dp.lv` (no 2.1.1; Ulda izvēle 06.11.2026).

## 2. Repozitorijs un zars

- Repo: `uldis-ola/fluidnc-webui` (repo nosaukums vēsturisks, modulis ir apakšmapēs)
- Zars: `claude/ecommerce-module-creation-1scmn7` (pēdējais moduļa commit `e6eb265` + šis apraksts)
- PR nav izveidots.

```
announcement-bar-pro/            OC 3.0.x pirmkods (upload/ + install.xml), build.sh
announcement-bar-pro-oc4/        OC 4.0/4.1 pirmkods (src/ ar install.json), build.sh
announcement-bar-pro-universal/  universālās pakas build.sh, README, CHANGELOG, šis fails,
                                 tests/e2e (Playwright testi), tests/demo (interaktīvs demo)
index.html                       repo vecais saturs, ar moduli nav saistīts
```

## 3. Būvēšana

```bash
cd announcement-bar-pro-universal && ./build.sh
# → dist/v2.2.0/announcement_bar_pro.ocmod.zip
```
Universālā paka satur abus komplektus vienā zip:
- OC3 instalētājs ņem tikai `upload/` + `install.xml`;
- OC4 instalētājs ņem `install.json` + `admin/ catalog/ system/` un ieliek `extension/announcement_bar_pro/`.

**Faila nosaukumu nemainīt:** OC4 moduļa kodu ņem no zip nosaukuma (`announcement_bar_pro.ocmod.zip`).
build.sh pārbauda, ka versijas abos komplektos sakrīt. Atsevišķas pakas: `announcement-bar-pro/build.sh`, `announcement-bar-pro-oc4/build.sh`.

## 4. Funkcijas

**Paziņojumi (neierobežots skaits):** teksts katrā valodā, pogas teksts + saite (var atvērt jaunā cilnē),
ikona (Font Awesome), fona/teksta krāsa, kārtība, statuss, sākuma/beigu datums, `{countdown}` atpakaļskaitīšana
(servera laikā), veikali (multistore), klientu grupas, lapas (visas / tikai / izņemot, maršruti ar `*`).

**Iestatījumi** (`module_announcement_bar_pro_*`): status, position (top/bottom), sticky, mode (rotate/marquee/stack),
interval, speed, font_size, padding, dismissible, dismiss_days (0 = līdz cilnes aizvēršanai), allow_html,
color_mode (auto/custom), default_bg_color, default_text_color, delete_data (dzēst ziņas pie atinstalēšanas).

**DB tabulas:** `oc_abp_message` (message_id, status, sort_order, date_start, date_end, bg_color, text_color, link,
link_new_tab, icon, countdown, stores, customer_groups, page_mode, page_routes, device, views, clicks, date_added,
date_modified), `oc_abp_message_description` (message_id, language_id, text, link_text, mobile_text). Shēmas migrācija
no v1 automātiska (tikai lietotājam ar modify tiesībām); shēma 6 pievieno kolonnas ar `SHOW COLUMNS` pārbaudi
(`schemaColumns()` + `addColumnIfMissing()` admin modelī). `mobile_text` ir `TEXT NOT NULL` bez DEFAULT (MySQL to neatļauj).

**2.2.0:** teksts telefonam (< 768 px, CSS `.abp-text-d`/`.abp-text-m`), ierīces (`data-device`, CSS + JS izslēdz no
rotācijas/marquee/stack, pārbūvē pie 768 px robežas), statistika (catalog `track`: POST id+type, 204, dedup OpenCart
sesijā + `sessionStorage abp_track`, max 100 notikumi sesijā, DNT/GPC → nesūta), dublēšana un statistikas atiestatīšana
adminā. Katalogs ar shēmu < 6 (faili jaunināti, admin vēl nav atvērts) strādā bez jaunajām kolonnām.

**Automātiskā pielāgošanās (JS, kopīgs OC3/OC4):**
- fiksēta/sticky galvene tiek nobīdīta zem joslas (tikai ja tiešām pārklāj; bez `!important`; atjauno, kad nepārklāj);
- apakšējā josla paceļas virs sīkdatņu joslas / mobilās izvēlnes;
- z-index = min(tēmas max + 1, 1039) (zem Bootstrap modāļiem);
- automātiskās krāsas no tēmas (`.btn-primary`, `#menu`, `.navbar` …), teksta krāsa pēc spilgtuma;
- nav Font Awesome → ikonas izņem; RTL atbalsts;
- aizvēršana atcerēta katram paziņojumam (atslēga no satura → saturs mainās = rāda atkal), localStorage/sessionStorage `abp_dismiss2`.

**Ievietošana lapā (OpenCart notikumi, bez core labojumiem):**
1. `catalog/view/common/header/after` → injectBar (ieliek joslu aiz `<body>`)
2. `catalog/view/common/footer/after` → injectBarFooter (ieliek marķieri `<!--abp-fallback-->`)
3. `catalog/view/*/after` → resolveFallback (ja galvenē nebija `<body>`, josla tiek ielikta pie marķiera un JS to pārceļ augšā)

Svarīgi: OpenCart renderē **footer pirms header** — tāpēc rezerves ceļš strādā ar marķieri, nevis tieši footerī.

## 5. Drošība

- Visi PHP faili: `defined('VERSION') || exit;`
- Admin: `hasPermission('modify')` katrai rakstīšanai, `user_token`, validācija (krāsas, datumi, saites, masīvi POST).
- SQL: `(int)` un `$this->db->escape()`; nav tiešu lietotāja datu vaicājumos.
- HTML režīms: savs whitelist sanitizators (DOMDocument, elementi pārbūvēti no jauna; `system/library/.../sanitizer.php`).
  Bez HTML režīma viss tiek escapēts. Saites: tikai http(s)/relatīvas, non-ASCII procentkodēts.
- Emoji: OC3 un OC 4.0.0.x (3-baitu utf8 savienojums) → saglabā kā HTML entītijas.
- Ārēji CDN netiek izmantoti.

## 6. Versiju īpatnības (tas, kas jāzina, labojot kodu)

| | OC 3.0.x | OC 4.0.0.0 | 4.0.1.x | 4.0.2.x+ / 4.1 |
|---|---|---|---|---|
| Maršruta metode | `/` | `\|` | `\|` | `.` |
| addEvent | pozicionāli | pozicionāli | masīvs | masīvs |
| DB charset | utf8 | utf8 | utf8mb4 | utf8mb4 |
| Namespace | nav | `Opencart\Admin\Controller\Extension\AnnouncementBarPro\Module` | | |

OC4 admin formas ir AJAX (JSON kļūdas → `#error-*`). OC 4.1 `#container` ir `position:absolute` (apakšējai joslai spacer).
OC 4.1 valodas atslēga `config_language_catalog`, citur `config_language`.

## 7. Pārbaudes stāvoklis (2.1.0)

Pārbaudīts ar īstu OpenCart, paka instalēta caur Extension Installer, Playwright Chromium:

| Versija | Rezultāts |
|---|---|
| OC 3.0.5.1 (jaunināšana v1 → 2.1.0) | 118/118 |
| OC 4.0.0.0*, 4.0.1.1*, 4.0.2.0, 4.0.2.3, 4.1.0.4 | katrā 69/69 (32 pamata + 9 papildu + 9 regresijas + 19 tēmu) |
| Demo lapa (tests/demo/test.js) | 13/13 |

\* 4.0.0/4.0.1 nestrādā ar PHP 8.3 → testēts ar deprecation brīdinājumu apspiešanu (PHP 8.1 simulācija).
Bez moduļa rindām error.log. Arī `php -l`, Twig 1.44/3.x lint, PHPCompatibility.

4 neatkarīgu AI pārskatu kārtas — visi atradumi izlaboti (sk. CHANGELOG.md): sanitizatora mūžīgais cikls (augsta, 2.0.4),
footer rezerve pārņēma ievietošanu (augsta, 2.1.0) u. c.

**Netestēts:** īsts PHP 7.x/8.1; OC 3.0.0–3.0.4; OC 2.x (neatbalsta); komerctēmas (Journal u. c.); Firefox, Safari.

**2.2.0 (07.11.2026):** NAV pārbaudīts ar īstu OpenCart/MySQL (lokāli nebija). Pārbaudīts: `php -l` visiem PHP failiem,
`tests/unit/schema_test.php` (57/57 — migrācijas loģika ar viltus DB abiem komplektiem), `tests/demo/test.js` (28/28 —
13 vecās + telefona teksts, ierīces, 768 px robežas šķērsošana, statistikas bākas, DNT/GPC), `build.sh` (versiju un
kopīgo failu pārbaude). Pirms publicēšanas jāpalaiž e2e testi īstā OC 3.0.5.1 un 4.x (jaunināšana 2.1.2 → 2.2.0).

## 8. Testu palaišana (tests/e2e)

Vajag: PHP 8.x + mysqli, MariaDB/MySQL (lietotājs `oc`/`ocpass`), Node + Playwright Chromium, OpenCart pirmkodi.
```bash
export ABP_WORK=/kaut/kur/darba-mape   # tajā OpenCart pirmkodi (oc-src = 3.0.5.1) un testa veikali
./tests/e2e/mkshop.sh <opencart upload dir> <ports> <db> <veikala mape>   # jauns testa veikals
./tests/e2e/run21oc3.sh   # OC3 pilnais (veikals oc21, ports 8096)
./tests/e2e/run21oc4.sh   # OC4 visas versijas (porti 8102/8103/8113–8115, veikali u4023,u4104,uold3–5)
```
Admin testa veikalos: `admin` / `admin123`. OPcache: pēc augšupielādes testi gaida 3 s.
OC 4.0.0.0 un 4.0.1.1 CLI instalētājs ir salūzis → `webinstall.js` (web instalētājs caur Playwright).
Skripti rakstīti konkrētai videi — ceļi/porti var būt jāpielāgo.

Demo: `node tests/demo/build.js` → `tests/demo/announcement-bar-demo.html` (iekļauj moduļa īsto JS/CSS), `node tests/demo/test.js`.
Migrācijas tests bez DB: `php tests/unit/schema_test.php`.
Publicētais demo: https://claude.ai/artifact/JV4VbyRutwCA9Axw42DGYs (privāts).

## 9. Kas vēl jāizdara

1. ~~Autora dati~~ — izdarīts 2.1.1 (`3dp.lv`, `https://3dp.lv`).
2. Pārbaudīt savā veikalā (vispirms testa kopijā!) ar savu tēmu: telefons + dators, aizvēršana, atpakaļskaitīšana.
3. Ja publicē OpenCart Marketplace: ekrānuzņēmumi, apraksts, cena (nav noteikta).
4. Iespējami uzlabojumi (nav pieprasīti): papildu valodas, A/B. (Statistika — izdarīts 2.2.0.)

## 10. Noteikumi turpmākajam darbam (lietotāja prasības)

- Atbildes latviski, īsi, konkrēti. Neizdomāt faktus; norādīt, kas pārbaudīts un kas pieņēmums.
- Nesaukt kodu par testētu, ja tas nav testēts.
- Nekādu core failu labojumu — tikai notikumi/OCMOD. Tīra un atkārtojama instalēšana/atinstalēšana.
- Pirms instalēšanas dzīvā veikalā — prasīt apstiprinājumu.
- Pēc izmaiņām: `php -l`, Twig, XML, zip struktūra, versija + CHANGELOG.
- Atskaites beigās: Pārbaudīts / Netestēts / Riski / Kas jāpārbauda live.
