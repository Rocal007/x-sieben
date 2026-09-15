# AGENT-EMAIL-EDITOR.md — X-SIEBEN CRM Modulare E-Mail-Architektur

Dieses Dokument definiert die Spezifikation, Architekturvorgaben und Implementierungsrichtlinien für den **E-Mail-Editor-Agenten (`crm_email_editor_agent`)** im X-SIEBEN CRM (`wp-content/themes/sieben/inc/core/crm/`).

---

## 1. Identität & Mission des Agenten (`crm_email_editor_agent`)

- **Agenten-Name:** `crm_email_editor_agent` (X7 CRM E-Mail Engine & Template Specialist)
- **Domäne:** X SIEBEN Wirtschaftstraining GmbH ([x-sieben.at](https://x-sieben.at))
- **Verantwortungsbereich:** Modulare E-Mail-Vorlagen, Drag-&-Drop-Gliederung, asynchrone Live-Vorschau (Desktop/Mobile), Client-sichere HTML-Normalisierung und Einzelfall-Anpassung im CRM-Mailer.
- **Mission:** Vollständige Parität zwischen dem **PDF-Editor** (`crm-pdf-sections.php`) und dem **E-Mail-Editor** (`crm-email-sections.php`). Jeder E-Mail-Typ wird modularisiert, visuell sortierbar, inline editierbar und mit einer interaktiven Live-Vorschau ausgestattet.

---

## 2. Paritäts-Matrix: PDF-Editor vs. E-Mail-Editor

| Feature / Komponente | PDF-Editor (Status: ✅ Fertiggestellt) | E-Mail-Editor (Status: 🎯 Zielzustand) |
| :--- | :--- | :--- |
| **Helper-Datei** | `helpers/crm-pdf-sections.php` | `helpers/crm-email-sections.php` |
| **Dokument-/Mail-Typen** | 5 Typen: `angebot`, `kb`, `tb`, `diplom`, `invoice` | 7 Typen: `angebot`, `kb`, `angebot_kb`, `anmeldung`, `tb`, `diplom`, `invoice` |
| **Gliederungsebene** | Hauptabschnitte (Seiten) + Unterabschnitte | Hauptabschnitte (Blöcke) + Unterabschnitte |
| **Drag & Drop** | jQuery UI Sortable für Abschnitte & Unterabschnitte | Identisches Drag & Drop für E-Mail-Blöcke |
| **Aktivieren / Deaktivieren** | Checkbox je Abschnitt & Unterabschnitt | Checkbox je E-Mail-Block & Unterabschnitt |
| **Inline-Bearbeitung** | Textarea mit Platzhaltern direkt im Manager | Textarea / Rich-Text mit Platzhaltern im Manager |
| **Abschnitte hinzufügen** | Drawer für neue Abschnitte & Unterabschnitte | Drawer für benutzerdefinierte E-Mail-Blöcke |
| **Live-Vorschau** | Asynchroner iframe (`x_sieben_pdf_live_preview`) | Asynchroner iframe (`x_sieben_email_live_preview`) |
| **Viewport-Simulation** | A4 Hochformat (Zoom / Vollbild) | Responsive Toggle: **Desktop (600px)** vs. **Mobile (375px)** |
| **Eintrags-Customizing** | Abschnitte im Modal je Kunde editierbar | Abschnitte im Mailer je Kunde vor Versand editierbar |
| **Client-Sicherheit** | TCPDF-Rendering ohne Seitensprünge | Tabellen-HTML, Inline-CSS, Outlook `border="0"`, `crm_prepare_email_html_for_sending()` |

---

## 3. Architektur & Dateistruktur

```
inc/core/crm/
├── helpers/
│   ├── crm-pdf-sections.php     # Bestehend: PDF-Abschnittsverwaltung
│   ├── crm-email-sections.php   # NEU: E-Mail-Abschnittsverwaltung (SSOT für Mail-Struktur)
│   ├── normalize.php            # Bestehend: E-Mail-HTML-Normalisierung & HTTPS-Kanonisierung
│   └── crm-status.php           # Bestehend: Audit-Trail & Statuswechsel
├── crm-settings.php             # Tab 2 Überarbeitung: E-Mail Drag & Drop Manager + Live-Vorschau
├── crm-model.php                # Dynamisches Zusammenbauen der E-Mails aus Modulen mit Fallback
├── controler/
│   └── output-controler.php     # AJAX: x_sieben_email_live_preview & x_sieben_save_email_sections
├── assets/
│   └── crm-admin.js             # Event-Handler für E-Mail Sortable, Live-Preview & Responsive-Toggle
└── css/
    └── crm-admin.css            # Styles für E-Mail Preview-Box, Pills und Responsive-Frames
```

---

## 4. Modulare E-Mail-Struktur (Die 7 Standard-Dokumente)

Jede E-Mail besteht aus logischen, unabhängig aktivierbaren Hauptabschnitten und Unterabschnitten:

### 4.1 `angebot` — E-Mail Kursangebot & Beratung
1. **Preheader & Header:** Preheader-Snippet, X-SIEBEN Bildmarke, Subtitel "Wirtschaftstraining & Personenzertifizierung".
2. **Begrüßung & Einleitung:** Persönliche Anrede (`{anrede} {titel} {nachname}`), Dank für das Interesse, Kursbezug (`{kurstitel}`).
3. **Kurs-Eckdaten-Box:** Kompakte Übersicht (Termin `{startdatum} - {enddatum}`, Zeiten `{uhrzeit}`, Modus: Online / Wien Rochusgasse, Lehreinheiten `{le}`).
4. **Modul- & Nutzenübersicht:** Bulletpoints zu den wichtigsten Kursmodulen und anerkannten Zertifizierungen (`{zertifizierungen}`).
5. **Investition & Fördermöglichkeiten:** Transparente Netto/Brutto-Kosten (`{preis_netto}`, `{preis_brutto}`), Hinweis auf Förderungen (AMS, WAFF, Bildungskarenz, Steuerabsetzbarkeit).
6. **Beilagen-Hinweis:** Klarer Verweis auf das angehängte PDF-Angebot und die Kurszeitenbestätigung.
7. **Call-to-Action & Rückmeldefrist:** Verbindliche Antwortfrist (`{expire}`) und Antwortbutton / Buchungshinweis.
8. **Signatur:** Offizielle Signatur Backoffice (`{backoffice_name}`) & Geschäftsführung (`{company_management}`).
9. **Postskriptum (P.S.):** ProvenExpert-Bewertungslink und Qualitätssiegel (TÜV, SystemCERT, Ö-Cert).
10. **Rechtlicher Footer:** Impressum, Firmenbuch (`{company_fn}`), UID (`{company_uid}`), AGB- & Datenschutz-Links.

### 4.2 `kb` — E-Mail Kurszeitenbestätigung
1. **Preheader & Header:** Betreff-Fokus "Ihre Kurszeitenbestätigung für {kurstitel}".
2. **Begrüßung & Anlass:** Höfliche Einleitung mit Bezug auf die Vorlage bei Förderstellen / AMS.
3. **Bestätigungsdaten:** Teilnehmername, SV-Nummer (`{svr}`), Kurszeitraum und genauer Stundenplan.
4. **Förderhinweis (AMS/Land):** Bestätigungsklausel für Kostenübernahmen und Behörden.
5. **Beilage:** Verweis auf das offizielle PDF-Dokument `KB_{nachname}.pdf`.
6. **Signatur & Footer.**

### 4.3 `angebot_kb` — Kombi-E-Mail (Angebot & Kurszeiten)
- Vereint Angebot und Kurszeiten in einer strukturierten E-Mail mit 2 PDF-Beilagen.

### 4.4 `anmeldung` — E-Mail Anmelde- & Buchungsbestätigung
- Verbindliche Buchungsbestätigung mit Kursbeginn-Checkliste, Vorbereitungsmaterialien und Rechnungsankündigung.

### 4.5 `tb` — E-Mail Teilnahmebestätigung
- Gratulation zur erfolgreichen Absolvierung, Ausweisung der absolvierten Lehreinheiten (100% Präsenz) und PDF-Anhang.

### 4.6 `diplom` — E-Mail Diplom & Abschlusszertifikat
- Feierliches Begleitschreiben, Ausweisung des Prüfungserfolgs (*mit ausgezeichnetem Erfolg* / *mit gutem Erfolg* / *erfolgreich abgeschlossen*), ISO 17024 Gültigkeit und Einladung ins X-SIEBEN Alumni-Netzwerk.

### 4.7 `invoice` — E-Mail Honorarnote / Rechnung
- Zahlungsbegleitschreiben mit Rechnungsnummer, Fälligkeitsdatum, Netto/USt/Brutto und strukturierter Bankverbindung (IBAN/BIC).

---

## 5. Spezifikation der E-Mail Live-Vorschau (`#crm-email-preview-section`)

Die E-Mail Live-Vorschau wird analog zur PDF-Live-Vorschau aufgebaut, jedoch für E-Mail-Clients optimiert:

1. **Responsive Viewport-Switcher:**
   - 🖥️ **Desktop (Standard):** 600px Breite mit Zentrierung (Standard für Outlook/Desktop-Clients).
   - 📱 **Mobile:** 375px Breite (Smartphone-Simulation für iPhone / Android).
   - ↔️ **Full Width:** 100% Breite zur Prüfung flexibler Layouts.
2. **Dokument-Pills:**
   - Umschalten zwischen allen 7 E-Mail-Vorlagen per Klick ohne Seiten-Reload.
3. **Echtzeit-Aktualisierung (Auto-Refresh):**
   - Nach Drag-and-Drop Verschieben eines E-Mail-Blocks oder Änderung eines Textes aktualisiert sich der iframe via AJAX (`x_sieben_email_live_preview`) binnen 300ms (Debounced).
4. **Tool-Leiste:**
   - 🔄 **Neu laden:** Manuelles Neurendern.
   - 🧪 **Test-Mail senden:** Direktes Versenden der Vorschau an die hinterlegte Test-E-Mail-Adresse.
   - 📋 **HTML kopieren:** Quelltext in die Zwischenablage kopieren.
   - ↗️ **Vollbild:** In neuem Browser-Tab öffnen.

---

## 6. HTML- & Client-Standards (Outlook, Apple Mail, Gmail)

Der Agent garantiert, dass alle generierten E-Mails strikt den E-Mail-Client-Standards genügen:

1. **Tabellen-Layouts:**
   - Keine CSS-Grids, keine Flexboxen im E-Mail-Body.
   - Ausschließlich verschachtelte `<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">`.
2. **Inline-Styles:**
   - Alle CSS-Styles müssen als Inline-Attribute (`style="..."`) formatiert sein.
3. **Bildpfade & HTTPS:**
   - Alle Bilder müssen absolute URLs (`https://x-sieben.at/...`) tragen.
   - Jedes `<img>` muss `alt`, `border="0"` und explizite Pixel-Größen (`width`, `height`) aufweisen.
4. **WordPress-Content-Filter-Verbot:**
   - E-Mail-Felder dürfen **unter keinen Umständen** mit `apply_filters('the_content', ...)` gerendert werden!
   - Korrekte Pipeline:
     ```php
     $html = crm_assemble_email_from_sections($doc_type, $entry_id, $course_id);
     $html = do_shortcode($html);
     $html = crm_prepare_email_html_for_sending($html);
     ```

---

## 7. LINGUA-LOCA AT & Radikale Objektivität (RO)

1. **Österreichischer Sprachstandard:**
   - Korrekt: *Teilnahmebestätigung*, *Kurszeiten*, *Lehrgang*, *Backoffice / Kanzleiteam*, *Honorarnote*.
   - Verboten: bundesdeutsche Floskeln (*"lecker"*, *"gucken"*, *"schauen Sie mal vorbei"*).
2. **Radikale Objektivität (RO):**
   - Sachliche, transparente Information ohne leere Werbephrasen.
   - Verbindliche Fristen und klare Handlungsanweisungen für Kursteilnehmer.
3. **Rechtliche Absicherung:**
   - AGB-Link: `https://x-sieben.at/wp-content/uploads/2025/09/AGB_X_SIEBEN_2025.pdf`
   - Datenschutzerklärung: `https://x-sieben.at/datenschutzerklaerung/`
   - Backoffice-Kontakt: Anna Brauer (`abrauer@x-sieben.at` / Tel. `0800 700 170`).

---

## 8. Abwärtskompatibilität & Migration

- **Option Key:** `crm_email_sections_order_{doc_type}` speichert die individuelle Reihenfolge und aktive Abschnitte.
- **Fallback-Mechanismus:** Wenn keine modularen Abschnitte definiert sind, greift das System transparent auf die klassischen Felder (`crm_custom_fields` -> `E-Mail Angebot`, `E-Mail Anmeldebestätigung` etc.) zurück.
- **Kein Datenverlust:** Bestehende Freitexte bleiben erhalten und werden als Standard-Inhalte der Unterabschnitte initialisiert.

### 8.1 Aufteilung in Gesamte E-Mails & Wiederverwendbare Komponenten (Tab 2)
Im Reiter **E-Mail Editor** (`crm-settings.php?tab=emails`) werden Textbausteine strikt in zwei Kategorien gegliedert:
1. **Gesamte E-Mails (Master-Vorlagen, `email_type = 'full_email'`):**
   - Vollständige Nachrichtenvorlagen für den E-Mail-Versand (`E-Mail Angebot`, `E-Mail Kursantrittsbestätigung / KB`, `E-Mail Anmeldebestätigung`, `E-Mail Teilnahmebestätigung - Allgemein`, `E-Mail Teilnahmebestätigung - Förderung`, `E-Mail Diplom`, `E-Mail Honorarnote / Rechnung`).
   - Im Editor stehen zwei Reihen an Klick-Chips bereit:
     - 🧩 *Wiederverwendbare Komponenten einbinden* (`{signatur_email}`, `{email_footer}`, `{buchung_email}`, `{agb_claim}`, `{bankverbindung}`, `{angebot_hinweis}`, `{angebot_ps}`)
     - 🔤 *Kurs- & Kundendaten-Platzhalter* (`{kurstitel}`, `{startdatum}`, `{uhrzeit}`, `{preis_netto}`, `{salutation}`...)
2. **Wiederverwendbare Komponenten & Bausteine (`email_type = 'component'`):**
   - Modulare Textbausteine (`E-Mail Signatur`, `E-Mail-Footer`, `Anmeldung Buchung E-Mail Text`, `AGB text`, `Bankverbindung`, etc.).
   - Jede Komponente zeigt eine Prominente Info-Box mit **1-Klick-Kopierbutton** für ihren Platzhalter-Code (`{signatur_email}`, `{email_footer}` etc.).
   - Ansichtsfilter-Pills erlauben das Umschalten zwischen `[Alle Vorlagen]`, `[📧 Gesamte E-Mails]` und `[🧩 Komponenten & Bausteine]`.


---

## 9. Checkliste für Implementierung & Code-Reviews

- [ ] Wurde `helpers/crm-email-sections.php` analog zu `crm-pdf-sections.php` implementiert?
- [ ] Werden alle 7 E-Mail-Typen mit modularen Standard-Abschnitten abgedeckt?
- [ ] Funktioniert das Drag-and-Drop Ordnen von Abschnitten und Unterabschnitten fehlerfrei?
- [ ] Funktioniert die asynchrone Live-Vorschau mit Desktop- und Mobile-Umschaltung?
- [ ] Werden alle HTML-Mails durch `crm_prepare_email_html_for_sending()` gereinigt?
- [ ] Wurde die PHP-Syntaxprüfung (`php -l`) für alle modifizierten Dateien erfolgreich bestanden?
- [ ] Wurde `CRM_VERSION` in `crm-admin.php` bei Veröffentlichung hochgezählt?
