<?php
/**
 * CRM Cache & State Fixpoint Operator
 *
 * Implements the NEXUS Cache Operator C(X) = X:
 * Provides automatic JS cache cleaning with flag control, specifically and exclusively
 * triggered on partial cache updates (e.g. section reordering, subject edits, status transitions).
 *
 * @package CustomCRM
 * @version 2.18.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Option key for storing the current JS cache version timestamp.
 */
if (!defined('CRM_JS_CACHE_VERSION_KEY')) {
    define('CRM_JS_CACHE_VERSION_KEY', 'crm_js_cache_version');
}

/**
 * Option key for the automatic JS cache clean toggle flag.
 */
if (!defined('CRM_JS_CACHE_FLAG_KEY')) {
    define('CRM_JS_CACHE_FLAG_KEY', 'crm_auto_js_cache_clean');
}

/**
 * Check whether the automatic JS cache clean flag is active.
 *
 * Precedence:
 * 1. Explicit request parameter: $_REQUEST['crm_js_cache_clean'] ('1' or '0')
 * 2. PHP Constant: CRM_AUTO_JS_CACHE_CLEAN (true/false)
 * 3. WordPress Option: crm_auto_js_cache_clean (default: true)
 *
 * @return bool True if cache cleaning on partial updates is enabled.
 */
function crm_is_js_cache_clean_enabled(): bool
{
    // 1. Request override
    if (isset($_REQUEST['crm_js_cache_clean'])) {
        return filter_var($_REQUEST['crm_js_cache_clean'], FILTER_VALIDATE_BOOLEAN);
    }

    // 2. Constant override
    if (defined('CRM_AUTO_JS_CACHE_CLEAN')) {
        return (bool) CRM_AUTO_JS_CACHE_CLEAN;
    }

    // 3. Database setting (defaults to true)
    return (bool) get_option(CRM_JS_CACHE_FLAG_KEY, true);
}

/**
 * Get the current JS cache version buster.
 *
 * @return string Microtime or timestamp string.
 */
function crm_get_js_cache_version(): string
{
    $ver = get_option(CRM_JS_CACHE_VERSION_KEY, '');
    if (empty($ver)) {
        $ver = (string) time();
        update_option(CRM_JS_CACHE_VERSION_KEY, $ver, false);
    }
    return (string) $ver;
}

/**
 * Get the composite asset version for script & style enqueues.
 * Combines CRM_VERSION with the dynamic cache version buster.
 *
 * @param string|null $base_version Optional base version. Defaults to CRM_VERSION.
 * @return string E.g. '2.18.0.1741697200'
 */
function crm_get_asset_version(?string $base_version = null): string
{
    $base = $base_version ?? (defined('CRM_VERSION') ? CRM_VERSION : '2.18.6');
    $js_file = dirname(__DIR__) . '/assets/crm-admin.js';
    $mtime = file_exists($js_file) ? (string) filemtime($js_file) : (string) time();

    if (!crm_is_js_cache_clean_enabled()) {
        return $base . '.' . $mtime;
    }

    $cache_ver = crm_get_js_cache_version();
    return $base . '.' . $cache_ver . '.' . $mtime;
}

/**
 * Bump the persistent JS cache version.
 *
 * @return string New cache version.
 */
function crm_bump_js_cache_version(): string
{
    $new_ver = (string) time();
    update_option(CRM_JS_CACHE_VERSION_KEY, $new_ver, false);
    return $new_ver;
}

/**
 * Core Cache Hook: Triggered exclusively on partial cache updates.
 *
 * If the flag is enabled:
 * - Bumps the persistent JS cache buster.
 * - Flushes WordPress object cache for CRM transients if applicable.
 * - Logs the audit entry.
 *
 * @param string $component The component being updated partially (e.g. 'pdf_angebot', 'email_kb', 'status_123').
 * @param int|string|null $item_id Optional entry ID or doc type identifier.
 * @param array $extra Optional contextual metadata.
 * @return array Result array with status, new version, and reason.
 */
function crm_on_partial_cache_update(string $component = 'general', $item_id = null, array $extra = []): array
{
    $is_enabled = crm_is_js_cache_clean_enabled();

    if (!$is_enabled) {
        return [
            'cleaned'    => false,
            'reason'     => 'flag_disabled',
            'component'  => $component,
            'item_id'    => $item_id,
            'version'    => crm_get_js_cache_version(),
            'timestamp'  => current_time('mysql'),
        ];
    }

    // Bump cache version
    $new_version = crm_bump_js_cache_version();

    // Clean any transient caches registered for this component
    if (function_exists('delete_transient')) {
        delete_transient('crm_cache_' . sanitize_key($component));
        if ($item_id) {
            delete_transient('crm_cache_' . sanitize_key($component) . '_' . $item_id);
        }
    }

    $result = [
        'cleaned'    => true,
        'flag'       => true,
        'component'  => $component,
        'item_id'    => $item_id,
        'version'    => $new_version,
        'asset_ver'  => crm_get_asset_version(),
        'timestamp'  => current_time('mysql'),
    ];

    /**
     * Action hook allowing other modules or logging services to react to partial cache clean.
     */
    do_action('crm_js_cache_cleaned_on_partial_update', $component, $result);

    return $result;
}

/**
 * AJAX Endpoint: Manually purge JS cache.
 */
add_action('wp_ajax_crm_clear_js_cache', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Nicht autorisierter Zugriff.', 'custom-crm')]);
    }

    check_ajax_referer('crm_ajax_nonce', 'nonce');

    $new_ver = crm_bump_js_cache_version();

    wp_send_json_success([
        'message'     => __('JS-Cache erfolgreich invalidiert!', 'custom-crm'),
        'new_version' => $new_ver,
        'asset_ver'   => crm_get_asset_version(),
        'timestamp'   => current_time('mysql'),
    ]);
});

/**
 * AJAX Endpoint: Partial Cache Update with Flag evaluation.
 */
add_action('wp_ajax_crm_partial_cache_update', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Nicht autorisierter Zugriff.', 'custom-crm')]);
    }

    $nonce = $_POST['nonce'] ?? ($_REQUEST['nonce'] ?? '');
    $nonce_valid = false;
    if (!empty($nonce)) {
        if (wp_verify_nonce($nonce, 'crm_ajax_nonce') || wp_verify_nonce($nonce, 'save_crm_settings')) {
            $nonce_valid = true;
        }
    }
    if (!$nonce_valid) {
        wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen.', 'custom-crm')]);
    }

    $component = sanitize_key($_POST['component'] ?? 'general');
    $item_id   = sanitize_text_field($_POST['item_id'] ?? '');

    $result = crm_on_partial_cache_update($component, $item_id);

    wp_send_json_success([
        'message'   => $result['cleaned']
            ? sprintf(__('Partieller Cache für "%s" aktualisiert und JS-Cache bereinigt.', 'custom-crm'), esc_html($component))
            : sprintf(__('Partieller Cache für "%s" aktualisiert (JS-Cache-Clean Flag inaktiv).', 'custom-crm'), esc_html($component)),
        'details'   => $result,
    ]);
});
