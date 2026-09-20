<?php
/**
 * Element: tb-zeitraum.php
 *
 * Rendert den Ausbildungszeitraum (vom [Start] bis [Ende] bei).
 * Erwartet:
 *  - $start_datum (string)
 *  - $end_datum (string)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$start = !empty($start_datum) ? htmlspecialchars($start_datum, ENT_QUOTES, 'UTF-8') : date('d.m.Y');
$end   = !empty($end_datum) ? htmlspecialchars($end_datum, ENT_QUOTES, 'UTF-8') : date('d.m.Y');

return '<table cellpadding="0" cellspacing="0" style="width: 100%;">
    <tr>
        <td style="width: 8%; vertical-align: middle; font-size:9.5pt;">vom </td>
        <td style="width: 26%;">
            <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5;">
                <span> ' . $start . '</span>
            </div>
        </td>
        <td style="width: 3%;"></td>
        <td style="width: 6%; vertical-align: middle; font-size:9.5pt;">bis </td>
        <td style="width: 26%;">
            <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5;">
                <span> ' . $end . '</span>
            </div>
        </td>
        <td style="width: 3%;"></td>
        <td style="width: 28%; vertical-align: middle; font-size:9.5pt;">bei</td>
    </tr>
</table>
<div style="font-size:6pt">&nbsp;</div>';
