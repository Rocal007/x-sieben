<?php

/**
 * Generiert die offizielle X-SIEBEN Honorarnote / Rechnung als PDF.
 * Vollständig autark und nahtlos in das modulare CRM-Abschnitts-System (crm-pdf-sections.php) integriert.
 *
 * @param int $entry_id
 * @param int $course_id
 * @param bool $output_to_browser
 * @param array|null $custom_sections
 * @return string|void
 */
function xsieben_invoice_pdf($entry_id, $course_id, $output_to_browser = true, $custom_sections = null)
{
    // TCPDF sicherstellen
    if (!class_exists('TCPDF')) {
        $tcpdf_path = get_template_directory() . '/tcbpdf/tcpdf.php';
        if (file_exists($tcpdf_path)) {
            require_once $tcpdf_path;
        }
    }

    require_once dirname(__DIR__) . '/helpers/crm-pdf-sections.php';

    // Model laden
    $course = new CRM_Model($course_id, $entry_id);

    $pdfAuthor = 'X-Sieben Wirtschaftstraining GmbH';

    // Hilfsfunktionen zur sauberen Bereinigung von Entities und Tags
    $clean_text = function ($val) {
        $decoded = html_entity_decode($val ?? '', ENT_QUOTES, 'UTF-8');
        $decoded = html_entity_decode($decoded, ENT_QUOTES, 'UTF-8');
        return trim(strip_tags($decoded));
    };

    $clean_inline_html = function ($val) {
        $val = preg_replace('/^\s*<p[^>]*>/iu', '', $val ?? '');
        $val = preg_replace('/<\/p>\s*$/iu', '', $val);
        return trim($val);
    };

    // Daten aus Model
    $clean_course_title = $clean_text($course->title);
    $safe_title         = (function_exists('mb_substr') ? mb_substr(preg_replace('/[^a-zA-Z0-9_\-äöüÄÖÜß]/u', '-', (string)($course->titel_short ?: ($course->title ?: 'Rechnung'))), 0, 50) : substr(preg_replace('/[^a-zA-Z0-9_\-]/', '-', (string)($course->titel_short ?: 'Rechnung')), 0, 50));
    $token              = function_exists('crm_generate_pdf_token') ? crm_generate_pdf_token($entry_id, 'hn') : '';
    $pdf_name           = "HN_" . ($entry_id ? $entry_id . '-' : '') . $course_id . "_" . ($token ? $token . '_' : '') . $safe_title . "_" . sanitize_file_name($course->vorname ?: 'Kunde') . "_" . sanitize_file_name($course->nachname ?: 'Rechnung') . ".pdf";

    // Rechnungsdaten
    $invoice_num  = 'HN_' . ($entry_id ? $entry_id . '-' : '') . $course_id;
    $invoice_date = date('d.m.Y');
    $due_date     = !empty($course->expire) ? $course->expire : date('d.m.Y', strtotime('+14 days'));

    // Kundendaten
    $customer_title_full = trim($course->titel . ' ' . $course->vorname . ' ' . $course->nachname);
    $customer_address    = trim(($course->street ?? '') . ' ' . ($course->house_number ?? ''));
    $customer_city       = trim(($course->zip_code ?? '') . ' ' . ($course->city ?? ''));

    // Preis- und Mengenberechnung
    $netto_kurs  = CRM_Pdf_Presenter::parse_price_float($course->preis_netto ?? 0);
    if ($netto_kurs == 0.0 && !empty($course->kosten)) {
        $netto_kurs = CRM_Pdf_Presenter::parse_price_float($course->kosten);
    }
    $ust_satz    = 20.00;
    $ust_kurs    = round(($netto_kurs / 100) * $ust_satz, 2);
    $brutto_kurs = round($netto_kurs + $ust_kurs, 2);

    $total_netto  = $netto_kurs;
    $total_ust    = $ust_kurs;
    $total_brutto = $brutto_kurs;

    $le_count  = !empty($course->anzahl_le) ? (int)$course->anzahl_le : 1;
    $single_le = $le_count > 0 ? round($netto_kurs / $le_count, 2) : $netto_kurs;

    // Zertifizierungen hinzurechnen falls vorhanden
    $cert_rows = '';
    $certifications_data = method_exists($course, 'get_certifications_from_form_field') ? $course->get_certifications_from_form_field() : [];
    if (!empty($certifications_data) && is_array($certifications_data)) {
        foreach ($certifications_data as $cert) {
            $c_name  = htmlspecialchars($clean_text($cert['name']));
            $c_price = CRM_Pdf_Presenter::parse_price_float($cert['price'] ?? 0);
            $pct_raw = rtrim((string)($cert['percentage'] ?? '20'), '%');
            $c_ust_satz = ($pct_raw === 'N/A' || $pct_raw === '' || $pct_raw === null) ? 20.00 : CRM_Pdf_Presenter::parse_price_float($pct_raw);

            $c_ust    = round(($c_price / (100 + $c_ust_satz)) * $c_ust_satz, 2);
            $c_netto  = round($c_price - $c_ust, 2);
            $c_brutto = round($c_netto + $c_ust, 2);

            $total_netto  += $c_netto;
            $total_ust    += $c_ust;

            $cert_rows .= '
            <tr>
                <td style="border-bottom: 1px solid #e2e8f0; padding: 5px 6px; font-size: 9pt;">' . $c_name . '</td>
                <td style="border-bottom: 1px solid #e2e8f0; text-align: center; padding: 5px 6px; font-size: 9pt;">1</td>
                <td style="border-bottom: 1px solid #e2e8f0; text-align: right; padding: 5px 6px; font-size: 9pt;">' . number_format($c_netto, 2, ',', '.') . ' €</td>
                <td style="border-bottom: 1px solid #e2e8f0; text-align: right; padding: 5px 6px; font-size: 9pt;">' . number_format($c_netto, 2, ',', '.') . ' €</td>
            </tr>';
        }
    }

    $total_netto  = round($total_netto, 2);
    $total_ust    = round($total_ust, 2);
    $total_brutto = round($total_netto + $total_ust, 2);

    // Logo ermitteln (mit URL-Fallback für Live-Server)
    $logo_src  = crm_resolve_asset_path('xsieben_logo.png');
    $logo_html = '<img src="' . esc_attr($logo_src) . '" width="170">';
    if (!empty($course->xsieben_logo)) {
        $logo_html = $course->xsieben_logo;
    }

    // Standard-Texte aus CRM Settings mit Fallbacks
    $hn_title_val      = $clean_text($course->get_crm_field_with_default('Honorarnote - Titel', 'Honorarnote'));
    $hn_einleitung_val = $clean_inline_html($course->get_crm_field_with_default('Honorarnote - Einleitung', 'Hiermit stellen wir Ihnen folgende Leistungen in Rechnung:'));
    $hn_zahlung_raw    = $course->get_crm_field_with_default('Honorarnote - Zahlungsanweisung', 'Bitte überweisen Sie den Betrag bis zum {expire} auf das Konto von X SIEBEN Wirtschaftstraining GmbH.<br>IBAN: AT29 3293 7001 0012 5260 | BIC: RLNWATWWWRN');
    $hn_zahlung_raw    = str_replace('[Datum]', $due_date, $hn_zahlung_raw);

    require_once __DIR__ . '/elements/invoice-elements.php';

    // Standard HTML Subsections
    $title_header_subs = CRM_Pdf_Invoice_Elements::get_titel_subs($hn_title_val, $invoice_num, $invoice_date);

    $default_footer_html = CRM_Pdf_Invoice_Elements::render_fusszeile(
        $course->company_name,
        $course->company_management,
        $course->company_court,
        $course->company_fn,
        $course->company_uid,
        $course->location_wien,
        $course->company_address,
        $course->company_phone,
        $course->company_email
    );

    $subsections_generators = [
        'titel_header' => $title_header_subs,
        'kopfzeile'    => $title_header_subs,

        'empfaenger' => CRM_Pdf_Invoice_Elements::get_empfaenger_subs(
            $course->format_postal_address('A', true),
            $course->svr,
            $due_date,
            $clean_course_title
        ),

        'einleitung' => CRM_Pdf_Invoice_Elements::get_einleitung_subs($hn_einleitung_val),

        'positionen' => CRM_Pdf_Invoice_Elements::get_positionen_subs(
            $clean_course_title,
            $course->start_datum,
            $course->end_datum,
            $le_count,
            $single_le,
            $netto_kurs,
            $cert_rows,
            $total_netto,
            $total_ust,
            $total_brutto
        ),

        'zahlung' => CRM_Pdf_Invoice_Elements::get_zahlung_subs($due_date, $course->company_name),

        'signatur' => [
            'aussteller_info' => $default_footer_html,
        ],
        'fusszeile' => [
            'aussteller_info' => $default_footer_html,
        ],
    ];

    $flattened_sub_generators = [];
    foreach ($subsections_generators as $sec_k => $subs) {
        if (is_array($subs)) {
            foreach ($subs as $sub_k => $sub_html) {
                $flattened_sub_generators[$sub_k] = $sub_html;
            }
        }
    }

    // Briefkopf & Firmen-Kopfzeile
    $html_header = CRM_Pdf_Invoice_Elements::render_header(
        $logo_html,
        $course->company_name,
        $course->location_wien,
        $course->company_address,
        $course->company_email,
        $course->company_website
    );

    // Holen der geordneten Abschnitte
    if (is_array($custom_sections) && !empty($custom_sections) && is_array(reset($custom_sections)) && isset(reset($custom_sections)['key'])) {
        $all_sections = $custom_sections;
    } else {
        $all_sections = crm_get_pdf_section_order('invoice', $entry_id);
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

    $body_html = '';
    $footer_html = '';
    $footer_enabled = true;

    foreach ($all_sections as $sec) {
        if (empty($sec['enabled'])) {
            if (in_array($sec['key'], ['signatur', 'fusszeile', 'footer'], true)) {
                $footer_enabled = false;
            }
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

        // Signatur / Fußzeile wird im TCPDF Footer fixiert, nicht im Fließtext
        if (in_array($sec_key, ['signatur', 'fusszeile', 'footer'], true)) {
            if (!empty($sec['subsections'])) {
                $sub_footer_html = '';
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    if (!empty($sub['content'])) {
                        if (strpos($sub['content'], '{standard}') !== false) {
                            $sub_footer_html .= str_replace('{standard}', $default_footer_html, $sub['content']);
                        } else {
                            $sub_footer_html .= $sub['content'];
                        }
                    } else {
                        $sub_footer_html .= $default_footer_html;
                    }
                }
                $footer_html = !empty($sub_footer_html) ? crm_replace_pdf_placeholders($sub_footer_html, $course) : $default_footer_html;
            } elseif (!empty($sec['content'])) {
                if (strpos($sec['content'], '{standard}') !== false) {
                    $footer_html = crm_replace_pdf_placeholders(str_replace('{standard}', $default_footer_html, $sec['content']), $course);
                } else {
                    $footer_html = crm_replace_pdf_placeholders($sec['content'], $course);
                }
            } else {
                $footer_html = $default_footer_html;
            }
            continue; // Nicht in $body_html einfügen!
        }

        if ($is_custom) {
            if (!empty($sec['title'])) {
                $sec_html .= '<div style="font-size:11pt; font-weight:bold; color:#007C90; margin-top:8px; margin-bottom:4px;">' . esc_html($sec['title']) . '</div>';
            }
            if (!empty($sec['content'])) {
                $sec_html .= '<div style="font-size:9pt; line-height:1.4;">' . crm_replace_pdf_placeholders($sec['content'], $course) . '</div>';
            }
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (!empty($sub['enabled']) && !empty($sub['content'])) {
                        $sub_sp_top = isset($sub['spacing_top']) && is_numeric($sub['spacing_top']) ? floatval($sub['spacing_top']) : 0.0;
                        $sub_sp_bottom = isset($sub['spacing_bottom']) && is_numeric($sub['spacing_bottom']) ? floatval($sub['spacing_bottom']) : 0.0;
                        $sub_prefix = ($sub_sp_top > 0 && function_exists('crm_get_pdf_spacing_html')) ? crm_get_pdf_spacing_html($sub_sp_top) : '';
                        $sub_suffix = ($sub_sp_bottom > 0 && function_exists('crm_get_pdf_spacing_html')) ? crm_get_pdf_spacing_html($sub_sp_bottom) : '';
                        $sec_html .= $sub_prefix . '<div style="font-size:9pt; line-height:1.4; margin-top:4px;">' . crm_replace_pdf_placeholders($sub['content'], $course) . '</div>' . $sub_suffix;
                    }
                }
            }
        } else {
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) {
                        continue;
                    }
                    $sub_key = $sub['key'];
                    $sub_sp_top = isset($sub['spacing_top']) && is_numeric($sub['spacing_top']) ? floatval($sub['spacing_top']) : 0.0;
                    $sub_sp_bottom = isset($sub['spacing_bottom']) && is_numeric($sub['spacing_bottom']) ? floatval($sub['spacing_bottom']) : 0.0;
                    $sub_prefix = ($sub_sp_top > 0 && function_exists('crm_get_pdf_spacing_html')) ? crm_get_pdf_spacing_html($sub_sp_top) : '';
                    $sub_suffix = ($sub_sp_bottom > 0 && function_exists('crm_get_pdf_spacing_html')) ? crm_get_pdf_spacing_html($sub_sp_bottom) : '';
                    $sub_html = '';

                    if (!empty($sub['is_custom'])) {
                        if (!empty($sub['title'])) {
                            $sub_html .= '<div style="font-size:9.5pt; font-weight:bold; color:#0f172a; margin-top:6px; margin-bottom:2px;">' . esc_html($sub['title']) . '</div>';
                        }
                        if (!empty($sub['content'])) {
                            $sub_html .= '<div style="font-size:9pt; line-height:1.4;">' . crm_replace_pdf_placeholders($sub['content'], $course) . '</div>';
                        }
                    } elseif (isset($subsections_generators[$sec_key][$sub_key]) || isset($flattened_sub_generators[$sub_key])) {
                        $default_sub = $subsections_generators[$sec_key][$sub_key] ?? $flattened_sub_generators[$sub_key];
                        if (!empty($sub['content'])) {
                            if (strpos($sub['content'], '{standard}') !== false) {
                                $custom_sub = str_replace('{standard}', $default_sub, $sub['content']);
                            } else {
                                $custom_sub = $sub['content'];
                            }
                            $sub_html .= crm_replace_pdf_placeholders($custom_sub, $course);
                        } else {
                            $sub_html .= $default_sub;
                        }
                    }

                    if (!empty($sub_html)) {
                        $sec_html .= $sub_prefix . $sub_html . $sub_suffix;
                    }
                }
            } else {
                if (isset($subsections_generators[$sec_key])) {
                    $sec_html .= implode('', $subsections_generators[$sec_key]);
                }
            }
        }

        if (!empty($sec_html)) {
            $body_html .= $sec_prefix . $sec_html . $sec_suffix;
        }
    }

    // Fallback wenn Footer-Sektion vorhanden & aktiviert, aber noch kein HTML erzeugt wurde
    if ($footer_enabled && empty($footer_html)) {
        $footer_html = $default_footer_html;
    }

    $full_html = $html_header . $body_html;

    // TCPDF Klasse
    if (!class_exists('MYPDFA_Invoice')) {
        class MYPDFA_Invoice extends TCPDF
        {
            public $footer_html = '';

            public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8', $diskcache = false, $pdfa = false)
            {
                parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
                $this->tcpdflink = false;
            }

            public function Header()
            {
                // Deaktiviert für präzises internes Layout
            }

            public function Footer()
            {
                if (!empty($this->footer_html)) {
                    $this->SetY(-24);
                    $this->writeHTMLCell(0, 0, 15, '', $this->footer_html, 0, 0, false, true, 'L', true);
                }
            }
        }
    }

    $pdf = new MYPDFA_Invoice(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($pdfAuthor);
    $pdf->SetTitle('Honorarnote ' . $invoice_num);
    $pdf->SetSubject('Honorarnote ' . $clean_course_title);

    $pdf->footer_html = $footer_html;
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(!empty($footer_html));

    // Margins & 1-Seiten-Garantie
    $pdf->SetMargins(15, 10, 15);
    $pdf->SetFooterMargin(24);
    $pdf->SetAutoPageBreak(false);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->SetFont('dejavusans', '', 9.5);
    $pdf->SetCellPadding(0);

    $pdf->AddPage();
    $pdf->writeHTML($full_html, true, false, true, false, '');

    // Speicherordner vorbereiten
    $save_dir = function_exists('crm_get_pdf_storage_dir') ? crm_get_pdf_storage_dir() : (get_template_directory() . '/angebote/');
    if (!is_dir($save_dir)) {
        wp_mkdir_p($save_dir);
    }
    // Ältere Honorarnoten für diesen Vorgang bereinigen
    $existing_old_hn = glob($save_dir . 'HN_' . ($entry_id ? $entry_id . '-' : '') . $course_id . '_*.pdf');
    if (!empty($existing_old_hn)) {
        foreach ($existing_old_hn as $old_f) {
            if (basename($old_f) !== $pdf_name && file_exists($old_f)) {
                @unlink($old_f);
            }
        }
    }
    $save_path = $save_dir . $pdf_name;
    $save_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $save_path);

    $pdf->Output($save_path, 'F');

    $storage_url = function_exists('crm_get_pdf_storage_url') ? crm_get_pdf_storage_url() : (get_template_directory_uri() . '/angebote/');
    $pdf_url = $storage_url . rawurlencode($pdf_name);

    if ($output_to_browser) {
        x_sieben_pdf_preview($pdf_url, $course_id, $entry_id, 'invoice');
    } else {
        return $pdf_url;
    }
}

