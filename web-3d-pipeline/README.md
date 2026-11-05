# web-3d-pipeline

Automatizēta 3D produktu modeļu sagatavošana e-komercijai un mājaslapām:

- **`optimize.py`** — `.fbx / .gltf / .glb / .obj / .stl / .ply` → viegls `.glb`.
  Veic decimāciju, pārveido tekstūras uz WebP/JPEG/KTX2 un saspiež ar Draco/Meshopt. Labo mērogu, asi un novietojumu uz grīdas,
  validē rezultātu, ģenerē poster attēlu un USDZ priekš iOS, kā arī manifestu, embed kodu, katalogu un CSV atskaiti.
- **`index.html`** — produkta lapa ar Google [`<model-viewer>`](https://modelviewer.dev/):
  - AR, ēnas, auto-rotācija, grozāmā galda kontroles un pilnekrāns;
  - krāsu varianti, anotācijas un to redaktors;
  - modeļu katalogs, LV/EN, analītikas notikumi;
  - var darboties **bez ārējiem CDN** (no mapes `vendor/`).

```
web-3d-pipeline/
├── optimize.py            # CLI
├── index.html             # produkta lapa / testu stends
├── requirements.txt       # pamata Python atkarības
├── requirements-extra.txt # poster, testi
├── models/                # šeit nonāk optimizētie modeļi (git ignorē)
├── vendor/                # model-viewer + dekoderi (`--fetch-vendor`, git ignorē)
└── tests/                 # pytest: plūsma + pārlūka e2e
```

---

## 1. Instalācija

### Python (obligāti, 3.9+)

```bash
cd web-3d-pipeline
python -m venv .venv
# Windows: .venv\Scripts\activate        Linux/macOS: source .venv/bin/activate
pip install -r requirements.txt
```

### Ieteicams: gltf-transform (Node.js 18+)

Tas dod labāko kvalitāti, jo decimācija saglabā UV un tekstūras, un nodrošina Draco/Meshopt un Khronos validatoru.

```bash
npm install -g @gltf-transform/cli
```

Bez tā strādā Python dzinējs (trimesh + Pillow), bet tas **nedecimē tīklus ar tekstūrām** un neprot Draco/Meshopt.

### Pēc vajadzības

| Iespēja | Ko instalēt |
|---|---|
| **FBX** ievade | viens no: [FBX2glTF](https://github.com/godotengine/FBX2glTF/releases) (`PATH`), [Blender](https://www.blender.org/download/) (`blender` `PATH`) vai `pip install bpy` |
| **USDZ** (iOS AR, `--usdz`) | Blender (`PATH`) vai `pip install bpy` |
| **Poster** (`--poster`) | `pip install playwright && playwright install chromium` |
| **KTX2** tekstūras | [KTX-Software](https://github.com/KhronosGroup/KTX-Software/releases) (`ktx` `PATH`) |
| **gltfpack** dzinējs | [meshoptimizer releases](https://github.com/zeux/meshoptimizer/releases) |
| Testi | `pip install -r requirements-extra.txt` |

`bpy` ir Blender kā Python modulis (~400 MB). Tas der tikai noteiktai Python versijai, ko var pārbaudīt [pypi.org/project/bpy](https://pypi.org/project/bpy/).

---

## 2. Lietošana

```bash
# viens fails -> modelis.web.glb blakus oriģinālam
python optimize.py modelis.fbx

# ieteicamā e-komercijas komanda: uz models/, ar poster un USDZ
python optimize.py krēsls.fbx -o models/ --poster --usdz

# visa mape (arī apakšmapes), katrs fails ≤ 3 MB, + report.csv
python optimize.py ienākošie/ -r -o models/ --max-size 3MB --poster

# CAD / STL modelis milimetros ar Z asi uz augšu
python optimize.py detaļa.stl -o models/ --units mm --up z

# lapai bez ārējiem CDN (vienreiz)
python optimize.py --fetch-vendor
```

### Galvenie parametri

| Parametrs | Noklusējums | Nozīme |
|---|---|---|
| `-o, --output` | blakus oriģinālam | `.glb` fails vai mape (vairākiem failiem — mape) |
| `-r, --recursive` | – | mapēs meklē arī apakšmapēs |
| `--engine` | `auto` | `gltf-transform` → `gltfpack` → `python` |
| `--ratio` | `0.5` | paturamā ģeometrijas daļa (1 = bez decimācijas) |
| `--texture-size` | `2048` | maks. tekstūras izmērs px |
| `--texture-format` | `webp` | `webp`, `jpeg`, `ktx2`, `keep` |
| `--compress` | `draco` | `draco`, `meshopt`, `quantize`, `false` |
| `--max-size` | – | piem. `3MB`: pakāpeniski samazina tekstūras un ģeometriju, līdz fails iekļaujas |
| `--strict-size` | – | kļūda (exit 2), ja `--max-size` netiek sasniegts |
| `--units` / `--scale` | `m` | avota mērvienības (`cm`, `mm`, `in`, `ft`) vai patvaļīgs koeficients |
| `--up` | `y` | `z` CAD/STL modeļiem |
| `--center` | `bottom` | centrē un noliek uz grīdas (svarīgi AR) |
| `--poster` | – | `<nosaukums>.poster.webp`, renderēts ar model-viewer |
| `--poster-size`, `--poster-orbit`, `--poster-bg` | `1200x1200`, `30deg 75deg auto`, `#ffffff` | poster iestatījumi (`--poster-bg transparent`) |
| `--usdz` | – | `<nosaukums>.usdz` iOS Quick Look |
| `--base-url` | – | URL prefikss embed kodā, piem. `/image/catalog/3d/` |
| `--no-validate`, `--no-manifest`, `--keep-temp` | – | izlaist validāciju / manifestu / saglabāt starpfailus |

Pilns saraksts: `python optimize.py --help`.

### Izvades faili (mapē `-o`)

| Fails | Saturs |
|---|---|
| `X.web.glb` | optimizētais modelis |
| `X.json` | **manifests** lapai: nosaukums, cena, apraksts, poster, iosSrc, izmēri, varianti, anotācijas |
| `X.embed.html` | gatavs `<model-viewer>` kods ielīmēšanai veikalā |
| `X.poster.webp`, `X.usdz` | ja izmantots `--poster` / `--usdz` |
| `catalog.json` | visu modeļu saraksts (lapas izvēlnei) |
| `report.csv` | atskaite (vairākiem failiem) |

**Manifestu var rediģēt ar roku.** Lauki `title`, `price`, `description`, `hotspots`, `orbit` un `notes` atkārtotā palaišanā netiek pārrakstīti.
Tekstus var dot vienā valodā vai `{"lv": "...", "en": "..."}`:

```json
{
  "title": { "lv": "Krēsls “Rīga”", "en": "Chair “Riga”" },
  "price": 129,
  "description": { "lv": "Ozolkoka sēdeklis, tērauda kājas.", "en": "Oak seat, steel legs." },
  "orbit": "30deg 75deg auto",
  "hotspots": [
    { "position": "0m 0.47m 0.1m", "normal": "0 1 0", "label": { "lv": "Ozolkoks, 4 cm", "en": "Solid oak, 4 cm" } }
  ]
}
```

Anotāciju koordinātas ērtāk iegūt lapā: **Testēšana → Anotāciju redaktors**. Klikšķini uz modeļa, nokopē JSON un ielīmē to manifestā.

**Krāsu varianti:** ja `.glb` satur `KHR_materials_variants` (piem. eksportēts no Blender ar variantiem), lapa pati parāda variantu pogas.

### Exit kodi

`0` — viss izdevās · `1` — nav atrasts ievades fails · `2` — kāds fails neizdevās (sīkāk atskaitē).

---

## 3. Lokāla testēšana

`index.html` **nestrādā ar dubultklikšķi** (`file://`), jo pārlūks bloķē ielādi. Vajag lokālu serveri:

```bash
python optimize.py manimodeli/ -o models/ --poster      # 1) modeļi -> models/
python optimize.py --fetch-vendor                      # 2) (neobligāti) JS lokāli
python -m http.server 8000                             # 3) serveris
```

Atver pārlūkā:

- `http://localhost:8000/` — pirmais modelis no `models/catalog.json`, augšā izvēlne;
- `http://localhost:8000/?model=models/krēsls.web.glb` — konkrēts modelis;
- `http://localhost:8000/?lang=en` — angliski.

Lapā var arī ievilkt `.glb` failu skatītājā.

**Ko pārbaudīt:**

1. Pareizi izmēri (cm) zem pogām. Ja tie ir 100× par lieliem, lieto `--units cm` vai `--units mm`.
2. Varianti, anotācijas, slēdži, grozāmā galda pogas, valoda.
3. DevTools → Network: modeļa izmērs (mērķis ≤ 2–5 MB). `<html data-mv-source="local">` nozīmē, ka viss nāk no paša servera.

**AR telefonā** vajag **HTTPS** (izņēmums ir `localhost`):

- izvieto uz hostinga vai izmanto tuneli: `cloudflared tunnel --url http://localhost:8000`;
- Android: Scene Viewer (Google Play Services for AR); iOS: Quick Look. Labāko rezultātu iOS dod `--usdz`;
- pārbaudi, vai modelis AR ir dabiskā izmērā un stāv uz grīdas.

### Automātiskie testi

```bash
pip install -r requirements-extra.txt
playwright install chromium
python -m pytest tests -v
```

- **`tests/test_pipeline.py`** — dzinēji, tekstūras, mērogs/ass, varianti, `--max-size`, FBX, USDZ, poster, partijas režīms, manifesta saglabāšana un kļūdu gadījumi.
- **`tests/test_viewer.py`** — lapa īstā pārlūkā: ielāde bez ārējiem pieprasījumiem, manifests, varianti, anotācijas, katalogs, valoda, kontroles un mobilais izkārtojums.
- **Izlaistie testi:** ja nav instalēts gltf-transform, Blender/bpy vai Playwright, attiecīgie testi tiek izlaisti.
- **Testa modeļi** tiek ģenerēti automātiski (`tests/make_fixtures.py`).

Ja Playwright pārlūks ir citur: `WEB3D_CHROMIUM=/ceļš/uz/chrome python -m pytest tests`.

---

## 4. Integrācija veikalā

Optimizētos failus (`.web.glb`, `.poster.webp`, `.usdz`) augšupielādē serverī, piem. `/image/catalog/3d/`.
Izmanto `--base-url /image/catalog/3d/`, lai `X.embed.html` saturētu pareizos ceļus.

**OpenCart (3.x / 4.x)**

1. Augšupielādē failus `image/catalog/3d/`, izmantojot FTP vai Image Manager.
2. Divus `<script>` blokus no `X.embed.html` pievieno **vienreiz** tēmas `header.twig` (pirms `</head>`).
3. `<model-viewer>…</model-viewer>` daļu ielīmē produkta aprakstā, pārslēdzot redaktoru uz koda (Source) režīmu.
   - Ja redaktors tagu izdzēš, ievieto to `product.twig` (piem. ar savu produkta lauku vai moduli).
4. `.glb`/`.usdz` MIME tipi (Apache `.htaccess`):
   ```apache
   AddType model/gltf-binary .glb
   AddType model/vnd.usdz+zip .usdz
   ```

**WooCommerce / WordPress:** skriptus pievieno ar “Insert Headers and Footers” spraudni vai `functions.php`. `<model-viewer>` ievieto produkta aprakstā, izmantojot Custom HTML bloku. WordPress pēc noklusējuma neļauj augšupielādēt `.glb`, tāpēc vajag atļaut MIME tipu.

**Bez ārējiem CDN (GDPR / CSP):** nokopē `vendor/` uz serveri un embed kodā nomaini skriptu adreses uz `/vendor/model-viewer.min.js` u.c. (kā `index.html` funkcijā `loadModelViewer`). Pretējā gadījumā CSP jāatļauj `ajax.googleapis.com`, `www.gstatic.com` (Draco) un `cdn.jsdelivr.net` (Meshopt).

### Analītika

`index.html` sūta notikumus uz `window.dataLayer` (Google Tag Manager / GA4) un kā `window` notikumu `web3d`:

| Notikums | Kad |
|---|---|
| `web3d_model_loaded` | modelis ielādēts (`ms`, `ar`) |
| `web3d_interaction` | pirmā lietotāja grozīšana |
| `web3d_ar_click`, `web3d_ar_status` | AR poga / AR sesijas statuss |
| `web3d_variant_change` | izvēlēts variants |
| `web3d_hotspot_click` | atvērta anotācija |
| `web3d_model_select`, `web3d_model_error` | modeļa maiņa / ielādes kļūda |

---

## 5. Zināmie ierobežojumi

- **Python dzinējs** nedecimē tīklus ar UV (lai nesabojātu tekstūras) un nevar apstrādāt Draco/Meshopt ievadi. Šiem gadījumiem lieto gltf-transform.
- **USDZ** veido Blender: materiāli tiek konvertēti uz USD Preview Surface, tāpēc sarežģīti materiāli var izskatīties vienkāršāk nekā GLB.
- **Poster** renderē programmatūras WebGL (bez GPU). Izskats atbilst lapai, bet sarežģītiem modeļiem tas var aizņemt dažas sekundes.
- **Katalogs** (`catalog.json`) glabā nosaukumu brīdī, kad palaists `optimize.py`. Pēc manifesta nosaukuma maiņas palaid skriptu vēlreiz.
- **KTX2 un gltfpack** ceļi ir iekļauti, bet nav notestēti ar automātiskajiem testiem.
- Publicē tikai modeļus un tekstūras, uz kuriem tev ir tiesības.
