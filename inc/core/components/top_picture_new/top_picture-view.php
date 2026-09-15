<?php
/**
 * Render Responsive Top Picture (Refactored)
 * * Features:
 * - High Performance (LCP Optimierung)
 * - Server-Side Caching (Transients)
 * - Clean HTML Output (Keine Inline-Styles)
 * - Unterstützt "Infinite Right" Desktop & "Reverse Order" Mobile
 */
function render_responsive_top_picture_bg($args = [])
{
    $post_id = get_the_ID();

    // -----------------------------------------------------------
    // 1. CACHE CHECK (Sofortiger Return wenn möglich)
    // -----------------------------------------------------------
    $cache_key = 'hero_html_v6_' . $post_id; 

    if (!is_user_logged_in()) {
        $cached_html = get_transient($cache_key);
        if ($cached_html !== false && !empty($cached_html)) {
            echo $cached_html;
            return;
        }
    }

    // -----------------------------------------------------------
    // 2. DATEN VORBEREITUNG
    // -----------------------------------------------------------
    
    // Bilder holen
    $thumb_id = get_post_thumbnail_id($post_id);
    $desktop_img = wp_get_attachment_image_src($thumb_id, 'hero-image');

    // Abbruch, wenn kein Desktop-Bild vorhanden ist
    if (!$desktop_img) {
        return; 
    }

    // Mobile Bild holen (Fallback auf Desktop, falls nicht gesetzt)
    $mobile_img = wp_get_attachment_image_src($thumb_id, 'mobile-top');
    if (!$mobile_img) {
        $mobile_img = $desktop_img;
    }

    // Titel Logik
    $acf_title = function_exists('get_field') ? get_field('aktuelles') : '';
    
    // Einstellungen mergen
    $config = wp_parse_args($args, [
        'title'        => $acf_title ?: get_the_title(),
        'show_buttons' => false,
        'show_logos'   => false,
        'logo_layout'  => 'vertical',
        'alt_text'     => strip_tags(get_the_title()), // Sauberes Alt-Attribut
    ]);

    // Variablen extrahieren für sauberes HTML Template
    $d_url  = esc_url($desktop_img[0]);
    $d_w    = $desktop_img[1];
    $d_h    = $desktop_img[2];
    $d_webp = $d_url . '.webp';

    $m_url  = esc_url($mobile_img[0]);
    $m_webp = $m_url . '.webp';

    // -----------------------------------------------------------
    // 3. HTML OUTPUT (Output Buffering)
    // -----------------------------------------------------------
    ob_start(); 
    ?>

    <div class="container-fluid top-picture-section">
        <div class="container h-100">
            <div class="row top-picture-flex-wrapper">

                <div class="col-md-6 col-xs-12 top-picture-title pdtb-default">
                    
                    <div><?= $config['title']; ?></div>
                    
                    <?php if ($config['show_buttons']) : ?>
                        <div class="top-picture-buttons pdtb-default">
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
                    
                    <picture>
                        <source media="(max-width: 991px)" srcset="<?= $m_webp; ?>" type="image/webp">
                        <source media="(max-width: 991px)" srcset="<?= $m_url; ?>">
                        
                        <source srcset="<?= $d_webp; ?>" type="image/webp">
                        
                        <img
                            src="<?= $d_url; ?>"
                            alt="<?= esc_attr($config['alt_text']); ?>"
                            width="<?= $d_w; ?>"
                            height="<?= $d_h; ?>"
                            class="no-lazy skip-lazy"
                            data-no-lazy="1"
                            fetchpriority="high"
                            loading="eager"
                            decoding="sync"
                        >
                    </picture>

                    <?php if ($config['show_logos']) : ?>
                        <div class="top-picture-logos-overlay hidden-xs">
                            <?= certs_courses($post_id, $config['logo_layout']); ?>
                        </div>
                    <?php endif; ?>

                </div>

            </div>
        </div>
    </div>

    <?php
    // -----------------------------------------------------------
    // 4. CACHE SPEICHERN & AUSGABE
    // -----------------------------------------------------------
    $final_html = ob_get_clean();

    if (!empty($final_html)) {
        // Cache für 1 Tag speichern
        set_transient($cache_key, $final_html, DAY_IN_SECONDS);
    }
    
    echo $final_html;
}