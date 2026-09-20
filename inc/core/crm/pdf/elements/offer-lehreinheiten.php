<?php
/**
 * Element: offer-lehreinheiten.php
 *
 * Rendert die Angabe der Lehreinheiten (Veranstaltungsinformationen).
 * Erwartet:
 *  - $course (CRM_Model)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$anzahl_le = $course->anzahl_le ?? 0;

return '<div style="font-size: 11pt;">Diese Veranstaltung beinhaltet <strong>' . esc_html((string)$anzahl_le) . ' Lehreinheiten</strong> (LE, 1 LE = 45min).</div><div style="font-size:14pt; line-height:14pt;">&nbsp;</div>';
