# MOVA Vacuum IP-Symcon Modul

# Version

## v1.0 Build 1

## Übersicht

Dieses Modul integriert MOVA Saugroboter in IP-Symcon über die offizielle MOVA Cloud API.

Aktuell liegt der Fokus auf:

* stabiler Cloud-Anbindung
* sauberer Statusanzeige
* Liveinformationen des Roboters
* einfacher Integration in Visualisierungen und Automationen

Das Modul wurde aktuell mit folgendem Gerät getestet:

* MOVA P50 Pro Ultra

---

# Funktionen

## Cloud Login

Anmeldung über:

* MOVA Account E-Mail
* Passwort

Das Modul verbindet sich direkt mit der europäischen MOVA Cloud.

---

# Unterstützte Informationen

## Geräteinformationen

Anzeige von:

* Gerätename
* Firmware-Version
* Seriennummer

Der Gerätename wird automatisch aus der Cloud übernommen.

Beispiel:

```text
P50 Pro Ultra
```

---

## Statusanzeige

Der aktuelle Status des Roboters wird automatisch übersetzt und lesbar dargestellt.

Beispiele:

```text
🟢 Bereit (geladen)
🧹 Reinigung läuft
🏠 Rückkehr zur Station
🔋 Lädt
🌬️ Mopp wird getrocknet
```

---

## Livewerte

Aktuell unterstützt:

* Online-Status
* Akkustand
* Gerätestatus

---

# Hinweise

## Lokale Kommunikation

Aktuell verwendet das Modul ausschließlich die MOVA Cloud API.

Lokale MiIO-/Dreame-Kommunikation wurde getestet, wird vom MOVA P50 Pro Ultra aktuell jedoch nicht offen unterstützt.

---

# Installation

## Modul installieren

Repository nach:

```text
C:\ProgramData\Symcon\modules\mova_vacuum
```

kopieren.

Danach in IP-Symcon:

```text
Kerninstanzen → Module neu laden
```

---

# Instanz anlegen

Neue Instanz erstellen:

```text
MOVA Vacuum
```

Danach:

* E-Mail eintragen
* Passwort eintragen
* Login testen
* Gerät auswählen

---

# Bekannte Einschränkungen

Aktuell sind keine lokalen Steuerfunktionen verfügbar.

Das Modul dient derzeit primär zur:

* Statusanzeige
* Geräteüberwachung
* Cloud-Auswertung

---
