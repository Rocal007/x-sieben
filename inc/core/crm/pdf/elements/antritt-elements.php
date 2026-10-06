<?php
/**
 * Class CRM_Pdf_Antritt_Elements
 *
 * Zentraler Element-Controller und Assembler für die Antrittsmeldung (AMS).
 * Verwaltet Standardkomponenten für Kopfzeile (Logos X-SIEBEN & AMS),
 * Adressat/Datum, Titelzeile, Stammdaten-Matrix und Firmenstampiglie/Signatur.
 *
 * @package X_SIEBEN_CRM
 * @version 2.19.5
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_Pdf_Antritt_Elements
{
    /**
     * Rendert die Kopfzeile mit Logos (X-SIEBEN links, AMS rechts).
     *
     * @param string $logo_x7 Pfad oder URL zum X-SIEBEN Firmenlogo
     * @param string $ams_logo Pfad oder URL zum AMS Logo
     * @return string
     */
    public static function render_header(string $logo_x7, string $ams_logo = ''): string
    {
        if (empty($logo_x7) && function_exists('crm_resolve_asset_path')) {
            $logo_x7 = crm_resolve_asset_path('xsieben_logo.png');
        }
        if (empty($ams_logo) && function_exists('crm_resolve_asset_path')) {
            $ams_logo = crm_resolve_asset_path('ams.png');
        }

        return '<!-- Top Header: X-SIEBEN Logo (links) & AMS Logo (rechts) -->' .
            '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 24px;">' .
            '<tr>' .
            '<td width="50%" align="left" style="vertical-align: middle;"><img src="' . esc_attr($logo_x7) . '" height="38" /></td>' .
            '<td width="50%" align="right" style="vertical-align: middle;"><img src="' . esc_attr($ams_logo) . '" height="38" /></td>' .
            '</tr>' .
            '</table>' .
            '<div style="font-size: 8pt;">&nbsp;</div>';
    }

    /**
     * Rendert den Adressaten AMS links und das Ausstellungsdatum rechts.
     *
     * @param string $current_date_str Ausstellungsdatum
     * @param string $ort Ort der Ausstellung (Standard: Wien)
     * @return string
     */
    public static function render_adressat_datum(string $current_date_str = '', string $ort = 'Wien'): string
    {
        if (empty($current_date_str)) {
            $current_date_str = date('d.m.Y');
        }

        return '<!-- Adressat AMS links & Datum rechts -->' .
            '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size: 10pt; line-height: 1.4; color: #0f172a; margin-bottom: 22px;">' .
            '<tr>' .
            '<td width="50%" align="left" style="vertical-align: top;">An das<br><strong>AMS</strong></td>' .
            '<td width="50%" align="right" style="vertical-align: top;">' . esc_html($ort) . ', am ' . esc_html($current_date_str) . '</td>' .
            '</tr>' .
            '</table>' .
            '<div style="font-size: 12pt;">&nbsp;</div>';
    }

    /**
     * Rendert die Titelzeile.
     *
     * @param string $clean_course_title Titel des Kurses
     * @return string
     */
    public static function render_titel(string $clean_course_title): string
    {
        return '<!-- Titel -->' .
            '<div style="font-size: 11pt; font-weight: bold; color: #0f172a; margin-bottom: 20px;">' .
            'ANTRITTSMELDUNG: „' . esc_html($clean_course_title) . '“' .
            '</div>' .
            '<div style="font-size: 10pt;">&nbsp;</div>';
    }

    /**
     * Rendert die strukturierte Stammdaten-Matrix.
     *
     * @param string $tn_name Name des Teilnehmers
     * @param string $tn_adresse Adresse des Teilnehmers
     * @param string $tn_svr Sozialversicherungsnummer
     * @param string $start_datum Antrittsdatum
     * @param string $schulungsort Schulungsort
     * @param string $institut_name Name des Kursinstituts
     * @param string $institut_adresse Adresse des Kursinstituts
     * @param string $uid UID des Kursinstituts
     * @return string
     */
    public static function render_stammdaten(
        string $tn_name,
        string $tn_adresse,
        string $tn_svr,
        string $start_datum,
        string $schulungsort = 'Rochusgasse 6,<br>1030 Wien / Live Online',
        string $institut_name = 'X SIEBEN Wirtschaftstraining GmbH',
        string $institut_adresse = 'Kurzegasse 7, 2493 Lichtenwörth (Tel: 0800 / 700 170)',
        string $uid = 'ATU76624137'
    ): string {
        return '<!-- 2-Spalten Stammdaten-Matrix an einheitlicher linker Grundlinie -->' .
            '<table width="100%" cellpadding="4" cellspacing="0" border="0" style="font-size: 10pt; line-height: 1.5; color: #0f172a;">' .
            '<tr><td width="38%" style="vertical-align: top; color: #334155;">Name des / der TeilnehmerIn:</td><td width="62%" style="vertical-align: top; font-weight: bold;">' . esc_html($tn_name) . '</td></tr>' .
            '<tr><td width="38%" style="vertical-align: top; color: #334155;">Adresse des / der TeilnehmerIn:</td><td width="62%" style="vertical-align: top;">' . esc_html($tn_adresse) . '</td></tr>' .
            '<tr><td width="38%" style="vertical-align: top; color: #334155;">Sozialversicherungsnummer:</td><td width="62%" style="vertical-align: top; font-weight: bold;">' . esc_html($tn_svr) . '</td></tr>' .
            '<tr><td width="38%" style="vertical-align: top; color: #334155;">hat den Lehrgang am:</td><td width="62%" style="vertical-align: top; font-weight: bold;">' . esc_html($start_datum) . ', um 09.00 Uhr</td></tr>' .
            '<tr><td width="38%" style="vertical-align: top; color: #334155;">beim Kursinstitut:</td><td width="62%" style="vertical-align: top; font-weight: bold;">' . esc_html($institut_name) . ' angetreten.</td></tr>' .
            '<tr><td width="38%" style="vertical-align: top; color: #334155;">Adresse des Kursinstituts:</td><td width="62%" style="vertical-align: top;">' . esc_html($institut_adresse) . '</td></tr>' .
            '<tr><td width="38%" style="vertical-align: top; color: #334155;">Schulungsort:</td><td width="62%" style="vertical-align: top;">' . $schulungsort . '</td></tr>' .
            '<tr><td width="38%" style="vertical-align: top; color: #334155;">UID des Kursinstituts:</td><td width="62%" style="vertical-align: top;">' . esc_html($uid) . '</td></tr>' .
            '</table>' .
            '<div style="font-size: 20pt;">&nbsp;</div>';
    }

    /**
     * Rendert die Unterschriftslinie und Firmenstampiglie.
     *
     * @param string $stempel Pfad oder URL zur Firmenstampiglie
     * @return string
     */
    public static function render_signatur(string $stempel = ''): string
    {
        if (empty($stempel) && function_exists('crm_resolve_asset_path')) {
            $stempel = crm_resolve_asset_path('stempel.png');
        }

        return '<!-- Unterschrift und Firmenstampiglie -->' .
            '<table width="100%" cellpadding="0" cellspacing="0" border="0">' .
            '<tr>' .
            '<td width="60%">' .
            '<div style="margin-bottom: 6px;"><img src="' . esc_attr($stempel) . '" height="74" /></div>' .
            '<div style="font-size: 8.5pt; color: #475569; line-height: 1.3;">........................................................................<br>Unterschrift und Firmenstampiglie</div>' .
            '</td>' .
            '<td width="40%">&nbsp;</td>' .
            '</tr>' .
            '</table>';
    }

    /**
     * Liefert alle Standard-Generatoren für Unterabschnitte der Antrittsmeldung.
     *
     * @param object $course CRM_Model
     * @param array $params Zusätzliche Parameter
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

        $ams_logo = function_exists('crm_resolve_asset_path') ? crm_resolve_asset_path('ams.png') : '';
        $stempel  = function_exists('crm_resolve_asset_path') ? crm_resolve_asset_path('stempel.png') : '';

        // Teilnehmerdaten
        $anrede_label = ($course->anrede === 'Frau') ? 'Frau' : (($course->anrede === 'Herr') ? 'Herr' : '');
        $tn_name      = trim($anrede_label . ' ' . ($course->vorname ?? '') . ' ' . ($course->nachname ?? ''));
        $tn_adresse   = trim(($course->street ?: '') . ', ' . ($course->zip_code ?: ($course->postcode ?? '')) . ' ' . ($course->city ?: ''));
        $tn_svr       = $course->svr ?: '';

        // Kursdaten
        $clean_course_title = html_entity_decode(html_entity_decode($course->title ?? '', ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
        $start_datum = $course->start_datum ?: date('d.m.Y');
        $current_date_str = $params['current_date_str'] ?? date('d.m.Y');

        // Schulungsort
        $schulungsort = 'Rochusgasse 6,<br>1030 Wien / Live Online';
        if (!empty($course->schulungsort)) {
            $schulungsort = nl2br(esc_html($course->schulungsort));
        } elseif (!empty($course->location_wien)) {
            $schulungsort = nl2br(esc_html($course->location_wien));
        }

        $institut_name    = !empty($course->company_name) ? $course->company_name : 'X SIEBEN Wirtschaftstraining GmbH';
        $institut_adresse = !empty($course->company_address) ? ($course->company_address . ' (Tel: ' . (!empty($course->company_phone) ? $course->company_phone : '0800 / 700 170') . ')') : 'Kurzegasse 7, 2493 Lichtenwörth (Tel: 0800 / 700 170)';
        $uid              = !empty($course->company_uid) ? $course->company_uid : 'ATU76624137';

        $header_html    = self::render_header($logo_x7, $ams_logo);
        $adressat_html  = self::render_adressat_datum($current_date_str);
        $titel_html     = self::render_titel($clean_course_title);
        $stammdaten_html= self::render_stammdaten($tn_name, $tn_adresse, $tn_svr, $start_datum, $schulungsort, $institut_name, $institut_adresse, $uid);
        $signatur_html  = self::render_signatur($stempel);

        $combined_antritt = $stammdaten_html . $signatur_html;

        return [
            'antrittsmeldung' => [
                'header'         => $header_html,
                'adressat_datum' => $adressat_html,
                'titel'          => $titel_html,
                'stammdaten'     => $stammdaten_html,
                'signatur'       => $signatur_html,
                'antritt_daten'  => $combined_antritt,
            ],
        ];
    }

    /**
     * Liefert HTML-Templates mit dynamischen Platzhaltern für den Abschnitts-Editor der Antrittsmeldung.
     * Verhindert das Einfügen statisch evaluierter Formulardaten in wiederverwendbare Vorlagen.
     *
     * @return array
     */
    public static function get_subsections_templates(): array
    {
        return [
            'antrittsmeldung' => [
                'header'         => self::get_header_template(),
                'adressat_datum' => self::get_adressat_datum_template(),
                'titel'          => self::get_titel_template(),
                'stammdaten'     => self::get_stammdaten_template(),
                'signatur'       => self::get_signatur_template(),
                'antritt_daten'  => self::get_antritt_daten_template(),
            ],
        ];
    }

    public static function get_header_template(): string
    {
        return '<!-- Top Header: X-SIEBEN Logo (links) & AMS Logo (rechts) -->' . "\n" .
            '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 24px;">' . "\n" .
            '<tr>' . "\n" .
            '<td width="50%" align="left" style="vertical-align: middle;"><img src="{logo_url}" height="38" /></td>' . "\n" .
            '<td width="50%" align="right" style="vertical-align: middle;"><img src="{ams_logo}" height="38" /></td>' . "\n" .
            '</tr>' . "\n" .
            '</table>' . "\n" .
            '<div style="font-size: 8pt;">&nbsp;</div>';
    }

    public static function get_adressat_datum_template(): string
    {
        return '<!-- Adressat AMS links & Datum rechts -->' . "\n" .
            '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size: 10pt; line-height: 1.4; color: #0f172a; margin-bottom: 22px;">' . "\n" .
            '<tr>' . "\n" .
            '<td width="50%" align="left" style="vertical-align: top;">An das<br><strong>AMS</strong></td>' . "\n" .
            '<td width="50%" align="right" style="vertical-align: top;">{ausstellungsort}, am {datum}</td>' . "\n" .
            '</tr>' . "\n" .
            '</table>' . "\n" .
            '<div style="font-size: 12pt;">&nbsp;</div>';
    }

    public static function get_titel_template(): string
    {
        return '<!-- Titel -->' . "\n" .
            '<div style="font-size: 11pt; font-weight: bold; color: #0f172a; margin-bottom: 20px;">' . "\n" .
            'ANTRITTSMELDUNG: „{kurstitel}“' . "\n" .
            '</div>' . "\n" .
            '<div style="font-size: 10pt;">&nbsp;</div>';
    }

    public static function get_stammdaten_template(): string
    {
        return '<!-- 2-Spalten Stammdaten-Matrix an einheitlicher linker Grundlinie -->' . "\n" .
            '<table width="100%" cellpadding="4" cellspacing="0" border="0" style="font-size: 10pt; line-height: 1.5; color: #0f172a;">' . "\n" .
            '<tr><td width="38%" style="vertical-align: top; color: #334155;">Name des / der TeilnehmerIn:</td><td width="62%" style="vertical-align: top; font-weight: bold;">{teilnehmer_name}</td></tr>' . "\n" .
            '<tr><td width="38%" style="vertical-align: top; color: #334155;">Adresse des / der TeilnehmerIn:</td><td width="62%" style="vertical-align: top;">{teilnehmer_adresse}</td></tr>' . "\n" .
            '<tr><td width="38%" style="vertical-align: top; color: #334155;">Sozialversicherungsnummer:</td><td width="62%" style="vertical-align: top; font-weight: bold;">{svr}</td></tr>' . "\n" .
            '<tr><td width="38%" style="vertical-align: top; color: #334155;">hat den Lehrgang am:</td><td width="62%" style="vertical-align: top; font-weight: bold;">{startdatum}, um 09.00 Uhr</td></tr>' . "\n" .
            '<tr><td width="38%" style="vertical-align: top; color: #334155;">beim Kursinstitut:</td><td width="62%" style="vertical-align: top; font-weight: bold;">{institut} angetreten.</td></tr>' . "\n" .
            '<tr><td width="38%" style="vertical-align: top; color: #334155;">Adresse des Kursinstituts:</td><td width="62%" style="vertical-align: top;">{institut_adresse}</td></tr>' . "\n" .
            '<tr><td width="38%" style="vertical-align: top; color: #334155;">Schulungsort:</td><td width="62%" style="vertical-align: top;">{schulungsort}</td></tr>' . "\n" .
            '<tr><td width="38%" style="vertical-align: top; color: #334155;">UID des Kursinstituts:</td><td width="62%" style="vertical-align: top;">{company_uid}</td></tr>' . "\n" .
            '</table>' . "\n" .
            '<div style="font-size: 20pt;">&nbsp;</div>';
    }

    public static function get_signatur_template(): string
    {
        return '<!-- Unterschrift und Firmenstampiglie -->' . "\n" .
            '<table width="100%" cellpadding="0" cellspacing="0" border="0">' . "\n" .
            '<tr>' . "\n" .
            '<td width="60%">' . "\n" .
            '<div style="margin-bottom: 6px;"><img src="{stempel}" height="74" /></div>' . "\n" .
            '<div style="font-size: 8.5pt; color: #475569; line-height: 1.3;">........................................................................<br>Unterschrift und Firmenstampiglie</div>' . "\n" .
            '</td>' . "\n" .
            '<td width="40%">&nbsp;</td>' . "\n" .
            '</tr>' . "\n" .
            '</table>';
    }

    public static function get_antritt_daten_template(): string
    {
        return self::get_stammdaten_template() . self::get_signatur_template();
    }
}

