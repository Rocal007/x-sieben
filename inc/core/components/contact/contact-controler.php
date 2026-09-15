<?php
// 1. Customizer Felder
function xsieben_customize_register( $wp_customize ) {

    /*
     * === KONTAKT-BLOCK ===
     */
    $wp_customize->add_section( 'xsieben_contact_section', array(
        'title'       => __( 'Kontakt Block', 'xsieben' ),
        'description' => __( 'Pflege hier deine Kontaktdaten für den Block', 'xsieben' ),
        'priority'    => 30,
    ) );

    // Telefon
    $wp_customize->add_setting( 'xsieben_phone', array(
        'default'           => '+43 800 700 170',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'xsieben_phone', array(
        'label'   => __( 'Telefonnummer', 'xsieben' ),
        'section' => 'xsieben_contact_section',
        'type'    => 'text',
    ) );

    // E-Mail
    $wp_customize->add_setting( 'xsieben_email', array(
        'default'           => 'office@x-sieben.at',
        'sanitize_callback' => 'sanitize_email',
    ) );
    $wp_customize->add_control( 'xsieben_email', array(
        'label'   => __( 'E-Mail-Adresse', 'xsieben' ),
        'section' => 'xsieben_contact_section',
        'type'    => 'email',
    ) );

    // WhatsApp
    $wp_customize->add_setting( 'xsieben_whatsapp', array(
        'default'           => 'https://lp.chatwerk.de/?organizationId=pqodsTzCbi&channelId=zKJsRqNMGm&messenger=WhatsApp',
        'sanitize_callback' => 'esc_url_raw',
    ) );
    $wp_customize->add_control( 'xsieben_whatsapp', array(
        'label'   => __( 'WhatsApp Link', 'xsieben' ),
        'section' => 'xsieben_contact_section',
        'type'    => 'url',
    ) );

    /*
     * === SOCIAL LINKS ===
     */
    $wp_customize->add_section( 'xsieben_social_section', array(
        'title'       => __( 'Social Media Links', 'xsieben' ),
        'description' => __( 'Links zu deinen Social Media Kanälen', 'xsieben' ),
        'priority'    => 31,
    ) );

    // Instagram
    $wp_customize->add_setting( 'xsieben_instagram', array(
        'default'           => 'https://www.instagram.com/x_sieben_wirtschaftstraining/',
        'sanitize_callback' => 'esc_url_raw',
    ) );
    $wp_customize->add_control( 'xsieben_instagram', array(
        'label'   => __( 'Instagram', 'xsieben' ),
        'section' => 'xsieben_social_section',
        'type'    => 'url',
    ) );

    // Facebook
    $wp_customize->add_setting( 'xsieben_facebook', array(
        'default'           => 'https://www.facebook.com/x.sieben.events',
        'sanitize_callback' => 'esc_url_raw',
    ) );
    $wp_customize->add_control( 'xsieben_facebook', array(
        'label'   => __( 'Facebook', 'xsieben' ),
        'section' => 'xsieben_social_section',
        'type'    => 'url',
    ) );

    // LinkedIn
    $wp_customize->add_setting( 'xsieben_linkedin', array(
        'default'           => 'https://www.linkedin.com/school/x-sieben-training---seminare-lehrg-nge-kurse-coaching/',
        'sanitize_callback' => 'esc_url_raw',
    ) );
    $wp_customize->add_control( 'xsieben_linkedin', array(
        'label'   => __( 'LinkedIn', 'xsieben' ),
        'section' => 'xsieben_social_section',
        'type'    => 'url',
    ) );

    // YouTube
    $wp_customize->add_setting( 'xsieben_youtube', array(
        'default'           => 'https://www.youtube.com/channel/UCBYw9E5wJZbarvKylJltsHg',
        'sanitize_callback' => 'esc_url_raw',
    ) );
    $wp_customize->add_control( 'xsieben_youtube', array(
        'label'   => __( 'YouTube', 'xsieben' ),
        'section' => 'xsieben_social_section',
        'type'    => 'url',
    ) );
}
add_action( 'customize_register', 'xsieben_customize_register' );
