<?php
/**
 * CRM PDF Sections Helper
 *
 * Verwaltet modulare Abschnitte und Unterabschnitte für PDF-Dokumente (Angebot, KB, TB),
 * inklusive Standard-Reihenfolge, Unterabschnitts-Gliederung, Hinzufügen von Abschnitten/Unterabschnitten,
 * Speicherung, Deaktivierung und Rendering für Drag-and-Drop Schnittstellen.
 *
 * Autarkes Modul im X-SIEBEN CRM.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Liefert den sicheren Verzeichnispfad für generierte CRM-PDFs.
 * Nutzt wp-content/uploads/crm-documents/ mit .htaccess- und index.php-Schutz.
 *
 * @return string Absolute directory path ending with DIRECTORY_SEPARATOR
 */
function crm_get_pdf_storage_dir(): string
{
    $upload_dir = wp_upload_dir();
    $dir = trailingslashit($upload_dir['basedir']) . 'crm-documents/';
    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
        if (!file_exists($dir . '.htaccess')) {
            @file_put_contents($dir . '.htaccess', "Options -Indexes\n<FilesMatch \"\.(?i:pdf)$\">\n  Order allow,deny\n  Allow from all\n</FilesMatch>\n");
        }
        if (!file_exists($dir . 'index.php')) {
            @file_put_contents($dir . 'index.php', "<?php\n// Silence is golden.\n");
        }
    }
    return apply_filters('crm_pdf_storage_dir', $dir);
}

/**
 * Liefert die Web-URL für den PDF-Speicherordner.
 *
 * @return string URL ending with '/'
 */
function crm_get_pdf_storage_url(): string
{
    $upload_dir = wp_upload_dir();
    $url = trailingslashit($upload_dir['baseurl']) . 'crm-documents/';
    return apply_filters('crm_pdf_storage_url', $url);
}

/**
 * Erzeugt ein deterministisches, 8-stelliges kryptografisches Token für PDF-Dateinamen.
 * Verhindert Dateinamen-Enumeration und IDOR-Scraping auf Webservern wie Nginx.
 *
 * @param int|string $entry_id
 * @param string     $doc_type
 * @return string 8-character hex token
 */
function crm_generate_pdf_token($entry_id, string $doc_type = ''): string
{
    $salt = defined('NONCE_SALT') ? NONCE_SALT : (defined('AUTH_KEY') ? AUTH_KEY : 'crm_x_sieben_salt_key');
    return substr(hash_hmac('sha256', (string)$entry_id . '|' . $doc_type, $salt), 0, 8);
}

/**
 * Liefert die globalen Master-Einstellungen für Kopf- und Fußzeilen des Angebots.
 *
 * @return array
 */
function crm_get_pdf_master_header_footer(): array
{
    $defaults = [
        'header_mode'    => 'full',        // 'full' (Logo+Adresse), 'logo_only', 'address_only', 'none', 'custom'
        'header_logo'    => true,
        'header_address' => true,
        'header_custom'  => '',
        'footer_mode'    => 'standard',    // 'standard' (Firmendaten+Seitenzahlen), 'full' (+Datum), 'page_numbers_only', 'company_only', 'none', 'custom'
        'footer_company' => true,
        'footer_page_num'=> true,
        'footer_date'    => false,
        'footer_custom'  => '',
    ];

    $saved = get_option('crm_pdf_master_header_footer', []);
    if (!is_array($saved)) {
        return $defaults;
    }
    return wp_parse_args($saved, $defaults);
}

/**
 * Speichert die globalen Master-Einstellungen für Kopf- und Fußzeilen.
 *
 * @param array $data
 * @return bool
 */
function crm_save_pdf_master_header_footer(array $data): bool
{
    $sanitized = [
        'header_mode'    => sanitize_key($data['header_mode'] ?? 'full'),
        'header_logo'    => !empty($data['header_logo']),
        'header_address' => !empty($data['header_address']),
        'header_custom'  => wp_kses_post(wp_unslash($data['header_custom'] ?? '')),
        'footer_mode'    => sanitize_key($data['footer_mode'] ?? 'standard'),
        'footer_company' => !empty($data['footer_company']),
        'footer_page_num'=> !empty($data['footer_page_num']),
        'footer_date'    => !empty($data['footer_date']),
        'footer_custom'  => sanitize_textarea_field(wp_unslash($data['footer_custom'] ?? '')),
    ];
    return update_option('crm_pdf_master_header_footer', $sanitized);
}

/**
 * Liefert die verfügbaren Modi für die Kopfzeile (Header).
 *
 * @return array
 */
function crm_get_pdf_header_modes(): array
{
    return [
        'master'       => __('Wie Master-Einstellung', 'custom-crm'),
        'full'         => __('Logo & Firmenadresse (Standard)', 'custom-crm'),
        'logo_only'    => __('Nur Logo (ohne Adresse)', 'custom-crm'),
        'address_only' => __('Nur Firmenadresse & Kontakt', 'custom-crm'),
        'none'         => __('Keine Kopfzeile (ausblenden)', 'custom-crm'),
        'custom'       => __('Benutzerdefinierter Header (HTML)', 'custom-crm'),
    ];
}

/**
 * Liefert die verfügbaren Modi für die Fußzeile (Footer).
 *
 * @return array
 */
function crm_get_pdf_footer_modes(): array
{
    return [
        'master'            => __('Wie Master-Einstellung', 'custom-crm'),
        'standard'          => __('Firmendaten + Seitenzahlen (Standard)', 'custom-crm'),
        'full'              => __('Firmendaten + Seitenzahlen + Datum', 'custom-crm'),
        'page_numbers_only' => __('Nur Seitenzahlen', 'custom-crm'),
        'company_only'      => __('Nur Firmendaten', 'custom-crm'),
        'none'              => __('Keine Fußzeile (ausblenden)', 'custom-crm'),
        'custom'            => __('Benutzerdefinierter Footer (Text)', 'custom-crm'),
    ];
}

/**
 * Erzeugt ein kompaktes Label für den aktuellen Header- & Footer-Status eines Abschnitts.
 *
 * @param array $sec
 * @return string
 */
function crm_get_pdf_hf_summary_label(array $sec): string
{
    $h_mode = $sec['header_mode'] ?? 'master';
    $f_mode = $sec['footer_mode'] ?? 'master';

    $h_label = 'H: Standard';
    if ($h_mode === 'master') {
        $h_label = 'H: Master';
    } elseif ($h_mode === 'none') {
        $h_label = 'H: Ohne';
    } elseif ($h_mode === 'logo_only') {
        $h_label = 'H: Nur Logo';
    } elseif ($h_mode === 'address_only') {
        $h_label = 'H: Nur Adr';
    } elseif ($h_mode === 'custom') {
        $h_label = 'H: Eigen';
    } elseif ($h_mode === 'full') {
        $h_label = 'H: Logo+Adr';
    }

    $f_label = 'F: Standard';
    if ($f_mode === 'master') {
        $f_label = 'F: Master';
    } elseif ($f_mode === 'none') {
        $f_label = 'F: Ohne';
    } elseif ($f_mode === 'page_numbers_only') {
        $f_label = 'F: Nur Seite';
    } elseif ($f_mode === 'company_only') {
        $f_label = 'F: Nur Firma';
    } elseif ($f_mode === 'full') {
        $f_label = 'F: Firma+Dat+Seite';
    } elseif ($f_mode === 'custom') {
        $f_label = 'F: Eigen';
    }

    return $h_label . ' | ' . $f_label;
}

/**
 * Liefert die Definitionen aller Abschnitte und Unterabschnitte je Dokumententyp.
 *
 * @param string|null $doc_type 'angebot', 'kb', 'tb' oder null für alle
 * @return array
 */
function crm_get_pdf_sections_definitions($doc_type = null): array
{
    $definitions = [
        'angebot' => [
            'deckblatt' => [
                'title'          => __('Anschreiben & Begrüßung (Deckblatt)', 'custom-crm'),
                'desc'           => __('Adressfeld, Angebotsnummer, persönliche Anrede, Einleitungstext, Grußformel, Signatur und Postskriptum.', 'custom-crm'),
                'badge'          => __('Seite 1', 'custom-crm'),
                'icon'           => 'dashicons-email-alt',
                'color'          => '#7c3aed',
                'default'        => true,
                'header_mode'    => 'master',
                'header_logo'    => true,
                'header_address' => true,
                'footer_mode'    => 'master',
                'footer_company' => true,
                'footer_page_num'=> true,
                'footer_date'    => false,
                'subsections' => [
                    'empfaenger' => [
                        'title'           => __('Empfänger & Angebotsdaten', 'custom-crm'),
                        'desc'            => __('Kundenadresse, Angebotsnummer, Datum und Gültigkeitsfrist.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{anrede} {vorname} {nachname}\nAngebotsnummer: {angebotsnummer}\nDatum: {datum}\nGültig bis: {expire}",
                    ],
                    'titel' => [
                        'title'           => __('Dokumententitel', 'custom-crm'),
                        'desc'            => __('Großer Titel "Angebot: [Kurstitel]".', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Angebot: {kurstitel}",
                    ],
                    'anrede_text' => [
                        'title'           => __('Persönliche Anrede & Anschreiben', 'custom-crm'),
                        'desc'            => __('Individuelle Anrede mit Begrüßungstext und Kurseinführung.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Sehr geehrte/r Frau/Herr {nachname},\n\nDanke für Ihr Interesse und willkommen bei der beliebten X SIEBEN Veranstaltung {kurstitel} mit lernförderndem Kleingruppen-Unterricht.\n\nDiese Veranstaltung fokussiert auf {zielgruppe}.",
                    ],
                    'gruss' => [
                        'title'           => __('Grußformel', 'custom-crm'),
                        'desc'            => __('Freundliche Grußformel.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Ich freue mich über Ihre Rückmeldung / Buchung.\nMit freundlichen Grüßen,",
                    ],
                    'signatur' => [
                        'title'           => __('X-SIEBEN Signatur', 'custom-crm'),
                        'desc'            => __('Offizielle Geschäftsführungs-Signatur.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                    'ps' => [
                        'title'           => __('Postskriptum (P.S.)', 'custom-crm'),
                        'desc'            => __('Zusätzlicher Beratungshinweis unter der Signatur.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "PS: Profitieren Sie von unseren flexiblen Teilzahlungsmöglichkeiten und ProvenExpert-Top-Bewertungen.",
                    ],
                    'hinweis_nachstehend' => [
                        'title'           => __('Gliederungsverweis', 'custom-crm'),
                        'desc'            => __('Verweis auf nachfolgende Abschnitte und Anhänge.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Nachstehend: Veranstaltungsinformationen | Anhang 1: Details zu den Inhalten der Veranstaltung | Anhang 2: Exklusive Zusatzleistungen",
                    ],
                ],
            ],
            'veranstaltung' => [
                'title'          => __('Veranstaltungsinformationen', 'custom-crm'),
                'desc'           => __('Kurstitel, Zeitraum / Termine, Lehreinheiten, Modulübersicht, Zertifizierungspartner und Schulungsort.', 'custom-crm'),
                'badge'          => __('Seite 2', 'custom-crm'),
                'icon'           => 'dashicons-calendar-alt',
                'color'          => '#0891b2',
                'default'        => true,
                'header_mode'    => 'master',
                'header_logo'    => true,
                'header_address' => true,
                'footer_mode'    => 'master',
                'footer_company' => true,
                'footer_page_num'=> true,
                'footer_date'    => false,
                'subsections' => [
                    'titel' => [
                        'title'           => __('Titelzeile', 'custom-crm'),
                        'desc'            => __('Kopfzeile "Veranstaltungsinformationen".', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Veranstaltungsinformationen: {kurstitel_short}",
                    ],
                    'zeitraum' => [
                        'title'           => __('Veranstaltungszeitraum', 'custom-crm'),
                        'desc'            => __('Start- und Enddatum mit Kalender-Symbol.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Vom {startdatum} bis einschließlich {enddatum}",
                    ],
                    'lehreinheiten' => [
                        'title'           => __('Lehreinheiten (LE)', 'custom-crm'),
                        'desc'            => __('Angabe der Lehreinheiten (1 LE = 45min).', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Diese Veranstaltung beinhaltet {le} Lehreinheiten (LE, 1 LE = 45min).",
                    ],
                    'module' => [
                        'title'           => __('Modulübersicht', 'custom-crm'),
                        'desc'            => __('Aufzählung aller Module und Trainingseinheiten.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                ],
            ],
            'abschluss' => [
                'title'          => __('Ihr persönlicher Abschluss', 'custom-crm'),
                'desc'           => __('Abschlussbezeichnung, Zertifikat, Teilnahmevoraussetzungen, Zertifizierungspartner, Durchführungsort und Fachberatung.', 'custom-crm'),
                'badge'          => __('Seite 3', 'custom-crm'),
                'icon'           => 'dashicons-awards',
                'color'          => '#d97706',
                'default'        => true,
                'header_mode'    => 'master',
                'header_logo'    => true,
                'header_address' => true,
                'footer_mode'    => 'master',
                'footer_company' => true,
                'footer_page_num'=> true,
                'footer_date'    => false,
                'subsections' => [
                    'titel' => [
                        'title'           => __('Titelzeile', 'custom-crm'),
                        'desc'            => __('Kopfzeile des Abschlusses.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Ihr persönlicher Abschluss: {kurstitel_short}",
                    ],
                    'abschluss_box' => [
                        'title'           => __('Abschluss & Zertifikat', 'custom-crm'),
                        'desc'            => __('Angestrebter Abschluss (z. B. IPMA, SystemCERT).', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                    'voraussetzungen' => [
                        'title'           => __('Teilnahmevoraussetzungen', 'custom-crm'),
                        'desc'            => __('Fachliche und organisatorische Voraussetzungen.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                    'zertifizierungen' => [
                        'title'           => __('Zertifizierungspartner & Badges', 'custom-crm'),
                        'desc'            => __('Offizielle Zertifizierungs-Logos und Partner-Badges.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                    'ort_durchfuehrung' => [
                        'title'           => __('Schulungsort & Durchführungsmodus', 'custom-crm'),
                        'desc'            => __('Wiener Adresse, Online-Unterricht und Durchführungsgarantie.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "ORT: X SIEBEN Wirtschaftstraining, Rochusgasse 6 in 1030 Wien\nDurchführung unserer Schulungen: Online Unterricht | vor Ort in unseren Veranstaltungsräumen | Blended Learning",
                    ],
                    'beratung' => [
                        'title'           => __('Fachberatung & Kontaktbox', 'custom-crm'),
                        'desc'            => __('Beratungs-E-Mail und Kontaktdaten.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Fachberatung & Kontakt: office@x-sieben.at | Tel: 0800 700 170",
                    ],
                ],
            ],
            'kosten' => [
                'title'          => __('Ihre Investition & Kosten', 'custom-crm'),
                'desc'           => __('Kursgebühr inkl. optionale Zertifizierungen, Gesamtkosten, Angebotsgültigkeit und Bankverbindung.', 'custom-crm'),
                'badge'          => __('Seite 4', 'custom-crm'),
                'icon'           => 'dashicons-money-alt',
                'color'          => '#059669',
                'default'        => true,
                'header_mode'    => 'master',
                'header_logo'    => true,
                'header_address' => true,
                'footer_mode'    => 'master',
                'footer_company' => true,
                'footer_page_num'=> true,
                'footer_date'    => false,
                'subsections' => [
                    'titel' => [
                        'title'           => __('Titelzeile', 'custom-crm'),
                        'desc'            => __('Kopfzeile "Ihre Investition".', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Kursgebühr inkl. optionale Zertifizierungen",
                    ],
                    'preistabelle' => [
                        'title'           => __('Preistabelle & Gesamtkosten', 'custom-crm'),
                        'desc'            => __('Aufschlüsselung Nettobetrag, 20% USt. und Bruttobetrag.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                    'gueltigkeit' => [
                        'title'           => __('Angebotsgültigkeit', 'custom-crm'),
                        'desc'            => __('Gültigkeitsfrist des Angebots.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "ANGEBOT GÜLTIG bis max. Gruppengrösse erreicht bzw.: {expire}",
                    ],
                    'bankverbindung' => [
                        'title'           => __('Bankverbindung & Zahlungskonditionen', 'custom-crm'),
                        'desc'            => __('IBAN, BIC und Bankverbindung.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Bankverbindung: Erste Bank | IBAN: AT29 3293 7001 0012 5260 | BIC: RLNWATWWWRN",
                    ],
                ],
            ],
            'anmeldung' => [
                'title'          => __('Anmeldung & Buchung', 'custom-crm'),
                'desc'           => __('Anmeldeformular, Kontaktdatenfelder, AGB-Klauseln und Unterschriftenbereich.', 'custom-crm'),
                'badge'          => __('Seite 5', 'custom-crm'),
                'icon'           => 'dashicons-edit-page',
                'color'          => '#2563eb',
                'default'        => true,
                'header_mode'    => 'master',
                'header_logo'    => true,
                'header_address' => true,
                'footer_mode'    => 'master',
                'footer_company' => true,
                'footer_page_num'=> true,
                'footer_date'    => false,
                'subsections' => [
                    'titel' => [
                        'title'           => __('Titelzeile', 'custom-crm'),
                        'desc'            => __('Kopfzeile "ANMELDUNG".', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "ANMELDUNG: {kurstitel}",
                    ],
                    'kundendaten' => [
                        'title'           => __('Kundendaten & Rechnungsadresse', 'custom-crm'),
                        'desc'            => __('Vorausgefüllte Adressdaten des Kunden.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                    'agb' => [
                        'title'           => __('AGB & Buchungsbedingungen', 'custom-crm'),
                        'desc'            => __('Geschäftsbedingungen und Stornoregelungen.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Bitte beachten Sie unsere Allgemeinen Geschäftsbedingungen (AGB). Mit Ihrer Buchung akzeptieren Sie unsere Richtlinien.",
                    ],
                    'signatur_kunde' => [
                        'title'           => __('Unterschriftenfeld', 'custom-crm'),
                        'desc'            => __('Unterschrift für verbindliche Anmeldung.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                    'anhang_hinweise' => [
                        'title'           => __('Verweise auf Anhänge', 'custom-crm'),
                        'desc'            => __('Verweis auf Anhang 1 und Anhang 2.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Anhang 1: Details zu den Inhalten der Veranstaltung\nAnhang 2: Exklusive Zusatzleistungen",
                    ],
                ],
            ],
            'inhalte' => [
                'title'          => __('Anhang 1: Details zu den Inhalten', 'custom-crm'),
                'desc'           => __('Detaillierte Modulinhalte, Lehrgangsplan und fachliche Schwerpunkte der Veranstaltung.', 'custom-crm'),
                'badge'          => __('Anhang 1', 'custom-crm'),
                'icon'           => 'dashicons-list-view',
                'color'          => '#4f46e5',
                'default'        => true,
                'header_mode'    => 'master',
                'header_logo'    => true,
                'header_address' => true,
                'footer_mode'    => 'master',
                'footer_company' => true,
                'footer_page_num'=> true,
                'footer_date'    => false,
                'subsections' => [
                    'titel' => [
                        'title'           => __('Titelzeile Anhang 1', 'custom-crm'),
                        'desc'            => __('Kopfzeile "Details zu den Inhalten".', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Details zu den Inhalten (Anhang 1)",
                    ],
                    'curriculum' => [
                        'title'           => __('Curriculum & Moduldetails', 'custom-crm'),
                        'desc'            => __('Ausführlicher Lehrgangsplan und Trainingsagenda.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                ],
            ],
            'zusatzleistungen' => [
                'title'          => __('Anhang 2: Exklusive Zusatzleistungen', 'custom-crm'),
                'desc'           => __('3-fach sicher: Durchführungsgarantie, Zufriedenheitsgarantie und Zertifizierungsbegleitung.', 'custom-crm'),
                'badge'          => __('Anhang 2', 'custom-crm'),
                'icon'           => 'dashicons-shield',
                'color'          => '#dc2626',
                'default'        => true,
                'header_mode'    => 'master',
                'header_logo'    => true,
                'header_address' => true,
                'footer_mode'    => 'master',
                'footer_company' => true,
                'footer_page_num'=> true,
                'footer_date'    => false,
                'subsections' => [
                    'titel' => [
                        'title'           => __('Titelzeile Anhang 2', 'custom-crm'),
                        'desc'            => __('Kopfzeile "Exklusive Zusatzleistungen".', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Exklusive Zusatzleistungen (Anhang 2)",
                    ],
                    'garantien' => [
                        'title'           => __('3-fach Garantie-Paket', 'custom-crm'),
                        'desc'            => __('Durchführungs-, Zufriedenheits- und Zertifizierungsbegleitung.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "3-fach sicher mit unserer Durchführungsgarantie, Zufriedenheitsgarantie und Zertifizierungsbegleitung.",
                    ],
                ],
            ],
        ],

        'kb' => [
            'titel' => [
                'title'       => __('Dokumententitel', 'custom-crm'),
                'desc'        => __('Große Hauptüberschrift "Bestätigung Kurszeiten".', 'custom-crm'),
                'badge'       => __('Kopfzeile', 'custom-crm'),
                'icon'        => 'dashicons-heading',
                'color'       => '#0f766e',
                'default'     => true,
                'subsections' => [
                    'haupttitel' => [
                        'title'           => __('Hauptüberschrift', 'custom-crm'),
                        'desc'            => __('Große zentrierte Überschrift "Bestätigung Kurszeiten".', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Bestätigung Kurszeiten",
                    ],
                ],
            ],
            'institut' => [
                'title'       => __('Kursinstitut Angaben', 'custom-crm'),
                'desc'        => __('Name des Kursinstituts, Schulungsort, Kursbezeichnung und Durchführungszeitraum.', 'custom-crm'),
                'badge'       => __('Institut', 'custom-crm'),
                'icon'        => 'dashicons-building',
                'color'       => '#0f766e',
                'default'     => true,
                'subsections' => [
                    'name' => [
                        'title'           => __('Name des Kursinstituts', 'custom-crm'),
                        'desc'            => __('X SIEBEN Wirtschaftstraining GmbH mit exakter Unterstreichung.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "X SIEBEN Wirtschaftstraining GmbH",
                    ],
                    'ort' => [
                        'title'           => __('Schulungsort (Adresse)', 'custom-crm'),
                        'desc'            => __('Wiener Standort bzw. online.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Rochusgasse 6, 1030 Wien bzw. online",
                    ],
                    'bezeichnung' => [
                        'title'           => __('Kursbezeichnung', 'custom-crm'),
                        'desc'            => __('Vollständiger Kurstitel.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{kurstitel}",
                    ],
                    'zeitraum' => [
                        'title'           => __('Kurs von-bis', 'custom-crm'),
                        'desc'            => __('Start- und Enddatum des Kurses.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Vom {startdatum} bis {enddatum}",
                    ],
                ],
            ],
            'teilnehmer' => [
                'title'       => __('KursteilnehmerIn Daten', 'custom-crm'),
                'desc'        => __('Vor- und Nachname des Kunden sowie Sozialversicherungsnummer in einer Zeile.', 'custom-crm'),
                'badge'       => __('Kunde', 'custom-crm'),
                'icon'        => 'dashicons-admin-users',
                'color'       => '#0f766e',
                'default'     => true,
                'subsections' => [
                    'name_svr_row' => [
                        'title'           => __('Name & SV-Nummer Zeile', 'custom-crm'),
                        'desc'            => __('Links Name des Kunden (54%), rechts SV-Nummer (46%).', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                ],
            ],
            'kurstyp' => [
                'title'       => __('Kurstyp Auswahl', 'custom-crm'),
                'desc'        => __('Markierung von Tageskurs, Abendkurs oder Wochenendkurs.', 'custom-crm'),
                'badge'       => __('Typ', 'custom-crm'),
                'icon'        => 'dashicons-tag',
                'color'       => '#0f766e',
                'default'     => true,
                'subsections' => [
                    'kurstyp_box' => [
                        'title'           => __('Kurstyp-Tabelle', 'custom-crm'),
                        'desc'            => __('Auswahlbox Tageskurs / Abendkurs / Wochenendkurs.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                ],
            ],
            'kurszeiten' => [
                'title'       => __('Kurszeiten-Tabelle', 'custom-crm'),
                'desc'        => __('Tabelle mit Wochentagen (Mo-So), Kurszeiten und Tele-/Selbstlernzeiten.', 'custom-crm'),
                'badge'       => __('Tabelle', 'custom-crm'),
                'icon'        => 'dashicons-grid-view',
                'color'       => '#0f766e',
                'default'     => true,
                'subsections' => [
                    'zeiten_tabelle' => [
                        'title'           => __('Wochentage-Matrix', 'custom-crm'),
                        'desc'            => __('Zeitenaufstellung von Montag bis Sonntag.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                ],
            ],
            'hinweis' => [
                'title'       => __('Hinweistext', 'custom-crm'),
                'desc'        => __('Regelungs- und Ablaufplan-Hinweis unter der Tabelle.', 'custom-crm'),
                'badge'       => __('Hinweis', 'custom-crm'),
                'icon'        => 'dashicons-info',
                'color'       => '#0f766e',
                'default'     => true,
                'subsections' => [
                    'hinweistext' => [
                        'title'           => __('Ablaufplan-Hinweis', 'custom-crm'),
                        'desc'            => __('Hinweis bezüglich unregelmäßiger Kurszeiten.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Bei unregelmäßigen Kurszeiten ist ein Ablaufplan der einzelnen Kurswochen beizulegen.",
                    ],
                ],
            ],
            'signatur' => [
                'title'       => __('Stampiglie & Unterschriften', 'custom-crm'),
                'desc'        => __('Offizielle Institut-Stampiglie, Datum und Unterschriftenzeile für Institut und Kunde.', 'custom-crm'),
                'badge'       => __('Signatur', 'custom-crm'),
                'icon'        => 'dashicons-marker',
                'color'       => '#0f766e',
                'default'     => true,
                'subsections' => [
                    'unterschriften' => [
                        'title'           => __('Signatur- & Stampiglienblock', 'custom-crm'),
                        'desc'            => __('X-SIEBEN Stampiglie und Unterschriftsfelder.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                ],
            ],
        ],

        'tb' => [
            'titel' => [
                'title'       => __('Titel & Einleitung', 'custom-crm'),
                'desc'        => __('Haupttitel "Teilnahmebestätigung" und Einleitungsformel "Wir bestätigen, dass".', 'custom-crm'),
                'badge'       => __('Kopfzeile', 'custom-crm'),
                'icon'        => 'dashicons-heading',
                'color'       => '#047857',
                'default'     => true,
                'subsections' => [
                    'haupttitel' => [
                        'title'           => __('Haupttitel "Teilnahmebestätigung"', 'custom-crm'),
                        'desc'            => __('Große Überschrift der Bestätigung.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Teilnahmebestätigung",
                    ],
                    'einleitung' => [
                        'title'           => __('Einleitungsformel', 'custom-crm'),
                        'desc'            => __('"Wir bestätigen, dass..."', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Wir bestätigen, dass",
                    ],
                ],
            ],
            'teilnehmer' => [
                'title'       => __('Kursteilnehmer-Box', 'custom-crm'),
                'desc'        => __('Name, SV-Nummer, Wohnadresse, Postleitzahl und Wohnort des Teilnehmers.', 'custom-crm'),
                'badge'       => __('Teilnehmer', 'custom-crm'),
                'icon'        => 'dashicons-admin-users',
                'color'       => '#047857',
                'default'     => true,
                'subsections' => [
                    'stammdaten' => [
                        'title'           => __('Teilnehmer-Stammdaten', 'custom-crm'),
                        'desc'            => __('Name, SV-Nummer und vollständige Anschrift.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                ],
            ],
            'zeitraum' => [
                'title'       => __('Ausbildungs-Zeitraum', 'custom-crm'),
                'desc'        => __('Zeitspanne von Datum bis Datum.', 'custom-crm'),
                'badge'       => __('Zeitraum', 'custom-crm'),
                'icon'        => 'dashicons-calendar-alt',
                'color'       => '#047857',
                'default'     => true,
                'subsections' => [
                    'zeitspanne' => [
                        'title'           => __('Zeitspanne', 'custom-crm'),
                        'desc'            => __('Vom [Startdatum] bis einschließlich [Enddatum].', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Im Zeitraum vom {startdatum} bis zum {enddatum}",
                    ],
                ],
            ],
            'ausbildungsstaette' => [
                'title'       => __('Ausbildungsstätte & Schulungsort', 'custom-crm'),
                'desc'        => __('Betriebsbezeichnung, Kanzlei-Stammsitz und Anschrift des Wiener Schulungsortes.', 'custom-crm'),
                'badge'       => __('Standort', 'custom-crm'),
                'icon'        => 'dashicons-location',
                'color'       => '#047857',
                'default'     => true,
                'subsections' => [
                    'standort' => [
                        'title'           => __('Standortangaben', 'custom-crm'),
                        'desc'            => __('Betriebsbezeichnung & Kanzleisitz.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                ],
            ],
            'teilnahme' => [
                'title'       => __('Bestätigungstext', 'custom-crm'),
                'desc'        => __('Formelle Bestätigung der Lehrgangsteilnahme inklusive Lehreinheiten (LE).', 'custom-crm'),
                'badge'       => __('Text', 'custom-crm'),
                'icon'        => 'dashicons-media-document',
                'color'       => '#047857',
                'default'     => true,
                'subsections' => [
                    'lehrgangstext' => [
                        'title'           => __('Bestätigungstext', 'custom-crm'),
                        'desc'            => __('Formelle Bestätigung über die erfolgreiche Teilnahme.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => 'an der Ausbildung: <strong>"{kurstitel}"</strong> ({le} LE) teilgenommen hat.',
                    ],
                ],
            ],
            'signatur' => [
                'title'       => __('Datum & Unterschrift', 'custom-crm'),
                'desc'        => __('Ausstellungsdatum und Unterschrift mit X-SIEBEN Signatur.', 'custom-crm'),
                'badge'       => __('Signatur', 'custom-crm'),
                'icon'        => 'dashicons-marker',
                'color'       => '#047857',
                'default'     => true,
                'subsections' => [
                    'unterschrift' => [
                        'title'           => __('Signatur & Datum', 'custom-crm'),
                        'desc'            => __('Ausstellungsdatum und X-SIEBEN Unterschrift.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Datum: {datum}\n\nUnterschrift:\n\nMag. Dr. Johannes Gasberger\nGeschäftsführung",
                    ],
                ],
            ],
        ],

        'diplom' => [
            'header' => [
                'title'       => __('Kopfzeile & Logos', 'custom-crm'),
                'desc'        => __('X-SIEBEN Institutslogo und optionales WBA-Akkreditierungslogo oben rechts.', 'custom-crm'),
                'badge'       => __('Kopfzeile', 'custom-crm'),
                'icon'        => 'dashicons-format-image',
                'color'       => '#b45309',
                'default'     => true,
                'subsections' => [
                    'logo_links' => [
                        'title'           => __('X-SIEBEN Institutslogo', 'custom-crm'),
                        'desc'            => __('Offizielles Firmenlogo links oben.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                    'logo_wba' => [
                        'title'           => __('WBA Akkreditierungslogo', 'custom-crm'),
                        'desc'            => __('WBA Gütesiegel rechts oben (falls zertifiziert).', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                ],
            ],
            'titel_absolvent' => [
                'title'       => __('Diplom-Titel & AbsolventIn', 'custom-crm'),
                'desc'        => __('Große Überschrift "D I P L O M" und Name des Absolventen / der Absolventin.', 'custom-crm'),
                'badge'       => __('Absolvent', 'custom-crm'),
                'icon'        => 'dashicons-awards',
                'color'       => '#b45309',
                'default'     => true,
                'subsections' => [
                    'haupttitel' => [
                        'title'           => __('Haupttitel "D I P L O M"', 'custom-crm'),
                        'desc'            => __('Großer petrolfarbener Schriftzug "D I P L O M".', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "D I P L O M",
                    ],
                    'absolvent_name' => [
                        'title'           => __('Name des/der AbsolventIn', 'custom-crm'),
                        'desc'            => __('Anrede, Titel, Vorname und Zuname in Fettschrift.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{anrede} {titel} {vorname} <strong>{nachname}</strong>",
                    ],
                ],
            ],
            'lehrgang' => [
                'title'       => __('Lehrgangsdaten & Zeitraum', 'custom-crm'),
                'desc'        => __('Lehrgangsbezeichnung, Untertitel, Lehreinheiten (LE) und Ausbildungszeitraum.', 'custom-crm'),
                'badge'       => __('Lehrgang', 'custom-crm'),
                'icon'        => 'dashicons-welcome-learn-more',
                'color'       => '#b45309',
                'default'     => true,
                'subsections' => [
                    'lehrgang_titel' => [
                        'title'           => __('Kurstyp & Ausbildungsbezeichnung', 'custom-crm'),
                        'desc'            => __('Zwischentitel mit {kurstyp} ("HAT DEN LEHRGANG" / "HAT DAS SEMINAR") und Ausbildungsbezeichnung.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "HAT DEN {kurstyp}<br><strong>{kurstitel}</strong>",
                    ],
                    'lehrgang_zeitraum' => [
                        'title'           => __('Umfang & Ausbildungszeitraum', 'custom-crm'),
                        'desc'            => __('Anzahl der Lehreinheiten und Zeitraum von-bis.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "IM AUSMASS VON {le} LEHREINHEITEN A’ JE 45 MINUTEN<br>IM ZEITRAUM VOM {startdatum} BIS ZUM {enddatum} BESUCHT",
                    ],
                ],
            ],
            'abschluss' => [
                'title'       => __('Prüfungsabschluss & Erfolg', 'custom-crm'),
                'desc'        => __('Begutachtungs- & Prüfungsformel und Erfolgsbewertung.', 'custom-crm'),
                'badge'       => __('Erfolg', 'custom-crm'),
                'icon'        => 'dashicons-yes-alt',
                'color'       => '#b45309',
                'default'     => true,
                'subsections' => [
                    'abschluss_formel' => [
                        'title'           => __('Begutachtungs- & Prüfungsformel', 'custom-crm'),
                        'desc'            => __('Formel zur schriftlichen Prüfung und Abschlussarbeit.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "UND NACH POSITIVER BEGUTACHTUNG DER ABSCHLUSSARBEIT SOWIE ERFOLGREICHER<br>ABSOLVIERUNG DER SCHRIFTLICHEN ABSCHLUSSPRÜFUNG",
                    ],
                    'erfolg_grad' => [
                        'title'           => __('Prüfungserfolg-Status', 'custom-crm'),
                        'desc'            => __('"ERFOLGREICH", "MIT GUTEM ERFOLG" oder "MIT AUSGEZEICHNETEM ERFOLG ABGESCHLOSSEN."', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                ],
            ],
            'beglaubigung' => [
                'title'       => __('Beglaubigung & Signatur', 'custom-crm'),
                'desc'        => __('Diplom-Nummer, Ausstellungsdatum, Institut-Stampiglie und Unterschrift.', 'custom-crm'),
                'badge'       => __('Beglaubigung', 'custom-crm'),
                'icon'        => 'dashicons-marker',
                'color'       => '#b45309',
                'default'     => true,
                'subsections' => [
                    'diplom_nr' => [
                        'title'           => __('Diplom-Nummer & Ausstellungsdatum', 'custom-crm'),
                        'desc'            => __('Offizielle 5-stellige Registriernummer und Ort/Datum.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "DIPLOM-NUMMER {diplom_nr} - WIEN, {datum}",
                    ],
                    'stampiglie_unterschrift' => [
                        'title'           => __('X-SIEBEN Stampiglie & Institutsleiter', 'custom-crm'),
                        'desc'            => __('Offizieller X-SIEBEN Stempel und Signatur Mag. Dr. Johannes Gasberger.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                ],
            ],
            'inhalte' => [
                'title'       => __('Ausbildungsinhalte (Module)', 'custom-crm'),
                'desc'        => __('Titel "AUSBILDUNGSINHALTE" und zweispaltige Modulübersicht.', 'custom-crm'),
                'badge'       => __('Inhalte', 'custom-crm'),
                'icon'        => 'dashicons-list-view',
                'color'       => '#b45309',
                'default'     => true,
                'subsections' => [
                    'inhalte_titel' => [
                        'title'           => __('Überschrift "AUSBILDUNGSINHALTE"', 'custom-crm'),
                        'desc'            => __('Zentrierter Zwischentitel.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "A U S B I L D U N G S I N H A L T E",
                    ],
                    'inhalte_matrix' => [
                        'title'           => __('Themenschwerpunkte (2-spaltig)', 'custom-crm'),
                        'desc'            => __('Aufstellung der Ausbildungsinhalte links und rechts.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                ],
            ],
            'guetesiegel' => [
                'title'       => __('Akkreditierungsleiste (Fußzeile)', 'custom-crm'),
                'desc'        => __('Partner- und Gütesiegellogos (Ö-Cert, TÜV Austria, SystemCERT, PMA / IPMA).', 'custom-crm'),
                'badge'       => __('Siegel', 'custom-crm'),
                'icon'        => 'dashicons-shield',
                'color'       => '#b45309',
                'default'     => true,
                'subsections' => [
                    'partner_logos' => [
                        'title'           => __('Zertifizierungspartner-Logos', 'custom-crm'),
                        'desc'            => __('Ö-Cert, TÜV, SystemCERT und PMA Siegel in der Fußzeile.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                ],
            ],
        ],

        'invoice' => [
            'titel_header' => [
                'title'       => __('Titel & Rechnungsnummer', 'custom-crm'),
                'desc'        => __('Dokumententitel "Honorarnote", Rechnungsnummer HN_{course_id} und Datum.', 'custom-crm'),
                'badge'       => __('Kopfzeile', 'custom-crm'),
                'icon'        => 'dashicons-media-text',
                'color'       => '#be185d',
                'default'     => true,
                'subsections' => [
                    'rechnung_titel' => [
                        'title'           => __('Dokumententitel', 'custom-crm'),
                        'desc'            => __('Zentrierte Hauptüberschrift ("Honorarnote").', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Honorarnote",
                    ],
                    'nummer_datum' => [
                        'title'           => __('Rechnungsnummer & Datum', 'custom-crm'),
                        'desc'            => __('Rechnungsnummer und aktuelles Rechnungsdatum.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "<strong>Rechnungsnummer:</strong> HN_{course_id}<br><strong>Datum:</strong> {datum}",
                    ],
                ],
            ],
            'empfaenger' => [
                'title'       => __('Rechnungsempfänger', 'custom-crm'),
                'desc'        => __('Name, Anschrift und gebuchte Veranstaltung des Kunden.', 'custom-crm'),
                'badge'       => __('Kunde', 'custom-crm'),
                'icon'        => 'dashicons-admin-users',
                'color'       => '#be185d',
                'default'     => true,
                'subsections' => [
                    'kundendaten' => [
                        'title'           => __('Kundenanschrift', 'custom-crm'),
                        'desc'            => __('Name, Straße, Hausnummer, PLZ und Ort.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "<strong>Name:</strong> {vorname} {nachname}<br><strong>Adresse:</strong> {ort}",
                    ],
                    'veranstaltung_ref' => [
                        'title'           => __('Veranstaltungsbezeichnung', 'custom-crm'),
                        'desc'            => __('Gebuchter Kurs / Weiterbildung als Referenz.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "<strong>Veranstaltung:</strong> {kurstitel}",
                    ],
                ],
            ],
            'einleitung' => [
                'title'       => __('Einleitungstext', 'custom-crm'),
                'desc'        => __('Abrechnungsanlass und einleitender Satz vor der Tabelle.', 'custom-crm'),
                'badge'       => __('Einleitung', 'custom-crm'),
                'icon'        => 'dashicons-editor-quote',
                'color'       => '#be185d',
                'default'     => true,
                'subsections' => [
                    'einleitungstext' => [
                        'title'           => __('Abrechnungssatz', 'custom-crm'),
                        'desc'            => __('"Hiermit stellen wir Ihnen folgende Leistungen in Rechnung:".', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Hiermit stellen wir Ihnen folgende Leistungen in Rechnung:",
                    ],
                ],
            ],
            'positionen' => [
                'title'       => __('Leistungs- & Preistabelle', 'custom-crm'),
                'desc'        => __('Tabellarische Positionen: Leistung, Lehreinheiten, Einzelpreis und Gesamtpreis.', 'custom-crm'),
                'badge'       => __('Tabelle', 'custom-crm'),
                'icon'        => 'dashicons-cart',
                'color'       => '#be185d',
                'default'     => true,
                'subsections' => [
                    'positionen_tabelle' => [
                        'title'           => __('Positionstabelle', 'custom-crm'),
                        'desc'            => __('Leistungstabelle mit Zeilen für Menge und Preis.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                    'gesamtbetrag' => [
                        'title'           => __('Gesamtbetrag', 'custom-crm'),
                        'desc'            => __('Hervorgehobene Summenzeile der Rechnung.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "<strong>Gesamtbetrag (Brutto):</strong> {gesamtpreis} €",
                    ],
                ],
            ],
            'zahlung' => [
                'title'       => __('Zahlungsanweisung & Bankverbindung', 'custom-crm'),
                'desc'        => __('Zahlungsziel, Fälligkeit, Überweisungshinweis, IBAN und BIC.', 'custom-crm'),
                'badge'       => __('Zahlung', 'custom-crm'),
                'icon'        => 'dashicons-money-alt',
                'color'       => '#be185d',
                'default'     => true,
                'subsections' => [
                    'zahlungsziel' => [
                        'title'           => __('Zahlungsfrist & Anweisung', 'custom-crm'),
                        'desc'            => __('Hinweis zur Überweisungsfrist und Zahlungsziel.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "Bitte überweisen Sie den Betrag bis zum {expire} auf das Konto von X SIEBEN Wirtschaftstraining GmbH.",
                    ],
                    'bankverbindung' => [
                        'title'           => __('Bankverbindung & IBAN', 'custom-crm'),
                        'desc'            => __('IBAN und BIC der X-SIEBEN.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "IBAN: AT29 3293 7001 0012 5260 | BIC: RLNWATWWWRN",
                    ],
                ],
            ],
            'signatur' => [
                'title'       => __('Fußzeile & Unternehmensdaten', 'custom-crm'),
                'desc'        => __('X-SIEBEN Firmendaten, Standorte, Firmenbuch- und UID-Nummer in der Fußzeile.', 'custom-crm'),
                'badge'       => __('Fußzeile', 'custom-crm'),
                'icon'        => 'dashicons-editor-insertmore',
                'color'       => '#be185d',
                'default'     => true,
                'subsections' => [
                    'aussteller_info' => [
                        'title'           => __('Unternehmens- & Standortdaten', 'custom-crm'),
                        'desc'            => __('Offizielle Firmendaten, Geschäftsführung, UID und Standorte (wird am Seitenende fixiert).', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                ],
            ],
        ],
    ];

    if ($doc_type !== null) {
        $key = strtolower(trim($doc_type));
        return $definitions[$key] ?? [];
    }

    return $definitions;
}

/**
 * Ersetzt Platzhalter wie {vorname}, {nachname}, {kurstitel} etc. in benutzerdefiniertem Text.
 *
 * @param string $content
 * @param object|null $course
 * @return string
 */
function crm_replace_pdf_placeholders(string $content, $course = null): string
{
    if (empty($content) || !is_object($course)) {
        return $content;
    }

    $c_entry_id = absint($course->entry_id ?? 0);
    $c_course_id = absint($course->course_id ?? 0);
    $diplom_nr_raw = $c_entry_id > 0 ? $c_entry_id : ($c_course_id > 0 ? $c_course_id : 5624);

    $clean_title = html_entity_decode(html_entity_decode($course->title ?? '', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
    $clean_short = html_entity_decode(html_entity_decode($course->titel_short ?? '', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');

    // Preis- und Mengenberechnung
    $netto_val = !empty($course->preis_netto) ? (float)str_replace(['.', ','], ['', '.'], (string)$course->preis_netto) : 0.0;
    if ($netto_val == 0.0 && !empty($course->kosten)) {
        $netto_val = (float)$course->kosten;
    }
    $brutto_val = !empty($course->preis_brutto) ? (float)str_replace(['.', ','], ['', '.'], (string)$course->preis_brutto) : ($netto_val * 1.20);
    $le_val     = !empty($course->anzahl_le) ? (int)$course->anzahl_le : 1;
    $single_val = $netto_val > 0 && $le_val > 0 ? ($netto_val / $le_val) : 0.0;

    $expire_val = !empty($course->expire) ? $course->expire : date('d.m.Y', strtotime('+14 days'));

    $kurstyp_val    = !empty($course->kurstyp) ? trim($course->kurstyp) : 'Lehrgang';
    $kurstyp_upper  = mb_strtoupper($kurstyp_val, 'UTF-8');
    $kurstyp_phrase = function_exists('crm_get_diplom_kurstyp_phrase')
        ? crm_get_diplom_kurstyp_phrase($kurstyp_val)
        : ('HAT DEN ' . $kurstyp_upper);

    $map = [
        'HAT DEN {kurstyp}'       => $kurstyp_phrase,
        'HAT DEN {kurstyp_upper}' => $kurstyp_phrase,
        'HAT DAS {kurstyp}'       => $kurstyp_phrase,
        'HAT DAS {kurstyp_upper}' => $kurstyp_phrase,
        'Hat den {kurstyp}'       => mb_convert_case($kurstyp_phrase, MB_CASE_TITLE, 'UTF-8'),
        '{hat_den_kurstyp}'       => $kurstyp_phrase,
        '{kurstyp_phrase}'        => $kurstyp_phrase,
        '{kurstyp_upper}'         => $kurstyp_upper,
        '{kurstyp_lower}'         => mb_strtolower($kurstyp_val, 'UTF-8'),
        '{kurstyp}'               => $kurstyp_val,
        '{vorname}'            => $course->vorname ?? '',
        '{nachname}'           => $course->nachname ?? '',
        '{anrede}'             => $course->anrede ?? '',
        '{anrede_brief}'       => (strcasecmp($course->anrede ?? '', 'Herr') === 0 || strcasecmp($course->anrede ?? '', 'Herrn') === 0) ? 'Herrn' : ($course->anrede ?? ''),
        '{kunden_firma}'       => $course->customer_company ?? '',
        '{empfaenger_adresse}' => method_exists($course, 'format_postal_address') ? $course->format_postal_address('A', true) : '',
        '{titel}'              => $course->titel ?? '',
        '{kurstitel}'          => $clean_title,
        '{kurstitel_short}' => $clean_short,
        '{startdatum}'      => $course->start_datum ?? '',
        '{enddatum}'        => $course->end_datum ?? '',
        '{uhrzeit}'         => !empty($course->uhrzeit) && is_string($course->uhrzeit) ? $course->uhrzeit : (!empty($course->kurszeiten) && is_string($course->kurszeiten) ? $course->kurszeiten : '09:00 – 17:00 Uhr'),
        '{kurszeiten}'      => !empty($course->kurszeiten) && is_string($course->kurszeiten) ? $course->kurszeiten : (!empty($course->uhrzeit) && is_string($course->uhrzeit) ? $course->uhrzeit : '09:00 – 17:00 Uhr'),
        '{zeiten}'          => !empty($course->kurszeiten) && is_string($course->kurszeiten) ? $course->kurszeiten : (!empty($course->uhrzeit) && is_string($course->uhrzeit) ? $course->uhrzeit : '09:00 – 17:00 Uhr'),
        '{preis}'           => number_format($netto_val, 2, ',', '.'),
        '{preis_netto}'     => number_format($netto_val, 2, ',', '.'),
        '{preis_brutto}'    => number_format($brutto_val, 2, ',', '.'),
        '{gesamtpreis}'     => number_format($brutto_val, 2, ',', '.'),
        '{le_single}'       => number_format($single_val, 2, ',', '.'),
        '{location_wien}'   => $course->location_wien ?? 'Rochusgasse 6, 1030 Wien',
        '{salutation}'      => (strcasecmp($course->anrede ?? '', 'Herr') === 0 || strcasecmp($course->anrede ?? '', 'Herrn') === 0) ? 'Sehr geehrter Herr' : ((strcasecmp($course->anrede ?? '', 'Frau') === 0) ? 'Sehr geehrte Frau' : 'Sehr geehrte Damen und Herren'),
        '{ort}'             => !empty($course->street) ? trim(($course->street ?? '') . ' ' . ($course->house_number ?? '') . ', ' . ($course->zip_code ?? '') . ' ' . ($course->city ?? '')) : ($course->location_wien ?? 'Rochusgasse 6, 1030 Wien bzw. online'),
        '{schulungsort}'    => $course->location_wien ?? 'Rochusgasse 6, 1030 Wien bzw. online',
        '{le}'              => $course->anzahl_le ?? '',
        '{institut}'        => $course->company_name ?? 'X SIEBEN Wirtschaftstraining GmbH',
        '{datum}'           => date('d.m.Y'),
        '{current_date}'    => date('d.m.Y'),
        '{expire}'          => $expire_val,
        '[Datum]'           => $expire_val,
        '{angebotsnummer}'  => $course->angebotsnummer ?? '',
        '{zielgruppe}'      => $course->zielgruppe ?? '',
        '{firmenname}'      => $course->company_name ?? 'X SIEBEN Wirtschaftstraining GmbH',
        '{email}'           => $course->email ?? '',
        '{telefon}'         => $course->phone ?? ($course->company_phone ?? ''),
        '{diplom_nr}'       => sprintf('%05d', $diplom_nr_raw),
        '{course_id}'       => $c_course_id,
        '{entry_id}'        => $c_entry_id,
        '{iban}'            => 'AT29 3293 7001 0012 5260',
        '{bic}'             => 'RLNWATWWWRN',
    ];
    return str_replace(array_keys($map), array_values($map), $content);
}

/**
 * Prüft, ob der übergebene Inhalt ein unveränderter Standard- oder Legacy-Platzhaltertext ist,
 * der fälschlicherweise als benutzerdefinierter Inhalt in der Datenbank gespeichert wurde.
 *
 * @param string $sec_key
 * @param string $sub_key
 * @param string $content
 * @return bool
 */
function crm_is_legacy_default_pdf_content(string $sec_key, string $sub_key, string $content): bool
{
    $norm = preg_replace('/\s+/', ' ', trim($content));
    if ($norm === '' || $norm === '{standard}') {
        return true;
    }

    $legacy_map = [
        'deckblatt' => [
            'empfaenger' => [
                '{anrede} {vorname} {nachname} Angebotsnummer: {angebotsnummer} Datum: {datum} Gültig bis: {expire}',
                'Angebotsnummer: {angebotsnummer}',
            ],
            'titel' => [
                'Angebot: {kurstitel}',
            ],
            'anrede_text' => [
                'Sehr geehrte/r Frau/Herr {nachname}, Danke für Ihr Interesse und willkommen bei der beliebten X SIEBEN Veranstaltung {kurstitel} mit lernförderndem Kleingruppen-Unterricht. Diese Veranstaltung fokussiert auf {zielgruppe}.',
                'Danke für Ihr Interesse und willkommen bei der beliebten X SIEBEN Veranstaltung',
            ],
            'gruss' => [
                'Ich freue mich über Ihre Rückmeldung / Buchung. Mit freundlichen Grüßen,',
                'Ich freue mich über Ihre Rückmeldung / Buchung.',
            ],
            'ps' => [
                'PS: Profitieren Sie von unseren flexiblen Teilzahlungsmöglichkeiten und ProvenExpert-Top-Bewertungen.',
            ],
            'hinweis_nachstehend' => [
                'Nachstehend: Veranstaltungsinformationen | Anhang 1: Details zu den Inhalten der Veranstaltung | Anhang 2: Exklusive Zusatzleistungen',
            ],
        ],
        'veranstaltung' => [
            'titel' => [
                'Veranstaltungsinformationen: {kurstitel_short}',
            ],
            'zeitraum' => [
                'Vom {startdatum} bis einschließlich {enddatum}',
            ],
            'lehreinheiten' => [
                'Diese Veranstaltung beinhaltet {le} Lehreinheiten (LE, 1 LE = 45min).',
            ],
            'ort_durchfuehrung' => [
                'ORT: X SIEBEN Wirtschaftstraining, Rochusgasse 6 in 1030 Wien Durchführung unserer Schulungen: Online Unterricht | vor Ort in unseren Veranstaltungsräumen | Blended Learning',
            ],
        ],
        'abschluss' => [
            'titel' => [
                'Ihr persönlicher Abschluss: {kurstitel_short}',
            ],
            'ort_durchfuehrung' => [
                'ORT: X SIEBEN Wirtschaftstraining, Rochusgasse 6 in 1030 Wien Durchführung unserer Schulungen: Online Unterricht | vor Ort in unseren Veranstaltungsräumen | Blended Learning',
            ],
            'beratung' => [
                'Fachberatung & Kontakt: office@x-sieben.at | Tel: 0800 700 170',
            ],
        ],
        'kosten' => [
            'titel' => [
                'Kursgebühr inkl. optionale Zertifizierungen',
            ],
            'gueltigkeit' => [
                'ANGEBOT GÜLTIG bis max. Gruppengrösse erreicht bzw.: {expire}',
            ],
            'bankverbindung' => [
                'Bankverbindung: Erste Bank | IBAN: AT29 3293 7001 0012 5260 | BIC: RLNWATWWWRN',
            ],
        ],
        'anmeldung' => [
            'titel' => [
                'ANMELDUNG: {kurstitel}',
            ],
            'agb' => [
                'Bitte beachten Sie unsere Allgemeinen Geschäftsbedingungen (AGB). Mit Ihrer Buchung akzeptieren Sie unsere Richtlinien.',
            ],
            'anhang_hinweise' => [
                'Anhang 1: Details zu den Inhalten der Veranstaltung Anhang 2: Exklusive Zusatzleistungen',
            ],
        ],
        'inhalte' => [
            'titel' => [
                'Details zu den Inhalten (Anhang 1)',
            ],
        ],
        'zusatzleistungen' => [
            'titel' => [
                'Exklusive Zusatzleistungen (Anhang 2)',
            ],
            'garantien' => [
                '3-fach sicher mit unserer Durchführungsgarantie, Zufriedenheitsgarantie und Zertifizierungsbegleitung.',
            ],
        ],
    ];

    if (isset($legacy_map[$sec_key][$sub_key])) {
        foreach ($legacy_map[$sec_key][$sub_key] as $snippet) {
            $norm_snippet = preg_replace('/\s+/', ' ', trim($snippet));
            if ($norm === $norm_snippet || (strlen($norm_snippet) > 20 && strpos($norm, $norm_snippet) !== false)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Holt die geordnete Liste aller Abschnitte inkl. Unterabschnitten für einen Dokumententyp.
 *
 * @param string $doc_type 'angebot', 'kb', 'tb'
 * @param int|null $entry_id Optional für eintragsbezogene Reihenfolge
 * @return array Liste strukturierter Abschnitte mit Unterabschnitten
 */
function crm_get_pdf_section_order(string $doc_type, $entry_id = null): array
{
    $doc_type    = strtolower(trim($doc_type));
    $definitions = crm_get_pdf_sections_definitions($doc_type);

    if (empty($definitions)) {
        return [];
    }

    // 1. Eintragsbezogene Konfiguration (falls vorhanden)
    $saved_order = null;
    if (!empty($entry_id)) {
        $entry_opt_key = 'crm_pdf_sec_' . $doc_type . '_' . intval($entry_id);
        $entry_val     = get_option($entry_opt_key, null);
        if (is_array($entry_val) && !empty($entry_val)) {
            $saved_order = $entry_val;
        }
    }

    // 2. Globale Konfiguration
    if ($saved_order === null) {
        $global_opt_key = 'crm_pdf_section_order_' . $doc_type;
        $saved_order    = get_option($global_opt_key, null);
    }

    // Auto-Migration für Angebot: zertifizierungen & ort_durchfuehrung von veranstaltung (Seite 2) nach abschluss (Seite 3) verschieben
    if ($doc_type === 'angebot' && is_array($saved_order)) {
        $moved_subs = [];
        $need_migration = false;
        foreach ($saved_order as &$sec_item) {
            if (is_array($sec_item) && ($sec_item['key'] ?? '') === 'veranstaltung' && !empty($sec_item['subsections'])) {
                $new_v_subs = [];
                foreach ($sec_item['subsections'] as $sub) {
                    $s_k = $sub['key'] ?? '';
                    if ($s_k === 'zertifizierungen' || $s_k === 'ort_durchfuehrung') {
                        $moved_subs[$s_k] = $sub;
                        $need_migration = true;
                    } else {
                        $new_v_subs[] = $sub;
                    }
                }
                $sec_item['subsections'] = $new_v_subs;
            }
        }
        unset($sec_item);

        if (!empty($moved_subs)) {
            foreach ($saved_order as &$sec_item) {
                if (is_array($sec_item) && ($sec_item['key'] ?? '') === 'abschluss' && isset($sec_item['subsections'])) {
                    $existing_keys = array_column($sec_item['subsections'], 'key');
                    $new_a_subs = [];
                    foreach ($sec_item['subsections'] as $sub) {
                        $s_k = $sub['key'] ?? '';
                        if ($s_k === 'beratung') {
                            if (!in_array('zertifizierungen', $existing_keys, true) && isset($moved_subs['zertifizierungen'])) {
                                $new_a_subs[] = $moved_subs['zertifizierungen'];
                            }
                            if (!in_array('ort_durchfuehrung', $existing_keys, true) && isset($moved_subs['ort_durchfuehrung'])) {
                                $new_a_subs[] = $moved_subs['ort_durchfuehrung'];
                            }
                        }
                        $new_a_subs[] = $sub;
                    }
                    if (!in_array('zertifizierungen', array_column($new_a_subs, 'key'), true) && isset($moved_subs['zertifizierungen'])) {
                        $new_a_subs[] = $moved_subs['zertifizierungen'];
                    }
                    if (!in_array('ort_durchfuehrung', array_column($new_a_subs, 'key'), true) && isset($moved_subs['ort_durchfuehrung'])) {
                        $new_a_subs[] = $moved_subs['ort_durchfuehrung'];
                    }
                    $sec_item['subsections'] = $new_a_subs;
                }
            }
            unset($sec_item);
        }

        if ($need_migration) {
            if (!empty($entry_id) && isset($entry_opt_key)) {
                update_option($entry_opt_key, $saved_order);
            } elseif (isset($global_opt_key)) {
                update_option($global_opt_key, $saved_order);
            }
        }
    }

    $result    = [];
    $seen_keys = [];

    // Gespeicherte Struktur parsen
    if (is_array($saved_order)) {
        foreach ($saved_order as $item) {
            $key       = is_array($item) ? ($item['key'] ?? '') : (string)$item;
            $enabled   = is_array($item) ? (!empty($item['enabled'])) : true;
            $is_custom = is_array($item) ? (!empty($item['is_custom'])) : false;

            if (empty($key) || isset($seen_keys[$key])) {
                continue;
            }

            if (isset($definitions[$key])) {
                // Vordefinierter Standard-Abschnitt
                $def = $definitions[$key];
                $subsections = [];
                $seen_subs = [];

                // Gespeicherte Unterabschnitte
                if (is_array($item) && isset($item['subsections']) && is_array($item['subsections'])) {
                    foreach ($item['subsections'] as $sub) {
                        $s_key     = $sub['key'] ?? '';
                        $s_enabled = !empty($sub['enabled']);
                        $s_custom  = !empty($sub['is_custom']);

                        if (empty($s_key) || isset($seen_subs[$s_key])) {
                            continue;
                        }

                        if (isset($def['subsections'][$s_key])) {
                            $sub_def = $def['subsections'][$s_key];
                            $raw_content = $sub['content'] ?? '';

                            // AUTO-CLEANUP / HEALING FÜR BESTEHENDE DATEN:
                            if (!$s_custom && !empty($raw_content)) {
                                $def_content = $sub_def['default_content'] ?? '';
                                $norm_raw = preg_replace('/\s+/', ' ', trim($raw_content));
                                $norm_def = preg_replace('/\s+/', ' ', trim($def_content));
                                if ($norm_raw === $norm_def || $norm_raw === '{standard}' || crm_is_legacy_default_pdf_content($key, $s_key, $norm_raw)) {
                                    $raw_content = '';
                                }
                            }

                            $subsections[] = [
                                'key'             => $s_key,
                                'title'           => $sub['title'] ?? $sub_def['title'],
                                'orig_title'      => $sub_def['title'],
                                'desc'            => $sub_def['desc'] ?? '',
                                'enabled'         => $s_enabled,
                                'is_custom'       => false,
                                'content'         => $raw_content,
                                'default_content' => $sub_def['default_content'] ?? '',
                            ];
                            $seen_subs[$s_key] = true;
                        } elseif ($s_custom) {
                            $subsections[] = [
                                'key'             => $s_key,
                                'title'           => $sub['title'] ?? __('Benutzerdefinierter Unterabschnitt', 'custom-crm'),
                                'orig_title'      => $sub['title'] ?? __('Benutzerdefinierter Unterabschnitt', 'custom-crm'),
                                'desc'            => __('Eigener Text / HTML-Inhalt', 'custom-crm'),
                                'enabled'         => $s_enabled,
                                'is_custom'       => true,
                                'content'         => $sub['content'] ?? '',
                                'default_content' => '',
                            ];
                            $seen_subs[$s_key] = true;
                        }
                    }
                }

                // Fehlende Standard-Unterabschnitte anfügen
                if (!empty($def['subsections'])) {
                    foreach ($def['subsections'] as $s_key => $sub_def) {
                        if (!isset($seen_subs[$s_key])) {
                            $subsections[] = [
                                'key'             => $s_key,
                                'title'           => $sub_def['title'],
                                'orig_title'      => $sub_def['title'],
                                'desc'            => $sub_def['desc'] ?? '',
                                'enabled'         => $sub_def['default'] ?? true,
                                'is_custom'       => false,
                                'content'         => '',
                                'default_content' => $sub_def['default_content'] ?? '',
                            ];
                            $seen_subs[$s_key] = true;
                        }
                    }
                }

                $h_mode     = isset($item['header_mode']) ? sanitize_key($item['header_mode']) : ($def['header_mode'] ?? 'master');
                $h_logo     = isset($item['header_logo']) ? !empty($item['header_logo']) : ($def['header_logo'] ?? true);
                $h_addr     = isset($item['header_address']) ? !empty($item['header_address']) : ($def['header_address'] ?? true);
                $h_custom   = isset($item['header_custom']) ? wp_kses_post(wp_unslash($item['header_custom'])) : ($def['header_custom'] ?? '');

                $f_mode     = isset($item['footer_mode']) ? sanitize_key($item['footer_mode']) : ($def['footer_mode'] ?? 'master');
                $f_company  = isset($item['footer_company']) ? !empty($item['footer_company']) : ($def['footer_company'] ?? true);
                $f_page_num = isset($item['footer_page_num']) ? !empty($item['footer_page_num']) : ($def['footer_page_num'] ?? true);
                $f_date     = isset($item['footer_date']) ? !empty($item['footer_date']) : ($def['footer_date'] ?? false);
                $f_custom   = isset($item['footer_custom']) ? sanitize_textarea_field(wp_unslash($item['footer_custom'])) : ($def['footer_custom'] ?? '');

                $result[] = [
                    'key'            => $key,
                    'title'          => $def['title'],
                    'desc'           => $def['desc'],
                    'badge'          => $def['badge'],
                    'icon'           => $def['icon'],
                    'color'          => $def['color'],
                    'enabled'        => $enabled,
                    'is_custom'      => false,
                    'content'        => '',
                    'header_mode'    => $h_mode,
                    'header_logo'    => (bool)$h_logo,
                    'header_address' => (bool)$h_addr,
                    'header_custom'  => $h_custom,
                    'footer_mode'    => $f_mode,
                    'footer_company' => (bool)$f_company,
                    'footer_page_num'=> (bool)$f_page_num,
                    'footer_date'    => (bool)$f_date,
                    'footer_custom'  => $f_custom,
                    'subsections'    => $subsections,
                ];
                $seen_keys[$key] = true;

            } elseif ($is_custom) {
                // Benutzerdefinierter neuer Hauptabschnitt / neue Seite
                $subsections = [];
                if (is_array($item) && isset($item['subsections']) && is_array($item['subsections'])) {
                    foreach ($item['subsections'] as $sub) {
                        $subsections[] = [
                            'key'             => $sub['key'] ?? uniqid('sub_'),
                            'title'           => $sub['title'] ?? __('Unterabschnitt', 'custom-crm'),
                            'orig_title'      => $sub['title'] ?? __('Unterabschnitt', 'custom-crm'),
                            'desc'            => __('Eigener Text / HTML', 'custom-crm'),
                            'enabled'         => !empty($sub['enabled']),
                            'is_custom'       => true,
                            'content'         => $sub['content'] ?? '',
                            'default_content' => '',
                        ];
                    }
                }

                $h_mode     = isset($item['header_mode']) ? sanitize_key($item['header_mode']) : 'master';
                $h_logo     = isset($item['header_logo']) ? !empty($item['header_logo']) : true;
                $h_addr     = isset($item['header_address']) ? !empty($item['header_address']) : true;
                $h_custom   = isset($item['header_custom']) ? wp_kses_post(wp_unslash($item['header_custom'])) : '';

                $f_mode     = isset($item['footer_mode']) ? sanitize_key($item['footer_mode']) : 'master';
                $f_company  = isset($item['footer_company']) ? !empty($item['footer_company']) : true;
                $f_page_num = isset($item['footer_page_num']) ? !empty($item['footer_page_num']) : true;
                $f_date     = isset($item['footer_date']) ? !empty($item['footer_date']) : false;
                $f_custom   = isset($item['footer_custom']) ? sanitize_textarea_field(wp_unslash($item['footer_custom'])) : '';

                $result[] = [
                    'key'            => $key,
                    'title'          => $item['title'] ?? __('Benutzerdefinierter Abschnitt', 'custom-crm'),
                    'desc'           => __('Benutzerdefinierte Seite / Block', 'custom-crm'),
                    'badge'          => $item['badge'] ?? __('Zusatz', 'custom-crm'),
                    'icon'           => 'dashicons-admin-page',
                    'color'          => $item['color'] ?? '#0891b2',
                    'enabled'        => $enabled,
                    'is_custom'      => true,
                    'content'        => $item['content'] ?? '',
                    'header_mode'    => $h_mode,
                    'header_logo'    => (bool)$h_logo,
                    'header_address' => (bool)$h_addr,
                    'header_custom'  => $h_custom,
                    'footer_mode'    => $f_mode,
                    'footer_company' => (bool)$f_company,
                    'footer_page_num'=> (bool)$f_page_num,
                    'footer_date'    => (bool)$f_date,
                    'footer_custom'  => $f_custom,
                    'subsections'    => $subsections,
                ];
                $seen_keys[$key] = true;
            }
        }
    }

    // Alle eventuell noch fehlenden Standard-Abschnitte hinten anfügen
    foreach ($definitions as $key => $def) {
        if (!isset($seen_keys[$key])) {
            $subsections = [];
            if (!empty($def['subsections'])) {
                foreach ($def['subsections'] as $s_key => $sub_def) {
                    $subsections[] = [
                        'key'             => $s_key,
                        'title'           => $sub_def['title'],
                        'orig_title'      => $sub_def['title'],
                        'desc'            => $sub_def['desc'] ?? '',
                        'enabled'         => $sub_def['default'] ?? true,
                        'is_custom'       => false,
                        'content'         => '',
                        'default_content' => $sub_def['default_content'] ?? '',
                    ];
                }
            }

            $result[] = [
                'key'            => $key,
                'title'          => $def['title'],
                'desc'           => $def['desc'],
                'badge'          => $def['badge'],
                'icon'           => $def['icon'],
                'color'          => $def['color'],
                'enabled'        => $def['default'] ?? true,
                'is_custom'      => false,
                'content'        => '',
                'header_mode'    => $def['header_mode'] ?? 'master',
                'header_logo'    => $def['header_logo'] ?? true,
                'header_address' => $def['header_address'] ?? true,
                'header_custom'  => '',
                'footer_mode'    => $def['footer_mode'] ?? 'master',
                'footer_company' => $def['footer_company'] ?? true,
                'footer_page_num'=> $def['footer_page_num'] ?? true,
                'footer_date'    => $def['footer_date'] ?? false,
                'footer_custom'  => '',
                'subsections'    => $subsections,
            ];
            $seen_keys[$key] = true;
        }
    }

    return $result;
}

/**
 * Liefert ausschließlich die AKTIVEN (eingeschalteten) Abschnittsschlüssel in geordneter Reihenfolge.
 *
 * @param string $doc_type
 * @param int|null $entry_id
 * @return string[] Array der aktiven Keys, z. B. ['deckblatt', 'veranstaltung', 'abschluss', ...]
 */
function crm_get_active_pdf_sections(string $doc_type, $entry_id = null): array
{
    $sections = crm_get_pdf_section_order($doc_type, $entry_id);
    $active   = [];

    foreach ($sections as $sec) {
        if (!empty($sec['enabled'])) {
            $active[] = $sec['key'];
        }
    }

    // Fallback: Falls versehentlich alle Abschnitte deaktiviert wurden, alle Standard-Keys liefern
    if (empty($active)) {
        $defs = crm_get_pdf_sections_definitions($doc_type);
        $active = array_keys($defs);
    }

    return $active;
}

/**
 * Liefert die geordneten aktiven Unterabschnitte für einen bestimmten Hauptabschnitt.
 *
 * @param string $doc_type 'angebot', 'kb', 'tb'
 * @param string $section_key z. B. 'deckblatt', 'veranstaltung'
 * @param int|null $entry_id
 * @return array Liste von Unterabschnitten ['key' => ..., 'title' => ..., 'is_custom' => bool, 'content' => ...]
 */
function crm_get_active_subsections(string $doc_type, string $section_key, $entry_id = null): array
{
    $sections = crm_get_pdf_section_order($doc_type, $entry_id);
    foreach ($sections as $sec) {
        if ($sec['key'] === $section_key) {
            if (empty($sec['enabled'])) {
                return [];
            }
            $active_subs = [];
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (!empty($sub['enabled'])) {
                        $active_subs[] = $sub;
                    }
                }
            }
            return $active_subs;
        }
    }
    return [];
}

/**
 * Liefert die vollständige aktive Struktur für einen Dokumenttyp inkl. Unterabschnitten.
 *
 * @param string $doc_type 'angebot', 'kb', 'tb'
 * @param int|null $entry_id
 * @return array
 */
function crm_get_pdf_full_structure(string $doc_type, $entry_id = null): array
{
    $sections = crm_get_pdf_section_order($doc_type, $entry_id);
    $active_structure = [];

    foreach ($sections as $sec) {
        if (!empty($sec['enabled'])) {
            $active_subs = [];
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (!empty($sub['enabled'])) {
                        $active_subs[] = $sub;
                    }
                }
            }
            $sec['active_subsections'] = $active_subs;
            $active_structure[] = $sec;
        }
    }

    return $active_structure;
}

/**
 * Speichert die neue Reihenfolge und den Aktivierungsstatus der Abschnitte und Unterabschnitte.
 *
 * @param string $doc_type 'angebot', 'kb', 'tb'
 * @param array $ordered_sections Array hierarchischer Abschnitte
 * @param int|null $entry_id Optional: nur für diesen Eintrag speichern (null = globale Vorlage)
 * @return bool
 */
function crm_save_pdf_section_order(string $doc_type, array $ordered_sections, $entry_id = null): bool
{
    $doc_type    = strtolower(trim($doc_type));
    $definitions = crm_get_pdf_sections_definitions($doc_type);

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

        // Subsections parsen
        $subsections = [];
        if (is_array($item) && isset($item['subsections']) && is_array($item['subsections'])) {
            $seen_subs = [];
            foreach ($item['subsections'] as $sub) {
                $sub_key     = sanitize_key($sub['key'] ?? '');
                $sub_enabled = !empty($sub['enabled']);
                $sub_custom  = !empty($sub['is_custom']);
                $sub_title   = sanitize_text_field($sub['title'] ?? '');
                $sub_content = wp_kses_post(wp_unslash($sub['content'] ?? ''));

                if (!empty($sub_key) && !isset($seen_subs[$sub_key])) {
                    // Standard-Unterabschnitte bereinigen: Wenn Content leer, {standard} oder dem Standard-Snippet entspricht, als leer ('') speichern!
                    if (!$sub_custom && isset($definitions[$key]['subsections'][$sub_key])) {
                        $def_sub_content = $definitions[$key]['subsections'][$sub_key]['default_content'] ?? '';
                        $norm_sub = preg_replace('/\s+/', ' ', trim($sub_content));
                        $norm_def = preg_replace('/\s+/', ' ', trim($def_sub_content));
                        if ($norm_sub === $norm_def || $norm_sub === '{standard}' || crm_is_legacy_default_pdf_content($key, $sub_key, $norm_sub)) {
                            $sub_content = '';
                        }
                    }

                    $subsections[] = [
                        'key'       => $sub_key,
                        'enabled'   => (bool)$sub_enabled,
                        'is_custom' => (bool)$sub_custom,
                        'title'     => $sub_title,
                        'content'   => $sub_content,
                    ];
                    $seen_subs[$sub_key] = true;
                }
            }
        }

        $h_mode     = sanitize_key($item['header_mode'] ?? 'master');
        $h_logo     = !empty($item['header_logo']);
        $h_addr     = !empty($item['header_address']);
        $h_custom   = wp_kses_post(wp_unslash($item['header_custom'] ?? ''));

        $f_mode     = sanitize_key($item['footer_mode'] ?? 'master');
        $f_company  = !empty($item['footer_company']);
        $f_page_num = !empty($item['footer_page_num']);
        $f_date     = !empty($item['footer_date']);
        $f_custom   = sanitize_textarea_field(wp_unslash($item['footer_custom'] ?? ''));

        if (!empty($key) && !isset($seen[$key])) {
            $sanitized[] = [
                'key'            => $key,
                'enabled'        => (bool)$enabled,
                'is_custom'      => (bool)$is_custom,
                'title'          => $title,
                'badge'          => $badge,
                'color'          => $color,
                'content'        => $content,
                'header_mode'    => $h_mode,
                'header_logo'    => (bool)$h_logo,
                'header_address' => (bool)$h_addr,
                'header_custom'  => $h_custom,
                'footer_mode'    => $f_mode,
                'footer_company' => (bool)$f_company,
                'footer_page_num'=> (bool)$f_page_num,
                'footer_date'    => (bool)$f_date,
                'footer_custom'  => $f_custom,
                'subsections'    => $subsections,
            ];
            $seen[$key] = true;
        }
    }

    // Fehlende Definitionen als deaktiviert anfügen
    foreach ($definitions as $k => $d) {
        if (!isset($seen[$k])) {
            $default_subs = [];
            if (!empty($d['subsections'])) {
                foreach ($d['subsections'] as $sk => $sd) {
                    $default_subs[] = [
                        'key'       => $sk,
                        'enabled'   => false,
                        'is_custom' => false,
                        'title'     => $sd['title'] ?? '',
                        'content'   => '',
                    ];
                }
            }
            $sanitized[] = [
                'key'            => $k,
                'enabled'        => false,
                'is_custom'      => false,
                'title'          => $d['title'] ?? '',
                'badge'          => $d['badge'] ?? '',
                'color'          => $d['color'] ?? '#007C90',
                'content'        => '',
                'header_mode'    => $d['header_mode'] ?? 'master',
                'header_logo'    => $d['header_logo'] ?? true,
                'header_address' => $d['header_address'] ?? true,
                'header_custom'  => '',
                'footer_mode'    => $d['footer_mode'] ?? 'master',
                'footer_company' => $d['footer_company'] ?? true,
                'footer_page_num'=> $d['footer_page_num'] ?? true,
                'footer_date'    => $d['footer_date'] ?? false,
                'footer_custom'  => '',
                'subsections'    => $default_subs,
            ];
        }
    }

    if (!empty($entry_id)) {
        $opt_key = 'crm_pdf_sec_' . $doc_type . '_' . intval($entry_id);
        $saved = update_option($opt_key, $sanitized);
    } else {
        $opt_key = 'crm_pdf_section_order_' . $doc_type;
        $saved = update_option($opt_key, $sanitized);
    }

    if ($saved && function_exists('crm_on_partial_cache_update')) {
        crm_on_partial_cache_update('pdf_' . $doc_type, $entry_id);
    }

    return (bool) $saved;
}

/**
 * Setzt die Abschnitts-Reihenfolge auf den Systemstandard zurück.
 *
 * @param string $doc_type 'angebot', 'kb', 'tb'
 * @param int|null $entry_id Optional: nur für diesen Eintrag zurücksetzen
 * @return bool
 */
function crm_reset_pdf_section_order(string $doc_type, $entry_id = null): bool
{
    $doc_type = strtolower(trim($doc_type));

    if (!empty($entry_id)) {
        $opt_key = 'crm_pdf_sec_' . $doc_type . '_' . intval($entry_id);
        $deleted = delete_option($opt_key);
    } else {
        $opt_key = 'crm_pdf_section_order_' . $doc_type;
        $deleted = delete_option($opt_key);
    }

    if ($deleted && function_exists('crm_on_partial_cache_update')) {
        crm_on_partial_cache_update('pdf_' . $doc_type, $entry_id);
    }

    return (bool) $deleted;
}

/**
 * Rendert die interaktive hierarchische Drag-and-Drop Liste für die Abschnitte eines Dokuments.
 *
 * @param string $doc_type 'angebot', 'kb', 'tb'
 * @param int|null $entry_id Optional für die Eintrags-Vorschau
 * @param bool $is_sidebar True wenn in der schmalen Sidebar dargestellt
 * @return void
 */
function crm_render_pdf_sections_manager(string $doc_type = 'angebot', $entry_id = null, bool $is_sidebar = false): void
{
    $sections = crm_get_pdf_section_order($doc_type, $entry_id);
    $container_id = 'crm-sections-list-' . esc_attr($doc_type) . ($entry_id ? '-' . intval($entry_id) : '');
    ?>
    <div class="crm-pdf-sections-manager <?php echo $is_sidebar ? 'crm-sections-sidebar' : 'crm-sections-full'; ?>"
         data-doc="<?php echo esc_attr($doc_type); ?>"
         data-entry="<?php echo esc_attr($entry_id ?: 0); ?>">

        <!-- Toolbar: Add Section Button & Form -->
        <div class="crm-sections-top-toolbar" style="margin-bottom:12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <div style="font-size:12px; color:#475569;">
                <span class="dashicons dashicons-info" style="font-size:14px; vertical-align:text-top; color:#007C90;"></span>
                <?php esc_html_e('Klicken Sie auf eine Seite, um deren Unterabschnitte zu bearbeiten oder zu sortieren.', 'custom-crm'); ?>
            </div>
            <button type="button" class="button crm-toggle-add-section-btn" style="background:#0f172a; color:#ffffff; border-color:#0f172a; font-size:12px; height:28px; line-height:26px; padding:0 10px; display:flex; align-items:center; gap:4px;">
                <span class="dashicons dashicons-plus-alt2" style="font-size:14px; width:14px; height:14px;"></span>
                <?php esc_html_e('Abschnitt / Seite hinzufügen', 'custom-crm'); ?>
            </button>
        </div>

        <!-- Add Section Drawer (Initially Hidden) -->
        <div class="crm-add-section-drawer" style="display:none; background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px; padding:14px; margin-bottom:14px;">
            <h4 style="margin:0 0 10px 0; font-size:13px; color:#0f172a; display:flex; align-items:center; gap:6px;">
                <span class="dashicons dashicons-welcome-add-page" style="color:#007C90;"></span>
                <?php esc_html_e('Neuen Abschnitt (neue PDF-Seite) anlegen', 'custom-crm'); ?>
            </h4>
            <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:10px; margin-bottom:10px;">
                <div>
                    <label style="display:block; font-size:11px; font-weight:600; color:#334155; margin-bottom:3px;"><?php esc_html_e('Titel des Abschnitts', 'custom-crm'); ?> *</label>
                    <input type="text" class="crm-new-sec-title regular-text" placeholder="z. B. Zusätzliche Vereinbarungen" style="width:100%; height:30px; font-size:12px;">
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:600; color:#334155; margin-bottom:3px;"><?php esc_html_e('Badge-Text', 'custom-crm'); ?></label>
                    <input type="text" class="crm-new-sec-badge regular-text" placeholder="z. B. Seite 8 oder Zusatz" style="width:100%; height:30px; font-size:12px;">
                </div>
                <div>
                    <label style="display:block; font-size:11px; font-weight:600; color:#334155; margin-bottom:3px;"><?php esc_html_e('Farb-Akzent', 'custom-crm'); ?></label>
                    <select class="crm-new-sec-color" style="width:100%; height:30px; font-size:12px;">
                        <option value="#7c3aed">Violett (#7c3aed)</option>
                        <option value="#0891b2" selected>Türkis (#0891b2)</option>
                        <option value="#059669">Grün (#059669)</option>
                        <option value="#d97706">Bernstein (#d97706)</option>
                        <option value="#2563eb">Blau (#2563eb)</option>
                        <option value="#dc2626">Rot (#dc2626)</option>
                        <option value="#475569">Schiefer (#475569)</option>
                    </select>
                </div>
            </div>
            <div style="margin-bottom:10px;">
                <label style="display:block; font-size:11px; font-weight:600; color:#334155; margin-bottom:3px;">
                    <?php esc_html_e('Inhalt / Freitext (HTML erlaubt, Platzhalter wie {vorname}, {nachname}, {kurstitel} möglich)', 'custom-crm'); ?>
                </label>
                <textarea class="crm-new-sec-content" rows="3" placeholder="Geben Sie hier den Inhalt für diese Seite ein..." style="width:100%; font-size:12px; font-family:monospace;"></textarea>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" class="button button-primary crm-create-section-btn" style="background:#007C90; border-color:#007C90; font-size:12px;">
                    <?php esc_html_e('Abschnitt hinzufügen', 'custom-crm'); ?>
                </button>
                <button type="button" class="button crm-cancel-add-sec-btn" style="font-size:12px;">
                    <?php esc_html_e('Abbrechen', 'custom-crm'); ?>
                </button>
            </div>
        </div>

        <!-- Main Sortable Sections List -->
        <ul class="crm-sortable-sections" id="<?php echo esc_attr($container_id); ?>" style="list-style:none; margin:0; padding:0;">
            <?php foreach ($sections as $index => $sec) :
                $is_enabled = !empty($sec['enabled']);
                $item_color = !empty($sec['color']) ? $sec['color'] : '#007C90';
                $is_custom  = !empty($sec['is_custom']);
                $subs_count = !empty($sec['subsections']) ? count($sec['subsections']) : 0;
            ?>
                <li class="crm-pdf-section-item <?php echo $is_enabled ? 'is-active' : 'is-disabled'; ?>"
                    data-key="<?php echo esc_attr($sec['key']); ?>"
                    data-custom="<?php echo $is_custom ? '1' : '0'; ?>"
                    data-title="<?php echo esc_attr($sec['title']); ?>"
                    data-badge="<?php echo esc_attr($sec['badge'] ?? ''); ?>"
                    data-color="<?php echo esc_attr($item_color); ?>"
                    data-content="<?php echo esc_attr($sec['content'] ?? ''); ?>"
                    data-header-mode="<?php echo esc_attr($sec['header_mode'] ?? 'master'); ?>"
                    data-header-logo="<?php echo !empty($sec['header_logo']) ? '1' : '0'; ?>"
                    data-header-address="<?php echo !empty($sec['header_address']) ? '1' : '0'; ?>"
                    data-header-custom="<?php echo esc_attr($sec['header_custom'] ?? ''); ?>"
                    data-footer-mode="<?php echo esc_attr($sec['footer_mode'] ?? 'master'); ?>"
                    data-footer-company="<?php echo !empty($sec['footer_company']) ? '1' : '0'; ?>"
                    data-footer-page-num="<?php echo !empty($sec['footer_page_num']) ? '1' : '0'; ?>"
                    data-footer-date="<?php echo !empty($sec['footer_date']) ? '1' : '0'; ?>"
                    data-footer-custom="<?php echo esc_attr($sec['footer_custom'] ?? ''); ?>"
                    style="margin-bottom:9px; background:#ffffff; border:1px solid <?php echo $is_enabled ? '#cbd5e1' : '#e2e8f0'; ?>; border-left:4px solid <?php echo esc_attr($item_color); ?>; border-radius:6px; box-shadow:0 1px 2px rgba(0,0,0,0.03); transition:all 0.15s ease;">

                    <!-- Section Item Header Bar -->
                    <div class="crm-section-header-row" style="display:flex; align-items:center; gap:10px; padding:9px 12px; cursor:pointer;">
                        <!-- Drag Handle -->
                        <span class="crm-section-drag-handle" title="<?php esc_attr_e('Ziehen zum Verschieben', 'custom-crm'); ?>" style="color:#94a3b8; cursor:grab; font-size:16px; display:flex; align-items:center; user-select:none;">
                            &#x2630;
                        </span>

                        <!-- Visibility Toggle Checkbox -->
                        <label class="crm-section-toggle-label" style="display:flex; align-items:center; margin:0; cursor:pointer;" title="<?php esc_attr_e('Abschnitt im PDF ein-/ausblenden', 'custom-crm'); ?>" onclick="event.stopPropagation();">
                            <input type="checkbox"
                                   class="crm-section-checkbox"
                                   value="1"
                                   <?php checked($is_enabled); ?>
                                   style="margin:0; width:15px; height:15px; cursor:pointer;">
                        </label>

                        <!-- Accordion Chevron -->
                        <span class="crm-section-chevron" style="color:#64748b; font-size:14px; width:16px; height:16px; display:inline-flex; align-items:center; justify-content:center; transition:transform 0.15s ease; user-select:none;">
                            &#x25B8;
                        </span>

                        <!-- Section Title & Badge -->
                        <div class="crm-section-clickable-info" style="flex:1; min-width:0;">
                            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                <strong class="crm-section-title" style="font-size:12.5px; color:#0f172a;">
                                    <?php echo esc_html($sec['title']); ?>
                                </strong>
                                <?php if (!empty($sec['badge'])) : ?>
                                    <span class="crm-section-badge" style="font-size:9.5px; font-weight:700; text-transform:uppercase; padding:1px 5px; border-radius:8px; background:#f1f5f9; color:<?php echo esc_attr($item_color); ?>; border:1px solid #e2e8f0;">
                                        <?php echo esc_html($sec['badge']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($is_custom) : ?>
                                    <span style="font-size:9px; font-weight:600; padding:1px 4px; border-radius:4px; background:#e0e7ff; color:#4338ca;">
                                        <?php esc_html_e('Benutzerdefiniert', 'custom-crm'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if (!$is_sidebar && !empty($sec['desc'])) : ?>
                                <div style="font-size:11px; color:#64748b; margin-top:1px; line-height:1.25; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                    <?php echo esc_html($sec['desc']); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Header & Footer Toggle Pill -->
                        <?php $hf_summary = crm_get_pdf_hf_summary_label($sec); ?>
                        <button type="button" class="button-link crm-toggle-hf-btn" title="<?php esc_attr_e('Kopf- & Fußzeile für diese Seite anpassen', 'custom-crm'); ?>" style="font-size:10px; font-weight:600; padding:2px 7px; border-radius:12px; background:#f5f3ff; color:#6d28d9; border:1px solid #ddd6fe; display:inline-flex; align-items:center; gap:3px; text-decoration:none; cursor:pointer; user-select:none; white-space:nowrap;" onclick="event.preventDefault(); event.stopPropagation(); jQuery(this).closest('.crm-pdf-section-item').find('> .crm-hf-drawer').slideToggle(180);">
                            <span class="dashicons dashicons-editor-kitchensink" style="font-size:12px; width:12px; height:12px; line-height:12px;"></span>
                            <span class="crm-hf-summary-text"><?php echo esc_html($hf_summary); ?></span>
                            <span class="crm-hf-chevron">&#x25BE;</span>
                        </button>

                        <!-- Subsections Counter Pill -->
                        <span class="crm-subs-counter-badge" style="font-size:10px; font-weight:600; padding:2px 7px; border-radius:12px; background:#f8fafc; color:#475569; border:1px solid #e2e8f0; white-space:nowrap; user-select:none;">
                            <?php echo sprintf(esc_html__('%d Unterabschnitte', 'custom-crm'), $subs_count); ?> &#x25BE;
                        </span>

                        <!-- Order & Delete Actions -->
                        <div class="crm-section-actions" style="display:flex; gap:3px; align-items:center;" onclick="event.stopPropagation();">
                            <?php if ($is_custom) : ?>
                                <button type="button" class="button-link crm-delete-section-btn" title="<?php esc_attr_e('Abschnitt löschen', 'custom-crm'); ?>" style="color:#dc2626; font-size:13px; text-decoration:none; padding:1px 4px;">
                                    ✕
                                </button>
                            <?php endif; ?>
                            <button type="button" class="button-link crm-move-up-btn" title="<?php esc_attr_e('Nach oben verschieben', 'custom-crm'); ?>" style="color:#64748b; font-size:13px; text-decoration:none; padding:1px 3px;">
                                &uarr;
                            </button>
                            <button type="button" class="button-link crm-move-down-btn" title="<?php esc_attr_e('Nach unten verschieben', 'custom-crm'); ?>" style="color:#64748b; font-size:13px; text-decoration:none; padding:1px 3px;">
                                &darr;
                            </button>
                        </div>
                    </div>

                    <!-- Header & Footer Drawer (Initially Collapsed) -->
                    <div class="crm-hf-drawer" style="display:none; padding:12px 14px; background:#fcfdff; border-top:1px solid #e2e8f0; border-bottom:1px solid #cbd5e1;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; padding-bottom:6px; border-bottom:1px solid #e2e8f0;">
                            <span style="font-size:11.5px; font-weight:700; color:#4338ca; text-transform:uppercase; letter-spacing:0.5px; display:flex; align-items:center; gap:5px;">
                                <span class="dashicons dashicons-admin-appearance" style="font-size:14px; width:14px; height:14px;"></span>
                                <?php esc_html_e('Kopf- & Fußzeile dieser Seite:', 'custom-crm'); ?>
                            </span>
                            <small style="color:#64748b; font-size:10.5px;">
                                <?php esc_html_e('Wählen Sie eine Master-Voreinstellung oder steuern Sie Logo, Adresse und Firmendaten gezielt.', 'custom-crm'); ?>
                            </small>
                        </div>

                        <div style="display:grid; grid-template-columns: <?php echo $is_sidebar ? '1fr' : '1fr 1fr'; ?>; gap:14px;">
                            <!-- KOPFZEILE (HEADER) -->
                            <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:10px 12px;">
                                <div style="font-size:12px; font-weight:700; color:#0f172a; margin-bottom:8px; display:flex; align-items:center; gap:5px;">
                                    <span class="dashicons dashicons-heading" style="color:#007C90; font-size:15px; width:15px; height:15px;"></span>
                                    <span><?php esc_html_e('Kopfzeile (Header)', 'custom-crm'); ?></span>
                                </div>

                                <div style="margin-bottom:8px;">
                                    <label style="display:block; font-size:10.5px; font-weight:600; color:#334155; margin-bottom:3px;">
                                        <?php esc_html_e('Header-Modus / Vorlage:', 'custom-crm'); ?>
                                    </label>
                                    <select class="crm-hf-header-mode regular-text" style="width:100%; height:28px; font-size:11.5px;">
                                        <option value="master" <?php selected(($sec['header_mode'] ?? 'master'), 'master'); ?>><?php esc_html_e('⚡ Wie Master-Einstellung', 'custom-crm'); ?></option>
                                        <option value="full" <?php selected(($sec['header_mode'] ?? 'master'), 'full'); ?>><?php esc_html_e('Logo & Firmenadresse (Standard)', 'custom-crm'); ?></option>
                                        <option value="logo_only" <?php selected(($sec['header_mode'] ?? 'master'), 'logo_only'); ?>><?php esc_html_e('Nur Logo (ohne Adresse)', 'custom-crm'); ?></option>
                                        <option value="address_only" <?php selected(($sec['header_mode'] ?? 'master'), 'address_only'); ?>><?php esc_html_e('Nur Firmenadresse (ohne Logo)', 'custom-crm'); ?></option>
                                        <option value="none" <?php selected(($sec['header_mode'] ?? 'master'), 'none'); ?>><?php esc_html_e('🚫 Keine Kopfzeile (ausblenden)', 'custom-crm'); ?></option>
                                        <option value="custom" <?php selected(($sec['header_mode'] ?? 'master'), 'custom'); ?>><?php esc_html_e('✏️ Eigener HTML-Header', 'custom-crm'); ?></option>
                                    </select>
                                </div>

                                <div class="crm-hf-header-checkboxes" style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:8px; font-size:11px; color:#334155;">
                                    <label style="display:inline-flex; align-items:center; gap:4px; cursor:pointer;">
                                        <input type="checkbox" class="crm-hf-header-logo" value="1" <?php checked(!empty($sec['header_logo'])); ?> style="margin:0;">
                                        <span><?php esc_html_e('Logo anzeigen', 'custom-crm'); ?></span>
                                    </label>
                                    <label style="display:inline-flex; align-items:center; gap:4px; cursor:pointer;">
                                        <input type="checkbox" class="crm-hf-header-address" value="1" <?php checked(!empty($sec['header_address'])); ?> style="margin:0;">
                                        <span><?php esc_html_e('Adresse & Kontakt anzeigen', 'custom-crm'); ?></span>
                                    </label>
                                </div>

                                <div class="crm-hf-header-custom-box" style="<?php echo (($sec['header_mode'] ?? '') === 'custom') ? 'display:block;' : 'display:none;'; ?> margin-top:6px;">
                                    <label style="display:block; font-size:10px; font-weight:600; color:#475569; margin-bottom:2px;">
                                        <?php esc_html_e('Eigener Header HTML / Platzhalter:', 'custom-crm'); ?>
                                    </label>
                                    <textarea class="crm-hf-header-custom" rows="2" style="width:100%; font-size:11px; font-family:monospace;" placeholder="<?php esc_attr_e('HTML oder Platzhalter wie {kurstitel}...', 'custom-crm'); ?>"><?php echo esc_textarea($sec['header_custom'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <!-- FUSSZEILE (FOOTER) -->
                            <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:10px 12px;">
                                <div style="font-size:12px; font-weight:700; color:#0f172a; margin-bottom:8px; display:flex; align-items:center; gap:5px;">
                                    <span class="dashicons dashicons-editor-insertmore" style="color:#007C90; font-size:15px; width:15px; height:15px;"></span>
                                    <span><?php esc_html_e('Fußzeile (Footer)', 'custom-crm'); ?></span>
                                </div>

                                <div style="margin-bottom:8px;">
                                    <label style="display:block; font-size:10.5px; font-weight:600; color:#334155; margin-bottom:3px;">
                                        <?php esc_html_e('Footer-Modus / Vorlage:', 'custom-crm'); ?>
                                    </label>
                                    <select class="crm-hf-footer-mode regular-text" style="width:100%; height:28px; font-size:11.5px;">
                                        <option value="master" <?php selected(($sec['footer_mode'] ?? 'master'), 'master'); ?>><?php esc_html_e('⚡ Wie Master-Einstellung', 'custom-crm'); ?></option>
                                        <option value="standard" <?php selected(($sec['footer_mode'] ?? 'master'), 'standard'); ?>><?php esc_html_e('Firmendaten + Seitenzahlen (Standard)', 'custom-crm'); ?></option>
                                        <option value="full" <?php selected(($sec['footer_mode'] ?? 'master'), 'full'); ?>><?php esc_html_e('Firmendaten + Seitenzahlen + Datum', 'custom-crm'); ?></option>
                                        <option value="page_numbers_only" <?php selected(($sec['footer_mode'] ?? 'master'), 'page_numbers_only'); ?>><?php esc_html_e('Nur Seitenzahlen', 'custom-crm'); ?></option>
                                        <option value="company_only" <?php selected(($sec['footer_mode'] ?? 'master'), 'company_only'); ?>><?php esc_html_e('Nur Firmendaten', 'custom-crm'); ?></option>
                                        <option value="none" <?php selected(($sec['footer_mode'] ?? 'master'), 'none'); ?>><?php esc_html_e('🚫 Keine Fußzeile (ausblenden)', 'custom-crm'); ?></option>
                                        <option value="custom" <?php selected(($sec['footer_mode'] ?? 'master'), 'custom'); ?>><?php esc_html_e('✏️ Eigener Text / Footer', 'custom-crm'); ?></option>
                                    </select>
                                </div>

                                <div class="crm-hf-footer-checkboxes" style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:8px; font-size:11px; color:#334155;">
                                    <label style="display:inline-flex; align-items:center; gap:4px; cursor:pointer;">
                                        <input type="checkbox" class="crm-hf-footer-company" value="1" <?php checked(!empty($sec['footer_company'])); ?> style="margin:0;">
                                        <span><?php esc_html_e('Firmendaten', 'custom-crm'); ?></span>
                                    </label>
                                    <label style="display:inline-flex; align-items:center; gap:4px; cursor:pointer;">
                                        <input type="checkbox" class="crm-hf-footer-page-num" value="1" <?php checked(!empty($sec['footer_page_num'])); ?> style="margin:0;">
                                        <span><?php esc_html_e('Seitenzahlen', 'custom-crm'); ?></span>
                                    </label>
                                    <label style="display:inline-flex; align-items:center; gap:4px; cursor:pointer;">
                                        <input type="checkbox" class="crm-hf-footer-date" value="1" <?php checked(!empty($sec['footer_date'])); ?> style="margin:0;">
                                        <span><?php esc_html_e('Datum', 'custom-crm'); ?></span>
                                    </label>
                                </div>

                                <div class="crm-hf-footer-custom-box" style="<?php echo (($sec['footer_mode'] ?? '') === 'custom') ? 'display:block;' : 'display:none;'; ?> margin-top:6px;">
                                    <label style="display:block; font-size:10px; font-weight:600; color:#475569; margin-bottom:2px;">
                                        <?php esc_html_e('Eigener Footer-Text ({PAGENO}, {NB}, {datum}):', 'custom-crm'); ?>
                                    </label>
                                    <input type="text" class="crm-hf-footer-custom regular-text" style="width:100%; height:26px; font-size:11px;" value="<?php echo esc_attr($sec['footer_custom'] ?? ''); ?>" placeholder="<?php esc_attr_e('z. B. Vertraulich | Seite {PAGENO} von {NB}', 'custom-crm'); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Subsections Drawer (Initially Collapsed) -->
                    <div class="crm-subsections-drawer" style="display:none; padding:10px 14px 12px 14px; background:#f8fafc; border-top:1px solid #e2e8f0; border-radius:0 0 6px 6px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; padding-bottom:4px; border-bottom:1px solid #e2e8f0;">
                            <span style="font-size:11px; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:0.5px;">
                                <?php esc_html_e('Unterabschnitte dieser Seite:', 'custom-crm'); ?>
                            </span>
                            <small style="color:#64748b; font-size:10.5px;">
                                <?php esc_html_e('Ziehen zum Sortieren | Häkchen zum Ein-/Ausblenden', 'custom-crm'); ?>
                            </small>
                        </div>

                        <!-- Nested Sortable Subsections List -->
                        <ul class="crm-sortable-subsections" style="list-style:none; margin:0 0 10px 0; padding:0;">
                            <?php if (!empty($sec['subsections'])) :
                                foreach ($sec['subsections'] as $sub) :
                                    $sub_enabled = !empty($sub['enabled']);
                                    $sub_custom  = !empty($sub['is_custom']);
                            ?>
                                <li class="crm-pdf-subsection-item <?php echo $sub_enabled ? 'sub-active' : 'sub-disabled'; ?>"
                                    data-sub-key="<?php echo esc_attr($sub['key']); ?>"
                                    data-custom="<?php echo $sub_custom ? '1' : '0'; ?>"
                                    data-title="<?php echo esc_attr($sub['title']); ?>"
                                    data-orig-title="<?php echo esc_attr($sub['orig_title'] ?? $sub['title']); ?>"
                                    data-content="<?php echo esc_attr($sub['content'] ?? ''); ?>"
                                    data-default-content="<?php echo esc_attr($sub['default_content'] ?? ''); ?>"
                                    style="display:block; margin-bottom:6px; background:#ffffff; border:1px solid <?php echo $sub_enabled ? '#cbd5e1' : '#e2e8f0'; ?>; border-radius:5px; transition:all 0.12s ease; overflow:hidden;">

                                    <!-- Sub Row Bar -->
                                    <div class="crm-sub-row" style="display:flex; align-items:center; gap:8px; padding:6px 10px; cursor:grab;">
                                        <!-- Sub Drag Handle -->
                                        <span class="crm-sub-drag-handle" title="<?php esc_attr_e('Ziehen zum Sortieren', 'custom-crm'); ?>" style="color:#94a3b8; font-size:14px; cursor:grab; user-select:none;">
                                            &#x22EE;&#x22EE;
                                        </span>

                                        <!-- Sub Checkbox -->
                                        <label style="display:flex; align-items:center; margin:0; cursor:pointer;" title="<?php esc_attr_e('Unterabschnitt ein-/ausblenden', 'custom-crm'); ?>">
                                            <input type="checkbox"
                                                   class="crm-sub-checkbox"
                                                   value="1"
                                                   <?php checked($sub_enabled); ?>
                                                   style="margin:0; width:14px; height:14px; cursor:pointer;">
                                        </label>

                                        <!-- Sub Info -->
                                        <div style="flex:1; min-width:0;">
                                            <span class="crm-sub-title" style="font-size:11.5px; font-weight:600; color:#1e293b;">
                                                <?php echo esc_html($sub['title']); ?>
                                            </span>
                                            <?php if (!empty($sub['desc']) && !$is_sidebar) : ?>
                                                <span style="font-size:10.5px; color:#64748b; margin-left:6px;">
                                                    &mdash; <?php echo esc_html($sub['desc']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($sub_custom) : ?>
                                                <span style="font-size:8.5px; font-weight:600; padding:1px 4px; border-radius:3px; background:#e0e7ff; color:#4338ca; margin-left:4px;">
                                                    <?php esc_html_e('Eigen', 'custom-crm'); ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="crm-sub-custom-badge" style="<?php echo !empty($sub['content']) ? 'display:inline-block;' : 'display:none;'; ?> font-size:8.5px; font-weight:600; padding:1px 4px; border-radius:3px; background:#fef3c7; color:#b45309; border:1px solid #fde68a; margin-left:4px;">
                                                <?php esc_html_e('Angepasst', 'custom-crm'); ?>
                                            </span>
                                        </div>

                                        <!-- Sub Actions -->
                                        <div class="crm-sub-actions" style="display:flex; gap:3px; align-items:center;">
                                            <button type="button" class="button-link crm-edit-sub-btn" title="<?php esc_attr_e('Unterabschnitt bearbeiten', 'custom-crm'); ?>" style="color:#007C90; font-size:10.5px; font-weight:600; padding:1px 6px; text-decoration:none; display:inline-flex; align-items:center; gap:2px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:3px; cursor:pointer;">
                                                ✎ <span class="crm-edit-sub-text"><?php esc_html_e('Bearbeiten', 'custom-crm'); ?></span>
                                            </button>
                                            <?php if ($sub_custom) : ?>
                                                <button type="button" class="button-link crm-delete-sub-btn" title="<?php esc_attr_e('Unterabschnitt löschen', 'custom-crm'); ?>" style="color:#dc2626; font-size:12px; padding:0 3px; text-decoration:none;">
                                                    ✕
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="button-link crm-sub-move-up" title="<?php esc_attr_e('Nach oben', 'custom-crm'); ?>" style="color:#64748b; font-size:11px; padding:0 2px; text-decoration:none;">
                                                &uarr;
                                            </button>
                                            <button type="button" class="button-link crm-sub-move-down" title="<?php esc_attr_e('Nach unten', 'custom-crm'); ?>" style="color:#64748b; font-size:11px; padding:0 2px; text-decoration:none;">
                                                &darr;
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Sub Inline Edit Drawer -->
                                    <div class="crm-sub-edit-drawer" style="display:none; padding:10px 12px; background:#f8fafc; border-top:1px solid #e2e8f0; cursor:default;">
                                        <div style="margin-bottom:6px;">
                                            <label style="display:block; font-size:10.5px; font-weight:600; color:#334155; margin-bottom:2px;">
                                                <?php esc_html_e('Titel des Unterabschnitts:', 'custom-crm'); ?>
                                            </label>
                                            <input type="text" class="crm-sub-input-title regular-text" value="<?php echo esc_attr($sub['title']); ?>" style="width:100%; height:26px; font-size:11.5px;">
                                        </div>

                                        <div style="margin-bottom:6px;">
                                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                                                <label style="font-size:10.5px; font-weight:600; color:#334155;">
                                                    <?php esc_html_e('Inhalt / Text / HTML:', 'custom-crm'); ?>
                                                </label>
                                                <?php if (!$sub_custom) : ?>
                                                    <span style="font-size:9.5px; color:#64748b;">
                                                        <?php esc_html_e('Tipp: {standard} fügt den dynamischen Originalinhalt ein', 'custom-crm'); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php
                                            $sub_content_display = !empty($sub['content']) ? $sub['content'] : (!empty($sub['default_content']) ? $sub['default_content'] : '');
                                            ?>
                                            <textarea class="crm-sub-input-content" rows="4" style="width:100%; font-size:11.5px; font-family:monospace; line-height:1.4;" placeholder="<?php esc_attr_e('Standardinhalt bearbeiten oder eigenen Text eingeben...', 'custom-crm'); ?>"><?php echo esc_textarea($sub_content_display); ?></textarea>
                                        </div>

                                        <!-- Placeholder Chips -->
                                        <div class="crm-sub-chips-bar" style="margin-bottom:8px; display:flex; gap:4px; flex-wrap:wrap; align-items:center;">
                                            <span style="font-size:9.5px; color:#475569; font-weight:600;"><?php esc_html_e('Platzhalter:', 'custom-crm'); ?></span>
                                            <button type="button" class="button-link crm-chip-btn" data-tag="{vorname}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{vorname}</button>
                                            <button type="button" class="button-link crm-chip-btn" data-tag="{nachname}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{nachname}</button>
                                            <button type="button" class="button-link crm-chip-btn" data-tag="{kurstitel}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{kurstitel}</button>
                                            <button type="button" class="button-link crm-chip-btn" data-tag="{kurstyp}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{kurstyp}</button>
                                            <button type="button" class="button-link crm-chip-btn" data-tag="{startdatum}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{startdatum}</button>
                                            <button type="button" class="button-link crm-chip-btn" data-tag="{preis}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{preis}</button>
                                            <button type="button" class="button-link crm-chip-btn" data-tag="{datum}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{datum}</button>
                                            <button type="button" class="button-link crm-chip-btn" data-tag="{expire}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{expire}</button>
                                            <button type="button" class="button-link crm-chip-btn" data-tag="{anrede_brief}" title="<?php esc_attr_e('Postalisches Herrn / Frau', 'custom-crm'); ?>" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{anrede_brief}</button>
                                            <button type="button" class="button-link crm-chip-btn" data-tag="{kunden_firma}" title="<?php esc_attr_e('Firmenname des Kunden', 'custom-crm'); ?>" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{kunden_firma}</button>
                                            <button type="button" class="button-link crm-chip-btn" data-tag="{empfaenger_adresse}" title="<?php esc_attr_e('Kompletter normgerechter Adressblock', 'custom-crm'); ?>" style="font-size:9.5px; padding:1px 5px; background:#ecfdf5; color:#065f46; border-radius:3px; text-decoration:none; border:1px solid #a7f3d0; font-weight:600;">{empfaenger_adresse}</button>
                                            <?php if (!$sub_custom) : ?>
                                                <button type="button" class="button-link crm-chip-btn" data-tag="{standard}" title="<?php esc_attr_e('Dynamische Standard-Tabelle / Standard-Inhalt', 'custom-crm'); ?>" style="font-size:9.5px; padding:1px 5px; background:#fef3c7; color:#92400e; border-radius:3px; text-decoration:none; border:1px solid #fde68a; font-weight:600;">{standard}</button>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Drawer Action Buttons -->
                                        <div style="display:flex; justify-content:space-between; align-items:center; gap:6px; padding-top:4px;">
                                            <div style="display:flex; gap:6px;">
                                                <button type="button" class="button button-primary crm-sub-apply-edit-btn" style="background:#007C90; border-color:#007C90; font-size:11px; height:24px; line-height:22px; padding:0 8px;">
                                                    ✓ <?php esc_html_e('Übernehmen', 'custom-crm'); ?>
                                                </button>
                                                <button type="button" class="button crm-sub-close-edit-btn" style="font-size:11px; height:24px; line-height:22px; padding:0 6px;">
                                                    <?php esc_html_e('Schließen', 'custom-crm'); ?>
                                                </button>
                                            </div>
                                            <?php if (!$sub_custom) : ?>
                                                <button type="button" class="button-link crm-sub-reset-default-btn" title="<?php esc_attr_e('Änderungen verwerfen und auf Systemstandard zurücksetzen', 'custom-crm'); ?>" style="font-size:10.5px; color:#dc2626; text-decoration:none;">
                                                    ↺ <?php esc_html_e('Auf Standard zurücksetzen', 'custom-crm'); ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach;
                            endif; ?>
                        </ul>

                        <!-- Add Subsection Form & Button -->
                        <div class="crm-add-sub-wrapper">
                            <button type="button" class="button button-secondary crm-toggle-add-sub-btn" style="font-size:11px; height:24px; line-height:22px; padding:0 8px; display:inline-flex; align-items:center; gap:3px;">
                                <span class="dashicons dashicons-plus" style="font-size:12px; width:12px; height:12px;"></span>
                                <?php esc_html_e('Unterabschnitt hinzufügen', 'custom-crm'); ?>
                            </button>

                            <div class="crm-add-sub-drawer" style="display:none; margin-top:8px; padding:10px; background:#ffffff; border:1px solid #cbd5e1; border-radius:4px;">
                                <div style="margin-bottom:6px;">
                                    <label style="display:block; font-size:10.5px; font-weight:600; color:#334155; margin-bottom:2px;"><?php esc_html_e('Titel des Unterabschnitts', 'custom-crm'); ?> *</label>
                                    <input type="text" class="crm-new-sub-title regular-text" placeholder="z. B. Zusätzlicher Hinweis oder Textabsatz" style="width:100%; height:26px; font-size:11.5px;">
                                </div>
                                <div style="margin-bottom:6px;">
                                    <label style="display:block; font-size:10.5px; font-weight:600; color:#334155; margin-bottom:2px;"><?php esc_html_e('Inhalt / Freitext (HTML erlaubt)', 'custom-crm'); ?></label>
                                    <textarea class="crm-new-sub-content" rows="2" placeholder="Text oder HTML für diesen Unterabschnitt..." style="width:100%; font-size:11.5px; font-family:monospace;"></textarea>
                                </div>
                                <div style="display:flex; gap:6px;">
                                    <button type="button" class="button button-primary crm-create-sub-btn" style="background:#007C90; border-color:#007C90; font-size:11px; height:24px; line-height:22px; padding:0 8px;">
                                        <?php esc_html_e('Hinzufügen', 'custom-crm'); ?>
                                    </button>
                                    <button type="button" class="button crm-cancel-add-sub-btn" style="font-size:11px; height:24px; line-height:22px; padding:0 6px;">
                                        <?php esc_html_e('Abbrechen', 'custom-crm'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- Footer Control Buttons -->
        <div class="crm-sections-ctrl-bar" style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-top:12px; flex-wrap:wrap;">
            <div style="display:flex; gap:6px; align-items:center;">
                <button type="button" class="button button-primary crm-save-sections-btn" style="background:#007C90; border-color:#007C90; font-size:12px; height:28px; line-height:26px; padding:0 12px;">
                    <span class="dashicons dashicons-saved" style="vertical-align:text-top; font-size:14px;"></span>
                    <?php esc_html_e('Reihenfolge & Struktur anwenden', 'custom-crm'); ?>
                </button>
                <button type="button" class="button button-secondary crm-reset-sections-btn" style="color:#dc2626; font-size:12px; height:28px; line-height:26px; padding:0 8px;" onclick="return confirm('<?php echo esc_js(__('Reihenfolge und Abschnitte auf System-Standard zurücksetzen?', 'custom-crm')); ?>');">
                    <span class="dashicons dashicons-undo" style="vertical-align:text-top; font-size:14px;"></span>
                    <?php esc_html_e('Reset', 'custom-crm'); ?>
                </button>
            </div>
            <span class="crm-sections-status" style="font-size:11px; font-weight:600; display:none;"></span>
        </div>
    </div>
    <?php
}
