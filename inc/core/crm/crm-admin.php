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
    define('CRM_VERSION', '2.18.87');
}

require_once __DIR__ . '/helpers/crm-cache.php';
require_once __DIR__ . '/helpers/crm-status.php';
require_once __DIR__ . '/helpers/crm-pdf-sections.php';
require_once __DIR__ . '/helpers/crm-email-sections.php';
require_once __DIR__ . '/helpers/crm-ai-client.php';
require_once __DIR__ . '/helpers/crm-views.php';
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
        'xsieben_anmeldebestaetigung'    => ['function' => 'xsieben_anmeldebestaetigung_pdf', 'label' => __('Anmeldebestätigung', 'custom-crm'), 'button_label' => __('AB', 'custom-crm')],
        'xsieben_antrittsbestaetigung'   => ['function' => 'xsieben_antrittsbestaetigung_pdf', 'label' => __('Antrittsmeldung', 'custom-crm'), 'button_label' => __('Antritt', 'custom-crm')],
        'xsieben_offer'                  => ['function' => 'xsieben_offer_pdf', 'label' => __('Angebot', 'custom-crm'), 'button_label' => __('Angebot', 'custom-crm')],
        'xsieben_angebot_und_kurszeiten' => ['function' => 'xsieben_angebot_kurszeiten_pdf', 'label' => __('Angebot und Kurszeiten', 'custom-crm'), 'button_label' => __('Angebot & KB', 'custom-crm')],
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

        // Submenu: Einstellungen
        add_submenu_page(
            'crm',
            __('CRM Einstellungen', 'custom-crm'),
            __('Einstellungen', 'custom-crm'),
            'manage_options',
            'crm-settings',
            'render_crm_settings_page'
        );

        // Submenu: Elemente & Bausteine (Unterpunkt von Einstellungen)
        add_submenu_page(
            'crm',
            __('Elemente & Bausteine', 'custom-crm'),
            __('— Elemente & Bausteine', 'custom-crm'),
            'manage_options',
            'crm-elements',
            'render_crm_settings_page'
        );

        // Submenu: E-Mail Editor (Unterpunkt von Einstellungen)
        add_submenu_page(
            'crm',
            __('E-Mail Editor', 'custom-crm'),
            __('— E-Mail Editor', 'custom-crm'),
            'manage_options',
            'crm-emails',
            'render_crm_settings_page'
        );

        // Submenu: PDF Editor (Unterpunkt von Einstellungen)
        add_submenu_page(
            'crm',
            __('PDF Editor', 'custom-crm'),
            __('— PDF Editor', 'custom-crm'),
            'manage_options',
            'crm-pdf',
            'render_crm_settings_page'
        );
    }
});

/**
 * Fallback enqueue: ensures CRM CSS and JS are always loaded even if deploying ONLY the crm folder.
 */
add_action('admin_enqueue_scripts', function ($hook) {
    $crm_pages = ['crm', 'crm-settings', 'crm-elements', 'crm-emails', 'crm-pdf'];
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
        $curr_user   = wp_get_current_user();
        $curr_email  = ($curr_user && !empty($curr_user->user_email)) ? $curr_user->user_email : '';
        $crm_test_em = get_option('crm_test_email') ?: ($curr_email ?: 'gajo@x-sieben.at');

        wp_localize_script('custom-crm-admin', 'crmData', [
            'ajaxUrl'            => admin_url('admin-ajax.php'),
            'nonce'              => wp_create_nonce('crm_ajax_nonce'),
            'autoJsCacheClean'   => function_exists('crm_is_js_cache_clean_enabled') ? crm_is_js_cache_clean_enabled() : true,
            'cacheVersion'       => function_exists('crm_get_js_cache_version') ? crm_get_js_cache_version() : '1',
            'assetVersion'       => $asset_ver,
            'currentUserEmail'   => $curr_email,
            'defaultTestEmail'   => $crm_test_em,
            'maxParallelWorkers' => 2,
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

        if (in_array($_GET['page'], ['crm-settings', 'crm-elements', 'crm-emails', 'crm-pdf'], true)) {
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
    $crm_pages = ['crm', 'crm-settings', 'crm-elements', 'crm-emails', 'crm-pdf'];
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

// Safeguard against missing global variables on CRM pages and style indented submenu items
add_action('admin_head', function () {
    $crm_pages = ['crm', 'crm-settings', 'crm-elements', 'crm-emails', 'crm-pdf'];
    if (isset($_GET['page']) && in_array($_GET['page'], $crm_pages, true)) {
        echo '<script>window.php_vars = window.php_vars || { disable_afk: "0", disable_auto_sync: "0", blacklist: "0" };</script>' . "\n";
    }
    ?>
    <style id="crm-admin-menu-subitems">
        #adminmenu #toplevel_page_crm .wp-submenu a[href*="page=crm-elements"],
        #adminmenu #toplevel_page_crm .wp-submenu a[href*="page=crm-emails"],
        #adminmenu #toplevel_page_crm .wp-submenu a[href*="page=crm-pdf"] {
            padding-left: 22px;
            font-size: 12px;
            opacity: 0.9;
        }
        #adminmenu #toplevel_page_crm .wp-submenu a[href*="page=crm-elements"]:hover,
        #adminmenu #toplevel_page_crm .wp-submenu a[href*="page=crm-emails"]:hover,
        #adminmenu #toplevel_page_crm .wp-submenu a[href*="page=crm-pdf"]:hover {
            opacity: 1;
        }
    </style>
    <?php
}, 1);

/**
 * Preserves custom HTML elements, spacing containers and inline styles in TinyMCE on CRM screens.
 * Ensures that empty spacers (e.g. <div style="..."></div>, <p>&nbsp;</p>, <br>) are not stripped on save or mode switch.
 * Strictly isolated: ONLY runs on CRM admin pages or for CRM field editors!
 *
 * @param array  $mceInit   TinyMCE configuration array.
 * @param string $editor_id ID of the editor instance.
 * @return array Modified configuration array.
 */
function crm_filter_tinymce_settings($mceInit, $editor_id = '')
{
    $crm_pages = ['crm', 'crm-settings', 'crm-elements', 'crm-emails', 'crm-pdf'];
    $is_crm = (isset($_GET['page']) && in_array($_GET['page'], $crm_pages, true))
        || (is_string($editor_id) && strpos($editor_id, 'crm_fields_') === 0);

    if ($is_crm && is_array($mceInit)) {
        $mceInit['verify_html']             = false;
        $mceInit['cleanup']                 = false;
        $mceInit['cleanup_on_startup']      = false;
        $mceInit['extended_valid_elements'] = 'div[*],span[*],p[*],br[*],hr[*],style[*],table[*],tr[*],td[*],th[*],tbody[*],thead[*],tfoot[*]';
        $mceInit['valid_children']          = '+body[style],+p[div|span|br]';
        $mceInit['remove_linebreaks']       = false;
        $mceInit['remove_trailing_brs']     = false;
        $mceInit['keep_styles']             = true;
    }
    return $mceInit;
}
add_filter('tiny_mce_before_init', 'crm_filter_tinymce_settings', 10, 2);




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
        'land'             => ['land', 'country', 'staat', 'herkunftsland', 'wohnsitzland'],
        'verborgenes feld' => ['verborgenes feld', 'kurs', 'kurstitel', 'ausgewählter kurs', 'gewählter kurs', 'kursname', 'seminar', 'lehrgang', 'schulung'],
        'kurs id'          => ['kurs id', 'kurs_id', 'course_id', 'course id', 'id des kurses'],
    ];

    $key = mb_strtolower(trim((string)$name), 'UTF-8');
    $candidates = $aliases[$key] ?? [$key];

    // Check WPForms composite address field first for address-related keys
    $is_street_key  = in_array($key, ['straße', 'strasse', 'street', 'adresse', 'anschrift', 'straße und hausnummer', 'strasse und hausnummer'], true);
    $is_zip_key     = in_array($key, ['plz', 'postleitzahl', 'zip', 'zipcode', 'postal code'], true);
    $is_city_key    = in_array($key, ['ort', 'stadt', 'city', 'wohnort'], true);
    $is_country_key = in_array($key, ['land', 'country', 'staat', 'herkunftsland', 'wohnsitzland'], true);
    $is_company_key = in_array($key, ['firma', 'company', 'unternehmen', 'organisation'], true);

    if ($is_street_key || $is_zip_key || $is_city_key || $is_country_key) {
        foreach ($fields as $field) {
            $ftype = isset($field['type']) ? (string)$field['type'] : '';
            $fid   = isset($field['id']) ? (int)$field['id'] : null;
            if ($ftype === 'address' || $fid === 35 || isset($field['address1'])) {
                if ($is_street_key && !empty($field['address1'])) {
                    return (string)$field['address1'];
                }
                if ($is_zip_key && !empty($field['postal'])) {
                    return (string)$field['postal'];
                }
                if ($is_city_key && !empty($field['city'])) {
                    return (string)$field['city'];
                }
                if ($is_country_key && !empty($field['country'])) {
                    return ($field['country'] === 'AT' ? 'Österreich' : (string)$field['country']);
                }
            }
        }
    }

    // Direct check for Company / Organisation (WPForms Field 25)
    if ($is_company_key) {
        foreach ($fields as $field) {
            $fid = isset($field['id']) ? (int)$field['id'] : null;
            if ($fid === 25) {
                $cval = is_array($field['value'] ?? '') ? implode(', ', $field['value']) : (string)($field['value'] ?? '');
                if ($cval === '' && !empty($field['first'])) {
                    $cval = (string)$field['first'];
                }
                return trim($cval);
            }
        }
    }

    // Exclude words for specific keys to avoid false positives
    $forbidden_substrings = [];
    if ($is_country_key) {
        $forbidden_substrings = ['förder', 'foerder', 'stelle', 'angebot', 'gutschein'];
    } elseif ($is_company_key) {
        $forbidden_substrings = ['privat', 'oder unternehmen', 'checkbox'];
    }

    // 1. Exakter oder Alias-Treffer
    foreach ($fields as $field) {
        if (!isset($field['name'])) continue;
        $fname = mb_strtolower(trim((string)$field['name']), 'UTF-8');

        $has_forbidden = false;
        foreach ($forbidden_substrings as $forb) {
            if (mb_strpos($fname, $forb) !== false) {
                $has_forbidden = true;
                break;
            }
        }
        if ($has_forbidden) continue;

        if (in_array($fname, $candidates, true)) {
            return is_array($field['value'] ?? '') ? implode(', ', $field['value']) : (string)($field['value'] ?? '');
        }
    }

    // 2. Substring-Treffer als sanfter Fallback (ausgenommen 'land' zur Vermeidung von Falschtreffern)
    if (!$is_country_key) {
        foreach ($fields as $field) {
            if (!isset($field['name'])) continue;
            $fname = mb_strtolower(trim((string)$field['name']), 'UTF-8');

            $has_forbidden = false;
            foreach ($forbidden_substrings as $forb) {
                if (mb_strpos($fname, $forb) !== false) {
                    $has_forbidden = true;
                    break;
                }
            }
            if ($has_forbidden) continue;

            foreach ($candidates as $cand) {
                if (mb_strlen($cand) >= 4 && mb_strpos($fname, $cand) !== false) {
                    return is_array($field['value'] ?? '') ? implode(', ', $field['value']) : (string)($field['value'] ?? '');
                }
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
        } elseif ($action_key === 'xsieben_offer' || $action_key === 'xsieben_kurszeitenbestaetigung' || $action_key === 'xsieben_teilnahmebestaetigung' || $action_key === 'xsieben_angebot_und_kurszeiten' || $action_key === 'xsieben_anmeldebestaetigung' || $action_key === 'xsieben_antrittsbestaetigung') {
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
 * AJAX Handler: Get Live Split Dossier for an entry.
 */
add_action('wp_ajax_crm_get_split_dossier', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Nicht autorisierter Zugriff.', 'custom-crm')]);
    }
    if (!check_ajax_referer('crm_ajax_nonce', 'nonce', false) && !check_ajax_referer('crm_ajax_nonce', 'security', false)) {
        wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen.', 'custom-crm')]);
    }

    $entry_id  = isset($_REQUEST['entry_id']) ? intval($_REQUEST['entry_id']) : 0;
    $course_id = isset($_REQUEST['course_id']) ? intval($_REQUEST['course_id']) : 0;

    if (!$entry_id) {
        wp_send_json_error(['message' => __('Ungültige Entry-ID.', 'custom-crm')]);
    }

    if (!function_exists('crm_render_split_dossier')) {
        require_once __DIR__ . '/helpers/crm-views.php';
    }

    $html = function_exists('crm_render_split_dossier') ? crm_render_split_dossier($entry_id, $course_id) : '';

    wp_send_json_success([
        'output'   => $html,
        'entry_id' => $entry_id,
    ]);
});

/**
 * AJAX Handler: Get More Kanban Entries (Infinite Scroll & Dynamic Batch Loading).
 */
add_action('wp_ajax_crm_get_more_kanban_entries', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Nicht autorisierter Zugriff.', 'custom-crm')]);
    }
    if (!check_ajax_referer('crm_ajax_nonce', 'nonce', false) && !check_ajax_referer('crm_ajax_nonce', 'security', false)) {
        wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen.', 'custom-crm')]);
    }

    $offset      = isset($_REQUEST['offset']) ? max(0, intval($_REQUEST['offset'])) : 0;
    $limit       = isset($_REQUEST['limit']) ? min(100, max(10, intval($_REQUEST['limit']))) : 30;
    $form_id_raw = isset($_REQUEST['form_id']) ? sanitize_text_field($_REQUEST['form_id']) : 'all';

    if (!function_exists('wpforms')) {
        wp_send_json_error(['message' => __('WPForms nicht verfügbar.', 'custom-crm')]);
    }

    if ($form_id_raw === 'all' || empty($form_id_raw)) {
        $entries = wpforms()->entry->get_entries([
            'number' => $limit,
            'offset' => $offset,
        ]);
        $total_entries = wpforms()->entry->get_entries(['select' => 'COUNT(entry_id)'], true);
    } else {
        $form_id = absint($form_id_raw);
        $entries = wpforms()->entry->get_entries([
            'form_id' => $form_id,
            'number'  => $limit,
            'offset'  => $offset,
        ]);
        $total_entries = wpforms()->entry->get_entries(['form_id' => $form_id, 'select' => 'COUNT(entry_id)'], true);
    }

    if (!function_exists('crm_render_kanban_card')) {
        require_once __DIR__ . '/helpers/crm-views.php';
    }
    if (!function_exists('crm_get_statuses')) {
        require_once __DIR__ . '/helpers/crm-status.php';
    }

    $all_statuses_def  = function_exists('crm_get_statuses') ? crm_get_statuses() : [];
    $entry_ids         = !empty($entries) ? wp_list_pluck($entries, 'entry_id') : [];
    $saved_statuses    = function_exists('crm_get_entries_statuses') ? crm_get_entries_statuses($entry_ids) : [];
    $saved_snap_counts = function_exists('crm_get_entries_snapshots_counts') ? crm_get_entries_snapshots_counts($entry_ids) : [];

    $columns = [
        'col_neu'    => ['neu', 'ki_vorbereitet'],
        'col_ready'  => ['versand_vorbereitet', 'ai_prepared', 'versandbereit'],
        'col_sent'   => ['angebot_gesendet', 'angebot_und_kurszeiten_gesendet', 'kurszeitenbestaetigung_gesendet', 'nachfassen', 'angebot_erstellt'],
        'col_booked' => ['angemeldet', 'gebucht', 'teilnahmebestaetigung_gesendet'],
        'col_done'   => ['abgeschlossen', 'diplom_gesendet', 'durchgefuehrt', 'storniert'],
    ];

    $cards_by_col = [
        'col_neu'    => [],
        'col_ready'  => [],
        'col_sent'   => [],
        'col_booked' => [],
        'col_done'   => [],
    ];

    $loaded_count = 0;
    if (!empty($entries)) {
        foreach ($entries as $entry) {
            $fields = is_string($entry->fields) ? json_decode($entry->fields, true) : $entry->fields;
            if (!is_array($fields)) continue;
            $loaded_count++;

            $entry_id     = $entry->entry_id;
            $salutation   = get_field_value($fields, 'Anrede');
            $first_name   = get_field_value($fields, 'Vorname');
            $last_name    = get_field_value($fields, 'Nachname');
            $course_title = get_field_value($fields, 'Verborgenes Feld');
            $title        = get_field_value($fields, 'Titel');
            $course_id    = function_exists('find_course_id_by_title_exact') ? find_course_id_by_title_exact($course_title) : 0;

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
            $status_key   = $entry_status ? $entry_status['status_key'] : 'neu';
            $status_label = $entry_status ? $entry_status['status_label'] : ($all_statuses_def['neu']['label'] ?? 'Neu / Anfrage');
            $inquiry_type = $entry_status['inquiry_type'] ?? 'course';
            $custom_title = $entry_status['custom_title'] ?? '';

            $has_saved_course_dates = !empty($entry_status['course_start_date']) || !empty($entry_status['course_end_date']);
            if ($has_saved_course_dates) {
                $start_date_raw = $entry_status['course_start_date'];
                $end_date_raw   = $entry_status['course_end_date'];
            } else {
                $start_date_raw = $course_id ? get_post_meta($course_id, 'start_datum', true) : '';
                $end_date_raw   = $course_id ? get_post_meta($course_id, 'end_datum', true) : '';
            }

            if (!empty($entry_status['course_id'])) {
                $course_id = intval($entry_status['course_id']);
                if (empty($course_title) && $course_id) {
                    $course_title = get_the_title($course_id);
                }
            } elseif ($inquiry_type === 'freie_anfrage') {
                $course_id    = 0;
                $course_title = $custom_title ?: __('Freie Geschäftsanfrage', 'custom-crm');
            }

            $foerderung          = function_exists('crm_get_entry_foerderung') ? crm_get_entry_foerderung($entry->entry_id, $fields) : ['ams' => false, 'waff' => false];
            $is_foerderung       = (!empty($foerderung['ams']) || !empty($foerderung['waff']));
            $foerder_pure_badges = function_exists('crm_render_foerderung_pure_badges') ? crm_render_foerderung_pure_badges($entry->entry_id, $foerderung) : '';

            $email_val   = get_field_value($fields, 'E-Mail') ?: get_field_value($fields, 'email');
            $phone_val   = get_field_value($fields, 'Telefon') ?: get_field_value($fields, 'phone');
            $company_val = get_field_value($fields, 'Firma') ?: get_field_value($fields, 'company');

            $client_name_parts   = array_filter([$salutation, $title, $first_name, $last_name]);
            $client_display_name = !empty($client_name_parts) ? implode(' ', $client_name_parts) : __('Unbekannter Kunde', 'custom-crm');

            $entry_timestamp       = !empty($entry->date) ? strtotime($entry->date) : time();
            $formatted_date        = date_i18n('d.m.y', $entry_timestamp);
            $full_date_tooltip     = date_i18n('d.m.Y, H:i', $entry_timestamp);
            $course_start_ts       = !empty($start_date_raw) ? strtotime($start_date_raw) : 0;
            $default_editor_action = $is_foerderung ? 'xsieben_angebot_und_kurszeiten' : 'xsieben_offer';

            $snap_count   = isset($saved_snap_counts[$entry->entry_id]) ? $saved_snap_counts[$entry->entry_id] : (function_exists('crm_get_entry_snapshots_count') ? crm_get_entry_snapshots_count($entry->entry_id) : 0);
            $actions_html = function_exists('crm_render_entry_actions') ? crm_render_entry_actions($entry->entry_id, $course_id, $status_key, $is_foerderung) : '';

            $item = compact(
                'entry', 'entry_id', 'fields', 'salutation', 'first_name', 'last_name', 'title',
                'course_title', 'course_id', 'entry_status', 'status_key', 'status_label',
                'inquiry_type', 'custom_title', 'start_date_raw', 'end_date_raw', 'foerderung', 'is_foerderung', 'foerder_pure_badges',
                'email_val', 'phone_val', 'company_val', 'client_display_name', 'entry_timestamp',
                'formatted_date', 'full_date_tooltip', 'course_start_ts', 'default_editor_action', 'snap_count', 'actions_html'
            );

            $target_col = 'col_neu';
            foreach ($columns as $ck => $st_arr) {
                if (in_array($status_key, $st_arr, true)) {
                    $target_col = $ck;
                    break;
                }
            }

            $is_sent_card = ($target_col === 'col_sent');
            $card_html = function_exists('crm_render_kanban_card') ? crm_render_kanban_card($item, $all_statuses_def, false, $is_sent_card) : '';
            $cards_by_col[$target_col][] = $card_html;
        }
    }

    $new_offset = $offset + $loaded_count;
    $has_more   = ($new_offset < $total_entries);

    wp_send_json_success([
        'cards_by_col'  => $cards_by_col,
        'loaded_count'  => $loaded_count,
        'new_offset'    => $new_offset,
        'has_more'      => $has_more,
        'total_entries' => intval($total_entries),
    ]);
});

/**
 * AJAX Handler: Friedelin KI-Lead-Vorbereitungs-Pipeline
 */
add_action('wp_ajax_crm_friedelin_prepare_lead', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Nicht autorisierter Zugriff.', 'custom-crm')]);
    }
    if (!check_ajax_referer('crm_ajax_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen.', 'custom-crm')]);
    }

    $entry_id = isset($_POST['entry_id']) ? intval($_POST['entry_id']) : 0;
    $form_id  = isset($_POST['form_id']) ? intval($_POST['form_id']) : 60468;

    if (!$entry_id) {
        wp_send_json_error(['message' => __('Ungültige Entry-ID.', 'custom-crm')]);
    }

    require_once __DIR__ . '/helpers/crm-ai-client.php';
    if (!function_exists('crm_friedelin_prepare_lead')) {
        wp_send_json_error(['message' => __('Friedelin AI-Client nicht verfügbar.', 'custom-crm')]);
    }

    $res = crm_friedelin_prepare_lead($entry_id, $form_id);
    if (!empty($res['success'])) {
        wp_send_json_success($res);
    } else {
        wp_send_json_error($res);
    }
});

/**
 * AJAX Handler: Direkte PDF-Simulation aus der Aktionen-Spalte.
 */
add_action('wp_ajax_crm_simulate_pdf', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Nicht autorisierter Zugriff.', 'custom-crm')]);
    }
    $nonce = $_POST['security'] ?? ($_POST['nonce'] ?? '');
    if (!wp_verify_nonce($nonce, 'crm_ajax_nonce')) {
        wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen.', 'custom-crm')]);
    }

    $entry_id  = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
    $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
    $doc_type  = isset($_POST['doc_type']) ? sanitize_key($_POST['doc_type']) : 'angebot';
    $variant   = isset($_POST['variant']) ? sanitize_key($_POST['variant']) : '';

    if (!$entry_id) {
        wp_send_json_error(['message' => __('Eintrags-ID fehlt.', 'custom-crm')]);
    }

    require_once __DIR__ . '/crm-model.php';
    require_once __DIR__ . '/helpers/crm-friedelin.php';

    // Fallback: Falls keine Kurs-ID übergeben wurde, über Model auflösen
    if (!$course_id && class_exists('CRM_Model')) {
        $temp_m = new CRM_Model(0, $entry_id);
        $course_id = $temp_m->post_id;
    }

    $pdf_url = '';
    try {
        if ($doc_type === 'angebot') {
            require_once __DIR__ . '/pdf/offer.php';
            $pdf_url = xsieben_offer_pdf($entry_id, $course_id, false, null, $variant ?: null);
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
        } elseif ($doc_type === 'anmeldebestaetigung' || $doc_type === 'ab') {
            require_once __DIR__ . '/pdf/anmeldebestaetigung.php';
            $pdf_url = xsieben_anmeldebestaetigung_pdf($entry_id, $course_id, false);
        } elseif ($doc_type === 'antrittsbestaetigung' || $doc_type === 'antritt') {
            require_once __DIR__ . '/pdf/antrittsbestaetigung.php';
            $pdf_url = xsieben_antrittsbestaetigung_pdf($entry_id, $course_id, false);
        }
    } catch (\Throwable $e) {
        wp_send_json_error(['message' => 'PDF-Fehler: ' . $e->getMessage()]);
    }

    if ($pdf_url) {
        wp_send_json_success([
            'message'  => __('PDF erfolgreich generiert.', 'custom-crm'),
            'pdf_url'  => $pdf_url,
            'doc_type' => $doc_type,
            'variant'  => $variant,
        ]);
    } else {
        wp_send_json_error(['message' => __('PDF konnte nicht erstellt werden.', 'custom-crm')]);
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
        $variant   = isset($_POST['variant']) ? sanitize_key($_POST['variant']) : '';
        if (!empty($entry_id) && !empty($course_id)) {
            require_once __DIR__ . '/crm-model.php';
            try {
                if ($doc_type === 'angebot') {
                    require_once __DIR__ . '/pdf/offer.php';
                    $pdf_url = xsieben_offer_pdf($entry_id, $course_id, false, null, $variant ?: null);
                } elseif ($doc_type === 'angebot_2') {
                    require_once __DIR__ . '/pdf/offer.php';
                    $pdf_url = xsieben_offer_pdf($entry_id, $course_id, false, null, 'mit_zertifikat');
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
                } elseif ($doc_type === 'anmeldebestaetigung' || $doc_type === 'ab') {
                    require_once __DIR__ . '/pdf/anmeldebestaetigung.php';
                    $pdf_url = xsieben_anmeldebestaetigung_pdf($entry_id, $course_id, false);
                } elseif ($doc_type === 'antrittsbestaetigung' || $doc_type === 'antritt') {
                    require_once __DIR__ . '/pdf/antrittsbestaetigung.php';
                    $pdf_url = xsieben_antrittsbestaetigung_pdf($entry_id, $course_id, false);
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
            } elseif ($doc_type === 'anmeldebestaetigung' || $doc_type === 'ab') {
                require_once __DIR__ . '/pdf/anmeldebestaetigung.php';
                $pdf_url = xsieben_anmeldebestaetigung_pdf($entry_id, $course_id, false);
            } elseif ($doc_type === 'antrittsbestaetigung' || $doc_type === 'antritt') {
                require_once __DIR__ . '/pdf/antrittsbestaetigung.php';
                $pdf_url = xsieben_antrittsbestaetigung_pdf($entry_id, $course_id, false);
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
 * AJAX Handler: Liefert das standardmäßig generierte HTML eines Unterabschnitts.
 */
add_action('wp_ajax_crm_get_subsection_default_html', function () {
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

    $doc_type  = sanitize_key($_POST['doc_type'] ?? 'angebot');
    $sec_key   = sanitize_key($_POST['sec_key'] ?? '');
    $sub_key   = sanitize_key($_POST['sub_key'] ?? '');
    $entry_id  = !empty($_POST['entry_id']) ? intval($_POST['entry_id']) : null;
    $course_id = !empty($_POST['course_id']) ? intval($_POST['course_id']) : null;

    require_once __DIR__ . '/helpers/crm-pdf-sections.php';
    $html = crm_get_subsection_default_html($doc_type, $sec_key, $sub_key, $entry_id, $course_id);

    wp_send_json_success([
        'html'     => $html,
        'doc_type' => $doc_type,
        'sec_key'  => $sec_key,
        'sub_key'  => $sub_key,
    ]);
});

/**
 * AJAX Handler: Speichert die globalen PDF-Element-Abstände (Standard oben & unten in pt).
 */
add_action('wp_ajax_crm_save_pdf_elements_spacing', function () {
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

    require_once __DIR__ . '/helpers/crm-pdf-sections.php';
    $data = [
        'title_spacing_top'    => $_POST['title_spacing_top'] ?? 13,
        'title_spacing_bottom' => $_POST['title_spacing_bottom'] ?? 11,
        'spacing_top'          => $_POST['spacing_top'] ?? 0,
        'spacing_bottom'       => $_POST['spacing_bottom'] ?? 0,
    ];

    crm_save_pdf_elements_spacing($data);

    if (function_exists('crm_on_partial_cache_update')) {
        crm_on_partial_cache_update('pdf_spacing');
    }

    wp_send_json_success([
        'message' => __('PDF-Element-Abstände (Standard) erfolgreich gespeichert.', 'custom-crm'),
        'spacing' => crm_get_pdf_elements_spacing(),
    ]);
});

/**
 * AJAX Handler: Speichert die Kopf- & Fußzeilen Master-Einstellungen.
 */
add_action('wp_ajax_crm_save_pdf_master_header_footer', function () {
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

    require_once __DIR__ . '/helpers/crm-pdf-sections.php';
    $master_data = isset($_POST['crm_pdf_master_hf']) && is_array($_POST['crm_pdf_master_hf'])
        ? $_POST['crm_pdf_master_hf']
        : [
            'header_mode'          => sanitize_text_field($_POST['header_mode'] ?? 'full'),
            'header_logo'          => !empty($_POST['header_logo']) ? 1 : 0,
            'header_address'       => !empty($_POST['header_address']) ? 1 : 0,
            'header_margin_top'    => floatval($_POST['header_margin_top'] ?? 8.0),
            'header_margin_bottom' => floatval($_POST['header_margin_bottom'] ?? 32.0),
            'footer_mode'          => sanitize_text_field($_POST['footer_mode'] ?? 'standard'),
            'footer_company'       => !empty($_POST['footer_company']) ? 1 : 0,
            'footer_page_num'      => !empty($_POST['footer_page_num']) ? 1 : 0,
            'footer_date'          => !empty($_POST['footer_date']) ? 1 : 0,
        ];

    crm_save_pdf_master_header_footer($master_data);

    if (function_exists('crm_on_partial_cache_update')) {
        crm_on_partial_cache_update('pdf_master_hf');
    }

    wp_send_json_success([
        'message' => __('Kopf- & Fußzeilen Master-Einstellungen erfolgreich gespeichert.', 'custom-crm'),
        'master'  => crm_get_pdf_master_header_footer(),
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

    $default_form_id = function_exists('crm_get_default_form_id') ? crm_get_default_form_id() : 60468;
    $selected_form_raw = isset($_GET['form_id']) ? sanitize_text_field($_GET['form_id']) : (string)$default_form_id;

    // Get all available forms with entries for the dropdown filter
    $all_forms = [];
    if (function_exists('wpforms')) {
        $available_forms = wpforms()->form->get();
        if (!empty($available_forms)) {
            foreach ($available_forms as $af) {
                $all_forms[$af->ID] = $af->post_title;
            }
        }
    }

    // Persist 'entries_per_page' via GET for pagination to work correctly after setting.
    $entries_per_page = isset($_GET['per_page']) ? max(30, intval($_GET['per_page'])) : 30;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $entries_per_page;

    // Process form submission for setting entries per page
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['entries_per_page'])) {
        $new_per_page = max(30, intval($_POST['entries_per_page']));
        $redirect_url = add_query_arg(['page' => 'crm', 'per_page' => $new_per_page, 'form_id' => $selected_form_raw], admin_url('admin.php'));
        wp_safe_redirect($redirect_url);
        exit;
    }

    // Fetch entries from WPForms
    if ($selected_form_raw === 'all') {
        $entries = wpforms()->entry->get_entries([
            'number'  => $entries_per_page,
            'offset'  => $offset,
        ]);
        $total_entries = wpforms()->entry->get_entries(['select' => 'COUNT(entry_id)'], true);
        $form_id = 0;
    } else {
        $form_id = absint($selected_form_raw) ?: $default_form_id;
        $entries = wpforms()->entry->get_entries([
            'form_id' => $form_id,
            'number'  => $entries_per_page,
            'offset'  => $offset,
        ]);
        $total_entries = wpforms()->entry->get_entries(['form_id' => $form_id, 'select' => 'COUNT(entry_id)'], true);
    }
    $total_pages = ceil($total_entries / $entries_per_page);

?>
    <div class="wrap">
        <h1 style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
            <span><?php esc_html_e('CRM – Anfragen & Buchungen', 'custom-crm'); ?></span>
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

        <?php
        // --- Top Kurse der letzten 3 Monate (Query vor Controls, Ausgabe rechts) ---
        global $wpdb;
        $three_months_ago = date('Y-m-d H:i:s', strtotime('-3 months'));
        $entries_table = $wpdb->prefix . 'wpforms_entries';
        $recent_entries = $wpdb->get_results($wpdb->prepare(
            "SELECT fields FROM {$entries_table} WHERE form_id = %d AND date >= %s AND status != 'trash'",
            $form_id,
            $three_months_ago
        ));

        $course_counts = [];
        if (!empty($recent_entries)) {
            foreach ($recent_entries as $re) {
                $re_fields = is_string($re->fields) ? json_decode($re->fields, true) : $re->fields;
                if (!is_array($re_fields)) continue;
                $re_course = '';
                foreach ($re_fields as $rf) {
                    if (isset($rf['name']) && $rf['name'] === 'Verborgenes Feld' && !empty($rf['value'])) {
                        $re_course = trim($rf['value']);
                        break;
                    }
                }
                if ($re_course !== '') {
                    $course_counts[$re_course] = isset($course_counts[$re_course]) ? $course_counts[$re_course] + 1 : 1;
                }
            }
            arsort($course_counts);
            $top_courses = array_slice($course_counts, 0, 5, true);
        } else {
            $top_courses = [];
        }
        ?>

            <div class="crm-controls" style="margin-bottom: 1em; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px;">
                <div class="crm-controls-left">
                    <form method="POST" action="<?php echo esc_url(admin_url('admin.php?page=crm')); ?>" style="margin-bottom:6px;">
                        <label for="entries_per_page"><?php esc_html_e('Entries per page (min. 30):', 'custom-crm'); ?> </label>
                        <input type="number" min="30" name="entries_per_page" id="entries_per_page" value="<?php echo esc_attr($entries_per_page); ?>" style="width:80px;">
                        <input type="submit" class="button" value="<?php esc_attr_e('Apply', 'custom-crm'); ?>">
                    </form>
                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <?php if (!empty($all_forms)): ?>
                        <select id="crmFormFilter" style="height:32px; font-size:13px; max-width:260px; font-weight:600; color:#0f172a; border-color:#94a3b8;" onchange="window.location.href=this.value;" title="<?php esc_attr_e('Formular-Filter', 'custom-crm'); ?>">
                            <option value="<?php echo esc_url(add_query_arg(['page' => 'crm', 'form_id' => 'all'], admin_url('admin.php'))); ?>" <?php selected($selected_form_raw, 'all'); ?>>
                                📋 <?php esc_html_e('Alle Formulare (Gesamtübersicht)', 'custom-crm'); ?>
                            </option>
                            <?php foreach ($all_forms as $af_id => $af_title): ?>
                                <option value="<?php echo esc_url(add_query_arg(['page' => 'crm', 'form_id' => $af_id], admin_url('admin.php'))); ?>" <?php selected((string)$af_id, $selected_form_raw); ?>>
                                    <?php echo ($af_id == 60468) ? '🎓 ' : (($af_id == 60798) ? '📞 ' : '📝 '); ?>
                                    <?php echo esc_html($af_title . ' (#' . $af_id . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                        <?php $all_statuses_def = function_exists('crm_get_statuses') ? crm_get_statuses() : []; ?>
                        <select id="crmStatusFilter" style="height:32px; font-size:13px; max-width:200px;">
                            <option value=""><?php esc_html_e('Alle Status anzeigen', 'custom-crm'); ?></option>
                            <?php 
                            $seen_labels = [];
                            foreach ($all_statuses_def as $sk => $sconf) : 
                                if (isset($seen_labels[$sconf['label']])) continue;
                                $seen_labels[$sconf['label']] = true;
                            ?>
                                <option value="<?php echo esc_attr($sconf['label']); ?>"><?php echo esc_html($sconf['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="crmSortOrder" style="height:32px; font-size:13px; max-width:240px;" title="<?php esc_attr_e('Sortieren nach / Order by', 'custom-crm'); ?>">
                            <option value="date_desc"><?php esc_html_e('📅 Anfrage: Neueste zuerst', 'custom-crm'); ?></option>
                            <option value="date_asc"><?php esc_html_e('📅 Anfrage: Älteste zuerst', 'custom-crm'); ?></option>
                            <option value="name_asc"><?php esc_html_e('👤 Kunde: A – Z', 'custom-crm'); ?></option>
                            <option value="name_desc"><?php esc_html_e('👤 Kunde: Z – A', 'custom-crm'); ?></option>
                            <option value="course_asc"><?php esc_html_e('🎓 Kurs: A – Z', 'custom-crm'); ?></option>
                            <option value="course_desc"><?php esc_html_e('🎓 Kurs: Z – A', 'custom-crm'); ?></option>
                            <option value="course_date_asc"><?php esc_html_e('⏳ Kursbeginn: Nächste', 'custom-crm'); ?></option>
                        </select>
                        <input type="text" id="courseTableSearch" placeholder="<?php printf(esc_attr__('Search %d entries...', 'custom-crm'), $total_entries); ?>" style="width: 220px;">
                    </div>
                </div>

                <?php if (!empty($top_courses)) : ?>
                <details class="crm-top-courses-panel">
                    <summary class="crm-top-courses-header">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <span class="crm-top-courses-title"><?php esc_html_e('Top Kurse', 'custom-crm'); ?></span>
                        <small>(<?php printf(esc_html__('%d Anfragen, 3 Mo.', 'custom-crm'), count($recent_entries)); ?>)</small>
                        <span class="crm-top-courses-toggle-icon dashicons dashicons-arrow-down-alt2"></span>
                    </summary>
                    <ol class="crm-top-courses-list">
                        <?php foreach ($top_courses as $tc_name => $tc_count) : ?>
                        <li class="crm-top-course-item" title="<?php echo esc_attr($tc_name); ?>">
                            <span class="crm-top-course-name"><?php echo esc_html(mb_strimwidth($tc_name, 0, 38, '…')); ?></span>
                            <span class="crm-top-course-count"><?php echo intval($tc_count); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ol>
                </details>
                <?php endif; ?>
        </div>


        <?php 
        $actions_config = crm_get_actions_config();
        $entry_ids = !empty($entries) ? wp_list_pluck($entries, 'entry_id') : [];
        $saved_statuses    = function_exists('crm_get_entries_statuses') ? crm_get_entries_statuses($entry_ids) : [];
        $saved_snap_counts = function_exists('crm_get_entries_snapshots_counts') ? crm_get_entries_snapshots_counts($entry_ids) : [];
        $row_index = 0;
        $prepared_entries = [];

        foreach ($entries as $entry) {
            $fields = is_string($entry->fields) ? json_decode($entry->fields, true) : $entry->fields;
            if (!is_array($fields)) continue;

            $row_index++;
            $row_alt_class = ($row_index % 2 === 0) ? 'alternate' : '';

            $entry_id = $entry->entry_id;
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
            $inquiry_type = $entry_status['inquiry_type'] ?? 'course';
            $custom_title = $entry_status['custom_title'] ?? '';

            // Persistent course dates snapshot
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

                    if (function_exists('crm_save_entry_course_dates')) {
                        crm_save_entry_course_dates($entry->entry_id, $course_id, $start_date_raw, $end_date_raw);
                    }
                } else {
                    $start_date_raw = '';
                    $end_date_raw   = '';
                }
            }

            if (!empty($entry_status['course_id'])) {
                $course_id = intval($entry_status['course_id']);
                if (empty($course_title) && $course_id) {
                    $course_title = get_the_title($course_id);
                }
            } elseif ($inquiry_type === 'freie_anfrage') {
                $course_id = 0;
                $course_title = $custom_title ?: __('Freie Geschäftsanfrage', 'custom-crm');
            }

            // Förderungs-Erkennung (AMS, WAFF)
            $foerderung = function_exists('crm_get_entry_foerderung') ? crm_get_entry_foerderung($entry->entry_id, $fields) : ['ams' => false, 'waff' => false];
            $is_foerderung = (!empty($foerderung['ams']) || !empty($foerderung['waff']));
            $foerder_pure_badges = function_exists('crm_render_foerderung_pure_badges') ? crm_render_foerderung_pure_badges($entry->entry_id, $foerderung) : '';

            // Widget für Spalte "Angefragter Kurs"
            $linked_course = crm_render_entry_course_widget(
                $entry->entry_id,
                $entry_status,
                $course_id ?: 0,
                $course_title,
                $start_date_raw,
                $end_date_raw
            );

            $anrede_val = get_field_value($fields, 'Anrede');
            $titel_val = get_field_value($fields, 'Titel');
            $first_name_val = get_field_value($fields, 'Vorname');
            $last_name_val = get_field_value($fields, 'Nachname');
            $email_val = get_field_value($fields, 'E-Mail') ?: get_field_value($fields, 'email');
            $phone_val = get_field_value($fields, 'Telefon') ?: get_field_value($fields, 'phone');
            $company_val = get_field_value($fields, 'Firma') ?: get_field_value($fields, 'company');
            $street_val = get_field_value($fields, 'Straße') ?: get_field_value($fields, 'street') ?: get_field_value($fields, 'adresse');
            $zip_val = get_field_value($fields, 'PLZ') ?: get_field_value($fields, 'zip');
            $city_val = get_field_value($fields, 'Ort') ?: get_field_value($fields, 'city');
            $country_val = get_field_value($fields, 'Land') ?: get_field_value($fields, 'country') ?: 'Österreich';
            $svr_val = get_field_value($fields, 'SV. Nr') ?: get_field_value($fields, 'SVR') ?: get_field_value($fields, 'Sozialversicherung');
            $message_val = get_field_value($fields, 'Nachricht / Freitext') ?: get_field_value($fields, 'Freitext') ?: get_field_value($fields, 'Nachricht') ?: get_field_value($fields, 'Anmerkungen');

            $client_name_parts = array_filter([$salutation, $title, $first_name, $last_name]);
            $client_display_name = !empty($client_name_parts) ? implode(' ', $client_name_parts) : __('Unbekannter Kunde', 'custom-crm');

            $entry_timestamp = !empty($entry->date) ? strtotime($entry->date) : time();
            $formatted_date = date_i18n('d.m.y', $entry_timestamp);
            $full_date_tooltip = date_i18n('d.m.Y, H:i', $entry_timestamp);
            $course_start_ts = !empty($start_date_raw) ? strtotime($start_date_raw) : 0;
            $default_editor_action = $is_foerderung ? 'xsieben_angebot_und_kurszeiten' : 'xsieben_offer';

            $snap_count = isset($saved_snap_counts[$entry->entry_id]) ? $saved_snap_counts[$entry->entry_id] : (function_exists('crm_get_entry_snapshots_count') ? crm_get_entry_snapshots_count($entry->entry_id) : 0);
            $actions_html = function_exists('crm_render_entry_actions') ? crm_render_entry_actions($entry->entry_id, $course_id, $status_key, $is_foerderung) : '';
            $journey_html = function_exists('crm_render_journey_tracker') ? crm_render_journey_tracker($status_key, $entry->entry_id) : '';

            $available_certifications = function_exists('crm_get_course_available_certifications') ? crm_get_course_available_certifications($course_id ?: 0, $entry->entry_id) : [];
            $course_certs_badges = function_exists('crm_render_course_cert_badges') ? crm_render_course_cert_badges($available_certifications, 'compact') : '';

            $prepared_entries[] = compact(
                'entry', 'entry_id', 'fields', 'row_index', 'row_alt_class', 'salutation', 'first_name', 'last_name', 'title',
                'course_title', 'course_id', 'entry_status', 'status_key', 'status_label', 'status_date_raw', 'status_date_formatted',
                'inquiry_type', 'custom_title', 'start_date_raw', 'end_date_raw', 'foerderung', 'is_foerderung', 'foerder_pure_badges',
                'linked_course', 'available_certifications', 'course_certs_badges', 'anrede_val', 'titel_val', 'first_name_val', 'last_name_val', 'email_val', 'phone_val', 'company_val',
                'street_val', 'zip_val', 'city_val', 'country_val', 'svr_val', 'message_val', 'client_display_name', 'entry_timestamp',
                'formatted_date', 'full_date_tooltip', 'course_start_ts', 'default_editor_action', 'snap_count', 'actions_html', 'journey_html'
            );
        }

        if (empty($prepared_entries)) : ?>
            <p><?php esc_html_e('No entries found for this form.', 'custom-crm'); ?></p>
        <?php else : ?>
            <?php
            // View Switcher Toolbar
            echo function_exists('crm_render_view_switcher') ? crm_render_view_switcher($total_entries) : '';

            // 1. Cards View (Default Active)
            echo function_exists('crm_render_card_view') ? crm_render_card_view($prepared_entries, $all_statuses_def) : '';

            // 2. Split View (Master-Detail)
            echo function_exists('crm_render_split_view') ? crm_render_split_view($prepared_entries, $all_statuses_def) : '';

            // 3. Kanban Pipeline View (Infinite Scroll for Open & Collapsed Done with Badge)
            echo function_exists('crm_render_kanban_view') ? crm_render_kanban_view($prepared_entries, $all_statuses_def, $total_entries) : '';
            ?>

            <!-- 4. Compact Table View (Fallback) -->
            <div id="crm-view-table" class="crm-view-pane" style="display:none;">
                <table class="widefat striped fixed js-sort-table">
                    <thead>
                        <tr>
                            <th style="width:28%;"><?php esc_html_e('Kunde', 'custom-crm'); ?></th>
                            <th style="width:30%;"><?php esc_html_e('Angefragter Kurs', 'custom-crm'); ?></th>
                            <th style="width:22%;"><?php esc_html_e('Status', 'custom-crm'); ?></th>
                            <th style="width:20%;"><?php esc_html_e('Nächster Schritt', 'custom-crm'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($prepared_entries as $item) {
                            extract($item);

                        echo '<tr class="crm-entry-row ' . esc_attr($row_alt_class) . '" data-entry-id="' . esc_attr($entry->entry_id) . '" data-course-id="' . esc_attr($course_id ?: 0) . '" data-inquiry-type="' . esc_attr($inquiry_type) . '" data-custom-title="' . esc_attr($custom_title) . '" data-client-name="' . esc_attr($client_display_name) . '" data-course-title="' . esc_attr($course_title) . '" data-is-foerderung="' . ($is_foerderung ? '1' : '0') . '" data-entry-date="' . esc_attr($entry_timestamp) . '" data-course-date="' . esc_attr($course_start_ts) . '" data-status-key="' . esc_attr($status_key) . '">';
                        echo '<td class="crm-client-cell title column-title has-row-actions page-title" data-colname="' . esc_attr__('Kunde', 'custom-crm') . '">';
                        echo '  <div class="crm-client-cell-inner">';
                        echo '    <div class="crm-client-title-row" style="display:inline-flex; align-items:center; gap:6px; flex-wrap:wrap;">';
                        echo '      <strong><a class="row-title crm-direct-editor-btn crm-open-case-link" href="#" data-entry-id="' . esc_attr($entry->entry_id) . '" data-course-id="' . esc_attr($course_id ?: 0) . '" data-action="' . esc_attr($default_editor_action) . '" title="' . esc_attr__('Klicken, um den gesamten Geschäftsvorfall & Arbeitsbereich zu öffnen', 'custom-crm') . '">' . esc_html($client_display_name) . '</a></strong>';
                        echo '      <span class="crm-badge crm-badge-anfrage" title="' . esc_attr__('Anfrage vom', 'custom-crm') . ' ' . esc_attr($full_date_tooltip) . '">' . esc_html($formatted_date) . '</span>';
                        echo '      ' . $foerder_pure_badges;
                        echo '    </div>';
                        echo '    <div class="row-actions">';
                        echo '      <span class="crm-action-case"><strong><button type="button" class="button-link crm-row-action-link crm-direct-editor-btn crm-case-btn" data-entry-id="' . esc_attr($entry->entry_id) . '" data-course-id="' . esc_attr($course_id) . '" data-action="' . esc_attr($default_editor_action) . '" title="' . esc_attr__('Gesamten Geschäftsvorfall & Arbeitsbereich öffnen', 'custom-crm') . '">' . esc_html__('Geschäftsvorfall', 'custom-crm') . '</button></strong> | </span>';
                        echo '      <span class="crm-action-link-course"><button type="button" class="button-link crm-row-action-link crm-link-course-btn" data-entry-id="' . esc_attr($entry->entry_id) . '" data-course-id="' . esc_attr($course_id) . '" data-inquiry-type="' . esc_attr($inquiry_type) . '" data-custom-title="' . esc_attr($custom_title) . '" title="' . esc_attr__('Kurs zuweisen oder als freie Geschäftsanfrage anlegen', 'custom-crm') . '">' . esc_html__('Kurs / Anfrage zuweisen', 'custom-crm') . '</button> | </span>';
                        echo '      <span class="crm-action-docs"><button type="button" class="button-link crm-row-action-link crm-direct-editor-btn crm-docs-btn" data-entry-id="' . esc_attr($entry->entry_id) . '" data-course-id="' . esc_attr($course_id) . '" data-action="' . esc_attr($default_editor_action) . '" title="' . esc_attr__('Dokumente (Angebote, KB, TB, Diplom) im großen Arbeitsbereich anzeigen & simulieren', 'custom-crm') . '">' . esc_html__('Dokumente', 'custom-crm') . '</button> | </span>';
                        echo '      <span class="inline hide-if-no-js"><button type="button" class="button-link editinline crm-quick-edit-btn" data-entry-id="' . esc_attr($entry->entry_id) . '" aria-expanded="false">' . esc_html__('Kundendaten bearbeiten', 'custom-crm') . '</button> | </span>';
                        echo '      <span class="history"><button type="button" class="button-link crm-row-action-history crm-history-btn" data-entry-id="' . esc_attr($entry->entry_id) . '" title="' . esc_attr__('Status-Verlauf & Historie anzeigen', 'custom-crm') . '">' . esc_html__('Verlauf', 'custom-crm') . '</button></span>';
                        echo '    </div>';
                        echo '  </div>';
                        echo '</td>';
                        echo '<td class="crm-course-cell" data-entry-id="' . esc_attr($entry->entry_id) . '">' . wp_kses_post($linked_course) . '</td>';

                        // Status Column (Pille, Verlauf, Archiv, Meilenstein-Bar & Zeitstempel)
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
                        echo function_exists('crm_render_journey_tracker') ? crm_render_journey_tracker($status_key, $entry->entry_id) : '';
                        echo '    <div class="crm-status-meta" title="' . esc_attr__('Letzte Statusaktualisierung', 'custom-crm') . '">';
                        echo '      <span class="dashicons dashicons-clock"></span>';
                        echo '      <span class="crm-status-date-val">' . esc_html($status_date_formatted) . '</span>';
                        echo '    </div>';
                        echo '  </div>';
                        echo '</td>';

                        // Nächster Schritt Column (Wizard mit Blitz & Sprechblase)
                        echo '<td class="crm-actions crm-actions-cell" data-entry-id="' . esc_attr($entry->entry_id) . '">';
                        echo function_exists('crm_render_entry_actions') ? crm_render_entry_actions($entry->entry_id, $course_id, $status_key, $is_foerderung) : '';
                        echo '</td></tr>';

                        // WordPress Native Quick Edit Row
                        $foerder_choice = 'none';
                        if (!empty($foerderung['ams'])) {
                            $foerder_choice = 'ams';
                        } elseif (!empty($foerderung['waff'])) {
                            $foerder_choice = 'waff';
                        }

                        // Check for WPForms composite address field
                        $addr_field = null;
                        foreach ($fields as $f) {
                            if ((isset($f['type']) && $f['type'] === 'address') || isset($f['address1']) || (isset($f['id']) && (int)$f['id'] === 35)) {
                                $addr_field = $f;
                                break;
                            }
                        }

                        // Track rendered field IDs to discover any other custom fields in this form entry
                        $rendered_fids = [];
                        if ($addr_field && isset($addr_field['id'])) {
                            $rendered_fids[] = (int)$addr_field['id'];
                        }

                        $get_field_meta = function($fields, $name_cands, $id_cands = [], $exclude_terms = []) use (&$rendered_fids) {
                            if (!is_array($name_cands)) $name_cands = [$name_cands];
                            if (!is_array($id_cands)) $id_cands = [$id_cands];
                            if (!is_array($exclude_terms)) $exclude_terms = [$exclude_terms];
                            if (!is_array($fields)) return ['id' => null, 'val' => ''];

                            // Pass 1: Check ID candidates first across all fields (exact form field match)
                            if (!empty($id_cands)) {
                                foreach ($fields as $f) {
                                    $fid = isset($f['id']) ? (int)$f['id'] : null;
                                    if ($fid && in_array($fid, $id_cands, true)) {
                                        $rendered_fids[] = $fid;
                                        $val = is_array($f['value'] ?? '') ? implode(', ', $f['value']) : (string)($f['value'] ?? '');
                                        if ($val === '' && !empty($f['first'])) {
                                            $val = (string)$f['first'];
                                        }
                                        return ['id' => $fid, 'val' => $val];
                                    }
                                }
                            }

                            // Pass 2: Check Name candidates with exclusion filter
                            foreach ($fields as $f) {
                                $fid = isset($f['id']) ? (int)$f['id'] : null;
                                $fname = isset($f['name']) ? mb_strtolower(trim((string)$f['name']), 'UTF-8') : '';

                                // Exclusion filter
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
                                        if ($fid) $rendered_fids[] = $fid;
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

                        $m_anrede  = $get_field_meta($fields, ['anrede'], [88]);
                        $m_titel   = $get_field_meta($fields, ['titel', 'akad'], [90]);
                        $m_vorname = $get_field_meta($fields, ['vorname', 'first name'], [86]);
                        $m_nachname= $get_field_meta($fields, ['nachname', 'last name'], [89]);
                        $m_email   = $get_field_meta($fields, ['e-mail', 'email'], [93]);
                        $m_phone   = $get_field_meta($fields, ['telefon', 'phone', 'tel', 'mobil']);
                        $m_company = $get_field_meta($fields, ['firma', 'company', 'unternehmen'], [25], ['privat', 'oder unternehmen']);
                        $m_street  = $addr_field ? ['id' => $addr_field['id'], 'val' => ($addr_field['address1'] ?? '')] : $get_field_meta($fields, ['straße', 'street', 'adresse', 'anschrift']);
                        $m_zip     = $addr_field ? ['id' => $addr_field['id'], 'val' => ($addr_field['postal'] ?? '')] : $get_field_meta($fields, ['plz', 'zip', 'postleitzahl']);
                        $m_city    = $addr_field ? ['id' => $addr_field['id'], 'val' => ($addr_field['city'] ?? '')] : $get_field_meta($fields, ['ort', 'city', 'stadt']);
                        $m_country = $addr_field ? ['id' => $addr_field['id'], 'val' => (($addr_field['country'] ?? '') === 'AT' ? 'Österreich' : ($addr_field['country'] ?? ''))] : $get_field_meta($fields, ['land', 'country', 'staat'], [], ['förder', 'foerder', 'stelle', 'angebot']);
                        $m_svr     = $get_field_meta($fields, ['sv. nr', 'sv-nr', 'svr', 'sozialversicherung'], [29]);
                        $m_course  = $get_field_meta($fields, ['verborgenes feld', 'kurstitel', 'kurs']);
                        $m_msg     = $get_field_meta($fields, ['nachricht', 'freitext', 'anmerkung', 'ihre nachricht']);
                        $m_certs   = $get_field_meta($fields, ['zertifizierungen', 'zertifizierung', 'zertifizierungen auswahl'], [99]);
                        $m_erfolg  = $get_field_meta($fields, ['abschluss erfolg', 'erfolg', 'abschluss'], [100]);

                        $anrede_val_dyn   = $m_anrede['val'] ?: $anrede_val;
                        $titel_val_dyn    = $m_titel['val'] ?: $title;
                        $vorname_val_dyn  = $m_vorname['val'] ?: $first_name;
                        $nachname_val_dyn = $m_nachname['val'] ?: $last_name;
                        $email_val_dyn    = $m_email['val'] ?: $email_val;
                        $phone_val_dyn    = $m_phone['val'] ?: $phone_val;
                        $company_val_dyn  = $m_company['val'] ?: $company_val;
                        $street_val_dyn   = $m_street['val'] ?: $street_val;
                        $zip_val_dyn      = $m_zip['val'] ?: $zip_val;
                        $city_val_dyn     = $m_city['val'] ?: $city_val;
                        $country_val_dyn  = $m_country['val'] ?: ($country_val ?: 'Österreich');
                        $svr_val_dyn      = $m_svr['val'] ?: $svr_val;
                        $course_val_dyn   = $m_course['val'] ?: $course_title;
                        $msg_val_dyn      = $m_msg['val'] ?: $message_val;

                        // Zertifizierungen Optionen & Vorbelegung
                        $all_cert_options = [
                            'IPMA / pma - Level D Zertifizierung - € 495,00 (10%)',
                            'IPMA / pma - Level C Zertifizierung - € 1.210,00 (10%)',
                            'IPMA / pma - Level B Zertifizierung - € 2.365,00 (10%)',
                            'IPMA / pma - Level B Zertifizierung (Online) - € 1.958,00 (10%)',
                            'SystemCERT- Kompetenzzertifizierung FachtrainerIn gemäß den Forderungen der ISO 17024 - € 324,00 (20%)',
                            'TÜV - ISO/IEC 17024 Kompetenz-Zertifizierung - € 497,00 (20%)',
                            'Option: TÜV - EN ISO 17024 Kompetenz-Zertifizierung - Online - € 497,00 (20%)',
                            'Scrum.org Zertifizierung - PSM I (USD 200,-) - € 171,50 (0%)',
                            'Scrum.org Zertifizierung - PSPO I (USD 200,-) - € 171,50 (0%)',
                            'Scrum.org Zertifizierung - PSPO I (USD 200,-) + PSM I (USD 200,-) - € 343,00 (0%)',
                            'LOG+L - Kompetenzzertifizierung nach DIN EN ISO 17024 - € 255,00 (20%)',
                            'Anrechnung von Modul Gender + Diversity + € 400,00 (20%)',
                        ];

                        $current_certs_raw = $m_certs['val'];
                        $current_selected_certs = [];
                        if (!empty($current_certs_raw)) {
                            $current_selected_certs = is_array($current_certs_raw) ? $current_certs_raw : array_filter(array_map('trim', explode("\n", (string)$current_certs_raw)));
                        }

                        // Dynamisch gebuchte Zertifizierungen hinzufügen, falls sie nicht im Katalog stehen
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

                        // Abschluss Erfolg Optionen & Vorbelegung
                        $all_erfolg_options = [
                            'Ausgezeichnetem Erfolg',
                            'Gutem Erfolg',
                            'Erfolg',
                        ];
                        $current_erfolg_val = trim((string)$m_erfolg['val']);

                        echo '<tr id="crm-quick-edit-row-' . esc_attr($entry->entry_id) . '" class="inline-edit-row inline-edit-row-post quick-edit-row quick-edit-row-post inline-editor crm-quick-edit-row" data-entry-id="' . esc_attr($entry->entry_id) . '" style="display:none;">';
                        echo '  <td colspan="4" class="colspanchange">';
                        echo '    <form class="crm-inline-entry-form" data-entry-id="' . esc_attr($entry->entry_id) . '" data-course-id="' . esc_attr($course_id ?: 0) . '">';
                        echo '      <div class="inline-edit-wrapper">';
                        echo '        <fieldset class="inline-edit-col-left">';
                        echo '          <legend class="inline-edit-legend">' . esc_html__('Kundendaten – Persönliche Angaben', 'custom-crm') . '</legend>';
                        echo '          <div class="inline-edit-col">';
                        echo '            <label>';
                        echo '              <span class="title">' . esc_html__('Anrede', 'custom-crm') . '</span>';
                        echo '              <span class="input-text-wrap">';
                        echo '                <select name="field_anrede" class="crm-quick-input">';
                        echo '                  <option value=""' . selected($anrede_val_dyn, '', false) . '>-- Bitte wählen --</option>';
                        echo '                  <option value="Herr"' . selected(stripos($anrede_val_dyn, 'Herr') !== false, true, false) . '>Herr</option>';
                        echo '                  <option value="Frau"' . selected(stripos($anrede_val_dyn, 'Frau') !== false, true, false) . '>Frau</option>';
                        echo '                </select>';
                        echo '              </span>';
                        echo '            </label>';
                        echo '            <label>';
                        echo '              <span class="title">' . esc_html__('Titel (akad.)', 'custom-crm') . '</span>';
                        echo '              <span class="input-text-wrap"><input type="text" name="field_titel" value="' . esc_attr($titel_val_dyn) . '" class="crm-quick-input" placeholder="z.B. Mag., Dr., BSc"></span>';
                        echo '            </label>';
                        echo '            <label>';
                        echo '              <span class="title">' . esc_html__('Vorname *', 'custom-crm') . '</span>';
                        echo '              <span class="input-text-wrap"><input type="text" name="field_vorname" value="' . esc_attr($vorname_val_dyn) . '" class="crm-quick-input" required></span>';
                        echo '            </label>';
                        echo '            <label>';
                        echo '              <span class="title">' . esc_html__('Nachname *', 'custom-crm') . '</span>';
                        echo '              <span class="input-text-wrap"><input type="text" name="field_nachname" value="' . esc_attr($nachname_val_dyn) . '" class="crm-quick-input" required></span>';
                        echo '            </label>';
                        echo '            <label>';
                        echo '              <span class="title">' . esc_html__('E-Mail *', 'custom-crm') . '</span>';
                        echo '              <span class="input-text-wrap"><input type="email" name="field_email" value="' . esc_attr($email_val_dyn) . '" class="crm-quick-input" required></span>';
                        echo '            </label>';
                        echo '            <label>';
                        echo '              <span class="title">' . esc_html__('Telefon', 'custom-crm') . '</span>';
                        echo '              <span class="input-text-wrap"><input type="text" name="field_phone" value="' . esc_attr($phone_val_dyn) . '" class="crm-quick-input" placeholder="+43 ..."></span>';
                        echo '            </label>';
                        echo '          </div>';
                        echo '        </fieldset>';
                        echo '        <fieldset class="inline-edit-col-center">';
                        echo '          <legend class="inline-edit-legend">' . esc_html__('Anschrift & Firma', 'custom-crm') . '</legend>';
                        echo '          <div class="inline-edit-col">';
                        echo '            <label>';
                        echo '              <span class="title">' . esc_html__('Firma / Organisation', 'custom-crm') . '</span>';
                        echo '              <span class="input-text-wrap"><input type="text" name="field_company" value="' . esc_attr($company_val_dyn) . '" class="crm-quick-input" placeholder="Firmenname (optional)"></span>';
                        echo '            </label>';
                        echo '            <label>';
                        echo '              <span class="title">' . esc_html__('Straße & Hausnr.', 'custom-crm') . '</span>';
                        echo '              <span class="input-text-wrap"><input type="text" name="field_street" value="' . esc_attr($street_val_dyn) . '" class="crm-quick-input" placeholder="Straße 12/3"></span>';
                        echo '            </label>';
                        echo '            <div class="crm-quick-flex-row">';
                        echo '              <label style="flex:1;">';
                        echo '                <span class="title">' . esc_html__('PLZ', 'custom-crm') . '</span>';
                        echo '                <span class="input-text-wrap"><input type="text" name="field_zip" value="' . esc_attr($zip_val_dyn) . '" class="crm-quick-input" placeholder="1010"></span>';
                        echo '              </label>';
                        echo '              <label style="flex:2;">';
                        echo '                <span class="title">' . esc_html__('Ort / Stadt', 'custom-crm') . '</span>';
                        echo '                <span class="input-text-wrap"><input type="text" name="field_city" value="' . esc_attr($city_val_dyn) . '" class="crm-quick-input" placeholder="Wien"></span>';
                        echo '              </label>';
                        echo '            </div>';
                        echo '            <label>';
                        echo '              <span class="title">' . esc_html__('Land', 'custom-crm') . '</span>';
                        echo '              <span class="input-text-wrap"><input type="text" name="field_country" value="' . esc_attr($country_val_dyn) . '" class="crm-quick-input"></span>';
                        echo '            </label>';
                        echo '          </div>';
                        echo '        </fieldset>';
                        echo '        <fieldset class="inline-edit-col-right">';
                        echo '          <legend class="inline-edit-legend">' . esc_html__('Förderung & Kursbezug', 'custom-crm') . '</legend>';
                        echo '          <div class="inline-edit-col">';
                        echo '            <label>';
                        echo '              <span class="title">' . esc_html__('SV-Nummer (AMS)', 'custom-crm') . '</span>';
                        echo '              <span class="input-text-wrap"><input type="text" name="field_svr" value="' . esc_attr($svr_val_dyn) . '" class="crm-quick-input" placeholder="z.B. 1234 010190"></span>';
                        echo '            </label>';
                        echo '            <label>';
                        echo '              <span class="title">' . esc_html__('Förderstelle', 'custom-crm') . '</span>';
                        echo '              <span class="input-text-wrap">';
                        echo '                <select name="field_foerderung_select" class="crm-quick-input">';
                        echo '                  <option value="none"' . selected($foerder_choice, 'none', false) . '>Keine (Privat/Firma)</option>';
                        echo '                  <option value="ams"' . selected($foerder_choice, 'ams', false) . '>AMS (Angebot & KB)</option>';
                        echo '                  <option value="waff"' . selected($foerder_choice, 'waff', false) . '>WAFF (Landesförderung)</option>';
                        echo '                </select>';
                        echo '              </span>';
                        echo '            </label>';
                        echo '            <label>';
                        echo '              <span class="title">' . esc_html__('Angefragter Kurstitel', 'custom-crm') . '</span>';
                        echo '              <span class="input-text-wrap"><input type="text" name="field_course_title" value="' . esc_attr($course_val_dyn) . '" class="crm-quick-input"></span>';
                        echo '            </label>';
                        echo '            <label>';
                        echo '              <span class="title">' . esc_html__('Nachricht / Freitext', 'custom-crm') . '</span>';
                        echo '              <span class="input-text-wrap"><textarea name="field_message" rows="2" class="crm-quick-input" placeholder="Anfrage-Notizen...">' . esc_textarea($msg_val_dyn) . '</textarea></span>';
                        echo '            </label>';
                        echo '          </div>';
                        echo '        </fieldset>';

                        // Fieldset: Zertifizierungen Auswahl (Feld 99)
                        echo '        <fieldset class="inline-edit-col-certs" style="flex:1 1 100%; border-top:1px solid #cbd5e1; padding-top:12px; margin-top:10px;">';
                        echo '          <legend class="inline-edit-legend" style="font-weight:600; color:#0f172a; font-size:13px; margin-bottom:8px;">' . esc_html__('Zertifizierungen Auswahl', 'custom-crm') . '</legend>';
                        echo '          <div class="crm-quick-certs-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap:8px 16px; background:#ffffff; border:1px solid #dcdcde; border-radius:4px; padding:12px 16px;">';
                        foreach ($all_cert_options as $cert_opt) {
                            $is_cert_checked = false;
                            $opt_clean = trim(html_entity_decode((string)$cert_opt, ENT_QUOTES, 'UTF-8'));
                            foreach ($current_selected_certs as $sel_c) {
                                $sel_c_clean = trim(html_entity_decode((string)$sel_c, ENT_QUOTES, 'UTF-8'));
                                if ($sel_c_clean === $opt_clean) {
                                    $is_cert_checked = true;
                                    break;
                                }
                                $sel_l = mb_strtolower($sel_c_clean, 'UTF-8');
                                $opt_l = mb_strtolower($opt_clean, 'UTF-8');
                                $is_scrum_pair = (strpos($sel_l, 'psm') !== false || strpos($sel_l, 'pspo') !== false) && (strpos($opt_l, 'psm') !== false || strpos($opt_l, 'pspo') !== false);
                                if ($is_scrum_pair) {
                                    $sel_has_psm = strpos($sel_l, 'psm') !== false;
                                    $sel_has_pspo = strpos($sel_l, 'pspo') !== false;
                                    $opt_has_psm = strpos($opt_l, 'psm') !== false;
                                    $opt_has_pspo = strpos($opt_l, 'pspo') !== false;
                                    if ($sel_has_psm && $sel_has_pspo && $opt_has_psm && $opt_has_pspo) { $is_cert_checked = true; break; }
                                    if ($sel_has_psm && !$sel_has_pspo && $opt_has_psm && !$opt_has_pspo) { $is_cert_checked = true; break; }
                                    if (!$sel_has_psm && $sel_has_pspo && !$opt_has_psm && $opt_has_pspo) { $is_cert_checked = true; break; }
                                    continue;
                                }
                                if (stripos($sel_c_clean, $opt_clean) !== false || stripos($opt_clean, $sel_c_clean) !== false) {
                                    $is_cert_checked = true;
                                    break;
                                }
                            }
                            echo '            <label style="display:flex; align-items:flex-start; gap:8px; font-size:12.5px; line-height:1.4; color:#334155; margin:0; cursor:pointer;">';
                            echo '              <input type="checkbox" name="field_zertifizierungen[]" value="' . esc_attr($cert_opt) . '"' . ($is_cert_checked ? ' checked="checked"' : '') . ' style="margin-top:2px; flex-shrink:0;">';
                            echo '              <span>' . esc_html($cert_opt) . '</span>';
                            echo '            </label>';
                        }
                        echo '          </div>';
                        echo '        </fieldset>';

                        // Fieldset: Abschluss Erfolg (Feld 100)
                        echo '        <fieldset class="inline-edit-col-erfolg" style="flex:1 1 100%; border-top:1px solid #cbd5e1; padding-top:12px; margin-top:10px;">';
                        echo '          <legend class="inline-edit-legend" style="font-weight:600; color:#0f172a; font-size:13px; margin-bottom:8px;">' . esc_html__('Abschluss Erfolg', 'custom-crm') . '</legend>';
                        echo '          <div class="crm-quick-erfolg-wrap" style="display:flex; flex-wrap:wrap; gap:24px; background:#ffffff; border:1px solid #dcdcde; border-radius:4px; padding:10px 16px;">';
                        echo '            <label style="display:flex; align-items:center; gap:6px; font-size:12.5px; color:#64748b; margin:0; cursor:pointer;">';
                        echo '              <input type="radio" name="field_abschluss_erfolg" value=""' . (empty($current_erfolg_val) ? ' checked="checked"' : '') . '>';
                        echo '              <span><em>-- Keine Angabe / Standard --</em></span>';
                        echo '            </label>';
                        foreach ($all_erfolg_options as $erfolg_opt) {
                            $is_erfolg_checked = false;
                            $erfolg_opt_clean = mb_strtolower(trim($erfolg_opt), 'UTF-8');
                            $current_erfolg_clean = mb_strtolower(trim($current_erfolg_val), 'UTF-8');
                            if ($current_erfolg_clean !== '' && (strpos($current_erfolg_clean, $erfolg_opt_clean) !== false || strpos($erfolg_opt_clean, $current_erfolg_clean) !== false)) {
                                $is_erfolg_checked = true;
                            }
                            echo '            <label style="display:flex; align-items:center; gap:6px; font-size:12.5px; color:#1e293b; font-weight:500; margin:0; cursor:pointer;">';
                            echo '              <input type="radio" name="field_abschluss_erfolg" value="' . esc_attr($erfolg_opt) . '"' . ($is_erfolg_checked ? ' checked="checked"' : '') . '>';
                            echo '              <span>' . esc_html($erfolg_opt) . '</span>';
                            echo '            </label>';
                        }
                        echo '          </div>';
                        echo '        </fieldset>';

                        echo '        <div class="submit inline-edit-save">';
                        echo '          <button type="button" class="button cancel crm-cancel-quick-edit" data-entry-id="' . esc_attr($entry->entry_id) . '">' . esc_html__('Abbrechen', 'custom-crm') . '</button>';
                        echo '          <button type="submit" class="button button-primary save crm-save-quick-edit-btn">' . esc_html__('Kundendaten speichern', 'custom-crm') . '</button>';
                        echo '          <span class="spinner crm-quick-edit-spinner" style="float:none; vertical-align:middle; margin-left:8px;"></span>';
                        echo '          <span class="crm-quick-edit-msg" style="display:none; font-size:12px; margin-left:8px; font-weight:600;"></span>';
                        echo '        </div>';
                        echo '      </div>';
                        echo '    </form>';
                        echo '  </td>';
                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
            </div> <!-- /#crm-view-table -->

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

        <!-- CRM Workflow- & Vorbereitungs-Wizard Modal -->
        <div id="crm-wizard-modal-backdrop" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); backdrop-filter:blur(2px); z-index:99999; align-items:center; justify-content:center;">
            <div id="crm-wizard-modal" style="background:#ffffff; border-radius:12px; width:92%; max-width:840px; max-height:90vh; display:flex; flex-direction:column; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35); position:relative; overflow:hidden;">
                <!-- Header -->
                <div class="crm-wizard-header" style="display:flex; justify-content:space-between; align-items:center; padding:14px 22px; background:linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom:1px solid #e2e8f0;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="dashicons dashicons-superhero" style="font-size:24px; width:24px; height:24px; color:#6d28d9;"></span>
                        <div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <h3 style="margin:0; font-size:15.5px; font-weight:700; color:#0f172a; line-height:1.2;">
                                    <?php esc_html_e('X-SIEBEN Workflow-Wizard', 'custom-crm'); ?>
                                </h3>
                                <span id="crm-wizard-status-badge"></span>
                            </div>
                            <div class="crm-wizard-subtitle" style="font-size:12px; color:#64748b; margin-top:3px;">
                                <span id="crm-wizard-client-label" style="font-weight:600; color:#1e293b;">Kunde</span> &bull; <span id="crm-wizard-course-label">Kurs</span>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <button type="button" id="crm-wizard-edit-client-btn" class="button crm-quick-edit-btn" style="font-size:11.5px; height:28px; line-height:26px; padding:0 10px; display:inline-flex; align-items:center; gap:5px; border-color:#cbd5e1; background:#ffffff; color:#334155; cursor:pointer;" title="<?php esc_attr_e('Kundendaten bearbeiten', 'custom-crm'); ?>">
                            <span class="dashicons dashicons-edit" style="font-size:14px; line-height:26px;"></span>
                            <span><?php esc_html_e('Kundendaten', 'custom-crm'); ?></span>
                        </button>
                        <button type="button" id="crm-wizard-history-toggle" class="button" style="font-size:11.5px; height:28px; line-height:26px; padding:0 10px; display:inline-flex; align-items:center; gap:5px; border-color:#cbd5e1; background:#ffffff; color:#334155; cursor:pointer;" title="<?php esc_attr_e('Status-Verlauf ein-/ausklappen', 'custom-crm'); ?>">
                            <span class="dashicons dashicons-backup" style="font-size:14px; line-height:26px;"></span>
                            <span><?php esc_html_e('Verlauf', 'custom-crm'); ?> (<span id="crm-wizard-history-count">0</span>)</span>
                        </button>
                        <button type="button" class="crm-close-wizard-modal" style="background:none; border:none; font-size:24px; color:#64748b; cursor:pointer; padding:0 4px; line-height:1;" title="<?php esc_attr_e('Schließen', 'custom-crm'); ?>">&times;</button>
                    </div>
                </div>

                <!-- Expandable Verlauf / Timeline Drawer -->
                <div id="crm-wizard-history-drawer" style="display:none; background:#f8fafc; border-bottom:1px solid #cbd5e1; padding:14px 22px; max-height:180px; overflow-y:auto; font-size:12px;">
                    <div style="font-weight:700; color:#475569; margin-bottom:8px; display:flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-calendar-alt" style="font-size:16px;"></span>
                        <?php esc_html_e('Chronologischer Verlauf dieser Anfrage', 'custom-crm'); ?>
                    </div>
                    <div id="crm-wizard-history-content"></div>
                </div>

                <!-- Visual Lifecycle Stage Indicator Bar -->
                <div id="crm-wizard-stage-bar" class="crm-wizard-stage-bar" style="display:flex; background:#f1f5f9; border-bottom:1px solid #e2e8f0; font-size:11px; font-weight:600; padding:6px 14px; gap:6px; overflow-x:auto;">
                    <div class="crm-stage-node" data-stage="offer" style="flex:1; text-align:center; padding:4px 6px; border-radius:4px; background:#fff; color:#6d28d9; border:1px solid #c4b5fd;">
                        <span>1. Anfrage & Angebot</span>
                    </div>
                    <div style="color:#94a3b8; align-self:center;">➔</div>
                    <div class="crm-stage-node" data-stage="followup" style="flex:1; text-align:center; padding:4px 6px; border-radius:4px; color:#64748b; border:1px solid transparent;">
                        <span>2. Nachfassen</span>
                    </div>
                    <div style="color:#94a3b8; align-self:center;">➔</div>
                    <div class="crm-stage-node" data-stage="enrolled" style="flex:1; text-align:center; padding:4px 6px; border-radius:4px; color:#64748b; border:1px solid transparent;">
                        <span>3. Angemeldet & TB</span>
                    </div>
                    <div style="color:#94a3b8; align-self:center;">➔</div>
                    <div class="crm-stage-node" data-stage="diploma" style="flex:1; text-align:center; padding:4px 6px; border-radius:4px; color:#64748b; border:1px solid transparent;">
                        <span>4. Diplom & Abschluss</span>
                    </div>
                </div>

                <!-- Steps Progress Bar -->
                <div class="crm-wizard-steps-bar" style="display:flex; border-bottom:1px solid #e2e8f0; background:#ffffff;">
                    <div class="crm-wizard-step-tab is-active" data-step="1" style="flex:1; padding:11px 16px; text-align:center; font-size:12.5px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; border-bottom:3px solid #6d28d9; color:#6d28d9;">
                        <span class="crm-wizard-step-num" style="display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%; background:#6d28d9; color:#fff; font-size:11px;">1</span>
                        <span class="crm-wizard-step-title"><?php esc_html_e('Lead & Förderung', 'custom-crm'); ?></span>
                    </div>
                    <div class="crm-wizard-step-tab" data-step="2" style="flex:1; padding:11px 16px; text-align:center; font-size:12.5px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; border-bottom:3px solid transparent; color:#64748b;">
                        <span class="crm-wizard-step-num" style="display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%; background:#e2e8f0; color:#64748b; font-size:11px;">2</span>
                        <span class="crm-wizard-step-title"><?php esc_html_e('Dokumente & PDFs', 'custom-crm'); ?></span>
                    </div>
                    <div class="crm-wizard-step-tab" data-step="3" style="flex:1; padding:11px 16px; text-align:center; font-size:12.5px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; border-bottom:3px solid transparent; color:#64748b;">
                        <span class="crm-wizard-step-num" style="display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%; background:#e2e8f0; color:#64748b; font-size:11px;">3</span>
                        <span class="crm-wizard-step-title"><?php esc_html_e('E-Mail & Freigabe', 'custom-crm'); ?></span>
                    </div>
                </div>

                <!-- Modal Body (Panels) -->
                <div id="crm-wizard-body" style="padding:20px 22px; overflow-y:auto; flex:1; min-height:340px;">
                    <div style="text-align:center; padding:40px; color:#64748b;">
                        <span class="dashicons dashicons-update spin" style="font-size:32px; width:32px; height:32px;"></span>
                        <p style="margin-top:10px; font-size:13px;"><?php esc_html_e('Wizard wird geladen...', 'custom-crm'); ?></p>
                    </div>
                </div>

                <!-- Footer Navigation -->
                <div class="crm-wizard-footer" style="display:flex; justify-content:space-between; align-items:center; padding:12px 22px; background:#f8fafc; border-top:1px solid #e2e8f0;">
                    <div class="crm-wizard-footer-left">
                        <button type="button" class="button crm-wizard-prev-btn" style="display:none;">
                            &larr; <?php esc_html_e('Zurück', 'custom-crm'); ?>
                        </button>
                    </div>
                    <div class="crm-wizard-footer-right" style="display:flex; gap:10px; align-items:center;">
                        <button type="button" class="button crm-close-wizard-modal">
                            <?php esc_html_e('Schließen', 'custom-crm'); ?>
                        </button>
                        <button type="button" class="button crm-wizard-open-editor-btn" style="color:#0369a1; border-color:#7dd3fc; background:#f0f9ff;">
                            <span class="dashicons dashicons-edit" style="line-height:26px; font-size:15px;"></span>
                            <?php esc_html_e('Im Vollbild-Editor öffnen', 'custom-crm'); ?>
                        </button>
                        <button type="button" class="button button-primary crm-wizard-next-btn" style="background:#6d28d9; border-color:#5b21b6; font-weight:600;">
                            <?php esc_html_e('Weiter zu Schritt 2 →', 'custom-crm'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: KURS- ODER GESCHÄFTSANFRAGE VERKNÜPFEN -->
        <div id="crm-link-modal" class="crm-modal" style="display:none; position:fixed; z-index:100050; left:0; top:0; width:100%; height:100%; background:rgba(15,23,42,0.65); backdrop-filter:blur(3px); align-items:center; justify-content:center;">
            <div class="crm-modal-content" style="background:#fff; width:92%; max-width:640px; border-radius:10px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.25), 0 10px 10px -5px rgba(0,0,0,0.1); overflow:hidden; display:flex; flex-direction:column; max-height:90vh;">
                <!-- Modal Header -->
                <div style="background:#0f172a; color:#fff; padding:14px 20px; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #334155;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="dashicons dashicons-admin-links" style="color:#38bdf8; font-size:19px; width:19px; height:19px;"></span>
                        <h3 style="margin:0; font-size:15px; font-weight:700; color:#f8fafc;">
                            <span id="crm-link-modal-title"><?php esc_html_e('Anfrage verknüpfen', 'custom-crm'); ?></span>
                        </h3>
                    </div>
                    <button type="button" class="crm-close-link-modal" style="background:none; border:none; color:#94a3b8; font-size:24px; cursor:pointer; line-height:1; padding:0 4px;" title="<?php esc_attr_e('Schließen', 'custom-crm'); ?>">&times;</button>
                </div>

                <!-- Tabs: Katalog-Kurs vs. Freie Geschäftsanfrage -->
                <div style="display:flex; border-bottom:2px solid #e2e8f0; background:#f8fafc;">
                    <button type="button" class="crm-link-tab-btn is-active" data-tab="course" style="flex:1; padding:12px 16px; border:none; background:transparent; font-size:13px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; border-bottom:3px solid #0284c7; color:#0284c7; transition:all 0.15s ease;">
                        <span class="dashicons dashicons-welcome-learn-more" style="font-size:16px; width:16px; height:16px;"></span>
                        <span>🎓 <?php esc_html_e('Kurs aus Katalog wählen', 'custom-crm'); ?></span>
                    </button>
                    <button type="button" class="crm-link-tab-btn" data-tab="business" style="flex:1; padding:12px 16px; border:none; background:transparent; font-size:13px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; border-bottom:3px solid transparent; color:#64748b; transition:all 0.15s ease;">
                        <span class="dashicons dashicons-businessman" style="font-size:16px; width:16px; height:16px;"></span>
                        <span>💼 <?php esc_html_e('Freie Geschäftsanfrage anlegen', 'custom-crm'); ?></span>
                    </button>
                </div>

                <!-- Modal Body Form -->
                <form id="crm-link-form" style="display:flex; flex-direction:column; flex:1; overflow-y:auto; margin:0;">
                    <input type="hidden" name="action" value="crm_link_entry">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('crm_ajax_nonce')); ?>">
                    <input type="hidden" name="entry_id" id="crm-link-entry-id" value="">
                    <input type="hidden" name="inquiry_type" id="crm-link-inquiry-type" value="course">

                    <div style="padding:20px; display:flex; flex-direction:column; gap:16px;">

                        <!-- TAB 1: KURS AUS KATALOG -->
                        <div id="crm-link-panel-course" class="crm-link-tab-panel">
                            <label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;">
                                <?php esc_html_e('Kurs im X-SIEBEN Katalog suchen & auswählen:', 'custom-crm'); ?>
                            </label>
                            
                            <div style="position:relative; margin-bottom:10px;">
                                <input type="text" id="crm-course-search-input" placeholder="<?php esc_attr_e('🔍 Tippen zum Suchen (z.B. Agile, Projektmanagement, DaF, KI, Scrum...)', 'custom-crm'); ?>" style="width:100%; height:36px; padding:6px 12px; font-size:13px; border:1px solid #cbd5e1; border-radius:6px; box-shadow:inset 0 1px 2px rgba(0,0,0,0.05);">
                            </div>

                            <?php $all_courses_list = function_exists('crm_get_all_courses_options') ? crm_get_all_courses_options() : []; ?>
                            <div style="border:1px solid #cbd5e1; border-radius:6px; overflow:hidden; background:#fff;">
                                <select id="crm-link-course-select" name="course_id" size="8" style="width:100%; border:none; font-size:13px; padding:4px; outline:none; height:180px;">
                                    <option value="" disabled selected style="font-style:italic; color:#94a3b8;"><?php esc_html_e('-- Bitte Kurs wählen --', 'custom-crm'); ?></option>
                                    <?php foreach ($all_courses_list as $co): ?>
                                        <option value="<?php echo esc_attr($co['id']); ?>" 
                                                data-title="<?php echo esc_attr($co['title']); ?>"
                                                data-start="<?php echo esc_attr($co['start_date']); ?>"
                                                data-end="<?php echo esc_attr($co['end_date']); ?>"
                                                data-dates="<?php echo esc_attr($co['date_str']); ?>"
                                                data-kosten="<?php echo esc_attr($co['kosten']); ?>"
                                                data-kurstyp="<?php echo esc_attr($co['kurstyp']); ?>"
                                                style="padding:6px 8px; border-bottom:1px solid #f1f5f9;">
                                            <?php echo esc_html($co['title'] . ' (#' . $co['id'] . ' · ' . $co['date_str'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Live Selected Course Details Box -->
                            <div id="crm-selected-course-preview" style="display:none; margin-top:12px; padding:10px 14px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px; font-size:12px; color:#0369a1;">
                                <div style="font-weight:700; font-size:13px; color:#0c4a6e; margin-bottom:4px;" id="crm-preview-course-title"></div>
                                <div style="display:flex; gap:12px; flex-wrap:wrap; font-size:11.5px; color:#0369a1;">
                                    <span>📅 <strong id="crm-preview-course-dates"></strong></span>
                                    <span>💶 <strong id="crm-preview-course-kosten"></strong></span>
                                    <span>🎓 <strong id="crm-preview-course-typ"></strong></span>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: FREIE GESCHÄFTSANFRAGE -->
                        <div id="crm-link-panel-business" class="crm-link-tab-panel" style="display:none;">
                            <div style="background:#fffbeb; border:1px solid #fef3c7; border-left:4px solid #f59e0b; padding:10px 14px; border-radius:6px; font-size:12px; color:#92400e; margin-bottom:12px;">
                                <strong><?php esc_html_e('Individuelle Kunden- / Geschäftsanfrage:', 'custom-crm'); ?></strong><br>
                                <?php esc_html_e('Für Inhouse-Trainings, Coaching, Beratung oder Sonderformate ohne standardmäßigen Katalogkurs.', 'custom-crm'); ?>
                            </div>

                            <div style="margin-bottom:12px;">
                                <label for="crm-business-title" style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;">
                                    <?php esc_html_e('Thema / Bezeichnung der Anfrage *', 'custom-crm'); ?>
                                </label>
                                <input type="text" id="crm-business-title" name="custom_title" placeholder="<?php esc_attr_e('z.B. Inhouse Training Agile Führungskräfte für 10 Personen', 'custom-crm'); ?>" style="width:100%; height:36px; padding:6px 12px; font-size:13px; border:1px solid #cbd5e1; border-radius:6px;">
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                                <div>
                                    <label for="crm-business-start-date" style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;">
                                        <?php esc_html_e('Starttermin / Wunschzeitraum', 'custom-crm'); ?>
                                    </label>
                                    <input type="text" id="crm-business-start-date" name="start_date" placeholder="<?php esc_attr_e('z.B. Termine n. V. oder 01.11.2026', 'custom-crm'); ?>" style="width:100%; height:36px; padding:6px 12px; font-size:13px; border:1px solid #cbd5e1; border-radius:6px;" value="Termine n. V.">
                                </div>
                                <div>
                                    <label for="crm-business-end-date" style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;">
                                        <?php esc_html_e('Endtermin (optional)', 'custom-crm'); ?>
                                    </label>
                                    <input type="text" id="crm-business-end-date" name="end_date" placeholder="<?php esc_attr_e('z.B. 03.11.2026', 'custom-crm'); ?>" style="width:100%; height:36px; padding:6px 12px; font-size:13px; border:1px solid #cbd5e1; border-radius:6px;">
                                </div>
                            </div>
                        </div>

                        <!-- Allgemeine Notiz für beide Tabs -->
                        <div>
                            <label for="crm-link-note" style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;">
                                <?php esc_html_e('Interne Notiz zur Verknüpfung (optional)', 'custom-crm'); ?>
                            </label>
                            <textarea id="crm-link-note" name="note" rows="2" placeholder="<?php esc_attr_e('z.B. Telefonisch abgestimmt, Inhouse Angebot erstellen...', 'custom-crm'); ?>" style="width:100%; padding:6px 10px; font-size:12px; border:1px solid #cbd5e1; border-radius:6px;"></textarea>
                        </div>

                    </div>

                    <!-- Modal Footer -->
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 20px; background:#f8fafc; border-top:1px solid #e2e8f0;">
                        <button type="button" class="button crm-close-link-modal">
                            <?php esc_html_e('Abbrechen', 'custom-crm'); ?>
                        </button>
                        <button type="submit" class="button button-primary" id="crm-save-link-btn" style="display:inline-flex; align-items:center; gap:6px; font-weight:600; padding:4px 14px; height:32px;">
                            <span class="dashicons dashicons-saved" style="font-size:16px; width:16px; height:16px;"></span>
                            <span><?php esc_html_e('Verknüpfung speichern', 'custom-crm'); ?></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Universal Kundendaten Edit Modal -->
        <div id="crm-customer-edit-modal-backdrop" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); backdrop-filter:blur(3px); z-index:100060; align-items:center; justify-content:center;">
            <div id="crm-customer-edit-modal" style="background:#ffffff; border-radius:12px; width:94%; max-width:880px; max-height:92vh; display:flex; flex-direction:column; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35); position:relative; overflow:hidden;">
                <!-- Header -->
                <div class="crm-customer-edit-modal-header" style="display:flex; justify-content:space-between; align-items:center; padding:16px 24px; background:linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom:1px solid #e2e8f0;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="dashicons dashicons-admin-users" style="font-size:24px; width:24px; height:24px; color:#0284c7;"></span>
                        <div>
                            <h3 id="crm-customer-edit-modal-title" style="margin:0; font-size:16px; font-weight:700; color:#0f172a; line-height:1.2;">
                                <?php esc_html_e('Kundendaten bearbeiten', 'custom-crm'); ?>
                            </h3>
                            <div style="font-size:12px; color:#64748b; margin-top:3px;">
                                <span id="crm-customer-edit-modal-subtitle"><?php esc_html_e('Persönliche Angaben, Anschrift, Förderung & Zertifizierungen', 'custom-crm'); ?></span>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="crm-close-customer-edit-modal" style="background:none; border:none; font-size:26px; color:#64748b; cursor:pointer; padding:0 4px; line-height:1;" title="<?php esc_attr_e('Schließen', 'custom-crm'); ?>">&times;</button>
                </div>
                <!-- Body -->
                <div id="crm-customer-edit-modal-content" style="padding:20px 24px; overflow-y:auto; flex:1;">
                    <p style="text-align:center; padding:30px; color:#64748b;">
                        <span class="dashicons dashicons-update spin"></span> <?php esc_html_e('Formular wird geladen...', 'custom-crm'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

<?php
}
