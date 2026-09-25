<?php
/**
 * X-SIEBEN CRM - Kursantrittsbestätigung / Antrittsmeldung PDF Generator
 *
 * Generiert die offizielle Antrittsmeldung für das AMS via TCPDF.
 * Entspricht exakt der Vorlage Kü_Antrittsmeldung.pdf von Hannes Gajo.
 *
 * @package X_SIEBEN_CRM
 * @version 2.18.82
 */

if (!defined('ABSPATH')) {
    exit;
}

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
    $tn_name      = trim($anrede_label . ' ' . $course->vorname . ' ' . $course->nachname);
    $tn_adresse   = trim(($course->street ?: '') . ', ' . ($course->zip_code ?: '') . ' ' . ($course->city ?: ''));
    $tn_svr       = $course->svr ?: '';

    // Kursdaten
    $clean_course_title = html_entity_decode(html_entity_decode($course->title ?? '', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
    $start_datum = $course->start_datum ?: date('d.m.Y');
    $current_date_str = date('d.m.Y');

    // Schulungsort
    $schulungsort = 'Rochusgasse 6,<br>1030 Wien / Live Online';
    if (!empty($course->schulungsort)) {
        $schulungsort = nl2br(esc_html($course->schulungsort));
    }

    // Assets
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

    $pdf->AddPage();

    $html = '
    <!-- Top Header mit AMS Logo zentriert -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 20px;">
        <tr>
            <td width="100%" align="center">
                <img src="' . $ams_logo . '" height="44" />
            </td>
        </tr>
    </table>

    <div style="font-size: 10pt;">&nbsp;</div>

    <!-- Adressat AMS links & Datum rechts -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size: 10pt; line-height: 1.4; color: #0f172a; margin-bottom: 25px;">
        <tr>
            <td width="50%" align="left" style="vertical-align: top;">
                An das<br>
                <strong>AMS</strong>
            </td>
            <td width="50%" align="right" style="vertical-align: top;">
                Wien, am ' . esc_html($current_date_str) . '
            </td>
        </tr>
    </table>

    <div style="font-size: 16pt;">&nbsp;</div>

    <!-- Titel -->
    <div style="font-size: 11pt; font-weight: bold; color: #0f172a; margin-bottom: 24px;">
        ANTRITTSMELDUNG: „' . esc_html($clean_course_title) . '“
    </div>

    <div style="font-size: 12pt;">&nbsp;</div>

    <!-- 2-Spalten Stammdaten-Matrix -->
    <table width="100%" cellpadding="5" cellspacing="0" border="0" style="font-size: 10pt; line-height: 1.5; color: #0f172a;">
        <tr>
            <td width="38%" style="vertical-align: top; color: #334155;">
                Name des / der TeilnehmerIn:
            </td>
            <td width="62%" style="vertical-align: top; font-weight: bold;">
                ' . esc_html($tn_name) . '
            </td>
        </tr>
        <tr>
            <td width="38%" style="vertical-align: top; color: #334155;">
                Adresse des / der TeilnehmerIn:
            </td>
            <td width="62%" style="vertical-align: top;">
                ' . esc_html($tn_adresse) . '
            </td>
        </tr>
        <tr>
            <td width="38%" style="vertical-align: top; color: #334155;">
                Sozialversicherungsnummer:
            </td>
            <td width="62%" style="vertical-align: top; font-weight: bold;">
                ' . esc_html($tn_svr) . '
            </td>
        </tr>
        <tr>
            <td width="38%" style="vertical-align: top; color: #334155;">
                hat den Lehrgang am:
            </td>
            <td width="62%" style="vertical-align: top; font-weight: bold;">
                ' . esc_html($start_datum) . ', um 09.00 Uhr
            </td>
        </tr>
        <tr>
            <td width="38%" style="vertical-align: top; color: #334155;">
                beim Kursinstitut:
            </td>
            <td width="62%" style="vertical-align: top; line-height: 1.45;">
                X SIEBEN Wirtschaftstraining GmbH; 0800 / 700 170<br>
                angetreten.<br><br>
                <strong>Adresse des Kursinstituts:</strong><br>
                Kurzegasse 7,<br>
                2493 Lichtenwörth<br><br>
                <strong>Schulungsort:</strong><br>
                ' . $schulungsort . '<br><br>
                ÖSTERREICH<br>
                UID ATU76624137
            </td>
        </tr>
    </table>

    <div style="font-size: 30pt;">&nbsp;</div>

    <!-- Unterschrift und Firmenstampiglie -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td width="55%">
                <div style="margin-bottom: 4px;">
                    <img src="' . $stempel . '" height="52" />
                </div>
                <div style="font-size: 8.5pt; color: #475569; line-height: 1.3;">
                    ........................................................................<br>
                    Unterschrift<br>
                    und Firmenstampiglie
                </div>
            </td>
            <td width="45%">&nbsp;</td>
        </tr>
    </table>';

    $pdf->writeHTML($html, true, false, true, false, '');

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
