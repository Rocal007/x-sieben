<?php
/**
 * X-SIEBEN CRM - Kurszeitenbestätigung (KB) PDF Generator
 *
 * Generiert die Kurszeitenbestätigung via TCPDF.
 * Alle visuellen HTML-Fragmente wurden nach MVC- und Autarkie-Kriterien
 * in eigenständige Elemente (elements/kb-*.php) ausgelagert.
 *
 * @package X_SIEBEN_CRM
 * @version 2.18.13
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/elements/kb-elements.php';

function xsieben_kurszeitenbestaetigung_pdf($entry_id, $course_id, $output_to_browser = true, $custom_sections = null)
{
    // Model laden
    $course = new CRM_Model($course_id, $entry_id);

    $clean_text = function ($val) {
        $decoded = html_entity_decode($val ?? '', ENT_QUOTES, 'UTF-8');
        return trim(strip_tags($decoded));
    };

    $clean_inline_html = function ($val) {
        $val = preg_replace('/^\s*<p[^>]*>/iu', '', $val ?? '');
        $val = preg_replace('/<\/p>\s*$/iu', '', $val);
        return trim($val);
    };

    // Variablen aus dem Model
    $file_title       = (function_exists('mb_substr') ? mb_substr(preg_replace('/[^a-zA-Z0-9_\-äöüÄÖÜß]/u', '-', (string)($course->titel_short ?: ($course->title ?: 'Kurs'))), 0, 50) : substr(preg_replace('/[^a-zA-Z0-9_\-]/', '-', (string)($course->titel_short ?: 'Kurs')), 0, 50));
    $title            = $clean_text($course->title);
    $display_title    = (function_exists('mb_strlen') && mb_strlen($title, 'UTF-8') > 105) ? mb_strimwidth($title, 0, 102, '...', 'UTF-8') : $title;
    $startdatum       = $course->start_datum;
    $enddatum         = $course->end_datum;
    $vorname          = $course->vorname;
    $nachname         = $course->nachname;
    $svr              = $course->svr;
    $kursart_t        = $course->kursart_t;
    $kursart_a        = $course->kursart_a;
    $kursart_we       = $course->kursart_we;
    $kurszeiten       = $course->kurszeiten;    // key-value array
    $selbststudium    = $course->selbststudium;  // key-value array
    $kurszeiten_datum = date('d.m.Y');

    // Assets-Pfade (autark, mit URL-Fallback für Live-Server)
    $stempel_file = crm_resolve_asset_path(file_exists(get_template_directory() . '/inc/core/crm/assets/stempel.png') ? 'stempel.png' : 'Signatur_Blau.png');

    // Dateiname mit kryptografischem Schutz-Token
    $safe_vorname  = sanitize_file_name($vorname ?: 'Kunde');
    $safe_nachname = sanitize_file_name($nachname ?: 'Teilnehmer');
    $token         = function_exists('crm_generate_pdf_token') ? crm_generate_pdf_token($entry_id, 'kb') : '';
    $pdfName       = "Kurszeitenbestaetigung_" . $safe_vorname . "_" . $safe_nachname . "_" . ($token ? $token . '_' : '') . $file_title . ".pdf";

    // Dynamische Texte aus dem CRM Model (PDF Editor) mit Default-Fallback
    $default_kb_institut = !empty($course->company_name) ? $course->company_name : 'X SIEBEN Wirtschaftstraining GmbH';
    $default_kb_ort      = !empty($course->location_wien) ? ($course->location_wien . ' bzw. online') : 'Rochusgasse 6, 1030 Wien bzw. online';

    $kb_title        = $clean_text($course->get_crm_field_with_default('KB - Titel', 'Bestätigung Kurszeiten'));
    $kb_institut     = $clean_text($course->get_crm_field_with_default('KB - Kursinstitut Name', $default_kb_institut));
    $kb_ort          = $clean_text($course->get_crm_field_with_default('KB - Schulungsort', $default_kb_ort));
    $kb_hinweis      = $clean_text($course->get_crm_field_with_default('KB - Hinweistext', 'Bei unregelmäßigen Kurszeiten ist ein Ablaufplan der einzelnen Kurswochen beizulegen.'));
    $kb_sig_institut = $clean_inline_html($course->get_crm_field_with_default('KB - Signatur Institut', 'Wien, ' . $kurszeiten_datum . '<br>Unterschrift, Stampiglie Kursinstitut'));
    $kb_sig_kunde    = $clean_inline_html($course->get_crm_field_with_default('KB - Signatur Kunde', 'Ort, Datum, Unterschrift, Kunde/Kundin'));

    // --- Modular HTML Sections for Dynamic Ordering ---
    require_once dirname(__DIR__) . '/helpers/crm-pdf-sections.php';

    // Generierung der HTML-Elemente über CRM_Pdf_Kb_Elements
    $sec_titel          = CRM_Pdf_Kb_Elements::render_titel($kb_title);
    $kb_institut_subs   = CRM_Pdf_Kb_Elements::get_institut_subs($kb_institut, $kb_ort, $display_title, $startdatum, $enddatum);
    $kb_teilnehmer_subs = CRM_Pdf_Kb_Elements::get_teilnehmer_subs($vorname, $nachname, $svr);
    $sec_kurstyp        = CRM_Pdf_Kb_Elements::render_kurstyp($kursart_t, $kursart_a, $kursart_we);
    $sec_kurszeiten     = CRM_Pdf_Kb_Elements::render_kurszeiten($kurszeiten, $selbststudium);
    $sec_hinweis        = CRM_Pdf_Kb_Elements::render_hinweis($kb_hinweis);
    $sec_signatur       = CRM_Pdf_Kb_Elements::render_signatur($stempel_file, $kb_sig_institut, $kb_sig_kunde);

    $kb_subsections = [
        'titel'      => ['haupttitel' => $sec_titel],
        'institut'   => $kb_institut_subs,
        'teilnehmer' => $kb_teilnehmer_subs,
        'kurstyp'    => ['kurstyp_box' => $sec_kurstyp],
        'kurszeiten' => ['kurszeiten_box' => $sec_kurszeiten],
        'hinweis'    => ['hinweis_box' => $sec_hinweis],
        'signatur'   => ['signatur_box' => $sec_signatur],
    ];

    $flattened_sub_generators = [];
    foreach ($kb_subsections as $sec_k => $subs) {
        if (is_array($subs)) {
            foreach ($subs as $sub_k => $sub_gen) {
                $flattened_sub_generators[$sub_k] = $sub_gen;
            }
        }
    }

    // Holen der hierarchischen Abschnitte
    if (is_array($custom_sections) && !empty($custom_sections) && is_array(reset($custom_sections)) && isset(reset($custom_sections)['key'])) {
        $all_sections = $custom_sections;
    } else {
        $all_sections = crm_get_pdf_section_order('kb', $entry_id);
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

    $html = '';
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
        $sec_content = '';

        if ($is_custom) {
            $sec_content .= '<div style="margin-bottom:12px; font-size:10pt; line-height:1.6;">';
            if (!empty($sec['title'])) {
                $sec_content .= '<strong>' . esc_html($sec['title']) . '</strong><br>';
            }
            if (!empty($sec['content'])) {
                $sec_content .= crm_replace_pdf_placeholders($sec['content'], $course);
            }
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    $sub_sp_top = isset($sub['spacing_top']) && is_numeric($sub['spacing_top']) ? floatval($sub['spacing_top']) : 0.0;
                    $sub_sp_bottom = isset($sub['spacing_bottom']) && is_numeric($sub['spacing_bottom']) ? floatval($sub['spacing_bottom']) : 0.0;
                    $sub_prefix = ($sub_sp_top > 0 && function_exists('crm_get_pdf_spacing_html')) ? crm_get_pdf_spacing_html($sub_sp_top) : '';
                    $sub_suffix = ($sub_sp_bottom > 0 && function_exists('crm_get_pdf_spacing_html')) ? crm_get_pdf_spacing_html($sub_sp_bottom) : '';

                    $sk = $sub['key'] ?? '';
                    $sub_html = '';
                    if (!empty($sub['is_custom'])) {
                        if (!empty($sub['title'])) {
                            $sub_html .= '<div style="font-size:10pt; font-weight:bold; margin-top:6px;">' . esc_html($sub['title']) . '</div>';
                        }
                        if (!empty($sub['content'])) {
                            $sub_html .= '<div style="margin-top:4px;">' . crm_replace_pdf_placeholders($sub['content'], $course) . '</div>';
                        }
                    } elseif (isset($flattened_sub_generators[$sk])) {
                        $def_sub = $flattened_sub_generators[$sk];
                        if (!empty($sub['content']) && $sub['content'] !== '{standard}') {
                            $custom = (strpos($sub['content'], '{standard}') !== false)
                                ? str_replace('{standard}', $def_sub, $sub['content'])
                                : $sub['content'];
                            $sub_html .= crm_replace_pdf_placeholders($custom, $course);
                        } else {
                            $sub_html .= $def_sub;
                        }
                    } elseif (!empty($sub['content'])) {
                        $sub_html .= '<div style="margin-top:4px;">' . crm_replace_pdf_placeholders($sub['content'], $course) . '</div>';
                    }
                    if (!empty($sub_html)) {
                        $sec_content .= $sub_prefix . $sub_html . $sub_suffix;
                    }
                }
            }
            $sec_content .= '</div><div style="font-size:16pt">&nbsp;</div>';

        } elseif ($sec_key === 'titel') {
            $sec_content .= $sec_titel;

        } elseif ($sec_key === 'institut') {
            $rows_html = '';
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    $sk = $sub['key'];
                    if (!empty($sub['is_custom']) && !empty($sub['content'])) {
                        $rows_html .= '<tr><td style="font-size:10pt; padding-bottom: 3pt; border-bottom: 1px solid black;">' . crm_replace_pdf_placeholders($sub['content'], $course) . '</td></tr><tr><td style="font-size: 5pt;">&nbsp;</td></tr>';
                    } elseif (isset($kb_institut_subs[$sk]) || isset($flattened_sub_generators[$sk])) {
                        $def_sub = $kb_institut_subs[$sk] ?? $flattened_sub_generators[$sk];
                        if (!empty($sub['content'])) {
                            if (strpos($sub['content'], '{standard}') !== false) {
                                $custom = str_replace('{standard}', $def_sub, $sub['content']);
                            } else {
                                $custom = '<tr><td style="font-size:10pt; padding-bottom: 3pt; border-bottom: 1px solid black;">' . $sub['content'] . '</td></tr><tr><td style="font-size: 5pt;">&nbsp;</td></tr>';
                            }
                            $rows_html .= crm_replace_pdf_placeholders($custom, $course);
                        } else {
                            $rows_html .= $def_sub;
                        }
                    }
                }
            } else {
                $rows_html = implode('', $kb_institut_subs);
            }

            $sec_content .= '<table cellspacing="0" cellpadding="0" style="width: 100%;">
                <tr>
                    <td style="font-size:11pt; font-weight: bold; padding-bottom: 6px;">
                        Kursinstitut
                    </td>
                </tr>' . $rows_html . '
            </table>
            <div style="font-size:14pt">&nbsp;</div>';

        } elseif ($sec_key === 'teilnehmer') {
            $rows_html = '';
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    $sk = $sub['key'];
                    if (!empty($sub['is_custom']) && !empty($sub['content'])) {
                        $rows_html .= '<tr><td colspan="3" style="font-size:10pt; padding-bottom: 3pt; border-bottom: 1px solid black;">' . crm_replace_pdf_placeholders($sub['content'], $course) . '</td></tr><tr><td style="font-size: 5pt;">&nbsp;</td></tr>';
                    } elseif (isset($kb_teilnehmer_subs[$sk]) || isset($flattened_sub_generators[$sk])) {
                        $def_sub = $kb_teilnehmer_subs[$sk] ?? $flattened_sub_generators[$sk];
                        if (!empty($sub['content'])) {
                            if (strpos($sub['content'], '{standard}') !== false) {
                                $custom = str_replace('{standard}', $def_sub, $sub['content']);
                            } else {
                                $custom = '<tr><td colspan="3" style="font-size:10pt; padding-bottom: 3pt; border-bottom: 1px solid black;">' . $sub['content'] . '</td></tr><tr><td style="font-size: 5pt;">&nbsp;</td></tr>';
                            }
                            $rows_html .= crm_replace_pdf_placeholders($custom, $course);
                        } else {
                            $rows_html .= $def_sub;
                        }
                    }
                }
            } else {
                $rows_html = implode('', $kb_teilnehmer_subs);
            }

            $sec_content .= '<table cellspacing="0" cellpadding="0" style="width: 100%;">
                <tr>
                    <td colspan="3" style="font-size:11pt; font-weight: bold; padding-bottom: 6px;">
                        KursteilnehmerIn:
                    </td>
                </tr>' . $rows_html . '
            </table>
            <div style="font-size:20pt">&nbsp;</div>';

        } elseif ($sec_key === 'kurstyp') {
            $sec_out = $sec_kurstyp;
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    if (!empty($sub['content'])) {
                        $sec_out = (strpos($sub['content'], '{standard}') !== false)
                            ? str_replace('{standard}', $sec_kurstyp, $sub['content'])
                            : $sub['content'];
                        $sec_out = crm_replace_pdf_placeholders($sec_out, $course);
                    }
                }
            }
            $sec_content .= $sec_out;

        } elseif ($sec_key === 'kurszeiten') {
            $sec_out = $sec_kurszeiten;
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    if (!empty($sub['content'])) {
                        $sec_out = (strpos($sub['content'], '{standard}') !== false)
                            ? str_replace('{standard}', $sec_kurszeiten, $sub['content'])
                            : $sub['content'];
                        $sec_out = crm_replace_pdf_placeholders($sec_out, $course);
                    }
                }
            }
            $sec_content .= $sec_out;

        } elseif ($sec_key === 'hinweis') {
            $sec_out = $sec_hinweis;
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    if (!empty($sub['content'])) {
                        $sec_out = (strpos($sub['content'], '{standard}') !== false)
                            ? str_replace('{standard}', $sec_hinweis, $sub['content'])
                            : ('<div style="font-size:8pt">&nbsp;</div><div style="font-size:8.5pt; color: #334155;">' . $sub['content'] . '</div><div style="font-size:32pt">&nbsp;</div>');
                        $sec_out = crm_replace_pdf_placeholders($sec_out, $course);
                    }
                }
            }
            $sec_content .= $sec_out;

        } elseif ($sec_key === 'signatur') {
            $sec_out = $sec_signatur;
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    if (!empty($sub['content'])) {
                        $sec_out = (strpos($sub['content'], '{standard}') !== false)
                            ? str_replace('{standard}', $sec_signatur, $sub['content'])
                            : $sub['content'];
                        $sec_out = crm_replace_pdf_placeholders($sec_out, $course);
                    }
                }
            }
            $sec_content .= $sec_out;
        }

        if (!empty($sec_content)) {
            $html .= $sec_prefix . $sec_content . $sec_suffix;
        }
    }

    if (!class_exists('TCPDF')) {
        $tcpdf_path = get_template_directory() . '/tcbpdf/tcpdf.php';
        if (file_exists($tcpdf_path)) {
            require_once $tcpdf_path;
        }
    }

    if (!class_exists('MYPDFA_Kurszeiten')) {
        class MYPDFA_Kurszeiten extends TCPDF
        {
            public $header_content = '';
            public $logo_html = '';

            public function Header()
            {
                // Do not render a header
            }

            public function Footer()
            {
                // Do not render a footer
            }
        }
    }

    $pdfAuthor = 'XSieben Wirtschaftstraining GmbH';

    // TCPDF Objekt erzeugen
    $pdf = new MYPDFA_Kurszeiten(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($pdfAuthor);
    $pdf->SetTitle('Kurszeitenbestaetigung');
    $pdf->SetSubject('Kurszeitenbestaetigung');

    // Header und Footer entfernen
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Ränder & 1-Seiten-Garantie
    $pdf->SetMargins(15, 10, 15);
    $pdf->SetAutoPageBreak(false);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->SetCellPadding(0);

    // Neue Seite
    $pdf->AddPage();
    $pdf->writeHTML($html, true, false, true, false, '');

    // Ordner sicherstellen & PDF speichern
    $save_dir = function_exists('crm_get_pdf_storage_dir') ? crm_get_pdf_storage_dir() : (get_template_directory() . '/angebote/');
    if (!file_exists($save_dir)) {
        wp_mkdir_p($save_dir);
    }
    // Clean up older KB files for this student
    $existing_old_kb = glob($save_dir . 'Kurszeitenbestaetigung_' . $safe_vorname . '_' . $safe_nachname . '_*.pdf');
    if (!empty($existing_old_kb)) {
        foreach ($existing_old_kb as $old_f) {
            if (basename($old_f) !== $pdfName && file_exists($old_f)) {
                @unlink($old_f);
            }
        }
    }
    $save_path = $save_dir . $pdfName;
    $save_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $save_path);
    $pdf->Output($save_path, 'F');

    // URL für Webzugriff
    $storage_url = function_exists('crm_get_pdf_storage_url') ? crm_get_pdf_storage_url() : (get_template_directory_uri() . '/angebote/');
    $pdf_url = $storage_url . rawurlencode($pdfName);
    if ($output_to_browser) {
        x_sieben_pdf_preview($pdf_url, $course_id, $entry_id, 'kurszeitenbestaetigung');
    } else {
        return $pdf_url;
    }
}

if (!function_exists('xsieben_kb_pdf')) {
    function xsieben_kb_pdf($entry_id, $course_id, $output_to_browser = true, $custom_sections = null)
    {
        return xsieben_kurszeitenbestaetigung_pdf($entry_id, $course_id, $output_to_browser, $custom_sections);
    }
}

