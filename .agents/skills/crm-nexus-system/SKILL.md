---
name: crm-nexus-system
description: >-
  Führt CRM- und Kursmanagement-Aufgaben für X-SIEBEN nach dem Senior Developer Agenten-Standard aus.
  Aktivieren, wenn CRM-Workflows, E-Mail-/PDF-Generierungen, Compliance-Prüfungen oder Status-Updates
  im Subprojekt inc/core/crm/ durchgeführt oder orchestriert werden sollen.
---

# CRM NEXUS System & Subagent Execution Skill

Dieser Skill stellt sicher, dass alle Operationen im CRM `inc/core/crm/` der definierten NEXUS-Pipeline $T = C \circ P_J \circ D_L \circ F$ und dem Senior Developer Standard folgen.

## 1. Verfügbare Senior Subagents
Folgende Subagents sind über `invoke_subagent` direkt ansprechbar:

- **`crm_nexus_architect`**: System-Architekt (Autarkie, MVC, DB-Schema, Cache-Operator $C$).
- **`crm_nexus_judikative`**: Proof-Validator $P_J$ (Compliance, Fluff-Filter, USt-Berechnung 20%, Schutzbegriffe).
- **`crm_nexus_legislative`**: Regulatory Operator $D_L$ (ISO 17024, IPMA Level D-B, AMS, FAGG, AGB).
- **`crm_nexus_factorium`**: Output Pipeline $F$ (HTML-E-Mails, PDF-Generierung, Zero-Leakage-Normalizer).
- **`crm_subagent_orchestrator`**: Geschäftsprozess-Koordinator für die 7 operativen Workflows.

## 2. Standard-Workflow (Execution Runbook)

1. **Analyse & Input-Erfassung:**
   - Datensatz aus `WPForms (ID 60468)` und Custom Post Type `courses` laden.
   - Status aus `wp_crm_entry_status` prüfen.

2. **Rechtliche & normative Rahmenbedingungen ($D_L$):**
   - Vorgaben für den spezifischen Geschäftsvorgang (z. B. Angebot oder Kurszeitenbestätigung) aus `crm_nexus_legislative` anfordern.

3. **Dokumenten- & Mailerzeugung ($F$):**
   - Vorlage via `crm_nexus_factorium` aufbereiten.
   - Zwingend `crm_prepare_email_html_for_sending()` anwenden (Zero Leakage, relative URLs zu `https://x-sieben.at/...`).

4. **Qualitäts- & Compliance-Gate ($P_J$):**
   - Unabhängige Validierung durch `crm_nexus_judikative`:
     - Rechnerischer Steuerabgleich: Netto + 20% USt = Brutto.
     - LINGUA-LOCA AT: Nur österreichische Fachbegriffe, kein Marketing-Fluff.
     - Freigabe nur bei Proof-State $p = 1$.

5. **Audit-Trail & State Transition ($C$):**
   - Persistierung in `wp_crm_entry_status` und Historie `wp_crm_entry_status_history`.
