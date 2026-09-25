<?php
/**
 * Element: kb-hinweis.php
 *
 * Rendert den Hinweistext für unregelmäßige Kurszeiten.
 * Erwartet:
 *  - $kb_hinweis (string)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$text = !empty($kb_hinweis) ? $kb_hinweis : 'Bei unregelmäßigen Kurszeiten ist ein Ablaufplan der einzelnen Kurswochen, entsprechend obiger Vorgabe, beizulegen. Dies gilt auch für Praxiszeiten.';

return '<div style="font-size:6pt">&nbsp;</div>
<div style="font-size:8.5pt; color: #334155;">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</div>
<div style="font-size:22pt">&nbsp;</div>';
