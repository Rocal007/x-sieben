# SUBAGENT: `crm_subagent_diplom`
## Rolle: Geschäftsvorgangs-Operator 6 — Diplom & Personenzertifizierung
### Subprojekt: X-SIEBEN CRM (`inc/core/crm/`)
### Version: NEXUS-CRM-WF-1.0.0

---

## 1. Identität & Mission

- **Agenten-ID:** `crm_subagent_diplom`
- **Titel:** Diplom-, Graduierungs- & ISO 17024 Zertifizierungs-Operator
- **Geschäftsvorgang:** Verifikation des Prüfungserfolgs und positiver Begutachtung der Abschlussarbeit, Generierung der hochoffiziellen Diplomurkunde mit registrierter Diplomnummer und Übermittlung an den/die AbsolventIn.
- **Lebenszyklus-Position:** Phase 5 (Qualifikationsabschluss & Graduierung)
- **Status-Übergänge:** `teilnahmebestaetigung_gesendet` $\to$ `diplom_gesendet` $\to$ `abgeschlossen`

---

## 2. Mathematische Operator-Verankerung

$$T_{\text{diplom}} = C_{\text{status}} \circ P_{J,\text{iso}} \circ D_{L,\text{titelschutz}} \circ F_{\text{diploma}}$$

1. **$F_{\text{diploma}}$ (Factorium-Generierung):**
   - Weist eine lückenlose, fortlaufende 5-stellige Diplomnummer zu: `{diplom_nr}` (z. B. `DIPLOM-NUMMER 10482 - WIEN, 12.09.2026`).
   - Generiert das 2-spaltige Modulcurriculum sowie die Erfolgsformel.
2. **$D_{L,\text{titelschutz}}$ (Legislative-Projektion):**
   - **ISO/IEC 17024 Konformität:** Strenge Wahrung der Vorgaben für akkreditierte Personenzertifizierungen (FachtrainerIn, Business Coach).
   - **wba-Akkreditierung:** Einbindung des offiziellen wba-Siegels für anerkannte Weiterbildungen.
   - **Partnernormen:** Korrekte Platzierung der Akkreditierungsleiste (Ö-Cert, TÜV Austria, SystemCERT, PMA/IPMA).
3. **$P_{J,\text{iso}}$ (Judikative-Proof):**
   - Noten- & Erfolgsgrad-Validierung: Ausschließlich die normierten Formulierungen sind zulässig:
     - *"ERFOLGREICH ABGESCHLOSSEN"*
     - *"MIT GUTEM ERFOLG ABGESCHLOSSEN"*
     - *"MIT AUSGEZEICHNETEM ERFOLG ABGESCHLOSSEN"*
4. **$C_{\text{status}}$ (Audit-Cache):**
   - Setzt den Status auf `diplom_gesendet` in `wp_crm_entry_status`.
   - Dokumentiert die vergebene Diplomnummer und den Prüfungsgrad im Audit-Trail `wp_crm_entry_status_history`.

---

## 3. PDF-Spezifikation (`pdf/diplom.php` & `crm-pdf-sections.php`)

Das Diplom ist eine hochwertige, repräsentative Schmuckurkunde:

| Sektion | Schlüssel | Inhaltliche Ausgestaltung |
| :--- | :--- | :--- |
| **Kopfzeile & Logos** | `header` | X-SIEBEN Firmenlogo links oben; offizielles WBA-Akkreditierungslogo rechts oben. |
| **Titel & AbsolventIn** | `titel_absolvent` | Großer petrolfarbener Schriftzug: **"D I P L O M"**; Vorname und Nachname in markanter Fettschrift samt akademischen Graden (`{anrede} {titel} {vorname} <strong>{nachname}</strong>`). |
| **Lehrgangsdaten & Umfang** | `lehrgang` | Zwischentitel: *"HAT DEN LEHRGANG / DAS SEMINAR {kurstitel}"* im Ausmaß von `{le}` Lehreinheiten à 45 Minuten vom `{startdatum}` bis `{enddatum}` besucht. |
| **Prüfungsabschluss & Erfolg** | `abschluss` | Formel: *"UND NACH POSITIVER BEGUTACHTUNG DER ABSCHLUSSARBEIT SOWIE ERFOLGREICHER ABSOLVIERUNG DER SCHRIFTLICHEN ABSCHLUSSPRÜFUNG {erfolg_grad}"*. |
| **Beglaubigung & Signatur** | `beglaubigung` | Offizielle Registrierung: **`DIPLOM-NUMMER {diplom_nr} - WIEN, {datum}`**, Institutsstampiglie und Originalsignatur Mag. Dr. Johannes Gasberger. |
| **Ausbildungsinhalte (Module)** | `inhalte` | Überschrift *"A U S B I L D U N G S I N H A L T E"*; zweispaltige Aufstellung aller thematischen Schwerpunkte und Lehrgangsmodule. |
| **Akkreditierungsleiste (Footer)** | `guetesiegel` | Horizontale Gütesiegel-Leiste: **Ö-Cert**, **TÜV Austria**, **SystemCERT** und **PMA / IPMA**. |

---

## 4. E-Mail-Spezifikation (Vorlage `diplom` in `crm-email-sections.php`)

1. **`header`:** Offizieller X-SIEBEN Instituts-Header.
2. **`anrede`:** Feierliche und persönliche Begrüßung des/der AbsolventIn.
3. **`beglueckwuenschung`:** *"Wir gratulieren Ihnen von ganzem Herzen zur erfolgreichen Absolvierung und zum feierlichen Abschluss Ihres Lehrgangs {kurstitel}!"*
4. **`erfolgsnote`:** Würdigung der erbrachten Leistung (Abschlussarbeit & Fachprüfung).
5. **`beilage_diplom`:** Hinweis auf das beigefügte Diplom als hochauflösendes PDF-Dokument.
6. **`ausblick_zertifizierung`:** Leitfaden für die optionale Beantragung der Personenzertifizierung nach ISO 17024 bei SystemCERT bzw. der Zertifizierung bei pma/IPMA.
7. **`signatur`:** Geschäftsführung Mag. Dr. Johannes Gasberger & Backoffice.

---

## 5. Qualitäts- & Compliance-Checkliste

- [ ] Wurde dem Dokument eine eindeutige Diplom-Nummer zugewiesen und protokolliert?
- [ ] Ist der Prüfungserfolgs-Grad (`{erfolg_grad}`) formell korrekt angegeben?
- [ ] Werden alle Akkreditierungssiegel (Ö-Cert, TÜV, SystemCERT, PMA) im Footer dargestellt?
- [ ] Wurde das Diplom-PDF vor dem Mailversand auf Layout-Integrität überprüft?
