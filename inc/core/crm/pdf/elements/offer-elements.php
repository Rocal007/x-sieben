<?php
/**
 * Class CRM_Pdf_Offer_Elements
 *
 * Zentraler Element-Controller und Assembler für alle visuellen Komponenten
 * des X-SIEBEN Angebots-PDFs (offer.php).
 *
 * Ausgelagerte Präsentationsschicht zur Gewährleistung von echter MVC-Entkopplung
 * und Wahrung des Autarkie-Gebots (Senior Developer Standard).
 *
 * @package X_SIEBEN_CRM
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_Pdf_Offer_Elements
{
    /**
     * Liefert das TCPDF-Stylesheet für das Angebots-Dokument.
     *
     * @return string
     */
    public static function render_styles(): string
    {
        return require __DIR__ . '/offer-styles.php';
    }

    /**
     * Rendert das PDF-Header-Layout für TCPDF.
     *
     * @param string $mode 'full', 'logo_only', 'address_only', 'custom', 'none'
     * @param array $company_info Stammdaten der Gesellschaft
     * @param string $logo_html HTML-Code des Logos
     * @param array $cfg Abschnitts-Konfiguration
     * @param array $master_cfg Master-Header-Konfiguration
     * @param object|null $course CRM_Model Instanz
     * @return string
     */
    public static function render_header(
        string $mode,
        array $company_info,
        string $logo_html = '',
        array $cfg = [],
        array $master_cfg = [],
        ?object $course = null
    ): string {
        return require __DIR__ . '/offer-header.php';
    }

    /**
     * Rendert das Empfänger- und Angebotsdatenfeld (Deckblatt).
     *
     * @param object $course CRM_Model
     * @param string $angebotsnummer
     * @return string
     */
    public static function render_empfaenger(object $course, string $angebotsnummer): string
    {
        return require __DIR__ . '/offer-empfaenger.php';
    }

    /**
     * Rendert die persönliche Anrede mit Begrüßungs-/Einleitungstext.
     *
     * @param string $salutation_name
     * @param string $angebot_intro
     * @return string
     */
    public static function render_anrede_intro(string $salutation_name, string $angebot_intro): string
    {
        return require __DIR__ . '/offer-anrede-intro.php';
    }

    /**
     * Rendert die Grußformel.
     *
     * @param string $angebot_gruss
     * @return string
     */
    public static function render_gruss(string $angebot_gruss): string
    {
        return require __DIR__ . '/offer-gruss.php';
    }

    /**
     * Rendert den Gliederungsverweis unter der Signatur.
     *
     * @return string
     */
    public static function render_hinweis_nachstehend(): string
    {
        return require __DIR__ . '/offer-hinweis-nachstehend.php';
    }

    /**
     * Rendert den Veranstaltungszeitraum mit Kalender-Icon und Divider.
     *
     * @param object $course CRM_Model
     * @return string
     */
    public static function render_zeitraum(object $course): string
    {
        return require __DIR__ . '/offer-zeitraum.php';
    }

    /**
     * Rendert die Lehreinheiten-Darstellung.
     *
     * @param object $course CRM_Model
     * @return string
     */
    public static function render_lehreinheiten(object $course): string
    {
        return require __DIR__ . '/offer-lehreinheiten.php';
    }

    /**
     * Rendert die Abschluss-Box mit Auszeichnungs-Icon, Trennlinie und Rahmen.
     *
     * @param object $course CRM_Model
     * @return string
     */
    public static function render_abschluss_box(object $course): string
    {
        return require __DIR__ . '/offer-abschluss-box.php';
    }

    /**
     * Rendert die Teilnahmevoraussetzungen mit Gefahren-Icon und Divider.
     *
     * @param object $course CRM_Model
     * @return string
     */
    public static function render_voraussetzungen(object $course): string
    {
        return require __DIR__ . '/offer-voraussetzungen.php';
    }

    /**
     * Rendert die Zertifizierungspartner-Logo-Box mit Divider.
     *
     * @param object $course CRM_Model
     * @return string
     */
    public static function render_zertifizierungen(object $course): string
    {
        return require __DIR__ . '/offer-zertifizierungen.php';
    }

    /**
     * Rendert den Schulungsort und Durchführungsmodus.
     *
     * @param object $course CRM_Model
     * @param string|null $custom_html
     * @return string
     */
    public static function render_ort_durchfuehrung(object $course, ?string $custom_html = null): string
    {
        return require __DIR__ . '/offer-ort-durchfuehrung.php';
    }

    /**
     * Rendert die Gültigkeitsklausel für das Angebot.
     *
     * @param object $course CRM_Model
     * @return string
     */
    public static function render_gueltigkeit(object $course): string
    {
        return require __DIR__ . '/offer-gueltigkeit.php';
    }

    /**
     * Rendert die Verweise auf Anhang 1 und 2 im Buchungsteil.
     *
     * @return string
     */
    public static function render_anhang_hinweise(): string
    {
        return require __DIR__ . '/offer-anhang-hinweise.php';
    }

    /**
     * Generiert die vollständige Subsektionen-Generatoren-Matrix für das Angebot.
     * Gewährleistet 100%ige Abwärts- und Datenbank-Kompatibilität zu crm-pdf-sections.php.
     *
     * @param object $course CRM_Model
     * @param array $context ['angebotsnummer' => ..., 'salutation_name' => ..., 'angebot_intro' => ..., 'angebot_gruss' => ...]
     * @return array
     */
    public static function get_subsections_generators(object $course, array $context = []): array
    {
        $angebotsnummer  = $context['angebotsnummer'] ?? ('A_' . ($course->entry_id ?? '0') . '-' . ($course->post_id ?? '0'));
        $salutation_name = $context['salutation_name'] ?? '';
        $angebot_intro   = $context['angebot_intro'] ?? '';
        $angebot_gruss   = $context['angebot_gruss'] ?? '';

        return [
            'deckblatt' => [
                'empfaenger'          => self::render_empfaenger($course, $angebotsnummer),
                'titel'               => $course->get_pdf_title($course->titel_short, 'Angebot'),
                'deckblatt_titel'     => $course->get_pdf_title($course->titel_short, 'Angebot'),
                'anrede_text'         => self::render_anrede_intro($salutation_name, $angebot_intro),
                'gruss'               => self::render_gruss($angebot_gruss),
                'signatur'            => $course->signatur,
                'ps'                  => (!empty($course->ps) ? $course->ps : ''),
                'hinweis_nachstehend' => self::render_hinweis_nachstehend(),
            ],

            'veranstaltung' => [
                'veranstaltung_titel' => $course->get_pdf_title($course->titel_short, 'Veranstaltungsinformationen'),
                'titel'               => $course->get_pdf_title($course->titel_short, 'Veranstaltungsinformationen'),
                'zeitraum'            => self::render_zeitraum($course),
                'lehreinheiten'       => self::render_lehreinheiten($course),
                'module'              => $course->module_gliederung_html ?: $course->module_html,
                'zeiteinteilung'      => $course->zeiteinteilung_html,
            ],

            'abschluss' => [
                'abschluss_titel'   => $course->get_pdf_title($course->titel_short, 'Ihr persönlicher Abschluss'),
                'titel'             => $course->get_pdf_title($course->titel_short, 'Ihr persönlicher Abschluss'),
                'abschluss_box'     => self::render_abschluss_box($course),
                'voraussetzungen'   => self::render_voraussetzungen($course),
                'zertifizierungen'  => self::render_zertifizierungen($course),
                'ort_durchfuehrung' => self::render_ort_durchfuehrung($course),
                'beratung'          => $course->beratung_email,
            ],

            'kosten' => [
                'kosten_titel'   => $course->get_pdf_title('Kursgebühr inkl. optionale Zertifizierungen', 'Ihre Investition'),
                'titel'          => $course->get_pdf_title('Kursgebühr inkl. optionale Zertifizierungen', 'Ihre Investition'),
                'preistabelle'   => $course->get_gesamt_kosten_html(),
                'gueltigkeit'    => self::render_gueltigkeit($course),
                'bankverbindung' => $course->bankverbindung,
            ],

            'anmeldung' => [
                'anmeldung_titel' => $course->get_pdf_title($course->title, 'ANMELDUNG'),
                'titel'           => $course->get_pdf_title($course->title, 'ANMELDUNG'),
                'kundendaten'     => $course->get_contact_info_html(),
                'agb'             => $course->anmeldung_agb,
                'signatur_kunde'  => $course->signatur,
                'anhang_hinweise' => self::render_anhang_hinweise(),
            ],

            'inhalte' => [
                'inhalte_titel' => $course->get_pdf_title('Details zu den Inhalten', 'Anhang 1'),
                'titel'         => $course->get_pdf_title('Details zu den Inhalten', 'Anhang 1'),
                'curriculum'    => $course->inhalte,
            ],

            'zusatzleistungen' => [
                'zusatzleistungen_titel' => $course->get_pdf_title('Exklusive Zusatzleistungen', 'Anhang 2'),
                'titel'                  => $course->get_pdf_title('Exklusive Zusatzleistungen', 'Anhang 2'),
                'garantien'              => $course->garantie,
            ],
        ];
    }
}
