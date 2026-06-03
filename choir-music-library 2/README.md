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
