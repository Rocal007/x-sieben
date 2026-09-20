<?php
/**
 * Element: kb-institut.php
 *
 * Rendert die Kursinstitut-Sektion für die Kurszeitenbestätigung.
 * Erwartet:
 *  - $kb_institut (string)
 *  - $kb_ort (string)
 *  - $display_title (string)
 *  - $startdatum (string)
 *  - $enddatum (string)
 *  - $sub_key (optional string für einzelne Zeilen)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$subs = [
    'name'        => '<tr><td style="font-size:10pt; padding-bottom: 3pt; border-bottom: 1px solid black;">Name des Kursinstituts: ' . htmlspecialchars($kb_institut ?? '', ENT_QUOTES, 'UTF-8') . '</td></tr><tr><td style="font-size: 5pt;">&nbsp;</td></tr>',
    'ort'         => '<tr><td style="font-size:10pt; padding-bottom: 3pt; border-bottom: 1px solid black;">Schulungsort (Adresse): ' . htmlspecialchars($kb_ort ?? '', ENT_QUOTES, 'UTF-8') . '</td></tr><tr><td style="font-size: 5pt;">&nbsp;</td></tr>',
    'bezeichnung' => '<tr><td style="font-size:10pt; padding-bottom: 3pt; border-bottom: 1px solid black;">Kursbezeichnung: ' . htmlspecialchars($display_title ?? '', ENT_QUOTES, 'UTF-8') . '</td></tr><tr><td style="font-size: 5pt;">&nbsp;</td></tr>',
    'zeitraum'    => '<tr><td style="font-size:10pt; padding-bottom: 3pt; border-bottom: 1px solid black;">Kurs von-bis: ' . htmlspecialchars($startdatum ?? '', ENT_QUOTES, 'UTF-8') . ' bis ' . htmlspecialchars($enddatum ?? '', ENT_QUOTES, 'UTF-8') . '</td></tr><tr><td style="font-size: 5pt;">&nbsp;</td></tr>',
];

if (!empty($sub_key)) {
    return $subs[$sub_key] ?? '';
}

if (!empty($return_subs_array)) {
    return $subs;
}

return '<table cellspacing="0" cellpadding="0" style="width: 100%;">
    <tr>
        <td style="font-size:11pt; font-weight: bold; padding-bottom: 6px;">
            Kursinstitut
        </td>
    </tr>' . implode('', $subs) . '
</table>
<div style="font-size:14pt">&nbsp;</div>';
