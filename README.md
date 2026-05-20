# MOVA Vacuum IP-Symcon Modul

Testversion fuer MOVAhome / MOVA P50 Pro Ultra.

## Version

- Version: `0.3`
- Build: `3`

## Funktionen

- Login gegen MOVAhome-Cloud-Endpunkt
- Geraeteliste abrufen, Auswahlfeld fuellen und Device-ID speichern
- Grundstatus zuerst per `getDeviceData`, mit Fallback auf `device/listV2`
- Anzeige von Geraetename, Modell, Firmware, Seriennummer, Online-Status, Akku und Status
- Aktionen: Start, Pause, Stop, Zur Station, Roboter suchen, Warnung loeschen, Auto-Entleerung
- Debug-Ausgabe in IP-Symcon

## Installation

Das Repository als IP-Symcon Modul installieren, z. B.:

`https://github.com/madrosDK/mova_vacuum`

## Konfiguration

1. Instanz **MOVA Vacuum** anlegen.
2. MOVAhome E-Mail und Passwort eintragen.
3. Region auf `EU` lassen.
4. `Aenderungen uebernehmen`.
5. Button **Login + Geraete suchen** ausfuehren.
6. Gefundenes Geraet im Auswahlfeld auswaehlen und uebernehmen.
7. Danach **Status aktualisieren** testen.

## Hinweise

Der P50 Pro Ultra wurde in der Cloud als `mova.vacuum.r2475a` gesehen. Der Modellfilter ist deshalb standardmaessig leer und optional.

MOVA/Dreame nutzt eine nicht offiziell dokumentierte Cloud-API. Falls Login, Device-Liste oder Befehle anders antworten, bitte den Debug-Auszug aus IP-Symcon posten. Dann koennen die Endpunkte, Header oder Property-IDs angepasst werden.

Fuer Google-/Apple-Login muss in der MOVAhome-App zuerst ein normales Passwort gesetzt werden.
