# Lietotāja ceļvedis — Konfigurators un iespējas

Atver `configurator/index.html` pārlūkā. Viss tiek saglabāts šajā pārlūkā (localStorage), līdz nospied **Reset**.
Labajā pusē ir **dzīvais priekšskatījums** (īstais skripts parauga lapā; dators / telefons, lapas valoda, groza summa
bezmaksas piegādes paziņojumiem) un **Install on your site** (kods un JSON). Konfiguratora saskarne ir angļu valodā.

## Paziņojumi
- **Add message** vai **From a template** (12 gatavi: bezmaksas piegāde, zibakcija ar atskaiti, nosūtīšana tajā pašā
  dienā, sveiciena kupons, jaunumu saite, jaunumi, atkal pieejams, svētki, nedēļas nogale, piedāvājums pirms aiziešanas,
  B2B, privātums). Teksti angļu, latviešu un krievu valodā; citās valodās sākumā angļu teksts.
- Sarakstā redzama krāsa, teksts, prioritāte un atzīmes. ⧉ dublē, ✕ dzēš. Lielāka **prioritāte** (0–100) rādās pirmā.
- **Vieta**: joslas noklusējums, augšējā vai apakšējā josla (abas var būt reizē).

## Saturs
- **Teksts** katrā valodā un pēc izvēles **īsāks teksts telefonam** (zem 768 px).
- Ar "Allow simple HTML": `<b> <strong> <i> <em> <u> <s> <br> <span> <a href>`. Viss pārējais tiek izņemts ar atļauto
  tagu filtru pārlūkā; saitēm jābūt `https://`, `http://` vai vietnes ceļam. Nekas no konfigurācijas netiek ievietots kā HTML.
- **Vietturi**: `{countdown}` (taimeris), `{remaining}` (bezmaksas piegāde: cik vēl trūkst).
- **Ikona**: emoji vai īss teksts (ikonu fonts nav vajadzīgs).
- **Saite** + **pogas teksts**: ar pogas tekstu ir poga, bez tā viss paziņojums ir saite.
- **Atlaides kods** ar pogu **Kopēt** ("Nokopēts!" redzams un paziņots ekrāna lasītājam).
- **E-pasta pieteikšanās**: šai versijai nav servera, tā adreses nevāc. Izmanto veidni "Newsletter" — poga ar saiti uz
  tavu pieteikšanās formu (Shopify, Mailchimp, MailerLite …).

## Atskaite un grafiks
- **Atskaite** līdz beigu laikam vai **katru dienu / nedēļu** līdz noteiktam laikam ("Pasūti {countdown} laikā"),
  ar izlaižamām dienām.
- **Sākums / beigas**, **diennakts logs** (pāri pusnaktij — pieder dienai, kurā sākas) un **nedēļas dienas** — veikala
  **laika joslā** (Bar settings). "Tagad" ņem no apmeklētāja ierīces pulksteņa.

## Izskats
- **Stili**: Solid (tavas krāsas precīzi), Gradient, Soft, Minimal, Glass. Visi, izņemot Solid, paši notur kontrastu
  vismaz 4,5:1; Solid gadījumā Konfigurators rāda tavu krāsu kontrastu.
- **Krāsas: Automatic** paņem vietnes galveno krāsu un lasāmu teksta krāsu.
- **Fona attēls** ar krāsas pārklājumu, **sistēmas fonti** (nekas netiek lejupielādēts), **savs CSS** (filtrēts).

## Kam un kur
- **Ierīces**: visas, datori (768 px+) vai telefoni.
- **Lapas**: visas, tikai atbilstošās vai visas, izņemot. Noteikumi: *URL satur*, *Ceļš vienāds* (beigu slīpsvītra
  nav svarīga), *Atbilst* ar `*` kā "jebkas" (pārējais burtiski, bez regulārajām izteiksmēm). Salīdzina ceļu un
  vaicājumu (`/collections/sale?page=2`), reģistrs nav svarīgs.
- **Valodas**: no URL (`/lv/…`, `?lang=lv`), tad `<html lang="…">`. Trūkstošs teksts → noklusētās valodas teksts.
- **Kampaņa**: UTM source / medium / campaign un vietne, no kuras atnāca (atceras apmeklējuma laikā).
- **Apmeklētāji**: jauns (pirmās 30 min) vai atgriezies — viena localStorage vērtība, nekad ar DNT / GPC.

## Parādīšana
Uzreiz, pēc N sekundēm, pēc N % ritināšanas vai peli izvelkot no loga (datoros). Vienreiz parādījies paziņojums tā
paša apmeklējuma nākamajās lapās rādās uzreiz.

## Joslas iestatījumi
Novietojums, mainīgi / skrejoši / visi reizē, izgaišana vai slīdēšana, sekundes, ātrums, cik paziņojumu reizē, fonta
izmērs, atkāpe, piestiprināta augšējā josla, bultiņas, **pauzes poga** (WCAG 2.2.2), aizvēršana un cik ilgi to atcerēties
(paziņojums atgriežas, ja mainās teksts, saite, poga vai ikona), **saritināšana cilnē** pēc N sekundēm, valodas un
apmeklētāju uzraksti (iebūvēti EN, LV, RU, DE, LT, ET, PL; labojami), laika josla, valūta, GA4 notikumi (tikai ja vietne
jau ielādē GA4 / GTM), gaidīt sīkdatņu piekrišanu.

## Bezmaksas piegādes progress
Tips "Free-shipping progress": `{remaining}`, panākuma teksts un progresa josla. Vajag **groza adapteri** (INSTALL.md §5).

## Eksports / imports
Kods A, B, C un JSON (kopēt vai lejupielādēt). **Load the JSON above** ielādē saglabātu konfigurāciju. Glabā JSON
kopiju — tā ir tava rezerves kopija.
