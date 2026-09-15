# 🍎 X-SIEBEN CRM — Agenten-Leitfaden für macOS (Apple User Guide)
## Wie Sie Ihr CRM mit KI-Agenten in Antigravity steuern und weiterentwickeln
### Für: X SIEBEN Wirtschaftstraining GmbH | Stand: September 2026

---

## 1. Übersicht: In 3 Schritten startklar auf dem Mac

Als Apple-Nutzer profitieren Sie von einer besonders flüssigen Arbeitsumgebung. Sie benötigen lediglich:
1. **Antigravity** (Ihre KI-Entwicklungsumgebung auf dem Mac)
2. Den **Projektordner** (heruntergeladen von GitHub)
3. Die **Prompt-Befehle** aus diesem Handbuch

---

## 2. Einrichtung auf dem Mac (Einmalig in 5 Minuten)

### Schritt 1: Antigravity auf dem Mac installieren
1. Laden Sie die Mac-Version von **Antigravity** herunter (unterstützt Apple Silicon M1/M2/M3/M4 sowie Intel).
2. Ziehen Sie die App in Ihren Ordner **Programme** (`/Applications`).
3. Starten Sie Antigravity und melden Sie sich an.

### Schritt 2: CRM-Projekt auf Ihren Mac holen
Öffnen Sie das Programm **Terminal** (über Spotlight: `Cmd + Leertaste` $\to$ *Terminal* eingeben):

```bash
# Erstellen Sie einen Projektordner (z.B. in Ihrem Benutzerordner)
mkdir -p ~/Sites/x-sieben-crm
cd ~/Sites/x-sieben-crm

# Laden Sie das Repository von GitHub herunter
git clone https://github.com/Rocal007/crm-x-sieben.git .
```
*(Alternativ können Sie auch die App **GitHub Desktop** auf dem Mac nutzen und das Repository per Klick klonen).*

### Schritt 3: Ordner in Antigravity öffnen
1. Drücken Sie in Antigravity: `Cmd + O` (Ablage $\to$ Ordner öffnen).
2. Wählen Sie den Ordner `~/Sites/x-sieben-crm` aus.
3. **Fertig!** Antigravity lädt automatisch alle 7 NEXUS-Agenten und Regeln im Hintergrund.

---

## 3. Die Mac-Tastatur-Kürzel (Sparen Sie Zeit)

| Aktion | Mac-Kürzel | Funktion |
| :--- | :--- | :--- |
| **Befehlspalette öffnen** | `Cmd + Shift + P` | Schneller Zugriff auf alle Befehle |
| **Datei schnell öffnen** | `Cmd + P` | Dateien wie `AGENT.md` direkt aufrufen |
| **Integriertes Terminal** | `Ctrl + ~` (oder `Cmd + J`) | Terminal direkt in der App ein-/ausblenden |
| **Dateisuche / Scan** | `Cmd + Shift + F` | Suche über das gesamte CRM |
| **Änderung speichern** | `Cmd + S` | Datei sichern |

---

## 4. Ihre 7 digitalen Assistenten (Die Agenten-Übersicht)

Im Chatfenster von Antigravity (rechte Seite) können Sie die Agenten direkt mit Namen ansprechen:

```
┌────────────────────────────────────────────────────────────────────────┐
│                        DIE 7 NEXUS CRM-AGENTEN                         │
├───────────────────────┬────────────────────────────────────────────────┤
│ @crm_nexus_architect  │ 🏛️ Technik & System (Hält alles stabil & autark)│
│ @crm_nexus_ux         │ 🧠 Design, Vorschau & Bedienkomfort            │
│ @crm_nexus_legislative│ 📜 Recht, AMS-Vorgaben, AGB & ISO 17024        │
│ @crm_nexus_judikative │ ⚖️ Rechnungsprüfung, Steuer (20% USt) & Fakten │
│ @crm_nexus_lingua     │ 🇦🇹 Österreichische Wirtschaftssprache          │
│ @crm_nexus_factorium  │ 🚀 E-Mail- & PDF-Bau, Outlook-Schutzschild     │
│ @crm_nexus_audit      │ 🛡️ Status-Verlauf & Test-Mail-Absicherung      │
└───────────────────────┴────────────────────────────────────────────────┘
```

---

## 5. Die Prompt-Bibliothek (Copy & Paste für Ihren Alltag)

Kopieren Sie diese Vorlagen einfach in das Antigravity-Chatfenster:

### Szenario A: Einen neuen E-Mail-Textbaustein anlegen
> **Ihr Prompt an die KI:**
> *„Ich möchte einen neuen E-Mail-Baustein für Bildungskarenz-Förderungen anlegen. @crm_nexus_lingua soll den Text in professioneller österreichischer Wirtschaftssprache verfassen. @crm_nexus_legislative soll prüfen, ob die formalen Voraussetzungen für Förderanträge stimmen. @crm_nexus_factorium soll den Baustein in `helpers/crm-email-sections.php` einbinden.“*

---

### Szenario B: Layout prüfen für Outlook, Gmail & Apple Mail
> **Ihr Prompt an die KI:**
> *„@crm_nexus_factorium: Prüfe alle Vorlagen auf E-Mail-Client-Sicherheit. Stelle sicher, dass auf dem Mac in Apple Mail und unter Microsoft Outlook keine Bilder blockiert werden und alle Links auf https://x-sieben.at/... zeigen.“*

---

### Szenario C: Neues AGB-PDF oder Partner-Logo hinterlegen
> **Ihr Prompt an die KI:**
> *„Wir haben eine neue AGB-Version (AGB_X_SIEBEN_2026.pdf). @crm_nexus_legislative und @crm_nexus_architect: Aktualisiert den offiziellen Download-Link in allen Mail-Vorlagen, im Datenmodell und in den Fußzeilen.“*

---

### Szenario D: Neuer Kurs oder Trainer mit Zertifikaten
> **Ihr Prompt an die KI:**
> *„Wir bieten ab sofort den Lehrgang ‚Agile Coach nach ISO 17024‘ an. @crm_nexus_judikative und @crm_nexus_legislative: Prüft, ob die Zertifizierungs-Badges (SystemCERT, ISO 17024) im Diplom-PDF und im Angebot korrekt verknüpft werden und die 20% USt stimmen.“*

---

### Szenario E: Vollständiger Qualitäts-Check vor dem Veröffentlichen
> **Ihr Prompt an die KI:**
> *„Führe einen vollständigen NEXUS-Audit über das CRM durch. Prüfe Syntax, Rechtschreibung nach Österreich-Standard, Steuerberechnung und Status-Historie.“*

---

## 6. Wie Sie Ihre Arbeit auf GitHub & den Live-Server übertragen

Wenn Sie oder die Agenten Änderungen vorgenommen haben, sichern Sie den Stand mit drei kurzen Befehlen im Terminal (`Ctrl + ~`):

```bash
# 1. Alle Änderungen für die Sicherung vormerken
git add .

# 2. Speichern mit kurzer Notiz
git commit -m "Update E-Mail Texte und AGB 2026"

# 3. Zu GitHub hochladen
git push origin main
```

### Automatische Übertragung auf die Live-Seite (`x-sieben.at`):
- **Via SFTP in Antigravity:** Drücken Sie `Cmd + Shift + P` $\to$ tippen Sie `SFTP: Sync Local -> Remote`. Alle geänderten Dateien werden automatisch auf Ihren Live-Server synchronisiert.
- **Via FileZilla / Cyberduck (Beliebt auf dem Mac):** Verbinden Sie sich mit Ihrem FTP-Server und ziehen Sie den Ordner `inc/core/crm/` in das Theme-Verzeichnis auf dem Server.

---

## 7. Die 4 goldenen Sicherheitsregeln (Die das System garantiert)

1. **Keine kaputten E-Mails:** Sie müssen sich nie wieder um Bildpfade oder Cookie-Banner kümmern – der Factorium-Normalisierer repariert jeden Link automatisch.
2. **Kein versehentlicher Kundenkontakt:** Testmails über *„Nur an Test-Empfänger“* erreichen garantiert nur Sie selbst. Der Kunde merkt nichts, der Kundenstatus bleibt unberührt.
3. **Rechtssicherheit in Österreich:** Das System unterscheidet strikt zwischen einfachen Teilnahmebestätigungen und akkreditierten ISO-Diplomen.
4. **100 % autark:** Nichts, was Sie im CRM tun, kann das Design oder die Website von X-SIEBEN beschädigen.

---

*Handbuch erstellt nach dem NEXUS Supremacy Standard V3.4 für X SIEBEN Wirtschaftstraining GmbH.*
