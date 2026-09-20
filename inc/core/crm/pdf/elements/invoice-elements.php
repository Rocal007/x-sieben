<?php
/**
 * Class CRM_Pdf_Invoice_Elements
 *
 * Zentraler Element-Controller und Assembler für die Honorarnote / Rechnung.
 *
 * @package X_SIEBEN_CRM
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_Pdf_Invoice_Elements
{
    public static function render_header(
        string $logo_html,
        string $company_name,
        string $location_wien,
        string $company_address,
        string $company_email,
        string $company_website
    ): string {
        return require __DIR__ . '/invoice-header.php';
    }

    public static function render_titel(
        string $hn_title_val,
        string $invoice_num,
        string $invoice_date,
        ?string $sub_key = null
    ): string {
        return require __DIR__ . '/invoice-titel.php';
    }

    public static function get_titel_subs(
        string $hn_title_val,
        string $invoice_num,
        string $invoice_date
    ): array {
        $return_subs_array = true;
        return require __DIR__ . '/invoice-titel.php';
    }

    public static function render_empfaenger(
        string $postal_address_html,
        $svr,
        string $due_date,
        string $clean_course_title,
        ?string $sub_key = null
    ): string {
        return require __DIR__ . '/invoice-empfaenger.php';
    }

    public static function get_empfaenger_subs(
        string $postal_address_html,
        $svr,
        string $due_date,
        string $clean_course_title
    ): array {
        $return_subs_array = true;
        return require __DIR__ . '/invoice-empfaenger.php';
    }

    public static function render_einleitung(string $hn_einleitung_val, ?string $sub_key = null): string
    {
        return require __DIR__ . '/invoice-einleitung.php';
    }

    public static function get_einleitung_subs(string $hn_einleitung_val): array
    {
        $return_subs_array = true;
        return require __DIR__ . '/invoice-einleitung.php';
    }

    public static function render_positionen(
        string $clean_course_title,
        string $start_datum,
        string $end_datum,
        int $le_count,
        float $single_le,
        float $netto_kurs,
        string $cert_rows,
        float $total_netto,
        float $total_ust,
        float $total_brutto,
        ?string $sub_key = null
    ): string {
        return require __DIR__ . '/invoice-positionen.php';
    }

    public static function get_positionen_subs(
        string $clean_course_title,
        string $start_datum,
        string $end_datum,
        int $le_count,
        float $single_le,
        float $netto_kurs,
        string $cert_rows,
        float $total_netto,
        float $total_ust,
        float $total_brutto
    ): array {
        $return_subs_array = true;
        return require __DIR__ . '/invoice-positionen.php';
    }

    public static function render_zahlung(
        string $due_date,
        string $company_name,
        ?string $sub_key = null
    ): string {
        return require __DIR__ . '/invoice-zahlung.php';
    }

    public static function get_zahlung_subs(string $due_date, string $company_name): array
    {
        $return_subs_array = true;
        return require __DIR__ . '/invoice-zahlung.php';
    }

    public static function render_fusszeile(
        string $company_name,
        string $company_management,
        string $company_court,
        string $company_fn,
        string $company_uid,
        string $location_wien,
        string $company_address,
        string $company_phone,
        string $company_email,
        ?string $sub_key = null
    ): string {
        return require __DIR__ . '/invoice-fusszeile.php';
    }

    public static function get_fusszeile_subs(
        string $company_name,
        string $company_management,
        string $company_court,
        string $company_fn,
        string $company_uid,
        string $location_wien,
        string $company_address,
        string $company_phone,
        string $company_email
    ): array {
        $return_subs_array = true;
        return require __DIR__ . '/invoice-fusszeile.php';
    }
}
