# Announcement Bar Pro — OpenCart 4.0.x / 4.1.x

Paziņojumu josla veikala augšā vai apakšā: akcijas, piegādes nosacījumi, svarīgas ziņas.
*An announcement bar for the top or bottom of every store page.*

- **Versija / Version:** 2.4.2 (same features as the OpenCart 3 package)
- **Saderība / Compatibility:** OpenCart 4.0.0.0 – 4.1.0.4, PHP 8.0+. Fresh install + full end-to-end test (2026-11-08) on 4.0.0.0, 4.0.1.1, 4.0.2.3 and 4.1.0.0 with PHP 8.0 and 8.1, and on 4.1.0.4 with PHP 8.1 and 8.3; MariaDB 11.4. Matrix: `announcement-bar-pro-universal/COMPATIBILITY.md`.
  - OpenCart's own PHP minimum applies: 4.1.0.4 needs PHP 8.1+ (4.1.0.0: 8.0.2+).
  - OpenCart 4.0.0.x–4.0.1.x: use PHP 8.0 or 8.1. On PHP 8.3 the OpenCart 4.0.0.0 core prints deprecation notices and its installer stops on them (OpenCart, not the module; 8.2 not tried).
  - OpenCart 4.0.0.x–4.0.1.x install the same way; their *Extensions → Installer* and module list look slightly different.
- **Valodas / Languages:** English, Latviešu, Русский
- **Licence / License:** GPL-3.0 (see `LICENSE.txt`)
- OpenCart 3.0.x: use the separate package in `announcement-bar-pro`.

> **2.4.1:** optional Cloudflare Turnstile anti-bot check of the e-mail sign-up form (site key + secret key in Settings → E-mail sign-ups; the secret is never shown; when on, Cloudflare processes the visitor's IP address and browser data on pages with a sign-up form, and with "Wait for cookie consent" its script loads only after consent), an unsubscribed address can no longer be re-subscribed by someone else (only its owner, through a new confirmation e-mail), and a cart-refresh fix in the storefront script, which is now byte-identical to the WordPress edition.
>
> **2.4.0:** several bars and inline boxes (cart page, under the price, mini-cart) with priority, country / cart / campaign / visitor targeting, tiered goals, OpenCart coupons, triggers, recurring countdown, e-mail sign-up (double opt-in), templates, GA4 events. Features, privacy notes and upgrade steps: README of the universal package.

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
- OC 4.0.x noklusētajā tēmā tukšais paziņojumu konteiners `#alert` pārklāj augšējo labo stūri. Modulis tam izslēdz klikšķus, kamēr konteiners ir tukšs (`#alert:empty{pointer-events:none}`), lai aizvēršanas poga strādātu.
- Ja paziņojumam nav teksta apmeklētāja valodā, tiek rādīts veikala noklusētās valodas teksts. Paziņojums, kam teksts ir tikai citā (ne noklusētajā) valodā, citām valodām netiek rādīts.
- Valodām bez moduļa tulkojuma (piem., de-de) tiek rādīti angļu teksti.
- Aizvēršana tiek atcerēta katram paziņojumam; atkārtota saglabāšana bez satura izmaiņām to neatceļ. Atpakaļskaitīšana izmanto servera laiku.
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
- On OC 4.0.x the default theme's empty, fixed `#alert` toast container covers the top-right corner (4.1 centres it; the rule is harmless there). The module disables pointer events on it while it is empty, so the close button can be clicked.
- A message with no text in the visitor's language shows the store's default-language text; a message written only in a non-default language is shown only in that language.
- Languages without a translation of this module (e.g. de-de) get the English labels.
- After *Uninstall* the module page redirects to the extension list instead of recreating data.
- Closing is remembered per message (a re-save without content changes keeps it closed). Countdowns use the server clock.
- Only the first `{countdown}` in a message is replaced; further ones are removed.
- Sticky top also works on themes with an absolutely positioned page wrapper (OpenCart 4.1): the bar is then pinned with `position:fixed` and a spacer.
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
