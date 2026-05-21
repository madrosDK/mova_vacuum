# MOVA Vacuum für IP-Symcon

ReadOnly-Modul für MOVAhome / MOVA P50 Pro Ultra.

## v0.6 build 5

Bereinigter produktiver Stand:

- Zugangsdaten/Loginbereich vorhanden
- Statusabruf über MOVAhome Cloud
- `device/listV2` als Hauptquelle
- `getDeviceData` nur für verfügbare `prop.s_*` Konfigurationswerte
- lokale IP, MiIO, Portscan und Explorer-Tests entfernt
- Status wird als Text angezeigt, z. B. `🟢 Bereit (geladen)`
- Akku bleibt eigene Variable

Es werden keine Steuerbefehle an den Roboter gesendet.
