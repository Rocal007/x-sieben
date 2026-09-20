<?php
/**
 * Element: diplom-guetesiegel.php
 *
 * Rendert die Fußzeilen-Leiste mit Partner- und Gütesiegel-Logos (Ö-Cert, TÜV, SystemCERT, PMA).
 * Erwartet:
 *  - Keine zwingenden Parameter; nutzt crm_resolve_asset_path()
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$oecert_src = esc_attr(crm_resolve_asset_path('oecert.png'));
$tuef_src   = esc_attr(crm_resolve_asset_path('tuef.png'));
$system_src = esc_attr(crm_resolve_asset_path('system-1.png'));
$pma_src    = esc_attr(crm_resolve_asset_path('PMA-1.png'));

return '
<table cellspacing="0" cellpadding="0" style="width: 100%; text-align: center;">
    <tr>
        <td style="width: 25%; vertical-align: middle; text-align: center;">
            <img src="' . $oecert_src . '" height="24" style="height: 24px;">
        </td>
        <td style="width: 25%; vertical-align: middle; text-align: center;">
            <img src="' . $tuef_src . '" height="28" style="height: 28px;">
        </td>
        <td style="width: 25%; vertical-align: middle; text-align: center;">
            <img src="' . $system_src . '" height="22" style="height: 22px;">
        </td>
        <td style="width: 25%; vertical-align: middle; text-align: center;">
            <img src="' . $pma_src . '" height="26" style="height: 26px;">
        </td>
    </tr>
</table>';
