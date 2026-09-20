<?php
/**
 * Class CRM_Pdf_Tb_Elements
 *
 * Zentraler Element-Controller und Assembler für die Teilnahmebestätigung (TB).
 *
 * @package X_SIEBEN_CRM
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_Pdf_Tb_Elements
{
    public static function render_titel(?string $tb_title = '', ?string $tb_einleitung = '', ?string $sub_key = null): string
    {
        $tb_title = (string)$tb_title;
        $tb_einleitung = (string)$tb_einleitung;
        return require __DIR__ . '/tb-titel.php';
    }

    public static function get_titel_subs(?string $tb_title = '', ?string $tb_einleitung = ''): array
    {
        $tb_title = (string)$tb_title;
        $tb_einleitung = (string)$tb_einleitung;
        $return_subs_array = true;
        return require __DIR__ . '/tb-titel.php';
    }

    public static function render_teilnehmer(
        ?string $tn_name = '',
        ?string $tn_svr = '',
        ?string $tn_adresse = '',
        ?string $tn_plz = '',
        ?string $tn_ort = ''
    ): string {
        $tn_name = (string)$tn_name;
        $tn_svr = (string)$tn_svr;
        $tn_adresse = (string)$tn_adresse;
        $tn_plz = (string)$tn_plz;
        $tn_ort = (string)$tn_ort;
        return require __DIR__ . '/tb-teilnehmer.php';
    }

    public static function render_zeitraum(?string $start_datum = '', ?string $end_datum = ''): string
    {
        $start_datum = (string)$start_datum;
        $end_datum = (string)$end_datum;
        return require __DIR__ . '/tb-zeitraum.php';
    }

    public static function render_ausbildungsstaette(
        ?string $tb_betrieb_name = '',
        ?string $tb_betrieb_str = '',
        ?string $tb_betrieb_plz = '',
        ?string $tb_betrieb_ort = '',
        ?string $tb_ort_str = '',
        ?string $tb_ort_plz = '',
        ?string $tb_ort_ort = ''
    ): string {
        $tb_betrieb_name = (string)$tb_betrieb_name;
        $tb_betrieb_str = (string)$tb_betrieb_str;
        $tb_betrieb_plz = (string)$tb_betrieb_plz;
        $tb_betrieb_ort = (string)$tb_betrieb_ort;
        $tb_ort_str = (string)$tb_ort_str;
        $tb_ort_plz = (string)$tb_ort_plz;
        $tb_ort_ort = (string)$tb_ort_ort;
        return require __DIR__ . '/tb-ausbildungsstaette.php';
    }

    public static function render_teilnahme(?string $tb_teilnahme = ''): string
    {
        $tb_teilnahme = (string)$tb_teilnahme;
        return require __DIR__ . '/tb-teilnahme.php';
    }

    public static function render_signatur(
        ?string $tb_datum = '',
        ?string $tb_unterschrift = '',
        ?string $signatur_html = ''
    ): string {
        $tb_datum = (string)$tb_datum;
        $tb_unterschrift = (string)$tb_unterschrift;
        $signatur_html = (string)$signatur_html;
        return require __DIR__ . '/tb-signatur.php';
    }
}
