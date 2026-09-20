<?php
/**
 * Element: kb-titel.php
 *
 * Rendert den zentrierten Titel der Kurszeitenbestätigung.
 * Erwartet:
 *  - $title (string)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$title = !empty($title) ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : 'Bestätigung Kurszeiten';

return '<div style="font-size:14pt">&nbsp;</div>
<table cellspacing="0" cellpadding="0" style="width: 100%;">
    <tr>
        <td style="font-size:16pt; font-weight: bold; text-align: center;">
            <span>' . $title . '</span>
        </td>
    </tr>
</table>
<div style="font-size:18pt">&nbsp;</div>';
