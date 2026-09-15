<?php
function xsieben_offer_pdf($entry_id, $course_id, $output_to_browser=true, $custom_sections=null)
{
    // Load course data
    $course = new CRM_Model($course_id, $entry_id);

    $nummer = $entry_id . '-' . $course_id;
    $safe_title = (function_exists('mb_substr') ? mb_substr(preg_replace('/[^\p{L}0-9_\-]/u', '_', (string)($course->titel_short ?: ($course->title ?: 'Kurs'))), 0, 50) : substr(preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)($course->titel_short ?: 'Kurs')), 0, 50));
    $angebotsnummer = "A_" . $nummer;
    $pdfAuthor = "XSieben Wirtschaftstraining";

    // --- HTML Styles (to be included in all parts) ---
    $styles = '<style>
        div {font-size:11pt;}
        table {border-collapse: collapse; border: none;}
        td, th {border: none;}
        .title {
            text-align: left;
            font-size: 16px;
            background-color: #007C90;
            padding: 6pt 8pt 6pt 8pt;
            color: white;
            width: 100%;
        }
        .text {
            line-height: 14pt;
            font-size: 10.5pt;
        }
        .clear {font-size:unset;}
        a {color: #04b3ce}
        hr {border: none; height: 1px;}
        .table-dot {font-size:9pt; color: #04b3ce; border: 1px dashed #16A0B9;}
        strong, b {font-weight: bold;}
        h3.modul-heading {
            font-size: 11pt;
            font-weight: bold;
            color: #007C90;
            border-bottom: 1.5px solid #007C90;
            padding-bottom: 3pt;
            margin-top: 14pt;
            margin-bottom: 8pt;
        }
        .modul-label {
            font-weight: bold;
            color: #0f172a;
        }
        p.modul-text {
            font-size: 10pt;
            line-height: 16pt;
            margin-bottom: 6pt;
        }
        ul.modul-list {
            margin-top: 4pt;
            margin-bottom: 10pt;
        }
        ul.modul-list li {
            font-size: 10pt;
            line-height: 16pt;
            padding-bottom: 3pt;
        }
        .modul-intro {
            margin-top: 4pt;
            margin-bottom: 12pt;
            line-height: 16pt;
        }
        .modul-intro p {
            line-height: 16pt;
            margin-bottom: 6pt;
        }
        .abschluss-heading {
            font-size: 11pt;
            font-weight: bold;
            color: #007C90;
            border-bottom: 1.5px solid #007C90;
            padding-bottom: 3pt;
            margin-top: 16pt;
            margin-bottom: 8pt;
        }
    </style>';

    // --- Modular HTML Sections for Dynamic Ordering ---
    require_once dirname(__DIR__) . '/helpers/crm-pdf-sections.php';

    // 1. Deckblatt / Anschreiben
    $angebot_default_intro = 'Danke für Ihr Interesse und willkommen bei der beliebten X SIEBEN Veranstaltung ' . $course->title . ' mit lernförderndem Kleingruppen-Unterricht.<br><br>Diese Veranstaltung fokussiert auf ' . $course->zielgruppe;
    $angebot_intro         = $course->get_crm_field_with_default('Angebot - Einleitung', $angebot_default_intro);
    $angebot_gruss         = $course->get_crm_field_with_default('Angebot - Grußformel', "Ich freue mich über Ihre Rückmeldung / Buchung.<br>\nMit freundlichen Grüßen,");

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

    $default_ort_durchfuehrung = '<table class="text" cellpadding="0" cellspacing="0" border="0" style="width:100%;">   
                        <tr>
                            <td style="font-size: 11pt; color: #0f172a;"><strong>ORT:</strong> X SIEBEN Wirtschaftstraining, Rochusgasse 6 in 1030 Wien</td>
                        </tr>
                        <tr>
                            <td style="height: 12pt; font-size: 12pt; line-height: 12pt;">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="line-height: 18pt; color: #334155; font-size: 10pt;">
                                <strong>Durchführung unserer Schulungen:</strong> Online Unterricht | vor Ort in unseren Veranstaltungsräumen | Blended Learning
                            </td>
                        </tr>
                        <tr>
                            <td style="height: 8pt; font-size: 8pt; line-height: 8pt;">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="color: #64748b; font-size: 9.5pt; line-height: 15pt;">
                                <em>Hinweis: Die Schulung wird bis zur TeilnehmerInnen-Anzahl von drei Personen adäquat verkürzt, wobei alle Inhalte vermittelt werden.</em>
                            </td>
                        </tr>
                    </table>';

    // Subsections Mapping per Standard Section
    $subsections_generators = [
        'deckblatt' => [
            'empfaenger' => '<table class="text" cellpadding="0" cellspacing="0" border="0" style="width: 100%;">
                <tr>
                    <td style="vertical-align:top; width: 55%; font-size: 10pt; line-height: 14pt;">' . $course->format_postal_address('A', true) . '</td>
                    <td style="vertical-align:top; text-align: right; font-size: 9.5pt; line-height: 14pt; width: 45%;">
                        Angebotsnummer: ' . $angebotsnummer . '<br>Angebotsdatum: ' . $course->current . '<br> Angebot gültig bis: ' . $course->expire . '
                    </td>
                </tr>
            </table>
            <div style="font-size:10pt">&nbsp;</div>',

            'titel' => $course->get_pdf_title($course->titel_short, 'Angebot') . '<div style="font-size:10pt">&nbsp;</div>',

            'anrede_text' => '<table class="text" cellpadding="0" cellspacing="0" border="0" style="width: 100%;">
                <tr>
                    <td>' . esc_html($salutation_name) . ',<br><br>' .
                        $angebot_intro . '
                    </td>
                </tr>
            </table>
            <div style="font-size:6pt">&nbsp;</div>',

            'gruss' => '<table class="text" cellpadding="0" cellspacing="0" border="0" style="width: 100%;">
                <tr>
                    <td>' . $angebot_gruss . '</td>
                </tr>
            </table>
            <div style="font-size:6pt">&nbsp;</div>',

            'signatur' => $course->signatur,

            'ps' => (!empty($course->ps) ? ('<div style="font-size:10pt">&nbsp;</div>' . $course->ps) : ''),

            'hinweis_nachstehend' => '<div style="font-size:8pt">&nbsp;</div>'
                . '<table cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin: 0; padding: 0;">'
                . '<tr><td style="margin: 0; padding: 0; font-size: 9pt; line-height: 1.3; color: #0f172a; text-align: left;">'
                . '<strong>Nachstehend: </strong>Veranstaltungsinformationen | Anhang 1: Details zu den Inhalten der Veranstaltung | Anhang 2: Exklusive Zusatzleistungen'
                . '</td></tr></table>',
        ],

        'veranstaltung' => [
            'titel' => $course->get_pdf_title($course->titel_short, 'Veranstaltungsinformationen') . '<div style="font-size:18pt; line-height:18pt;">&nbsp;</div>',

            'zeitraum' => '<table cellpadding="0" cellspacing="0" border="0" style="font-size: 11pt; width:100%;">
                <tr>
                    <td style="width:6%; vertical-align:middle;">' . $course->calender_icon . '</td>
                    <td style="width:94%; vertical-align:middle;"><div style="font-size:3pt">&nbsp;</div> Vom <strong>' . $course->start_datum . '</strong> bis einschließlich<strong> ' . $course->end_datum . '</strong></td>
                </tr>
            </table>' . crm_pdf_divider('#cbd5e1', 12, 16),

            'lehreinheiten' => '<div style="font-size: 11pt;">Diese Veranstaltung beinhaltet <strong>' . $course->anzahl_le . ' Lehreinheiten</strong> (LE, 1 LE = 45min).</div><div style="font-size:14pt; line-height:14pt;">&nbsp;</div>',

            'module' => $course->module_html,
        ],

        'abschluss' => [
            'titel' => $course->get_pdf_title($course->titel_short) . '<div style="font-size:24pt; line-height:24pt;">&nbsp;</div>',

            'abschluss_box' => '<table class="text" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                <tr>
                    <td style="width:5%; vertical-align:middle;">' . $course->abschluss_icon . '</td>
                    <td style="width:95%; vertical-align:middle; font-size:11.5pt;"><strong> IHR PERSÖNLICHER ABSCHLUSS</strong></td>
                </tr>
            </table>'
            . crm_pdf_divider('#cbd5e1', 12, 16) .
            '<table cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                <tr>
                    <td style="height:12pt; font-size:12pt; line-height:12pt;">&nbsp;</td>
                </tr>
                <tr>
                    <td style="text-align:center; font-size:13.5pt; font-weight:bold; color:#0f172a; line-height:22pt;">
                       ' . $course->abschluss . '
                    </td>
                </tr>
                <tr>
                    <td style="height:10pt; font-size:10pt; line-height:10pt;">&nbsp;</td>
                </tr>
                <tr>
                    <td style="border-top: 1.5px solid #007C90; height:18pt; font-size:18pt; line-height:18pt;">&nbsp;</td>
                </tr>
            </table>',

            'voraussetzungen' => '<table class="text" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                <tr>
                    <td style="width:5%; vertical-align:middle;">' . $course->danger_icon . '</td>
                    <td style="width:95%; vertical-align:middle; font-size:11.5pt;"><strong>Voraussetzungen</strong></td>
                </tr>
                <tr>
                    <td colspan="2" style="height:8pt; font-size:8pt; line-height:8pt;">&nbsp;</td>
                </tr>
                <tr>
                    <td colspan="2">' . $course->voraussetzungen_html . '</td>
                </tr>
            </table>' . crm_pdf_divider('#cbd5e1', 16, 20),

            'zertifizierungen' => '<table class="text" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                <tr>
                    <td style="font-size:11.5pt; font-weight:bold; color:#0f172a;"><strong>ZERTIFIZIERUNGSPARTNER ...</strong></td>
                </tr>
                <tr>
                    <td style="height:10pt; font-size:10pt; line-height:10pt;">&nbsp;</td>
                </tr>
                <tr>
                    <td>' . $course->zertifizierungen_images_html . '</td>
                </tr>
            </table>'
            . crm_pdf_divider('#cbd5e1', 18, 22),

            'ort_durchfuehrung' => $course->get_crm_field_with_default('Angebot - Ort und Durchführung', $default_ort_durchfuehrung),

            'beratung' => $course->beratung_email,
        ],

        'kosten' => [
            'titel' => $course->get_pdf_title('Kursgebühr inkl. optionale Zertifizierungen', 'Ihre Investition') . '<div style="font-size:20pt">&nbsp;</div>',

            'preistabelle' => $course->get_gesamt_kosten_html() . '<div style="font-size:40pt">&nbsp;</div>',

            'gueltigkeit' => '<table class="text" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="font-size: 10.5pt;"><strong>ANGEBOT GÜLTIG</strong> bis max. Gruppengrösse erreicht bzw.: <span> ' . $course->expire . '</span></td>
                </tr>
            </table>' . crm_pdf_divider('#cbd5e1', 12, 16),

            'bankverbindung' => $course->bankverbindung,
        ],

        'anmeldung' => [
            'titel' => $course->get_pdf_title($course->title, 'ANMELDUNG') . '<div style="font-size:20pt">&nbsp;</div>',

            'kundendaten' => $course->get_contact_info_html(),

            'agb' => $course->anmeldung_agb,

            'signatur_kunde' => $course->signatur . '<div style="font-size:10pt">&nbsp;</div>',

            'anhang_hinweise' => '<table cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin: 0; padding: 0;">'
                . '<tr><td style="margin: 0; padding: 0; font-size: 10pt; line-height: 1.4; color: #0f172a; text-align: left;">'
                . '<strong>Anhang 1: </strong>Details zu den Inhalten der Veranstaltung<br>'
                . '<strong>Anhang 2: </strong>Exklusive Zusatzleistungen'
                . '</td></tr></table>',
        ],

        'inhalte' => [
            'titel' => $course->get_pdf_title('Details zu den Inhalten', 'Anhang 1') . '<div style="font-size:18pt; line-height:18pt;">&nbsp;</div>',
            'curriculum' => $course->inhalte,
        ],

        'zusatzleistungen' => [
            'titel' => $course->get_pdf_title('Exklusive Zusatzleistungen', 'Anhang 2') . '<div style="font-size:20pt">&nbsp;</div>',
            'garantien' => $course->garantie,
        ],
    ];

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
                if ($h_mode === 'none') {
                    $this->SetTopMargin(18);
                } elseif ($h_mode === 'logo_only') {
                    $this->SetTopMargin(44);
                } elseif ($h_mode === 'address_only') {
                    $this->SetTopMargin(36);
                } else {
                    $this->SetTopMargin(44);
                }

                $f_mode = $this->resolveFooterMode($this->current_section_config);
                if ($f_mode === 'none') {
                    $this->SetAutoPageBreak(TRUE, 15);
                } else {
                    $this->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
                }

                parent::AddPage($orientation, $format, $keepmargins, $tocpage);
                $this->page_configs[$this->page] = $this->current_section_config;
            }

            public function Header()
            {
                if (!isset($this->page_configs[$this->page])) {
                    $this->page_configs[$this->page] = $this->current_section_config;
                }
                $cfg = $this->page_configs[$this->page] ?? $this->current_section_config;
                $mode = $this->resolveHeaderMode($cfg);

                if ($mode === 'none') {
                    return;
                }

                $html = '';
                $y = 12;

                if ($mode === 'custom') {
                    $custom_html = !empty($cfg['header_custom']) ? $cfg['header_custom'] : ($this->master_config['header_custom'] ?? '');
                    $html = function_exists('crm_replace_pdf_placeholders') ? crm_replace_pdf_placeholders($custom_html, $this->course_obj) : $custom_html;
                } elseif ($mode === 'logo_only') {
                    $logo = !empty($this->company_info['xsieben_logo']) ? $this->company_info['xsieben_logo'] : $this->logo_html;
                    if (empty($logo) && function_exists('crm_resolve_asset_path')) {
                        $logo = '<img width="200" style="max-width:200px; height:auto;" src="' . esc_attr(crm_resolve_asset_path('xsieben_logo.png')) . '">';
                    }
                    $html = '<table cellspacing="0" cellpadding="0" border="0" style="width: 100%;">
                        <tr>
                            <td style="width: 100%; text-align: left; vertical-align: top; line-height: 1; font-size: 1pt; padding: 0; margin: 0;">' . $logo . '</td>
                        </tr>
                    </table>';
                } elseif ($mode === 'address_only') {
                    $c_name  = !empty($this->company_info['company_name']) ? $this->company_info['company_name'] : 'X SIEBEN Wirtschaftstraining GmbH';
                    $c_addr  = !empty($this->company_info['company_address']) ? $this->company_info['company_address'] : 'Kurzegasse 7, 2493 Lichtenwörth';
                    $c_phone = !empty($this->company_info['company_phone']) ? $this->company_info['company_phone'] : '0800 700 170';
                    $c_email = !empty($this->company_info['company_email']) ? $this->company_info['company_email'] : 'office@x-sieben.at';

                    $html = '<table cellspacing="0" cellpadding="0" border="0" style="width: 100%;">
                        <tr>
                            <td style="font-size: 8.5pt; width: 100%; text-align: right; line-height: 12pt; color: #475569;">
                                <strong style="color: #0f172a;">' . htmlspecialchars($c_name) . '</strong><br>
                                ' . htmlspecialchars($c_addr) . '<br>
                                Telefon: ' . htmlspecialchars($c_phone) . ' | E-Mail: ' . htmlspecialchars($c_email) . '
                            </td>
                        </tr>
                    </table>';
                } else {
                    // Full header: Logo links, Firmenadresse rechts — immer dynamisch aufgebaut
                    $logo    = !empty($this->company_info['xsieben_logo']) ? $this->company_info['xsieben_logo'] : $this->logo_html;
                    if (empty($logo) && function_exists('crm_resolve_asset_path')) {
                        $logo = '<img width="200" style="max-width:200px; height:auto;" src="' . esc_attr(crm_resolve_asset_path('xsieben_logo.png')) . '">';
                    }
                    $c_name  = !empty($this->company_info['company_name']) ? $this->company_info['company_name'] : 'X SIEBEN Wirtschaftstraining GmbH';
                    $c_addr  = !empty($this->company_info['company_address']) ? $this->company_info['company_address'] : 'Kurzegasse 7, 2493 Lichtenwörth';
                    $c_phone = !empty($this->company_info['company_phone']) ? $this->company_info['company_phone'] : '0800 700 170';
                    $c_email = !empty($this->company_info['company_email']) ? $this->company_info['company_email'] : 'office@x-sieben.at';

                    $html = '<table cellspacing="0" cellpadding="0" border="0" style="text-align: left; width: 100%;">
                        <tr>
                            <td style="width: 55%; vertical-align: top; line-height: 1; font-size: 1pt; padding: 0; margin: 0;">' . $logo . '</td>
                            <td style="font-size: 9pt; width: 45%; text-align: right; line-height: 13pt; color: #334155; vertical-align: top; padding: 0; margin: 0;">
                                <strong>' . htmlspecialchars($c_name) . '</strong><br>
                                ' . htmlspecialchars($c_addr) . '<br>
                                Telefon: ' . htmlspecialchars($c_phone) . '<br>
                                E-Mail: ' . htmlspecialchars($c_email) . '
                            </td>
                        </tr>
                    </table>';
                }

                if (!empty($html)) {
                    $this->writeHTMLCell(
                        $w = 0,
                        $h = 0,
                        $x = '14.1',
                        $y = $y,
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
    $pdf_name      = "A_" . $nummer . "_" . ($token ? $token . '_' : '') . $safe_title . "_" . $safe_vorname . "_" . $safe_nachname . ".pdf";

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

    $header_html_content = '<table cellspacing="0" cellpadding="0" border="0" style="text-align: left; width: 100%;">
        <tr>
            <td style="width: 55%; vertical-align: top; line-height: 1; font-size: 1pt; padding: 0; margin: 0;">' . $effective_logo . '</td>
            <td style="font-size: 9pt; width: 45%; text-align: right; line-height: 13pt; color: #334155; vertical-align: top; padding: 0; margin: 0;">
                <strong>' . htmlspecialchars($header_company_name) . '</strong><br>
                ' . htmlspecialchars($header_company_address) . '<br>
                Telefon: ' . htmlspecialchars($header_company_phone) . '<br>
                E-Mail: ' . htmlspecialchars($header_company_email) . '
            </td>
        </tr>
    </table>';

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
    $pdf->SetMargins(PDF_MARGIN_LEFT, 44, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER - 1);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->SetCellPadding(0);

    // Holen der hierarchischen Abschnitte (inkl. Subsections & Custom-Sections)
    $all_sections = crm_get_pdf_section_order('angebot', $entry_id);

    // Filter falls $custom_sections übergeben wurde
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

    foreach ($all_sections as $sec) {
        if (empty($sec['enabled'])) {
            continue;
        }

        $sec_key   = $sec['key'];
        $is_custom = !empty($sec['is_custom']);
        $sec_html  = '';

        if ($is_custom) {
            // Benutzerdefinierte Seite
            $badge = !empty($sec['badge']) ? $sec['badge'] : 'Zusatz';
            $sec_html .= $course->get_pdf_title($sec['title'], $badge) . '<div style="font-size:14pt">&nbsp;</div>';
            if (!empty($sec['content'])) {
                $sec_html .= '<div style="font-size:10pt; line-height:1.6;">' . crm_replace_pdf_placeholders($sec['content'], $course) . '</div>';
            }
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (!empty($sub['enabled']) && !empty($sub['content'])) {
                        $sec_html .= '<div style="font-size:10pt; line-height:1.6; margin-top:8px;">' . crm_replace_pdf_placeholders($sub['content'], $course) . '</div>';
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
                    $sub_key = $sub['key'];
                    if (!empty($sub['is_custom'])) {
                        // Benutzerdefinierter Unterabschnitt
                        if (!empty($sub['title'])) {
                            $sec_html .= '<div style="font-size:11pt; font-weight:bold; margin-top:10px; margin-bottom:4px; color:#0f172a;">' . esc_html($sub['title']) . '</div>';
                        }
                        if (!empty($sub['content'])) {
                            $custom_sub_text = crm_replace_pdf_placeholders($sub['content'], $course);
                            if (!preg_match('/<(?:table|div|p|ul|ol|h[1-6]|br)\b/i', $custom_sub_text)) {
                                $custom_sub_text = nl2br($custom_sub_text);
                            }
                            $sec_html .= '<div style="font-size:10pt; line-height:1.6; margin-bottom:8px;">' . $custom_sub_text . '</div>';
                        }
                    } elseif (isset($subsections_generators[$sec_key][$sub_key])) {
                        $default_sub_html = $subsections_generators[$sec_key][$sub_key];
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
                            $sec_html .= crm_replace_pdf_placeholders($custom_sub_html, $course);
                        } else {
                            $sec_html .= $default_sub_html;
                        }
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
            $pdf->writeHTML($styles . $sec_html, true, false, true, false, '');
        }
    }

    $save_dir = function_exists('crm_get_pdf_storage_dir') ? crm_get_pdf_storage_dir() : (get_template_directory() . '/angebote/');
    if (!file_exists($save_dir)) {
        wp_mkdir_p($save_dir);
    }
    // Clean up any older PDF files for this entry to prevent stale file clutter or encoding collisions
    $existing_old_files = glob($save_dir . 'A_' . $nummer . '_*.pdf');
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
