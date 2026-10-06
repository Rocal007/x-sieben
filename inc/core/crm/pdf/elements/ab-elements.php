<?php
/**
 * Class CRM_Pdf_Ab_Elements
 *
 * Zentraler Element-Controller und Assembler für die Anmeldebestätigung (AB).
 * Verwaltet Standardkomponenten für Kopfzeile, Adressfenster, Referenzleiste,
 * Kurseckdaten, Hotline, Signatur und rechtliche Hinweise / AGB.
 *
 * @package X_SIEBEN_CRM
 * @version 2.19.3
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_Pdf_Ab_Elements
{
    /**
     * Rendert die Kopfzeile mit Logos (wba links, X-SIEBEN rechts).
     *
     * @param string $logo_x7 Pfad oder URL zum X-SIEBEN Firmenlogo
     * @param string $wba_logo Pfad oder URL zum wba Logo
     * @param int $margin_bottom Abstand nach unten in pt
     * @return string
     */
    public static function render_header(string $logo_x7, string $wba_logo = '', int $margin_bottom = 25): string
    {
        if (empty($wba_logo) && function_exists('crm_resolve_asset_path')) {
            $wba_logo = crm_resolve_asset_path('wba-1.png');
        }
        if (empty($logo_x7) && function_exists('crm_resolve_asset_path')) {
            $logo_x7 = crm_resolve_asset_path('xsieben_logo.png');
        }

        return '
        <!-- Top Header: wba (links) & X-SIEBEN Logo (rechts) -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: ' . intval($margin_bottom) . 'px;">
            <tr>
                <td width="30%" align="left">
                    <img src="' . esc_attr($wba_logo) . '" height="48" />
                </td>
                <td width="70%" align="right">
                    <img src="' . esc_attr($logo_x7) . '" height="38" />
                </td>
            </tr>
        </table>
        <div style="font-size: 16pt;">&nbsp;</div>';
    }

    /**
     * Rendert das Adressfenster / Empfängerdaten.
     *
     * @param string $anrede_prefix 'Frau', 'Herrn', 'Frau/Herrn'
     * @param string $tn_name Vollständiger Name
     * @param string $tn_strasse Straße und Hausnummer
     * @param string $tn_plz_ort PLZ und Ort
     * @return string
     */
    public static function render_empfaenger(string $anrede_prefix, string $tn_name, string $tn_strasse, string $tn_plz_ort): string
    {
        return '
        <!-- Adressfenster -->
        <div style="font-size: 10pt; line-height: 1.45; color: #0f172a; margin-bottom: 22px;">
            An ' . esc_html($anrede_prefix) . '<br>
            <strong>' . esc_html($tn_name) . '</strong><br>
            ' . esc_html($tn_strasse) . '<br>
            ' . esc_html($tn_plz_ort) . '
        </div>
        <div style="font-size: 12pt;">&nbsp;</div>';
    }

    /**
     * Rendert die 4-Spalten Referenzleiste.
     *
     * @param string $entry_date_str Anmeldedatum
     * @param string $current_date_str Ausstellungsdatum
     * @param string $unser_zeichen Unser Zeichen
     * @param string $kontakt_telefon Telefon und Bearbeiter
     * @return string
     */
    public static function render_referenz(string $entry_date_str, string $current_date_str = '', string $unser_zeichen = 'X7 – Anna Brauer', string $kontakt_telefon = '0800 / 700 170, X SIEBEN Team'): string
    {
        if (empty($current_date_str)) {
            $current_date_str = date('d.m.Y');
        }

        return '
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
                <td width="24%">' . esc_html($unser_zeichen) . '</td>
                <td width="32%">' . esc_html($kontakt_telefon) . '</td>
                <td width="16%" align="right">' . esc_html($current_date_str) . '</td>
            </tr>
        </table>
        <div style="font-size: 14pt;">&nbsp;</div>';
    }

    /**
     * Rendert die Betreffzeile.
     *
     * @param string $kategorie_label 'Seminar' oder 'Lehrgang'
     * @param string $clean_course_title Titel des Kurses
     * @return string
     */
    public static function render_betreff(string $kategorie_label, string $clean_course_title): string
    {
        return '
        <!-- Betreffzeile -->
        <div style="font-size: 11.5pt; font-weight: bold; color: #0f172a; margin-bottom: 14px;">
            Ihre Anmeldebestätigung zum ' . esc_html($kategorie_label) . ' „' . esc_html($clean_course_title) . '“
        </div>';
    }

    /**
     * Rendert die persönliche Anrede und den Bestätigungstext.
     *
     * @param string $salutation_greeting Z.B. "Sehr geehrte Frau Dr. Mustermann"
     * @param string $kategorie_label 'Seminar' oder 'Lehrgang'
     * @return string
     */
    public static function render_anrede_text(string $salutation_greeting, string $kategorie_label): string
    {
        return '
        <!-- Persönliche Anrede & Bestätigung -->
        <div style="font-size: 10pt; line-height: 1.5; color: #1e293b; margin-bottom: 14px;">
            ' . esc_html($salutation_greeting) . ',<br><br>
            vielen Dank für Ihre Anmeldung zur Schulung. Wir bestätigen diese hiermit zum unten angeführten ' . esc_html($kategorie_label) . ':
        </div>';
    }

    /**
     * Rendert die Tabelle mit den Kurs-Eckdaten.
     *
     * @param string $clean_course_title Titel der Veranstaltung
     * @param string $beginn_text Wochentag, Startdatum, Zeiten, Dauer
     * @param string $ort_text Schulungsort / Online
     * @return string
     */
    public static function render_eckdaten(string $clean_course_title, string $beginn_text, string $ort_text): string
    {
        return '
        <!-- Eckdaten an einheitlicher linker Grundlinie -->
        <table width="100%" cellpadding="3" cellspacing="0" border="0" style="margin-bottom: 18px; font-size: 10pt; line-height: 1.6;">
            <tr>
                <td width="30%" style="font-weight: bold; color: #0f172a; vertical-align: top;">Veranstaltungstitel:</td>
                <td width="70%" style="color: #007c8c; font-weight: bold; vertical-align: top;">' . esc_html($clean_course_title) . '</td>
            </tr>
            <tr>
                <td width="30%" style="font-weight: bold; color: #0f172a; vertical-align: top;">Veranstaltungsbeginn:</td>
                <td width="70%" style="color: #007c8c; font-weight: bold; vertical-align: top;">' . esc_html($beginn_text) . '</td>
            </tr>
            <tr>
                <td width="30%" style="font-weight: bold; color: #0f172a; vertical-align: top;">Veranstaltungsort:</td>
                <td width="70%" style="color: #007c8c; font-weight: bold; vertical-align: top;">' . esc_html($ort_text) . '</td>
            </tr>
        </table>';
    }

    /**
     * Rendert den Hotline- und Rückfragen-Hinweis.
     *
     * @param string $hotline_nr Standard: 0800 / 700 170
     * @return string
     */
    public static function render_hotline(string $hotline_nr = '0800 / 700 170'): string
    {
        return '
        <!-- Hotline-Hinweis -->
        <div style="font-size: 10pt; line-height: 1.5; color: #1e293b; margin-bottom: 18px;">
            Sollten Sie zu Ihrer Anmeldung noch Fragen haben, helfe ich Ihnen telefonisch und kostenfrei gerne unter der <strong>' . esc_html($hotline_nr) . '</strong> weiter.
        </div>';
    }

    /**
     * Rendert die Grußformel und Firmenstampiglie.
     *
     * @param string $stempel Pfad oder URL zur Firmenstampiglie
     * @param string $signatur_name Name und Funktion
     * @return string
     */
    public static function render_signatur(string $stempel = '', string $signatur_name = 'Mag. Dr. Johannes Gasberger | X SIEBEN Wirtschaftstraining GmbH'): string
    {
        if (empty($stempel) && function_exists('crm_resolve_asset_path')) {
            $stempel = crm_resolve_asset_path('stempel.png');
        }

        return '
        <!-- Grußformel & Signatur -->
        <div style="font-size: 10pt; line-height: 1.4; color: #1e293b; margin-bottom: 8px;">
            Mit freundlichen Grüßen
        </div>

        <div style="margin-bottom: 6px;">
            <img src="' . esc_attr($stempel) . '" height="74" />
        </div>

        <div style="font-size: 9.5pt; font-weight: bold; color: #0f172a;">
            ' . esc_html($signatur_name) . '
        </div>';
    }

    /**
     * Rendert die rechtlichen Hinweise und AGB für Seite 2.
     *
     * @param string $agb_url URL zu den AGB
     * @param string $privacy_url URL zur Datenschutzerklärung
     * @return string
     */
    public static function render_agb_hinweis(string $agb_url = '', string $privacy_url = ''): string
    {
        if (empty($agb_url)) {
            $agb_url = 'https://x-sieben.at/wp-content/uploads/2025/09/AGB_X_SIEBEN_2025.pdf';
        }
        if (empty($privacy_url)) {
            $privacy_url = 'https://www.xsieben.at/datenschutzerklaerung/';
        }

        return '
        <div style="font-size: 11pt; font-weight: bold; color: #0f172a; margin-bottom: 14px;">
            Hinweis:
        </div>

        <div style="font-size: 10pt; line-height: 1.6; color: #1e293b; margin-bottom: 14px;">
            Mit Ihrer Buchung bestätigen Sie die Allgemeinen Geschäftsbedingungen (AGB) sowie die darin enthaltene Widerrufsbelehrung von X SIEBEN gelesen und akzeptiert zu haben.
        </div>

        <div style="font-size: 10pt; line-height: 1.6; color: #1e293b; margin-bottom: 14px;">
            Die AGB mit der darin enthaltenen Widerrufsbelehrung finden Sie auf unserer Homepage unter <a href="' . esc_url($agb_url) . '" style="color: #0284c7; text-decoration: underline;">' . esc_html($agb_url) . '</a>, auf Ersuchen können Ihnen die AGB aber auch via E-Mail zugesandt werden.
        </div>

        <div style="font-size: 10pt; line-height: 1.6; color: #1e293b; margin-bottom: 14px;">
            Unsere Datenschutzerklärung finden Sie unter <a href="' . esc_url($privacy_url) . '" style="color: #0284c7; text-decoration: underline;">' . esc_html($privacy_url) . '</a>.
        </div>';
    }

    /**
     * Liefert alle Standard-Generatoren für Unterabschnitte der Anmeldebestätigung.
     *
     * @param object $course CRM_Model
     * @param array $params Zusätzliche vorberechnete Parameter
     * @return array
     */
    public static function get_subsections_generators($course, array $params = []): array
    {
        $custom_logo_url = !empty($course->company_logo_url) ? $course->company_logo_url : '';
        $logo_x7 = function_exists('crm_resolve_asset_path')
            ? crm_resolve_asset_path(!empty($custom_logo_url) ? $custom_logo_url : 'xsieben_logo.png')
            : $custom_logo_url;
        if (empty($logo_x7) && function_exists('crm_resolve_asset_path')) {
            $logo_x7 = crm_resolve_asset_path('xsieben_logo.png');
        }

        $wba_logo = function_exists('crm_resolve_asset_path') ? crm_resolve_asset_path('wba-1.png') : '';
        $stempel  = function_exists('crm_resolve_asset_path') ? crm_resolve_asset_path('stempel.png') : '';

        // Teilnehmerdaten
        $anrede_prefix = ($course->anrede === 'Frau') ? 'Frau' : (($course->anrede === 'Herr') ? 'Herrn' : 'Frau/Herrn');
        $salutation_greeting = ($course->anrede === 'Frau') ? ('Sehr geehrte Frau ' . $course->nachname) : (($course->anrede === 'Herr') ? ('Sehr geehrter Herr ' . $course->nachname) : 'Sehr geehrte Damen und Herren');
        $tn_name       = trim(($course->vorname ?? '') . ' ' . ($course->nachname ?? ''));
        $tn_strasse    = $course->street ?: '';
        $tn_plz_ort    = trim(($course->zip_code ?: ($course->postcode ?? '')) . ' ' . ($course->city ?: ''));

        // Referenzdaten
        $entry_date_str = $params['entry_date_str'] ?? date('d.m.Y');
        $current_date_str = $params['current_date_str'] ?? date('d.m.Y');

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

        // Kategorie
        $is_seminar = (stripos($clean_course_title, 'Seminar') !== false || stripos($clean_course_title, 'Workshop') !== false);
        $kategorie_label = $is_seminar ? 'Seminar' : 'Lehrgang';

        $agb_url = !empty($course->agb_url) ? $course->agb_url : 'https://x-sieben.at/wp-content/uploads/2025/09/AGB_X_SIEBEN_2025.pdf';
        $privacy_url = !empty($course->privacy_url) ? $course->privacy_url : 'https://www.xsieben.at/datenschutzerklaerung/';

        $header_html     = self::render_header($logo_x7, $wba_logo, 25);
        $header_p2_html  = self::render_header($logo_x7, $wba_logo, 35);
        $empfaenger_html = self::render_empfaenger($anrede_prefix, $tn_name, $tn_strasse, $tn_plz_ort);
        $referenz_html   = self::render_referenz($entry_date_str, $current_date_str);
        $betreff_html    = self::render_betreff($kategorie_label, $clean_course_title);
        $anrede_html     = self::render_anrede_text($salutation_greeting, $kategorie_label);
        $eckdaten_html   = self::render_eckdaten($clean_course_title, $beginn_text, $ort_text);
        $hotline_html    = self::render_hotline();
        $signatur_html   = self::render_signatur($stempel);
        $agb_html        = self::render_agb_hinweis($agb_url, $privacy_url);

        // Zusammengesetzte Bestätigung für ältere Konfigurationen
        $combined_bestaetigung = $empfaenger_html . $referenz_html . $betreff_html . $anrede_html . $eckdaten_html . $hotline_html . $signatur_html;

        return [
            'anschreiben' => [
                'header'       => $header_html,
                'empfaenger'   => $empfaenger_html,
                'referenz'     => $referenz_html,
                'betreff'      => $betreff_html,
                'anrede_text'  => $anrede_html,
                'eckdaten'     => $eckdaten_html,
                'hotline'      => $hotline_html,
                'signatur'     => $signatur_html,
                'bestaetigung' => $combined_bestaetigung,
            ],
            'agb_hinweis' => [
                'header'      => $header_p2_html,
                'hinweistext' => $agb_html,
            ],
        ];
    }
}
