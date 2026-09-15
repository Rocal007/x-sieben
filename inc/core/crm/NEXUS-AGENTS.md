# NEXUS CRM — MASTER AGENT REGISTER & PROTOCOL MATRIX
## X-SIEBEN Wirtschaftstraining GmbH | Subprojekt CRM `inc/core/crm/`
### Version: NEXUS-CRM-3.4.0 | Stand: September 2026

---

## I. SYSTEMÜBERSICHT & SUBPROJEKT-STATUS

Das Modul `wp-content/themes/sieben/inc/core/crm/` ist ein **autarkes Subprojekt** des WordPress-Themes `sieben` für die **X SIEBEN Wirtschaftstraining GmbH**.
Gemäß den **NEXUS-System-Protokollen** (`E:\Nexus-app\nexus`) unterliegt jede Datenverarbeitung, Benutzeroberfläche, Dokumentengenerierung und Kundenkorrespondenz der mathematischen Kontroll- und Fixpunktarchitektur:

$$T = C \circ P_J \circ D_L \circ F$$

mit der Fixpunktbedingung:

$$T(X^*) = X^*$$

---

## II. DIE 7 NEXUS CRM-AGENTEN & OPERATOREN-MATRIX

| Agent-ID | NEXUS-Modul | Rolle & Titel | Primärer Operator | Code-Verankerung im CRM |
| :--- | :--- | :--- | :--- | :--- |
| **`crm_nexus_architect`** | **ARCHITECTUM** | CRM System-Architekt | $T$, Autarkie-Operator | `crm-admin.php`, `crm-model.php`, `controler/` |
| **`crm_nexus_ux`** | **VISIUM / VFB** | UX & Neurodidaktik Operator | $W_{\text{aktiv}}$, $B_{\text{Ludus}}$ | `assets/crm-admin.js`, `css/crm-admin.css`, Previews |
| **`crm_nexus_legislative`** | **LEGISLATIVE** | Regulatory & Standards Operator | $D_L$, $\mathcal{C}_{\text{legal}}$ | `pdf/`, `crm-settings.php`, AGB-/ISO-Vorgaben |
| **`crm_nexus_judikative`** | **JUDIKATIVE** | Proof-Validator & Compliance | $P_J$, $V_{\text{gate}}$, $\mathcal{V}_{\text{forbidden}}$ | `controler/output-controler.php`, Preis-Validierung |
| **`crm_nexus_lingua`** | **LINGUA-LOCA** | Regional-Linguistic Operator AT | $\Lambda_{\text{local}}$, Cicero-7Q | Vorlagen in `crm-model.php`, E-Mail-Texte |
| **`crm_nexus_factorium`** | **FACTORIUM** | Document & Output Pipeline | $F$, Normalizer-Pipeline | `helpers/crm-*-sections.php`, `helpers/normalize.php` |
| **`crm_nexus_audit`** | **AXIOM & STATE** | Fixpunkt & Audit-Trail Operator | $C$, State-Transition-Matrix | `helpers/crm-status.php`, Tabellen `wp_crm_entry_*` |

---

## III. MATHEMATISCHE & KYBERNETISCHE VERANKERUNG IM CRM

### 1. Die Pipeline-Gleichung ($T$)
Für jede Aktion im CRM (Generierung von E-Mails, Rendern von PDFs, Status-Updates) gilt:
- $F$ (**Factorium/LLM**): Erzeugt den Daten- und Dokumenteninhalt aus `WPForms (ID 60468)` und `courses` CPT.
- $D_L$ (**Legislative**): Erzwingt ISO 17024, IPMA®, USt- und AGB-Konformität.
- $P_J$ (**Judikative**): Validiert Fakten, Preise (Netto + 20% USt = Brutto) und blockiert Fluff-Phrasen ($V_{\text{gate}}$).
- $C$ (**Audit/Cache**): Schreibt den validierten Zustand in `wp_crm_entry_status` und fixiert ihn im Audit-Trail `wp_crm_entry_status_history`.

### 2. Das Neurodidaktische Modell nach Vera F. Birkenbihl (VFB)
$$W_{\text{aktiv}} = \left( \sum_{i=1}^{n} (V_i \times A_i) \right) \cdot \left[ \frac{\text{Dekodierung}}{\text{Pauken} \to 0} \right] \cdot \eta_{\text{Spiel}}$$

- **Minimierung von Pauken:** Vorkonfigurierte Sektionen, Drag&Drop-Sortierung, eindeutige Status-Badges.
- **Asynchrone Live-Vorschau:** Sofortiges visuelles Feedback für Desktop (600px) und Mobile (375px) ohne Seitenreload.
- **Cognitive Fluency (System 1):** Blitzschnelle Erfassung des Status eines Teilnehmers in unter 1 Sekunde.
- **Rationaler Wächter (System 2):** Lückenlose Historie mit Zeitstempel, Benutzer-ID und Versandstatus im Modal.

### 3. LINGUA-LOCA AT (Österreichische Wirtschaftssprache)
$$\Lambda_{\text{local}}(T_{\text{src}}, g) = \left[ (T_{\text{src}} \setminus F_{\text{sterile}}) \otimes M_{\text{vocab}}(g) \right] + \vec{S}_{\text{syntax}}(g) \cdot \eta_{\text{tonality}}(g)$$

- Beseitigung aller bundesdeutschen Floskeln ($F_{\text{sterile}}$).
- Verbindliche Etablierung österreichischer Bildungs- und Rechtsterminologie:
  - `Teilnahmebestätigung` (nie "Teilnahmebescheinigung" oder "Zertifikat" bei reinem Kursbesuch).
  - `Lehrgang` / `Seminar` / `Modul` / `Lehreinheiten (UE)`.
  - `Kurszeitenbestätigung` (für AMS/Förderstellen).
  - `Honorarnote` (für externe Trainer).
  - `Backoffice / Kanzleiteam` (nie "Zentrale" oder "Support-Center").

### 4. Zero-Leakage & Kanonisierung (`helpers/normalize.php`)
Jedes HTML-Dokument wird vor Versand oder Vorschau deterministisch normalisiert:
1. **HTTPS-Kanonisierung:** Alle relativen Links (`/wp-content/...`) und Test-Domains (`x-sieben.test`) werden auf `https://x-sieben.at/...` umgeschrieben.
2. **Cookie-Consent-Restoration:** `consent-original-src-_` wird zu `src`; Tracking-Scripte werden entfernt.
3. **Emoji-Sanitization:** WordPress-Smiley-Grafiken werden in native Unicode-Zeichen zurückverwandelt.
4. **Outlook-Kompatibilität:** Tabellen und Bilder erhalten automatisierte `border="0"`- und Inline-Style-Absicherungen.

---

## IV. DETAIL-DOKUMENTATION DER SYSTEM-OPERATOREN

Sämtliche Detail-Spezifikationen der horizontalen System-Agenten befinden sich im Unterordner `agents/`:
1. [System-Architekt (`ARCHITECT.md`)](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/ARCHITECT.md)
2. [UX & Neurodidaktik (`UX-NEURODIDAKTIK.md`)](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/UX-NEURODIDAKTIK.md)
3. [Legislative & Regulatory (`LEGISLATIVE.md`)](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/LEGISLATIVE.md)
4. [Judikative & Proof-Validator (`JUDIKATIVE.md`)](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/JUDIKATIVE.md)
5. [Lingua-Loca & Content Purity (`LINGUA-LOCA.md`)](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/LINGUA-LOCA.md)
6. [Factorium Output Engine (`FACTORIUM.md`)](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/FACTORIUM.md)
7. [Audit & State Fixpoint (`AUDIT-FIXPOINT.md`)](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/AUDIT-FIXPOINT.md)

Zusätzlich existiert das Spezialhandbuch:
- [E-Mail-Editor Agent (`AGENT-EMAIL-EDITOR.md`)](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/AGENT-EMAIL-EDITOR.md)

---

## V. SUBAGENTS FÜR JEDEN GESCHÄFTSVORGANG (`agents/workflows/`)

Die vertikalen Prozess-Subagenten steuern jeden konkreten Geschäftsvorgang vom Erstkontakt bis zur Diplomverleihung:

| Subagent-ID | Geschäftsvorgang | E-Mail-Vorlage | PDF-Vorlage | Spezifikation |
| :--- | :--- | :--- | :--- | :--- |
| **`crm_subagent_orchestrator`** | **Gesamt-Lifecycle Orchestrierung** | Alle 7 Typen | Alle 6 Typen | [00-ORCHESTRATOR.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/workflows/00-ORCHESTRATOR.md) |
| **`crm_subagent_angebot`** | **1. Kursangebot & Beratung** | `angebot` | `pdf/offer.php` | [01-VORGANG-ANGEBOT.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/workflows/01-VORGANG-ANGEBOT.md) |
| **`crm_subagent_kb`** | **2. Kurszeitenbestätigung (AMS)** | `kb` | `pdf/kurszeitenbestaetigung.php` | [02-VORGANG-KURSZEITENBESTAETIGUNG.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/workflows/02-VORGANG-KURSZEITENBESTAETIGUNG.md) |
| **`crm_subagent_angebot_kb`** | **3. Express-Kombi Angebot & KB** | `angebot_kb` | `offer.php` + `kurszeitenbestaetigung.php` | [03-VORGANG-KOMBI-ANGEBOT-KB.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/workflows/03-VORGANG-KOMBI-ANGEBOT-KB.md) |
| **`crm_subagent_anmeldung`** | **4. Anmeldung, Buchung & Onboarding** | `anmeldung` | Anmeldeformular / Buchungsbeleg | [04-VORGANG-ANMELDUNG.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/workflows/04-VORGANG-ANMELDUNG.md) |
| **`crm_subagent_tb`** | **5. Teilnahmebestätigung (TB)** | `tb` | `pdf/teilnamebestaetigung.php` | [05-VORGANG-TEILNAHMEBESTAETIGUNG.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/workflows/05-VORGANG-TEILNAHMEBESTAETIGUNG.md) |
| **`crm_subagent_diplom`** | **6. Diplom & Personenzertifizierung** | `diplom` | `pdf/diplom.php` | [06-VORGANG-DIPLOM.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/workflows/06-VORGANG-DIPLOM.md) |
| **`crm_subagent_invoice`** | **7. Honorarnote & Kursabrechnung** | `invoice` | `pdf/invoice.php` | [07-VORGANG-HONORARNOTE.md](file:///c:/laragon/www/x-sieben/wp-content/themes/sieben/inc/core/crm/agents/workflows/07-VORGANG-HONORARNOTE.md) |

