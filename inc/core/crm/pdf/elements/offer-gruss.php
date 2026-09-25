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

$gruss = $angebot_gruss ?? "Ich freue mich über Ihre Rückmeldung / Buchung.<br><br>\nMit freundlichen Grüßen,";

// Sicherstellen, dass zwischen dem Satz "Ich freue mich über Ihre Rückmeldung / Buchung." und "Mit freundlichen Grüßen,"
// immer eine saubere Leerzeile (<br><br>) liegt, auch wenn im Originaltext nur ein einfacher Zeilenumbruch war
$gruss = preg_replace('/(Ich freue mich[^\n<]*)(?:<br\s*\/?>|\n)+\s*(Mit freundlichen Grüßen)/iu', "$1<br><br>$2", $gruss);

return '<table class="text" cellpadding="0" cellspacing="0" border="0" style="width: 100%; margin-top: 8pt; margin-bottom: 4pt;">
    <tr>
        <td style="font-size: 9pt; line-height: 12.5pt; color: #1e293b;">' . $gruss . '</td>
    </tr>
</table>';
