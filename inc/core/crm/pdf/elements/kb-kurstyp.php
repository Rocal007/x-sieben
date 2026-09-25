<?php
/**
 * Element: kb-kurstyp.php
 *
 * Rendert die Kurstyp-Auswahltabelle für die Kurszeitenbestätigung.
 * Erwartet:
 *  - $kursart_t (string)
 *  - $kursart_a (string)
 *  - $kursart_we (string)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$kursart_t        = $kursart_t ?? '';
$kursart_a        = $kursart_a ?? '';
$kursart_we       = $kursart_we ?? '';
$kursart_praesenz = $kursart_praesenz ?? '';
$kursart_online   = $kursart_online ?? '';

$box = function ($val) {
    return (!empty($val) && (strpos((string)$val, 'X') !== false || strpos((string)$val, 'x') !== false || $val === true || $val === '1' || $val === 1 || strtolower((string)$val) === 'ja')) ? '&#9746;' : '&#9633;';
};

return '<table cellspacing="0" cellpadding="4" style="width: 100%; border: 1px solid black; font-size: 9.5pt;">
    <tr>
        <td style="width: 16%; font-weight: bold; vertical-align: top;">Kurstyp:</td>
        <td style="width: 28%; vertical-align: top;">' . $box($kursart_t) . ' Tageskurs</td>
        <td style="width: 28%; vertical-align: top;">' . $box($kursart_a) . ' Abendkurs</td>
        <td style="width: 28%; vertical-align: top;">' . $box($kursart_we) . ' Wochenendkurs</td>
    </tr>
    <tr>
        <td></td>
        <td colspan="3" style="padding-top: 3px; font-size: 9pt;">
            ' . $box($kursart_praesenz) . ' Präsenzkurs / Webinar bzw. Blended Learning (Präsenz- u. Live-Online-Kurs)
        </td>
    </tr>
    <tr>
        <td></td>
        <td colspan="3" style="padding-top: 3px; font-size: 9pt;">
            ' . $box($kursart_online) . ' Online-Kurs (zeit- u. ortsunabhängiges selbständiges Erarbeiten von Inhalten)
        </td>
    </tr>
</table>
<div style="font-size:12pt">&nbsp;</div>';
