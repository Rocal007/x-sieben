<?php
/**
 * Render Responsive Top Picture - LCP OPTIMIZED
 */
function render_responsive_top_picture_bg($args = [])
{
    $post_id = get_the_ID();
    
    // Fetch layout setting from backend (default to split_screen)
    $layout = get_post_meta($post_id, 'top_picture_layout', true);
    if (!$layout) {
        $layout = 'split_screen';
    }

    $cache_key = 'hero_html_layout_v9_' . $post_id . '_' . $layout;
    if (!is_user_logged_in()) {
        $cached_html = get_transient($cache_key);
        if ($cached_html !== false && !empty($cached_html)) {
            echo $cached_html;
            return;
        }
    }

    ob_start();

    // 1. DATEN HOLEN
    $acf_aktuelles = function_exists('get_field') ? get_field('aktuelles') : '';

    // Bilder abrufen
    $mobile_img_data = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), 'mobile-top');
    $desktop_img_data = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), 'hero-image');

    // Fallback: Wenn kein Desktop-Bild, Abbruch.
    if (!$desktop_img_data) {
        ob_end_clean();
        return; 
    }

    // Fallback: Wenn kein Mobile-Bild, nimm Desktop
    if (!$mobile_img_data) {
        $mobile_img_data = $desktop_img_data;
    }

    $defaults = [
        'title'        => $acf_aktuelles ?: get_the_title(),
        'show_buttons' => false,
        'show_logos'   => false,
        'logo_layout'  => 'vertical',
        'alt_text'     => strip_tags(get_the_title()),
    ];

    $args = wp_parse_args($args, $defaults);
    $title = $args['title'];

    // URLs & Maße extrahieren
    $d_url = esc_url($desktop_img_data[0]);
    $d_w   = $desktop_img_data[1];
    $d_h   = $desktop_img_data[2];
    
    $m_url = esc_url($mobile_img_data[0]);
    $d_webp = $d_url . '.webp';
    $m_webp = $m_url . '.webp';
?>

    <link rel="preload" as="image" href="<?= $m_webp; ?>" media="(max-width: 991px)" fetchpriority="high">
    <link rel="preload" as="image" href="<?= $d_webp; ?>" media="(min-width: 992px)" fetchpriority="high">

    <div class="container-fluid top-picture-section top-picture-layout-<?= esc_attr($layout); ?>">
        <div class="container h-100">
            <div class="row top-picture-flex-wrapper">

                <div class="col-md-6 col-xs-12 top-picture-title pdtb-default">
                    <div><?= $title; ?></div>
                    <?php if ($args['show_buttons']) : ?>
                     <div class="top-picture-buttons">
                            <div class="col-md-6 col-xs-12 pdb10">
                                <a class="btn btn-primary btn-block btn-lg"
   href="#passender-schwerpunkt">
    Passenden Schwerpunkt finden
</a>
                            </div>
                            <div class="col-md-6 col-xs-12 pdb10">
                                <a class="btn btn-info btn-block btn-lg" href="/kurse-uebersicht/">Programme entdecken</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6 col-xs-12 top-picture-image-wrapper">
                    
                    <picture class="no-lazy skip-lazy" data-no-lazy="1">
                        <source media="(max-width: 991px)" srcset="<?= $m_webp; ?>" type="image/webp">
                        <source media="(max-width: 991px)" srcset="<?= $m_url; ?>">
                        
                        <source srcset="<?= $d_webp; ?>" type="image/webp">

                        <img
                            src="<?= $d_url; ?>"
                            alt="<?= esc_attr($args['alt_text']); ?>"
                            width="<?= $d_w; ?>"
                            height="<?= $d_h; ?>"
                            class="no-lazy skip-lazy"
                            data-no-lazy="1"
                            fetchpriority="high"
                            loading="eager"
                            decoding="sync" 
                        >
                    </picture>

                    <?php if ($args['show_logos']) : ?>
                        <div class="top-picture-logos-overlay hidden-xs">
                            <?= certs_courses($post_id, $args['logo_layout']); ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

<?php
    $final_html = ob_get_clean();
    
    if (!empty($final_html)) {
        set_transient($cache_key, $final_html, DAY_IN_SECONDS);
    }
    
    echo $final_html;
}

/**
 * Backward compatibility alias function for templates calling render_responsive_top_picture directly.
 */
if (!function_exists('render_responsive_top_picture')) {
    function render_responsive_top_picture($args = [])
    {
        render_responsive_top_picture_bg($args);
    }
}