# Toolkit SD – changelog

## 1.7.0 – 2026-11-26

- Nieuwe regel: **gratis ASE eerst**. Wat ASE gratis kan, blijft in ASE. Toolkit SD vult enkel aan wat Pro is, en geen functie wordt nog door beide plugins tegelijk uitgevoerd.
- REST API: staat in ASE "Disable REST API" aan, dan sluit ASE af voor bezoekers en geeft Toolkit SD enkel de uitzonderingen vrij (eigen routes, SureCookie, Independent Analytics, Novamira en het overzicht /wp-json/). De filter draait nu op prioriteit 300, na ASE (200). De beperking per rol blijft in Toolkit SD.
- REST API: is ASE niet actief of staat "Disable REST API" uit, dan sluit Toolkit SD zelf af, als vangnet. Staat het in ASE uit, dan verschijnt een melding om het daar aan te zetten.
- Doorsturen: waarschuwing als ASE ook doorstuurt na in- of uitloggen. ASE stuurt meteen door, waardoor de instelling van Toolkit SD niet meer aan bod komt. Gaan alle rollen naar hetzelfde adres, dan krijg je de tip dat dit ook met ASE gratis kan.
- Overname uit ASE: doorsturen wordt enkel overgenomen als rollen een verschillend adres hebben. De shortcode [obfuscate] enkel als de site link, subject of text gebruikt, of als ASE alle adressen in de inhoud verbergt. Admin bar-items enkel als ASE gratis ze niet kan verbergen. De beperking van de REST API per rol wordt mee overgenomen.
- "ASE-functies uitzetten" zet in ASE enkel uit wat Toolkit SD overgenomen heeft. "Disable REST API" gaat in ASE juist aan, en doorsturen naar één adres blijft in ASE (zo nodig omgezet naar één adres voor de gekozen rollen). De back-up en "ASE terugzetten" werken zoals voorheen.
- Overstap ASE: bij elk onderdeel staat "Pro → Toolkit SD" of "Gratis → blijft in ASE", met een samenvatting van wat er in ASE verandert.

## 1.6.1 – 2026-11-25

- Gebruikers met meerdere rollen (bv. via ASE): wie de site mag instellen, wordt na het in- of uitloggen niet meer doorgestuurd door een regel voor een andere rol zoals Editor. Enkel een regel voor Administrator geldt dan nog.
- Menu voor editors: wie naast Editor ook een rol met meer rechten heeft, krijgt het volledige menu. Editor samen met een rol met minder rechten (bv. Auteur) blijft afgeschermd.

## 1.6.0 – 2026-11-25

- Nieuwe module **Menu voor editors afschermen** (staat standaard uit). Editors zien enkel Pagina's, Media en hun profiel. Wat plugins later aan het menu toevoegen, blijft voor hen verborgen.
- Verborgen beheerpagina's zijn voor editors ook echt afgesloten. Pagina's bewerken (ook met Elementor) en media opladen blijven gewoon werken. Het Dashboard blijft bereikbaar.
- In de admin bar zien editors enkel hun profiel, afmelden, de link naar de site en "Bewerken".
- De lijst kan je aanvullen in Beheermenu. Met "Herstel standaard" zet je ze terug.
- Overname uit ASE: het menu zoals editors het in ASE zagen, wordt eenmalig overgenomen. Dat kan later opnieuw met de knop "Importeer uit ASE". ASE zelf wordt niet gewijzigd. Volgorde en namen van het menu blijven in ASE.

## 1.5.0 – 2026-11-25

- De beheerpagina staat nu onder Extra > Toolkit SD (Tools in het Engels). Het oude adres onder Instellingen stuurt automatisch door.
- Doorsturen na inloggen: op een nieuwe site staat voor de rol Editor standaard `/dashboard/` ingevuld. Sites die al instellingen bewaard hebben, houden wat er stond.
- Veiliger updaten: zonder geldige controlesom in het versiebestand wordt een update nooit geïnstalleerd.
- Lege index.php in de mappen van de plugin, zodat de webserver geen bestandslijst toont.

## 1.4.1 – 2026-11-25

- Updates komen voortaan rechtstreeks van de releases op GitHub (Sonora-design/Toolkit-SD), niet meer van sonoradesign.be.
- De vraag naar GitHub stuurt enkel "Toolkit-SD" mee, niet het adres van de site.
- Downloads worden enkel aanvaard van github.com of sonoradesign.be, en altijd met controle van de controlesom.

## 1.4.0 – 2026-11-25

- Updates via sonoradesign.be: de site kijkt twee keer per dag in `https://sonoradesign.be/updates/toolkit-sd/info.json`. Een nieuwe versie verschijnt bij Plugins en Dashboard > Updates, met de changelog onder "Details bekijken". Automatische updates kunnen per site aangezet worden.
- Ander adres nodig (bv. om te testen)? Zet `define( 'SDE_UPDATE_URL', '…' );` in wp-config.php.
- Bouwscript `bin/build.py` maakt de zip en het versiebestand.

## 1.3.0 – 2026-11-25

- Nieuwe naam: **Toolkit SD** (voorheen Sonora design enhancements). Map en hoofdbestand heten nu `toolkit-sd/toolkit-sd.php`; de beheerpagina staat onder Instellingen > Toolkit SD.
- Instellingen blijven behouden: ze staan nog altijd in de optie `sde_settings`.
- README toegevoegd.

## 1.2.2 – 2026-11-23

- Loginpagina: breedte en hoogte van het logo kunnen niet meer overschreven worden door andere plugins (bv. White Label CMS).
- Overname uit ASE: duidelijkere melding voor de shortcode [obfuscate].

## 1.2.1 – 2026-11-23

- Rustigere beheerpagina: knoppen en keuzes in #006fa0 met lichte achtergrond in plaats van donkerblauwe vlakken, kleinere titels en keuzeknoppen, invulvelden niet meer over de volle breedte.

## 1.2.0 – 2026-11-23

- E-mailadressen verbergen: shortcode `[obfuscate]` overgenomen van ASE, met de Pro-opties: klikbare link (`link="yes"`), onderwerp, eigen linktekst en CSS-klasse. Vervangt de shortcode van ASE Free, die `link="yes"` negeert.
- Nieuwe instellingen: shortcode aan/uit, automatisch in de inhoud (ook mailto-links), enkel voor bezoekers, methode (gecodeerde tekens of omgekeerde tekst zoals ASE), standaardweergave en standaard klikbaar.
- Shortcode-maker met kopieerknop en een lijst van pagina's die de shortcode gebruiken.
- Overname uit ASE neemt alle e-mailopties over; "ASE-functies uitzetten" zet ook de shortcode van ASE uit.

## 1.1.0 – 23 november 2026
Nieuwe beheerpagina in dezelfde opbouw als Fluidly, en meer instellingen per module.

- Beheerpagina: bovenbalk met opslaan zonder herladen, tabbladen, kaarten met schakelaars per module.
- REST API: SureCookie, Independent Analytics, de inlogkoppeling van Novamira en /wp-json/ apart aan of uit; toegang voor ingelogde gebruikers beperken tot bepaalde rollen (administrators altijd); test als bezoeker.
- Loginpagina: eigen logo (mediabibliotheek) en logomaat, tekst onder het logo, alle teksten, kleuren, afronding, onderdelen tonen of verbergen (wachtwoord vergeten, onthoud mij, terug naar de site, privacy, taalkeuze), eigen CSS. Live voorbeeld voor desktop en gsm.
- Beheermenu: tabel met alle menu-items en per item de zichtbaarheid per rol; extra regels voor items die niet in de lijst staan.
- Doorsturen per rol, nu ook na het uitloggen.
- Admin bar in de backend en beheermeldingen: rollen kiezen met vinkjes.
- Mediacategorieën: filter ook in de rasterweergave en in het mediavenster (ook in Elementor).
- E-mailadressen verbergen: optie om dit enkel voor bezoekers te doen.
- Overstap ASE: eerst bekijken wat overgenomen wordt.

## 1.0.0 – 23 november 2026
Eerste versie. Vervangt de Pro-functies van ASE die we op klantsites gebruiken.

- REST API afsluiten voor bezoekers, met uitzonderingen (SureCookie en Independent Analytics automatisch).
- Loginpagina in Sonora-stijl: logo en domeinnaam links, kaart rechts, vaste hoogte, geen externe lettertypes.
- Beheermenu vereenvoudigen per rol (regels per site) en items uit de admin bar halen.
- Redirect na inloggen per rol.
- Admin bar verbergen in de backend per rol.
- Mediacategorieën (zelfde indeling als ASE Pro, bestaande categorieën blijven behouden).
- E-mailadressen in de inhoud verbergen voor spambots.
- Beheermeldingen verbergen voor niet-admins.
- Overname uit ASE: instellingen overnemen, overgenomen functies in ASE uitzetten (met back-up) en terugzetten.

Alle modules staan standaard uit: een nieuwe installatie verandert niets tot ze ingesteld is.
