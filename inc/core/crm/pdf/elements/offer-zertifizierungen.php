<?php
/**
 * Element: offer-zertifizierungen.php
 *
 * Rendert die Zertifizierungspartner-Logos mit Trennlinie.
 * Erwartet:
 *  - $course (CRM_Model)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$images_html = $course->zertifizierungen_images_html ?? '';

$divider = function_exists('crm_pdf_divider') 
    ? crm_pdf_divider('#cbd5e1', 18, 22) 
    : '<div style="height:20pt; border-bottom:1px solid #cbd5e1;">&nbsp;</div>';

return '<table class="text" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
    <tr>
        <td style="font-size:11.5pt; font-weight:bold; color:#0f172a;"><strong>ZERTIFIZIERUNGSPARTNER ...</strong></td>
    </tr>
    <tr>
        <td style="height:10pt; font-size:10pt; line-height:10pt;">&nbsp;</td>
    </tr>
    <tr>
        <td>' . $images_html . '</td>
    </tr>
</table>'
. $divider;
