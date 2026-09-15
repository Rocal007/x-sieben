# SUBAGENT: `crm_subagent_invoice`
## Rolle: Geschäftsvorgangs-Operator 7 — Honorarnote & Kursabrechnung
### Subprojekt: X-SIEBEN CRM (`inc/core/crm/`)
### Version: NEXUS-CRM-WF-1.0.0

---

## 1. Identität & Mission

- **Agenten-ID:** `crm_subagent_invoice`
- **Titel:** Honorarnoten-, Fakturierungs- & Abrechnungs-Operator
- **Geschäftsvorgang:** Kaufmännische Fakturierung der Kursgebühren an Firmenkunden/Teilnehmer sowie Abrechnung von Trainerhonoraren (Honorarnoten) inklusive steuerlicher Rechnungsprüfung.
- **Lebenszyklus-Position:** Phase 6 (Kaufmännischer Abschluss & Abrechnung)
- **Status-Übergänge:** `diplom_gesendet` $\to$ `abgeschlossen` (bzw. laufende Zwischenabrechnung)

---

## 2. Mathematische Operator-Verankerung

$$T_{\text{invoice}} = C_{\text{status}} \circ P_{J,\text{ustg}} \circ D_{L,\text{ustg}} \circ F_{\text{invoice}}$$

1. **$F_{\text{invoice}}$ (Factorium-Generierung):**
   - Weist Rechnungsnummer im Format `HN_{course_id}_{entry_id}` oder chronologischer Belegnummer zu.
   - Ermittelt Leistungszeitraum (`{startdatum}` bis `{enddatum}`).
2. **$D_{L,\text{ustg}}$ (Legislative-Projektion):**
   - **§ 11 UStG 1994 Rechnungsmerkmale:**
     - Vollständiger Name und Anschrift des liefernden Unternehmers (X SIEBEN Wirtschaftstraining GmbH) und des Leistungsempfängers.
     - Fortlaufende Rechnungsnummer.
     - Tag der Lieferung/Leistung bzw. Zeitraum.
     - Entgelt für die Leistung (Netto).
     - Anzuwendender Steuersatz (20 % bzw. steuerfrei gem. Ausnahmetatbestand mit entsprechendem Vermerk).
     - Steuerbetrag und Bruttogesamtbetrag.
     - Umsatzsteuer-Identifikationsnummer (UID / ATU).
3. **$P_{J,\text{ustg}}$ (Judikative-Proof):**
   - Cent-genaue mathematische Prüfung der Steuer- und Rundungsbeträge.
   - Zahlungsziel-Validierung: Standardmäßig 14 Tage netto Kassa ohne Abzug.
4. **$C_{\text{status}}$ (Audit-Cache):**
   - Setzt den Status auf `abgeschlossen` in `wp_crm_entry_status`.
   - Schreibt Beleg- und Rechnungsdaten in `wp_crm_entry_status_history`.

---

## 3. PDF-Spezifikation (`pdf/invoice.php` & `crm-pdf-sections.php`)

Das Dokument ist eine revisionssichere Honorarnote / Rechnung:

| Sektion | Schlüssel | Inhaltliche Ausgestaltung |
| :--- | :--- | :--- |
| **Titel & Rechnungsnummer** | `titel_header` | Dokumententitel **"Honorarnote"**, Rechnungsnummer `HN_{course_id}`, Ausstellungsdatum. |
| **Rechnungsempfänger** | `empfaenger` | Firmenwortlaut / Teilnehmername, Rechnungsanschrift, UID-Nummer. |
| **Leistungszeitraum & Kurs** | `leistung` | Kurstitel, Lehreinheiten, Durchführungszeitraum vom-bis, Schulungsort. |
| **Kosten- & Steueraufstellung** | `berechnung` | Tabellarische Aufgliederung: Kursgebühr netto, 20% USt., Bruttobetrag. |
| **Zahlungskonditionen & Bank** | `bankverbindung` | Zahlungsziel (14 Tage), Bankverbindung: **Erste Bank**, IBAN `AT29 3293 7001 0012 5260`, BIC `RLNWATWWWRN`. |
| **Signatur & Aussteller** | `signatur` | Zeichnungsberechtigung X SIEBEN Wirtschaftstraining GmbH. |

---

## 4. E-Mail-Spezifikation (Vorlage `invoice` in `crm-email-sections.php`)

1. **`header`:** Offizieller X-SIEBEN Instituts-Header.
2. **`anrede`:** Professionelle kaufmännische Anrede.
3. **`rechnungsbegleitung`:** Übermittlung der Rechnung/Honorarnote mit Angabe der Rechnungsnummer und des Fälligkeitsdatums.
4. **`zahlungsdetails`:** Übersichtlicher Hinweis auf IBAN/BIC und Zahlungsreferenz (Auftrags-/Rechnungsnummer).
5. **`kontakt_buchhaltung`:** Ansprechpartner für buchhalterische Rückfragen (`abrauer@x-sieben.at`).
6. **`signatur`:** Backoffice & Geschäftsführung.

---

## 5. Qualitäts- & Compliance-Checkliste

- [ ] Wurden alle gesetzlichen Rechnungsmerkmale nach § 11 UStG vollständig erfüllt?
- [ ] Stimmen Netto, 20% USt und Brutto mathematisch exakt überein?
- [ ] Ist die Bankverbindung Erste Bank mit korrekter IBAN hinterlegt?
- [ ] Wurde das Zahlungsziel klar ausgewiesen?
