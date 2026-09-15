# X-SIEBEN CRM & Kurs-Management-Modul

[![Version](https://img.shields.io/badge/version-2.18.12-blue.svg)](crm-admin.php)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D%208.0-777bb4.svg)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-Theme%20Module-21759b.svg)](https://wordpress.org/)
[![Compliance](https://img.shields.io/badge/Standard-LINGUA--LOCA%20AT-success.svg)](AGENT.md)

Autarkes CRM-, Kursverarbeitungs- und Dokumentengenerierungs-Modul für die **X SIEBEN Wirtschaftstraining GmbH** (Wien / Wr. Neustadt).

---

## 📌 Kernfunktionen

- **WPForms-Integration:** Nahtlose Anbindung an Formular-Einträge (Default-Formular-ID `60468`) mit CPT `courses`.
- **E-Mail & PDF Editor getrennt (`crm-settings.php`):**
  - Eigener E-Mail-Editor (`admin.php?page=crm-emails`) für alle Transaktions-Vorlagen und Signaturen.
  - Eigener PDF-Editor (`admin.php?page=crm-pdf`) für alle Bausteine, Klauseln, Bankdaten und Textpassagen.
  - **Integrierte Live-PDF-Vorschau:** Automatische TCPDF-Vorschau aller 5 Dokumenttypen (KB, TB, Diplom, Angebot & Honorarnote) direkt unter dem Editor, synchronisiert mit Filter-Pills und automatischem Re-Rendering beim Speichern von Bausteinen.
- **Dokumenten-Engine (PDF):** Dynamische Erstellung von rechtskonformen PDF-Dokumenten via TCPDF:
  - Angebote (mit Modulen, Trainern, Preisen und AGB-Klauseln)
  - Kurszeitenbestätigungen (KB)
  - Kombi-Dokumente (Angebot & KB)
  - Teilnahmebestätigungen (TB)
  - Diplome & Abschlusszertifikate
  - Honorarnoten / Rechnungsdokumente
- **E-Mail-Pipeline & Normalizer (`helpers/normalize.php`):**
  - Strikte HTTPS-Kanonisierung (`https://x-sieben.at/...`) für Bild- und Linkpfade.
  - Cookie-Banner-Maskierung (Real Cookie Banner) wird rückgängig gemacht.
  - Emojis werden in native UTF-8-Symbole gewandelt.
  - Outlook-Optimierung (`border="0"`-Normalisierung).
- **Zweistufige Audit- & Status-Engine (`helpers/crm-status.php`):**
  - Dedizierte Tabellen `wp_crm_entry_status` und `wp_crm_entry_status_history` via `dbDelta`.
  - Vollständiger Revisions-Trail aller E-Mail-Sendungen und Statuswechsel.
  - Sicherer Test-Modus (`only_test` vs. `both`) zur Vermeidung von versehentlichen E-Mails an Kunden.

---

## 🏛️ Architektur-Übersicht

```
inc/core/crm/
│
├── crm-admin.php              # Menü-Registrierung, Tabellen-Render, Version 2.11.7
├── crm-model.php              # Zentrales Datenmodell CRM_Model (WPForms ↔ CPT courses)
├── crm-form.php               # Formular-Feldextraktion und Normalisierung
├── crm-settings.php           # E-Mail Editor, PDF Editor, Live-PDF-Vorschau & Optionen
├── crm-view-entry.php         # Detailansicht einzelner Einreichungen
├── crm-view-detail.php        # Tabellen-Subview
│
├── controler/
│   ├── output-controler.php   # AJAX-Handler (x_sieben_send_mail), Dispatching & PDF-Attaches
│   └── from-handler.php       # Formular-POST-Payload-Handler
│
├── helpers/
│   ├── normalize.php          # E-Mail-HTML-Normalisierung (crm_prepare_email_html_for_sending)
│   └── crm-status.php         # Audit-Engine, Status-Badges & Historien-Modal
│
├── pdf/                       # PDF-Generierungs-Templates
│   ├── offer.php              # Angebot
│   ├── kurszeitenbestaetigung.php # Kurszeitenbestätigung
│   ├── angebot_kurszeiten.php # Kombinationsangebot
│   ├── teilnamebestaetigung.php   # Teilnahmebestätigung
│   ├── diplom.php             # Diplom
│   └── invoice.php            # Rechnung
│
├── mailer/
│   ├── crm-mailer.php         # wp_mail Wrapper mit SMTP- und Header-Vorbereitung
│   ├── courses_mailer.php     # Template-Bindings
│   └── mailer.php             # Basis-Mailer
│
└── assets/ & css/
    ├── crm-admin.js           # AJAX-Events, Inline-Status, Historien-Modal
    └── crm-admin.css          # Modernes Admin-Styling & Badges
```

---

## 🛡️ Autarkie-Gebot & Entwicklungsrichtlinien

1. **Unabhängigkeit:** Keine Abhängigkeit zu externen Theme-Funktionen. Das Modul kann per Verzeichnis-Kopie portiert werden.
2. **Keine destruktiven Content-Filter:** E-Mail-Felder (`signatur_email`, `angebot_email`, etc.) dürfen **nicht** durch `apply_filters('the_content', ...)` geleitet werden.
3. **NEXUS LINGUA-LOCA AT:**
   - Authentische österreichische Wirtschaftssprache (*Teilnahmebestätigung*, *Kurszeiten*, *Lehrgang*, *Backoffice*).
   - Radikale Objektivität (RO) ohne werbliche Übertreibungen.

---

## 📄 Lizenz & Urheberrecht

© X SIEBEN Wirtschaftstraining GmbH — Alle Rechte vorbehalten.  
Geschäftsführung: Mag. Dr. Johannes Gasberger  
URL: [x-sieben.at](https://x-sieben.at)
