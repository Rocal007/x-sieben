<?php
function display_share_buttons()
{
    $social_media_links = [
        'facebook'  => ['url' => 'https://www.facebook.com/sharer/sharer.php?u=', 'label' => 'Bei Facebook teilen', 'icon' => 'fa-facebook'],
        'twitter'   => ['url' => 'https://twitter.com/share?url=', 'label' => 'Bei Twitter teilen', 'icon' => 'fa-twitter'],
        'linkedin'  => ['url' => 'https://www.linkedin.com/shareArticle?mini=true&url=', 'label' => 'Bei LinkedIn teilen', 'icon' => 'fa-linkedin'],
        'xing'      => ['url' => 'https://www.xing.com/spi/shares/new?url=', 'label' => 'Bei XING teilen', 'icon' => 'fa-xing'],
        'whatsapp'  => ['url' => 'https://api.whatsapp.com/send?text=', 'label' => 'Bei Whatsapp teilen', 'icon' => 'fa-whatsapp'],   ];
    ?>
    <div class="shariff shariff-align-flex-start" role="group" aria-label="Social media share buttons">
        <ul class="share-buttons-horizontal x7-social-share-list">
            <?php foreach ( $social_media_links as $platform => $data ) : ?>
                <li class="share shariff-button <?php echo esc_attr( $platform ); ?>">
                    <a href="<?php echo esc_url( $data['url'] . get_permalink() ); ?>"
                       title="<?php echo esc_attr( $data['label'] ); ?>"
                       aria-label="<?php echo esc_attr( $data['label'] ); ?>"
                       role="button"
                       rel="noopener nofollow"
                       class="shariff-link x7-touch-target share-color-<?php echo esc_attr( $platform ); ?>"
                       target="_blank">
                        <span class="shariff-icon">
                            <i class="fa <?php echo esc_attr( $data['icon'] ); ?>" aria-hidden="true"></i>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

/**
 * Display Social Buttons for Sharing or Following
 * * @param string $mode Options: 'share' or 'follow'
 */
function social_buttons($mode = 'share')
{
    // Define the base handles/URLs for your profiles (Update these!)
    $profiles = [
        'facebook'  => 'https://www.facebook.com/YOUR_PAGE',
        'twitter'   => 'https://twitter.com/YOUR_PROFILE',
        'linkedin'  => 'https://www.linkedin.com/company/YOUR_COMPANY',
        'xing'      => 'https://www.xing.com/pages/YOUR_PAGE',
        'whatsapp'  => '+49123456789' // Only used for follow/contact mode
    ];

    $social_media_links = [
        'facebook'  => ['share' => 'https://www.facebook.com/sharer/sharer.php?u=', 'icon' => 'fa-facebook', 'label_share' => 'Bei Facebook teilen', 'label_follow' => 'Folge uns auf Facebook'],
        'twitter'   => ['share' => 'https://twitter.com/share?url=', 'icon' => 'fa-twitter', 'label_share' => 'Bei Twitter teilen', 'label_follow' => 'Folge uns auf Twitter'],
        'linkedin'  => ['share' => 'https://www.linkedin.com/shareArticle?mini=true&url=', 'icon' => 'fa-linkedin', 'label_share' => 'Bei LinkedIn teilen', 'label_follow' => 'Folge uns auf LinkedIn'],
        'xing'      => ['share' => 'https://www.xing.com/spi/shares/new?url=', 'icon' => 'fa-xing', 'label_share' => 'Bei XING teilen', 'label_follow' => 'Folge uns auf XING'],
        'whatsapp'  => ['share' => 'https://api.whatsapp.com/send?text=', 'icon' => 'fa-whatsapp', 'label_share' => 'Bei Whatsapp teilen', 'label_follow' => 'Schreib uns bei Whatsapp'],
    ];

    ?>
    <div class="shariff shariff-align-flex-start social-mode-<?php echo esc_attr($mode); ?>" role="group" aria-label="Social media buttons">
        <ul class="share-buttons-horizontal x7-social-share-list">
            <?php foreach ($social_media_links as $platform => $data) : 
                // Determine the correct URL and Label based on mode
                if ($mode === 'follow') {
                    $final_url = $profiles[$platform];
                    $final_label = $data['label_follow'];
                    $rel = 'noopener';
                } else {
                    $final_url = $data['share'] . get_permalink();
                    $final_label = $data['label_share'];
                    $rel = 'noopener nofollow';
                }
            ?>
                <li class="share shariff-button <?php echo esc_attr($platform); ?>">
                    <a href="<?php echo esc_url($final_url); ?>"
                       title="<?php echo esc_attr($final_label); ?>"
                       aria-label="<?php echo esc_attr($final_label); ?>"
                       role="button"
                       rel="<?php echo esc_attr($rel); ?>"
                       class="shariff-link x7-touch-target share-color-<?php echo esc_attr($platform); ?>"
                       target="_blank">
                        <span class="shariff-icon">
                            <i class="fa <?php echo esc_attr($data['icon']); ?>" aria-hidden="true"></i>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}