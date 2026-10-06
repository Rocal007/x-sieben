<?php
/**
 * Element: diplom-inhalte.php
 *
 * Rendert Ausbildungsinhalte (Titel und zweispaltige Modulübersicht).
 * Erwartet:
 *  - $clean_links (string HTML)
 *  - $clean_rechts (string HTML)
 *  - $sub_key (optional string)
 *  - $return_subs_array (optional bool)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$curriculum_body = $curriculum_html ?? '';
if (empty($curriculum_body)) {
    $links  = $clean_links ?? '';
    $rechts = $clean_rechts ?? '';
    if (!empty($links) || !empty($rechts)) {
        $curriculum_body = '
        <table cellspacing="0" cellpadding="0" style="width: 100%;">
            <tr>
                <td style="width: 48%; vertical-align: top; font-size: 9pt; line-height: 1.45; color: #1e293b;">
                    ' . $links . '
                </td>
                <td style="width: 4%;"></td>
                <td style="width: 48%; vertical-align: top; font-size: 9pt; line-height: 1.45; color: #1e293b;">
                    ' . $rechts . '
                </td>
            </tr>
        </table>';
    }
}

$subs = [
    'inhalte_titel'  => '<div style="font-size: 15pt; font-weight: bold; color: #007C90; letter-spacing: 1.5px; margin-bottom: 14px;">INHALTE DER AUSBILDUNG</div>',
    'inhalte_matrix' => '<div style="font-size: 9.5pt; color: #334155; line-height: 1.45;">' . $curriculum_body . '</div>',
];

if (!empty($sub_key)) {
    return $subs[$sub_key] ?? '';
}

if (!empty($return_subs_array)) {
    return $subs;
}

return implode('', $subs);
