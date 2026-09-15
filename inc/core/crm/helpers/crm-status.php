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
    define('CRM_DB_VERSION', '1.3');
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

    // 1. Current status table (with persistent course dates snapshot)
    $sql_status = "CREATE TABLE $table_status (
        entry_id bigint(20) NOT NULL,
        form_id bigint(20) NOT NULL DEFAULT 60468,
        status_key varchar(60) NOT NULL,
        status_label varchar(100) NOT NULL,
        status_date datetime NOT NULL,
        course_id bigint(20) DEFAULT NULL,
        course_start_date varchar(50) DEFAULT NULL,
        course_end_date varchar(50) DEFAULT NULL,
        note text DEFAULT NULL,
        updated_by bigint(20) NOT NULL DEFAULT 0,
        PRIMARY KEY  (entry_id),
        KEY status_key (status_key),
        KEY status_date (status_date),
        KEY course_id (course_id)
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
 * Returns the 2 primary action keys and remaining secondary action keys based on entry status.
 *
 * @param string $status_key
 * @return array ['primary' => string[], 'secondary' => string[]]
 */
function crm_get_actions_for_status($status_key)
{
    $all_keys = [
        'xsieben_angebot_und_kurszeiten',
        'xsieben_offer',
        'xsieben_kurszeitenbestaetigung',
        'xsieben_teilnahmebestaetigung',
        'xsieben_diplom',
    ];

    switch ($status_key) {
        case 'angebot_und_kurszeiten_gesendet':
        case 'angemeldet':
            // Both Angebot and KB were sent or customer booked.
            // Obsolete: Angebot & KB, Angebot, KB.
            // Next 2 steps: TB and Diplom!
            $primary = ['xsieben_teilnahmebestaetigung', 'xsieben_diplom'];
            break;

        case 'teilnahmebestaetigung_gesendet':
            // TB sent, next is Diplom, secondary is TB (to update/resend)
            $primary = ['xsieben_diplom', 'xsieben_teilnahmebestaetigung'];
            break;

        case 'diplom_gesendet':
        case 'abgeschlossen':
            // Completed: show Diplom and TB
            $primary = ['xsieben_diplom', 'xsieben_teilnahmebestaetigung'];
            break;

        case 'angebot_gesendet':
            // ONLY Angebot was sent. Davor/danach können es immer eigene E-Mails sein:
            // KB (Kurszeitenbestätigung) can now be sent as its own separate email!
            $primary = ['xsieben_kurszeitenbestaetigung', 'xsieben_teilnahmebestaetigung'];
            break;

        case 'kurszeitenbestaetigung_gesendet':
            // ONLY KB was sent. Angebot can now be sent as its own separate email!
            $primary = ['xsieben_offer', 'xsieben_teilnahmebestaetigung'];
            break;

        case 'neu':
        case 'angebot_erstellt':
        case 'nachfassen':
        case 'in_bearbeitung':
        case 'storniert':
        default:
            // NICHTS ist versendet!
            // Erst wenn etwas tatsächlich VERSENDET ist, fallen Aktionen weg.
            // Davor können es immer eigene E-Mails sein: Kombi (Angebot & KB) oder Einzelschritte.
            $primary = ['xsieben_angebot_und_kurszeiten', 'xsieben_offer'];
            break;
    }

    $secondary = array_values(array_diff($all_keys, $primary));

    return [
        'primary'   => $primary,
        'secondary' => $secondary,
    ];
}

/**
 * Renders the modern action buttons (always 2 primary steps + optional more menu).
 *
 * @param int $entry_id
 * @param int $course_id
 * @param string $status_key
 * @return string HTML
 */
function crm_render_entry_actions($entry_id, $course_id, $status_key = 'neu')
{
    if (!function_exists('crm_get_actions_config')) {
        return '';
    }

    $actions_config = crm_get_actions_config();
    $flow = crm_get_actions_for_status($status_key);

    $entry_id  = absint($entry_id);
    $course_id = absint($course_id);

    // WordPress Backend Theme Color Mapping
    $action_color_classes = [
        'xsieben_angebot_und_kurszeiten' => 'crm-btn-wp-blue',   // WP Primary Blue
        'xsieben_offer'                  => 'crm-btn-wp-cyan',   // WP Teal / Cyan
        'xsieben_kurszeitenbestaetigung' => 'crm-btn-wp-purple', // WP Admin Purple
        'xsieben_teilnahmebestaetigung'  => 'crm-btn-wp-green',  // WP Success Green
        'xsieben_diplom'                 => 'crm-btn-wp-gold',   // WP Award Gold / Amber
    ];

    ob_start();
    ?>
    <div class="crm-actions-wrap" data-entry-id="<?php echo esc_attr($entry_id); ?>">
        <div class="crm-primary-actions">
            <?php
            foreach ($flow['primary'] as $key) :
                if (!isset($actions_config[$key])) continue;
                $config = $actions_config[$key];
                $color_class = isset($action_color_classes[$key]) ? $action_color_classes[$key] : 'crm-btn-wp-blue';
            ?>
                <button type="button"
                    class="button crm-action-btn crm-btn-action <?php echo esc_attr($color_class); ?>"
                    data-action="<?php echo esc_attr($key); ?>"
                    data-entry-id="<?php echo esc_attr($entry_id); ?>"
                    data-course-id="<?php echo esc_attr($course_id); ?>"
                    data-context="<?php echo esc_attr($key); ?>"
                    title="<?php echo esc_attr($config['label']); ?>">
                    <?php echo esc_html($config['button_label']); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($flow['secondary'])) : ?>
            <div class="crm-more-actions-dropdown">
                <button type="button"
                    class="crm-more-toggle-btn"
                    title="<?php esc_attr_e('Weitere Aktionen anzeigen', 'custom-crm'); ?>"
                    aria-haspopup="true"
                    aria-expanded="false">
                    <span class="dashicons dashicons-ellipsis"></span>
                </button>
                <div class="crm-more-actions-menu">
                    <div class="crm-more-menu-header"><?php esc_html_e('Weitere Aktionen', 'custom-crm'); ?></div>
                    <?php foreach ($flow['secondary'] as $sec_key) :
                        if (!isset($actions_config[$sec_key])) continue;
                        $sec_config = $actions_config[$sec_key];
                        $sec_color_class = isset($action_color_classes[$sec_key]) ? $action_color_classes[$sec_key] : 'crm-btn-wp-blue';
                    ?>
                        <button type="button"
                            class="crm-more-menu-item crm-action-btn"
                            data-action="<?php echo esc_attr($sec_key); ?>"
                            data-entry-id="<?php echo esc_attr($entry_id); ?>"
                            data-course-id="<?php echo esc_attr($course_id); ?>"
                            data-context="<?php echo esc_attr($sec_key); ?>"
                            title="<?php echo esc_attr($sec_config['label']); ?>">
                            <span class="crm-more-item-label"><?php echo esc_html($sec_config['label']); ?></span>
                            <span class="crm-more-item-badge <?php echo esc_attr($sec_color_class); ?>"><?php echo esc_html($sec_config['button_label']); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

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
 * Ermittelt die Anzahl der vorhandenen Snapshots für einen Eintrag.
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
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE entry_id = %d", $entry_id));
}

/**
 * Batch-Abfrage für die Anzahl vorhandener Snapshots mehrerer Einträge in einem einzigen Query (verhindert N+1).
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

    return $counts;
}

/**
 * Rendert das HTML für das Snapshot-Archiv eines Eintrags.
 *
 * @param int $entry_id
 * @return string HTML-Ausgabe
 */
function crm_render_snapshots_html($entry_id)
{
    $snapshots = crm_get_entry_snapshots($entry_id);
    if (empty($snapshots)) {
        return '<div style="padding: 24px; text-align: center; color: #64748b; font-size: 13px;">
            <span class="dashicons dashicons-archive" style="font-size: 32px; width: 32px; height: 32px; display: block; margin: 0 auto 8px; opacity: 0.5;"></span>
            Für diesen Eintrag wurden bisher noch keine Dokumente versendet oder archiviert.
        </div>';
    }

    $doc_labels = [
        'angebot'             => 'Kursangebot',
        'kb'                  => 'Kurszeitenbestätigung',
        'angebot_kb'          => 'Angebot & Kurszeitenbestätigung',
        'angebot_kurszeiten'  => 'Angebot & Kurszeitenbestätigung',
        'xsieben_angebot'     => 'Kursangebot',
        'xsieben_kurszeitenbestaetigung' => 'Kurszeitenbestätigung',
        'xsieben_angebot_kurszeiten'     => 'Angebot & Kurszeitenbestätigung',
        'xsieben_angebot_und_kurszeiten' => 'Angebot & Kurszeitenbestätigung',
        'anmeldung'           => 'Anmeldebestätigung',
        'tb'                  => 'Teilnahmebestätigung',
        'diplom'              => 'Diplom / Zertifikat',
        'invoice'             => 'Honorarnote',
    ];

    $html = '<div class="crm-snapshots-container" style="display: flex; flex-direction: column; gap: 14px;">';

    foreach ($snapshots as $snap) {
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

        $html .= '<div class="crm-snapshot-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">';
        
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
                $pname = $pdf_names[$idx] ?? 'Archiviertes PDF ' . ($idx + 1);
                $html .= sprintf(
                    '<a href="%s" target="_blank" class="button button-small" style="display: inline-flex; align-items: center; gap: 4px; color: #007C90; border-color: #007C90;"><span class="dashicons dashicons-pdf" style="font-size: 16px; width: 16px; height: 16px;"></span> %s</a>',
                    esc_url($purl),
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

    $html .= '</div>'; // End Container

    return $html;
}
