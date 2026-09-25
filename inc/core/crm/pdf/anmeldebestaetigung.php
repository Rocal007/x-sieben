<?php
/**
 * X-SIEBEN CRM - Kursanmeldebestätigung (AB) PDF Generator
 *
 * Generiert die offizielle Anmeldebestätigung für TeilnehmerInnen via TCPDF.
 * Entspricht exakt der Vorlage Anmeldebestätigung.pdf von Hannes Gajo.
 *
 * @package X_SIEBEN_CRM
 * @version 2.18.82
 */

if (!defined('ABSPATH')) {
    exit;
}

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
    $tn_name       = trim($course->vorname . ' ' . $course->nachname);
    $tn_strasse    = $course->street ?: '';
    $tn_plz_ort    = trim(($course->zip_code ?: '') . ' ' . ($course->city ?: ''));

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

    // Assets
    $wba_logo   = crm_resolve_asset_path('wba-1.png');
    $logo_x7    = crm_resolve_asset_path('xsieben_logo.png');
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
                // Leer - Header wird im Seitenbody gerendert
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
                <td width="25%" align="left"><img src="' . $oecert . '" height="18" /></td>
                <td width="25%" align="center"><img src="' . $tuef . '" height="18" /></td>
                <td width="25%" align="center"><img src="' . $systemcert . '" height="18" /></td>
                <td width="25%" align="right"><img src="' . $pma . '" height="18" /></td>
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
    // SEITE 1: ANMELDEBESTÄTIGUNG
    // ==========================================
    $pdf->AddPage();

    $page1_html = '
    <!-- Top Header: wba (links) & X-SIEBEN Logo (rechts) -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 25px;">
        <tr>
            <td width="30%" align="left">
                <img src="' . $wba_logo . '" height="48" />
            </td>
            <td width="70%" align="right">
                <img src="' . $logo_x7 . '" height="38" />
            </td>
        </tr>
    </table>

    <div style="font-size: 16pt;">&nbsp;</div>

    <!-- Adressfenster -->
    <div style="font-size: 10pt; line-height: 1.45; color: #0f172a; margin-bottom: 22px;">
        An ' . esc_html($anrede_prefix) . '<br>
        <strong>' . esc_html($tn_name) . '</strong><br>
        ' . esc_html($tn_strasse) . '<br>
        ' . esc_html($tn_plz_ort) . '
    </div>

    <div style="font-size: 12pt;">&nbsp;</div>

    <!-- 4-Spalten Referenzleiste -->
    <table width="100%" cellpadding="2" cellspacing="0" border="0" style="font-size: 8.5pt; color: #334155; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; padding-top: 4px; padding-bottom: 4px; margin-bottom: 20px;">
        <tr>
            <td width="28%" style="font-weight: bold; color: #64748b;">Ihre Zeichen / Nachricht vom</td>
            <td width="24%" style="font-weight: bold; color: #64748b;">Unser Zeichen</td>
            <td width="32%" style="font-weight: bold; color: #64748b;">Telefon, Name</td>
            <td width="16%" align="right" style="font-weight: bold; color: #64748b;">Datum</td>
        </tr>
        <tr>
            <td width="28%">Anmeldung vom ' . esc_html($entry_date_str) . '</td>
            <td width="24%">X7 – Anna Brauer</td>
            <td width="32%">0800 / 700 170, X SIEBEN Team</td>
            <td width="16%" align="right">' . esc_html($current_date_str) . '</td>
        </tr>
    </table>

    <div style="font-size: 14pt;">&nbsp;</div>

    <!-- Betreffzeile -->
    <div style="font-size: 11.5pt; font-weight: bold; color: #0f172a; margin-bottom: 14px;">
        Ihre Anmeldebestätigung zum ' . esc_html($kategorie_label) . ' „' . esc_html($clean_course_title) . '“
    </div>

    <!-- Persönliche Anrede & Bestätigung -->
    <div style="font-size: 10pt; line-height: 1.5; color: #1e293b; margin-bottom: 14px;">
        ' . esc_html($salutation_greeting) . ',<br><br>
        vielen Dank für Ihre Anmeldung zur Schulung. Wir bestätigen diese hiermit zum unten angeführten ' . esc_html($kategorie_label) . ':
    </div>

    <!-- Eckdaten mit orangem Highlight -->
    <div style="margin-left: 20px; margin-bottom: 18px; font-size: 10pt; line-height: 1.6;">
        <table width="100%" cellpadding="3" cellspacing="0" border="0">
            <tr>
                <td width="30%" style="font-weight: bold; color: #0f172a;">Veranstaltungstitel:</td>
                <td width="70%" style="color: #ea580c; font-weight: bold;">' . esc_html($clean_course_title) . '</td>
            </tr>
            <tr>
                <td width="30%" style="font-weight: bold; color: #0f172a;">Veranstaltungsbeginn:</td>
                <td width="70%" style="color: #ea580c; font-weight: bold;">' . esc_html($beginn_text) . '</td>
            </tr>
            <tr>
                <td width="30%" style="font-weight: bold; color: #0f172a;">Veranstaltungsort:</td>
                <td width="70%" style="color: #ea580c; font-weight: bold;">' . esc_html($ort_text) . '</td>
            </tr>
        </table>
    </div>

    <!-- Hotline-Hinweis -->
    <div style="font-size: 10pt; line-height: 1.5; color: #1e293b; margin-bottom: 18px;">
        Sollten Sie zu Ihrer Anmeldung noch Fragen haben, helfe ich Ihnen telefonisch und kostenfrei gerne unter der <strong>0800 / 700 170</strong> weiter.
    </div>

    <!-- Grußformel & Signatur -->
    <div style="font-size: 10pt; line-height: 1.4; color: #1e293b; margin-bottom: 8px;">
        Mit freundlichen Grüßen
    </div>

    <div style="margin-bottom: 6px;">
        <img src="' . $stempel . '" height="52" />
    </div>

    <div style="font-size: 9.5pt; font-weight: bold; color: #0f172a;">
        Mag. Dr. Johannes Gasberger | X SIEBEN Wirtschaftstraining GmbH
    </div>';

    $pdf->writeHTML($page1_html, true, false, true, false, '');

    // ==========================================
    // SEITE 2: RECHTLICHE HINWEISE & AGB
    // ==========================================
    $pdf->AddPage();

    $page2_html = '
    <!-- Top Header: wba & X-SIEBEN Logo -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 35px;">
        <tr>
            <td width="30%" align="left">
                <img src="' . $wba_logo . '" height="48" />
            </td>
            <td width="70%" align="right">
                <img src="' . $logo_x7 . '" height="38" />
            </td>
        </tr>
    </table>

    <div style="font-size: 20pt;">&nbsp;</div>

    <div style="font-size: 11pt; font-weight: bold; color: #0f172a; margin-bottom: 14px;">
        Hinweis:
    </div>

    <div style="font-size: 10pt; line-height: 1.6; color: #1e293b; margin-bottom: 14px;">
        Mit Ihrer Buchung bestätigen Sie die Allgemeinen Geschäftsbedingungen (AGB) sowie die darin enthaltene Widerrufsbelehrung von X SIEBEN gelesen und akzeptiert zu haben.
    </div>

    <div style="font-size: 10pt; line-height: 1.6; color: #1e293b; margin-bottom: 14px;">
        Die AGB mit der darin enthaltenen Widerrufsbelehrung finden Sie auf unserer Homepage unter <a href="https://x-sieben.at/wp-content/uploads/2025/09/AGB_X_SIEBEN_2025.pdf" style="color: #0284c7; text-decoration: underline;">https://x-sieben.at/wp-content/uploads/2025/09/AGB_X_SIEBEN_2025.pdf</a>, auf Ersuchen können Ihnen die AGB aber auch via E-Mail zugesandt werden.
    </div>

    <div style="font-size: 10pt; line-height: 1.6; color: #1e293b; margin-bottom: 14px;">
        Unsere Datenschutzerklärung finden Sie unter <a href="https://www.xsieben.at/datenschutzerklaerung/" style="color: #0284c7; text-decoration: underline;">https://www.xsieben.at/datenschutzerklaerung/</a>.
    </div>';

    $pdf->writeHTML($page2_html, true, false, true, false, '');

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
