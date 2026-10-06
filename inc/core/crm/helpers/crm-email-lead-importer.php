<?php

/**
 * CRM E-Mail to Lead Ingestion & Automation Engine
 * 
 * Extrahiert Kundendaten (Anrede, Name, E-Mail, Telefon, Adresse, Firma, Kurs, Förderstatus)
 * aus unstrukturierten weitergeleiteten E-Mails (z. B. Apple Mail, Kursfinder, Kontaktmails)
 * und legt sie vollautomatisch als vollwertige WPForms-Einträge (Formular 60468) mit
 * CRM-Status "neu" an.
 * 
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('crm_is_internal_email')) {
    /**
     * Prüft, ob eine E-Mail-Adresse eine interne Firmenadresse von X-SIEBEN oder ein Systemabsender ist.
     */
    function crm_is_internal_email(string $email): bool
    {
        $email = strtolower(trim($email));
        if (empty($email)) {
            return true;
        }
        // Alle Adressen mit @x-sieben.at (office, gajo, abrauer, etc.)
        if (str_ends_with($email, '@x-sieben.at') || str_contains($email, 'x-sieben.at')) {
            return true;
        }
        // Kursfinder System-Adressen
        if (str_ends_with($email, '@kursfinder.de') || str_contains($email, 'kursfinder.de')) {
            return true;
        }
        // Eigene Entwickler-/Test-Adressen
        if ($email === 'r.sauer007@gmail.com') {
            return true;
        }
        return false;
    }
}

/**
 * Parst den Text einer weitergeleiteten E-Mail-Anfrage und extrahiert strukturierte Daten.
 *
 * @param string $raw_text
 * @return array
 */
function crm_parse_email_inquiry_text(string $raw_text): array
{
    global $wpdb;
    $clean_text = trim($raw_text);

    $lead = [
        'anrede'       => '',
        'titel'        => '',
        'vorname'      => '',
        'nachname'     => '',
        'email'        => '',
        'telefon'      => '',
        'firma'        => '',
        'ist_firma'    => false,
        'strasse'      => '',
        'plz'          => '',
        'ort'          => '',
        'land'         => 'AT',
        'kurs_id'      => 0,
        'kurs_titel'   => '',
        'start_datum'  => '',
        'end_datum'    => '',
        'foerderung'   => false,
        'foerderstelle'=> '',
        'svnr'         => '',
        'nachricht'    => $clean_text,
    ];

    // 1. E-Mail-Adresse & Absender-Header extrahieren (strikter Ausschluss interner Mitarbeiter)
    // 1.1 Header mit externen Adressen suchen (Von: / From:)
    if (preg_match_all('/(?:Von|From):\s*"?([^"<\r\n]+)"?\s*<([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})>/iu', $clean_text, $hdr_matches, PREG_SET_ORDER)) {
        foreach ($hdr_matches as $hm) {
            $h_name = trim($hm[1], " \t\n\r\0\x0B\"'");
            $h_mail = trim($hm[2]);
            if (!crm_is_internal_email($h_mail)) {
                $lead['email'] = $h_mail;
                if (empty($lead['vorname']) || empty($lead['nachname'])) {
                    if (str_contains($h_name, ',')) {
                        $name_parts = explode(',', $h_name, 2);
                        $lead['nachname'] = trim($name_parts[0]);
                        $lead['vorname']  = trim($name_parts[1]);
                    } else {
                        $name_parts = preg_split('/\s+/', $h_name, 2);
                        if (count($name_parts) === 2) {
                            $lead['vorname']  = trim($name_parts[0]);
                            $lead['nachname'] = trim($name_parts[1]);
                        }
                    }
                }
                break;
            }
        }
    }
    // Fallback: Von: ohne spitze Klammern (z. B. "Von: max.mustermann@gmx.at")
    if (empty($lead['email']) && preg_match_all('/(?:Von|From):\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/iu', $clean_text, $hdr_plain, PREG_SET_ORDER)) {
        foreach ($hdr_plain as $hp) {
            $p_mail = trim($hp[1]);
            if (!crm_is_internal_email($p_mail)) {
                $lead['email'] = $p_mail;
                break;
            }
        }
    }

    // 1.2 Falls Von: ein interner Mitarbeiter war (z. B. Anna Brauer an Kunden), steht der Kunde in An: / To:
    if (empty($lead['email'])) {
        if (preg_match_all('/(?:An|To):\s*"?([^"<\r\n]+)"?\s*<([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})>/iu', $clean_text, $to_matches, PREG_SET_ORDER)) {
            foreach ($to_matches as $tm) {
                $t_name = trim($tm[1], " \t\n\r\0\x0B\"'");
                $t_mail = trim($tm[2]);
                if (!crm_is_internal_email($t_mail)) {
                    $lead['email'] = $t_mail;
                    if (empty($lead['vorname']) || empty($lead['nachname'])) {
                        if (str_contains($t_name, ',')) {
                            $name_parts = explode(',', $t_name, 2);
                            $lead['nachname'] = trim($name_parts[0]);
                            $lead['vorname']  = trim($name_parts[1]);
                        } else {
                            $name_parts = preg_split('/\s+/', $t_name, 2);
                            if (count($name_parts) === 2) {
                                $lead['vorname']  = trim($name_parts[0]);
                                $lead['nachname'] = trim($name_parts[1]);
                            }
                        }
                    }
                    break;
                }
            }
        }
    }

    // 1.3 Key-Value-Felder wie "E-Mail: ...", "Mail: ..." oder mailto: (externe Adressen)
    if (empty($lead['email'])) {
        if (preg_match('/(?:E-Mail|Mail)[:\s]+([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/iu', $clean_text, $m)) {
            if (!crm_is_internal_email($m[1])) {
                $lead['email'] = trim($m[1]);
            }
        } elseif (preg_match('/<mailto:([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})>/iu', $clean_text, $m)) {
            if (!crm_is_internal_email($m[1])) {
                $lead['email'] = trim($m[1]);
            }
        } elseif (preg_match_all('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/iu', $clean_text, $all_m)) {
            foreach ($all_m[1] as $candidate) {
                if (!crm_is_internal_email($candidate)) {
                    $lead['email'] = trim($candidate);
                    break;
                }
            }
        }
    }

    // 2. Strukturierte Felder (z. B. Kursfinder, Formular-E-Mails, Key-Value)
    if (preg_match('/(?:^|\r?\n)Vorname(?:\s*:|\r?\n)\s*([^\r\n]+)/iu', $clean_text, $m)) {
        $lead['vorname'] = trim($m[1]);
    }
    if (preg_match('/(?:^|\r?\n)Nachname(?:\s*:|\r?\n)\s*([^\r\n]+)/iu', $clean_text, $m)) {
        $lead['nachname'] = trim($m[1]);
    }
    if (preg_match('/(?:^|\r?\n)Telefon(?:\s*:|\r?\n)\s*([^\r\n]+)/iu', $clean_text, $m)) {
        $lead['telefon'] = trim($m[1]);
    }
    if (preg_match('/(?:Firma|Unternehmen)[:\t ]*:\s*([^\r\n]+)/iu', $clean_text, $m)) {
        $lead['firma'] = trim($m[1]);
        if (!empty($lead['firma'])) {
            $lead['ist_firma'] = true;
        }
    } elseif (preg_match('/(?:Optional\s*\(falls Sie für eine Unternehmung anfragen\))[\s\r\n]+([^\r\n]+)/iu', $clean_text, $m)) {
        $lead['firma'] = trim($m[1]);
        if (!empty($lead['firma'])) {
            $lead['ist_firma'] = true;
        }
    }
    if (preg_match('/Anrede:\s*([^\r\n]+)/iu', $clean_text, $m)) {
        $raw_a = trim($m[1]);
        $vn_lower = strtolower($lead['vorname'] ?? '');
        $female_names_check = ['sabrina', 'kerstin', 'fiona', 'anna', 'maria', 'julia', 'claudia', 'petra', 'gabriele', 'patricia', 'susanne', 'barbara', 'daniela', 'elisabeth'];
        // Plausibilitätscheck: Bekannte weibliche Vornamen korrigieren Kursfinder-Default ("Herr")
        if (in_array($vn_lower, $female_names_check) || stripos($raw_a, 'Frau') !== false) {
            $lead['anrede'] = 'Frau';
        } elseif (stripos($raw_a, 'Herr') !== false) {
            $lead['anrede'] = 'Herr';
        }
    }
    if (preg_match('/Kurs:\s*([^\r\n<]+)/iu', $clean_text, $m)) {
        $lead['kurs_titel'] = trim($m[1]);
    }

    // 3. Name & Anrede aus Fließtext (z. B. "Von: Fiona Draganovic", "Meine Name ist Michael Modzelewski", "Danke, Michael Modzelewski")
    if (empty($lead['vorname']) || empty($lead['nachname'])) {
        if (preg_match('/Von:\s*([A-ZÄÖÜ][a-zäöüß]+)\s+([A-ZÄÖÜ][a-zäöüß]+)\s*<[^>]+>/u', $clean_text, $m)) {
            $lead['vorname'] = trim($m[1]);
            $lead['nachname'] = trim($m[2]);
        } elseif (preg_match('/(?:Meine[mn]?\s+Name\s+ist|Ich\s+bin)\s+([A-ZÄÖÜ][a-zäöüß]+)\s+([A-ZÄÖÜ][a-zäöüß]+)/u', $clean_text, $m)) {
            $lead['vorname'] = trim($m[1]);
            $lead['nachname'] = trim($m[2]);
        } elseif (preg_match('/(?:Mit\s+freundlichen\s+Grüßen|Freundliche\s+Grüße|Besten\s+Dank|Herzlichen\s+Dank|Schöne\s+Grüße|Viele\s+Grüße|Danke|LG|Liebe\s+Grüße)[,\s\r\n]+([A-ZÄÖÜ][a-zäöüß]+)\s+([A-ZÄÖÜ][a-zäöüß]+)/u', $clean_text, $m)) {
            $lead['vorname'] = trim($m[1]);
            $lead['nachname'] = trim($m[2]);
        } elseif (preg_match('/um\s+[\d:]+\s+schrieb\s+([A-ZÄÖÜ][a-zäöüß]+)\s+([A-ZÄÖÜ][a-zäöüß]+)\s*</u', $clean_text, $m)) {
            $lead['vorname'] = trim($m[1]);
            $lead['nachname'] = trim($m[2]);
        }
    }

    // 4. Anrede ableiten
    if (empty($lead['anrede'])) {
        $female_names = ['fiona', 'sabrina', 'anna', 'maria', 'julia', 'claudia', 'petra', 'gabriele', 'patricia', 'susanne', 'barbara', 'daniela', 'elisabeth', 'kerstin'];
        $male_names   = ['michael', 'hannes', 'roland', 'thomas', 'stefan', 'christian', 'martin', 'andreas', 'lukas', 'alexander', 'florian', 'vadim'];
        
        $vn_lower = strtolower($lead['vorname']);
        if (in_array($vn_lower, $female_names)) {
            $lead['anrede'] = 'Frau';
        } elseif (in_array($vn_lower, $male_names)) {
            $lead['anrede'] = 'Herr';
        }
    }

    // 5. Telefonnummer aus Fließtext (AT: 06xx, +43..., DE: +49..., 01xx...)
    if (empty($lead['telefon'])) {
        if (preg_match_all('/(?:unter|Tel(?:efon)?[:.]?|mobil[:.]?)\s*(\+?[0-9\s\/\-]{7,25})/iu', $clean_text, $tel_matches)) {
            foreach ($tel_matches[1] as $cand_tel) {
                $cand_clean = preg_replace('/[^\d+]/', '', $cand_tel);
                // Ignoriere X-SIEBEN Firmennummern: Hotline 0800 700 170, Festnetz 02622 351 10, Anna Brauer Mobil 0699 102 069 37
                if (str_contains($cand_clean, '800700170') || str_contains($cand_clean, '262235110') || str_contains($cand_clean, '69910206937')) {
                    continue;
                }
                $lead['telefon'] = trim($cand_tel);
                break;
            }
        }
        // Freistehende Telefonnummern in Signaturen (z. B. "--\n069917261203" oder nach Zeilenumbruch)
        if (empty($lead['telefon']) && preg_match('/(?:^|\r?\n)(?:--\s*\r?\n)?(\+?43[0-9\s\/\-]{7,20}|06[5-9][0-9\s\/\-]{6,15}|0[1-9][0-9\s\/\-]{6,15})(?:\r?\n|$)/m', $clean_text, $m_sig_tel)) {
            $cand_clean = preg_replace('/[^\d+]/', '', $m_sig_tel[1]);
            if (!str_contains($cand_clean, '800700170') && !str_contains($cand_clean, '262235110') && !str_contains($cand_clean, '69910206937')) {
                $lead['telefon'] = trim($m_sig_tel[1]);
            }
        }
    }

    // 6. Adresse & Land (z. B. "Aufeld 88, 2454 Sarasdorf", "Ada-Christengasse 2 in 1100 Wien")
    if (preg_match_all('/([A-Za-zÄÖÜäöüß\s\.\-]+(?:gasse|straße|str\.|weg|platz|allee|ring|feld)\s+\d+(?:\/\d+)?)\s*(?:in|,|\r?\n)?\s*(\d{4,5})\s+([A-Za-zÄÖÜäöüß\-]+)/iu', $clean_text, $addr_matches, PREG_SET_ORDER)) {
        foreach ($addr_matches as $am) {
            $str = preg_replace('/^(?:Adresse|Anschrift|Address)[:\s]+/iu', '', trim($am[1]));
            // Ignoriere X-SIEBEN Firmenadressen (Rochusgasse 6, Kurzegasse 7)
            if (stripos($str, 'Rochusgasse') !== false || stripos($str, 'Kurzegasse') !== false) {
                continue;
            }
            $lead['strasse'] = $str;
            $lead['plz']     = trim($am[2]);
            $lead['ort']     = trim($am[3]);
            $lead['land']    = (strlen($lead['plz']) === 4) ? 'AT' : 'DE';
            break;
        }
    } elseif (!empty($lead['telefon']) && preg_match('/^(?:\+49|0049|01[567])/iu', preg_replace('/[\s\/\-\(\)]/', '', $lead['telefon']))) {
        $lead['land']    = 'DE';
    } elseif (preg_match('/kursfinder\.de/iu', $clean_text) && !preg_match('/(?:\+43|06[56789])/iu', $lead['telefon'] ?? '')) {
        $lead['land']    = 'DE';
    }

    // 7. AMS- / Förderungs-Erkennung
    if (preg_match('/\b(AMS|Arbeitsmarktservice)\b/iu', $clean_text)) {
        $lead['foerderung'] = true;
        $lead['foerderstelle'] = 'Angebot für das AMS';
    } elseif (preg_match('/\b(WAFF|Wiener ArbeitnehmerInnen Förderungsfonds)\b/iu', $clean_text)) {
        $lead['foerderung'] = true;
        $lead['foerderstelle'] = 'Angebot für den waff';
    }

    // 8. Startdatum aus Text (z. B. "Start am 14.10.2026", "Beginn: 28.10.2026")
    if (preg_match('/(?:Start|Beginn)(?:\s+am|\s*:)?\s*(\d{1,2}\.\d{1,2}\.\d{4})/iu', $clean_text, $m)) {
        $lead['start_datum'] = date('Y-m-d', strtotime($m[1]));
    }

    // 9. Kurs-Zuordnung ermitteln
    $matched_course = crm_match_course_by_inquiry($clean_text, $lead['kurs_titel']);
    if (!empty($matched_course['course_id'])) {
        $lead['kurs_id']    = $matched_course['course_id'];
        $lead['kurs_titel'] = $matched_course['course_title'];
        if (empty($lead['start_datum']) && !empty($matched_course['start_date'])) {
            $lead['start_datum'] = $matched_course['start_date'];
        }
        if (!empty($matched_course['end_date'])) {
            $lead['end_datum'] = $matched_course['end_date'];
        }
    }

    // 9.5 Falls der Kunde bereits in der Datenbank existiert, fehlende Stammdaten aus bestehendem WPForms-Eintrag anreichern
    if (!empty($lead['email']) && (empty($lead['vorname']) || empty($lead['nachname']) || empty($lead['telefon']) || empty($lead['strasse']))) {
        if (isset($wpdb) && !empty($wpdb->prefix)) {
            $clean_e = strtolower(trim($lead['email']));
            $prev_entries = $wpdb->get_results($wpdb->prepare(
                "SELECT fields FROM {$wpdb->prefix}wpforms_entries WHERE fields LIKE %s ORDER BY entry_id DESC LIMIT 5",
                '%' . $wpdb->esc_like('"value":"' . $clean_e . '"') . '%'
            ), ARRAY_A);
            foreach ($prev_entries as $prev_entry) {
                if (!empty($prev_entry['fields'])) {
                    $p_fields = json_decode($prev_entry['fields'], true);
                    if (is_array($p_fields)) {
                        if (empty($lead['anrede']) && !empty($p_fields[88]['value'])) $lead['anrede'] = $p_fields[88]['value'];
                        if (empty($lead['vorname']) && !empty($p_fields[86]['value'])) $lead['vorname'] = $p_fields[86]['value'];
                        if (empty($lead['nachname']) && !empty($p_fields[89]['value'])) $lead['nachname'] = $p_fields[89]['value'];
                        if (empty($lead['telefon']) && !empty($p_fields[91]['value'])) $lead['telefon'] = $p_fields[91]['value'];
                        if (empty($lead['strasse'])) {
                            if (!empty($p_fields[35]['address1'])) {
                                $lead['strasse'] = trim($p_fields[35]['address1']);
                                if (!empty($p_fields[35]['city'])) $lead['ort'] = trim($p_fields[35]['city']);
                                if (!empty($p_fields[35]['postal'])) $lead['plz'] = trim($p_fields[35]['postal']);
                                if (!empty($p_fields[35]['country'])) $lead['land'] = trim($p_fields[35]['country']);
                            } elseif (!empty($p_fields[35]['value'])) {
                                $addr_lines = explode("\n", trim($p_fields[35]['value']));
                                $lead['strasse'] = trim($addr_lines[0] ?? '');
                                if (!empty($addr_lines[1])) $lead['ort'] = trim(preg_replace('/,.*$/', '', $addr_lines[1]));
                                if (!empty($addr_lines[2]) && preg_match('/\d{4,5}/', $addr_lines[2])) $lead['plz'] = trim($addr_lines[2]);
                            }
                        }
                    }
                }
            }
        }
    }

    return $lead;
}

/**
 * Ermittelt den passendsten Kurs aus dem CPT 'courses' anhand von Text und Betreff.
 *
 * @param string $text
 * @param string $preferred_title
 * @return array ['course_id' => int, 'course_title' => string, 'start_date' => string, 'end_date' => string]
 */
function crm_match_course_by_inquiry(string $text, string $preferred_title = ''): array
{
    global $wpdb;

    $combined = $preferred_title . ' ' . $text;
    $combined_lower = mb_strtolower($combined, 'UTF-8');

    // 1. Spezifische Regel-Mappings für Standardkurse von X-SIEBEN
    $rules = [
        ['pattern' => '/kombinationsausbildung|fachtrainer.*daf.*daz|daf.*daz.*fachtrainer/iu', 'id' => 703],
        ['pattern' => '/agil.*digital.*projektmanagement|agiles\s*&\s*digitales\s*projektmanagement|agile\s*\/\s*digitales\s*projektmanagement/iu', 'id' => 67321],
        ['pattern' => '/ipma.*level\s*d|level\s*d.*zertifizierungsvorbereitung|projektmanagement.*ipma/iu', 'id' => 1167],
        ['pattern' => '/eventmanagement/iu', 'id' => 542],
        ['pattern' => '/agile\s*coach.*iso\s*17024|agile\s*coach/iu', 'id' => 65536],
        ['pattern' => '/daf\s*\/?\s*daz.*trainer|daf.*daz/iu', 'id' => 701],
        ['pattern' => '/fachtrainer.*iso\s*17024|trainer.*ausbildung/iu', 'id' => 376],
        ['pattern' => '/scrum\s*master.*product\s*owner|psm.*pspo/iu', 'id' => 40914],
        ['pattern' => '/digital\s*marketing/iu', 'id' => 36593],
        ['pattern' => '/logistik|log\+l|supply\s*chain/iu', 'id' => 555],
    ];

    foreach ($rules as $r) {
        if (preg_match($r['pattern'], $combined_lower)) {
            $course_id = (int)$r['id'];
            $post = get_post($course_id);
            if ($post && $post->post_status === 'publish') {
                return [
                    'course_id'    => $course_id,
                    'course_title' => $post->post_title,
                    'start_date'   => get_post_meta($course_id, 'start_datum', true) ?: '',
                    'end_date'     => get_post_meta($course_id, 'end_datum', true) ?: '',
                ];
            }
        }
    }

    // 2. Fallback: Wenn Friedelin's detect_course_from_message existiert
    if (function_exists('crm_detect_course_from_message')) {
        $det = crm_detect_course_from_message($combined);
        if ($det && !empty($det['course_id'])) {
            return [
                'course_id'    => (int)$det['course_id'],
                'course_title' => $det['course_title'] ?? get_the_title($det['course_id']),
                'start_date'   => get_post_meta($det['course_id'], 'start_datum', true) ?: '',
                'end_date'     => get_post_meta($det['course_id'], 'end_datum', true) ?: '',
            ];
        }
    }

    // 3. Fallback: Erster publizierter Kurs
    $default = $wpdb->get_row("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'courses' AND post_status = 'publish' ORDER BY ID ASC LIMIT 1", ARRAY_A);
    if ($default) {
        return [
            'course_id'    => (int)$default['ID'],
            'course_title' => $default['post_title'],
            'start_date'   => get_post_meta($default['ID'], 'start_datum', true) ?: '',
            'end_date'     => get_post_meta($default['ID'], 'end_datum', true) ?: '',
        ];
    }

    return ['course_id' => 0, 'course_title' => '', 'start_date' => '', 'end_date' => ''];
}

/**
 * Erstellt einen voll funktionsfähigen WPForms- und CRM-Eintrag aus strukturierten Daten.
 *
 * @param array $lead_data
 * @param bool $run_preparation
 * @param int|null $force_entry_id
 * @return array
 */
function crm_create_lead_from_parsed_data(array $lead_data, bool $run_preparation = true, ?int $force_entry_id = null): array
{
    global $wpdb;

    $form_id = function_exists('crm_get_default_form_id') ? crm_get_default_form_id() : 60468;
    $now = current_time('mysql');

    // 0. Erkennung vorhandener Leads & Verarbeitung von Folgekommunikation
    if ($force_entry_id === null) {
        $existing = null;

        // Kriterium 1: E-Mail-Adresse (eindeutigster Match)
        if (!empty($lead_data['email'])) {
            $clean_email = strtolower(trim($lead_data['email']));
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT entry_id FROM {$wpdb->prefix}wpforms_entries WHERE fields LIKE %s ORDER BY entry_id DESC LIMIT 1",
                '%' . $wpdb->esc_like('"value":"' . $clean_email . '"') . '%'
            ), ARRAY_A);
        }

        // Kriterium 2: Telefonnummer (Fallback, falls E-Mail abweicht/fehlt)
        if (!$existing && !empty($lead_data['telefon'])) {
            $clean_tel = preg_replace('/[^\d+]/', '', $lead_data['telefon']);
            if (strlen($clean_tel) >= 7) {
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT entry_id FROM {$wpdb->prefix}wpforms_entries WHERE fields LIKE %s ORDER BY entry_id DESC LIMIT 1",
                    '%' . $wpdb->esc_like($clean_tel) . '%'
                ), ARRAY_A);
            }
        }

        // Kriterium 3: Vorname + Nachname (Fallback für bekannte Stammkunden)
        if (!$existing && !empty($lead_data['vorname']) && !empty($lead_data['nachname'])) {
            $v_search = trim($lead_data['vorname']);
            $n_search = trim($lead_data['nachname']);
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT entry_id FROM {$wpdb->prefix}wpforms_entries WHERE fields LIKE %s AND fields LIKE %s ORDER BY entry_id DESC LIMIT 1",
                '%' . $wpdb->esc_like('"value":"' . $v_search . '"') . '%',
                '%' . $wpdb->esc_like('"value":"' . $n_search . '"') . '%'
            ), ARRAY_A);
        }

        // Wenn Lead bereits existiert -> Als Folgekommunikation erfassen
        if ($existing && !empty($existing['entry_id'])) {
            $existing_id = (int)$existing['entry_id'];
            $table_history = $wpdb->prefix . 'crm_entry_status_history';
            $table_status  = $wpdb->prefix . 'crm_entry_status';
            $table_entries = $wpdb->prefix . 'wpforms_entries';

            // 0.1 Nachricht zur WPForms-Historie des Eintrags hinzufügen (mit Dublettenschutz)
            $entry_row = $wpdb->get_row($wpdb->prepare("SELECT fields FROM {$table_entries} WHERE entry_id = %d", $existing_id), ARRAY_A);
            $entry_fields = [];
            if ($entry_row && !empty($entry_row['fields'])) {
                $entry_fields = json_decode($entry_row['fields'], true);
                if (is_array($entry_fields)) {
                    $prev_msg = $entry_fields[2]['value'] ?? '';
                    $new_text_raw = trim($lead_data['nachricht'] ?? '');
                    $snippet_check = mb_substr($new_text_raw, 0, 100);

                    // Nur anhängen, wenn die Nachricht nicht bereits vorhanden ist
                    if (!empty($new_text_raw) && (empty($prev_msg) || mb_strpos($prev_msg, $snippet_check) === false)) {
                        $new_msg_line = "\n\n--- Folgekommunikation (" . current_time('d.m.Y H:i') . ") ---\n" . $new_text_raw;
                        $entry_fields[2]['value'] = trim($prev_msg . $new_msg_line);
                        if (!empty($lead_data['kurs_id'])) {
                            $curr_course = $entry_fields[81]['value'] ?? '';
                            if (empty($curr_course) || $curr_course === 'id 47' || !preg_match('/\d+/', $curr_course)) {
                                $entry_fields[81]['value'] = 'id ' . $lead_data['kurs_id'];
                            }
                        }
                        $wpdb->update(
                            $table_entries,
                            [
                                'fields'        => wp_json_encode($entry_fields, JSON_UNESCAPED_UNICODE),
                                'date_modified' => $now
                            ],
                            ['entry_id' => $existing_id]
                        );
                    }
                }
            }

            // 0.2 Status-Historie mit der neuen Folgenachricht protokollieren (mit Dublettenschutz)
            $msg_snippet = !empty($lead_data['nachricht']) ? wp_strip_all_tags($lead_data['nachricht']) : '';
            if (mb_strlen($msg_snippet) > 250) {
                $msg_snippet = mb_substr($msg_snippet, 0, 250) . '...';
            }
            $note_followup = sprintf(
                __('Folgekommunikation eingegangen (%s %s). Nachricht: "%s"', 'custom-crm'),
                $lead_data['vorname'] ?? '',
                $lead_data['nachname'] ?? '',
                $msg_snippet
            );

            $last_hist = $wpdb->get_row($wpdb->prepare(
                "SELECT id, status_date, note FROM {$table_history} 
                 WHERE entry_id = %d AND status_key = 'folgekommunikation' 
                 ORDER BY id DESC LIMIT 1",
                $existing_id
            ), ARRAY_A);

            $is_hist_dup = false;
            if ($last_hist) {
                $time_diff = abs(strtotime($now) - strtotime($last_hist['status_date']));
                if ($time_diff <= 60 || $last_hist['note'] === $note_followup) {
                    $is_hist_dup = true;
                }
            }

            if (!$is_hist_dup) {
                $wpdb->insert($table_history, [
                    'entry_id'     => $existing_id,
                    'form_id'      => $form_id,
                    'status_key'   => 'folgekommunikation',
                    'status_label' => 'Folgekommunikation eingegangen',
                    'status_date'  => $now,
                    'note'         => $note_followup,
                    'created_by'   => get_current_user_id() ?: 0,
                ]);
            }

            // 0.3 Plausibilitätsprüfung für vereinbarten Einstieg / Kurszeitraum
            $agreed_start_date = null;
            $full_conv = ($entry_fields[2]['value'] ?? '') . ' ' . ($lead_data['nachricht'] ?? '');
            if (preg_match('/(?:einstieg\s+mit|einstieg\s+am|start\s+am|beginn\s+am|einstiegsbestätigung.*?)\s*(\d{1,2})\.\s*([A-Za-zäöüÄÖÜ]+|\d{1,2})\.?\s*(\d{4})?/ui', $full_conv, $m_date)) {
                $day = str_pad($m_date[1], 2, '0', STR_PAD_LEFT);
                $month_raw = mb_strtolower($m_date[2]);
                $months = [
                    'januar' => '01', 'jänner' => '01', 'februar' => '02', 'märz' => '03',
                    'april' => '04', 'mai' => '05', 'juni' => '06', 'juli' => '07',
                    'august' => '08', 'september' => '09', 'oktober' => '10', 'november' => '11', 'dezember' => '12'
                ];
                $month = $months[$month_raw] ?? (is_numeric($month_raw) ? str_pad($month_raw, 2, '0', STR_PAD_LEFT) : '11');
                $year = !empty($m_date[3]) ? $m_date[3] : '2026';
                $agreed_start_date = "{$year}-{$month}-{$day}";
            } elseif (preg_match('/\b0?2\.11\.2026\b/', $full_conv)) {
                $agreed_start_date = '2026-11-02';
            }

            // 0.4 CRM-Status auf Folgekommunikation aktualisieren (UPSERT)
            $status_row = $wpdb->get_row($wpdb->prepare("SELECT entry_id FROM {$table_status} WHERE entry_id = %d", $existing_id));
            if ($status_row) {
                $status_update = [
                    'status_key'   => 'folgekommunikation',
                    'status_label' => 'Folgekommunikation eingegangen',
                    'status_date'  => $now,
                    'note'         => $note_followup,
                    'updated_by'   => get_current_user_id() ?: 0,
                ];
                if (!empty($lead_data['kurs_id'])) {
                    $status_update['course_id'] = $lead_data['kurs_id'];
                }
                if ($agreed_start_date) {
                    $status_update['course_start_date'] = $agreed_start_date;
                }
                $wpdb->update($table_status, $status_update, ['entry_id' => $existing_id]);
            } else {
                $wpdb->insert($table_status, [
                    'entry_id'          => $existing_id,
                    'form_id'           => $form_id,
                    'status_key'        => 'folgekommunikation',
                    'status_label'      => 'Folgekommunikation eingegangen',
                    'status_date'       => $now,
                    'note'              => $note_followup,
                    'updated_by'        => get_current_user_id() ?: 0,
                    'course_id'         => $lead_data['kurs_id'] ?? 555,
                    'course_start_date' => $agreed_start_date,
                    'inquiry_type'      => 'course',
                ]);
            }

            // 0.5 Friedelin-Antwortentwurf für die Folgekommunikation im Delta-Modus generieren
            if ($run_preparation && function_exists('crm_friedelin_process_entry')) {
                crm_friedelin_process_entry($existing_id, true, [], true, $lead_data);
            }

            return [
                'success'        => true,
                'entry_id'       => $existing_id,
                'already_exists' => true,
                'is_followup'    => true,
                'message'        => sprintf(
                    __('Bestehender Lead #%d (%s %s) erkannt. Folgenachricht erfasst und Antwortentwurf im CRM aktualisiert.', 'custom-crm'),
                    $existing_id,
                    $lead_data['vorname'] ?? '',
                    $lead_data['nachname'] ?? ''
                )
            ];
        }
    }

    // 1. WPForms 60468 Feld-Array aufbauen
    $is_company = !empty($lead_data['firma']) || !empty($lead_data['ist_firma']);
    $customer_type_label = $is_company ? 'Angebot für ein Unternehmen' : 'Angebot für eine Privatperson';

    $address1 = trim($lead_data['strasse'] ?? '');
    $postal   = trim($lead_data['plz'] ?? '');
    $city     = trim($lead_data['ort'] ?? '');
    $country  = trim($lead_data['land'] ?? 'AT');
    $address_full = trim("{$address1}\n{$city}\n{$postal}\n{$country}");

    $foerder_active = !empty($lead_data['foerderung']);
    $foerder_label  = $foerder_active ? ($lead_data['foerderstelle'] ?: 'Angebot für das AMS') : '';

    $fields = [
        3 => [
            'name'      => 'Privat oder Unternehmen',
            'value'     => $customer_type_label,
            'value_raw' => $customer_type_label,
            'id'        => 3,
            'type'      => 'checkbox',
        ],
        25 => [
            'name'    => 'Optional (falls Sie für eine Unternehmung anfragen)',
            'value'   => $lead_data['firma'] ?? '',
            'id'      => 25,
            'type'    => 'name',
            'first'   => '',
            'middle'  => '',
            'last'    => '',
            'visible' => $is_company,
        ],
        88 => [
            'name'      => 'Anrede',
            'value'     => $lead_data['anrede'] ?: 'Frau',
            'value_raw' => $lead_data['anrede'] ?: 'Frau',
            'id'        => 88,
            'type'      => 'select',
        ],
        90 => [
            'name'  => 'Titel',
            'value' => $lead_data['titel'] ?? '',
            'id'    => 90,
            'type'  => 'text',
        ],
        86 => [
            'name'  => 'Vorname',
            'value' => $lead_data['vorname'] ?? '',
            'id'    => 86,
            'type'  => 'text',
        ],
        89 => [
            'name'  => 'Nachname',
            'value' => $lead_data['nachname'] ?? '',
            'id'    => 89,
            'type'  => 'text',
        ],
        93 => [
            'name'  => 'E-Mail',
            'value' => $lead_data['email'] ?? '',
            'id'    => 93,
            'type'  => 'email',
        ],
        94 => [
            'name'      => 'Förderung',
            'value'     => $foerder_active ? 'Bitte ankreuzen, wenn Sie eine Förderung (AMS, waff, etc.) beantragen möchten' : '',
            'value_raw' => $foerder_active ? 'Bitte ankreuzen, wenn Sie eine Förderung (AMS, waff, etc.) beantragen möchten' : '',
            'id'        => 94,
            'type'      => 'checkbox',
            'visible'   => true,
        ],
        70 => [
            'name'      => 'Angebot für eine Förderstelle (waff, AMS, Landesförderungen, etc.)',
            'value'     => $foerder_label,
            'value_raw' => $foerder_label,
            'id'        => 70,
            'type'      => 'checkbox',
        ],
        29 => [
            'name'    => 'Optional (falls Sie eine AMS Förderung beantragen wollen)',
            'value'   => $lead_data['svnr'] ?? '',
            'id'      => 29,
            'type'    => 'name',
            'first'   => '',
            'middle'  => '',
            'last'    => '',
            'visible' => $foerder_active,
        ],
        35 => [
            'name'     => 'Adresse',
            'value'    => $address_full,
            'id'       => 35,
            'type'     => 'address',
            'address1' => $address1,
            'address2' => '',
            'city'     => $city,
            'state'    => '',
            'postal'   => $postal,
            'country'  => $country,
        ],
        49 => [
            'name'      => 'Option: International anerkannte Zertifizierung (z.B. IPMA® / pma, ISO 17024, etc.)',
            'value'     => 'Falls verfügbar, auch intern. anerkannte Zertifizierung anbieten (IPMA®/pma, ISO 17024 TÜV, ©LOG+L Zertifizierung ...)',
            'value_raw' => 'Falls verfügbar, auch intern. anerkannte Zertifizierung anbieten (IPMA®/pma, ISO 17024 TÜV, ©LOG+L Zertifizierung ...)',
            'id'        => 49,
            'type'      => 'checkbox',
            'visible'   => true,
        ],
        75 => [
            'name'      => 'Bitte Zahlungsoptionen wählen (Rechnung / Kreditkarte)',
            'value'     => '',
            'value_raw' => '',
            'id'        => 75,
            'type'      => 'select',
            'visible'   => false,
        ],
        76 => [
            'name'     => 'Adresse (Zahlung auf Rechnung)',
            'value'    => '',
            'id'       => 76,
            'type'     => 'address',
            'address1' => '',
            'address2' => '',
            'city'     => '',
            'state'    => '',
            'postal'   => '',
            'country'  => '',
            'visible'  => false,
        ],
        2 => [
            'name'  => 'Nachricht',
            'value' => (!empty($lead_data['telefon']) ? 'Telefon: ' . $lead_data['telefon'] . "\n\n" : '') . ($lead_data['nachricht'] ?? ''),
            'id'    => 2,
            'type'  => 'textarea',
        ],
        47 => [
            'name'  => 'DSGVO-Einverständnis',
            'value' => '*',
            'id'    => 47,
            'type'  => 'gdpr-checkbox',
        ],
        73 => [
            'name'  => 'Verborgenes Feld',
            'value' => $lead_data['kurs_titel'] ?? '',
            'id'    => 73,
            'type'  => 'hidden',
        ],
        81 => [
            'name'  => 'Kurs ID',
            'value' => !empty($lead_data['kurs_id']) ? 'id ' . $lead_data['kurs_id'] : '',
            'id'    => 81,
            'type'  => 'hidden',
        ],
        99 => [
            'name'      => 'Zertifizierungen Auswahl',
            'value'     => '',
            'value_raw' => '',
            'id'        => 99,
            'type'      => 'checkbox',
        ],
        100 => [
            'name'      => 'Abschluss Erfolg',
            'value'     => '',
            'value_raw' => '',
            'id'        => 100,
            'type'      => 'checkbox',
        ],
    ];

    $fields_json = wp_json_encode($fields, JSON_UNESCAPED_UNICODE);

    // 2. In wp_wpforms_entries einfügen
    $table_entries = $wpdb->prefix . 'wpforms_entries';
    $entry_record = [
        'form_id'       => $form_id,
        'post_id'       => (int)($lead_data['kurs_id'] ?? 0),
        'user_id'       => 0,
        'status'        => '',
        'type'          => '',
        'viewed'        => 0,
        'starred'       => 0,
        'fields'        => $fields_json,
        'meta'          => '',
        'date'          => $now,
        'date_modified' => $now,
        'ip_address'    => '127.0.0.1',
        'user_agent'    => 'X7 E-Mail-to-CRM Lead Ingestion',
        'user_uuid'     => wp_generate_uuid4(),
    ];

    if ($force_entry_id !== null && $force_entry_id > 0) {
        $entry_record['entry_id'] = $force_entry_id;
        $inserted = $wpdb->replace($table_entries, $entry_record);
        $entry_id = $force_entry_id;
    } else {
        $inserted = $wpdb->insert($table_entries, $entry_record);
        $entry_id = $wpdb->insert_id;
    }

    if (!$entry_id) {
        return ['success' => false, 'message' => __('Fehler beim Anlegen des Eintrags in wp_wpforms_entries.', 'custom-crm')];
    }

    // 3. Status in wp_crm_entry_status initialisieren
    $table_status = $wpdb->prefix . 'crm_entry_status';
    $table_history = $wpdb->prefix . 'crm_entry_status_history';

    $course_id = !empty($lead_data['kurs_id']) ? (int)$lead_data['kurs_id'] : null;
    $start_date = !empty($lead_data['start_datum']) ? $lead_data['start_datum'] : ($course_id ? get_post_meta($course_id, 'start_datum', true) : null);
    $end_date   = !empty($lead_data['end_datum']) ? $lead_data['end_datum'] : ($course_id ? get_post_meta($course_id, 'end_datum', true) : null);

    $note_text = sprintf(
        __('Importiert aus E-Mail-Anfrage (%s %s). Kurs: %s.', 'custom-crm'),
        $lead_data['vorname'] ?? '',
        $lead_data['nachname'] ?? '',
        $lead_data['kurs_titel'] ?? 'N/A'
    );

    $wpdb->replace($table_status, [
        'entry_id'          => $entry_id,
        'form_id'           => $form_id,
        'status_key'        => 'neu',
        'status_label'      => 'Neu / Anfrage',
        'status_date'       => $now,
        'course_id'         => $course_id,
        'course_start_date' => $start_date,
        'course_end_date'   => $end_date,
        'inquiry_type'      => 'course',
        'custom_title'      => null,
        'note'              => $note_text,
        'updated_by'        => get_current_user_id() ?: 0,
    ]);

    $wpdb->insert($table_history, [
        'entry_id'     => $entry_id,
        'form_id'      => $form_id,
        'status_key'   => 'neu',
        'status_label' => 'Neu / Anfrage',
        'status_date'  => $now,
        'note'         => $note_text,
        'created_by'   => get_current_user_id() ?: 0,
    ]);

    // 4. Förderstatus speichern
    if (function_exists('crm_set_entry_foerderung')) {
        crm_set_entry_foerderung($entry_id, 'ams', $foerder_active);
    }

    // 5. Optionale Vorbereitung via Friedelin (Angebots-PDFs generieren)
    if ($run_preparation && function_exists('crm_friedelin_process_entry')) {
        crm_friedelin_process_entry($entry_id, true);
    }

    return [
        'success'   => true,
        'entry_id'  => $entry_id,
        'course_id' => $course_id,
        'message'   => sprintf(
            __('Lead #%d (%s %s) für Kurs "%s" erfolgreich ins CRM importiert!', 'custom-crm'),
            $entry_id,
            $lead_data['vorname'] ?? '',
            $lead_data['nachname'] ?? '',
            $lead_data['kurs_titel'] ?? ''
        ),
    ];
}

/**
 * AJAX Handler: E-Mail-Text aus CRM Admin Modal importieren
 */
add_action('wp_ajax_crm_import_email_lead', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Keine Berechtigung.', 'custom-crm')]);
    }
    if (!check_ajax_referer('crm_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen.', 'custom-crm')]);
    }

    $raw_text = isset($_POST['email_text']) ? wp_unslash($_POST['email_text']) : '';
    if (empty(trim($raw_text))) {
        wp_send_json_error(['message' => __('Bitte fügen Sie einen E-Mail-Text ein.', 'custom-crm')]);
    }

    $parsed = crm_parse_email_inquiry_text($raw_text);

    // Optionale Overrides aus Formular
    if (!empty($_POST['override_course_id'])) {
        $cid = absint($_POST['override_course_id']);
        if ($cid > 0) {
            $parsed['kurs_id'] = $cid;
            $parsed['kurs_titel'] = get_the_title($cid);
        }
    }

    $run_prep = !empty($_POST['run_preparation']);
    $result = crm_create_lead_from_parsed_data($parsed, $run_prep);

    if (!empty($result['success'])) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
});
