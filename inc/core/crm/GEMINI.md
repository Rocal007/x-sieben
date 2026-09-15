# Lokale KI als Standard (Local AI Default Policy)

Standardmäßig ist immer die **lokale KI** auf der lokalen NVIDIA GeForce RTX 5060 Ti GPU zu verwenden:

## 1. Bildgenerierung (Image Generation)
* **Standard:** IMMER das lokale Setup mit **FLUX.1** über **Stable Diffusion WebUI Forge** verwenden (`http://127.0.0.1:7860`).
* **Befehl / Tool:**
  ```bash
  forge-generate "<ausführlicher englischer Prompt>" -o "<output_pfad.png>" --steps 20 --cfg 1.0 -W 1024 -H 1024
  ```
* Das eingebaute Cloud-Tool `generate_image` darf **nicht** standardmäßig verwendet werden, außer der Benutzer wünscht dies explizit.
* Jedes generierte Bild sofort im Chat als Markdown-Bild einbinden:
  `![Beschreibung](/absoluter/pfad/zum/bild.png)`

## 2. Lokale LLM-Flotte & NEXUS Agenten-Zuordnung (Ollama)
Alle LLMs laufen lokal via Ollama (`http://127.0.0.1:11434`) auf der RTX 5060 Ti GPU (16 GB VRAM).
Aufgaben im X-SIEBEN CRM werden strikt den 7 NEXUS-Operatoren zugeordnet:

| NEXUS-Agent / Protokoll | Modell | Rolle im X-SIEBEN CRM |
| :--- | :--- | :--- |
| **`crm_nexus_architect` (ARCHITECTUM)** | `qwen2.5-coder:14b` | Autarkie-Wächter, MVC-Entkopplung, Schema-Architektur, Versionskonsistenz (`CRM_VERSION`) |
| **`crm_nexus_factorium` (FACTORIUM)** | `qwen2.5-coder:7b` & `1.5b` | 7 E-Mails, 5 PDFs, HTML5-Normalisierung (0 Byte JS), Schema.org, SQL-Queries |
| **`crm_nexus_judikative` (JUDIKATIVE)** | `deepseek-r1:14b` & `phi4:14b` | Centgenaue USt (20% AT), Datenintegrität, Fluff-Filter ($\mathcal{V}_{\text{forbidden}}$), Circuit-Breaker |
| **`crm_nexus_lingua` (LINGUA-LOCA)** | `qwen2.5:14b` & `mistral-nemo:12b` | Österreich-Standard ($\Lambda_{\text{local}}$), Beseitigung bundesdeutscher Floskeln, Cicero-7Q, $FRE_{\text{norm}}$ |
| **`crm_nexus_ux` (VISIUM / VFB)** | `FLUX.1` (Forge) + VFB-UX | Neurodidaktik ($W_{\text{aktiv}}$), Live-Vorschauen Desktop (600px) / Mobile (375px), Status-Badges |
| **`crm_nexus_legislative` (LEGISLATIVE)** | Regelsystem + RIS | ISO 17024, IPMA/pma, AMS-UE, GewO 1994, AGB-Schutz, FAGG |
| **`crm_nexus_audit` (AXIOM)** | SQLite / DB-Status | Fixpunkt $T(X^*)=X^*$, Audit-Trail in `wp_crm_entry_status_history` |

---

## 3. Direkte CLI-Befehle für Agenten
* **Architektur & MVC:** `ollama-ask -m qwen2.5-coder:14b "<prompt>"`
* **Code & HTML-Normalisierung:** `ollama-ask -m qwen2.5-coder:7b "<prompt>"` (oder `1.5b` für Micro-Syntax)
* **Wahrheits-Check & Proof:** `ollama-ask -m deepseek-r1:14b "<prompt>"`
* **Österreichische Tonalität & Mailtexte:** `ollama-ask -m qwen2.5:14b "<prompt>"`
* **Europäische Nuancen:** `ollama-ask -m mistral-nemo:latest "<prompt>"`
