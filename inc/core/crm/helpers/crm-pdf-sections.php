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
        'header_margin_top'    => 8.0,        // mm von oben (TCPDF Header Y-Position, Standard: 8 mm)
        'header_margin_bottom' => 32.0,       // mm bis zum Seiteninhalt (TCPDF SetTopMargin, Standard: 32 mm)
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
        'header_mode'          => sanitize_key($data['header_mode'] ?? 'full'),
        'header_logo'          => !empty($data['header_logo']),
        'header_address'       => !empty($data['header_address']),
        'header_custom'        => wp_kses_post(wp_unslash($data['header_custom'] ?? '')),
        'header_margin_top'    => isset($data['header_margin_top']) && $data['header_margin_top'] !== '' ? max(0.0, floatval($data['header_margin_top'])) : 8.0,
        'header_margin_bottom' => isset($data['header_margin_bottom']) && $data['header_margin_bottom'] !== '' ? max(5.0, floatval($data['header_margin_bottom'])) : 32.0,
        'footer_mode'          => sanitize_key($data['footer_mode'] ?? 'standard'),
        'footer_company'       => !empty($data['footer_company']),
        'footer_page_num'      => !empty($data['footer_page_num']),
        'footer_date'          => !empty($data['footer_date']),
        'footer_custom'        => sanitize_textarea_field(wp_unslash($data['footer_custom'] ?? '')),
    ];
    return update_option('crm_pdf_master_header_footer', $sanitized);
}

/**
 * Liefert die globalen Master-Einstellungen für PDF-Element-Abstände (in pt).
 *
 * @return array
 */
function crm_get_pdf_elements_spacing(): array
{
    $defaults = [
        'spacing_top'          => 0,  // Standard-Elemente in pt (0 = Standard-Layout / kein Zusatzabstand)
        'spacing_bottom'       => 0,  // Standard-Elemente in pt (0 = Standard-Layout / kein Zusatzabstand)
        'title_spacing_top'    => 13, // Master Dokumententitel Abstand oben in pt (Standard: 13)
        'title_spacing_bottom' => 11, // Master Dokumententitel Abstand unten in pt (Standard: 11)
    ];

    $saved = get_option('crm_pdf_elements_spacing', []);
    if (!is_array($saved)) {
        return $defaults;
    }
    return wp_parse_args($saved, $defaults);
}

/**
 * Speichert die globalen Master-Einstellungen für PDF-Element-Abstände.
 *
 * @param array $data
 * @return bool
 */
function crm_save_pdf_elements_spacing(array $data): bool
{
    $sanitized = [
        'spacing_top'          => isset($data['spacing_top']) && is_numeric($data['spacing_top']) ? max(0, floatval($data['spacing_top'])) : 0,
        'spacing_bottom'       => isset($data['spacing_bottom']) && is_numeric($data['spacing_bottom']) ? max(0, floatval($data['spacing_bottom'])) : 0,
        'title_spacing_top'    => isset($data['title_spacing_top']) && is_numeric($data['title_spacing_top']) ? max(0, floatval($data['title_spacing_top'])) : 13,
        'title_spacing_bottom' => isset($data['title_spacing_bottom']) && is_numeric($data['title_spacing_bottom']) ? max(0, floatval($data['title_spacing_bottom'])) : 11,
    ];
    return update_option('crm_pdf_elements_spacing', $sanitized);
}

/**
 * Erzeugt ein TCPDF-konformes Spacer-HTML für einen vertikalen Abstand in pt.
 *
 * @param float $pt
 * @return string
 */
function crm_get_pdf_spacing_html(float $pt): string
{
    if ($pt <= 0.0) {
        return '';
    }
    $pt_clean = round($pt, 1);
    return '<div class="crm-pdf-spacer" style="font-size:' . $pt_clean . 'pt; line-height:' . $pt_clean . 'pt; height:' . $pt_clean . 'pt;">&nbsp;</div>';
}

/**
 * Ermittelt die effektiven vertikalen Abstände (oben & unten) für ein Element oder einen Abschnitt.
 * Berücksichtigt elementspezifische Werte und fällt optional auf globale Master-Werte zurück.
 *
 * @param array $item Abschnitt oder Unterabschnitt
 * @param array|null $global Globale Spacing-Konfiguration (null = automatisch laden)
 * @return array ['top' => float, 'bottom' => float]
 */
function crm_get_pdf_effective_spacing(array $item, ?array $global = null): array
{
    if ($global === null) {
        $global = crm_get_pdf_elements_spacing();
    }

    $key = $item['key'] ?? '';
    $is_title = ($key === 'titel' || (is_string($key) && str_ends_with($key, '_titel')) || $key === 'haupttitel');

    $has_top    = isset($item['spacing_top']) && $item['spacing_top'] !== '' && is_numeric($item['spacing_top']) && floatval($item['spacing_top']) > 0;
    $has_bottom = isset($item['spacing_bottom']) && $item['spacing_bottom'] !== '' && is_numeric($item['spacing_bottom']) && floatval($item['spacing_bottom']) > 0;

    $top    = $has_top ? floatval($item['spacing_top']) : ($is_title ? floatval($global['title_spacing_top'] ?? 13.0) : floatval($global['spacing_top'] ?? 0.0));
    $bottom = $has_bottom ? floatval($item['spacing_bottom']) : ($is_title ? floatval($global['title_spacing_bottom'] ?? 11.0) : floatval($global['spacing_bottom'] ?? 0.0));

    return [
        'top'    => max(0.0, (float)$top),
        'bottom' => max(0.0, (float)$bottom),
    ];
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
                    'veranstaltung_titel' => [
                        'title'           => __('Titelzeile: Veranstaltungsinformationen', 'custom-crm'),
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
                        'title'           => __('Modulübersicht & Themeninhalte', 'custom-crm'),
                        'desc'            => __('Aufzählung aller Module, Gliederung und Themenbereiche.', 'custom-crm'),
                        'default'         => true,
                        'default_content' => "{standard}",
                    ],
                    'zeiteinteilung' => [
                        'title'           => __('Zeiteinteilung & Lehreinheiten-Aufteilung', 'custom-crm'),
                        'desc'            => __('Detaillierte Aufteilung der Lehreinheiten (Unterricht, Selbststudium, Praxis) inkl. Summenzeile.', 'custom-crm'),
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
                    'abschluss_titel' => [
                        'title'           => __('Titelzeile: Ihr persönlicher Abschluss', 'custom-crm'),
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
                    'kosten_titel' => [
                        'title'           => __('Titelzeile: Ihre Investition & Kosten', 'custom-crm'),
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
                    'anmeldung_titel' => [
                        'title'           => __('Titelzeile: Anmeldung', 'custom-crm'),
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
                    'inhalte_titel' => [
                        'title'           => __('Titelzeile: Details zu den Inhalten', 'custom-crm'),
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
                    'zusatzleistungen_titel' => [
                        'title'           => __('Titelzeile: Exklusive Zusatzleistungen', 'custom-crm'),
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

    // Angebot 2 (Inkl. Zertifizierung): Erbt die modulare 7-teilige Struktur von Angebot 1
    $definitions['angebot_2'] = $definitions['angebot'];
    $definitions['angebot_2']['abschluss']['title'] = __('Ihr persönlicher Abschluss & Zertifizierung', 'custom-crm');
    $definitions['angebot_2']['kosten']['title']    = __('Ihre Investition & Kosten (inkl. Zertifizierung)', 'custom-crm');
    $definitions['angebot_2']['kosten']['desc']     = __('Kursgebühr inkl. optionale Zertifizierungen, Gesamtkosten, Angebotsgültigkeit und Bankverbindung.', 'custom-crm');

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

    // Wenn $course ein CRM_Model ist, zuerst parse_string_with_data anwenden
    if (method_exists($course, 'parse_string_with_data')) {
        $content = $course->parse_string_with_data($content);
    }

    $c_entry_id = absint($course->entry_id ?? 0);
    $c_course_id = absint($course->course_id ?? 0);
    $diplom_nr_raw = $c_entry_id > 0 ? $c_entry_id : ($c_course_id > 0 ? $c_course_id : 5624);

    $clean_title = html_entity_decode(html_entity_decode($course->title ?? '', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
    $clean_short = html_entity_decode(html_entity_decode($course->titel_short ?? '', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');

    // Preis- und Mengenberechnung
    $netto_val = CRM_Pdf_Presenter::parse_price_float($course->preis_netto ?? 0);
    if ($netto_val == 0.0 && !empty($course->kosten)) {
        $netto_val = CRM_Pdf_Presenter::parse_price_float($course->kosten);
    }
    $brutto_val = CRM_Pdf_Presenter::parse_price_float($course->preis_brutto ?? 0);
    if ($brutto_val == 0.0 && $netto_val > 0.0) {
        $brutto_val = round($netto_val * 1.20, 2);
    }
    $le_val     = !empty($course->anzahl_le) ? (int)$course->anzahl_le : 1;
    $single_val = $netto_val > 0 && $le_val > 0 ? ($netto_val / $le_val) : 0.0;

    $expire_val = !empty($course->expire) ? $course->expire : date('d.m.Y', strtotime('+14 days'));

    $kurstyp_val    = !empty($course->kurstyp) ? trim($course->kurstyp) : 'Lehrgang';
    $kurstyp_upper  = mb_strtoupper($kurstyp_val, 'UTF-8');
    $kurstyp_phrase = function_exists('crm_get_diplom_kurstyp_phrase')
        ? crm_get_diplom_kurstyp_phrase($kurstyp_val)
        : ('HAT DEN ' . $kurstyp_upper);

    // Dynamische Bankverbindung
    $bank_str = !empty($course->company_bank) ? (string)$course->company_bank : 'Erste Bank | IBAN: AT29 3293 7001 0012 5260 | BIC: RLNWATWWWRN';
    $iban_val = 'AT29 3293 7001 0012 5260';
    $bic_val  = 'RLNWATWWWRN';
    if (preg_match('/IBAN:\s*([A-Z0-9 ]+?)(?:\s*\||$)/i', $bank_str, $ib_m)) {
        $iban_val = trim($ib_m[1]);
    }
    if (preg_match('/BIC:\s*([A-Z0-9 ]+?)(?:\s*\||$)/i', $bank_str, $bc_m)) {
        $bic_val = trim($bc_m[1]);
    }

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
        '{kurstitel_short}'    => $clean_short,
        '{startdatum}'         => $course->start_datum ?? '',
        '{enddatum}'           => $course->end_datum ?? '',
        '{uhrzeit}'            => !empty($course->uhrzeit) && is_string($course->uhrzeit) ? $course->uhrzeit : (!empty($course->kurszeiten) && is_string($course->kurszeiten) ? $course->kurszeiten : '09:00 – 17:00 Uhr'),
        '{kurszeiten}'         => !empty($course->kurszeiten) && is_string($course->kurszeiten) ? $course->kurszeiten : (!empty($course->uhrzeit) && is_string($course->uhrzeit) ? $course->uhrzeit : '09:00 – 17:00 Uhr'),
        '{zeiten}'             => !empty($course->kurszeiten) && is_string($course->kurszeiten) ? $course->kurszeiten : (!empty($course->uhrzeit) && is_string($course->uhrzeit) ? $course->uhrzeit : '09:00 – 17:00 Uhr'),
        '{preis}'              => number_format($netto_val, 2, ',', '.'),
        '{preis_netto}'        => number_format($netto_val, 2, ',', '.'),
        '{preis_brutto}'       => number_format($brutto_val, 2, ',', '.'),
        '{gesamtpreis}'        => number_format($brutto_val, 2, ',', '.'),
        '{le_single}'          => number_format($single_val, 2, ',', '.'),
        '{location_wien}'      => $course->location_wien ?? 'Rochusgasse 6, 1030 Wien',
        '{salutation}'         => (strcasecmp($course->anrede ?? '', 'Herr') === 0 || strcasecmp($course->anrede ?? '', 'Herrn') === 0) ? 'Sehr geehrter Herr' : ((strcasecmp($course->anrede ?? '', 'Frau') === 0) ? 'Sehr geehrte Frau' : 'Sehr geehrte Damen und Herren'),
        '{ort}'                => !empty($course->street) ? trim(($course->street ?? '') . ' ' . ($course->house_number ?? '') . ', ' . ($course->zip_code ?? '') . ' ' . ($course->city ?? '')) : ($course->location_wien ?? 'Rochusgasse 6, 1030 Wien bzw. online'),
        '{schulungsort}'       => $course->location_wien ?? 'Rochusgasse 6, 1030 Wien bzw. online',
        '{le}'                 => $course->anzahl_le ?? '',
        '{institut}'           => $course->company_name ?? 'X SIEBEN Wirtschaftstraining GmbH',
        '{datum}'              => date('d.m.Y'),
        '{current_date}'       => date('d.m.Y'),
        '{expire}'             => $expire_val,
        '[Datum]'              => $expire_val,
        '{angebotsnummer}'     => $course->angebotsnummer ?? '',
        '{zielgruppe}'         => $course->zielgruppe ?? '',
        '{firmenname}'         => $course->company_name ?? 'X SIEBEN Wirtschaftstraining GmbH',
        '{company_name}'       => $course->company_name ?? 'X SIEBEN Wirtschaftstraining GmbH',
        '{company_address}'    => $course->company_address ?? '',
        '{company_phone}'      => $course->company_phone ?? '0800 700 170',
        '{company_email}'      => $course->company_email ?? 'office@x-sieben.at',
        '{company_uid}'        => $course->company_uid ?? 'ATU76624137',
        '{company_fn}'         => $course->company_fn ?? 'FN 550277 g',
        '{company_court}'      => $course->company_court ?? 'Landesgericht Wiener Neustadt',
        '{email}'              => $course->email ?? '',
        '{telefon}'            => $course->phone ?? ($course->company_phone ?? ''),
        '{diplom_nr}'          => sprintf('%05d', $diplom_nr_raw),
        '{course_id}'          => $c_course_id,
        '{entry_id}'           => $c_entry_id,
        '{bankverbindung}'     => $bank_str,
        '{iban}'               => $iban_val,
        '{bic}'                => $bic_val,
        '{bank_iban}'          => $iban_val,
        '{bank_bic}'           => $bic_val,
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

    // Detect obsolete hardcoded titles (e.g. legacy test snippets with "Digital Marketing Manager")
    if (stripos($content, 'Digital Marketing Manager') !== false && (stripos($sub_key, 'titel') !== false || stripos($sub_key, 'title') !== false)) {
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
            'veranstaltung_titel' => [
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
            'abschluss_titel' => [
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
            'kosten_titel' => [
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
            'anmeldung_titel' => [
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
            'inhalte_titel' => [
                'Details zu den Inhalten (Anhang 1)',
            ],
        ],
        'zusatzleistungen' => [
            'titel' => [
                'Exklusive Zusatzleistungen (Anhang 2)',
            ],
            'zusatzleistungen_titel' => [
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
            if ($norm === $norm_snippet) {
                return true;
            }
        }
    } else {
        foreach ($legacy_map as $sec_k => $subs) {
            if (isset($subs[$sub_key])) {
                foreach ($subs[$sub_key] as $snippet) {
                    $norm_snippet = preg_replace('/\s+/', ' ', trim($snippet));
                    if ($norm === $norm_snippet) {
                        return true;
                    }
                }
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
    $saved_order    = null;
    $global_opt_key = 'crm_pdf_section_order_' . $doc_type;
    $global_order   = get_option($global_opt_key, null);

    // Fallback für Angebot 2: Wenn für angebot_2 noch keine eigene Konfiguration existiert, von angebot erben
    if ($doc_type === 'angebot_2' && $global_order === null) {
        $global_order = get_option('crm_pdf_section_order_angebot', null);
    }

    if (!empty($entry_id)) {
        $entry_opt_key = 'crm_pdf_sec_' . $doc_type . '_' . intval($entry_id);
        $entry_val     = get_option($entry_opt_key, null);
        if (is_array($entry_val) && !empty($entry_val)) {
            $saved_order = $entry_val;
        }
    }

    // 2. Globale Konfiguration (falls kein Eintrags-Order vorliegt)
    if ($saved_order === null) {
        $saved_order = $global_order;
    }

    // Index global definierter benutzerdefinierter Unterabschnitte aufbauen (für Vererbung)
    $global_custom_subs = [];
    if (is_array($global_order)) {
        foreach ($global_order as $g_sec) {
            if (is_array($g_sec) && !empty($g_sec['subsections']) && is_array($g_sec['subsections'])) {
                foreach ($g_sec['subsections'] as $g_sub) {
                    $g_k = $g_sub['key'] ?? '';
                    $g_c = trim($g_sub['content'] ?? '');
                    if (!empty($g_k) && !empty($g_c) && $g_c !== '{standard}') {
                        $global_custom_subs[$g_k] = $g_sub['content'];
                    }
                }
            }
        }
    }

    // Auto-Migration für Angebot: zertifizierungen & ort_durchfuehrung von veranstaltung (Seite 2) nach abschluss (Seite 3) verschieben
    if (in_array($doc_type, ['angebot', 'angebot_2'], true) && is_array($saved_order)) {
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

    // Globale Registry aller definierten Standard-Unterabschnitte aufbauen (für seitenübergreifendes Drag & Drop)
    $all_sub_defs = [];
    foreach ($definitions as $sec_k => $sec_d) {
        if (!empty($sec_d['subsections'])) {
            foreach ($sec_d['subsections'] as $sub_k => $sub_d) {
                $all_sub_defs[$sub_k] = $sub_d;
            }
        }
    }

    // Erfassen aller Unterabschnitts-Schlüssel, die irgendwo in der gespeicherten Struktur existieren
    $saved_subs_global = [];
    if (is_array($saved_order)) {
        foreach ($saved_order as $item) {
            $item_sec_k = is_array($item) ? ($item['key'] ?? '') : (string)$item;
            if (is_array($item) && !empty($item['subsections']) && is_array($item['subsections'])) {
                foreach ($item['subsections'] as $sub) {
                    $sk = is_array($sub) ? ($sub['key'] ?? '') : (string)$sub;
                    if ($sk === 'titel' && $item_sec_k !== 'deckblatt' && !empty($item_sec_k)) {
                        $sk = "{$item_sec_k}_titel";
                    }
                    if (!empty($sk)) {
                        $saved_subs_global[$sk] = true;
                    }
                }
            }
        }
    }

    $globally_seen_subs = [];

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
                        // Legacy-Mapping für sektionsspezifische Titel:
                        if ($s_key === 'titel' && $key !== 'deckblatt') {
                            $s_key = "{$key}_titel";
                        }
                        $s_enabled = !empty($sub['enabled']);
                        $s_custom  = !empty($sub['is_custom']);

                        if (empty($s_key) || isset($seen_subs[$s_key]) || isset($globally_seen_subs[$s_key])) {
                            continue;
                        }

                        // Unterabschnitt-Definition finden: lokal oder seitenübergreifend
                        $sub_def = $def['subsections'][$s_key] ?? ($all_sub_defs[$s_key] ?? null);

                        if (!$s_custom && $sub_def !== null) {
                            $raw_content = $sub['content'] ?? '';

                            // AUTO-CLEANUP / HEALING FÜR BESTEHENDE DATEN:
                            if (!empty($raw_content)) {
                                $def_content = $sub_def['default_content'] ?? '';
                                $norm_raw = preg_replace('/\s+/', ' ', trim($raw_content));
                                $norm_def = preg_replace('/\s+/', ' ', trim($def_content));
                                if ($norm_raw === $norm_def || $norm_raw === '{standard}' || crm_is_legacy_default_pdf_content($key, $s_key, $norm_raw)) {
                                    $raw_content = '';
                                }
                            }

                            // Kaskadierende Vererbung: Wenn für diesen Eintrag kein individueller Inhalt vorliegt,
                            // aber global ein benutzerdefiniertes Standard-HTML gespeichert wurde, dieses erben!
                            if (empty($raw_content) && !empty($global_custom_subs[$s_key])) {
                                $raw_content = $global_custom_subs[$s_key];
                            }

                            $sub_sp_top = isset($sub['spacing_top']) && is_numeric($sub['spacing_top']) ? max(0.0, floatval($sub['spacing_top'])) : 0.0;
                            $sub_sp_bottom = isset($sub['spacing_bottom']) && is_numeric($sub['spacing_bottom']) ? max(0.0, floatval($sub['spacing_bottom'])) : 0.0;

                            $effective_default_content = !empty($global_custom_subs[$s_key]) ? $global_custom_subs[$s_key] : ($sub_def['default_content'] ?? '');

                            $subsections[] = [
                                'key'             => $s_key,
                                'title'           => $sub['title'] ?? $sub_def['title'],
                                'orig_title'      => $sub_def['title'],
                                'desc'            => $sub_def['desc'] ?? '',
                                'enabled'         => $s_enabled,
                                'is_custom'       => false,
                                'content'         => $raw_content,
                                'default_content' => $effective_default_content,
                                'spacing_top'     => $sub_sp_top,
                                'spacing_bottom'  => $sub_sp_bottom,
                            ];
                            $seen_subs[$s_key] = true;
                            $globally_seen_subs[$s_key] = true;
                        } elseif ($s_custom) {
                            $sub_sp_top = isset($sub['spacing_top']) && is_numeric($sub['spacing_top']) ? max(0.0, floatval($sub['spacing_top'])) : 0.0;
                            $sub_sp_bottom = isset($sub['spacing_bottom']) && is_numeric($sub['spacing_bottom']) ? max(0.0, floatval($sub['spacing_bottom'])) : 0.0;

                            $subsections[] = [
                                'key'             => $s_key,
                                'title'           => $sub['title'] ?? __('Benutzerdefinierter Unterabschnitt', 'custom-crm'),
                                'orig_title'      => $sub['title'] ?? __('Benutzerdefinierter Unterabschnitt', 'custom-crm'),
                                'desc'            => __('Eigener Text / HTML-Inhalt', 'custom-crm'),
                                'enabled'         => $s_enabled,
                                'is_custom'       => true,
                                'content'         => $sub['content'] ?? '',
                                'default_content' => '',
                                'spacing_top'     => $sub_sp_top,
                                'spacing_bottom'  => $sub_sp_bottom,
                            ];
                            $seen_subs[$s_key] = true;
                            $globally_seen_subs[$s_key] = true;
                        }
                    }
                }

                // Fehlende Standard-Unterabschnitte anfügen (NUR wenn sie nicht auf einer anderen Seite platziert wurden!)
                if (!empty($def['subsections'])) {
                    foreach ($def['subsections'] as $s_key => $sub_def) {
                        if (!isset($seen_subs[$s_key]) && !isset($globally_seen_subs[$s_key]) && !isset($saved_subs_global[$s_key])) {
                            $effective_default = !empty($global_custom_subs[$s_key]) ? $global_custom_subs[$s_key] : ($sub_def['default_content'] ?? '');
                            $new_sub = [
                                'key'             => $s_key,
                                'title'           => $sub_def['title'],
                                'orig_title'      => $sub_def['title'],
                                'desc'            => $sub_def['desc'] ?? '',
                                'enabled'         => $sub_def['default'] ?? true,
                                'is_custom'       => false,
                                'content'         => !empty($global_custom_subs[$s_key]) ? $global_custom_subs[$s_key] : '',
                                'default_content' => $effective_default,
                                'spacing_top'     => 0.0,
                                'spacing_bottom'  => 0.0,
                            ];
                            // Fehlende Kopf-/Titelzeilen gehören an die oberste Position (Index 0) der Seite
                            $is_first_in_def = (array_key_first($def['subsections']) === $s_key);
                            if ($is_first_in_def && str_ends_with($s_key, '_titel')) {
                                array_unshift($subsections, $new_sub);
                            } else {
                                $subsections[] = $new_sub;
                            }
                            $seen_subs[$s_key] = true;
                            $globally_seen_subs[$s_key] = true;
                        }
                    }
                }

                $h_mode     = isset($item['header_mode']) ? sanitize_key($item['header_mode']) : ($def['header_mode'] ?? 'master');
                $h_logo     = isset($item['header_logo']) ? !empty($item['header_logo']) : ($def['header_logo'] ?? true);
                $h_addr     = isset($item['header_address']) ? !empty($item['header_address']) : ($def['header_address'] ?? true);
                $h_custom   = isset($item['header_custom']) ? wp_kses_post(wp_unslash($item['header_custom'])) : ($def['header_custom'] ?? '');
                $h_sp_top   = (isset($item['header_margin_top']) && $item['header_margin_top'] !== '' && is_numeric($item['header_margin_top'])) ? max(0.0, floatval($item['header_margin_top'])) : null;
                $h_sp_bottom= (isset($item['header_margin_bottom']) && $item['header_margin_bottom'] !== '' && is_numeric($item['header_margin_bottom'])) ? max(5.0, floatval($item['header_margin_bottom'])) : null;

                $f_mode     = isset($item['footer_mode']) ? sanitize_key($item['footer_mode']) : ($def['footer_mode'] ?? 'master');
                $f_company  = isset($item['footer_company']) ? !empty($item['footer_company']) : ($def['footer_company'] ?? true);
                $f_page_num = isset($item['footer_page_num']) ? !empty($item['footer_page_num']) : ($def['footer_page_num'] ?? true);
                $f_date     = isset($item['footer_date']) ? !empty($item['footer_date']) : ($def['footer_date'] ?? false);
                $f_custom   = isset($item['footer_custom']) ? sanitize_textarea_field(wp_unslash($item['footer_custom'])) : ($def['footer_custom'] ?? '');

                $sec_sp_top    = isset($item['spacing_top']) && is_numeric($item['spacing_top']) ? max(0.0, floatval($item['spacing_top'])) : floatval($def['spacing_top'] ?? 0.0);
                $sec_sp_bottom = isset($item['spacing_bottom']) && is_numeric($item['spacing_bottom']) ? max(0.0, floatval($item['spacing_bottom'])) : floatval($def['spacing_bottom'] ?? 0.0);

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
                    'header_margin_top'    => $h_sp_top,
                    'header_margin_bottom' => $h_sp_bottom,
                    'footer_mode'    => $f_mode,
                    'footer_company' => (bool)$f_company,
                    'footer_page_num'=> (bool)$f_page_num,
                    'footer_date'    => (bool)$f_date,
                    'footer_custom'  => $f_custom,
                    'spacing_top'    => $sec_sp_top,
                    'spacing_bottom' => $sec_sp_bottom,
                    'subsections'    => $subsections,
                ];
                $seen_keys[$key] = true;

            } elseif ($is_custom) {
                $subsections = [];
                if (is_array($item) && isset($item['subsections']) && is_array($item['subsections'])) {
                    foreach ($item['subsections'] as $sub) {
                        $sk = $sub['key'] ?? '';
                        if (!empty($sk) && isset($globally_seen_subs[$sk])) {
                            continue;
                        }
                        $sub_sp_top = isset($sub['spacing_top']) && is_numeric($sub['spacing_top']) ? max(0.0, floatval($sub['spacing_top'])) : 0.0;
                        $sub_sp_bottom = isset($sub['spacing_bottom']) && is_numeric($sub['spacing_bottom']) ? max(0.0, floatval($sub['spacing_bottom'])) : 0.0;

                        $is_sub_custom = !empty($sub['is_custom']);
                        $sub_def       = (!$is_sub_custom && !empty($sk) && isset($all_sub_defs[$sk])) ? $all_sub_defs[$sk] : null;

                        $subsections[] = [
                            'key'             => $sk ?: uniqid('sub_'),
                            'title'           => $sub['title'] ?? ($sub_def['title'] ?? __('Unterabschnitt', 'custom-crm')),
                            'orig_title'      => $sub_def['title'] ?? ($sub['title'] ?? __('Unterabschnitt', 'custom-crm')),
                            'desc'            => $sub_def['desc'] ?? __('Eigener Text / HTML', 'custom-crm'),
                            'enabled'         => !empty($sub['enabled']),
                            'is_custom'       => $sub_def === null,
                            'content'         => $sub['content'] ?? '',
                            'default_content' => $sub_def['default_content'] ?? '',
                            'spacing_top'     => $sub_sp_top,
                            'spacing_bottom'  => $sub_sp_bottom,
                        ];
                        if (!empty($sk)) {
                            $globally_seen_subs[$sk] = true;
                        }
                    }
                }

                $h_mode     = isset($item['header_mode']) ? sanitize_key($item['header_mode']) : 'master';
                $h_logo     = isset($item['header_logo']) ? !empty($item['header_logo']) : true;
                $h_addr     = isset($item['header_address']) ? !empty($item['header_address']) : true;
                $h_custom   = isset($item['header_custom']) ? wp_kses_post(wp_unslash($item['header_custom'])) : '';
                $h_sp_top   = (isset($item['header_margin_top']) && $item['header_margin_top'] !== '' && is_numeric($item['header_margin_top'])) ? max(0.0, floatval($item['header_margin_top'])) : null;
                $h_sp_bottom= (isset($item['header_margin_bottom']) && $item['header_margin_bottom'] !== '' && is_numeric($item['header_margin_bottom'])) ? max(5.0, floatval($item['header_margin_bottom'])) : null;

                $f_mode     = isset($item['footer_mode']) ? sanitize_key($item['footer_mode']) : 'master';
                $f_company  = isset($item['footer_company']) ? !empty($item['footer_company']) : true;
                $f_page_num = isset($item['footer_page_num']) ? !empty($item['footer_page_num']) : true;
                $f_date     = isset($item['footer_date']) ? !empty($item['footer_date']) : false;
                $f_custom   = isset($item['footer_custom']) ? sanitize_textarea_field(wp_unslash($item['footer_custom'])) : '';

                $sec_sp_top    = isset($item['spacing_top']) && is_numeric($item['spacing_top']) ? max(0.0, floatval($item['spacing_top'])) : 0.0;
                $sec_sp_bottom = isset($item['spacing_bottom']) && is_numeric($item['spacing_bottom']) ? max(0.0, floatval($item['spacing_bottom'])) : 0.0;

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
                    'header_margin_top'    => $h_sp_top,
                    'header_margin_bottom' => $h_sp_bottom,
                    'footer_mode'    => $f_mode,
                    'footer_company' => (bool)$f_company,
                    'footer_page_num'=> (bool)$f_page_num,
                    'footer_date'    => (bool)$f_date,
                    'footer_custom'  => $f_custom,
                    'spacing_top'    => $sec_sp_top,
                    'spacing_bottom' => $sec_sp_bottom,
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
                        'spacing_top'     => 0.0,
                        'spacing_bottom'  => 0.0,
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
                'header_margin_top'    => null,
                'header_margin_bottom' => null,
                'footer_mode'    => $def['footer_mode'] ?? 'master',
                'footer_company' => $def['footer_company'] ?? true,
                'footer_page_num'=> $def['footer_page_num'] ?? true,
                'footer_date'    => $def['footer_date'] ?? false,
                'footer_custom'  => '',
                'spacing_top'    => floatval($def['spacing_top'] ?? 0.0),
                'spacing_bottom' => floatval($def['spacing_bottom'] ?? 0.0),
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

    // Globale Registry aller definierten Standard-Unterabschnitte
    $all_sub_defs = [];
    foreach ($definitions as $sec_k => $sec_d) {
        if (!empty($sec_d['subsections'])) {
            foreach ($sec_d['subsections'] as $sub_k => $sub_d) {
                $all_sub_defs[$sub_k] = $sub_d;
            }
        }
    }

    $globally_seen_subs = [];

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
                // Legacy-Mapping für sektionsspezifische Titel:
                if ($sub_key === 'titel' && $key !== 'deckblatt') {
                    $sub_key = "{$key}_titel";
                }
                $sub_enabled = !empty($sub['enabled']);
                $sub_custom  = !empty($sub['is_custom']);
                $sub_title   = sanitize_text_field($sub['title'] ?? '');
                $sub_content = wp_kses_post(wp_unslash($sub['content'] ?? ''));

                if (!empty($sub_key) && !isset($seen_subs[$sub_key]) && !isset($globally_seen_subs[$sub_key])) {
                    // Standard-Unterabschnitt Definition ermitteln (lokal oder seitenübergreifend)
                    $sub_def = $definitions[$key]['subsections'][$sub_key] ?? ($all_sub_defs[$sub_key] ?? null);

                    if (!$sub_custom && $sub_def !== null) {
                        $def_sub_content = $sub_def['default_content'] ?? '';
                        $norm_sub = preg_replace('/\s+/', ' ', trim($sub_content));
                        $norm_def = preg_replace('/\s+/', ' ', trim($def_sub_content));
                        if ($norm_sub === $norm_def || $norm_sub === '{standard}') {
                            $sub_content = '';
                        }
                    }

                    $sub_sp_top    = isset($sub['spacing_top']) && is_numeric($sub['spacing_top']) ? max(0.0, floatval($sub['spacing_top'])) : 0.0;
                    $sub_sp_bottom = isset($sub['spacing_bottom']) && is_numeric($sub['spacing_bottom']) ? max(0.0, floatval($sub['spacing_bottom'])) : 0.0;

                    $subsections[] = [
                        'key'            => $sub_key,
                        'enabled'        => (bool)$sub_enabled,
                        'is_custom'      => (bool)$sub_custom,
                        'title'          => $sub_title,
                        'content'        => $sub_content,
                        'spacing_top'    => $sub_sp_top,
                        'spacing_bottom' => $sub_sp_bottom,
                    ];
                    $seen_subs[$sub_key] = true;
                    $globally_seen_subs[$sub_key] = true;
                }
            }
        }

        $h_mode     = sanitize_key($item['header_mode'] ?? 'master');
        $h_logo     = !empty($item['header_logo']);
        $h_addr     = !empty($item['header_address']);
        $h_custom   = wp_kses_post(wp_unslash($item['header_custom'] ?? ''));
        $h_sp_top   = (isset($item['header_margin_top']) && $item['header_margin_top'] !== '' && is_numeric($item['header_margin_top'])) ? max(0.0, floatval($item['header_margin_top'])) : null;
        $h_sp_bottom= (isset($item['header_margin_bottom']) && $item['header_margin_bottom'] !== '' && is_numeric($item['header_margin_bottom'])) ? max(5.0, floatval($item['header_margin_bottom'])) : null;

        $f_mode     = sanitize_key($item['footer_mode'] ?? 'master');
        $f_company  = !empty($item['footer_company']);
        $f_page_num = !empty($item['footer_page_num']);
        $f_date     = !empty($item['footer_date']);
        $f_custom   = sanitize_textarea_field(wp_unslash($item['footer_custom'] ?? ''));

        $sec_sp_top    = isset($item['spacing_top']) && is_numeric($item['spacing_top']) ? max(0.0, floatval($item['spacing_top'])) : 0.0;
        $sec_sp_bottom = isset($item['spacing_bottom']) && is_numeric($item['spacing_bottom']) ? max(0.0, floatval($item['spacing_bottom'])) : 0.0;

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
                'header_margin_top'    => $h_sp_top,
                'header_margin_bottom' => $h_sp_bottom,
                'footer_mode'    => $f_mode,
                'footer_company' => (bool)$f_company,
                'footer_page_num'=> (bool)$f_page_num,
                'footer_date'    => (bool)$f_date,
                'footer_custom'  => $f_custom,
                'spacing_top'    => $sec_sp_top,
                'spacing_bottom' => $sec_sp_bottom,
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
                    if (!isset($globally_seen_subs[$sk])) {
                        $default_subs[] = [
                            'key'            => $sk,
                            'enabled'        => false,
                            'is_custom'      => false,
                            'title'          => $sd['title'] ?? '',
                            'content'        => '',
                            'spacing_top'    => 0.0,
                            'spacing_bottom' => 0.0,
                        ];
                        $globally_seen_subs[$sk] = true;
                    }
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
                'header_margin_top'    => null,
                'header_margin_bottom' => null,
                'footer_mode'    => $d['footer_mode'] ?? 'master',
                'footer_company' => $d['footer_company'] ?? true,
                'footer_page_num'=> $d['footer_page_num'] ?? true,
                'footer_date'    => $d['footer_date'] ?? false,
                'footer_custom'  => '',
                'spacing_top'    => floatval($d['spacing_top'] ?? 0.0),
                'spacing_bottom' => floatval($d['spacing_bottom'] ?? 0.0),
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

    if (!$saved && get_option($opt_key) === $sanitized) {
        $saved = true;
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
 * Holt den standardmäßig dynamisch generierten HTML-Inhalt eines Unterabschnitts.
 * Ermöglicht dem Benutzer, das Original-HTML von {standard}-Komponenten in den Editor zu laden.
 *
 * @param string $doc_type 'angebot', 'kb', 'tb', 'diplom', 'invoice'
 * @param string $sec_key Hauptabschnitts-Schlüssel
 * @param string $sub_key Unterabschnitts-Schlüssel
 * @param int|null $entry_id Optionaler WPForms Eintrag
 * @param int|null $course_id Optionaler Kurs-Post-ID
 * @return string Gerendertes HTML / Vorlagentext
 */
function crm_get_subsection_default_html(string $doc_type, string $sec_key, string $sub_key, $entry_id = null, $course_id = null): string
{
    $doc_type = strtolower(trim($doc_type));
    if (empty($doc_type)) {
        $doc_type = 'angebot';
    }
    $sec_key  = sanitize_key($sec_key);
    $sub_key  = sanitize_key($sub_key);
    $entry_id = !empty($entry_id) ? absint($entry_id) : null;
    $course_id = !empty($course_id) ? absint($course_id) : null;

    if (!empty($entry_id) && empty($course_id)) {
        $course_id = absint(get_post_meta($entry_id, 'course_id', true));
    }

    if (empty($course_id) && function_exists('crm_get_preview_sample_data')) {
        $sample = crm_get_preview_sample_data();
        if (empty($entry_id) && !empty($sample['entry_id'])) {
            $entry_id = absint($sample['entry_id']);
        }
        if (!empty($sample['course_id'])) {
            $course_id = absint($sample['course_id']);
        }
    }

    require_once dirname(__DIR__) . '/crm-model.php';
    $course = new CRM_Model($course_id ?: 0, $entry_id);

    if ($doc_type === 'angebot_2' && function_exists('crm_resolve_course_certification')) {
        $course->override_certifications = crm_resolve_course_certification($entry_id, $course_id);
    }

    $gens = [];

    switch ($doc_type) {
        case 'angebot':
        case 'angebot_2':
            require_once dirname(__DIR__) . '/pdf/elements/offer-elements.php';
            $angebotsnummer  = 'A_' . ($entry_id ?: '0') . '-' . ($course->post_id ?? '0');
            $salutation_name = trim($course->salutation . ' ' . trim($course->titel . ' ' . $course->vorname . ' ' . $course->nachname));
            $salutation_name = preg_replace('/\s+/', ' ', $salutation_name);
            $angebot_default_intro = 'Danke für Ihr Interesse und willkommen bei der beliebten X SIEBEN Veranstaltung ' . $course->title . ' mit lernförderndem Kleingruppen-Unterricht.<br><br>Diese Veranstaltung fokussiert auf ' . $course->zielgruppe;
            $angebot_intro = $course->get_crm_field_with_default('Angebot - Einleitung', $angebot_default_intro);
            $angebot_gruss = $course->get_crm_field_with_default('Angebot - Grußformel', "Ich freue mich über Ihre Rückmeldung / Buchung.<br>\nMit freundlichen Grüßen,");
            $gens = CRM_Pdf_Offer_Elements::get_subsections_generators($course, [
                'angebotsnummer'  => $angebotsnummer,
                'salutation_name' => $salutation_name,
                'angebot_intro'   => $angebot_intro,
                'angebot_gruss'   => $angebot_gruss,
            ]);
            break;

        case 'kb':
            require_once dirname(__DIR__) . '/pdf/elements/kb-elements.php';
            $stempel_file = function_exists('crm_resolve_asset_path') ? crm_resolve_asset_path('stempel.png') : '';
            $default_kb_institut = !empty($course->company_name) ? $course->company_name : 'X SIEBEN Wirtschaftstraining GmbH';
            $default_kb_ort      = !empty($course->location_wien) ? ($course->location_wien . ' bzw. online') : 'Rochusgasse 6, 1030 Wien bzw. online';
            $kb_title        = $course->get_crm_field_with_default('KB - Titel', 'Bestätigung Kurszeiten');
            $kb_institut     = $course->get_crm_field_with_default('KB - Kursinstitut Name', $default_kb_institut);
            $kb_ort          = $course->get_crm_field_with_default('KB - Schulungsort', $default_kb_ort);
            $kb_hinweis      = $course->get_crm_field_with_default('KB - Hinweistext', 'Bei unregelmäßigen Kurszeiten ist ein Ablaufplan der einzelnen Kurswochen beizulegen.');
            $kurszeiten_datum = date('d.m.Y');
            $kb_sig_institut = $course->get_crm_field_with_default('KB - Signatur Institut', 'Wien, ' . $kurszeiten_datum . '<br>Unterschrift, Stampiglie Kursinstitut');
            $kb_sig_kunde    = $course->get_crm_field_with_default('KB - Signatur Kunde', 'Ort, Datum, Unterschrift, Kunde/Kundin');
            $display_title   = !empty($course->titel_short) ? $course->titel_short : $course->title;
            $startdatum      = $course->start_datum ?: date('d.m.Y');
            $enddatum        = $course->end_datum ?: date('d.m.Y');
            $vorname         = $course->vorname ?: '';
            $nachname        = $course->nachname ?: '';
            $svr             = $course->svr ?: '';
            $kursart_t       = (string)($course->kursart_t ?? '');
            $kursart_a       = (string)($course->kursart_a ?? '');
            $kursart_we      = (string)($course->kursart_we ?? '');
            $kurszeiten      = is_array($course->kurszeiten) ? $course->kurszeiten : [];
            $selbststudium   = is_array($course->selbststudium) ? $course->selbststudium : [];

            $gens = [
                'titel'      => ['haupttitel' => CRM_Pdf_Kb_Elements::render_titel($kb_title)],
                'institut'   => CRM_Pdf_Kb_Elements::get_institut_subs($kb_institut, $kb_ort, $display_title, $startdatum, $enddatum),
                'teilnehmer' => CRM_Pdf_Kb_Elements::get_teilnehmer_subs($vorname, $nachname, $svr),
                'kurstyp'    => ['kurstyp_box' => CRM_Pdf_Kb_Elements::render_kurstyp($kursart_t, $kursart_a, $kursart_we)],
                'kurszeiten' => ['kurszeiten_box' => CRM_Pdf_Kb_Elements::render_kurszeiten($kurszeiten, $selbststudium)],
                'hinweis'    => ['hinweis_box' => CRM_Pdf_Kb_Elements::render_hinweis($kb_hinweis)],
                'signatur'   => ['signatur_box' => CRM_Pdf_Kb_Elements::render_signatur($stempel_file, $kb_sig_institut, $kb_sig_kunde)],
            ];
            break;

        case 'tb':
            require_once dirname(__DIR__) . '/pdf/elements/tb-elements.php';
            $tb_title       = $course->get_crm_field_with_default('TB - Titel', 'Teilnahmebestätigung');
            $tb_einleitung  = $course->get_crm_field_with_default('TB - Einleitungstext', 'Wir bestätigen, dass');
            $tb_teilnahme   = $course->get_crm_field_with_default('TB - Teilnahmetext', 'an der Ausbildung: <strong>"' . $course->title . '"</strong> (' . $course->anzahl_le . ' LE) teilgenommen hat.');
            $tb_datum       = $course->get_crm_field_with_default('TB - Ausstellungsdatum', date('d.m.Y'));
            $tb_unterschrift= $course->get_crm_field_with_default('TB - Unterschriftszeile', "Mag. Dr. Johannes Gasberger\nGeschäftsführung");
            $tn_name        = trim($course->anrede . ' ' . $course->vorname . ' ' . $course->nachname);
            $tn_svr         = $course->svr ?: '';
            $tn_adresse     = $course->street ?: '';
            $tn_plz         = $course->postcode ?: '';
            $tn_ort         = $course->city ?: '';
            $start_datum    = $course->start_datum ?: date('d.m.Y');
            $end_datum      = $course->end_datum ?: date('d.m.Y');
            $tb_betrieb_name= $course->company_name ?: 'X SIEBEN Wirtschaftstraining GmbH';
            $tb_betrieb_str = $course->company_address ?: 'Rochusgasse 6';
            $tb_betrieb_plz = '1030';
            $tb_betrieb_ort = 'Wien';
            $tb_ort_str     = !empty($course->location_wien) ? $course->location_wien : 'Rochusgasse 6';
            $tb_ort_plz     = '1030';
            $tb_ort_ort     = 'Wien';

            $gens = [
                'titel'              => CRM_Pdf_Tb_Elements::get_titel_subs($tb_title, $tb_einleitung),
                'teilnehmer'         => ['teilnehmer_box' => CRM_Pdf_Tb_Elements::render_teilnehmer($tn_name, $tn_svr, $tn_adresse, $tn_plz, $tn_ort)],
                'zeitraum'           => ['zeitraum_box' => CRM_Pdf_Tb_Elements::render_zeitraum($start_datum, $end_datum)],
                'ausbildungsstaette' => ['ausbildungsstaette_box' => CRM_Pdf_Tb_Elements::render_ausbildungsstaette($tb_betrieb_name, $tb_betrieb_str, $tb_betrieb_plz, $tb_betrieb_ort, $tb_ort_str, $tb_ort_plz, $tb_ort_ort)],
                'teilnahme'          => ['teilnahme_box' => CRM_Pdf_Tb_Elements::render_teilnahme($tb_teilnahme)],
                'signatur'           => ['signatur_box' => CRM_Pdf_Tb_Elements::render_signatur($tb_datum, $tb_unterschrift, $course->signatur)],
            ];
            break;

        case 'diplom':
            require_once dirname(__DIR__) . '/pdf/elements/diplom-elements.php';
            $logo_path        = function_exists('crm_resolve_asset_path') ? crm_resolve_asset_path('logo.png') : '';
            $stempel_path     = function_exists('crm_resolve_asset_path') ? crm_resolve_asset_path('stempel.png') : '';
            $full_name_html   = CRM_Pdf_Diplom_Elements::render_name($course->anrede, $course->vorname, $course->nachname, $course->titel);
            $kurstyp_phrase   = 'HAT DEN LEHRGANG';
            $main_title_upper = mb_strtoupper($course->title ?: 'KURS', 'UTF-8');
            $subtitle_upper   = mb_strtoupper($course->titel_short ?: '', 'UTF-8');
            $anzahl_le        = $course->anzahl_le ?: '0';
            $start_formatted  = $course->start_datum ?: date('d.m.Y');
            $end_formatted    = $course->end_datum ?: date('d.m.Y');
            $diplom_nr        = 'D_' . ($entry_id ?: '0') . '-' . ($course->post_id ?? '0');
            $issue_date       = date('d.m.Y');
            $clean_links      = !empty($course->texte_fur_diplom_links) ? $course->texte_fur_diplom_links : 'Einführung in das Themengebiet<br>Erfolgsfaktoren und Zieldefinition<br>Planung und Ressourcensteuerung';
            $clean_rechts     = !empty($course->texte_fur_diplom_rechts) ? $course->texte_fur_diplom_rechts : 'Vertiefende Fachkompetenzen<br>Agile Methoden und Frameworks<br>Abschließende Reflexion';

            $gens = [
                'header'          => CRM_Pdf_Diplom_Elements::get_header_subs($logo_path, ''),
                'titel_absolvent' => CRM_Pdf_Diplom_Elements::get_titel_subs($full_name_html),
                'lehrgang'        => CRM_Pdf_Diplom_Elements::get_lehrgang_subs($kurstyp_phrase, $main_title_upper, $subtitle_upper, $anzahl_le, $start_formatted, $end_formatted),
                'abschluss'       => CRM_Pdf_Diplom_Elements::get_abschluss_subs('', 'ERFOLGREICH'),
                'beglaubigung'    => CRM_Pdf_Diplom_Elements::get_beglaubigung_subs($diplom_nr, $issue_date, $stempel_path),
                'inhalte'         => CRM_Pdf_Diplom_Elements::get_inhalte_subs($clean_links, $clean_rechts),
            ];
            break;

        case 'invoice':
            require_once dirname(__DIR__) . '/pdf/elements/invoice-elements.php';
            $clean_course_title = $course->title ?: 'Kurs';
            $due_date = date('d.m.Y', strtotime('+14 days'));
            $le_count = $course->anzahl_le ?: 0;
            $netto_kurs = CRM_Pdf_Presenter::parse_price_float($course->preis_netto ?? 0);
            if ($netto_kurs == 0.0 && !empty($course->kosten)) {
                $netto_kurs = CRM_Pdf_Presenter::parse_price_float($course->kosten);
            }
            $single_le = !empty($le_count) ? round($netto_kurs / floatval($le_count), 2) : 0;
            $cert_rows = [];
            $total_netto = $netto_kurs;
            $total_ust = round($total_netto * 0.20, 2);
            $total_brutto = round($total_netto + $total_ust, 2);
            $hn_einleitung_val = 'Hiermit stellen wir Ihnen nachfolgende Leistungen gemäß unseren Vereinbarungen in Rechnung.';
            $title_header_subs = CRM_Pdf_Invoice_Elements::get_titel_header_subs(
                $course->company_name,
                $course->company_court,
                $course->company_fn,
                $course->company_uid,
                $course->location_wien,
                $course->company_address,
                $course->company_phone,
                $course->company_email
            );
            $default_footer_html = CRM_Pdf_Invoice_Elements::render_fusszeile(
                $course->company_name,
                $course->company_address,
                $course->company_phone,
                $course->company_email,
                $course->company_fn,
                $course->company_court,
                $course->company_uid,
                $course->bank_iban,
                $course->bank_bic
            );
            $gens = [
                'titel_header' => $title_header_subs,
                'kopfzeile'    => $title_header_subs,
                'empfaenger'   => CRM_Pdf_Invoice_Elements::get_empfaenger_subs($course->format_postal_address('A', true), $course->svr, $due_date, $clean_course_title),
                'einleitung'   => CRM_Pdf_Invoice_Elements::get_einleitung_subs($hn_einleitung_val),
                'positionen'   => CRM_Pdf_Invoice_Elements::get_positionen_subs($clean_course_title, $course->start_datum, $course->end_datum, $le_count, $single_le, $netto_kurs, $cert_rows, $total_netto, $total_ust, $total_brutto),
                'zahlung'      => CRM_Pdf_Invoice_Elements::get_zahlung_subs($due_date, $course->company_name),
                'signatur'     => ['aussteller_info' => $default_footer_html],
                'fusszeile'    => ['aussteller_info' => $default_footer_html],
            ];
            break;
    }

    $html = '';
    if (isset($gens[$sec_key][$sub_key])) {
        $html = $gens[$sec_key][$sub_key];
    } else {
        foreach ($gens as $s_k => $subs) {
            if (is_array($subs) && isset($subs[$sub_key])) {
                $html = $subs[$sub_key];
                break;
            }
        }
    }

    // Fallback falls kein HTML-Generator greift, aber Standard-Definition Text enthält
    if (empty($html)) {
        $defs = crm_get_pdf_sections_definitions($doc_type);
        $sub_def = $defs[$sec_key]['subsections'][$sub_key] ?? null;
        if (!$sub_def) {
            foreach ($defs as $s_k => $s_d) {
                if (!empty($s_d['subsections'][$sub_key])) {
                    $sub_def = $s_d['subsections'][$sub_key];
                    break;
                }
            }
        }
        if ($sub_def && !empty($sub_def['default_content']) && $sub_def['default_content'] !== '{standard}') {
            $html = crm_replace_pdf_placeholders($sub_def['default_content'], $course);
        }
    }

    return trim((string)$html);
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
                    data-header-margin-top="<?php echo isset($sec['header_margin_top']) && $sec['header_margin_top'] !== null && $sec['header_margin_top'] !== '' ? esc_attr($sec['header_margin_top']) : ''; ?>"
                    data-header-margin-bottom="<?php echo isset($sec['header_margin_bottom']) && $sec['header_margin_bottom'] !== null && $sec['header_margin_bottom'] !== '' ? esc_attr($sec['header_margin_bottom']) : ''; ?>"
                    data-footer-mode="<?php echo esc_attr($sec['footer_mode'] ?? 'master'); ?>"
                    data-footer-company="<?php echo !empty($sec['footer_company']) ? '1' : '0'; ?>"
                    data-footer-page-num="<?php echo !empty($sec['footer_page_num']) ? '1' : '0'; ?>"
                    data-footer-date="<?php echo !empty($sec['footer_date']) ? '1' : '0'; ?>"
                    data-footer-custom="<?php echo esc_attr($sec['footer_custom'] ?? ''); ?>"
                    data-spacing-top="<?php echo esc_attr($sec['spacing_top'] ?? 0); ?>"
                    data-spacing-bottom="<?php echo esc_attr($sec['spacing_bottom'] ?? 0); ?>"
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

                        <!-- Spacing Toggle Pill -->
                        <?php
                        $sec_sp_top = isset($sec['spacing_top']) ? floatval($sec['spacing_top']) : 0;
                        $sec_sp_bottom = isset($sec['spacing_bottom']) ? floatval($sec['spacing_bottom']) : 0;
                        $spacing_summary = sprintf(__('Abstand: ↑%s pt / ↓%s pt', 'custom-crm'), $sec_sp_top, $sec_sp_bottom);
                        ?>
                        <button type="button" class="button-link crm-toggle-spacing-btn" title="<?php esc_attr_e('Abstand oben & unten für dieses Element anpassen', 'custom-crm'); ?>" style="font-size:10px; font-weight:600; padding:2px 7px; border-radius:12px; background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; display:inline-flex; align-items:center; gap:3px; text-decoration:none; cursor:pointer; user-select:none; white-space:nowrap;" onclick="event.preventDefault(); event.stopPropagation(); jQuery(this).closest('.crm-pdf-section-item').find('> .crm-spacing-drawer').slideToggle(180);">
                            <span class="dashicons dashicons-editor-expand" style="font-size:12px; width:12px; height:12px; line-height:12px;"></span>
                            <span class="crm-spacing-summary-text"><?php echo esc_html($spacing_summary); ?></span>
                            <span class="crm-spacing-chevron">&#x25BE;</span>
                        </button>

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

                                <div class="crm-hf-header-spacing-row" style="display:flex; gap:10px; margin-top:8px; padding-top:8px; border-top:1px dashed #cbd5e1;">
                                    <div style="flex:1;">
                                        <label style="display:block; font-size:10px; font-weight:600; color:#475569; margin-bottom:2px;" title="<?php esc_attr_e('Y-Abstand von oberer Blattkante bis Beginn Header in mm', 'custom-crm'); ?>">
                                            <?php esc_html_e('Header-Abstand oben (mm):', 'custom-crm'); ?>
                                        </label>
                                        <input type="number" step="0.5" min="0" max="100" class="crm-hf-header-margin-top regular-text"
                                               style="width:100%; height:26px; font-size:11px;"
                                               value="<?php echo isset($sec['header_margin_top']) && $sec['header_margin_top'] !== null && $sec['header_margin_top'] !== '' ? esc_attr($sec['header_margin_top']) : ''; ?>"
                                               placeholder="<?php esc_attr_e('Master: 8', 'custom-crm'); ?>">
                                    </div>
                                    <div style="flex:1;">
                                        <label style="display:block; font-size:10px; font-weight:600; color:#475569; margin-bottom:2px;" title="<?php esc_attr_e('Abstand von oberer Blattkante bis Beginn Dokumenteninhalt in mm', 'custom-crm'); ?>">
                                            <?php esc_html_e('Abstand Inhalt (mm):', 'custom-crm'); ?>
                                        </label>
                                        <input type="number" step="0.5" min="5" max="150" class="crm-hf-header-margin-bottom regular-text"
                                               style="width:100%; height:26px; font-size:11px;"
                                               value="<?php echo isset($sec['header_margin_bottom']) && $sec['header_margin_bottom'] !== null && $sec['header_margin_bottom'] !== '' ? esc_attr($sec['header_margin_bottom']) : ''; ?>"
                                               placeholder="<?php esc_attr_e('Master: 32', 'custom-crm'); ?>">
                                    </div>
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

                    <!-- Spacing Drawer (Initially Collapsed) -->
                    <div class="crm-spacing-drawer" style="display:none; padding:10px 14px; background:#f0fdf4; border-top:1px solid #bbf7d0; border-bottom:1px solid #cbd5e1;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
                            <span style="font-size:11.5px; font-weight:700; color:#15803d; text-transform:uppercase; letter-spacing:0.5px; display:flex; align-items:center; gap:5px;">
                                <span class="dashicons dashicons-editor-expand" style="font-size:14px; width:14px; height:14px;"></span>
                                <?php esc_html_e('Element-Abstände (in pt):', 'custom-crm'); ?>
                            </span>
                            <small style="color:#64748b; font-size:10.5px;">
                                <?php esc_html_e('0 pt = Standard-Layout / kein Zusatzabstand. 10 pt entsprechen ca. 3,5 mm.', 'custom-crm'); ?>
                            </small>
                        </div>
                        <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                            <div style="display:flex; align-items:center; gap:6px;">
                                <label style="font-size:11px; font-weight:600; color:#334155;">
                                    <?php esc_html_e('Abstand oben (pt):', 'custom-crm'); ?>
                                </label>
                                <input type="number" step="1" min="0" max="300" class="crm-sec-spacing-top" value="<?php echo esc_attr($sec_sp_top); ?>" style="width:75px; height:28px; font-size:11.5px; text-align:center;">
                            </div>
                            <div style="display:flex; align-items:center; gap:6px;">
                                <label style="font-size:11px; font-weight:600; color:#334155;">
                                    <?php esc_html_e('Abstand unten (pt):', 'custom-crm'); ?>
                                </label>
                                <input type="number" step="1" min="0" max="300" class="crm-sec-spacing-bottom" value="<?php echo esc_attr($sec_sp_bottom); ?>" style="width:75px; height:28px; font-size:11.5px; text-align:center;">
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
                                <?php esc_html_e('Ziehen zum Sortieren & Seitenwechsel | Häkchen zum Ein-/Ausblenden', 'custom-crm'); ?>
                            </small>
                        </div>

                        <!-- Nested Sortable Subsections List -->
                        <ul class="crm-sortable-subsections" style="list-style:none; margin:0 0 10px 0; padding:4px; min-height:35px; border-radius:4px;">
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
                                    data-spacing-top="<?php echo esc_attr($sub['spacing_top'] ?? 0); ?>"
                                    data-spacing-bottom="<?php echo esc_attr($sub['spacing_bottom'] ?? 0); ?>"
                                    style="display:block; margin-bottom:6px; background:#ffffff; border:1px solid <?php echo $sub_enabled ? '#cbd5e1' : '#e2e8f0'; ?>; border-radius:5px; transition:all 0.12s ease; overflow:hidden;">

                                    <!-- Sub Row Bar -->
                                    <div class="crm-sub-row" style="display:flex; align-items:center; gap:8px; padding:6px 10px; cursor:grab;">
                                        <!-- Sub Drag Handle -->
                                        <span class="crm-sub-drag-handle" title="<?php esc_attr_e('Ziehen zum Sortieren & Seitenwechsel', 'custom-crm'); ?>" style="color:#94a3b8; font-size:14px; cursor:grab; user-select:none;">
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

                                        <!-- Spacing Inputs for Subsections -->
                                        <div style="display:flex; gap:12px; align-items:center; margin-bottom:8px; background:#ffffff; padding:6px 10px; border-radius:4px; border:1px solid #e2e8f0;">
                                            <span style="font-size:10.5px; font-weight:700; color:#15803d; display:inline-flex; align-items:center; gap:3px;">
                                                <span class="dashicons dashicons-editor-expand" style="font-size:12px; width:12px; height:12px;"></span>
                                                <?php esc_html_e('Abstand (in pt):', 'custom-crm'); ?>
                                            </span>
                                            <div style="display:flex; align-items:center; gap:4px;">
                                                <label style="font-size:10.5px; font-weight:600; color:#334155;">
                                                    <?php esc_html_e('Oben:', 'custom-crm'); ?>
                                                </label>
                                                <input type="number" step="1" min="0" max="300" class="crm-sub-spacing-top" value="<?php echo esc_attr($sub['spacing_top'] ?? 0); ?>" style="width:65px; height:24px; font-size:11px; text-align:center;">
                                            </div>
                                            <div style="display:flex; align-items:center; gap:4px;">
                                                <label style="font-size:10.5px; font-weight:600; color:#334155;">
                                                    <?php esc_html_e('Unten:', 'custom-crm'); ?>
                                                </label>
                                                <input type="number" step="1" min="0" max="300" class="crm-sub-spacing-bottom" value="<?php echo esc_attr($sub['spacing_bottom'] ?? 0); ?>" style="width:65px; height:24px; font-size:11px; text-align:center;">
                                            </div>
                                            <span style="font-size:9.5px; color:#64748b; margin-left:auto;">
                                                <?php esc_html_e('0 = kein Zusatzabstand', 'custom-crm'); ?>
                                            </span>
                                        </div>

                                        <div style="margin-bottom:6px;">
                                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                                <label style="font-size:10.5px; font-weight:600; color:#334155;">
                                                    <?php esc_html_e('Inhalt / Text / HTML:', 'custom-crm'); ?>
                                                </label>
                                                <?php if (!$sub_custom) : ?>
                                                    <span style="font-size:9.5px; color:#64748b;">
                                                        <?php esc_html_e('Tipp: {standard} fügt den dynamischen Originalinhalt ein', 'custom-crm'); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <?php if (!$sub_custom) : ?>
                                                <div class="crm-sub-standard-notice" style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:4px; padding:6px 10px; margin-bottom:6px; display:flex; align-items:center; justify-content:space-between; gap:8px;">
                                                    <div style="font-size:10px; color:#166534; line-height:1.35;">
                                                        <span class="dashicons dashicons-editor-code" style="font-size:13px; width:13px; height:13px; vertical-align:middle; color:#15803d;"></span>
                                                        <strong><?php esc_html_e('Standard-Komponente:', 'custom-crm'); ?></strong>
                                                        <span class="crm-standard-notice-text">
                                                            <?php echo !empty($sub['content']) ? esc_html__('Aktuell angepasst. Klicken Sie auf den Button, um den System-Standard neu zu laden.', 'custom-crm') : esc_html__('Dynamischer Systemstandard. Klicken Sie auf den Button, um das Original-HTML in diesen Editor zu laden und frei anzupassen.', 'custom-crm'); ?>
                                                        </span>
                                                    </div>
                                                    <button type="button"
                                                            class="button button-small crm-sub-load-standard-btn"
                                                            data-doc="<?php echo esc_attr($doc_type); ?>"
                                                            data-sec="<?php echo esc_attr($sec['key']); ?>"
                                                            data-sub="<?php echo esc_attr($sub['key']); ?>"
                                                            title="<?php esc_attr_e('Vollständiges Standard-HTML dieses Elements in das Textfeld laden', 'custom-crm'); ?>"
                                                            style="background:#007C90; color:#ffffff; border-color:#007C90; font-size:10.5px; height:24px; line-height:22px; padding:0 8px; white-space:nowrap; display:inline-flex; align-items:center; gap:3px; cursor:pointer;">
                                                        ⚡ <?php esc_html_e('Standard-HTML laden', 'custom-crm'); ?>
                                                    </button>
                                                </div>
                                            <?php endif; ?>

                                            <?php
                                            $sub_content_display = !empty($sub['content']) ? $sub['content'] : (!empty($sub['default_content']) ? $sub['default_content'] : '');
                                            $textarea_rows = (strlen($sub_content_display) > 200) ? 8 : 4;
                                            ?>
                                            <textarea class="crm-sub-input-content" rows="<?php echo $textarea_rows; ?>" style="width:100%; font-size:11.5px; font-family:monospace; line-height:1.4;" placeholder="<?php esc_attr_e('Standardinhalt bearbeiten oder eigenen Text eingeben...', 'custom-crm'); ?>"><?php echo esc_textarea($sub_content_display); ?></textarea>
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
