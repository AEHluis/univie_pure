# Issue #10: Campus IP-Adressen Visibility Implementation

## Übersicht

Implementierung der IP-basierten Zugriffskontrolle für die Sichtbarkeitseinstellung "Campus (IP-Adressen)" aus dem Pure-System.

**GitLab Issue:** #10
**Priorität:** Mittel
**Aufwand:** 3.5-4.5 Stunden
**Status:** Planung / Wartend auf Informationen

---

## Offene Klärungen (BLOCKER)

### 1. Campus IP-Ranges vom RRZN beschaffen
- [ ] IP-Adressbereiche der Uni Hannover anfragen
- [ ] Format: CIDR-Notation (z.B. `130.75.0.0/16`)
- [ ] Klären: Änderungsfrequenz der IP-Ranges
- [ ] Kontakt: RRZN IT-Zentrum

**Erwartetes Ergebnis:**
```
130.75.0.0/16
141.76.0.0/16
192.168.1.0/24
...
```

### 2. Visibility Key in Pure API bestätigen
- [ ] Test-Equipment im Pure-Backend anlegen
- [ ] Sichtbarkeit auf "Campus (IP-Adressen)" setzen
- [ ] API Response abrufen: `GET /equipments/{uuid}`
- [ ] `visibility.key` Wert dokumentieren

**Zu prüfender Endpoint:**
```
https://www.fis.uni-hannover.de/ws/api/524/equipments/{UUID}
```

**Erwarteter Key (zu bestätigen):**
```xml
<visibility key="RESTRICTED_IP">
  <value>
    <text locale="de_DE">Campus (IP-Adressen)</text>
  </value>
</visibility>
```

### 3. Scope Definition
- [ ] Klären: Nur Equipment oder alle Content-Typen?
- [ ] Falls alle: Publikationen, Projekte, DataSets inkludieren?
- [ ] Rücksprache mit Stakeholdern (Julia Geidel, @sbroll)

---

## Implementierungsaufgaben

### Phase 1: Configuration (0.5h)

#### 1.1 Extension Configuration erweitern
**Datei:** `ext_conf_template.txt`

```
# cat=basic/enable/100; type=string; label=Campus IP-Adressbereiche:Kommaseparierte Liste von IP-Ranges in CIDR-Notation (z.B. 130.75.0.0/16,141.76.0.0/16)
campusIpRanges =

# cat=basic/enable/101; type=boolean; label=IP-basierte Visibility aktivieren:Aktiviert die Filterung nach Campus IP-Adressen
enableIpVisibility = 1
```

**Tasks:**
- [ ] `ext_conf_template.txt` erstellen/erweitern
- [ ] Standard-Werte definieren
- [ ] Dokumentation in Kommentaren

---

### Phase 2: IP-Prüfung Utility (1h)

#### 2.1 IP-Helper Klasse erstellen
**Datei:** `Classes/Utility/IpAddressUtility.php`

**Funktionen:**
- `isInCampusNetwork(string $ipAddress): bool`
- `ipInRange(string $ip, string $range): bool`
- `getCampusIpRanges(): array`

**Tasks:**
- [ ] Klasse erstellen
- [ ] CIDR-Notation Parser implementieren
- [ ] IPv4 und IPv6 Support
- [ ] Unit Tests schreiben
- [ ] PHPDoc Dokumentation

**Code-Struktur:**
```php
<?php
declare(strict_types=1);

namespace Univie\UniviePure\Utility;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class IpAddressUtility
{
    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration
    ) {}

    public function isInCampusNetwork(?string $ipAddress = null): bool
    {
        // Implementation
    }

    public function ipInRange(string $ip, string $range): bool
    {
        // CIDR notation support
    }

    protected function getCampusIpRanges(): array
    {
        // Read from extension configuration
    }
}
```

---

### Phase 3: Visibility Filter in Endpoints (2h)

#### 3.1 Equipments Endpoint erweitern
**Datei:** `Classes/Endpoints/Equipments.php`

**Tasks:**
- [ ] `IpAddressUtility` per Dependency Injection einbinden
- [ ] `filterByVisibility()` Methode implementieren
- [ ] `transformEquipmentArray()` erweitern
- [ ] Logging für gefilterte Items

**Pseudo-Code:**
```php
protected function transformEquipmentArray(array $equipments, array $settings): array
{
    $array = [];
    $isInCampusNetwork = $this->ipAddressUtility->isInCampusNetwork();

    foreach ($equipments['items'] as $equipment) {
        $visibilityKey = $this->getNestedArrayValue($equipment, 'visibility.key', 'FREE');

        // Filter logic
        if (!$this->isVisibleForCurrentUser($visibilityKey, $isInCampusNetwork)) {
            continue;
        }

        $array['items'][] = $this->processEquipment($equipment, $settings);
    }

    return $array;
}

protected function isVisibleForCurrentUser(string $visibilityKey, bool $isInCampus): bool
{
    return match($visibilityKey) {
        'BACKEND', 'CONFIDENTIAL' => false,
        'RESTRICTED_IP' => $isInCampus,
        'FREE' => true,
        default => true
    };
}
```

#### 3.2 ResearchOutput Endpoint erweitern
**Datei:** `Classes/Endpoints/ResearchOutput.php`

**Tasks:**
- [ ] Gleiche Logik wie bei Equipments
- [ ] `transformArray()` Methode erweitern
- [ ] Cache-Identifier um IP-Status erweitern

#### 3.3 Projects Endpoint erweitern
**Datei:** `Classes/Endpoints/Projects.php`

**Tasks:**
- [ ] Visibility Filter implementieren
- [ ] Cache-Strategie anpassen

#### 3.4 DataSets Endpoint erweitern
**Datei:** `Classes/Endpoints/DataSets.php`

**Tasks:**
- [ ] Visibility Filter implementieren
- [ ] Cache-Strategie anpassen

---

### Phase 4: Caching-Strategie (0.5h)

#### 4.1 Cache-Varianten für IP-basierte Visibility
**Datei:** `Classes/Service/WebService.php`

**Problem:** Cache darf nicht zwischen Campus- und externen Usern geteilt werden.

**Lösung:**
```php
public function generateCacheIdentifier(
    string $endpoint,
    string $uuid,
    ?string $lang = null,
    string $responseType = 'json',
    string $renderer = 'html'
): string {
    $parts = [
        $endpoint,
        $uuid,
        $lang ?? '',
        $responseType,
        $renderer,
        $this->ipAddressUtility->isInCampusNetwork() ? 'campus' : 'external' // NEU
    ];

    return sha1(implode('|', array_filter($parts)));
}
```

**Tasks:**
- [ ] Cache-Identifier erweitern
- [ ] Bestehende Cache-Methoden prüfen
- [ ] Cache-Flush bei IP-Range Änderungen

---

### Phase 5: Testing (1h)

#### 5.1 Unit Tests
**Datei:** `Tests/Unit/Utility/IpAddressUtilityTest.php`

**Test Cases:**
- [ ] IPv4 CIDR matching
- [ ] IPv6 CIDR matching
- [ ] Ungültige IP-Adressen
- [ ] Leere IP-Range Konfiguration
- [ ] Edge Cases (localhost, private ranges)

#### 5.2 Functional Tests
**Datei:** `Tests/Functional/VisibilityFilterTest.php`

**Test Cases:**
- [ ] Equipment mit `FREE` visibility → immer sichtbar
- [ ] Equipment mit `RESTRICTED_IP` + Campus IP → sichtbar
- [ ] Equipment mit `RESTRICTED_IP` + externe IP → nicht sichtbar
- [ ] Equipment mit `BACKEND` → nie sichtbar
- [ ] Cache-Verhalten mit verschiedenen IPs

#### 5.3 Integration Tests
- [ ] Test mit echten Campus IP-Ranges
- [ ] Test mit VPN-Zugriff
- [ ] Test mit externem Zugriff
- [ ] Performance-Test (große Datensätze)

---

### Phase 6: Dokumentation (0.5h)

#### 6.1 Code-Dokumentation
- [ ] PHPDoc für alle neuen Methoden
- [ ] Inline-Kommentare für komplexe Logik
- [ ] CHANGELOG.md aktualisieren

#### 6.2 Admin-Dokumentation
**Datei:** `Documentation/Administrator/CampusIpVisibility.md`

**Inhalte:**
- [ ] Feature-Beschreibung
- [ ] Konfiguration der IP-Ranges
- [ ] Troubleshooting Guide
- [ ] FAQ

**Struktur:**
```markdown
# Campus IP-basierte Visibility

## Überblick
...

## Konfiguration

### Extension Settings
...

### IP-Range Format
...

## Troubleshooting

### Equipment wird nicht angezeigt
...

### IP-Range Änderungen
...
```

#### 6.3 User-Dokumentation
- [ ] Anleitung für Redakteure
- [ ] Screenshots der Pure-Einstellungen
- [ ] Verhaltens-Matrix (Visibility × Benutzertyp)

---

## Deployment Checklist

### Pre-Deployment
- [ ] Code Review durchgeführt
- [ ] Alle Tests erfolgreich (Unit + Functional)
- [ ] Extension Configuration dokumentiert
- [ ] IP-Ranges vom RRZN erhalten und validiert
- [ ] Staging-Test durchgeführt

### Deployment
- [ ] Extension aktualisieren
- [ ] Cache leeren (`typo3 cache:flush`)
- [ ] IP-Ranges in Extension Configuration eintragen
- [ ] Feature aktivieren (`enableIpVisibility = 1`)

### Post-Deployment
- [ ] Funktionstest von Campus-IP
- [ ] Funktionstest von externer IP
- [ ] Monitoring auf Fehler prüfen
- [ ] Feedback von Redakteuren einholen

---

## Risiken & Mitigationen

| Risiko | Wahrscheinlichkeit | Impact | Mitigation |
|--------|-------------------|--------|------------|
| IP-Ranges ändern sich unerwartet | Mittel | Hoch | RRZN-Benachrichtigung + Monitoring |
| False Positives (Campus-User ausgesperrt) | Niedrig | Hoch | Umfangreiche Tests + Logging |
| Cache-Probleme | Mittel | Mittel | Cache-Strategie + Flush-Mechanismus |
| Performance-Impact | Niedrig | Mittel | Caching + Benchmarking |
| IPv6 Kompatibilität | Niedrig | Mittel | IPv6 Support + Tests |

---

## Alternativen (für spätere Diskussion)

### 1. Shibboleth/SSO Integration
**Vorteile:**
- Robuster als IP-basiert
- Keine IP-Range Pflege nötig
- Benutzer-Attribute verfügbar

**Nachteile:**
- Höherer Implementierungsaufwand
- Erfordert Shibboleth-Setup
- Komplexere Architektur

### 2. Reverse Proxy mit IP-Filterung
**Vorteile:**
- Zentrale Verwaltung
- TYPO3-unabhängig
- Performance

**Nachteile:**
- Infrastruktur-Änderungen nötig
- Separate Konfiguration

---

## Changelog

| Datum | Version | Autor | Änderung |
|-------|---------|-------|----------|
| 2026-01-21 | 1.0 | Claude Code | Initial TODO erstellt |

---

## Referenzen

- GitLab Issue: https://scm.rrzn.uni-hannover.de/terv12/univie_pure/-/issues/10
- Pure API Docs: https://api.research-repository.uwa.edu.au/ws/api/524/api-docs/
- TYPO3 v12 Extension Configuration: https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ExtensionArchitecture/ConfigurationOptions/Index.html
