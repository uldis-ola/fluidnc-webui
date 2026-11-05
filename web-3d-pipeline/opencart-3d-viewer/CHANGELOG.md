# Changelog — OpenCart 3D Product Viewer

## 1.1.0 — 2026-11-05
Supersedes 1.0.1. Darbojas neatkarīgi no tēmas.
Atjaunināšana: Installer → Uninstall → Install (dati paliek). Ja iestatījumos bija `.thumbnails`, vari to izdzēst (tukšs = automātiski).

- Automātiska galerijas atrašana jebkurā tēmā: pēc šī produkta attēlu failu nosaukumiem
  (src, data-src/lazy-load, srcset, href, data-zoom-image, background-image, citi izmēri/webp).
- Ja galerija ir režģa kolonna (Bootstrap/flex/grid), skatītājs tiek ievietots kolonnā, izkārtojums netiek salauzts.
- Telefonā, ja tēmas kolonna ir par šauru, skatītājs tiek parādīts pilnā platumā virs produkta bloka (arī pēc pagriešanas).
- Produktam bez attēliem: kreisā/galvenā kolonna blakus produkta informācijai.
- HTML tiek ievietots lapas izvadē pirms `</body>` (`view/product/product/after`) — strādā arī tēmām bez `{{ footer }}`.
  Manuālajā režīmā, ja tēma neizvada `{{ product_3d_html }}`, skatītājs tiek ievietots automātiski.
- Valodas: ja veikala kods ir `lv`, `lv-LV` u.c., tiek izmantoti `lv-lv` teksti (tāpat RU, EN).
- Saderība: 1.0.x notikums turpina strādāt, ja faili atjaunināti bez Uninstall/Install.
- Galerijas atrašana neapstājas pie kolonnas ar īsu aprakstu (cilnes/apraksts vairs netiek paslēpti).
- Produkta nosaukums tiek atpazīts tikai pēc teksta sakritības (ne pirmais `<h1>` lapā — tas var būt logo).

## 1.0.1 — 2026-11-05
Supersedes 1.0.0. Labojumi pēc neatkarīgas drošības un pareizības pārbaudes.
Atjaunināšana: augšupielādē pakotni caur Installer, pēc tam Extensions > Modules > 3D Product Viewer **Uninstall → Install**
(dati paliek; vajadzīgs jaunā notikuma reģistrēšanai). Iestatījumus pārbaudi un saglabā vēlreiz.

- Drošība: `install`/`uninstall` pa tiešo maršrutu prasa `modify` tiesības (iepriekš pietika ar `access`).
- Drošība: augšupielādes saturu pārbauda visā failā; poster tipam jāsakrīt ar paplašinājumu; USDZ pirmajam ierakstam jābūt USD;
  .glb ar ārējām atsaucēm tiek noraidīts; faila nosaukums tiek rezervēts atomāri (nav pārrakstīšanas).
- Drošība: `.htaccess` — `Options -Indexes`, `nosniff`, saderīgs ar Apache 2.2 un 2.4. README Nginx piemērs izlabots.
- Galerija: "replace" režīmā attēli vairs nepazūd, ja model-viewer neielādējas vai modelis ir bojāts.
- URL bez domēna: strādā arī www/bez-www un http/https neatbilstības gadījumā (iepriekš CORS bloķēja).
- Produktam bez attēliem skatītājs tiek ievietots galvenajā kolonnā, nevis šaurajā labajā.
- CSS selektors drīkst saturēt `>`.
- Admin priekšskatījums strādā ar failu nosaukumiem, kuros ir atstarpes vai speciālās rakstzīmes.
- Saglabājot produktu ar nederīgu ceļu, esošā 3D saite netiek dzēsta (ieraksts žurnālā).
- Produkta kopēšana kopē arī 3D modeli.

## 1.0.0 — 2026-11-05
- Pirmā versija (supersedes: nav — jauns modulis).
- Admin: cilne "3D modelis" produkta formā (.glb/.gltf, .usdz, poster), augšupielāde ar satura validāciju,
  esošo failu izvēle, priekšskatījums, novietojums/kamera/auto-rotācija katram produktam.
- Iestatījumu lapa: statuss, novietojums (galerijas vietā / virs / zem / manuāli), CSS selektors, augstums,
  fons, AR, ēnas, ekspozīcija, kamera, poster no produkta attēla, lokāli faili vai CDN, mape, maks. izmērs.
- Veikals: Google model-viewer 4.0.0 (lokāli, bez ārējiem pieprasījumiem), Draco/Meshopt/KTX2 dekoderi, AR poga.
- Tikai notikumi (events) — nav OCMOD un kodola failu izmaiņu. Sava tabula `oc_product_3d`.
- Valodas: en-gb, lv-lv, ru-ru.
