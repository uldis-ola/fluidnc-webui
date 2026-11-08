# Announcement Bar Pro 2.4.2 OpenCart 1.5.x veikalam — uzstādīšana

Prasības: OpenCart 1.5.4 – 1.5.6.4, **PHP 5.6 vai jaunāks** (ieteicams 7.x), **vQmod 2.4+**, MySQL 5.1+ / MariaDB.
Atbalsts: 3DP SIA, extension@3dp.lv

## 1. Rezerves kopija
Pirms jebkura paplašinājuma uzstādīšanas izveidojiet veikala failu un datubāzes rezerves kopiju.

## 2. vQmod (izlaidiet, ja veikalā tas jau ir)
OpenCart 1.5 nav notikumu sistēmas, tāpēc josla tiek pievienota ar vQmod (tas nemaina nevienu core failu diskā).

1. Lejupielādējiet *vQmod for OpenCart* no https://github.com/vqmod/vqmod/releases (piem., `vqmod-2.6.x-opencart.zip`).
2. Augšupielādējiet tā mapi `vqmod` veikala saknes mapē (blakus `index.php`, `admin/`, `catalog/`).
3. Ļaujiet tīmekļa serverim rakstīt mapēs `vqmod/` un `vqmod/vqcache/` (piem., 755 vai 775; instalēšanas laikā arī
   `index.php` un `admin/index.php` jābūt rakstāmiem).
4. Pārlūkā atveriet `https://jusu-veikals/vqmod/install/`. Jābūt tekstam **"VQMOD HAS BEEN INSTALLED ON YOUR SYSTEM!"**
   (vai "already installed").

## 3. Moduļa augšupielāde (FTP)
Augšupielādējiet šīs pakas mapes **`upload/` saturu** veikala saknes mapē, saglabājot mapju struktūru
(`admin/…`, `catalog/…`, `system/…`, `vqmod/xml/announcement_bar_pro.xml`). Neviens esošs fails netiek pārrakstīts.

Sava tēma? Veikala veidne ir `catalog/view/theme/default/template/module/announcement_bar_pro.tpl`; OpenCart 1.5 to
izmanto visām tēmām, ja tēmai nav savas kopijas.

## 4. Uzstādīšana un iestatīšana
1. Admin → *Extensions → Modules* → **Announcement Bar Pro** → **Install**, tad **Edit**.
2. Jauna instalācija ir ieslēgta. Pievienojiet pirmo paziņojumu (*Paziņojumi → +*) vai sāciet no veidnes.
3. Cilne *Iestatījumi*: novietojums, rādīšanas veids, krāsas, e-pasta pieteikšanās, GA4, gaidīšana uz sīkdatņu piekrišanu,
   Turnstile atslēgas, **Ielādēt ikonu fontu** (izslēdziet, ja tēma jau ielādē Font Awesome 4).

Datubāzes tabulas tiek izveidotas instalējot (un pārbaudītas katru reizi, atverot moduļa lapu; atkārtota palaišana neko
nemaina). Shēmas numurs tiek ierakstīts pēdējais, tāpēc pārtraukta instalēšana vienkārši pabeidzas nākamajā reizē.

## Jaunināšana
Augšupielādējiet jaunos `upload/` failus virs vecajiem (3. solis), tad vienreiz atveriet moduļa lapu adminā.
vQmod kešu pārbūvē pats (vai izdzēsiet failus mapē `vqmod/vqcache/`).

## Atinstalēšana
*Extensions → Modules → Announcement Bar Pro → Uninstall.* Iestatījumi tiek noņemti; paziņojumi un pieteikumi saglabājas,
ja vien nav atzīmēts *Iestatījumi → Dzēst datus atinstalējot*. Lai noņemtu pilnībā, pēc tam izdzēsiet `upload/` failus un
`vqmod/xml/announcement_bar_pro.xml` (kamēr modulis ir atinstalēts, āķi neko nedara).

## Ja josla neparādās
- Pēc veikala apmeklējuma mapē `vqmod/vqcache/` ir `vq2-catalog_controller_common_header.php` (vQmod strādā), un
  `vqmod/logs/` nav kļūdu par `announcement_bar_pro.xml`.
- Modulis ir ieslēgts, paziņojums ir aktīvs, tā datumos un atbilst lapai / veikalam / klientu grupai.
- Valodu mapes: OpenCart 1.5 izmanto valodas *direktoriju* (`english`, `latvian`, `russian`); ja jūsu valodu pakas mape saucas
  citādi, nokopējiet moduļa valodas failu tur (pārējās valodas izmanto angļu tekstus).
- Divpakāpju apstiprinājuma e-pastiem jābūt iestatītam *System → Settings → Mail* (SMTP vai Mail) un veikala e-pastam.
- Cloudflare Turnstile vajag izejošu HTTPS no PHP (curl vai `allow_url_fopen`) ar CA sertifikātu komplektu.
- OpenCart 1.5 ar PHP 7.2+ vajag ierasto `system/library/encryption.php` aizstājēju (mcrypt vairs nav) — tā ir OpenCart
  prasība, nevis šī moduļa.
