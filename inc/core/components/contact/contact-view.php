<?php

/**
 * Master array for contacts and social links with icons, accessibility and SEO
 */
function xsieben_get_links()
{
    // Contact info from theme mods
    $phone    = get_theme_mod('xsieben_phone', '+43 800 700 170');
    $email    = sanitize_email(get_theme_mod('xsieben_email', 'office@x-sieben.at'));
    $whatsapp = get_theme_mod('xsieben_whatsapp', 'https://lp.chatwerk.de/?organizationId=pqodsTzCbi&channelId=zKJsRqNMGm&messenger=WhatsApp');

    // Social links from theme mods
    $instagram = get_theme_mod('xsieben_instagram', 'https://www.instagram.com/x_sieben_wirtschaftstraining/');
    $facebook  = get_theme_mod('xsieben_facebook', 'https://www.facebook.com/x.sieben.events');
    $linkedin  = get_theme_mod('xsieben_linkedin', 'https://www.linkedin.com/school/x-sieben-training---seminare-lehrg-nge-kurse-coaching/');
    $youtube   = get_theme_mod('xsieben_youtube', 'https://www.youtube.com/channel/UCBYw9E5wJZbarvKylJltsHg');

    // Master array
    $links = [
        'contacts' => [
            [
                'title' => 'Telefon',
                'icon'  => 'fa fa-mobile',
                'link'  => 'tel:' . esc_attr(preg_replace('/\s+/', '', $phone)),
                'class' => 'phone',
                'text'  => 'Möchten Sie mehr erfahren? Rufen Sie uns jetzt an und sprechen Sie direkt mit unserem Team',
                'aria_label' => 'Rufen Sie uns an unter ' . esc_html($phone),
                'external' => false,
            ],
            [
                'title' => 'E-Mail',
                'icon'  => 'fa fa-envelope-o',
                'link'  => 'mailto:' . $email,
                'class' => 'email',
                'text'  => 'Schreiben Sie uns eine E-Mail mit Ihren Anliegen. Wir antworten Ihnen schnellstmöglich',
                'aria_label' => 'E-Mail senden an ' . esc_html($email),
                'external' => false,
            ],
            [
                'title' => 'Zoom-Beratung',
                'icon'  => 'fa fa-video-camera',
                'link'  => 'https://calendly.com/xsieben/meeting-buchen-individuelles-beratungsgespraech?month=2023-01',
                'class' => 'zoom',
                'text'  => 'Vereinbaren Sie einen Zoom-Call und erhalten Sie persönliche Beratung von unseren Experten',
                'aria_label' => 'Zoom-Beratung buchen',
                'external' => true,
            ],
            [
                'title' => 'WhatsApp Chat',
                'icon'  => 'fa fa-whatsapp',
                'link'  => esc_url($whatsapp),
                'class' => 'whatsapp',
                'text'  => 'Kontaktieren Sie uns über WhatsApp für schnelle Antworten und unkomplizierte Hilfe',
                'aria_label' => 'WhatsApp Chat öffnen',
                'external' => true,
            ],
        ],
        'socials' => [
            [
                'title' => 'Instagram',
                'link'  => esc_url($instagram),
                'icon'  => 'fa fa-instagram',
                'aria_label' => 'Folgen Sie uns auf Instagram',
            ],
            [
                'title' => 'Facebook',
                'link'  => esc_url($facebook),
                'icon'  => 'fa fa-facebook',
                'aria_label' => 'Folgen Sie uns auf Facebook',
            ],
            [
                'title' => 'LinkedIn',
                'link'  => esc_url($linkedin),
                'icon'  => 'fa fa-linkedin',
                'aria_label' => 'Folgen Sie uns auf LinkedIn',
            ],
            [
                'title' => 'YouTube',
                'link'  => esc_url($youtube),
                'icon'  => 'fa fa-youtube',
                'aria_label' => 'Folgen Sie uns auf YouTube',
            ],
        ]
    ];

    return $links;
}

/**
 * Contact Line Shortcode
 */
function xsieben_contact_line()
{
    $contacts = xsieben_get_links()['contacts'];

    ob_start(); ?>
    <div class="container-fluid">
        <div class="container">
            <section class="xsieben-contact-line text-center" aria-label="Kontaktinformationen">
                <div class="row">
                    <?php foreach ($contacts as $contact) : ?>
                        <div class="col-sm-3 contact-column mrtb50">
                            <a href="<?php echo esc_attr($contact['link']); ?>"
                               class="contact-icon <?php echo esc_attr($contact['class']); ?>"
                               aria-label="<?php echo esc_attr($contact['aria_label']); ?>"
                               title="<?php echo esc_attr($contact['title']); ?>"
                               <?php echo $contact['external'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                <i class="<?php echo esc_attr($contact['icon']); ?>" aria-hidden="true"></i>
                            </a>
                            <div class="h5 mrt20"><strong><?php echo esc_html($contact['title']); ?></strong></div>
                            <p><?php echo esc_html($contact['text']); ?></p>
                            <!-- Button inside the same card -->
                            <a href="<?php echo esc_attr($contact['link']); ?>"
                               class="btn btn-primary mrtb30"
                               aria-label="<?php echo esc_attr($contact['aria_label']); ?>"
                               title="<?php echo esc_attr($contact['title']); ?>"
                               <?php echo $contact['external'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                                <?php
                                switch ($contact['class']) {
                                    case 'phone':
                                        echo 'Rufen Sie uns an';
                                        break;
                                    case 'email':
                                        echo 'E-Mail senden';
                                        break;
                                    case 'zoom':
                                        echo 'Beraten lassen';
                                        break;
                                    case 'whatsapp':
                                        echo 'Mit uns chatten';
                                        break;
                                    default:
                                        echo esc_html($contact['title']);
                                }
                                ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('xsieben_contact_line', 'xsieben_contact_line');

/**
 * Compact Contact Block Shortcode
 */
function xsieben_contact_block()
{
    $contacts = xsieben_get_links()['contacts'];

    ob_start(); ?>
    <section id="hciss" class="xsieben-contact-block" aria-label="Kontaktmöglichkeiten">
        <?php foreach ($contacts as $contact) : ?>
            <a href="<?php echo esc_attr($contact['link']); ?>"
               aria-label="<?php echo esc_attr($contact['aria_label']); ?>"
               title="<?php echo esc_attr($contact['title']); ?>"
               <?php echo $contact['external'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                <i class="<?php echo esc_attr($contact['icon']); ?> pdl10" aria-hidden="true"></i>
                <?php echo esc_html($contact['title']); ?>
            </a>
        <?php endforeach; ?>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode('xsieben_contacts', 'xsieben_contact_block');

/**
 * Social Block Shortcode
 */
function xsieben_social_block()
{
    $socials = xsieben_get_links()['socials'];

    ob_start(); ?>
    <nav class="xsieben-socials footer-column" aria-label="Soziale Netzwerke">
        <div class="footer-title"><?php _e('Folgen Sie uns', 'xsieben'); ?></div>
        <ul class="social-links list-unstyled">
            <?php foreach ($socials as $social) : ?>
                <li>
                    <a href="<?php echo esc_url($social['link']); ?>"
                       target="_blank" rel="noopener noreferrer"
                       aria-label="<?php echo esc_attr($social['aria_label']); ?>"
                       title="<?php echo esc_attr($social['title']); ?>">
                        <i class="<?php echo esc_attr($social['icon']); ?>" aria-hidden="true"></i>
                        <span> <?php echo esc_html($social['title']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <?php
    return ob_get_clean();
}
add_shortcode('xsieben_socials', 'xsieben_social_block');
