<?php
/**
 * Element: diplom-titel.php
 *
 * Rendert Haupttitel "D I P L O M" und Absolventenname.
 * Erwartet:
 *  - $full_name_html (string HTML)
 *  - $sub_key (optional string)
 *  - $return_subs_array (optional bool)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$name = $full_name_html ?? '';

$subs = [
    'haupttitel'     => '<div style="font-size: 24pt; font-weight: bold; color: #007C90; letter-spacing: 5px;">D I P L O M</div><div style="font-size: 4pt;">&nbsp;</div>',
    'absolvent_name' => '<div style="font-size: 17pt; color: #0f172a;">' . $name . '</div><div style="font-size: 5pt;">&nbsp;</div>',
];

if (!empty($sub_key)) {
    return $subs[$sub_key] ?? '';
}

if (!empty($return_subs_array)) {
    return $subs;
}

return '<table cellspacing="0" cellpadding="0" style="width: 100%; text-align: center;"><tr><td>' . implode('', $subs) . '</td></tr></table>';
