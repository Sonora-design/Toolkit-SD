# Toolkit SD

Eigen WordPress-plugin van Sonora design. Vervangt de functies van ASE Pro die we op klantsites gebruiken, zodat die sites op de gratis versie van ASE kunnen draaien.

**Instellingen op de site:** Instellingen > Toolkit SD.

Vroeger heette deze plugin "Sonora design enhancements" (tot en met 1.2.2).

## Modules

Alle modules staan standaard uit. Een nieuwe installatie verandert niets tot ze ingesteld is.

| Module | Wat het doet |
|---|---|
| REST API afschermen | Bezoekers krijgen enkel de routes die nodig zijn (SureCookie, Independent Analytics, Novamira-inlogkoppeling). |
| Loginpagina in Sonora-stijl | Logo, teksten, kleuren en afronding instelbaar, met live voorbeeld. |
| Beheermenu per rol | Menu-items verbergen of tonen per rol. |
| Doorsturen | Per rol een pagina na in- en uitloggen. |
| Admin bar in de backend | Verbergen voor gekozen rollen. |
| Mediacategorieën | Zelfde categorieën als ASE Pro, met filter in het mediavenster. |
| E-mailadressen verbergen | Shortcode `[obfuscate]` (ook klikbaar) en optioneel alle adressen in de inhoud. |
| Beheermeldingen verbergen | Enkel gekozen rollen zien ze nog. |

## Overstap van ASE Pro

Tabblad **Overstap ASE**: bekijken, overnemen, ASE-functies uitzetten (met back-up). Het volledige stappenplan staat in het projectdocument "Sonora design – ASE Pro-uitfasering".

## Nieuwe versie uitbrengen

1. Versienummer aanpassen in `toolkit-sd.php` (koptekst en `VERSION`).
2. `CHANGELOG.md` aanvullen.
3. Commit en tag `vX.Y.Z`, daarna een release met de zip.

## Vereisten

WordPress 6.4 of hoger, PHP 7.4 of hoger.
