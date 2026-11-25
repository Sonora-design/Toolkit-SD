# Toolkit SD

Eigen WordPress-plugin van Sonora design. Vervangt de functies van ASE Pro die we op klantsites gebruiken, zodat die sites op de gratis versie van ASE kunnen draaien.

**Instellingen op de site:** Extra > Toolkit SD.

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

1. Versienummer aanpassen in `toolkit-sd.php` (koptekst `Version:` en `const VERSION`).
2. `CHANGELOG.md` aanvullen met een regel `## X.Y.Z – datum`.
3. `python3 bin/build.py` om te controleren.
4. Commit en push naar `main`, daarna `git tag vX.Y.Z && git push origin vX.Y.Z`.
5. De GitHub Action maakt de release met de zip en `info.json`. Alle sites zien de update binnen 12 uur (of meteen via Dashboard > Updates > Opnieuw controleren).

De volledige werkwijze voor Claude Code staat in `CLAUDE.md`.

## Updates op de sites

Sinds 1.4.1 kijkt elke site twee keer per dag in `https://github.com/Sonora-design/Toolkit-SD/releases/latest/download/info.json`. Geen sleutels op de site; de vraag stuurt enkel "Toolkit-SD" mee, niet het adres van de site. De download wordt gecontroleerd met de controlesom (sha256) uit `info.json`. Een ander adres (bv. om te testen) kan met `define( 'SDE_UPDATE_URL', '…' );` in wp-config.php.

## Vereisten

WordPress 6.4 of hoger, PHP 7.4 of hoger.
