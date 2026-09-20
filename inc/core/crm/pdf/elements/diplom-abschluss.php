<?php
/**
 * Element: diplom-abschluss.php
 *
 * Rendert Prüfungsformel und Erfolgsgrad (z. B. ERFOLGREICH / MIT AUSGEZEICHNETEM ERFOLG).
 * Erwartet:
 *  - $exam_suffix (string)
 *  - $succ_text (string)
 *  - $sub_key (optional string)
 *  - $return_subs_array (optional bool)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$suffix = $exam_suffix ?? '';
$text   = esc_html($succ_text ?? 'ERFOLGREICH');

$subs = [
    'abschluss_formel' => '<div style="font-size: 7pt; color: #475569; letter-spacing: 0.3px; line-height: 1.35;">UND NACH POSITIVER BEGUTACHTUNG DER ABSCHLUSSARBEIT SOWIE ERFOLGREICHER<br>ABSOLVIERUNG DER SCHRIFTLICHEN ABSCHLUSSPRÜFUNG' . $suffix . '</div><div style="font-size: 4pt;">&nbsp;</div>',
    'erfolg_grad'      => '<div style="font-size: 13.5pt; font-weight: bold; color: #0f172a; letter-spacing: 1.5px;">' . $text . '</div><div style="font-size: 2pt;">&nbsp;</div><div style="font-size: 7.5pt; color: #64748b; letter-spacing: 1px;">ABGESCHLOSSEN.</div><div style="font-size: 4pt;">&nbsp;</div>',
];

if (!empty($sub_key)) {
    return $subs[$sub_key] ?? '';
}

if (!empty($return_subs_array)) {
    return $subs;
}

return '<table cellspacing="0" cellpadding="0" style="width: 100%; text-align: center;"><tr><td>' . implode('', $subs) . '</td></tr></table>';
