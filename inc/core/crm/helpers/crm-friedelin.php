<?php

/**
 * Friedelin AI Lead Preparation Engine (Version 1)
 * 
 * Automates WPForms lead analysis, document generation (Offer + Kurszeitenbestätigung),
 * companion email drafting, and CRM status management ('ki_vorbereitet').
 * 
 * Strict Human-in-the-Loop: Prepares all materials for manual approval by Hannes.
 * No autonomous email dispatch to customers.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Analyzes a WPForms entry and extracts structured customer, course, and funding data.
 *
 * @param int $entry_id
 * @return array
 */
function crm_friedelin_analyze_entry(int $entry_id): array
{
    if (!function_exists('wpforms') || !$entry_id) {
        return ['success' => false, 'error' => 'WPForms or Entry ID missing'];
    }

    $entry = wpforms()->entry->get($entry_id);
    if (!$entry) {
        return ['success' => false, 'error' => 'Entry not found'];
    }

    $fields = is_string($entry->fields) ? json_decode($entry->fields, true) : $entry->fields;
    if (!is_array($fields)) {
        return ['success' => false, 'error' => 'Could not decode entry fields'];
    }

    // Helper to extract field value by name or ID
    $get_val_by_id = function ($id) use ($fields) {
        return isset($fields[$id]['value']) ? (is_array($fields[$id]['value']) ? implode(', ', $fields[$id]['value']) : trim((string)$fields[$id]['value'])) : '';
    };

    $get_val_by_name_search = function ($keyword) use ($fields) {
        foreach ($fields as $f) {
            if (isset($f['name']) && stripos($f['name'], $keyword) !== false) {
                return is_array($f['value']) ? implode(', ', $f['value']) : trim((string)$f['value']);
            }
        }
        return '';
    };

    // Extract core fields
    $anrede_raw    = $get_val_by_id(88) ?: $get_val_by_name_search('anrede');
    $titel         = $get_val_by_id(90) ?: $get_val_by_name_search('titel');
    $vorname       = $get_val_by_id(86) ?: $get_val_by_name_search('vorname');
    $nachname      = $get_val_by_id(89) ?: $get_val_by_name_search('nachname');
    $email         = $get_val_by_id(93) ?: $get_val_by_name_search('e-mail');
    $svr           = $get_val_by_id(29) ?: $get_val_by_name_search('sv-nummer');
    $company_raw   = $get_val_by_id(25) ?: $get_val_by_name_search('firma');
    $customer_type = $get_val_by_id(3)  ?: $get_val_by_name_search('kundenart');
    $certs_raw     = $get_val_by_id(99) ?: $get_val_by_name_search('zertifizier');
    $payment_opt   = $get_val_by_id(100) ?: $get_val_by_name_search('zahlung');
    $funding_raw   = $get_val_by_id(102) ?: $get_val_by_name_search('förder');
    $message_raw   = $get_val_by_id(2) ?: ($get_val_by_id(103) ?: ($get_val_by_name_search('nachricht') ?: $get_val_by_name_search('anmerkung')));
    $course_title  = $get_val_by_name_search('Verborgenes Feld') ?: $get_val_by_name_search('kurs');

    // Clean salutation
    $salutation = 'Sehr geehrte Damen und Herren';
    $anrede = '';
    if (stripos($anrede_raw, 'Herr') !== false) {
        $anrede = 'Herr';
        $salutation = 'Sehr geehrter Herr' . (!empty($titel) ? ' ' . $titel : '') . ' ' . $nachname;
    } elseif (stripos($anrede_raw, 'Frau') !== false) {
        $anrede = 'Frau';
        $salutation = 'Sehr geehrte Frau' . (!empty($titel) ? ' ' . $titel : '') . ' ' . $nachname;
    } elseif (!empty($nachname)) {
        $salutation = 'Guten Tag ' . (!empty($titel) ? $titel . ' ' : '') . $vorname . ' ' . $nachname;
    }

    // Clean company
    $company = '';
    if (!empty($company_raw) && stripos($company_raw, 'Privatperson') === false && stripos($company_raw, 'Angebot für') === false && strcasecmp($company_raw, 'Unternehmen') !== 0) {
        $company = $company_raw;
    }

    // Resolve course ID
    $course_id = 0;
    if (!empty($course_title) && strcasecmp(trim($course_title), 'KONTAKT') !== 0 && function_exists('find_course_id_by_title_exact')) {
        $course_id = find_course_id_by_title_exact($course_title);
    }
    if (!$course_id) {
        $kurs_id_raw = $get_val_by_name_search('kurs id') ?: ($fields[81]['value'] ?? '');
        if ($kurs_id_raw && preg_match('/(\d+)/', $kurs_id_raw, $m)) {
            $candidate_id = intval($m[1]);
            if ($candidate_id != 47 && get_post_type($candidate_id) === 'courses') {
                $course_id = $candidate_id;
            }
        }
    }
    // Fallback: Intelligente Kurs-Erkennung aus Kundennachricht / Anfrage-Freitext
    if (!$course_id && function_exists('crm_detect_course_from_message')) {
        $detected = crm_detect_course_from_message($message_raw, $fields, $entry_id);
        if ($detected && !empty($detected['course_id'])) {
            $course_id = (int)$detected['course_id'];
            $course_title = $detected['course_title'];
        }
    }

    // Detect process type (AMS / Förderung vs. Standard Angebot)
    $all_text_combined = mb_strtolower($funding_raw . ' ' . $message_raw . ' ' . $svr . ' ' . $customer_type, 'UTF-8');
    $is_ams_funding = false;
    $funding_keywords = ['ams', 'waff', 'kurszeiten', 'kurszeitenbestätigung', 'förderung', 'förderstelle', 'arbeitsmarktservice', 'bildungskarenz'];
    
    foreach ($funding_keywords as $kw) {
        if (strpos($all_text_combined, $kw) !== false) {
            $is_ams_funding = true;
            break;
        }
    }
    if (!empty($svr) && strlen(trim($svr)) >= 4) {
        $is_ams_funding = true;
    }

    $process_type = $is_ams_funding ? 'ams_foerderung' : 'standard_angebot';

    return [
        'success'        => true,
        'entry_id'       => $entry_id,
        'course_id'      => $course_id,
        'course_title'   => $course_title,
        'vorname'        => $vorname,
        'nachname'       => $nachname,
        'titel'          => $titel,
        'anrede'         => $anrede,
        'salutation'     => $salutation,
        'email'          => $email,
        'company'        => $company,
        'customer_type'  => $customer_type,
        'svr'            => $svr,
        'certs_raw'      => $certs_raw,
        'payment_option' => $payment_opt,
        'funding_raw'    => $funding_raw,
        'message_raw'    => $message_raw,
        'process_type'   => $process_type,
        'is_ams_funding' => $is_ams_funding,
        'date'           => $entry->date,
    ];
}

/**
 * Intelligently detects and resolves the targeted course from an inquiry message, free-text or form field.
 *
 * Implements a 3-tier recognition engine:
 * 1. Semantic Domain Patterns (DaF/DaZ, Trainer, IPMA/PM, Scrum, Marketing, Transformation, Logistics, Resilience, etc.)
 * 2. Course Catalog N-Gram / Token Matcher across all published courses
 * 3. Auto-Persist into wp_crm_entry_status_history so the course is permanently linked.
 *
 * @param string $message_text The message text, inquiry, or email body.
 * @param array $entry_fields Optional WPForms fields array for additional context.
 * @param int $entry_id Optional entry ID to auto-persist the resolved course.
 * @return array|null Returns ['course_id' => int, 'course_title' => string, 'confidence' => float, 'matched_by' => string] or null.
 */
function crm_detect_course_from_message(string $message_text = '', array $entry_fields = [], int $entry_id = 0): ?array
{
    // Auto-load entry fields if entry_id is given but no text provided
    if (empty($message_text) && empty($entry_fields) && $entry_id > 0 && function_exists('wpforms')) {
        $raw_entry = wpforms()->entry->get($entry_id);
        if ($raw_entry && !empty($raw_entry->fields)) {
            $entry_fields = is_string($raw_entry->fields) ? json_decode($raw_entry->fields, true) : $raw_entry->fields;
            if (is_array($entry_fields)) {
                $message_text = $entry_fields[2]['value'] ?? ($entry_fields[103]['value'] ?? '');
            }
        }
    }

    // Prioritize actual customer message text & course fields (filter out boilerplate labels like Option 49)
    $clean_msg = trim($message_text);
    $text_candidates = [];
    if (!empty($clean_msg)) {
        $text_candidates[] = $clean_msg;
    }
    if (!empty($entry_fields) && is_array($entry_fields)) {
        foreach ($entry_fields as $f) {
            $fname = isset($f['name']) ? mb_strtolower(trim((string)$f['name']), 'UTF-8') : '';
            if (strpos($fname, 'verborgen') !== false || strpos($fname, 'kurs') !== false || strpos($fname, 'nachricht') !== false || strpos($fname, 'freitext') !== false || strpos($fname, 'anmerkung') !== false) {
                $val = is_array($f['value'] ?? '') ? implode(' ', $f['value']) : (string)($f['value'] ?? '');
                if (strcasecmp(trim($val), 'KONTAKT') !== 0 && !empty(trim($val))) {
                    $text_candidates[] = $val;
                }
            }
        }
    }

    $text_combined = implode(' ', $text_candidates);

    $text_lower = mb_strtolower($text_combined, 'UTF-8');
    if (empty(trim($text_lower))) {
        return null;
    }

    $match = null;

    // 1. DaF / DaZ Cluster (Deutsch als Fremdsprache / Deutsch als Zweitsprache)
    if (
        strpos($text_lower, 'daf') !== false ||
        strpos($text_lower, 'daz') !== false ||
        strpos($text_lower, 'deutsch als fremdsprache') !== false ||
        strpos($text_lower, 'deutsch als zweitsprache') !== false ||
        strpos($text_lower, 'sprachlektor') !== false ||
        (strpos($text_lower, 'lektor') !== false && strpos($text_lower, 'trainer') !== false)
    ) {
        if (strpos($text_lower, 'fachtrainer') !== false && (strpos($text_lower, 'kombi') !== false || strpos($text_lower, '&') !== false || strpos($text_lower, 'und') !== false || strpos($text_lower, 'beides') !== false)) {
            $match = [
                'course_id'    => 703,
                'course_title' => 'FachtrainerInnen & DaF / DaZ TrainerInnen Ausbildung - ISO 17024',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_daf_daz_fachtrainer_kombi'
            ];
        } elseif (strpos($text_lower, 'ams') !== false || strpos($text_lower, 'aktion') !== false) {
            $match = [
                'course_id'    => 65629,
                'course_title' => 'DaF / DaZ Ausbildung für TrainerInnen - AMS Aktion',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_daf_daz_ams'
            ];
        } else {
            $match = [
                'course_id'    => 701,
                'course_title' => 'DaF / DaZ Ausbildung für TrainerInnen - Nur 12 Tage',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_daf_daz'
            ];
        }
    }

    // 2. FachtrainerInnen / TrainerInnen Ausbildung Cluster
    elseif (
        strpos($text_lower, 'fachtrainer') !== false ||
        strpos($text_lower, 'fachtrainerin') !== false ||
        strpos($text_lower, 'trainerausbildung') !== false ||
        strpos($text_lower, 'trainerinnen ausbildung') !== false ||
        strpos($text_lower, 'wirtschaftstrainer') !== false
    ) {
        if (strpos($text_lower, 'interkulturell') !== false) {
            $match = [
                'course_id'    => 733,
                'course_title' => 'Interkulturelle FachtrainerIn & KommunikationstrainerIn - ISO 17024',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_fachtrainer_interkulturell'
            ];
        } elseif (strpos($text_lower, 'ams') !== false) {
            $match = [
                'course_id'    => 65648,
                'course_title' => 'TrainerInnen Ausbildung - ISO 17024: AMS-Aktion',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_fachtrainer_ams'
            ];
        } elseif (strpos($text_lower, 'vertiefung') !== false || strpos($text_lower, 'praxis') !== false) {
            $match = [
                'course_id'    => 9217,
                'course_title' => 'TrainerInnen Praxis- & Vertiefungslehrgang - ISO 17024',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_fachtrainer_praxis'
            ];
        } else {
            $match = [
                'course_id'    => 376,
                'course_title' => 'TrainerInnen Ausbildung - ISO 17024',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_fachtrainer'
            ];
        }
    }

    // 3. Projektmanagement / IPMA / GPM / SPM Cluster
    elseif (
        strpos($text_lower, 'projektmanagement') !== false ||
        strpos($text_lower, 'project management') !== false ||
        strpos($text_lower, 'ipma') !== false ||
        strpos($text_lower, 'pma') !== false ||
        strpos($text_lower, 'prince2') !== false
    ) {
        if (strpos($text_lower, 'prince2') !== false) {
            $match = [
                'course_id'    => 1164,
                'course_title' => 'PRINCE2® Foundation - IT Projektmanagement in 3 Tagen',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_pm_prince2'
            ];
        } elseif (strpos($text_lower, 'agile leadership') !== false) {
            $match = [
                'course_id'    => 39578,
                'course_title' => 'Projektmanagement. IPMA® / pma - Agile Leadership Level C und D Zertifizierung in 2 Tagen',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_pm_agile_leadership'
            ];
        } elseif (strpos($text_lower, 'prüfungsvorbereitung') !== false || strpos($text_lower, 'zertifizierungsvorbereitung') !== false || strpos($text_lower, 'level d') !== false || strpos($text_lower, 'level c') !== false || strpos($text_lower, 'level b') !== false) {
            if (strpos($text_lower, 'gpm') !== false) {
                $match = [
                    'course_id'    => 67335,
                    'course_title' => 'Projektmanagement. IPMA® / GPM – Level D / C / B Zertifizierungsvorbereitung in 3 Tagen',
                    'confidence'   => 0.95,
                    'matched_by'   => 'domain_pm_gpm'
                ];
            } elseif (strpos($text_lower, 'spm') !== false || strpos($text_lower, 'vzpm') !== false) {
                $match = [
                    'course_id'    => 68140,
                    'course_title' => 'Projektmanagement. IPMA® spm–VZPM – Level D / C / B Zertifizierungsvorbereitung in 3 Tagen',
                    'confidence'   => 0.95,
                    'matched_by'   => 'domain_pm_spm'
                ];
            } else {
                $match = [
                    'course_id'    => 1167,
                    'course_title' => 'Projektmanagement. IPMA® / pma – Level D / C / B Zertifizierungsvorbereitung in 3 Tagen',
                    'confidence'   => 0.95,
                    'matched_by'   => 'domain_pm_level_dcb'
                ];
            }
        } elseif (strpos($text_lower, 'grundlagen') !== false) {
            $match = [
                'course_id'    => 15918,
                'course_title' => 'Projektmanagement Grundlagen - vom anerkannten IPMA® / pma Ausbildungspartner - 2 Tage',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_pm_grundlagen'
            ];
        } elseif (strpos($text_lower, 'vertiefung') !== false) {
            $match = [
                'course_id'    => 15929,
                'course_title' => 'Projektmanagement Vertiefung - vom anerkannten IPMA® / pma Ausbildungspartner - 2 Tage',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_pm_vertiefung'
            ];
        } elseif (strpos($text_lower, 'r&d') !== false || strpos($text_lower, 'forschung') !== false) {
            $match = [
                'course_id'    => 1175,
                'course_title' => 'R&D Projektmanagement - IPMA® / pma in nur 4 Tagen',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_pm_rnd'
            ];
        } else {
            $match = [
                'course_id'    => 318,
                'course_title' => 'Projektmanagement - Best of - IPMA® / pma - Lehrgang',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_pm_lehrgang'
            ];
        }
    }

    // 4. Agile Coach / Agile Führung Cluster
    elseif (
        strpos($text_lower, 'agile coach') !== false ||
        strpos($text_lower, 'agiler coach') !== false ||
        strpos($text_lower, 'agile leadership') !== false ||
        strpos($text_lower, 'agile führungskraft') !== false
    ) {
        if (strpos($text_lower, 'leadership') !== false || strpos($text_lower, 'führung') !== false) {
            $match = [
                'course_id'    => 65566,
                'course_title' => 'Agile Leadership Coach - 5 Module - Lehrgang',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_agile_leadership_coach'
            ];
        } else {
            $match = [
                'course_id'    => 74011,
                'course_title' => 'Agile Coach – ISO 17024 – TÜV – mit Scrum Master & Product Owner Kompetenz',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_agile_coach'
            ];
        }
    }

    // 5. Scrum / Agile Methoden Cluster
    elseif (
        strpos($text_lower, 'scrum') !== false ||
        strpos($text_lower, 'product owner') !== false ||
        strpos($text_lower, 'scrum master') !== false ||
        strpos($text_lower, 'psm') !== false ||
        strpos($text_lower, 'pspo') !== false ||
        strpos($text_lower, 'kanban') !== false
    ) {
        if (strpos($text_lower, 'kanban') !== false) {
            $match = [
                'course_id'    => 67791,
                'course_title' => 'KANBAN - Maximale Effizienz und Produktivität - 2 Tage',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_kanban'
            ];
        } elseif (strpos($text_lower, 'tüv') !== false || strpos($text_lower, 'iso 17024') !== false) {
            $match = [
                'course_id'    => 32495,
                'course_title' => 'Professional Scrum Master & Product Owner - ISO 17024 TÜV AUSTRIA - Lehrgang',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_scrum_tuev'
            ];
        } elseif (strpos($text_lower, 'prüfung') !== false || strpos($text_lower, 'vorbereitung') !== false) {
            $match = [
                'course_id'    => 22184,
                'course_title' => 'PSM I & PSPO I – Prüfungsvorbereitung',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_scrum_pruefung'
            ];
        } else {
            $match = [
                'course_id'    => 40914,
                'course_title' => 'Agiles & Digitales Projektmanagement - Scrum PSM I - PSPO I - Lehrgang',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_scrum_lehrgang'
            ];
        }
    }

    // 6. KI / AI / Bootcamp Cluster
    elseif (
        strpos($text_lower, 'bootcamp') !== false ||
        strpos($text_lower, 'ki trainer') !== false ||
        strpos($text_lower, 'künstliche intelligenz') !== false ||
        strpos($text_lower, 'chatgpt') !== false ||
        strpos($text_lower, 'ki-tools') !== false
    ) {
        if (strpos($text_lower, 'ki trainer') !== false) {
            $match = [
                'course_id'    => 55667,
                'course_title' => 'KI TrainerInnen Ausbildung - Nutzen Sie Künstliche Intelligenz für beeindruckende Ergebnisse',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_ki_trainer'
            ];
        } elseif (strpos($text_lower, 'bootcamp') !== false) {
            $match = [
                'course_id'    => 61598,
                'course_title' => 'KI-Bootcamp: Verpassen Sie nicht den Anschluss. Werden Sie zum Innovationstreiber.',
                'confidence'   => 0.90,
                'matched_by'   => 'domain_ki_bootcamp'
            ];
        } elseif (strpos($text_lower, 'tools') !== false || strpos($text_lower, 'vertrieb') !== false || strpos($text_lower, 'chatgpt') !== false) {
            $match = [
                'course_id'    => 55735,
                'course_title' => 'Digital Marketing und bahnbrechende KI-Tools zum sofortigen Praxiseinsatz',
                'confidence'   => 0.90,
                'matched_by'   => 'domain_ki_tools'
            ];
        } else {
            $match = [
                'course_id'    => 72498,
                'course_title' => 'Digital & KI Transformation Manager',
                'confidence'   => 0.90,
                'matched_by'   => 'domain_ki_trans'
            ];
        }
    }

    // 7. Digital Marketing / Online Marketing / SEO Cluster
    elseif (
        strpos($text_lower, 'digital marketing') !== false ||
        strpos($text_lower, 'marketing manager') !== false ||
        strpos($text_lower, 'online-marketing') !== false ||
        strpos($text_lower, 'online marketing') !== false ||
        strpos($text_lower, 'content marketing') !== false ||
        strpos($text_lower, 'seo') !== false ||
        strpos($text_lower, 'social media') !== false
    ) {
        if (strpos($text_lower, 'content') !== false || strpos($text_lower, 'seo neu') !== false) {
            $match = [
                'course_id'    => 646,
                'course_title' => 'Content Marketing & SEO Neu - Kundengewinnung & Kundenbindung',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_marketing_content'
            ];
        } elseif (strpos($text_lower, 'kompakt') !== false || strpos($text_lower, '2 tage') !== false) {
            $match = [
                'course_id'    => 554,
                'course_title' => 'Online-Marketing – Kompakt in 2 Tagen',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_marketing_kompakt'
            ];
        } else {
            $match = [
                'course_id'    => 36593,
                'course_title' => 'Digitalisierung - Diplomierter Digital Marketing Manager - ISO 17024 - Trend',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_marketing_diplom'
            ];
        }
    }

    // 8. Transformation Manager / Cultural Transformation Cluster
    elseif (
        strpos($text_lower, 'transformation manager') !== false ||
        strpos($text_lower, 'transformation') !== false ||
        strpos($text_lower, 'kulturwandel') !== false ||
        strpos($text_lower, 'change manager') !== false
    ) {
        if (strpos($text_lower, 'kultur') !== false || strpos($text_lower, 'cultural') !== false) {
            $match = [
                'course_id'    => 79371,
                'course_title' => 'Cultural Transformation Management – Kulturwandel gestalten',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_trans_culture'
            ];
        } elseif (strpos($text_lower, 'medical') !== false || strpos($text_lower, 'krankenhaus') !== false) {
            $match = [
                'course_id'    => 77150,
                'course_title' => 'Medical AI Transformation Manager:in – KI-gestützte Transformation im Krankenhauswesen',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_trans_medical'
            ];
        } else {
            $match = [
                'course_id'    => 65521,
                'course_title' => 'Business Transformation Manager - ISO 17024',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_trans_business'
            ];
        }
    }

    // 9. Logistik / Supply Chain Cluster
    elseif (
        strpos($text_lower, 'logistik') !== false ||
        strpos($text_lower, 'lagerleiter') !== false ||
        strpos($text_lower, 'supply chain') !== false ||
        strpos($text_lower, 'lagermanagement') !== false ||
        strpos($text_lower, 'betriebslogistik') !== false ||
        strpos($text_lower, 'log+l') !== false
    ) {
        if (strpos($text_lower, 'lagerleiter') !== false) {
            $match = [
                'course_id'    => 14761,
                'course_title' => 'Diplomierter Lagerleiter - Lagermanagement - Best of - Lehrgang',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_logistik_lagerleiter'
            ];
        } elseif (strpos($text_lower, 'betriebslogistik') !== false) {
            $match = [
                'course_id'    => 38736,
                'course_title' => 'Betriebslogistik für Einsteiger - Lehrgang',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_logistik_betrieb'
            ];
        } elseif (strpos($text_lower, 'führerschein') !== false || strpos($text_lower, 'log+l') !== false) {
            $match = [
                'course_id'    => 555,
                'course_title' => '©LOG+L - Europäischer Logistikmanagement Führerschein - DIN EN ISO 17024',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_logistik_fuehrerschein'
            ];
        } else {
            $match = [
                'course_id'    => 316,
                'course_title' => 'Logistik und Supply Chain Management - Lehrgang',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_logistik_lehrgang'
            ];
        }
    }

    // 10. Resilienz Coach
    elseif (strpos($text_lower, 'resilienz') !== false) {
        $match = [
            'course_id'    => 700,
            'course_title' => 'Resilienz Coach in nur 9 Tagen - für HR MitarbeiterInnen',
            'confidence'   => 0.95,
            'matched_by'   => 'domain_resilienz'
        ];
    }

    // 11. WordPress / WooCommerce
    elseif (strpos($text_lower, 'wordpress') !== false || strpos($text_lower, 'woocommerce') !== false) {
        if (strpos($text_lower, 'shop') !== false || strpos($text_lower, 'woocommerce') !== false) {
            $match = [
                'course_id'    => 41179,
                'course_title' => 'Wordpress Online Shop & WooCommerce - Webshop Gestaltung leicht gemacht',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_wordpress_shop'
            ];
        } else {
            $match = [
                'course_id'    => 31382,
                'course_title' => 'WordPress für Einsteiger - Basics in nur  2 Tagen',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_wordpress_basics'
            ];
        }
    }

    // 12. Verkauf & Vertrieb
    elseif (strpos($text_lower, 'verkauf') !== false || strpos($text_lower, 'verhandlungs-profi') !== false || strpos($text_lower, 'verhandlung') !== false) {
        if (strpos($text_lower, 'verhandlung') !== false) {
            $match = [
                'course_id'    => 34094,
                'course_title' => 'Verhandlungs-Profi in nur acht Modulen - Diplomlehrgang',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_verhandlung'
            ];
        } else {
            $match = [
                'course_id'    => 34272,
                'course_title' => 'Diplomlehrgang Verkauf in der Praxis – ISO 17024',
                'confidence'   => 0.95,
                'matched_by'   => 'domain_verkauf'
            ];
        }
    }

    // 13. Design Thinking
    elseif (strpos($text_lower, 'design thinking') !== false) {
        $match = [
            'course_id'    => 9263,
            'course_title' => 'Design Thinking & Innovation - Tools - Grundlagen',
            'confidence'   => 0.95,
            'matched_by'   => 'domain_design_thinking'
        ];
    }

    // 14. Personalmanagement / HR
    elseif (strpos($text_lower, 'personalmanagement') !== false || strpos($text_lower, 'personalmanager') !== false) {
        $match = [
            'course_id'    => 560,
            'course_title' => 'Personalmanagement - Grundlagen - Best of',
            'confidence'   => 0.95,
            'matched_by'   => 'domain_personalmanagement'
        ];
    }

    // Fallback: Catalog Token-Overlap Matching across all published courses
    if (!$match) {
        global $wpdb;
        $published_courses = $wpdb->get_results(
            "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'courses' AND post_status = 'publish'",
            ARRAY_A
        );

        if (!empty($published_courses)) {
            $best_score = 0;
            $best_course = null;
            $stopwords = [
                'ausbildung', 'lehrgang', 'seminar', 'kurs', 'modul', 'tage', 'online', 'praxis',
                'best', 'tools', 'nach', 'fuer', 'für', 'einer', 'eine', 'einen', 'einem', 'interessiere',
                'mich', 'bitte', 'guten', 'hallo', 'danke', 'grüße', 'gruesse', 'angebot', 'kosten',
                'informationen', 'nähere', 'ersatz', 'sucht', 'suche', 'gesamte', 'jahr', 'jahre', 'jahrelang'
            ];

            // Extract candidate query words (min 4 chars, not stopword)
            preg_match_all('/[a-zäöüß]{4,}/u', $text_lower, $word_matches);
            $query_words = array_diff($word_matches[0] ?? [], $stopwords);
            $query_words = array_unique($query_words);

            foreach ($published_courses as $pc) {
                $c_title_lower = mb_strtolower($pc['post_title'], 'UTF-8');
                $score = 0;
                foreach ($query_words as $qw) {
                    if (strpos($c_title_lower, $qw) !== false) {
                        $score += mb_strlen($qw, 'UTF-8'); // longer word matches get higher weight
                    }
                }
                if ($score > $best_score && $score >= 8) {
                    $best_score = $score;
                    $best_course = $pc;
                }
            }

            if ($best_course) {
                $match = [
                    'course_id'    => (int)$best_course['ID'],
                    'course_title' => $best_course['post_title'],
                    'confidence'   => min(0.90, round(0.50 + ($best_score / 30), 2)),
                    'matched_by'   => 'catalog_token_score'
                ];
            }
        }
    }

    // Auto-Persist in wp_crm_entry_status if entry_id is provided
    if ($match && $entry_id > 0) {
        global $wpdb;
        $table_status = $wpdb->prefix . 'crm_entry_status';
        $current_db_cid = $wpdb->get_var($wpdb->prepare(
            "SELECT course_id FROM {$table_status} WHERE entry_id = %d LIMIT 1",
            $entry_id
        ));

        if (empty($current_db_cid) || intval($current_db_cid) === 0 || intval($current_db_cid) === 47) {
            $start_date = get_post_meta($match['course_id'], 'start_datum', true) ?: '';
            $end_date   = get_post_meta($match['course_id'], 'end_datum', true) ?: '';

            if ($current_db_cid !== null) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_status} SET course_id = %d, course_start_date = %s, course_end_date = %s WHERE entry_id = %d",
                    $match['course_id'],
                    $start_date,
                    $end_date,
                    $entry_id
                ));
            } else {
                $wpdb->insert($table_status, [
                    'entry_id'          => $entry_id,
                    'form_id'           => 60468,
                    'status_key'        => 'neu',
                    'status_label'      => 'Neu / Anfrage',
                    'status_date'       => current_time('mysql'),
                    'course_id'         => $match['course_id'],
                    'course_start_date' => $start_date,
                    'course_end_date'   => $end_date,
                ]);
            }

            if (function_exists('crm_add_entry_status_history')) {
                crm_add_entry_status_history(
                    $entry_id,
                    'course_ai_detected',
                    __('Kurs automatisch erkannt', 'custom-crm'),
                    sprintf(
                        __('Kurs "%s" (ID %d) wurde anhand der Kundenanfrage automatisch erkannt und verknüpft (Konfidenz: %d%%).', 'custom-crm'),
                        $match['course_title'],
                        $match['course_id'],
                        round($match['confidence'] * 100)
                    )
                );
            }
        }
    }

    return $match;
}

/**
 * Resolves the appropriate certification for a given entry and course based on lead wishes and course offerings.
 * Implements strict rules:
 * - Specific customer mention has priority (Level D, C, B, TÜV, SystemCERT, Scrum, etc.)
 * - For Project Management courses, defaults to Level D if no specific level is requested.
 *
/**
 * Parst eine einzelne Zertifizierungszeile (z.B. aus Feld 99 oder Formulareingabe)
 * in ein standardisiertes Array mit Name, Preis und USt-Prozentsatz.
 *
 * @param string $line
 * @param int $course_id
 * @return array|null
 */
function crm_parse_certification_line(string $line, int $course_id = 0): ?array
{
    $line = trim($line);
    if ($line === '') {
        return null;
    }

    $name = '';
    $price = '0,00';
    $percentage = '20%';

    // Format 1: Name - € 1.234,50 (10%) oder Name - € 1234,50 (20%)
    $pattern1 = '/^(.+?)\s*[-–]\s*€?\s*([\d\.,]+)(?:\s*€)?(?:\s*\((\d+)%\))?/iu';
    // Format 2: Name (+484,00 €) oder Name (+484,00)
    $pattern2 = '/^(.+?)\s*\(\+?\s*([\d\.,]+)\s*€?\s*\)/iu';

    if (preg_match($pattern1, $line, $matches)) {
        $name = trim($matches[1]);
        $price = trim($matches[2]);
        $percentage = !empty($matches[3]) ? $matches[3] . '%' : ((stripos($name, 'ipma') !== false || stripos($name, 'pma') !== false) ? '10%' : '20%');
    } elseif (preg_match($pattern2, $line, $matches2)) {
        $name = trim($matches2[1]);
        $price = trim($matches2[2]);
        $percentage = (stripos($name, 'ipma') !== false || stripos($name, 'pma') !== false) ? '10%' : '20%';
    } elseif (stripos($line, 'Fachtrainer') !== false) {
        // Sonderfall: FachtrainerIn
        $name = $line;
        $price = '528,00';
        $percentage = '20%';
    } elseif ($course_id && function_exists('crm_get_course_available_certifications')) {
        // Fallback: Name mit verfügbaren Kurszertifizierungen abgleichen
        $available = crm_get_course_available_certifications($course_id, 0);
        $line_l = mb_strtolower($line, 'UTF-8');
        foreach ($available as $ac) {
            $ac_name_l = mb_strtolower($ac['name'], 'UTF-8');
            $ac_short_l = mb_strtolower($ac['short_name'], 'UTF-8');
            if ($line_l === $ac_name_l || $line_l === $ac_short_l || stripos($ac_name_l, $line_l) !== false || stripos($line_l, $ac_short_l) !== false) {
                $name = $ac['short_name'] ?: $ac['name'];
                $price = !empty($ac['price_formatted']) ? preg_replace('/[^\d,\.]/', '', $ac['price_formatted']) : (string)($ac['price_raw'] ?? '0,00');
                $percentage = !empty($ac['ust']) ? $ac['ust'] : '20%';
                break;
            }
        }
        if (!$name) {
            $name = $line;
        }
    } else {
        $name = $line;
    }

    $price_clean = preg_replace('/[^\d,\.]/', '', $price);
    $clean_for_float = str_replace('.', '', $price_clean);
    $clean_for_float = str_replace(',', '.', $clean_for_float);
    $price_float = (float)$clean_for_float;
    $ust_int = (int)preg_replace('/[^\d]/', '', $percentage) ?: 20;

    return [
        'name'       => $name,
        'price'      => $price,
        'price_raw'  => $price_float,
        'percentage' => $percentage,
        'ust'        => $ust_int
    ];
}

/**
 * Autonome Zertifizierungsauflösung (Friedelin-Engine).
 * Priorisiert explizit in Feld 99 gespeicherte Zertifizierungsauswahlen.
 *
 * @param int $entry_id
 * @param int $course_id
 * @return array Array of certification records for CRM_Model::get_certifications_from_form_field()
 */
function crm_resolve_course_certification(int $entry_id, int $course_id): array
{
    if (!$course_id) {
        return [];
    }

    $course_title = get_the_title($course_id);
    $course_title_lower = mb_strtolower($course_title, 'UTF-8');

    // Retrieve entry text to find customer wishes & check Feld 99
    $wish_text = '';
    $has_field_99 = false;
    $field_99_raw = null;
    $entry_fields = [];

    if ($entry_id) {
        if (function_exists('wpforms')) {
            $entry = wpforms()->entry->get($entry_id);
            if ($entry && !empty($entry->fields)) {
                $entry_fields = is_string($entry->fields) ? json_decode($entry->fields, true) : $entry->fields;
            }
        }
        if (empty($entry_fields)) {
            global $wpdb;
            if ($wpdb) {
                $raw_fields = $wpdb->get_var($wpdb->prepare("SELECT fields FROM {$wpdb->prefix}wpforms_entries WHERE entry_id = %d", $entry_id));
                if ($raw_fields) {
                    $entry_fields = json_decode($raw_fields, true);
                }
            }
        }
    }

    if (is_array($entry_fields)) {
        foreach ($entry_fields as $f) {
            $fid = isset($f['id']) ? (int)$f['id'] : null;
            $fname = isset($f['name']) ? mb_strtolower(trim((string)$f['name']), 'UTF-8') : '';
            if ($fid === 99 || strpos($fname, 'zertifizier') !== false) {
                $has_field_99 = true;
                $field_99_raw = is_array($f['value'] ?? '') ? implode("\n", $f['value']) : (string)($f['value'] ?? '');
            }
            if (isset($f['value'])) {
                $val = is_array($f['value']) ? implode(' ', $f['value']) : (string)$f['value'];
                $wish_text .= ' ' . $val;
            }
        }
    }
    $wish_lower = mb_strtolower($wish_text, 'UTF-8');

    // 0. Ausschluss für reine DaF / DaZ Ausbildungen (weder 12 Tage noch AMS Aktion haben ISO 17024)
    $is_pure_daf_daz = (
        ($course_id == 701 || $course_id == 65629) ||
        ((strpos($course_title_lower, 'daf') !== false || strpos($course_title_lower, 'daz') !== false) && strpos($course_title_lower, 'kombi') === false)
    );
    if ($is_pure_daf_daz) {
        return [];
    }

    // 1. PRIORITÄT: Feld 99 (Explizit gespeicherte Zertifizierungsauswahl mit Mehrfachauswahl)
    if ($has_field_99) {
        $trimmed_val = trim((string)$field_99_raw);
        if ($trimmed_val === '') {
            // Benutzer hat explizit alle Zertifizierungen abgewählt (reines Basis-Angebot, 0 Zertifizierungen)
            return [];
        }

        $lines = explode("\n", $trimmed_val);
        $resolved_from_field = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $parsed = crm_parse_certification_line($line, $course_id);
            if ($parsed) {
                $resolved_from_field[] = $parsed;
            }
        }

        if (!empty($resolved_from_field)) {
            return $resolved_from_field;
        }
    }

    $meta_zert = mb_strtolower((string)get_post_meta($course_id, 'zertifikat', true), 'UTF-8');

    // 2. FALLBACK (heuristische Erkennung nur bei neuen Einträgen ohne gespeichertes Feld 99)
    // 2.1 Check Project Management / IPMA
    $is_pm = (
        strpos($course_title_lower, 'projektmanagement') !== false ||
        strpos($course_title_lower, 'project management') !== false ||
        strpos($course_title_lower, 'ipma') !== false ||
        strpos($course_title_lower, 'pma') !== false ||
        strpos($meta_zert, 'ipma') !== false ||
        strpos($meta_zert, 'pma') !== false
    );

    if ($is_pm) {
        if (strpos($wish_lower, 'level b') !== false || strpos($wish_lower, 'ebene b') !== false) {
            return [[
                'name'       => (strpos($wish_lower, 'online') !== false) ? 'IPMA / pma - Level B Zertifizierung (Online)' : 'IPMA / pma - Level B Zertifizierung',
                'price'      => (strpos($wish_lower, 'online') !== false) ? '1.848,00' : '2.304,50',
                'percentage' => '10%'
            ]];
        }
        if (strpos($wish_lower, 'level c') !== false || strpos($wish_lower, 'ebene c') !== false) {
            return [[
                'name'       => 'IPMA / pma - Level C Zertifizierung',
                'price'      => '1.133,00',
                'percentage' => '10%'
            ]];
        }
        // Default for PM: Level D
        return [[
            'name'       => 'IPMA / pma - Level D Zertifizierung',
            'price'      => '484,00',
            'percentage' => '10%'
        ]];
    }

    // 2. Check Scrum.org (PSM / PSPO)
    if (
        strpos($wish_lower, 'scrum') !== false ||
        strpos($wish_lower, 'psm') !== false ||
        strpos($wish_lower, 'pspo') !== false ||
        strpos($course_title_lower, 'scrum') !== false ||
        strpos($meta_zert, 'scrum') !== false ||
        strpos($meta_zert, 'psm') !== false ||
        strpos($meta_zert, 'pspo') !== false
    ) {
        if ((strpos($wish_lower, 'pspo') !== false && strpos($wish_lower, 'psm') !== false) ||
            (strpos($course_title_lower, 'product owner') !== false && strpos($course_title_lower, 'scrum master') !== false)) {
            return [[
                'name'       => 'Scrum.org Zertifizierung - PSPO I + PSM I',
                'price'      => '343,00',
                'percentage' => '0%'
            ]];
        }
        if (strpos($wish_lower, 'pspo') !== false || strpos($course_title_lower, 'product owner') !== false) {
            return [[
                'name'       => 'Scrum.org Zertifizierung - PSPO I (USD 200,-)',
                'price'      => '171,50',
                'percentage' => '0%'
            ]];
        }
        return [[
            'name'       => 'Scrum.org Zertifizierung - PSM I (USD 200,-)',
            'price'      => '171,50',
            'percentage' => '0%'
        ]];
    }

    // 3. Trainer-Ausbildungen: SystemCERT ISO 17024 FachtrainerIn (Ausschließlich definierte Positivliste)
    $is_fachtrainer_course = (
        strpos($course_title_lower, '9 tagen') !== false ||
        strpos($course_title_lower, 'interkultureller') !== false ||
        (strpos($course_title_lower, 'fachtrainer') !== false && strpos($course_title_lower, 'ams') !== false) ||
        (strpos($course_title_lower, 'kombi') !== false && (strpos($course_title_lower, 'fachtrainer') !== false || strpos($course_title_lower, 'trainer') !== false)) ||
        strpos($course_title_lower, 'ki-trainer') !== false ||
        strpos($course_title_lower, 'ki trainer') !== false ||
        strpos($wish_lower, 'fachtrainer') !== false ||
        strpos($wish_lower, 'systemcert') !== false
    );

    if ($is_fachtrainer_course) {
        return [[
            'name'       => 'SystemCERT- Kompetenzzertifizierung FachtrainerIn gemäß den Forderungen der ISO 17024',
            'price'      => '324,00',
            'percentage' => '20%'
        ]];
    }

    // 4. Check expliziter Kundenwunsch nach TÜV / ISO 17024
    if (
        strpos($wish_lower, 'tüv') !== false ||
        strpos($wish_lower, 'tuev') !== false ||
        strpos($wish_lower, 'iso 17024') !== false ||
        strpos($wish_lower, '17024') !== false ||
        strpos($meta_zert, 'tüv') !== false ||
        strpos($meta_zert, 'iso 17024') !== false
    ) {
        return [[
            'name'       => 'TÜV - ISO/IEC 17024 Kompetenz-Zertifizierung',
            'price'      => '497,00',
            'percentage' => '20%'
        ]];
    }

    // 5. Check ACF repeater am Kurs
    if (function_exists('have_rows') && have_rows('zertifizierungen', $course_id)) {
        $course_certs = [];
        while (have_rows('zertifizierungen', $course_id)) {
            the_row();
            $z_name  = get_sub_field('name-zert');
            $z_preis = get_sub_field('preis');
            $z_ust   = get_sub_field('Ust_satz');
            if ($z_name && $z_preis) {
                $course_certs[] = [
                    'name'       => (string)$z_name,
                    'price'      => (string)$z_preis,
                    'percentage' => !empty($z_ust) ? (string)$z_ust . '%' : '20%'
                ];
            }
        }
        if (!empty($course_certs)) {
            return [$course_certs[0]];
        }
    }

    // Kein blinder Fallback: Wenn der konkrete Kurs keine Zertifizierung anbietet, gibt es kein Angebot 2
    return [];
}

/**
 * Main Friedelin processing engine for a single entry.
 * Generates required PDFs, drafts individualized email, saves draft in DB, and sets status to 'versand_vorbereitet'.
 *
 * @param int $entry_id
 * @param bool $manual_trigger
 * @return array
 */
function crm_friedelin_process_entry(int $entry_id, bool $manual_trigger = false, array $selected_docs = []): array
{
    $analysis = crm_friedelin_analyze_entry($entry_id);
    if (empty($analysis['success'])) {
        return $analysis;
    }

    $course_id = $analysis['course_id'];
    $is_ams_funding = $analysis['is_ams_funding'];
    $generated_pdfs = [];
    $pdf_urls = [];

    // Ensure CRM Model class is available
    if (!class_exists('CRM_Model')) {
        $model_path = get_template_directory() . '/inc/core/crm/crm-model.php';
        if (file_exists($model_path)) {
            require_once $model_path;
        }
    }

    $course_model = ($course_id && class_exists('CRM_Model')) ? new CRM_Model($course_id, $entry_id) : null;
    $course_title = $course_model ? $course_model->title : ($analysis['course_title'] ?: 'Ihre Aus- & Weiterbildung');

    // Resolve Certification
    $resolved_cert = crm_resolve_course_certification($entry_id, $course_id);
    $has_cert_option = !empty($resolved_cert);
    $cert_name = $has_cert_option ? $resolved_cert[0]['name'] : '';

    // Determine which documents should be generated/attached
    $has_custom_selection = !empty($selected_docs);
    $want_offer_1 = $has_custom_selection ? !empty($selected_docs['offer_1']) : true;
    $want_offer_2 = $has_custom_selection ? (!empty($selected_docs['offer_2']) && $has_cert_option) : $has_cert_option;
    $want_kb      = $has_custom_selection ? !empty($selected_docs['kb']) : $is_ams_funding;
    $want_agb     = $has_custom_selection ? !empty($selected_docs['agb']) : true;

    // 1. Generate Offers
    if (function_exists('xsieben_offer_pdf') && $course_id) {
        if ($has_cert_option) {
            // Offer 1: Basis (ohne Zertifizierung)
            if ($want_offer_1) {
                $offer_1_url = xsieben_offer_pdf($entry_id, $course_id, false, null, 'basis');
                if ($offer_1_url) {
                    $generated_pdfs[] = 'Angebot 1: Basis (' . basename($offer_1_url) . ')';
                    $pdf_urls[] = $offer_1_url;
                }
            }

            // Offer 2: Inkl. Zertifizierung
            if ($want_offer_2) {
                $offer_2_url = xsieben_offer_pdf($entry_id, $course_id, false, null, 'mit_zertifikat', $resolved_cert);
                if ($offer_2_url) {
                    $generated_pdfs[] = 'Angebot 2: Inkl. Zertifizierung (' . basename($offer_2_url) . ')';
                    $pdf_urls[] = $offer_2_url;
                }
            }
        } else {
            // Standard Offer
            if ($want_offer_1) {
                $offer_url = xsieben_offer_pdf($entry_id, $course_id, false, null, 'basis');
                if ($offer_url) {
                    $generated_pdfs[] = 'Angebot (' . basename($offer_url) . ')';
                    $pdf_urls[] = $offer_url;
                }
            }
        }
    }

    // 2. Kurszeitenbestätigung (KB) PDF
    $context = ($is_ams_funding || $want_kb) ? 'xsieben_angebot_und_kurszeiten' : 'xsieben_angebot';
    if ($want_kb && $course_id) {
        if (function_exists('xsieben_kurszeitenbestaetigung_pdf')) {
            $kb_pdf_url = xsieben_kurszeitenbestaetigung_pdf($entry_id, $course_id, false);
            if ($kb_pdf_url) {
                $generated_pdfs[] = 'Kurszeitenbestätigung (' . basename($kb_pdf_url) . ')';
                $pdf_urls[] = $kb_pdf_url;
            }
        }
    }

    // 3. AGB 2025 PDF
    if ($want_agb) {
        $agb_url = function_exists('crm_get_setting') ? crm_get_setting('legal_agb_url') : '';
        if (empty($agb_url)) {
            $agb_url = 'https://x-sieben.at/wp-content/uploads/2025/09/AGB_X_SIEBEN_2025.pdf';
        }
        $generated_pdfs[] = 'AGB 2025 (' . basename($agb_url) . ')';
        $pdf_urls[] = $agb_url;
    }

    // Primary PDF for single-file handlers
    $primary_pdf_url = !empty($pdf_urls) ? $pdf_urls[0] : '';
    $all_pdf_param = implode(',', $pdf_urls);

    // 4. Draft Companion Email via central authoritative X-SIEBEN Engine
    require_once __DIR__ . '/crm-email-sections.php';
    $offer_email_data = crm_build_standard_offer_email($entry_id, $course_id, [
        'is_ams_funding'  => ($is_ams_funding || $want_kb),
        'has_cert_option' => ($has_cert_option && $want_offer_2),
        'cert_name'       => $cert_name,
        'want_offer_1'    => $want_offer_1,
        'want_offer_2'    => $want_offer_2,
        'want_kb'         => $want_kb,
        'want_agb'        => $want_agb,
    ]);

    $email_subject = $offer_email_data['subject'];
    $body_html     = $offer_email_data['body'];

    // 5. Save Friedelin Draft to DB (wp_options)
    $summary_note = sprintf(
        'Vorgang: %s | Dokumente: %s | Kurs-ID: %s',
        ($is_ams_funding || $want_kb) ? 'AMS-/Förderfall (Angebot + KB)' : 'Standard-Angebot',
        !empty($generated_pdfs) ? implode(', ', $generated_pdfs) : 'Keine',
        $course_id ?: 'Nicht verknüpft'
    );

    // Sanitize body to ensure NO internal AI notices/badges ever enter draft payload
    if (function_exists('crm_strip_internal_ai_notices')) {
        $body_html = crm_strip_internal_ai_notices($body_html);
    }

    $final_selected_docs = [
        'offer_1' => $want_offer_1,
        'offer_2' => $want_offer_2,
        'kb'      => $want_kb,
        'agb'     => $want_agb,
    ];

    $draft_payload = [
        'entry_id'        => $entry_id,
        'course_id'       => $course_id,
        'process_type'    => $analysis['process_type'],
        'is_ams_funding'  => ($is_ams_funding || $want_kb),
        'recipient'       => $analysis['email'],
        'subject'         => $email_subject,
        'body'            => $body_html,
        'pdf_urls'        => $pdf_urls,
        'primary_pdf_url' => $primary_pdf_url,
        'all_pdf_param'   => $all_pdf_param,
        'context'         => $context,
        'prepared_at'     => current_time('mysql'),
        'ai_summary'      => $summary_note,
        'selected_docs'   => $final_selected_docs,
    ];

    update_option('crm_friedelin_draft_' . $entry_id, $draft_payload);

    // 6. Update CRM Status to 'versand_vorbereitet'
    $audit_note = sprintf(
        'Für den Versand vorbereitet: Lead analysiert (%s). %s generiert, Begleit-E-Mail vorbereitet. Wartet auf manuelle Freigabe vor Versand.',
        ($is_ams_funding || $want_kb) ? 'AMS-/Förderfall' : 'Standard-Angebot',
        !empty($generated_pdfs) ? implode(' & ', $generated_pdfs) : 'Dokumente'
    );

    if (function_exists('crm_set_entry_status')) {
        crm_set_entry_status($entry_id, 'versand_vorbereitet', $audit_note);
    }

    return [
        'success'         => true,
        'entry_id'        => $entry_id,
        'course_id'       => $course_id,
        'status_key'      => 'versand_vorbereitet',
        'status_label'    => 'Für den Versand vorbereitet',
        'generated_pdfs'  => $generated_pdfs,
        'pdf_urls'        => $pdf_urls,
        'primary_pdf_url' => $primary_pdf_url,
        'all_pdf_param'   => $all_pdf_param,
        'context'         => $context,
        'subject'         => $email_subject,
        'body'            => $body_html,
        'summary'         => $summary_note,
        'selected_docs'   => $final_selected_docs,
        'message'         => 'Lead erfolgreich für den Versand vorbereitet (Freigabe erforderlich).',
    ];
}

/**
 * Retrieves the stored Friedelin draft for a specific entry.
 *
 * @param int $entry_id
 * @return array|null
 */
function crm_friedelin_get_entry_draft(int $entry_id): ?array
{
    $draft = get_option('crm_friedelin_draft_' . $entry_id, null);
    if (!is_array($draft)) {
        return null;
    }
    if (!empty($draft['body']) && function_exists('crm_strip_internal_ai_notices')) {
        $draft['body'] = crm_strip_internal_ai_notices($draft['body']);
    }
    return $draft;
}

/**
 * Deletes the stored Friedelin draft for an entry.
 *
 * @param int $entry_id
 * @return bool
 */
function crm_friedelin_delete_entry_draft(int $entry_id): bool
{
    return delete_option('crm_friedelin_draft_' . $entry_id);
}

/**
 * Automatic hook on WPForms submission.
 * Triggered when a new entry is completed in WPForms.
 */
add_action('wpforms_process_complete', function ($fields, $entry, $form_data, $entry_id) {
    $target_form_id = function_exists('crm_get_default_form_id') ? crm_get_default_form_id() : 60468;
    $current_form_id = isset($form_data['id']) ? absint($form_data['id']) : 0;

    if ($current_form_id === $target_form_id && !empty($entry_id)) {
        crm_friedelin_process_entry(absint($entry_id), false);
    }
}, 20, 4);

/**
 * AJAX Handler to manually trigger preparation for any lead from the CRM table.
 */
add_action('wp_ajax_crm_run_friedelin_preparation', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized access.', 'custom-crm')]);
    }
    if (!check_ajax_referer('crm_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Security check failed.', 'custom-crm')]);
    }

    $entry_id = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
    if (!$entry_id) {
        wp_send_json_error(['message' => __('Entry ID missing.', 'custom-crm')]);
    }

    $selected_docs = [];
    if (!empty($_POST['selected_docs'])) {
        $raw_sel = wp_unslash($_POST['selected_docs']);
        $decoded = is_string($raw_sel) ? json_decode($raw_sel, true) : $raw_sel;
        if (is_array($decoded)) {
            $selected_docs = $decoded;
        }
    }

    $result = crm_friedelin_process_entry($entry_id, true, $selected_docs);

    if (!empty($result['success'])) {
        $now = current_time('mysql');
        $date_formatted = date_i18n('d.m.Y, H:i', strtotime($now));
        $badge_html = function_exists('crm_render_status_badge')
            ? crm_render_status_badge('versand_vorbereitet', 'Für den Versand vorbereitet', $now)
            : 'Für den Versand vorbereitet';

        wp_send_json_success([
            'message'        => $result['message'],
            'entry_id'       => $entry_id,
            'status_key'     => 'versand_vorbereitet',
            'status_label'   => 'Für den Versand vorbereitet',
            'badge_html'     => $badge_html,
            'date_formatted' => $date_formatted,
            'subject'        => $result['subject'] ?? '',
            'body'           => $result['body'] ?? '',
            'summary'        => $result['summary'],
            'pdf_urls'       => $result['pdf_urls'],
            'context'        => $result['context'],
            'selected_docs'  => $result['selected_docs'] ?? [],
        ]);
    } else {
        wp_send_json_error(['message' => $result['error'] ?? 'Fehler bei der Vorbereitung für den Versand.']);
    }
});

