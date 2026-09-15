# SENIOR DEVELOPER AGENT ARCHITECTURE & GOVERNANCE
# X-SIEBEN CRM & Theme System | Standalone & Matrix Architecture

## 1. Grundsatz: Senior-Entwickler-Standard für KI-Agenten
Agenten in diesem Projekt werden wie hochgradig entkoppelte, typisierte Software-Module nach Domain-Driven Design (DDD) und Clean Architecture strukturiert:
1. **Keine monolithischen "Alleskönner"-Agenten:** Jeder Agent besitzt eine exakt definierte Rolle und Verantwortlichkeit (Single Responsibility Principle).
2. **Keine Mikro-Fragmentierung (Anti-Pattern):** Agenten werden nicht für triviale Einzelbefehle gespawnt, sondern für vollständige, in sich geschlossene Domänen oder Bounded Contexts.
3. **Zwei-Phasen-Trennung (Generator vs. Validator):** Der erzeugende Agent (Worker/Factorium) darf niemals sein eigenes Review durchführen. Jede Freigabe erfordert die unabhängige Judikative ($P_J$).

---

## 2. Horizontale System-Operatoren (Governance & Quality)
Die horizontalen Agenten sichern Querschnittsfunktionen über alle Geschäftsvorgänge hinweg ab:

| Agent-ID | Modul / Rolle | Werkzeuge & Berechtigungen | Verantwortlichkeit |
| :--- | :--- | :--- | :--- |
| **`crm_nexus_architect`** | ARCHITECTUM / System-Architekt | Write, Subagents, Shell | MVC-Architektur, Standalone-Autarkie (`inc/core/crm/`), DB-Schema `wp_crm_entry_*`, Versionsmanagement. |
| **`crm_nexus_judikative`** | JUDIKATIVE / Proof-Validator ($P_J$) | Read-Only (Kein Write) | Gatekeeper für Compliance, rechnerische USt-Validierung (Netto + 20% = Brutto), Ausschluss von Marketing-Fluff. |
| **`crm_nexus_legislative`** | LEGISLATIVE / Regulatory ($D_L$) | Read-Only (Kein Write) | Normen-Konformität (ISO 17024, IPMA Level D-B, AMS, Ö-Cert), FAGG, AGB- und Datenschutz-Klauseln. |
| **`crm_nexus_factorium`** | FACTORIUM / Output Pipeline | Write, Code | Builder für HTML-E-Mails, PDF-Generierung, HTTPS-Kanonisierung via `normalize.php`, CSS/JS Assets. |

---

## 3. Vertikale Workflow-Agenten (Bounded Contexts)
Gesteuert durch **`crm_subagent_orchestrator`** wickeln die vertikalen Agenten isolierte Kunden-Lebenszyklen ab:
- **Vorgang 1 (Angebot):** Erstberatung, Kostenvoranschlag, 20% USt, FAGG-Rücktritt.
- **Vorgang 2 (Kurszeitenbestätigung):** Förderstellen-/AMS-Konformität, Lehreinheiten (UE), Kurszeiten.
- **Vorgang 3 (Express-Kombi):** Synchrone Dual-Generierung von Angebot + Kurszeitenbestätigung.
- **Vorgang 4 (Anmeldung):** Buchungsbestätigung, verbindlicher Vertragsabschluss nach ABGB.
- **Vorgang 5 (Teilnahmebestätigung):** Prüfung der 75%-Anwesenheitsquote, Ö-Cert-Kriterien.
- **Vorgang 6 (Diplom):** Personenzertifizierung nach ISO 17024, SystemCERT / TÜV Austria, Diplom-Nummer.
- **Vorgang 7 (Honorarnote):** Externe Trainerabrechnung nach § 11 UStG.

---

## 4. Vertrag & Übergabe-Protokoll (Contract Interface)
Jede Interaktion zwischen Agenten erfolgt über ein standardisiertes Übergabe-Protokoll:
- **Input-Payload:** `entry_id`, `customer_data`, `course_data`, `document_type`.
- **Regel-Projektion ($D_L$):** Legislative prüft und ergänzt Pflichtklauseln.
- **Entwurf ($F$):** Factorium generiert das Dokument/die E-Mail.
- **Proof-Gate ($P_J$):** Judikative validiert das Resultat. Rückgabe:
  - $p = 1$: Freigegeben für Versand oder DB-Persistierung.
  - $p = 0$: Abgelehnt mit strukturierter Mängelliste zur Nachbesserung.
- **Audit-Trail ($C$):** Transaktionssichere Erfassung im Audit-Log (`wp_crm_entry_status_history`).
