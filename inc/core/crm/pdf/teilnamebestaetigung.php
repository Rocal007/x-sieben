<?php
function xsieben_teilnahmebestaetigung_pdf($entry_id, $course_id, $output_to_browser = true, $custom_sections = null)
{
    // Model laden
    $course = new CRM_Model($course_id, $entry_id);

    $safe_vorname  = sanitize_file_name($course->vorname ?: 'Kunde');
    $safe_nachname = sanitize_file_name($course->nachname ?: 'Teilnehmer');
    $safe_title    = (function_exists('mb_substr') ? mb_substr(sanitize_file_name($course->titel_short ?: ($course->title ?: 'Kurs')), 0, 50) : substr(sanitize_file_name($course->titel_short ?: 'Kurs'), 0, 50));
    $token         = function_exists('crm_generate_pdf_token') ? crm_generate_pdf_token($entry_id, 'tb') : '';
    $pdf_name      = "Teilnahmebestaetigung_" . $safe_vorname . "_" . $safe_nachname . "_" . ($token ? $token . '_' : '') . $safe_title . ".pdf";

    // Textbereinigung
    $clean_text = function ($val) {
        $decoded = html_entity_decode($val ?? '', ENT_QUOTES, 'UTF-8');
        return trim(strip_tags($decoded));
    };

    $clean_inline_html = function ($val) {
        $val = preg_replace('/^\s*<p[^>]*>/iu', '', $val ?? '');
        $val = preg_replace('/<\/p>\s*$/iu', '', $val);
        return trim($val);
    };

    // Daten aus CRM Model
    $tn_name    = trim($course->titel . ' ' . $course->vorname . ' ' . $course->nachname);
    $tn_svr     = $course->svr;
    $tn_adresse = $course->street;
    $tn_plz     = $course->zip_code;
    $tn_ort     = $course->city;

    // Dynamische Felder aus dem CRM Model (PDF Editor) mit Fallbacks
    $tb_title           = $clean_text($course->get_crm_field_with_default('TB - Titel', 'Teilnahmebestätigung'));
    $tb_einleitung      = $clean_text($course->get_crm_field_with_default('TB - Einleitungstext', 'Wir bestätigen, dass'));
    $tb_betrieb_name    = $clean_text($course->get_crm_field_with_default('TB - Betrieb Name', 'X SIEBEN Wirtschaftstraining GmbH'));
    $tb_betrieb_str     = $clean_text($course->get_crm_field_with_default('TB - Betrieb Strasse', 'Kurzegasse 7'));
    $tb_betrieb_plz     = $clean_text($course->get_crm_field_with_default('TB - Betrieb PLZ', '2493'));
    $tb_betrieb_ort     = $clean_text($course->get_crm_field_with_default('TB - Betrieb Ort', 'Lichtenwörth'));
    $tb_ort_str         = $clean_text($course->get_crm_field_with_default('TB - Schulungsort Strasse', 'Rochusgasse 6'));
    $tb_ort_plz         = $clean_text($course->get_crm_field_with_default('TB - Schulungsort PLZ', '1030'));
    $tb_ort_ort         = $clean_text($course->get_crm_field_with_default('TB - Schulungsort Ort', 'Wien'));
    $tb_datum           = $clean_text($course->get_crm_field_with_default('TB - Datum Text', 'Wien, am ' . date('d.m.Y')));
    $tb_unterschrift    = $clean_text($course->get_crm_field_with_default('TB - Unterschrift Zusatz', ''));

    // Standard-Teilnahmetext (Bereinigt von HTML-Entities wie &#8211;, &#038;)
    $clean_course_title   = html_entity_decode(html_entity_decode($course->title ?? '', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
    $default_tb_teilnahme = 'an der Veranstaltung <strong>„' . htmlspecialchars($clean_course_title, ENT_QUOTES, 'UTF-8') . '“</strong> im Gesamtausmaß von <strong>' . htmlspecialchars($course->anzahl_le ?? '') . ' Lehreinheiten</strong> (1 LE = 45 Minuten) teilgenommen hat.';
    $tb_teilnahme_raw     = $course->get_crm_field('TB - Bestaetigungstext') ?: $course->get_crm_field('TB - Teilnahme Text');
    if (empty(trim(strip_tags($tb_teilnahme_raw)))) {
        $tb_teilnahme_raw = $default_tb_teilnahme;
    }
    $tb_teilnahme         = html_entity_decode(html_entity_decode($clean_inline_html($tb_teilnahme_raw), ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');

    // --- Modular HTML Sections for Dynamic Ordering ---
    require_once dirname(__DIR__) . '/helpers/crm-pdf-sections.php';

    // 1. Titel & Einleitung
    $tb_titel_subs = [
        'haupttitel' => '<div style="font-size:4pt">&nbsp;</div>
        <table cellspacing="0" cellpadding="0" style="width: 100%;">
            <tr>
                <td style="font-size:14pt; font-weight: bold; line-height:1; text-align: center;">
                    ' . htmlspecialchars($tb_title) . '
                </td>
            </tr>
        </table>
        <div style="font-size:6pt">&nbsp;</div>',

        'einleitung' => '<table cellspacing="0" cellpadding="0" style="width: 100%;">
            <tr>
                <td style="font-size:9.5pt; line-height: 1; text-align: left;">
                    <span>' . htmlspecialchars($tb_einleitung) . '</span>
                </td>
            </tr>
        </table>
        <div style="font-size:5pt">&nbsp;</div>',
    ];

    // 2. Box 1: Kursteilnehmer
    $sec_teilnehmer = '<div style="width: 100%; border: 2px solid black;">
        <div style="font-size:3pt">&nbsp;</div>
        <table cellpadding="2" cellspacing="0" style="width: 100%;">
            <tr>
                <td style="width: 2%"></td>
                <td style="width: 68%">
                    <span style="font-size:8pt;">Vor- und Familien- /Nachname</span>
                    <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                        <span> ' . (!empty($tn_name) ? htmlspecialchars($tn_name) : '&nbsp;') . '</span>
                    </div>
                </td>
                <td style="width: 2%"></td>
                <td style="width: 26%">
                    <span style="font-size:8pt;">SV-Nummer</span>
                    <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                        <span> ' . (!empty($tn_svr) ? htmlspecialchars($tn_svr) : '&nbsp;') . '</span>
                    </div>
                </td>
                <td style="width: 2%"></td>
            </tr>
            <tr>
                <td style="width: 2%"></td>
                <td colspan="3">
                    <span style="font-size:8pt;">Wohnadresse (Straße, Hausnummer, Stiege, Türnummer)</span>
                    <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                        <span> ' . (!empty($tn_adresse) ? htmlspecialchars($tn_adresse) : '&nbsp;') . '</span>
                    </div>
                </td>
                <td style="width: 2%"></td>
            </tr>
            <tr>
                <td style="width: 2%"></td>
                <td style="width: 26%">
                    <span style="font-size:8pt;">Postleitzahl</span>
                    <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                        <span> ' . (!empty($tn_plz) ? htmlspecialchars($tn_plz) : '&nbsp;') . '</span>
                    </div>
                </td>
                <td style="width: 2%"></td>
                <td style="width: 68%">
                    <span style="font-size:8pt;">Ort</span>
                    <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                        <span> ' . (!empty($tn_ort) ? htmlspecialchars($tn_ort) : '&nbsp;') . '</span>
                    </div>
                </td>
                <td style="width: 2%"></td>
            </tr>
        </table>
        <div style="font-size:3pt">&nbsp;</div>
    </div>
    <div style="font-size:6pt">&nbsp;</div>';

    // 3. Zeitraum
    $sec_zeitraum = '<table cellpadding="0" cellspacing="0" style="width: 100%;">
        <tr>
            <td style="width: 8%; vertical-align: middle; font-size:9.5pt;">vom </td>
            <td style="width: 26%;">
                <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5;">
                    <span> ' . htmlspecialchars($course->start_datum ?: date('d.m.Y')) . '</span>
                </div>
            </td>
            <td style="width: 3%;"></td>
            <td style="width: 6%; vertical-align: middle; font-size:9.5pt;">bis </td>
            <td style="width: 26%;">
                <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5;">
                    <span> ' . htmlspecialchars($course->end_datum ?: date('d.m.Y')) . '</span>
                </div>
            </td>
            <td style="width: 3%;"></td>
            <td style="width: 28%; vertical-align: middle; font-size:9.5pt;">bei</td>
        </tr>
    </table>
    <div style="font-size:6pt">&nbsp;</div>';

    // 4. Box 2: Ausbildungsstätte & Schulungsort
    $sec_ausbildungsstaette = '<div style="width: 100%; border: 2px solid black;">
        <div style="font-size:3pt">&nbsp;</div>
        <table cellpadding="2" cellspacing="0" style="width: 100%;">
            <tr>
                <td style="width: 2%"></td>
                <td style="width: 96%">
                    <span style="font-size:8pt;">Bezeichnung des Betriebes/der Ausbildungseinrichtung</span>
                    <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                        <span> ' . (!empty($tb_betrieb_name) ? htmlspecialchars($tb_betrieb_name) : '&nbsp;') . '</span>
                    </div>
                </td>
                <td style="width: 2%"></td>
            </tr>
            <tr>
                <td style="width: 2%"></td>
                <td style="width: 96%">
                    <span style="font-size:8pt;">Adresse des Betriebes (Straße, Hausnummer, Stiege, Türnummer)</span>
                    <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                        <span> ' . (!empty($tb_betrieb_str) ? htmlspecialchars($tb_betrieb_str) : '&nbsp;') . '</span>
                    </div>
                </td>
                <td style="width: 2%"></td>
            </tr>
        </table>
        <table cellpadding="2" cellspacing="0" style="width: 100%;">
            <tr>
                <td style="width: 2%"></td>
                <td style="width: 26%">
                    <span style="font-size:8pt;">Postleitzahl</span>
                    <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                        <span> ' . (!empty($tb_betrieb_plz) ? htmlspecialchars($tb_betrieb_plz) : '&nbsp;') . '</span>
                    </div>
                </td>
                <td style="width: 2%"></td>
                <td style="width: 68%">
                    <span style="font-size:8pt;">Ort</span>
                    <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                        <span> ' . (!empty($tb_betrieb_ort) ? htmlspecialchars($tb_betrieb_ort) : '&nbsp;') . '</span>
                    </div>
                </td>
                <td style="width: 2%"></td>
            </tr>
        </table>
        <table cellpadding="2" cellspacing="0" style="width: 100%;">
            <tr>
                <td style="width: 2%"></td>
                <td style="width: 96%">
                    <span style="font-size:8pt;">Adresse des Schulungsortes (Straße, Hausnummer, Stiege, Türnummer)</span>
                    <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                        <span> ' . (!empty($tb_ort_str) ? htmlspecialchars($tb_ort_str) : '&nbsp;') . '</span>
                    </div>
                </td>
                <td style="width: 2%"></td>
            </tr>
        </table>
        <table cellpadding="2" cellspacing="0" style="width: 100%;">
            <tr>
                <td style="width: 2%"></td>
                <td style="width: 26%">
                    <span style="font-size:8pt;">Postleitzahl</span>
                    <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                        <span> ' . (!empty($tb_ort_plz) ? htmlspecialchars($tb_ort_plz) : '&nbsp;') . '</span>
                    </div>
                </td>
                <td style="width: 2%"></td>
                <td style="width: 68%">
                    <span style="font-size:8pt;">Ort</span>
                    <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                        <span> ' . (!empty($tb_ort_ort) ? htmlspecialchars($tb_ort_ort) : '&nbsp;') . '</span>
                    </div>
                </td>
                <td style="width: 2%"></td>
            </tr>
        </table>
        <div style="font-size:3pt">&nbsp;</div>
    </div>
    <div style="font-size:8pt">&nbsp;</div>';

    // 5. Teilnahme Text
    $sec_teilnahme = '<table style="width: 100%;">
        <tr>
            <td style="font-size: 9.5pt; line-height: 1.35;">' . $tb_teilnahme . '</td>
        </tr>
    </table>
    <div style="font-size:10pt">&nbsp;</div>';

    // 6. Datum & Unterschrift
    $sec_signatur = '<table cellspacing="0" cellpadding="0" style="width: 100%;">
        <tr>
            <td style="width: 45%; vertical-align: top; font-size: 9.5pt;">
                ' . htmlspecialchars($tb_datum) . '
            </td>
            <td style="width: 55%; vertical-align: top;">
                ' . (!empty($tb_unterschrift) ? '<span style="font-size: 9.5pt;">' . htmlspecialchars($tb_unterschrift) . '</span><br>' : '') . '
                ' . $course->signatur . '
            </td>
        </tr>
    </table>';

    // Holen der hierarchischen Abschnitte
    $all_sections = crm_get_pdf_section_order('tb', $entry_id);

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
            $html .= '</div><div style="font-size:10pt">&nbsp;</div>';

        } elseif ($sec_key === 'titel') {
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    $sk = $sub['key'];
                    if (!empty($sub['is_custom']) && !empty($sub['content'])) {
                        $html .= '<div style="font-size:9.5pt; margin-bottom:4px;">' . crm_replace_pdf_placeholders($sub['content'], $course) . '</div>';
                    } elseif (isset($tb_titel_subs[$sk])) {
                        $def_sub = $tb_titel_subs[$sk];
                        if (!empty($sub['content'])) {
                            if (strpos($sub['content'], '{standard}') !== false) {
                                $custom = str_replace('{standard}', $def_sub, $sub['content']);
                            } else {
                                $custom = '<div style="font-size:9.5pt; margin-bottom:4px;">' . $sub['content'] . '</div>';
                            }
                            $html .= crm_replace_pdf_placeholders($custom, $course);
                        } else {
                            $html .= $def_sub;
                        }
                    }
                }
            } else {
                $html .= implode('', $tb_titel_subs);
            }

        } elseif ($sec_key === 'teilnehmer') {
            $sec_out = $sec_teilnehmer;
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    if (!empty($sub['content'])) {
                        $sec_out = (strpos($sub['content'], '{standard}') !== false)
                            ? str_replace('{standard}', $sec_teilnehmer, $sub['content'])
                            : $sub['content'];
                        $sec_out = crm_replace_pdf_placeholders($sec_out, $course);
                    }
                }
            }
            $html .= $sec_out;

        } elseif ($sec_key === 'zeitraum') {
            $sec_out = $sec_zeitraum;
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    if (!empty($sub['content'])) {
                        $sec_out = (strpos($sub['content'], '{standard}') !== false)
                            ? str_replace('{standard}', $sec_zeitraum, $sub['content'])
                            : $sub['content'];
                        $sec_out = crm_replace_pdf_placeholders($sec_out, $course);
                    }
                }
            }
            $html .= $sec_out;

        } elseif ($sec_key === 'ausbildungsstaette') {
            $sec_out = $sec_ausbildungsstaette;
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    if (!empty($sub['content'])) {
                        $sec_out = (strpos($sub['content'], '{standard}') !== false)
                            ? str_replace('{standard}', $sec_ausbildungsstaette, $sub['content'])
                            : $sub['content'];
                        $sec_out = crm_replace_pdf_placeholders($sec_out, $course);
                    }
                }
            }
            $html .= $sec_out;

        } elseif ($sec_key === 'teilnahme') {
            $sec_out = $sec_teilnahme;
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    if (!empty($sub['content'])) {
                        $sec_out = (strpos($sub['content'], '{standard}') !== false)
                            ? str_replace('{standard}', $sec_teilnahme, $sub['content'])
                            : $sub['content'];
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

    if (!class_exists('MYPDFA_teilnahme')) {
        class MYPDFA_teilnahme extends TCPDF
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

    // TCPDF Objekt erzeugen
    $pdf = new MYPDFA_teilnahme(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    $pdfAuthor = 'XSieben Wirtschaftstraining GmbH';

    // Dokumentinformationen setzen
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($pdfAuthor);
    $pdf->SetTitle('Teilnahmebestätigung');
    $pdf->SetSubject('Teilnahmebestätigung PDF');

    // Kopf- und Fußzeilen entfernen
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Margins & AutoPageBreak: Exakt 1 Seite garantiert
    $pdf->SetMargins(15, 8, 15);
    $pdf->SetAutoPageBreak(false);

    // Schriftart
    $pdf->SetFont('dejavusans', '', 10);

    // Neue Seite
    $pdf->AddPage();

    // HTML ausgeben
    $pdf->writeHTML($html, true, false, true, false, '');

    // PDF speichern
    $save_dir = function_exists('crm_get_pdf_storage_dir') ? crm_get_pdf_storage_dir() : (get_template_directory() . '/angebote/');
    if (!file_exists($save_dir)) {
        wp_mkdir_p($save_dir);
    }
    // Ältere Teilnahmebestätigungen dieses Teilnehmers bereinigen
    $existing_old_tb = glob($save_dir . 'Teilnahmebestaetigung_' . $safe_vorname . '_' . $safe_nachname . '_*.pdf');
    if (!empty($existing_old_tb)) {
        foreach ($existing_old_tb as $old_f) {
            if (basename($old_f) !== $pdf_name && file_exists($old_f)) {
                @unlink($old_f);
            }
        }
    }
    $save_path = $save_dir . $pdf_name;
    $save_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $save_path);

    $pdf->Output($save_path, 'F');

    // URL zum PDF für Webzugriff
    $storage_url = function_exists('crm_get_pdf_storage_url') ? crm_get_pdf_storage_url() : (get_template_directory_uri() . '/angebote/');
    $pdf_url = $storage_url . rawurlencode($pdf_name);
    if ($output_to_browser) {
        x_sieben_pdf_preview($pdf_url, $course_id, $entry_id, 'teilnahmebestaetigung');
    } else {
        return $pdf_url;
    }
}
