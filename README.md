# MOVA Vacuum für IP-Symcon

ReadOnly-Modul für MOVAhome / MOVA P50 Pro Ultra.

## Stand v0.6 build 1

Dieses Modul liest ausschließlich Daten über die funktionierende MOVAhome Cloud API aus.

Aktuell genutzt:

- `/dreame-user-iot/iotuserdata/getDeviceData`
- `/dreame-user-iot/iotuserbind/device/listV2`
- `keyDefine.url` zur dynamischen Statusübersetzung

Es werden keine Steuerbefehle an den Roboter gesendet.

## Angezeigte Werte

- Online
- Akku
- Status-Code
- Status-Text
- Firmware
- Modell
- Seriennummer
- MAC-Adresse
- Produkt-ID
- Vendor
- Feature-Codes
- Cloud-/Bind-Domain
- Icon-URL
- KeyDefine-URL
- Video/Kamera-Status
- Gerätezeiten
- Rohdaten der verfügbaren `prop.s_*` Konfigurationen

## Hinweise

Beim P50 Pro Ultra liefert `getDeviceData` aktuell hauptsächlich `prop.s_*` Konfigurationswerte. Die wichtigsten Live-Werte kommen zuverlässig über `device/listV2`.

