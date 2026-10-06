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
    'haupttitel'     => '<div style="font-size: 8pt;">&nbsp;</div><div style="font-size: 26pt; font-weight: bold; color: #007C90; letter-spacing: 6px;">D I P L O M</div><div style="font-size: 9pt;">&nbsp;</div>',
    'absolvent_name' => '<div style="font-size: 21pt; color: #0f172a; letter-spacing: 0.5px;">' . $name . '</div><div style="font-size: 9pt;">&nbsp;</div>',
];

if (!empty($sub_key)) {
    return $subs[$sub_key] ?? '';
}

if (!empty($return_subs_array)) {
    return $subs;
}

return '<table cellspacing="0" cellpadding="0" style="width: 100%; text-align: center;"><tr><td>' . implode('', $subs) . '</td></tr></table>';
