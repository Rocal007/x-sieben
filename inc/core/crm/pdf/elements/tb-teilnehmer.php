<?php
/**
 * Element: tb-teilnehmer.php
 *
 * Rendert die umrahmte Box für Kursteilnehmer (Teilnahmebestätigung).
 * Erwartet:
 *  - $tn_name (string)
 *  - $tn_svr (string)
 *  - $tn_adresse (string)
 *  - $tn_plz (string)
 *  - $tn_ort (string)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$name    = !empty($tn_name) ? htmlspecialchars($tn_name, ENT_QUOTES, 'UTF-8') : '&nbsp;';
$svr     = !empty($tn_svr) ? htmlspecialchars((string)$tn_svr, ENT_QUOTES, 'UTF-8') : '&nbsp;';
$adresse = !empty($tn_adresse) ? htmlspecialchars($tn_adresse, ENT_QUOTES, 'UTF-8') : '&nbsp;';
$plz     = !empty($tn_plz) ? htmlspecialchars($tn_plz, ENT_QUOTES, 'UTF-8') : '&nbsp;';
$ort     = !empty($tn_ort) ? htmlspecialchars($tn_ort, ENT_QUOTES, 'UTF-8') : '&nbsp;';

return '<div style="width: 100%; border: 2px solid black;">
    <div style="font-size:3pt">&nbsp;</div>
    <table cellpadding="2" cellspacing="0" style="width: 100%;">
        <tr>
            <td style="width: 2%"></td>
            <td style="width: 68%">
                <span style="font-size:8pt;">Vor- und Familien- /Nachname</span>
                <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                    <span> ' . $name . '</span>
                </div>
            </td>
            <td style="width: 2%"></td>
            <td style="width: 26%">
                <span style="font-size:8pt;">SV-Nummer</span>
                <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                    <span> ' . $svr . '</span>
                </div>
            </td>
            <td style="width: 2%"></td>
        </tr>
        <tr>
            <td style="width: 2%"></td>
            <td colspan="3">
                <span style="font-size:8pt;">Wohnadresse (Straße, Hausnummer, Stiege, Türnummer)</span>
                <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                    <span> ' . $adresse . '</span>
                </div>
            </td>
            <td style="width: 2%"></td>
        </tr>
        <tr>
            <td style="width: 2%"></td>
            <td style="width: 26%">
                <span style="font-size:8pt;">Postleitzahl</span>
                <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                    <span> ' . $plz . '</span>
                </div>
            </td>
            <td style="width: 2%"></td>
            <td style="width: 68%">
                <span style="font-size:8pt;">Ort</span>
                <div style="font-size:9.5pt; border: 1px solid black; line-height: 1.5">
                    <span> ' . $ort . '</span>
                </div>
            </td>
            <td style="width: 2%"></td>
        </tr>
    </table>
    <div style="font-size:3pt">&nbsp;</div>
</div>
<div style="font-size:6pt">&nbsp;</div>';
