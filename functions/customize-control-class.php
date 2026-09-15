<?php
/**
* Customization options
**/
function sieben_customize_register( $wp_customize ) {

    // --- Color Settings ---
    $wp_customize->add_setting(
        'theme_color',
        array(
            'default' => '#ffa92c',
            'capability'      => 'edit_theme_options',
            'sanitize_callback' => 'sanitize_hex_color',
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Color_Control(
            $wp_customize,
            'theme_color',
            array(
                'label'      => __('Theme Color ', 'sieben'),
                'section' => 'colors',
                'priority' => 10
            )
        )
    );

    $wp_customize->add_setting(
        'secondary_color',
        array(
            'default' => '#4D4D4D',
            'capability'      => 'edit_theme_options',
            'sanitize_callback' => 'sanitize_hex_color',
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Color_Control(
            $wp_customize,
            'secondary_color',
            array(
                'label'      => esc_html__('Secondary Color', 'sieben'),
                'section' => 'colors',
                'priority' => 11
            )
        )
    );
    
    // --- Social Links Settings ---
    $wp_customize->add_section('sieben_social_url_options', array(
        'priority' => 17,
        'capability' => 'edit_theme_options',
        'theme_supports' => '',
        'title' => __('Social Links', 'sieben'),
        'description' => __('Enter URLs for your social networks e.g.', 'sieben') . ' https://twitter.com/example'
    ));

    $social_links = array( 'Facebook' => 'social_facebook', 'Twitter' => 'social_twitter','Xing' => 'social_xing', 'Google-Plus' => 'social_googleplus', 'Pinterest' => 'social_pinterest', 'YouTube' => 'social_youtube', 'Vimeo' => 'social_vimeo', 'LinkedIn' => 'social_linkedin', 'Flickr' => 'social_flickr', 'Tumblr' => 'social_tumblr', 'Instagram' => 'social_instagram', 'RSS' => 'social_rss', 'GitHub' => 'social_github' );
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
add_action( 'customize_register', 'sieben_customize_register' );


add_action('wp_head','sieben_custom_css', 900);
/**
 * Outputs CSS from Customizer settings into the <head>.
 */
function sieben_custom_css(){ 
    // Get the saved color options, with default values
    $theme_color = get_theme_mod('theme_color', '#ffa92c');
    $secondary_color = get_theme_mod('secondary_color', '#4D4D4D');
    ?>
    <style type="text/css">
        /* Example CSS rules using the customizer colors */
        a, .widget-title {
            color: <?php echo esc_attr($theme_color); ?>;
        }
        body {
            color: <?php echo esc_attr($secondary_color); ?>;
        }
    </style>
    <?php 
}