# OpenCart 3D Product Viewer (OpenCart 3.0.x)

Pievieno produktam **3D modeli (.glb / .gltf)** administrācijā un rāda to produkta lapā ar Google
[`<model-viewer>`](https://modelviewer.dev/): grozīšana, tuvināšana, auto-rotācija, ēnas un **AR** (Android Scene Viewer, iOS Quick Look).

- **Bez kodola un tēmas failu labošanas.** Tiek izmantoti tikai OpenCart notikumi (events), OCMOD nav vajadzīgs.
- **Bez ārējiem CDN.** model-viewer un dekoderi ir iekļauti pakotnē (CDN var ieslēgt iestatījumos).
- **Sava tabula `oc_product_3d`.** Tabula `oc_product` netiek mainīta.
- **Valodas:** EN / LV / RU.

Pakotne: `dist/product_3d_viewer_v1.1.0.ocmod.zip` (~0,7 MB).

## Kā tas strādā

| Kur | Notikums (event) | Ko dara |
|---|---|---|
| Admin produkta forma | `admin/view/catalog/product_form/before` | pievieno cilni **3D modelis** (augšupielāde, izvēle, priekšskatījums) |
| Produkta saglabāšana | `admin/model/catalog/product/addProduct/after`, `editProduct/after` | saglabā `product_3d[...]` laukus tabulā `oc_product_3d` |
| Produkta dzēšana | `admin/model/catalog/product/deleteProduct/after` | dzēš 3D ierakstu (failus atstāj) |
| Produkta kopēšana | `admin/model/catalog/product/copyProduct/after` | kopē 3D ierakstu uz jauno produktu |
| Admin izvēlne | `admin/view/common/column_left/before` | Catalog → 3D Product Viewer |
| Veikala produkta lapa | `catalog/view/product/product/before` | mainīgie tēmām (`model_3d`, `product_3d`, `product_3d_html`) |
| Veikala produkta lapa | `catalog/view/product/product/after` | ievieto skatītāju un skriptus lapā pirms `</body>` |

### Darbojas ar jebkuru tēmu

Tēmas failus nav jāmaina. `product_3d.js` atrod galeriju šādā secībā:
1. CSS selektors no iestatījumiem, ja tas ir norādīts. **Tukšs selektors nozīmē automātisku režīmu (ieteicams).**
2. **Automātiski:**
   - Lapā tiek atrasti šī produkta attēli pēc faila nosaukuma: `src`, `data-src` (lazy-load), `srcset`, `href`, `data-zoom-image`, `background-image`. Der jebkurš izmērs, arī `.webp`.
   - No lielākā attēla skripts kāpj uz augšu līdz blokam, kurā nav produkta nosaukuma, cenas, pirkšanas pogas un garu tekstu.
3. Zināmie selektori (`.thumbnails`, `.product-gallery` u.c.).
4. **Produktam bez attēliem:** kolonna blakus produkta informācijai.

**Izkārtojums:**
- Ja galerija ir režģa kolonna, skatītājs tiek ievietots tās iekšpusē, lai izkārtojums nesalūztu.
- Telefonā, ja kolonna ir šaurāka par ~300 px, skatītājs tiek parādīts pilnā platumā.

**Ja skatītājs neielādējas,** produkta attēli atkal tiek parādīti.

**Pārbaudīts** uz default tēmas un 4 krasi atšķirīgām testa tēmām (`tests/themes/`):
- flex + lazy-load + slaideris;
- CSS grid + `background-image` + bez `h1`;
- tēma bez `{{ footer }}`;
- vienas kolonnas tēma.

Katrā tēmā pārbaudīti 3 novietojumi, produkti ar un bez attēliem, dators un telefons. Journal un citas maksas tēmas nav testētas.

### Tabula `oc_product_3d`

```sql
CREATE TABLE IF NOT EXISTS `oc_product_3d` (
  `product_id`    INT(11)      NOT NULL,          -- = oc_product.product_id
  `model_3d`      VARCHAR(255) NOT NULL DEFAULT '', -- ceļš relatīvi pret image/, piem. catalog/3d/kresls.glb
  `ios_3d`        VARCHAR(255) NOT NULL DEFAULT '', -- .usdz (iOS AR), nav obligāts
  `poster_3d`     VARCHAR(255) NOT NULL DEFAULT '', -- poster attēls, nav obligāts
  `placement`     VARCHAR(16)  NOT NULL DEFAULT '', -- '' = no iestatījumiem | replace | before | after | manual | disabled
  `camera_orbit`  VARCHAR(64)  NOT NULL DEFAULT '',
  `auto_rotate`   VARCHAR(1)   NOT NULL DEFAULT '', -- '' = no iestatījumiem | 1 | 0
  `date_modified` DATETIME     NOT NULL,
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Tabulu izveido moduļa **Install**. Ar roku nekas nav jāizpilda; `oc_` prefikss tiek ņemts no `DB_PREFIX`.

### Mainīgie tēmām

Produkta lapas Twig skatā (`product/product.twig`) ir pieejami:

- `{{ model_3d }}` — modeļa URL (ceļš no veikala saknes, piem. `/image/catalog/3d/kresls.glb`). `$product_info` netiek mainīts;
- `{{ product_3d }}` — masīvs (`src`, `ios_src`, `poster`, `placement` …);
- `{{ product_3d_html }}` — gatavs skatītāja HTML (izmanto, ja novietojums ir **Manuāli**).

Skatītāja izskatu var pārrakstīt savā tēmā: `catalog/view/theme/<tēma>/template/extension/module/product_3d.twig`.

## Instalēšana

> Pirms instalēšanas dzīvā veikalā **izveido datubāzes un failu rezerves kopiju**.

1. **Extensions → Installer → Upload**: izvēlies `product_3d_viewer_v1.0.0.ocmod.zip` un sagaidi “Success”.
   OCMOD modifikācijas nav, tāpēc *Modifications → Refresh* nav vajadzīgs.
2. **Extensions → Extensions → Modules**: atrodi **3D Product Viewer** un spied zaļo **+** (Install).
   Tiek izveidota tabula, notikumi, noklusējuma iestatījumi un mape `image/catalog/3d/`.
3. Spied **Edit** (zīmulis) → pārbaudi iestatījumus → **Save**.
   - **Galerijas CSS selektors:** default tēmai `.thumbnails`. Citām tēmām (Journal u.c.) elementu atrodi ar pārlūka inspektoru.
   - **Novietojums:** galerijas vietā / virs / zem / manuāli.
4. **Catalog → Products → Edit** → cilne **3D modelis** → **Augšupielādēt** `.glb` → **Save**.
5. Atver produktu veikalā.

Ja Installer nav pieejams (FTP), nokopē `upload/` saturu veikala saknē un turpini no 2. soļa.

### Servera prasības

- **OpenCart:** 3.0.x, PHP 7.3+ (pārbaudīts ar PHP 8.3), MySQL/MariaDB ar InnoDB.
- **Augšupielādes limits:** `upload_max_filesize` un `post_max_size` jābūt ≥ modeļa izmēram. Faktiskais limits redzams iestatījumu lapā.
- **MIME tipi:** Apache tos iestata ar `image/catalog/3d/.htaccess`, ko izveido modulis (Apache 2.2 un 2.4). **Nginx** gadījumā pievieno servera blokā
  (atsevišķā `location`, lai nepārrakstītu pārējos MIME tipus):
  ```nginx
  location ~* \.(glb|gltf|usdz)$ {
      types { model/gltf-binary glb; model/gltf+json gltf; model/vnd.usdz+zip usdz; }
      add_header X-Content-Type-Options nosniff;
  }
  ```
- **AR:** prasa HTTPS.

## Drošība

- Augšupielāde ir pieejama tikai lietotājiem ar `modify` tiesībām uz `extension/module/product_3d`. Pieprasījumos tiek pārbaudīts `user_token`.
- **Faila saturs tiek pārbaudīts**, ne tikai paplašinājums:
  - `.glb`: `glTF` galvene, versija 2, garums;
  - `.gltf`: JSON glTF 2.0, bez ārējiem failiem;
  - `.usdz`: ZIP galvene;
  - attēli: `getimagesize`.
  
  Faila nosaukums tiek attīrīts, un esošie faili netiek pārrakstīti.
- **Saglabātie ceļi** drīkst norādīt tikai uz esošiem failiem `image/` mapē ar atļautu paplašinājumu (`..` un ceļi ārpus `image/` tiek noraidīti).
- **Mapē `image/catalog/3d/`** `.htaccess` aizliedz izpildīt skriptus (Apache).
- **Twig izvade:** visas no DB un lietotāja nākošās vērtības tiek escape (`|e`). Valodas teksti un servera ceļi tiek izvadīti tieši, jo tie nāk no moduļa failiem.
- **Uninstall/Install** pa tiešo maršrutu prasa `modify` tiesības uz Extensions > Modules.
- **Galerijas aizstāšana ir droša:** ja model-viewer skripts neielādējas (CSP, bloķētājs, sens pārlūks) vai modelis nav nolasāms, produkta attēli atkal tiek parādīti.
- **Domēnam jāsakrīt ar `config_url`** (www/bez-www, http/https). OpenCart lapā ir `<base href>`, tāpēc citā hostā CORS bloķē gan moduli, gan paša OpenCart fontus un AJAX. Modulis tad atstāj redzamus parastos attēlus, bet risinājums ir 301 pāradresācija uz vienu domēnu.
- **CDN režīmā** skripti nāk no `ajax.googleapis.com` un `cdn.jsdelivr.net` bez SRI. Drošākai lietošanai paliec pie noklusējuma (lokālie faili).

## Atinstalēšana

**Extensions → Modules → 3D Product Viewer → Uninstall** noņem notikumus un iestatījumus.
- **Tabula `oc_product_3d` paliek.** Pēc atkārtotas instalēšanas modeļi atkal ir piesaistīti produktiem.
- **Tabula tiek dzēsta** tikai tad, ja iestatījumos ieslēgts “Atinstalējot dzēst 3D saites”.
- **Augšupielādētie faili** `image/` mapē netiek dzēsti nekad.

## Ieteikumi modeļiem

Optimizē ar `../optimize.py`:

```bash
python optimize.py modelis.fbx -o out/ --max-size 5MB --poster --usdz
```

Pēc tam produkta cilnē augšupielādē `.web.glb`, `.usdz` un `.poster.webp`.

## Izstrāde un testi

```bash
python build.py                                   # php -l + dist/product_3d_viewer_vX.Y.Z.ocmod.zip
python tests/e2e_opencart.py --base http://127.0.0.1:8080/ --user admin --password ... \
    --zip dist/product_3d_viewer_v1.0.1.ocmod.zip --glb ../models/chair.web.glb
```

Regresijas testi (neatkarīgās pārbaudes atradumi):

```bash
python tests/e2e_regression.py --zip dist/product_3d_viewer_v1.1.0.ocmod.zip --glb ../models/chair.web.glb \
    --site-root /ceļš/uz/testa/veikalu --mysql "mysql -uroot oc_test"
```

Tēmu tests (vispirms nokopē `tests/themes/*` uz testa veikala `catalog/view/theme/`):

```bash
python tests/e2e_themes.py --themes default,tA_flex,tB_grid,tC_nofooter,tD_stacked --mysql "mysql -uroot oc_test"
```

E2E testi darbojas uz **testa** veikala: tas instalē moduli un maina produkta 42 3D laukus. Nepalaid to dzīvā veikalā.

## Zināmie ierobežojumi

- **Produkta kopēšana** piešķir 3D ierakstu produktam ar lielāko `product_id` (OC3 `copyProduct` jauno ID neatgriež). Ja vienlaikus produktus pievieno vairāki administratori, pārbaudi kopiju.
- **Nederīgs ceļš** (fails uz brīdi nav pieejams, kļūdains URL) saglabājot produktu **netiek dzēsts**: paliek iepriekšējā vērtība, un sistēmas žurnālā (System > Maintenance > Error Logs) parādās `[product_3d]` ieraksts.
- **Tēmas, kas produkta lapu veido tikai ar JavaScript** (piem. attēlus ielādē AJAX pēc `load`) var prasīt CSS selektoru iestatījumos.
- **Quick view** (produkts uznirstošā logā) netiek atbalstīts, skatītājs ir tikai pilnajā produkta lapā.
- **Viens modelis uz produktu.** Opciju/variantu modeļi netiek atbalstīti; krāsu varianti vienā `.glb` gan strādā (`KHR_materials_variants`).
