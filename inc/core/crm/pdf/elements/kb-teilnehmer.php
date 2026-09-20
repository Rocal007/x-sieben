<?php
/**
 * Element: kb-teilnehmer.php
 *
 * Rendert die KursteilnehmerIn-Sektion für die Kurszeitenbestätigung.
 * Erwartet:
 *  - $vorname (string)
 *  - $nachname (string)
 *  - $svr (string)
 *  - $sub_key (optional string)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$full_name = trim(($vorname ?? '') . ' ' . ($nachname ?? ''));
$svr_str   = !empty($svr) ? (' ' . htmlspecialchars((string)$svr, ENT_QUOTES, 'UTF-8')) : '';

$subs = [
    'name_svr_row' => '<tr>
        <td style="width: 50%; font-size:10pt; padding-bottom: 3pt; border-bottom: 1px solid black;">
            Name: ' . htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') . '
        </td>
        <td style="width: 4%;"></td>
        <td style="width: 46%; font-size:10pt; padding-bottom: 3pt; border-bottom: 1px solid black;">
            SV-Nummer:' . $svr_str . '
        </td>
    </tr>',
];

if (!empty($sub_key)) {
    return $subs[$sub_key] ?? '';
}

if (!empty($return_subs_array)) {
    return $subs;
}

return '<table cellspacing="0" cellpadding="0" style="width: 100%;">
    <tr>
        <td colspan="3" style="font-size:11pt; font-weight: bold; padding-bottom: 6px;">
            KursteilnehmerIn:
        </td>
    </tr>' . implode('', $subs) . '
</table>
<div style="font-size:20pt">&nbsp;</div>';
