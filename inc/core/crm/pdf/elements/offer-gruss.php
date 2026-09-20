<?php
/**
 * Element: offer-gruss.php
 *
 * Rendert die Grußformel (Deckblatt).
 * Erwartet:
 *  - $angebot_gruss (string, bereinigtes HTML)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$gruss = $angebot_gruss ?? "Ich freue mich über Ihre Rückmeldung / Buchung.<br>\nMit freundlichen Grüßen,";

return '<table class="text" cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin-top: 2pt; margin-bottom: 2pt;">
    <tr>
        <td style="font-size: 9pt; line-height: 12.5pt; color: #1e293b;">' . $gruss . '</td>
    </tr>
</table>';
