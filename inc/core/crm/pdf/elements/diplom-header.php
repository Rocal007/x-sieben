<?php
/**
 * Element: diplom-header.php
 *
 * Rendert die Kopfzeile des Diploms (X-SIEBEN Logo links, optionales WBA Logo rechts).
 * Erwartet:
 *  - $logo_path (string)
 *  - $wba_logo_html (string)
 *  - $sub_key (optional string)
 *  - $return_subs_array (optional bool)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$logo     = !empty($logo_path) ? esc_attr($logo_path) : '';
$wba_html = $wba_logo_html ?? '';

$subs = [
    'logo_links' => '<td style="width: 60%; text-align: left; vertical-align: top;"><img src="' . $logo . '" width="135"></td>',
    'logo_wba'   => '<td style="width: 40%; text-align: right; vertical-align: top;">' . $wba_html . '</td>',
];

if (!empty($sub_key)) {
    return $subs[$sub_key] ?? '';
}

if (!empty($return_subs_array)) {
    return $subs;
}

return '<table cellspacing="0" cellpadding="0" style="width: 100%;"><tr>' . implode('', $subs) . '</tr></table><div style="font-size: 8pt;">&nbsp;</div>';
