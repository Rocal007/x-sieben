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

$weekdays = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

$html = '<table border="1" cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse; border: 1px solid black; font-size: 9.5pt;">
    <thead>
        <tr style="background-color: #f1f5f9;">
            <th style="border: 1px solid black; width: 20%; font-weight: bold; text-align: left;">Kurstage</th>
            <th style="border: 1px solid black; width: 26%; font-weight: bold; text-align: center;">Kurszeit (von - bis)<br><span style="font-size: 8pt; color: #475569; font-weight: normal;">exkl. Mittagspause</span></th>
            <th style="border: 1px solid black; width: 27%; font-weight: bold; text-align: center;">Tele-/Selbstlernzeit<br><span style="font-size: 8pt; color: #475569; font-weight: normal;">bei und unter Aufsicht</span></th>
            <th style="border: 1px solid black; width: 27%; font-weight: bold; text-align: center;">Tele-/Selbstlernzeit<br><span style="font-size: 8pt; color: #475569; font-weight: normal;">außerhalb des Kursinstitutes</span></th>
        </tr>
    </thead>
    <tbody>';

foreach ($weekdays as $day) {
    $key        = strtolower($day);
    $kurszeit   = isset($kurszeiten[$key]) ? $kurszeiten[$key] : '';
    $selbstzeit = isset($selbststudium[$key]) ? $selbststudium[$key] : '';

    $html .= '<tr>
        <td style="border: 1px solid black; width: 20%;"><strong>' . htmlspecialchars($day, ENT_QUOTES, 'UTF-8') . '</strong></td>
        <td style="border: 1px solid black; width: 26%; text-align: center;">' . htmlspecialchars($kurszeit, ENT_QUOTES, 'UTF-8') . '</td>
        <td style="border: 1px solid black; width: 27%; text-align: center;">' . htmlspecialchars($selbstzeit, ENT_QUOTES, 'UTF-8') . '</td>
        <td style="border: 1px solid black; width: 27%; text-align: center;"></td>
    </tr>';
}

$html .= '</tbody></table>';

return $html;
