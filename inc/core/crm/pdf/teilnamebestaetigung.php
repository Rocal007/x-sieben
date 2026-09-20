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
    require_once __DIR__ . '/elements/tb-elements.php';

    // 1. Titel & Einleitung
    $tb_titel_subs = CRM_Pdf_Tb_Elements::get_titel_subs($tb_title, $tb_einleitung);

    // 2. Box 1: Kursteilnehmer
    $sec_teilnehmer = CRM_Pdf_Tb_Elements::render_teilnehmer($tn_name, $tn_svr, $tn_adresse, $tn_plz, $tn_ort);

    // 3. Zeitraum
    $sec_zeitraum = CRM_Pdf_Tb_Elements::render_zeitraum($course->start_datum ?: '', $course->end_datum ?: '');

    // 4. Box 2: Ausbildungsstätte & Schulungsort
    $sec_ausbildungsstaette = CRM_Pdf_Tb_Elements::render_ausbildungsstaette(
        $tb_betrieb_name,
        $tb_betrieb_str,
        $tb_betrieb_plz,
        $tb_betrieb_ort,
        $tb_ort_str,
        $tb_ort_plz,
        $tb_ort_ort
    );

    // 5. Teilnahme Text
    $sec_teilnahme = CRM_Pdf_Tb_Elements::render_teilnahme($tb_teilnahme);

    // 6. Datum & Unterschrift
    $sec_signatur = CRM_Pdf_Tb_Elements::render_signatur($tb_datum, $tb_unterschrift, $course->signatur);

    $tb_subsections = [
        'titel'              => $tb_titel_subs,
        'teilnehmer'         => ['teilnehmer_box' => $sec_teilnehmer],
        'zeitraum'           => ['zeitraum_box' => $sec_zeitraum],
        'ausbildungsstaette' => ['ausbildungsstaette_box' => $sec_ausbildungsstaette],
        'teilnahme'          => ['teilnahme_box' => $sec_teilnahme],
        'signatur'           => ['signatur_box' => $sec_signatur],
    ];

    $flattened_sub_generators = [];
    foreach ($tb_subsections as $sec_k => $subs) {
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
        $all_sections = crm_get_pdf_section_order('tb', $entry_id);
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
            $sec_content .= '</div><div style="font-size:10pt">&nbsp;</div>';

        } elseif ($sec_key === 'titel') {
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    $sk = $sub['key'];
                    $sub_sp_top = isset($sub['spacing_top']) && is_numeric($sub['spacing_top']) ? floatval($sub['spacing_top']) : 0.0;
                    $sub_sp_bottom = isset($sub['spacing_bottom']) && is_numeric($sub['spacing_bottom']) ? floatval($sub['spacing_bottom']) : 0.0;
                    $sub_prefix = ($sub_sp_top > 0 && function_exists('crm_get_pdf_spacing_html')) ? crm_get_pdf_spacing_html($sub_sp_top) : '';
                    $sub_suffix = ($sub_sp_bottom > 0 && function_exists('crm_get_pdf_spacing_html')) ? crm_get_pdf_spacing_html($sub_sp_bottom) : '';

                    if (!empty($sub['is_custom']) && !empty($sub['content'])) {
                        $sec_content .= $sub_prefix . '<div style="font-size:9.5pt; margin-bottom:4px;">' . crm_replace_pdf_placeholders($sub['content'], $course) . '</div>' . $sub_suffix;
                    } elseif (isset($tb_titel_subs[$sk]) || isset($flattened_sub_generators[$sk])) {
                        $def_sub = $tb_titel_subs[$sk] ?? $flattened_sub_generators[$sk];
                        if (!empty($sub['content'])) {
                            if (strpos($sub['content'], '{standard}') !== false) {
                                $custom = str_replace('{standard}', $def_sub, $sub['content']);
                            } else {
                                $custom = '<div style="font-size:9.5pt; margin-bottom:4px;">' . $sub['content'] . '</div>';
                            }
                            $sec_content .= $sub_prefix . crm_replace_pdf_placeholders($custom, $course) . $sub_suffix;
                        } else {
                            $sec_content .= $sub_prefix . $def_sub . $sub_suffix;
                        }
                    }
                }
            } else {
                $sec_content .= implode('', $tb_titel_subs);
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
            $sec_content .= $sec_out;

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
            $sec_content .= $sec_out;

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
            $sec_content .= $sec_out;

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
