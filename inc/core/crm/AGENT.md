# X-SIEBEN CRM — NEXUS AGENTEN-HANDBUCH (SUBPROJEKT-SPEZIFIKATION)

Dieses Dokument definiert die Architektur, Konventionen, Arbeitsregeln und die **NEXUS-Agentenhierarchie** für das autarke X-SIEBEN CRM-Modul (`inc/core/crm/`).

---

## 1. Identität & Domänenfokus
- **Spezialisierung:** Kursmanagement (`courses`), WPForms-Einträge (`Form-ID 60468`), PDF-Generierung (5 Dokumenttypen), E-Mail-Versand (7 Mail-Typen) und Kundenstatus-Tracking.
- **Organisation:** X SIEBEN Wirtschaftstraining GmbH (Wien / Wr. Neustadt).
- **Kurse:** IPMA®/pma Projektmanagement (Level D, C, B), Agile Methoden (Scrum, Kanban, Agile Coach), Führung, Personenzertifizierungen nach ISO 17024 (FachtrainerIn, Business Coach).
- **Zertifizierungspartner:** pma/IPMA, SystemCERT, TÜV Austria, wba, CERT NÖ, AMS.

---

## 2. NEXUS CRM Agenten-Suite

Gemäß den NEXUS-Protokollen ($T = C \circ P_J \circ D_L \circ F$) operieren für das CRM-Subprojekt 7 spezialisierte Agenten:

| Agent | Protokoll | Fokus | Spezifikationsdatei |
| :--- | :--- | :--- | :--- |
| **`crm_nexus_architect`** | ARCHITECTUM | Autarkie, MVC, Schema, Versionierung (`CRM_VERSION = '2.18.4'`) | [ARCHITECT.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/ARCHITECT.md) |
| **`crm_nexus_ux`** | VISIUM / VFB | Birkenbihl-Modell ($W_{\text{aktiv}}$), Live-Previews Desktop (600px) / Mobile (375px) | [UX-NEURODIDAKTIK.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/UX-NEURODIDAKTIK.md) |
| **`crm_nexus_legislative`** | LEGISLATIVE | ISO 17024, IPMA/pma, AMS, GewO 1994, AGB-Schutz, FAGG | [LEGISLATIVE.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/LEGISLATIVE.md) |
| **`crm_nexus_judikative`** | JUDIKATIVE | Circuit-Breaker ($V_{\text{gate}}$), Fluff-Filter ($\mathcal{V}_{\text{forbidden}}$), 20% USt AT | [JUDIKATIVE.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/JUDIKATIVE.md) |
| **`crm_nexus_lingua`** | LINGUA-LOCA AT | Österreich-Standard ($\Lambda_{\text{local}}$), Cicero-7Q, Amstad $FRE_{\text{norm}}$ | [LINGUA-LOCA.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/LINGUA-LOCA.md) |
| **`crm_nexus_factorium`** | FACTORIUM | 7 Mails, 5 PDFs, Sektionen-Engine, HTTPS-Kanonisierung | [FACTORIUM.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/FACTORIUM.md) |
| **`crm_nexus_audit`** | AXIOM & STATE | Fixpunkt $T(X^*)=X^*$, Audit-Trail, Dual-Mode Testversand (`only_test` vs `both`) | [AUDIT-FIXPOINT.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/AUDIT-FIXPOINT.md) |

Das vollständige Master-Register mit Operatorenmatrix befindet sich in [NEXUS-AGENTS.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/NEXUS-AGENTS.md).
Für die E-Mail-Editor-Architektur existiert zusätzlich das Spezialhandbuch [AGENT-EMAIL-EDITOR.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/AGENT-EMAIL-EDITOR.md).

---

## 3. Autarkie-Architektur (Standalone-Prinzip)
Das Modul `inc/core/crm/` ist 100 % unabhängig vom Rest des Themes:
- **`crm-admin.php`**: Hauptseite, Menüeintrag, Asset-Fallback-Registration, Versionsanzeige (`CRM_VERSION = '2.18.4'`).
- **`helpers/crm-cache.php`**: Automatisches JS-Cache-Clean mit Flag-Steuerung (`crm_auto_js_cache_clean`) ausschließlich bei partiellem Cache-Update ($C(X) = X$).
- **`crm-settings.php`**: E-Mail-Editor, PDF-Editor mit modularer Sektionsverwaltung sowie integrierter asynchroner PDF-Live-Vorschau.
- **`helpers/crm-email-sections.php`**: Modulare Drag-and-Drop E-Mail-Abschnitte und asynchrone E-Mail-Live-Vorschau (Desktop/Mobile).
- **`crm-model.php`**: Zentrales Datenmodell `CRM_Model`. Liest WPForms-Felder und Kursdaten aus. E-Mail-Vorlagen werden via `wpautop()` + `do_shortcode()` + `crm_prepare_email_html_for_sending()` geladen. **NIEMALS** `apply_filters('the_content', ...)` auf E-Mail-Inhalte anwenden!
- **`helpers/normalize.php`**: E-Mail-HTML-Normalisierer `crm_prepare_email_html_for_sending()`. Garantiert absolute HTTPS-URLs (`https://x-sieben.at/...`), bereinigt Cookie-Banner-Attribute und repariert Smileys.
- **`helpers/crm-status.php`**: Datenbanktabellen `wp_crm_entry_status` und `wp_crm_entry_status_history`, Status-Badges, Zeitstempel, Modal-Historie.
- **`controler/output-controler.php`**: AJAX-Handler `x_sieben_send_mail` mit Test-Modus (`only_test` vs `both`) und PDF-Anhängen.
- **`assets/crm-admin.js`**: Interaktive Steuerung (Inline-Erfolgsmeldung, Status-Dropdown-Wechsel, Historien-Modal, Client-Cache-Operator `window.crmJsCache`).

---

## 4. LINGUA-LOCA AT (Österreich-Standard)
- Regionale Begrifflichkeiten: "Teilnahmebestätigung", "Kurszeiten", "Lehrgang", "Backoffice / Kanzleiteam".
- Keine bundesdeutschen Floskeln.
- Radikale Objektivität (RO): Höchste Faktenpräzision, transparente Abläufe, keine Marketing-Floskeln.

---

## 5. Checkliste für Änderungen & Wartung
- [ ] Wurden alle Änderungen innerhalb von `inc/core/crm/` durchgeführt?
- [ ] Werden alle Bildquellen und Links über `crm_prepare_email_html_for_sending()` abgesichert?
- [ ] Bestehen alle PHP-Dateien die Syntaxprüfung (`php -l`)?
- [ ] Wurde bei funktionalen Updates die Konstante `CRM_VERSION` in `crm-admin.php` hochgezählt?
- [ ] Funktioniert der Testversand sowohl als `only_test` als auch als `both` einwandfrei?
