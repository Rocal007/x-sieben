<?php
/**
 * Element: offer-hinweis-nachstehend.php
 *
 * Rendert den Gliederungsverweis unterhalb der Signatur (Deckblatt).
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

return '<div style="font-size: 2pt; line-height: 2pt;">&nbsp;</div>'
    . '<table cellpadding="0" cellspacing="0" border="0" style="width: 100%; border-top: 0.5pt solid #cbd5e1; padding-top: 2pt; margin-top: 2pt;">'
    . '<tr><td style="font-size: 7.5pt; line-height: 10pt; color: #475569; text-align: left;">'
    . '<strong style="color: #007C90;">Nachstehend: </strong>Veranstaltungsinformationen | Anhang 1: Details zu den Inhalten der Veranstaltung | Anhang 2: Exklusive Zusatzleistungen'
    . '</td></tr></table>';
