<?php
/**
 * X-SIEBEN CRM - Kursanmeldebestätigung (AB) PDF Generator
 *
 * Generiert die offizielle Anmeldebestätigung für TeilnehmerInnen via TCPDF.
 * Modular aufgebaut mit Abschnitten (Drag & Drop), anpassbaren Unterabschnitten,
 * Standard-Logo-Unterstützung und voller Editor-Integration.
 * Entspricht exakt der Vorlage Anmeldebestätigung.pdf von Hannes Gajo.
 *
 * @package X_SIEBEN_CRM
 * @version 2.19.3
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/elements/ab-elements.php';

/**
 * Optische Harmonisierung & Standardkomponenten (v2.18.93 / v2.19.3):
 * Eckdaten an einheitlicher linker Grundlinie
 * style="color: #007c8c; font-weight: bold;"
 * height="74" (Stampiglie)
 */
function xsieben_anmeldebestaetigung_pdf($entry_id, $course_id, $output_to_browser = true, $custom_sections = null)
{
    // Model laden
    $course = new CRM_Model($course_id, $entry_id);

    $clean_text = function ($val) {
        $decoded = html_entity_decode($val ?? '', ENT_QUOTES, 'UTF-8');
        return trim(strip_tags($decoded));
    };

    $safe_vorname  = sanitize_file_name($course->vorname ?: 'Kunde');
    $safe_nachname = sanitize_file_name($course->nachname ?: 'Teilnehmer');
    $safe_title    = (function_exists('mb_substr') ? mb_substr(sanitize_file_name($course->titel_short ?: ($course->title ?: 'Kurs')), 0, 50) : substr(sanitize_file_name($course->titel_short ?: 'Kurs'), 0, 50));
    $token         = function_exists('crm_generate_pdf_token') ? crm_generate_pdf_token($entry_id, 'ab') : '';
    $pdf_name      = "Anmeldebestaetigung_" . $safe_vorname . "_" . $safe_nachname . "_" . ($token ? $token . '_' : '') . $safe_title . ".pdf";

    // Teilnehmerdaten
    $anrede_prefix = ($course->anrede === 'Frau') ? 'Frau' : (($course->anrede === 'Herr') ? 'Herrn' : 'Frau/Herrn');
    $salutation_greeting = ($course->anrede === 'Frau') ? ('Sehr geehrte Frau ' . $course->nachname) : (($course->anrede === 'Herr') ? ('Sehr geehrter Herr ' . $course->nachname) : 'Sehr geehrte Damen und Herren');
    $tn_name       = trim(($course->vorname ?? '') . ' ' . ($course->nachname ?? ''));
    $tn_strasse    = $course->street ?: '';
    $tn_plz_ort    = trim(($course->zip_code ?: ($course->postcode ?? '')) . ' ' . ($course->city ?: ''));

    // Referenzdaten
    $entry_date_str = date('d.m.Y');
    if ($entry_id && function_exists('wpforms')) {
        $entry_obj = wpforms()->entry->get($entry_id);
        if ($entry_obj && !empty($entry_obj->date)) {
            $entry_date_str = date('d.m.Y', strtotime($entry_obj->date));
        }
    }
    $current_date_str = date('d.m.Y');

    // Kursdaten
    $clean_course_title = html_entity_decode(html_entity_decode($course->title ?? '', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
    $start_datum = $course->start_datum ?: date('d.m.Y');
    $end_datum   = $course->end_datum ?: '';

    // Start-Wochentag ermitteln
    $start_wochentag = 'Dienstag';
    if (!empty($start_datum)) {
        $ts = strtotime(str_replace('.', '-', $start_datum));
        if ($ts) {
            $wt_map = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
            $start_wochentag = $wt_map[date('w', $ts)] ?? 'Dienstag';
        }
    }

    // Kurszeiten
    $kurszeit_info = 'ab 09.00 bis 17.00';
    if (!empty($course->kurszeiten) && is_array($course->kurszeiten)) {
        foreach ($course->kurszeiten as $kz) {
            if (!empty($kz)) {
                $kurszeit_info = 'ab ' . $kz;
                break;
            }
        }
    }
    $beginn_text = $start_wochentag . ', ' . $start_datum . ', ' . $kurszeit_info . ($end_datum ? ' (Dauer bis zum ' . $end_datum . ')' : '');

    // Veranstaltungsort
    $ort_text = !empty($course->durchfuehrung) ? $course->durchfuehrung : 'Live-Online - Zoom.';
    if (stripos($ort_text, 'zoom') === false && (stripos($ort_text, 'live') !== false || stripos($ort_text, 'online') !== false)) {
        $ort_text = 'Live-Online - Zoom.';
    }

    // Kurs-Kategorie (Lehrgang vs. Seminar)
    $is_seminar = (stripos($clean_course_title, 'Seminar') !== false || stripos($clean_course_title, 'Workshop') !== false);
    $kategorie_label = $is_seminar ? 'Seminar' : 'Lehrgang';

    // Standard-Logo: Primär aus den CRM-Einstellungen / Course-Objekt, Fallback auf xsieben_logo.png
    $logo_x7 = crm_resolve_asset_path('xsieben_logo.png');
    if (!empty($course->company_logo_url)) {
        $logo_x7 = function_exists('crm_resolve_asset_path') ? crm_resolve_asset_path($course->company_logo_url) : $course->company_logo_url;
    }

    // Weitere Standard-Assets
    $wba_logo   = crm_resolve_asset_path('wba-1.png');
    $stempel    = crm_resolve_asset_path('stempel.png');
    $oecert     = crm_resolve_asset_path('oecert.png');
    $tuef       = crm_resolve_asset_path('tuef.png');
    $systemcert = crm_resolve_asset_path('system-1.png');
    $pma        = crm_resolve_asset_path('PMA-1.png');

    // TCPDF Setup
    if (!class_exists('TCPDF')) {
        $tcpdf_path = get_template_directory() . '/tcbpdf/tcpdf.php';
        if (file_exists($tcpdf_path)) {
            require_once $tcpdf_path;
        }
    }

    if (!class_exists('MYPDFA_Anmeldung')) {
        class MYPDFA_Anmeldung extends TCPDF
        {
            public $footer_logos_html = '';
            public $footer_company_text = '';

            public function Header()
            {
                // Leer - Header wird im Seitenbody bzw. per Abschnitt gerendert
            }

            public function Footer()
            {
                $this->SetY(-36);
                $this->SetFont('dejavusans', '', 7);
                $this->writeHTML($this->footer_logos_html . $this->footer_company_text, true, false, true, false, '');
            }
        }
    }

    $pdf = new MYPDFA_Anmeldung(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('X-SIEBEN Wirtschaftstraining GmbH');
    $pdf->SetTitle('Anmeldebestätigung - ' . $clean_course_title);
    $pdf->SetSubject('Anmeldebestätigung');

    // Margins & Fonts
    $pdf->SetMargins(20, 15, 20);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(36);
    $pdf->SetAutoPageBreak(true, 38);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->SetFont('dejavusans', '', 10);

    // Footer HTML für alle Seiten
    $footer_logos = '
    <div style="border-top: 1px solid #cbd5e1; padding-top: 4px; margin-bottom: 4px;">
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td width="25%" align="left"><img src="' . esc_attr($oecert) . '" height="18" /></td>
                <td width="25%" align="center"><img src="' . esc_attr($tuef) . '" height="18" /></td>
                <td width="25%" align="center"><img src="' . esc_attr($systemcert) . '" height="18" /></td>
                <td width="25%" align="right"><img src="' . esc_attr($pma) . '" height="18" /></td>
            </tr>
        </table>
    </div>';

    $footer_company = '
    <div style="font-size: 6.8pt; color: #475569; line-height: 1.35; margin-top: 2px;">
        <strong>X SIEBEN Wirtschaftstraining GmbH</strong><br>
        Seminare . Lehrgänge . Kurse . Coaching, Headquarters: Kurzegasse 7, 2493 Lichtenwörth, Filialen in Wien, Mobil (+43) 0699/102 069 37, Telefon (+43) 02622/ 351 10, Fax-DW 14, office@x-sieben.at, www.x-sieben.at, Bankverbindung: Raiffeisenregionalbank Wr. Neustadt, IBAN AT29 3293 7001 0012 5260, BIC RLNWATWWWRN, Handelsgericht Wr. Neustadt, UID: ATU76624137
    </div>';

    $pdf->footer_logos_html = $footer_logos;
    $pdf->footer_company_text = $footer_company;

    // ==========================================
    // MODULARE ABSCHNITTE LADEN & ASSEMBELN
    // ==========================================
    if (is_array($custom_sections) && !empty($custom_sections) && is_array(reset($custom_sections)) && isset(reset($custom_sections)['key'])) {
        $all_sections = $custom_sections;
    } else {
        $all_sections = function_exists('crm_get_pdf_section_order') ? crm_get_pdf_section_order('ab', $entry_id) : [];
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

    // Standard-Generatoren für alle Unterabschnitte
    $ab_generators = CRM_Pdf_Ab_Elements::get_subsections_generators($course, [
        'entry_date_str'   => $entry_date_str,
        'current_date_str' => $current_date_str,
    ]);

    $global_spacing = function_exists('crm_get_pdf_elements_spacing') ? crm_get_pdf_elements_spacing() : ['spacing_top' => 0, 'spacing_bottom' => 0];

    // Falls keine Abschnitte definiert sind (Fallback)
    if (empty($all_sections)) {
        $defs = function_exists('crm_get_pdf_sections_definitions') ? crm_get_pdf_sections_definitions('ab') : [];
        foreach ($defs as $d_k => $d_v) {
            $subs = [];
            foreach (($d_v['subsections'] ?? []) as $sub_k => $sub_v) {
                if (!empty($sub_v['default'])) {
                    $subs[] = ['key' => $sub_k, 'enabled' => true, 'is_custom' => false, 'content' => '{standard}'];
                }
            }
            $all_sections[] = [
                'key'         => $d_k,
                'title'       => $d_v['title'] ?? $d_k,
                'enabled'     => true,
                'is_custom'   => false,
                'subsections' => $subs,
            ];
        }
    }

    // Render-Loop über alle aktivierten Abschnitte
    foreach ($all_sections as $sec) {
        if (empty($sec['enabled'])) {
            continue;
        }

        $sec_key   = $sec['key'] ?? '';
        $is_custom = !empty($sec['is_custom']);

        $sec_spacing = function_exists('crm_get_pdf_effective_spacing')
            ? crm_get_pdf_effective_spacing($sec, $global_spacing)
            : ['top' => 0, 'bottom' => 0];
        $sec_prefix  = function_exists('crm_get_pdf_spacing_html') ? crm_get_pdf_spacing_html($sec_spacing['top']) : '';
        $sec_suffix  = function_exists('crm_get_pdf_spacing_html') ? crm_get_pdf_spacing_html($sec_spacing['bottom']) : '';

        $page_html = '';

        if ($is_custom) {
            $page_html .= '<div style="margin-bottom:12px; font-size:10pt; line-height:1.6;">';
            if (!empty($sec['title'])) {
                $page_html .= '<strong>' . esc_html($sec['title']) . '</strong><br>';
            }
            if (!empty($sec['content'])) {
                $page_html .= function_exists('crm_replace_pdf_placeholders') ? crm_replace_pdf_placeholders($sec['content'], $course) : $sec['content'];
            }
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    $sub_sp_top = isset($sub['spacing_top']) && is_numeric($sub['spacing_top']) ? floatval($sub['spacing_top']) : 0.0;
                    $sub_sp_bottom = isset($sub['spacing_bottom']) && is_numeric($sub['spacing_bottom']) ? floatval($sub['spacing_bottom']) : 0.0;
                    $sub_prefix = ($sub_sp_top > 0 && function_exists('crm_get_pdf_spacing_html')) ? crm_get_pdf_spacing_html($sub_sp_top) : '';
                    $sub_suffix = ($sub_sp_bottom > 0 && function_exists('crm_get_pdf_spacing_html')) ? crm_get_pdf_spacing_html($sub_sp_bottom) : '';

                    $sub_html = '';
                    if (!empty($sub['title'])) {
                        $sub_html .= '<div style="font-size:10pt; font-weight:bold; margin-top:6px;">' . esc_html($sub['title']) . '</div>';
                    }
                    if (!empty($sub['content'])) {
                        $sub_html .= '<div style="margin-top:4px;">' . (function_exists('crm_replace_pdf_placeholders') ? crm_replace_pdf_placeholders($sub['content'], $course) : $sub['content']) . '</div>';
                    }
                    $page_html .= $sub_prefix . $sub_html . $sub_suffix;
                }
            }
            $page_html .= '</div>';
        } else {
            // Standardabschnitt: anschreiben oder agb_hinweis
            $sec_subs_gens = $ab_generators[$sec_key] ?? [];

            if (!empty($sec['subsections']) && is_array($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) {
                        continue;
                    }
                    $sk = $sub['key'] ?? '';
                    $sub_sp_top = isset($sub['spacing_top']) && is_numeric($sub['spacing_top']) ? floatval($sub['spacing_top']) : 0.0;
                    $sub_sp_bottom = isset($sub['spacing_bottom']) && is_numeric($sub['spacing_bottom']) ? floatval($sub['spacing_bottom']) : 0.0;
                    $sub_prefix = ($sub_sp_top > 0 && function_exists('crm_get_pdf_spacing_html')) ? crm_get_pdf_spacing_html($sub_sp_top) : '';
                    $sub_suffix = ($sub_sp_bottom > 0 && function_exists('crm_get_pdf_spacing_html')) ? crm_get_pdf_spacing_html($sub_sp_bottom) : '';

                    $def_sub = $sec_subs_gens[$sk] ?? '';
                    $sub_html = '';

                    if (!empty($sub['is_custom'])) {
                        if (!empty($sub['title'])) {
                            $sub_html .= '<div style="font-size:10pt; font-weight:bold; margin-top:6px;">' . esc_html($sub['title']) . '</div>';
                        }
                        if (!empty($sub['content'])) {
                            $sub_html .= '<div style="margin-top:4px;">' . (function_exists('crm_replace_pdf_placeholders') ? crm_replace_pdf_placeholders($sub['content'], $course) : $sub['content']) . '</div>';
                        }
                    } elseif (!empty($sub['content']) && $sub['content'] !== '{standard}') {
                        $custom = (strpos($sub['content'], '{standard}') !== false)
                            ? str_replace('{standard}', $def_sub, $sub['content'])
                            : $sub['content'];
                        $sub_html .= function_exists('crm_replace_pdf_placeholders') ? crm_replace_pdf_placeholders($custom, $course) : $custom;
                    } else {
                        $sub_html .= $def_sub;
                    }

                    if (!empty($sub_html)) {
                        $page_html .= $sub_prefix . $sub_html . $sub_suffix;
                    }
                }
            } else {
                // Keine Unterabschnitte definiert: Alle Standard-Generatoren dieses Abschnitts ausgeben
                foreach ($sec_subs_gens as $sk => $def_sub) {
                    if ($sk === 'bestaetigung') continue; // Redundantes kombiniertes Element auslassen
                    $page_html .= $def_sub;
                }
            }
        }

        if (!empty(trim(strip_tags($page_html, '<img>')))) {
            $pdf->AddPage();
            $pdf->writeHTML($sec_prefix . $page_html . $sec_suffix, true, false, true, false, '');
        }
    }

    // Speicherpfad & Rückgabe
    $save_dir = function_exists('crm_get_pdf_storage_dir') ? crm_get_pdf_storage_dir() : (get_template_directory() . '/angebote/');
    if (!file_exists($save_dir)) {
        wp_mkdir_p($save_dir);
    }
    $save_path = $save_dir . $pdf_name;
    $storage_url = function_exists('crm_get_pdf_storage_url') ? crm_get_pdf_storage_url() : (get_template_directory_uri() . '/angebote/');
    $pdf_url = $storage_url . rawurlencode($pdf_name);

    $pdf->Output($save_path, 'F');

    if ($output_to_browser) {
        if (function_exists('x_sieben_pdf_preview')) {
            x_sieben_pdf_preview($pdf_url, $course_id, $entry_id, 'anmeldebestaetigung');
        } else {
            $pdf->Output($pdf_name, 'I');
            exit;
        }
    } else {
        return $pdf_url;
    }
}

if (!function_exists('xsieben_ab_pdf')) {
    function xsieben_ab_pdf($entry_id, $course_id, $output_to_browser = true, $custom_sections = null)
    {
        return xsieben_anmeldebestaetigung_pdf($entry_id, $course_id, $output_to_browser, $custom_sections);
    }
}
