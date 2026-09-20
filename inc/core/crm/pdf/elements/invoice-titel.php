<?php
/**
 * Element: invoice-titel.php
 *
 * Rendert Dokumententitel ("Honorarnote"), Rechnungsnummer und Rechnungsdatum.
 * Erwartet:
 *  - $hn_title_val (string)
 *  - $invoice_num (string)
 *  - $invoice_date (string)
 *  - $sub_key (optional string)
 *  - $return_subs_array (optional bool)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$title   = htmlspecialchars($hn_title_val ?? 'Honorarnote', ENT_QUOTES, 'UTF-8');
$inv_num = htmlspecialchars($invoice_num ?? '', ENT_QUOTES, 'UTF-8');
$date    = htmlspecialchars($invoice_date ?? '', ENT_QUOTES, 'UTF-8');

$subs = [
    'rechnung_titel' => '<table cellspacing="0" cellpadding="0" style="width: 100%;">
        <tr>
            <td style="font-size: 15pt; font-weight: bold; color: #007C90; text-align: center;">
                ' . $title . '
            </td>
        </tr>
    </table>
    <div style="font-size: 4pt">&nbsp;</div>',

    'nummer_datum' => '<table cellspacing="0" cellpadding="2" style="width: 100%; font-size: 8.5pt; border-bottom: 1px solid #007C90; padding-bottom: 3px;">
        <tr>
            <td style="width: 50%; color: #334155;"><strong>Rechnungsnummer:</strong> ' . $inv_num . '</td>
            <td style="width: 50%; text-align: right; color: #334155;"><strong>Datum:</strong> ' . $date . '</td>
        </tr>
    </table>
    <div style="font-size: 6pt">&nbsp;</div>',
];

if (!empty($sub_key)) {
    return $subs[$sub_key] ?? '';
}

if (!empty($return_subs_array)) {
    return $subs;
}

return implode('', $subs);
