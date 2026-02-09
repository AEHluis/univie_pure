# Feature Request: API-Erweiterung für Personenbezogene Publikationsabfrage

## Hintergrund

Das Personalverwaltungssystem **t3luhepv** benötigt die Möglichkeit, auf Personen-Detailseiten automatisch die zugehörigen FIS-Publikationen anzuzeigen. Redakteure sollen dies über eine einfache Checkbox aktivieren können.

## Fachliche Anforderung

> "In der Personen-Detailansicht sollen automatisch alle Publikationen der jeweiligen Person aus dem Forschungsinformationssystem (FIS/Pure) angezeigt werden."

### Nutzen

- **Redakteure** müssen Publikationslisten nicht manuell pflegen
- **Besucher** sehen stets aktuelle Publikationen direkt auf der Personenseite
- **Datenqualität** bleibt hoch, da die Daten direkt aus dem FIS kommen

## Gewünschte Funktionalität

### 1. Personen-Zuordnung über externe ID

Die Verknüpfung zwischen dem Personalverzeichnis (t3luhepv) und dem FIS soll über die sogenannte **luhId** erfolgen:

| System | Feldname | Beispielwert |
|--------|----------|--------------|
| t3luhepv (Personalverzeichnis) | `luhId` | `9ZK-APX` |
| Pure/FIS | `externalId` | `9ZK-APX` |

**Anforderung:** univie_pure muss einen Service bereitstellen, der eine luhId entgegennimmt und die zugehörige FIS-Person-UUID zurückliefert.

### 2. AJAX-basierte Publikationsliste

Die Publikationen sollen **asynchron** (per AJAX) geladen werden, um:

- Die Seitenladezeit nicht zu beeinflussen
- Bei Personen ohne FIS-Eintrag keinen leeren Bereich anzuzeigen
- Paginierung ohne Seiten-Reload zu ermöglichen

### 3. Darstellungsoptionen

Folgende Einstellungen sollen konfigurierbar sein:

| Option | Beschreibung | Standardwert |
|--------|--------------|--------------|
| Publikationen pro Seite | Anzahl der angezeigten Einträge | 10 |
| Zitationsstil | Formatierung der Literaturangaben | LUH Standard |
| Nach Jahr gruppieren | Publikationen nach Erscheinungsjahr sortiert anzeigen | Ja |

### 4. Verhalten bei fehlenden Daten

| Situation | Erwartetes Verhalten |
|-----------|---------------------|
| Person hat keine luhId | Kein Publikationsbereich angezeigt |
| luhId nicht im FIS gefunden | Kein Publikationsbereich angezeigt |
| Person im FIS, aber ohne Publikationen | Kein Publikationsbereich angezeigt |

**Wichtig:** Es soll niemals ein leerer Container oder eine "Keine Ergebnisse"-Meldung erscheinen.

## Technische Schnittstelle (Zusammenfassung)

### Benötigte Komponenten in univie_pure

1. **PersonResolverService**
   - Eingabe: `luhId` (String)
   - Ausgabe: `UUID` der FIS-Person oder `null`
   - Mit Caching (7 Tage für gefundene, 24h für nicht gefundene Personen)

2. **AJAX-Endpunkt für Publikationen**
   - Eingabe: Person-UUID, Seite, Seitengröße, Rendering-Stil
   - Ausgabe: HTML-Fragment mit Publikationsliste und Paginierung
   - HTTP 204 wenn keine Publikationen vorhanden

3. **JavaScript-Loader**
   - Initialisiert sich automatisch bei Vorhandensein eines Containers mit `data-person-uuid`
   - Lädt Publikationen asynchron
   - Entfernt Container bei leerer Antwort

4. **CSS-Styling**
   - Konsistentes Design mit bestehenden univie_pure-Komponenten
   - Loading-Spinner während des Ladens
   - Responsive Paginierung

## Abhängigkeiten

- univie_pure bleibt **optional** für t3luhepv (Soft-Dependency)
- Ohne installiertes univie_pure funktioniert t3luhepv wie bisher
- Die Checkbox "FIS-Publikationen anzeigen" erscheint nur, wenn univie_pure installiert ist

## Akzeptanzkriterien

- [ ] luhId kann zu FIS-UUID aufgelöst werden
- [ ] Publikationen werden per AJAX geladen
- [ ] Paginierung funktioniert ohne Seiten-Reload
- [ ] Bei fehlenden Daten wird kein leerer Bereich angezeigt
- [ ] Die Funktion kann pro Plugin-Instanz aktiviert/deaktiviert werden
- [ ] Das Styling ist konsistent mit bestehenden Publikationslisten
- [ ] Performance: Die Personenseite lädt schnell, Publikationen werden nachgeladen

## Offene Fragen

1. Soll die Anzahl der Publikationen als Badge/Zähler angezeigt werden, bevor die Liste vollständig geladen ist?
2. Gibt es Anforderungen an Filteroptionen (z.B. nur bestimmte Publikationstypen)?
3. Soll ein Link zur vollständigen FIS-Profilseite der Person angezeigt werden?

---

**Referenz:** Detaillierte technische Implementierungsdokumentation siehe `IMKT_FIS_Publikationen_Implementation.md` im t3luhepv-Repository.
