<?php
/**
 * Element: diplom-beglaubigung.php
 *
 * Rendert Diplom-Nummer, Ausstellungsort/-datum, Stampiglie und Institutsleiter-Signatur.
 * Erwartet:
 *  - $diplom_nr (string)
 *  - $issue_date (string)
 *  - $stempel_path (string)
 *  - $sub_key (optional string)
 *  - $return_subs_array (optional bool)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$nr      = $diplom_nr ?? '';
$date    = $issue_date ?? '';
$stempel = !empty($stempel_path) ? esc_attr($stempel_path) : '';

$subs = [
    'diplom_nr'               => '<div style="font-size: 7.5pt; font-weight: bold; color: #334155; letter-spacing: 0.5px;">DIPLOM-NUMMER ' . $nr . ' - WIEN, ' . $date . '</div><div style="font-size: 4pt;">&nbsp;</div>',
    'stampiglie_unterschrift' => '<div style="font-size: 7.5pt; font-weight: bold; color: #0f172a;">X SIEBEN WIRTSCHAFTSTRAINING GmbH</div><div style="padding-top: 1px; padding-bottom: 1px;"><img src="' . $stempel . '" width="120"></div><div style="font-size: 8pt; font-weight: bold; color: #0f172a; line-height: 1.1;">Mag. Dr. Johannes Gasberger</div><div style="font-size: 7pt; color: #475569;">Institutsleiter</div><div style="font-size: 4pt;">&nbsp;</div>',
];

if (!empty($sub_key)) {
    return $subs[$sub_key] ?? '';
}

if (!empty($return_subs_array)) {
    return $subs;
}

return '<table cellspacing="0" cellpadding="0" style="width: 100%; text-align: center;"><tr><td>' . implode('', $subs) . '</td></tr></table>';
