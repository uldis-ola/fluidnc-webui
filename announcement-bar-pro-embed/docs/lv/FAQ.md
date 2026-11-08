# Biežāk uzdotie jautājumi

**Vai tas sūta datus jums vai kādam citam?**
Nē. Skripts ielādē tikai savu stila lapu un (variantā B) tavu JSON failu. Nav konta, izsekošanas, licences pārbaudes.
GA4 notikumus sūta tikai tavs GA4 / GTM un tikai, ja tos ieslēdz.

**Kuras platformas?**
Jebkura, kur var pievienot skriptu pirms `</body>`: Shopify, Wix, Squarespace, Webflow, Tilda, WordPress, HTML, citas CMS.
Pārbaudīts Chrome lokālās testa lapās, arī Shopify tēmu atdarinošā lapā un lapā ar stingru CSP. Dzīvās Wix /
Squarespace / Webflow / Tilda vietnēs nav pārbaudīts.

**Kāpēc bezmaksas piegādes paziņojums nerādās?**
Tas gaida grozu: lapai jāizsauc `ABP.setCart({subtotal, currency, count})` (Shopify: `examples/cart-adapter-shopify.js`).
Tas paliek paslēpts arī tad, ja groza valūtai paziņojumā nav summas.

**Vai var pieteikties jaunumiem pašā joslā?**
Tieši nē (nav servera adrešu glabāšanai). Izmanto pogu ar saiti uz savu pieteikšanās formu.

**Josla neparādās.**
Pārlūka konsolē meklē rindas "Announcement Bar Pro:". Biežākie iemesli: cits konfigurācijas taga id, bojāts JSON
(eksportē vēlreiz), `.css` nav blakus `.js` (vai izmanto `data-css`), visi paziņojumi ieplānoti vēlāk vai ierobežoti
citām lapām, valodām vai ierīcēm, vai tie agrāk aizvērti šajā pārlūkā (pamēģini privāto logu).

**Josla aizsedz galveni.**
Fiksētas un piestiprinātas galvenes tiek pabīdītas zem joslas automātiski. Neparastām tēmām — izslēdz piestiprināšanu
vai pievieno savu CSS.

**Laiki nepareizi.**
Bar settings norādi veikala laika joslu (piem. `Europe/Riga`). "Tagad" ņem no apmeklētāja ierīces.

**Vai der stingram CSP?**
Jā, ar A / B (sk. INSTALL.md §3).

**Cik liels?**
Ap 20 KB gzip JavaScript + ap 4 KB gzip CSS, ielādē ar `defer` (lapu nebloķē).

**Licence un atbalsts?**
MIT licence (LICENSE.txt / LICENSING.md). Atbalsts: extension@3dp.lv.
