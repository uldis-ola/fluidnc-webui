# Projekts: 3D produktu modeļi tīmeklim + OpenCart 3D Product Viewer

Pilns kopsavilkums nākamajam Claude Code čatam. Datums: 2026-11-06.

**Repozitorijs:** `uldis-ola/fluidnc-webui`, zars **`ccr-0714b7c6-ikgkhp`** (viss commitots un push). Projekts atrodas mapē `web-3d-pipeline/`.

---

## 1. Mērķis

1. Automātiski sagatavot 3D produktu modeļus tīmeklim (e-veikalam).
2. Rādīt tos OpenCart 3 veikala produkta lapā ar Google `<model-viewer>`: grozīšana, tuvināšana, AR telefonā.
3. Uzlikt to **3dp.lv** veikalā.
4. Pārdot OpenCart moduli **OpenCart Marketplace**.

---

## 2. Kas ir izdarīts

### A) `web-3d-pipeline/optimize.py` — modeļu optimizācija (Python CLI)

- **Ievade:** .fbx / .gltf / .glb / .obj / .stl / .ply, viens fails vai visa mape (`-r`).
- **FBX konvertācija:** ar FBX2glTF, Blender CLI vai `pip install bpy`.
- **Optimizācija** ar gltf-transform (ieteicams), gltfpack vai Python (trimesh):
  - decimācija;
  - tekstūras WebP/JPEG/KTX2;
  - Draco/Meshopt saspiešana;
  - `--max-size 3MB` limits.
- **Mērogs un ass:** `--units cm/mm`, `--up z`, `--center bottom` (uz grīdas, AR).
- **Validācija:** Khronos glTF-Validator.
- **Papildu izvade:** `--poster` (attēls), `--usdz` (iPhone AR), manifests `X.json`, `X.embed.html`, `catalog.json`, `report.csv`.
- **Lokāli faili:** `--fetch-vendor` lejupielādē model-viewer un dekoderus, lai nevajadzētu CDN.
- **Testi:** `web-3d-pipeline/tests/` (pytest, 20 testi, visi iziet).
- **Demo lapa:** `web-3d-pipeline/index.html` ar varianti, anotācijām, LV/EN un katalogu.

### B) OpenCart modulis `web-3d-pipeline/opencart-3d-viewer/` — v1.2.1

- **Pakotne:** `dist/product_3d_viewer_v1.2.1.ocmod.zip` (~0,7 MB).
- **Admin:**
  - produkta formā cilne "3D modelis": .glb/.gltf, .usdz un poster augšupielāde ar satura pārbaudi;
  - priekšskatījums;
  - iestatījumi katram produktam;
  - iestatījumu lapa ar "Sistēmas pārbaudi".
- **Veikalā:** skatītājs galerijas vietā, virs vai zem tās, vai manuāli (`{{ product_3d_html }}`).
  - **Galerija tiek atrasta automātiski jebkurā tēmā** pēc produkta attēlu failu nosaukumiem.
  - Ja skatītājs neielādējas, atkal tiek rādīti foto.
- **Tehnika:**
  - tikai OpenCart events (nav OCMOD, nav kodola izmaiņu);
  - sava tabula `oc_product_3d`;
  - model-viewer un dekoderi iekļauti lokāli (CDN pēc izvēles);
  - valodas: admin EN/LV/RU; veikalā EN, LV, RU, DE, LT, ET, PL.
- **Drošība:**
  - tiesības un `user_token` pārbaude;
  - satura validācija;
  - `.htaccess` augšupielādes mapē;
  - divu neatkarīgu recenzentu (drošība un OpenCart pareizība) atradumi izlaboti.
- **Testi (`tests/`):**
  - `e2e_opencart.py` (12 soļi);
  - `e2e_regression.py` (14 pārbaudes);
  - `e2e_themes.py` (7 testa tēmas `tests/themes/` × novietojumi × ar/bez attēliem × dators/telefons).
- **Pārbaudīts uz:** OpenCart 3.0.3.8 (PHP 7.4), 3.0.4.1 un 3.0.5.x (PHP 8.3). Visi testi iziet.
- **Pārdošanai:**
  - `docs/USER_GUIDE.md` (EN);
  - `docs/CHANGELOG_EN.md`;
  - `LICENSE.txt` (melnraksts);
  - `marketplace/LISTING.md` (apraksts);
  - `marketplace/screenshots/` (5 attēli).
- **Būvēšana:** `python build.py` (php -l + zip).

### C) 3dp.lv

- **Veikals:** OpenCart 3, tēma "meistars", produkta galerija `.mt-gallery`, drošības modulis "Sargs".
- **Sausais izmēģinājums** (tikai pārlūkā, serverī nekas nemainīts): modulis tēmu atpazīst. Tas atklāja šauras galerijas kļūdu, kas ir izlabota.
- **Modulis 3dp.lv INSTALĒTS 2026-11-06 (v1.2.1), Uldis apstiprināja.** Faili caur FTP (3dp-ws `tools/deploy.sh p3d`), Install adminā; System check 8/8 OK; produkts 79 “Oak cutting board” ar 3D modeli no veikala foto; pārbaudīts 1366 px un 360 px (0 kļūdu), teksti 7 valodās.
- v1.2.1 izmaiņas: veikala teksti DE/LT/ET/PL, admin valodas kods `lv`, drošības pārskata labojumi (notikumu metodes bez parametriem nedod 500; `index.html` augšupielādes mapē `Options -Indexes` vietā).

### D) Demo

- Interaktīvs demo (4 tēmas, novietojumi): https://claude.ai/artifact/4dzNKD89k8ontNgBahponQ (privāts).

---

## 3. Kas jāizdara

### 3.1 Uzlikt 3dp.lv — IZDARĪTS 2026-11-06 (atliek: AR uz īsta telefona; īsti modeļi citiem produktiem)

1. Rezerves kopija (DB + faili).
2. **Extensions → Installer → Upload** `product_3d_viewer_v1.2.1.ocmod.zip`.
3. **Extensions → Modules → 3D Product Viewer → + (Install) → Edit**:
   - Placement = **Above the image gallery**;
   - selektors = **tukšs**;
   - avots = **Local files**;
   - **Auto-rotate = izslēgts**.

   Pēc tam **Save**.
4. **System check:** visām rindām jābūt OK.
5. Pārbaude: `https://3dp.lv/catalog/view/javascript/product_3d/product_3d.js` atgriež 200.
6. Produktam (piem. "Oak cutting board") cilnē "3D modelis" augšupielādē .glb un saglabā.
7. Pārbaudi dzīvo lapu datorā un 360 px telefonā, un AR uz īsta telefona.

### 3.2 3D modeļi īstiem produktiem

- **06.11:** modeļi no foto (79, 78, 335, 333, 332) tika uzlikti, bet Uldis tos NORAIDĪJA (“nav viens pret viens”) un tie noņemti. Noteikums: 3D modeļi tikai 1:1 no ražošanas failiem (SVG/DXF/STL/Studio), nevis no foto. `tools3d/` skripti der tikai melnrakstiem.

- Vajag īstus produktu modeļus (.fbx/.glb). Testa krēslu uz dzīvā veikala nelikt.
- Sagatavošana:
  ```
  python web-3d-pipeline/optimize.py modelis.fbx -o out/ --max-size 5MB --poster --usdz
  ```

### 3.3 Pirms pārdošanas Marketplace

- **06.11 izdarīts:** cena **$39** (Ulda izvēle; konkurenti $25 bez AR, $99 ar AR); LICENSE aizpildīts (3DP SIA, 12 mēn. atjauninājumi + atbalsts info@3dp.lv, 14 d. atmaksa, Latvijas likums) — vēl jurista pārbaude; LISTING: demo https://3dp.lv/oak-cutting-board-with-engraving, atbalsts 2 darba d.; ekrāni 01 un 04 no dzīvā 3dp.lv. **Atliek tikai Uldim:** jurista pārbaude, OpenCart pārdevēja konts, augšupielāde marketplace.

1. `LICENSE.txt`: uzņēmums, kontakti, atbalsta/atjauninājumu termiņi, atmaksa; jurista pārbaude.
2. Cena: nav noteikta, vajag konkurentu salīdzinājumu.
3. Atbalsta e-pasts un publiska demo saite `marketplace/LISTING.md`.
4. OpenCart pārdevēja konts un augšupielāde.
5. Ieteicams: ekrānuzņēmumi no dzīvā 3dp.lv pēc instalēšanas.

### 3.4 Netestēts (zināmie riski)

- OpenCart 3.0.0–3.0.3.7, KTX2 tekstūras, Nginx, maksas tēmas (Journal u.c.), AR uz īsta telefona, dzīvs veikals.
- Quick-view uznirstošie logi netiek atbalstīti.

---

## 4. 3dp.lv noteikumi (obligāti)

- Strādāt tikai ar 3dp.lv. Vecos moduļus atslēgt, nevis dzēst.
- Teksti vismaz EN, LV, RU. Nekādu ārēju CDN un Google Fonts.
- Viss responsīvs (pārbaudīt 360 px un datorā). Gaišs fons, nav kustīgu attēlu.
- Īsti produktu foto un modeļi. Cenas no augstākās uz zemāko, nekad nerakstīt "PVN iekļauts".
- Maksājumu moduļi paliek testa režīmā, līdz uzņēmums ir reģistrēts.
- Drošība: HTTPS, bez jauniem admin kontiem un failu tiesībām bez Ulda OK. Pirms instalēšanas rezerves kopija.
- Pēc katras izmaiņas: ekrānuzņēmumi pirms/pēc (dators + telefons) un gaidīt Ulda OK.

---

## 5. Lokāla testa vide (ja vajag atkārtot testus)

- MariaDB + `php -S`. OpenCart avots: `git clone -b 3.0.x.x https://github.com/opencart/opencart`.
- Ar `install/cli_install.php` uzstāda testa veikalu.
- OpenCart 3.0.3.8 vajag PHP 7.4, piem. Docker `thecodingmachine/php:7.4-v4-cli` ar `PHP_EXTENSIONS="mysqli gd zip"`.
- Testi:
  ```
  python tests/e2e_opencart.py --base http://127.0.0.1:8080/ --zip dist/product_3d_viewer_v1.2.1.ocmod.zip --glb ../models/chair.web.glb
  python tests/e2e_regression.py ... --site-root /ceļš/uz/veikalu --mysql "mysql -uroot <db>"
  python tests/e2e_themes.py --themes default,tA_flex,tB_grid,tC_nofooter,tD_stacked,tE_short,tF_h1logo --mysql "mysql -uroot <db>"
  ```
  Tēmu testam vispirms nokopē `tests/themes/*` uz veikala `catalog/view/theme/`.
