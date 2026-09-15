<?php
/**
 * Customization options
 */
function sieben_customize_register($wp_customize) {
    // Color Settings
    sieben_add_color_settings($wp_customize);
    
    // Social Links Settings
    sieben_add_social_links($wp_customize);
    
    // Mobile Logo Upload
    sieben_add_mobile_logo($wp_customize);
}

/**
 * Add color settings to customizer
 */
function sieben_add_color_settings($wp_customize) {
    $color_settings = array(
        'theme_color' => array(
            'default' => '#ffa92c',
            'label' => __('Theme Color', 'sieben'),
            'priority' => 10
        ),
        'secondary_color' => array(
            'default' => '#4D4D4D',
            'label' => __('Secondary Color', 'sieben'),
            'priority' => 11
        ),
        'tertiary_color' => array(
            'default' => '#0073AA',
            'label' => __('Tertiary Color', 'sieben'),
            'priority' => 12
        )
    );

    foreach ($color_settings as $setting_name => $args) {
        $wp_customize->add_setting($setting_name, array(
            'default' => $args['default'],
            'capability' => 'edit_theme_options',
            'sanitize_callback' => 'sanitize_hex_color',
        ));

        $wp_customize->add_control(new WP_Customize_Color_Control(
            $wp_customize,
            $setting_name,
            array(
                'label' => $args['label'],
                'section' => 'colors',
                'priority' => $args['priority']
            )
        ));
    }
}

/**
 * Add social links section and settings
 */
function sieben_add_social_links($wp_customize) {
    $wp_customize->add_section('sieben_social_url_options', array(
        'priority' => 17,
        'capability' => 'edit_theme_options',
        'theme_supports' => '',
        'title' => __('Social Links', 'sieben'),
        'description' => __('Enter URLs for your social networks e.g.', 'sieben') . ' https://twitter.com/example'
    ));

    $social_links = array(
        'Facebook' => 'social_facebook',
        'Twitter' => 'social_twitter',
        'Xing' => 'social_xing',
        'Google-Plus' => 'social_googleplus',
        'Pinterest' => 'social_pinterest',
        'YouTube' => 'social_youtube',
        'Vimeo' => 'social_vimeo',
        'LinkedIn' => 'social_linkedin',
        'Flickr' => 'social_flickr',
        'Tumblr' => 'social_tumblr',
        'Instagram' => 'social_instagram',
        'RSS' => 'social_rss',
        'GitHub' => 'social_github'
    );

    foreach ($social_links as $key => $val) {
        $wp_customize->add_setting('sieben_social_theme_options[' . $val . ']', array(
            'default' => '',
            'type' => 'option',
            'capability' => 'edit_theme_options',
            'transport' => 'postMessage',
            'sanitize_callback' => 'esc_url_raw'
        ));
        
        $wp_customize->add_control('sieben_social_theme_options[' . $val . ']', array(
            'label' => sprintf(__('%s', 'sieben'), $key),
            'section' => 'sieben_social_url_options',
            'settings' => 'sieben_social_theme_options[' . $val . ']',
            'type' => 'text'
        ));
    }
}

/**
 * Add mobile logo setting
 */
function sieben_add_mobile_logo($wp_customize) {
    $wp_customize->add_setting('sieben_mobile_logo', array(
        'default' => '',
        'sanitize_callback' => 'absint',
    ));

    $wp_customize->add_control(new WP_Customize_Media_Control($wp_customize, 'sieben_mobile_logo', array(
        'label' => __('Mobile Logo', 'sieben'),
        'section' => 'title_tagline',
        'mime_type' => 'image',
        'priority' => 9,
    )));
}

add_action('customize_register', 'sieben_customize_register');

/**
 * Output CSS from Customizer settings into the <head>
 */
function sieben_custom_css() {
    // Get the saved color options, with default values
    $theme_color = get_theme_mod('theme_color', '#ffa92c');
    $secondary_color = get_theme_mod('secondary_color', '#4D4D4D');
    $tertiary_color = get_theme_mod('tertiary_color', '#0073AA');
    ?>
    <style type="text/css">
        :root {
            --theme-color: <?php echo esc_attr($theme_color); ?>;
            --secondary-color: <?php echo esc_attr($secondary_color); ?>;
            --tertiary-color: <?php echo esc_attr($tertiary_color); ?>;
        }
    </style>
    <?php 
}

add_action('wp_head', 'sieben_custom_css', 900);