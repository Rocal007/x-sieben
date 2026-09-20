<?php
/**
 * Element: offer-voraussetzungen.php
 *
 * Rendert die Teilnahmevoraussetzungen mit Gefahren-/Achtungs-Icon.
 * Erwartet:
 *  - $course (CRM_Model)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$danger_icon = $course->danger_icon ?? '';
$voraussetzungen_html = $course->voraussetzungen_html ?? '';

$divider = function_exists('crm_pdf_divider') 
    ? crm_pdf_divider('#cbd5e1', 16, 20) 
    : '<div style="height:18pt; border-bottom:1px solid #cbd5e1;">&nbsp;</div>';

return '<table class="text" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
    <tr>
        <td style="width:5%; vertical-align:middle;">' . $danger_icon . '</td>
        <td style="width:95%; vertical-align:middle; font-size:11.5pt;"><strong>Voraussetzungen</strong></td>
    </tr>
    <tr>
        <td colspan="2" style="height:8pt; font-size:8pt; line-height:8pt;">&nbsp;</td>
    </tr>
    <tr>
        <td colspan="2">' . $voraussetzungen_html . '</td>
    </tr>
</table>' . $divider;
