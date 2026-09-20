<?php
/**
 * Element: invoice-empfaenger.php
 *
 * Rendert Rechnungsempfänger-Box (Anschrift, SV-Nummer, Zahlungsziel) und Veranstaltungsreferenz.
 * Erwartet:
 *  - $postal_address_html (string HTML)
 *  - $svr (string|int)
 *  - $due_date (string)
 *  - $clean_course_title (string)
 *  - $sub_key (optional string)
 *  - $return_subs_array (optional bool)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$addr_html    = $postal_address_html ?? '';
$svr_val      = !empty($svr) ? '<strong>SV-Nummer:</strong> ' . htmlspecialchars((string)$svr, ENT_QUOTES, 'UTF-8') . '<br>' : '';
$due          = htmlspecialchars($due_date ?? '', ENT_QUOTES, 'UTF-8');
$course_title = htmlspecialchars($clean_course_title ?? '', ENT_QUOTES, 'UTF-8');

$subs = [
    'kundendaten' => '<table cellspacing="0" cellpadding="4" style="width: 100%; border: 1px solid #e2e8f0; background-color: #f8fafc; font-size: 9pt;">
        <tr>
            <td style="width: 62%; vertical-align: top; font-size: 9pt; line-height: 13pt;">
                <span style="color: #007C90; font-size: 7.5pt; font-weight: bold; text-transform: uppercase;">Rechnungsempfänger:</span><br>
                ' . $addr_html . '
            </td>
            <td style="width: 38%; vertical-align: top; text-align: right; font-size: 8.5pt; color: #334155; line-height: 1.4;">
                ' . $svr_val . '
                <strong>Zahlungsziel:</strong> ' . $due . '
            </td>
        </tr>
    </table>',

    'veranstaltung_ref' => '<div style="font-size: 4pt">&nbsp;</div>
    <div style="font-size: 9pt; color: #334155;">
        <strong>Veranstaltung:</strong> ' . $course_title . '
    </div>
    <div style="font-size: 6pt">&nbsp;</div>',
];

if (!empty($sub_key)) {
    return $subs[$sub_key] ?? '';
}

if (!empty($return_subs_array)) {
    return $subs;
}

return implode('', $subs);
