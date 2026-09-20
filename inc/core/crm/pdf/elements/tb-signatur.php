<?php
/**
 * Element: tb-signatur.php
 *
 * Rendert Datum und Unterschriftenblock der Teilnahmebestätigung.
 * Erwartet:
 *  - $tb_datum (string)
 *  - $tb_unterschrift (string)
 *  - $signatur_html (string HTML)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$datum        = !empty($tb_datum) ? htmlspecialchars($tb_datum, ENT_QUOTES, 'UTF-8') : '';
$zusatz       = !empty($tb_unterschrift) ? '<span style="font-size: 9.5pt;">' . htmlspecialchars($tb_unterschrift, ENT_QUOTES, 'UTF-8') . '</span><br>' : '';
$sig          = $signatur_html ?? '';

return '<table cellspacing="0" cellpadding="0" style="width: 100%;">
    <tr>
        <td style="width: 45%; vertical-align: top; font-size: 9.5pt;">
            ' . $datum . '
        </td>
        <td style="width: 55%; vertical-align: top;">
            ' . $zusatz . '
            ' . $sig . '
        </td>
    </tr>
</table>';
