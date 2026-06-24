# HISCO Hierarchie – Deutsche Übersetzungsnotizen

## Herkunft

Die Datei `hisco_hierarchy_de.csv` wurde aus den englischen HISCO-Katalogdateien abgeleitet:

- `hisco_major_group.csv`
- `hisco_minor_group.csv`
- `hisco_unit_group.csv`

Die englischen Originaldateien wurden **nicht verändert**.

## Bearbeitungsdatum

2026-06-24

## Zeilenanzahl

| Ebene | Anzahl |
|-------|--------|
| major | 10 |
| minor | 77 |
| unit  | 299 |
| **Gesamt** | **386** |

## Übersetzungsentscheidungen

### Terminologische Grundsätze

- „not elsewhere classified" → „anderweitig nicht klassifiziert"
- „related workers" → je nach Kontext „verwandte Berufe" oder „verwandte Arbeitskräfte"
- Berufsgruppen werden als substantivische Gruppenbezeichnungen formuliert
  (z. B. „Chemiker", „Bürokräfte", „Dienstleistungsberufe")
- Historische und berufssoziologische Terminologie hat Vorrang vor heutigem Alltagsdeutsch

### Spezifische Hinweise

- **Hauptgruppen 0 und 1**: Im englischen Original identisch beschrieben
  (beide = „Professional, Technical and related workers"). Im Deutschen ebenfalls gleich
  übersetzt; translation_note verweist auf die gemeinsame HISCO-Hauptgruppe 0/1.
- **Hauptgruppen 7, 8 und 9**: Analog dazu = HISCO-Hauptgruppe 7/8/9.
- **minor_id 61 (Farmers)**: Der englische Begriff „Farmers" bezeichnet selbstständige
  Hofbewirtschafter. Die Übersetzung „Landwirte (Selbstständige)" folgt dem funktionalen
  Merkmal und unterscheidet sich bewusst vom historischen deutschen Standesbegriff „Bauer".
  Benutzeroberflächen des Moduls können beide Bezeichnungen separat referenzieren.
- **unit_id 124 (Solicitors)**: Britisch-rechtlicher Berufstitel ohne direkte deutsche
  Entsprechung; als „Rechtsbeistände" übersetzt; translation_note markiert die Unsicherheit.
- **unit_id 583 (Military)**: Breite Kategorie; als „Militärangehörige" übersetzt.

### Unsichere oder markierte Übersetzungen

Zeilen mit nicht-leerem `translation_note`-Feld enthalten:
- Hinweise auf Mehrfachbelegung derselben Beschreibung (Hauptgruppen 0/1 und 7/8/9)
- Fachterminologische Unsicherheiten (z. B. „Solicitors")
- Funktionale Unterschiede zwischen englischen und deutschen Berufsbegriffen
  (z. B. „Farmers" vs. „Bauern/Landwirte")

## Originaldaten unverändert

Die Spalten `label_en` und `description_en` in `hisco_hierarchy_de.csv` enthalten
die Inhalte der englischen Quelldateien ohne Änderungen.
