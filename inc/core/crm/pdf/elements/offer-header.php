<?php
/**
 * Element: offer-header.php
 *
 * Rendert das PDF-Header-Layout für TCPDF (Varianten: full, logo_only, address_only, custom).
 * Erwartet:
 *  - $mode (string)
 *  - $company_info (array)
 *  - $logo_html (string)
 *  - $cfg (optional array)
 *  - $master_cfg (optional array)
 *  - $course (optional CRM_Model)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$mode         = $mode ?? 'full';
$company_info = is_array($company_info ?? null) ? $company_info : [];
$cfg          = is_array($cfg ?? null) ? $cfg : [];
$master_cfg   = is_array($master_cfg ?? null) ? $master_cfg : [];
$course       = $course ?? null;

if ($mode === 'none') {
    return '';
}

if ($mode === 'custom') {
    $custom_html = !empty($cfg['header_custom']) ? $cfg['header_custom'] : ($master_cfg['header_custom'] ?? '');
    return function_exists('crm_replace_pdf_placeholders') ? crm_replace_pdf_placeholders($custom_html, $course) : $custom_html;
}

$logo = !empty($company_info['xsieben_logo']) ? $company_info['xsieben_logo'] : ($logo_html ?? '');
if (empty($logo) && function_exists('crm_resolve_asset_path')) {
    $logo = '<img width="200" style="max-width:200px; height:auto;" src="' . esc_attr(crm_resolve_asset_path('xsieben_logo.png')) . '">';
}

if ($mode === 'logo_only') {
    return '<table cellspacing="0" cellpadding="0" border="0" style="width: 100%;">
        <tr>
            <td style="width: 100%; text-align: left; vertical-align: top; line-height: 1; font-size: 1pt; padding: 0; margin: 0;">' . $logo . '</td>
        </tr>
    </table>';
}

$c_name  = !empty($company_info['company_name']) ? $company_info['company_name'] : 'X SIEBEN Wirtschaftstraining GmbH';
$c_addr  = !empty($company_info['company_address']) ? $company_info['company_address'] : 'Kurzegasse 7, 2493 Lichtenwörth';
$c_phone = !empty($company_info['company_phone']) ? $company_info['company_phone'] : '0800 700 170';
$c_email = !empty($company_info['company_email']) ? $company_info['company_email'] : 'office@x-sieben.at';

if ($mode === 'address_only') {
    return '<table cellspacing="0" cellpadding="0" border="0" style="width: 100%;">
        <tr>
            <td style="font-size: 8.5pt; width: 100%; text-align: right; line-height: 12pt; color: #475569;">
                <strong style="color: #0f172a;">' . htmlspecialchars($c_name, ENT_QUOTES, 'UTF-8') . '</strong><br>
                ' . htmlspecialchars($c_addr, ENT_QUOTES, 'UTF-8') . '<br>
                Telefon: ' . htmlspecialchars($c_phone, ENT_QUOTES, 'UTF-8') . ' | E-Mail: ' . htmlspecialchars($c_email, ENT_QUOTES, 'UTF-8') . '
            </td>
        </tr>
    </table>';
}

// Default / Full: Logo links, Adresse rechts
return '<table cellspacing="0" cellpadding="0" border="0" style="text-align: left; width: 100%;">
    <tr>
        <td style="width: 55%; vertical-align: top; line-height: 1; font-size: 1pt; padding: 0; margin: 0;">' . $logo . '</td>
        <td style="font-size: 9pt; width: 45%; text-align: right; line-height: 13pt; color: #334155; vertical-align: top; padding: 0; margin: 0;">
            <strong>' . htmlspecialchars($c_name, ENT_QUOTES, 'UTF-8') . '</strong><br>
            ' . htmlspecialchars($c_addr, ENT_QUOTES, 'UTF-8') . '<br>
            Telefon: ' . htmlspecialchars($c_phone, ENT_QUOTES, 'UTF-8') . '<br>
            E-Mail: ' . htmlspecialchars($c_email, ENT_QUOTES, 'UTF-8') . '
        </td>
    </tr>
</table>';
