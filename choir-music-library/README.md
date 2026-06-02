# Choir Music Library

WordPress-Plugin fuer einen geschuetzten Chor-Notenbereich.

## Installation

1. Den Ordner `choir-music-library` nach `wp-content/plugins/` kopieren.
2. Im WordPress-Backend das Plugin "Choir Music Library" aktivieren.
3. Unter "Chor-Noten" Musikstuecke anlegen.

## Inhalte

Pro Musikstueck koennen gepflegt werden:

- Songname ueber den Beitragstitel
- Komponist
- Texter
- Besetzung
- Zusatzinformationen
- Informationen zum Singen
- Tags
- mehrere Noten-PDFs
- mehrere Hoerbeispiele
- optionale Aussprachehilfen als PDF oder Audio
- optionale sonstige Dateien

## Zugriff

Das Plugin liest die Membership-Level aus WP Simple Membership / Simple Membership. In jedem Musikstueck kann in der Seitenbox festgelegt werden, welche Level Zugriff haben. Bleibt die Auswahl leer, duerfen alle eingeloggten Simple-Membership-Mitglieder das Musikstueck sehen.

Datei- und Audio-Links werden ueber eine geschuetzte WordPress-Route ausgeliefert und pruefen vor der Auslieferung den Zugriff auf das Musikstueck.

## Frontend

Uebersichtsseite:

```text
[chor_noten_uebersicht]
```

Einzelnes Musikstueck auf einer beliebigen Seite:

```text
[chor_musikstueck id="123"]
```

Musikstuecke sind ausserdem unter dem Custom-Post-Type-Archiv und den Einzelansichten verfuegbar.

## Sprache

Unter `Chor-Noten > Einstellungen` kann die Plugin-Sprache zwischen Deutsch und Englisch umgestellt werden.

## Zahlungspflichtige Downloads

In jedem Musikstueck kann in der Box `Zahlung` aktiviert werden, dass Downloads erst nach Zahlung verfuegbar sind. Dafuer wird ein Produkt-Schluessel hinterlegt, der im Kaufdatensatz von WP Simple Shopping Cart vorkommen muss, zum Beispiel der Produktname oder eine SKU. Zusaetzlich kann der passende WP-Simple-Shopping-Cart-Kauf-Shortcode hinterlegt werden.

Nach erfolgreicher Zahlung versucht das Plugin ueber die WP-Simple-Shopping-Cart-Payment-Hooks den Kauf anhand der Kaeufer-E-Mail zu speichern und Downloads fuer diese E-Mail freizuschalten.

## PDF-Wasserzeichen

PDF-Downloads werden durch das Plugin selbst mit einem seitlichen Wasserzeichen versehen. Das Schema lautet:

```text
Optionaler Freitext · Haupt-URL der Webseite · Name · E-Mail · Datum
```

Die markierten PDFs werden beim ersten Download pro Nutzer und Datei erzeugt und im Upload-Ordner unter `cml-watermarked` zwischengespeichert.

Unter `Chor-Noten > Einstellungen` kann gewaehlt werden, ob alle PDF-Noten oder nur zahlungspflichtige PDF-Noten ein Wasserzeichen erhalten.
Der Basistext kann dort angepasst werden. Bleibt er leer, wird die Haupt-URL der Webseite verwendet. Zusaetzlich kann ein optionaler Freitext vor dem Basistext gesetzt werden.
Der Basistext kann optional komplett ausgeblendet werden.
