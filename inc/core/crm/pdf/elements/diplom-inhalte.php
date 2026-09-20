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

$links  = $clean_links ?? '';
$rechts = $clean_rechts ?? '';

$inhalte_html = '
<table cellspacing="0" cellpadding="0" style="width: 100%;">
    <tr>
        <td style="width: 48%; vertical-align: top; text-align: center; font-size: 6.2pt; line-height: 1.2; color: #1e293b;">
            ' . $links . '
        </td>
        <td style="width: 4%;"></td>
        <td style="width: 48%; vertical-align: top; text-align: center; font-size: 6.2pt; line-height: 1.2; color: #1e293b;">
            ' . $rechts . '
        </td>
    </tr>
</table>';

$subs = [
    'inhalte_titel'  => '<div style="font-size: 4pt;">&nbsp;</div><div style="text-align: center; font-size: 8pt; font-weight: bold; color: #0f172a; letter-spacing: 3px;">A U S B I L D U N G S I N H A L T E</div><div style="font-size: 3pt;">&nbsp;</div>',
    'inhalte_matrix' => $inhalte_html,
];

if (!empty($sub_key)) {
    return $subs[$sub_key] ?? '';
}

if (!empty($return_subs_array)) {
    return $subs;
}

return implode('', $subs);
