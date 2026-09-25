<?php
/**
 * Element: kb-kurszeiten.php
 *
 * Rendert die wöchentliche Kurszeiten-Matrix für die Kurszeitenbestätigung.
 * Erwartet:
 *  - $kurszeiten (array)
 *  - $selbststudium (array)
 *
 * @package X_SIEBEN_CRM
 */

if (!defined('ABSPATH')) {
    exit;
}

$kurszeiten    = is_array($kurszeiten ?? null) ? $kurszeiten : [];
$selbststudium = is_array($selbststudium ?? null) ? $selbststudium : [];

$box = function ($val) {
    return (!empty($val) && (strpos($val, 'X') !== false || strpos($val, 'x') !== false || $val === true)) ? '&#9746;' : '&#9633;';
};

$weekdays = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

$html = '<table border="1" cellpadding="4" cellspacing="0" style="width: 100%; border-collapse: collapse; border: 1px solid black; font-size: 9.5pt;">
    <thead>
        <tr style="background-color: #f1f5f9;">
            <th style="border: 1px solid black; width: 22%; font-weight: bold; text-align: left; vertical-align: middle;">Kurstage</th>
            <th style="border: 1px solid black; width: 26%; font-weight: bold; text-align: center; vertical-align: middle;">Kurszeit 1 (von – bis)<br><span style="font-size: 8pt; color: #475569; font-weight: normal;">exkl. einer Mittagspause</span></th>
            <th style="border: 1px solid black; width: 26%; font-weight: bold; text-align: center; vertical-align: middle;">Tele-/Selbstlernzeit bei und unter Aufsicht des Kursinstituts<br><span style="font-size: 8pt; color: #475569; font-weight: normal;">(von – bis)</span></th>
            <th style="border: 1px solid black; width: 26%; font-weight: bold; text-align: center; vertical-align: middle;">Tele-/Selbstlernzeit außerhalb des Kursinstituts<br><span style="font-size: 8pt; color: #475569; font-weight: normal;">(von – bis)</span></th>
        </tr>
    </thead>
    <tbody>';

foreach ($weekdays as $day) {
    $key        = strtolower($day);
    $kurszeit   = isset($kurszeiten[$key]) ? $kurszeiten[$key] : '';
    $selbstzeit = isset($selbststudium[$key]) ? $selbststudium[$key] : '';
    $has_active = (!empty($kurszeit) || !empty($selbstzeit));

    $html .= '<tr>
        <td style="border: 1px solid black; width: 22%;">' . $box($has_active) . ' <strong>' . htmlspecialchars($day, ENT_QUOTES, 'UTF-8') . '</strong></td>
        <td style="border: 1px solid black; width: 26%; text-align: center;">' . htmlspecialchars($kurszeit, ENT_QUOTES, 'UTF-8') . '</td>
        <td style="border: 1px solid black; width: 26%; text-align: center;">' . htmlspecialchars($selbstzeit, ENT_QUOTES, 'UTF-8') . '</td>
        <td style="border: 1px solid black; width: 26%; text-align: center;"></td>
    </tr>';
}

$html .= '</tbody></table>';

return $html;
