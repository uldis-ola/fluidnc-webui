# web-3d-pipeline

3D produktu modeļu optimizācija tīmeklim (`optimize.py`) un attēlošana ar Google
[`<model-viewer>`](https://modelviewer.dev/) (`index.html`): AR, ēnas, auto-rotācija, grozāmais galds.

```
web-3d-pipeline/
├── optimize.py        # CLI: .fbx/.gltf/.glb/.obj/.stl/.ply -> viegls .glb
├── index.html         # produkta lapa ar <model-viewer>
├── requirements.txt   # Python atkarības
└── models/            # šeit liec optimizētos .glb failus
```

## 1. Instalācija

**Python 3.9+**

```bash
cd web-3d-pipeline
python -m venv .venv
# Windows: .venv\Scripts\activate      Linux/macOS: source .venv/bin/activate
pip install -r requirements.txt
# vai: pip install trimesh numpy pillow fast-simplification scipy networkx
```

**Ieteicams – gltf-transform** (labākā kvalitāte: decimācija saglabā UV/tekstūras, WebP, Draco/Meshopt).
Vajag [Node.js 18+](https://nodejs.org/):

```bash
npm install -g @gltf-transform/cli
gltf-transform --version
```

Ja globāli neinstalē, skripts mēģinās `npx @gltf-transform/cli` (vajag internetu pirmajā reizē).

**FBX failiem** (viens no):

- [FBX2glTF](https://github.com/godotengine/FBX2glTF/releases) – izpildāmo failu ieliec `PATH` ar nosaukumu `FBX2glTF`;
- [Blender](https://www.blender.org/download/) – `blender` jābūt `PATH` (skripts to palaiž bez GUI).

**Neobligāti:** [gltfpack](https://github.com/zeux/meshoptimizer/releases) (`PATH`).

## 2. Lietošana

```bash
python optimize.py modelis.fbx
# -> modelis.web.glb blakus oriģinālam

python optimize.py modelis.glb -o models/model.web.glb --ratio 0.3 --texture-size 1024
python optimize.py modelis.gltf --texture-format jpeg
python optimize.py skenejums.obj --engine python --force-decimate
```

| Parametrs | Noklusējums | Nozīme |
|---|---|---|
| `-o, --output` | `<nosaukums>.web.glb` | izvades fails |
| `--engine` | `auto` | `gltf-transform` → `gltfpack` → `python` (pirmais pieejamais) |
| `--ratio` | `0.5` | paturamā ģeometrijas daļa (1 = bez decimācijas) |
| `--error` | `0.001` | decimācijas kļūdas robeža (gltf-transform) |
| `--texture-size` | `2048` | maks. tekstūras izmērs px (e-komercijai parasti pietiek 1024–2048) |
| `--texture-format` | `webp` | `webp` vai `jpeg` |
| `--compress` | `draco` | `draco`, `meshopt`, `quantize`, `false` |
| `--force-decimate` | – | python dzinējā decimē arī tīklus ar UV (**tekstūras pazūd**) |
| `--min-faces` | `1000` | python dzinējā mazākus tīklus nedecimē |
| `--keep-temp` | – | saglabā starpfailus (piem. FBX→GLB) |

**Dzinēju atšķirības**

- `gltf-transform` – pilna optimizācija; decimācija saglabā UV un tekstūras.
- `gltfpack` – decimācija + saspiešana; tekstūras paliek oriģinālas.
- `python` – bez Node.js; tekstūras samazina un pārkodē WebP/JPEG, bet tīklus **ar UV** pēc noklusējuma nedecimē,
  jo `fast-simplification` UV koordinātas nesaglabā.

## 3. Lokāla testēšana

`index.html` **nedarbosies, atverot ar dubultklikšķi** (`file://`) – pārlūks bloķē modeļa ielādi. Vajag lokālu serveri:

```bash
# 1) optimizē modeli tieši mapē models/
python optimize.py ~/Lejupielādes/krēsls.fbx -o models/model.web.glb

# 2) palaid serveri web-3d-pipeline mapē
python -m http.server 8000

# 3) atver pārlūkā
http://localhost:8000/
http://localhost:8000/?src=models/cits-modelis.glb   # cits modelis bez HTML labošanas
```

Lapā var arī **ievilkt .glb failu** skatītājā vai izvēlēties to sadaļā “Testēšana”.

**Pārbaudi:**

1. Modelis ielādējas, apakšā redzami izmēri (cm).
2. Slēdži “Auto-rotācija” un “Ēnas” strādā; pogas ⟲/⟳ 45°, virziens, ātrums, leņķis, Priekša/Sāns/Aizmugure, Atiestatīt.
3. DevTools → Network: modeļa izmērs. Mērķis e-komercijai – līdz ~2–5 MB.
4. Validācija: <https://github.khronos.org/glTF-Validator/> vai `gltf-transform inspect models/model.web.glb`.

**AR testēšana telefonā** – AR (WebXR/Scene Viewer) prasa **HTTPS**; `http://` strādā tikai `localhost`. Iespējas:

- izvieto lapu uz HTTPS hostinga (GitHub Pages, Netlify, sava mājaslapa);
- vai lokāli tunelis, piem. `npx localtunnel --port 8000` / `cloudflared tunnel --url http://localhost:8000`.

Android – Scene Viewer (vajag Google Play Services for AR); iOS – Quick Look. iOS Quick Look izmanto **USDZ**:
`<model-viewer>` pats ģenerē USDZ no GLB, bet labākam rezultātam var pievienot `ios-src="models/model.usdz"`.

## 4. Piezīmes

- `index.html` ielādē `model-viewer` 4.0.0 no Google CDN un Draco dekoderi no `gstatic.com`;
  `--compress meshopt` gadījumā dekoderi ielādē no `cdn.jsdelivr.net` (konfigurēts lapas `<head>`).
  Ja mājaslapai ir stingra CSP, šie domēni jāatļauj vai faili jāhostē pašam.
- Mērogs: glTF vienība = 1 metrs. Ja FBX bija centimetros, AR modelis būs 100× par lielu – eksportē pareizā mērogā
  vai labo Blender.
- Pirms publicēšanas pārliecinies, ka tev ir tiesības uz modeli un tekstūrām.
