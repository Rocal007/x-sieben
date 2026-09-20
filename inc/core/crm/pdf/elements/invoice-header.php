<?php
/**
 * Element: invoice-header.php
 *
 * Rendert den offiziellen X-SIEBEN Briefkopf der Honorarnote (Logo links, Firmendaten rechts).
 * Erwartet:
 *  - $logo_html (string HTML)
 *  - $company_name (string)
 *  - $location_wien (string)
 *  - $company_address (string)
 *  - $company_email (string)
 *  - $company_website (string)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$logo     = $logo_html ?? '';
$company  = htmlspecialchars($company_name ?? '', ENT_QUOTES, 'UTF-8');
$loc_wien = htmlspecialchars($location_wien ?? '', ENT_QUOTES, 'UTF-8');
$address  = htmlspecialchars($company_address ?? '', ENT_QUOTES, 'UTF-8');
$email    = htmlspecialchars($company_email ?? '', ENT_QUOTES, 'UTF-8');
$website  = htmlspecialchars($company_website ?? '', ENT_QUOTES, 'UTF-8');

return '
<table cellpadding="0" cellspacing="0" style="width: 100%; border-bottom: 2px solid #007C90; padding-bottom: 6px; margin-bottom: 6px;">
    <tr>
        <td style="width: 55%; vertical-align: middle;">
            ' . $logo . '
        </td>
        <td style="width: 45%; vertical-align: middle; text-align: right; font-size: 7.5pt; color: #64748b; line-height: 1.3;">
            <strong>' . $company . '</strong><br>
            ' . $loc_wien . '<br>
            Zentrale: ' . $address . '<br>
            ' . $email . ' | ' . $website . '
        </td>
    </tr>
</table>
<div style="font-size: 4pt">&nbsp;</div>';
