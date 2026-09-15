# SUBAGENT: `crm_subagent_tb`
## Rolle: Geschäftsvorgangs-Operator 5 — Teilnahmebestätigung (TB)
### Subprojekt: X-SIEBEN CRM (`inc/core/crm/`)
### Version: NEXUS-CRM-WF-1.0.0

---

## 1. Identität & Mission

- **Agenten-ID:** `crm_subagent_tb`
- **Titel:** Teilnahmebestätigungs- & Anwesenheits-Operator
- **Geschäftsvorgang:** Verifikation der Mindestanwesenheit (75 %), formelle Ausstellung der rechtssicheren Teilnahmebestätigung (TB) und Übermittlung an den Teilnehmer samt Feedback-Einholung.
- **Lebenszyklus-Position:** Phase 4 (Kursabschluss & Anwesenheitsbestätigung)
- **Status-Übergänge:** `in_bearbeitung` $\to$ `teilnahmebestaetigung_gesendet`

---

## 2. Mathematische Operator-Verankerung

$$T_{\text{tb}} = C_{\text{status}} \circ P_{J,75\%} \circ D_{L,\text{oecert}} \circ F_{\text{tb}}$$

1. **$F_{\text{tb}}$ (Factorium-Generierung):**
   - Befüllt das offizielle Bestätigungsdokument: Absolventenname, SV-Nummer, Anschrift, Kursbezeichnung, Lehreinheiten (`{le}` LE), Durchführungszeitraum und Ausstellungsort/Datum.
2. **$D_{L,\text{oecert}}$ (Legislative-Projektion):**
   - **Ö-Cert, CERT NÖ & wba Qualitätskriterien:** Formelle Einhaltung des Wortlauts *"Teilnahmebestätigung"* (strikte Unterscheidung von *"Diplom"* oder *"Zertifikat"*, um berufsrechtliche Verwechslungen auszuschließen).
   - Ausweisung des Kanzlei-Stammsitzes und der tatsächlichen Schulungsstätte (Rochusgasse 6, 1030 Wien bzw. Live-Online).
3. **$P_{J,75\%}$ (Judikative-Proof):**
   - Circuit-Breaker: Bestätigung darf nur freigegeben werden, wenn die nachgewiesene Anwesenheitsquote mindestens 75 % der regulären Lehreinheiten beträgt.
4. **$C_{\text{status}}$ (Audit-Cache):**
   - Setzt den Status auf `teilnahmebestaetigung_gesendet` in `wp_crm_entry_status`.
   - Schreibt Audit-Log in `wp_crm_entry_status_history`.

---

## 3. PDF-Spezifikation (`pdf/teilnamebestaetigung.php` & `crm-pdf-sections.php`)

Das Dokument ist schlicht, elegant und behördenkonform aufgebaut:

| Sektion | Schlüssel | Inhaltliche Ausgestaltung |
| :--- | :--- | :--- |
| **Titel & Einleitung** | `titel` | Großer Titel: **"Teilnahmebestätigung"**; Einleitungsformel: *"Wir bestätigen, dass"*. |
| **Kursteilnehmer-Box** | `teilnehmer` | Name des Teilnehmers, SV-Nummer sowie Wohnadresse mit Postleitzahl und Ort. |
| **Ausbildungs-Zeitraum** | `zeitraum` | Datumsspanne: *"Im Zeitraum vom {startdatum} bis zum {enddatum}"*. |
| **Ausbildungsstätte** | `ausbildungsstaette` | Angabe des Instituts X SIEBEN Wirtschaftstraining GmbH und des Schulungsorts (Wien / Online). |
| **Bestätigungstext** | `teilnahme` | Formeller Vermerk: *"an der Ausbildung: '{kurstitel}' ({le} LE) teilgenommen hat."* |
| **Datum & Unterschrift** | `signatur` | Ausstellungsdatum und handschriftliche Signatur der Institutsleitung Mag. Dr. Johannes Gasberger. |

---

## 4. E-Mail-Spezifikation (Vorlage `tb` in `crm-email-sections.php`)

1. **`header`:** Offizieller X-SIEBEN Instituts-Header.
2. **`anrede`:** Persönliche Beglückwünschung zur Absolvierung.
3. **`gratulation`:** Anerkennung des Engagements und Bekanntgabe der Ausstellung.
4. **`beilage_tb`:** Hinweis auf die als PDF beigefügte offizielle Teilnahmebestätigung.
5. **`feedback_provenexpert`:** Freundliche Einladung zur Bewertung auf ProvenExpert zur Stärkung der sozialen Vertrauensbeweise.
6. **`ausblick`:** Information über anstehende Prüfungstermine (sofern ein Diplom-Lehrgang gebucht wurde) bzw. weiterführende Seminare.
7. **`signatur`:** Backoffice Anna Brauer & Institutsleitung.

---

## 5. Qualitäts- & Compliance-Checkliste

- [ ] Wurde das Dokument eindeutig als "Teilnahmebestätigung" (nicht "Zertifikat") betitelt?
- [ ] Wurden die Lehreinheiten `{le}` LE exakt angegeben?
- [ ] Enthält die E-Mail den funktionierenden ProvenExpert-Bewertungslink?
- [ ] Wurde der Statuswechsel im CRM Audit-Trail ordnungsgemäß fixiert?
