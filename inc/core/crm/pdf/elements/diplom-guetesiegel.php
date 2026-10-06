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

$oecert_file = file_exists(get_template_directory() . '/inc/core/crm/assets/oecert-diplom.png') ? 'oecert-diplom.png' : 'oecert.png';
$tuef_file   = file_exists(get_template_directory() . '/inc/core/crm/assets/tuef-diplom.png') ? 'tuef-diplom.png' : 'tuef.png';
$system_file = file_exists(get_template_directory() . '/inc/core/crm/assets/systemcert-diplom.png') ? 'systemcert-diplom.png' : 'system-1.png';
$pma_file    = file_exists(get_template_directory() . '/inc/core/crm/assets/pma-diplom.png') ? 'pma-diplom.png' : 'PMA-1.png';

$oecert_src = esc_attr(crm_resolve_asset_path($oecert_file));
$tuef_src   = esc_attr(crm_resolve_asset_path($tuef_file));
$system_src = esc_attr(crm_resolve_asset_path($system_file));
$pma_src    = esc_attr(crm_resolve_asset_path($pma_file));

return '
<table cellspacing="0" cellpadding="0" style="width: 100%; text-align: center;">
    <tr>
        <td style="width: 25%; vertical-align: middle; text-align: center;">
            <img src="' . $oecert_src . '" height="30" style="height: 30px;">
        </td>
        <td style="width: 25%; vertical-align: middle; text-align: center;">
            <img src="' . $tuef_src . '" height="44" style="height: 44px;">
        </td>
        <td style="width: 25%; vertical-align: middle; text-align: center;">
            <img src="' . $system_src . '" height="30" style="height: 30px;">
        </td>
        <td style="width: 25%; vertical-align: middle; text-align: center;">
            <img src="' . $pma_src . '" height="45" style="height: 45px;">
        </td>
    </tr>
</table>';
