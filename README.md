# MOVA Vacuum IP-Symcon Modul

Startversion für MOVAhome / MOVA P50 Pro Ultra (`mova.vacuum.r2587a`).

## Funktionen

- Login gegen MOVAhome-Cloud-Endpunkt
- Geräteliste abrufen und Device-ID speichern
- Grundstatus per `get_properties`
- Aktionen: Start, Pause, Zur Station
- Debug-Ausgabe in IP-Symcon

## Installation

Den Inhalt dieses ZIPs direkt als Modul installieren/kopieren, z. B.:

`C:\ProgramData\Symcon\modules\mova-vacuum\`

Struktur:

- `library.json`
- `README.md`
- `MovaVacuum/module.json`
- `MovaVacuum/form.json`
- `MovaVacuum/module.php`

## Konfiguration

1. Instanz **MOVA Vacuum** anlegen.
2. MOVAhome E-Mail und Passwort eintragen.
3. Region auf `EU` lassen.
4. `Änderungen übernehmen`.
5. Button **Login + Geräte suchen** ausführen.
6. Danach **Status aktualisieren** testen.

## Hinweise

Das ist eine technische Startversion. MOVA/Dreame nutzt eine nicht offiziell dokumentierte Cloud-API. Falls Login, Device-Liste oder Befehle anders antworten, bitte den Debug-Auszug aus IP-Symcon posten. Dann können die Endpunkte, Header oder MIOT-Property-IDs schnell angepasst werden.

Für Google-/Apple-Login muss in der MOVAhome-App zuerst ein normales Passwort gesetzt werden.
