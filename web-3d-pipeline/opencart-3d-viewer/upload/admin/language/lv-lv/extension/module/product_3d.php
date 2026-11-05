<?php
// Heading
$_['heading_title']         = '3D produktu skatītājs';

// Text
$_['text_extension']        = 'Paplašinājumi';
$_['text_success']          = 'Saglabāts: 3D produktu skatītāja iestatījumi!';
$_['text_edit']             = 'Rediģēt 3D produktu skatītāju';
$_['text_uploaded']         = 'Fails augšupielādēts.';
$_['text_products']         = 'Produkti ar 3D modeli: %s';
$_['text_local']            = 'Lokāli faili (ieteicams, bez ārējiem pieprasījumiem)';
$_['text_cdn']              = 'Google CDN';
$_['text_local_missing']    = 'Mapē catalog/view/javascript/product_3d/ nav model-viewer failu — augšupielādē moduli vēlreiz vai izmanto CDN.';
$_['text_placement_replace'] = 'Attēlu galerijas vietā';
$_['text_placement_before'] = 'Virs attēlu galerijas';
$_['text_placement_after']  = 'Zem attēlu galerijas';
$_['text_placement_manual'] = 'Manuāli (tēma izvada {{ product_3d_html }})';
$_['text_general']          = 'Vispārīgi';
$_['text_viewer']           = 'Skatītājs';
$_['text_files']            = 'Faili';

// Entry
$_['entry_status']          = 'Statuss';
$_['entry_placement']       = 'Novietojums produkta lapā';
$_['entry_selector']        = 'Galerijas CSS selektors';
$_['entry_height']          = 'Skatītāja augstums (px)';
$_['entry_bg_color']        = 'Fona krāsa';
$_['entry_ar']              = 'Papildinātās realitātes (AR) poga';
$_['entry_auto_rotate']     = 'Auto-rotācija';
$_['entry_camera_controls'] = 'Grozīšana / tuvināšana ar peli un pirkstiem';
$_['entry_shadow']          = 'Ēnas intensitāte (0–2)';
$_['entry_exposure']        = 'Ekspozīcija (0,1–3)';
$_['entry_camera_orbit']    = 'Kameras leņķis pēc noklusējuma';
$_['entry_poster_fallback'] = 'Galveno produkta attēlu rādīt kā posteri';
$_['entry_source']          = 'model-viewer avots';
$_['entry_folder']          = 'Augšupielādes mape (image/ iekšā)';
$_['entry_max_size']        = 'Maks. faila izmērs (MB)';
$_['entry_delete_data']     = 'Atinstalējot dzēst 3D saites';

// Help
$_['help_selector']         = 'Atstāj tukšu automātiskai atrašanai (ieteicams — strādā ar jebkuru tēmu: modulis lapā atrod šī produkta attēlus). CSS selektoru norādi tikai, ja vajag noteiktu vietu, piem. .thumbnails';
$_['help_camera_orbit']     = 'piem. "30deg 75deg auto" (horizontāli, vertikāli, attālums). Tukšs = automātiski.';
$_['help_max_size']         = 'Servera limits (upload_max_filesize / post_max_size): %s MB.';
$_['help_delete_data']      = 'Tiek dzēsta tikai produktu ↔ modeļu saišu tabula. Augšupielādētie faili image/ mapē netiek dzēsti nekad.';
$_['help_folder']           = 'Jāsākas ar catalog/, piem. catalog/3d/';

// Product form tab
$_['p3d_tab']               = '3D modelis';
$_['p3d_menu']              = '3D produktu skatītājs';
$_['p3d_model']             = '3D modelis (.glb / .gltf)';
$_['p3d_ios']               = 'iOS AR fails (.usdz, nav obligāts)';
$_['p3d_poster']            = 'Poster attēls (nav obligāts)';
$_['p3d_placement']         = 'Novietojums';
$_['p3d_placement_default'] = '— kā moduļa iestatījumos —';
$_['p3d_place_replace']     = 'Attēlu galerijas vietā';
$_['p3d_place_before']      = 'Virs attēlu galerijas';
$_['p3d_place_after']       = 'Zem attēlu galerijas';
$_['p3d_place_manual']      = 'Manuāli (tēma)';
$_['p3d_placement_disabled'] = 'Nerādīt šim produktam';
$_['p3d_camera_orbit']      = 'Kameras leņķis';
$_['p3d_auto_rotate']       = 'Auto-rotācija';
$_['p3d_inherit']           = '— kā moduļa iestatījumos —';
$_['p3d_yes']               = 'Jā';
$_['p3d_no']                = 'Nē';
$_['p3d_upload']            = 'Augšupielādēt';
$_['p3d_choose']            = 'Izvēlēties esošu…';
$_['p3d_clear']             = 'Noņemt';
$_['p3d_preview']           = 'Priekšskatījums';
$_['p3d_uploading']         = 'Augšupielādē…';
$_['p3d_none']              = 'Faili nav atrasti';
$_['p3d_hint']              = 'Ieteikums: pirms augšupielādes optimizē modeli (piem. web-3d-pipeline/optimize.py): ≤ 5 MB, tekstūras ≤ 2048 px. Izmaiņas stājas spēkā pēc produkta saglabāšanas.';
$_['p3d_max']               = 'Maks. %s MB';
$_['p3d_settings']          = 'Moduļa iestatījumi';
$_['p3d_readonly']          = 'Tev nav tiesību mainīt 3D modeļus.';

// Error
$_['error_permission']      = 'Uzmanību: tev nav tiesību mainīt 3D produktu skatītāju!';
$_['error_type']            = 'Nezināms faila tips.';
$_['error_upload']          = 'Faila augšupielāde neizdevās.';
$_['error_size']            = 'Fails ir par lielu (maks. %s MB).';
$_['error_extension']       = 'Atļautie failu tipi: %s';
$_['error_content']         = 'Faila saturs nav atļauts.';
$_['error_glb']             = 'Nederīgs .glb fails (gaidīts glTF 2.0 binārais).';
$_['error_gltf']            = 'Nederīgs .gltf fails (gaidīts glTF 2.0 JSON).';
$_['error_gltf_external']   = 'Šis .gltf atsaucas uz ārējiem failiem (.bin / tekstūras). Augšupielādē vienu .glb failu.';
$_['error_usdz']            = 'Nederīgs .usdz fails.';
$_['error_image']           = 'Posterim jābūt JPG, PNG vai WebP attēlam.';
$_['error_folder']          = 'Mapē %s nevar rakstīt.';
