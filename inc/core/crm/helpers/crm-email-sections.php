<?php
/**
 * CRM E-Mail Sections Helper
 *
 * Verwaltet modulare Abschnitte für E-Mail-Vorlagen (Angebot, KB, Kombi Angebot & KB,
 * Anmeldung, TB, Diplom, Honorarnote / Rechnung), inklusive Standard-Reihenfolge,
 * Hinzufügen/Löschen von Blöcken, Speicherung, Deaktivierung und Rendering für Drag-and-Drop
 * sowie Client-sichere HTML-Generierung und Live-Vorschau.
 *
 * Autarkes Modul im X-SIEBEN CRM.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Liefert die Master- und Standard-Definitionen aller modularen Abschnitte für E-Mail-Vorlagen.
 *
 * @param string|null $doc_type 'angebot', 'kb', 'angebot_kb', 'anmeldung', 'tb', 'diplom', 'invoice' oder null
 * @return array
 */
function crm_get_email_sections_definitions($doc_type = null): array
{
    $definitions = [
        // ==========================================
        // 1. ANGEBOT (Kursangebot & Beratung)
        // ==========================================
        'angebot' => [
            'header' => [
                'title'          => __('Preheader & Kopfzeile (Logo)', 'custom-crm'),
                'desc'           => __('Offizielles X-SIEBEN Logo und Untertitel "Wirtschaftstraining & Personenzertifizierung".', 'custom-crm'),
                'badge'          => __('Kopfzeile', 'custom-crm'),
                'icon'           => 'dashicons-format-image',
                'color'          => '#0284c7',
                'default'        => true,
                'default_content'=> '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:20px;"><tr><td align="left" style="padding-bottom:12px; border-bottom:2px solid #007C90;"><img src="https://x-sieben.at/wp-content/themes/sieben/inc/core/crm/assets/xsieben_logo.png" alt="X SIEBEN Wirtschaftstraining" width="170" height="auto" style="display:block; border:0; max-width:170px;"></td><td align="right" style="padding-bottom:12px; border-bottom:2px solid #007C90; font-family:Arial,sans-serif; font-size:11px; color:#64748b;">Wirtschaftstraining & Personenzertifizierung<br><span style="color:#007C90; font-weight:bold;">Wien & Niederösterreich</span></td></tr></table>',
            ],
            'anrede' => [
                'title'          => __('Persönliche Anrede & Begrüßung', 'custom-crm'),
                'desc'           => __('Persönliche Begrüßung und Dank für das Interesse am Kurs.', 'custom-crm'),
                'badge'          => __('Begrüßung', 'custom-crm'),
                'icon'           => 'dashicons-admin-users',
                'color'          => '#0284c7',
                'default'        => true,
                'default_content'=> '<p style="font-family:Arial,sans-serif; font-size:14px; line-height:1.6; color:#1e293b; margin:0 0 16px 0;">{salutation} {titel} {nachname},</p><p style="font-family:Arial,sans-serif; font-size:14px; line-height:1.6; color:#1e293b; margin:0 0 16px 0;">vielen Dank für Ihre geschätzte Anfrage und Ihr Interesse an unserer praxisnahen Weiterbildung <strong>{kurstitel}</strong>.</p><p style="font-family:Arial,sans-serif; font-size:14px; line-height:1.6; color:#1e293b; margin:0 0 16px 0;">Gerne übermitteln wir Ihnen nachstehend und als PDF-Beilage Ihr persönliches, verbindliches Kursangebot samt allen organisatorischen Eckdaten.</p>',
            ],
            'eckdaten' => [
                'title'          => __('Kurs-Eckdaten-Box', 'custom-crm'),
                'desc'           => __('Kompakter Terminüberblick (Zeitraum, Zeiten, Lehreinheiten, Modus/Ort).', 'custom-crm'),
                'badge'          => __('Eckdaten', 'custom-crm'),
                'icon'           => 'dashicons-calendar-alt',
                'color'          => '#0891b2',
                'default'        => true,
                'default_content'=> '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background:#f8fafc; border-left:4px solid #007C90; border-top:1px solid #e2e8f0; border-right:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0; border-radius:4px; margin:18px 0; font-family:Arial,sans-serif; font-size:13px; color:#334155;"><tr><td style="padding:14px 18px;"><strong style="font-size:14px; color:#0f172a; display:block; margin-bottom:8px;">📅 Veranstaltungs-Eckdaten: {kurstitel}</strong><table role="presentation" border="0" cellpadding="3" cellspacing="0" width="100%" style="font-size:13px; color:#334155;"><tr><td width="130" style="color:#64748b; font-weight:bold;">Zeitraum:</td><td><strong>{startdatum} bis {enddatum}</strong></td></tr><tr><td style="color:#64748b; font-weight:bold;">Kurszeiten:</td><td>{uhrzeit}</td></tr><tr><td style="color:#64748b; font-weight:bold;">Umfang:</td><td>{le} Lehreinheiten (LE, 1 LE = 45 Min.)</td></tr><tr><td style="color:#64748b; font-weight:bold;">Schulungsort:</td><td>{location_wien} / Live-Online interaktiv</td></tr></table></td></tr></table>',
            ],
            'module' => [
                'title'          => __('Modul- & Nutzenübersicht', 'custom-crm'),
                'desc'           => __('Didaktischer Aufbau, Kursinhalte, Trainerkompetenz und Akkreditierungen.', 'custom-crm'),
                'badge'          => __('Inhalte', 'custom-crm'),
                'icon'           => 'dashicons-list-view',
                'color'          => '#059669',
                'default'        => true,
                'default_content'=> '<p style="font-family:Arial,sans-serif; font-size:14px; line-height:1.6; color:#1e293b; margin:0 0 10px 0;"><strong>Ihre Mehrwerte bei X SIEBEN:</strong></p><ul style="font-family:Arial,sans-serif; font-size:13px; line-height:1.6; color:#334155; margin:0 0 16px 20px; padding:0;"><li>Lernfördernde Kleingruppen für maximale persönliche Betreuung und Interaktion</li><li>Akkreditierte TrainerInnen mit langjähriger Management- und Prüfungserfahrung</li><li>Zertifiziert nach internationalen Qualitätsstandards (Ö-Cert, CERT-NÖ, TÜV, SystemCERT)</li><li>Umfangreiche Vorbereitung auf offizielle Personenzertifizierungen nach ISO 17024</li></ul>',
            ],
            'investition' => [
                'title'          => __('Investition & Fördermöglichkeiten', 'custom-crm'),
                'desc'           => __('Transparente Kostenaufstellung Netto/Brutto und Hinweis auf Förderstellen.', 'custom-crm'),
                'badge'          => __('Finanzen', 'custom-crm'),
                'icon'           => 'dashicons-money-alt',
                'color'          => '#d97706',
                'default'        => true,
                'default_content'=> '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background:#fefce8; border:1px solid #fef08a; border-radius:4px; padding:12px 16px; margin:16px 0; font-family:Arial,sans-serif; font-size:13px; color:#713f12;"><tr><td><strong>Investition:</strong> € {preis_netto} exkl. USt (€ {preis_brutto} inkl. 20% USt)<br><span style="font-size:12px; color:#854d0e;">💡 <em>Fördermöglichkeit:</em> Förderbar über AMS, WAFF, Bildungskarenz sowie länderspezifische Bildungskonten. Weiterbildungskosten sind zudem steuerlich absetzbar.</span></td></tr></table>',
            ],
            'beilagen' => [
                'title'          => __('Hinweis auf PDF-Beilagen', 'custom-crm'),
                'desc'           => __('Hinweis auf angehängtes Kursangebot und Kurszeitenbestätigung (KB).', 'custom-crm'),
                'badge'          => __('Beilagen', 'custom-crm'),
                'icon'           => 'dashicons-media-document',
                'color'          => '#7c3aed',
                'default'        => true,
                'default_content'=> '<p style="font-family:Arial,sans-serif; font-size:13.5px; line-height:1.6; color:#334155; margin:0 0 16px 0;">📎 <strong>Beilagen zu dieser E-Mail:</strong><br>• Detailliertes Angebot mit Lehrplan und Zertifizierungsinformationen als PDF<br>• Offizielle Kurszeitenbestätigung (KB) zur Einreichung bei Förderstellen oder Arbeitgebern</p>',
            ],
            'buchung' => [
                'title'          => __('Call-to-Action, Frist & Buchungshinweis', 'custom-crm'),
                'desc'           => __('Verbindliche Gültigkeitsfrist und Hinweis zur Platzreservierung.', 'custom-crm'),
                'badge'          => __('CTA', 'custom-crm'),
                'icon'           => 'dashicons-yes-alt',
                'color'          => '#2563eb',
                'default'        => true,
                'default_content'=> '{buchung_email}',
            ],
            'signatur' => [
                'title'          => __('Offizielle E-Mail-Signatur', 'custom-crm'),
                'desc'           => __('Signatur Backoffice Anna Brauer & Geschäftsführung Dr. Gasberger.', 'custom-crm'),
                'badge'          => __('Signatur', 'custom-crm'),
                'icon'           => 'dashicons-edit',
                'color'          => '#059669',
                'default'        => true,
                'default_content'=> '{signatur_email}',
            ],
            'ps' => [
                'title'          => __('Postskriptum (P.S.) & ProvenExpert', 'custom-crm'),
                'desc'           => __('ProvenExpert-Bewertungslink und Qualitätsgarantie.', 'custom-crm'),
                'badge'          => __('P.S.', 'custom-crm'),
                'icon'           => 'dashicons-star-filled',
                'color'          => '#b45309',
                'default'        => true,
                'default_content'=> '<p style="font-family:Arial,sans-serif; font-size:12px; line-height:1.5; color:#64748b; margin:16px 0 0 0;"><strong>P.S.:</strong> Überzeugen Sie sich von unserer Teilnehmerzufriedenheit auf <a href="https://www.provenexpert.com/x-sieben-wirtschaftstraining-gmbh/" target="_blank" style="color:#007C90; text-decoration:underline;">ProvenExpert (Note: Sehr gut)</a>.</p>',
            ],
            'footer' => [
                'title'          => __('Rechtlicher Footer & AGB', 'custom-crm'),
                'desc'           => __('Impressum, Firmenbuch, UID, AGB- und Datenschutz-Links.', 'custom-crm'),
                'badge'          => __('Footer', 'custom-crm'),
                'icon'           => 'dashicons-admin-generic',
                'color'          => '#475569',
                'default'        => true,
                'default_content'=> '{email_footer}',
            ],
        ],

        // ==========================================
        // 2. KB (Kurszeitenbestätigung)
        // ==========================================
        'kb' => [
            'header' => [
                'title'          => __('Kopfzeile (Logo)', 'custom-crm'),
                'desc'           => __('Kopfzeile mit Logo und Institutsdaten.', 'custom-crm'),
                'badge'          => __('Kopfzeile', 'custom-crm'),
                'icon'           => 'dashicons-format-image',
                'color'          => '#0f766e',
                'default'        => true,
                'default_content'=> '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:20px;"><tr><td align="left" style="padding-bottom:12px; border-bottom:2px solid #0f766e;"><img src="https://x-sieben.at/wp-content/themes/sieben/inc/core/crm/assets/xsieben_logo.png" alt="X SIEBEN" width="170" height="auto" style="display:block; border:0; max-width:170px;"></td><td align="right" style="padding-bottom:12px; border-bottom:2px solid #0f766e; font-family:Arial,sans-serif; font-size:11px; color:#64748b;">Kurszeitenbestätigung (KB)<br><span style="color:#0f766e; font-weight:bold;">Behörden- & Förderstellenkonform</span></td></tr></table>',
            ],
            'anrede' => [
                'title'          => __('Persönliche Anrede & Anlass', 'custom-crm'),
                'desc'           => __('Begrüßung und Zweck der Bestätigung.', 'custom-crm'),
                'badge'          => __('Begrüßung', 'custom-crm'),
                'icon'           => 'dashicons-admin-users',
                'color'          => '#0f766e',
                'default'        => true,
                'default_content'=> '<p style="font-family:Arial,sans-serif; font-size:14px; line-height:1.6; color:#1e293b; margin:0 0 16px 0;">{salutation} {titel} {nachname},</p><p style="font-family:Arial,sans-serif; font-size:14px; line-height:1.6; color:#1e293b; margin:0 0 16px 0;">anbei übermitteln wir Ihnen die offizielle <strong>Kurszeitenbestätigung (KB)</strong> für die Veranstaltung <strong>{kurstitel}</strong> zur Vorlage bei Förderstellen, Ihrem Arbeitgeber oder dem AMS.</p>',
            ],
            'bestaetigung' => [
                'title'          => __('Bestätigungsdaten & Stundenplan', 'custom-crm'),
                'desc'           => __('Angaben zu Teilnehmer, SV-Nummer, Zeitraum und Wochenstunden.', 'custom-crm'),
                'badge'          => __('Stundenplan', 'custom-crm'),
                'icon'           => 'dashicons-calendar-alt',
                'color'          => '#0f766e',
                'default'        => true,
                'default_content'=> '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background:#f0fdfa; border-left:4px solid #0f766e; border-top:1px solid #ccfbf1; border-right:1px solid #ccfbf1; border-bottom:1px solid #ccfbf1; border-radius:4px; padding:14px 18px; margin:16px 0; font-family:Arial,sans-serif; font-size:13px; color:#134e4a;"><tr><td><strong>Bestätigte Kursdaten:</strong><br>• TeilnehmerIn: {anrede} {vorname} {nachname}<br>• Sozialversicherungsnummer: {svr}<br>• Zeitraum: {startdatum} bis {enddatum}<br>• Kurszeiten: {uhrzeit}<br>• Gesamtumfang: {le} Lehreinheiten</td></tr></table>',
            ],
            'foerderung' => [
                'title'          => __('Förderklausel (AMS / Land / waff)', 'custom-crm'),
                'desc'           => __('Rechtlicher Hinweis auf Einhaltung der Anwesenheitspflicht.', 'custom-crm'),
                'badge'          => __('Klausel', 'custom-crm'),
                'icon'           => 'dashicons-shield',
                'color'          => '#047857',
                'default'        => true,
                'default_content'=> '<p style="font-family:Arial,sans-serif; font-size:13px; line-height:1.5; color:#475569; margin:0 0 16px 0;">Das vorliegende Dokument bestätigt die verbindliche Anmeldung sowie die terminliche Einteilung der Lehreinheiten nach den Kriterien der anerkannten Erwachsenenbildungsträger.</p>',
            ],
            'beilagen' => [
                'title'          => __('Hinweis auf PDF-Anhang', 'custom-crm'),
                'desc'           => __('Hinweis auf das offizielle PDF-Dokument.', 'custom-crm'),
                'badge'          => __('Beilage', 'custom-crm'),
                'icon'           => 'dashicons-media-document',
                'color'          => '#7c3aed',
                'default'        => true,
                'default_content'=> '<p style="font-family:Arial,sans-serif; font-size:13.5px; line-height:1.6; color:#334155; margin:0 0 16px 0;">📎 <strong>Angehängte Bestätigung:</strong><br>Die rechtsgültig gezeichnete Kurszeitenbestätigung finden Sie im Anhang als PDF-Datei.</p>',
            ],
            'signatur' => [
                'title'          => __('Signatur', 'custom-crm'),
                'desc'           => __('Signatur Backoffice.', 'custom-crm'),
                'badge'          => __('Signatur', 'custom-crm'),
                'icon'           => 'dashicons-edit',
                'color'          => '#059669',
                'default'        => true,
                'default_content'=> '{signatur_email}',
            ],
            'footer' => [
                'title'          => __('Rechtlicher Footer', 'custom-crm'),
                'desc'           => __('Standard E-Mail-Footer.', 'custom-crm'),
                'badge'          => __('Footer', 'custom-crm'),
                'icon'           => 'dashicons-admin-generic',
                'color'          => '#475569',
                'default'        => true,
                'default_content'=> '{email_footer}',
            ],
        ],

        // ==========================================
        // 3. ANGEBOT_KB (Kombi Angebot & Kurszeiten)
        // ==========================================
        'angebot_kb' => [
            'header' => [
                'title'          => __('Kopfzeile (Logo)', 'custom-crm'),
                'desc'           => __('Kopfzeile mit Logo.', 'custom-crm'),
                'badge'          => __('Kopfzeile', 'custom-crm'),
                'icon'           => 'dashicons-format-image',
                'color'          => '#7c3aed',
                'default'        => true,
                'default_content'=> '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:20px;"><tr><td align="left" style="padding-bottom:12px; border-bottom:2px solid #7c3aed;"><img src="https://x-sieben.at/wp-content/themes/sieben/inc/core/crm/assets/xsieben_logo.png" alt="X SIEBEN" width="170" height="auto" style="display:block; border:0; max-width:170px;"></td><td align="right" style="padding-bottom:12px; border-bottom:2px solid #7c3aed; font-family:Arial,sans-serif; font-size:11px; color:#64748b;">Angebot & Kurszeitenbestätigung<br><span style="color:#7c3aed; font-weight:bold;">Kompaktunterlagen</span></td></tr></table>',
            ],
            'anrede' => [
                'title'          => __('Persönliche Anrede & Begleittext', 'custom-crm'),
                'desc'           => __('Begrüßung und Einleitung zum Kombi-Paket.', 'custom-crm'),
                'badge'          => __('Begrüßung', 'custom-crm'),
                'icon'           => 'dashicons-admin-users',
                'color'          => '#7c3aed',
                'default'        => true,
                'default_content'=> '<p style="font-family:Arial,sans-serif; font-size:14px; line-height:1.6; color:#1e293b; margin:0 0 16px 0;">{salutation} {titel} {nachname},</p><p style="font-family:Arial,sans-serif; font-size:14px; line-height:1.6; color:#1e293b; margin:0 0 16px 0;">vielen Dank für Ihre Anfrage. Wir freuen uns, Ihnen sowohl das <strong>Kursangebot</strong> als auch die dazugehörige <strong>Kurszeitenbestätigung (KB)</strong> für <strong>{kurstitel}</strong> gemeinsam zukommen zu lassen.</p>',
            ],
            'eckdaten' => [
                'title'          => __('Kombi-Eckdaten-Box', 'custom-crm'),
                'desc'           => __('Termine, Zeiten, Einheiten und Kosten auf einen Blick.', 'custom-crm'),
                'badge'          => __('Eckdaten', 'custom-crm'),
                'icon'           => 'dashicons-calendar-alt',
                'color'          => '#7c3aed',
                'default'        => true,
                'default_content'=> '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background:#faf5ff; border-left:4px solid #7c3aed; border-top:1px solid #f3e8ff; border-right:1px solid #f3e8ff; border-bottom:1px solid #f3e8ff; border-radius:4px; padding:14px 18px; margin:16px 0; font-family:Arial,sans-serif; font-size:13px; color:#581c87;"><tr><td><strong>Veranstaltungsdaten:</strong><br>• Zeitraum: <strong>{startdatum} bis {enddatum}</strong><br>• Kurszeiten: {uhrzeit} ({le} Lehreinheiten)<br>• Schulungsort: {location_wien} / Live-Online<br>• Investition: € {preis_netto} exkl. USt (€ {preis_brutto} inkl. USt)</td></tr></table>',
            ],
            'beilagen' => [
                'title'          => __('Hinweis auf beide PDF-Beilagen', 'custom-crm'),
                'desc'           => __('Verweis auf Angebot.pdf und Kurszeiten.pdf.', 'custom-crm'),
                'badge'          => __('2 Beilagen', 'custom-crm'),
                'icon'           => 'dashicons-media-document',
                'color'          => '#7c3aed',
                'default'        => true,
                'default_content'=> '<p style="font-family:Arial,sans-serif; font-size:13.5px; line-height:1.6; color:#334155; margin:0 0 16px 0;">📎 <strong>Beilagen im Anhang:</strong><br>1. <em>Angebot_{nachname}.pdf</em> — Vollständiges Angebot inklusive Lehrgangsinhalten<br>2. <em>KB_{nachname}.pdf</em> — Offizielle Kurszeitenbestätigung zur Einreichung</p>',
            ],
            'buchung' => [
                'title'          => __('Buchungshinweis & Frist', 'custom-crm'),
                'desc'           => __('Gültigkeitsfrist.', 'custom-crm'),
                'badge'          => __('CTA', 'custom-crm'),
                'icon'           => 'dashicons-yes-alt',
                'color'          => '#2563eb',
                'default'        => true,
                'default_content'=> '{buchung_email}',
            ],
            'signatur' => [
                'title'          => __('Signatur', 'custom-crm'),
                'desc'           => __('Signatur Backoffice.', 'custom-crm'),
                'badge'          => __('Signatur', 'custom-crm'),
                'icon'           => 'dashicons-edit',
                'color'          => '#059669',
                'default'        => true,
                'default_content'=> '{signatur_email}',
            ],
            'footer' => [
                'title'          => __('Footer', 'custom-crm'),
                'desc'           => __('Rechtlicher Footer.', 'custom-crm'),
                'badge'          => __('Footer', 'custom-crm'),
                'icon'           => 'dashicons-admin-generic',
                'color'          => '#475569',
                'default'        => true,
                'default_content'=> '{email_footer}',
            ],
        ],

        // ==========================================
        // 4. ANMELDUNG (Buchungsbestätigung)
        // ==========================================
        'anmeldung' => [
            'header' => [
                'title'          => __('Kopfzeile (Logo)', 'custom-crm'),
                'desc'           => __('Kopfzeile mit Logo.', 'custom-crm'),
                'badge'          => __('Kopfzeile', 'custom-crm'),
                'icon'           => 'dashicons-format-image',
                'color'          => '#059669',
                'default'        => true,
                'default_content'=> '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:20px;"><tr><td align="left" style="padding-bottom:12px; border-bottom:2px solid #059669;"><img src="https://x-sieben.at/wp-content/themes/sieben/inc/core/crm/assets/xsieben_logo.png" alt="X SIEBEN" width="170" height="auto" style="display:block; border:0; max-width:170px;"></td><td align="right" style="padding-bottom:12px; border-bottom:2px solid #059669; font-family:Arial,sans-serif; font-size:11px; color:#64748b;">Anmelde- & Buchungsbestätigung<br><span style="color:#059669; font-weight:bold;">Fixe Kursplatz-Garantie</span></td></tr></table>',
            ],
            'anrede' => [
                'title'          => __('Persönliche Anrede & Gratulation', 'custom-crm'),
                'desc'           => __('Bestätigung des Fixplatzes.', 'custom-crm'),
                'badge'          => __('Begrüßung', 'custom-crm'),
                'icon'           => 'dashicons-admin-users',
                'color'          => '#059669',
                'default'        => true,
                'default_content'=> '<p style="font-family:Arial,sans-serif; font-size:14px; line-height:1.6; color:#1e293b; margin:0 0 16px 0;">{salutation} {titel} {nachname},</p><p style="font-family:Arial,sans-serif; font-size:14px; line-height:1.6; color:#1e293b; margin:0 0 16px 0;">wir freuen uns sehr, Ihre Anmeldung für die Weiterbildung <strong>{kurstitel}</strong> verbindlich zu bestätigen. Ihr Kursplatz ist damit fest reserviert!</p>',
            ],
            'kursdaten' => [
                'title'          => __('Checkliste zum Kursstart', 'custom-crm'),
                'desc'           => __('Ablauf, Starttermin, Unterlagen und Anreise / Einwahllink.', 'custom-crm'),
                'badge'          => __('Checkliste', 'custom-crm'),
                'icon'           => 'dashicons-saved',
                'color'          => '#059669',
                'default'        => true,
                'default_content'=> '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background:#f0fdf4; border-left:4px solid #059669; border-top:1px solid #bbf7d0; border-right:1px solid #bbf7d0; border-bottom:1px solid #bbf7d0; border-radius:4px; padding:14px 18px; margin:16px 0; font-family:Arial,sans-serif; font-size:13px; color:#14532d;"><tr><td><strong>Ihre Kursstart-Checkliste:</strong><br>• Erster Kurstag: <strong>{startdatum}</strong> (Beginn: {uhrzeit})<br>• Schulungsort: {location_wien} bzw. digitaler Seminarraum<br>• Kursunterlagen: Werden Ihnen vor Kursbeginn digital zur Verfügung gestellt<br>• Bei Fragen steht Ihnen unser Backoffice jederzeit unterstützend zur Seite.</td></tr></table>',
            ],
            'rechnung_hinweis' => [
                'title'          => __('Hinweis zur Honorarnote / Rechnung', 'custom-crm'),
                'desc'           => __('Rechnungslegung und Zahlungsmodalitäten.', 'custom-crm'),
                'badge'          => __('Rechnung', 'custom-crm'),
                'icon'           => 'dashicons-money-alt',
                'color'          => '#d97706',
                'default'        => true,
                'default_content'=> '<p style="font-family:Arial,sans-serif; font-size:13.5px; line-height:1.6; color:#334155; margin:0 0 16px 0;">Die dazugehörige Honorarnote erhalten Sie in einer gesonderten Nachricht. Bei Firmenbuchungen oder Kostenübernahmen durch Förderstellen bitten wir um Übermittlung der entsprechenden Kostenzusage.</p>',
            ],
            'signatur' => [
                'title'          => __('Signatur', 'custom-crm'),
                'desc'           => __('Signatur Backoffice.', 'custom-crm'),
                'badge'          => __('Signatur', 'custom-crm'),
                'icon'           => 'dashicons-edit',
                'color'          => '#059669',
                'default'        => true,
                'default_content'=> '{signatur_email}',
            ],
            'footer' => [
                'title'          => __('Footer', 'custom-crm'),
                'desc'           => __('Rechtlicher Footer.', 'custom-crm'),
                'badge'          => __('Footer', 'custom-crm'),
                'icon'           => 'dashicons-admin-generic',
                'color'          => '#475569',
                'default'        => true,
                'default_content'=> '{email_footer}',
            ],
        ],

        // ==========================================
        // 5. TB (Teilnahmebestätigung)
        // ==========================================
        'tb' => [
            'header' => [
                'title'          => __('Kopfzeile (Logo)', 'custom-crm'),
                'desc'           => __('Kopfzeile mit Logo.', 'custom-crm'),
                'badge'          => __('Kopfzeile', 'custom-crm'),
                'icon'           => 'dashicons-format-image',
                'color'          => '#047857',
                'default'        => true,
                'default_content'=> '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:20px;"><tr><td align="left" style="padding-bottom:12px; border-bottom:2px solid #047857;"><img src="https://x-sieben.at/wp-content/themes/sieben/inc/core/crm/assets/xsieben_logo.png" alt="X SIEBEN" width="170" height="auto" style="display:block; border:0; max-width:170px;"></td><td align="right" style="padding-bottom:12px; border-bottom:2px solid #047857; font-family:Arial,sans-serif; font-size:11px; color:#64748b;">Teilnahmebestätigung (TB)<br><span style="color:#047857; font-weight:bold;">Erfolgreicher Kursabschluss</span></td></tr></table>',
            ],
            'anrede' => [
                'title'          => __('Persönliche Anrede & Gratulation', 'custom-crm'),
                'desc'           => __('Glückwünsche zur erfolgreichen Absolvierung.', 'custom-crm'),
                'badge'          => __('Begrüßung', 'custom-crm'),
                'icon'           => 'dashicons-admin-users',
                'color'          => '#047857',
                'default'        => true,
                'default_content'=> '<p style="font-family:Arial,sans-serif; font-size:14px; line-height:1.6; color:#1e293b; margin:0 0 16px 0;">{salutation} {titel} {nachname},</p><p style="font-family:Arial,sans-serif; font-size:14px; line-height:1.6; color:#1e293b; margin:0 0 16px 0;">wir gratulieren Ihnen herzlich zum erfolgreichen Abschluss der Veranstaltung <strong>{kurstitel}</strong>!</p><p style="font-family:Arial,sans-serif; font-size:14px; line-height:1.6; color:#1e293b; margin:0 0 16px 0;">Als Nachweis über Ihre erfolgreiche Teilnahme und die absolvierten {le} Lehreinheiten übermitteln wir Ihnen anbei Ihre offizielle <strong>Teilnahmebestätigung (TB)</strong>.</p>',
            ],
            'beilagen' => [
                'title'          => __('Hinweis auf PDF-Teilnahmebestätigung', 'custom-crm'),
                'desc'           => __('Hinweis auf TB_Nachname.pdf.', 'custom-crm'),
                'badge'          => __('Beilage', 'custom-crm'),
                'icon'           => 'dashicons-media-document',
                'color'          => '#047857',
                'default'        => true,
                'default_content'=> '<p style="font-family:Arial,sans-serif; font-size:13.5px; line-height:1.6; color:#334155; margin:0 0 16px 0;">📎 <strong>Ihr Dokument im Anhang:</strong><br>Die zertifizierte Bestätigung liegt dieser E-Mail als PDF bei und kann direkt für Förderabrechnungen, Behörden oder Ihren Arbeitgeber verwendet werden.</p>',
            ],
            'alumni' => [
                'title'          => __('Alumni-Netzwerk & Weiterbildung', 'custom-crm'),
                'desc'           => __('Einladung zu vertiefenden Lehrgängen und Netzwerktreffen.', 'custom-crm'),
                'badge'          => __('Alumni', 'custom-crm'),
                'icon'           => 'dashicons-groups',
                'color'          => '#0284c7',
                'default'        => true,
                'default_content'=> '<p style="font-family:Arial,sans-serif; font-size:13px; line-height:1.5; color:#475569; margin:0 0 16px 0;">Wir wünschen Ihnen bei der praktischen Umsetzung des erworbenen Wissens viel Erfolg und freuen uns darauf, Sie bei einem vertiefenden Seminar oder unserer Zertifizierungsvorbereitung wieder begrüßen zu dürfen.</p>',
            ],
            'signatur' => [
                'title'          => __('Signatur', 'custom-crm'),
                'desc'           => __('Signatur Backoffice.', 'custom-crm'),
                'badge'          => __('Signatur', 'custom-crm'),
                'icon'           => 'dashicons-edit',
                'color'          => '#059669',
                'default'        => true,
                'default_content'=> '{signatur_email}',
            ],
            'footer' => [
                'title'          => __('Footer', 'custom-crm'),
                'desc'           => __('Rechtlicher Footer.', 'custom-crm'),
                'badge'          => __('Footer', 'custom-crm'),
                'icon'           => 'dashicons-admin-generic',
                'color'          => '#475569',
                'default'        => true,
                'default_content'=> '{email_footer}',
            ],
        ],

        // ==========================================
        // 6. DIPLOM (Diplom & Abschlusszertifikat)
        // ==========================================
        'diplom' => [
            'header' => [
                'title'          => __('Feierliche Kopfzeile (Diplom-Logo)', 'custom-crm'),
                'desc'           => __('Gold-/Bernsteinfarbene Kopfzeile mit Diplom-Badge.', 'custom-crm'),
                'badge'          => __('Kopfzeile', 'custom-crm'),
                'icon'           => 'dashicons-awards',
                'color'          => '#b45309',
                'default'        => true,
                'default_content'=> '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:20px;"><tr><td align="left" style="padding-bottom:12px; border-bottom:2px solid #b45309;"><img src="https://x-sieben.at/wp-content/themes/sieben/inc/core/crm/assets/xsieben_logo.png" alt="X SIEBEN" width="170" height="auto" style="display:block; border:0; max-width:170px;"></td><td align="right" style="padding-bottom:12px; border-bottom:2px solid #b45309; font-family:Arial,sans-serif; font-size:11px; color:#64748b;">Diplom & Abschlusszertifikat<br><span style="color:#b45309; font-weight:bold;">ISO 17024 Personenzertifizierung</span></td></tr></table>',
            ],
            'anrede' => [
                'title'          => __('Feierliche Gratulation & Prüfungserfolg', 'custom-crm'),
                'desc'           => __('Anerkennung des Prüfungserfolgs ({diplom_success}).', 'custom-crm'),
                'badge'          => __('Gratulation', 'custom-crm'),
                'icon'           => 'dashicons-awards',
                'color'          => '#b45309',
                'default'        => true,
                'default_content'=> '<p style="font-family:Arial,sans-serif; font-size:14px; line-height:1.6; color:#1e293b; margin:0 0 16px 0;">{salutation} {titel} {nachname},</p><p style="font-family:Arial,sans-serif; font-size:14px; line-height:1.6; color:#1e293b; margin:0 0 16px 0;">wir gratulieren Ihnen herzlich zur erfolgreichen Absolvierung der kommissionellen Abschlussprüfung zum {kurstyp} <strong>{kurstitel}</strong> {diplom_success}!</p>',
            ],
            'diplom_details' => [
                'title'          => __('Ausweisungs-Box & Zertifikat-Details', 'custom-crm'),
                'desc'           => __('Akkreditierungen (SystemCERT, Ö-Cert, TÜV) und Qualifikationsnachweis.', 'custom-crm'),
                'badge'          => __('Qualifikation', 'custom-crm'),
                'icon'           => 'dashicons-star-filled',
                'color'          => '#b45309',
                'default'        => true,
                'default_content'=> '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background:#fffbeb; border-left:4px solid #b45309; border-top:1px solid #fef3c7; border-right:1px solid #fef3c7; border-bottom:1px solid #fef3c7; border-radius:4px; padding:14px 18px; margin:16px 0; font-family:Arial,sans-serif; font-size:13px; color:#78350f;"><tr><td><strong>Offizieller Qualifikationsnachweis:</strong><br>Mit diesem Diplom weisen Sie Ihre fundierten Fachkenntnisse und praktischen Kompetenzen nach höchsten Qualitätsstandards aus. Ihr Diplom berechtigt zur Führung der entsprechenden Fachbezeichnung.</td></tr></table>',
            ],
            'beilagen' => [
                'title'          => __('Hinweis auf beigelegtes PDF-Diplom', 'custom-crm'),
                'desc'           => __('Verweis auf hochauflösendes PDF-Diplom.', 'custom-crm'),
                'badge'          => __('Beilage', 'custom-crm'),
                'icon'           => 'dashicons-media-document',
                'color'          => '#b45309',
                'default'        => true,
                'default_content'=> '<p style="font-family:Arial,sans-serif; font-size:13.5px; line-height:1.6; color:#334155; margin:0 0 16px 0;">📎 <strong>Ihr Diplom im Anhang:</strong><br>Ihr hochauflösendes, siegelgezeichnetes Abschlussdiplom liegt dieser E-Mail als PDF bei. Das Originaldokument auf Urkundenpapier geht Ihnen zusätzlich postalisch zu.</p>',
            ],
            'signatur' => [
                'title'          => __('Signatur', 'custom-crm'),
                'desc'           => __('Geschäftsführung Dr. Johannes Gasberger.', 'custom-crm'),
                'badge'          => __('Signatur', 'custom-crm'),
                'icon'           => 'dashicons-edit',
                'color'          => '#059669',
                'default'        => true,
                'default_content'=> '{signatur_email}',
            ],
            'footer' => [
                'title'          => __('Footer', 'custom-crm'),
                'desc'           => __('Rechtlicher Footer.', 'custom-crm'),
                'badge'          => __('Footer', 'custom-crm'),
                'icon'           => 'dashicons-admin-generic',
                'color'          => '#475569',
                'default'        => true,
                'default_content'=> '{email_footer}',
            ],
        ],

        // ==========================================
        // 7. INVOICE (Honorarnote / Rechnung)
        // ==========================================
        'invoice' => [
            'header' => [
                'title'          => __('Kopfzeile (Logo)', 'custom-crm'),
                'desc'           => __('Kopfzeile mit Logo.', 'custom-crm'),
                'badge'          => __('Kopfzeile', 'custom-crm'),
                'icon'           => 'dashicons-format-image',
                'color'          => '#be185d',
                'default'        => true,
                'default_content'=> '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:20px;"><tr><td align="left" style="padding-bottom:12px; border-bottom:2px solid #be185d;"><img src="https://x-sieben.at/wp-content/themes/sieben/inc/core/crm/assets/xsieben_logo.png" alt="X SIEBEN" width="170" height="auto" style="display:block; border:0; max-width:170px;"></td><td align="right" style="padding-bottom:12px; border-bottom:2px solid #be185d; font-family:Arial,sans-serif; font-size:11px; color:#64748b;">Honorarnote / Faktura<br><span style="color:#be185d; font-weight:bold;">Buchhaltungsbeleg</span></td></tr></table>',
            ],
            'anrede' => [
                'title'          => __('Persönliche Anrede & Rechnungsankündigung', 'custom-crm'),
                'desc'           => __('Höfliches Rechnungsbegleitschreiben.', 'custom-crm'),
                'badge'          => __('Begrüßung', 'custom-crm'),
                'icon'           => 'dashicons-admin-users',
                'color'          => '#be185d',
                'default'        => true,
                'default_content'=> '<p style="font-family:Arial,sans-serif; font-size:14px; line-height:1.6; color:#1e293b; margin:0 0 16px 0;">{salutation} {titel} {nachname},</p><p style="font-family:Arial,sans-serif; font-size:14px; line-height:1.6; color:#1e293b; margin:0 0 16px 0;">anbei übermitteln wir Ihnen die Honorarnote für die Teilnahme an der Weiterbildung <strong>{kurstitel}</strong>.</p>',
            ],
            'rechnungsdaten' => [
                'title'          => __('Zahlungsdaten & Betrag', 'custom-crm'),
                'desc'           => __('Rechnungsbetrag Netto/Brutto und Zahlungsziel.', 'custom-crm'),
                'badge'          => __('Betrag', 'custom-crm'),
                'icon'           => 'dashicons-money-alt',
                'color'          => '#be185d',
                'default'        => true,
                'default_content'=> '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background:#fdf2f8; border-left:4px solid #be185d; border-top:1px solid #fce7f3; border-right:1px solid #fce7f3; border-bottom:1px solid #fce7f3; border-radius:4px; padding:14px 18px; margin:16px 0; font-family:Arial,sans-serif; font-size:13px; color:#831843;"><tr><td><strong>Zahlungsdetails:</strong><br>• Rechnungsbetrag: <strong>€ {preis_brutto}</strong> (inkl. 20% USt, netto: € {preis_netto})<br>• Zahlungsziel: Zahlbar binnen 14 Tagen ohne Abzug</td></tr></table>',
            ],
            'bankverbindung' => [
                'title'          => __('Bankverbindung & Verwendungszweck', 'custom-crm'),
                'desc'           => __('IBAN, BIC und Verwendungszweck.', 'custom-crm'),
                'badge'          => __('Bank', 'custom-crm'),
                'icon'           => 'dashicons-cart',
                'color'          => '#be185d',
                'default'        => true,
                'default_content'=> '{bankverbindung}',
            ],
            'beilagen' => [
                'title'          => __('Hinweis auf PDF-Honorarnote', 'custom-crm'),
                'desc'           => __('Hinweis auf Honorarnote.pdf.', 'custom-crm'),
                'badge'          => __('Beilage', 'custom-crm'),
                'icon'           => 'dashicons-media-document',
                'color'          => '#be185d',
                'default'        => true,
                'default_content'=> '<p style="font-family:Arial,sans-serif; font-size:13.5px; line-height:1.6; color:#334155; margin:0 0 16px 0;">📎 <strong>Beilage im Anhang:</strong><br>Die rechtsgültige Honorarnote finden Sie als PDF im Anhang zu dieser E-Mail.</p>',
            ],
            'signatur' => [
                'title'          => __('Signatur', 'custom-crm'),
                'desc'           => __('Signatur Backoffice.', 'custom-crm'),
                'badge'          => __('Signatur', 'custom-crm'),
                'icon'           => 'dashicons-edit',
                'color'          => '#059669',
                'default'        => true,
                'default_content'=> '{signatur_email}',
            ],
            'footer' => [
                'title'          => __('Footer', 'custom-crm'),
                'desc'           => __('Rechtlicher Footer.', 'custom-crm'),
                'badge'          => __('Footer', 'custom-crm'),
                'icon'           => 'dashicons-admin-generic',
                'color'          => '#475569',
                'default'        => true,
                'default_content'=> '{email_footer}',
            ],
        ],
    ];

    if ($doc_type !== null) {
        $doc_type = strtolower(trim($doc_type));
        return $definitions[$doc_type] ?? [];
    }

    return $definitions;
}

/**
 * Liefert die aktuell gespeicherte oder standardmäßige Reihenfolge der E-Mail-Abschnitte.
 *
 * @param string $doc_type 'angebot', 'kb', 'angebot_kb', 'anmeldung', 'tb', 'diplom', 'invoice'
 * @param int|null $entry_id Optionaler Eintrag
 * @return array
 */
function crm_get_email_section_order(string $doc_type, $entry_id = null): array
{
    $doc_type    = strtolower(trim($doc_type));
    $definitions = crm_get_email_sections_definitions($doc_type);

    if (empty($definitions)) {
        return [];
    }

    $saved_order = [];
    if (!empty($entry_id)) {
        $entry_order = get_post_meta($entry_id, '_crm_email_sections_order_' . $doc_type, true);
        if (is_array($entry_order) && !empty($entry_order)) {
            $saved_order = $entry_order;
        }
    }

    if (empty($saved_order)) {
        $global_order = get_option('crm_email_sections_order_' . $doc_type, []);
        if (is_array($global_order) && !empty($global_order)) {
            $saved_order = $global_order;
        }
    }

    // Wenn noch keine modulare Reihenfolge gespeichert ist: Fallback auf Standarddefinitionen
    if (empty($saved_order)) {
        $result = [];
        foreach ($definitions as $key => $def) {
            $result[] = [
                'key'             => $key,
                'enabled'         => !empty($def['default']),
                'is_custom'       => false,
                'title'           => $def['title'] ?? ucfirst($key),
                'default_title'   => $def['title'] ?? ucfirst($key),
                'badge'           => $def['badge'] ?? '',
                'default_badge'   => $def['badge'] ?? '',
                'color'           => $def['color'] ?? '#0284c7',
                'content'         => $def['default_content'] ?? '',
                'default_content' => $def['default_content'] ?? '',
                'desc'            => $def['desc'] ?? '',
            ];
        }
        return $result;
    }

    // Gespeicherte Struktur validieren und mit aktuellen Definitionen abgleichen
    $result = [];
    $processed_keys = [];

    foreach ($saved_order as $item) {
        $key = is_array($item) ? ($item['key'] ?? '') : (string)$item;
        if (empty($key)) {
            continue;
        }

        $is_custom = is_array($item) && !empty($item['is_custom']);
        $def       = $definitions[$key] ?? null;

        if ($def !== null || $is_custom) {
            $enabled       = is_array($item) ? (!empty($item['enabled'])) : true;
            $title         = is_array($item) && !empty($item['title']) ? $item['title'] : ($def['title'] ?? ucfirst($key));
            $default_title = $def['title'] ?? ($is_custom ? $title : ucfirst($key));
            $badge         = is_array($item) && isset($item['badge']) ? $item['badge'] : ($def['badge'] ?? '');
            $default_badge = $def['badge'] ?? '';
            $color         = is_array($item) && !empty($item['color']) ? $item['color'] : ($def['color'] ?? '#0284c7');
            $content       = is_array($item) && isset($item['content']) ? $item['content'] : ($def['default_content'] ?? '');
            $default_content = $def['default_content'] ?? '';
            $desc          = $def['desc'] ?? '';

            $result[] = [
                'key'             => $key,
                'enabled'         => (bool)$enabled,
                'is_custom'       => (bool)$is_custom,
                'title'           => $title,
                'default_title'   => $default_title,
                'badge'           => $badge,
                'default_badge'   => $default_badge,
                'color'           => $color,
                'content'         => $content,
                'default_content' => $default_content,
                'desc'            => $desc,
            ];
            $processed_keys[$key] = true;
        }
    }

    // Neu hinzugekommene Standard-Definitionen anhängen
    foreach ($definitions as $key => $def) {
        if (!isset($processed_keys[$key])) {
            $result[] = [
                'key'             => $key,
                'enabled'         => !empty($def['default']),
                'is_custom'       => false,
                'title'           => $def['title'] ?? ucfirst($key),
                'default_title'   => $def['title'] ?? ucfirst($key),
                'badge'           => $def['badge'] ?? '',
                'default_badge'   => $def['badge'] ?? '',
                'color'           => $def['color'] ?? '#0284c7',
                'content'         => $def['default_content'] ?? '',
                'default_content' => $def['default_content'] ?? '',
                'desc'            => $def['desc'] ?? '',
            ];
        }
    }

    return $result;
}

/**
 * Speichert die neue Reihenfolge und den Aktivierungsstatus der E-Mail-Abschnitte.
 *
 * @param string $doc_type
 * @param array $ordered_sections
 * @param int|null $entry_id
 * @return bool
 */
function crm_save_email_section_order(string $doc_type, array $ordered_sections, $entry_id = null): bool
{
    $doc_type = strtolower(trim($doc_type));
    $definitions = crm_get_email_sections_definitions($doc_type);

    if (empty($definitions)) {
        return false;
    }

    $sanitized = [];
    $seen      = [];

    foreach ($ordered_sections as $item) {
        $key       = is_array($item) ? sanitize_key($item['key'] ?? '') : sanitize_key((string)$item);
        $enabled   = is_array($item) ? (!empty($item['enabled'])) : true;
        $is_custom = is_array($item) ? (!empty($item['is_custom'])) : false;
        $title     = is_array($item) ? sanitize_text_field($item['title'] ?? '') : '';
        $badge     = is_array($item) ? sanitize_text_field($item['badge'] ?? '') : '';
        $color     = is_array($item) ? sanitize_hex_color($item['color'] ?? '') : '';
        $content   = is_array($item) ? wp_kses_post(wp_unslash($item['content'] ?? '')) : '';

        if (!empty($key) && !isset($seen[$key])) {
            $sanitized[] = [
                'key'       => $key,
                'enabled'   => (bool)$enabled,
                'is_custom' => (bool)$is_custom,
                'title'     => $title,
                'badge'     => $badge,
                'color'     => $color,
                'content'   => $content,
            ];
            $seen[$key] = true;
        }
    }

    if (!empty($entry_id)) {
        $saved = (bool)update_post_meta($entry_id, '_crm_email_sections_order_' . $doc_type, $sanitized);
    } else {
        $saved = update_option('crm_email_sections_order_' . $doc_type, $sanitized);
    }

    if ($saved && function_exists('crm_on_partial_cache_update')) {
        crm_on_partial_cache_update('email_' . $doc_type, $entry_id);
    }

    return (bool) $saved;
}

/**
 * Setzt die Reihenfolge der E-Mail-Abschnitte auf den Standard zurück.
 *
 * @param string $doc_type
 * @param int|null $entry_id
 * @return bool
 */
function crm_reset_email_section_order(string $doc_type, $entry_id = null): bool
{
    $doc_type = strtolower(trim($doc_type));
    if (!empty($entry_id)) {
        $deleted = delete_post_meta($entry_id, '_crm_email_sections_order_' . $doc_type);
    } else {
        $deleted = delete_option('crm_email_sections_order_' . $doc_type);
    }

    if ($deleted && function_exists('crm_on_partial_cache_update')) {
        crm_on_partial_cache_update('email_' . $doc_type, $entry_id);
    }

    return (bool) $deleted;
}

/**
 * Liefert relevante Platzhalter-Chips für eine E-Mail-Vorlage oder einen Abschnitt.
 *
 * @param string $doc_type
 * @return array
 */
function crm_get_email_placeholders_for_doc(string $doc_type): array
{
    $common = [
        '{salutation}'      => 'Anrede (formell)',
        '{anrede}'          => 'Herr/Frau',
        '{titel}'           => 'Akad. Titel',
        '{vorname}'         => 'Vorname',
        '{nachname}'        => 'Nachname',
        '{kurstitel}'       => 'Kurstitel',
        '{kurstyp}'         => 'Kurstyp (z. B. Lehrgang, Seminar)',
        '{startdatum}'      => 'Startdatum',
        '{enddatum}'        => 'Enddatum',
        '{uhrzeit}'         => 'Uhrzeit',
        '{le}'              => 'Lehreinheiten',
        '{location_wien}'   => 'Standort Wien',
        '{preis_netto}'     => 'Preis Netto',
        '{preis_brutto}'    => 'Preis Brutto',
        '{expire}'          => 'Angebotsfrist',
        '{buchung_email}'   => 'Buchungshinweis',
        '{signatur_email}'  => 'E-Mail Signatur',
        '{email_footer}'    => 'E-Mail Footer',
        '{agb_claim}'       => 'AGB Hinweis',
    ];

    if ($doc_type === 'kb' || $doc_type === 'angebot_kb') {
        $common['{svr}'] = 'SV-Nummer';
    }

    if ($doc_type === 'diplom') {
        $common['{diplom_success}'] = 'Erfolg (z.B. mit ausgezeichnetem Erfolg)';
    }

    if ($doc_type === 'invoice') {
        $common['{bankverbindung}'] = 'Bankverbindung / IBAN';
    }

    return $common;
}

/**
 * Rendert den Drag-and-Drop E-Mail-Abschnitts-Manager im CRM-Admin.
 *
 * @param string $doc_type
 * @param int|null $entry_id
 * @param bool $is_sidebar
 * @return void
 */
function crm_render_email_sections_manager(string $doc_type = 'angebot', $entry_id = null, bool $is_sidebar = false): void
{
    $sections     = crm_get_email_section_order($doc_type, $entry_id);
    $container_id = 'crm-email-sections-list-' . esc_attr($doc_type) . ($entry_id ? '-' . intval($entry_id) : '');
    $placeholders = crm_get_email_placeholders_for_doc($doc_type);
    ?>
    <div class="crm-email-sections-manager <?php echo $is_sidebar ? 'crm-email-sections-sidebar' : 'crm-email-sections-full'; ?>"
         data-doc="<?php echo esc_attr($doc_type); ?>"
         data-entry="<?php echo esc_attr($entry_id ?: 0); ?>">

        <!-- Top Toolbar: Info & Add Block -->
        <div class="crm-sections-top-toolbar" style="margin-bottom:12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <div style="font-size:12px; color:#475569;">
                <span class="dashicons dashicons-info" style="font-size:14px; vertical-align:text-top; color:#0284c7;"></span>
                <?php esc_html_e('Verschieben Sie Abschnitte per Drag & Drop. Klicken Sie auf einen Block, um Inhalt und Variablen zu bearbeiten.', 'custom-crm'); ?>
            </div>
            <button type="button" class="button crm-toggle-add-email-sec-btn" style="background:#0369a1; color:#ffffff; border-color:#0369a1; font-size:12px; height:28px; line-height:26px; padding:0 10px; display:flex; align-items:center; gap:4px;">
                <span class="dashicons dashicons-plus-alt2" style="font-size:14px; width:14px; height:14px;"></span>
                <?php esc_html_e('E-Mail-Block hinzufügen', 'custom-crm'); ?>
            </button>
        </div>

        <!-- Add Section Drawer (Initially Collapsed) -->
        <div class="crm-add-email-sec-drawer" style="display:none; background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px; padding:14px; margin-bottom:14px;">
            <h4 style="margin:0 0 10px 0; font-size:13px; color:#0369a1; display:flex; align-items:center; gap:6px;">
                <span class="dashicons dashicons-welcome-add-page" style="color:#0284c7;"></span>
                <?php esc_html_e('Neuen E-Mail-Abschnitt (Inhaltsblock) anlegen', 'custom-crm'); ?>
            </h4>
            <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:10px; margin-bottom:10px;">
                <div>
                    <label style="display:block; font-size:11px; font-weight:600; color:#334155; margin-bottom:3px;"><?php esc_html_e('Titel des Blocks', 'custom-crm'); ?> *</label>
                    <input type="text" class="crm-new-email-sec-title regular-text" placeholder="z. B. Wichtiger Förderhinweis" style="width:100%; height:30px; font-size:12px;">
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:600; color:#334155; margin-bottom:3px;"><?php esc_html_e('Badge-Text', 'custom-crm'); ?></label>
                    <input type="text" class="crm-new-email-sec-badge regular-text" placeholder="z. B. Hinweis" style="width:100%; height:30px; font-size:12px;">
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:600; color:#334155; margin-bottom:3px;"><?php esc_html_e('Farb-Akzent', 'custom-crm'); ?></label>
                    <select class="crm-new-email-sec-color" style="width:100%; height:30px; font-size:12px;">
                        <option value="#0284c7" selected>Blau (#0284c7)</option>
                        <option value="#007C90">Türkis (#007C90)</option>
                        <option value="#059669">Grün (#059669)</option>
                        <option value="#d97706">Bernstein (#d97706)</option>
                        <option value="#7c3aed">Violett (#7c3aed)</option>
                        <option value="#be185d">Pink (#be185d)</option>
                        <option value="#475569">Schiefer (#475569)</option>
                    </select>
                </div>
            </div>
            <div style="margin-bottom:10px;">
                <label style="display:block; font-size:11px; font-weight:600; color:#334155; margin-bottom:3px;">
                    <?php esc_html_e('Inhalt / HTML-Vorlage (Tabellen-HTML & Platzhalter möglich):', 'custom-crm'); ?>
                </label>
                <textarea class="crm-new-email-sec-content" rows="3" placeholder="<p style='font-family:Arial,sans-serif; font-size:13px;'>Ihr Text hier...</p>" style="width:100%; font-size:12px; font-family:monospace;"></textarea>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" class="button button-primary crm-create-email-sec-btn" style="background:#0284c7; border-color:#0284c7; font-size:12px;">
                    <?php esc_html_e('Abschnitt hinzufügen', 'custom-crm'); ?>
                </button>
                <button type="button" class="button crm-cancel-add-email-sec-btn" style="font-size:12px;">
                    <?php esc_html_e('Abbrechen', 'custom-crm'); ?>
                </button>
            </div>
        </div>

        <!-- Sortable Sections List -->
        <ul class="crm-sortable-email-sections" id="<?php echo esc_attr($container_id); ?>" style="list-style:none; margin:0; padding:0;">
            <?php foreach ($sections as $index => $sec) :
                $is_enabled       = !empty($sec['enabled']);
                $item_color       = !empty($sec['color']) ? $sec['color'] : '#0284c7';
                $is_custom        = !empty($sec['is_custom']);
                $default_content  = $sec['default_content'] ?? '';
                $default_title    = $sec['default_title'] ?? $sec['title'];
                $default_badge    = $sec['default_badge'] ?? ($sec['badge'] ?? '');
                $current_content  = isset($sec['content']) && $sec['content'] !== '' ? $sec['content'] : $default_content;
                $has_custom_content = !$is_custom && !empty($sec['content']) && trim((string)$sec['content']) !== trim((string)$default_content);
            ?>
                <li class="crm-email-section-item <?php echo $is_enabled ? 'is-active' : 'is-disabled'; ?>"
                    data-key="<?php echo esc_attr($sec['key']); ?>"
                    data-custom="<?php echo $is_custom ? '1' : '0'; ?>"
                    data-title="<?php echo esc_attr($sec['title']); ?>"
                    data-default-title="<?php echo esc_attr($default_title); ?>"
                    data-badge="<?php echo esc_attr($sec['badge'] ?? ''); ?>"
                    data-default-badge="<?php echo esc_attr($default_badge); ?>"
                    data-color="<?php echo esc_attr($item_color); ?>"
                    data-content="<?php echo esc_attr($sec['content'] ?? ''); ?>"
                    data-default-content="<?php echo esc_attr($default_content); ?>"
                    style="margin-bottom:8px; background:#ffffff; border:1px solid <?php echo $is_enabled ? '#cbd5e1' : '#e2e8f0'; ?>; border-left:4px solid <?php echo esc_attr($item_color); ?>; border-radius:6px; box-shadow:0 1px 2px rgba(0,0,0,0.03); transition:all 0.15s ease;">

                    <!-- Section Header Row -->
                    <div class="crm-email-sec-header-row" style="display:flex; align-items:center; gap:10px; padding:9px 12px; cursor:pointer;">
                        <!-- Drag Handle -->
                        <span class="crm-email-sec-drag-handle" title="<?php esc_attr_e('Ziehen zum Verschieben', 'custom-crm'); ?>" style="color:#94a3b8; cursor:grab; font-size:16px; display:flex; align-items:center; user-select:none;">
                            &#x2630;
                        </span>

                        <!-- Checkbox -->
                        <label class="crm-email-sec-toggle-label" style="display:flex; align-items:center; margin:0; cursor:pointer;" title="<?php esc_attr_e('Abschnitt in E-Mail ein-/ausblenden', 'custom-crm'); ?>" onclick="event.stopPropagation();">
                            <input type="checkbox"
                                   class="crm-email-sec-checkbox"
                                   value="1"
                                   <?php checked($is_enabled); ?>
                                   style="margin:0; width:15px; height:15px; cursor:pointer;">
                        </label>

                        <!-- Chevron -->
                        <span class="crm-email-sec-chevron" style="color:#64748b; font-size:14px; width:16px; height:16px; display:inline-flex; align-items:center; justify-content:center; transition:transform 0.15s ease; user-select:none;">
                            &#x25B8;
                        </span>

                        <!-- Info -->
                        <div class="crm-email-sec-info" style="flex:1; min-width:0;">
                            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                <strong class="crm-email-sec-title-text" style="font-size:12.5px; color:#0f172a;">
                                    <?php echo esc_html($sec['title']); ?>
                                </strong>
                                <?php if (!empty($sec['badge'])) : ?>
                                    <span class="crm-email-sec-badge" style="font-size:9.5px; font-weight:700; text-transform:uppercase; padding:1px 6px; border-radius:8px; background:#f0f9ff; color:<?php echo esc_attr($item_color); ?>; border:1px solid #e0f2fe;">
                                        <?php echo esc_html($sec['badge']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($is_custom) : ?>
                                    <span style="font-size:9px; font-weight:600; padding:1px 4px; border-radius:4px; background:#e0e7ff; color:#4338ca;">
                                        <?php esc_html_e('Benutzerdefiniert', 'custom-crm'); ?>
                                    </span>
                                <?php endif; ?>
                                <span class="crm-email-sec-custom-badge" style="<?php echo $has_custom_content ? 'display:inline-block;' : 'display:none;'; ?> font-size:8.5px; font-weight:600; padding:1px 4px; border-radius:3px; background:#fef3c7; color:#b45309; border:1px solid #fde68a;">
                                    <?php esc_html_e('Angepasst', 'custom-crm'); ?>
                                </span>
                            </div>
                            <?php if (!empty($sec['desc'])) : ?>
                                <div style="font-size:11px; color:#64748b; margin-top:2px; line-height:1.25; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                    <?php echo esc_html($sec['desc']); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Actions -->
                        <div class="crm-email-sec-actions" style="display:flex; gap:4px; align-items:center;" onclick="event.stopPropagation();">
                            <button type="button" class="button-link crm-edit-email-sec-btn" title="<?php esc_attr_e('Abschnitt bearbeiten', 'custom-crm'); ?>" style="color:#0284c7; font-size:10.5px; font-weight:600; padding:1px 6px; text-decoration:none; display:inline-flex; align-items:center; gap:2px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:3px; cursor:pointer;">
                                ✎ <span class="crm-edit-email-sec-text"><?php esc_html_e('Bearbeiten', 'custom-crm'); ?></span>
                            </button>
                            <?php if ($is_custom) : ?>
                                <button type="button" class="button-link crm-delete-email-sec-btn" title="<?php esc_attr_e('Abschnitt löschen', 'custom-crm'); ?>" style="color:#dc2626; font-size:13px; text-decoration:none; padding:1px 4px;">
                                    ✕
                                </button>
                            <?php endif; ?>
                            <button type="button" class="button-link crm-email-sec-move-up" title="<?php esc_attr_e('Nach oben verschieben', 'custom-crm'); ?>" style="color:#64748b; font-size:13px; text-decoration:none; padding:1px 3px;">
                                &uarr;
                            </button>
                            <button type="button" class="button-link crm-email-sec-move-down" title="<?php esc_attr_e('Nach unten verschieben', 'custom-crm'); ?>" style="color:#64748b; font-size:13px; text-decoration:none; padding:1px 3px;">
                                &darr;
                            </button>
                        </div>
                    </div>

                    <!-- Expansion Drawer: Content & Chips -->
                    <div class="crm-email-sec-drawer" style="display:none; padding:12px 14px; background:#f8fafc; border-top:1px solid #e2e8f0; border-radius:0 0 6px 6px; cursor:default;">
                        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:10px; margin-bottom:10px;">
                            <div>
                                <label style="display:block; font-size:10.5px; font-weight:600; color:#334155; margin-bottom:2px;">
                                    <?php esc_html_e('Block-Titel:', 'custom-crm'); ?>
                                </label>
                                <input type="text" class="crm-email-sec-input-title regular-text" value="<?php echo esc_attr($sec['title']); ?>" style="width:100%; height:28px; font-size:11.5px;">
                            </div>
                            <div>
                                <label style="display:block; font-size:10.5px; font-weight:600; color:#334155; margin-bottom:2px;">
                                    <?php esc_html_e('Badge-Text:', 'custom-crm'); ?>
                                </label>
                                <input type="text" class="crm-email-sec-input-badge regular-text" value="<?php echo esc_attr($sec['badge'] ?? ''); ?>" style="width:100%; height:28px; font-size:11.5px;">
                            </div>
                        </div>

                        <!-- Quick Insert Chips -->
                        <div class="crm-email-chips-bar" style="margin-bottom:8px;">
                            <span style="font-size:10.5px; font-weight:600; color:#64748b; margin-right:6px;"><?php esc_html_e('Verfügbare Platzhalter (Klick zum Einfügen an Cursor-Position):', 'custom-crm'); ?></span>
                            <div style="display:flex; flex-wrap:wrap; gap:4px; margin-top:4px;">
                                <?php foreach ($placeholders as $code => $label) : ?>
                                    <button type="button" class="button-link crm-chip-btn crm-email-insert-chip" data-tag="<?php echo esc_attr($code); ?>" data-code="<?php echo esc_attr($code); ?>" title="<?php echo esc_attr($label); ?>" style="background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; font-size:10px; font-family:monospace; padding:1px 6px; cursor:pointer; color:#0369a1; text-decoration:none;">
                                        <?php echo esc_html($code); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                                <label style="font-size:10.5px; font-weight:600; color:#334155;">
                                    <?php esc_html_e('Block-Inhalt (HTML & Platzhalter):', 'custom-crm'); ?>
                                </label>
                                <?php if (!$is_custom && !empty($default_content)) : ?>
                                    <span style="font-size:9.5px; color:#64748b;">
                                        <?php esc_html_e('Tipp: Platzhalter wie {kurstitel}, {uhrzeit} werden dynamisch ersetzt', 'custom-crm'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <textarea class="crm-email-sec-input-content" rows="5" style="width:100%; font-size:11.5px; font-family:monospace; line-height:1.4;"><?php echo esc_textarea($current_content); ?></textarea>
                        </div>

                        <!-- Drawer Action Buttons (Same as PDF Editor Pattern) -->
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:6px; padding-top:10px; margin-top:10px; border-top:1px solid #e2e8f0;">
                            <div style="display:flex; gap:6px;">
                                <button type="button" class="button button-primary crm-email-sec-apply-btn" style="background:#0284c7; border-color:#0284c7; font-size:11px; height:26px; line-height:24px; padding:0 10px; cursor:pointer;">
                                    ✓ <?php esc_html_e('Übernehmen', 'custom-crm'); ?>
                                </button>
                                <button type="button" class="button crm-email-sec-close-btn" style="font-size:11px; height:26px; line-height:24px; padding:0 8px; cursor:pointer;">
                                    <?php esc_html_e('Schließen', 'custom-crm'); ?>
                                </button>
                            </div>
                            <?php if (!$is_custom) : ?>
                                <button type="button" class="button-link crm-email-sec-reset-btn" title="<?php esc_attr_e('Änderungen verwerfen und auf Systemstandard zurücksetzen', 'custom-crm'); ?>" style="font-size:10.5px; color:#dc2626; text-decoration:none; cursor:pointer;">
                                    ↺ <?php esc_html_e('Auf Standard zurücksetzen', 'custom-crm'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- Bottom Actions Bar -->
        <div class="crm-email-sections-footer-bar" style="margin-top:14px; padding-top:10px; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <div style="display:flex; gap:8px; align-items:center;">
                <button type="button" class="button button-primary crm-save-email-sections-btn" style="background:#0284c7; border-color:#0284c7; font-size:12px; height:30px; line-height:28px;">
                    <span class="dashicons dashicons-saved" style="vertical-align:text-top; font-size:14px;"></span>
                    <?php esc_html_e('E-Mail-Reihenfolge anwenden', 'custom-crm'); ?>
                </button>
                <button type="button" class="button crm-reset-email-sections-btn" style="color:#64748b; font-size:12px; height:30px; line-height:28px;">
                    <span class="dashicons dashicons-undo" style="vertical-align:text-top; font-size:14px;"></span>
                    <?php esc_html_e('Auf Standard zurücksetzen', 'custom-crm'); ?>
                </button>
            </div>
            <span class="crm-email-sections-status" style="font-size:12px; font-weight:600; display:none;"></span>
        </div>
    </div>
    <?php
}

/**
 * Baut die vollständige E-Mail aus ihren modularen Abschnitten zusammen.
 *
 * @param string $doc_type
 * @param int|null $entry_id
 * @param int|null $course_id
 * @param array|null $custom_sections
 * @return string Client-sicheres HTML
 */
function crm_assemble_email_from_sections(string $doc_type, $entry_id = null, $course_id = null, $custom_sections = null): string
{
    require_once dirname(__DIR__) . '/crm-model.php';
    $crm_model = new CRM_Model($course_id, $entry_id);

    $sections = is_array($custom_sections) ? $custom_sections : crm_get_email_section_order($doc_type, $entry_id);

    $body_content = '';

    foreach ($sections as $sec) {
        if (empty($sec['enabled'])) {
            continue;
        }

        $raw_content = $sec['content'] ?? '';
        if (empty(trim($raw_content))) {
            continue;
        }

        // Platzhalter ersetzen
        $parsed = $crm_model->parse_string_with_data($raw_content);

        // Nested placeholders auflösen (z. B. {buchung_email} oder {signatur_email})
        $parsed = $crm_model->parse_string_with_data($parsed);

        // Shortcodes auflösen
        $parsed = do_shortcode($parsed);

        // Block hinzufügen
        $body_content .= '<div class="crm-mail-block crm-block-' . esc_attr($sec['key'] ?? 'section') . '" style="margin-bottom:14px;">' . $parsed . '</div>';
    }

    // Äußeres E-Mail-Container-Layout (Tabelle für Outlook / Mobilgeräte)
    $output = '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse; background-color:#f1f5f9; padding:20px 0;">'
            . '<tr><td align="center" style="padding:15px 10px;">'
            . '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px; background-color:#ffffff; border:1px solid #e2e8f0; border-radius:6px; padding:24px 28px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">'
            . '<tr><td style="font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#1e293b; line-height:1.6;">'
            . $body_content
            . '</td></tr>'
            . '</table>'
            . '</td></tr>'
            . '</table>';

    // Strikte Normalisierung (HTTPS, Cookie-Consent-Restoration, Border 0 etc.)
    if (function_exists('crm_prepare_email_html_for_sending')) {
        $output = crm_prepare_email_html_for_sending($output);
    }

    return $output;
}

/**
 * Erzeugt eine vollständige HTML-Vorschauseite für den E-Mail-Live-Preview-Iframe.
 *
 * @param string $doc_type
 * @param int|null $entry_id
 * @param int|null $course_id
 * @return string
 */
function crm_get_email_preview_html(string $doc_type, $entry_id = null, $course_id = null): string
{
    $email_body = crm_assemble_email_from_sections($doc_type, $entry_id, $course_id);

    $html = '<!DOCTYPE html>'
          . '<html lang="de">'
          . '<head>'
          . '<meta charset="UTF-8">'
          . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
          . '<title>E-Mail Live-Vorschau - ' . esc_html(strtoupper($doc_type)) . '</title>'
          . '<style>'
          . 'body { margin:0; padding:15px 0; background:#f8fafc; font-family:Arial,sans-serif; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; }'
          . 'table { border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt; }'
          . 'img { -ms-interpolation-mode:bicubic; max-width:100%; height:auto; }'
          . 'a { color:#007C90; text-decoration:underline; }'
          . 'p { margin:0 0 14px 0; }'
          . '</style>'
          . '</head>'
          . '<body>'
          . $email_body
          . '</body>'
          . '</html>';

    return $html;
}

/**
 * Liefert die Standard-Betreffzeilen für die 7 E-Mail-Typen.
 *
 * @return array<string, string>
 */
function crm_get_default_email_subjects(): array
{
    return [
        'angebot'    => __('Angebot für: {kurstitel}', 'custom-crm'),
        'kb'         => __('Kurszeitenbestätigung für: {kurstitel}', 'custom-crm'),
        'angebot_kb' => __('Angebot & Kurszeiten für: {kurstitel}', 'custom-crm'),
        'anmeldung'  => __('Ihre Anfrage / Anmeldung für: {kurstitel}', 'custom-crm'),
        'tb'         => __('Ihre Teilnahmebestätigung für: {kurstitel}', 'custom-crm'),
        'diplom'     => __('Diplom {kurstitel}', 'custom-crm'),
        'invoice'    => __('Honorarnote für: {kurstitel}', 'custom-crm'),
        'default'    => __('Ihre Anfrage zu: {kurstitel}', 'custom-crm'),
    ];
}

/**
 * Liefert die konfigurierte Betreffzeile für einen E-Mail-Typ mit Fallback auf Standard.
 *
 * @param string $doc_type
 * @return string
 */
function crm_get_email_subject_template(string $doc_type): string
{
    $defaults = crm_get_default_email_subjects();
    $saved = get_option('crm_email_subject_' . $doc_type, null);
    if ($saved !== null && trim((string)$saved) !== '') {
        return (string)$saved;
    }
    return $defaults[$doc_type] ?? ($defaults['default'] ?? 'Ihre Anfrage');
}

/**
 * Speichert die Betreffzeile für einen E-Mail-Typ.
 *
 * @param string $doc_type
 * @param string $subject
 * @return bool
 */
function crm_save_email_subject_template(string $doc_type, string $subject): bool
{
    $saved = update_option('crm_email_subject_' . $doc_type, sanitize_text_field($subject));
    if ($saved && function_exists('crm_on_partial_cache_update')) {
        crm_on_partial_cache_update('email_subject_' . $doc_type);
    }
    return (bool) $saved;
}

/**
 * Rendert die bearbeitbare Betreff-Box im E-Mail-Editor in den Einstellungen.
 *
 * @param string $doc_type
 */
function crm_render_email_subject_editor(string $doc_type): void
{
    $current_subject = crm_get_email_subject_template($doc_type);
    $defaults        = crm_get_default_email_subjects();
    $default_subject = $defaults[$doc_type] ?? '';
    ?>
    <div class="crm-email-subject-editor-box" data-doc-type="<?php echo esc_attr($doc_type); ?>" style="background:#ffffff; border:1px solid #cbd5e1; border-left:4px solid #0284c7; border-radius:6px; padding:12px 16px; margin:0 0 14px 0; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; flex-wrap:wrap; gap:8px;">
            <label for="crm_email_subject_<?php echo esc_attr($doc_type); ?>" style="font-weight:700; font-size:13px; color:#0f172a; display:flex; align-items:center; gap:6px;">
                <span class="dashicons dashicons-email" style="color:#0284c7; font-size:18px;"></span>
                <span><?php esc_html_e('E-Mail-Betreffzeile (Standard für diesen E-Mail-Typ):', 'custom-crm'); ?></span>
            </label>
            <div class="crm-subject-save-status" style="font-size:11.5px; color:#16a34a; font-weight:600; display:none;">
                ✓ <?php esc_html_e('Gespeichert', 'custom-crm'); ?>
            </div>
        </div>

        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <div style="flex:1; min-width:280px; position:relative;">
                <input type="text" 
                       id="crm_email_subject_<?php echo esc_attr($doc_type); ?>" 
                       name="crm_email_subject[<?php echo esc_attr($doc_type); ?>]"
                       class="crm-email-subject-input regular-text" 
                       value="<?php echo esc_attr($current_subject); ?>" 
                       data-doc-type="<?php echo esc_attr($doc_type); ?>" 
                       data-default="<?php echo esc_attr($default_subject); ?>"
                       style="width:100%; height:34px; font-size:13px; font-weight:600; color:#1e293b;" 
                       placeholder="<?php esc_attr_e('Betreffzeile eingeben, z.B. Angebot für: {kurstitel}', 'custom-crm'); ?>">
            </div>

            <button type="button" 
                    class="button button-primary crm-btn-save-subject" 
                    data-doc-type="<?php echo esc_attr($doc_type); ?>" 
                    style="font-weight:600; height:34px; display:inline-flex; align-items:center; gap:5px;">
                <span class="dashicons dashicons-saved" style="font-size:15px; line-height:20px; vertical-align:text-top;"></span>
                <?php esc_html_e('Betreff speichern', 'custom-crm'); ?>
            </button>

            <button type="button" 
                    class="button crm-btn-reset-subject" 
                    data-doc-type="<?php echo esc_attr($doc_type); ?>" 
                    title="<?php esc_attr_e('Auf Standard-Betreff zurücksetzen', 'custom-crm'); ?>" 
                    style="height:34px; color:#64748b;">
                <span class="dashicons dashicons-undo" style="font-size:15px; line-height:20px; vertical-align:text-top;"></span>
            </button>
        </div>

        <!-- Platzhalter Chips für Betreffzeile -->
        <div class="crm-subject-chips-bar" style="margin-top:8px; display:flex; gap:5px; flex-wrap:wrap; align-items:center;">
            <span style="font-size:11px; color:#64748b; font-weight:600;"><?php esc_html_e('Platzhalter einfügen:', 'custom-crm'); ?></span>
            <button type="button" class="button button-small crm-insert-subject-chip" data-target="crm_email_subject_<?php echo esc_attr($doc_type); ?>" data-chip="{kurstitel}">{kurstitel}</button>
            <button type="button" class="button button-small crm-insert-subject-chip" data-target="crm_email_subject_<?php echo esc_attr($doc_type); ?>" data-chip="{vorname}">{vorname}</button>
            <button type="button" class="button button-small crm-insert-subject-chip" data-target="crm_email_subject_<?php echo esc_attr($doc_type); ?>" data-chip="{nachname}">{nachname}</button>
            <button type="button" class="button button-small crm-insert-subject-chip" data-target="crm_email_subject_<?php echo esc_attr($doc_type); ?>" data-chip="{titel}">{titel}</button>
            <button type="button" class="button button-small crm-insert-subject-chip" data-target="crm_email_subject_<?php echo esc_attr($doc_type); ?>" data-chip="{startdatum}">{startdatum}</button>
            <button type="button" class="button button-small crm-insert-subject-chip" data-target="crm_email_subject_<?php echo esc_attr($doc_type); ?>" data-chip="{kurs_id}">#{kurs_id}</button>
        </div>
    </div>
    <?php
}

/**
 * Liefert die Konfiguration der zugeordneten Standard- und optionalen PDF-Dokumente für E-Mail-Typen.
 *
 * @param string|null $doc_type 'angebot', 'kb', 'angebot_kb', 'anmeldung', 'tb', 'diplom', 'invoice' oder null
 * @return array
 */
function crm_get_email_type_attachments_config($doc_type = null): array
{
    $config = [
        'angebot' => [
            'standard' => [
                [
                    'type'  => 'angebot',
                    'title' => __('Kursangebot (PDF)', 'custom-crm'),
                    'badge' => '📄 Angebot',
                    'icon'  => 'dashicons-media-document',
                    'color' => '#0284c7',
                    'desc'  => __('Verbindliches Angebot samt Lehrplan, Eckdaten und Akkreditierungsnachweisen.', 'custom-crm'),
                ],
            ],
            'optional' => [
                [
                    'type'  => 'kb',
                    'title' => __('Kurszeitenbestätigung (KB)', 'custom-crm'),
                    'badge' => '📅 Kurszeiten',
                    'icon'  => 'dashicons-calendar-alt',
                    'color' => '#0f766e',
                    'desc'  => __('Offizielle Terminbestätigung zur Vorlage bei Förderstellen (AMS, WAFF, Land).', 'custom-crm'),
                ],
                [
                    'type'  => 'agb',
                    'title' => __('AGB & Teilnahmebedingungen 2025', 'custom-crm'),
                    'badge' => '⚖️ AGB 2025',
                    'icon'  => 'dashicons-media-text',
                    'color' => '#475569',
                    'desc'  => __('Offizielle Geschäfts- und Stornobedingungen der X SIEBEN GmbH.', 'custom-crm'),
                ],
            ],
        ],
        'kb' => [
            'standard' => [
                [
                    'type'  => 'kb',
                    'title' => __('Kurszeitenbestätigung (KB)', 'custom-crm'),
                    'badge' => '📅 Kurszeiten',
                    'icon'  => 'dashicons-calendar-alt',
                    'color' => '#0f766e',
                    'desc'  => __('Offizielle Bestätigung der Lehreinheiten und Termine.', 'custom-crm'),
                ],
            ],
            'optional' => [
                [
                    'type'  => 'agb',
                    'title' => __('AGB & Teilnahmebedingungen 2025', 'custom-crm'),
                    'badge' => '⚖️ AGB 2025',
                    'icon'  => 'dashicons-media-text',
                    'color' => '#475569',
                    'desc'  => __('Allgemeine Geschäftsbedingungen.', 'custom-crm'),
                ],
                [
                    'type'  => 'angebot',
                    'title' => __('Kursangebot (PDF)', 'custom-crm'),
                    'badge' => '📄 Angebot',
                    'icon'  => 'dashicons-media-document',
                    'color' => '#0284c7',
                    'desc'  => __('Ergänzendes Kursangebot.', 'custom-crm'),
                ],
            ],
        ],
        'angebot_kb' => [
            'standard' => [
                [
                    'type'  => 'angebot',
                    'title' => __('Kursangebot (PDF)', 'custom-crm'),
                    'badge' => '📄 Angebot',
                    'icon'  => 'dashicons-media-document',
                    'color' => '#0284c7',
                    'desc'  => __('Detailliertes Kursangebot.', 'custom-crm'),
                ],
                [
                    'type'  => 'kb',
                    'title' => __('Kurszeitenbestätigung (KB)', 'custom-crm'),
                    'badge' => '📅 Kurszeiten',
                    'icon'  => 'dashicons-calendar-alt',
                    'color' => '#0f766e',
                    'desc'  => __('Offizielle Bestätigung der Kurszeiten.', 'custom-crm'),
                ],
            ],
            'optional' => [
                [
                    'type'  => 'agb',
                    'title' => __('AGB & Teilnahmebedingungen 2025', 'custom-crm'),
                    'badge' => '⚖️ AGB 2025',
                    'icon'  => 'dashicons-media-text',
                    'color' => '#475569',
                    'desc'  => __('Offizielle AGB 2025.', 'custom-crm'),
                ],
            ],
        ],
        'anmeldung' => [
            'standard' => [
                [
                    'type'  => 'angebot',
                    'title' => __('Kursangebot / Buchungsbestätigung', 'custom-crm'),
                    'badge' => '📄 Buchung',
                    'icon'  => 'dashicons-saved',
                    'color' => '#059669',
                    'desc'  => __('Verbindliche Buchungsunterlagen samt Zahlungszielen.', 'custom-crm'),
                ],
            ],
            'optional' => [
                [
                    'type'  => 'kb',
                    'title' => __('Kurszeitenbestätigung (KB)', 'custom-crm'),
                    'badge' => '📅 Kurszeiten',
                    'icon'  => 'dashicons-calendar-alt',
                    'color' => '#0f766e',
                    'desc'  => __('Kurszeiten zur Einreichung beim Dienstgeber.', 'custom-crm'),
                ],
                [
                    'type'  => 'agb',
                    'title' => __('AGB & Teilnahmebedingungen 2025', 'custom-crm'),
                    'badge' => '⚖️ AGB 2025',
                    'icon'  => 'dashicons-media-text',
                    'color' => '#475569',
                    'desc'  => __('Rechtliche Grundlagen & Stornofristen.', 'custom-crm'),
                ],
            ],
        ],
        'tb' => [
            'standard' => [
                [
                    'type'  => 'tb',
                    'title' => __('Teilnahmebestätigung (TB)', 'custom-crm'),
                    'badge' => '📜 TB',
                    'icon'  => 'dashicons-id-alt',
                    'color' => '#047857',
                    'desc'  => __('Offizieller Nachweis der absolvierten Lehreinheiten (z. B. für AMS oder Dienstgeber).', 'custom-crm'),
                ],
            ],
            'optional' => [
                [
                    'type'  => 'diplom',
                    'title' => __('Diplom (PDF)', 'custom-crm'),
                    'badge' => '🎓 Diplom',
                    'icon'  => 'dashicons-awards',
                    'color' => '#b45309',
                    'desc'  => __('Abschlussdiplom bei erfolgreicher Prüfung.', 'custom-crm'),
                ],
            ],
        ],
        'diplom' => [
            'standard' => [
                [
                    'type'  => 'diplom',
                    'title' => __('Offizielles Diplom (PDF)', 'custom-crm'),
                    'badge' => '🎓 Diplom',
                    'icon'  => 'dashicons-awards',
                    'color' => '#b45309',
                    'desc'  => __('Prämiertes Abschlussdiplom der X SIEBEN Wirtschaftstraining GmbH.', 'custom-crm'),
                ],
            ],
            'optional' => [
                [
                    'type'  => 'tb',
                    'title' => __('Teilnahmebestätigung (TB)', 'custom-crm'),
                    'badge' => '📜 TB',
                    'icon'  => 'dashicons-id-alt',
                    'color' => '#047857',
                    'desc'  => __('Zusätzlicher Stundennachweis.', 'custom-crm'),
                ],
            ],
        ],
        'invoice' => [
            'standard' => [
                [
                    'type'  => 'invoice',
                    'title' => __('Honorarnote / Rechnung (PDF)', 'custom-crm'),
                    'badge' => '💶 Honorarnote',
                    'icon'  => 'dashicons-money-alt',
                    'color' => '#be185d',
                    'desc'  => __('Offizielle Honorarnote mit Zahlungskonditionen und UID-Nummer.', 'custom-crm'),
                ],
            ],
            'optional' => [
                [
                    'type'  => 'agb',
                    'title' => __('AGB & Zahlungsbedingungen', 'custom-crm'),
                    'badge' => '⚖️ AGB 2025',
                    'icon'  => 'dashicons-media-text',
                    'color' => '#475569',
                    'desc'  => __('Allgemeine Geschäftsbedingungen.', 'custom-crm'),
                ],
            ],
        ],
    ];

    if ($doc_type !== null) {
        return $config[$doc_type] ?? ['standard' => [], 'optional' => []];
    }

    return $config;
}

/**
 * Rendert eine informative Übersichts-Box der zugeordneten PDF-Beilagen im E-Mail-Editor.
 *
 * @param string $doc_type
 */
function crm_render_email_type_attachments_info(string $doc_type): void
{
    $conf = crm_get_email_type_attachments_config($doc_type);
    $standards = $conf['standard'] ?? [];
    $optionals = $conf['optional'] ?? [];
    ?>
    <div class="crm-email-template-attachments-info" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px 16px; margin:0 0 16px 0; font-size:12.5px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
            <div style="font-weight:700; color:#0f172a; display:flex; align-items:center; gap:6px;">
                <span class="dashicons dashicons-paperclip" style="color:#0f766e; font-size:18px;"></span>
                <span><?php esc_html_e('Zugeordnete PDF-Anhänge (Standard-Beilagen)', 'custom-crm'); ?></span>
            </div>
            <span style="font-size:11px; color:#64748b; font-style:italic;">
                <?php esc_html_e('Im Mailer beim Versand per Checkbox frei anpassbar', 'custom-crm'); ?>
            </span>
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
            <span style="font-weight:600; color:#475569; font-size:11.5px;"><?php esc_html_e('Standardmäßig beigelegt:', 'custom-crm'); ?></span>
            <?php foreach ($standards as $std) : ?>
                <span class="crm-badge" style="background:<?php echo esc_attr($std['color']); ?>; color:#ffffff; padding:3px 8px; border-radius:12px; font-weight:600; font-size:11px; display:inline-flex; align-items:center; gap:4px;">
                    <span class="dashicons <?php echo esc_attr($std['icon']); ?>" style="font-size:12px; width:12px; height:12px; line-height:12px;"></span>
                    <?php echo esc_html($std['title']); ?>
                </span>
            <?php endforeach; ?>

            <?php if (!empty($optionals)) : ?>
                <span style="color:#94a3b8; margin:0 4px;">|</span>
                <span style="font-weight:600; color:#475569; font-size:11.5px;"><?php esc_html_e('Optional zuschaltbar:', 'custom-crm'); ?></span>
                <?php foreach ($optionals as $opt) : ?>
                    <span class="crm-badge" style="background:#ffffff; border:1px solid #cbd5e1; color:#334155; padding:2px 7px; border-radius:12px; font-size:11px; display:inline-flex; align-items:center; gap:4px;">
                        <span class="dashicons <?php echo esc_attr($opt['icon']); ?>" style="font-size:12px; width:12px; height:12px; line-height:12px; color:<?php echo esc_attr($opt['color']); ?>;"></span>
                        <?php echo esc_html($opt['title']); ?>
                    </span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Rendert die interaktive PDF-Anhänge-Auswahlbox im Mailer (x_sieben_pdf_mailer).
 *
 * @param string $pdf_url Comma-separated or single PDF URL
 * @param int $course_id
 * @param int $entry_id
 * @param string $context
 */
function crm_render_email_attachments_selector(string $pdf_url, int $course_id, int $entry_id, string $context): void
{
    // 1. Array der aktuell übergebenen URLs ermitteln
    $active_urls = array_filter(array_map('trim', explode(',', $pdf_url)));
    
    // 2. Bestehende Dokumente auf dem Server ermitteln (neuer crm-documents Uploads-Ordner, Fallback: Theme angebote/)
    $storage_dir = function_exists('crm_get_pdf_storage_dir') ? crm_get_pdf_storage_dir() : (get_template_directory() . '/angebote/');
    $storage_uri = function_exists('crm_get_pdf_storage_url') ? crm_get_pdf_storage_url() : (get_template_directory_uri() . '/angebote/');
    $legacy_dir  = get_template_directory() . '/angebote/';
    $legacy_uri  = get_template_directory_uri() . '/angebote/';

    // Model laden um Vor- und Nachname für KB / TB / Diplom zu kennen
    $vorname  = '';
    $nachname = '';
    if ($course_id && $entry_id && class_exists('CRM_Model')) {
        $model    = new CRM_Model($course_id, $entry_id);
        $vorname  = $model->vorname;
        $nachname = $model->nachname;
    }

    $resolved_paths = [];
    $resolved_uris  = [];

    // Hilfsfunktion zum Suchen von Dateien (sucht zuerst im neuen crm-documents/ Ordner, dann fallback in angebote/)
    $find_server_pdf = function($prefix, $secondary = null) use ($storage_dir, $storage_uri, $legacy_dir, $legacy_uri, &$resolved_paths, &$resolved_uris) {
        $dirs_to_check = [
            ['dir' => $storage_dir, 'uri' => $storage_uri],
            ['dir' => $legacy_dir,  'uri' => $legacy_uri],
        ];

        $best_file  = false;
        $best_mtime = -1;
        $best_path  = '';
        $best_uri   = '';

        foreach ($dirs_to_check as $target) {
            $dir = $target['dir'];
            $uri = $target['uri'];
            if (!is_dir($dir)) continue;

            $files = scandir($dir);
            foreach ($files as $f) {
                if (substr($f, -4) !== '.pdf') continue;
                if (strpos($f, $prefix) === 0) {
                    if ($secondary === null || strpos($f, $secondary) !== false) {
                        $fpath = $dir . $f;
                        $mtime = file_exists($fpath) ? filemtime($fpath) : 0;
                        if ($mtime > $best_mtime) {
                            $best_mtime = $mtime;
                            $best_file  = $f;
                            $best_path  = $fpath;
                            $best_uri   = $uri . rawurlencode($f);
                        }
                    }
                }
            }
        }

        if ($best_file) {
            $resolved_paths[$best_file] = $best_path;
            $resolved_uris[$best_file]  = $best_uri;
        }

        return $best_file;
    };

    $found_angebot = $find_server_pdf("A_{$entry_id}-");
    $found_kb      = $find_server_pdf("Kurszeitenbestaetigung_{$vorname}_{$nachname}_");
    if (!$found_kb) {
        $found_kb = $find_server_pdf("KB_{$entry_id}-");
    }
    $found_tb      = $find_server_pdf("Teilnahmebestaetigung_{$vorname}_{$nachname}_");
    if (!$found_tb) {
        $found_tb = $find_server_pdf("TB_{$entry_id}-");
    }
    $found_diplom  = false;
    if (!empty($vorname) && !empty($nachname)) {
        $found_diplom = $find_server_pdf("Diplom_", $vorname);
    }
    $found_invoice = $find_server_pdf("Honorarnote_{$entry_id}-");
    if (!$found_invoice) {
        $found_invoice = $find_server_pdf("HN_{$entry_id}-");
    }

    $get_cand_url = function($fname) use ($resolved_uris, $storage_uri) {
        if (!$fname) return '';
        return $resolved_uris[$fname] ?? ($storage_uri . rawurlencode($fname));
    };

    $get_cand_size = function($fname) use ($resolved_paths, $storage_dir) {
        if (!$fname) return '';
        $fpath = $resolved_paths[$fname] ?? ($storage_dir . $fname);
        return file_exists($fpath) ? size_format(filesize($fpath), 1) : '';
    };

    // 3. Strukturierte Dokumentkandidaten zusammenstellen
    $candidates = [
        'angebot' => [
            'doc_type'     => 'angebot',
            'title'        => __('Kursangebot (PDF)', 'custom-crm'),
            'badge'        => '📄 Angebot',
            'badge_class'  => 'crm-badge-blue',
            'color'        => '#0284c7',
            'icon'         => 'dashicons-media-document',
            'url'          => $get_cand_url($found_angebot),
            'filename'     => $found_angebot ?: '',
            'filesize'     => $get_cand_size($found_angebot),
            'can_generate' => true,
            'desc'         => __('Vollständiges Kursangebot samt Modulstruktur.', 'custom-crm'),
        ],
        'kb' => [
            'doc_type'     => 'kb',
            'title'        => __('Kurszeitenbestätigung (KB)', 'custom-crm'),
            'badge'        => '📅 Kurszeiten',
            'badge_class'  => 'crm-badge-teal',
            'color'        => '#0f766e',
            'icon'         => 'dashicons-calendar-alt',
            'url'          => $get_cand_url($found_kb),
            'filename'     => $found_kb ?: '',
            'filesize'     => $get_cand_size($found_kb),
            'can_generate' => true,
            'desc'         => __('Termin- und Stundennachweis für Förderstellen.', 'custom-crm'),
        ],
        'agb' => [
            'doc_type'     => 'agb',
            'title'        => __('AGB & Teilnahmebedingungen 2025', 'custom-crm'),
            'badge'        => '⚖️ AGB 2025',
            'badge_class'  => 'crm-badge-slate',
            'color'        => '#475569',
            'icon'         => 'dashicons-media-text',
            'url'          => 'https://x-sieben.at/wp-content/uploads/2025/09/AGB_X_SIEBEN_2025.pdf',
            'filename'     => 'AGB_X_SIEBEN_2025.pdf',
            'filesize'     => file_exists(ABSPATH . 'wp-content/uploads/2025/09/AGB_X_SIEBEN_2025.pdf') ? size_format(filesize(ABSPATH . 'wp-content/uploads/2025/09/AGB_X_SIEBEN_2025.pdf'), 1) : '428 KB',
            'can_generate' => false,
            'desc'         => __('Offizielles Dokument der X SIEBEN Wirtschaftstraining GmbH.', 'custom-crm'),
        ],
        'tb' => [
            'doc_type'     => 'tb',
            'title'        => __('Teilnahmebestätigung (TB)', 'custom-crm'),
            'badge'        => '📜 TB',
            'badge_class'  => 'crm-badge-green',
            'color'        => '#047857',
            'icon'         => 'dashicons-id-alt',
            'url'          => $get_cand_url($found_tb),
            'filename'     => $found_tb ?: '',
            'filesize'     => $get_cand_size($found_tb),
            'can_generate' => true,
            'desc'         => __('Bestätigung über die absolvierten Lehreinheiten.', 'custom-crm'),
        ],
        'diplom' => [
            'doc_type'     => 'diplom',
            'title'        => __('Diplom (PDF)', 'custom-crm'),
            'badge'        => '🎓 Diplom',
            'badge_class'  => 'crm-badge-amber',
            'color'        => '#b45309',
            'icon'         => 'dashicons-awards',
            'url'          => $get_cand_url($found_diplom),
            'filename'     => $found_diplom ?: '',
            'filesize'     => $get_cand_size($found_diplom),
            'can_generate' => true,
            'desc'         => __('Offizielles Abschlussdiplom.', 'custom-crm'),
        ],
        'invoice' => [
            'doc_type'     => 'invoice',
            'title'        => __('Honorarnote / Rechnung', 'custom-crm'),
            'badge'        => '💶 Honorarnote',
            'badge_class'  => 'crm-badge-pink',
            'color'        => '#be185d',
            'icon'         => 'dashicons-money-alt',
            'url'          => $get_cand_url($found_invoice),
            'filename'     => $found_invoice ?: '',
            'filesize'     => $get_cand_size($found_invoice),
            'can_generate' => true,
            'desc'         => __('Honorarnote mit Zahlungsangaben.', 'custom-crm'),
        ],
    ];

    // Zuordnung: Welche Dokumente sind im aktuellen Mailer-Aufruf aktiv?
    // Wenn eine URL in $active_urls vorkommt, wird sie direkt dem Kandidaten zugewiesen und als aktiv markiert.
    $processed_urls = [];
    foreach ($candidates as $k => &$cand) {
        $cand['is_checked'] = false;

        // URL prüfen gegen aktive URLs
        if (!empty($cand['url'])) {
            foreach ($active_urls as $act_url) {
                if (urldecode($cand['url']) === urldecode($act_url) || basename($cand['url']) === basename($act_url)) {
                    $cand['is_checked'] = true;
                    $cand['url'] = $act_url;
                    $processed_urls[] = $act_url;
                    break;
                }
            }
        }

        // Falls Kandidat noch keine URL hatte, aber eine aktive URL auf seinen Dateityp passt:
        if (empty($cand['url'])) {
            foreach ($active_urls as $act_url) {
                if (in_array($act_url, $processed_urls, true)) continue;
                $bn = basename(parse_url($act_url, PHP_URL_PATH));
                $matches = false;
                if ($k === 'angebot' && (strpos($bn, 'A_') === 0 || stristr($bn, 'angebot') !== false)) $matches = true;
                if ($k === 'kb' && (strpos($bn, 'Kurszeitenbestaetigung_') === 0 || strpos($bn, 'KB_') === 0)) $matches = true;
                if ($k === 'tb' && (strpos($bn, 'Teilnahmebestaetigung_') === 0 || strpos($bn, 'TB_') === 0)) $matches = true;
                if ($k === 'diplom' && strpos($bn, 'Diplom_') === 0) $matches = true;
                if ($k === 'invoice' && (strpos($bn, 'Honorarnote_') === 0 || strpos($bn, 'HN_') === 0)) $matches = true;

                if ($matches) {
                    $cand['url'] = $act_url;
                    $cand['filename'] = $bn;
                    $cand['is_checked'] = true;
                    $processed_urls[] = $act_url;
                    break;
                }
            }
        }
    }
    unset($cand);

    // Alle übrigen URLs in $active_urls sind benutzerdefinierte / aus der Mediathek stammende Anhänge
    $custom_attachments = [];
    foreach ($active_urls as $act_url) {
        if (in_array($act_url, $processed_urls, true)) continue;
        $bn = basename(parse_url($act_url, PHP_URL_PATH));
        $custom_attachments[] = [
            'doc_type'     => 'custom',
            'title'        => $bn,
            'badge'        => '📎 Eigener Anhang',
            'badge_class'  => 'crm-badge-indigo',
            'color'        => '#4f46e5',
            'icon'         => 'dashicons-paperclip',
            'url'          => $act_url,
            'filename'     => $bn,
            'filesize'     => '',
            'is_checked'   => true,
            'is_custom'    => true,
            'can_generate' => false,
            'desc'         => __('Manuell hinzugefügtes PDF-Dokument.', 'custom-crm'),
        ];
    }

    // Ermitteln, wie viele Anhänge aktuell aktiv sind
    $active_count = 0;
    foreach ($candidates as $c) {
        if (!empty($c['is_checked'])) $active_count++;
    }
    $active_count += count($custom_attachments);
    ?>

    <!-- CRM PDF-Anhänge Auswahl & Beilagen-Manager -->
    <div id="crm-email-attachments-selector" class="crm-attachments-box" style="background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:14px 18px; margin:16px 0 20px 0; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
        
        <div class="crm-attachments-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:10px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-paperclip" style="color:#0f766e; font-size:20px;"></span>
                <strong style="font-size:13.5px; color:#0f172a;"><?php esc_html_e('PDF-Anhänge auswählen & beilegen', 'custom-crm'); ?></strong>
                <span id="crm-attachments-count-badge" class="crm-badge" style="background:#0f766e; color:#ffffff; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600;">
                    <?php echo esc_html(sprintf(_n('%d Anhang aktiv', '%d Anhänge aktiv', $active_count, 'custom-crm'), $active_count)); ?>
                </span>
            </div>

            <button type="button" class="button crm-add-custom-attachment-btn" style="display:inline-flex; align-items:center; gap:5px; font-size:12px; height:28px; line-height:26px; border-color:#0284c7; color:#0284c7; font-weight:600;">
                <span class="dashicons dashicons-plus-alt" style="font-size:14px; line-height:14px; width:14px; height:14px;"></span>
                <?php esc_html_e('PDF aus Mediathek / Hochladen', 'custom-crm'); ?>
            </button>
        </div>

        <p style="font-size:11.5px; color:#64748b; margin:0 0 12px 0; line-height:1.4;">
            <?php esc_html_e('Wählen Sie per Checkbox, welche PDF-Dokumente dieser E-Mail als Beilage angehängt werden sollen:', 'custom-crm'); ?>
        </p>

        <div id="crm-attachments-list" class="crm-attachments-list" 
             data-entry="<?php echo absint($entry_id); ?>" 
             data-course="<?php echo absint($course_id); ?>" 
             data-context="<?php echo esc_attr($context); ?>"
             style="display:flex; flex-direction:column; gap:8px;">

            <?php
            // Alle Kandidaten ausgeben
            foreach ($candidates as $key => $doc) :
                $is_active = !empty($doc['is_checked']);
                $has_url   = !empty($doc['url']);
                $card_border = $is_active ? 'border-color:#0f766e; background:#f0fdf4;' : 'border-color:#e2e8f0; background:#f8fafc;';
            ?>
                <div class="crm-attachment-card <?php echo $is_active ? 'is-attached' : ''; ?>" 
                     data-doc-type="<?php echo esc_attr($doc['doc_type']); ?>"
                     data-doc-url="<?php echo esc_url($doc['url']); ?>"
                     style="display:flex; align-items:center; justify-content:space-between; border:1px solid; <?php echo $card_border; ?> border-radius:6px; padding:8px 12px; transition:all 0.15s ease;">
                    
                    <div style="display:flex; align-items:center; gap:10px; flex:1; min-width:0;">
                        <input type="checkbox" 
                               class="crm-attachment-checkbox" 
                               id="crm_att_<?php echo esc_attr($key); ?>" 
                               value="<?php echo esc_url($doc['url']); ?>" 
                               data-doc-type="<?php echo esc_attr($doc['doc_type']); ?>"
                               <?php checked($is_active, true); ?> 
                               style="margin:0; width:17px; height:17px; cursor:pointer;">
                        
                        <label for="crm_att_<?php echo esc_attr($key); ?>" style="cursor:pointer; display:flex; align-items:center; gap:8px; margin:0; min-width:0;">
                            <span class="dashicons <?php echo esc_attr($doc['icon']); ?>" style="color:<?php echo esc_attr($doc['color']); ?>; font-size:18px;"></span>
                            
                            <div style="min-width:0;">
                                <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                    <strong style="font-size:12.5px; color:#0f172a;"><?php echo esc_html($doc['title']); ?></strong>
                                    <span class="crm-badge" style="background:#e2e8f0; color:#334155; font-size:10px; font-weight:600; padding:1px 6px; border-radius:10px;">
                                        <?php echo esc_html($doc['badge']); ?>
                                    </span>
                                    <?php if (!empty($doc['filesize'])) : ?>
                                        <span style="font-size:11px; color:#64748b;">(<?php echo esc_html($doc['filesize']); ?>)</span>
                                    <?php endif; ?>
                                </div>

                                <div class="crm-att-filename-line" style="font-size:11px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:420px;">
                                    <?php if ($has_url) : ?>
                                        <span>📄 <?php echo esc_html($doc['filename'] ?: basename(parse_url($doc['url'], PHP_URL_PATH))); ?></span>
                                    <?php else : ?>
                                        <span style="color:#0284c7; font-style:italic;">⚡ <?php esc_html_e('Wird bei Aktivierung automatisch generiert', 'custom-crm'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </label>
                    </div>

                    <div class="crm-att-actions" style="display:flex; align-items:center; gap:6px; margin-left:12px;">
                        <span class="crm-att-status-indicator" style="font-size:11px; font-weight:600; color:<?php echo $is_active ? '#16a34a' : '#94a3b8'; ?>;">
                            <?php echo $is_active ? __('✓ Angehängt', 'custom-crm') : __('Nicht angehängt', 'custom-crm'); ?>
                        </span>

                        <a href="<?php echo esc_url($doc['url'] ?: '#'); ?>" 
                           target="_blank" 
                           class="button button-small crm-att-preview-btn" 
                           style="<?php echo $has_url ? 'display:inline-flex;' : 'display:none;'; ?> align-items:center; gap:3px; font-size:11px; height:24px; line-height:22px; padding:0 7px;" 
                           title="<?php esc_attr_e('PDF ansehen oder herunterladen', 'custom-crm'); ?>">
                            <span class="dashicons dashicons-visibility" style="font-size:13px; line-height:13px; width:13px; height:13px;"></span>
                            <?php esc_html_e('Vorschau', 'custom-crm'); ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php
            // Benutzerdefinierte Anhänge ausgeben
            foreach ($custom_attachments as $idx => $cust) :
            ?>
                <div class="crm-attachment-card is-attached is-custom" 
                     data-doc-type="custom"
                     data-doc-url="<?php echo esc_url($cust['url']); ?>"
                     style="display:flex; align-items:center; justify-content:space-between; border:1px solid #0f766e; background:#f0fdf4; border-radius:6px; padding:8px 12px; transition:all 0.15s ease;">
                    
                    <div style="display:flex; align-items:center; gap:10px; flex:1; min-width:0;">
                        <input type="checkbox" 
                               class="crm-attachment-checkbox" 
                               id="crm_att_custom_<?php echo esc_attr($idx); ?>" 
                               value="<?php echo esc_url($cust['url']); ?>" 
                               data-doc-type="custom"
                               checked="checked" 
                               style="margin:0; width:17px; height:17px; cursor:pointer;">
                        
                        <label for="crm_att_custom_<?php echo esc_attr($idx); ?>" style="cursor:pointer; display:flex; align-items:center; gap:8px; margin:0; min-width:0;">
                            <span class="dashicons dashicons-paperclip" style="color:#4f46e5; font-size:18px;"></span>
                            <div style="min-width:0;">
                                <div style="display:flex; align-items:center; gap:6px;">
                                    <strong style="font-size:12.5px; color:#0f172a;"><?php echo esc_html($cust['title']); ?></strong>
                                    <span class="crm-badge" style="background:#e0e7ff; color:#3730a3; font-size:10px; font-weight:600; padding:1px 6px; border-radius:10px;">
                                        <?php echo esc_html($cust['badge']); ?>
                                    </span>
                                </div>
                                <div class="crm-att-filename-line" style="font-size:11px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:420px;">
                                    <span>📄 <?php echo esc_html($cust['filename']); ?></span>
                                </div>
                            </div>
                        </label>
                    </div>

                    <div class="crm-att-actions" style="display:flex; align-items:center; gap:6px; margin-left:12px;">
                        <span class="crm-att-status-indicator" style="font-size:11px; font-weight:600; color:#16a34a;">
                            <?php esc_html_e('✓ Angehängt', 'custom-crm'); ?>
                        </span>

                        <a href="<?php echo esc_url($cust['url']); ?>" 
                           target="_blank" 
                           class="button button-small crm-att-preview-btn" 
                           style="display:inline-flex; align-items:center; gap:3px; font-size:11px; height:24px; line-height:22px; padding:0 7px;" 
                           title="<?php esc_attr_e('PDF ansehen', 'custom-crm'); ?>">
                            <span class="dashicons dashicons-visibility" style="font-size:13px; line-height:13px; width:13px; height:13px;"></span>
                            <?php esc_html_e('Vorschau', 'custom-crm'); ?>
                        </a>

                        <button type="button" class="button button-small crm-remove-custom-att-btn" style="color:#dc2626; height:24px; line-height:22px; padding:0 6px;" title="<?php esc_attr_e('Anhang entfernen', 'custom-crm'); ?>">
                            <span class="dashicons dashicons-trash" style="font-size:13px; line-height:13px; width:13px; height:13px;"></span>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    </div>
    <?php
}

