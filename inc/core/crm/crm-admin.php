<?php

/**
 * ===================================================================
 * Custom CRM Functions for WordPress
 * ===================================================================
 *
 * This file creates a CRM admin page to manage WPForms entries.
 *
 * Features:
 * - Displays form entries in a paginated table.
 * - Links entries to a 'courses' custom post type.
 * - Provides AJAX-powered actions for each entry (e.g., generate PDF, send email).
 * - Includes client-side search and sort functionality for the entry table.
 * - Optimized and refactored for performance and maintainability.
 *
 */


// --- Core Setup & Helpers ---
if (!defined('CRM_VERSION')) {
    define('CRM_VERSION', '2.18.17');
}

require_once __DIR__ . '/helpers/crm-cache.php';
require_once __DIR__ . '/helpers/crm-status.php';
require_once __DIR__ . '/helpers/crm-pdf-sections.php';
require_once __DIR__ . '/helpers/crm-email-sections.php';
require_once __DIR__ . '/helpers/crm-ai-client.php';
require_once __DIR__ . '/crm-settings.php';

/**
 * Get the configuration for all possible CRM actions.
 * Consolidates action definitions to a single source of truth.
 *
 * @return array
 */
function crm_get_actions_config()
{
    return [
        'xsieben_teilnahmebestaetigung'  => ['function' => 'xsieben_teilnahmebestaetigung_pdf', 'label' => __('Attendance Confirmation', 'custom-crm'), 'button_label' => __('TB', 'custom-crm')],
        'xsieben_kurszeitenbestaetigung' => ['function' => 'xsieben_kurszeitenbestaetigung_pdf', 'label' => __('Course Times Confirmation', 'custom-crm'), 'button_label' => __('KB', 'custom-crm')],
        'xsieben_offer'                  => ['function' => 'xsieben_offer_pdf', 'label' => __('Angebot', 'custom-crm'), 'button_label' => __('Angebot', 'custom-crm')],
        'xsieben_angebot_und_kurszeiten'      => ['function' => 'xsieben_angebot_kurszeiten_pdf', 'label' => __('Angebot und Kurszeiten', 'custom-crm'), 'button_label' => __('Angebot & KB', 'custom-crm')],
        'xsieben_invoice'                => ['function' => 'xsieben_invoice_pdf', 'label' => __('Honorarnote', 'custom-crm'), 'button_label' => __('HN', 'custom-crm')],
        // 'xsieben_mailer'                 => ['function' => 'xsieben_mailer', 'label' => __('Email', 'custom-crm'), 'button_label' => __('E-mail', 'custom-crm')],
        'xsieben_diplom'                 => ['function' => 'xsieben_diplom_pdf', 'label' => __('Diplom', 'custom-crm'), 'button_label' => __('Diplom', 'custom-crm')],
        // 'view_entry'                     => ['function' => 'xsieben_view_entry', 'label' => __('View Entry Details', 'custom-crm'), 'button_label' => __('Details', 'custom-crm')],
    ];
}

/**
 * Add the CRM admin page to the WordPress menu.
 */
add_action('admin_menu', function () {
    if (current_user_can('manage_options')) {
        add_menu_page(
            __('CRM', 'custom-crm'),
            __('CRM', 'custom-crm'),
            'manage_options',
            'crm',
            'render_crm_admin_page', // This function will render the page content
            'dashicons-groups',
            25
        );

        // Submenu: Übersicht / Einträge
        add_submenu_page(
            'crm',
            __('CRM Einträge', 'custom-crm'),
            __('Einträge', 'custom-crm'),
            'manage_options',
            'crm',
            'render_crm_admin_page'
        );

        // Submenu: E-Mail Editor
        add_submenu_page(
            'crm',
            __('E-Mail Editor', 'custom-crm'),
            __('E-Mail Editor', 'custom-crm'),
            'manage_options',
            'crm-emails',
            'render_crm_settings_page'
        );

        // Submenu: PDF Editor
        add_submenu_page(
            'crm',
            __('PDF Editor', 'custom-crm'),
            __('PDF Editor', 'custom-crm'),
            'manage_options',
            'crm-pdf',
            'render_crm_settings_page'
        );

        // Submenu: Einstellungen
        add_submenu_page(
            'crm',
            __('CRM Einstellungen', 'custom-crm'),
            __('Einstellungen', 'custom-crm'),
            'manage_options',
            'crm-settings',
            'render_crm_settings_page'
        );
    }
});

/**
 * Fallback enqueue: ensures CRM CSS and JS are always loaded even if deploying ONLY the crm folder.
 */
add_action('admin_enqueue_scripts', function ($hook) {
    $crm_pages = ['crm', 'crm-settings', 'crm-emails', 'crm-pdf'];
    if (isset($_GET['page']) && in_array($_GET['page'], $crm_pages, true)) {
        // 1. Isolate CRM pages from conflicting / failing 3rd-party scripts
        // a) auto-focus-keyword-for-seo throws 'Uncaught ReferenceError: php_vars is not defined'
        wp_dequeue_script('afkw__metabox-script');
        wp_dequeue_style('afkw__styles');
        wp_deregister_script('afkw__metabox-script');
        wp_deregister_style('afkw__styles');

        // b) code-is-passion-libraries-plugin assets fail with 403 Forbidden on nginx mu-plugins
        $cis_handles = [
            'cis_post_selector', 'cis_post_selector_style',
            'cis_admin_image_upload', 'cis_admin_image_selector_style',
            'cis_admin_video_upload', 'cis_admin_video_selector_style',
            'cis_admin_anyfile_upload', 'cis_admin_anyfile_selector_style',
            'cis-admin-repeater', 'dubfriend-jquery-repeater', 'cis-admin-repeater-style',
            'cis_admin_style', 'cis-js-globals', 'cis_il8n_admin_style',
            'lib-cis-il8n', 'cis-seo-admin-css', 'cis-seo-admin-js',
            'spinplusmin', 'cis-gdpr-footer', 'cis_parallax_teaser_widget',
            'cis_editor_widget', 'cis-lib-ass'
        ];
        foreach ($cis_handles as $handle) {
            wp_dequeue_script($handle);
            wp_dequeue_style($handle);
            wp_deregister_script($handle);
            wp_deregister_style($handle);
        }

        wp_enqueue_media();
        $asset_ver = function_exists('crm_get_asset_version') ? crm_get_asset_version() : CRM_VERSION;

        // Ensure fresh script registration with dynamic cache buster version
        wp_deregister_script('custom-crm-admin');
        wp_enqueue_script(
            'custom-crm-admin',
            get_template_directory_uri() . '/inc/core/crm/assets/crm-admin.js',
            ['jquery', 'jquery-ui-sortable'],
            $asset_ver,
            true
        );
        wp_localize_script('custom-crm-admin', 'crmData', [
            'ajaxUrl'          => admin_url('admin-ajax.php'),
            'nonce'            => wp_create_nonce('crm_ajax_nonce'),
            'autoJsCacheClean' => function_exists('crm_is_js_cache_clean_enabled') ? crm_is_js_cache_clean_enabled() : true,
            'cacheVersion'     => function_exists('crm_get_js_cache_version') ? crm_get_js_cache_version() : '1',
            'assetVersion'     => $asset_ver,
        ]);
        wp_localize_script('custom-crm-admin', 'xSiebenAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('x_sieben_mailer_nonce'),
        ]);

        wp_deregister_style('crm-admin-styles');
        wp_enqueue_style(
            'crm-admin-styles',
            get_template_directory_uri() . '/inc/core/crm/css/crm-admin.css',
            [],
            $asset_ver
        );

        if (in_array($_GET['page'], ['crm-settings', 'crm-emails', 'crm-pdf'], true)) {
            $all_fields  = get_option('crm_custom_fields', []);
            $field_count = !empty($all_fields) && is_array($all_fields) ? max(array_keys($all_fields)) + 1 : 100;
            $curr_user   = wp_get_current_user();

            wp_enqueue_style(
                'crm-settings-styles',
                get_template_directory_uri() . '/inc/core/crm/css/crm-settings.css',
                ['crm-admin-styles'],
                $asset_ver
            );

            wp_enqueue_script(
                'crm-settings-scripts',
                get_template_directory_uri() . '/inc/core/crm/assets/crm-settings.js',
                ['jquery', 'jquery-ui-sortable', 'custom-crm-admin'],
                $asset_ver,
                true
            );

            wp_localize_script('crm-settings-scripts', 'crmSettingsData', [
                'ajaxUrl'           => admin_url('admin-ajax.php'),
                'nonce'             => wp_create_nonce('crm_ajax_nonce'),
                'nonceSaveField'    => wp_create_nonce('save_crm_field_individual'),
                'noncePdfPreview'   => wp_create_nonce('crm_pdf_preview_nonce'),
                'fieldCount'        => $field_count,
                'currentUserEmail'  => ($curr_user && !empty($curr_user->user_email)) ? $curr_user->user_email : '',
                'i18n'              => [
                    'mediaError'        => __('Die WordPress Medienverwaltung konnte nicht geladen werden.', 'custom-crm'),
                    'chooseLogo'        => __('Logo auswählen oder hochladen', 'custom-crm'),
                    'useLogo'           => __('Als Logo verwenden', 'custom-crm'),
                    'confirmDelete'     => __('Diesen Textbaustein wirklich löschen?', 'custom-crm'),
                    'saveField'         => __('Feld speichern', 'custom-crm'),
                    'newField'          => __('Neues Feld', 'custom-crm'),
                    'saving'            => __('Speichern...', 'custom-crm'),
                    'saved'             => __('Gespeichert!', 'custom-crm'),
                    'applyOrder'        => __('Reihenfolge anwenden', 'custom-crm'),
                    'applyEmailOrder'   => __('E-Mail-Reihenfolge anwenden', 'custom-crm'),
                    'serverError'       => __('Serverfehler', 'custom-crm'),
                    'errorSaving'       => __('Fehler beim Speichern', 'custom-crm'),
                    'previewError'      => __('Fehler beim Generieren der Vorschau.', 'custom-crm'),
                    'copied'            => __('Kopiert!', 'custom-crm'),
                    'copyHtml'          => __('HTML kopieren', 'custom-crm'),
                    'sending'           => __('Senden...', 'custom-crm'),
                    'testMailSuccess'   => __('Test-Mail erfolgreich versendet!', 'custom-crm'),
                    'testMailError'     => __('Fehler beim Versand der Test-Mail.', 'custom-crm'),
                    'promptTestEmail'   => __('An welche E-Mail-Adresse soll die Test-Vorschau gesendet werden?', 'custom-crm'),
                    'noHtmlAvailable'   => __('Kein HTML-Inhalt verfügbar. Bitte Vorschau neu laden.', 'custom-crm'),
                    'clearingCache'     => __('Leeren...', 'custom-crm'),
                    'cacheInvalidating' => __('Cache wird invalidiert...', 'custom-crm'),
                    'cacheCleared'      => __('JS-Cache erfolgreich geleert!', 'custom-crm'),
                    'cacheError'        => __('Fehler beim Leeren des Caches.', 'custom-crm'),
                ]
            ]);
        }
    }
}, 99);

// Late cleanup to prevent late enqueues from overriding isolation
add_action('admin_print_scripts', function () {
    $crm_pages = ['crm', 'crm-settings', 'crm-emails', 'crm-pdf'];
    if (isset($_GET['page']) && in_array($_GET['page'], $crm_pages, true)) {
        wp_dequeue_script('afkw__metabox-script');
        wp_deregister_script('afkw__metabox-script');
        $cis_handles = [
            'cis_post_selector', 'cis_admin_image_upload', 'cis_admin_video_upload',
            'cis_admin_anyfile_upload', 'cis-admin-repeater', 'dubfriend-jquery-repeater',
            'cis-js-globals', 'lib-cis-il8n', 'cis-seo-admin-js', 'spinplusmin',
            'cis-gdpr-footer', 'cis_parallax_teaser_widget', 'cis_editor_widget', 'cis-lib-ass'
        ];
        foreach ($cis_handles as $handle) {
            wp_dequeue_script($handle);
            wp_deregister_script($handle);
        }
    }
}, 1000);

// Safeguard against missing global variables on CRM pages (e.g. php_vars from 3rd party plugins)
add_action('admin_head', function () {
    $crm_pages = ['crm', 'crm-settings', 'crm-emails', 'crm-pdf'];
    if (isset($_GET['page']) && in_array($_GET['page'], $crm_pages, true)) {
        echo '<script>window.php_vars = window.php_vars || { disable_afk: "0", disable_auto_sync: "0", blacklist: "0" };</script>' . "\n";
    }
}, 1);




/**
 * Finds the ID of a course by its exact title, using a more efficient database query.
 * Caches results within a single request to avoid redundant database calls.
 *
 * @param string $title The course title to search for.
 * @return int|false The course ID if found, otherwise false.
 */
function find_course_id_by_title_exact($title)
{
    // Use a static variable to cache results during a single request
    static $course_cache = [];
    $trimmed_title = trim($title);

    if (empty($trimmed_title)) {
        return false;
    }

    if (isset($course_cache[$trimmed_title])) {
        return $course_cache[$trimmed_title];
    }

    global $wpdb;
    // This direct query is much faster than fetching all posts and looping in PHP.
    $course_id = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_title = %s",
        'courses',
        'publish',
        $trimmed_title
    ));

    // Fallback for titles that might have slight variations (e.g., whitespace issues)
    // For maximum performance, it's best to ensure the title in the form entry matches exactly.
    if (!$course_id) {
        $normalized_title_to_find = normalize_string($trimmed_title);
        // This fallback is still inefficient. A better long-term solution is to save a normalized
        // title in post_meta on course save and query that meta field directly.
        $all_courses = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
            'courses',
            'publish'
        ));
        foreach ($all_courses as $course) {
            if (normalize_string($course->post_title) === $normalized_title_to_find) {
                $course_id = (int) $course->ID;
                break;
            }
        }
    }

    $course_cache[$trimmed_title] = $course_id ? (int) $course_id : false;
    return $course_cache[$trimmed_title];
}


/**
 * Helper to get a field value from a WPForms decoded fields array.
 *
 * @param array $fields Decoded entry fields.
 * @param string $name The name of the field to find.
 * @return string The field value.
 */
/**
 * Sucht fehlertolerant nach einem Feldwert anhand von Namen und gängigen Synonymen/Aliassen.
 *
 * @param array $fields
 * @param string $name
 * @return string
 */
function crm_match_field_value($fields, $name)
{
    if (empty($fields) || !is_array($fields)) {
        return '';
    }

    $aliases = [
        'anrede'           => ['anrede', 'salutation', 'geschlecht', 'herr/frau', 'anrede / titel'],
        'vorname'          => ['vorname', 'first name', 'firstname', 'rufname', 'vorname des teilnehmers'],
        'nachname'         => ['nachname', 'last name', 'lastname', 'familienname', 'zuname', 'nachname des teilnehmers'],
        'titel'            => ['titel', 'title', 'akademischer grad', 'akademischer titel'],
        'e-mail'           => ['e-mail', 'email', 'e-mail-adresse', 'email-adresse', 'ihre e-mail', 'ihre e-mail-adresse', 'mail', 'kontakt-email'],
        'telefon'          => ['telefon', 'telefonnummer', 'phone', 'tel', 'mobil', 'handynummer', 'mobilnummer', 'ihre telefonnummer'],
        'firma'            => ['firma', 'unternehmen', 'organisation', 'company', 'firmenname', 'arbeitgeber', 'dienstgeber'],
        'straße'           => ['straße', 'strasse', 'street', 'adresse', 'anschrift', 'straße und hausnummer', 'strasse und hausnummer'],
        'plz'              => ['plz', 'postleitzahl', 'zip', 'zipcode', 'postal code'],
        'ort'              => ['ort', 'stadt', 'city', 'wohnort'],
        'verborgenes feld' => ['verborgenes feld', 'kurs', 'kurstitel', 'ausgewählter kurs', 'gewählter kurs', 'kursname', 'seminar', 'lehrgang', 'schulung'],
        'kurs id'          => ['kurs id', 'kurs_id', 'course_id', 'course id', 'id des kurses'],
    ];

    $key = mb_strtolower(trim((string)$name), 'UTF-8');
    $candidates = $aliases[$key] ?? [$key];

    // 1. Exakter oder Alias-Treffer
    foreach ($fields as $field) {
        if (!isset($field['name'])) continue;
        $fname = mb_strtolower(trim((string)$field['name']), 'UTF-8');
        if (in_array($fname, $candidates, true)) {
            return is_array($field['value'] ?? '') ? implode(', ', $field['value']) : (string)($field['value'] ?? '');
        }
    }

    // 2. Substring-Treffer als sanfter Fallback
    foreach ($fields as $field) {
        if (!isset($field['name'])) continue;
        $fname = mb_strtolower(trim((string)$field['name']), 'UTF-8');
        foreach ($candidates as $cand) {
            if (mb_strlen($cand) >= 4 && mb_strpos($fname, $cand) !== false) {
                return is_array($field['value'] ?? '') ? implode(', ', $field['value']) : (string)($field['value'] ?? '');
            }
        }
    }

    return '';
}

function get_field_value($fields, $name)
{
    return crm_match_field_value($fields, $name);
}

// --- AJAX Handler ---

/**
 * Handles all CRM actions via AJAX for a responsive UI.
 */
add_action('wp_ajax_crm_entry_action', function () {
    // --- Security ---
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Unauthorized access.', 'custom-crm')]);
    }
    if (!check_ajax_referer('crm_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Security check failed.', 'custom-crm')]);
    }

    // --- Sanitize input ---
    $entry_id   = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
    $course_id  = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $action_key = isset($_POST['action_key']) ? sanitize_key($_POST['action_key']) : '';
    $context    = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : '';

    if (empty($entry_id) || empty($action_key)) {
        wp_send_json_error(['message' => __('Missing required parameters.', 'custom-crm')]);
    }

    $actions = crm_get_actions_config();

    // --- Validate action ---
    if (!isset($actions[$action_key]) || !function_exists($actions[$action_key]['function'])) {
        wp_send_json_error(['message' => __('Invalid action specified.', 'custom-crm')]);
    }

    try {
        // --- Execute action ---
        ob_start();

        // Call the associated function. Pass context as 3rd param if function supports it.
        $function = $actions[$action_key]['function'];
        $refFunc  = new ReflectionFunction($function);
        $paramCount = $refFunc->getNumberOfParameters();

        if ($action_key === 'xsieben_diplom') {
            $diplom_success = isset($_POST['diplom_success']) ? sanitize_text_field(wp_unslash($_POST['diplom_success'])) : null;
            call_user_func($function, $entry_id, $course_id, true, $diplom_success);
        } elseif ($action_key === 'xsieben_offer' || $action_key === 'xsieben_kurszeitenbestaetigung' || $action_key === 'xsieben_teilnahmebestaetigung' || $action_key === 'xsieben_angebot_und_kurszeiten') {
            $custom_sections = isset($_POST['custom_sections']) && is_array($_POST['custom_sections']) ? array_map('sanitize_key', $_POST['custom_sections']) : null;
            call_user_func($function, $entry_id, $course_id, true, $custom_sections);
        } elseif ($paramCount >= 3) {
            call_user_func($function, $entry_id, $course_id, $context);
        } else {
            call_user_func($function, $entry_id, $course_id);
        }

        $output = ob_get_clean();

            // Track PDF creation in CRM history (Audit-Trail)
            if (function_exists('crm_add_entry_status_history')) {
                $act_label = isset($actions[$action_key]['label']) ? $actions[$action_key]['label'] : $action_key;
                crm_add_entry_status_history(
                    $entry_id,
                    'pdf_' . $action_key,
                    sprintf(__('PDF %s erstellt', 'custom-crm'), $act_label),
                    sprintf(__('PDF generiert für Eintrag #%d (Kontext: %s)', 'custom-crm'), $entry_id, $context ?: $action_key)
                );
            }

        wp_send_json_success([
            'message' => sprintf(
                __('%s executed successfully for Entry ID: %d (Context: %s)', 'custom-crm'),
                esc_html($actions[$action_key]['label']),
                $entry_id,
                esc_html($context)
            ),
            'output'  => $output,
        ]);
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Exception caught: ' . $e->getMessage()]);
    }
});

/**
 * AJAX Handler: Speichert die geänderte Drag & Drop Abschnitt-Reihenfolge eines PDFs.
 */
add_action('wp_ajax_crm_save_pdf_section_order', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Nicht autorisierter Zugriff.', 'custom-crm')]);
    }

    $nonce = $_POST['nonce'] ?? ($_REQUEST['nonce'] ?? '');
    $nonce_valid = false;
    if (!empty($nonce)) {
        if (wp_verify_nonce($nonce, 'crm_ajax_nonce') || wp_verify_nonce($nonce, 'save_crm_settings') || wp_verify_nonce($nonce, 'crm_pdf_preview_nonce')) {
            $nonce_valid = true;
        }
    }
    if (!$nonce_valid) {
        wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen.', 'custom-crm')]);
    }

    $doc_type = sanitize_key($_POST['doc_type'] ?? 'angebot');
    $entry_id = !empty($_POST['entry_id']) ? intval($_POST['entry_id']) : null;
    $sections = isset($_POST['sections']) && is_array($_POST['sections']) ? $_POST['sections'] : [];

    if (empty($sections)) {
        wp_send_json_error(['message' => __('Keine Abschnitte zum Speichern übergeben.', 'custom-crm')]);
    }

    require_once __DIR__ . '/helpers/crm-pdf-sections.php';
    $saved = crm_save_pdf_section_order($doc_type, $sections, $entry_id);

    if ($saved) {
        // Audit-Trail vermerken falls eintragsbezogen
        if (!empty($entry_id) && function_exists('crm_add_entry_status_history')) {
            crm_add_entry_status_history(
                $entry_id,
                'pdf_sections_reordered',
                __('PDF-Abschnitte angepasst', 'custom-crm'),
                sprintf(__('Reihenfolge der PDF-Abschnitte für %s aktualisiert.', 'custom-crm'), strtoupper($doc_type))
            );
        }

        // Falls entry_id & course_id übergeben wurden: PDF sofort neu generieren und URL zurückgeben
        $pdf_url = '';
        $course_id = !empty($_POST['course_id']) ? intval($_POST['course_id']) : 0;
        if (!empty($entry_id) && !empty($course_id)) {
            require_once __DIR__ . '/crm-model.php';
            try {
                if ($doc_type === 'angebot') {
                    require_once __DIR__ . '/pdf/offer.php';
                    $pdf_url = xsieben_offer_pdf($entry_id, $course_id, false);
                } elseif ($doc_type === 'kb') {
                    require_once __DIR__ . '/pdf/kurszeitenbestaetigung.php';
                    $pdf_url = xsieben_kurszeitenbestaetigung_pdf($entry_id, $course_id, false);
                } elseif ($doc_type === 'tb') {
                    require_once __DIR__ . '/pdf/teilnamebestaetigung.php';
                    $pdf_url = xsieben_teilnahmebestaetigung_pdf($entry_id, $course_id, false);
                } elseif ($doc_type === 'diplom') {
                    require_once __DIR__ . '/pdf/diplom.php';
                    $pdf_url = xsieben_diplom_pdf($entry_id, $course_id, false);
                } elseif ($doc_type === 'invoice') {
                    require_once __DIR__ . '/pdf/invoice.php';
                    $pdf_url = xsieben_invoice_pdf($entry_id, $course_id, false);
                }
            } catch (\Throwable $e) {
                error_log('CRM PDF Section Reorder Error: ' . $e->getMessage());
            }
        }

        $cache_res = function_exists('crm_on_partial_cache_update')
            ? crm_on_partial_cache_update('pdf_' . $doc_type, $entry_id)
            : [];

        wp_send_json_success([
            'message'   => __('PDF-Abschnittsreihenfolge erfolgreich gespeichert.', 'custom-crm'),
            'doc_type'  => $doc_type,
            'entry_id'  => $entry_id,
            'course_id' => $course_id,
            'pdf_url'   => $pdf_url,
            'js_cache'  => $cache_res,
        ]);
    } else {
        wp_send_json_error(['message' => __('Fehler beim Speichern der Abschnittsreihenfolge.', 'custom-crm')]);
    }
});

/**
 * AJAX Handler: Setzt die Drag & Drop Abschnitt-Reihenfolge auf Systemstandard zurück.
 */
add_action('wp_ajax_crm_reset_pdf_section_order', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Nicht autorisierter Zugriff.', 'custom-crm')]);
    }

    $nonce = $_POST['nonce'] ?? ($_REQUEST['nonce'] ?? '');
    $nonce_valid = false;
    if (!empty($nonce)) {
        if (wp_verify_nonce($nonce, 'crm_ajax_nonce') || wp_verify_nonce($nonce, 'save_crm_settings') || wp_verify_nonce($nonce, 'crm_pdf_preview_nonce')) {
            $nonce_valid = true;
        }
    }
    if (!$nonce_valid) {
        wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen.', 'custom-crm')]);
    }

    $doc_type   = sanitize_key($_POST['doc_type'] ?? 'angebot');
    $entry_id   = !empty($_POST['entry_id']) ? intval($_POST['entry_id']) : null;
    $is_sidebar = !empty($_POST['is_sidebar']);

    require_once __DIR__ . '/helpers/crm-pdf-sections.php';
    crm_reset_pdf_section_order($doc_type, $entry_id);

    ob_start();
    crm_render_pdf_sections_manager($doc_type, $entry_id, $is_sidebar);
    $html = ob_get_clean();

    $pdf_url = '';
    $course_id = !empty($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    if (!empty($entry_id) && !empty($course_id)) {
        require_once __DIR__ . '/crm-model.php';
        try {
            if ($doc_type === 'angebot') {
                require_once __DIR__ . '/pdf/offer.php';
                $pdf_url = xsieben_offer_pdf($entry_id, $course_id, false);
            } elseif ($doc_type === 'kb') {
                require_once __DIR__ . '/pdf/kurszeitenbestaetigung.php';
                $pdf_url = xsieben_kurszeitenbestaetigung_pdf($entry_id, $course_id, false);
            } elseif ($doc_type === 'tb') {
                require_once __DIR__ . '/pdf/teilnamebestaetigung.php';
                $pdf_url = xsieben_teilnahmebestaetigung_pdf($entry_id, $course_id, false);
            } elseif ($doc_type === 'diplom') {
                require_once __DIR__ . '/pdf/diplom.php';
                $pdf_url = xsieben_diplom_pdf($entry_id, $course_id, false);
            } elseif ($doc_type === 'invoice') {
                require_once __DIR__ . '/pdf/invoice.php';
                $pdf_url = xsieben_invoice_pdf($entry_id, $course_id, false);
            }
        } catch (\Throwable $e) {
            error_log('CRM PDF Reset Section Reorder Error: ' . $e->getMessage());
        }
    }

    $cache_res = function_exists('crm_on_partial_cache_update')
        ? crm_on_partial_cache_update('pdf_' . $doc_type, $entry_id)
        : [];

    wp_send_json_success([
        'message'   => __('Reihenfolge erfolgreich auf Standard zurückgesetzt.', 'custom-crm'),
        'html'      => $html,
        'doc_type'  => $doc_type,
        'course_id' => $course_id,
        'pdf_url'   => $pdf_url,
        'js_cache'  => $cache_res,
    ]);
});

/**
 * AJAX Handler: Speichert die geänderte Drag & Drop Abschnitt-Reihenfolge einer E-Mail-Vorlage.
 */
add_action('wp_ajax_crm_save_email_section_order', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Nicht autorisierter Zugriff.', 'custom-crm')]);
    }

    $nonce = $_POST['nonce'] ?? ($_REQUEST['nonce'] ?? '');
    $nonce_valid = false;
    if (!empty($nonce)) {
        if (wp_verify_nonce($nonce, 'crm_ajax_nonce') || wp_verify_nonce($nonce, 'save_crm_settings') || wp_verify_nonce($nonce, 'crm_email_preview_nonce')) {
            $nonce_valid = true;
        }
    }
    if (!$nonce_valid) {
        wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen.', 'custom-crm')]);
    }

    $doc_type = sanitize_key($_POST['doc_type'] ?? 'angebot');
    $entry_id = !empty($_POST['entry_id']) ? intval($_POST['entry_id']) : null;
    $sections = isset($_POST['sections']) && is_array($_POST['sections']) ? $_POST['sections'] : [];

    if (empty($sections)) {
        wp_send_json_error(['message' => __('Keine Abschnitte zum Speichern übergeben.', 'custom-crm')]);
    }

    require_once __DIR__ . '/helpers/crm-email-sections.php';
    $saved = crm_save_email_section_order($doc_type, $sections, $entry_id);

    if ($saved) {
        if (!empty($entry_id) && function_exists('crm_add_entry_status_history')) {
            crm_add_entry_status_history(
                $entry_id,
                'email_sections_reordered',
                __('E-Mail-Abschnitte angepasst', 'custom-crm'),
                sprintf(__('Reihenfolge der E-Mail-Abschnitte für %s aktualisiert.', 'custom-crm'), strtoupper($doc_type))
            );
        }

        $cache_res = function_exists('crm_on_partial_cache_update')
            ? crm_on_partial_cache_update('email_' . $doc_type, $entry_id)
            : [];

        wp_send_json_success([
            'message'  => __('E-Mail-Abschnittsreihenfolge erfolgreich gespeichert.', 'custom-crm'),
            'doc_type' => $doc_type,
            'entry_id' => $entry_id,
            'js_cache' => $cache_res,
        ]);
    } else {
        wp_send_json_error(['message' => __('Fehler beim Speichern der E-Mail-Abschnittsreihenfolge.', 'custom-crm')]);
    }
});

/**
 * AJAX Handler: Setzt die Drag & Drop E-Mail-Abschnitte auf Systemstandard zurück.
 */
add_action('wp_ajax_crm_reset_email_section_order', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Nicht autorisierter Zugriff.', 'custom-crm')]);
    }

    $nonce = $_POST['nonce'] ?? ($_REQUEST['nonce'] ?? '');
    $nonce_valid = false;
    if (!empty($nonce)) {
        if (wp_verify_nonce($nonce, 'crm_ajax_nonce') || wp_verify_nonce($nonce, 'save_crm_settings') || wp_verify_nonce($nonce, 'crm_email_preview_nonce')) {
            $nonce_valid = true;
        }
    }
    if (!$nonce_valid) {
        wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen.', 'custom-crm')]);
    }

    $doc_type   = sanitize_key($_POST['doc_type'] ?? 'angebot');
    $entry_id   = !empty($_POST['entry_id']) ? intval($_POST['entry_id']) : null;
    $is_sidebar = !empty($_POST['is_sidebar']);

    require_once __DIR__ . '/helpers/crm-email-sections.php';
    crm_reset_email_section_order($doc_type, $entry_id);

    ob_start();
    crm_render_email_sections_manager($doc_type, $entry_id, $is_sidebar);
    $html = ob_get_clean();

    $cache_res = function_exists('crm_on_partial_cache_update')
        ? crm_on_partial_cache_update('email_' . $doc_type, $entry_id)
        : [];

    wp_send_json_success([
        'message'  => __('E-Mail-Abschnitte erfolgreich auf Standard zurückgesetzt.', 'custom-crm'),
        'html'     => $html,
        'doc_type' => $doc_type,
        'js_cache' => $cache_res,
    ]);
});

// --- Admin Page Rendering ---

/**
 * Renders the main CRM admin page.
 */
function render_crm_admin_page()
{
    // Initial checks and setup
    if (!function_exists('wpforms')) {
        echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__('WPForms plugin is not active. This page requires WPForms.', 'custom-crm') . '</p></div></div>';
        return;
    }

    $form_id = function_exists('crm_get_default_form_id') ? crm_get_default_form_id() : 60468;
    // Persist 'entries_per_page' via GET for pagination to work correctly after setting.
    $entries_per_page = isset($_GET['per_page']) ? max(30, intval($_GET['per_page'])) : 30;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $entries_per_page;

    // Process form submission for setting entries per page
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['entries_per_page'])) {
        $new_per_page = max(30, intval($_POST['entries_per_page']));
        // Redirect to a clean URL to prevent form re-submission issues
        wp_safe_redirect(admin_url('admin.php?page=crm&per_page=' . $new_per_page));
        exit;
    }

    // Fetch entries from WPForms
    $entries = wpforms()->entry->get_entries([
        'form_id' => $form_id,
        'number'  => $entries_per_page,
        'offset'  => $offset,
    ]);

    // Get total entry count for pagination
    $total_entries = wpforms()->entry->get_entries(['form_id' => $form_id, 'select' => 'COUNT(entry_id)'], true);
    $total_pages = ceil($total_entries / $entries_per_page);

?>
    <div class="wrap">
        <h1 style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <span><?php printf(esc_html__('CRM – Entries for Form ID: %d', 'custom-crm'), esc_html($form_id)); ?></span>
            <span class="crm-version-badge" style="font-size:12px; font-weight:600; background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; padding:3px 10px; border-radius:12px; display:inline-flex; align-items:center; gap:5px; box-shadow:0 1px 2px rgba(0,0,0,0.03);">
                <span class="dashicons dashicons-tag" style="font-size:14px; width:14px; height:14px; line-height:14px;"></span>
                Version <?php echo esc_html(CRM_VERSION); ?>
            </span>
        </h1>

        <div id="crm-ajax-notice-container"></div>

        <!-- SCREEN 2: DOKUMENTEN- & ANGEBOTS-EDITOR (Fokussierter Vollbild-Arbeitsbereich) -->
        <div id="crm-editor-view" style="display:none;">
            <div class="crm-editor-topbar">
                <div class="crm-editor-topbar-left">
                    <button type="button" class="button button-secondary crm-back-to-list-btn" title="<?php esc_attr_e('Zurück zur Anfragen-Übersicht', 'custom-crm'); ?>">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                        <span><?php esc_html_e('Zurück zur Übersicht', 'custom-crm'); ?></span>
                    </button>
                    <div class="crm-editor-breadcrumb">
                        <span class="crm-editor-entry-badge">Eintrag #<span id="crm-editor-entry-id-val">---</span></span>
                        <span class="crm-editor-client-name" id="crm-editor-client-name-val">Kunde</span>
                        <span class="crm-editor-divider">/</span>
                        <span class="crm-editor-course-title" id="crm-editor-course-title-val">Kurs</span>
                    </div>
                </div>
                <div class="crm-editor-topbar-right">
                    <button type="button" class="crm-editor-close-btn crm-back-to-list-btn" title="<?php esc_attr_e('Schließen & Zurück zur Übersicht', 'custom-crm'); ?>" aria-label="<?php esc_attr_e('Schließen', 'custom-crm'); ?>">&times;</button>
                </div>
            </div>

            <div id="crm-entry-details-container" style="margin-bottom: 20px;"></div>

            <div class="crm-editor-bottombar">
                <button type="button" class="button button-secondary crm-back-to-list-btn">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    <span><?php esc_html_e('Zurück zur Anfragen-Übersicht', 'custom-crm'); ?></span>
                </button>
            </div>
        </div>

        <!-- SCREEN 1: ANFRAGEN-ÜBERSICHT (Listen-Ansicht) -->
        <div id="crm-list-view">
            <div class="crm-controls" style="margin-bottom: 1em; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <form method="POST" action="<?php echo esc_url(admin_url('admin.php?page=crm')); ?>">
                <label for="entries_per_page"><?php esc_html_e('Entries per page (min. 30):', 'custom-crm'); ?> </label>
                <input type="number" min="30" name="entries_per_page" id="entries_per_page" value="<?php echo esc_attr($entries_per_page); ?>" style="width:80px;">
                <input type="submit" class="button" value="<?php esc_attr_e('Apply', 'custom-crm'); ?>">
            </form>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <?php $all_statuses_def = function_exists('crm_get_statuses') ? crm_get_statuses() : []; ?>
                <select id="crmStatusFilter" style="height:32px; font-size:13px; max-width:210px;">
                    <option value=""><?php esc_html_e('Alle Status anzeigen', 'custom-crm'); ?></option>
                    <?php foreach ($all_statuses_def as $sk => $sconf) : ?>
                        <option value="<?php echo esc_attr($sconf['label']); ?>"><?php echo esc_html($sconf['label']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="courseTableSearch" placeholder="<?php printf(esc_attr__('Search %d entries...', 'custom-crm'), $total_entries); ?>" style="width: 250px;">
            </div>
        </div>


        <?php if (empty($entries)) : ?>
            <p><?php esc_html_e('No entries found for this form.', 'custom-crm'); ?></p>
        <?php else : ?>
            <table class="widefat striped fixed js-sort-table">
                <thead>
                    <tr>
                        <th style="width:9%;"><?php esc_html_e('Datum der Anfrage', 'custom-crm'); ?></th>
                        <th style="width:21%;"><?php esc_html_e('Kunde', 'custom-crm'); ?></th>
                        <th style="width:29%;"><?php esc_html_e('Angefragter Kurs', 'custom-crm'); ?></th>
                        <th style="width:21%;"><?php esc_html_e('Status & Wann', 'custom-crm'); ?></th>
                        <th style="width:20%;"><?php esc_html_e('Actions', 'custom-crm'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $actions_config = crm_get_actions_config();
                    $entry_ids = !empty($entries) ? wp_list_pluck($entries, 'entry_id') : [];
                    $saved_statuses    = function_exists('crm_get_entries_statuses') ? crm_get_entries_statuses($entry_ids) : [];
                    $saved_snap_counts = function_exists('crm_get_entries_snapshots_counts') ? crm_get_entries_snapshots_counts($entry_ids) : [];

                    foreach ($entries as $entry) {
                        $fields = is_string($entry->fields) ? json_decode($entry->fields, true) : $entry->fields;
                        if (!is_array($fields)) continue;

                        $salutation = get_field_value($fields, 'Anrede');
                        $first_name = get_field_value($fields, 'Vorname');
                        $last_name = get_field_value($fields, 'Nachname');
                        $course_title = get_field_value($fields, 'Verborgenes Feld');
                        $title = get_field_value($fields, 'Titel');
                        $course_id = find_course_id_by_title_exact($course_title);

                        // Fallback: If not found by exact title, attempt resolution via 'Kurs ID' field
                        if (!$course_id) {
                            $kurs_id_raw = get_field_value($fields, 'Kurs ID');
                            if ($kurs_id_raw && preg_match('/(\d+)/', $kurs_id_raw, $m)) {
                                $candidate_id = intval($m[1]);
                                if (get_post_type($candidate_id) === 'courses') {
                                    $course_id = $candidate_id;
                                }
                            }
                        }

                        $entry_status = isset($saved_statuses[$entry->entry_id]) ? $saved_statuses[$entry->entry_id] : null;
                        $status_key = $entry_status ? $entry_status['status_key'] : 'neu';
                        $status_label = $entry_status ? $entry_status['status_label'] : ($all_statuses_def['neu']['label'] ?? 'Neu / Anfrage');
                        $status_date_raw = $entry_status ? $entry_status['status_date'] : $entry->date;
                        $status_date_formatted = date_i18n('d.m.Y, H:i', strtotime($status_date_raw));

                        // Persistent course dates snapshot:
                        // Once dates are stored with an inquiry, they remain immutable forever, even if the course dates change later!
                        $has_saved_course_dates = !empty($entry_status['course_start_date']) || !empty($entry_status['course_end_date']);

                        if ($has_saved_course_dates) {
                            $start_date_raw = $entry_status['course_start_date'];
                            $end_date_raw   = $entry_status['course_end_date'];
                            if (empty($course_id) && !empty($entry_status['course_id'])) {
                                $course_id = intval($entry_status['course_id']);
                            }
                        } else {
                            if ($course_id) {
                                $start_date_raw = get_post_meta($course_id, 'start_datum', true);
                                $end_date_raw   = get_post_meta($course_id, 'end_datum', true);

                                // Snapshot and persist now with this inquiry so it is frozen permanently
                                if (function_exists('crm_save_entry_course_dates')) {
                                    crm_save_entry_course_dates($entry->entry_id, $course_id, $start_date_raw, $end_date_raw);
                                }
                            } else {
                                $start_date_raw = '';
                                $end_date_raw   = '';
                            }
                        }

                        if ($course_id || !empty($start_date_raw) || !empty($end_date_raw)) {
                            if (!empty($start_date_raw) && !empty($end_date_raw)) {
                                $start_ts = strtotime($start_date_raw);
                                $end_ts   = strtotime($end_date_raw);
                                $date_display = date_i18n('d.m.y', $start_ts) . ' – ' . date_i18n('d.m.y', $end_ts);
                                $date_tooltip = sprintf(
                                    __('Kurszeitraum (bei Anfrage): %s bis %s', 'custom-crm'),
                                    date_i18n('d.m.Y', $start_ts),
                                    date_i18n('d.m.Y', $end_ts)
                                );
                            } elseif (!empty($start_date_raw)) {
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

                            $linked_course = sprintf(
                                '<div class="crm-course-widget">' .
                                    '<a href="%s" target="_blank" class="crm-course-title-link" title="%s">%s</a>' .
                                    '<div class="crm-course-dates-row">' .
                                        '<span class="crm-badge %s" title="%s">' .
                                            '<span class="dashicons dashicons-calendar-alt"></span>' .
                                            '<span class="crm-badge-date-text">%s</span>' .
                                        '</span>' .
                                    '</div>' .
                                '</div>',
                                esc_url($edit_link),
                                esc_attr($course_link_title),
                                esc_html($course_title),
                                esc_attr($date_badge_class),
                                esc_attr($date_tooltip),
                                esc_html($date_display)
                            );
                        } else {
                            $linked_course = sprintf(
                                '<div class="crm-course-widget">' .
                                    '<span class="crm-course-title-unlinked">%s</span>' .
                                    '<div class="crm-course-dates-row">' .
                                        '<span class="crm-badge crm-badge-warning" title="%s">' .
                                            '<span class="dashicons dashicons-warning"></span>' .
                                            '<span>%s</span>' .
                                        '</span>' .
                                    '</div>' .
                                '</div>',
                                esc_html($course_title ?: __('Keine Kursangabe', 'custom-crm')),
                                esc_attr__('Kein verknüpfter Kurs-Beitrag im System gefunden', 'custom-crm'),
                                esc_html__('Nicht verknüpft', 'custom-crm')
                            );
                        }

                        $client_name_parts = array_filter([$salutation, $title, $first_name, $last_name]);
                        $client_display_name = !empty($client_name_parts) ? implode(' ', $client_name_parts) : __('Unbekannter Kunde', 'custom-crm');

                        $entry_timestamp = !empty($entry->date) ? strtotime($entry->date) : time();
                        $formatted_date = date_i18n('d.m.y', $entry_timestamp);
                        $full_date_tooltip = date_i18n('d.m.Y, H:i', $entry_timestamp);

                        echo '<tr class="crm-entry-row" data-entry-id="' . esc_attr($entry->entry_id) . '" data-course-id="' . esc_attr($course_id ?: 0) . '" data-client-name="' . esc_attr($client_display_name) . '" data-course-title="' . esc_attr($course_title) . '">';
                        echo '<td class="crm-date-cell" data-sort="' . esc_attr($entry_timestamp) . '" title="' . esc_attr($full_date_tooltip) . '">' . esc_html($formatted_date) . '</td>';
                        echo '<td class="crm-client-cell"><strong>' . esc_html($client_display_name) . '</strong></td>';
                        echo '<td>' . wp_kses_post($linked_course) . '</td>';

                        // Status & Wann Column (Modern Interactive Pill & History)
                        echo '<td class="crm-status-cell" data-entry-id="' . esc_attr($entry->entry_id) . '">';
                        echo '  <div class="crm-status-widget">';
                        echo '    <div class="crm-status-header-row">';
                        echo '      <div class="crm-status-pill crm-status-' . esc_attr($status_key) . '" data-status="' . esc_attr($status_key) . '" title="' . esc_attr__('Klicken zum Ändern des Status', 'custom-crm') . '">';
                        echo '        <span class="crm-status-dot"></span>';
                        echo '        <span class="crm-status-label">' . esc_html($status_label) . '</span>';
                        echo '        <span class="crm-status-chevron dashicons dashicons-arrow-down-alt2"></span>';
                        echo '        <select class="crm-status-dropdown" data-entry-id="' . esc_attr($entry->entry_id) . '" title="' . esc_attr__('Status auswählen', 'custom-crm') . '">';
                        foreach ($all_statuses_def as $sk => $sconf) {
                            $selected = ($status_key === $sk) ? ' selected="selected"' : '';
                            echo '          <option value="' . esc_attr($sk) . '"' . $selected . '>' . esc_html($sconf['label']) . '</option>';
                        }
                        echo '        </select>';
                        echo '      </div>';
                        echo '      <button type="button" class="crm-history-btn" data-entry-id="' . esc_attr($entry->entry_id) . '" title="' . esc_attr__('Status-Verlauf & Historie anzeigen', 'custom-crm') . '" aria-label="' . esc_attr__('Verlauf', 'custom-crm') . '">';
                        echo '        <span class="dashicons dashicons-backup"></span>';
                        echo '      </button>';
                        $snap_count = isset($saved_snap_counts[$entry->entry_id]) ? $saved_snap_counts[$entry->entry_id] : (function_exists('crm_get_entry_snapshots_count') ? crm_get_entry_snapshots_count($entry->entry_id) : 0);
                        $snap_badge = ($snap_count > 0) ? '<span class="crm-snap-count-badge">' . $snap_count . '</span>' : '';
                        echo '      <button type="button" class="crm-snapshots-btn" data-entry-id="' . esc_attr($entry->entry_id) . '" title="' . esc_attr(sprintf(__('Dokument- & Daten-Archiv (%d Snapshots)', 'custom-crm'), $snap_count)) . '" aria-label="' . esc_attr__('Archiv', 'custom-crm') . '">';
                        echo '        <span class="dashicons dashicons-archive"></span>' . $snap_badge;
                        echo '      </button>';
                        echo '    </div>';
                        echo '    <div class="crm-status-meta" title="' . esc_attr__('Letzte Statusaktualisierung', 'custom-crm') . '">';
                        echo '      <span class="dashicons dashicons-clock"></span>';
                        echo '      <span class="crm-status-date-val">' . esc_html($status_date_formatted) . '</span>';
                        echo '    </div>';
                        echo '  </div>';
                        echo '</td>';

                        echo '<td class="crm-actions" data-entry-id="' . esc_attr($entry->entry_id) . '">';
                        echo function_exists('crm_render_entry_actions') ? crm_render_entry_actions($entry->entry_id, $course_id, $status_key) : '';
                        echo '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>

            <div class="tablenav-bottom">
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php printf(esc_html__('%d entries', 'custom-crm'), $total_entries); ?></span>
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg(['paged' => '%#%', 'per_page' => $entries_per_page]),
                        'format' => '?paged=%#%',
                        'current' => $current_page,
                        'total' => $total_pages,
                        'prev_text' => '&laquo; ' . esc_html__('Previous', 'custom-crm'),
                        'next_text' => esc_html__('Next', 'custom-crm') . ' &raquo;',
                        'type' => 'plain',
                    ]);
                    ?>
                </div>
            </div>
        <?php endif; ?>
        </div> <!-- /#crm-list-view -->

        <!-- CRM History Modal -->
        <div id="crm-history-modal-backdrop" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:99999; align-items:center; justify-content:center;">
            <div id="crm-history-modal" style="background:#fff; border-radius:8px; width:90%; max-width:550px; max-height:85vh; overflow-y:auto; padding:20px; box-shadow:0 10px 25px rgba(0,0,0,0.25); position:relative;">
                <div id="crm-history-modal-content">
                    <p style="text-align:center; padding:20px; color:#64748b;">⏳ Verlauf wird geladen...</p>
                </div>
            </div>
        </div>

        <!-- CRM Snapshots Modal -->
        <div id="crm-snapshots-modal-backdrop" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:99998; align-items:center; justify-content:center;">
            <div id="crm-snapshots-modal" style="background:#f8fafc; border-radius:10px; width:92%; max-width:720px; max-height:85vh; overflow-y:auto; padding:24px; box-shadow:0 20px 35px rgba(0,0,0,0.3); position:relative;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px;">
                    <h3 style="margin: 0; font-size: 16px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-archive" style="color: #007C90;"></span> <?php esc_html_e('Dokument- & Daten-Snapshots (Revisionssicher)', 'custom-crm'); ?>
                    </h3>
                    <button type="button" class="crm-close-snapshots-modal" style="background: none; border: none; font-size: 22px; color: #64748b; cursor: pointer; padding: 0 4px; line-height: 1;" title="<?php esc_attr_e('Schließen', 'custom-crm'); ?>">&times;</button>
                </div>
                <div id="crm-snapshots-modal-content">
                    <p style="text-align:center; padding:20px; color:#64748b;">⏳ Snapshots werden geladen...</p>
                </div>
            </div>
        </div>

        <!-- CRM Snapshot Detail Modal (für E-Mail-Vorschau oder JSON-Daten) -->
        <div id="crm-snapshot-detail-modal-backdrop" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:99999; align-items:center; justify-content:center;">
            <div id="crm-snapshot-detail-modal" style="background:#ffffff; border-radius:10px; width:92%; max-width:850px; max-height:88vh; display:flex; flex-direction:column; box-shadow:0 25px 50px rgba(0,0,0,0.35); position:relative; overflow:hidden;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding: 14px 20px; background: #f8fafc;">
                    <h3 id="crm-snapshot-detail-title" style="margin: 0; font-size: 15px; color: #1e293b; font-weight: 600;">
                        <?php esc_html_e('Snapshot Details', 'custom-crm'); ?>
                    </h3>
                    <button type="button" class="crm-close-snapshot-detail" style="background: none; border: none; font-size: 24px; color: #64748b; cursor: pointer; padding: 0 4px; line-height: 1;" title="<?php esc_attr_e('Schließen', 'custom-crm'); ?>">&times;</button>
                </div>
                <div id="crm-snapshot-detail-body" style="padding: 20px; overflow-y: auto; flex: 1;">
                    <!-- Dynamically populated -->
                </div>
            </div>
        </div>
    </div>

<?php
}
