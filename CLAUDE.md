# Toolkit SD – instructies voor Claude Code

WordPress-plugin van Sonora design (Jurgen De Winter). Draait op praktijkwebsites van zorgverleners in België. Vervangt de ASE Pro-functies die we gebruiken. Achtergrond en stand per site: projectdocument "Sonora design – ASE Pro-uitfasering" in het Claude-project Sonora design.

## Taal en toon

- Communiceer met Jurgen in het Nederlands (Vlaams), kort en concreet.
- Teksten in de plugin (labels, hints, meldingen): Nederlands, rustig, zonder vakjargon, "je"-vorm. Gebruik "opvolging", niet "onderhoud".
- Code-commentaar in het Nederlands, zoals de bestaande code.

## Wat nooit mag veranderen

Deze namen staan in de database van de klantsites. Wijzigen = instellingen kwijt.
- Optie `sde_settings`, back-upopties `sde_ase_backup`, `sde_wlcms_backup`, transient `sde_update_info`.
- Namespace `SDE` (en `SDE\Admin`, `SDE\Ase`, `SDE\Updater`, …), constanten `SDE_FILE`, `SDE_DIR`, `SDE_URL`.
- Taxonomie `asenha-media-category` (overgenomen van ASE Pro).
- Map en hoofdbestand: `toolkit-sd/toolkit-sd.php`. Slug `toolkit-sd`.
- Nieuwe instellingen: altijd een standaardwaarde in `defaults()` (in `toolkit-sd.php`), zodat bestaande sites niets merken.
- Nieuwe modules staan standaard **uit**.

## Privacy en veiligheid (niet onderhandelbaar)

- Geen externe diensten, geen tracking, geen Google Fonts of CDN's. Alles lokaal.
- Geen sleutels of wachtwoorden in de code of op klantsites.
- Updates enkel via `https://sonoradesign.be/updates/toolkit-sd/info.json`, met sha256-controle (zie `includes/updater.php`).
- Gebruikersinvoer altijd sanitizen, uitvoer escapen, nonces en `manage_options` bij AJAX.

## Structuur

- `toolkit-sd.php` – koptekst, `VERSION`, `defaults()`, laden van modules.
- `modules/` – één bestand per module (rest, login, admin-menu, login-redirect, admin-bar, media-categories, email-obfuscate, hide-notices).
- `includes/admin.php` – beheerpagina (Instellingen > Toolkit SD), tabs, opslaan via AJAX.
- `includes/ase-import.php` – overname uit ASE en ASE-functies uitzetten/terugzetten.
- `includes/updater.php` – updates via sonoradesign.be.
- `assets/` – admin.css/js (stijl zoals Fluidly, CTA-kleur #006fa0), login.css, logo.
- `bin/build.py` – bouwt `dist/toolkit-sd-X.Y.Z.zip` en `dist/info.json`.

## Nieuwe versie

1. Wijziging maken. `php -l` op elk gewijzigd PHP-bestand.
2. Versie verhogen op **twee** plaatsen in `toolkit-sd.php`: koptekst `Version:` en `const VERSION`. Semver: fix = patch, nieuwe optie/module = minor.
3. `CHANGELOG.md`: bovenaan `## X.Y.Z – JJJJ-MM-DD` met korte punten in het Nederlands (dit verschijnt bij "Details bekijken" in WordPress).
4. `python3 bin/build.py` (controleert dat versie en changelog kloppen).
5. Commit op `main`, tag `vX.Y.Z`, push met tags.
6. Release op GitHub: `gh release create vX.Y.Z dist/toolkit-sd-X.Y.Z.zip --title "Toolkit SD X.Y.Z" --notes-file <changelog van deze versie>`.
7. `dist/toolkit-sd-X.Y.Z.zip` en `dist/info.json` naar `sonoradesign.be/public_html/updates/toolkit-sd/` (zie "Publiceren"). Oude zips mogen blijven staan.
8. Controleren: `curl -s https://sonoradesign.be/updates/toolkit-sd/info.json` toont de nieuwe versie, en de sha256 van de zip online = `sha256` in info.json.

Vraag Jurgen altijd om bevestiging vóór stap 5 (push) en stap 7 (publiceren): vanaf dan zien alle klantsites de update.

## Publiceren naar sonoradesign.be

Nog in te richten (zie projectdocument). Tot dan: Jurgen zet de twee bestanden via Site Tools > Bestandsbeheer in `public_html/updates/toolkit-sd/`, of vraagt het in het Claude-project Sonora design.

## Testen

- Geen klantsite als testsite zonder akkoord van Jurgen.
- Na publiceren: op een site Dashboard > Updates > "Opnieuw controleren" → Toolkit SD moet de nieuwe versie tonen.
