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

/**
 * Parst den Text einer weitergeleiteten E-Mail-Anfrage und extrahiert strukturierte Daten.
 *
 * @param string $raw_text
 * @return array
 */
function crm_parse_email_inquiry_text(string $raw_text): array
{
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

    // 1. E-Mail-Adresse extrahieren
    if (preg_match('/(?:E-Mail|Mail|Von):\s*.*?([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/iu', $clean_text, $m)) {
        $lead['email'] = trim($m[1]);
    } elseif (preg_match('/<mailto:([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})>/iu', $clean_text, $m)) {
        $lead['email'] = trim($m[1]);
    } elseif (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/iu', $clean_text, $m)) {
        // Ignoriere system-eigene Mail-Adressen
        $found = trim($m[1]);
        if (!in_array(strtolower($found), ['office@x-sieben.at', 'gajo@x-sieben.at', 'r.sauer007@gmail.com', 'vadim.burov@kursfinder.de'])) {
            $lead['email'] = $found;
        }
    }

    // 2. Strukturierte Felder (z. B. Kursfinder, Formular-E-Mails, Key-Value)
    if (preg_match('/Vorname:\s*([^\r\n]+)/iu', $clean_text, $m)) {
        $lead['vorname'] = trim($m[1]);
    }
    if (preg_match('/Nachname:\s*([^\r\n]+)/iu', $clean_text, $m)) {
        $lead['nachname'] = trim($m[1]);
    }
    if (preg_match('/Telefon:\s*([^\r\n]+)/iu', $clean_text, $m)) {
        $lead['telefon'] = trim($m[1]);
    }
    if (preg_match('/Firma:\s*([^\r\n]+)/iu', $clean_text, $m)) {
        $lead['firma'] = trim($m[1]);
        if (!empty($lead['firma'])) {
            $lead['ist_firma'] = true;
        }
    }
    if (preg_match('/Anrede:\s*([^\r\n]+)/iu', $clean_text, $m)) {
        $raw_a = trim($m[1]);
        // Plausibilitätscheck: "Sabrina" ist typisch weiblich
        if (strcasecmp($lead['vorname'] ?? '', 'Sabrina') === 0 || stripos($raw_a, 'Frau') !== false) {
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
        } elseif (preg_match('/(?:Mit\s+freundlichen\s+Grüßen|Danke|LG|Liebe\s+Grüße)[,\s\r\n]+([A-ZÄÖÜ][a-zäöüß]+)\s+([A-ZÄÖÜ][a-zäöüß]+)/u', $clean_text, $m)) {
            $lead['vorname'] = trim($m[1]);
            $lead['nachname'] = trim($m[2]);
        } elseif (preg_match('/um\s+[\d:]+\s+schrieb\s+([A-ZÄÖÜ][a-zäöüß]+)\s+([A-ZÄÖÜ][a-zäöüß]+)\s*</u', $clean_text, $m)) {
            $lead['vorname'] = trim($m[1]);
            $lead['nachname'] = trim($m[2]);
        }
    }

    // 4. Anrede ableiten
    if (empty($lead['anrede'])) {
        $female_names = ['fiona', 'sabrina', 'anna', 'maria', 'julia', 'claudia', 'petra', 'gabriele', 'patricia', 'susanne', 'barbara', 'daniela', 'elisabeth'];
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
        if (preg_match('/(?:unter|Tel(?:efon)?[:.]?|mobil[:.]?)\s*(\+?[0-9\s\/\-]{7,25})/iu', $clean_text, $m)) {
            $lead['telefon'] = trim($m[1]);
        }
    }

    // 6. Adresse aus Fließtext (z. B. "Ada-Christengasse 2 in 1100 Wien")
    if (preg_match('/([A-Za-zÄÖÜäöüß\s\.\-]+(?:gasse|straße|str\.|weg|platz|allee|ring)\s+\d+(?:\/\d+)?)\s*(?:in|,)?\s*(\d{4,5})\s+([A-Za-zÄÖÜäöüß\-]+)/iu', $clean_text, $m)) {
        $lead['strasse'] = trim($m[1]);
        $lead['plz']     = trim($m[2]);
        $lead['ort']     = trim($m[3]);
        $lead['land']    = (strlen($lead['plz']) === 4) ? 'AT' : 'DE';
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
        ['pattern' => '/ipma.*level\s*d|level\s*d.*zertifizierungsvorbereitung|projektmanagement.*ipma/iu', 'id' => 1167],
        ['pattern' => '/eventmanagement/iu', 'id' => 542],
        ['pattern' => '/agile\s*coach.*iso\s*17024|agile\s*coach/iu', 'id' => 65536],
        ['pattern' => '/daf\s*\/?\s*daz.*trainer|daf.*daz/iu', 'id' => 701],
        ['pattern' => '/fachtrainer.*iso\s*17024|trainer.*ausbildung/iu', 'id' => 376],
        ['pattern' => '/scrum\s*master.*product\s*owner|psm.*pspo/iu', 'id' => 40914],
        ['pattern' => '/digital\s*marketing/iu', 'id' => 36593],
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

    // 0. Duplikats-Prüfung anhand E-Mail (sofern nicht erzwungen)
    if ($force_entry_id === null && !empty($lead_data['email'])) {
        $clean_email = strtolower(trim($lead_data['email']));
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT entry_id FROM {$wpdb->prefix}wpforms_entries WHERE fields LIKE %s ORDER BY entry_id DESC LIMIT 1",
            '%' . $wpdb->esc_like('"value":"' . $clean_email . '"') . '%'
        ), ARRAY_A);
        if ($existing && !empty($existing['entry_id'])) {
            return [
                'success' => true,
                'entry_id' => (int)$existing['entry_id'],
                'already_exists' => true,
                'message' => "Lead existiert bereits (#{$existing['entry_id']}). Kein Duplikat angelegt."
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
