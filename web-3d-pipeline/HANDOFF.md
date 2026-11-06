# Nodošana: OpenCart 3D Product Viewer → 3dp.lv

## Kur viss atrodas
- Repozitorijs: `uldis-ola/fluidnc-webui`, zars **`ccr-0714b7c6-ikgkhp`**
- Modulis: `web-3d-pipeline/opencart-3d-viewer/`
- **Instalējamā pakotne:** `web-3d-pipeline/opencart-3d-viewer/dist/product_3d_viewer_v1.2.0.ocmod.zip`
- Lietotāja rokasgrāmata (EN): `web-3d-pipeline/opencart-3d-viewer/docs/USER_GUIDE.md`
- Marketplace apraksts + ekrānuzņēmumi: `web-3d-pipeline/opencart-3d-viewer/marketplace/`
- Licence (melnraksts, jāaizpilda): `web-3d-pipeline/opencart-3d-viewer/LICENSE.txt`
- Modeļu optimizācija: `web-3d-pipeline/optimize.py` (+ README.md)

## Stāvoklis
- Modulis v1.2.0 gatavs un notestēts uz OpenCart 3.0.3.8 (PHP 7.4), 3.0.4.1 un 3.0.5.x (PHP 8.3), 7 tēmās.
- **3dp.lv: NAV instalēts** (pārbaudīts 2026-11-06: `catalog/view/javascript/product_3d/product_3d.js` → 404).
- 3dp.lv tēma "meistars", galerija `.mt-gallery` — sausajā izmēģinājumā modulis to atpazīst automātiski.

## Uzdevums nākamajam čatam (iekopē Claude in Chrome vai Claude Code ar pārlūka piekļuvi)

```
Esmu pieslēdzies 3dp.lv OpenCart 3 admin. Instalē moduli "3D Product Viewer":
0. Pirms tam: izveido rezerves kopiju (DB + faili) vai pārliecinies, ka tāda ir.
1. Extensions → Installer → Upload → fails product_3d_viewer_v1.2.0.ocmod.zip
   (no repo uldis-ola/fluidnc-webui, zars ccr-0714b7c6-ikgkhp,
   web-3d-pipeline/opencart-3d-viewer/dist/). Gaidi zaļo "Success"; ja sarkans – apstājies un parādi tekstu.
2. Extensions → Extensions → filtrs "Modules" → "3D Product Viewer" → zaļā "+" (Install).
3. Edit: Status = Enabled; Placement = "Above the image gallery"; Gallery CSS selector = tukšs;
   model-viewer source = Local files; Auto-rotate = IZSLĒGTS (3dp.lv: nedrīkst kustīgi attēli). Save.
4. Atver Edit vēlreiz → sadaļā "System check" visām rindām jābūt OK.
5. Pārbaudi veikalā: https://3dp.lv/catalog/view/javascript/product_3d/product_3d.js → jābūt 200.
6. Testam: Catalog → Products → "Oak cutting board" → cilne "3D model" → Upload .glb → Save.
   Pārbaudi produkta lapu datorā un 360 px telefonā (3D redzams, cena/poga nepārklāta, nav horizontālas ritināšanas).
   Ja testēts ar svešu modeli – pēc pārbaudes "Remove" + Save.
Neko citu veikalā nemaini. Nekādus jaunus admin lietotājus neveido.
```

## 3dp.lv noteikumi, kas attiecas
- Auto-rotācija izslēgta (nav kustīgu attēlu).
- Lokālie faili, nekādi ārēji CDN.
- Īstiem produktiem — tikai īsti to produktu 3D modeļi (nevis testa krēsls).
- Pārbaudīt 360 px un datora platumā.

## Pirms pārdošanas Marketplace (tavā pusē)
1. `LICENSE.txt`: uzņēmums, kontakti, atbalsta/atjauninājumu termiņi, atmaksa; jurista pārbaude.
2. Cena.
3. Atbalsta e-pasts + publiska demo saite `marketplace/LISTING.md`.
4. OpenCart pārdevēja konts.
