<?php
/**
 * Class CRM_Pdf_Kb_Elements
 *
 * Zentraler Element-Controller und Assembler für die Kurszeitenbestätigung (KB).
 *
 * @package X_SIEBEN_CRM
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_Pdf_Kb_Elements
{
    public static function render_titel(string $title): string
    {
        return require __DIR__ . '/kb-titel.php';
    }

    public static function render_institut(
        string $kb_institut,
        string $kb_ort,
        string $display_title,
        string $startdatum,
        string $enddatum,
        ?string $sub_key = null
    ): string {
        return require __DIR__ . '/kb-institut.php';
    }

    public static function get_institut_subs(
        string $kb_institut,
        string $kb_ort,
        string $display_title,
        string $startdatum,
        string $enddatum
    ): array {
        $return_subs_array = true;
        return require __DIR__ . '/kb-institut.php';
    }

    public static function render_teilnehmer(
        string $vorname,
        string $nachname,
        $svr = '',
        ?string $sub_key = null
    ): string {
        return require __DIR__ . '/kb-teilnehmer.php';
    }

    public static function get_teilnehmer_subs(
        string $vorname,
        string $nachname,
        $svr = ''
    ): array {
        $return_subs_array = true;
        return require __DIR__ . '/kb-teilnehmer.php';
    }

    public static function render_kurstyp(string $kursart_t, string $kursart_a, string $kursart_we): string
    {
        return require __DIR__ . '/kb-kurstyp.php';
    }

    public static function render_kurszeiten(array $kurszeiten, array $selbststudium): string
    {
        return require __DIR__ . '/kb-kurszeiten.php';
    }

    public static function render_hinweis(string $kb_hinweis): string
    {
        return require __DIR__ . '/kb-hinweis.php';
    }

    public static function render_signatur(string $stempel_file, string $kb_sig_institut, string $kb_sig_kunde): string
    {
        return require __DIR__ . '/kb-signatur.php';
    }
}
