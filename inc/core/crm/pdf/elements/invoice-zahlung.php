<?php
/**
 * Element: invoice-zahlung.php
 *
 * Rendert Zahlungsziel und Bankverbindung.
 * Erwartet:
 *  - $due_date (string)
 *  - $company_name (string)
 *  - $sub_key (optional string)
 *  - $return_subs_array (optional bool)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$due     = htmlspecialchars($due_date ?? '', ENT_QUOTES, 'UTF-8');
$company = htmlspecialchars($company_name ?? '', ENT_QUOTES, 'UTF-8');

$subs = [
    'zahlungsziel' => '<div style="font-size: 9pt; line-height: 1.4; color: #334155;">
        Bitte überweisen Sie den Betrag bis zum <strong>' . $due . '</strong> auf das Konto von ' . $company . '.
    </div>',

    'bankverbindung' => '<div style="font-size: 9pt; font-weight: bold; color: #007C90; margin-top: 3px;">
        IBAN: AT29 3293 7001 0012 5260 | BIC: RLNWATWWWRN (Raiffeisenlandesbank NÖ-Wien)
    </div>
    <div style="font-size: 8pt">&nbsp;</div>',
];

if (!empty($sub_key)) {
    return $subs[$sub_key] ?? '';
}

if (!empty($return_subs_array)) {
    return $subs;
}

return implode('', $subs);
