<?php
/**
 * Element: tb-ausbildungsstaette.php
 *
 * Rendert die Box für Ausbildungsstätte und Schulungsort.
 * Erwartet:
 *  - $tb_betrieb_name (string)
 *  - $tb_betrieb_str (string)
 *  - $tb_betrieb_plz (string)
 *  - $tb_betrieb_ort (string)
 *  - $tb_ort_str (string)
 *  - $tb_ort_plz (string)
 *  - $tb_ort_ort (string)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$b_name = !empty($tb_betrieb_name) ? htmlspecialchars($tb_betrieb_name, ENT_QUOTES, 'UTF-8') : '&nbsp;';
$b_str  = !empty($tb_betrieb_str)  ? htmlspecialchars($tb_betrieb_str, ENT_QUOTES, 'UTF-8')  : '&nbsp;';
$b_plz  = !empty($tb_betrieb_plz)  ? htmlspecialchars($tb_betrieb_plz, ENT_QUOTES, 'UTF-8')  : '&nbsp;';
$b_ort  = !empty($tb_betrieb_ort)  ? htmlspecialchars($tb_betrieb_ort, ENT_QUOTES, 'UTF-8')  : '&nbsp;';

$o_str  = !empty($tb_ort_str) ? htmlspecialchars($tb_ort_str, ENT_QUOTES, 'UTF-8') : '&nbsp;';
$o_plz  = !empty($tb_ort_plz) ? htmlspecialchars($tb_ort_plz, ENT_QUOTES, 'UTF-8') : '&nbsp;';
$o_ort  = !empty($tb_ort_ort) ? htmlspecialchars($tb_ort_ort, ENT_QUOTES, 'UTF-8') : '&nbsp;';

return '<div style="width: 100%; border: 2px solid black;">
    <div style="font-size:3pt">&nbsp;</div>
    <table cellpadding="2" cellspacing="0" style="width: 100%;">
        <tr>
            <td style="width: 2%"></td>
            <td style="width: 96%">
                <span style="font-size:8pt;">Bezeichnung des Betriebes/der Ausbildungseinrichtung</span>
                <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                    <span> ' . $b_name . '</span>
                </div>
            </td>
            <td style="width: 2%"></td>
        </tr>
        <tr>
            <td style="width: 2%"></td>
            <td style="width: 96%">
                <span style="font-size:8pt;">Adresse des Betriebes (Straße, Hausnummer, Stiege, Türnummer)</span>
                <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                    <span> ' . $b_str . '</span>
                </div>
            </td>
            <td style="width: 2%"></td>
        </tr>
    </table>
    <table cellpadding="2" cellspacing="0" style="width: 100%;">
        <tr>
            <td style="width: 2%"></td>
            <td style="width: 26%">
                <span style="font-size:8pt;">Postleitzahl</span>
                <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                    <span> ' . $b_plz . '</span>
                </div>
            </td>
            <td style="width: 2%"></td>
            <td style="width: 68%">
                <span style="font-size:8pt;">Ort</span>
                <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                    <span> ' . $b_ort . '</span>
                </div>
            </td>
            <td style="width: 2%"></td>
        </tr>
    </table>
    <table cellpadding="2" cellspacing="0" style="width: 100%;">
        <tr>
            <td style="width: 2%"></td>
            <td style="width: 96%">
                <span style="font-size:8pt;">Adresse des Schulungsortes (Straße, Hausnummer, Stiege, Türnummer)</span>
                <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                    <span> ' . $o_str . '</span>
                </div>
            </td>
            <td style="width: 2%"></td>
        </tr>
    </table>
    <table cellpadding="2" cellspacing="0" style="width: 100%;">
        <tr>
            <td style="width: 2%"></td>
            <td style="width: 26%">
                <span style="font-size:8pt;">Postleitzahl</span>
                <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                    <span> ' . $o_plz . '</span>
                </div>
            </td>
            <td style="width: 2%"></td>
            <td style="width: 68%">
                <span style="font-size:8pt;">Ort</span>
                <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                    <span> ' . $o_ort . '</span>
                </div>
            </td>
            <td style="width: 2%"></td>
        </tr>
    </table>
    <div style="font-size:3pt">&nbsp;</div>
</div>
<div style="font-size:8pt">&nbsp;</div>';
