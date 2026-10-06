<?php
/**
 * Element: diplom-lehrgang.php
 *
 * Rendert Lehrgangsdaten (Kurstyp, Titel, Untertitel, Lehreinheiten, Zeitraum).
 * Erwartet:
 *  - $kurstyp_phrase (string)
 *  - $main_title_upper (string)
 *  - $subtitle_upper (string)
 *  - $anzahl_le (int)
 *  - $start_formatted (string)
 *  - $end_formatted (string)
 *  - $sub_key (optional string)
 *  - $return_subs_array (optional bool)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$phrase   = esc_html($kurstyp_phrase ?? 'HAT DEN LEHRGANG');
$main     = esc_html($main_title_upper ?? '');
$sub_text = !empty($subtitle_upper) ? '<div style="font-size: 13pt; font-weight: bold; color: #0f172a; letter-spacing: 1px; margin-top: 3px;">' . esc_html($subtitle_upper) . '</div>' : '';
$le       = intval($anzahl_le ?? 0);
$start    = $start_formatted ?? '';
$end      = $end_formatted ?? '';
$verb_str = !empty($zeitraum_verb) ? (' ' . esc_html($zeitraum_verb)) : '';

$subs = [
    'lehrgang_titel'    => '<div style="font-size: 9pt; color: #64748b; letter-spacing: 2px;">' . $phrase . '</div><div style="font-size: 7pt;">&nbsp;</div><div style="font-size: 16.5pt; font-weight: bold; color: #0f172a; letter-spacing: 0.5px;">' . $main . '</div>' . $sub_text . '<div style="font-size: 9pt;">&nbsp;</div>',
    'lehrgang_zeitraum' => '<div style="font-size: 8.5pt; color: #475569; letter-spacing: 0.4px; line-height: 1.6;">' . $le . ' LEHREINHEITEN A’ JE 45 MINUTEN<br>IM ZEITRAUM VOM ' . $start . ' BIS ZUM ' . $end . $verb_str . '</div><div style="font-size: 9pt;">&nbsp;</div>',
];

if (!empty($sub_key)) {
    return $subs[$sub_key] ?? '';
}

if (!empty($return_subs_array)) {
    return $subs;
}

return '<table cellspacing="0" cellpadding="0" style="width: 100%; text-align: center;"><tr><td>' . implode('', $subs) . '</td></tr></table>';
