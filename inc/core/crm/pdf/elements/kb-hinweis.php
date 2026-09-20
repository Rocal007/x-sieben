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

$text = $kb_hinweis ?? 'Bei unregelmäßigen Kurszeiten ist ein Ablaufplan der einzelnen Kurswochen beizulegen.';

return '<div style="font-size:8pt">&nbsp;</div>
<div style="font-size:8.5pt; color: #334155;">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</div>
<div style="font-size:32pt">&nbsp;</div>';
