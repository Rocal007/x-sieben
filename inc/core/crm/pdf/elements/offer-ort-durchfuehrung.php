<?php
/**
 * Element: offer-ort-durchfuehrung.php
 *
 * Rendert den Schulungsort und Durchführungsmodus.
 * Erwartet:
 *  - $course (CRM_Model)
 *  - $custom_html (optionaler benutzerdefinierter HTML-String)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$default_html = '<table class="text" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
    <tr>
        <td style="font-size: 11pt; color: #0f172a;"><strong>ORT:</strong> X SIEBEN Wirtschaftstraining, Rochusgasse 6 in 1030 Wien</td>
    </tr>
    <tr>
        <td style="height: 12pt; font-size: 12pt; line-height: 12pt;">&nbsp;</td>
    </tr>
    <tr>
        <td style="line-height: 18pt; color: #334155; font-size: 10pt;"><strong>Durchführung unserer Schulungen:</strong> Online Unterricht | vor Ort in unseren Veranstaltungsräumen | Blended Learning</td>
    </tr>
    <tr>
        <td style="height: 8pt; font-size: 8pt; line-height: 8pt;">&nbsp;</td>
    </tr>
    <tr>
        <td style="color: #64748b; font-size: 9.5pt; line-height: 15pt;"><em>Hinweis: Die Schulung wird bis zur TeilnehmerInnen-Anzahl von drei Personen adäquat verkürzt, wobei alle Inhalte vermittelt werden.</em></td>
    </tr>
</table>';

if (!empty($custom_html)) {
    return $custom_html;
}

if (isset($course) && is_object($course) && method_exists($course, 'get_crm_field_with_default')) {
    return $course->get_crm_field_with_default('Angebot - Ort und Durchführung', $default_html);
}

return $default_html;
