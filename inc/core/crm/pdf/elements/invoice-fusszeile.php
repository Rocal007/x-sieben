<?php
/**
 * Element: invoice-fusszeile.php
 *
 * Rendert die offizielle Firmenfußzeile der Honorarnote (Aussteller-Info, Geschäftsführung, Gerichtsstand, UID, Seminarzentrum).
 * Erwartet:
 *  - $company_name (string)
 *  - $company_management (string)
 *  - $company_court (string)
 *  - $company_fn (string)
 *  - $company_uid (string)
 *  - $location_wien (string)
 *  - $company_address (string)
 *  - $company_phone (string)
 *  - $company_email (string)
 *  - $sub_key (optional string)
 *  - $return_subs_array (optional bool)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$c_name  = htmlspecialchars($company_name ?? '', ENT_QUOTES, 'UTF-8');
$c_mgmt  = htmlspecialchars($company_management ?? '', ENT_QUOTES, 'UTF-8');
$c_court = htmlspecialchars($company_court ?? '', ENT_QUOTES, 'UTF-8');
$c_fn    = htmlspecialchars($company_fn ?? '', ENT_QUOTES, 'UTF-8');
$c_uid   = htmlspecialchars($company_uid ?? '', ENT_QUOTES, 'UTF-8');
$l_wien  = htmlspecialchars($location_wien ?? '', ENT_QUOTES, 'UTF-8');
$c_addr  = htmlspecialchars($company_address ?? '', ENT_QUOTES, 'UTF-8');
$c_phone = htmlspecialchars($company_phone ?? '', ENT_QUOTES, 'UTF-8');
$c_email = htmlspecialchars($company_email ?? '', ENT_QUOTES, 'UTF-8');

$table = '<table cellpadding="0" cellspacing="0" style="width: 100%; border-top: 1px solid #cbd5e1; padding-top: 5px; font-size: 7.5pt; color: #64748b; line-height: 1.35;">
    <tr>
        <td style="width: 55%;">
            <strong>' . $c_name . '</strong><br>
            Geschäftsführung: ' . $c_mgmt . '<br>
            Firmenbuchgericht: ' . $c_court . ' | ' . $c_fn . ' | UID: ' . $c_uid . '
        </td>
        <td style="width: 45%; text-align: right;">
            <strong>Seminarzentrum:</strong> ' . $l_wien . '<br>
            <strong>Zentrale:</strong> ' . $c_addr . '<br>
            Tel: ' . $c_phone . ' | ' . $c_email . '
        </td>
    </tr>
</table>';

$subs = [
    'aussteller_info' => $table,
];

if (!empty($sub_key)) {
    return $subs[$sub_key] ?? '';
}

if (!empty($return_subs_array)) {
    return $subs;
}

return $table;
