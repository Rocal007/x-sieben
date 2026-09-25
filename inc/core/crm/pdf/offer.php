<?php
/**
 * X-SIEBEN CRM - Angebot PDF Generator
 *
 * Generiert das modulare Kursangebot als PDF via TCPDF.
 * Alle visuellen HTML-Fragmente und CSS-Styles wurden nach MVC- und
 * Autarkie-Kriterien in eigenständige Elemente (elements/) ausgelagert.
 *
 * @package X_SIEBEN_CRM
 * @version 2.18.13
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/elements/offer-elements.php';

function xsieben_offer_pdf($entry_id, $course_id, $output_to_browser = true, $custom_sections = null, $offer_variant = null, $custom_certifications = null)
{
    // Load course data
    $course = new CRM_Model($course_id, $entry_id);

    // Variantensteuerung: 'basis' (nur Kurs) vs. 'mit_zertifikat' (Kurs + Zertifizierung)
    if ($offer_variant === 'basis') {
        $course->override_certifications = [];
    } elseif ($offer_variant === 'mit_zertifikat') {
        if ($custom_certifications !== null && is_array($custom_certifications)) {
            $course->override_certifications = $custom_certifications;
        } elseif (function_exists('crm_resolve_course_certification')) {
            $course->override_certifications = crm_resolve_course_certification($entry_id, $course_id);
        }
    }

    $nummer     = $entry_id . '-' . $course_id;
    $safe_title = (function_exists('mb_substr') ? mb_substr(preg_replace('/[^\p{L}0-9_\-]/u', '_', (string)($course->titel_short ?: ($course->title ?: 'Kurs'))), 0, 50) : substr(preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)($course->titel_short ?: 'Kurs')), 0, 50));
    $angebotsnummer = "A_" . $nummer;
    $pdfAuthor  = "XSieben Wirtschaftstraining";

    // --- HTML Styles (Modular ausgelagert in elements/offer-styles.php) ---
    $styles = CRM_Pdf_Offer_Elements::render_styles();

    // --- Modular HTML Sections for Dynamic Ordering ---
    require_once dirname(__DIR__) . '/helpers/crm-pdf-sections.php';

    // Deckblatt: Texte aufbereiten
    $angebot_default_intro = 'Danke für Ihr Interesse und willkommen bei der beliebten X SIEBEN Veranstaltung ' . $course->title . ' mit lernförderndem Kleingruppen-Unterricht.<br><br>Diese Veranstaltung fokussiert auf ' . $course->zielgruppe;
    $angebot_intro         = $course->get_crm_field_with_default('Angebot - Einleitung', $angebot_default_intro);
    $angebot_gruss         = $course->get_crm_field_with_default('Angebot - Grußformel', "Ich freue mich über Ihre Rückmeldung / Buchung.<br><br>\nMit freundlichen Grüßen,");

    // Clean up wpautop / HTML paragraph wrappers for clean, uniform spacing inside table cell
    $clean_pdf_text = function ($html) {
        $html = preg_replace('/<div[^>]*>\s*(?:&nbsp;|\x{00a0})?\s*<\/div>/iu', '<br><br>', $html);
        $html = preg_replace('/^\s*<p[^>]*>/iu', '', $html);
        $html = preg_replace('/<\/p>\s*<p[^>]*>/iu', '<br><br>', $html);
        $html = preg_replace('/<\/p>\s*$/iu', '', $html);
        $html = preg_replace('/(?:<br\s*\/?>\s*){3,}/iu', '<br><br>', $html);
        return trim($html);
    };

    $angebot_intro = $clean_pdf_text($angebot_intro);
    $angebot_gruss = $clean_pdf_text($angebot_gruss);

    $salutation_name = trim($course->salutation . ' ' . trim($course->titel . ' ' . $course->vorname . ' ' . $course->nachname));
    $salutation_name = preg_replace('/\s+/', ' ', $salutation_name);

    // Subsections Mapping über zentrale Element-Registry
    $subsections_generators = CRM_Pdf_Offer_Elements::get_subsections_generators($course, [
        'angebotsnummer'  => $angebotsnummer,
        'salutation_name' => $salutation_name,
        'angebot_intro'   => $angebot_intro,
        'angebot_gruss'   => $angebot_gruss,
    ]);

    $flattened_sub_generators = [];
    foreach ($subsections_generators as $sec_k => $subs) {
        if (is_array($subs)) {
            foreach ($subs as $sub_k => $sub_html) {
                $flattened_sub_generators[$sub_k] = $sub_html;
            }
        }
    }

    // --- PDF Document Generation ---
    if (!class_exists('TCPDF')) {
        $tcpdf_path = get_template_directory() . '/tcbpdf/tcpdf.php';
        if (file_exists($tcpdf_path)) {
            require_once $tcpdf_path;
        }
    }

    if (!class_exists('MYPDFA_Angebot')) {
        class MYPDFA_Angebot extends TCPDF
        {
            public $master_config = [];
            public $current_section_config = [];
            public $page_configs = [];
            public $company_info = [];
            public $course_obj = null;
            public $header_content = '';
            public $logo_html = '';
            public $footer_text = '';

            public function __construct($orientation='P', $unit='mm', $format='A4', $unicode=true, $encoding='UTF-8', $diskcache=false, $pdfa=false)
            {
                parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
            }

            public function setSectionConfig($config)
            {
                $this->current_section_config = is_array($config) ? $config : [];
            }

            public function resolveHeaderMode($cfg)
            {
                $sec_mode = $cfg['header_mode'] ?? 'master';
                $logo_mode = get_option('crm_pdf_logo_mode', $this->master_config['logo_mode'] ?? 'page1_only');

                // Wenn page1_only aktiv ist und wir uns auf Seite > 1 befinden:
                if ($this->page > 1 && $logo_mode === 'page1_only') {
                    return 'none';
                }

                // 1. Wenn der Abschnitt auf die Master-Einstellung verweist:
                if ($sec_mode === 'master') {
                    $master_mode = $this->master_config['header_mode'] ?? 'full';
                    if (in_array($master_mode, ['none', 'custom', 'logo_only', 'address_only'], true)) {
                        return $master_mode;
                    }
                    // Für Master 'full': Master-Checkboxen prüfen
                    $has_logo = !empty($this->master_config['header_logo']);
                    $has_addr = !empty($this->master_config['header_address']);
                    if (!$has_logo && !$has_addr) return 'none';
                    if ($has_logo && !$has_addr) return 'logo_only';
                    if (!$has_logo && $has_addr) return 'address_only';
                    return 'full';
                }

                // 2. Explizit gewählter Abschnittsmodus:
                if (in_array($sec_mode, ['none', 'custom', 'logo_only', 'address_only'], true)) {
                    return $sec_mode;
                }

                // 3. Für expliziten Abschnittsmodus 'full': Abschnitts-Checkboxen prüfen
                $has_logo = isset($cfg['header_logo']) ? !empty($cfg['header_logo']) : true;
                $has_addr = isset($cfg['header_address']) ? !empty($cfg['header_address']) : true;
                if (!$has_logo && !$has_addr) return 'none';
                if ($has_logo && !$has_addr) return 'logo_only';
                if (!$has_logo && $has_addr) return 'address_only';
                return 'full';
            }

            public function resolveFooterMode($cfg)
            {
                $mode = $cfg['footer_mode'] ?? 'master';
                if ($mode === 'master') {
                    $mode = $this->master_config['footer_mode'] ?? 'standard';
                }
                return $mode;
            }

            public function AddPage($orientation='', $format='', $keepmargins=false, $tocpage=false)
            {
                $h_mode = $this->resolveHeaderMode($this->current_section_config);

                // Ermittlung des dynamischen oberen Seitenrands (header_margin_bottom)
                $sec_margin_bottom = $this->current_section_config['header_margin_bottom'] ?? null;
                $master_margin_bottom = $this->master_config['header_margin_bottom'] ?? null;

                if ($h_mode === 'none') {
                    // Wenn keine Kopfzeile: kompakter Rand 18 mm (oder benutzerdefinierter Rand)
                    $top_margin = ($sec_margin_bottom !== null && $sec_margin_bottom !== '') ? floatval($sec_margin_bottom) : 18.0;
                } else {
                    // Wenn Kopfzeile aktiv: Priorität: 1. Abschnitts-Override -> 2. Master-Vorgabe (Standard: 32 mm)
                    if ($sec_margin_bottom !== null && $sec_margin_bottom !== '') {
                        $top_margin = floatval($sec_margin_bottom);
                    } elseif ($master_margin_bottom !== null && $master_margin_bottom !== '') {
                        $top_margin = floatval($master_margin_bottom);
                    } else {
                        $top_margin = 32.0;
                    }
                }
                $this->SetTopMargin($top_margin);

                $f_mode = $this->resolveFooterMode($this->current_section_config);
                $this->SetAutoPageBreak(TRUE, ($f_mode === 'none' ? 12 : 14));

                parent::AddPage($orientation, $format, $keepmargins, $tocpage);
                $this->page_configs[$this->page] = $this->current_section_config;
            }

            public function Header()
            {
                if (!isset($this->page_configs[$this->page])) {
                    $this->page_configs[$this->page] = $this->current_section_config;
                }
                $cfg  = $this->page_configs[$this->page] ?? $this->current_section_config;
                $mode = $this->resolveHeaderMode($cfg);

                if ($mode === 'none') {
                    return;
                }

                $html = CRM_Pdf_Offer_Elements::render_header(
                    $mode,
                    $this->company_info,
                    $this->logo_html,
                    $cfg,
                    $this->master_config,
                    $this->course_obj
                );

                if (!empty($html)) {
                    // Dynamischer vertikaler Header-Abstand von oben (header_margin_top):
                    // Priorität: 1. Abschnitts-Override -> 2. Master-Vorgabe -> 3. Standard 8.0 mm
                    $sec_margin_top    = $cfg['header_margin_top'] ?? null;
                    $master_margin_top = $this->master_config['header_margin_top'] ?? null;

                    if ($sec_margin_top !== null && $sec_margin_top !== '') {
                        $header_y = floatval($sec_margin_top);
                    } elseif ($master_margin_top !== null && $master_margin_top !== '') {
                        $header_y = floatval($master_margin_top);
                    } else {
                        $header_y = 8.0;
                    }

                    // Header-Positionierung: Logo 5-7 pt (ca. 2.1 mm) weiter links
                    $header_x = 12.9; // 15.0 mm - 6 pt (~2.1 mm) weiter links
                    $header_w = 182.1; // 210 mm - 12.9 mm (links) - 15.0 mm (rechts)

                    $this->writeHTMLCell(
                        $w = $header_w,
                        $h = 0,
                        $x = $header_x,
                        $y = $header_y,
                        $html,
                        $border = 0,
                        $ln = 1,
                        $fill = 0,
                        $reseth = true,
                        $align = 'top',
                        $autopadding = true
                    );
                }
            }

            public function Footer()
            {
                $cfg = $this->page_configs[$this->page] ?? $this->current_section_config;
                $mode = $this->resolveFooterMode($cfg);

                if ($mode === 'none') {
                    return;
                }

                $this->SetY(-15);
                $this->SetFont('dejavusans', '', 7);
                $this->SetTextColor(100, 116, 139);

                if ($mode === 'custom') {
                    $custom_footer = !empty($cfg['footer_custom']) ? $cfg['footer_custom'] : ($this->master_config['footer_custom'] ?? '');
                    if (function_exists('crm_replace_pdf_placeholders')) {
                        $custom_footer = crm_replace_pdf_placeholders($custom_footer, $this->course_obj);
                    }
                    $custom_footer = str_replace(['{PAGENO}', '{pno}'], $this->getAliasNumPage(), $custom_footer);
                    $custom_footer = str_replace(['{NB}', '{nbpg}'], $this->getAliasNbPages(), $custom_footer);
                    $custom_footer = str_replace('{datum}', date('d.m.Y'), $custom_footer);
                    $this->Cell(0, 10, $custom_footer, 0, false, 'C');
                    return;
                }

                $show_company  = isset($cfg['footer_company']) ? !empty($cfg['footer_company']) : ($this->master_config['footer_company'] ?? true);
                $show_page_num = isset($cfg['footer_page_num']) ? !empty($cfg['footer_page_num']) : ($this->master_config['footer_page_num'] ?? true);
                $show_date     = isset($cfg['footer_date']) ? !empty($cfg['footer_date']) : ($this->master_config['footer_date'] ?? false);

                // Override flags if specific mode chosen
                if ($mode === 'page_numbers_only') {
                    $show_company  = false;
                    $show_page_num = true;
                    $show_date     = false;
                } elseif ($mode === 'company_only') {
                    $show_company  = true;
                    $show_page_num = false;
                    $show_date     = false;
                } elseif ($mode === 'full') {
                    $show_company  = true;
                    $show_page_num = true;
                    $show_date     = true;
                } elseif ($mode === 'standard') {
                    $show_company  = true;
                    $show_page_num = true;
                    $show_date     = false;
                }

                if (!$show_company && !$show_page_num && !$show_date) {
                    return;
                }

                $date_str = date('d.m.Y');

                if ($show_company) {
                    $c_uid   = !empty($this->company_info['company_uid']) ? $this->company_info['company_uid'] : 'ATU76624137';
                    $c_court = !empty($this->company_info['company_court']) ? $this->company_info['company_court'] : 'Landesgericht Wiener Neustadt';
                    $c_fn    = !empty($this->company_info['company_fn']) ? $this->company_info['company_fn'] : 'FN 550277 g';

                    $line1 = "UID: " . $c_uid . " | Firmenbuchgericht: " . $c_court;
                    $line2 = "Firmenbuchnummer: " . $c_fn;

                    if ($show_date) {
                        $line2 .= "  |  Datum: " . $date_str;
                    }
                    if ($show_page_num) {
                        $line2 .= "  |  Seite " . $this->getAliasNumPage() . " von " . $this->getAliasNbPages();
                    }

                    $this->Cell(0, 4, $line1, 0, 1, 'C');
                    $this->Cell(0, 4, $line2, 0, 0, 'C');
                } else {
                    $single_line = '';
                    if ($show_date && $show_page_num) {
                        $single_line = "Datum: " . $date_str . "  |  Seite " . $this->getAliasNumPage() . " von " . $this->getAliasNbPages();
                    } elseif ($show_page_num) {
                        $single_line = "Seite " . $this->getAliasNumPage() . " von " . $this->getAliasNbPages();
                    } elseif ($show_date) {
                        $single_line = "Datum: " . $date_str;
                    }

                    $this->Cell(0, 10, $single_line, 0, false, 'C');
                }
            }

            public function cleanupTempFiles()
            {
                unset(self::$cleaned_ids[$this->file_id]);
                $this->_destroy(true);
            }
        }
    }

    $pdf = new MYPDFA_Angebot(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $safe_vorname  = sanitize_file_name($course->vorname ?: 'Kunde');
    $safe_nachname = sanitize_file_name($course->nachname ?: 'Angebot');
    $token         = function_exists('crm_generate_pdf_token') ? crm_generate_pdf_token($entry_id, 'angebot') : '';
    $variant_tag   = '';
    if ($offer_variant === 'basis') {
        $variant_tag = 'Angebot_1_Basis_';
    } elseif ($offer_variant === 'mit_zertifikat') {
        $variant_tag = 'Angebot_2_inkl_Zertifizierung_';
    }
    $pdf_name      = "A_" . $nummer . "_" . $variant_tag . ($token ? $token . '_' : '') . $safe_title . "_" . $safe_vorname . "_" . $safe_nachname . ".pdf";

    $header_company_name    = !empty($course->company_name) ? $course->company_name : 'X SIEBEN Wirtschaftstraining GmbH';
    $header_company_address = !empty($course->company_address) ? $course->company_address : 'Kurzegasse 7, 2493 Lichtenwörth';
    $header_company_phone   = !empty($course->company_phone) ? $course->company_phone : '0800 700 170';
    $header_company_email   = !empty($course->company_email) ? $course->company_email : 'office@x-sieben.at';

    $effective_logo = !empty($course->xsieben_logo)
        ? $course->xsieben_logo
        : (function_exists('crm_resolve_asset_path') ? '<img width="200" style="max-width:200px; height:auto;" src="' . esc_attr(crm_resolve_asset_path('xsieben_logo.png')) . '">' : '');

    $pdf->company_info = [
        'company_name'    => $header_company_name,
        'company_address' => $header_company_address,
        'company_phone'   => $header_company_phone,
        'company_email'   => $header_company_email,
        'company_uid'     => !empty($course->company_uid) ? $course->company_uid : 'ATU76624137',
        'company_court'   => !empty($course->company_court) ? $course->company_court : 'Landesgericht Wiener Neustadt',
        'company_fn'      => !empty($course->company_fn) ? $course->company_fn : 'FN 550277 g',
        'xsieben_logo'    => $effective_logo,
    ];
    $pdf->master_config = function_exists('crm_get_pdf_master_header_footer') ? crm_get_pdf_master_header_footer() : [];
    $pdf->course_obj    = $course;

    $header_html_content = CRM_Pdf_Offer_Elements::render_header('full', $pdf->company_info, $effective_logo);

    $pdf->header_content = $header_html_content;
    $pdf->logo_html       = $effective_logo;
    $pdf->footer_text     = !empty($course->company_uid)
        ? ("UID: " . $course->company_uid . " | Firmenbuchgericht: " . $course->company_court . "\nFirmenbuchnummer: " . $course->company_fn)
        : "UID: ATU76624137 | Firmenbuchgericht: Landesgericht Wiener Neustadt\nFirmenbuchnummer: FN 550277 g";

    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($pdfAuthor);
    $pdf->SetTitle('Angebot' . $angebotsnummer);
    $pdf->SetSubject('Angebot ' . $angebotsnummer);

    $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(PDF_MARGIN_LEFT, 32, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER - 1);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->SetCellPadding(0);

    // Holen der hierarchischen Abschnitte (inkl. Subsections & Custom-Sections)
    if (is_array($custom_sections) && !empty($custom_sections) && is_array(reset($custom_sections)) && isset(reset($custom_sections)['key'])) {
        $all_sections = $custom_sections;
    } else {
        $target_doc = ($offer_variant === 'mit_zertifikat') ? 'angebot_2' : 'angebot';
        $all_sections = crm_get_pdf_section_order($target_doc, $entry_id);
        // Filter falls $custom_sections als Key-Liste übergeben wurde
        if (is_array($custom_sections) && !empty($custom_sections)) {
            $allowed_keys = is_string(reset($custom_sections)) ? $custom_sections : array_column($custom_sections, 'key');
            $filtered = [];
            foreach ($all_sections as $sec) {
                if (in_array($sec['key'], $allowed_keys, true)) {
                    $filtered[] = $sec;
                }
            }
            $all_sections = $filtered;
        }
    }
    $global_spacing = function_exists('crm_get_pdf_elements_spacing') ? crm_get_pdf_elements_spacing() : ['spacing_top' => 0, 'spacing_bottom' => 0];

    foreach ($all_sections as $sec) {
        if (empty($sec['enabled'])) {
            continue;
        }

        $sec_key     = $sec['key'];
        $is_custom   = !empty($sec['is_custom']);
        $sec_spacing = function_exists('crm_get_pdf_effective_spacing')
            ? crm_get_pdf_effective_spacing($sec, $global_spacing)
            : ['top' => 0, 'bottom' => 0];
        $sec_prefix  = function_exists('crm_get_pdf_spacing_html') ? crm_get_pdf_spacing_html($sec_spacing['top']) : '';
        $sec_suffix  = function_exists('crm_get_pdf_spacing_html') ? crm_get_pdf_spacing_html($sec_spacing['bottom']) : '';
        $sec_html    = '';

        if ($is_custom) {
            // Benutzerdefinierte Seite
            $badge = !empty($sec['badge']) ? $sec['badge'] : 'Zusatz';
            $sec_html .= $course->get_pdf_title($sec['title'], $badge) . '<div style="font-size:14pt">&nbsp;</div>';
            if (!empty($sec['content'])) {
                $sec_html .= '<div style="font-size:10pt; line-height:1.6;">' . crm_replace_pdf_placeholders($sec['content'], $course) . '</div>';
            }
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) {
                        continue;
                    }
                    $sub_k = $sub['key'] ?? '';
                    $sub_effective = function_exists('crm_get_pdf_effective_spacing')
                        ? crm_get_pdf_effective_spacing($sub, $global_spacing)
                        : ['top' => floatval($sub['spacing_top'] ?? 0), 'bottom' => floatval($sub['spacing_bottom'] ?? 0)];
                    $sub_sp_top    = $sub_effective['top'];
                    $sub_sp_bottom = $sub_effective['bottom'];
                    $sub_prefix    = ($sub_sp_top > 0 && function_exists('crm_get_pdf_spacing_html')) ? crm_get_pdf_spacing_html($sub_sp_top) : '';
                    $sub_suffix    = ($sub_sp_bottom > 0 && function_exists('crm_get_pdf_spacing_html')) ? crm_get_pdf_spacing_html($sub_sp_bottom) : '';

                    $sub_item_html = '';
                    if (!empty($sub['is_custom'])) {
                        if (!empty($sub['title'])) {
                            $sub_item_html .= '<div style="font-size:11pt; font-weight:bold; margin-top:10px; margin-bottom:4px; color:#0f172a;">' . esc_html($sub['title']) . '</div>';
                        }
                        if (!empty($sub['content'])) {
                            $c_text = crm_replace_pdf_placeholders($sub['content'], $course);
                            if (!preg_match('/<(?:table|div|p|ul|ol|h[1-6]|br)\b/i', $c_text)) {
                                $c_text = nl2br($c_text);
                            }
                            $sub_item_html .= '<div style="font-size:10pt; line-height:1.6; margin-bottom:8px;">' . $c_text . '</div>';
                        }
                    } elseif (isset($flattened_sub_generators[$sub_k])) {
                        $def_sub_html = $flattened_sub_generators[$sub_k];
                        $custom_content = trim($sub['content'] ?? '');
                        if (!empty($custom_content) && $custom_content !== '{standard}' && !(function_exists('crm_is_legacy_default_pdf_content') && crm_is_legacy_default_pdf_content($sec_key, $sub_k, $custom_content))) {
                            if (strpos($custom_content, '{standard}') !== false) {
                                $c_html = str_replace('{standard}', $def_sub_html, $custom_content);
                            } else {
                                $c_html = !preg_match('/<(?:table|div|p|ul|ol|h[1-6]|br)\b/i', $custom_content)
                                    ? '<div style="font-size:10pt; line-height:1.5; margin-bottom:8px;">' . nl2br($custom_content) . '</div>'
                                    : '<div style="margin-bottom:6px;">' . $custom_content . '</div>';
                            }
                            $sub_item_html .= crm_replace_pdf_placeholders($c_html, $course);
                        } else {
                            $sub_item_html .= $def_sub_html;
                        }
                    } elseif (!empty($sub['content'])) {
                        $sub_item_html .= '<div style="font-size:10pt; line-height:1.6; margin-top:8px;">' . crm_replace_pdf_placeholders($sub['content'], $course) . '</div>';
                    }

                    if (!empty($sub_item_html)) {
                        $sec_html .= $sub_prefix . $sub_item_html . $sub_suffix;
                    }
                }
            }
        } else {
            // Vordefinierte Standard-Seite: Unterabschnitte der Reihe nach zusammensetzen
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) {
                        continue;
                    }
                    $sub_key       = $sub['key'] ?? '';
                    $sub_effective = function_exists('crm_get_pdf_effective_spacing')
                        ? crm_get_pdf_effective_spacing($sub, $global_spacing)
                        : ['top' => floatval($sub['spacing_top'] ?? 0), 'bottom' => floatval($sub['spacing_bottom'] ?? 0)];
                    $sub_sp_top    = $sub_effective['top'];
                    $sub_sp_bottom = $sub_effective['bottom'];

                    if ($sec_key === 'deckblatt') {
                        if ($sub_key === 'titel') {
                            // Sicherstellen, dass der Dokumententitel auf Seite 1 immer einen sauberen vertikalen Abstand zur Anrede hat (mind. 32 pt, Standard 36 pt)
                            $has_explicit_sub_bot = isset($sub['spacing_bottom']) && $sub['spacing_bottom'] !== '' && floatval($sub['spacing_bottom']) > 0;
                            if (!$has_explicit_sub_bot) {
                                $sub_sp_bottom = max(32.0, floatval($global_spacing['title_spacing_bottom'] ?? 36.0));
                            }
                        } elseif ($sub_key === 'signatur') {
                            // Sicherstellen, dass nach der Geschäftsführer-Signatur ein harmonischer Abstand vor dem PS: liegt (18 pt statt 8 pt)
                            $has_explicit_sub_top = isset($sub['spacing_top']) && floatval($sub['spacing_top']) > 0;
                            $has_explicit_sub_bot = isset($sub['spacing_bottom']) && floatval($sub['spacing_bottom']) > 0;
                            if (!$has_explicit_sub_top) {
                                $sub_sp_top = min(8.0, $sub_sp_top);
                            }
                            if (!$has_explicit_sub_bot) {
                                $sub_sp_bottom = 18.0;
                            }
                        } elseif ($sub_key === 'gruss') {
                            // Harmonischer Abstand zwischen Einleitungstext und Grußformel (mind. 8 pt)
                            $has_explicit_sub_top = isset($sub['spacing_top']) && floatval($sub['spacing_top']) > 0;
                            $has_explicit_sub_bot = isset($sub['spacing_bottom']) && floatval($sub['spacing_bottom']) > 0;
                            if (!$has_explicit_sub_top) {
                                $sub_sp_top = 8.0;
                            }
                            if (!$has_explicit_sub_bot) {
                                $sub_sp_bottom = min(8.0, $sub_sp_bottom);
                            }
                        } else {
                            $has_explicit_sub_top = isset($sub['spacing_top']) && floatval($sub['spacing_top']) > 0;
                            $has_explicit_sub_bot = isset($sub['spacing_bottom']) && floatval($sub['spacing_bottom']) > 0;
                            if (!$has_explicit_sub_top) {
                                $sub_sp_top = min(8.0, $sub_sp_top);
                            }
                            if (!$has_explicit_sub_bot) {
                                $sub_sp_bottom = min(8.0, $sub_sp_bottom);
                            }
                        }
                    } elseif (empty($sub['is_custom'])) {
                        // Auf Folgeseiten: Standard-Unterabschnitte kompakt halten, falls keine expliziten Werte gesetzt wurden
                        $has_explicit_sub_top = isset($sub['spacing_top']) && $sub['spacing_top'] !== '' && floatval($sub['spacing_top']) > 0;
                        $has_explicit_sub_bot = isset($sub['spacing_bottom']) && $sub['spacing_bottom'] !== '' && floatval($sub['spacing_bottom']) > 0;
                        if (!$has_explicit_sub_top) {
                            $sub_sp_top = min(4.0, $sub_sp_top);
                        }
                        if (!$has_explicit_sub_bot) {
                            $sub_sp_bottom = min(4.0, $sub_sp_bottom);
                        }
                    }

                    $sub_prefix = ($sub_sp_top > 0 && function_exists('crm_get_pdf_spacing_html')) ? crm_get_pdf_spacing_html($sub_sp_top) : '';
                    $sub_suffix = ($sub_sp_bottom > 0 && function_exists('crm_get_pdf_spacing_html')) ? crm_get_pdf_spacing_html($sub_sp_bottom) : '';
                    $sub_html   = '';

                    if (!empty($sub['is_custom'])) {
                        // Benutzerdefinierter Unterabschnitt
                        if (!empty($sub['title'])) {
                            $sub_html .= '<div style="font-size:11pt; font-weight:bold; margin-top:10px; margin-bottom:4px; color:#0f172a;">' . esc_html($sub['title']) . '</div>';
                        }
                        if (!empty($sub['content'])) {
                            $custom_sub_text = crm_replace_pdf_placeholders($sub['content'], $course);
                            if (!preg_match('/<(?:table|div|p|ul|ol|h[1-6]|br)\b/i', $custom_sub_text)) {
                                $custom_sub_text = nl2br($custom_sub_text);
                            }
                            $sub_html .= '<div style="font-size:10pt; line-height:1.6; margin-bottom:8px;">' . $custom_sub_text . '</div>';
                        }
                    } elseif (isset($subsections_generators[$sec_key][$sub_key]) || isset($flattened_sub_generators[$sub_key])) {
                        $default_sub_html = $subsections_generators[$sec_key][$sub_key] ?? $flattened_sub_generators[$sub_key];
                        $custom_content   = trim($sub['content'] ?? '');

                        $is_default_snippet = false;
                        if (!empty($custom_content)) {
                            if ($custom_content === '{standard}') {
                                $is_default_snippet = true;
                            } elseif (function_exists('crm_is_legacy_default_pdf_content') && crm_is_legacy_default_pdf_content($sec_key, $sub_key, $custom_content)) {
                                $is_default_snippet = true;
                            }
                        }

                        if (!empty($custom_content) && !$is_default_snippet) {
                            if (strpos($custom_content, '{standard}') !== false) {
                                $custom_sub_html = str_replace('{standard}', $default_sub_html, $custom_content);
                            } else {
                                $custom_sub_html = $custom_content;
                                if (!preg_match('/<(?:table|div|p|ul|ol|h[1-6]|br)\b/i', $custom_sub_html)) {
                                    $custom_sub_html = '<div style="font-size:10pt; line-height:1.5; margin-bottom:8px;">' . nl2br($custom_sub_html) . '</div>';
                                } else {
                                    $custom_sub_html = '<div style="margin-bottom:6px;">' . $custom_sub_html . '</div>';
                                }
                            }
                            $sub_html .= crm_replace_pdf_placeholders($custom_sub_html, $course);
                        } else {
                            $sub_html .= $default_sub_html;
                        }
                    }

                    if (!empty($sub_html)) {
                        $sec_html .= $sub_prefix . $sub_html . $sub_suffix;
                    }
                }
            } else {
                // Fallback: Alle Generatoren des Abschnitts
                if (isset($subsections_generators[$sec_key])) {
                    $sec_html = implode('', $subsections_generators[$sec_key]);
                }
            }
        }

        if (!empty(trim(strip_tags($sec_html, '<img>')))) {
            $pdf->setSectionConfig($sec);
            $pdf->AddPage();

            // Deckblatt: AutoPageBreak temporär deaktivieren, damit alle 7 Elemente (inkl. Gliederungsverweis) auf Seite 1 bleiben
            if ($sec_key === 'deckblatt') {
                $pdf->SetAutoPageBreak(false);
            } else {
                $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
            }

            $full_page_html = $styles . $sec_prefix . $sec_html . $sec_suffix;
            $pattern = '/(<table[^>]*class=[\'"][^\'"]*\btitle\b[^\'"]*[\'"][^>]*>.*?<\/table>)/si';
            $parts   = preg_split($pattern, $full_page_html, -1, PREG_SPLIT_DELIM_CAPTURE);

            if (count($parts) > 1) {
                foreach ($parts as $idx => $part) {
                    $clean_part = preg_replace('/<style\b[^>]*>.*?<\/style>/si', '', $part);
                    if (empty(trim(strip_tags($clean_part, '<img>'))) && strpos($part, 'crm-pdf-spacer') === false) {
                        continue;
                    }
                    if ($idx % 2 === 1) {
                        // Titel-Banner über die gesamte Seitenbreite (0 bis Seitenbreite) rendern
                        // Fluchtlinie: 15mm Textabstand wie beim Fließtext (11.8mm Spacer + TCPDF Zellabstand = exakt 15.2mm Fluchtlinie)
                        $fullwidth_title = $part;
                        if (strpos($fullwidth_title, 'width="11.8mm"') === false && preg_match('/<td[^>]*>(.*?)<\/td>/si', $fullwidth_title, $td_m)) {
                            $inner_text = $td_m[1];
                            $fullwidth_title = '
                            <table class="title" cellpadding="3" cellspacing="0" border="0" style="width: 100%; background-color: #007C90;">
                                <!-- Fluchtlinie: 15mm Textabstand -->
                                <tr>
                                    <td width="11.8mm" style="font-size: 1pt; line-height: 1pt;">&nbsp;</td>
                                    <td width="186.4mm" style="color: #ffffff; font-size: 11pt; line-height: 16pt; vertical-align: middle;">
                                        ' . $inner_text . '
                                    </td>
                                    <td width="11.8mm" style="font-size: 1pt; line-height: 1pt;">&nbsp;</td>
                                </tr>
                            </table>';
                        }
                        $page_w  = $pdf->getPageWidth();
                        $title_y = $pdf->GetY();
                        $pdf->writeHTMLCell($page_w, 0, 0, $title_y, $fullwidth_title, 0, 1, 0, true, 'L', false);
                    } else {
                        // Fließtext & Abschnitte innerhalb des normalen Seitenrands (15mm)
                        $chunk_html = (strpos($part, '<style') === false) ? ($styles . $part) : $part;
                        $pdf->writeHTML($chunk_html, true, false, true, false, '');
                    }
                }
            } else {
                $pdf->writeHTML($full_page_html, true, false, true, false, '');
            }

            // Nach Deckblatt Standard-AutoPageBreak wieder aktivieren
            $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
        }
    }

    $save_dir = function_exists('crm_get_pdf_storage_dir') ? crm_get_pdf_storage_dir() : (get_template_directory() . '/angebote/');
    if (!file_exists($save_dir)) {
        wp_mkdir_p($save_dir);
    }
    // Clean up any older PDF files for this entry and variant to prevent stale file clutter
    $clean_pattern = 'A_' . $nummer . '_*.pdf';
    if ($offer_variant === 'basis') {
        $clean_pattern = 'A_' . $nummer . '_Angebot_1_Basis_*.pdf';
    } elseif ($offer_variant === 'mit_zertifikat') {
        $clean_pattern = 'A_' . $nummer . '_Angebot_2_inkl_Zertifizierung_*.pdf';
    }
    $existing_old_files = glob($save_dir . $clean_pattern);
    if (!empty($existing_old_files)) {
        foreach ($existing_old_files as $old_file) {
            if (basename($old_file) !== $pdf_name && file_exists($old_file)) {
                @unlink($old_file);
            }
        }
    }

    $save_path = $save_dir . $pdf_name;
    $save_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $save_path);
    $pdf->Output($save_path, 'F');
    $pdf->cleanupTempFiles();
    $storage_url = function_exists('crm_get_pdf_storage_url') ? crm_get_pdf_storage_url() : (get_template_directory_uri() . '/angebote/');
    $pdf_url = $storage_url . rawurlencode($pdf_name);
    if ($output_to_browser) {
        x_sieben_pdf_preview($pdf_url, $course_id, $entry_id, 'xsieben_angebot');
    }
    else {
        return $pdf_url;
    }
}
