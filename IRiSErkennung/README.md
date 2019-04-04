# IRiSErkennung
Dieses Erkennungsmodul wird im Rahmen des Forschungsprojekt "IRiS - Intelligente Rettung im SmartHome" ([Homepage](https://symcon.de/forschung/iris)). Im Rahmen des Projektes soll ein Demonstrator für einen Gebäudeeditor erstellt werden, welcher vorhandene Geräte möglichst automatisiert erkennt und konfiguriert. Dieses Modul soll hierbei zur Erkennung der Instanzen dienen und soll durch Benutzerfeedback noch weiter evaluiert und verbessert werden.

### Inhaltverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Ermöglicht ein Auslesen aller Instanzen und weist jeder Statusvariablen einen Typ zu
* Die Korrektheit jeder einzelnen Erkennung kann bestätigt oder verneint werden
* Zusätzlich können bei falschen Erkennungen der korrekte Typ und eine Anmerkung mitgegeben werden

### 2. Voraussetzungen

- IP-Symcon ab Version 5.0

### 3. Software-Installation

Über das Modul-Control folgende URL hinzufügen.  
`https://github.com/DrNiels/IRiSErkennung`  

### 4. Einrichten der Instanzen in IP-Symcon

- Unter "Instanz hinzufügen" ist das 'IRiSErkennung'-Modul unter dem Hersteller '(Sonstige)' aufgeführt.  

__Konfigurationsseite__:

Die obige Liste "Erkannte Geräte" beinhaltet nach der Erkennung alle Geräte-Instanzen in IP-Symcon. Die Einträge der Liste können annotiert werden. Unterhalb jedes Instanzeintrages sind die dazugehörigen Statusvariablen aufgelistet und können ebenfalls geprüft werden. 

Spalte            | Beschreibung
----------------- | ---------------------------------
Instanz/Variable  | Anzahl der Szenen die zur Verfügung gestellt werden.
Erkannter Typ     | Der vom Modul erkannte Typ für die Instanz bzw. die Statusvariable
Korrekt?          | Hier kann angegeben werden, ob die Erkennung des Moduls korrekt ist
Tatsächlicher Typ | Liegt die Erkennung falsch, so kann hier der tatsächliche Typ angegeben werden
Anmerkung         | Hier können Anmerkungen zur Erkennung angegeben werden

Weitere Funktionen stehen über die Buttons im Aktionsbereich zur Verfügung

Button                | Funktion
--------------------- | ---------------
Erkenne Geräte        | Die aktuelle Liste "Erkannte Geräte" wird zurückgesetzt und neu erstellt. Dabei gehen alle aktuellen Annotationen verloren
Sende Daten zu Symcon | Der aktuelle Stand der Liste wird anonymisiert an Symcon geschickt, damit die Daten zur Verifikation und Verbesserung der Geräteerkennung verwendet werden können. Die Daten beinhalten die Konfiguration der einzelnen Instanzen und Variablen, auf deren Basis die Entscheidung zur Typenzuweisung getroffen wird
Öffne IRiS-Homepage   | Öffnet die IRiS-Homepage

__Mögliche Gerätetypen__

Typ                      | Anmerkungen
------------------------ | --------------------
Licht (Aktor)            | -
Rauchmelder              | -
Taster/Schalter (Sensor) | Die Variable/Instanz beschreibt einen Taster oder Schalter, welcher nur physikalisch betätigt werden kann. Eine Aktualisierung der Variable sollte also bedeuten, dass sich eine Person beim Gerät befindet.
Bewegungs-/Präsenzmelder | -
Türöffner                | -
Türsensor                | -
Fensteröffner            | -
Fenstersensor            | -
Temperatursensor         | -
Gas                      | Das Gerät hat mit Gas zu tun, beispielsweise eine Gasheizung, und ist im Brandfall somit als Gefahrenquelle zu sehen.
Photovoltaik             | Ein Teil der Photovoltaikanlage
Rollladen                | -
Kein Typ                 | Keiner der obigen Typen passt zu der Variablen/der Instanz
Mehrere Typen            | Dieser Typ ist nur für Instanzen möglich und beschreibt, dass die Instanz die Funktion mehrerer Typen übernimmt, beispielsweise ein Bewegungsmelder mit Temperatursensor

### 5. Statusvariablen und Profile

Das Modul legt keine Statusvariablen oder Profile an.

### 6. WebFront

Das Modul bietet keine Optionen im WebFront

### 7. PHP-Befehlsreferenz

`boolean IE_Detect(integer $InstanzID);`  
Die Funktion lässt die Erkennung durchlaufen und setzt somit die Geräteliste zurück.  
Die Funktion liefert keinerlei Rückgabewert.  
Beispiel:  
`IE_Detect(12345);`

`boolean IE_SendData(integer $InstanzID);`  
Die Funktion schickt die Geräteliste in ihrer aktuellen Konfiguration an Symcon.  
Die Funktion liefert keinerlei Rückgabewert.  
Beispiel:  
`IE_SendData(12345);`

`boolean IE_GenerateEvaluationFile(integer $InstanzID);`  
Die Funktion erstellt eine JSON-Datei im Modulordner, welcher die aktuelle Konfiguration enthält. Dies kann verwendet werden um selbst zu sehen, welche Daten an Symcon geschickt werden oder kann als alternative Methode der Einreichung verwendet werden, falls aus diversen Gründen das Verschicken der Daten nicht funktioniert.  
Die Funktion liefert keinerlei Rückgabewert.  
Beispiel:  
`IE_GenerateEvaluationFile(12345);`
