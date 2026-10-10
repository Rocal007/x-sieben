<?php
/**
 * Render Responsive Top Picture - LCP OPTIMIZED
 */
function render_responsive_top_picture_bg($args = [])
{
    $queried = get_queried_object();
    $is_taxonomy = (is_tax() || is_category() || is_tag() || is_archive()) && ($queried instanceof WP_Term);

    if ($is_taxonomy) {
        $layout = 'split_screen';
        $cache_key = 'hero_html_term_v13_' . $queried->term_id . '_' . $layout;
        $post_id = get_the_ID();
    } elseif (is_singular()) {
        $post_id = get_the_ID();
        $layout = get_post_meta($post_id, 'top_picture_layout', true);
        if (!$layout) {
            $layout = 'split_screen';
        }
        $cache_key = 'hero_html_post_v13_' . $post_id . '_' . $layout;
    } else {
        $post_id = get_the_ID();
        $layout = $post_id ? get_post_meta($post_id, 'top_picture_layout', true) : 'split_screen';
        if (!$layout) {
            $layout = 'split_screen';
        }
        $cache_key = 'hero_html_misc_v13_' . ($post_id ?: md5($_SERVER['REQUEST_URI'] ?? 'home')) . '_' . $layout;
    }

    if (!is_user_logged_in()) {
        $cached_html = get_transient($cache_key);
        if ($cached_html !== false && !empty($cached_html)) {
            echo $cached_html;
            return;
        }
    }

    ob_start();

    // 1. DATEN HOLEN
    $acf_aktuelles = function_exists('get_field') ? get_field('aktuelles', $post_id) : '';

    // Bilder abrufen
    $mobile_img_data = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), 'mobile-top');
    $desktop_img_data = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), 'hero-image');

    // Fallback: Falls args explizite Bild-URLs liefern (z.B. bei Taxonomien)
    if (!$desktop_img_data && !empty($args['desktop_image_url'])) {
        $desktop_img_data = [$args['desktop_image_url'], 1448, 1086];
    }

    // Fallback: Wenn kein Desktop-Bild, Abbruch.
    if (!$desktop_img_data) {
        ob_end_clean();
        return; 
    }

    // Fallback: Wenn kein Mobile-Bild, nimm Desktop bzw. args
    if (!$mobile_img_data) {
        if (!empty($args['mobile_image_url'])) {
            $mobile_img_data = [$args['mobile_image_url'], 991, 557];
        } else {
            $mobile_img_data = $desktop_img_data;
        }
    }

    $defaults = [
        'title'        => $acf_aktuelles ?: get_the_title($post_id),
        'show_buttons' => false,
        'show_logos'   => false,
        'logo_layout'  => 'vertical',
        'alt_text'     => strip_tags(get_the_title($post_id)),
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
        <style>
            @media (min-width: 992px) {
                .top-picture-layout-split_screen .top-picture-flex-wrapper { display: flex !important; align-items: stretch !important; }
                .top-picture-layout-split_screen .top-picture-title { display: flex !important; flex-direction: column !important; justify-content: center !important; min-height: 500px !important; padding-top: 20px !important; padding-bottom: 20px !important; }
            }
        </style>
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
                            <style>
                                .top-picture-logos-overlay { position: absolute; top: 18px; left: 18px; z-index: 10; pointer-events: auto; }
                                .top-picture-logos-overlay .zert-images-vertical { display: flex; flex-direction: column; gap: 8px; }
                                .top-picture-logos-overlay .zert-image-item { background: #ffffff !important; border-radius: 6px !important; padding: 4px 6px !important; border: 1px solid rgba(0,0,0,0.12) !important; box-shadow: 0 2px 6px rgba(0,0,0,0.15) !important; display: inline-flex !important; align-items: center; justify-content: center; }
                                .top-picture-logos-overlay .zert-image-item img, .top-picture-logos-overlay img { max-width: 65px !important; max-height: 42px !important; width: auto !important; height: auto !important; object-fit: contain !important; display: block !important; }
                            </style>
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