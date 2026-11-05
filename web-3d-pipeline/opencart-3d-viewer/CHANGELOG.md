# Changelog — OpenCart 3D Product Viewer

## 1.0.0 — 2026-11-05
- Pirmā versija (supersedes: nav — jauns modulis).
- Admin: cilne "3D modelis" produkta formā (.glb/.gltf, .usdz, poster), augšupielāde ar satura validāciju,
  esošo failu izvēle, priekšskatījums, novietojums/kamera/auto-rotācija katram produktam.
- Iestatījumu lapa: statuss, novietojums (galerijas vietā / virs / zem / manuāli), CSS selektors, augstums,
  fons, AR, ēnas, ekspozīcija, kamera, poster no produkta attēla, lokāli faili vai CDN, mape, maks. izmērs.
- Veikals: Google model-viewer 4.0.0 (lokāli, bez ārējiem pieprasījumiem), Draco/Meshopt/KTX2 dekoderi, AR poga.
- Tikai notikumi (events) — nav OCMOD un kodola failu izmaiņu. Sava tabula `oc_product_3d`.
- Valodas: en-gb, lv-lv, ru-ru.
