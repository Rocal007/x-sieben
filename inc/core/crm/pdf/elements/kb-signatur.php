<?php
/**
 * Element: kb-signatur.php
 *
 * Rendert die Stampiglie und Unterschriftsfelder für Kursinstitut und Kunde.
 * Erwartet:
 *  - $stempel_file (string)
 *  - $kb_sig_institut (string)
 *  - $kb_sig_kunde (string)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$stempel_file    = $stempel_file ?? '';
$kb_sig_institut = $kb_sig_institut ?? '';
$kb_sig_kunde    = $kb_sig_kunde ?? '';

return '<table cellspacing="0" cellpadding="0" style="width: 100%;">
    <tr>
        <td style="width: 46%; vertical-align: bottom;">
            <img src="' . esc_attr($stempel_file) . '" width="180px">
        </td>
        <td style="width: 8%;"></td>
        <td style="width: 46%; vertical-align: bottom;">
            &nbsp;
        </td>
    </tr>
    <tr>
        <td style="width: 46%; vertical-align: top;">
            <div style="border-top: 1px solid black; font-size: 2pt;">&nbsp;</div>
            <span style="font-size: 9pt;">' . $kb_sig_institut . '</span>
        </td>
        <td style="width: 8%;"></td>
        <td style="width: 46%; vertical-align: top;">
            <div style="border-top: 1px solid black; font-size: 2pt;">&nbsp;</div>
            <span style="font-size: 9pt;">' . $kb_sig_kunde . '</span>
        </td>
    </tr>
</table>';
