<?php
/**
 * Element: tb-titel.php
 *
 * Rendert Titel und Einleitungszeile der Teilnahmebestätigung.
 * Erwartet:
 *  - $tb_title (string)
 *  - $tb_einleitung (string)
 *  - $sub_key (optional string)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$title      = !empty($tb_title) ? htmlspecialchars($tb_title, ENT_QUOTES, 'UTF-8') : 'Teilnahmebestätigung';
$einleitung = !empty($tb_einleitung) ? htmlspecialchars($tb_einleitung, ENT_QUOTES, 'UTF-8') : 'Wir bestätigen, dass';

$subs = [
    'haupttitel' => '<div style="font-size:4pt">&nbsp;</div>
    <table cellspacing="0" cellpadding="0" style="width: 100%;">
        <tr>
            <td style="font-size:14pt; font-weight: bold; line-height:1; text-align: center;">
                ' . $title . '
            </td>
        </tr>
    </table>
    <div style="font-size:6pt">&nbsp;</div>',

    'einleitung' => '<table cellspacing="0" cellpadding="0" style="width: 100%;">
        <tr>
            <td style="font-size:9.5pt; line-height: 1; text-align: left;">
                <span>' . $einleitung . '</span>
            </td>
        </tr>
    </table>
    <div style="font-size:5pt">&nbsp;</div>',
];

if (!empty($sub_key)) {
    return $subs[$sub_key] ?? '';
}

if (!empty($return_subs_array)) {
    return $subs;
}

return implode('', $subs);
