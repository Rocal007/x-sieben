<?php
/**
 * Element: offer-abschluss-box.php
 *
 * Rendert die Abschluss-Box mit Icon, Trennlinie und hervorgehobenem Kurstitel.
 * Erwartet:
 *  - $course (CRM_Model)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$abschluss_icon = $course->abschluss_icon ?? '';
$abschluss_text = $course->abschluss ?? '';

$divider = function_exists('crm_pdf_divider') 
    ? crm_pdf_divider('#cbd5e1', 12, 16) 
    : '<div style="height:14pt; border-bottom:1px solid #cbd5e1;">&nbsp;</div>';

return '<table class="text" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
    <tr>
        <td style="width:5%; vertical-align:middle;">' . $abschluss_icon . '</td>
        <td style="width:95%; vertical-align:middle; font-size:11.5pt;"><strong> IHR PERSÖNLICHER ABSCHLUSS</strong></td>
    </tr>
</table>'
. $divider .
'<table cellpadding="0" cellspacing="0" border="0" style="width:100%;">
    <tr>
        <td style="height:12pt; font-size:12pt; line-height:12pt;">&nbsp;</td>
    </tr>
    <tr>
        <td style="text-align:center; font-size:13.5pt; font-weight:bold; color:#0f172a; line-height:22pt;">
           ' . $abschluss_text . '
        </td>
    </tr>
    <tr>
        <td style="height:10pt; font-size:10pt; line-height:10pt;">&nbsp;</td>
    </tr>
    <tr>
        <td style="border-top: 1.5px solid #007C90; height:18pt; font-size:18pt; line-height:18pt;">&nbsp;</td>
    </tr>
</table>';
