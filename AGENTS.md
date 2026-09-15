# AGENTS.md — X-SIEBEN CRM & Kurs-Spezialist

Dieses Dokument definiert die Richtlinien, Architekturvorgaben und das Domänenwissen für KI-Agenten, die am WordPress-Theme `sieben` und insbesondere am integrierten CRM-System für die Kurse der **X SIEBEN Wirtschaftstraining GmbH** arbeiten.

---

## 1. Identität & Mission des Agenten (`xsieben_crm_agent`)

- **Name:** `xsieben_crm_agent` (X7 CRM & Course Operator)
- **Domäne:** X SIEBEN Wirtschaftstraining GmbH ([x-sieben.at](https://x-sieben.at))
- **Standorte:** Wien (Rochusgasse 6, 1030 Wien), Zentrale (Kurzegasse 7, 2493 Lichtenwörth / Wr. Neustadt)
- **Geschäftsführung:** Mag. Dr. Johannes Gasberger | **Backoffice:** Anna Brauer (`abrauer@x-sieben.at`)
- **Kernkompetenz:** Weiterbildung, Seminare, Lehrgänge und Personenzertifizierungen in:
  - **Projektmanagement:** IPMA® / pma (Level D, C, B)
  - **Agiles Management:** Scrum, Kanban, Agile Coach, SAFe
  - **Führung, Coaching, Digitale Transformation & KI**
  - **ISO 17024 Personenzertifizierungen:** FachtrainerIn, Business Coach
  - **Akkreditierungen & Partner:** pma / IPMA, SystemCERT, TÜV Austria, wba, CERT NÖ, AMS

---

## 2. Technische Kernarchitektur (`inc/core/crm/`)

### A. Autarkie-Gebot (Standalone-Prinzip)
Der gesamte Ordner `wp-content/themes/sieben/inc/core/crm/` muss **100 % autark** und ohne externe Theme-Abhängigkeiten lauffähig sein. Er muss durch einfaches Kopieren auf den Live-Server voll funktionsfähig bleiben.

### B. Datenmodell (`crm-model.php`)
- Verknüpft den Custom Post Type `courses` mit WPForms-Einträgen (Formular-ID: `60468`).
- Extrahiert Kurstitel, Modulinhalte, Termine/Zeiten, Preise (Netto/Brutto), Trainer und Zertifizierungs-Badges.
- **Wichtigste Regel:** E-Mail-Felder (`signatur_email`, `angebot_email`, `anmeldung_email` etc.) dürfen **NIEMALS** über `apply_filters('the_content', ...)` gefiltert werden!
  - *Grund:* Globale Theme-Filter (z. B. in `functions.php`) schneiden die Domain aus Links/Bildern ab und erzeugen unbrauchbare relative URLs (`/wp-content/...`), die in E-Mail-Clients (Gmail, Outlook) zu Bildausfällen führen.
  - *Korrekt:* `wpautop()` + `do_shortcode()` + `crm_prepare_email_html_for_sending()` verwenden.

### C. E-Mail-Normalisierung & Versand (`helpers/normalize.php` & `controler/output-controler.php`)
- Jede ausgehende E-Mail muss zwingend durch `crm_prepare_email_html_for_sending($body)` laufen:
  1. **HTTPS-Kanonisierung:** Alle Bildquellen (`src`) und Links (`href`) mit relativen Pfaden (`/wp-content/...`), Test-Domains (`x-sieben.test`) oder Weiterleitungs-Domains (`www.x-sieben.at`) werden automatisch in saubere, öffentliche HTTPS-URLs umgeschrieben: `https://x-sieben.at/...`.
  2. **Cookie-Consent-Restoration:** Attribute wie `consent-original-src-_` (von Real Cookie Banner) werden in echte `src`-Attribute zurückverwandelt; Consent-Tracking-Attribute werden entfernt.
  3. **Emoji-Smiley-Fix:** WordPress-Smiley-Grafiken (z. B. `🧪`) werden wieder in native Unicode-Textzeichen umgewandelt.
  4. **Client-Kompatibilität:** Automatische Ergänzung von `border="0"` für saubere Outlook-Darstellung.

### D. Status- & Audit-Engine (`helpers/crm-status.php`)
- Verwaltet die Datenbanktabellen `wp_crm_entry_status` und `wp_crm_entry_status_history`.
- Erstellt und migriert Tabellen automatisch via `dbDelta()`.
- **Statuswerte:**
  - `neu` (Neu eingegangen)
  - `angebot_gesendet` (Angebot gesendet)
  - `kurszeitenbestaetigung_gesendet` (Kurszeitenbestätigung gesendet)
  - `angebot_kurszeiten_gesendet` (Angebot & KB gesendet)
  - `anmeldung_gesendet` (Anmeldung gesendet)
  - `teilnahmebestaetigung_gesendet` (Teilnahmebestätigung gesendet)
  - `diplom_gesendet` (Diplom gesendet)
  - `in_bearbeitung`, `abgeschlossen`, `storniert`
  - `test_mail_gesendet` (Protokolleintrag im Audit-Trail)
- **Test-Mail-Logik:**
  - `Nur an Test-Empfänger`: Kunde erhält nichts, Kundenstatus in der Tabelle bleibt unberührt, Aktion wird in der Historie vermerkt.
  - `An Test & Kunde gleichzeitig`: Beide erhalten die Mail, Status wird aktualisiert.

### E. Frontend, Versionierung & Cache-Busting (`crm-admin.php`, `assets/crm-admin.js`, `helpers/crm-cache.php`)
- **Version:** Definiert über Konstante `CRM_VERSION` (aktuell: `Version 2.18.15`).
- **Anzeige:** Dezent oben im Header (`[🏷️ Version 2.18.15]`) und in den CRM-Settings.
- **Automatisches JS-Cache-Clean mit Flag:** Gesteuert über das Flag `crm_auto_js_cache_clean` (Einstellungen / Konstante `CRM_AUTO_JS_CACHE_CLEAN` / Request). Wird **nur bei partiellem Cache-Update** ausgeführt und invalidiert Browser-Assets via `crm_get_asset_version()` sowie clientseitig über `window.crmJsCache.cleanPartial()`.

### F. Modulare Dokument- & E-Mail-Editoren
- **PDF-Editor (`helpers/crm-pdf-sections.php`):** 5 Dokumenttypen (Angebot, KB, TB, Diplom, Honorarnote) mit modularer Drag-&-Drop-Gliederung und asynchroner Live-Vorschau.
- **E-Mail-Editor (`helpers/crm-email-sections.php` & `crm_email_editor_agent`):** 7 E-Mail-Typen (`angebot`, `kb`, `angebot_kb`, `anmeldung`, `tb`, `diplom`, `invoice`), modulare Blöcke & Unterabschnitte, Drag-&-Drop-Reihenfolge, Inline-Editor und asynchrone E-Mail-Live-Vorschau (Desktop 600px / Mobile 375px). Vollständig dokumentiert in [inc/core/crm/AGENT-EMAIL-EDITOR.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/AGENT-EMAIL-EDITOR.md).

### G. NEXUS CRM Agenten-Hierarchie (Subprojekt-Operatoren)
Das CRM wird als autarkes Subprojekt nach den NEXUS-Protokollen ($T = C \circ P_J \circ D_L \circ F$) durch 7 spezialisierte Agenten gesteuert (vollständige Matrix in [inc/core/crm/NEXUS-AGENTS.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/NEXUS-AGENTS.md)):
1. **`crm_nexus_architect`**: System-Architektur, Standalone-Autarkie, MVC, Schema & Versionierung ([ARCHITECT.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/ARCHITECT.md))
2. **`crm_nexus_ux`**: Birkenbihl-Neurodidaktik ($W_{\text{aktiv}}$), Cognitive Fluency (System 1/2), Live-Previews Desktop (600px) / Mobile (375px) ([UX-NEURODIDAKTIK.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/UX-NEURODIDAKTIK.md))
3. **`crm_nexus_legislative`**: ISO 17024, IPMA/pma, AMS, GewO 1994, AGB-Schutz ([LEGISLATIVE.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/LEGISLATIVE.md))
4. **`crm_nexus_judikative`**: Proof-Validator ($P_J$), Circuit-Breaker ($V_{\text{gate}}$), Fluff-Filter ($\mathcal{V}_{\text{forbidden}}$), Steuerprüfungen ([JUDIKATIVE.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/JUDIKATIVE.md))
5. **`crm_nexus_lingua`**: Österreich-Standard ($\Lambda_{\text{local}}$), Cicero-7Q, Amstad $FRE_{\text{norm}}$ ([LINGUA-LOCA.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/LINGUA-LOCA.md))
6. **`crm_nexus_factorium`**: Output-Pipeline (7 Mails, 5 PDFs), HTTPS-Kanonisierung ([FACTORIUM.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/FACTORIUM.md))
7. **`crm_nexus_audit`**: Fixpunkt-Stabilität $T(X^*)=X^*$, Audit-Trail, Dual-Mode Testversand ([AUDIT-FIXPOINT.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/AUDIT-FIXPOINT.md))

### H. Geschäftsvorgangs-Subagenten (`inc/core/crm/agents/workflows/`)
Für jeden einzelnen operativen Geschäftsvorgang vom Erstkontakt bis zur Diplomverleihung existiert ein dedizierter Prozess-Subagent:
1. **`crm_subagent_orchestrator`**: Leitender Workflow- und Lifecycle-Orchestrator ([00-ORCHESTRATOR.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/workflows/00-ORCHESTRATOR.md))
2. **`crm_subagent_angebot`**: Vorgang 1 — Kursangebot & Beratung (PDF `offer.php`, E-Mail `angebot`, FAGG, 20% USt) ([01-VORGANG-ANGEBOT.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/workflows/01-VORGANG-ANGEBOT.md))
3. **`crm_subagent_kb`**: Vorgang 2 — Kurszeitenbestätigung AMS & Förderstellen (PDF `kurszeitenbestaetigung.php`, E-Mail `kb`, SV-Nr.) ([02-VORGANG-KURSZEITENBESTAETIGUNG.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/workflows/02-VORGANG-KURSZEITENBESTAETIGUNG.md))
4. **`crm_subagent_angebot_kb`**: Vorgang 3 — Express-Kombi Angebot & KB (Dual-PDF, E-Mail `angebot_kb`, Terminsynchronität) ([03-VORGANG-KOMBI-ANGEBOT-KB.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/workflows/03-VORGANG-KOMBI-ANGEBOT-KB.md))
5. **`crm_subagent_anmeldung`**: Vorgang 4 — Anmeldung, Buchung & Onboarding (Anmeldebeleg, E-Mail `anmeldung`, ABGB, Termin-Snapshot) ([04-VORGANG-ANMELDUNG.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/workflows/04-VORGANG-ANMELDUNG.md))
6. **`crm_subagent_tb`**: Vorgang 5 — Teilnahmebestätigung (PDF `teilnamebestaetigung.php`, E-Mail `tb`, 75% Anwesenheit, Ö-Cert) ([05-VORGANG-TEILNAHMEBESTAETIGUNG.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/workflows/05-VORGANG-TEILNAHMEBESTAETIGUNG.md))
7. **`crm_subagent_diplom`**: Vorgang 6 — Diplom & Personenzertifizierung (PDF `diplom.php`, E-Mail `diplom`, Diplom-Nr., ISO 17024, SystemCERT) ([06-VORGANG-DIPLOM.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/workflows/06-VORGANG-DIPLOM.md))
8. **`crm_subagent_invoice`**: Vorgang 7 — Honorarnote & Abrechnung (PDF `invoice.php`, E-Mail `invoice`, § 11 UStG) ([07-VORGANG-HONORARNOTE.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/workflows/07-VORGANG-HONORARNOTE.md))



---

## 3. Sprach-, Stil- & Rechts-Standards (NEXUS LINGUA-LOCA AT)

1. **Österreichische Wirtschaftssprache:**
   - Professionelle, verbindliche Nahbarkeit ("Sehr geehrte Frau / Sehr geehrter Herr").
   - "Rückruf durch unser Backoffice / Kanzleiteam" (statt "Zentrale").
   - Österreichische Begriffe: "Teilnahmebestätigung", "Lehrgang", "Kurszeiten", "Aktenzahl", "Räumung", "Marillen".
   - Keine bundesdeutschen Floskeln ("lecker", "gucken", "schauen Sie mal vorbei").
2. **Radikale Objektivität (RO):**
   - Sachliche Darstellung von Kursinhalten, Terminen, Trainern und Akkreditierungen.
   - Keine übertriebenen Marketing-Versprechen oder Floskeln.
3. **Rechtliche Verlinkungen:**
   - AGB: `https://x-sieben.at/wp-content/uploads/2025/09/AGB_X_SIEBEN_2025.pdf`
   - Datenschutz: `https://x-sieben.at/datenschutzerklaerung/`

---

## 4. Checkliste für Änderungen & Wartung

- [ ] Wurden alle Änderungen innerhalb von `inc/core/crm/` durchgeführt?
- [ ] Werden alle Bildquellen und Links über `crm_prepare_email_html_for_sending()` abgesichert?
- [ ] Bestehen alle PHP-Dateien die Syntaxprüfung (`php -l`)?
- [ ] Wurde die automatisierte SDS-Test-Suite erfolgreich ausgeführt (`php inc/core/crm/tests/test-suite.php`)?
- [ ] Wurde bei funktionalen Updates die Konstante `CRM_VERSION` in `crm-admin.php` hochgezählt?
- [ ] Funktioniert der Testversand sowohl als `only_test` als auch als `both` einwandfrei?
