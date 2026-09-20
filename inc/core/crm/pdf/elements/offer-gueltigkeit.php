<?php
/**
 * Element: offer-gueltigkeit.php
 *
 * Rendert die Gültigkeitsklausel für das Angebot mit Trennlinie.
 * Erwartet:
 *  - $course (CRM_Model)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$expire_date = $course->expire ?? '';

$divider = function_exists('crm_pdf_divider') 
    ? crm_pdf_divider('#cbd5e1', 12, 16) 
    : '<div style="height:14pt; border-bottom:1px solid #cbd5e1;">&nbsp;</div>';

return '<table class="text" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td style="font-size: 10.5pt;"><strong>ANGEBOT GÜLTIG</strong> bis max. Gruppengrösse erreicht bzw.: <span> ' . esc_html($expire_date) . '</span></td>
    </tr>
</table>' . $divider;
