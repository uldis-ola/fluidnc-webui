# Announcement Bar Pro Embed — uzstādīšana

Vajag divas lietas: **failus** (`abp-embed.min.js` + `abp-embed.min.css` no pakas mapes `abp-embed/`) un
**konfigurāciju**, ko izveido Konfiguratorā (`configurator/index.html` — atver pārlūkā ar dubultklikšķi; strādā bez
interneta un neko nekur nesūta).

Trīs veidi — izvēlies vienu:

| | Ko ielīmē | Vajag failu mitināšanu | Stingrs CSP |
|---|---|---|---|
| **A** (ieteicams) | konfigurācija `<script type="application/json">` + viens `<script src>` | 2 faili | jā |
| **B** | viens `<script src … data-config="…/abp-config.json">` | 2 faili + JSON | jā (`connect-src`) |
| **C** viss vienā | stili + konfigurācija + skripts vienā blokā (~70 KB) | nē | nē |

Pēc izmaiņām Konfiguratorā ielīmē jauno kodu (A / C) vai augšupielādē jauno JSON (B).

## 1. Failu mitināšana (A un B)
Augšupielādē `abp-embed.min.js` un `abp-embed.min.css` **vienā mapē** (skripts ielādē blakus esošo `.css` ar tādu
pašu nosaukumu). Der jebkura statiska mitināšana: savs serveris, platformas failu krātuve vai savs CDN. Ieraksti mapes
adresi Konfiguratorā ("Install on your site") — kods ir gatavs kopēšanai.

## 2. Platformas

### Shopify
1. *Online Store → Themes → … → Edit code → Assets → Add a new asset*: augšupielādē abus failus.
2. Atver `layout/theme.liquid`, ielīmē kodu **A** tieši pirms `</body>` un skripta rindu aizstāj ar
   `<script src="{{ 'abp-embed.min.js' | asset_url }}" defer></script>` (CSS tiek atrasts blakus automātiski).
3. Bezmaksas piegādes progress (pēc izvēles): `examples/cart-adapter-shopify.js` saturu ielīmē `<script>…</script>`
   tajā pašā vietā. Tas lasa Shopify `/cart.js` un atjauno joslu pēc AJAX groza izmaiņām. Ja pārdod vairākās valūtās,
   Konfiguratorā norādi summu katrai valūtai.

### Wix
Wix nevar mitināt `.js` failus. Izmanto **C (viss vienā)**: *Settings → Custom Code → + Add Custom Code* → ielīmē,
"All pages", vieta **Body – end**. (Custom Code vajag Wix plānu ar pieslēgtu domēnu.) Ja Wix neļauj tik garu kodu,
faili jāmitina citur un jāizmanto A.

### Squarespace
1. Failus augšupielādē caur saiti teksta blokā → *File*; katrs fails saņem `/s/…` adresi. Ja `.css` nav ar to pašu
   nosaukumu tajā pašā mapē, skriptam pievieno `data-css="<css adrese>"`.
2. *Settings → Advanced → Code Injection → Footer*: ielīmē **A** (vajag Business plānu vai augstāku).
   Bez mitināšanas: **C** laukā Footer.

### Webflow
*Site settings → Custom code → Footer code*: ielīmē **A** (faili uz sava servera / CDN) vai **C**. Publicē.

### Tilda
Lapas beigās (vai kājenē) pievieno bloku **T123 (HTML)** un ielīmē **C** vai A ar saviem failiem. Publicē visas lapas.

### Vienkāršs HTML / jebkura CMS
Ielīmē **A** pirms `</body>` katrā lapā (vai kopīgajā kājenes veidnē). Ceļi var būt relatīvi.

## 3. Content-Security-Policy
Skripts neizmanto `eval`, iekļautus notikumu apstrādātājus un `style` atribūtus (stili caur CSSOM). Ar A vai B pietiek:
```
script-src 'self'; style-src 'self'; connect-src 'self'   (connect-src tikai B)
```
- A konfigurācija ir `type="application/json"` — tie ir dati, tie netiek izpildīti, `'unsafe-inline'` nevajag.
- **Savs CSS**: tiek pievienots kā konstruēta stila lapa (mūsdienu pārlūkos `'unsafe-inline'` nevajag). Ja politika
  izmanto nonce, liec to uz skripta (`<script nonce="…" src=…>`) vai `data-nonce="…"`.
- C (viss vienā) ir iekļauts kods un prasa `'unsafe-inline'` — ar stingru politiku to neizmanto.

## 4. Skripta atribūti
| Atribūts | Nozīme |
|---|---|
| `data-config="#abp-config"` | iekļautā JSON taga id (noklusējums) |
| `data-config="https://…/abp-config.json"` | JSON fails (http/https vai vietnes ceļš); ielādē bez sīkdatnēm |
| `data-css="none"` | stila lapu neielādēt (to pievieno pats) |
| `data-css="https://…/abp.css"` | stila lapa citā adresē |
| `data-nonce="…"` | CSP nonce stila lapai un savam CSS |

## 5. Bezmaksas piegādes progress (groza adapteris)
Joslai nav servera, un tā pati grozu nelasa. Bezmaksas piegādes paziņojumi ir paslēpti, līdz lapa izsauc:
```js
ABP.setCart({ subtotal: 42.50, currency: 'EUR', count: 3 });
```
- Shopify: gatavs `examples/cart-adapter-shopify.js`.
- Citas platformas: `examples/cart-adapter-generic.js` rāda izsaukumu; jāzina, kur platforma dod groza summu. Ja to
  nevar, bezmaksas piegādes paziņojumus neizmanto.
- Summas starp valūtām netiek konvertētas; grozs valūtā bez summas paziņojumu paslēpj.

## 6. Sīkdatņu piekrišana
Ar "Wait for cookie consent" nekas netiek glabāts vai lasīts (apmeklētāja atmiņa, kampaņa, GA4), līdz piekrišanas rīks
izpilda:
```js
window.abpConsent = true; document.dispatchEvent(new Event('abp:consent'));
```
Do Not Track un Global Privacy Control vienmēr tiek ievēroti. Aizvēršana tiek atcerēta localStorage (funkcionāls
iestatījums); ar "Remember closed" = 0 — tikai līdz pārlūka sesijas beigām.

## 7. Pārbaude
Atver vietni: josla parādās sekundes laikā. Ja nē — pārlūka konsolē rindas sākas ar "Announcement Bar Pro:"
(nav konfigurācijas, bojāts JSON, nav CSS). Atbalsts: **extension@3dp.lv**.
