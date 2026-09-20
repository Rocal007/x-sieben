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

$kursart_t  = $kursart_t ?? '';
$kursart_a  = $kursart_a ?? '';
$kursart_we = $kursart_we ?? '';

return '<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid black; font-size: 10pt;">
    <tr>
        <td style="width: 34%;">Kurstyp: Tageskurs ' . $kursart_t . '</td>
        <td style="width: 33%;">Abendkurs ' . $kursart_a . '</td>
        <td style="width: 33%;">Wochenendkurs ' . $kursart_we . '</td>
    </tr>
</table>
<div style="font-size:20pt">&nbsp;</div>';
