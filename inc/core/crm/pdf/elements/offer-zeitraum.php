<?php
/**
 * Element: offer-zeitraum.php
 *
 * Rendert den Veranstaltungszeitraum mit Kalender-Icon und Trennlinie.
 * Erwartet:
 *  - $course (CRM_Model)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$calender_icon = $course->calender_icon ?? '';
$start_datum   = $course->start_datum ?? '';
$end_datum     = $course->end_datum ?? '';

$divider = function_exists('crm_pdf_divider') 
    ? crm_pdf_divider('#cbd5e1', 12, 16) 
    : '<div style="height:14pt; border-bottom:1px solid #cbd5e1;">&nbsp;</div>';

return '<table cellpadding="0" cellspacing="0" border="0" style="font-size: 11pt; width:100%;">
    <tr>
        <td style="width:6%; vertical-align:middle;">' . $calender_icon . '</td>
        <td style="width:94%; vertical-align:middle;"><div style="font-size:3pt">&nbsp;</div> Vom <strong>' . esc_html($start_datum) . '</strong> bis einschließlich<strong> ' . esc_html($end_datum) . '</strong></td>
    </tr>
</table>' . $divider;
