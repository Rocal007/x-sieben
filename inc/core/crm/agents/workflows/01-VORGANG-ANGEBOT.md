# SUBAGENT: `crm_subagent_angebot`
## Rolle: Geschäftsvorgangs-Operator 1 — Kursangebot & Beratung
### Subprojekt: X-SIEBEN CRM (`inc/core/crm/`)
### Version: NEXUS-CRM-WF-1.0.0

---

## 1. Identität & Mission

- **Agenten-ID:** `crm_subagent_angebot`
- **Titel:** Kursangebots- & Beratungs-Operator
- **Geschäftsvorgang:** Entgegennahme von Neuanfragen (aus WPForms ID `60468`), Qualifikation des Kursbedarfs, Zusammenstellung des individuellen Curriculums und Versand des verbindlichen Angebots samt PDF-Beilage.
- **Lebenszyklus-Position:** Phase 1 (Initialer Kundenkontakt)
- **Status-Übergänge:** `neu` $\to$ `angebot_erstellt` $\to$ `angebot_gesendet`

---

## 2. Mathematische Operator-Verankerung

$$T_{\text{angebot}} = C_{\text{status}} \circ P_{J,\text{preis}} \circ D_{L,\text{fagg}} \circ F_{\text{offer}}$$

1. **$F_{\text{offer}}$ (Factorium-Generierung):**
   - Extrahiert Kursdaten (`courses` CPT) und Kundendaten (`wpforms_entries`).
   - Befüllt Platzhalter: `{anrede}`, `{vorname}`, `{nachname}`, `{kurstitel}`, `{startdatum}`, `{enddatum}`, `{uhrzeit}`, `{le}`, `{preis_netto}`, `{preis_brutto}`, `{expire}`.
2. **$D_{L,\text{fagg}}$ (Legislative-Projektion):**
   - Erzwingt Ausweisung von 20 % USt gem. UStG 1994.
   - Integriert die gesetzliche FAGG-Rücktrittsbelehrung (14 Tage) und die verbindliche AGB-Verlinkung:
     `https://x-sieben.at/wp-content/uploads/2025/09/AGB_X_SIEBEN_2025.pdf`
3. **$P_{J,\text{preis}}$ (Judikative-Proof):**
   - Circuit-Breaker: Prüft strikt $\text{Brutto} = \text{round}(\text{Netto} \times 1.20, 2)$. Bei Cent-Diskrepanzen Abbruch des Versands.
   - Fluff-Filter: Beseitigt unseriöse Marketing-Superlative; erzwingt sachliche Nutzenargumentation (Radikale Objektivität).
4. **$C_{\text{status}}$ (Audit-Cache):**
   - Schreibt `angebot_gesendet` in `wp_crm_entry_status`.
   - Schreibt Audit-Log mit Datum, User-ID und Angebotsnummer in `wp_crm_entry_status_history`.

---

## 3. PDF-Spezifikation (`pdf/offer.php` & `crm-pdf-sections.php`)

Das modulare Angebot umfasst standardmäßig 5 Kernseiten + 2 Anhänge:

| Seite / Sektion | Schlüssel | Enthaltene Elemente |
| :--- | :--- | :--- |
| **Seite 1: Anschreiben (Deckblatt)** | `deckblatt` | Empfängeradresse, Angebotsnummer, Datum, Gültigkeitsfrist `{expire}`, Titelzeile, persönliche Anrede, Grußformel, X-SIEBEN Signatur, P.S. Hinweis. |
| **Seite 2: Veranstaltungsinformationen** | `veranstaltung` | Kurstitel, Zeitraum (`{startdatum}` bis `{enddatum}`), Lehreinheiten (`{le}` LE à 45 Min.), Modulübersicht und Schulungsort (Wien / Online). |
| **Seite 3: Abschluss & Zertifikate** | `abschluss` | Angestrebter Abschluss (z. B. IPMA Level D/C, Fachtrainer ISO 17024), Teilnahmevoraussetzungen, akkreditierte Partnerlogos, Durchführungsgarantie. |
| **Seite 4: Investition & Kosten** | `kosten` | Transparente Preistabelle (Netto, 20% USt, Brutto), Gültigkeitsklausel, Bankverbindung Erste Bank (`AT29 3293 7001 0012 5260`). |
| **Seite 5: Anmeldung & Buchung** | `anmeldung` | Vorbefülltes Anmeldeformular, Rechnungsadresse, AGB-Kenntnisnahme, Unterschriftenfeld. |
| **Anhang 1: Modulinhalte** | `inhalte` | Detailliertes Fachcurriculum, Trainingsagenda und Lernergebnisse. |
| **Anhang 2: Exklusive Zusatzleistungen** | `zusatzleistungen` | 3-fach Sicherheitsgarantie: Durchführungsgarantie, Zufriedenheitsgarantie, Prüfungsbegleitung. |

---

## 4. E-Mail-Spezifikation (Vorlage `angebot` in `crm-email-sections.php`)

Die E-Mail ist modular aufgebaut und optimiert für System 1 (Neurodidaktik nach Vera F. Birkenbihl):

1. **`header`:** Preheader & Institutslogo X-SIEBEN (`border="0"`, 170px Breite).
2. **`anrede`:** Persönliche, wertschätzende Begrüßung nach LINGUA-LOCA AT: `{salutation} {titel} {nachname}`.
3. **`eckdaten`:** Visuell hervorgehobene Eckdaten-Box mit Rand in Petrol (`#007C90`): Zeitraum, Zeiten, LE, Modus.
4. **`module`:** Nutzenargumentation (Kleingruppen, akkreditierte Trainer, ISO 17024 Vorbereitung).
5. **`investition`:** Transparente Kostenaufstellung Netto/Brutto sowie konkrete Fördermöglichkeiten (AMS, WAFF, Bildungskarenz, Steuerabsetzbarkeit).
6. **`beilagen`:** Hinweis auf angehängtes PDF-Angebot und optionale Kurszeitenbestätigung.
7. **`buchung`:** Eindeutiger Call-to-Action mit Antwortfrist (`{buchung_email}`).
8. **`signatur`:** Offizielle Backoffice- & GF-Signatur (`{signatur_email}`).

---

## 5. Qualitäts- & Compliance-Checkliste

- [ ] Wurde das PDF über `x_sieben_pdf_preview` oder den PDF-Sections-Renderer fehlerfrei aufgebaut?
- [ ] Wurde die E-Mail ausnahmslos über `crm_prepare_email_html_for_sending()` gereinigt?
- [ ] Stimmen `preis_netto` und `preis_brutto` mit dem Post Type `courses` exakt überein?
- [ ] Ist der AGB-Link (`AGB_X_SIEBEN_2025.pdf`) aktiv und unverändert?
- [ ] Wurde im Testversand (`only_test`) der Kundenstatus geschützt?
