<?php
define("THEME_VERSION", 1289123);
define('CIS_TEXTDOMAIN', 'codeispassion'); // set the CIS_TEXTDOMAIN for the whole Theme
// define('THEME_NAME', __('Code is Passion - Core Theme', CIS_TEXTDOMAIN)); // Theme Name: Remember to set this in the style.css, too
// define('DEFAULT_AVATAR_URL', ''); // Default Avatar for Comments
/******** Include Libraries *************/
require_once get_template_directory() . '/inc/init.php';
require_once("functions/functions-components.php");
require_once("functions/functions-snippets.php");
require_once("admin/courses-edit.php");
require_once("admin/courses-api-ams.php");
require_once("admin/courses-api-waff.php");
require_once("admin/courses-angebote-pdf.php");
require get_template_directory() . '/functions/theme-default-setup.php';
require get_template_directory() . '/functions/widget.php';
require get_template_directory() . '/functions/enqueue-files.php';

//require get_template_directory() . '/functions/theme-customization.php';


/**************** WordPress SETTINGS *******************/
// language
load_theme_textdomain(CIS_TEXTDOMAIN, get_template_directory() . '/languages'); // internationalization
$locale = get_locale();
$locale_file = TEMPLATEPATH . "/languages/$locale.php";
if (is_readable($locale_file)) {
    require_once($locale_file);
}

/* register js globals */
add_action('wp_head', 'cis_js_globals', 2);
add_action('admin_head', 'cis_js_globals', 2);
function cis_js_globals()
{ ?>
<?php
}

add_action('after_setup_theme', 'remove_mk_visual_composer_mapper');
function remove_mk_visual_composer_mapper()
{
    remove_action('vc_mapper_init_before', 'mk_visual_composer_mapper');
}





if (!function_exists('sieben_setup')):
    function sieben_setup()
    {
        load_theme_textdomain('sieben', get_template_directory() . '/languages');
        add_theme_support('automatic-feed-links');
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        register_nav_menus(
            array(
                'primary' => esc_html__('Top Menu', 'sieben'),
                'footer-menu' => esc_html__('Footer Menu', 'sieben'),
                'mobile-menu' => esc_html__('Mobile Menu', 'sieben'),
                'burger-menue' => esc_html__('Burger Menu', 'sieben'),
            )
        );
        add_theme_support(
            'html5',
            array(
                'comment-form',
                'comment-list',
                'gallery',
                'caption',
            )
        );
        add_theme_support(
            'custom-logo',
            array(
                'height' => 85,
                'width' => 240,
                'flex-height' => true,
                'header-text' => array('navbar-brand', 'ttl_tagline'),
            )
        );
        add_theme_support(
            'custom-background',
            apply_filters(
                'sieben_custom_background_args',
                array(
                    'default-color' => '#ffffff',
                )
            )
        );
        add_theme_support('customize-selective-refresh-widgets');

        add_image_size('sieben-related-thumbnail', 260, 160, false);
        add_image_size('sieben-single-thumbnail', 820, 420, false);
        add_image_size('sieben-thumbnail-image', 260, 165, true);
        add_image_size('sieben-thumbnail-small-wide', 9999, 120, false);
        add_image_size('sieben-similar-thumbnail', 400, 300, true);
        add_image_size('sieben-slider', 1000, 450, true);
        add_image_size('sieben-certs-mini', 80, 60, true);
        add_image_size('sieben-rev-logos', 9999, 60);
        add_image_size('sieben-top-pictures', 570, 295, false);
        add_image_size('top-thumbnail-brain', 570, 279, array('center', 'top'));
        add_image_size('3col-thumbnail-brain', 355, 174, array('center', 'top'));
        add_image_size('2col-thumbnail-brain', 522, 265, array('center', 'top'));
        add_image_size('home-3col', 300, 149, array('center', 'top'));
        add_image_size('blog-top', 99999, 578, false);
        add_image_size('hero-image', 1600, 9999, false);
        add_image_size('mobile-top', 450, 9999, false);
        add_image_size('sieben-certs-horizontal', 9999, 32, false); // width, height, crop
        add_image_size('sieben-courses-top', 99999, 578, false);
        add_image_size('tablet-desktop', 900, 9999, false);
    }

    add_action('after_setup_theme', 'sieben_setup');

endif;

function sieben_customize_register($wp_customize)
{
    // Mobile Logo Upload
    $wp_customize->add_setting('sieben_mobile_logo', array(
        'default'           => '',
        'sanitize_callback' => 'absint',
    ));

    $wp_customize->add_control(new WP_Customize_Media_Control($wp_customize, 'sieben_mobile_logo', array(
        'label'    => __('Mobile Logo', 'sieben'),
        'section'  => 'title_tagline', // Same section as Site Identity
        'mime_type' => 'image',
        'priority' => 9, // Put right after the main logo
    )));
}
add_action('customize_register', 'sieben_customize_register');


/*
 * Creating a function to create our CPT
 */
function custom_post_type()
{
    $labels = array(
        'name' => _x('Team Members', 'Team Members', 'sieben'),
        'singular_name' => _x('Team Members', 'Team Member', 'sieben'),
        'menu_name' => __('Team Members', 'sieben'),
        'parent_item_colon' => __('Parent Members', 'sieben'),
        'all_items' => __('All Members', 'sieben'),
        'view_item' => __('View Member', 'sieben'),
        'add_new_item' => __('Add New Member', 'sieben'),
        'add_new' => __('Add New', 'sieben'),
        'edit_item' => __('Edit Member', 'sieben'),
        'update_item' => __('Update Member', 'sieben'),
        'search_items' => __('Search Member', 'sieben'),
        'not_found' => __('Not Found', 'sieben'),
        'not_found_in_trash' => __('Not found in Trash', 'sieben'),
    );
    $args = array(
        'label' => __('members', 'sieben'),
        'description' => __('Members news and reviews', 'sieben'),
        'labels' => $labels,
        // Features this CPT supports in Post Editor
        'supports' => array('title', 'editor', 'excerpt', 'thumbnail',),
        'hierarchical' => false,
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => true, // Enable REST API support
        'show_in_admin_bar' => true,
        'menu_position' => 5,
        'can_export' => true,
        'has_archive' => true,
        'exclude_from_search' => false,
        'publicly_queryable' => true,
        'capability_type' => 'page',
        'rewrite' => [
            'slug' => 'experte',
            'with_front' => false
        ],
    );
    register_post_type('members', $args);


    $courses_labels = array(
        'name' => _x('Courses', 'Team Members', 'sieben'),
        'singular_name' => _x('Courses', 'Team Member', 'sieben'),
        'menu_name' => __('Courses', 'sieben'),
        'parent_item_colon' => __('Parent Courses', 'sieben'),
        'all_items' => __('All Courses', 'sieben'),
        'view_item' => __('View Course', 'sieben'),
        'add_new_item' => __('Add New Course', 'sieben'),
        'add_new' => __('Add New', 'sieben'),
        'edit_item' => __('Edit Course', 'sieben'),
        'update_item' => __('Update Course', 'sieben'),
        'search_items' => __('Search Course', 'sieben'),
        'not_found' => __('Not Found', 'sieben'),
        'not_found_in_trash' => __('Not found in Trash', 'sieben'),
    );
    $courses_args = array(
        'label' => __('courses', 'sieben'),
        'description' => __('Courses news and reviews', 'sieben'),
        'labels' => $courses_labels,
        // Features this CPT supports in Post Editor
        'supports' => array('title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'comments'),
        'hierarchical' => false,
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_admin_bar' => true,
        'show_in_rest' => true,
        'menu_position' => 5,
        'can_export' => true,
        'has_archive' => true,
        'exclude_from_search' => false,
        'publicly_queryable' => true,
        'capability_type' => 'page',
        'rewrite' => [
            'slug' => 'weiterbildung',
            'with_front' => false
        ],
    );
    // Registering your Custom Post Type
    register_post_type('courses', $courses_args);
    register_taxonomy(
        'coursecategory', // the slug for the new taxonomy
        ['courses'], // the post type(s) you want to register a custom taxonomy for
        [
            'public' => true,
            'labels' => [
                'name' => __('Course Categories', 'sieben'),
                'singular_name' => __('Course Category', 'sieben'),
            ],
            'hierarchical' => true, // this will decide if it is like "tags" or like "categories
            'show_in_rest' => true, // Enable REST API support
            'rewrite' => [
                'slug' => 'coursecategory',
                'with_front' => true
            ],
            'query_var' => true,
            'show_admin_column' => true
        ]
    );
    register_taxonomy(
        'coursetag', // the slug for the new taxonomy
        ['courses'], // the post type(s) you want to register a custom taxonomy for
        [
            'public' => true,
            'labels' => [
                'name' => __('Course Tags', 'sieben'),
                'singular_name' => __('Course Tag', 'sieben'),
            ],
            'hierarchical' => false, // this will decide if it is like "tags" or like "categories
            'rewrite' => ['slug' => 'course-tag', 'with_front' => false],
            'show_in_rest' => true, // Enable REST API support
            'query_var' => true
        ]
    );
    register_taxonomy(
        'coursetype', // the slug for the new taxonomy
        ['courses'], // the post type(s) you want to register a custom taxonomy for
        [
            'public' => true,
            'labels' => [
                'name' => __('Course Types', 'sieben'),
                'singular_name' => __('Course Type', 'sieben'),
            ],
            'hierarchical' => false, // this will decide if it is like "tags" or like "categories
            'rewrite' => ['slug' => 'course-type', 'with_front' => false],
            'query_var' => true,
            'show_admin_column' => true,
            'show_in_rest' => true, // Enable REST API support
        ]
    );
    register_taxonomy(
        'coursefocus', // the slug for the new taxonomy
        ['courses'], // the post type(s) you want to register a custom taxonomy for
        [
            'public' => true,
            'labels' => [
                'name' => __('Fokus', 'sieben'),
                'singular_name' => __('Fokus', 'sieben'),
            ],
            'hierarchical' => true, // this will decide if it is like "tags" or like "categories
            'rewrite' => ['slug' => 'course-focus', 'with_front' => false],
            'query_var' => true,
            'show_in_rest' => true, // Enable REST API support
            'show_admin_column' => true
        ]

    );

    register_taxonomy(
        'fieldofeducation', // the slug for the new taxonomy
        ['courses'], // the post type(s) you want to register a custom taxonomy for
        [
            'public' => true,
            'labels' => [
                'name' => __('Fields of Education (AMS)', 'sieben'),
                'singular_name' => __('Field of Education (AMS)', 'sieben'),
            ],
            'hierarchical' => true, // this will decide if it is like "tags" or like "categories
            'rewrite' => ['slug' => 'field-of-education', 'with_front' => false],
            'query_var' => true,
            'show_in_rest' => true, // Enable REST API support
            'show_admin_column' => true
        ]
    );


    $ca_args = array(
        'label' => __('Certificate Authority', 'sieben'),
        // Features this CPT supports in Post Editor
        'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
        'hierarchical' => false,
        'public' => true,
        'can_export' => true,
        'has_archive' => true,
        'show_in_rest' => true, // Enable REST API support
        'exclude_from_search' => false,
        'publicly_queryable' => true,
        'capability_type' => 'page',
        'rewrite' => [
            'slug' => 'certificate-authority',
            'with_front' => false
        ],
    );
    // Registering your Custom Post Type
    register_post_type('ca', $ca_args);

    $logo_args = array(
        'label' => __('Kunden Logos', 'sieben'),
        // Features this CPT supports in Post Editor
        'supports' => array('title', 'editor', 'thumbnail', 'custom-fields'),
        'hierarchical' => false,
        'public' => true,
        'can_export' => true,
        'has_archive' => true,
        'exclude_from_search' => false,
        'show_in_rest' => true, // Enable REST API support
        'publicly_queryable' => true,
        'capability_type' => 'page',
        'rewrite' => [
            'slug' => 'kunden-logos',
            'with_front' => false
        ],
    );
    // Registering your Custom Post Type
    register_post_type('logo', $logo_args);
}

add_action('init', 'custom_post_type', 0);

// Add extra fields to "Add New Course Category" form
function sieben_coursecategory_add_meta_field()
{
?>
    <div class="form-field">
        <label for="term_image"><?php _e('Featured Image', 'sieben'); ?></label>
        <input type="text" name="term_image" id="term_image" value="" />
        <button class="button sieben-upload-button"><?php _e('Upload Image', 'sieben'); ?></button>
    </div>
    <div class="form-field">
        <label for="term_content"><?php _e('Custom Content', 'sieben'); ?></label>
        <textarea name="term_content" id="term_content" rows="5"></textarea>
    </div>
<?php
}
add_action('coursecategory_add_form_fields', 'sieben_coursecategory_add_meta_field', 10, 2);

// Add extra fields to "Edit Course Category" form
function sieben_coursecategory_edit_meta_field($term)
{
    $term_id      = $term->term_id;
    $term_image   = get_term_meta($term_id, 'term_image', true);
    $term_content = get_term_meta($term_id, 'term_content', true);
?>
    <tr class="form-field">
        <th scope="row"><label for="term_image"><?php _e('Featured Image', 'sieben'); ?></label></th>
        <td>
            <input type="text" name="term_image" id="term_image" value="<?php echo esc_attr($term_image); ?>" />
            <button class="button sieben-upload-button"><?php _e('Upload Image', 'sieben'); ?></button>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="term_content"><?php _e('Custom Content', 'sieben'); ?></label></th>
        <td>
            <textarea name="term_content" id="term_content" rows="5"><?php echo esc_textarea($term_content); ?></textarea>
        </td>
    </tr>
    <?php
}
add_action('coursecategory_edit_form_fields', 'sieben_coursecategory_edit_meta_field', 10, 2);

// Save fields
function sieben_save_coursecategory_meta($term_id)
{
    if (isset($_POST['term_image'])) {
        update_term_meta($term_id, 'term_image', sanitize_text_field($_POST['term_image']));
    }
    if (isset($_POST['term_content'])) {
        update_term_meta($term_id, 'term_content', sanitize_textarea_field($_POST['term_content']));
    }
}
add_action('edited_coursecategory', 'sieben_save_coursecategory_meta', 10, 2);
add_action('create_coursecategory', 'sieben_save_coursecategory_meta', 10, 2);





function cc_mime_types($mimes)
{
    $mimes['webp'] = 'image/webp';
    $mimes['svg'] = 'image/svg+xml';
    return $mimes;
}

add_filter('upload_mimes', 'cc_mime_types');


/*** for the courses page ***/
function csei_row($label, $value)
{
    if (trim($value) != "") {
    ?>
        <div class="csei-row">
            <span class="csei-lab"><strong>
                    <?php echo $label; ?>
                </strong>
                <?php echo $value; ?>
            </span>
        </div>
    <?php
    }
}

function xsieben_course_base_meta_html()
{
    $custom = get_post_custom(get_the_ID());

    ?>

    <div class="sidebar-inner-container">
        <div class="courses-sidebar-icon zertifikat"></div>
        <div class="courses-sidebar-element-inner abschluss">
            <?php csei_row("ABSCHLUSS:", $custom["zertifikat"][0]); ?>
        </div>
    </div>

    <div class="courses-sidebar-icon clock"></div>
    <div class="courses-sidebar-element-inner">
        <?php
        $start_datum = strtotime(get_post_meta(get_the_ID(), 'start_datum', true));
        $end_datum = strtotime(get_post_meta(get_the_ID(), 'end_datum', true));
        $price = get_post_meta(get_the_ID(), 'kosten', true);

        if ($start_datum > 100) {
            csei_row("KURSBEGINN:", date('d.m.Y', $start_datum));
        }

        if ($end_datum > 100) {
            csei_row("KURSENDE:", date('d.m.Y', $end_datum));
        }

        $termine = intval(get_post_meta(get_the_ID(), 'anzahl_termine', true) . " Tage");
        if ($termine < 1)
            $termine = "";
        //csei_row("Termine:", $termine);

        csei_row("DAUER:", get_post_meta(get_the_ID(), 'dauer', true));

        // time
        $from = trim(get_post_meta(get_the_ID(), 'uhrzeit', true));
        $to = trim(get_post_meta(get_the_ID(), 'uhrzeit_ende', true));
        if ($from != "") {
            $time = "";
            if ($to != "") {
                $time = "Von " . $from . " bis " . $to . " Uhr";
            } else {
                $time = $from . " Uhr";
            }
            csei_row("KURSZEITEN: ", $time);
        }

        csei_row("IHRE INVESTITION: ", "€ " . $price . ".-");

        $ort = (trim($custom["strasse"][0])) ? $custom["strasse"][0] . "<br/>" : '';
        $ort .= (trim($custom["addresszusatz"][0])) ? $custom["addresszusatz"][0] . "<br/>" : '';
        $ort .= $custom["plz"][0] . " " . $custom["ort"][0];
        //csei_row("Ort:", $ort);

        csei_row("", $custom["zeit_zusatz"][0]);
        ?>
    </div>
<?php }

// bootstrap 5 wp_nav_menu walker
class bootstrap_5_wp_nav_menu_walker extends Walker_Nav_menu
{
    private $current_item;
    private $dropdown_menu_alignment_values = [
        'dropdown-menu-start',
        'dropdown-menu-end',
        'dropdown-menu-sm-start',
        'dropdown-menu-sm-end',
        'dropdown-menu-md-start',
        'dropdown-menu-md-end',
        'dropdown-menu-lg-start',
        'dropdown-menu-lg-end',
        'dropdown-menu-xl-start',
        'dropdown-menu-xl-end',
        'dropdown-menu-xxl-start',
        'dropdown-menu-xxl-end'
    ];

    function start_lvl(&$output, $depth = 0, $args = null)
    {
        $dropdown_menu_class[] = '';
        foreach ($this->current_item->classes as $class) {
            if (in_array($class, $this->dropdown_menu_alignment_values)) {
                $dropdown_menu_class[] = $class;
            }
        }
        $indent = str_repeat("\t", $depth);
        $submenu = ($depth > 0) ? ' sub-menu' : '';
        $output .= "\n$indent<ul class=\"dropdown-menu$submenu " . esc_attr(implode(" ", $dropdown_menu_class)) . " depth_$depth\">\n";
    }

    function start_el(&$output, $item, $depth = 0, $args = null, $id = 0)
    {
        $this->current_item = $item;

        $indent = ($depth) ? str_repeat("\t", $depth) : '';

        $li_attributes = '';
        $class_names = $value = '';

        $classes = empty($item->classes) ? array() : (array) $item->classes;

        $classes[] = ($args->walker->has_children) ? 'dropdown' : '';
        $classes[] = 'nav-item';
        $classes[] = 'nav-item-' . $item->ID;
        if ($depth && $args->walker->has_children) {
            $classes[] = 'dropdown-menu dropdown-menu-end';
        }

        $class_names = join(' ', apply_filters('nav_menu_css_class', array_filter($classes), $item, $args));
        $class_names = ' class="' . esc_attr($class_names) . '"';

        $id = apply_filters('nav_menu_item_id', 'menu-item-' . $item->ID, $item, $args);
        $id = strlen($id) ? ' id="' . esc_attr($id) . '"' : '';

        $output .= $indent . '<li ' . $id . $value . $class_names . $li_attributes . '>';

        $attributes = !empty($item->attr_title) ? ' title="' . esc_attr($item->attr_title) . '"' : '';
        $attributes .= !empty($item->target) ? ' target="' . esc_attr($item->target) . '"' : '';
        $attributes .= !empty($item->xfn) ? ' rel="' . esc_attr($item->xfn) . '"' : '';
        $attributes .= !empty($item->url) ? ' href="' . esc_attr($item->url) . '"' : '';

        $active_class = ($item->current || $item->current_item_ancestor || in_array("current_page_parent", $item->classes, true) || in_array("current-post-ancestor", $item->classes, true)) ? 'active' : '';
        $nav_link_class = ($depth > 0) ? 'dropdown-item ' : 'nav-link ';
        $attributes .= ($args->walker->has_children) ? ' class="' . $nav_link_class . $active_class . ' dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"' : ' class="' . $nav_link_class . $active_class . '"';

        $item_output = $args->before;
        $item_output .= '<a' . $attributes . '>';
        $item_output .= $args->link_before . apply_filters('the_title', $item->title, $item->ID) . $args->link_after;
        $item_output .= '</a>';
        $item_output .= $args->after;

        $output .= apply_filters('walker_nav_menu_start_el', $item_output, $item, $depth, $args);
    }
}

function custom_posts_orderby_category($query)
{
    // Check if we're in the admin panel and on the posts page
    if (is_admin() && $query->is_main_query() && $query->get('post_type') === 'post') {
        // Order posts by category
        $query->set('orderby', 'category');
        $query->set('order', 'ASC'); // You can change this to 'DESC' if needed
    }
}
add_action('pre_get_posts', 'custom_posts_orderby_category');


// Hook into the 'template_redirect' action
add_action('template_redirect', 'custom_redirect_to_homepage');

function custom_redirect_to_homepage()
{
    // Check if it's a 404 error
    if (is_404()) {
        // Redirect to the homepage
        wp_redirect(home_url('/'), 301);
        exit;
    }
}

// Remove all plugin notices
remove_all_actions('admin_notices');


// Disable Gutenberg editor
add_filter('use_block_editor_for_post', '__return_false', 10);

// Disable Gutenberg widgets
add_filter('gutenberg_use_widgets_block_editor', '__return_false');
add_filter('use_widgets_block_editor', '__return_false');



function add_all_post_meta_to_rest($response, $post, $request)
{
    // Fetch all meta fields for the post
    $meta_data = get_post_meta($post->ID);

    // Add the meta data to the REST response
    $response->data['meta'] = $meta_data;

    return $response;
}

add_filter('rest_prepare_courses', 'add_all_post_meta_to_rest', 10, 3);

function my_login_logo_from_customizer()
{
    // Get the custom logo ID set in the Customizer
    $custom_logo_id = get_theme_mod('custom_logo');

    // Retrieve the image URL
    $logo = wp_get_attachment_image_src($custom_logo_id, 'full');

    if ($logo) {
        $logo_url = esc_url($logo[0]);
        echo '<style type="text/css">
            #login h1 a, .login h1 a {
                background-image: url(' . $logo_url . ');
                height: 100px;
                width: auto;
                background-size: contain;
                background-repeat: no-repeat;
                padding-bottom: 30px;
            }
        </style>';
    }
}
add_action('login_enqueue_scripts', 'my_login_logo_from_customizer');

/**
 * Make internal links relative and remove target="_blank" from them.
 */
add_filter('the_content', function ($content) {
    // Get your site's base URL (handles http/https, with or without www)
    $site_url = site_url();

    // Normalize for matching (escape regex chars)
    $site_url_pattern = preg_quote($site_url, '#');

    // 1️⃣ Remove the site URL from all internal links (make relative)
    $content = preg_replace(
        '#' . $site_url_pattern . '#i',
        '',
        $content
    );

    // 2️⃣ Remove target="_blank" ONLY from internal links
    // We'll match <a> tags that start with /
    $content = preg_replace_callback(
        '#<a\s+([^>]*href=["\']/(?!/)[^"\']*["\'][^>]*)>#i',
        function ($matches) {
            // Remove any target="_blank" (case-insensitive)
            $tag = preg_replace('/\s*target=["\']_blank["\']/i', '', $matches[1]);
            return '<a ' . trim($tag) . '>';
        },
        $content
    );

    return $content;
});

// /**
//  * REST API Restriktion (Sicherheits-Patch)
//  * Blockiert alle Anfragen von nicht-eingeloggten Benutzern (inklusive Whitelist).
//  */
// add_filter( 'rest_authentication_errors', function( $result ) {
//     // 1. Wenn bereits ein Fehler von einem anderen Plugin vorliegt, diesen durchreichen
//     if ( ! empty( $result ) ) {
//         return $result;
//     }

//     // 2. Whitelist: Real Cookie Banner Route explizit erlauben
//     if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/wp-json/real-cookie-banner/' ) !== false ) {
//         return $result;
//     }

//     // 3. Wenn der Nutzer NICHT eingeloggt ist -> Hartes Blockieren (HTTP 401)
//     if ( ! is_user_logged_in() ) {
//         return new WP_Error( 
//             'rest_forbidden', 
//             'Der Zugriff auf die REST API ist für externe Agenten gesperrt.', 
//             array( 'status' => 401 ) 
//         );
//     }

//     // 4. Wenn eingeloggt -> Request normal passieren lassen
//     return $result;
// });