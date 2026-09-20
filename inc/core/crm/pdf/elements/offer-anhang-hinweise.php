<?php
/**
 * Element: offer-anhang-hinweise.php
 *
 * Rendert die Verweise auf Anhang 1 und Anhang 2 (Anmeldeseite).
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

return '<table cellpadding="5" cellspacing="0" border="0" style="width: 100%; background-color: #f1f5f9; border-left: 3px solid #007C90; margin-top: 6pt;">'
    . '<tr><td style="font-size: 8.5pt; line-height: 12pt; color: #1e293b; text-align: left;">'
    . '<strong>Anhang 1: </strong>Details zu den Inhalten der Veranstaltung<br>'
    . '<strong>Anhang 2: </strong>Exklusive Zusatzleistungen'
    . '</td></tr></table>';
