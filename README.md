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

## 2. Voraussetzungen

- IP-Symcon ab Version 6.0

## 3. Installation

### a. Installation des Moduls

Im [Module Store](https://www.symcon.de/service/dokumentation/komponenten/verwaltungskonsole/module-store/) ist das Modul unter dem Suchbegriff *Panasonic ComfortCloud* zu finden.<br>
Alternativ kann das Modul über [Module Control](https://www.symcon.de/service/dokumentation/modulreferenz/module-control/) unter Angabe der URL `https://github.com/demel42/PanasonicComfortCloud.git` installiert werden.

### b. Einrichtung in IPS

## 4. Funktionsreferenz

alle Funktionen sind über _RequestAction_ der jew. Variablen ansteuerbar

## 5. Konfiguration

### PanasonicComfortCloud Device

#### Properties

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :------------------------ | :------  | :----------- | :----------- |
| Instanz deaktivieren      | boolean  | false        | Instanz temporär deaktivieren |
|                           |          |              | |

#### Aktionen

| Bezeichnung                | Beschreibung |
| :------------------------- | :----------- |

### Variablenprofile

Es werden folgende Variablenprofile angelegt:
* Boolean<br>
* Integer<br>
* Float<br>
* String<br>

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

- 0.9 @ dd.mm.yyyy HH:MM (beta)
  - Initiale Version
