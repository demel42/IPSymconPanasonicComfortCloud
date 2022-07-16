[![IPS-Version](https://img.shields.io/badge/Symcon_Version-6.0+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguration)
6. [Anhang](#6-anhang)
7. [Versions-Historie](#7-versions-historie)

## 1. Funktionsumfang

Steuerung der Panasonic-Geräte die in der Panasonic Comfort Cloud angemeldet sind, unterstützt wird zur Zeit:<br>
- Klimagerät

## 2. Voraussetzungen

- IP-Symcon ab Version 6.0
- Account in der Panasonic Comfort Cloud zur Steuerung angemeldeter Klimageräte

## 3. Installation

### a. Installation des Moduls

Im [Module Store](https://www.symcon.de/service/dokumentation/komponenten/verwaltungskonsole/module-store/) ist das Modul unter dem Suchbegriff *Panasonic ComfortCloud* zu finden.<br>
Alternativ kann das Modul über [Module Control](https://www.symcon.de/service/dokumentation/modulreferenz/module-control/) unter Angabe der URL `https://github.com/demel42/IPSymconPanasonicComfortCloud.git` installiert werden.

### b. Einrichtung in IPS

#### Panasonic Comfort Cloud I/O
In IP-Symcon nun unterhalb von _I/O Instanzen_ die Funktion _Instanz hinzufügen_ auswählen und als Hersteller _Panasonic_ angeben.
In der IO-Instanz muss nur der Hostname/die IP-Adresse des WebControl angegeben werden.
Mittels _Zugriff prüfen_ kann getestet werden (Hinweis: dauert ein paar Sekunden)

#### Panasonic Comfort Cloud Konfigurator
In IP-Symcon nun unterhalb von _Konfigurator Instanzen_ die Funktion _Instanz hinzufügen_ auswählen und als Hersteller _Panasonic_ angeben.
In dem Konfigurator werden nun alle eingerichteten Gruppen mit den Geräten aufgelistet; eine Anlage der Geräte-Instanz kann entsprechend erfolgen

#### Panasonic Comfort Cloud Gerät
Die Geräte-Instanz wird über dem Konfigurator angelegt. In der _Basis-Konfiguration_ ist Geräte-ID, Modell sowie der Produkt-Typ eingetragen.

## 4. Funktionsreferenz

alle Funktionen sind über _RequestAction_ der jew. Variablen ansteuerbar

## 5. Konfiguration

### Panasonic Comfort Cloud I/O

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :------------------------ | :------  | :----------- | :----------- |
| Instanz deaktivieren      | boolean  | false        | Instanz temporär deaktivieren |
|                           |          |              | |
| Benutzerkennung           | string   |              | Kennung der Panaonic Confort Cloud |
| Kennwort                  | string   |              | Passwort der Panaonic Confort Cloud |

#### Aktionen

| Bezeichnung                | Beschreibung |
| :------------------------- | :----------- |
| Zugriff testen             | Test der Zugangsdaten und Ausgabe der in der Cloud vorhandenen Geräte |


### Panasonic Comfort Cloud Konfigurator

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :------------------------ | :------  | :----------- | :----------- |
| Kategorie                 | integer  | 0            | Kategorie zu Anlage von Instanzen |


### Panasonic Comfort Cloud Gerät

#### Properties

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :------------------------ | :------  | :----------- | :----------- |
| Instanz deaktivieren      | boolean  | false        | Instanz temporär deaktivieren |
|                           |          |              | |
| Geräte-ID                 | string   |              | |
| Modell                    | string   |              | |
| Typ                       | integer  | 3            | 3=Klimagerät |
|                           |          |              | |
| Luftstromrichtungswechsel | integer  | 0            | Richtung des Wechsels (0=nur vertikal, 1=vertikal und horizontal) |
| hat nanoe X-Technologie   | boolean  |              | Gerät verfügt über die Technik zur Luftreinigung |
|                           |          |              | |
| Aktualisierungsintervall  | integer  | 60           | іn Sekunden |

#### Aktionen

| Bezeichnung                | Beschreibung |
| :------------------------- | :----------- |
| Daten aktualisieren        | Datenabruf aus der Panasonic Comfort Cloud |

### Variablenprofile

Es werden folgende Variablenprofile angelegt:
* Boolean<br>
PanasonicCloud.Operate

* Integer<br>
PanasonicCloud.AirflowDirection_0,
PanasonicCloud.AirflowDirection_1,
PanasonicCloud.AirflowHorizontal,
PanasonicCloud.AirflowVertical,
PanasonicCloud.EcoMode,
PanasonicCloud.FanSpeed,
PanasonicCloud.NanoeMode,
PanasonicCloud.OperationMode

* Float<br>
PanasonicCloud.Temperature,

## 6. Anhang

### GUIDs
- Modul: `{B2C42DAE-0ECA-62EE-9F56-B037A99A2F41}`
- Instanzen:
  - PanasonicCloudIO: `{FA9B3ACC-2056-06B5-4DA6-0C7D375A89FB}`
  - PanasonicCloudConfig: `{85693205-4AF7-C720-B108-05AD5815060D}`
  - PanasonicCloudDevice: `{A972DA17-4989-9CAD-2680-0CB492645050}`
- Nachrichten:
  - {34871A78-6B14-6BD4-3BE2-192BCB0B150D}: an PanasonicCloudIO
  - {FE8D32D1-6A63-D55B-FC77-8C34A637A5E0}: an PanasonicCloudConfig, PanasonicCloudDevice

### Quellen

## 7. Versions-Historie

- 1.6 @ 16.07.2022 10:06
  - Fix: Ersatz der Variable 'AirflowDirection' durch 'AirflowAutoMode'

- 1.5 @ 15.07.2022 11:54
  - Fix: Änderung der ComfortCloud-API nachgeführt
  - Funktion "Token löschen" hinzugefügt

- 1.4.2 @ 12.07.2022 17:48
  - Fix: Property "airflow_swing" (Luftstromrichtungswechsel) wurde nicht gesetzt

- 1.4.1 @ 09.07.2022 15:03
  - Fix: Übernahme der Soll-Temperatur als float

- 1.4 @ 09.07.2022 09:51
  - Fix: "Zeitpunkt der letzen Änderung" korrigiert (wird als Millisekunden geliefert)

- 1.3 @ 05.07.2022 15:36
  - Verbesserung: IPS-Status wird nur noch gesetzt, wenn er sich ändert

- 1.2 @ 22.06.2022 10:22
  - Fix: Angabe der Kompatibilität auf 6.2 korrigiert
  - Fix: unbekannter Timer in 'PanasonicCloudConfig'

- 1.1 @ 28.05.2022 12:15
  - update submodule CommonStubs
    Fix: Ausgabe des nächsten Timer-Zeitpunkts
  - einige Funktionen (GetFormElements, GetFormActions) waren fehlerhafterweise "protected" und nicht "private"
  - interne Funktionen sind nun entweder private oder nur noch via IPS_RequestAction() erreichbar

- 1.0.1 @ 17.05.2022 15:38
  - update submodule CommonStubs
    Fix: Absicherung gegen fehlende Objekte

- 1.0 @ 16.05.2022 11:26
  - Initiale Version
