<?php
defined('VERSION') || exit; // not callable directly (in the universal package these files also sit, unused, inside OpenCart 4)
// Heading
$_['heading_title']              = 'Announcement Bar Pro';

// Tabs
$_['tab_messages']               = 'Paziņojumi';
$_['tab_settings']               = 'Iestatījumi';
$_['tab_content']                = 'Saturs';
$_['tab_schedule']               = 'Laiks';
$_['tab_visibility']             = 'Redzamība';

// Text
$_['text_extension']             = 'Paplašinājumi';
$_['text_success']               = 'Veiksmīgi: Announcement Bar Pro izmaiņas saglabātas!';
$_['text_message_list']          = 'Paziņojumi';
$_['text_add_message']           = 'Pievienot paziņojumu';
$_['text_edit_message']          = 'Rediģēt paziņojumu';
$_['text_no_messages']           = 'Paziņojumu vēl nav. Nospiediet "Pievienot", lai izveidotu pirmo.';
$_['text_module_disabled']       = 'Modulis ir izslēgts — paziņojumi veikalā netiek rādīti. Ieslēdziet to cilnē "Iestatījumi".';
$_['text_general']               = 'Vispārīgi';
$_['text_appearance']            = 'Izskats';
$_['text_dismiss']               = 'Aizvēršanas poga';
$_['text_advanced']              = 'Papildus';
$_['text_mode_rotate']           = 'Mainīgi (pa vienam)';
$_['text_mode_marquee']          = 'Skrejošā rinda';
$_['text_mode_stack']            = 'Visi reizē (viens zem otra)';
$_['text_position_top']          = 'Lapas augšā';
$_['text_position_bottom']       = 'Lapas apakšā (fiksēti)';
$_['text_state_active']          = 'Aktīvs';
$_['text_state_scheduled']       = 'Ieplānots';
$_['text_state_expired']         = 'Beidzies';
$_['text_state_disabled']        = 'Izslēgts';
$_['text_page_all']              = 'Visās lapās';
$_['text_page_home']             = 'Tikai sākumlapā';
$_['text_page_include']          = 'Tikai norādītajās lapās';
$_['text_page_exclude']          = 'Visur, izņemot norādītās';

// Column
$_['column_text']                = 'Paziņojums';
$_['column_status']              = 'Statuss';
$_['column_period']              = 'Periods';
$_['column_sort_order']          = 'Secība';
$_['column_action']              = 'Darbība';

// Entry
$_['entry_status']               = 'Statuss';
$_['entry_mode']                 = 'Attēlošanas veids';
$_['entry_interval']             = 'Maiņas intervāls (s)';
$_['entry_speed']                = 'Kustības ātrums (px/s)';
$_['entry_allow_html']           = 'Atļaut vienkāršu HTML';
$_['entry_position']             = 'Novietojums';
$_['entry_sticky']               = 'Piestiprināts ritinot';
$_['entry_font_size']            = 'Fonta izmērs (px)';
$_['entry_padding']              = 'Vertikālā atkāpe (px)';
$_['entry_default_bg_color']     = 'Noklusētā fona krāsa';
$_['entry_default_text_color']   = 'Noklusētā teksta krāsa';
$_['entry_dismissible']          = 'Rādīt aizvēršanas pogu (×)';
$_['entry_dismiss_days']         = 'Atcerēties aizvēršanu (dienas)';
$_['entry_delete_data']          = 'Dzēst paziņojumus atinstalējot';
$_['entry_text']                 = 'Teksts';
$_['entry_link_text']            = 'Pogas teksts';
$_['entry_link']                 = 'Saite';
$_['entry_link_new_tab']         = 'Atvērt jaunā cilnē';
$_['entry_icon']                 = 'Ikona';
$_['entry_bg_color']             = 'Fona krāsa';
$_['entry_text_color']           = 'Teksta krāsa';
$_['entry_preview']              = 'Priekšskatījums';
$_['entry_sort_order']           = 'Secība';
$_['entry_date_start']           = 'Sākums';
$_['entry_date_end']             = 'Beigas';
$_['entry_countdown']            = 'Rādīt atpakaļskaitīšanu līdz beigām';
$_['entry_store']                = 'Veikali';
$_['entry_customer_group']       = 'Klientu grupas';
$_['entry_page_mode']            = 'Lapas';
$_['entry_page_routes']          = 'Lapu maršruti';

// Help
$_['help_mode']                  = 'Kā rādīt vairākus aktīvus paziņojumus.';
$_['help_allow_html']            = 'Atļauj &lt;b&gt;, &lt;strong&gt;, &lt;i&gt;, &lt;em&gt;, &lt;u&gt;, &lt;s&gt;, &lt;br&gt;, &lt;span&gt; un saites. Visi citi tagi un atribūti tiek izņemti.';
$_['help_sticky']                = 'Josla paliek redzama augšā, kad lapu ritina.';
$_['help_default_color']         = 'Sākuma krāsa jauniem paziņojumiem.';
$_['help_dismiss_days']          = '0 = tikai līdz pārlūka cilnes aizvēršanai. Josla parādās atkal, ja paziņojumi mainās.';
$_['help_delete_data']           = 'Ja atzīmēts, atinstalējot moduli visi paziņojumi tiek neatgriezeniski dzēsti. Neatzīmējiet, lai tie saglabātos atkārtotai instalēšanai.';
$_['help_text_plain']            = 'Vienkāršs teksts (HTML tiek izņemts).';
$_['help_text_html']             = 'Atļauts vienkāršs HTML: b, strong, i, em, u, s, br, span, a.';
$_['help_countdown_placeholder'] = 'Ar {countdown} varat ievietot taimeri teksta vidū.';
$_['help_link_text']             = 'Nav obligāts. Ja tukšs un saite norādīta, viss paziņojums ir klikšķināms.';
$_['help_link']                  = 'Pilna adrese (https://…), ceļš (/akcija) vai index.php?route=…';
$_['help_icon']                  = 'Font Awesome 4 klase, piem., fa-truck, fa-gift, fa-percent.';
$_['help_dates']                 = 'Veikala laiks. Tukšs = bez ierobežojuma.';
$_['help_countdown']             = 'Vajadzīgs beigu datums. Kad laiks beidzas, paziņojums paslēpjas pats.';
$_['help_empty_all']             = 'Nekas nav atzīmēts = visi.';
$_['help_routes']                = 'Viens OpenCart maršruts rindā, piem., common/home, product/product, product/category, checkout/*';

// Error
$_['error_permission']           = 'Brīdinājums: Jums nav tiesību mainīt Announcement Bar Pro!';
$_['error_form']                 = 'Brīdinājums: Lūdzu, pārbaudiet formas kļūdas!';
$_['error_value']                = 'Nederīga vērtība!';
$_['error_range']                = 'Jābūt veselam skaitlim no %s līdz %s!';
$_['error_color']                = 'Krāsai jābūt formātā #RRGGBB!';
$_['error_text_length']          = 'Nedrīkst pārsniegt %s rakstzīmes!';
$_['error_text_required']        = 'Ievadiet tekstu vismaz vienā valodā!';
$_['error_link']                 = 'Nederīga saite! Izmantojiet http(s)://…, /ceļš vai index.php?route=…';
$_['error_icon']                 = 'Ikonai jābūt formātā fa-nosaukums, piem., fa-truck!';
$_['error_date']                 = 'Datumam jābūt formātā YYYY-MM-DD HH:mm!';
$_['error_date_order']           = 'Beigām jābūt pēc sākuma!';
$_['error_countdown']            = 'Atpakaļskaitīšanai vajadzīgs beigu datums!';
$_['error_routes']               = 'Nederīgs maršrutu saraksts! Atļauti burti, cipari, _ un /, beigās var būt /*; ne vairāk kā 50 maršruti.';
$_['error_routes_required']      = 'Ievadiet vismaz vienu maršrutu!';

// 2.0.2
$_['text_missing_language']      = 'Nav teksta: %s. Šo valodu apmeklētāji redz veikala noklusētās valodas tekstu (ja tāds ir).';
$_['text_low_contrast']          = 'Zems kontrasts — tekstu var būt grūti izlasīt.';
$_['error_not_found']            = 'Brīdinājums: Paziņojums nav atrasts (iespējams, tas ir dzēsts).';
$_['help_lang_fallback']         = 'Valodās, kur teksts nav ievadīts, tiek rādīts veikala noklusētās valodas teksts.';

// 2.0.3
$_['entry_end_all_day']         = 'Līdz šīs dienas beigām (23:59:59)';
$_['text_orphan_stores']         = 'Šis paziņojums bija ierobežots uz veikaliem, kas vairs neeksistē. Izvēlieties, kur tam jārādās (atstājiet visus neatzīmētus tikai tad, ja tam jārādās visos veikalos).';
$_['text_orphan_customer_groups'] = 'Šis paziņojums bija ierobežots uz klientu grupām, kas vairs neeksistē. Izvēlieties, kam tas jārāda.';
$_['error_orphan_stores']        = 'Izvēlieties vismaz vienu veikalu — veikali, uz kuriem paziņojums bija ierobežots, ir dzēsti.';
$_['error_orphan_customer_groups'] = 'Izvēlieties vismaz vienu klientu grupu — grupas, uz kurām paziņojums bija ierobežots, ir dzēstas.';
$_['error_nothing_selected']     = 'Atzīmējiet vismaz vienu paziņojumu, ko dzēst.';
$_['entry_color_mode']           = 'Krāsas';
$_['text_color_auto']            = 'Automātiski — pēc veikala tēmas';
$_['text_color_custom']          = 'Pielāgotas — katram paziņojumam savas krāsas';
$_['help_color_mode']            = 'Automātiski: josla ņem tēmas galveno krāsu (pogas/izvēlne) un izvēlas labi lasāmu teksta krāsu. Pielāgotas: katram paziņojumam iestatītās krāsas.';
$_['text_color_auto_note']       = 'Iestatījumos krāsas ir „Automātiski”: josla izmanto tēmas krāsu. Zemāk norādītās krāsas darbosies, ja izvēlēsieties „Pielāgotas”.';

// 2.2.0
$_['entry_mobile_text']         = 'Teksts telefonam (neobligāts)';
$_['help_mobile_text']          = 'Rāda augšējā teksta vietā ekrānos, kas šaurāki par 768 px. Atstājiet tukšu, lai visur rādītu to pašu tekstu. Noteikumi tādi paši kā tekstam; arī šeit darbojas {countdown}.';
$_['entry_device']              = 'Ierīces';
$_['text_device_all']           = 'Visas ierīces';
$_['text_device_desktop']       = 'Tikai datori (768 px un platāki)';
$_['text_device_mobile']        = 'Tikai telefoni (šaurāki par 768 px)';
$_['help_device']               = 'Nosaka ekrāna platums — tā pati 768 px robeža kā telefona tekstam. Ja kādam ekrāna izmēram nepaliek neviena paziņojuma, josla tur netiek rādīta.';
$_['column_views']              = 'Skatījumi';
$_['column_clicks']             = 'Klikšķi';
$_['column_ctr']                = 'CTR (%)';
$_['help_stats']                = 'Statistika ir vienkārši skaitītāji: katram paziņojumam viens skatījums un viens klikšķis apmeklētāja sesijā. Bez personas datiem un bez savām sīkdatnēm; apmeklētāji ar „Do Not Track” vai Global Privacy Control netiek skaitīti.';
$_['button_duplicate']          = 'Dublēt';
$_['button_reset_stats']        = 'Atiestatīt statistiku';
$_['text_duplicated']           = 'Veiksmīgi: paziņojums nokopēts. Kopija ir izslēgta — izlabojiet to un ieslēdziet.';
$_['text_stats_reset']          = 'Veiksmīgi: paziņojuma statistika atiestatīta.';
$_['text_confirm_reset']        = 'Atiestatīt šī paziņojuma skatījumus un klikšķus uz nulli?';
