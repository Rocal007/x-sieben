<?php
if (!function_exists('crm_clean_diplom_html')) {
    /**
     * Bereinigt WYSIWYG-Texte für das Diplom-PDF von unsichtbaren TinyMCE-/Screenreader-Tags,
     * filtert unberührte ACF-Standardplatzhalter heraus und formatiert Zeilen für TCPDF.
     */
    function crm_clean_diplom_html($content)
    {
        if (empty($content)) {
            return '';
        }
        // Entferne versteckte Screenreader/TinyMCE-Container (z. B. clip-path Divs)
        $content = preg_replace('/<div[^>]*style="[^"]*clip[^"]*"[^>]*>.*?<\/div>/is', '', $content);
        $content = preg_replace('/<div[^>]*role="(status|alert)"[^>]*>.*?<\/div>/is', '', $content);

        // Prüfen, ob nach Bereinigung relevanter Text vorhanden ist
        $text_only = trim(str_replace(['&nbsp;', '&#160;'], '', strip_tags($content)));
        if (empty($text_only)) {
            return '';
        }

        // Filtert unberührte ACF-Default-Platzhalter wie "Text rechts", "Text links", "Text-links"
        $normalized = mb_strtolower(trim(str_replace(['-', ' ', '.'], '', $text_only)));
        if ($normalized === 'textrechts' || $normalized === 'textlinks') {
            return '';
        }

        // Paragraphen in saubere Blöcke mit dezenten Abständen umwandeln
        $content = preg_replace('/<p[^>]*>/i', '', $content);
        $content = str_ireplace('</p>', '<br>', $content);
        $content = preg_replace('/(<br\s*\/?>\s*){2,}/i', '<br><br>', $content);

        return trim($content);
    }
}

if (!function_exists('crm_format_date_german_upper')) {
    /**
     * Formatiert ein beliebiges Datumsformat in ausgeschriebenes, deutsches Großbuchstaben-Format.
     * Beispiel: "27.05.2026" -> "27. MAI 2026"
     */
    function crm_format_date_german_upper($date_str)
    {
        if (empty($date_str)) {
            return '';
        }
        $ts = strtotime($date_str);
        if (!$ts) {
            return mb_strtoupper((string)$date_str, 'UTF-8');
        }
        $months = [
            1 => 'JÄNNER', 2 => 'FEBRUAR', 3 => 'MÄRZ', 4 => 'APRIL',
            5 => 'MAI', 6 => 'JUNI', 7 => 'JULI', 8 => 'AUGUST',
            9 => 'SEPTEMBER', 10 => 'OKTOBER', 11 => 'NOVEMBER', 12 => 'DEZEMBER'
        ];
        $day  = date('d', $ts);
        $m    = (int)date('n', $ts);
        $year = date('Y', $ts);
        $month_name = $months[$m] ?? date('F', $ts);

        return sprintf('%02d. %s %d', $day, $month_name, $year);
    }
}

if (!function_exists('crm_get_diplom_success_block')) {
    /**
     * Liefert den grammatikalisch und visuell korrekten Erfolgs-Block für das Diplom.
     *
     * Optionen:
     * - "erfolgreich"
     * - "mit gutem erfolg" / "mit sehr gutem erfolg"
     * - "mit ausgezeichnetem erfolg"
     */
    function crm_get_diplom_success_block($success_choice)
    {
        $choice = mb_strtolower(trim((string)$success_choice), 'UTF-8');

        if (strpos($choice, 'ausgezeichnet') !== false) {
            return [
                'has_mit' => true,
                'text'    => 'AUSGEZEICHNETEM ERFOLG',
                'key'     => 'mit ausgezeichnetem erfolg'
            ];
        } elseif (strpos($choice, 'sehr gut') !== false) {
            return [
                'has_mit' => true,
                'text'    => 'SEHR GUTEM ERFOLG',
                'key'     => 'mit sehr gutem erfolg'
            ];
        } elseif (strpos($choice, 'gut') !== false) {
            return [
                'has_mit' => true,
                'text'    => 'GUTEM ERFOLG',
                'key'     => 'mit gutem erfolg'
            ];
        } else {
            // "erfolgreich" / default
            return [
                'has_mit' => false,
                'text'    => 'ERFOLGREICH',
                'key'     => 'erfolgreich'
            ];
        }
    }
}

if (!function_exists('crm_get_diplom_kurstyp_phrase')) {
    /**
     * Erzeugt die grammatikalisch und typografisch korrekte Phrase für das Diplom basierend auf dem Kurstyp.
     * Beispiel:
     * - "Lehrgang" -> "HAT DEN LEHRGANG"
     * - "Seminar" -> "HAT DAS SEMINAR"
     * - "Crashkurs" -> "HAT DEN CRASHKURS"
     * - "Workshop" -> "HAT DEN WORKSHOP"
     * - "Ausbildung" -> "HAT DIE AUSBILDUNG"
     * - "Coaching" -> "HAT DAS COACHING"
     * - "Blended Learning" -> "HAT DAS BLENDED LEARNING"
     * - "eLearning" -> "HAT DAS ELEARNING"
     */
    function crm_get_diplom_kurstyp_phrase($kurstyp)
    {
        $kt = mb_strtolower(trim((string)$kurstyp), 'UTF-8');
        if (empty($kt)) {
            return 'HAT DEN LEHRGANG';
        }

        // Geschlechtsabhängige Artikelbestimmung im Akkusativ
        if (strpos($kt, 'seminar') !== false || strpos($kt, 'training') !== false || strpos($kt, 'coaching') !== false || strpos($kt, 'bundle') !== false || strpos($kt, 'learning') !== false || strpos($kt, 'elearning') !== false) {
            $article = 'DAS';
        } elseif (strpos($kt, 'ausbildung') !== false) {
            $article = 'DIE';
        } else {
            $article = 'DEN';
        }

        $upper = mb_strtoupper(trim((string)$kurstyp), 'UTF-8');
        return 'HAT ' . $article . ' ' . $upper;
    }
}

if (!function_exists('crm_get_diplom_curriculum_html')) {
    /**
     * Extrahiert strukturierte Ausbildungsinhalte aus dem Kurs-Akkordeon oder Modul-Repeatern (Apple-Stil).
     */
    function crm_get_diplom_curriculum_html($course_id, $anzahl_le = 0)
    {
        if (empty($course_id)) {
            return '';
        }
        $acc = get_post_meta($course_id, 'courses_accordion', true);
        if (is_string($acc)) {
            $acc = maybe_unserialize($acc);
        }
        $inhalte_raw = '';
        if (is_array($acc)) {
            foreach ($acc as $tab) {
                if (isset($tab['title']) && stripos($tab['title'], 'inhalte') !== false && !empty($tab['content'])) {
                    $inhalte_raw = $tab['content'];
                    break;
                }
            }
        }

        if (!empty($inhalte_raw)) {
            $c = preg_replace('/<div[^>]*style=["\'][^"\']*clip[^"\']*["\'][^>]*>.*?<\/div>/is', '', $inhalte_raw);
            $c = preg_replace('/<div[^>]*role=["\'](status|alert)["\'][^>]*>.*?<\/div>/is', '', $c);
            $c = preg_replace('/<h[1-6][^>]*>\s*<\/h[1-6]>/is', '', $c);
            $c = preg_replace('/<div[^>]*class=["\'][^"\']*collapsible[^"\']*["\'][^>]*>/is', '', $c);
            $c = str_replace('</div>', '', $c);

            if (preg_match('/(Insgesamt\s+\d+.*?)$/is', $c, $m)) {
                $c = $m[1];
            }

            // 1. Rohtext-Pfeile normalisieren & in saubere <li> Unterpunkte wandeln
            $c = str_replace(['-&gt;', '&rarr;', '→'], '->', $c);
            $c = preg_replace('/(?<!^)(?<!\n)\s*->\s*/u', "\n-> ", $c);
            $c = preg_replace_callback('/(?:^|\n)\s*->\s*(.+?)(?=\n|$)/u', function($m) {
                return "\n<ul><li>" . trim($m[1]) . "</li></ul>";
            }, $c);
            $c = preg_replace('/<\/ul>\s*<ul>/is', '', $c);
            $c = preg_replace('/<li[^>]*>\s*(?:->|→)\s*/iu', '<li>', $c);

            // 2. Modul-Überschriften und Absätze mit passenden Abständen versehen
            $c = preg_replace('/<strong>(.*?)<\/strong>/is', '<strong style="color: #0f172a;">$1</strong>', $c);

            // 3. Formatierungsfunktion für Listen mit großzügigen Abständen
            $format_list = function ($str) {
                return preg_replace_callback('/<ul>(.*?)<\/ul>/is', function ($m_ul) {
                    $items = $m_ul[1];
                    $items = preg_replace('/<li[^>]*>/is', '<li style="margin-bottom: 3.5px;">', $items);
                    return '<ul style="margin-top: 4px; margin-bottom: 12px; padding-left: 15px; list-style-type: disc;">' . $items . '</ul>';
                }, $str);
            };

            // 4. Intelligente Aufteilung auf Seiten 2 & 3 für ausgewogene Balancierung (~50/50)
            preg_match_all('/<li[^>]*>/i', $c, $m_all, PREG_OFFSET_CAPTURE);
            $total_items = count($m_all[0]);

            if ($total_items >= 16 && $total_items <= 32) {
                // Bei ca. 44% trennen, damit Seite 2 (inkl. Haupttitel + Intro) und Seite 3 visuell exakt balanciert sind
                $target_idx = (int)floor($total_items * 0.44);
                if ($target_idx > 0 && isset($m_all[0][$target_idx][1])) {
                    $offset = $m_all[0][$target_idx][1];
                    $part1 = substr($c, 0, $offset) . "</ul>";
                    $part2 = "<ul style=\"margin-top: 4px; margin-bottom: 12px; padding-left: 15px; list-style-type: disc;\">" . substr($c, $offset);

                    $part1 = $format_list($part1);
                    $part2 = $format_list($part2);

                    return $part1 . '</div><br pagebreak="true"/><div style="font-size: 10pt; color: #334155; line-height: 1.55; padding-top: 6px;">' . $part2;
                }
            }

            return $format_list($c);
        }

        $module_count = absint(get_post_meta($course_id, 'module', true));
        if ($module_count > 0) {
            $mods = [];
            for ($i = 0; $i < $module_count; $i++) {
                $m_title = get_post_meta($course_id, "module_{$i}_modul_titel", true);
                if (!empty($m_title) && $m_title !== '-') {
                    $mods[] = esc_html(trim($m_title));
                }
            }
            if (!empty($mods)) {
                $out = '<div style="margin-bottom: 8px;"><strong>Im Detail:</strong></div>';
                $out .= '<ul style="padding-left: 15px; list-style-type: disc;">';
                foreach ($mods as $m_item) {
                    $out .= '<li style="margin-bottom: 4px;">' . $m_item . '</li>';
                }
                $out .= '</ul>';
                return $out;
            }
        }

        return '';
    }
}

function xsieben_diplom_pdf($entry_id, $course_id, $output_to_browser = true, $success_override = null, $custom_sections = null)
{
    require_once dirname(__DIR__) . '/helpers/crm-pdf-sections.php';

    // TCPDF sicherstellen
    if (!class_exists('TCPDF')) {
        $tcpdf_path = get_template_directory() . '/tcbpdf/tcpdf.php';
        if (file_exists($tcpdf_path)) {
            require_once $tcpdf_path;
        }
    }

    // Lade Kurs- und Eintragsdaten
    $course = new CRM_Model($course_id, $entry_id);

    if (!empty($success_override)) {
        $course->diplom_success = $success_override;
    }

    $pdfAuthor = 'X SIEBEN Wirtschaftstraining GmbH';

    // Sauberen Dateinamen erzeugen
    $safe_title    = (function_exists('mb_substr') ? mb_substr(sanitize_file_name($course->titel_short ?: ($course->title ?: 'Kurs')), 0, 50) : substr(sanitize_file_name($course->titel_short ?: 'Kurs'), 0, 50));
    $safe_vorname  = sanitize_file_name($course->vorname ?: 'Teilnehmer');
    $safe_nachname = sanitize_file_name($course->nachname ?: 'Diplom');
    $token         = function_exists('crm_generate_pdf_token') ? crm_generate_pdf_token($entry_id, 'diplom') : '';
    $pdf_name      = 'Diplom_' . ($token ? $token . '_' : '') . $safe_title . '_' . $safe_vorname . '_' . $safe_nachname . '.pdf';

    // Assets-Pfade (vollständig autark, mit URL-Fallback für Live-Server)
    $logo_path    = crm_resolve_asset_path(file_exists(get_template_directory() . '/inc/core/crm/assets/x-sieben-logo-diplom.jpg') ? 'x-sieben-logo-diplom.jpg' : 'xsieben_logo.png');
    $stempel_path = crm_resolve_asset_path(file_exists(get_template_directory() . '/inc/core/crm/assets/stempel-diplom.png') ? 'stempel-diplom.png' : 'stempel.png');

    // Prüfungserfolg ermitteln
    $succ_raw      = $course->get_diplom_success() ?: 'erfolgreich';
    $succ_info     = crm_get_diplom_success_block($succ_raw);
    $zeitraum_verb = $succ_info['has_mit'] ? 'MIT' : '';

    // Teilnehmername: Anrede + Vorname in Normalcase, Nachname in GROSSBUCHSTABEN
    $name_prefix = array_filter([$course->anrede, $course->titel, $course->vorname]);
    $prefix_str  = implode(' ', $name_prefix);
    $last_upper  = mb_strtoupper(trim((string)$course->nachname), 'UTF-8');
    $full_name_html = trim(esc_html($prefix_str) . ' <strong style="font-weight: bold;">' . esc_html($last_upper) . '</strong>');

    // Kurstitel und optionaler Subtitel (- BEST OF - o.ä.)
    $raw_title = $course->title ?: $course->titel_short;
    $main_title = $raw_title;
    $subtitle   = '';

    if (mb_strlen($raw_title) > 38) {
        if (preg_match('/^(.*?)(?:\s*[-–—]\s*(BEST\s*OF.*?|LEHRGANG.*?|SEMINAR.*?))$/iu', $raw_title, $m_match)) {
            $main_title = trim($m_match[1]);
            $subtitle   = '- ' . trim($m_match[2]) . ' -';
        } elseif (preg_match('/^(.*?)(?:\s*–\s*|\s*-\s*)(.*)$/u', $raw_title, $m_match)) {
            $main_title = trim($m_match[1]);
            $subtitle   = '- ' . trim($m_match[2]) . ' -';
        }
    }

    $main_title_upper = mb_strtoupper($main_title, 'UTF-8');
    $subtitle_upper   = mb_strtoupper($subtitle, 'UTF-8');

    // Lehreinheiten
    $anzahl_le = intval($course->anzahl_le) ?: 142;

    // Datumsangaben
    $start_formatted = crm_format_date_german_upper($course->start_datum ?: date('d.m.Y'));
    $end_formatted   = crm_format_date_german_upper($course->end_datum ?: date('d.m.Y'));
    $issue_date      = $end_formatted ?: crm_format_date_german_upper(date('d.m.Y'));

    // Diplom-Nummer (5-stellig gepolstert aus Eintrag oder Fallback)
    $diplom_nr_raw = absint($entry_id) > 0 ? absint($entry_id) : (absint($course_id) > 0 ? absint($course_id) : 5624);
    $diplom_nr     = sprintf('%05d', $diplom_nr_raw);

    // Zertifizierungen des Kurses ermitteln
    $has_wba = false;
    $course_certs_meta = get_post_meta($course_id, 'zertifikate', true);
    if (!is_array($course_certs_meta) && !empty($course_certs_meta)) {
        $course_certs_meta = [$course_certs_meta];
    }
    if (!is_array($course_certs_meta)) {
        $course_certs_meta = [];
    }

    // Prüfe auch Repeater-Felder auf WBA
    if (function_exists('get_field')) {
        $rep = get_field('zertifizierungen', $course_id);
        if (is_array($rep)) {
            foreach ($rep as $r_item) {
                if (stripos($r_item['name-zert'] ?? '', 'wba') !== false) {
                    $has_wba = true;
                    break;
                }
            }
        }
    }
    if (in_array(5767, $course_certs_meta) || in_array('5767', $course_certs_meta)) {
        $has_wba = true;
    }

    // WBA Logo oben rechts (wenn dem Kurs zugeordnet, Apple-Stil mit Text)
    $wba_logo_html = '';
    if ($has_wba) {
        $wba_file = file_exists(get_template_directory() . '/inc/core/crm/assets/wba-diplom.png') ? 'wba-diplom.png' : 'wba-1.png';
        $wba_logo_html = '<img src="' . esc_attr(crm_resolve_asset_path($wba_file)) . '" width="140">';
    }

    require_once __DIR__ . '/elements/diplom-elements.php';

    // Fußzeilen-Tabelle mit Partnerlogos
    $bottom_certs_html = CRM_Pdf_Diplom_Elements::render_guetesiegel();

    // Ausbildungsinhalte aus ACF-Feldern: "diplom links" und "diplom rechts"
    $clean_links  = crm_clean_diplom_html($course->texte_fur_diplom_links ?? (function_exists('get_field') ? get_field('texte_fur_diplom_links', $course_id) : ''));
    $clean_rechts = crm_clean_diplom_html($course->texte_fur_diplom_rechts ?? (function_exists('get_field') ? get_field('texte_fur_diplom_rechts', $course_id) : ''));

    // Falls nicht manuell befüllt: Direkt aus dem Kurs-Akkordeon (tab: Inhalte) oder Modul-Repeater ziehen
    $curriculum_html = '';
    if (empty($clean_links) && empty($clean_rechts)) {
        if (function_exists('crm_get_diplom_curriculum_html')) {
            $curriculum_html = crm_get_diplom_curriculum_html($course_id, $anzahl_le);
        }
    }
    if (empty($clean_links) && empty($clean_rechts) && empty($curriculum_html)) {
        $clean_links = 'Einführung in das Themengebiet<br>Erfolgsfaktoren und Zieldefinition<br>Planung und Ressourcensteuerung<br>Praxisnahe Fallstudien und Methoden<br>Kommunikation und Teamorganisation';
        $clean_rechts = 'Vertiefende Fachkompetenzen<br>Agile Methoden und Frameworks<br>Qualitätssicherung und Prozessoptimierung<br>Abschließende Reflexion und Praxistransfer';
    }

    // Prüfungsabschluss-Satz je nach Erfolgs-Typ
    $exam_suffix = $succ_info['has_mit'] ? ' MIT' : '';

    // Kurstyp ermitteln und grammatikalische Phrase erzeugen (z. B. "HAT DEN LEHRGANG", "HAT DAS SEMINAR")
    $kurstyp_raw    = !empty($course->kurstyp) ? trim($course->kurstyp) : 'Lehrgang';
    $kurstyp_phrase = crm_get_diplom_kurstyp_phrase($kurstyp_raw);

    // Standard-Vorlagen für Abschnitte und Unterabschnitte
    $dip_header_subs       = CRM_Pdf_Diplom_Elements::get_header_subs($logo_path, $wba_logo_html);
    $dip_titel_subs        = CRM_Pdf_Diplom_Elements::get_titel_subs($full_name_html);
    $dip_lehrgang_subs     = CRM_Pdf_Diplom_Elements::get_lehrgang_subs($kurstyp_phrase, $main_title_upper, $subtitle_upper, $anzahl_le, $start_formatted, $end_formatted, $zeitraum_verb);
    $dip_abschluss_subs    = CRM_Pdf_Diplom_Elements::get_abschluss_subs($exam_suffix, $succ_info['text']);
    $dip_beglaubigung_subs = CRM_Pdf_Diplom_Elements::get_beglaubigung_subs($diplom_nr, $issue_date, $stempel_path);
    $dip_inhalte_subs      = CRM_Pdf_Diplom_Elements::get_inhalte_subs($clean_links, $clean_rechts, $curriculum_html);

    $dip_subsections = [
        'header'          => $dip_header_subs,
        'titel_absolvent' => $dip_titel_subs,
        'lehrgang'        => $dip_lehrgang_subs,
        'abschluss'       => $dip_abschluss_subs,
        'beglaubigung'    => $dip_beglaubigung_subs,
        'inhalte'         => $dip_inhalte_subs,
    ];

    $flattened_sub_generators = [];
    foreach ($dip_subsections as $sec_k => $subs) {
        if (is_array($subs)) {
            foreach ($subs as $sub_k => $sub_gen) {
                $flattened_sub_generators[$sub_k] = $sub_gen;
            }
        }
    }

    $footer_certs_final = $bottom_certs_html;

    // Abschnitte abrufen und ggf. filtern
    if (is_array($custom_sections) && !empty($custom_sections) && is_array(reset($custom_sections)) && isset(reset($custom_sections)['key'])) {
        $all_sections = $custom_sections;
    } else {
        $all_sections = crm_get_pdf_section_order('diplom', $entry_id);
        // Filter falls $custom_sections als Key-Liste übergeben wurde
        if (is_array($custom_sections) && !empty($custom_sections)) {
            $allowed_keys = is_string(reset($custom_sections)) ? $custom_sections : array_column($custom_sections, 'key');
            $filtered = [];
            foreach ($all_sections as $sec) {
                if (in_array($sec['key'], $allowed_keys, true)) {
                    $filtered[] = $sec;
                }
            }
            $all_sections = $filtered;
        }
    }
    $global_spacing = function_exists('crm_get_pdf_elements_spacing') ? crm_get_pdf_elements_spacing() : ['spacing_top' => 0, 'spacing_bottom' => 0];

    $page1_html      = '';
    $page2_html      = '';
    $header_html     = CRM_Pdf_Diplom_Elements::render_header($logo_path, $wba_logo_html);
    $inhalte_enabled = true;

    foreach ($all_sections as $sec) {
        $sec_key   = $sec['key'];
        $is_enabled = !empty($sec['enabled']);

        if ($sec_key === 'guetesiegel') {
            if (!$is_enabled) {
                $footer_certs_final = '';
            } elseif (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) {
                        $footer_certs_final = '';
                    } elseif (!empty($sub['content'])) {
                        if (strpos($sub['content'], '{standard}') !== false) {
                            $footer_certs_final = crm_replace_pdf_placeholders(str_replace('{standard}', $bottom_certs_html, $sub['content']), $course);
                        } else {
                            $footer_certs_final = crm_replace_pdf_placeholders($sub['content'], $course);
                        }
                    }
                }
            }
            continue;
        }

        if ($sec_key === 'header') {
            if (!$is_enabled) {
                $header_html = '';
            } elseif (!empty($sec['subsections'])) {
                $tds = [];
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    $sk = $sub['key'];
                    if (!empty($sub['is_custom']) && !empty($sub['content'])) {
                        $tds[] = '<td style="text-align:center; vertical-align:middle;">' . crm_replace_pdf_placeholders($sub['content'], $course) . '</td>';
                    } elseif (isset($dip_header_subs[$sk])) {
                        $def_sub = $dip_header_subs[$sk];
                        if (!empty($sub['content']) && $sub['content'] !== '{standard}') {
                            $custom = (strpos($sub['content'], '{standard}') !== false) ? str_replace('{standard}', $def_sub, $sub['content']) : $sub['content'];
                            $tds[] = crm_replace_pdf_placeholders($custom, $course);
                        } else {
                            $tds[] = $def_sub;
                        }
                    }
                }
                if (!empty($tds)) {
                    $header_html = '<table cellspacing="0" cellpadding="0" style="width: 100%;"><tr>' . implode('', $tds) . '</tr></table>';
                }
            }
            continue;
        }

        if (!$is_enabled) {
            if ($sec_key === 'inhalte') {
                $inhalte_enabled = false;
            }
            continue;
        }

        $sec_spacing = function_exists('crm_get_pdf_effective_spacing')
            ? crm_get_pdf_effective_spacing($sec, $global_spacing)
            : ['top' => 0, 'bottom' => 0];
        $sec_prefix  = function_exists('crm_get_pdf_spacing_html') ? crm_get_pdf_spacing_html($sec_spacing['top']) : '';
        $sec_suffix  = function_exists('crm_get_pdf_spacing_html') ? crm_get_pdf_spacing_html($sec_spacing['bottom']) : '';
        $sec_content = '';

        if (!empty($sec['is_custom'])) {
            $custom_body = '<div style="margin-bottom:6px; font-size:9pt; text-align:center;">';
            if (!empty($sec['title'])) {
                $custom_body .= '<div style="font-weight:bold; font-size:10pt; margin-bottom:4px;">' . esc_html($sec['title']) . '</div>';
            }
            if (!empty($sec['content'])) {
                $custom_body .= crm_replace_pdf_placeholders(nl2br($sec['content']), $course);
            }
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    $sub_sp_top = isset($sub['spacing_top']) && is_numeric($sub['spacing_top']) ? floatval($sub['spacing_top']) : 0.0;
                    $sub_sp_bottom = isset($sub['spacing_bottom']) && is_numeric($sub['spacing_bottom']) ? floatval($sub['spacing_bottom']) : 0.0;
                    $sub_prefix = ($sub_sp_top > 0 && function_exists('crm_get_pdf_spacing_html')) ? crm_get_pdf_spacing_html($sub_sp_top) : '';
                    $sub_suffix = ($sub_sp_bottom > 0 && function_exists('crm_get_pdf_spacing_html')) ? crm_get_pdf_spacing_html($sub_sp_bottom) : '';

                    $sk = $sub['key'] ?? '';
                    $sub_html = '';
                    if (!empty($sub['is_custom'])) {
                        if (!empty($sub['title'])) {
                            $sub_html .= '<div style="font-weight:bold; font-size:9pt; margin-top:4px; margin-bottom:2px;">' . esc_html($sub['title']) . '</div>';
                        }
                        if (!empty($sub['content'])) {
                            $sub_html .= '<div style="margin-top:2px;">' . crm_replace_pdf_placeholders(nl2br($sub['content']), $course) . '</div>';
                        }
                    } elseif (isset($flattened_sub_generators[$sk])) {
                        $def_sub = $flattened_sub_generators[$sk];
                        if (!empty($sub['content']) && $sub['content'] !== '{standard}') {
                            $custom = (strpos($sub['content'], '{standard}') !== false)
                                ? str_replace('{standard}', $def_sub, $sub['content'])
                                : $sub['content'];
                            $sub_html .= crm_replace_pdf_placeholders($custom, $course);
                        } else {
                            $sub_html .= $def_sub;
                        }
                    } elseif (!empty($sub['content'])) {
                        $sub_html .= '<div style="margin-top:2px;">' . crm_replace_pdf_placeholders(nl2br($sub['content']), $course) . '</div>';
                    }
                    if (!empty($sub_html)) {
                        $custom_body .= $sub_prefix . $sub_html . $sub_suffix;
                    }
                }
            }
            $custom_body .= '</div>';
            $sec_content = '<table cellspacing="0" cellpadding="0" style="width: 100%; text-align: center;"><tr><td>' . $custom_body . '</td></tr></table>';

        } elseif ($sec_key === 'titel_absolvent') {
            $inner = '';
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    $sk = $sub['key'];
                    if (!empty($sub['is_custom']) && !empty($sub['content'])) {
                        $inner .= '<div style="margin:4px 0;">' . crm_replace_pdf_placeholders($sub['content'], $course) . '</div>';
                    } elseif (isset($dip_titel_subs[$sk])) {
                        $def_sub = $dip_titel_subs[$sk];
                        if (!empty($sub['content']) && $sub['content'] !== '{standard}') {
                            $custom = (strpos($sub['content'], '{standard}') !== false) ? str_replace('{standard}', $def_sub, $sub['content']) : '<div style="margin:4px 0;">' . $sub['content'] . '</div>';
                            $inner .= crm_replace_pdf_placeholders($custom, $course);
                        } else {
                            $inner .= $def_sub;
                        }
                    }
                }
            } else {
                $inner = implode('', $dip_titel_subs);
            }
            if (!empty($inner)) {
                $sec_content = '<table cellspacing="0" cellpadding="0" style="width: 100%; text-align: center;"><tr><td>' . $inner . '</td></tr></table>';
            }

        } elseif ($sec_key === 'lehrgang') {
            $inner = '';
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    $sk = $sub['key'];
                    if (!empty($sub['is_custom']) && !empty($sub['content'])) {
                        $inner .= '<div style="margin:4px 0;">' . crm_replace_pdf_placeholders($sub['content'], $course) . '</div>';
                    } elseif (isset($dip_lehrgang_subs[$sk])) {
                        $def_sub = $dip_lehrgang_subs[$sk];
                        if (!empty($sub['content']) && $sub['content'] !== '{standard}') {
                            $custom = (strpos($sub['content'], '{standard}') !== false) ? str_replace('{standard}', $def_sub, $sub['content']) : '<div style="margin:4px 0;">' . $sub['content'] . '</div>';
                            $inner .= crm_replace_pdf_placeholders($custom, $course);
                        } else {
                            $inner .= $def_sub;
                        }
                    }
                }
            } else {
                $inner = implode('', $dip_lehrgang_subs);
            }
            if (!empty($inner)) {
                $sec_content = '<table cellspacing="0" cellpadding="0" style="width: 100%; text-align: center;"><tr><td>' . $inner . '</td></tr></table>';
            }

        } elseif ($sec_key === 'abschluss') {
            $inner = '';
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    $sk = $sub['key'];
                    if (!empty($sub['is_custom']) && !empty($sub['content'])) {
                        $inner .= '<div style="margin:4px 0;">' . crm_replace_pdf_placeholders($sub['content'], $course) . '</div>';
                    } elseif (isset($dip_abschluss_subs[$sk])) {
                        $def_sub = $dip_abschluss_subs[$sk];
                        if (!empty($sub['content']) && $sub['content'] !== '{standard}') {
                            $custom = (strpos($sub['content'], '{standard}') !== false) ? str_replace('{standard}', $def_sub, $sub['content']) : '<div style="margin:4px 0;">' . $sub['content'] . '</div>';
                            $inner .= crm_replace_pdf_placeholders($custom, $course);
                        } else {
                            $inner .= $def_sub;
                        }
                    }
                }
            } else {
                $inner = implode('', $dip_abschluss_subs);
            }
            if (!empty($inner)) {
                $sec_content = '<table cellspacing="0" cellpadding="0" style="width: 100%; text-align: center;"><tr><td>' . $inner . '</td></tr></table>';
            }

        } elseif ($sec_key === 'beglaubigung') {
            $inner = '';
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    $sk = $sub['key'];
                    if (!empty($sub['is_custom']) && !empty($sub['content'])) {
                        $inner .= '<div style="margin:4px 0;">' . crm_replace_pdf_placeholders($sub['content'], $course) . '</div>';
                    } elseif (isset($dip_beglaubigung_subs[$sk])) {
                        $def_sub = $dip_beglaubigung_subs[$sk];
                        if (!empty($sub['content']) && $sub['content'] !== '{standard}') {
                            $custom = (strpos($sub['content'], '{standard}') !== false) ? str_replace('{standard}', $def_sub, $sub['content']) : '<div style="margin:4px 0;">' . $sub['content'] . '</div>';
                            $inner .= crm_replace_pdf_placeholders($custom, $course);
                        } else {
                            $inner .= $def_sub;
                        }
                    }
                }
            } else {
                $inner = implode('', $dip_beglaubigung_subs);
            }
            if (!empty($inner)) {
                $sec_content = '<table cellspacing="0" cellpadding="0" style="width: 100%; text-align: center;"><tr><td>' . $inner . '</td></tr></table>';
            }

        } elseif ($sec_key === 'inhalte') {
            $inner = '';
            if (!empty($sec['subsections'])) {
                foreach ($sec['subsections'] as $sub) {
                    if (empty($sub['enabled'])) continue;
                    $sk = $sub['key'];
                    if (!empty($sub['is_custom']) && !empty($sub['content'])) {
                        $inner .= '<div style="margin:4px 0;">' . crm_replace_pdf_placeholders($sub['content'], $course) . '</div>';
                    } elseif (isset($dip_inhalte_subs[$sk])) {
                        $def_sub = $dip_inhalte_subs[$sk];
                        if (!empty($sub['content']) && $sub['content'] !== '{standard}') {
                            $custom = (strpos($sub['content'], '{standard}') !== false) ? str_replace('{standard}', $def_sub, $sub['content']) : '<div style="margin:4px 0;">' . $sub['content'] . '</div>';
                            $inner .= crm_replace_pdf_placeholders($custom, $course);
                        } else {
                            $inner .= $def_sub;
                        }
                    }
                }
            } else {
                $inner = CRM_Pdf_Diplom_Elements::render_inhalte($clean_links, $clean_rechts, null, $curriculum_html);
            }
            if (!empty($inner)) {
                $page2_html .= $sec_prefix . $inner . $sec_suffix;
            }
            continue; // Do NOT add to $page1_html!
        }

        if (!empty($sec_content)) {
            $page1_html .= $sec_prefix . $sec_content . $sec_suffix;
        }
    }

    // --- PDF-Erstellung via TCPDF im A4-Hochformat (Apple-Stil & 2-Seiten-Architektur) ---
    if (!class_exists('MYPDFA_diplom')) {
        class MYPDFA_diplom extends TCPDF
        {
            public $header_html = '';
            public $footer_certs_html = '';

            public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8', $diskcache = false, $pdfa = false)
            {
                parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
                $this->tcpdflink = false;
            }

            public function Header()
            {
                if (!empty($this->header_html)) {
                    $this->SetY(10);
                    $this->writeHTMLCell(0, 0, 16, '', $this->header_html, 0, 0, false, true, 'L', true);
                }
            }

            public function Footer()
            {
                if (!empty($this->footer_certs_html)) {
                    $this->SetY(-22);
                    $this->writeHTMLCell(0, 0, 16, '', $this->footer_certs_html, 0, 0, false, true, 'C', true);
                }
            }
        }
    }

    $pdf = new MYPDFA_diplom('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(true);
    $pdf->setPrintFooter(true);
    $pdf->header_html = $header_html;
    $pdf->footer_certs_html = $footer_certs_final;
    $pdf->SetMargins(16, 32, 16);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(22);
    $pdf->SetAutoPageBreak(false);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->SetFont('dejavusans', '', 9);
    $pdf->SetCellPadding(0);

    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($pdfAuthor);
    $pdf->SetTitle('Diplom ' . $diplom_nr . ' - ' . esc_html($safe_nachname));
    $pdf->SetSubject('Diplom ' . esc_html($course->titel_short));

    // Seite 1: Urkunde (Apple-Stil, 1 Seite ohne Ausbildungsinhalte)
    $pdf->AddPage('P', 'A4');
    if (function_exists('crm_clean_pdf_html')) {
        $page1_html = crm_clean_pdf_html($page1_html);
    }
    $pdf->writeHTML($page1_html, true, false, true, false, '');

    // Seite 2+: Ausbildungsinhalte (wenn aktiv und Inhalte vorhanden)
    if (!empty($page2_html) && $inhalte_enabled) {
        $pdf->SetMargins(16, 28, 16);
        $pdf->SetAutoPageBreak(true, 25);
        $pdf->AddPage('P', 'A4');
        if (function_exists('crm_clean_pdf_html')) {
            $page2_html = crm_clean_pdf_html($page2_html);
        }
        $pdf->writeHTML($page2_html, true, false, true, false, '');
    }

    // Zielordner sicherstellen
    $save_dir = function_exists('crm_get_pdf_storage_dir') ? crm_get_pdf_storage_dir() : (get_template_directory() . '/angebote/');
    if (!file_exists($save_dir)) {
        wp_mkdir_p($save_dir);
    }
    // Ältere Diplome dieses Teilnehmers bereinigen
    $existing_old_diplom = glob($save_dir . 'Diplom_*_' . $safe_vorname . '_' . $safe_nachname . '.pdf');
    if (!empty($existing_old_diplom)) {
        foreach ($existing_old_diplom as $old_f) {
            if (basename($old_f) !== $pdf_name && file_exists($old_f)) {
                @unlink($old_f);
            }
        }
    }

    $save_path = $save_dir . $pdf_name;
    $save_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $save_path);
    $pdf->Output($save_path, 'F');

    $storage_url = function_exists('crm_get_pdf_storage_url') ? crm_get_pdf_storage_url() : (get_template_directory_uri() . '/angebote/');
    $pdf_url = $storage_url . rawurlencode($pdf_name);

    if ($output_to_browser) {
        x_sieben_pdf_preview($pdf_url, $course_id, $entry_id, 'xsieben_diplom');
    } else {
        return $pdf_url;
    }
}
