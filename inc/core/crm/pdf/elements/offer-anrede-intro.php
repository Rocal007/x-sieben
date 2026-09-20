<?php
/**
 * Element: offer-anrede-intro.php
 *
 * Rendert die persönliche Anrede mit Einleitungstext (Deckblatt).
 * Erwartet:
 *  - $salutation_name (string)
 *  - $angebot_intro (string, bereinigtes HTML)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$salutation = !empty($salutation_name) ? esc_html($salutation_name) : 'Sehr geehrte Damen und Herren';
$intro      = $angebot_intro ?? '';

return '<table class="text" cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin-top: 3pt; margin-bottom: 3pt;">
    <tr>
        <td style="font-size: 9pt; line-height: 12.5pt; color: #1e293b;">' . $salutation . ',<br><div style="font-size:3pt; line-height:3pt;">&nbsp;</div>' . $intro . '</td>
    </tr>
</table>';
