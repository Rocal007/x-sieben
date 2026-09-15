<?php

// Define theme version if not already defined
if (!defined('THEME_VERSION')) {
    define('THEME_VERSION', wp_get_theme()->get('Version'));
}

/**
 * Enqueue theme CSS and JS files
 */
function sieben_enqueue()
{
    $suffix = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';

    $css_base = get_theme_file_uri('css/');
    $inc_base = get_theme_file_uri('inc/core/components/');
    $elements_base = get_theme_file_uri('inc/core/elements/');
    $js_base = get_theme_file_uri('js/');

    // CSS files
    // wp_enqueue_style('font-awesome', $css_base . 'font-awesome' . $suffix . '.css', [], THEME_VERSION);
    // wp_enqueue_style('stellarnav', $css_base . 'stellarnav.css', [], THEME_VERSION);
    wp_enqueue_style('bootstrap', $css_base . 'bootstrap' . $suffix . '.css', [], THEME_VERSION);
    wp_enqueue_style('quick-common', $css_base . 'quick_common.css', [], THEME_VERSION);
    wp_enqueue_style('icons', $css_base . 'icons.css', [], THEME_VERSION);
    wp_enqueue_style('accordion', $css_base . 'accordion.css', [], THEME_VERSION);
    wp_enqueue_style('fontawesome', $css_base . 'fontawesome.css', [], THEME_VERSION);

    // Component and element styles
    $styles = [
        'slider' => 'slider/slider.css',
        'breadcrumbs' => 'breadcrumbs/breadcrumbs.css',
        'contact' => 'contact/contact.css',
        'hubs' => 'hubs/hubs.css',
        'courses-list' => 'courses_all/courses_list.css',
        'vorteile' => 'vorteile/vorteile.css',
        'top-picture' => 'top_picture/top_picture.css',
        'related-courses' => 'related_courses/related_courses.css',
        'trainer_courses' => 'trainer_courses/trainer_courses.css',
        'faq' => 'faq/faq-css.css',
        'certs' => 'certs/certs.css',
        'search' => 'search/search.css',
        'fly-out' => 'fly_out_menu/fly_out.css',
        'sieben-header' => 'header/header.css',
        'sieben-footer' => 'footer/footer.css',
        'sieben-kunden_logos' => 'kunden_logos/kunden_logos.css',
        'knowlege_graph' => 'knowlege_graph/knowlege_graph.css'
    ];

    foreach ($styles as $handle => $path) {
        wp_enqueue_style($handle, $inc_base . $path, [], THEME_VERSION);
    }

    // Theme-level styles
    wp_enqueue_style('sieben-sidebars', $css_base . 'sidebars.css', [], THEME_VERSION);
    wp_enqueue_style('sieben-custom', $css_base . 'custom.css', [], THEME_VERSION);
    wp_enqueue_style('sieben-proven', $css_base . 'proven_expert.css', [], THEME_VERSION);
    wp_enqueue_style('sieben-style', get_stylesheet_uri(), [], THEME_VERSION);

    // JS files
    // jQuery loads first (handled by WP)
    wp_enqueue_script('bootstrap', $js_base . 'bootstrap' . $suffix . '.js', ['jquery'], THEME_VERSION, true);

    // custom.js MUST load after Bootstrap
    wp_enqueue_script('sieben-main-custom', $js_base . 'custom.js', ['bootstrap'], THEME_VERSION, true);
    wp_enqueue_script('sieben-list-order', get_template_directory_uri() . '/inc/js/list_order.js', ['bootstrap'], THEME_VERSION, true);

    wp_enqueue_script('sieben-header', $inc_base . 'header/header.js', ['jquery'], THEME_VERSION, true);
    wp_enqueue_script('sieben-vorteile', $inc_base . 'vorteile/vorteile.js', ['jquery'], THEME_VERSION, true);
    wp_enqueue_script(
        'sieben-layout-switcher',
        get_template_directory_uri() . '/inc/js/layout_switcher.js', // proper full URL
        [], // dependencies, add 'jquery' if needed
        THEME_VERSION,
        true // load in footer
    );
    // Localize admin-ajax.php URL for JS
    wp_localize_script('sieben-layout-switcher', 'myAjax', [
        'ajax_url' => admin_url('admin-ajax.php')
    ]);
}
add_action('wp_enqueue_scripts', 'sieben_enqueue');

/**
 * Enqueue admin scripts
 */
function my_enqueue_admin_scripts($hook)
{
    // Fast Course Edit page
    if ($hook === 'courses_page_fast-course-edit') {
        wp_enqueue_script(
            'my-admin-sort-search',
            get_theme_file_uri('admin/courses-edit.js'),
            [],
            '1.0',
            true
        );
    }

    // CRM admin page
    if ($hook === 'toplevel_page_crm') {
        // Enqueue the scripts required for the Visual/Text editor
        wp_enqueue_script('editor');
        wp_enqueue_script('quicktags');
        wp_enqueue_script('wplink');
        wp_enqueue_script('wp-fullscreen');
        wp_enqueue_script('wp-tinymce');

        // Enqueue the necessary CSS for the editor's appearance
        wp_enqueue_style('editor-buttons');
        wp_enqueue_style('dashicons');
        wp_enqueue_style('wp-pointer');

        $crm_asset_ver = function_exists('crm_get_asset_version')
            ? crm_get_asset_version()
            : (defined('CRM_VERSION') ? CRM_VERSION : '2.18.0');

        // CRM script (now contains AJAX code)
        wp_enqueue_script(
            'custom-crm-admin',
            get_theme_file_uri('inc/core/crm/assets/crm-admin.js'),
            ['jquery', 'jquery-ui-sortable'],
            $crm_asset_ver,
            true
        );

        // Localize the object your JS expects
        wp_localize_script('custom-crm-admin', 'xSiebenAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('x_sieben_mailer_nonce'),
        ]);

        wp_localize_script('custom-crm-admin', 'crmData', [
            'ajaxUrl'          => admin_url('admin-ajax.php'),
            'nonce'            => wp_create_nonce('crm_ajax_nonce'),
            'autoJsCacheClean' => function_exists('crm_is_js_cache_clean_enabled') ? crm_is_js_cache_clean_enabled() : true,
            'cacheVersion'     => function_exists('crm_get_js_cache_version') ? crm_get_js_cache_version() : '1',
            'assetVersion'     => $crm_asset_ver,
        ]);

        wp_enqueue_style(
            'crm-admin-styles',
            get_theme_file_uri('inc/core/crm/css/crm-admin.css'),
            [],
            $crm_asset_ver
        );
    }
}
add_action('admin_enqueue_scripts', 'my_enqueue_admin_scripts');





/**
 * Load TCPDF only when available
 */
add_action('after_setup_theme', function () {
    $tcpdf_path = get_template_directory() . '/tcbpdf/tcpdf.php';

    if (file_exists($tcpdf_path)) {
        require_once $tcpdf_path;
    } else {
        error_log('TCPDF library not found at: ' . $tcpdf_path);
    }
});

/**
 * Fügt das 'defer'-Attribut zu allen Frontend-Skripten hinzu.
 * Schließt Admin-Skripte aus und behandelt Inline-Skripte.
 */
function sieben_add_defer_attribute($tag, $handle, $src) {
    // 1. Prüfen, ob wir uns NICHT im Admin-Bereich befinden
    if (is_admin()) {
        return $tag;
    }

    // 2. Skripte, die eventuell SOFORT geladen werden MÜSSEN, hier ausschließen
    // z.B. polyfills oder Skripte, die von kritischem Inline-JS verwendet werden
    $excludes = ['jquery-core', 'jquery-migrate']; 
    if (in_array($handle, $excludes)) {
        return $tag;
    }
    
    // 3. jQuery und andere Frontend-Skripte auf 'defer' setzen
    // Beachten Sie, dass 'jquery' intern in 'jquery-core' und 'jquery-migrate' aufgeteilt wird
    // Oft ist es am besten, alle Skripte, die keine Inline-Skripte brechen, zu defern.
    // Mit diesem Filter wird allen Skripten, die nicht ausgeschlossen wurden, defer hinzugefügt.
    if (strpos($tag, 'text/javascript') !== false || strpos($tag, 'application/javascript') !== false) {
        return str_replace('<script', '<script defer', $tag);
    }

    return $tag;
}
add_filter('script_loader_tag', 'sieben_add_defer_attribute', 10, 3);
