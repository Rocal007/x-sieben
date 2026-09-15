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
    $netto_kurs  = !empty($course->preis_netto) ? (float)str_replace(['.', ','], ['', '.'], (string)$course->preis_netto) : 0.0;
    if ($netto_kurs == 0.0 && !empty($course->kosten)) {
        $netto_kurs = (float)$course->kosten;
    }
    $ust_satz    = 20.00;
    $ust_kurs    = ($netto_kurs / 100) * $ust_satz;
    $brutto_kurs = $netto_kurs + $ust_kurs;

    $total_netto  = $netto_kurs;
    $total_ust    = $ust_kurs;
    $total_brutto = $brutto_kurs;

    $le_count  = !empty($course->anzahl_le) ? (int)$course->anzahl_le : 1;
    $single_le = $le_count > 0 ? ($netto_kurs / $le_count) : $netto_kurs;

    // Zertifizierungen hinzurechnen falls vorhanden
    $cert_rows = '';
    $certifications_data = method_exists($course, 'get_certifications_from_form_field') ? $course->get_certifications_from_form_field() : [];
    if (!empty($certifications_data) && is_array($certifications_data)) {
        foreach ($certifications_data as $cert) {
            $c_name  = htmlspecialchars($clean_text($cert['name']));
            $c_price = (float)str_replace(['.', ','], ['', '.'], $cert['price'] ?? 0);
            $pct_raw = rtrim($cert['percentage'] ?? '20', '%');
            $c_ust_satz = ($pct_raw === 'N/A' || empty($pct_raw)) ? 20.00 : (float)$pct_raw;

            $c_ust    = ($c_price / (100 + $c_ust_satz)) * $c_ust_satz;
            $c_netto  = $c_price - $c_ust;
            $c_brutto = $c_price;

            $total_netto  += $c_netto;
            $total_ust    += $c_ust;
            $total_brutto += $c_brutto;

            $cert_rows .= '
            <tr>
                <td style="border-bottom: 1px solid #e2e8f0; padding: 5px 6px; font-size: 9pt;">' . $c_name . '</td>
                <td style="border-bottom: 1px solid #e2e8f0; text-align: center; padding: 5px 6px; font-size: 9pt;">1</td>
                <td style="border-bottom: 1px solid #e2e8f0; text-align: right; padding: 5px 6px; font-size: 9pt;">' . number_format($c_netto, 2, ',', '.') . ' €</td>
                <td style="border-bottom: 1px solid #e2e8f0; text-align: right; padding: 5px 6px; font-size: 9pt;">' . number_format($c_netto, 2, ',', '.') . ' €</td>
            </tr>';
        }
    }

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

    // Standard HTML Subsections
    $title_header_subs = [
        'rechnung_titel' => '<table cellspacing="0" cellpadding="0" style="width: 100%;">
            <tr>
                <td style="font-size: 15pt; font-weight: bold; color: #007C90; text-align: center;">
                    ' . htmlspecialchars($hn_title_val) . '
                </td>
            </tr>
        </table>
        <div style="font-size: 4pt">&nbsp;</div>',

        'nummer_datum' => '<table cellspacing="0" cellpadding="2" style="width: 100%; font-size: 8.5pt; border-bottom: 1px solid #007C90; padding-bottom: 3px;">
            <tr>
                <td style="width: 50%; color: #334155;"><strong>Rechnungsnummer:</strong> ' . htmlspecialchars($invoice_num) . '</td>
                <td style="width: 50%; text-align: right; color: #334155;"><strong>Datum:</strong> ' . htmlspecialchars($invoice_date) . '</td>
            </tr>
        </table>
        <div style="font-size: 6pt">&nbsp;</div>',
    ];

    $default_footer_html = '<table cellpadding="0" cellspacing="0" style="width: 100%; border-top: 1px solid #cbd5e1; padding-top: 5px; font-size: 7.5pt; color: #64748b; line-height: 1.35;">
        <tr>
            <td style="width: 55%;">
                <strong>' . htmlspecialchars($course->company_name) . '</strong><br>
                Geschäftsführung: ' . htmlspecialchars($course->company_management) . '<br>
                Firmenbuchgericht: ' . htmlspecialchars($course->company_court) . ' | ' . htmlspecialchars($course->company_fn) . ' | UID: ' . htmlspecialchars($course->company_uid) . '
            </td>
            <td style="width: 45%; text-align: right;">
                <strong>Seminarzentrum:</strong> ' . htmlspecialchars($course->location_wien) . '<br>
                <strong>Zentrale:</strong> ' . htmlspecialchars($course->company_address) . '<br>
                Tel: ' . htmlspecialchars($course->company_phone) . ' | ' . htmlspecialchars($course->company_email) . '
            </td>
        </tr>
    </table>';

    $subsections_generators = [
        'titel_header' => $title_header_subs,
        'kopfzeile'    => $title_header_subs,

        'empfaenger' => [
            'kundendaten' => '<table cellspacing="0" cellpadding="4" style="width: 100%; border: 1px solid #e2e8f0; background-color: #f8fafc; font-size: 9pt;">
                <tr>
                    <td style="width: 62%; vertical-align: top; font-size: 9pt; line-height: 13pt;">
                        <span style="color: #007C90; font-size: 7.5pt; font-weight: bold; text-transform: uppercase;">Rechnungsempfänger:</span><br>
                        ' . $course->format_postal_address('A', true) . '
                    </td>
                    <td style="width: 38%; vertical-align: top; text-align: right; font-size: 8.5pt; color: #334155; line-height: 1.4;">
                        ' . (!empty($course->svr) ? '<strong>SV-Nummer:</strong> ' . htmlspecialchars((string)$course->svr) . '<br>' : '') . '
                        <strong>Zahlungsziel:</strong> ' . htmlspecialchars($due_date) . '
                    </td>
                </tr>
            </table>',

            'veranstaltung_ref' => '<div style="font-size: 4pt">&nbsp;</div>
            <div style="font-size: 9pt; color: #334155;">
                <strong>Veranstaltung:</strong> ' . htmlspecialchars($clean_course_title) . '
            </div>
            <div style="font-size: 6pt">&nbsp;</div>',
        ],

        'einleitung' => [
            'einleitungstext' => '<div style="font-size: 9.5pt; color: #0f172a; margin-bottom: 4px;">
                ' . $hn_einleitung_val . '
            </div>',
        ],

        'positionen' => [
            'positionen_tabelle' => '<table cellpadding="5" cellspacing="0" border="0" width="100%" style="border-collapse: collapse; font-size: 9pt;">
                <thead>
                    <tr style="background-color: #007C90; color: #ffffff;">
                        <th style="width: 55%; text-align: left; font-weight: bold; padding: 5px 6px;">Leistung / Kurs</th>
                        <th style="width: 12%; text-align: center; font-weight: bold; padding: 5px 4px;">Menge</th>
                        <th style="width: 16%; text-align: right; font-weight: bold; padding: 5px 6px;">Einzelpreis</th>
                        <th style="width: 17%; text-align: right; font-weight: bold; padding: 5px 6px;">Gesamt Netto</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="border-bottom: 1px solid #cbd5e1; padding: 6px;">
                            <strong>' . htmlspecialchars($clean_course_title) . '</strong><br>
                            <span style="font-size: 8pt; color: #64748b;">Leistungszeitraum: ' . htmlspecialchars($course->start_datum) . ' bis ' . htmlspecialchars($course->end_datum) . '</span>
                        </td>
                        <td style="border-bottom: 1px solid #cbd5e1; text-align: center; padding: 6px;">' . $le_count . ' LE</td>
                        <td style="border-bottom: 1px solid #cbd5e1; text-align: right; padding: 6px;">' . number_format($single_le, 2, ',', '.') . ' €</td>
                        <td style="border-bottom: 1px solid #cbd5e1; text-align: right; padding: 6px;">' . number_format($netto_kurs, 2, ',', '.') . ' €</td>
                    </tr>
                    ' . $cert_rows . '
                </tbody>
            </table>',

            'gesamtbetrag' => '<table cellpadding="2" cellspacing="0" border="0" width="100%" style="font-size: 9pt; margin-top: 3px;">
                <tr>
                    <td style="width: 72%; text-align: right; color: #64748b;">Summe Netto:</td>
                    <td style="width: 28%; text-align: right; font-weight: bold; color: #0f172a;">' . number_format($total_netto, 2, ',', '.') . ' €</td>
                </tr>
                <tr>
                    <td style="width: 72%; text-align: right; color: #64748b; font-size: 8.5pt;">+ 20,00% USt:</td>
                    <td style="width: 28%; text-align: right; color: #64748b; font-size: 8.5pt;">' . number_format($total_ust, 2, ',', '.') . ' €</td>
                </tr>
                <tr style="background-color: #f1f5f9;">
                    <td style="width: 72%; text-align: right; font-size: 10.5pt; font-weight: bold; color: #007C90; border-top: 1.5px solid #007C90; border-bottom: 1.5px solid #007C90; padding: 4px;">Gesamtbetrag (Brutto):</td>
                    <td style="width: 28%; text-align: right; font-size: 10.5pt; font-weight: bold; color: #007C90; border-top: 1.5px solid #007C90; border-bottom: 1.5px solid #007C90; padding: 4px;">' . number_format($total_brutto, 2, ',', '.') . ' €</td>
                </tr>
            </table>
            <div style="font-size: 6pt">&nbsp;</div>',
        ],

        'zahlung' => [
            'zahlungsziel' => '<div style="font-size: 9pt; line-height: 1.4; color: #334155;">
                Bitte überweisen Sie den Betrag bis zum <strong>' . htmlspecialchars($due_date) . '</strong> auf das Konto von ' . htmlspecialchars($course->company_name) . '.
            </div>',

            'bankverbindung' => '<div style="font-size: 9pt; font-weight: bold; color: #007C90; margin-top: 3px;">
                IBAN: AT29 3293 7001 0012 5260 | BIC: RLNWATWWWRN (Raiffeisenlandesbank NÖ-Wien)
            </div>
            <div style="font-size: 8pt">&nbsp;</div>',
        ],

        'signatur' => [
            'aussteller_info' => $default_footer_html,
        ],
        'fusszeile' => [
            'aussteller_info' => $default_footer_html,
        ],
    ];

    // Briefkopf & Firmen-Kopfzeile
    $html_header = '
    <table cellpadding="0" cellspacing="0" style="width: 100%; border-bottom: 2px solid #007C90; padding-bottom: 6px; margin-bottom: 6px;">
        <tr>
            <td style="width: 55%; vertical-align: middle;">
                ' . $logo_html . '
            </td>
            <td style="width: 45%; vertical-align: middle; text-align: right; font-size: 7.5pt; color: #64748b; line-height: 1.3;">
                <strong>' . htmlspecialchars($course->company_name) . '</strong><br>
                ' . htmlspecialchars($course->location_wien) . '<br>
                Zentrale: ' . htmlspecialchars($course->company_address) . '<br>
                ' . htmlspecialchars($course->company_email) . ' | ' . htmlspecialchars($course->company_website) . '
            </td>
        </tr>
    </table>
    <div style="font-size: 4pt">&nbsp;</div>';

    // Holen der geordneten Abschnitte
    $all_sections = crm_get_pdf_section_order('invoice', $entry_id);

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

        $sec_key   = $sec['key'];
        $is_custom = !empty($sec['is_custom']);

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
                $body_html .= '<div style="font-size:11pt; font-weight:bold; color:#007C90; margin-top:8px; margin-bottom:4px;">' . esc_html($sec['title']) . '</div>';
            }
            if (!empty($sec['content'])) {
                $body_html .= '<div style="font-size:9pt; line-height:1.4;">' . crm_replace_pdf_placeholders($sec['content'], $course) . '</div>';
            }
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (!empty($sub['enabled']) && !empty($sub['content'])) {
                        $body_html .= '<div style="font-size:9pt; line-height:1.4; margin-top:4px;">' . crm_replace_pdf_placeholders($sub['content'], $course) . '</div>';
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
                    if (!empty($sub['is_custom'])) {
                        if (!empty($sub['title'])) {
                            $body_html .= '<div style="font-size:9.5pt; font-weight:bold; color:#0f172a; margin-top:6px; margin-bottom:2px;">' . esc_html($sub['title']) . '</div>';
                        }
                        if (!empty($sub['content'])) {
                            $body_html .= '<div style="font-size:9pt; line-height:1.4;">' . crm_replace_pdf_placeholders($sub['content'], $course) . '</div>';
                        }
                    } elseif (isset($subsections_generators[$sec_key][$sub_key])) {
                        $default_sub = $subsections_generators[$sec_key][$sub_key];
                        if (!empty($sub['content'])) {
                            if (strpos($sub['content'], '{standard}') !== false) {
                                $custom_sub = str_replace('{standard}', $default_sub, $sub['content']);
                            } else {
                                $custom_sub = $sub['content'];
                            }
                            $body_html .= crm_replace_pdf_placeholders($custom_sub, $course);
                        } else {
                            $body_html .= $default_sub;
                        }
                    }
                }
            } else {
                if (isset($subsections_generators[$sec_key])) {
                    $body_html .= implode('', $subsections_generators[$sec_key]);
                }
            }
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

