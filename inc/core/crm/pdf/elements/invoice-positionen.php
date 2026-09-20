<?php
/**
 * Element: invoice-positionen.php
 *
 * Rendert die Rechnungspositionen-Tabelle sowie die Summenzeilen (Netto, 20% USt, Brutto).
 * Erwartet:
 *  - $clean_course_title (string)
 *  - $start_datum (string)
 *  - $end_datum (string)
 *  - $le_count (int)
 *  - $single_le (float)
 *  - $netto_kurs (float)
 *  - $cert_rows (string HTML)
 *  - $total_netto (float)
 *  - $total_ust (float)
 *  - $total_brutto (float)
 *  - $sub_key (optional string)
 *  - $return_subs_array (optional bool)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$title   = htmlspecialchars($clean_course_title ?? '', ENT_QUOTES, 'UTF-8');
$start   = htmlspecialchars($start_datum ?? '', ENT_QUOTES, 'UTF-8');
$end     = htmlspecialchars($end_datum ?? '', ENT_QUOTES, 'UTF-8');
$le      = intval($le_count ?? 1);
$s_le    = number_format((float)($single_le ?? 0.0), 2, ',', '.');
$n_kurs  = number_format((float)($netto_kurs ?? 0.0), 2, ',', '.');
$certs   = $cert_rows ?? '';
$t_netto = number_format((float)($total_netto ?? 0.0), 2, ',', '.');
$t_ust   = number_format((float)($total_ust ?? 0.0), 2, ',', '.');
$t_brutto= number_format((float)($total_brutto ?? 0.0), 2, ',', '.');

$subs = [
    'positionen_tabelle' => '<table cellpadding="5" cellspacing="0" border="0" width="100%" style="border-collapse: collapse; font-size: 9pt;">
        <thead>
            <tr style="background-color: #007C90; color: #ffffff;">
                <th style="width: 55%; text-align: left; font-weight: bold; padding: 5px 6px;">Leistung / Kurs</th>
                <th style="width: 12%; text-align: center; font-weight: bold; padding: 5px 4px;">Menge</th>
                <th style="width: 16%; text-align: right; font-weight: bold; padding: 5px 6px;">Einzelpreis</th>
                <th style="width: 17%; text-align: right; font-weight: bold; padding: 5px 6px;">Gesamt Netto</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="border-bottom: 1px solid #cbd5e1; padding: 6px;">
                    <strong>' . $title . '</strong><br>
                    <span style="font-size: 8pt; color: #64748b;">Leistungszeitraum: ' . $start . ' bis ' . $end . '</span>
                </td>
                <td style="border-bottom: 1px solid #cbd5e1; text-align: center; padding: 6px;">' . $le . ' LE</td>
                <td style="border-bottom: 1px solid #cbd5e1; text-align: right; padding: 6px;">' . $s_le . ' €</td>
                <td style="border-bottom: 1px solid #cbd5e1; text-align: right; padding: 6px;">' . $n_kurs . ' €</td>
            </tr>
            ' . $certs . '
        </tbody>
    </table>',

    'gesamtbetrag' => '<table cellpadding="2" cellspacing="0" border="0" width="100%" style="font-size: 9pt; margin-top: 3px;">
        <tr>
            <td style="width: 72%; text-align: right; color: #64748b;">Summe Netto:</td>
            <td style="width: 28%; text-align: right; font-weight: bold; color: #0f172a;">' . $t_netto . ' €</td>
        </tr>
        <tr>
            <td style="width: 72%; text-align: right; color: #64748b; font-size: 8.5pt;">+ 20,00% USt:</td>
            <td style="width: 28%; text-align: right; color: #64748b; font-size: 8.5pt;">' . $t_ust . ' €</td>
        </tr>
        <tr style="background-color: #f1f5f9;">
            <td style="width: 72%; text-align: right; font-size: 10.5pt; font-weight: bold; color: #007C90; border-top: 1.5px solid #007C90; border-bottom: 1.5px solid #007C90; padding: 4px;">Gesamtbetrag (Brutto):</td>
            <td style="width: 28%; text-align: right; font-size: 10.5pt; font-weight: bold; color: #007C90; border-top: 1.5px solid #007C90; border-bottom: 1.5px solid #007C90; padding: 4px;">' . $t_brutto . ' €</td>
        </tr>
    </table>
    <div style="font-size: 6pt">&nbsp;</div>',
];

if (!empty($sub_key)) {
    return $subs[$sub_key] ?? '';
}

if (!empty($return_subs_array)) {
    return $subs;
}

return implode('', $subs);
