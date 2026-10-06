<?php
/**
 * X-SIEBEN CRM - Kursantrittsbestätigung / Antrittsmeldung PDF Generator
 *
 * Generiert die offizielle Antrittsmeldung für das AMS via TCPDF.
 * Modular aufgebaut mit Abschnitten (Drag & Drop), anpassbaren Unterabschnitten,
 * Standard-Logo-Unterstützung und voller Editor-Integration.
 * Entspricht exakt der Vorlage Kü_Antrittsmeldung.pdf von Hannes Gajo.
 *
 * @package X_SIEBEN_CRM
 * @version 2.19.5
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/elements/antritt-elements.php';

/**
 * Optische Harmonisierung & Standardkomponenten (v2.18.93 / v2.19.3):
 * Top Header: X-SIEBEN Logo (links) & AMS Logo (rechts)
 * Adresse des Kursinstituts:
 * Schulungsort:
 * UID des Kursinstituts:
 * height="74" (Stampiglie)
 */
function xsieben_antrittsbestaetigung_pdf($entry_id, $course_id, $output_to_browser = true, $custom_sections = null)
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
    $token         = function_exists('crm_generate_pdf_token') ? crm_generate_pdf_token($entry_id, 'antritt') : '';
    $pdf_name      = "Antrittsmeldung_" . $safe_vorname . "_" . $safe_nachname . "_" . ($token ? $token . '_' : '') . $safe_title . ".pdf";

    // Teilnehmerdaten
    $anrede_label = ($course->anrede === 'Frau') ? 'Frau' : (($course->anrede === 'Herr') ? 'Herr' : '');
    $tn_name      = trim($anrede_label . ' ' . ($course->vorname ?? '') . ' ' . ($course->nachname ?? ''));
    $tn_adresse   = trim(($course->street ?: '') . ', ' . ($course->zip_code ?: ($course->postcode ?? '')) . ' ' . ($course->city ?: ''));
    $tn_svr       = $course->svr ?: '';

    // Kursdaten
    $clean_course_title = html_entity_decode(html_entity_decode($course->title ?? '', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
    $start_datum = $course->start_datum ?: date('d.m.Y');
    $current_date_str = date('d.m.Y');

    // Schulungsort
    $schulungsort = 'Rochusgasse 6,<br>1030 Wien / Live Online';
    if (!empty($course->schulungsort)) {
        $schulungsort = nl2br(esc_html($course->schulungsort));
    } elseif (!empty($course->location_wien)) {
        $schulungsort = nl2br(esc_html($course->location_wien));
    }

    // Standard-Logo: Primär aus den CRM-Einstellungen / Course-Objekt, Fallback auf xsieben_logo.png
    $logo_x7  = crm_resolve_asset_path('xsieben_logo.png');
    if (!empty($course->company_logo_url)) {
        $logo_x7 = function_exists('crm_resolve_asset_path') ? crm_resolve_asset_path($course->company_logo_url) : $course->company_logo_url;
    }

    // Weitere Standard-Assets
    $ams_logo = crm_resolve_asset_path('ams.png');
    $stempel  = crm_resolve_asset_path('stempel.png');

    // TCPDF Setup
    if (!class_exists('TCPDF')) {
        $tcpdf_path = get_template_directory() . '/tcbpdf/tcpdf.php';
        if (file_exists($tcpdf_path)) {
            require_once $tcpdf_path;
        }
    }

    if (!class_exists('MYPDFA_Antritt')) {
        class MYPDFA_Antritt extends TCPDF
        {
            public function Header() {}
            public function Footer() {}
        }
    }

    $pdf = new MYPDFA_Antritt(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('X-SIEBEN Wirtschaftstraining GmbH');
    $pdf->SetTitle('Antrittsmeldung - ' . $clean_course_title);
    $pdf->SetSubject('Antrittsmeldung AMS');

    // Margins & Fonts (1-Seiten-Garantie)
    $pdf->SetMargins(20, 15, 20);
    $pdf->SetHeaderMargin(0);
    $pdf->SetFooterMargin(0);
    $pdf->SetAutoPageBreak(false);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->SetFont('dejavusans', '', 10);

    // ==========================================
    // MODULARE ABSCHNITTE LADEN & ASSEMBELN
    // ==========================================
    if (is_array($custom_sections) && !empty($custom_sections) && is_array(reset($custom_sections)) && isset(reset($custom_sections)['key'])) {
        $all_sections = $custom_sections;
    } else {
        $all_sections = function_exists('crm_get_pdf_section_order') ? crm_get_pdf_section_order('antritt', $entry_id) : [];
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

    // Standard-Generatoren für Unterabschnitte
    $antritt_generators = CRM_Pdf_Antritt_Elements::get_subsections_generators($course, [
        'current_date_str' => $current_date_str,
    ]);

    $global_spacing = function_exists('crm_get_pdf_elements_spacing') ? crm_get_pdf_elements_spacing() : ['spacing_top' => 0, 'spacing_bottom' => 0];

    // Falls keine Abschnitte definiert sind (Fallback)
    if (empty($all_sections)) {
        $defs = function_exists('crm_get_pdf_sections_definitions') ? crm_get_pdf_sections_definitions('antritt') : [];
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

    // Render-Loop über alle Abschnitte
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
            // Standardabschnitt: antrittsmeldung
            $sec_subs_gens = $antritt_generators[$sec_key] ?? [];

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

                    $def_sub = $sec_subs_gens[$sk] ?? ($sk === 'antrittsmeldung_titel' ? ($sec_subs_gens['titel'] ?? '') : ($sk === 'titel' ? ($sec_subs_gens['antrittsmeldung_titel'] ?? '') : ''));
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
                    if ($sk === 'antritt_daten' || ($sk === 'antrittsmeldung_titel' && isset($sec_subs_gens['titel']))) continue; // Redundantes Element auslassen
                    $page_html .= $def_sub;
                }
            }
        }

        if (!empty(trim(strip_tags($page_html, '<img>')))) {
            $pdf->AddPage();
            $full_chunk = $sec_prefix . $page_html . $sec_suffix;
            if (function_exists('crm_clean_pdf_html')) {
                $full_chunk = crm_clean_pdf_html($full_chunk);
            }
            $pdf->writeHTML($full_chunk, true, false, true, false, '');
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
            x_sieben_pdf_preview($pdf_url, $course_id, $entry_id, 'antrittsbestaetigung');
        } else {
            $pdf->Output($pdf_name, 'I');
            exit;
        }
    } else {
        return $pdf_url;
    }
}

if (!function_exists('xsieben_antritt_pdf')) {
    function xsieben_antritt_pdf($entry_id, $course_id, $output_to_browser = true, $custom_sections = null)
    {
        return xsieben_antrittsbestaetigung_pdf($entry_id, $course_id, $output_to_browser, $custom_sections);
    }
}

if (!function_exists('xsieben_antrittsmeldung_pdf')) {
    function xsieben_antrittsmeldung_pdf($entry_id, $course_id, $output_to_browser = true, $custom_sections = null)
    {
        return xsieben_antrittsbestaetigung_pdf($entry_id, $course_id, $output_to_browser, $custom_sections);
    }
}
