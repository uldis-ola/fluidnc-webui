# Announcement Bar Pro — pilns projekta apraksts (nodošanai citam čatam)

> Šis fails ir domāts, lai jaunā Claude Code čatā varētu turpināt darbu bez iepriekšējās sarunas.
> Iedod jaunajam čatam šo failu un projekta arhīvu (vai repozitoriju).

## 1. Kas tas ir

Paziņojumu josla (announcement bar) OpenCart veikalam: josla lapas augšā vai apakšā ar akcijām,
piegādes info, atpakaļskaitīšanu u. tml. Viena instalācijas paka der **OpenCart 3.0.x, 4.0.x un 4.1.x**.
Josla automātiski pielāgojas veikala tēmai.

- Versija: **2.4.1**, DB shēma **8**
- Licence: GPL-3.0 (LICENSE.txt no OpenCart pirmkoda)
- Valodas: LV, EN, RU (admin + veikals). Ja veikala valodai tulkojuma nav → angļu.
- Marketplace-gatavs. Autors `3DP SIA`, saite `mailto:extension@3dp.lv` (no 2.3.0; Ulda izvēle 07.11.2026 — moduļi nav saistīti ar 3dp.lv).

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
# → dist/v2.4.1/announcement_bar_pro.ocmod.zip
```
Universālā paka satur abus komplektus vienā zip:
- OC3 instalētājs ņem tikai `upload/` + `install.xml`;
- OC4 instalētājs ņem `install.json` + `admin/ catalog/ system/` un ieliek `extension/announcement_bar_pro/`.

**Faila nosaukumu nemainīt:** OC4 moduļa kodu ņem no zip nosaukuma (`announcement_bar_pro.ocmod.zip`).
build.sh pārbauda, ka versijas abos komplektos sakrīt un ka kopīgie faili (veikala JS/CSS un admin `abp_admin.js`/`abp_admin.css`) abos komplektos ir baitu identiski; veikala JS/CSS (`announcement_bar_pro.js`, `announcement_bar_pro_pre.js`, `announcement_bar_pro.css`) ir baitu identiski arī WordPress versijā (`../announcement-bar-pro-wordpress/announcement-bar-pro/assets/js|css/announcement-bar-pro*.js|css`) — ja šī mape blakus ir, build.sh tos salīdzina un atšķirības gadījumā aptur būvēšanu (2.4.1). Atsevišķas pakas: `announcement-bar-pro/build.sh`, `announcement-bar-pro-oc4/build.sh`.

## 4. Funkcijas

**Paziņojumi (neierobežots skaits):** teksts katrā valodā, pogas teksts + saite (var atvērt jaunā cilnē),
ikona (Font Awesome), fona/teksta krāsa, kārtība, statuss, sākuma/beigu datums, `{countdown}` atpakaļskaitīšana
(servera laikā), veikali (multistore), klientu grupas, lapas (visas / tikai / izņemot, maršruti ar `*`).

**Iestatījumi** (`module_announcement_bar_pro_*`): status, position (top/bottom), sticky, mode (rotate/marquee/stack),
interval, speed, font_size, padding, dismissible, dismiss_days (0 = līdz cilnes aizvēršanai), allow_html,
color_mode (auto/custom), default_bg_color, default_text_color, delete_data (dzēst ziņas pie atinstalēšanas),
style (solid/gradient/soft/minimal/glass), transition (fade/slide), arrows (2.3.0).

**DB tabulas:** `oc_abp_message` (message_id, status, sort_order, date_start, date_end, bg_color, text_color, link,
link_new_tab, icon, countdown, stores, customer_groups, page_mode, page_routes, device, views, clicks, date_added,
date_modified), `oc_abp_message_description` (message_id, language_id, text, link_text, mobile_text). Shēmas migrācija
no v1 automātiska (tikai lietotājam ar modify tiesībām); shēma 6 pievieno kolonnas ar `SHOW COLUMNS` pārbaudi
(`schemaColumns()` + `addColumnIfMissing()` admin modelī). `mobile_text` ir `TEXT NOT NULL` bez DEFAULT (MySQL to neatļauj).

**2.4.1 (shēma paliek 8):** Cloudflare Turnstile pieteikšanās formai un atkārtotas pieteikšanās noteikums.
- *Turnstile:* iestatījumi `ts_site`, `ts_secret` (ieslēgts tikai ar abām; `Features::tsSiteKey()/tsSecretKey()/tsOn()`). Slepenā atslēga nekad netiek drukāta: `settingKeys()` to nesatur, admin rāda tukšu password lauku + `ts_secret_saved`; tukšs lauks = paturēt (`tsSecretToSave()` → null), `ts_secret_remove` = dzēst. Veikalā `prepare()` dod tikai `ts` (site key), `ts_wait`, `err_ts` → twig `data-ts`, `data-ts-wait`, `data-err-ts` + `<span class="abp-ts">` pirms `.abp-form-msg`. `subscribe()` pēc visām pārbaudēm, tieši pirms `signUp()` — `turnstileError()` → `Features::tsVerify($secret, $post['cf-turnstile-response'], clientIp(), [$fx, 'httpPost'])`: 'ok' | 'missing' | 'failed' (fail closed; cits `action` nekā `abp-signup` = failed). `httpPost()` — curl ar TLS pārbaudi, citādi https stream, 8 s.
- *Atkārtota pieteikšanās:* status 2 + dubultā apstiprināšana → `setResubscribeToken()` (tikai `token`), e-pasts ar `Features::unsubToken()` = HMAC('unsub|'.token, secret). Tokens `Features::optinToken()` = `sprintf('%08x', time())` + 56 hex; `tokenFresh()` (≤ 7 d, ≤ 5 min nākotnē). `tokenPage()` status 2 → `resubscribe()` (status 1, token '', date_confirmed, unsubscribe = sha256(atvasinātais)). `saveSubscriber()` ON DUPLICATE KEY UPDATE ar `IF(status = 2, …)`, `status` pēdējais; `confirmSubscriber()` tikai status 0.

**2.4.0 (shēma 8):** jauna bibliotēka `system/library/…/features.php` (klases ķermenis abos komplektos baitu identisks, `build.sh` to pārbauda):
opciju validācija (`validate()` ar `lookup` atzvanu uz DB), valsts / zonas / valūtas sliekšņi, pakāpes, groza nosacījumi, termiņa atskaite
(`nextCutoff`), CSS filtrs, formas tokeni (HMAC ar `module_announcement_bar_pro_secret`), CSV, 13 veidnes.
- *Kolonnas:* `abp_message.placement` (''|top|bottom|cart|product|minicart), `priority` (0–100, kārtošana DESC), `options` (JSON, ASCII),
  `subscribes`; `abp_message_description.extra` (JSON: pakāpju nosaukumi/teksti, e-pasta formas teksti). Tabulas `abp_subscriber` (tikai tokenu
  SHA-256), `abp_rate` (ar atslēgu jaukts IP + logs). `_schema` tiek ierakstīts pēdējais (unset + set).
- *Vietas:* notikumi `checkout/cart`, `product/product`, `common/cart` view/after (6 notikumi kopā); `prepare()` statiski kešo visas ziņas
  pieprasījumā; resursi (CSS/JS) vienreiz lapā; ar vienu joslu id `abp-bar`, ar divām — `abp-bar-bottom`. Inline: režīms stack, bez aizvēršanas.
- *Klienta puses vārti:* `data-off` (grafiks), `data-coff` (groza nosacījumi, atjauno `…/cart`), `data-uoff` (UTM/atnākšana/jauns-atgriezies,
  izlemj pre.js), `data-toff` (trigeris), `data-over` (virs „Paziņojumi joslā”). pre.js apstrādā visus `.abp[data-abp]` + `#abp-bar`, `window.abpPre()`.
- *Endpointi:* `subscribe` (POST, same-origin, tokens, honeypot, `signup_limit_ip`/h/IP (10), `signup_limit_total`/h kopā (200), ≤ 3 apstiprinājuma
  e-pasti adresei 24 h — `abp_rate` atslēga = HMAC no adreses), `confirm` / `unsubscribe` (GET rāda pogu, POST maina), `track` (300/10 min),
  `cart` (vispirms ID pārbaude — bez ID nav DB rakstīšanas; 1200/10 min). Pasts: OC3 `new Mail(engine)`, OC4 `new \Opencart\System\Library\Mail(engine, opts)`;
  `Features::email()` atraida vadības simbolus, pēdiņas, `\` (FILTER_VALIDATE_EMAIL pieņem `"a\<LF>X:1"@a.co`), galvenēm `headerText()` (bez CR/LF).
  Bez dubultās apstiprināšanas: klienta konta `newsletter` nemaina, status 2 (atteicies) neatjauno, atbilde vienāda.
- *Valsts:* sesijas piegādes/maksātāja adrese → klienta adrese → `CF-IPCountry` (tikai ja `geo_cf` UN `REMOTE_ADDR` CloudFlare diapazonos —
  `Features::CF_IPV4/CF_IPV6`, 07.11.2026; tas pats `CF-Connecting-IP`) → veikala valsts / nezināma (`geo_fallback`).
- *Kupons:* SQL `date_start < NOW()` un `date_end > NOW()` kā OC3 kodols (OC 4.1 to pašu dara PHP; DB laika josla = PHP), t.i. beigu dienā vairs nerāda.
- *Iestatījumi:* max_messages, autohide, pause_button, font, custom_css, ga4, geo_cf, geo_fallback, privacy_id, double_optin, signup_limit_ip,
  signup_limit_total, consent_wait (+ iekšējais secret). `consent_wait` → `data-consent="1"`; JS `waitConsent()`, `abp:consent` notikums,
  `abpPre.consent()` pārrēķina A/B un apmeklētāja noteikumus.

**2.3.0 „Pro Max” (shēma 7):**
- *Bezmaksas piegāde:* `type` = standard|free_shipping, `threshold` DECIMAL(15,4) (pamatvalūtā), `progress_bar`; teksts = progresa teksts ar
  `{remaining}`, `success_text` katrā valodā (obligāts, kur ir teksts). Serveris: `cart->getSubTotal()` (bez nodokļiem), `currency->format(…, session currency)`.
  Endpoint `…/cart` (GET `ids`, ≤ 20, tikai same-origin, JSON `{m:{id:{a,b,p,done}}}`, sesiju nemaina, kļūdas neizvada). JS: jQuery `ajaxComplete`
  uz `checkout/cart` / `common/cart` maršrutiem (abas versijas), `pageshow` (bfcache).
- *Kupons:* `coupon` VARCHAR(32) (A–Z0–9_-, lielie burti), čips + „Kopēt” (Clipboard API → `execCommand`), `aria-live`, skaitītājs `copies`.
- *A/B:* `ab_test`, `text_b`/`mobile_text_b`/`link_text_b`, skaitītāji `views_b`/`clicks_b`/`copies_b`. Abas versijas ir lapā (`.abp-v-a`/`.abp-v-b`),
  pre.js izvēlas `data-variant` no `localStorage abp_ab` (bez glabātuves → A). Track POST `v=a|b`. „Izmantot B” = `useB` (POST): B → A, tests izslēgts,
  B skaitītāji kļūst par galvenajiem. Labākā atzīme sarakstā, ja abām ≥ 100 skatījumu (CTR salīdzina ar reizinājumu, bez dalīšanas).
- *Grafiks:* `weekdays` ('' = katru dienu, citādi ISO 1–7 cipari), `time_start`/`time_end` HH:MM (pāri pusnaktij pieder sākuma dienai).
  Serveris (`Sanitizer::scheduleOpen`) atzīmē `data-off`, JS ik sekundi pārrēķina pēc `data-now` + `data-tz` (veikala laika josla, `date('Z')`).
- *Kategorijas/preces:* `categories`/`products` (JSON saraksti, ≤ 200). Kategorijas lapa = pēdējais `path` ID; preces lapa = preces ID vai tās kategorijas
  (`product_to_category`). Admin autocomplete: OC3 `catalog/category/autocomplete`, OC4 `catalog/category.autocomplete` (`|` < 4.0.2.0).
- *Eksports/imports:* `export` (GET, JSON lejupielāde, teksti pēc valodas koda, bez statistikas), `import` (POST fails ≤ 1 MB, `format = announcement_bar_pro`,
  ≤ 500 ziņas, katra caur `normalizeMessagePost → validateMessage → sanitizeMessage`, statuss 0; nezināmas valodas izlaistas un nosauktas).
- *Stili:* `style` ('' = moduļa iestatījums), `button_style` filled|outline|link (vecām ziņām `outline`). Krāsas stiliem rēķina veikala JS (`skinOf`):
  visi stili ≥ 4,5:1, Glass pārbaudīts pret baltu un melnu fonu; Solid ar pielāgotām krāsām netiek mainīts (kā 2.1.2). Pre.js slēpj joslu, līdz krāsas
  ir aprēķinātas (`abp-auto-pending`, CSS atklāj pēc 2 s).
- *Admin:* kopīgs `abp_admin.js` + `abp_admin.css` (OC3 `admin/view/javascript/announcement_bar_pro/`, OC4 `extension/announcement_bar_pro/admin/view/javascript/`,
  ielādē no `HTTP(S)_CATALOG`). Konfigurācija JSON `<script type="application/json" id="abp-config">`. Dzīvais priekšskatījums — `iframe sandbox="allow-scripts"`
  (bez same-origin) ar `srcdoc`, kurā ielādēti veikala īstie CSS/JS.

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

OC4 admin formas ir AJAX (JSON kļūdas → `#error-*`; 2.3.0 kontrolieris kļūdas glabā kā OC3 (`text[2]`) un `flatErrors()` tās pārvērš par `text_2` → `#error-text-2`).
`url->link()` abās versijās atgriež `&amp;` — Twig šīs saites drukā **bez** `|e` (citādi `&amp;amp;` un pazūd `user_token`). Admin `<link>`/saturs nedrīkst būt
starp `{{ column_left }}` un `<div id="content">` (OC3 CSS `#column-left + #content`). OC 4.1 `#container` ir `position:absolute` (apakšējai joslai spacer).
OC 4.1 valodas atslēga `config_language_catalog`, citur `config_language`.

## 7. Pārbaudes stāvoklis

**2.4.1 (07.–08.11.2026), tā pati vide; Turnstile ar Cloudflare oficiālajām testa atslēgām un īstu `siteverify`:**

| Pārbaude | Rezultāts |
|---|---|
| OC 3.0.5.1: 2.3.0 → 2.4.0 → 2.4.1 (Extension Installer), + Turnstile (izslēgts / ieslēgts / 2x… neizdodas / secret nekad lapā / sīkdatņu piekrišana) + atkārtotās pieteikšanās noteikums | 162/163 (pirms pusnakts; vienīgā kļūda bija nepareizs testa pieņēmums par 1x… secret, izlabots); pēdējais palaidiens 161/164 — 2 kupona datuma pārbaudes krīt starp 00:00 un 02:00 (OpenCart DB sesija UTC, testa CURDATE() vietējā laikā), 1 vienreizēja kļūda 2.3.0 → 2.4.0 posmā (abos iepriekšējos palaidienos PASS) |
| OC 4.1.0.4: jauna 2.4.1 instalācija, tās pašas pārbaudes | 148/150 (tās pašas 2 kupona datuma pārbaudes pēc pusnakts) |
| `tests/unit/schema_test.php` (+ Turnstile ar viltotu siteverify, atslēgu formāti, tokena laiks, atvasinātais atteikšanās tokens, avota pārbaudes) | 243/243 |
| `tests/demo/test.js` | 64/64 |

PHP bez CA saišķa (Windows testa vide) — curl kļūda 60; `httpPost()` tad vienreiz mēģina ar sistēmas sertifikātu krātuvi (`CURLSSLOPT_NATIVE_CA`, TLS pārbaude paliek).

**2.4.0 (07.11.2026), īsts OpenCart + MariaDB 11.4 (`abp-testenv`), Playwright Chromium, SMTP sink :2525:**

| Pārbaude | Rezultāts |
|---|---|
| OC 3.0.5.1: 2.3.0 ar 3 ziņām → 2.4.0 caur Extension Installer, migrācija 7→8, visas 15 iespējas caur admin UI + veikals 1366/390 | 133/133 |
| OC 4.1.0.4: jauna 2.4.0 instalācija, tās pašas pārbaudes | 123/123 |
| `tests/unit/schema_test.php` (shēma 8: jauna, 5→8, 6→8, 7→8, pārtraukta, atkārtota; SQL; features.php abos komplektos) + 2.4.0 pārskata labojumi (e-pasta injekcija, CloudFlare CIDR, pieteikšanās, kupons, groza endpoint) | 191/191 |
| `tests/demo/test.js` (2.2/2.3 + vairākas joslas, trigeris, pakāpes, pauze, limits, auto-hide, GA4, forma, termiņš) | 64/64 |
| `php -l` visiem PHP failiem, `build.sh` | OK |

Skripti: `tests/e2e/abp24-e2e.js` (kopija `abp-testenv/scripts/`), `abp-testenv/scripts/runall24.sh` (jauni veikali + abi testi), `serve.sh`, `smtp-sink.js`.
Ekrānuzņēmumi: `abp-testenv/shots/v24/`. **Netestēts:** OC 4.0.x, īsts PHP 7.x, MySQL 8, Firefox/Safari, komerctēmas, īsts CloudFlare,
īsts pasta serveris (tikai SMTP sink), OC4 jaunināšana 2.3.0 → 2.4.0 (atinstalēt/instalēt ceļš), attēlu pārvaldnieka dialogs (lauks aizpildīts tāpat kā to dara pārvaldnieks).

### 2.1.0

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

**2.3.0 (07.11.2026), īsts OpenCart + MariaDB 11.4 (lokāli, `abp-testenv`), Playwright Chromium:**

| Pārbaude | Rezultāts |
|---|---|
| OC 3.0.5.1: 2.2.0 ar 2 ziņām → 2.3.0 caur Extension Installer, migrācija, visas funkcijas caur admin UI + veikals 1366/390 | 72/72 |
| OC 4.1.0.4: jauna 2.3.0 instalācija, tās pašas pārbaudes | 63/63 |
| `tests/unit/schema_test.php` (shēma 7: jauna, 5→7, 6→7, pārtraukta, atkārtota; SQL whitelist; sanitizatora palīgi) | 95/95 |
| `tests/demo/test.js` (stili, kontrasts, 320–1920 px, bezmaksas piegāde, kupons, A/B, bultiņas + 2.2.0) | 50/50 |
| `php -l` visiem 23 PHP failiem, `build.sh` | OK |

Skripti: `tests/e2e/abp23-e2e.js` + `run23.sh` (vajag `abp-testenv`: `scripts/mkshop.sh <ver> <port>`, MariaDB :3307, PHP serveris).
Ekrānuzņēmumi: `abp-testenv/shots/v23/`. **Netestēts:** OC 4.0.x ar 2.3.0, īsts PHP 7.x, MySQL 8 (tikai MariaDB), Firefox/Safari, komerctēmas,
3dp.lv tēma „meistars”, OC4 jaunināšana 2.2.0 → 2.3.0 (atinstalēt/instalēt ceļš).

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

1. ~~Autora dati~~ — izdarīts 2.3.0 (`3DP SIA`, `mailto:extension@3dp.lv`).
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
