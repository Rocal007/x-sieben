<?php

/**
 * CRM Customer Status & Timestamp Tracking Engine
 * Manages customer lifecycle status ('Angebot gesendet', 'Kurszeitenbestätigung gesendet', etc.),
 * audit timestamps, and historical logs.
 * 
 * 100% self-contained within inc/core/crm/
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('CRM_DB_VERSION')) {
    define('CRM_DB_VERSION', '1.4');
}

/**
 * Liefert die konfigurierte WPForms-Formular-ID für CRM-Anfragen.
 * Standardwert: 60468, anpassbar über wp_options 'crm_wpforms_form_id' oder Filter 'crm_default_form_id'.
 *
 * @return int
 */
function crm_get_default_form_id(): int
{
    $form_id = absint(get_option('crm_wpforms_form_id', 60468));
    return (int) apply_filters('crm_default_form_id', $form_id ?: 60468);
}

/**
 * Ensure the status and history tables exist.
 * Uses persistent DB version tracking and a static in-request cache to prevent
 * executing dbDelta() and SHOW COLUMNS on every single query/request.
 *
 * @param bool $force Force schema execution even if DB version matches.
 */
function crm_ensure_status_tables($force = false)
{
    static $ensured = false;
    if ($ensured && !$force) {
        return;
    }

    $installed_ver = get_option('crm_db_version');
    if ($installed_ver === CRM_DB_VERSION && !$force) {
        $ensured = true;
        return;
    }

    global $wpdb;

    $table_status = $wpdb->prefix . 'crm_entry_status';
    $table_history = $wpdb->prefix . 'crm_entry_status_history';
    $table_snapshots = $wpdb->prefix . 'crm_document_snapshots';
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // 1. Current status table (with persistent course dates snapshot & inquiry linking)
    $sql_status = "CREATE TABLE $table_status (
        entry_id bigint(20) NOT NULL,
        form_id bigint(20) NOT NULL DEFAULT 60468,
        status_key varchar(60) NOT NULL,
        status_label varchar(100) NOT NULL,
        status_date datetime NOT NULL,
        course_id bigint(20) DEFAULT NULL,
        course_start_date varchar(50) DEFAULT NULL,
        course_end_date varchar(50) DEFAULT NULL,
        inquiry_type varchar(50) NOT NULL DEFAULT 'course',
        custom_title varchar(255) DEFAULT NULL,
        note text DEFAULT NULL,
        updated_by bigint(20) NOT NULL DEFAULT 0,
        PRIMARY KEY  (entry_id),
        KEY status_key (status_key),
        KEY status_date (status_date),
        KEY course_id (course_id),
        KEY inquiry_type (inquiry_type)
    ) $charset_collate;";

    // 2. Status history table
    $sql_history = "CREATE TABLE $table_history (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        entry_id bigint(20) NOT NULL,
        form_id bigint(20) NOT NULL DEFAULT 60468,
        status_key varchar(60) NOT NULL,
        status_label varchar(100) NOT NULL,
        status_date datetime NOT NULL,
        note text DEFAULT NULL,
        created_by bigint(20) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        KEY entry_id (entry_id),
        KEY status_date (status_date)
    ) $charset_collate;";

    // 3. Document snapshots table (revisionssicheres Archiv für versendete Dokumente & Payloads)
    $sql_snapshots = "CREATE TABLE $table_snapshots (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        entry_id bigint(20) NOT NULL,
        form_id bigint(20) NOT NULL DEFAULT 60468,
        course_id bigint(20) NOT NULL,
        doc_type varchar(50) NOT NULL,
        status_key varchar(60) NOT NULL,
        recipient varchar(191) NOT NULL,
        sent_targets text NOT NULL,
        subject varchar(255) NOT NULL,
        email_body_html mediumtext NOT NULL,
        pdf_filename varchar(255) DEFAULT NULL,
        pdf_file_path varchar(255) DEFAULT NULL,
        pdf_file_url varchar(255) DEFAULT NULL,
        data_snapshot_json longtext NOT NULL,
        is_test tinyint(1) NOT NULL DEFAULT 0,
        sent_by bigint(20) NOT NULL DEFAULT 0,
        sent_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY entry_id (entry_id),
        KEY doc_type (doc_type),
        KEY sent_at (sent_at)
    ) $charset_collate;";

    dbDelta($sql_status);
    dbDelta($sql_history);
    dbDelta($sql_snapshots);

    // Defensive check to ensure columns exist immediately on existing tables
    $has_course_col = $wpdb->get_results("SHOW COLUMNS FROM $table_status LIKE 'course_start_date'");
    if (empty($has_course_col)) {
        $wpdb->query("ALTER TABLE $table_status ADD COLUMN course_id bigint(20) DEFAULT NULL AFTER status_date");
        $wpdb->query("ALTER TABLE $table_status ADD COLUMN course_start_date varchar(50) DEFAULT NULL AFTER course_id");
        $wpdb->query("ALTER TABLE $table_status ADD COLUMN course_end_date varchar(50) DEFAULT NULL AFTER course_start_date");
    }

    $has_inquiry_col = $wpdb->get_results("SHOW COLUMNS FROM $table_status LIKE 'inquiry_type'");
    if (empty($has_inquiry_col)) {
        $wpdb->query("ALTER TABLE $table_status ADD COLUMN inquiry_type varchar(50) NOT NULL DEFAULT 'course' AFTER course_end_date");
        $wpdb->query("ALTER TABLE $table_status ADD COLUMN custom_title varchar(255) DEFAULT NULL AFTER inquiry_type");
    }

    update_option('crm_db_version', CRM_DB_VERSION);
    $ensured = true;
}
add_action('admin_init', 'crm_ensure_status_tables');

/**
 * Returns configuration of all available CRM statuses.
 *
 * @return array
 */
function crm_get_statuses()
{
    return [
        'neu' => [
            'label'  => __('Neu / Anfrage', 'custom-crm'),
            'color'  => '#0369a1',
            'bg'     => '#e0f2fe',
            'border' => '#7dd3fc',
            'icon'   => 'dashicons-email',
        ],
        'versand_vorbereitet' => [
            'label'  => __('Für den Versand vorbereitet', 'custom-crm'),
            'color'  => '#6d28d9',
            'bg'     => '#f5f3ff',
            'border' => '#c4b5fd',
            'icon'   => 'dashicons-email-alt2',
        ],
        'ai_prepared' => [
            'label'  => __('Für den Versand vorbereitet', 'custom-crm'),
            'color'  => '#6d28d9',
            'bg'     => '#f5f3ff',
            'border' => '#c4b5fd',
            'icon'   => 'dashicons-email-alt2',
        ],
        'ki_vorbereitet' => [
            'label'  => __('Für den Versand vorbereitet', 'custom-crm'),
            'color'  => '#6d28d9',
            'bg'     => '#f5f3ff',
            'border' => '#c4b5fd',
            'icon'   => 'dashicons-email-alt2',
        ],

        'angebot_erstellt' => [
            'label'  => __('Angebot erstellt', 'custom-crm'),
            'color'  => '#075985',
            'bg'     => '#f0f9ff',
            'border' => '#bae6fd',
            'icon'   => 'dashicons-media-document',
        ],
        'angebot_gesendet' => [
            'label'  => __('Angebot gesendet', 'custom-crm'),
            'color'  => '#1d4ed8',
            'bg'     => '#eff6ff',
            'border' => '#93c5fd',
            'icon'   => 'dashicons-email-alt',
        ],
        'kurszeitenbestaetigung_gesendet' => [
            'label'  => __('Kurszeitenbestätigung gesendet', 'custom-crm'),
            'color'  => '#6d28d9',
            'bg'     => '#f5f3ff',
            'border' => '#c4b5fd',
            'icon'   => 'dashicons-calendar-alt',
        ],
        'angebot_und_kurszeiten_gesendet' => [
            'label'  => __('Angebot & KB gesendet', 'custom-crm'),
            'color'  => '#3730a3',
            'bg'     => '#e0e7ff',
            'border' => '#a5b4fc',
            'icon'   => 'dashicons-media-text',
        ],
        'angemeldet' => [
            'label'  => __('Angemeldet / Gebucht', 'custom-crm'),
            'color'  => '#166534',
            'bg'     => '#dcfce7',
            'border' => '#86efac',
            'icon'   => 'dashicons-yes',
        ],
        'teilnahmebestaetigung_gesendet' => [
            'label'  => __('Teilnahmebestätigung gesendet', 'custom-crm'),
            'color'  => '#115e59',
            'bg'     => '#ccfbf1',
            'border' => '#5eead4',
            'icon'   => 'dashicons-yes-alt',
        ],
        'diplom_gesendet' => [
            'label'  => __('Diplom gesendet', 'custom-crm'),
            'color'  => '#92400e',
            'bg'     => '#fef3c7',
            'border' => '#fcd34d',
            'icon'   => 'dashicons-awards',
        ],
        'in_bearbeitung' => [
            'label'  => __('In Bearbeitung', 'custom-crm'),
            'color'  => '#9a3412',
            'bg'     => '#fff7ed',
            'border' => '#fed7aa',
            'icon'   => 'dashicons-update',
        ],
        'nachfassen' => [
            'label'  => __('Nachfassen / Offen', 'custom-crm'),
            'color'  => '#854d0e',
            'bg'     => '#fefce8',
            'border' => '#fde047',
            'icon'   => 'dashicons-clock',
        ],
        'abgeschlossen' => [
            'label'  => __('Abgeschlossen', 'custom-crm'),
            'color'  => '#334155',
            'bg'     => '#f1f5f9',
            'border' => '#cbd5e1',
            'icon'   => 'dashicons-saved',
        ],
        'storniert' => [
            'label'  => __('Storniert / Absage', 'custom-crm'),
            'color'  => '#991b1b',
            'bg'     => '#fee2e2',
            'border' => '#fca5a5',
            'icon'   => 'dashicons-dismiss',
        ],
        'test_mail_gesendet' => [
            'label'  => __('🧪 Test-Mail gesendet', 'custom-crm'),
            'color'  => '#0e7490',
            'bg'     => '#ecfeff',
            'border' => '#a5f3fc',
            'icon'   => 'dashicons-email-alt',
        ],
    ];
}

/**
 * Ermittelt den Meilenstein der Kundenreise (1 bis 5) für einen Status-Key.
 * Birkenbihl-Neurodidaktik: Schnelle Orientierung auf dem Lebenszyklus-Pfad.
 *
 * @param string $status_key
 * @return int (1 = Anfrage, 2 = Angebot, 3 = Nachfassen, 4 = Gebucht, 5 = Abschluss, 0 = Storno)
 */
function crm_get_lifecycle_milestone_number(string $status_key): int
{
    $status_key = sanitize_key($status_key);

    if ($status_key === 'storniert') {
        return 0;
    }

    switch ($status_key) {
        case 'angebot_gesendet':
        case 'kurszeitenbestaetigung_gesendet':
        case 'angebot_und_kurszeiten_gesendet':
            return 2;

        case 'nachfassen':
            return 3;

        case 'angemeldet':
        case 'rechnung_gestellt':
        case 'rechnung_bezahlt':
            return 4;

        case 'teilnahmebestaetigung_gesendet':
        case 'diplom_gesendet':
        case 'abgeschlossen':
            return 5;

        case 'neu':
        case 'in_bearbeitung':
        case 'versand_vorbereitet':
        case 'ai_prepared':
        case 'ki_vorbereitet':
        case 'angebot_erstellt':
        case 'test_mail_gesendet':
        default:
            return 1;
    }
}

/**
 * Rendert die kompakte, gehirn-gerechte 5-Stufen Kundenreise-Ampel (Birkenbihl).
 *
 * @param string $current_status_key
 * @param int $entry_id
 * @return string HTML
 */
function crm_render_journey_tracker(string $current_status_key, int $entry_id = 0): string
{
    $current_step = crm_get_lifecycle_milestone_number($current_status_key);
    $is_storno = ($current_step === 0);

    $step_titles = [
        1 => __('Stufe 1/5: Anfrage', 'custom-crm'),
        2 => __('Stufe 2/5: Angebot versendet', 'custom-crm'),
        3 => __('Stufe 3/5: Nachfassen', 'custom-crm'),
        4 => __('Stufe 4/5: Gebucht & Angemeldet', 'custom-crm'),
        5 => __('Stufe 5/5: Abgeschlossen', 'custom-crm'),
    ];

    $tooltips = [
        1 => __('Stufe 1 von 5: Anfrage (Interesse bekundet)', 'custom-crm'),
        2 => __('Stufe 2 von 5: Angebot (Unterlagen versendet)', 'custom-crm'),
        3 => __('Stufe 3 von 5: Nachfassen (Bedenkzeit / Nachfrage)', 'custom-crm'),
        4 => __('Stufe 4 von 5: Gebucht (Fixiert & angemeldet)', 'custom-crm'),
        5 => __('Stufe 5 von 5: Abschluss (Teilnahmebestätigung / Diplom)', 'custom-crm'),
    ];

    $caption_text = $is_storno ? __('Storniert / Abgesagt', 'custom-crm') : ($step_titles[$current_step] ?? __('Stufe 1/5: Anfrage', 'custom-crm'));
    $overall_title = $is_storno ? __('Status: Storniert / Abgesagt', 'custom-crm') : ($tooltips[$current_step] ?? '');

    ob_start();
    ?>
    <div class="crm-journey-tracker <?php echo $is_storno ? 'is-storno' : ''; ?>" data-current-step="<?php echo esc_attr($current_step); ?>" title="<?php echo esc_attr($overall_title); ?>" style="margin: 4px 0 3px 0; max-width: 170px; width: 100%;">
        <div class="crm-journey-bar" style="display: flex; gap: 3px; width: 100%; height: 5px; align-items: center;">
            <?php for ($num = 1; $num <= 5; $num++):
                $seg_class = '';
                if ($is_storno) {
                    $seg_class = 'is-storno';
                } elseif ($num < $current_step) {
                    $seg_class = 'is-completed';
                } elseif ($num === $current_step) {
                    $seg_class = 'is-active';
                } else {
                    $seg_class = 'is-upcoming';
                }
            ?>
                <div class="crm-journey-seg crm-seg-<?php echo esc_attr($num); ?> <?php echo esc_attr($seg_class); ?>" 
                     data-step="<?php echo esc_attr($num); ?>" 
                     title="<?php echo esc_attr($tooltips[$num]); ?>" 
                     style="flex: 1; height: 5px; border-radius: 3px;"></div>
            <?php endfor; ?>
        </div>
        <div class="crm-journey-caption" style="display: flex; align-items: center; justify-content: space-between; margin-top: 3px; font-size: 10.5px; line-height: 1.2;">
            <span class="crm-journey-step-text crm-text-step-<?php echo esc_attr($current_step); ?>" style="font-weight: 600;">
                <?php echo esc_html($caption_text); ?>
            </span>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Sets the current status for an entry, and writes an entry into the audit trail.
 */
function crm_set_entry_status($entry_id, $status_key, $note = '', $status_date = null, $user_id = null, $form_id = null)
{
    global $wpdb;

    $entry_id = absint($entry_id);
    if (!$entry_id) {
        return false;
    }

    $form_id = $form_id ? absint($form_id) : crm_get_default_form_id();
    $statuses = crm_get_statuses();
    $status_key = sanitize_key($status_key);
    $status_label = isset($statuses[$status_key]) ? $statuses[$status_key]['label'] : ucwords(str_replace('_', ' ', $status_key));
    $status_date = $status_date ?: current_time('mysql');
    $user_id = $user_id !== null ? absint($user_id) : get_current_user_id();

    crm_ensure_status_tables();

    $table_status = $wpdb->prefix . 'crm_entry_status';
    $table_history = $wpdb->prefix . 'crm_entry_status_history';

    // 1. Insert into history
    $wpdb->insert(
        $table_history,
        [
            'entry_id'     => $entry_id,
            'form_id'      => $form_id,
            'status_key'   => $status_key,
            'status_label' => $status_label,
            'status_date'  => $status_date,
            'note'         => $note,
            'created_by'   => $user_id,
        ],
        ['%d', '%d', '%s', '%s', '%s', '%s', '%d']
    );

    // 2. Upsert into current status table
    $exists = $wpdb->get_var($wpdb->prepare("SELECT entry_id FROM $table_status WHERE entry_id = %d", $entry_id));

    if ($exists) {
        $wpdb->update(
            $table_status,
            [
                'form_id'      => $form_id,
                'status_key'   => $status_key,
                'status_label' => $status_label,
                'status_date'  => $status_date,
                'note'         => $note,
                'updated_by'   => $user_id,
            ],
            ['entry_id' => $entry_id],
            ['%d', '%s', '%s', '%s', '%s', '%d'],
            ['%d']
        );
    } else {
        $wpdb->insert(
            $table_status,
            [
                'entry_id'     => $entry_id,
                'form_id'      => $form_id,
                'status_key'   => $status_key,
                'status_label' => $status_label,
                'status_date'  => $status_date,
                'note'         => $note,
                'updated_by'   => $user_id,
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%d']
        );
    }

    return true;
}

/**
 * Adds an entry to the status history without overwriting the current main status (e.g. for test mails).
 */
function crm_add_entry_status_history($entry_id, $status_key, $status_label, $note = '', $form_id = null)
{
    global $wpdb;

    $entry_id = absint($entry_id);
    if (!$entry_id) {
        return false;
    }

    $form_id = $form_id ? absint($form_id) : crm_get_default_form_id();
    $table_history = $wpdb->prefix . 'crm_entry_status_history';

    return (bool) $wpdb->insert(
        $table_history,
        [
            'entry_id'     => $entry_id,
            'form_id'      => $form_id,
            'status_key'   => sanitize_key($status_key),
            'status_label' => sanitize_text_field($status_label),
            'status_date'  => current_time('mysql'),
            'note'         => $note,
            'created_by'   => get_current_user_id(),
        ],
        ['%d', '%d', '%s', '%s', '%s', '%s', '%d']
    );
}

/**
 * Batch retrieves current statuses for a list of entry IDs in a single query.
 *
 * @param array $entry_ids
 * @return array Keyed by entry_id
 */
function crm_get_entries_statuses(array $entry_ids)
{
    global $wpdb;

    $entry_ids = array_filter(array_map('absint', $entry_ids));
    if (empty($entry_ids)) {
        return [];
    }

    $table_status = $wpdb->prefix . 'crm_entry_status';

    $in_placeholders = implode(',', array_fill(0, count($entry_ids), '%d'));
    $query = $wpdb->prepare("SELECT * FROM $table_status WHERE entry_id IN ($in_placeholders)", $entry_ids);
    $results = $wpdb->get_results($query, ARRAY_A);

    $statuses = [];
    if (!empty($results)) {
        foreach ($results as $row) {
            $statuses[$row['entry_id']] = $row;
        }
    }

    return $statuses;
}

/**
 * Retrieves the current status row for a single entry.
 *
 * @param int $entry_id
 * @return array|null
 */
function crm_get_entry_status($entry_id)
{
    $statuses = crm_get_entries_statuses([(int)$entry_id]);
    return $statuses[(int)$entry_id] ?? null;
}

/**
 * Retrieves the full status history for an entry.
 *
 * @param int $entry_id
 * @return array
 */
function crm_get_entry_history($entry_id)
{
    global $wpdb;

    $entry_id = absint($entry_id);
    if (!$entry_id) {
        return [];
    }

    $table_history = $wpdb->prefix . 'crm_entry_status_history';

    return $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table_history WHERE entry_id = %d ORDER BY status_date DESC, id DESC", $entry_id),
        ARRAY_A
    );
}

/**
 * Renders the HTML badge for a given status.
 */
function crm_render_status_badge($status_key, $status_label = '', $status_date = '')
{
    $all_statuses = crm_get_statuses();
    $conf = isset($all_statuses[$status_key]) ? $all_statuses[$status_key] : [
        'label'  => $status_label ?: $status_key,
        'color'  => '#334155',
        'bg'     => '#f1f5f9',
        'border' => '#cbd5e1',
        'icon'   => 'dashicons-marker',
    ];

    $label = $status_label ?: $conf['label'];

    return sprintf(
        '<span class="crm-status-badge crm-status-%s" data-status="%s" style="background:%s; color:%s; border:1px solid %s;"><span class="crm-status-dot"></span><span class="crm-status-label">%s</span></span>',
        esc_attr($status_key),
        esc_attr($status_key),
        esc_attr($conf['bg']),
        esc_attr($conf['color']),
        esc_attr($conf['border']),
        esc_html($label)
    );
}

/**
 * Ermittelt die Förderstellen-Zuordnung (AMS, WAFF) für einen Eintrag.
 * Priorität 1: Explizit gespeicherte Einstellung des Bearbeiters.
 * Priorität 2: Automatische Erkennung aus den WPForms-Feldern.
 *
 * @param int $entry_id
 * @param array|null $fields
 * @return array ['ams' => bool, 'waff' => bool]
 */
function crm_get_entry_foerderung($entry_id, $fields = null): array
{
    $entry_id = absint($entry_id);
    if (!$entry_id) {
        return ['ams' => false, 'waff' => false];
    }

    $saved = get_option('crm_entry_foerderung_' . $entry_id, null);
    if (is_array($saved)) {
        return [
            'ams'  => !empty($saved['ams']),
            'waff' => !empty($saved['waff']),
        ];
    }

    // Automatische Erkennung aus den Feldern des WPForms-Eintrags
    if ($fields === null && function_exists('wpforms') && isset(wpforms()->entry)) {
        $entry = wpforms()->entry->get($entry_id);
        if ($entry && !empty($entry->fields)) {
            $fields = is_string($entry->fields) ? json_decode($entry->fields, true) : $entry->fields;
        }
    }

    $has_ams  = false;
    $has_waff = false;

    if (is_array($fields)) {
        foreach ($fields as $f) {
            $name_raw   = isset($f['name']) ? (string)$f['name'] : '';
            $name_lower = mb_strtolower(trim($name_raw), 'UTF-8');
            $raw_val    = $f['value'] ?? '';
            $val_str    = is_array($raw_val) ? implode(' ', $raw_val) : (string)$raw_val;
            $val_lower  = mb_strtolower(trim($val_str), 'UTF-8');

            if ($val_lower === '') {
                continue; // Wenn Checkbox nicht angehakt oder Text leer ist, nicht als aktiv werten!
            }

            // 1. Spezifische Checkboxen: "Angebot für eine Förderstelle (waff, AMS, Landesförderungen, etc.)"
            if (strpos($val_lower, 'angebot für das ams') !== false || strpos($val_lower, 'für das ams') !== false) {
                $has_ams = true;
            }
            if (
                strpos($val_lower, 'angebot für landesförderung') !== false ||
                strpos($val_lower, 'waff') !== false ||
                strpos($val_lower, 'land nö') !== false ||
                strpos($val_lower, 'landesförderung') !== false
            ) {
                $has_waff = true;
            }

            // 2. SV. Nr. (Optional für AMS Förderantrag)
            if (
                (strpos($name_lower, 'sv. nr') !== false ||
                 strpos($name_lower, 'sv-nr') !== false ||
                 strpos($name_lower, 'sozialversicherung') !== false ||
                 strpos($name_lower, 'svr') !== false) &&
                preg_match('/\d{3,}/', $val_lower)
            ) {
                $has_ams = true;
            }

            // 3. Freitext / E-Mail / Nachricht / Anmerkung
            if (in_array($name_lower, ['nachricht', 'nachricht / freitext', 'freitext', 'anmerkungen', 'anmerkung', 'ihre nachricht', 'message', 'text'], true)) {
                if (preg_match('/\bams\b/u', $val_lower) || strpos($val_lower, 'arbeitsmarktservice') !== false) {
                    $has_ams = true;
                }
                if (preg_match('/\bwaff\b/u', $val_lower) || strpos($val_lower, 'wiener arbeitnehmer') !== false) {
                    $has_waff = true;
                }
            }

            // 4. Allgemeine Checkbox "Förderung": "Bitte ankreuzen, wenn Sie eine Förderung (AMS, waff, etc.) beantragen möchten"
            if (
                strpos($name_lower, 'förderung') !== false ||
                strpos($val_lower, 'bitte ankreuzen') !== false ||
                strpos($val_lower, 'förderung beantragen') !== false
            ) {
                // Wenn Förderung allgemein angekreuzt wurde und noch keine spezifische gewählt ist -> Standard AMS
                if (!$has_ams && !$has_waff) {
                    $has_ams = true;
                }
            }
        }
    }

    return [
        'ams'  => $has_ams,
        'waff' => $has_waff,
    ];
}

/**
 * Speichert den Förderstatus für einen CRM-Eintrag und protokolliert die Änderung.
 *
 * @param int $entry_id
 * @param string $type 'ams' oder 'waff'
 * @param bool $is_active
 * @return array Updated ['ams' => bool, 'waff' => bool]
 */
function crm_set_entry_foerderung($entry_id, string $type, bool $is_active): array
{
    $entry_id = absint($entry_id);
    if (!$entry_id) {
        return ['ams' => false, 'waff' => false];
    }

    $type = sanitize_key($type);
    if (!in_array($type, ['ams', 'waff'], true)) {
        return crm_get_entry_foerderung($entry_id);
    }

    $current = crm_get_entry_foerderung($entry_id);
    $current[$type] = (bool)$is_active;

    update_option('crm_entry_foerderung_' . $entry_id, $current);

    if (function_exists('crm_add_entry_status_history')) {
        $label = ($type === 'ams') ? 'AMS-Förderung' : 'WAFF-Förderung';
        $state_str = $is_active ? __('aktiviert', 'custom-crm') : __('deaktiviert', 'custom-crm');
        crm_add_entry_status_history(
            $entry_id,
            'foerderung_' . $type,
            $label . ' ' . $state_str,
            sprintf(__('Förderstatus "%s" wurde auf %s gesetzt.', 'custom-crm'), $label, $state_str)
        );
    }

    return $current;
}

/**
 * Rendert die interaktiven Badges mit Checkboxen für AMS und WAFF.
 *
 * @param int $entry_id
 * @param int $course_id
 * @param array|null $foerderung
 * @return string HTML
 */
function crm_render_foerderung_badges($entry_id, $course_id = 0, $foerderung = null): string
{
    $entry_id  = absint($entry_id);
    $course_id = absint($course_id);

    if ($foerderung === null) {
        $foerderung = crm_get_entry_foerderung($entry_id);
    }

    $ams_active  = !empty($foerderung['ams']);
    $waff_active = !empty($foerderung['waff']);

    $ams_class  = $ams_active ? 'crm-foerder-badge crm-foerder-ams is-active' : 'crm-foerder-badge crm-foerder-ams';
    $waff_class = $waff_active ? 'crm-foerder-badge crm-foerder-waff is-active' : 'crm-foerder-badge crm-foerder-waff';

    $ams_title  = $ams_active ? __('AMS Förderung aktiv (Angebot & KB vorausgewählt)', 'custom-crm') : __('Klicken zum Aktivieren der AMS Förderung', 'custom-crm');
    $waff_title = $waff_active ? __('WAFF Förderung aktiv (Angebot & KB vorausgewählt)', 'custom-crm') : __('Klicken zum Aktivieren der WAFF Förderung', 'custom-crm');

    ob_start();
    ?>
    <div class="crm-foerderung-badges" data-entry-id="<?php echo esc_attr($entry_id); ?>" data-course-id="<?php echo esc_attr($course_id); ?>">
        <label class="<?php echo esc_attr($ams_class); ?>" title="<?php echo esc_attr($ams_title); ?>">
            <input type="checkbox"
                   class="crm-foerder-cb"
                   data-type="ams"
                   data-entry-id="<?php echo esc_attr($entry_id); ?>"
                   data-course-id="<?php echo esc_attr($course_id); ?>"
                   <?php checked($ams_active); ?>>
            <span class="crm-foerder-text">AMS</span>
        </label>
        <label class="<?php echo esc_attr($waff_class); ?>" title="<?php echo esc_attr($waff_title); ?>">
            <input type="checkbox"
                   class="crm-foerder-cb"
                   data-type="waff"
                   data-entry-id="<?php echo esc_attr($entry_id); ?>"
                   data-course-id="<?php echo esc_attr($course_id); ?>"
                   <?php checked($waff_active); ?>>
            <span class="crm-foerder-text">WAFF</span>
        </label>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Renders pure visual badges (without checkboxes) for AMS and WAFF next to the course date.
 *
 * @param int $entry_id
 * @param array|null $foerderung
 * @return string HTML
 */
function crm_render_foerderung_pure_badges($entry_id, $foerderung = null): string
{
    $entry_id = absint($entry_id);
    if ($foerderung === null) {
        $foerderung = crm_get_entry_foerderung($entry_id);
    }

    $ams_active  = !empty($foerderung['ams']);
    $waff_active = !empty($foerderung['waff']);

    ob_start();
    ?>
    <span class="crm-course-foerder-badges" data-entry-id="<?php echo esc_attr($entry_id); ?>">
        <?php if ($ams_active) : ?>
            <span class="crm-badge crm-badge-foerder crm-badge-ams" title="<?php esc_attr_e('AMS Förderung aktiv', 'custom-crm'); ?>">AMS</span>
        <?php endif; ?>
        <?php if ($waff_active) : ?>
            <span class="crm-badge crm-badge-foerder crm-badge-waff" title="<?php esc_attr_e('WAFF Förderung aktiv', 'custom-crm'); ?>">WAFF</span>
        <?php endif; ?>
    </span>
    <?php
    return ob_get_clean();
}

/**
 * Returns the 2 primary action keys and remaining secondary action keys based on entry status and funding.
 *
 * @param string $status_key
 * @param bool $is_foerderung Ob AMS oder WAFF Förderung aktiv ist
 * @return array ['primary' => string[], 'secondary' => string[]]
 */
function crm_get_actions_for_status($status_key, $is_foerderung = false)
{
    $all_keys = [
        'xsieben_angebot_und_kurszeiten',
        'xsieben_offer',
        'xsieben_kurszeitenbestaetigung',
        'xsieben_teilnahmebestaetigung',
        'xsieben_diplom',
    ];

    // Wenn AMS oder WAFF aktiv ist, ist "Angebot & KB" (xsieben_angebot_und_kurszeiten)
    // der nächste logische Primärschritt vor Abschluss/Buchung
    if ($is_foerderung && in_array($status_key, ['neu', 'ai_prepared', 'ki_vorbereitet', 'versand_vorbereitet', 'angebot_erstellt', 'angebot_gesendet', 'kurszeitenbestaetigung_gesendet', 'nachfassen', 'in_bearbeitung', 'storniert'], true)) {
        $primary = ['xsieben_angebot_und_kurszeiten'];
        return [
            'primary'   => $primary,
            'secondary' => array_values(array_diff($all_keys, $primary)),
        ];
    }

    switch ($status_key) {
        case 'angebot_und_kurszeiten_gesendet':
        case 'angemeldet':
            // Angebot & KB bereits versandt bzw. angemeldet -> Nächster Schritt: TB
            $primary = ['xsieben_teilnahmebestaetigung'];
            break;

        case 'teilnahmebestaetigung_gesendet':
            // TB versendet -> Nächster Schritt: Diplom
            $primary = ['xsieben_diplom'];
            break;

        case 'diplom_gesendet':
        case 'abgeschlossen':
            // Abgeschlossen -> Diplom
            $primary = ['xsieben_diplom'];
            break;

        case 'angebot_gesendet':
            // Nur Angebot versendet -> Nächster Schritt: Kurszeitenbestätigung (KB)
            $primary = ['xsieben_kurszeitenbestaetigung'];
            break;

        case 'kurszeitenbestaetigung_gesendet':
            // Nur KB versendet -> Nächster Schritt: Angebot bzw. TB
            $primary = ['xsieben_offer'];
            break;

        case 'neu':
        case 'ai_prepared':
        case 'ki_vorbereitet':
        case 'versand_vorbereitet':
        case 'angebot_erstellt':
        case 'nachfassen':
        case 'in_bearbeitung':
        case 'storniert':
        default:
            // Regulärer Kunde: Primär Angebot
            $primary = ['xsieben_offer'];
            break;
    }

    $secondary = array_values(array_diff($all_keys, $primary));

    return [
        'primary'   => $primary,
        'secondary' => $secondary,
    ];
}

/**
 * Rendert das Dropdown-Menü zur Simulation und Vorschau aller PDF-Dokumente.
 *
 * @param int $entry_id
 * @param int $course_id
 * @param string $align 'left' oder 'right'
 * @return string HTML
 */
function crm_render_docs_dropdown($entry_id, $course_id, $align = 'left')
{
    $entry_id  = absint($entry_id);
    $course_id = absint($course_id);
    $is_foerd  = function_exists('crm_entry_has_foerderung') ? crm_entry_has_foerderung($entry_id) : false;
    $default_act = $is_foerd ? 'xsieben_angebot_und_kurszeiten' : 'xsieben_offer';

    ob_start();
    ?>
    <button type="button" class="button crm-direct-editor-btn crm-docs-btn" data-entry-id="<?php echo esc_attr($entry_id); ?>" data-course-id="<?php echo esc_attr($course_id); ?>" data-action="<?php echo esc_attr($default_act); ?>" title="<?php esc_attr_e('Dokumente (Angebote, KB, TB, Diplom) im großen Arbeitsbereich anzeigen & simulieren', 'custom-crm'); ?>">
        <span>📄 <?php esc_html_e('Dokumente', 'custom-crm'); ?></span>
    </button>
    <?php
    return ob_get_clean();
}

/**
 * Renders the modern action buttons (always 2 primary steps + optional more menu).
 *
 * @param int $entry_id
 * @param int $course_id
 * @param string $status_key
 * @param bool|null $is_foerderung
 * @return string HTML
 */
function crm_render_entry_actions($entry_id, $course_id, $status_key = 'neu', $is_foerderung = null)
{
    $entry_id  = absint($entry_id);
    $course_id = absint($course_id);

    if ($is_foerderung === null) {
        $is_foerderung = function_exists('crm_entry_has_foerderung') ? crm_entry_has_foerderung($entry_id) : false;
    }

    $all_statuses = function_exists('crm_get_all_statuses') ? crm_get_all_statuses() : [];
    $status_conf  = isset($all_statuses[$status_key]) ? $all_statuses[$status_key] : ($all_statuses['neu'] ?? []);

    // Playful Wizard character & speech bubble saying
    $is_ready     = false;
    $theme_class  = 'theme-purple';

    switch ($status_key) {
        case 'ki_vorbereitet':
            $speech       = __('KI hat vorbereitet!', 'custom-crm');
            $title        = __('▶ Wizard: KI-Vorbereitung erneut ausführen (Dokumente & Begleit-Mail aktualisieren)', 'custom-crm');
            $stage        = 'offer';
            $theme_class  = 'theme-purple';
            break;

        case 'versandbereit':
            $is_ready     = true;
            $speech       = __('Versandbereit! Freigeben?', 'custom-crm');
            $title        = __('▶ Wizard: Für den Versand vorbereitet – Klick zur Freigabe & zum Versand', 'custom-crm');
            $stage        = 'offer';
            $theme_class  = 'theme-green';
            break;

        case 'gesendet':
            $speech       = __('Angebot ist versendet!', 'custom-crm');
            $title        = __('▶ Wizard: Bereits versendet – Klick zum erneuten Vorbereiten oder Aktualisieren', 'custom-crm');
            $stage        = 'offer';
            $theme_class  = 'theme-green';
            break;

        case 'gebucht':
            $speech       = __('Gebucht! TB vorbereiten', 'custom-crm');
            $title        = __('▶ Wizard: Gebucht – Durchführung im Wizard vorbereiten', 'custom-crm');
            $stage        = 'booking';
            $theme_class  = 'theme-green';
            break;

        case 'durchgefuehrt':
            $speech       = __('Erfolgreich durchgeführt!', 'custom-crm');
            $title        = __('▶ Wizard: Abgeschlossen – Verlauf & Dokumente im Wizard einsehen', 'custom-crm');
            $stage        = 'done';
            $theme_class  = 'theme-slate';
            break;

        case 'versand_vorbereitet':
        case 'ai_prepared':
            $is_ready     = true;
            $speech       = __('Versandbereit! Freigeben?', 'custom-crm');
            $title        = __('▶ Wizard: Angebot & Dokumente vorbereitet – Klick zur Freigabe & zum Versand', 'custom-crm');
            $stage        = 'offer';
            $theme_class  = 'theme-green';
            break;

        case 'angebot_erstellt':
            $speech       = __('Angebot liegt bereit', 'custom-crm');
            $title        = __('▶ Wizard: Angebot erstellt – Wizard öffnen', 'custom-crm');
            $stage        = 'offer';
            $theme_class  = 'theme-purple';
            break;

        case 'angebot_gesendet':
        case 'angebot_und_kurszeiten_gesendet':
        case 'kurszeitenbestaetigung_gesendet':
            $speech       = __('Angebot ist raus!', 'custom-crm');
            $title        = __('▶ Wizard: Angebot versendet – Wizard für Nachfassen / Buchungsübernahme öffnen', 'custom-crm');
            $stage        = 'followup';
            $theme_class  = 'theme-amber';
            break;

        case 'nachfassen':
            $speech       = __('Zeit zum Nachfassen!', 'custom-crm');
            $title        = __('▶ Wizard: Nachfassen empfohlen – Wizard öffnen', 'custom-crm');
            $stage        = 'followup';
            $theme_class  = 'theme-amber';
            break;

        case 'angemeldet':
            $speech       = __('Gebucht! TB erstellen', 'custom-crm');
            $title        = __('▶ Wizard: Kunde angemeldet / gebucht – Wizard für Teilnahmebestätigung (TB) öffnen', 'custom-crm');
            $stage        = 'enrolled';
            $theme_class  = 'theme-emerald';
            break;

        case 'teilnahmebestaetigung_gesendet':
            $speech       = __('TB raus! Diplom?', 'custom-crm');
            $title        = __('▶ Wizard: TB versendet – Wizard für Diplom & Zertifikat öffnen', 'custom-crm');
            $stage        = 'diploma';
            $theme_class  = 'theme-amber';
            break;

        case 'diplom_gesendet':
            $speech       = __('Diplom ist versendet!', 'custom-crm');
            $title        = __('▶ Wizard: Diplom versendet – Verlauf & Dokumente einsehen', 'custom-crm');
            $stage        = 'done';
            $theme_class  = 'theme-slate';
            break;

        case 'abgeschlossen':
            $speech       = __('Erfolgreich abgeschlossen!', 'custom-crm');
            $title        = __('▶ Wizard: Abgeschlossen – Verlauf & Dokumente im Wizard einsehen', 'custom-crm');
            $stage        = 'done';
            $theme_class  = 'theme-slate';
            break;

        case 'storniert':
            $speech       = __('Status: Storniert', 'custom-crm');
            $title        = __('▶ Wizard: Storniert – Wizard zur Ansicht oder Reaktivierung öffnen', 'custom-crm');
            $stage        = 'storno';
            $theme_class  = 'theme-red';
            break;

        case 'neu':
        default:
            $speech       = $is_foerderung ? __('Bereit für Angebot & KB', 'custom-crm') : __('Bereit für Angebot!', 'custom-crm');
            $title        = $is_foerderung ? __('▶ Wizard: Vorbereitung für Angebot & KB im Wizard starten', 'custom-crm') : __('▶ Wizard: Vorbereitung für Angebot im Wizard starten', 'custom-crm');
            $stage        = 'offer';
            $theme_class  = 'theme-purple';
            break;
    }

    ob_start();
    ?>
    <div class="crm-actions-wrap" data-entry-id="<?php echo esc_attr($entry_id); ?>">
        <div class="crm-primary-actions">
            <!-- Modern Action Button with Play Icon ▶ and Speech Bubble -->
            <button type="button"
                class="crm-wizard-character-btn crm-wizard-icon-btn crm-run-wizard-btn crm-run-friedelin-btn <?php echo esc_attr($theme_class); ?> <?php echo ($is_ready ? 'is-ready' : ''); ?>"
                data-entry-id="<?php echo esc_attr($entry_id); ?>"
                data-course-id="<?php echo esc_attr($course_id); ?>"
                data-stage="<?php echo esc_attr($stage); ?>"
                title="<?php echo esc_attr($title); ?>"
                aria-label="<?php echo esc_attr($title); ?>">
                <span class="crm-wizard-figure crm-play-figure" title="<?php esc_attr_e('Vorgang starten', 'custom-crm'); ?>">
                    <svg viewBox="0 0 24 24" width="11" height="11" fill="currentColor" style="display:block; margin-left:2px;"><path d="M8 5.14v13.72a1 1 0 0 0 1.54.84l11-6.86a1 1 0 0 0 0-1.68l-11-6.86A1 1 0 0 0 8 5.14z"/></svg>
                </span>
                <span class="crm-wizard-speech-bubble">
                    <span class="crm-bubble-text"><?php echo esc_html($speech); ?></span>
                </span>
                <?php if ($is_ready): ?>
                    <span class="crm-wizard-ready-indicator" title="<?php esc_attr_e('Fertig vorbereitet für Versand', 'custom-crm'); ?>"></span>
                <?php endif; ?>
            </button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * AJAX Handler: Schaltet AMS oder WAFF Förderung für einen CRM-Eintrag um.
 */
add_action('wp_ajax_crm_toggle_foerderung', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Nicht autorisierter Zugriff.', 'custom-crm')]);
    }
    if (!check_ajax_referer('crm_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen.', 'custom-crm')]);
    }

    $entry_id   = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
    $course_id  = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
    $type       = isset($_POST['type']) ? sanitize_key($_POST['type']) : '';
    $active     = !empty($_POST['active']);
    $status_key = isset($_POST['status_key']) ? sanitize_key($_POST['status_key']) : 'neu';

    if (!$entry_id || !in_array($type, ['ams', 'waff'], true)) {
        wp_send_json_error(['message' => __('Ungültige Parameter.', 'custom-crm')]);
    }

    $updated = crm_set_entry_foerderung($entry_id, $type, $active);
    $is_foerderung = (!empty($updated['ams']) || !empty($updated['waff']));

    $actions_html = crm_render_entry_actions($entry_id, $course_id, $status_key, $is_foerderung);
    $badges_html  = crm_render_foerderung_badges($entry_id, $course_id, $updated);

    $label = ($type === 'ams') ? 'AMS' : 'WAFF';
    $msg = $active
        ? sprintf(__('%s-Förderung aktiviert (Angebot & KB als Standard).', 'custom-crm'), $label)
        : sprintf(__('%s-Förderung deaktiviert.', 'custom-crm'), $label);

    wp_send_json_success([
        'message'       => $msg,
        'entry_id'      => $entry_id,
        'type'          => $type,
        'active'        => $active,
        'foerderung'    => $updated,
        'is_foerderung' => $is_foerderung,
        'actions_html'  => $actions_html,
        'badges_html'   => $badges_html,
    ]);
});

/**
 * AJAX Handler: Lädt die strukturierten Daten für den Angebots- & Vorbereitungs-Wizard.
 */
add_action('wp_ajax_crm_get_wizard_data', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Nicht autorisierter Zugriff.', 'custom-crm')]);
    }
    if (!check_ajax_referer('crm_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen.', 'custom-crm')]);
    }

    $entry_id = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
    if (!$entry_id) {
        wp_send_json_error(['message' => __('Ungültige Eintrags-ID.', 'custom-crm')]);
    }

    $analysis = function_exists('crm_friedelin_analyze_entry') ? crm_friedelin_analyze_entry($entry_id) : [];
    if (empty($analysis['success'])) {
        wp_send_json_error(['message' => __('Anfrage-Daten konnten nicht analysiert werden.', 'custom-crm')]);
    }

    $foerderung = function_exists('crm_get_entry_foerderung') ? crm_get_entry_foerderung($entry_id) : ['ams' => false, 'waff' => false];
    $saved_statuses = function_exists('crm_get_entries_statuses') ? crm_get_entries_statuses([$entry_id]) : [];
    $entry_status = $saved_statuses[$entry_id] ?? null;
    $status_key = $entry_status ? $entry_status['status_key'] : 'neu';
    $status_label = $entry_status ? $entry_status['status_label'] : 'Neu / Anfrage';

    $is_prepared = in_array($status_key, ['versand_vorbereitet', 'ki_vorbereitet', 'ai_prepared'], true);
    $draft = function_exists('crm_friedelin_get_entry_draft') ? crm_friedelin_get_entry_draft($entry_id) : null;

    $course_id = $analysis['course_id'];
    $start_date_raw = '';
    $end_date_raw = '';
    if (!empty($entry_status['course_start_date'])) {
        $start_date_raw = $entry_status['course_start_date'];
        $end_date_raw = $entry_status['course_end_date'] ?? '';
    } elseif ($course_id) {
        $start_date_raw = get_post_meta($course_id, 'start_datum', true);
        $end_date_raw = get_post_meta($course_id, 'end_datum', true);
    }

    $dates_text = 'Termine n. V.';
    if (!empty($start_date_raw) && !empty($end_date_raw)) {
        $dates_text = date_i18n('d.m.Y', strtotime($start_date_raw)) . ' – ' . date_i18n('d.m.Y', strtotime($end_date_raw));
    } elseif (!empty($start_date_raw)) {
        $dates_text = 'Ab ' . date_i18n('d.m.Y', strtotime($start_date_raw));
    }

    $default_test_email = get_option('crm_test_email') ?: (get_option('crm_general_settings', [])['test_email'] ?? get_option('crm_test_email_address', get_option('admin_email', 'gajo@x-sieben.at')));

    // Get full history timeline for this entry
    $raw_history = function_exists('crm_get_entry_history') ? crm_get_entry_history($entry_id) : [];
    $formatted_history = [];
    foreach ($raw_history as $h) {
        $user_name = '';
        if (!empty($h['created_by'])) {
            $u = get_userdata($h['created_by']);
            if ($u) {
                $user_name = $u->display_name;
            }
        }
        $formatted_history[] = [
            'id'           => $h['id'] ?? 0,
            'status_key'   => $h['status_key'],
            'status_label' => $h['status_label'],
            'status_date'  => date_i18n('d.m.Y, H:i', strtotime($h['status_date'])),
            'raw_date'     => $h['status_date'],
            'note'         => $h['note'] ?? '',
            'created_by'   => $h['created_by'] ?? 0,
            'user_name'    => $user_name,
        ];
    }

    // Determine workflow stage based on status & Verlauf
    $stage = 'offer';
    if (in_array($status_key, ['versand_vorbereitet', 'ki_vorbereitet', 'ai_prepared', 'neu', 'in_bearbeitung', 'angebot_erstellt'], true)) {
        $stage = 'offer';
    } elseif (in_array($status_key, ['angebot_gesendet', 'angebot_und_kurszeiten_gesendet', 'kurszeitenbestaetigung_gesendet', 'nachfassen'], true)) {
        $stage = 'followup';
    } elseif ($status_key === 'angemeldet') {
        $stage = 'enrolled';
    } elseif (in_array($status_key, ['teilnahmebestaetigung_gesendet', 'diplom_gesendet', 'abgeschlossen'], true)) {
        $stage = 'diploma';
    } elseif ($status_key === 'storniert') {
        $stage = 'storno';
    }

    // Resolve Certification option & AGB
    $resolved_cert = ($course_id && function_exists('crm_resolve_course_certification')) ? crm_resolve_course_certification($entry_id, $course_id) : [];
    $has_cert_option = !empty($resolved_cert);
    $cert_name = $has_cert_option ? $resolved_cert[0]['name'] : '';

    $agb_url = function_exists('crm_get_setting') ? crm_get_setting('legal_agb_url') : '';
    if (empty($agb_url)) {
        $agb_url = 'https://x-sieben.at/wp-content/uploads/2025/09/AGB_X_SIEBEN_2025.pdf';
    }

    // Ensure draft always contains the authoritative, standardized X-SIEBEN offer email
    $is_ams = !empty($foerderung['ams']) || !empty($foerderung['waff']);
    $draft_body = !empty($draft['body']) ? $draft['body'] : '';
    $is_corrupt_body = (empty($draft_body) || strpos($draft_body, 'Vorgang:') === 0 || strpos($draft_body, '<table') === false);

    if (empty($draft) || $is_corrupt_body) {
        if ($course_id && function_exists('crm_build_standard_offer_email')) {
            require_once __DIR__ . '/crm-email-sections.php';
            $sel_docs = (!empty($draft['selected_docs']) && is_array($draft['selected_docs']))
                ? $draft['selected_docs']
                : [
                    'offer_1' => true,
                    'offer_2' => $has_cert_option,
                    'kb'      => $is_ams,
                    'agb'     => true,
                ];

            $std_mail = crm_build_standard_offer_email($entry_id, $course_id, [
                'is_ams_funding'  => $is_ams,
                'has_cert_option' => $has_cert_option,
                'cert_name'       => $cert_name,
                'selected_docs'   => $sel_docs,
            ]);

            if (empty($draft)) {
                $draft = [
                    'entry_id'        => $entry_id,
                    'course_id'       => $course_id,
                    'recipient'       => $analysis['email'] ?? '',
                    'subject'         => $std_mail['subject'],
                    'body'            => $std_mail['body'],
                    'pdf_urls'        => [],
                    'primary_pdf_url' => '',
                    'all_pdf_param'   => '',
                    'context'         => $is_ams ? 'xsieben_angebot_und_kurszeiten' : 'xsieben_angebot',
                    'prepared_at'     => current_time('mysql'),
                    'selected_docs'   => $sel_docs,
                ];
            } else {
                $draft['subject']       = $std_mail['subject'];
                $draft['body']          = $std_mail['body'];
                $draft['selected_docs'] = $sel_docs;
                update_option('crm_friedelin_draft_' . $entry_id, $draft);
            }
        }
    }

    if ($draft && !empty($draft['body']) && function_exists('crm_strip_internal_ai_notices')) {
        $draft['body'] = crm_strip_internal_ai_notices($draft['body']);
    }

    wp_send_json_success([
        'entry_id'            => $entry_id,
        'client_display_name' => $analysis['salutation'] ?? ($analysis['vorname'] . ' ' . $analysis['nachname']),
        'vorname'             => $analysis['vorname'] ?? '',
        'nachname'            => $analysis['nachname'] ?? '',
        'titel'               => $analysis['titel'] ?? '',
        'anrede'              => $analysis['anrede'] ?? '',
        'salutation'          => $analysis['salutation'] ?? '',
        'email'               => $analysis['email'] ?? '',
        'svr'                 => $analysis['svr'] ?? '',
        'company'             => $analysis['company'] ?? '',
        'course_id'           => $course_id,
        'course_title'        => $analysis['course_title'] ?? 'Kein Kurs zugeordnet',
        'dates_text'          => $dates_text,
        'foerderung'          => $foerderung,
        'status_key'          => $status_key,
        'status_label'        => $status_label,
        'stage'               => $stage,
        'is_prepared'         => $is_prepared,
        'draft'               => $draft,
        'history'             => $formatted_history,
        'history_count'       => count($formatted_history),
        'default_test_email'  => $default_test_email,
        'has_cert_option'     => $has_cert_option,
        'cert_name'           => $cert_name,
        'agb_url'             => $agb_url,
    ]);
});

/**
 * AJAX: Dynamically generate or refresh the authoritative X-SIEBEN offer email
 * based on the currently selected documents in the wizard.
 */
add_action('wp_ajax_crm_get_wizard_email_preview', function () {
    check_ajax_referer('crm_ajax_nonce', 'nonce');
    $entry_id = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
    $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
    $selected_docs = isset($_POST['selected_docs']) ? json_decode(stripslashes($_POST['selected_docs']), true) : [];

    require_once __DIR__ . '/crm-email-sections.php';
    $foerderung = function_exists('crm_get_entry_foerderung') ? crm_get_entry_foerderung($entry_id) : ['ams' => false, 'waff' => false];
    $is_ams = !empty($foerderung['ams']) || !empty($foerderung['waff']) || !empty($selected_docs['kb']);

    $resolved_cert = ($course_id && function_exists('crm_resolve_course_certification')) ? crm_resolve_course_certification($entry_id, $course_id) : [];
    $has_cert_option = !empty($resolved_cert);
    $cert_name = $has_cert_option ? $resolved_cert[0]['name'] : '';

    $mail_data = crm_build_standard_offer_email($entry_id, $course_id, [
        'is_ams_funding'  => $is_ams,
        'has_cert_option' => $has_cert_option,
        'cert_name'       => $cert_name,
        'selected_docs'   => $selected_docs,
    ]);

    wp_send_json_success([
        'subject' => $mail_data['subject'],
        'body'    => $mail_data['body'],
    ]);
});

/**
 * AJAX Handler: Speichert geänderte WPForms-Formulardaten eines Eintrags inline aus der CRM-Tabelle.
 */
add_action('wp_ajax_crm_save_entry_form_data', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Nicht autorisierter Zugriff.', 'custom-crm')]);
    }
    if (!check_ajax_referer('crm_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen.', 'custom-crm')]);
    }

    global $wpdb;
    $entry_id   = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
    $course_id  = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
    $status_key = isset($_POST['status_key']) ? sanitize_key($_POST['status_key']) : 'neu';

    if (!$entry_id) {
        wp_send_json_error(['message' => __('Ungültige Entry-ID.', 'custom-crm')]);
    }

    // Eingabefelder sanitizen
    $anrede       = isset($_POST['field_anrede']) ? sanitize_text_field(wp_unslash($_POST['field_anrede'])) : '';
    $titel        = isset($_POST['field_titel']) ? sanitize_text_field(wp_unslash($_POST['field_titel'])) : '';
    $vorname      = isset($_POST['field_vorname']) ? sanitize_text_field(wp_unslash($_POST['field_vorname'])) : '';
    $nachname     = isset($_POST['field_nachname']) ? sanitize_text_field(wp_unslash($_POST['field_nachname'])) : '';
    $email        = isset($_POST['field_email']) ? sanitize_email(wp_unslash($_POST['field_email'])) : '';
    $phone        = isset($_POST['field_phone']) ? sanitize_text_field(wp_unslash($_POST['field_phone'])) : '';
    $company      = isset($_POST['field_company']) ? sanitize_text_field(wp_unslash($_POST['field_company'])) : '';
    $street       = isset($_POST['field_street']) ? sanitize_text_field(wp_unslash($_POST['field_street'])) : '';
    $zip          = isset($_POST['field_zip']) ? sanitize_text_field(wp_unslash($_POST['field_zip'])) : '';
    $city         = isset($_POST['field_city']) ? sanitize_text_field(wp_unslash($_POST['field_city'])) : '';
    $country      = isset($_POST['field_country']) ? sanitize_text_field(wp_unslash($_POST['field_country'])) : 'Österreich';
    $svr          = isset($_POST['field_svr']) ? sanitize_text_field(wp_unslash($_POST['field_svr'])) : '';
    $foerder_sel  = isset($_POST['field_foerderung_select']) ? sanitize_key($_POST['field_foerderung_select']) : 'none';
    $course_title = isset($_POST['field_course_title']) ? sanitize_text_field(wp_unslash($_POST['field_course_title'])) : '';
    $message      = isset($_POST['field_message']) ? sanitize_textarea_field(wp_unslash($_POST['field_message'])) : '';

    $table_entries = $wpdb->prefix . 'wpforms_entries';
    $entry_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_entries WHERE entry_id = %d", $entry_id), ARRAY_A);
    if (!$entry_row) {
        wp_send_json_error(['message' => __('Eintrag in der Datenbank nicht gefunden.', 'custom-crm')]);
    }

    $fields = !empty($entry_row['fields']) ? json_decode($entry_row['fields'], true) : [];
    if (!is_array($fields)) {
        $fields = [];
    }

    // 1. Direct update for ALL dynamic WPForms fields by their field ID
    if (isset($_POST['wpforms_fields']) && is_array($_POST['wpforms_fields'])) {
        foreach ($_POST['wpforms_fields'] as $posted_fid => $posted_fval) {
            $posted_fid = (int)$posted_fid;
            $posted_fval = is_array($posted_fval) ? array_map('sanitize_text_field', wp_unslash($posted_fval)) : sanitize_textarea_field(wp_unslash($posted_fval));
            foreach ($fields as &$f) {
                if (isset($f['id']) && (int)$f['id'] === $posted_fid) {
                    $f['value'] = $posted_fval;
                    break;
                }
            }
            unset($f);
        }
    }

    // Helper to update or insert a field in $fields
    $update_field = function (&$fields_arr, $field_keys, $new_val, $default_name = '', $default_id = null) {
        if (!is_array($field_keys)) {
            $field_keys = [$field_keys];
        }
        $is_country_search = in_array('land', $field_keys, true) || in_array('country', $field_keys, true) || in_array('staat', $field_keys, true);
        $found = false;
        foreach ($fields_arr as $k => &$f) {
            $fid = isset($f['id']) ? (int)$f['id'] : null;
            $fname = isset($f['name']) ? mb_strtolower(trim((string)$f['name']), 'UTF-8') : '';

            // Never match Förderstelle fields when searching for country!
            if ($is_country_search) {
                if (mb_strpos($fname, 'förder') !== false || mb_strpos($fname, 'foerder') !== false || mb_strpos($fname, 'stelle') !== false || mb_strpos($fname, 'angebot') !== false) {
                    continue;
                }
            }

            foreach ($field_keys as $key_pattern) {
                if (is_int($key_pattern) && $fid === $key_pattern) {
                    $f['value'] = $new_val;
                    $found = true;
                    break 2;
                } elseif (is_string($key_pattern)) {
                    $kp = mb_strtolower($key_pattern, 'UTF-8');
                    $is_match = ($fname === $kp);
                    if (!$is_match && mb_strlen($kp) >= 4) {
                        if ($kp === 'land') {
                            $is_match = in_array($fname, ['land', 'country', 'staat', 'herkunftsland', 'wohnsitzland'], true);
                        } else {
                            $is_match = (mb_strpos($fname, $kp) !== false);
                        }
                    }
                    if ($is_match) {
                        $f['value'] = $new_val;
                        $found = true;
                        break 2;
                    }
                }
            }
        }
        unset($f);

        if (!$found && $default_name !== '') {
            $new_id = $default_id ?: (count($fields_arr) + 100);
            $fields_arr[] = [
                'id'    => $new_id,
                'name'  => $default_name,
                'value' => $new_val,
                'type'  => 'text',
            ];
        }
    };

    $update_field($fields, [88, 'anrede'], $anrede, 'Anrede', 88);
    $update_field($fields, [90, 'titel', 'akad. titel'], $titel, 'Titel', 90);
    $update_field($fields, [86, 'vorname', 'first name'], $vorname, 'Vorname', 86);
    $update_field($fields, [89, 'nachname', 'last name'], $nachname, 'Nachname', 89);
    $update_field($fields, [93, 'e-mail', 'email'], $email, 'E-Mail', 93);
    $update_field($fields, ['telefon', 'phone', 'tel'], $phone, 'Telefon');
    $update_field($fields, [25, 'firma', 'company', 'unternehmen'], $company, 'Firma', 25);

    // Check if WPForms composite address field exists
    $addr_found = false;
    foreach ($fields as &$f) {
        if ((isset($f['type']) && $f['type'] === 'address') || (isset($f['id']) && (int)$f['id'] === 35) || isset($f['address1'])) {
            $addr_found = true;
            $f['address1'] = $street;
            if (!isset($f['address2'])) $f['address2'] = '';
            $f['postal'] = $zip;
            $f['city'] = $city;
            $f['country'] = ($country === 'Österreich' || $country === 'Austria') ? 'AT' : $country;
            $val_parts = array_filter([$street, $city, $zip, $f['country']], function($p) { return trim((string)$p) !== ''; });
            $f['value'] = implode("\n", $val_parts);
            $f['value_raw'] = $f['value'];
            break;
        }
    }
    unset($f);

    // Fallback if separate fields are used instead of composite address field
    if (!$addr_found) {
        $update_field($fields, ['straße', 'street', 'adresse', 'anschrift'], $street, 'Straße');
        $update_field($fields, ['plz', 'zip', 'postleitzahl'], $zip, 'PLZ');
        $update_field($fields, ['ort', 'city', 'stadt'], $city, 'Ort');
        $update_field($fields, ['land', 'country', 'staat'], $country, 'Land');
    }

    $update_field($fields, [29, 'sv. nr', 'sv-nr', 'svr', 'sozialversicherungsnummer'], $svr, 'SV. Nr. (Optional für AMS Förderantrag)', 29);
    $update_field($fields, ['nachricht', 'freitext', 'anmerkung', 'ihre nachricht'], $message, 'Nachricht / Freitext');
    if ($course_title) {
        $update_field($fields, ['verborgenes feld', 'kurstitel', 'kurs'], $course_title, 'Verborgenes Feld');
    }

    // Zertifizierungen Auswahl (Feld 99)
    if (isset($_POST['field_zertifizierungen'])) {
        $posted_certs = is_array($_POST['field_zertifizierungen'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['field_zertifizierungen']))
            : array_filter(array_map('trim', explode("\n", sanitize_textarea_field(wp_unslash($_POST['field_zertifizierungen'])))));
        $certs_val = implode("\n", $posted_certs);
        $update_field($fields, [99, 'zertifizierungen auswahl', 'zertifizierungen'], $certs_val, 'Zertifizierungen Auswahl', 99);
    } else {
        // Leere Zertifikatsauswahl bei Formular-Submit persistieren
        $update_field($fields, [99, 'zertifizierungen auswahl', 'zertifizierungen'], '', 'Zertifizierungen Auswahl', 99);
    }

    // Abschluss Erfolg (Feld 100)
    if (isset($_POST['field_abschluss_erfolg'])) {
        $erfolg_val = sanitize_text_field(wp_unslash($_POST['field_abschluss_erfolg']));
        $update_field($fields, [100, 'abschluss erfolg', 'erfolg', 'abschluss'], $erfolg_val, 'Abschluss Erfolg', 100);
    }

    // Save JSON back to database
    $updated_json = wp_json_encode($fields, JSON_UNESCAPED_UNICODE);
    $wpdb->update($table_entries, ['fields' => $updated_json], ['entry_id' => $entry_id]);

    // Also synchronize individual field index rows in wp_wpforms_entry_fields for consistency
    $table_entry_fields = $wpdb->prefix . 'wpforms_entry_fields';
    foreach ($fields as $f) {
        if (isset($f['id']) && isset($f['value'])) {
            $val_str = is_array($f['value']) ? implode(', ', $f['value']) : (string)$f['value'];
            $wpdb->update(
                $table_entry_fields,
                ['value' => $val_str],
                ['entry_id' => $entry_id, 'field_id' => (int)$f['id']]
            );
        }
    }

    // Förderstatus synchronisieren
    if ($foerder_sel === 'ams') {
        crm_set_entry_foerderung($entry_id, 'ams', true);
        crm_set_entry_foerderung($entry_id, 'waff', false);
    } elseif ($foerder_sel === 'waff') {
        crm_set_entry_foerderung($entry_id, 'waff', true);
        crm_set_entry_foerderung($entry_id, 'ams', false);
    } else {
        crm_set_entry_foerderung($entry_id, 'ams', false);
        crm_set_entry_foerderung($entry_id, 'waff', false);
    }

    // Course ID ggf. neu zuordnen falls Kurstitel geändert wurde
    if ($course_title && function_exists('find_course_id_by_title_exact')) {
        $found_cid = find_course_id_by_title_exact($course_title);
        if ($found_cid) {
            $course_id = $found_cid;
        }
    }

    // Audit-Trail
    if (function_exists('crm_add_entry_status_history')) {
        $name_parts = array_filter([$anrede, $titel, $vorname, $nachname]);
        $full_name = !empty($name_parts) ? implode(' ', $name_parts) : __('Unbekannter Kunde', 'custom-crm');
        crm_add_entry_status_history(
            $entry_id,
            'form_data_updated',
            __('Formulardaten bearbeitet', 'custom-crm'),
            sprintf(__('Kundendaten inline aktualisiert für %s (%s).', 'custom-crm'), $full_name, $email)
        );
    }

    // Client display name & badges
    $name_parts = array_filter([$anrede, $titel, $vorname, $nachname]);
    $client_display_name = !empty($name_parts) ? implode(' ', $name_parts) : __('Unbekannter Kunde', 'custom-crm');
    $foerderung = crm_get_entry_foerderung($entry_id);
    $is_foerderung = (!empty($foerderung['ams']) || !empty($foerderung['waff']));
    $badges_html = crm_render_foerderung_pure_badges($entry_id, $foerderung);
    $actions_html = crm_render_entry_actions($entry_id, $course_id, $status_key, $is_foerderung);

    $cache_res = function_exists('crm_on_partial_cache_update') ? crm_on_partial_cache_update('form_data_' . $entry_id, $entry_id) : [];

    wp_send_json_success([
        'message'             => sprintf(__('Formulardaten für "%s" erfolgreich gespeichert.', 'custom-crm'), $client_display_name),
        'entry_id'            => $entry_id,
        'course_id'           => $course_id,
        'client_display_name' => $client_display_name,
        'course_title'        => $course_title,
        'email'               => $email,
        'phone'               => $phone,
        'company'             => $company,
        'street'              => $street,
        'zip'                 => $zip,
        'city'                => $city,
        'country'             => $country,
        'svr'                 => $svr,
        'foerder_sel'         => $foerder_sel,
        'first_name'          => $vorname,
        'last_name'           => $nachname,
        'anrede'              => $anrede,
        'titel'               => $titel,
        'message'             => $message,
        'badges_html'         => $badges_html,
        'actions_html'        => $actions_html,
        'is_foerderung'       => $is_foerderung,
        'js_cache'            => $cache_res,
    ]);
});

/**
 * Render the full, responsive customer data edit form for any entry ID.
 *
 * @param int $entry_id WPForms entry ID.
 * @param int $course_id Associated course ID (optional).
 * @return string HTML for the customer data edit form.
 */
function crm_render_customer_edit_form($entry_id, $course_id = 0) {
    global $wpdb;
    $entry_id = absint($entry_id);
    if (!$entry_id) {
        return '';
    }

    $table_entries = $wpdb->prefix . 'wpforms_entries';
    $entry_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_entries WHERE entry_id = %d", $entry_id), ARRAY_A);
    if (!$entry_row) {
        return '<p class="crm-error-msg">' . esc_html__('Eintrag nicht gefunden.', 'custom-crm') . '</p>';
    }

    $fields = !empty($entry_row['fields']) ? json_decode($entry_row['fields'], true) : [];
    if (!is_array($fields)) {
        $fields = [];
    }

    // Status & Förderung abrufen
    $status_data = function_exists('crm_get_entry_status') ? crm_get_entry_status($entry_id) : [];
    if (!$course_id && !empty($status_data['course_id'])) {
        $course_id = absint($status_data['course_id']);
    }
    $foerderung = function_exists('crm_get_entry_foerderung') ? crm_get_entry_foerderung($entry_id) : ['ams' => false, 'waff' => false];
    $foerder_choice = 'none';
    if (!empty($foerderung['ams'])) {
        $foerder_choice = 'ams';
    } elseif (!empty($foerderung['waff'])) {
        $foerder_choice = 'waff';
    }

    // Field extractor helper
    $get_field_meta = function($fields_arr, $name_cands, $id_cands = [], $exclude_terms = []) {
        if (!is_array($name_cands)) $name_cands = [$name_cands];
        if (!is_array($id_cands)) $id_cands = [$id_cands];
        if (!is_array($exclude_terms)) $exclude_terms = [$exclude_terms];
        if (!is_array($fields_arr)) return ['id' => null, 'val' => ''];

        // Pass 1: ID Match
        if (!empty($id_cands)) {
            foreach ($fields_arr as $f) {
                $fid = isset($f['id']) ? (int)$f['id'] : null;
                if ($fid && in_array($fid, $id_cands, true)) {
                    $val = is_array($f['value'] ?? '') ? implode(', ', $f['value']) : (string)($f['value'] ?? '');
                    if ($val === '' && !empty($f['first'])) {
                        $val = (string)$f['first'];
                    }
                    return ['id' => $fid, 'val' => $val];
                }
            }
        }

        // Pass 2: Name match with exclusion filter
        foreach ($fields_arr as $f) {
            $fid = isset($f['id']) ? (int)$f['id'] : null;
            $fname = isset($f['name']) ? mb_strtolower(trim((string)$f['name']), 'UTF-8') : '';

            $is_excluded = false;
            foreach ($exclude_terms as $ex) {
                if ($ex !== '' && mb_strpos($fname, mb_strtolower($ex, 'UTF-8')) !== false) {
                    $is_excluded = true;
                    break;
                }
            }
            if ($is_excluded) continue;

            foreach ($name_cands as $cand) {
                $cand_l = mb_strtolower(trim((string)$cand), 'UTF-8');
                $is_match = ($fname === $cand_l);
                if (!$is_match && mb_strlen($cand_l) >= 4) {
                    if ($cand_l === 'land') {
                        $is_match = in_array($fname, ['land', 'country', 'staat', 'herkunftsland', 'wohnsitzland'], true);
                    } else {
                        $is_match = (mb_strpos($fname, $cand_l) !== false);
                    }
                }
                if ($is_match) {
                    $val = is_array($f['value'] ?? '') ? implode(', ', $f['value']) : (string)($f['value'] ?? '');
                    if ($val === '' && !empty($f['first'])) {
                        $val = (string)$f['first'];
                    }
                    return ['id' => $fid, 'val' => $val];
                }
            }
        }
        return ['id' => null, 'val' => ''];
    };

    // Prüfen auf WPForms Composite Address Field
    $addr_field = null;
    foreach ($fields as $f) {
        if ((isset($f['type']) && $f['type'] === 'address') || (isset($f['id']) && (int)$f['id'] === 35) || isset($f['address1'])) {
            $addr_field = $f;
            break;
        }
    }

    $m_anrede   = $get_field_meta($fields, ['anrede'], [88]);
    $m_titel    = $get_field_meta($fields, ['titel', 'akad'], [90]);
    $m_vorname  = $get_field_meta($fields, ['vorname', 'first name'], [86]);
    $m_nachname = $get_field_meta($fields, ['nachname', 'last name'], [89]);
    $m_email    = $get_field_meta($fields, ['e-mail', 'email'], [93]);
    $m_phone    = $get_field_meta($fields, ['telefon', 'phone', 'tel', 'mobil']);
    $m_company  = $get_field_meta($fields, ['firma', 'company', 'unternehmen'], [25], ['privat', 'oder unternehmen']);
    $m_street   = $addr_field ? ['id' => $addr_field['id'], 'val' => ($addr_field['address1'] ?? '')] : $get_field_meta($fields, ['straße', 'street', 'adresse', 'anschrift']);
    $m_zip      = $addr_field ? ['id' => $addr_field['id'], 'val' => ($addr_field['postal'] ?? '')] : $get_field_meta($fields, ['plz', 'zip', 'postleitzahl']);
    $m_city     = $addr_field ? ['id' => $addr_field['id'], 'val' => ($addr_field['city'] ?? '')] : $get_field_meta($fields, ['ort', 'city', 'stadt']);
    $m_country  = $addr_field ? ['id' => $addr_field['id'], 'val' => (($addr_field['country'] ?? '') === 'AT' ? 'Österreich' : ($addr_field['country'] ?? ''))] : $get_field_meta($fields, ['land', 'country', 'staat'], [], ['förder', 'foerder', 'stelle', 'angebot']);
    $m_svr      = $get_field_meta($fields, ['sv. nr', 'sv-nr', 'svr', 'sozialversicherung'], [29]);
    $m_course   = $get_field_meta($fields, ['verborgenes feld', 'kurstitel', 'kurs']);
    $m_msg      = $get_field_meta($fields, ['nachricht', 'freitext', 'anmerkung', 'ihre nachricht']);
    $m_certs    = $get_field_meta($fields, ['zertifizierungen', 'zertifizierung', 'zertifizierungen auswahl'], [99]);
    $m_erfolg   = $get_field_meta($fields, ['abschluss erfolg', 'erfolg', 'abschluss'], [100]);

    $anrede_val_dyn   = $m_anrede['val'];
    $titel_val_dyn    = $m_titel['val'];
    $vorname_val_dyn  = $m_vorname['val'];
    $nachname_val_dyn = $m_nachname['val'];
    $email_val_dyn    = $m_email['val'];
    $phone_val_dyn    = $m_phone['val'];
    $company_val_dyn  = $m_company['val'];
    $street_val_dyn   = $m_street['val'];
    $zip_val_dyn      = $m_zip['val'];
    $city_val_dyn     = $m_city['val'];
    $country_val_dyn  = $m_country['val'] ?: 'Österreich';
    $svr_val_dyn      = $m_svr['val'];
    $course_val_dyn   = $m_course['val'] ?: ($status_data['course_title'] ?? '');
    $msg_val_dyn      = $m_msg['val'];

    // Zertifizierungen
    $all_cert_options = [
        'IPMA / pma - Level D Zertifizierung - € 495,00 (10%)',
        'IPMA / pma - Level C Zertifizierung - € 1.210,00 (10%)',
        'IPMA / pma - Level B Zertifizierung - € 2.365,00 (10%)',
        'IPMA / pma - Level B Zertifizierung (Online) - € 1.958,00 (10%)',
        'SystemCERT- Kompetenzzertifizierung FachtrainerIn gemäß den Forderungen der ISO 17024 - € 324,00 (20%)',
        'TÜV - ISO/IEC 17024 Kompetenz-Zertifizierung - € 497,00 (20%)',
        'Scrum.org Zertifizierung - PSM I (USD 200,-) - € 171,50 (0%)',
        'Scrum.org Zertifizierung - PSPO I (USD 200,-) - € 171,50 (0%)',
        'Scrum.org Zertifizierung - PSPO I (USD 200,-) + PSM I (USD 200,-) - € 343,00 (0%)',
        'LOG+L - Kompetenzzertifizierung nach DIN EN ISO 17024 - € 306,00 (20%)',
        'Anrechnung von Modul Gender + Diversity + € 400,00 (20%)',
    ];
    $current_certs_raw = $m_certs['val'];
    $current_selected_certs = [];
    if (!empty($current_certs_raw)) {
        $current_selected_certs = is_array($current_certs_raw) ? $current_certs_raw : array_filter(array_map('trim', explode("\n", (string)$current_certs_raw)));
    }
    foreach ($current_selected_certs as $csc) {
        $csc_clean = trim(html_entity_decode((string)$csc, ENT_QUOTES, 'UTF-8'));
        $already_in = false;
        foreach ($all_cert_options as $aco) {
            $aco_clean = trim(html_entity_decode((string)$aco, ENT_QUOTES, 'UTF-8'));
            if ($csc_clean === $aco_clean || stripos($csc_clean, $aco_clean) !== false) {
                $already_in = true;
                break;
            }
        }
        if (!$already_in && $csc_clean !== '') {
            $all_cert_options[] = $csc;
        }
    }

    // Abschluss-Erfolg
    $all_erfolg_options = [
        'Ausgezeichnetem Erfolg',
        'Gutem Erfolg',
        'Erfolg',
    ];
    $current_erfolg_val = trim((string)$m_erfolg['val']);

    ob_start();
    ?>
    <form class="crm-inline-entry-form crm-universal-customer-form" data-entry-id="<?php echo esc_attr($entry_id); ?>" data-course-id="<?php echo esc_attr($course_id); ?>">
        <div class="inline-edit-wrapper" style="display:flex; flex-direction:column; gap:16px;">
            <div class="crm-quick-3col-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:16px;">
                <!-- Spalte 1: Persönliche Angaben -->
                <fieldset class="inline-edit-col-left" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px;">
                    <legend class="inline-edit-legend" style="font-weight:700; font-size:12.5px; color:#0f172a; padding:0 6px;">
                        👤 <?php esc_html_e('Persönliche Angaben', 'custom-crm'); ?>
                    </legend>
                    <div class="inline-edit-col" style="display:flex; flex-direction:column; gap:10px;">
                        <label style="display:flex; flex-direction:column; gap:3px;">
                            <span class="title" style="font-size:11.5px; font-weight:600; color:#475569;"><?php esc_html_e('Anrede', 'custom-crm'); ?></span>
                            <select name="field_anrede" class="crm-quick-input" style="width:100%;">
                                <option value="" <?php selected($anrede_val_dyn, ''); ?>>-- Bitte wählen --</option>
                                <option value="Herr" <?php selected(stripos($anrede_val_dyn, 'Herr') !== false, true); ?>>Herr</option>
                                <option value="Frau" <?php selected(stripos($anrede_val_dyn, 'Frau') !== false, true); ?>>Frau</option>
                            </select>
                        </label>
                        <label style="display:flex; flex-direction:column; gap:3px;">
                            <span class="title" style="font-size:11.5px; font-weight:600; color:#475569;"><?php esc_html_e('Titel (akad.)', 'custom-crm'); ?></span>
                            <input type="text" name="field_titel" value="<?php echo esc_attr($titel_val_dyn); ?>" class="crm-quick-input" placeholder="z.B. Mag., Dr., BSc" style="width:100%;">
                        </label>
                        <label style="display:flex; flex-direction:column; gap:3px;">
                            <span class="title" style="font-size:11.5px; font-weight:600; color:#475569;"><?php esc_html_e('Vorname *', 'custom-crm'); ?></span>
                            <input type="text" name="field_vorname" value="<?php echo esc_attr($vorname_val_dyn); ?>" class="crm-quick-input" required style="width:100%;">
                        </label>
                        <label style="display:flex; flex-direction:column; gap:3px;">
                            <span class="title" style="font-size:11.5px; font-weight:600; color:#475569;"><?php esc_html_e('Nachname *', 'custom-crm'); ?></span>
                            <input type="text" name="field_nachname" value="<?php echo esc_attr($nachname_val_dyn); ?>" class="crm-quick-input" required style="width:100%;">
                        </label>
                        <label style="display:flex; flex-direction:column; gap:3px;">
                            <span class="title" style="font-size:11.5px; font-weight:600; color:#475569;"><?php esc_html_e('E-Mail *', 'custom-crm'); ?></span>
                            <input type="email" name="field_email" value="<?php echo esc_attr($email_val_dyn); ?>" class="crm-quick-input" required style="width:100%;">
                        </label>
                        <label style="display:flex; flex-direction:column; gap:3px;">
                            <span class="title" style="font-size:11.5px; font-weight:600; color:#475569;"><?php esc_html_e('Telefon', 'custom-crm'); ?></span>
                            <input type="text" name="field_phone" value="<?php echo esc_attr($phone_val_dyn); ?>" class="crm-quick-input" placeholder="+43 ..." style="width:100%;">
                        </label>
                    </div>
                </fieldset>

                <!-- Spalte 2: Anschrift & Firma -->
                <fieldset class="inline-edit-col-center" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px;">
                    <legend class="inline-edit-legend" style="font-weight:700; font-size:12.5px; color:#0f172a; padding:0 6px;">
                        🏢 <?php esc_html_e('Anschrift & Firma', 'custom-crm'); ?>
                    </legend>
                    <div class="inline-edit-col" style="display:flex; flex-direction:column; gap:10px;">
                        <label style="display:flex; flex-direction:column; gap:3px;">
                            <span class="title" style="font-size:11.5px; font-weight:600; color:#475569;"><?php esc_html_e('Firma / Organisation', 'custom-crm'); ?></span>
                            <input type="text" name="field_company" value="<?php echo esc_attr($company_val_dyn); ?>" class="crm-quick-input" placeholder="Firmenname (optional)" style="width:100%;">
                        </label>
                        <label style="display:flex; flex-direction:column; gap:3px;">
                            <span class="title" style="font-size:11.5px; font-weight:600; color:#475569;"><?php esc_html_e('Straße & Hausnr.', 'custom-crm'); ?></span>
                            <input type="text" name="field_street" value="<?php echo esc_attr($street_val_dyn); ?>" class="crm-quick-input" placeholder="Straße 12/3" style="width:100%;">
                        </label>
                        <div class="crm-quick-flex-row" style="display:flex; gap:8px;">
                            <label style="flex:1; display:flex; flex-direction:column; gap:3px;">
                                <span class="title" style="font-size:11.5px; font-weight:600; color:#475569;"><?php esc_html_e('PLZ', 'custom-crm'); ?></span>
                                <input type="text" name="field_zip" value="<?php echo esc_attr($zip_val_dyn); ?>" class="crm-quick-input" placeholder="1010" style="width:100%;">
                            </label>
                            <label style="flex:2; display:flex; flex-direction:column; gap:3px;">
                                <span class="title" style="font-size:11.5px; font-weight:600; color:#475569;"><?php esc_html_e('Ort / Stadt', 'custom-crm'); ?></span>
                                <input type="text" name="field_city" value="<?php echo esc_attr($city_val_dyn); ?>" class="crm-quick-input" placeholder="Wien" style="width:100%;">
                            </label>
                        </div>
                        <label style="display:flex; flex-direction:column; gap:3px;">
                            <span class="title" style="font-size:11.5px; font-weight:600; color:#475569;"><?php esc_html_e('Land', 'custom-crm'); ?></span>
                            <input type="text" name="field_country" value="<?php echo esc_attr($country_val_dyn); ?>" class="crm-quick-input" style="width:100%;">
                        </label>
                    </div>
                </fieldset>

                <!-- Spalte 3: Förderung & Kursbezug -->
                <fieldset class="inline-edit-col-right" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px;">
                    <legend class="inline-edit-legend" style="font-weight:700; font-size:12.5px; color:#0f172a; padding:0 6px;">
                        🏛️ <?php esc_html_e('Förderung & Kursbezug', 'custom-crm'); ?>
                    </legend>
                    <div class="inline-edit-col" style="display:flex; flex-direction:column; gap:10px;">
                        <label style="display:flex; flex-direction:column; gap:3px;">
                            <span class="title" style="font-size:11.5px; font-weight:600; color:#475569;"><?php esc_html_e('SV-Nummer (AMS)', 'custom-crm'); ?></span>
                            <input type="text" name="field_svr" value="<?php echo esc_attr($svr_val_dyn); ?>" class="crm-quick-input" placeholder="z.B. 1234 010190" style="width:100%;">
                        </label>
                        <label style="display:flex; flex-direction:column; gap:3px;">
                            <span class="title" style="font-size:11.5px; font-weight:600; color:#475569;"><?php esc_html_e('Förderstelle', 'custom-crm'); ?></span>
                            <select name="field_foerderung_select" class="crm-quick-input" style="width:100%;">
                                <option value="none" <?php selected($foerder_choice, 'none'); ?>>Keine (Privat/Firma)</option>
                                <option value="ams" <?php selected($foerder_choice, 'ams'); ?>>AMS (Angebot & KB)</option>
                                <option value="waff" <?php selected($foerder_choice, 'waff'); ?>>WAFF (Landesförderung)</option>
                            </select>
                        </label>
                        <label style="display:flex; flex-direction:column; gap:3px;">
                            <span class="title" style="font-size:11.5px; font-weight:600; color:#475569;"><?php esc_html_e('Angefragter Kurstitel', 'custom-crm'); ?></span>
                            <input type="text" name="field_course_title" value="<?php echo esc_attr($course_val_dyn); ?>" class="crm-quick-input" style="width:100%;">
                        </label>
                        <label style="display:flex; flex-direction:column; gap:3px;">
                            <span class="title" style="font-size:11.5px; font-weight:600; color:#475569;"><?php esc_html_e('Nachricht / Freitext', 'custom-crm'); ?></span>
                            <textarea name="field_message" rows="3" class="crm-quick-input" placeholder="Anfrage-Notizen..." style="width:100%; resize:vertical;"><?php echo esc_textarea($msg_val_dyn); ?></textarea>
                        </label>
                    </div>
                </fieldset>
            </div>

            <!-- Fieldset: Zertifizierungen Auswahl (Feld 99) -->
            <fieldset class="inline-edit-col-certs" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:14px;">
                <legend class="inline-edit-legend" style="font-weight:700; font-size:12.5px; color:#0f172a; padding:0 6px;">
                    🎓 <?php esc_html_e('Zertifizierungen Auswahl (Feld 99)', 'custom-crm'); ?>
                </legend>
                <div class="crm-quick-certs-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:8px 14px; max-height:180px; overflow-y:auto; padding:6px;">
                    <?php foreach ($all_cert_options as $cert_opt) : 
                        $is_cert_checked = false;
                        $opt_clean = trim(html_entity_decode((string)$cert_opt, ENT_QUOTES, 'UTF-8'));
                        foreach ($current_selected_certs as $sel_c) {
                            $sel_c_clean = trim(html_entity_decode((string)$sel_c, ENT_QUOTES, 'UTF-8'));
                            if ($sel_c_clean === $opt_clean || stripos($sel_c_clean, $opt_clean) !== false || stripos($opt_clean, $sel_c_clean) !== false) {
                                $is_cert_checked = true;
                                break;
                            }
                        }
                    ?>
                        <label style="display:flex; align-items:flex-start; gap:8px; font-size:12px; line-height:1.4; color:#334155; margin:0; cursor:pointer;">
                            <input type="checkbox" name="field_zertifizierungen[]" value="<?php echo esc_attr($cert_opt); ?>" <?php checked($is_cert_checked); ?> style="margin-top:2px; flex-shrink:0;">
                            <span><?php echo esc_html($cert_opt); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <!-- Fieldset: Abschluss Erfolg (Feld 100) -->
            <fieldset class="inline-edit-col-erfolg" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:14px;">
                <legend class="inline-edit-legend" style="font-weight:700; font-size:12.5px; color:#0f172a; padding:0 6px;">
                    🏆 <?php esc_html_e('Abschluss Erfolg (Feld 100)', 'custom-crm'); ?>
                </legend>
                <div class="crm-quick-erfolg-wrap" style="display:flex; flex-wrap:wrap; gap:20px; padding:4px 6px;">
                    <label style="display:flex; align-items:center; gap:6px; font-size:12.5px; color:#64748b; margin:0; cursor:pointer;">
                        <input type="radio" name="field_abschluss_erfolg" value="" <?php checked(empty($current_erfolg_val)); ?>>
                        <span><em>-- Keine Angabe / Standard --</em></span>
                    </label>
                    <?php foreach ($all_erfolg_options as $erfolg_opt) : 
                        $is_erfolg_checked = false;
                        $erfolg_opt_clean = mb_strtolower(trim($erfolg_opt), 'UTF-8');
                        $current_erfolg_clean = mb_strtolower(trim($current_erfolg_val), 'UTF-8');
                        if ($current_erfolg_clean !== '' && (strpos($current_erfolg_clean, $erfolg_opt_clean) !== false || strpos($erfolg_opt_clean, $current_erfolg_clean) !== false)) {
                            $is_erfolg_checked = true;
                        }
                    ?>
                        <label style="display:flex; align-items:center; gap:6px; font-size:12.5px; color:#1e293b; font-weight:500; margin:0; cursor:pointer;">
                            <input type="radio" name="field_abschluss_erfolg" value="<?php echo esc_attr($erfolg_opt); ?>" <?php checked($is_erfolg_checked); ?>>
                            <span><?php echo esc_html($erfolg_opt); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="submit inline-edit-save" style="display:flex; align-items:center; justify-content:flex-end; gap:10px; padding-top:10px; border-top:1px solid #e2e8f0; margin-top:4px;">
                <button type="button" class="button crm-cancel-quick-edit" data-entry-id="<?php echo esc_attr($entry_id); ?>">
                    <?php esc_html_e('Abbrechen', 'custom-crm'); ?>
                </button>
                <button type="submit" class="button button-primary crm-save-quick-edit-btn" style="display:inline-flex; align-items:center; gap:6px; font-weight:600;">
                    <span class="dashicons dashicons-saved" style="font-size:16px; width:16px; height:16px;"></span>
                    <span><?php esc_html_e('Kundendaten speichern', 'custom-crm'); ?></span>
                </button>
                <span class="spinner crm-quick-edit-spinner" style="float:none; vertical-align:middle; margin:0;"></span>
                <span class="crm-quick-edit-msg" style="display:none; font-size:12.5px; font-weight:600;"></span>
            </div>
        </div>
    </form>
    <?php
    return ob_get_clean();
}

/**
 * AJAX Handler: Get full customer edit form HTML for modal or inline drawer.
 */
add_action('wp_ajax_crm_get_entry_edit_form', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Nicht autorisierter Zugriff.', 'custom-crm')]);
    }
    if (!check_ajax_referer('crm_ajax_nonce', 'nonce', false) && !check_ajax_referer('crm_ajax_nonce', 'security', false)) {
        wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen.', 'custom-crm')]);
    }

    $entry_id  = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
    $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;

    if (!$entry_id) {
        wp_send_json_error(['message' => __('Ungültige Entry-ID.', 'custom-crm')]);
    }

    $form_html = crm_render_customer_edit_form($entry_id, $course_id);
    if (empty($form_html)) {
        wp_send_json_error(['message' => __('Formular konnte nicht geladen werden.', 'custom-crm')]);
    }

    wp_send_json_success([
        'html'      => $form_html,
        'entry_id'  => $entry_id,
        'course_id' => $course_id,
    ]);
});

/**
 * AJAX handler to toggle/update selected certifications for an entry.
 */
add_action('wp_ajax_crm_update_entry_certifications', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Nicht autorisierter Zugriff.', 'custom-crm')]);
    }
    if (!check_ajax_referer('crm_ajax_nonce', 'nonce', false) && !check_ajax_referer('crm_ajax_nonce', 'security', false)) {
        wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen.', 'custom-crm')]);
    }

    $entry_id  = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
    $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
    if (!$entry_id) {
        wp_send_json_error(['message' => __('Ungültige Entry-ID.', 'custom-crm')]);
    }

    global $wpdb;
    $table_entries = $wpdb->prefix . 'wpforms_entries';
    $entry_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_entries WHERE entry_id = %d", $entry_id), ARRAY_A);
    if (!$entry_row) {
        wp_send_json_error(['message' => __('Eintrag nicht gefunden.', 'custom-crm')]);
    }

    $fields = !empty($entry_row['fields']) ? json_decode($entry_row['fields'], true) : [];
    if (!is_array($fields)) {
        $fields = [];
    }

    // Kurs-ID ermitteln falls nicht übergeben
    if (!$course_id) {
        $status_data = function_exists('crm_get_entry_status') ? crm_get_entry_status($entry_id) : [];
        $course_id = !empty($status_data['course_id']) ? absint($status_data['course_id']) : 0;
    }

    $posted_certs = isset($_POST['selected_certs']) && is_array($_POST['selected_certs']) ? $_POST['selected_certs'] : [];

    $available_certs = $course_id ? crm_get_course_available_certifications($course_id, 0) : [];
    $avail_map = [];
    foreach ($available_certs as $ac) {
        $avail_map[mb_strtolower($ac['short_name'], 'UTF-8')] = $ac;
        $avail_map[mb_strtolower($ac['name'], 'UTF-8')] = $ac;
    }

    $formatted_lines = [];
    $selected_names = [];
    foreach ($posted_certs as $c) {
        $raw_name = is_array($c) ? sanitize_text_field($c['name'] ?? '') : sanitize_text_field($c);
        if (empty($raw_name)) continue;

        $name_l = mb_strtolower($raw_name, 'UTF-8');
        $price = '';
        $ust = '20%';

        if (isset($avail_map[$name_l])) {
            $matched = $avail_map[$name_l];
            $clean_name = $matched['short_name'] ?: $matched['name'];
            $price = $matched['price_num'] > 0 ? number_format($matched['price_num'], 2, ',', '.') : (string)($matched['price_raw'] ?? '');
            $ust = $matched['ust'] ?: '20%';
        } else {
            $clean_name = $raw_name;
            if (is_array($c) && !empty($c['price'])) {
                $price = sanitize_text_field($c['price']);
            }
            if (is_array($c) && !empty($c['ust'])) {
                $ust = sanitize_text_field($c['ust']);
            }
        }

        $price_clean = preg_replace('/[^\d,\.]/', '', $price);
        $ust_clean = rtrim($ust, '%') . '%';

        $formatted_line = $clean_name . ' - € ' . $price_clean . ' (' . $ust_clean . ')';
        $formatted_lines[] = $formatted_line;
        $selected_names[] = $clean_name;
    }

    $certs_field_val = implode("\n", $formatted_lines);

    $update_field = function (&$fields_arr, $field_keys, $new_val, $field_name_default = '', $explicit_id = null) {
        $found = false;
        foreach ($fields_arr as &$f) {
            $fid = isset($f['id']) ? (int)$f['id'] : null;
            $fname = isset($f['name']) ? mb_strtolower(trim((string)$f['name']), 'UTF-8') : '';
            if ($explicit_id && $fid === (int)$explicit_id) {
                $f['value'] = $new_val;
                $found = true;
                break;
            }
            foreach ($field_keys as $k) {
                if (is_numeric($k) && $fid === (int)$k) {
                    $f['value'] = $new_val;
                    $found = true;
                    break 2;
                } elseif (is_string($k) && $fname === mb_strtolower(trim($k), 'UTF-8')) {
                    $f['value'] = $new_val;
                    $found = true;
                    break 2;
                }
            }
        }
        unset($f);

        if (!$found && $explicit_id) {
            $fields_arr[] = [
                'id'    => (int)$explicit_id,
                'name'  => $field_name_default ?: 'Zertifizierungen Auswahl',
                'value' => $new_val,
                'type'  => 'text',
            ];
        }
    };

    $update_field($fields, [99, 'zertifizierungen auswahl', 'zertifizierungen'], $certs_field_val, 'Zertifizierungen Auswahl', 99);

    // Save JSON back to wpforms_entries
    $updated_json = wp_json_encode($fields, JSON_UNESCAPED_UNICODE);
    $wpdb->update($table_entries, ['fields' => $updated_json], ['entry_id' => $entry_id]);

    // Synchronize wp_wpforms_entry_fields table
    $table_entry_fields = $wpdb->prefix . 'wpforms_entry_fields';
    $wpdb->update(
        $table_entry_fields,
        ['value' => $certs_field_val],
        ['entry_id' => $entry_id, 'field_id' => 99]
    );

    // Veraltete Angebot 2 PDF-Caches löschen
    $save_dir = function_exists('crm_get_pdf_storage_dir') ? crm_get_pdf_storage_dir() : (get_template_directory() . '/angebote/');
    $matching_old = glob($save_dir . 'A_' . $entry_id . '-*_Angebot_2_*.pdf');
    if (!empty($matching_old)) {
        foreach ($matching_old as $mo) {
            if (file_exists($mo)) @unlink($mo);
        }
    }

    // Audit-Trail
    if (function_exists('crm_add_entry_status_history')) {
        $count = count($selected_names);
        $summary = $count > 0
            ? sprintf(_n('%d Zertifizierung gewählt: %s', '%d Zertifizierungen gewählt: %s', $count, 'custom-crm'), $count, implode(', ', $selected_names))
            : __('Keine Zertifizierungen gewählt (reines Basis-Angebot)', 'custom-crm');
        crm_add_entry_status_history($entry_id, 'certs_updated', 'Zertifizierungen geändert', $summary);
    }

    // Re-query updated available certs
    $updated_available = crm_get_course_available_certifications($course_id, $entry_id);
    $widget_html = crm_render_course_cert_badges($updated_available, 'widget', $entry_id, $course_id);
    $dossier_html = crm_render_course_cert_badges($updated_available, 'dossier', $entry_id, $course_id);
    $compact_html = crm_render_course_cert_badges($updated_available, 'compact', $entry_id, $course_id);

    $has_cert_option = !empty($selected_names);
    $new_offer_zert_url = '';
    if ($has_cert_option && function_exists('xsieben_offer_pdf')) {
        try {
            $new_offer_zert_url = xsieben_offer_pdf($entry_id, $course_id, false, null, 'mit_zertifikat');
        } catch (\Throwable $e) {
            error_log('CRM PDF re-render error on cert update: ' . $e->getMessage());
        }
    }

    $msg = !empty($selected_names)
        ? sprintf(__('Zertifizierungen für #%d aktualisiert (%s)', 'custom-crm'), $entry_id, implode(', ', $selected_names))
        : sprintf(__('Zertifizierungen für #%d abgewählt (nur Basis-Angebot)', 'custom-crm'), $entry_id);

    wp_send_json_success([
        'message'             => $msg,
        'entry_id'            => $entry_id,
        'course_id'           => $course_id,
        'selected_cert_names' => $selected_names,
        'count'               => count($selected_names),
        'has_cert_option'     => $has_cert_option,
        'widget_html'         => $widget_html,
        'dossier_html'        => $dossier_html,
        'compact_html'        => $compact_html,
        'offer_zert_url'      => $new_offer_zert_url,
    ]);
});

// --- AJAX Handlers ---

/**
 * AJAX handler for updating customer status manually.
 */
add_action('wp_ajax_crm_update_entry_status', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized access.', 'custom-crm')]);
    }
    if (!check_ajax_referer('crm_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Security check failed.', 'custom-crm')]);
    }

    $entry_id   = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
    $status_key = isset($_POST['status_key']) ? sanitize_key($_POST['status_key']) : '';
    $note       = isset($_POST['note']) ? sanitize_text_field($_POST['note']) : '';

    if (!$entry_id || !$status_key) {
        wp_send_json_error(['message' => __('Parameter fehlen.', 'custom-crm')]);
    }

    $all_statuses = crm_get_statuses();
    if (!isset($all_statuses[$status_key])) {
        wp_send_json_error(['message' => __('Ungültiger Status.', 'custom-crm')]);
    }

    $now = current_time('mysql');
    $note_text = !empty($note) ? $note : __('Status manuell geändert', 'custom-crm');

    $success = crm_set_entry_status($entry_id, $status_key, $note_text, $now);

    if ($success) {
        $badge_html = crm_render_status_badge($status_key, $all_statuses[$status_key]['label'], $now);
        $date_formatted = date_i18n('d.m.Y, H:i', strtotime($now));

        $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
        if (!$course_id && function_exists('wpforms') && isset(wpforms()->entry)) {
            $entry = wpforms()->entry->get($entry_id);
            if ($entry && !empty($entry->fields)) {
                $fields = is_string($entry->fields) ? json_decode($entry->fields, true) : $entry->fields;
                $course_title = function_exists('get_field_value') ? get_field_value($fields, 'Verborgenes Feld') : '';
                if ($course_title && function_exists('find_course_id_by_title_exact')) {
                    $course_id = find_course_id_by_title_exact($course_title);
                }
            }
        }

        $actions_html = function_exists('crm_render_entry_actions') ? crm_render_entry_actions($entry_id, $course_id, $status_key) : '';
        $card_cta_html = function_exists('crm_render_card_cta')
            ? crm_render_card_cta([
                'entry_id'      => $entry_id,
                'course_id'     => $course_id,
                'status_key'    => $status_key,
                'is_foerderung' => (function_exists('crm_entry_has_foerderung') ? crm_entry_has_foerderung($entry_id) : false)
            ])
            : $actions_html;

        $cache_res = function_exists('crm_on_partial_cache_update')
            ? crm_on_partial_cache_update('status_' . $entry_id, $status_key)
            : [];

        wp_send_json_success([
            'message'        => sprintf(__('Status auf "%s" geändert.', 'custom-crm'), $all_statuses[$status_key]['label']),
            'status_key'     => $status_key,
            'status_label'   => $all_statuses[$status_key]['label'],
            'badge_html'     => $badge_html,
            'date_formatted' => $date_formatted,
            'raw_date'       => $now,
            'actions_html'   => $actions_html,
            'card_cta_html'  => $card_cta_html,
            'js_cache'       => $cache_res,
        ]);
    } else {
        wp_send_json_error(['message' => __('Status konnte nicht gespeichert werden.', 'custom-crm')]);
    }
});

/**
 * AJAX handler for loading the status history timeline of an entry.
 */
add_action('wp_ajax_crm_get_entry_history', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized access.', 'custom-crm')]);
    }
    if (!check_ajax_referer('crm_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Security check failed.', 'custom-crm')]);
    }

    $entry_id = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
    if (!$entry_id) {
        wp_send_json_error(['message' => __('Entry ID fehlt.', 'custom-crm')]);
    }

    $history = crm_get_entry_history($entry_id);

    // Also get initial entry date from WPForms if available
    $entry_date = '';
    if (function_exists('wpforms') && isset(wpforms()->entry)) {
        $entry = wpforms()->entry->get($entry_id);
        if ($entry && !empty($entry->date)) {
            $entry_date = $entry->date;
        }
    }

    ob_start();
    ?>
    <div class="crm-timeline-wrapper" style="padding:10px 5px;">
        <h3 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
            <span>📜 Status-Verlauf & Historie (Entry #<?php echo esc_html($entry_id); ?>)</span>
            <button type="button" class="crm-close-modal button-link" style="font-size:18px; line-height:1; color:#64748b; text-decoration:none;">&times;</button>
        </h3>

        <?php if (empty($history) && empty($entry_date)) : ?>
            <p style="color:#64748b; font-style:italic;"><?php esc_html_e('Noch keine Status-Aktionen aufgezeichnet.', 'custom-crm'); ?></p>
        <?php else : ?>
            <ul class="crm-timeline" style="list-style:none; margin:15px 0 0 10px; padding:0; border-left:2px solid #cbd5e1;">
                <?php foreach ($history as $item) :
                    $date_str = date_i18n('d.m.Y, H:i', strtotime($item['status_date']));
                    $user_info = '';
                    if (!empty($item['created_by'])) {
                        $user = get_userdata($item['created_by']);
                        if ($user) {
                            $user_info = ' von ' . esc_html($user->display_name);
                        }
                    }
                ?>
                    <li style="position:relative; margin-bottom:16px; padding-left:20px;">
                        <span style="position:absolute; left:-7px; top:4px; width:12px; height:12px; border-radius:50%; background:#0284c7; border:2px solid #fff; box-shadow:0 0 0 1px #0284c7;"></span>
                        <div style="font-size:12px; color:#64748b; margin-bottom:2px;">
                            📅 <strong><?php echo esc_html($date_str); ?></strong><?php echo $user_info; ?>
                        </div>
                        <div>
                            <?php echo crm_render_status_badge($item['status_key'], $item['status_label']); ?>
                        </div>
                        <?php if (!empty($item['note'])) : ?>
                            <div style="margin-top:4px; font-size:12px; color:#334155; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; padding:6px 10px;">
                                <?php echo nl2br(esc_html($item['note'])); ?>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>

                <?php if (!empty($entry_date)) : ?>
                    <li style="position:relative; margin-bottom:10px; padding-left:20px;">
                        <span style="position:absolute; left:-7px; top:4px; width:12px; height:12px; border-radius:50%; background:#64748b; border:2px solid #fff; box-shadow:0 0 0 1px #64748b;"></span>
                        <div style="font-size:12px; color:#64748b; margin-bottom:2px;">
                            📅 <strong><?php echo esc_html(date_i18n('d.m.Y, H:i', strtotime($entry_date))); ?></strong>
                        </div>
                        <div>
                            <?php echo crm_render_status_badge('neu', 'Anfrage eingegangen (WPForms)'); ?>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
});

/**
 * AJAX handler for loading the document snapshots archive of an entry.
 */
add_action('wp_ajax_crm_get_entry_snapshots', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized access.', 'custom-crm')]);
    }
    if (!check_ajax_referer('crm_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Security check failed.', 'custom-crm')]);
    }

    $entry_id = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
    if (!$entry_id) {
        wp_send_json_error(['message' => __('Entry ID fehlt.', 'custom-crm')]);
    }

    $count = crm_get_entry_snapshots_count($entry_id);
    $html  = crm_render_snapshots_html($entry_id);

    wp_send_json_success([
        'entry_id' => $entry_id,
        'count'    => $count,
        'html'     => $html,
    ]);
});

/**
 * Saves and freezes course dates with an inquiry entry so historical records remain immutable.
 *
 * @param int $entry_id
 * @param int $course_id
 * @param string $start_date
 * @param string $end_date
 * @param int $form_id
 * @return bool
 */
function crm_save_entry_course_dates($entry_id, $course_id, $start_date = '', $end_date = '', $form_id = null)
{
    global $wpdb;

    $entry_id = absint($entry_id);
    if (!$entry_id) {
        return false;
    }

    $form_id = $form_id ? absint($form_id) : crm_get_default_form_id();

    crm_ensure_status_tables();
    $table_status = $wpdb->prefix . 'crm_entry_status';

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_status WHERE entry_id = %d", $entry_id), ARRAY_A);

    $course_id_val   = $course_id ? absint($course_id) : null;
    $start_date_val  = !empty($start_date) ? sanitize_text_field($start_date) : null;
    $end_date_val    = !empty($end_date) ? sanitize_text_field($end_date) : null;

    if ($row) {
        $update_data = [];
        $update_fmt  = [];

        if (empty($row['course_id']) && $course_id_val) {
            $update_data['course_id'] = $course_id_val;
            $update_fmt[] = '%d';
        }
        if (empty($row['course_start_date']) && $start_date_val) {
            $update_data['course_start_date'] = $start_date_val;
            $update_fmt[] = '%s';
        }
        if (empty($row['course_end_date']) && $end_date_val) {
            $update_data['course_end_date'] = $end_date_val;
            $update_fmt[] = '%s';
        }

        if (!empty($update_data)) {
            $wpdb->update(
                $table_status,
                $update_data,
                ['entry_id' => $entry_id],
                $update_fmt,
                ['%d']
            );
        }
    } else {
        $all_statuses = crm_get_statuses();
        $wpdb->insert(
            $table_status,
            [
                'entry_id'          => $entry_id,
                'form_id'           => $form_id,
                'status_key'        => 'neu',
                'status_label'      => $all_statuses['neu']['label'] ?? 'Neu / Anfrage',
                'status_date'       => current_time('mysql'),
                'course_id'         => $course_id_val,
                'course_start_date' => $start_date_val,
                'course_end_date'   => $end_date_val,
                'note'              => __('Automatisch initialisiert mit Kursdaten-Snapshot', 'custom-crm'),
                'updated_by'        => get_current_user_id() ?: 0,
            ],
            ['%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d']
        );
    }

    return true;
}

/**
 * Retrieves the stored course dates snapshot for an entry.
 *
 * @param int $entry_id
 * @return array|null ['course_id' => int, 'start_date' => string, 'end_date' => string]
 */
function crm_get_entry_course_dates($entry_id)
{
    global $wpdb;

    $entry_id = absint($entry_id);
    if (!$entry_id) {
        return null;
    }

    $table_status = $wpdb->prefix . 'crm_entry_status';

    $row = $wpdb->get_row(
        $wpdb->prepare("SELECT course_id, course_start_date, course_end_date FROM $table_status WHERE entry_id = %d", $entry_id),
        ARRAY_A
    );

    if ($row && (!empty($row['course_start_date']) || !empty($row['course_end_date']))) {
        return [
            'course_id'   => !empty($row['course_id']) ? absint($row['course_id']) : 0,
            'start_date'  => $row['course_start_date'],
            'end_date'    => $row['course_end_date'],
        ];
    }

    return null;
}

/**
 * Automatically snapshot course dates when a new WPForms entry is submitted.
 */
add_action('wpforms_process_complete', function ($fields, $entry, $form_data, $entry_id) {
    $entry_id = absint($entry_id);
    if (!$entry_id) {
        return;
    }

    $default_form_id = crm_get_default_form_id();
    $form_id = isset($form_data['id']) ? absint($form_data['id']) : $default_form_id;
    if ($form_id !== $default_form_id) {
        return;
    }

    $course_title = '';
    $course_id_field = '';

    if (is_array($fields)) {
        foreach ($fields as $field) {
            $name = isset($field['name']) ? trim($field['name']) : '';
            $name_lower = mb_strtolower($name, 'UTF-8');
            $val  = isset($field['value']) ? trim((string)$field['value']) : '';
            if (in_array($name_lower, ['verborgenes feld', 'kurs', 'kurstitel', 'ausgewählter kurs', 'gewählter kurs', 'kursname'], true)) {
                $course_title = $val;
            } elseif (in_array($name_lower, ['kurs id', 'kurs_id', 'course_id', 'course id'], true)) {
                $course_id_field = $val;
            }
        }
    }

    $course_id = 0;
    if ($course_title && function_exists('find_course_id_by_title_exact')) {
        $course_id = find_course_id_by_title_exact($course_title);
    }
    if (!$course_id && $course_id_field && preg_match('/(\d+)/', $course_id_field, $m)) {
        $candidate_id = intval($m[1]);
        if (get_post_type($candidate_id) === 'courses') {
            $course_id = $candidate_id;
        }
    }

    if ($course_id) {
        $start_date = get_post_meta($course_id, 'start_datum', true);
        $end_date   = get_post_meta($course_id, 'end_datum', true);
        crm_save_entry_course_dates($entry_id, $course_id, $start_date, $end_date, $form_id);
    }
}, 10, 4);

/**
 * =========================================================================
 * CRM DOCUMENT SNAPSHOT ENGINE (Revisionssicheres Dokumenten- & Daten-Archiv)
 * =========================================================================
 */

/**
 * Erstellt einen permanenten Dokument- und Daten-Snapshot eines versendeten Dokuments.
 * Kopiert generierte PDFs in ein geschütztes Archivverzeichnis und speichert E-Mail-HTML sowie JSON-Dump.
 *
 * @param array $args
 * @return int|false Snapshot-ID oder false
 */
function crm_create_document_snapshot(array $args)
{
    global $wpdb;

    $entry_id  = absint($args['entry_id'] ?? 0);
    $course_id = absint($args['course_id'] ?? 0);
    if (!$entry_id) {
        return false;
    }

    crm_ensure_status_tables();
    $table = $wpdb->prefix . 'crm_document_snapshots';

    $form_id         = !empty($args['form_id']) ? absint($args['form_id']) : crm_get_default_form_id();
    $doc_type        = sanitize_key($args['doc_type'] ?? 'angebot');
    $status_key      = sanitize_key($args['status_key'] ?? 'angebot_gesendet');
    $recipient       = sanitize_email($args['recipient'] ?? '');
    $sent_targets    = is_array($args['sent_targets'] ?? '') ? implode(', ', array_map('sanitize_email', $args['sent_targets'])) : sanitize_text_field($args['sent_targets'] ?? '');
    $subject         = sanitize_text_field($args['subject'] ?? '');
    $email_body_html = wp_unslash($args['email_body_html'] ?? '');
    $is_test         = !empty($args['is_test']) ? 1 : 0;
    $sent_by         = isset($args['sent_by']) ? absint($args['sent_by']) : (get_current_user_id() ?: 0);
    $sent_at         = !empty($args['sent_at']) ? $args['sent_at'] : current_time('mysql');

    // 1. PDF-Archivierung (in geschützten persistenten Upload-Ordner)
    $archived_filenames = [];
    $archived_filepaths = [];
    $archived_fileurls  = [];

    $attachments = (array) ($args['attachments'] ?? []);
    if (!empty($attachments)) {
        $upload_dir  = wp_upload_dir();
        $archive_dir = $upload_dir['basedir'] . '/crm_archive/' . date('Y/m');
        $archive_url = $upload_dir['baseurl'] . '/crm_archive/' . date('Y/m');

        if (!file_exists($archive_dir)) {
            wp_mkdir_p($archive_dir);
        }

        foreach ($attachments as $att_path) {
            $att_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $att_path);
            if (file_exists($att_path)) {
                $ext = pathinfo($att_path, PATHINFO_EXTENSION);
                $orig_name = pathinfo($att_path, PATHINFO_FILENAME);
                $safe_orig = preg_replace('/[^\p{L}0-9_\-]/u', '_', $orig_name);
                $unique_name = sanitize_file_name("snapshot_{$doc_type}_{$entry_id}_" . date('Ymd_His') . "_{$safe_orig}.{$ext}");
                $target_path = $archive_dir . '/' . $unique_name;

                if (@copy($att_path, $target_path)) {
                    $archived_filenames[] = $unique_name;
                    $archived_filepaths[] = $target_path;
                    $archived_fileurls[]  = $archive_url . '/' . rawurlencode($unique_name);
                }
            }
        }
    }

    $pdf_filename  = !empty($archived_filenames) ? implode(', ', $archived_filenames) : null;
    $pdf_file_path = !empty($archived_filepaths) ? implode(', ', $archived_filepaths) : null;
    $pdf_file_url  = !empty($archived_fileurls) ? implode(', ', $archived_fileurls) : null;

    // 2. Daten-Snapshot JSON generieren
    $data_snapshot = [];
    $crm_model = $args['crm_model'] ?? null;

    if ($crm_model instanceof CRM_Model) {
        $data_snapshot = [
            'entry_id'        => $entry_id,
            'course_id'       => $course_id,
            'kurstitel'       => $crm_model->kurstitel ?? $crm_model->title ?? '',
            'kurstitel_short' => $crm_model->kurstitel_short ?? '',
            'kurstyp'         => $crm_model->kurstyp ?? '',
            'startdatum'      => $crm_model->start_datum ?? '',
            'enddatum'        => $crm_model->end_datum ?? '',
            'uhrzeit'         => $crm_model->uhrzeit ?? '',
            'anzahl_le'       => $crm_model->anzahl_le ?? '',
            'schulungsort'    => $crm_model->schulungsort ?? '',
            'preis_netto'     => $crm_model->preis_netto ?? '',
            'preis_brutto'    => $crm_model->preis_brutto ?? '',
            'le_single'       => $crm_model->le_single ?? '',
            'zertifizierungen'=> $crm_model->zertifizierungen ?? [],
            'form_certifications' => method_exists($crm_model, 'get_certifications_from_form_field') ? $crm_model->get_certifications_from_form_field() : [],
            'trainer'         => $crm_model->trainer ?? '',
            'kunde'           => [
                'anrede'           => $crm_model->anrede ?? '',
                'salutation'       => $crm_model->salutation ?? '',
                'titel'            => $crm_model->titel ?? '',
                'vorname'          => $crm_model->vorname ?? '',
                'nachname'         => $crm_model->nachname ?? '',
                'email'            => $crm_model->email ?? '',
                'customer_company' => $crm_model->customer_company ?? '',
                'customer_type'    => $crm_model->customer_type ?? '',
                'street'           => $crm_model->street ?? '',
                'city'             => $crm_model->city ?? '',
                'zip_code'         => $crm_model->zip_code ?? '',
                'country'          => $crm_model->country ?? 'AT',
                'svr'              => $crm_model->svr ?? '',
            ],
            'captured_at'     => $sent_at,
        ];
    } elseif (!empty($args['data_snapshot']) && is_array($args['data_snapshot'])) {
        $data_snapshot = $args['data_snapshot'];
    }

    $data_snapshot_json = wp_json_encode($data_snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    // 3. In Datenbank einfügen
    $inserted = $wpdb->insert(
        $table,
        [
            'entry_id'           => $entry_id,
            'form_id'            => $form_id,
            'course_id'          => $course_id,
            'doc_type'           => $doc_type,
            'status_key'         => $status_key,
            'recipient'          => $recipient,
            'sent_targets'       => $sent_targets,
            'subject'            => $subject,
            'email_body_html'    => $email_body_html,
            'pdf_filename'       => $pdf_filename,
            'pdf_file_path'      => $pdf_file_path,
            'pdf_file_url'       => $pdf_file_url,
            'data_snapshot_json' => $data_snapshot_json,
            'is_test'            => $is_test,
            'sent_by'            => $sent_by,
            'sent_at'            => $sent_at,
        ],
        ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s']
    );

    return $inserted ? (int)$wpdb->insert_id : false;
}

/**
 * Holt alle Snapshots eines Eintrags chronologisch absteigend.
 *
 * @param int $entry_id
 * @return array
 */
function crm_get_entry_snapshots($entry_id)
{
    global $wpdb;
    $entry_id = absint($entry_id);
    if (!$entry_id) return [];
    $table = $wpdb->prefix . 'crm_document_snapshots';
    return $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table WHERE entry_id = %d ORDER BY sent_at DESC, id DESC", $entry_id),
        ARRAY_A
    );
}

/**
 * Holt einen einzelnen Snapshot anhand seiner ID ab.
 *
 * @param int $snapshot_id
 * @return array|null
 */
function crm_get_snapshot_by_id($snapshot_id)
{
    global $wpdb;
    $snapshot_id = absint($snapshot_id);
    if (!$snapshot_id) return null;
    $table = $wpdb->prefix . 'crm_document_snapshots';
    return $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $snapshot_id),
        ARRAY_A
    );
}

/**
 * Ermittelt die zulässigen Dokumenttypen für einen bestimmten Status-Key.
 * Birkenbihl-Prinzip: Immer nur genau die Dokumente anzeigen, die zum aktuellen Status gehören.
 *
 * @param string $status_key
 * @return array Leeres Array = alle Dokumenttypen erlaubt (z.B. bei abgeschlossen/storniert)
 */
function crm_get_doc_types_for_status(string $status_key): array
{
    $status_key = sanitize_key($status_key);

    switch ($status_key) {
        case 'neu':
        case 'in_bearbeitung':
        case 'versand_vorbereitet':
        case 'ai_prepared':
        case 'ki_vorbereitet':
        case 'angebot_erstellt':
        case 'angebot_gesendet':
        case 'test_mail_gesendet':
        case 'nachfassen':
            return [
                'angebot',
                'xsieben_angebot',
                'kb',
                'xsieben_kurszeitenbestaetigung',
                'angebot_kb',
                'angebot_kurszeiten',
                'xsieben_angebot_kurszeiten',
                'xsieben_angebot_und_kurszeiten',
            ];

        case 'kurszeitenbestaetigung_gesendet':
            return [
                'kb',
                'xsieben_kurszeitenbestaetigung',
                'angebot_kb',
                'angebot_kurszeiten',
                'xsieben_angebot_kurszeiten',
                'xsieben_angebot_und_kurszeiten',
            ];

        case 'angebot_und_kurszeiten_gesendet':
            return [
                'angebot',
                'xsieben_angebot',
                'kb',
                'xsieben_kurszeitenbestaetigung',
                'angebot_kb',
                'angebot_kurszeiten',
                'xsieben_angebot_kurszeiten',
                'xsieben_angebot_und_kurszeiten',
            ];

        case 'angemeldet':
        case 'anmeldung':
            return ['anmeldung'];

        case 'teilnahmebestaetigung_gesendet':
        case 'tb_vorbereitet':
        case 'tb_gesendet':
            return ['tb', 'xsieben_teilnahmebestaetigung'];

        case 'diplom_gesendet':
        case 'diplom_vorbereitet':
            return ['diplom', 'xsieben_diplom'];

        case 'rechnung_gestellt':
        case 'honorarnote':
        case 'hn':
            return ['invoice', 'honorarnote'];

        case 'abgeschlossen':
        case 'storniert':
        default:
            return []; // Leeres Array bedeutet: alle Dokumenttypen erlaubt
    }
}

/**
 * Erzeugt eine benutzerfreundliche Beschriftung für PDF-Dateien im CRM-Archiv.
 *
 * @param string $url_or_name
 * @param int    $idx
 * @return string
 */
function crm_get_friendly_pdf_label(string $url_or_name, int $idx = 0): string
{
    $basename = basename($url_or_name);

    if (stripos($basename, 'Angebot_1_Basis') !== false) {
        return '📄 Angebot 1: Basis (PDF)';
    }
    if (stripos($basename, 'Angebot_2') !== false || stripos($basename, 'inkl_Zertifizierung') !== false || stripos($basename, 'mit_zertifikat') !== false) {
        return '📜 Angebot 2: Inkl. Zertifizierung (PDF)';
    }
    if (stripos($basename, 'Kurszeitenbestaetigung') !== false || stripos($basename, '_KB_') !== false) {
        return '📅 Kurszeitenbestätigung (PDF)';
    }
    if (stripos($basename, 'Teilnahmebestaetigung') !== false) {
        return '🎓 Teilnahmebestätigung (PDF)';
    }
    if (stripos($basename, 'Diplom') !== false) {
        return '🏆 Diplom & Zertifikat (PDF)';
    }
    if (stripos($basename, 'HN_') !== false || stripos($basename, 'Honorarnote') !== false || stripos($basename, 'Rechnung') !== false) {
        return '💶 Honorarnote (PDF)';
    }
    if (preg_match('/^A_\d+/', $basename) || stripos($basename, 'Angebot') !== false) {
        return '📄 Kursangebot (PDF)';
    }

    return '📄 PDF-Dokument ' . ($idx + 1);
}

/**
 * Ermittelt vorbereitete Dokumente (Friedelin-Draft & generierte PDFs) für einen Eintrag.
 *
 * @param int    $entry_id
 * @param string $status_key
 * @return array|null
 */
function crm_get_prepared_documents_for_entry(int $entry_id, string $status_key): ?array
{
    $entry_id = absint($entry_id);
    if (!$entry_id) {
        return null;
    }

    $is_prepared_offer = in_array($status_key, ['versand_vorbereitet', 'ai_prepared', 'ki_vorbereitet', 'angebot_erstellt', 'neu', 'in_bearbeitung', 'test_mail_gesendet'], true);
    
    // 1. Friedelin-Draft prüfen
    $draft = function_exists('crm_friedelin_get_entry_draft') ? crm_friedelin_get_entry_draft($entry_id) : null;

    // Falls versand_vorbereitet und noch kein Draft existiert: automatische Vorbereitung anstoßen
    if ($is_prepared_offer && empty($draft) && function_exists('crm_friedelin_process_entry')) {
        $proc_res = crm_friedelin_process_entry($entry_id);
        if (!empty($proc_res['success'])) {
            $draft = crm_friedelin_get_entry_draft($entry_id);
        }
    }

    $pdf_urls = [];
    if (!empty($draft['pdf_urls']) && is_array($draft['pdf_urls'])) {
        $pdf_urls = array_values(array_filter($draft['pdf_urls']));
    }

    // 2. Fallback: Suche nach bereits generierten PDFs im Upload-Ordner
    if (empty($pdf_urls) && function_exists('crm_get_pdf_storage_dir')) {
        $dir     = crm_get_pdf_storage_dir();
        $baseurl = function_exists('crm_get_pdf_storage_url') ? crm_get_pdf_storage_url() : '';
        if ($is_prepared_offer) {
            $offer_files = glob($dir . 'A_' . $entry_id . '-*.pdf');
            if (!empty($offer_files)) {
                foreach ($offer_files as $f) {
                    $pdf_urls[] = $baseurl . rawurlencode(basename($f));
                }
            }
            $kb_files = glob($dir . 'Kurszeitenbestaetigung_*' . $entry_id . '*.pdf');
            if (!empty($kb_files)) {
                foreach ($kb_files as $f) {
                    $pdf_urls[] = $baseurl . rawurlencode(basename($f));
                }
            }
        }
    }

    if (empty($draft) && empty($pdf_urls)) {
        return null;
    }

    // Daten-Snapshot aus CRM_Model aufbauen
    $status_row = function_exists('crm_get_entry_saved_status') ? crm_get_entry_saved_status($entry_id) : null;
    $course_id  = !empty($draft['course_id']) ? absint($draft['course_id']) : (!empty($status_row['course_id']) ? absint($status_row['course_id']) : 0);

    $data_snapshot = [];
    if ($course_id && class_exists('CRM_Model')) {
        $crm_model = new CRM_Model($course_id, $entry_id);
        $data_snapshot = [
            'entry_id'        => $entry_id,
            'course_id'       => $course_id,
            'kurstitel'       => $crm_model->kurstitel ?? $crm_model->title ?? '',
            'kurstitel_short' => $crm_model->kurstitel_short ?? '',
            'kurstyp'         => $crm_model->kurstyp ?? '',
            'startdatum'      => $crm_model->start_datum ?? '',
            'enddatum'        => $crm_model->end_datum ?? '',
            'uhrzeit'         => $crm_model->uhrzeit ?? '',
            'anzahl_le'       => $crm_model->anzahl_le ?? '',
            'schulungsort'    => $crm_model->schulungsort ?? '',
            'preis_netto'     => $crm_model->preis_netto ?? '',
            'preis_brutto'    => $crm_model->preis_brutto ?? '',
            'le_single'       => $crm_model->le_single ?? '',
            'zertifizierungen'=> $crm_model->zertifizierungen ?? [],
            'trainer'         => strip_tags($crm_model->trainer ?? ''),
            'kunde'           => [
                'anrede'           => $crm_model->anrede ?? '',
                'salutation'       => $crm_model->salutation ?? '',
                'titel'            => $crm_model->titel ?? '',
                'vorname'          => $crm_model->vorname ?? '',
                'nachname'         => $crm_model->nachname ?? '',
                'email'            => $crm_model->email ?? '',
                'customer_company' => $crm_model->customer_company ?? '',
                'customer_type'    => $crm_model->customer_type ?? '',
                'street'           => $crm_model->street ?? '',
                'city'             => $crm_model->city ?? '',
                'zip_code'         => $crm_model->zip_code ?? '',
                'country'          => $crm_model->country ?? 'AT',
                'svr'              => $crm_model->svr ?? '',
            ],
            'status'          => 'Vorbereitet (Entwurf)',
            'captured_at'     => $draft['prepared_at'] ?? current_time('mysql'),
        ];
    }

    $doc_type = !empty($draft['context'])
        ? $draft['context']
        : (!empty($draft['is_ams_funding']) ? 'xsieben_angebot_und_kurszeiten' : 'xsieben_angebot');
    
    $recipient   = !empty($draft['recipient']) ? $draft['recipient'] : ($data_snapshot['kunde']['email'] ?? '');
    $subject     = !empty($draft['subject']) ? $draft['subject'] : ('Angebot: ' . ($data_snapshot['kurstitel'] ?? 'Ihre Weiterbildung'));
    $body        = !empty($draft['body']) ? $draft['body'] : '';
    $prepared_at = !empty($draft['prepared_at']) ? $draft['prepared_at'] : current_time('mysql');
    $ai_summary  = !empty($draft['ai_summary']) ? $draft['ai_summary'] : '';

    return [
        'id'                 => 'prepared-' . $entry_id,
        'entry_id'           => $entry_id,
        'doc_type'           => $doc_type,
        'status_key'         => $status_key,
        'is_prepared'        => true,
        'recipient'          => $recipient,
        'sent_targets'       => $recipient,
        'subject'            => $subject,
        'email_body_html'    => $body,
        'pdf_urls'           => $pdf_urls,
        'prepared_at'        => $prepared_at,
        'sent_at'            => $prepared_at,
        'sent_by'            => 0,
        'ai_summary'         => $ai_summary,
        'data_snapshot_json' => !empty($data_snapshot) ? wp_json_encode($data_snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '',
    ];
}

/**
 * Ermittelt die Anzahl der vorhandenen Snapshots (inkl. vorbereiteter Dokumente) für einen Eintrag.
 *
 * @param int $entry_id
 * @return int
 */
function crm_get_entry_snapshots_count($entry_id)
{
    global $wpdb;
    $entry_id = absint($entry_id);
    if (!$entry_id) return 0;
    
    $table = $wpdb->prefix . 'crm_document_snapshots';
    $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE entry_id = %d", $entry_id));

    // Auch vorbereiteten Friedelin-Draft berücksichtigen
    $draft = function_exists('crm_friedelin_get_entry_draft') ? crm_friedelin_get_entry_draft($entry_id) : null;
    if (!empty($draft['pdf_urls'])) {
        $count += 1;
    }

    return $count;
}

/**
 * Batch-Abfrage für die Anzahl vorhandener Snapshots mehrerer Einträge in einem einzigen Query (verhindert N+1).
 * Berücksichtigt revisionssichere Snapshots sowie vorbereitete Friedelin-Drafts.
 *
 * @param array $entry_ids
 * @return array Assoziatives Array [entry_id => count]
 */
function crm_get_entries_snapshots_counts(array $entry_ids)
{
    global $wpdb;

    $entry_ids = array_filter(array_map('absint', $entry_ids));
    if (empty($entry_ids)) {
        return [];
    }

    $table = $wpdb->prefix . 'crm_document_snapshots';
    $in_placeholders = implode(',', array_fill(0, count($entry_ids), '%d'));
    $query = $wpdb->prepare("SELECT entry_id, COUNT(*) as cnt FROM $table WHERE entry_id IN ($in_placeholders) GROUP BY entry_id", $entry_ids);
    $results = $wpdb->get_results($query, ARRAY_A);

    $counts = [];
    if (!empty($results)) {
        foreach ($results as $row) {
            $counts[(int)$row['entry_id']] = (int)$row['cnt'];
        }
    }

    // Vorbereitete Friedelin-Drafts für alle angeforderten Entry-IDs in einem einzigen Query prüfen
    $opt_names = array_map(function ($id) { return 'crm_friedelin_draft_' . $id; }, $entry_ids);
    $opt_placeholders = implode(',', array_fill(0, count($opt_names), '%s'));
    $draft_opts = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name IN ($opt_placeholders)", $opt_names));
    if (!empty($draft_opts)) {
        foreach ($draft_opts as $opt) {
            if (preg_match('/crm_friedelin_draft_(\d+)/', $opt, $m)) {
                $eid = (int)$m[1];
                $counts[$eid] = ($counts[$eid] ?? 0) + 1;
            }
        }
    }

    return $counts;
}

/**
 * Rendert das HTML für das Snapshot-Archiv eines Eintrags.
 * Filtert streng nach dem aktuellen Status des Eintrags („zeige immer nur die dokumente die zu diesem status gehören“).
 * Zeigt vorbereitete Dokumente an, wenn vorhanden, und verlagert frühere Meilensteine in ein aufklappbares Archiv.
 *
 * @param int $entry_id
 * @return string HTML-Ausgabe
 */
function crm_render_snapshots_html($entry_id)
{
    $entry_id = absint($entry_id);
    if (!$entry_id) {
        return '<div style="padding: 24px; text-align: center; color: #64748b; font-size: 13px;">Ungültige Entry-ID.</div>';
    }

    $status_info  = function_exists('crm_get_entry_saved_status') ? crm_get_entry_saved_status($entry_id) : null;
    $status_key   = $status_info['status_key'] ?? 'neu';
    $status_label = $status_info['status_label'] ?? 'Neu / Anfrage';

    $allowed_doc_types = crm_get_doc_types_for_status($status_key);
    $all_snapshots     = crm_get_entry_snapshots($entry_id);

    // Vorbereitete Dokumente prüfen
    $prepared_item = crm_get_prepared_documents_for_entry($entry_id, $status_key);

    // Snapshots nach aktuellem Status vs. frühere Meilensteine trennen
    $current_snapshots = [];
    $other_snapshots   = [];

    foreach ($all_snapshots as $snap) {
        $st = $snap['doc_type'];
        if (empty($allowed_doc_types) || in_array($st, $allowed_doc_types, true)) {
            $current_snapshots[] = $snap;
        } else {
            $other_snapshots[] = $snap;
        }
    }

    $has_current = ($prepared_item !== null) || !empty($current_snapshots);

    if (!$has_current && empty($other_snapshots)) {
        return '<div style="padding: 30px 20px; text-align: center; color: #64748b; font-size: 13px;">
            <span class="dashicons dashicons-archive" style="font-size: 36px; width: 36px; height: 36px; display: block; margin: 0 auto 10px; opacity: 0.45; color: #94a3b8;"></span>
            <strong style="color: #334155; font-size: 14px; display: block; margin-bottom: 4px;">Keine Dokumente vorhanden</strong>
            Für den aktuellen Status <span style="font-weight: 600; color: #007C90;">„' . esc_html($status_label) . '“</span> wurden bisher noch keine Dokumente vorbereitet oder archiviert.
        </div>';
    }

    $doc_labels = [
        'angebot'                        => 'Kursangebot',
        'kb'                             => 'Kurszeitenbestätigung',
        'angebot_kb'                     => 'Angebot & Kurszeitenbestätigung',
        'angebot_kurszeiten'             => 'Angebot & Kurszeitenbestätigung',
        'xsieben_angebot'                => 'Kursangebot',
        'xsieben_kurszeitenbestaetigung' => 'Kurszeitenbestätigung',
        'xsieben_angebot_kurszeiten'     => 'Angebot & Kurszeitenbestätigung',
        'xsieben_angebot_und_kurszeiten' => 'Angebot & Kurszeitenbestätigung',
        'anmeldung'                      => 'Anmeldebestätigung',
        'tb'                             => 'Teilnahmebestätigung',
        'xsieben_teilnahmebestaetigung'  => 'Teilnahmebestätigung',
        'diplom'                         => 'Diplom & Zertifikat',
        'xsieben_diplom'                 => 'Diplom & Zertifikat',
        'invoice'                        => 'Honorarnote',
        'honorarnote'                    => 'Honorarnote',
    ];

    $html = '<div class="crm-snapshots-container" style="display: flex; flex-direction: column; gap: 14px;">';

    // Status-Kopfzeile
    $total_current_count = ($prepared_item ? 1 : 0) + count($current_snapshots);
    $html .= '<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">';
    $html .= '  <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">';
    $html .= '    <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 700;">Aktiver Status:</span>';
    $html .= '    <span class="crm-status-pill crm-status-' . esc_attr($status_key) . '" style="display: inline-flex; vertical-align: middle;">';
    $html .= '      <span class="crm-status-dot"></span>';
    $html .= '      <span class="crm-status-label">' . esc_html($status_label) . '</span>';
    $html .= '    </span>';
    $html .= '    <span style="font-size: 12px; color: #475569; font-weight: 500;">(' . $total_current_count . ' Dokument' . ($total_current_count === 1 ? '' : 'e') . ' zu diesem Status)</span>';
    $html .= '  </div>';
    $html .= '  <div style="font-size: 11px; color: #007C90; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">';
    $html .= '    <span class="dashicons dashicons-shield-alt" style="font-size: 14px; width: 14px; height: 14px;"></span> Revisionssicherer Audit-Trail';
    $html .= '  </div>';
    $html .= '</div>';

    // 1. Vorbereitetes Dokument rendern (falls vorhanden)
    if ($prepared_item !== null) {
        $prep_id       = esc_attr($prepared_item['id']);
        $prep_doc_type = esc_html($prepared_item['doc_type']);
        $prep_title    = $doc_labels[$prepared_item['doc_type']] ?? 'Vorbereitetes Dokument';
        $prep_date     = date_i18n('d.m.Y, H:i', strtotime($prepared_item['prepared_at']));
        $prep_subject  = esc_html($prepared_item['subject']);
        $prep_target   = esc_html($prepared_item['recipient']);
        $prep_summary  = esc_html($prepared_item['ai_summary']);

        $prep_badge = '<span style="background: #f5f3ff; color: #6d28d9; border: 1px solid #c4b5fd; border-radius: 4px; padding: 2px 7px; font-size: 10.5px; font-weight: 700; letter-spacing: 0.3px;">⚡ VORBEREITET / BEREIT ZUM VERSAND</span>';

        $html .= '<div class="crm-snapshot-card crm-snapshot-prepared" style="background: #ffffff; border: 1px solid #c4b5fd; border-left: 4px solid #8b5cf6; border-radius: 8px; padding: 14px 16px; box-shadow: 0 2px 6px rgba(109, 40, 217, 0.06);">';
        
        // Header
        $html .= '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">';
        $html .= '  <div>';
        $html .= '    <strong style="font-size: 14px; color: #007C90;">' . esc_html($prep_title) . '</strong> ';
        $html .= '    ' . $prep_badge;
        $html .= '  </div>';
        $html .= '  <div style="font-size: 11px; color: #64748b;">';
        $html .= '    📅 ' . esc_html($prep_date) . ' &bull; 👤 Friedelin (KI-Assistent)';
        $html .= '  </div>';
        $html .= '</div>';

        // Details
        $html .= '<div style="font-size: 12px; line-height: 1.5; color: #334155; margin-bottom: 12px;">';
        $html .= '  <div><strong>Betreff:</strong> ' . $prep_subject . '</div>';
        $html .= '  <div><strong>Empfänger:</strong> ' . $prep_target . '</div>';
        if (!empty($prep_summary)) {
            $html .= '  <div style="margin-top: 5px; color: #475569; font-size: 11.5px; background: #f8fafc; padding: 4px 8px; border-radius: 4px; border-left: 2px solid #8b5cf6;"><strong>Status-Hinweis:</strong> ' . $prep_summary . '</div>';
        }
        $html .= '</div>';

        // Actions
        $html .= '<div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 10px;">';
        
        // PDF-Buttons
        if (!empty($prepared_item['pdf_urls'])) {
            foreach ($prepared_item['pdf_urls'] as $idx => $purl) {
                $pname = crm_get_friendly_pdf_label($purl, $idx);
                $html .= sprintf(
                    '<a href="%s" target="_blank" class="button button-small" title="%s" style="display: inline-flex; align-items: center; gap: 4px; color: #007C90; border-color: #007C90; font-weight: 600;"><span class="dashicons dashicons-pdf" style="font-size: 16px; width: 16px; height: 16px;"></span> %s</a>',
                    esc_url($purl),
                    esc_attr(basename($purl)),
                    esc_html($pname)
                );
            }
        } else {
            $html .= '<span style="font-size: 11px; color: #94a3b8; font-style: italic;">Keine PDFs hinterlegt</span>';
        }

        // Vorbereiteter E-Mail-Text Button
        if (!empty($prepared_item['email_body_html'])) {
            $html .= sprintf(
                '<button type="button" class="button button-small crm-btn-view-snapshot-email" data-snapshot-id="%s" style="display: inline-flex; align-items: center; gap: 4px;"><span class="dashicons dashicons-email-alt" style="font-size: 16px; width: 16px; height: 16px;"></span> E-Mail-Text anzeigen</button>',
                $prep_id
            );
        }

        // Konditionen & Daten Button
        if (!empty($prepared_item['data_snapshot_json'])) {
            $html .= sprintf(
                '<button type="button" class="button button-small crm-btn-view-snapshot-data" data-snapshot-id="%s" style="display: inline-flex; align-items: center; gap: 4px;"><span class="dashicons dashicons-database" style="font-size: 16px; width: 16px; height: 16px;"></span> Konditionen & Daten</button>',
                $prep_id
            );
        }

        $html .= '</div>'; // End Actions

        // Hidden Email Body & Data Container
        if (!empty($prepared_item['email_body_html'])) {
            $html .= sprintf(
                '<div id="crm-snapshot-email-%s" style="display:none;" data-email-body="%s"></div>',
                $prep_id,
                esc_attr($prepared_item['email_body_html'])
            );
        }
        if (!empty($prepared_item['data_snapshot_json'])) {
            $html .= sprintf(
                '<div id="crm-snapshot-data-%s" style="display:none;" data-snapshot-json="%s"></div>',
                $prep_id,
                esc_attr($prepared_item['data_snapshot_json'])
            );
        }

        $html .= '</div>'; // End Prepared Card
    }

    // 2. Versendete Snapshots für diesen Status rendern
    if (!empty($current_snapshots)) {
        foreach ($current_snapshots as $snap) {
            $id         = absint($snap['id']);
            $doc_type   = esc_html($snap['doc_type']);
            $doc_title  = $doc_labels[$snap['doc_type']] ?? ucfirst($doc_type);
            $is_test    = !empty($snap['is_test']);
            $date_fmt   = date_i18n('d.m.Y, H:i:s', strtotime($snap['sent_at']));
            $subject    = esc_html($snap['subject']);
            $targets    = esc_html($snap['sent_targets'] ?: $snap['recipient']);
            $pdf_urls   = !empty($snap['pdf_file_url']) ? array_filter(array_map('trim', explode(',', $snap['pdf_file_url']))) : [];
            $pdf_names  = !empty($snap['pdf_filename']) ? array_filter(array_map('trim', explode(',', $snap['pdf_filename']))) : [];

            $user_info = '';
            if (!empty($snap['sent_by'])) {
                $u = get_userdata($snap['sent_by']);
                $user_info = $u ? $u->display_name : 'User #' . $snap['sent_by'];
            } else {
                $user_info = 'System';
            }

            $badge_type = $is_test
                ? '<span style="background: #e0f2fe; color: #0369a1; border: 1px solid #7dd3fc; border-radius: 4px; padding: 2px 6px; font-size: 10px; font-weight: 600;">🧪 TEST-VERSAND</span>'
                : '<span style="background: #dcfce7; color: #166534; border: 1px solid #86efac; border-radius: 4px; padding: 2px 6px; font-size: 10px; font-weight: 600;">✅ LIVE VERSENDET</span>';

            $border_left = $is_test ? '#0284c7' : '#16a34a';

            $html .= '<div class="crm-snapshot-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-left: 4px solid ' . $border_left . '; border-radius: 8px; padding: 14px 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">';
            
            // Header
            $html .= '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">';
            $html .= '  <div>';
            $html .= '    <strong style="font-size: 13.5px; color: #007C90;">' . esc_html($doc_title) . '</strong> ';
            $html .= '    ' . $badge_type;
            $html .= '  </div>';
            $html .= '  <div style="font-size: 11px; color: #64748b;">';
            $html .= '    📅 ' . esc_html($date_fmt) . ' &bull; 👤 ' . esc_html($user_info);
            $html .= '  </div>';
            $html .= '</div>';

            // Details
            $html .= '<div style="font-size: 12px; line-height: 1.5; color: #334155; margin-bottom: 12px;">';
            $html .= '  <div><strong>Betreff:</strong> ' . $subject . '</div>';
            $html .= '  <div><strong>Empfänger:</strong> ' . $targets . '</div>';
            $html .= '</div>';

            // Actions
            $html .= '<div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 10px;">';
            
            // PDF Links
            if (!empty($pdf_urls)) {
                foreach ($pdf_urls as $idx => $purl) {
                    $pname = crm_get_friendly_pdf_label($purl, $idx);
                    $html .= sprintf(
                        '<a href="%s" target="_blank" class="button button-small" title="%s" style="display: inline-flex; align-items: center; gap: 4px; color: #007C90; border-color: #007C90;"><span class="dashicons dashicons-pdf" style="font-size: 16px; width: 16px; height: 16px;"></span> %s</a>',
                        esc_url($purl),
                        esc_attr(basename($purl)),
                        esc_html($pname)
                    );
                }
            } else {
                $html .= '<span style="font-size: 11px; color: #94a3b8; font-style: italic;">Kein PDF-Anhang hinterlegt</span>';
            }

            // Email Text View Button
            $html .= sprintf(
                '<button type="button" class="button button-small crm-btn-view-snapshot-email" data-snapshot-id="%d" style="display: inline-flex; align-items: center; gap: 4px;"><span class="dashicons dashicons-email-alt" style="font-size: 16px; width: 16px; height: 16px;"></span> E-Mail-Text anzeigen</button>',
                $id
            );

            // Data JSON View Toggle
            $html .= sprintf(
                '<button type="button" class="button button-small crm-btn-view-snapshot-data" data-snapshot-id="%d" style="display: inline-flex; align-items: center; gap: 4px;"><span class="dashicons dashicons-database" style="font-size: 16px; width: 16px; height: 16px;"></span> Konditionen & Daten</button>',
                $id
            );

            $html .= '</div>'; // End Actions

            // Hidden Email Body & Data Container
            $html .= sprintf(
                '<div id="crm-snapshot-email-%d" style="display:none;" data-email-body="%s"></div>',
                $id,
                esc_attr($snap['email_body_html'])
            );
            $html .= sprintf(
                '<div id="crm-snapshot-data-%d" style="display:none;" data-snapshot-json="%s"></div>',
                $id,
                esc_attr($snap['data_snapshot_json'])
            );

            $html .= '</div>'; // End Card
        }
    }

    // 3. Aufklappbarer Bereich für frühere Meilensteine (falls vorhanden)
    if (!empty($other_snapshots)) {
        $html .= '<details style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 10px 14px; margin-top: 4px;">';
        $html .= '  <summary style="cursor: pointer; font-size: 12px; font-weight: 600; color: #64748b; display: flex; align-items: center; gap: 6px;">';
        $html .= '    🗂️ Frühere Meilensteine / Archiv anzeigen (' . count($other_snapshots) . ' archivierte Dokumente aus vorherigen Phasen)';
        $html .= '  </summary>';
        $html .= '  <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 12px;">';

        foreach ($other_snapshots as $snap) {
            $id         = absint($snap['id']);
            $doc_type   = esc_html($snap['doc_type']);
            $doc_title  = $doc_labels[$snap['doc_type']] ?? ucfirst($doc_type);
            $is_test    = !empty($snap['is_test']);
            $date_fmt   = date_i18n('d.m.Y, H:i:s', strtotime($snap['sent_at']));
            $subject    = esc_html($snap['subject']);
            $targets    = esc_html($snap['sent_targets'] ?: $snap['recipient']);
            $pdf_urls   = !empty($snap['pdf_file_url']) ? array_filter(array_map('trim', explode(',', $snap['pdf_file_url']))) : [];

            $badge_type = $is_test
                ? '<span style="background: #e0f2fe; color: #0369a1; border: 1px solid #7dd3fc; border-radius: 4px; padding: 2px 6px; font-size: 10px; font-weight: 600;">🧪 TEST-VERSAND</span>'
                : '<span style="background: #dcfce7; color: #166534; border: 1px solid #86efac; border-radius: 4px; padding: 2px 6px; font-size: 10px; font-weight: 600;">✅ LIVE VERSENDET</span>';

            $html .= '<div class="crm-snapshot-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;">';
            $html .= '  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 6px;">';
            $html .= '    <div><strong style="font-size: 13px; color: #64748b;">' . esc_html($doc_title) . '</strong> ' . $badge_type . '</div>';
            $html .= '    <div style="font-size: 10.5px; color: #94a3b8;">📅 ' . esc_html($date_fmt) . '</div>';
            $html .= '  </div>';
            $html .= '  <div style="font-size: 11.5px; color: #475569; margin-bottom: 8px;"><strong>Betreff:</strong> ' . $subject . '</div>';
            
            // Actions
            $html .= '  <div style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center;">';
            if (!empty($pdf_urls)) {
                foreach ($pdf_urls as $idx => $purl) {
                    $pname = crm_get_friendly_pdf_label($purl, $idx);
                    $html .= sprintf(
                        '<a href="%s" target="_blank" class="button button-small" title="%s" style="font-size: 11px;"><span class="dashicons dashicons-pdf"></span> %s</a>',
                        esc_url($purl),
                        esc_attr(basename($purl)),
                        esc_html($pname)
                    );
                }
            }
            $html .= sprintf(
                '<button type="button" class="button button-small crm-btn-view-snapshot-email" data-snapshot-id="%d" style="font-size: 11px;">E-Mail-Text</button>',
                $id
            );
            $html .= sprintf(
                '<button type="button" class="button button-small crm-btn-view-snapshot-data" data-snapshot-id="%d" style="font-size: 11px;">Konditionen & Daten</button>',
                $id
            );
            $html .= '  </div>';

            $html .= sprintf('<div id="crm-snapshot-email-%d" style="display:none;" data-email-body="%s"></div>', $id, esc_attr($snap['email_body_html']));
            $html .= sprintf('<div id="crm-snapshot-data-%d" style="display:none;" data-snapshot-json="%s"></div>', $id, esc_attr($snap['data_snapshot_json']));

            $html .= '</div>'; // End inner card
        }

        $html .= '  </div>';
        $html .= '</details>';
    }

    $html .= '</div>'; // End Container

    return $html;
}

/**
 * Liefert alle publizierten Kurse sortiert nach Titel für Auswahldialoge.
 *
 * @return array
 */
function crm_get_all_courses_options(): array
{
    static $cached_courses = null;
    if ($cached_courses !== null) {
        return $cached_courses;
    }

    $posts = get_posts([
        'post_type'      => 'courses',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    $list = [];
    foreach ($posts as $c) {
        $start_date = get_post_meta($c->ID, 'start_datum', true);
        $end_date   = get_post_meta($c->ID, 'end_datum', true);
        $kosten     = get_post_meta($c->ID, 'kosten', true);
        $le         = get_post_meta($c->ID, 'lehreinheiten_gesamt', true);
        $kurstyp    = get_post_meta($c->ID, 'kurstyp', true) ?: 'Lehrgang';

        $date_str = '';
        if ($start_date && $end_date) {
            $date_str = date_i18n('d.m.Y', strtotime($start_date)) . ' – ' . date_i18n('d.m.Y', strtotime($end_date));
        } elseif ($start_date) {
            $date_str = 'Ab ' . date_i18n('d.m.Y', strtotime($start_date));
        } else {
            $date_str = 'Termine n. V.';
        }

        $list[] = [
            'id'         => (int)$c->ID,
            'title'      => trim(wp_strip_all_tags(html_entity_decode(get_the_title($c->ID), ENT_QUOTES, 'UTF-8'))),
            'start_date' => (string)($start_date ?: ''),
            'end_date'   => (string)($end_date ?: ''),
            'date_str'   => $date_str,
            'kosten'     => (string)($kosten ?: ''),
            'le'         => (string)($le ?: ''),
            'kurstyp'    => (string)$kurstyp,
        ];
    }

    $cached_courses = $list;
    return $cached_courses;
}

/**
 * Verknüpft einen WPForms-Eintrag mit einem Katalog-Kurs oder einer freien Geschäftsanfrage.
 *
 * @param int $entry_id
 * @param array $data
 * @return bool
 */
function crm_link_entry_target(int $entry_id, array $data): bool
{
    global $wpdb;

    $entry_id = absint($entry_id);
    if (!$entry_id) {
        return false;
    }

    crm_ensure_status_tables();
    $table_status = $wpdb->prefix . 'crm_entry_status';

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_status WHERE entry_id = %d", $entry_id), ARRAY_A);

    $inquiry_type = isset($data['inquiry_type']) && $data['inquiry_type'] === 'freie_anfrage' ? 'freie_anfrage' : 'course';
    $course_id    = !empty($data['course_id']) ? absint($data['course_id']) : null;
    $custom_title = !empty($data['custom_title']) ? sanitize_text_field(wp_unslash($data['custom_title'])) : null;
    $start_date   = !empty($data['start_date']) ? sanitize_text_field(wp_unslash($data['start_date'])) : null;
    $end_date     = !empty($data['end_date']) ? sanitize_text_field(wp_unslash($data['end_date'])) : null;
    $note         = !empty($data['note']) ? sanitize_textarea_field(wp_unslash($data['note'])) : null;
    $user_id      = get_current_user_id() ?: 0;

    // Wenn Kurs ausgewählt, aber keine Termine übergeben wurden -> vom Kurs übernehmen
    if ($inquiry_type === 'course' && $course_id) {
        if (empty($start_date)) {
            $start_date = get_post_meta($course_id, 'start_datum', true) ?: null;
        }
        if (empty($end_date)) {
            $end_date = get_post_meta($course_id, 'end_datum', true) ?: null;
        }
        if (empty($custom_title)) {
            $custom_title = trim(wp_strip_all_tags(html_entity_decode(get_the_title($course_id), ENT_QUOTES, 'UTF-8')));
        }
    } elseif ($inquiry_type === 'freie_anfrage') {
        // Bei freier Anfrage standardmäßig Termine nach Vereinbarung falls nicht befüllt
        if (empty($start_date)) {
            $start_date = __('Termine n. V.', 'custom-crm');
        }
        if (empty($custom_title)) {
            $custom_title = __('Freie Geschäftsanfrage / Inhouse', 'custom-crm');
        }
    }

    if ($row) {
        $update_data = [
            'inquiry_type'      => $inquiry_type,
            'course_id'         => ($inquiry_type === 'course' ? $course_id : null),
            'custom_title'      => $custom_title,
            'course_start_date' => $start_date,
            'course_end_date'   => $end_date,
            'updated_by'        => $user_id,
        ];
        $update_fmt = ['%s', '%d', '%s', '%s', '%s', '%d'];

        if ($note !== null) {
            $update_data['note'] = $note;
            $update_fmt[] = '%s';
        }

        $wpdb->update($table_status, $update_data, ['entry_id' => $entry_id], $update_fmt, ['%d']);
    } else {
        $all_statuses = crm_get_statuses();
        $form_id = !empty($data['form_id']) ? absint($data['form_id']) : crm_get_default_form_id();
        $wpdb->insert(
            $table_status,
            [
                'entry_id'          => $entry_id,
                'form_id'           => $form_id,
                'status_key'        => 'neu',
                'status_label'      => $all_statuses['neu']['label'] ?? 'Neu / Anfrage',
                'status_date'       => current_time('mysql'),
                'course_id'         => ($inquiry_type === 'course' ? $course_id : null),
                'course_start_date' => $start_date,
                'course_end_date'   => $end_date,
                'inquiry_type'      => $inquiry_type,
                'custom_title'      => $custom_title,
                'note'              => $note ?: __('Initial verknüpft', 'custom-crm'),
                'updated_by'        => $user_id,
            ],
            ['%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d']
        );
    }

    // Audit-Trail in Status-Historie
    if (function_exists('crm_add_entry_status_history')) {
        if ($inquiry_type === 'freie_anfrage') {
            $hist_title = sprintf(__('Als freie Geschäftsanfrage verknüpft: %s', 'custom-crm'), $custom_title);
            crm_add_entry_status_history($entry_id, 'linked_business', 'Freie Geschäftsanfrage', $hist_title);
        } else {
            $course_name = $custom_title ?: ($course_id ? get_the_title($course_id) : '');
            $hist_title = sprintf(__('Mit Kurs verknüpft: %s (ID: %d)', 'custom-crm'), $course_name, $course_id);
            crm_add_entry_status_history($entry_id, 'linked_course', 'Kurs verknüpft', $hist_title);
        }
    }

    return true;
}

/**
 * Ermittelt alle verfügbaren Zertifizierungen für einen Kurs.
 *
 * Prüft den ACF-Repeater 'zertifizierungen', Post-Meta 'zertifikat' sowie
 * definierte Ausbildungspartner (IPMA, Scrum.org, SystemCERT ISO 17024, TÜV AUSTRIA).
 * Schließt reine DaF/DaZ-Kurse revisionssicher aus.
 * Markiert die vom Kunden gewünschte Zertifizierung mit 'is_selected' => true.
 *
 * @param int $course_id Post-ID des Kurses.
 * @param int|array $entry_id  Optionale WPForms-Eintrags-ID oder direktes Array gewählter Zertifizierungen.
 * @return array Array von Zertifizierungs-Objekten/Arrays.
 */
function crm_get_course_available_certifications(int $course_id, $entry_id = 0): array
{
    if (!$course_id) {
        return [];
    }

    $course_title = get_the_title($course_id);
    $course_title_lower = mb_strtolower($course_title, 'UTF-8');

    // 0. Ausschluss für reine DaF / DaZ Ausbildungen (weder 12 Tage noch AMS Aktion haben externe Zertifizierung)
    $is_pure_daf_daz = (
        ($course_id == 701 || $course_id == 65629) ||
        ((strpos($course_title_lower, 'daf') !== false || strpos($course_title_lower, 'daz') !== false) && strpos($course_title_lower, 'kombi') === false)
    );
    if ($is_pure_daf_daz) {
        return [];
    }

    // Ermittlung der aktuell für diesen Eintrag gewählten Zertifizierungen (Mehrfachauswahl möglich)
    $resolved_certs = [];
    if (is_array($entry_id)) {
        $resolved_certs = $entry_id;
    } elseif (is_numeric($entry_id) && (int)$entry_id > 0 && function_exists('crm_resolve_course_certification')) {
        $resolved_certs = crm_resolve_course_certification((int)$entry_id, $course_id);
    }

    $is_cert_in_resolved = function(string $clean_name, string $short_name, string $raw_name) use ($resolved_certs): bool {
        if (empty($resolved_certs)) {
            return false;
        }
        $clean_l = mb_strtolower(trim($clean_name), 'UTF-8');
        $short_l = mb_strtolower(trim($short_name), 'UTF-8');
        $raw_l   = mb_strtolower(trim($raw_name), 'UTF-8');
        $is_online = (strpos($clean_l, 'online') !== false || strpos($short_l, 'online') !== false || strpos($raw_l, 'online') !== false);

        foreach ($resolved_certs as $rc) {
            $rc_name_l = mb_strtolower(trim((string)($rc['name'] ?? '')), 'UTF-8');
            $rc_is_online = (strpos($rc_name_l, 'online') !== false);

            // Online vs. Präsenz müssen strikt übereinstimmen
            if ($is_online !== $rc_is_online) {
                continue;
            }

            // Exakte Übereinstimmungen
            if ($clean_l === $rc_name_l || $short_l === $rc_name_l || $raw_l === $rc_name_l) {
                return true;
            }
            if (strpos($clean_l, $rc_name_l) !== false || strpos($rc_name_l, $clean_l) !== false) {
                return true;
            }
            if (strpos($short_l, $rc_name_l) !== false || strpos($rc_name_l, $short_l) !== false) {
                return true;
            }

            // Spezifische IPMA Level-Prüfungen
            if (strpos($clean_l, 'level d') !== false && strpos($rc_name_l, 'level d') !== false) {
                return true;
            }
            if (strpos($clean_l, 'level c') !== false && strpos($rc_name_l, 'level c') !== false) {
                return true;
            }
            if (strpos($clean_l, 'level b') !== false && strpos($rc_name_l, 'level b') !== false) {
                return true;
            }

            // Spezifische Scrum-Prüfungen
            if (strpos($clean_l, 'psm i') !== false && strpos($clean_l, 'pspo') === false && strpos($rc_name_l, 'psm') !== false && strpos($rc_name_l, 'pspo') === false) {
                return true;
            }
            if (strpos($clean_l, 'pspo i') !== false && strpos($clean_l, 'psm') === false && strpos($rc_name_l, 'pspo') !== false && strpos($rc_name_l, 'psm') === false) {
                return true;
            }
            if (strpos($clean_l, 'psm') !== false && strpos($clean_l, 'pspo') !== false && strpos($rc_name_l, 'psm') !== false && strpos($rc_name_l, 'pspo') !== false) {
                return true;
            }

            // Spezifische TÜV / SystemCERT Prüfungen
            if (strpos($clean_l, 'systemcert') !== false && strpos($rc_name_l, 'systemcert') !== false) {
                return true;
            }
            if ((strpos($clean_l, 'tüv') !== false || strpos($clean_l, 'tuev') !== false) && (strpos($rc_name_l, 'tüv') !== false || strpos($rc_name_l, 'tuev') !== false)) {
                return true;
            }
        }
        return false;
    };

    $certs = [];

    // 1. ACF Repeater 'zertifizierungen' am Kurs
    if (function_exists('have_rows') && have_rows('zertifizierungen', $course_id)) {
        while (have_rows('zertifizierungen', $course_id)) {
            the_row();
            $raw_name  = trim((string)get_sub_field('name-zert'));
            $raw_preis = get_sub_field('preis');
            $raw_ust   = get_sub_field('Ust_satz');
            $tooltip   = trim((string)get_sub_field('beschreibungtooltip'));

            if (!empty($raw_name)) {
                // Bereinigung von "Optional: ", "Zusatzoption: ", abschließenden Punkten
                $clean_name = preg_replace('/^(optional:\s*|zusatzoption:\s*)/i', '', $raw_name);
                $clean_name = rtrim($clean_name, '.');

                // Preis formatieren
                $price_num = is_numeric($raw_preis) ? floatval($raw_preis) : floatval(str_replace(',', '.', str_replace('.', '', (string)$raw_preis)));
                $price_formatted = $price_num > 0 ? number_format($price_num, 2, ',', '.') . ' €' : '';

                // Anbieter-Klassifizierung
                $name_l = mb_strtolower($clean_name, 'UTF-8');
                $provider = 'Zertifizierung';
                $badge_class = 'crm-cert-default';
                $short_name = $clean_name;

                if (strpos($name_l, 'ipma') !== false || strpos($name_l, 'pma') !== false) {
                    $provider = 'IPMA / pma';
                    $badge_class = 'crm-cert-ipma';
                    if (strpos($name_l, 'level b') !== false) {
                        $short_name = (strpos($name_l, 'online') !== false) ? 'IPMA Level B (Online)' : 'IPMA Level B';
                    } elseif (strpos($name_l, 'level c') !== false) {
                        $short_name = 'IPMA Level C';
                    } elseif (strpos($name_l, 'level d') !== false) {
                        $short_name = 'IPMA Level D';
                    }
                } elseif (strpos($name_l, 'scrum') !== false || strpos($name_l, 'psm') !== false || strpos($name_l, 'pspo') !== false) {
                    $provider = 'Scrum.org';
                    $badge_class = 'crm-cert-scrum';
                    if (strpos($name_l, 'psm i') !== false && strpos($name_l, 'pspo i') !== false) {
                        $short_name = 'Scrum PSM I + PSPO I';
                    } elseif (strpos($name_l, 'pspo i') !== false) {
                        $short_name = 'Scrum PSPO I';
                    } elseif (strpos($name_l, 'psm i') !== false) {
                        $short_name = 'Scrum PSM I';
                    }
                } elseif (strpos($name_l, 'tüv') !== false || strpos($name_l, 'tuev') !== false || (strpos($name_l, '17024') !== false && strpos($name_l, 'systemcert') === false)) {
                    $provider = 'TÜV AUSTRIA';
                    $badge_class = 'crm-cert-tuev';
                    $short_name = 'TÜV ISO 17024';
                } elseif (strpos($name_l, 'systemcert') !== false) {
                    $provider = 'SystemCERT';
                    $badge_class = 'crm-cert-systemcert';
                    $short_name = 'SystemCERT ISO 17024';
                }

                $is_selected = $is_cert_in_resolved($clean_name, $short_name, $raw_name);

                $certs[] = [
                    'raw_name'        => $raw_name,
                    'name'            => $clean_name,
                    'short_name'      => $short_name,
                    'price_raw'       => (string)$raw_preis,
                    'price_num'       => $price_num,
                    'price_formatted' => $price_formatted,
                    'ust'             => !empty($raw_ust) ? $raw_ust . '%' : '20%',
                    'provider'        => $provider,
                    'badge_class'     => $badge_class,
                    'is_selected'     => $is_selected,
                    'tooltip'         => $tooltip ?: ($clean_name . ($price_formatted ? ' (zzgl. ' . $price_formatted . ')' : '')),
                    'source'          => 'acf'
                ];
            }
        }
    }

    // 2. Fallback: Meta 'zertifikat' am Kurs falls kein Repeater gepflegt
    if (empty($certs)) {
        $meta_zert = trim((string)get_post_meta($course_id, 'zertifikat', true));
        $meta_l = mb_strtolower($meta_zert, 'UTF-8');

        if (!empty($meta_zert)) {
            if (strpos($meta_l, 'scrum') !== false) {
                $certs[] = [
                    'raw_name'        => $meta_zert,
                    'name'            => 'Scrum.org Zertifizierung (PSM I / PSPO I)',
                    'short_name'      => 'Scrum.org (PSM/PSPO)',
                    'price_raw'       => '171.50',
                    'price_num'       => 171.50,
                    'price_formatted' => 'ab 171,50 €',
                    'ust'             => '0%',
                    'provider'        => 'Scrum.org',
                    'badge_class'     => 'crm-cert-scrum',
                    'is_selected'     => $is_cert_in_resolved($meta_zert, 'Scrum.org (PSM/PSPO)', $meta_zert),
                    'tooltip'         => $meta_zert,
                    'source'          => 'meta'
                ];
            } elseif (strpos($meta_l, 'ipma') !== false || strpos($meta_l, 'pma') !== false) {
                $certs[] = [
                    'raw_name'        => $meta_zert,
                    'name'            => 'IPMA® / pma Zertifizierung (Level D / C / B)',
                    'short_name'      => 'IPMA® / pma (Level D/C/B)',
                    'price_raw'       => '484',
                    'price_num'       => 484.00,
                    'price_formatted' => 'ab 484,00 €',
                    'ust'             => '10%',
                    'provider'        => 'IPMA / pma',
                    'badge_class'     => 'crm-cert-ipma',
                    'is_selected'     => $is_cert_in_resolved($meta_zert, 'IPMA® / pma (Level D/C/B)', $meta_zert),
                    'tooltip'         => $meta_zert,
                    'source'          => 'meta'
                ];
            } elseif (strpos($meta_l, 'iso 17024') !== false || strpos($meta_l, 'tüv') !== false) {
                $certs[] = [
                    'raw_name'        => $meta_zert,
                    'name'            => 'TÜV AUSTRIA - ISO/IEC 17024 Zertifizierung',
                    'short_name'      => 'TÜV ISO 17024',
                    'price_raw'       => '497',
                    'price_num'       => 497.00,
                    'price_formatted' => '497,00 €',
                    'ust'             => '20%',
                    'provider'        => 'TÜV AUSTRIA',
                    'badge_class'     => 'crm-cert-tuev',
                    'is_selected'     => $is_cert_in_resolved($meta_zert, 'TÜV ISO 17024', $meta_zert),
                    'tooltip'         => $meta_zert,
                    'source'          => 'meta'
                ];
            } elseif (strpos($meta_l, 'systemcert') !== false || (strpos($course_title_lower, 'fachtrainer') !== false && strpos($course_title_lower, 'ams') !== false)) {
                $certs[] = [
                    'raw_name'        => $meta_zert,
                    'name'            => 'SystemCERT ISO 17024 FachtrainerIn',
                    'short_name'      => 'SystemCERT ISO 17024',
                    'price_raw'       => '324',
                    'price_num'       => 324.00,
                    'price_formatted' => '324,00 €',
                    'ust'             => '20%',
                    'provider'        => 'SystemCERT',
                    'badge_class'     => 'crm-cert-systemcert',
                    'is_selected'     => $is_cert_in_resolved($meta_zert, 'SystemCERT ISO 17024', $meta_zert),
                    'tooltip'         => $meta_zert,
                    'source'          => 'meta'
                ];
            }
        }
    }

    return $certs;
}

/**
 * Rendert visuelle HTML-Badges für verfügbare Zertifizierungen.
 * Unterstützt interaktive Buttons zur direkten An- und Abwahl (Mehrfachauswahl) im Angebot.
 *
 * Unterstützt Kontexte:
 * - 'widget': Standardanzeige im Kurs-Widget (Cards View & Kompakte Tabelle)
 * - 'compact': Platzsparende Badges für Kanban-Karten & Split-View Feed
 * - 'dossier': Detaillierte Liste für den Geschäftsvorfall / 3-Punkte-Spickzettel
 *
 * @param array  $certs     Ergebnis von crm_get_course_available_certifications()
 * @param string $context   'widget' | 'compact' | 'dossier'
 * @param int    $entry_id  Optionale Eintrags-ID für interaktive Toggle-Buttons
 * @param int    $course_id Optionale Kurs-ID
 * @return string HTML-Ausgabe
 */
function crm_render_course_cert_badges(array $certs, string $context = 'widget', int $entry_id = 0, int $course_id = 0): string
{
    if (empty($certs)) {
        if ($context === 'widget') {
            return '<div class="crm-course-certs-row"' . ($entry_id ? ' data-entry-id="' . esc_attr($entry_id) . '" data-course-id="' . esc_attr($course_id) . '"' : '') . '>' .
                '<span class="crm-badge crm-badge-cert crm-badge-cert-none" title="' . esc_attr__('Dieser Kurs schließt mit einem X-SIEBEN Diplom / Teilnahmebestätigung ab (keine externe Zertifizierung)', 'custom-crm') . '">' .
                    '<span class="dashicons dashicons-welcome-learn-more" style="font-size:11px; width:11px; height:11px; line-height:11px;"></span> ' .
                    esc_html__('Diplom (ohne ext. Zert.)', 'custom-crm') .
                '</span>' .
            '</div>';
        }
        return '';
    }

    if ($context === 'compact') {
        $out = '<div class="crm-kanban-certs-row crm-course-certs-compact"' . ($entry_id ? ' data-entry-id="' . esc_attr($entry_id) . '" data-course-id="' . esc_attr($course_id) . '"' : '') . '>';
        foreach ($certs as $c) {
            $is_sel = !empty($c['is_selected']);
            $sel_cls = $is_sel ? ' crm-cert-selected' : '';
            $price_str = !empty($c['price_formatted']) ? ' (' . $c['price_formatted'] . ')' : '';
            $check_icon = $is_sel ? '✓ ' : '🏅 ';
            $cert_display_name = $c['short_name'] ?: $c['name'];
            $btn_title = $is_sel
                ? sprintf(__('„%s“ ist für das Angebot ausgewählt. Klicken zum Abwählen.', 'custom-crm'), $cert_display_name)
                : sprintf(__('„%s“ zum Angebot hinzufügen. Klicken zum Auswählen.', 'custom-crm'), $cert_display_name);

            if ($entry_id > 0) {
                $out .= sprintf(
                    '<button type="button" class="crm-badge crm-badge-cert crm-cert-toggle-btn %s%s" ' .
                    'data-entry-id="%d" data-course-id="%d" data-cert-name="%s" data-cert-price="%s" data-cert-ust="%s" data-selected="%d" ' .
                    'aria-pressed="%s" title="%s">%s%s%s</button>',
                    esc_attr($c['badge_class']),
                    esc_attr($sel_cls),
                    $entry_id,
                    $course_id,
                    esc_attr($cert_display_name),
                    esc_attr($c['price_formatted'] ?: ($c['price_raw'] ?? '')),
                    esc_attr($c['ust'] ?? '20%'),
                    $is_sel ? 1 : 0,
                    $is_sel ? 'true' : 'false',
                    esc_attr($btn_title),
                    $check_icon,
                    esc_html($cert_display_name),
                    esc_html($price_str)
                );
            } else {
                $out .= sprintf(
                    '<span class="crm-badge crm-badge-cert %s%s" title="%s">%s%s%s</span>',
                    esc_attr($c['badge_class']),
                    esc_attr($sel_cls),
                    esc_attr($c['tooltip']),
                    $check_icon,
                    esc_html($cert_display_name),
                    esc_html($price_str)
                );
            }
        }
        $out .= '</div>';
        return $out;
    }

    if ($context === 'dossier') {
        $out = '<div class="crm-spickzettel-certs-box"' . ($entry_id ? ' data-entry-id="' . esc_attr($entry_id) . '" data-course-id="' . esc_attr($course_id) . '"' : '') . '>' .
            '<div class="crm-spickzettel-certs-title">' .
                '<span class="dashicons dashicons-awards" style="font-size:13px; width:13px; height:13px; line-height:13px; color:#6d28d9;"></span> ' .
                '<strong>' . esc_html__('Verfügbare Zertifizierungen:', 'custom-crm') . '</strong>' .
                '<span style="font-size:10.5px; color:#64748b; font-weight:normal; margin-left:6px;">' . esc_html__('(Klick zum An-/Abwählen)', 'custom-crm') . '</span>' .
            '</div>' .
            '<div class="crm-spickzettel-certs-list">';
        foreach ($certs as $c) {
            $is_sel = !empty($c['is_selected']);
            $sel_cls = $is_sel ? ' crm-cert-selected' : '';
            $price_str = !empty($c['price_formatted']) ? ' + ' . $c['price_formatted'] : '';
            $sel_badge = $is_sel
                ? ' <span class="crm-cert-sel-tag">✓ ' . esc_html__('Im Angebot', 'custom-crm') . '</span>'
                : ' <span class="crm-cert-unsel-tag" style="font-size:10px; color:#94a3b8;">' . esc_html__('Abgewählt', 'custom-crm') . '</span>';
            $cert_display_name = $c['short_name'] ?: $c['name'];
            $btn_title = $is_sel
                ? sprintf(__('„%s“ ist für das Angebot ausgewählt. Klicken zum Abwählen.', 'custom-crm'), $cert_display_name)
                : sprintf(__('„%s“ zum Angebot hinzufügen. Klicken zum Auswählen.', 'custom-crm'), $cert_display_name);

            if ($entry_id > 0) {
                $out .= sprintf(
                    '<button type="button" class="crm-spickzettel-cert-item crm-cert-toggle-btn%s" ' .
                        'data-entry-id="%d" data-course-id="%d" data-cert-name="%s" data-cert-price="%s" data-cert-ust="%s" data-selected="%d" ' .
                        'aria-pressed="%s" title="%s" style="background:transparent; border:1px solid %s; border-radius:6px; padding:4px 8px; margin-bottom:4px; display:flex; align-items:center; justify-content:space-between; width:100%%; cursor:pointer; text-align:left;">' .
                        '<span class="crm-badge crm-badge-cert %s" style="pointer-events:none;">%s%s</span>' .
                        '<span class="crm-cert-price-tax" style="pointer-events:none;"><strong>%s</strong> <small>(USt: %s)</small></span>' .
                        '%s' .
                    '</button>',
                    esc_attr($sel_cls),
                    $entry_id,
                    $course_id,
                    esc_attr($cert_display_name),
                    esc_attr($c['price_formatted'] ?: ($c['price_raw'] ?? '')),
                    esc_attr($c['ust'] ?? '20%'),
                    $is_sel ? 1 : 0,
                    $is_sel ? 'true' : 'false',
                    esc_attr($btn_title),
                    $is_sel ? '#0284c7' : '#e2e8f0',
                    esc_attr($c['badge_class']),
                    $is_sel ? '✓ ' : '🏅 ',
                    esc_html($cert_display_name),
                    esc_html($price_str),
                    esc_html($c['ust']),
                    $sel_badge
                );
            } else {
                $out .= sprintf(
                    '<div class="crm-spickzettel-cert-item%s" title="%s">' .
                        '<span class="crm-badge crm-badge-cert %s">🏅 %s</span>' .
                        '<span class="crm-cert-price-tax"><strong>%s</strong> <small>(USt: %s)</small></span>' .
                        '%s' .
                    '</div>',
                    esc_attr($sel_cls),
                    esc_attr($c['tooltip']),
                    esc_attr($c['badge_class']),
                    esc_html($cert_display_name),
                    esc_html($price_str),
                    esc_html($c['ust']),
                    $sel_badge
                );
            }
        }
        $out .= '</div></div>';
        return $out;
    }

    // Default 'widget' (Cards View & Table View)
    $out = '<div class="crm-course-certs-row"' . ($entry_id ? ' data-entry-id="' . esc_attr($entry_id) . '" data-course-id="' . esc_attr($course_id) . '"' : '') . '>';
    $out .= '<span class="crm-course-certs-label" title="' . esc_attr__('Verfügbare Zertifizierungsoptionen für diesen Kurs (Klicken zum An- oder Abwählen)', 'custom-crm') . '"><span class="dashicons dashicons-awards" style="font-size:12px; width:12px; height:12px; line-height:12px; color:#7c3aed;"></span> ' . esc_html__('Zertifizierung:', 'custom-crm') . '</span> ';
    foreach ($certs as $c) {
        $is_sel = !empty($c['is_selected']);
        $sel_cls = $is_sel ? ' crm-cert-selected' : '';
        $price_str = !empty($c['price_formatted']) ? ' (+' . $c['price_formatted'] . ')' : '';
        $check_icon = $is_sel ? '✓ ' : '🏅 ';
        $cert_display_name = $c['short_name'] ?: $c['name'];
        $btn_title = $is_sel
            ? sprintf(__('„%s“ ist für das Angebot ausgewählt. Klicken zum Abwählen.', 'custom-crm'), $cert_display_name)
            : sprintf(__('„%s“ zum Angebot hinzufügen. Klicken zum Auswählen.', 'custom-crm'), $cert_display_name);

        if ($entry_id > 0) {
            $out .= sprintf(
                '<button type="button" class="crm-badge crm-badge-cert crm-cert-toggle-btn %s%s" ' .
                'data-entry-id="%d" data-course-id="%d" data-cert-name="%s" data-cert-price="%s" data-cert-ust="%s" data-selected="%d" ' .
                'aria-pressed="%s" title="%s">%s%s%s</button> ',
                esc_attr($c['badge_class']),
                esc_attr($sel_cls),
                $entry_id,
                $course_id,
                esc_attr($cert_display_name),
                esc_attr($c['price_formatted'] ?: ($c['price_raw'] ?? '')),
                esc_attr($c['ust'] ?? '20%'),
                $is_sel ? 1 : 0,
                $is_sel ? 'true' : 'false',
                esc_attr($btn_title),
                $check_icon,
                esc_html($cert_display_name),
                esc_html($price_str)
            );
        } else {
            $out .= sprintf(
                '<span class="crm-badge crm-badge-cert %s%s" title="%s">%s%s%s</span> ',
                esc_attr($c['badge_class']),
                esc_attr($sel_cls),
                esc_attr($c['tooltip']),
                $check_icon,
                esc_html($cert_display_name),
                esc_html($price_str)
            );
        }
    }
    $out .= '</div>';
    return $out;
}

/**
 * Rendert das visuelle HTML-Widget für die Tabellenspalte "Angefragter Kurs".
 *
 * @param int $entry_id
 * @param array|null $entry_status
 * @param int $course_id
 * @param string $course_title
 * @param string $start_date_raw
 * @param string $end_date_raw
 * @return string
 */
function crm_render_entry_course_widget(int $entry_id, ?array $entry_status = null, int $course_id = 0, string $course_title = '', string $start_date_raw = '', string $end_date_raw = ''): string
{
    $inquiry_type = $entry_status['inquiry_type'] ?? 'course';
    $custom_title = $entry_status['custom_title'] ?? '';

    // 1. Fall: Freie Geschäftsanfrage
    if ($inquiry_type === 'freie_anfrage') {
        $display_title = $custom_title ?: ($course_title ?: __('Freie Geschäftsanfrage / Inhouse', 'custom-crm'));
        $date_display = !empty($start_date_raw) ? esc_html($start_date_raw) : __('Termine n. V.', 'custom-crm');
        if (!empty($start_date_raw) && !empty($end_date_raw) && strtotime($start_date_raw) && strtotime($end_date_raw)) {
            $date_display = date_i18n('d.m.y', strtotime($start_date_raw)) . ' – ' . date_i18n('d.m.y', strtotime($end_date_raw));
        }

        return sprintf(
            '<div class="crm-course-widget crm-course-widget-business">' .
                '<div style="display: flex; align-items: center; justify-content: space-between; gap: 6px; margin-bottom: 3px;">' .
                    '<span class="crm-badge crm-badge-business" title="%s">' .
                        '<span class="dashicons dashicons-businessman" style="font-size:13px; width:13px; height:13px; line-height:13px;"></span>' .
                        '<span>%s</span>' .
                    '</span>' .
                    '<button type="button" class="button-link crm-link-course-btn crm-course-edit-link" data-entry-id="%d" data-course-id="0" data-inquiry-type="freie_anfrage" data-custom-title="%s" title="%s" style="font-size: 11px; text-decoration: none; color: #475569; display: inline-flex; align-items: center; gap: 2px;">' .
                        '<span class="dashicons dashicons-edit" style="font-size: 13px; width: 13px; height: 13px;"></span> %s' .
                    '</button>' .
                '</div>' .
                '<div class="crm-course-title-business" style="font-weight: 700; font-size: 13px; color: #1e293b; line-height: 1.35; margin-bottom: 4px;">%s</div>' .
                '<div class="crm-course-dates-row">' .
                    '<span class="crm-badge crm-badge-date" title="%s">' .
                        '<span class="dashicons dashicons-calendar-alt"></span>' .
                        '<span class="crm-badge-date-text">%s</span>' .
                    '</span>' .
                '</div>' .
            '</div>',
            esc_attr__('Individuelle freie Geschäftsanfrage / Inhouse-Training', 'custom-crm'),
            esc_html__('Freie Geschäftsanfrage', 'custom-crm'),
            $entry_id,
            esc_attr($display_title),
            esc_attr__('Verknüpfung bearbeiten oder zu Katalogkurs wechseln', 'custom-crm'),
            esc_html__('Ändern', 'custom-crm'),
            esc_html($display_title),
            esc_attr__('Vereinbarter Termin / Zeitraum', 'custom-crm'),
            esc_html($date_display)
        );
    }

    // 2. Fall: Katalog-Kurs verknüpft
    if ($course_id > 0) {
        $display_title = $course_title ?: get_the_title($course_id);
        if (!empty($start_date_raw) && !empty($end_date_raw) && strtotime($start_date_raw) && strtotime($end_date_raw)) {
            $start_ts = strtotime($start_date_raw);
            $end_ts   = strtotime($end_date_raw);
            $date_display = date_i18n('d.m.y', $start_ts) . ' – ' . date_i18n('d.m.y', $end_ts);
            $date_tooltip = sprintf(__('Kurszeitraum (bei Anfrage): %s bis %s', 'custom-crm'), date_i18n('d.m.Y', $start_ts), date_i18n('d.m.Y', $end_ts));
        } elseif (!empty($start_date_raw) && strtotime($start_date_raw)) {
            $start_ts = strtotime($start_date_raw);
            $date_display = __('Ab ', 'custom-crm') . date_i18n('d.m.y', $start_ts);
            $date_tooltip = sprintf(__('Kursbeginn (bei Anfrage): %s', 'custom-crm'), date_i18n('d.m.Y', $start_ts));
        } else {
            $date_display = __('Termine n. V.', 'custom-crm');
            $date_tooltip = __('Kurszeitraum nach Vereinbarung / noch nicht terminiert', 'custom-crm');
        }

        $edit_link = get_edit_post_link($course_id);
        $course_link_title = sprintf(__('Kurs im Editor bearbeiten (ID: %d)', 'custom-crm'), $course_id);
        $date_badge_class = (!empty($start_date_raw)) ? 'crm-badge-date' : 'crm-badge-nodate';

        $available_certs = crm_get_course_available_certifications($course_id, $entry_id);
        $certs_html = crm_render_course_cert_badges($available_certs, 'widget', $entry_id, $course_id);

        return sprintf(
            '<div class="crm-course-widget">' .
                '<div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 6px; margin-bottom: 3px;">' .
                    '<a href="%s" target="_blank" class="crm-course-title-link" title="%s">%s</a>' .
                    '<button type="button" class="button-link crm-link-course-btn crm-course-edit-link" data-entry-id="%d" data-course-id="%d" data-inquiry-type="course" title="%s" style="font-size: 11px; text-decoration: none; color: #475569; display: inline-flex; align-items: center; gap: 2px; flex-shrink: 0; padding-top: 1px;">' .
                        '<span class="dashicons dashicons-edit" style="font-size: 13px; width: 13px; height: 13px;"></span> %s' .
                    '</button>' .
                '</div>' .
                '<div class="crm-course-dates-row">' .
                    '<span class="crm-badge %s" title="%s">' .
                        '<span class="dashicons dashicons-calendar-alt"></span>' .
                        '<span class="crm-badge-date-text">%s</span>' .
                    '</span>' .
                '</div>' .
                '%s' .
            '</div>',
            esc_url($edit_link),
            esc_attr($course_link_title),
            esc_html($display_title),
            $entry_id,
            $course_id,
            esc_attr__('Kurs wechseln oder als Geschäftsanfrage anlegen', 'custom-crm'),
            esc_html__('Ändern', 'custom-crm'),
            esc_attr($date_badge_class),
            esc_attr($date_tooltip),
            esc_html($date_display),
            $certs_html
        );
    }

    // 3. Fall: Noch nicht verknüpft (z.B. Kontaktformular, Call Back oder freie Anfrage)
    return sprintf(
        '<div class="crm-course-widget crm-course-widget-unlinked">' .
            '<div style="display: flex; align-items: center; gap: 6px; margin-bottom: 6px;">' .
                '<span class="crm-badge crm-badge-warning" title="%s">' .
                    '<span class="dashicons dashicons-warning" style="font-size: 13px; width: 13px; height: 13px;"></span>' .
                    '<span>%s</span>' .
                '</span>' .
            '</div>' .
            '<button type="button" class="button button-primary button-small crm-link-course-btn" data-entry-id="%d" data-course-id="0" style="display: inline-flex; align-items: center; gap: 5px; font-weight: 600; font-size: 11px; padding: 2px 9px; height: 26px; border-radius: 4px;">' .
                '<span class="dashicons dashicons-admin-links" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px;"></span>' .
                '<span>%s</span>' .
            '</button>' .
        '</div>',
        esc_attr__('Anfrage ist noch keinem Kurs oder keiner Geschäftsanfrage zugeordnet', 'custom-crm'),
        esc_html__('Nicht verknüpft (Kontakt)', 'custom-crm'),
        $entry_id,
        esc_html__('Kurs / Geschäftsanfrage verknüpfen', 'custom-crm')
    );
}

/**
 * AJAX-Handler zum Verknüpfen einer Anfrage mit einem Katalog-Kurs oder einer freien Geschäftsanfrage.
 */
add_action('wp_ajax_crm_link_entry', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Nicht autorisiert.', 'custom-crm')]);
    }
    if (!check_ajax_referer('crm_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen.', 'custom-crm')]);
    }

    $entry_id     = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
    $inquiry_type = isset($_POST['inquiry_type']) && $_POST['inquiry_type'] === 'freie_anfrage' ? 'freie_anfrage' : 'course';
    $course_id    = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
    $custom_title = isset($_POST['custom_title']) ? sanitize_text_field(wp_unslash($_POST['custom_title'])) : '';
    $start_date   = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
    $end_date     = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';
    $note         = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';

    if (!$entry_id) {
        wp_send_json_error(['message' => __('Ungültige Eintrags-ID.', 'custom-crm')]);
    }

    if ($inquiry_type === 'course' && !$course_id) {
        wp_send_json_error(['message' => __('Bitte wählen Sie einen Kurs aus dem Katalog aus.', 'custom-crm')]);
    }

    if ($inquiry_type === 'freie_anfrage' && empty($custom_title)) {
        $custom_title = __('Freie Geschäftsanfrage / Inhouse', 'custom-crm');
    }

    $success = crm_link_entry_target($entry_id, [
        'inquiry_type' => $inquiry_type,
        'course_id'    => $course_id,
        'custom_title' => $custom_title,
        'start_date'   => $start_date,
        'end_date'     => $end_date,
        'note'         => $note,
    ]);

    if (!$success) {
        wp_send_json_error(['message' => __('Fehler beim Speichern der Verknüpfung.', 'custom-crm')]);
    }

    // Statuszeile erneut laden
    $status_row = crm_get_entry_status($entry_id);

    // HTML-Widget für die Tabelle rendern
    $widget_html = crm_render_entry_course_widget(
        $entry_id,
        $status_row,
        ($inquiry_type === 'course' ? $course_id : 0),
        $custom_title ?: ($course_id ? get_the_title($course_id) : ''),
        $status_row['course_start_date'] ?? $start_date,
        $status_row['course_end_date'] ?? $end_date
    );

    $spickzettel_html = '';
    if (function_exists('crm_render_screen2_spickzettel')) {
        $spickzettel_html = crm_render_screen2_spickzettel($entry_id, ($inquiry_type === 'course' ? $course_id : 0));
    }

    $course_display = ($inquiry_type === 'freie_anfrage') ? $custom_title : get_the_title($course_id);

    wp_send_json_success([
        'message'          => $inquiry_type === 'freie_anfrage'
            ? sprintf(__('Erfolgreich als freie Geschäftsanfrage verknüpft: %s', 'custom-crm'), $custom_title)
            : sprintf(__('Erfolgreich mit Kurs verknüpft: %s', 'custom-crm'), get_the_title($course_id)),
        'entry_id'         => $entry_id,
        'course_id'        => ($inquiry_type === 'course' ? $course_id : 0),
        'course_title'     => $course_display,
        'inquiry_type'     => $inquiry_type,
        'custom_title'     => $custom_title,
        'widget_html'      => $widget_html,
        'spickzettel_html' => $spickzettel_html,
    ]);
});
