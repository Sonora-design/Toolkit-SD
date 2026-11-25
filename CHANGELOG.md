# Toolkit SD – changelog

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
