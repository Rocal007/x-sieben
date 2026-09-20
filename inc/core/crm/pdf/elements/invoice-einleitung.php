<?php
/**
 * Element: invoice-einleitung.php
 *
 * Rendert den Einleitungstext der Honorarnote.
 * Erwartet:
 *  - $hn_einleitung_val (string HTML)
 *  - $sub_key (optional string)
 *  - $return_subs_array (optional bool)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$text = $hn_einleitung_val ?? 'Hiermit stellen wir Ihnen folgende Leistungen in Rechnung:';

$subs = [
    'einleitungstext' => '<div style="font-size: 9.5pt; color: #0f172a; margin-bottom: 4px;">
        ' . $text . '
    </div>',
];

if (!empty($sub_key)) {
    return $subs[$sub_key] ?? '';
}

if (!empty($return_subs_array)) {
    return $subs;
}

return implode('', $subs);
