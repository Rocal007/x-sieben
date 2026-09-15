<?php
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
    $kurszeiten       = $course->kurszeiten;    // now a key-value array
    $selbststudium    = $course->selbststudium;  // now a key-value array
    $kurszeiten_datum = date('d.m.Y');

    // Assets-Pfade (autark, mit URL-Fallback für Live-Server)
    $stempel_file = crm_resolve_asset_path(file_exists(get_template_directory() . '/inc/core/crm/assets/stempel.png') ? 'stempel.png' : 'Signatur_Blau.png');

    // Dateiname mit kryptografischem Schutz-Token
    $safe_vorname  = sanitize_file_name($vorname ?: 'Kunde');
    $safe_nachname = sanitize_file_name($nachname ?: 'Teilnehmer');
    $token         = function_exists('crm_generate_pdf_token') ? crm_generate_pdf_token($entry_id, 'kb') : '';
    $pdfName       = "Kurszeitenbestaetigung_" . $safe_vorname . "_" . $safe_nachname . "_" . ($token ? $token . '_' : '') . $file_title . ".pdf";

    $table_style = 'style="border: 1px solid black;"';

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

    // 1. Titel
    $sec_titel = '
<div style="font-size:14pt">&nbsp;</div>
<table cellspacing="0" cellpadding="0" style="width: 100%;">
    <tr>
        <td style="font-size:16pt; font-weight: bold; text-align: center;">
            <span>' . htmlspecialchars($kb_title) . '</span>
        </td>
    </tr>
</table>
<div style="font-size:18pt">&nbsp;</div>';

    // 2. Kursinstitut (Subsections: name, ort, bezeichnung, zeitraum)
    $kb_institut_subs = [
        'name'        => '<tr><td style="font-size:10pt; padding-bottom: 3pt; border-bottom: 1px solid black;">Name des Kursinstituts: ' . htmlspecialchars($kb_institut) . '</td></tr><tr><td style="font-size: 5pt;">&nbsp;</td></tr>',
        'ort'         => '<tr><td style="font-size:10pt; padding-bottom: 3pt; border-bottom: 1px solid black;">Schulungsort (Adresse): ' . htmlspecialchars($kb_ort) . '</td></tr><tr><td style="font-size: 5pt;">&nbsp;</td></tr>',
        'bezeichnung' => '<tr><td style="font-size:10pt; padding-bottom: 3pt; border-bottom: 1px solid black;">Kursbezeichnung: ' . htmlspecialchars($display_title) . '</td></tr><tr><td style="font-size: 5pt;">&nbsp;</td></tr>',
        'zeitraum'    => '<tr><td style="font-size:10pt; padding-bottom: 3pt; border-bottom: 1px solid black;">Kurs von-bis: ' . htmlspecialchars($startdatum) . ' bis ' . htmlspecialchars($enddatum) . '</td></tr><tr><td style="font-size: 5pt;">&nbsp;</td></tr>',
    ];

    // 3. KursteilnehmerIn (Subsections: name_svr_row)
    $kb_teilnehmer_subs = [
        'name_svr_row' => '<tr>
            <td style="width: 50%; font-size:10pt; padding-bottom: 3pt; border-bottom: 1px solid black;">
                Name: ' . htmlspecialchars($vorname . ' ' . $nachname) . '
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 46%; font-size:10pt; padding-bottom: 3pt; border-bottom: 1px solid black;">
                SV-Nummer:' . (!empty($svr) ? ' ' . htmlspecialchars((string)$svr) : '') . '
            </td>
        </tr>',
    ];

    // 4. Kurstyp
    $sec_kurstyp = '
<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid black; font-size: 10pt;">
    <tr>
        <td style="width: 34%;">Kurstyp: Tageskurs ' . $kursart_t . '</td>
        <td style="width: 33%;">Abendkurs ' . $kursart_a . '</td>
        <td style="width: 33%;">Wochenendkurs ' . $kursart_we . '</td>
    </tr>
</table>
<div style="font-size:20pt">&nbsp;</div>';

    // 5. Dynamische Tabelle Kurszeiten
    $weekdays = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    $sec_kurszeiten = '<table border="1" cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse; border: 1px solid black; font-size: 9.5pt;">
        <thead>
            <tr style="background-color: #f1f5f9;">
                <th style="border: 1px solid black; width: 20%; font-weight: bold; text-align: left;">Kurstage</th>
                <th style="border: 1px solid black; width: 26%; font-weight: bold; text-align: center;">Kurszeit (von - bis)<br><span style="font-size: 8pt; color: #475569; font-weight: normal;">exkl. Mittagspause</span></th>
                <th style="border: 1px solid black; width: 27%; font-weight: bold; text-align: center;">Tele-/Selbstlernzeit<br><span style="font-size: 8pt; color: #475569; font-weight: normal;">bei und unter Aufsicht</span></th>
                <th style="border: 1px solid black; width: 27%; font-weight: bold; text-align: center;">Tele-/Selbstlernzeit<br><span style="font-size: 8pt; color: #475569; font-weight: normal;">außerhalb des Kursinstitutes</span></th>
            </tr>
        </thead>
        <tbody>';

    foreach ($weekdays as $day) {
        $key        = strtolower($day);
        $kurszeit   = isset($kurszeiten[$key]) ? $kurszeiten[$key] : '';
        $selbstzeit = isset($selbststudium[$key]) ? $selbststudium[$key] : '';

        $sec_kurszeiten .= '<tr>
                    <td style="border: 1px solid black; width: 20%;"><strong>' . htmlspecialchars($day) . '</strong></td>
                    <td style="border: 1px solid black; width: 26%; text-align: center;">' . htmlspecialchars($kurszeit) . '</td>
                    <td style="border: 1px solid black; width: 27%; text-align: center;">' . htmlspecialchars($selbstzeit) . '</td>
                    <td style="border: 1px solid black; width: 27%; text-align: center;"></td>
                </tr>';
    }
    $sec_kurszeiten .= '</tbody></table>';

    // 6. Hinweistext
    $sec_hinweis = '<div style="font-size:8pt">&nbsp;</div>
    <div style="font-size:8.5pt; color: #334155;">' . htmlspecialchars($kb_hinweis) . '</div>
    <div style="font-size:32pt">&nbsp;</div>';

    // 7. Stampiglie & Unterschrift (exakt ausgerichtete Zeilen)
    $sec_signatur = '
    <table cellspacing="0" cellpadding="0" style="width: 100%;">
        <tr>
            <td style="width: 46%; vertical-align: bottom;">
                <img src="' . esc_attr($stempel_file) . '" width="180px">
            </td>
            <td style="width: 8%;"></td>
            <td style="width: 46%; vertical-align: bottom;">
                &nbsp;
            </td>
        </tr>
        <tr>
            <td style="width: 46%; vertical-align: top;">
                <div style="border-top: 1px solid black; font-size: 2pt;">&nbsp;</div>
                <span style="font-size: 9pt;">' . $kb_sig_institut . '</span>
            </td>
            <td style="width: 8%;"></td>
            <td style="width: 46%; vertical-align: top;">
                <div style="border-top: 1px solid black; font-size: 2pt;">&nbsp;</div>
                <span style="font-size: 9pt;">' . $kb_sig_kunde . '</span>
            </td>
        </tr>
    </table>';

    // Holen der hierarchischen Abschnitte
    $all_sections = crm_get_pdf_section_order('kb', $entry_id);

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

    $html = '';
    foreach ($all_sections as $sec) {
        if (empty($sec['enabled'])) {
            continue;
        }

        $sec_key   = $sec['key'];
        $is_custom = !empty($sec['is_custom']);

        if ($is_custom) {
            $html .= '<div style="margin-bottom:12px; font-size:10pt; line-height:1.6;">';
            if (!empty($sec['title'])) {
                $html .= '<strong>' . esc_html($sec['title']) . '</strong><br>';
            }
            if (!empty($sec['content'])) {
                $html .= crm_replace_pdf_placeholders($sec['content'], $course);
            }
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (!empty($sub['enabled']) && !empty($sub['content'])) {
                        $html .= '<div style="margin-top:6px;">' . crm_replace_pdf_placeholders($sub['content'], $course) . '</div>';
                    }
                }
            }
            $html .= '</div><div style="font-size:16pt">&nbsp;</div>';

        } elseif ($sec_key === 'titel') {
            $html .= $sec_titel;

        } elseif ($sec_key === 'institut') {
            $rows_html = '';
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    $sk = $sub['key'];
                    if (!empty($sub['is_custom']) && !empty($sub['content'])) {
                        $rows_html .= '<tr><td style="font-size:10pt; padding-bottom: 3pt; border-bottom: 1px solid black;">' . crm_replace_pdf_placeholders($sub['content'], $course) . '</td></tr><tr><td style="font-size: 5pt;">&nbsp;</td></tr>';
                    } elseif (isset($kb_institut_subs[$sk])) {
                        $def_sub = $kb_institut_subs[$sk];
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

            $html .= '<table cellspacing="0" cellpadding="0" style="width: 100%;">
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
                    } elseif (isset($kb_teilnehmer_subs[$sk])) {
                        $def_sub = $kb_teilnehmer_subs[$sk];
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

            $html .= '<table cellspacing="0" cellpadding="0" style="width: 100%;">
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
            $html .= $sec_out;

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
            $html .= $sec_out;

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
            $html .= $sec_out;

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
            $html .= $sec_out;
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
