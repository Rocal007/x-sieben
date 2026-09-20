<?php
/**
 * Element: offer-empfaenger.php
 *
 * Rendert das Empfänger- und Angebotsdaten-Modul (Deckblatt).
 * Erwartet:
 *  - $course (CRM_Model)
 *  - $angebotsnummer (string)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$postal_address = method_exists($course, 'format_postal_address') 
    ? $course->format_postal_address('A', true) 
    : '';

$current_date = $course->current ?? date('d.m.Y');
$expire_date  = $course->expire ?? '';

return '<table class="text" cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin-bottom: 2pt;">
    <tr>
        <td style="vertical-align:top; width: 55%; font-size: 9pt; line-height: 12.5pt; color: #1e293b;">' . $postal_address . '</td>
        <td style="vertical-align:top; text-align: right; font-size: 8.5pt; line-height: 12.5pt; width: 45%; color: #334155;">
            Angebotsnummer: ' . esc_html($angebotsnummer) . '<br>Angebotsdatum: ' . esc_html($current_date) . '<br>Angebot gültig bis: ' . esc_html($expire_date) . '
        </td>
    </tr>
</table>';
