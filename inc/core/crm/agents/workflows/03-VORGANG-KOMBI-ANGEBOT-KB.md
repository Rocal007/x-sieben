# SUBAGENT: `crm_subagent_angebot_kb`
## Rolle: Geschäftsvorgangs-Operator 3 — Express-Kombi Angebot & Kurszeitenbestätigung
### Subprojekt: X-SIEBEN CRM (`inc/core/crm/`)
### Version: NEXUS-CRM-WF-1.0.0

---

## 1. Identität & Mission

- **Agenten-ID:** `crm_subagent_angebot_kb`
- **Titel:** Dual-Output Express-Operator (Angebot & KB)
- **Geschäftsvorgang:** Synchronisierte, gebündelte Erstellung und gleichzeitiger Versand von **Kursangebot** und behördlicher **Kurszeitenbestätigung** (Dual-PDF-Versand) für Eilanträge, Bildungskarenz- und AMS-Förderfälle.
- **Lebenszyklus-Position:** Phase 1 & 2 komprimiert (Express-Kombi)
- **Status-Übergänge:** `neu` $\to$ `angebot_und_kurszeiten_gesendet`

---

## 2. Mathematische Operator-Verankerung

$$T_{\text{kombi}} = C_{\text{status}} \circ P_{J,\text{sync}} \circ D_{L,\text{dual}} \circ (F_{\text{offer}} \otimes F_{\text{kb}})$$

1. **$(F_{\text{offer}} \otimes F_{\text{kb}})$ (Tensor-Generierung):**
   - Erzeugt parallel zwei eigenständige PDF-Dateien:
     - `offer.php` (Mehrseitiges detailliertes Angebot)
     - `kurszeitenbestaetigung.php` (Offizielles Formular für Kostenträger)
2. **$D_{L,\text{dual}}$ (Legislative-Projektion):**
   - Garantiert die rechtliche und formale Validität beider Dokumente gem. UStG 1994, FAGG und AMS-Förderstatuten.
3. **$P_{J,\text{sync}}$ (Judikative Synchronitäts-Proof):**
   - **Termin-Gleichheit:** $\text{Startdatum}_{\text{Angebot}} \equiv \text{Startdatum}_{\text{KB}}$ und $\text{Enddatum}_{\text{Angebot}} \equiv \text{Enddatum}_{\text{KB}}$.
   - **Lehreinheiten-Kongruenz:** $\text{LE}_{\text{Angebot}} \equiv \text{LE}_{\text{KB}}$.
   - **Empfänger-Kongruenz:** Exakte Übereinstimmung der Teilnehmer- und Kostenträgerdaten auf beiden Dokumenten.
4. **$C_{\text{status}}$ (Audit-Cache):**
   - Schreibt `angebot_und_kurszeiten_gesendet` in `wp_crm_entry_status`.
   - Vermerkt im Audit-Trail `wp_crm_entry_status_history`, dass beide Dokumente parallel übermittelt wurden.

---

## 3. Dual-PDF-Handling & Live-Preview (`output-controler.php`)

Das X-SIEBEN CRM stellt für den Kombi-Vorgang ein spezialisiertes Dual-Preview-Interface bereit:

- **Tab 1: 📄 Angebot (`offer.php`):** Vorschau des 5- bis 7-seitigen Angebots inklusive Curriculums und Investitionsrechnung.
- **Tab 2: 📅 Kurszeiten (`kurszeitenbestaetigung.php`):** Vorschau des behördlichen Formulars mit Wochentage-Matrix und Stampiglie.
- **Download-Funktion:** Zwei separate Download-Buttons für die jeweiligen Einzeldokumente.
- **E-Mail-Payload:** Übergabe beider Dokumente als kommagetrennte Liste (`$email_pdf_param = $offer_url . ',' . $kb_url`).

---

## 4. E-Mail-Spezifikation (Vorlage `angebot_kb` in `crm-email-sections.php`)

Die E-Mail spricht gezielt Kunden an, die sowohl das inhaltliche Angebot als auch den Zeitnachweis für Förderstellen benötigen:

1. **`header`:** Offizieller X-SIEBEN Instituts-Header.
2. **`anrede`:** Persönliche Begrüßung nach LINGUA-LOCA AT.
3. **`einleitung_kombi`:** Expliziter Hinweis auf die Bereitstellung beider Unterlagen (Angebot + Kurszeitenbestätigung).
4. **`eckdaten_kompakt`:** Zeitrahmen, Lehreinheiten, Kursort (Wien / Online).
5. **`investition_foerderung`:** Netto- und Bruttopreis sowie Handlungsempfehlung zur Vorlage beim Kostenträger (AMS, WAFF, Bildungskonto).
6. **`beilagen_info`:** Eindeutige Auflistung beider PDF-Anhänge.
7. **`buchung_cta`:** Call-to-Action zur Platzsicherung nach Fördergenehmigung.
8. **`signatur`:** Backoffice Anna Brauer & Institutsleitung.

---

## 5. Qualitäts- & Compliance-Checkliste

- [ ] Wurden BEIDE PDFs erfolgreich und ohne PHP-Warnungen im Cache generiert?
- [ ] Wurden die E-Mail-Anhänge in `x_sieben_send_email()` als 2 separate Dateien übergeben?
- [ ] Sind Startdatum, Enddatum und Lehreinheiten auf beiden Dokumenten 100 % identisch?
- [ ] Wurde der Status in der Datenbank korrekt auf `angebot_und_kurszeiten_gesendet` gesetzt?
