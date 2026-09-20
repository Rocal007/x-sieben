<?php
/**
 * Class CRM_Pdf_Diplom_Elements
 *
 * Zentraler Element-Controller und Assembler für das Diplom-PDF.
 *
 * @package X_SIEBEN_CRM
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_Pdf_Diplom_Elements
{
    public static function render_header(string $logo_path, string $wba_logo_html, ?string $sub_key = null): string
    {
        return require __DIR__ . '/diplom-header.php';
    }

    public static function get_header_subs(string $logo_path, string $wba_logo_html): array
    {
        $return_subs_array = true;
        return require __DIR__ . '/diplom-header.php';
    }

    public static function render_titel(string $full_name_html, ?string $sub_key = null): string
    {
        return require __DIR__ . '/diplom-titel.php';
    }

    public static function get_titel_subs(string $full_name_html): array
    {
        $return_subs_array = true;
        return require __DIR__ . '/diplom-titel.php';
    }

    public static function render_lehrgang(
        string $kurstyp_phrase,
        string $main_title_upper,
        string $subtitle_upper,
        int $anzahl_le,
        string $start_formatted,
        string $end_formatted,
        ?string $sub_key = null
    ): string {
        return require __DIR__ . '/diplom-lehrgang.php';
    }

    public static function get_lehrgang_subs(
        string $kurstyp_phrase,
        string $main_title_upper,
        string $subtitle_upper,
        int $anzahl_le,
        string $start_formatted,
        string $end_formatted
    ): array {
        $return_subs_array = true;
        return require __DIR__ . '/diplom-lehrgang.php';
    }

    public static function render_abschluss(string $exam_suffix, string $succ_text, ?string $sub_key = null): string
    {
        return require __DIR__ . '/diplom-abschluss.php';
    }

    public static function get_abschluss_subs(string $exam_suffix, string $succ_text): array
    {
        $return_subs_array = true;
        return require __DIR__ . '/diplom-abschluss.php';
    }

    public static function render_beglaubigung(
        string $diplom_nr,
        string $issue_date,
        string $stempel_path,
        ?string $sub_key = null
    ): string {
        return require __DIR__ . '/diplom-beglaubigung.php';
    }

    public static function get_beglaubigung_subs(
        string $diplom_nr,
        string $issue_date,
        string $stempel_path
    ): array {
        $return_subs_array = true;
        return require __DIR__ . '/diplom-beglaubigung.php';
    }

    public static function render_inhalte(string $clean_links, string $clean_rechts, ?string $sub_key = null): string
    {
        return require __DIR__ . '/diplom-inhalte.php';
    }

    public static function get_inhalte_subs(string $clean_links, string $clean_rechts): array
    {
        $return_subs_array = true;
        return require __DIR__ . '/diplom-inhalte.php';
    }

    public static function render_guetesiegel(): string
    {
        return require __DIR__ . '/diplom-guetesiegel.php';
    }
}
