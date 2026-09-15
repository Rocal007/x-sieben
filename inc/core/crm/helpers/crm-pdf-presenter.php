<?php
/**
 * CRM PDF & View Presenter
 *
 * Ausgelagerte Präsentationsschicht (Presentation / View Layer) für das X-SIEBEN CRM.
 * Kapselt sämltliche HTML-Generatoren für TCPDF-Tabellen, Modulaufbereitung, Badges,
 * Preistabellen und Kontaktboxen zur Gewährleistung echter Separation of Concerns (SoC).
 *
 * 100% autark im Verzeichnis inc/core/crm/
 *
 * @package X_SIEBEN_CRM
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_Pdf_Presenter
{
    /**
     * Generiert den formatierten HTML-Titelblock für PDF-Seiten.
     *
     * @param string $title Der Titel (z. B. 'Exklusive Zusatzleistungen' oder Kurstitel).
     * @param string|null $prefix Optionaler Präfix (z. B. 'Anhang 1').
     * @return string Der HTML-String.
     */
    public static function render_pdf_title(string $title, ?string $prefix = null): string
    {
        $prefix_html = !empty($prefix) ? '<strong>' . esc_html($prefix) . '</strong> - ' : '';
        $display_title = (function_exists('mb_strlen') && mb_strlen($title, 'UTF-8') > 115) 
            ? mb_strimwidth($title, 0, 112, '...', 'UTF-8') 
            : $title;

        return '
        <table class="title" cellpadding="5" cellspacing="0" border="0" style="width: 100%; background-color: #007C90;">
            <tr>
                <td style="font-size: 13pt; line-height: 17pt; color: #ffffff; padding: 6pt 8pt 6pt 8pt; vertical-align: middle;">
                    ' . $prefix_html . '<span style="font-size: 11.5pt;">' . esc_html($display_title) . '</span>
                </td>
            </tr>
        </table>
        ';
    }

    /**
     * Generiert einen HTML-Tabellen-String mit den Logos der Zertifizierungspartner.
     *
     * @param array $zert_images_src Array von lokalen Dateipfaden oder URLs.
     * @return string
     */
    public static function render_zertifizierungen_images(array $zert_images_src): string
    {
        if (empty($zert_images_src)) {
            return '';
        }

        $html = '<table cellpadding="0" cellspacing="8" border="0"><tr>';
        foreach ($zert_images_src as $image) {
            $img_resolved = function_exists('crm_resolve_asset_path') ? crm_resolve_asset_path($image) : $image;
            $html .= '
            <td cellpadding="6" style="width:62px; height:36px; border: 1px solid #cbd5e1; text-align: center; vertical-align: middle;">
                <img src="' . esc_attr($img_resolved) . '" width="48" height="24">
            </td>';
        }
        $html .= '</tr></table>';

        return $html;
    }

    /**
     * Generiert eine HTML-Aufzählungsliste aus den Voraussetzungen.
     *
     * @param mixed $voraussetzungen_list Array oder Text der Voraussetzungen.
     * @return string
     */
    public static function render_voraussetzungen_list($voraussetzungen_list): string
    {
        if (empty($voraussetzungen_list)) {
            return '';
        }

        $html = '<ul style="margin: 0; padding-left: 18px;">';

        // Repeater: Array von Arrays (mit Key "requirements")
        if (is_array($voraussetzungen_list) && isset($voraussetzungen_list[0]) && is_array($voraussetzungen_list[0])) {
            foreach ($voraussetzungen_list as $row) {
                if (isset($row['requirements']) && !empty(trim($row['requirements']))) {
                    $html .= '<li style="line-height: 16pt; font-size: 10pt; padding-bottom: 5pt;">' . esc_html(trim($row['requirements'])) . '</li>';
                }
            }
        }
        // Checkbox/Select: Array von Strings
        elseif (is_array($voraussetzungen_list)) {
            foreach ($voraussetzungen_list as $item) {
                if (is_string($item) && !empty(trim($item))) {
                    $html .= '<li style="line-height: 16pt; font-size: 10pt; padding-bottom: 5pt;">' . esc_html(trim($item)) . '</li>';
                }
            }
        }
        // Textfeld: Einfacher String mit Zeilenumbrüchen
        elseif (is_string($voraussetzungen_list)) {
            $lines = preg_split('/\r\n|\r|\n/', $voraussetzungen_list);
            foreach ($lines as $line) {
                if (!empty(trim($line))) {
                    $html .= '<li style="line-height: 16pt; font-size: 10pt; padding-bottom: 5pt;">' . esc_html(trim($line)) . '</li>';
                }
            }
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * Generiert den 4-spaltigen HTML-Kontaktblock mit Icons.
     *
     * @param object $course Instanz von CRM_Model.
     * @return string
     */
    public static function render_contact_info(object $course): string
    {
        $web_icon   = $course->web_icon ?? '';
        $mail_icon  = $course->mail_icon ?? '';
        $fax_icon   = $course->fax_icon ?? '';
        $phone_icon = $course->phone_icon ?? '';

        $html = '<table class="text" cellpadding="0" cellspacing="0" border="0" style="padding-bottom: 30pt;">';
        $html .= '    <tr style="padding-bottom: 10pt;">';
        $html .= '        <td style="width:7%;">' . $web_icon . '<div style="font-size:5pt">&nbsp;</div> </td>';
        $html .= '        <td style="width:43%;"><div style="font-size:2pt">&nbsp;</div> <a href="https://x-sieben.at/kontakt">www.x-sieben.at/kontakt</a></td>';
        $html .= '        <td style="width:7%;">' . $fax_icon . '<div style="font-size:5pt">&nbsp;</div> </td>';
        $html .= '        <td style="width:43%;"><div style="font-size:2pt">&nbsp;</div> Fax: (+43) 2622 / 351 10 14</td>';
        $html .= '    </tr>';
        $html .= '    <tr>';
        $html .= '        <td style="width:7%;">' . $mail_icon . '<div style="font-size:5pt">&nbsp;</div> </td>';
        $html .= '        <td style="width:43%;"><div style="font-size:2pt">&nbsp;</div><a href="mailto:office@x-sieben.at">office@x-sieben.at</a></td>';
        $html .= '        <td style="width:7%;">' . $phone_icon . '<div style="font-size:5pt">&nbsp;</div> </td>';
        $html .= '        <td style="width:43%;"><div style="font-size:2pt">&nbsp;</div> Rückfragen: <a href="tel: 0043800700170">(+43) 800 700 170</a></td>';
        $html .= '    </tr>';
        $html .= '</table>';

        return $html;
    }

    /**
     * Generiert die tabellarische Preistabelle für Gesamtkosten inkl. Optionen.
     *
     * @param object $course
     * @return string
     */
    public static function render_gesamt_kosten(object $course): string
    {
        $netto_kurs = (float) ($course->preis_netto ?? 0);
        $brutto_kurs = (float) ($course->preis_brutto ?? 0);
        $ust_satz = 20.00;
        $ust_kurs = ($netto_kurs / 100) * $ust_satz;

        $certifications_data = method_exists($course, 'get_certifications_from_form_field')
            ? $course->get_certifications_from_form_field()
            : [];

        $total_netto = $netto_kurs;
        $total_ust = $ust_kurs;
        $total_brutto = $brutto_kurs;

        $anzahl_le  = $course->anzahl_le ?? 0;
        $le_single  = $course->le_single ?? 0;

        $html = '
    <table cellpadding="6" cellspacing="0" border="0" width="100%" style="border-collapse: collapse; font-size:10pt;">
        <thead>
            <tr style="background-color:#f2f2f2;">
                <th style="text-align:left; width:70%; border-bottom:1px solid #aaa;">Beschreibung</th>
                <th style="text-align:right; width:30%; border-bottom:1px solid #aaa;">Preis</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="width:70%;">Kursgebühr
                    <span style="color:#555; font-size:9pt;">
                        ' . esc_html((string)$anzahl_le) . ' Lehreinheiten (' . number_format((float)$le_single, 2, ',', '.') . ' €/LE, 1 LE = 45min)
                    </span>
                </td>
                <td style="width:30%; text-align:right;">' . number_format($netto_kurs, 2, ',', '.') . ' €</td>
            </tr>
            <tr style="color:#555; font-size:9pt;">
                <td>+ ' . number_format($ust_satz, 2, ',', '.') . '% USt. (von ' . number_format($netto_kurs, 2, ',', '.') . ' €)</td>
                <td style="text-align:right;">' . number_format($ust_kurs, 2, ',', '.') . ' €</td>
            </tr>
            <tr><td colspan="2" style="border-bottom:0.5pt dashed #ccc;"></td></tr>';

        // Zertifizierungen hinzufügen
        if (!empty($certifications_data)) {
            foreach ($certifications_data as $cert) {
                $name = htmlspecialchars($cert['name']);
                $price = str_replace(['.', ','], ['', '.'], $cert['price']);
                $percentage_raw = rtrim($cert['percentage'], '%');

                $ust_satz_cert = ($percentage_raw === 'N/A') ? 20.00 : (float) $percentage_raw;
                $price = (float) $price;

                $ust_cert = ($price / (100 + $ust_satz_cert)) * $ust_satz_cert;
                $netto_cert = $price - $ust_cert;
                $brutto_cert = $netto_cert + $ust_cert;

                $total_netto += $netto_cert;
                $total_ust += $ust_cert;
                $total_brutto += $brutto_cert;

                $html .= '
            <tr>
                <td style="width:70%;">' . $name . '</td>
                <td style="width:30%; text-align:right;">' . number_format($netto_cert, 2, ',', '.') . ' €</td>
            </tr>
            <tr style="color:#555; font-size:9pt;">
                <td>+ ' . number_format($ust_satz_cert, 2, ',', '.') . '% USt. (von ' . number_format($netto_cert, 2, ',', '.') . ' €)</td>
                <td style="text-align:right;">' . number_format($ust_cert, 2, ',', '.') . ' €</td>
            </tr>
            <tr><td colspan="2" style="border-bottom:0.5pt dashed #ccc;"></td></tr>';
            }
        }

        // Gesamtsummen
        $html .= '
            <tr style="color:#555; font-size:9pt;">
                <td><strong>Gesamt Netto</strong></td>
                <td style="text-align:right;"><strong>' . number_format($total_netto, 2, ',', '.') . ' €</strong></td>
            </tr>
            <tr style="color:#555; font-size:9pt;">
                <td><strong>Gesamt USt.</strong></td>
                <td style="text-align:right;">' . number_format($total_ust, 2, ',', '.') . ' €</td>
            </tr>
            <tr>
                <td colspan="2" style="border-bottom:0.5pt dashed #ccc;">
            </td>
            </tr>
            <tr style="background-color:#f9f9f9;">
                <td><strong>Gesamt Brutto</strong></td>
                <td style="text-align:right;"><strong>' . number_format($total_brutto, 2, ',', '.') . ' €</strong></td>
            </tr>
        </tbody>
    </table>';

        return $html;
    }

    /**
     * Generiert die Standard-Kursgebühr-Tabelle (ohne Zertifizierungs-Loop).
     *
     * @param object $course
     * @return string
     */
    public static function render_kursgebuehr(object $course): string
    {
        $netto = (float) ($course->preis_netto ?? 0);
        $brutto = (float) ($course->preis_brutto ?? 0);
        $ust_satz = 20.00;
        $ust = ($netto / 100) * $ust_satz;

        $anzahl_le = $course->anzahl_le ?? 0;
        $le_single = $course->le_single ?? 0;

        return '
    <table cellpadding="6" cellspacing="0" border="0" width="100%" style="border-collapse: collapse; font-size:10pt;">
        <thead>
            <tr style="background-color:#f2f2f2;">
                <th style="text-align:left; width:70%; border-bottom:1px solid #aaa;">Beschreibung</th>
                <th style="text-align:right; width:30%; border-bottom:1px solid #aaa;">Preis</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="width:70%;">Kursgebühr
                    <span style="color:#555; font-size:9pt;">
                        ' . esc_html((string)$anzahl_le) . ' Lehreinheiten (' . number_format((float)$le_single, 2, ',', '.') . ' €/LE, 1 LE = 45min)
                    </span>
                </td>
                <td style="width:30%; text-align:right;">' . number_format($netto, 2, ',', '.') . ' €</td>
            </tr>
            <tr style="color:#555; font-size:9pt;">
                <td>+ ' . number_format($ust_satz, 2, ',', '.') . '% USt. (von ' . number_format($netto, 2, ',', '.') . ' €)</td>
                <td style="text-align:right;">' . number_format($ust, 2, ',', '.') . ' €</td>
            </tr>
            <tr><td colspan="2" style="border-bottom:0.5pt dashed #ccc;"></td></tr>
            <tr style="background-color:#f9f9f9;">
                <td><strong>Gesamt Brutto</strong></td>
                <td style="text-align:right;"><strong>' . number_format($brutto, 2, ',', '.') . ' €</strong></td>
            </tr>
        </tbody>
    </table>';
    }

    /**
     * Rendert die Modulübersicht aus dem ACF-Repeater 'module'.
     *
     * @param int $post_id
     * @return string
     */
    public static function render_module_html(int $post_id): string
    {
        if (!function_exists('have_rows') || !have_rows('module', $post_id)) {
            return '';
        }

        $module_rows    = [];
        $breakdown_rows = [];
        $extra_rows     = [];
        $section_title  = '';

        while (have_rows('module', $post_id)) {
            the_row();
            $mod = trim((string) get_sub_field('modul'));
            $tit = trim((string) get_sub_field('modul_titel'));
            $le  = trim((string) get_sub_field('anzahl_le'));

            if (empty($mod) && empty($tit) && empty($le)) {
                continue;
            }

            $tit_clean = trim(str_replace(['<', '>', '&lt;', '&gt;', '&LT;', '&GT;'], '', $tit));
            if (empty($mod) && empty($le) && !empty($tit_clean) && (strpos($tit, '<') !== false || mb_strtoupper($tit_clean) === $tit_clean)) {
                $section_title = $tit_clean;
                continue;
            }

            $mod_clean = trim(preg_replace('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{FE00}-\x{FE0F}]/u', '', $mod));

            if (in_array($mod, ['+', '->', '-']) || (!empty($le) && !preg_match('/^modul\b/i', $mod) && !preg_match('/^abschnitt\b/i', $mod))) {
                $prefix = in_array($mod, ['+', '->', '-']) ? $mod : '•';
                $breakdown_rows[] = [
                    'prefix' => $prefix,
                    'titel'  => $tit,
                    'le'     => $le
                ];
            } elseif (stripos($mod_clean, 'inklusive') !== false || stripos($mod_clean, 'mehrwert') !== false) {
                $extra_rows[] = [
                    'label' => !empty($mod_clean) ? $mod_clean : 'INKLUSIVE',
                    'titel' => $tit
                ];
            } else {
                $module_rows[] = [
                    'modul' => !empty($mod_clean) ? $mod_clean : $mod,
                    'titel' => $tit,
                    'le'    => $le
                ];
            }
        }

        if (empty($module_rows) && empty($breakdown_rows) && empty($extra_rows)) {
            return '';
        }

        $html = '';

        // 1. Modul- und Themeninhalte
        if (!empty($module_rows)) {
            $html .= '<table cellpadding="4" cellspacing="0" border="0" style="width: 100%; border-collapse: collapse; font-size: 10pt;">';
            $html .= '<thead>
                <tr style="background-color: #f1f5f9; border-bottom: 1.5px solid #007C90;">
                    <th style="width: 22%; text-align: left; color: #007C90; font-weight: bold;">Gliederung</th>
                    <th style="width: 78%; text-align: left; color: #007C90; font-weight: bold;">Beschreibung</th>
                </tr>
            </thead><tbody>';

            foreach ($module_rows as $row) {
                $html .= '<tr>
                    <td valign="top" style="width: 22%; font-weight: bold; color: #1e293b; padding-top: 4px; padding-bottom: 4px;">' . esc_html($row['modul']) . '</td>
                    <td valign="top" style="width: 78%; color: #334155; padding-top: 4px; padding-bottom: 4px;">' . esc_html($row['titel']) . '</td>
                </tr>';
            }
            $html .= '</tbody></table>';
        }

        // 2. Zeiteinteilung / Lehreinheiten-Aufteilung
        if (!empty($breakdown_rows)) {
            if (!empty($html)) {
                $html .= '<div style="font-size:10pt">&nbsp;</div>';
            }
            $total_breakdown_le = 0;
            $html .= '<table cellpadding="6" cellspacing="0" border="0" width="100%" style="border-collapse: collapse; font-size: 10pt;">';
            $html .= '<thead>
                <tr style="background-color:#f2f2f2;">
                    <th style="text-align:left; width:85%; border-bottom:1px solid #aaa; font-weight: bold; color: #1e293b;">Zeiteinteilung / Lehreinheiten</th>
                    <th style="text-align:right; width:15%; border-bottom:1px solid #aaa; font-weight: bold; color: #1e293b;">LE</th>
                </tr>
            </thead><tbody>';

            foreach ($breakdown_rows as $row) {
                $le_num = intval(preg_replace('/[^0-9]/', '', $row['le']));
                $total_breakdown_le += $le_num;

                $clean_tit = ltrim($row['titel'], "+-• \t\n\r");
                $prefix_symbol = !empty($row['prefix']) ? $row['prefix'] : '+';
                $prefix_html = '<span style="color: #007C90; font-weight: bold;">' . esc_html($prefix_symbol) . '</span> ';

                $html .= '<tr>
                    <td style="width:85%; color: #334155; line-height: 1.4;">' . $prefix_html . esc_html($clean_tit) . '</td>
                    <td style="width:15%; text-align:right; font-weight: bold; color: #0f172a;">' . esc_html($row['le']) . '</td>
                </tr>';
                $html .= '<tr><td colspan="2" style="border-bottom:0.5pt dashed #ccc;"></td></tr>';
            }

            if ($total_breakdown_le > 0) {
                $html .= '<tr style="background-color:#f9f9f9;">
                    <td style="width:85%;"><strong>Gesamt Lehreinheiten</strong></td>
                    <td style="width:15%; text-align:right;"><strong>' . $total_breakdown_le . ' LE</strong></td>
                </tr>';
            }

            $html .= '</tbody></table>';
        }

        // 3. Mehrwert / Inklusive Leistungen
        if (!empty($extra_rows) || !empty($section_title)) {
            if (!empty($html)) {
                $html .= '<div style="font-size:10pt">&nbsp;</div>';
            }
            $raw_title = !empty($section_title) ? $section_title : 'Ihr Mehrwert';
            $clean_title = trim(str_replace(['<', '>', '&lt;', '&gt;', '&LT;', '&GT;'], '', html_entity_decode($raw_title, ENT_QUOTES, 'UTF-8')));
            $clean_title = !empty($clean_title) ? mb_strtoupper($clean_title, 'UTF-8') : 'IHR MEHRWERT';
            $html .= '<table cellpadding="5" cellspacing="0" border="0" width="100%" style="border-collapse: collapse; font-size: 10pt;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th colspan="2" style="text-align: left; border-bottom: 1px solid #aaa; font-weight: bold; color: #007C90;">&lt; ' . esc_html($clean_title) . ' &gt;</th>
                    </tr>
                </thead>
                <tbody>';
            foreach ($extra_rows as $row) {
                $html .= '<tr>
                    <td valign="top" style="width: 25%; font-weight: bold; color: #007C90; padding-top: 4px; padding-bottom: 4px;">' . esc_html($row['label']) . '</td>
                    <td valign="top" style="width: 75%; color: #334155; padding-top: 4px; padding-bottom: 4px;">' . esc_html($row['titel']) . '</td>
                </tr>
                <tr><td colspan="2" style="border-bottom: 0.5pt dashed #ccc;"></td></tr>';
            }
            $html .= '</tbody></table>';
        }

        return $html;
    }

    /**
     * Extrahiert und formatiert die dynamischen Inhalte für Anhang 1.
     *
     * @param int $post_id
     * @param object $course
     * @param string $accordion_title
     * @return string
     */
    public static function render_inhalte_dyn(int $post_id, object $course, string $accordion_title = 'Inhalte'): string
    {
        $content = '';
        if (function_exists('get_field')) {
            $accordions = get_field('accordion_list', $post_id);
            if (is_array($accordions)) {
                foreach ($accordions as $item) {
                    if (isset($item['title']) && stripos($item['title'], $accordion_title) !== false) {
                        $content = $item['content'] ?? '';
                        break;
                    }
                }
            }
        }

        if (empty($content)) {
            $content = get_post_meta($post_id, 'inhalte', true);
        }

        if (empty($content)) {
            return '';
        }

        return self::_format_content_modules($content);
    }

    public static function _format_content_modules(string $content): string
    {
        $content = preg_replace('/<h[1-6][^>]*>\s*<\/h[1-6]>/iu', '', $content);
        $content = preg_replace('/<p[^>]*>\s*<\/p>/iu', '', $content);
        $content = preg_replace('/<div[^>]*>\s*<\/div>/iu', '', $content);
        $content = preg_replace('/<div[^>]*>\s*<hr[^>]*>\s*<\/div>/iu', '<hr />', $content);

        $mod_pattern = '/(?:<hr[^>]*>\s*)?(?:<(?:h[1-6]|p|div)[^>]*>\s*)?(?:<(?:strong|b|span|em)[^>]*>\s*)*\b(?<!\bin\s)(?<!\bim\s)(?<!\baus\s)(?<!\bvon\s)(?<!\bab\s)(?<!\bmit\s)(?<!\bjedem\s)(?<!\bdiesem\s)modul\s+(\d+|[ivxlcdm]+|ki)\b(?:\s*<\/(?:strong|b|span|em)>)*\s*[:\s–\-]*/iu';

        if (!preg_match_all($mod_pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return self::_clean_generic_section_html($content);
        }

        $num_modules = count($matches[0]);
        $output = '';

        $first_offset = $matches[0][0][1];
        if ($first_offset > 0) {
            $intro = substr($content, 0, $first_offset);
            $clean_intro = self::_clean_generic_section_html($intro);
            if (!empty(trim($clean_intro))) {
                $output .= '<div class="modul-intro">' . $clean_intro . '</div><div style="font-size: 14pt; line-height: 14pt;">&nbsp;</div>';
            }
        }

        for ($i = 0; $i < $num_modules; $i++) {
            $mod_num = strtoupper(trim($matches[1][$i][0]));
            $start_pos = $matches[0][$i][1] + strlen($matches[0][$i][0]);
            $end_pos = ($i + 1 < $num_modules) ? $matches[0][$i + 1][1] : strlen($content);
            $mod_chunk = substr($content, $start_pos, $end_pos - $start_pos);

            $closing_html = '';
            if ($i === $num_modules - 1) {
                $closing_pattern = '/(?:<hr[^>]*>\s*)?(?:<(?:h[1-6]|p|div)[^>]*>\s*)?(?:<(?:strong|b|span|em)[^>]*>\s*)*(?:Lehrgangsabschluss|Abschluss\s*&amp;\s*Zertifizierung|Abschluss\s*&amp;\s*Diplom|Abschluss\s*:\s*Diplom|Abschluss\s*Diplom|Voraussetzungen\s+zum\s+Erwerb\s+des\s+Diploms)\b/iu';
                if (preg_match($closing_pattern, $mod_chunk, $closing_match, PREG_OFFSET_CAPTURE)) {
                    $closing_pos = $closing_match[0][1];
                    $closing_chunk = substr($mod_chunk, $closing_pos);
                    $mod_chunk = substr($mod_chunk, 0, $closing_pos);
                    $closing_html = self::_clean_closing_section_html($closing_chunk);
                }
            }

            list($mod_title, $mod_body) = self::_extract_module_title_and_body($mod_chunk);

            $heading_text = 'MODUL ' . $mod_num;
            if (!empty($mod_title)) {
                $heading_text .= ': ' . $mod_title;
            }

            if ($i > 0) {
                $output .= '<div style="font-size: 14pt; line-height: 14pt;">&nbsp;</div>';
            }
            $output .= '<h3 class="modul-heading">' . esc_html($heading_text) . '</h3>';
            $output .= '<div class="modul-body">' . self::_clean_module_body_html($mod_body) . '</div>';

            if (!empty($closing_html)) {
                $output .= '<div style="font-size: 14pt; line-height: 14pt;">&nbsp;</div>' . $closing_html;
            }
        }

        return $output;
    }

    public static function _extract_module_title_and_body(string $chunk): array
    {
        $chunk = trim($chunk);
        $chunk = preg_replace('/^[:\s–\-]+/u', '', $chunk);

        $mod_title = '';
        $mod_body  = $chunk;

        if (preg_match('/^(.*?)(<\/(?:strong|b|span|em|i|h[1-6]|p)>|<br\s*\/?>|\n)/isu', $chunk, $m)) {
            $candidate = trim(strip_tags($m[1]));
            $candidate = preg_replace('/^[:\s–\-]+/u', '', $candidate);
            if (!empty($candidate) && !preg_match('/^\b(?:Zielgruppe|Ziel|Ziele|Inhalte|Inhalt|Methodik|Didaktik|Voraussetzungen)\b\s*[:\s–\-]/iu', $candidate) && strlen($candidate) < 250) {
                $mod_title = $candidate;
                $mod_body  = substr($chunk, strlen($m[0]));
            }
        }

        $mod_body = preg_replace('/^(?:\s*(?:<\/(?:strong|b|span|em|i|h[1-6]|p|div)>|<br\s*\/?>)\s*)+/iu', '', $mod_body);
        $mod_body = preg_replace('/^[:\s–\-]+/u', '', $mod_body);

        return [trim($mod_title), trim($mod_body)];
    }

    public static function _clean_module_body_html(string $body): string
    {
        $body = preg_replace('/<hr[^>]*>/iu', '', $body);

        $labels_regex = '/(?:<(?:p|div|h[4-6])[^>]*>\s*)?(?:<(?:strong|b|span)[^>]*>\s*)?\b(Zielgruppe|Ziele|Ziel|Inhalte|Inhalt|Methodik|Didaktik|Voraussetzungen)\b(?:\s*<\/(?:strong|b|span)>)*\s*[:–\-]\s*(?:<\/(?:p|div|h[4-6])>)?/iu';
        $body = preg_replace_callback($labels_regex, function ($m) {
            $lbl = ucfirst(strtolower($m[1]));
            if ($lbl === 'Inhalt') $lbl = 'Inhalte';
            return "\n\n<p><strong class=\"modul-label\">" . $lbl . ":</strong></p>\n";
        }, $body);

        $body = preg_replace('/<li[^>]*>\s*<\/li>/iu', '', $body);
        $body = preg_replace('/<li[^>]*>\s*<p[^>]*>(.*?)<\/p>\s*<\/li>/isu', '<li>$1</li>', $body);
        $body = preg_replace('/<ul[^>]*>/iu', '<ul class="modul-list">', $body);

        $body = preg_replace('/(?<!\n)\s*(<ul\b|<ol\b)/iu', "\n\n$1", $body);
        $body = preg_replace('/(<\/ul>|<\/ol>)\s*(?!\n)/iu', "$1\n\n", $body);

        $body = preg_replace_callback('/<(ul|ol)[^>]*>.*?<\/\1>/isu', function ($matches) {
            return preg_replace('/\n{2,}/', "\n", $matches[0]);
        }, $body);

        $paragraphs = preg_split('/\n{2,}/', $body);
        $clean_paras = [];
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if (empty($p)) continue;
            if (preg_match('/^<(?:p|ul|ol|table|div|h[1-6]|blockquote)/i', $p)) {
                $clean_paras[] = $p;
            } elseif (preg_match('/^<li/i', $p)) {
                $clean_paras[] = '<ul class="modul-list">' . $p . '</ul>';
            } else {
                $clean_paras[] = '<p class="modul-text">' . $p . '</p>';
            }
        }
        $body = implode("\n", $clean_paras);
        $body = preg_replace('/<p[^>]*>\s*<\/p>/iu', '', $body);

        return $body;
    }

    public static function _clean_generic_section_html(string $html): string
    {
        $html = preg_replace('/<hr[^>]*>/iu', '', $html);
        $html = preg_replace('/<p[^>]*>\s*<\/p>/iu', '', $html);
        $html = preg_replace('/<ul[^>]*>/iu', '<ul class="modul-list">', $html);
        $html = preg_replace('/<li[^>]*>\s*<p[^>]*>(.*?)<\/p>\s*<\/li>/isu', '<li>$1</li>', $html);
        return trim($html);
    }

    public static function _clean_closing_section_html(string $html): string
    {
        $html = preg_replace('/<hr[^>]*>/iu', '', $html);
        $html = preg_replace_callback('/(?:<(?:h[1-6]|p|div)[^>]*>\s*)?(?:<(?:strong|b|span|em)[^>]*>\s*)*(Lehrgangsabschluss|Abschluss\s*&amp;\s*Zertifizierung|Abschluss\s*&amp;\s*Diplom|Abschluss\s*:\s*Diplom|Abschluss\s*Diplom|Voraussetzungen\s+zum\s+Erwerb\s+des\s+Diploms)\b(?:\s*<\/(?:strong|b|span|em)>)*(?:\s*<\/(?:h[1-6]|p|div)>)*/iu', function ($m) {
            return '<h3 class="abschluss-heading">' . $m[1] . '</h3>';
        }, $html);
        $html = preg_replace('/<ul[^>]*>/iu', '<ul class="modul-list">', $html);
        $html = preg_replace('/<li[^>]*>\s*<p[^>]*>(.*?)<\/p>\s*<\/li>/isu', '<li>$1</li>', $html);
        return trim($html);
    }

    /**
     * Rendert die Referenten/Trainer-Links.
     *
     * @param int $post_id
     * @return string
     */
    public static function render_trainer(int $post_id): string
    {
        if (!$post_id) {
            return '';
        }
        $vt_meta = get_post_meta($post_id, 'vortragende', true);
        if (empty($vt_meta) || !is_array($vt_meta)) {
            return '';
        }
        $query = new WP_Query([
            'post__in' => $vt_meta,
            'post_type' => 'members',
            'posts_per_page' => -1,
            'orderby' => 'post__in',
        ]);
        if (!$query->have_posts()) {
            return '';
        }
        $html = '';
        $count = 0;
        while ($query->have_posts()) {
            $query->the_post();
            if ($count > 0) {
                $html .= '<span style="margin-left: -4px;">, </span>';
            }
            $html .= sprintf(
                '<a href="%s" title="%s">%s</a>',
                esc_url(get_permalink()),
                esc_attr(get_the_title()),
                esc_html(get_the_title())
            );
            $count++;
        }
        wp_reset_postdata();
        return $html;
    }

    /**
     * Rendert die Tabelle für Zusatzleistungen / Garantie.
     *
     * @param string $garantie_text
     * @return string
     */
    public static function render_garantie(string $garantie_text): string
    {
        return '<table class="text" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td>' . $garantie_text . '</td>
                    </tr>
                </table>';
    }

    /**
     * Rendert die Postskriptum-Box (PS / PPS).
     *
     * @param string $ps_custom
     * @return string
     */
    public static function render_ps(string $ps_custom = ''): string
    {
        $clean = trim($ps_custom);
        if (!empty(trim(strip_tags($clean)))) {
            // Falls der benutzerdefinierte Text bereits ein table-Tag enthält, nicht nochmals in table wrappen
            if (stripos($clean, '<table') !== false) {
                return '<div style="font-size:10pt; width:100%;">' . $clean . '</div>';
            }
            return '<table cellpadding="0" cellspacing="0" border="0" style="font-size:10pt; width:100%; border:none;"><tr><td style="margin:0; padding:0; border:none;">' . $clean . '</td></tr></table>';
        }
        return '<table cellpadding="0" cellspacing="0" border="0" style="font-size:10pt; width:100%; border:none;">
                    <tr>
                        <td style="margin:0; padding:0; border:none;">PS: Die <strong>Bewertungen unserer Kursteilnehmer</strong> finden Sie auf der externen Bewertungsplattform <a href="https://www.x-sieben.at/provenexpert.com/x-sieben-wirtschaftstraining/?utm_source=Widget&utm_medium=Widget&utm_campaign=Widget">ProvenExpert</a>! <br>
                        </td>
                        </tr>
                        <tr>
                        <td style="margin:0; padding:0; border:none;">PPS: <strong>Keine Förderung?</strong> Dennoch <strong>jetzt weiterbilden</strong> und bis in zu <strong>24 Monatsraten</strong> bezahlen. Mit <a href="https://www.x-sieben.at/jetzt-weiterbilden-bezahlen-in-bis-zu-24-raten-mit-klarna/">Klarna</a>.
                        </td>
                    </tr>
                </table>';
    }

    /**
     * Rendert die offizielle Instituts-Signatur für Dokumente.
     *
     * @param string $signatur_icon
     * @param string $name
     * @param string $title
     * @return string
     */
    public static function render_signature(string $signatur_icon, string $name = 'Mag. Dr. Johannes Gasberger', string $title = 'Geschäftsführer | X SIEBEN Wirtschaftstraining GmbH'): string
    {
        return '<table cellpadding="0" cellspacing="0" border="0" style="margin: 0; padding: 0;">'
            . '<tr><td style="margin: 0; padding: 0; line-height: 1; text-align: left;">' . $signatur_icon . '</td></tr>'
            . '<tr><td style="margin: 0; padding: 4px 0 0 0; font-size: 10pt; color: #0f172a; line-height: 1.2; text-align: left;">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td style="margin: 0; padding: 2px 0 0 0; font-size: 9pt; color: #475569; line-height: 1.2; text-align: left;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '</table>';
    }

    /**
     * Rendert den Anmelde-AGB- und Datenschutztext.
     *
     * @param string $agb_custom
     * @return string
     */
    public static function render_anmeldung_agb(string $agb_custom = ''): string
    {
        if (!empty(trim(strip_tags($agb_custom)))) {
            return $agb_custom;
        }
        return '<p>Mit Ihrer Anmeldung bestätigen Sie die <a href="https://x-sieben.at/wp-content/uploads/2025/09/AGB_X_SIEBEN_2025.pdf">AGB</a> samt Widerrufsbelehrung der X SIEBEN Wirtschaftstraining GmbH gelesen und akzeptiert zu haben. Diese finden Sie auf unserer Website unter ‚AGB‘ oder auf Wunsch per E-Mail. Die Datenschutzerklärung finden Sie <a href="https://x-sieben.at/datenschutzerklaerung/">hier</a></p>';
    }

    /**
     * Rendert die Tabelle der Zertifizierungen im Kurs.
     *
     * @param int $post_id
     * @return string
     */
    public static function render_zertifizierungen_loop(int $post_id): string
    {
        $zusatztext = esc_html((string) get_post_meta($post_id, 'zertifizierung-zusatztext', true));

        $html = '<table cellpadding="0" cellspacing="0" border="0">';
        if (!empty($zusatztext)) {
            $html .= '<tr><td>' . $zusatztext . '</td></tr>';
        }
        $html .= '<tr>';
        $html .= '<th style="text-align:left"></th>';
        $html .= '<th style="text-align:right"></th>';
        $html .= '</tr>';
        if (function_exists('have_rows') && have_rows('zertifizierungen', $post_id)) {
            while (have_rows('zertifizierungen', $post_id)) : the_row();
                $preis = (float) get_sub_field('preis');
                $ust_satz = (float) get_sub_field('Ust_satz');
                $ust = ($preis / (100 + $ust_satz)) * $ust_satz;
                $zert_preis_netto = $preis - $ust;
                $zert_preis_brutto = $zert_preis_netto + $ust;
                $html .= '<tr>';
                $html .= '<td style="width: 70%">' . esc_html(get_sub_field('name-zert')) . '</td>';
                $html .= '<td style="width: 30%; text-align:right">' . number_format($zert_preis_netto, 2, ',', '.') . ' €</td>';
                $html .= '</tr><tr>';
                $html .= '<td>+ ' . esc_html((string)$ust_satz) . '% (von ' . number_format($zert_preis_netto, 2, ',', '.') . ' €) </td>';
                $html .= '<td style="text-align:right">' . number_format($ust, 2, ',', '.') . '€ </td>';
                $html .= '</tr><tr><td colspan="2" style="border-top: 1px solid #cbd5e1; height: 1px; font-size: 1pt;">&nbsp;</td></tr><tr>';
                $html .= '<td><strong>Gesamt Brutto</strong> </td><td style="text-align:right"><strong>' . number_format($zert_preis_brutto, 2, ',', '.') . ' €</strong> </td></tr>';
            endwhile;
        }
        $html .= '</table>';
        $html .= '<div style="font-size:10pt">&nbsp;</div>';
        return $html;
    }

    /**
     * Rendert die optionalen Zertifizierungen aus dem WPForms-Feld.
     *
     * @param array $certifications_data
     * @return string
     */
    public static function render_form_zertifizierungen_loop(array $certifications_data): string
    {
        if (empty($certifications_data)) {
            return '';
        }

        $total_brutto = 0.00;
        $total_netto  = 0.00;

        $html = '
    <div style="font-family:dejavusans; font-size:14pt; margin-bottom:6px;">Optionale Zertifizierungen</div>
    <div style="font-family:dejavusans; font-size:10pt; margin-bottom:8px;">
        Zu dieser Veranstaltung können wir Ihnen optional folgende Zertifizierungen anbieten:
    </div>
    <hr style="border-top:1px solid #aaa; margin-bottom: 5px;">
    <div style="font-family:dejavusans; font-size:10pt;">
        <span style="display:inline-block; width:70%;"><strong>Zertifizierung</strong></span>
        <span style="display:inline-block; width:29%; text-align:right;"><strong>Preis</strong></span>
    </div>
    <hr style="border-top:1px solid #aaa; margin-bottom: 8px;">';

        foreach ($certifications_data as $cert) {
            $name = htmlspecialchars($cert['name']);
            $price = str_replace(['.', ','], ['', '.'], $cert['price']);
            $percentage_raw = rtrim($cert['percentage'], '%');

            $ust_satz = ($percentage_raw === 'N/A') ? 20.00 : (float) $percentage_raw;
            $price = (float) $price;

            $ust = ($price / (100 + $ust_satz)) * $ust_satz;
            $zert_preis_netto = $price - $ust;
            $zert_preis_brutto = $zert_preis_netto + $ust;

            $total_brutto += $zert_preis_brutto;
            $total_netto  += $zert_preis_netto;

            $html .= '
            <div style="font-family:dejavusans; font-size:10pt; margin-bottom: 2px;">
                <span style="display:inline-block; width:70%;">' . $name . '</span>
                <span style="display:inline-block; width:29%; text-align:right;">' . number_format($zert_preis_netto, 2, ',', '.') . ' €</span>
            </div>
            <div style="font-family:dejavusans; color:#555; font-size:9pt; margin-bottom: 2px;">
                <span style="display:inline-block; width:70%;">+ ' . number_format($ust_satz, 2, ',', '.') . '% (von ' . number_format($zert_preis_netto, 2, ',', '.') . ' €)</span>
                <span style="display:inline-block; width:29%; text-align:right;">' . number_format($ust, 2, ',', '.') . ' €</span>
            </div>
            <hr style="border-top:0.5pt dashed #ccc; margin-top: 2px; margin-bottom: 2px;">
        ';
        }

        $html .= '
    <div style="font-family:dejavusans; font-size:10pt; margin-top: 8px;">
        <span style="display:inline-block; width:70%;">Gesamt Netto</span>
        <span style="display:inline-block; width:29%; text-align:right;">' . number_format($total_netto, 2, ',', '.') . ' €</span>
    </div>
    <div style="font-family:dejavusans; font-size:10pt; background-color:#f9f9f9; padding:2px 0;">
        <span style="display:inline-block; width:70%;"><strong>Gesamt Brutto</strong></span>
        <span style="display:inline-block; width:29%; text-align:right;"><strong>' . number_format($total_brutto, 2, ',', '.') . ' €</strong></span>
    </div>
    ';

        return $html;
    }
}
