# Announcement Bar Pro 2.4.2 — instalēšana (OpenCart 2.0.x–2.3.x)

**Vispirms izveido failu un datubāzes rezerves kopiju.** Prasības: OpenCart 2.0.0.0–2.3.0.2, PHP 5.6 vai jaunāks
(PHP 5.6 gadījumā — OpenSSL paplašinājums).

Izvēlies paku pēc OpenCart versijas (to rāda admina kājene vai *System › Maintenance*):

- OpenCart **2.0.x, 2.1.x, 2.2.x** → `announcement_bar_pro_oc2.0-2.2_v2.4.2.ocmod.zip`
- OpenCart **2.3.x** → `announcement_bar_pro_oc2.3_v2.4.2.ocmod.zip`

## A. Ar Extension Installer (OpenCart ir iestatīts FTP)

OpenCart 2.x pakas failus kopē caur FTP: *System › Settings › veikals › FTP* jābūt aizpildītam un ieslēgtam.

1. *Extensions › Extension Installer* → augšupielādē `.ocmod.zip`.
2. *Extensions › Modifications* → **Refresh** (zilā poga augšā labajā pusē).
3. Instalē moduli:
   - 2.0–2.2: *Extensions › Modules* → **Announcement Bar Pro** → **Install** (zaļais +) → **Edit**.
   - 2.3: *Extensions › Extensions* → izvēlies **Modules** → **Announcement Bar Pro** → **Install** → **Edit**.
4. Jauna instalācija ir ieslēgta. Cilnē **Paziņojumi** pievieno pirmo paziņojumu (vai izvēlies veidni).

## B. Bez FTP (lielākajai daļai hostingu)

1. Atarhivē paku savā datorā.
2. Mapes `upload` **saturu** augšupielādē OpenCart saknes mapē (tur, kur ir `index.php` un `admin/`) ar hostinga
   failu pārvaldnieku vai FTP/SFTP programmu. Mapes tiek apvienotas; neviens OpenCart fails netiek aizstāts.
3. *Extensions › Extension Installer* → augšupielādē pakā esošo `announcement_bar_pro.ocmod.xml` (`.ocmod.xml`
   FTP nevajag). **2.0 / 2.1 versijā šis solis ir obligāts** — tas pievieno veikala āķi; 2.2 / 2.3 tas tikai parāda
   moduli sadaļā *Modifications*.
4. *Extensions › Modifications* → **Refresh**.
5. Instalē moduli (A3. solis).

## Jaunināšana no vecākas 2.x pakas

Augšupielādē jaunos failus pāri vecajiem (A vai B), atjauno modifikācijas un vienreiz atver moduļa lapu kā
administrators: datubāze tiek atjaunināta automātiski (paziņojumi, iestatījumi un statistika saglabājas).

## Atinstalēšana

*Modules › Announcement Bar Pro › Uninstall* noņem notikumus un iestatījumus. Paziņojumi un pierakstījušies e-pasti
saglabājas, ja vien nav atzīmēts *Iestatījumi › Papildus › Dzēst paziņojumus atinstalējot*. Lai noņemtu failus,
izdzēs `README.md` uzskaitītos ceļus un 2.0/2.1 versijā arī modifikāciju sadaļā *Extensions › Modifications*,
tad nospied **Refresh**.

## Ja josla neparādās

- **OpenCart 2.0.x:** *Modifications › Refresh* ieslēdz veikalam apkopes režīmu un to neizslēdz (OpenCart 2.0 kļūda,
  ne šī moduļa). Administrators veikalu redz, apmeklētāji redz apkopes lapu. Izslēdz to: *System › Settings › veikals ›
  Server › Maintenance Mode*.

- Modulis ir **ieslēgts** (cilne Iestatījumi) un vismaz viens paziņojums ir ieslēgts un derīgs šajā laikā.
- 2.0 / 2.1: *Extensions › Modifications* sarakstā ir "Announcement Bar Pro" un pēc instalēšanas nospiests **Refresh**.
- 2.2 / 2.3: modulis ir instalēts (notikumi tiek pievienoti, nospiežot *Install*).
- Paziņojums, kas ierobežots pēc veikala, grupas, lapas, valsts vai groza nosacījuma, rādās tikai tur, kur tie atbilst.

Atbalsts: **extension@3dp.lv** (3DP SIA). Lūdzu norādi OpenCart un PHP versiju un tēmas nosaukumu.
