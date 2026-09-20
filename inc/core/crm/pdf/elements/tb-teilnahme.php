<?php
/**
 * Element: tb-teilnahme.php
 *
 * Rendert den formellen Teilnahmebestätigungstext (mit Lehrgang & LE).
 * Erwartet:
 *  - $tb_teilnahme (string HTML)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$text = $tb_teilnahme ?? '';

return '<table style="width: 100%;">
    <tr>
        <td style="font-size: 9.5pt; line-height: 1.35;">' . $text . '</td>
    </tr>
</table>
<div style="font-size:10pt">&nbsp;</div>';
