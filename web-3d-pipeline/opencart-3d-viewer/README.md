# OpenCart 3D Product Viewer (OpenCart 3.0.x)

Pievieno produktam **3D modeli (.glb / .gltf)** administrācijā un rāda to produkta lapā ar Google
[`<model-viewer>`](https://modelviewer.dev/): grozīšana, tuvināšana, auto-rotācija, ēnas un **AR** (Android Scene Viewer, iOS Quick Look).

- **Bez kodola un tēmas failu labošanas.** Tiek izmantoti tikai OpenCart notikumi (events), OCMOD nav vajadzīgs.
- **Bez ārējiem CDN.** model-viewer un dekoderi ir iekļauti pakotnē (CDN var ieslēgt iestatījumos).
- **Sava tabula `oc_product_3d`.** Tabula `oc_product` netiek mainīta.
- **Valodas:** EN / LV / RU.

Pakotne: `dist/product_3d_viewer_v1.0.0.ocmod.zip` (~0,7 MB).

## Kā tas strādā

| Kur | Notikums (event) | Ko dara |
|---|---|---|
| Admin produkta forma | `admin/view/catalog/product_form/before` | pievieno cilni **3D modelis** (augšupielāde, izvēle, priekšskatījums) |
| Produkta saglabāšana | `admin/model/catalog/product/addProduct/after`, `editProduct/after` | saglabā `product_3d[...]` laukus tabulā `oc_product_3d` |
| Produkta dzēšana | `admin/model/catalog/product/deleteProduct/after` | dzēš 3D ierakstu (failus atstāj) |
| Admin izvēlne | `admin/view/common/column_left/before` | Catalog → 3D Product Viewer |
| Veikala produkta lapa | `catalog/view/product/product/before` | pievieno skatītāju; `product_3d.js` to novieto pie galerijas pēc CSS selektora |

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

- `{{ model_3d }}` — modeļa URL (atbilst `$product_info['model_3d']`);
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
- **MIME tipi:** Apache tos iestata ar `image/catalog/3d/.htaccess`, ko izveido modulis. **Nginx** gadījumā pievieno:
  ```nginx
  types { model/gltf-binary glb; model/gltf+json gltf; model/vnd.usdz+zip usdz; }
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
- **Twig izvade** visur tiek escape (`|e`).

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
    --zip dist/product_3d_viewer_v1.0.0.ocmod.zip --glb ../models/chair.web.glb
```

E2E tests darbojas uz **testa** veikala: tas instalē moduli un maina produkta 42 3D laukus. Nepalaid to dzīvā veikalā.

## Zināmie ierobežojumi

- **Produkta kopēšana** (*Copy*) nekopē 3D modeli. OC3 `copyProduct` neatgriež jauno ID, tāpēc modelis jāpiesaista ar roku.
- **Citas tēmas:** ja galerijas selektors nav atrasts, skatītājs tiek ievietots pirms produkta virsraksta. Precīzam novietojumam iestati selektoru vai izmanto **Manuāli** + `{{ product_3d_html }}`.
- **Viens modelis uz produktu.** Opciju/variantu modeļi netiek atbalstīti; krāsu varianti vienā `.glb` gan strādā (`KHR_materials_variants`).
