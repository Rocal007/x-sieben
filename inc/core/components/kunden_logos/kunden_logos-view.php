<?php
/**
 * Zeigt Kundenlogos im Grid/Horizontal/Vertical Layout oder als scrollbaren Container mit Buttons und Autoscroll.
 * Lazy-Loading aktiviert.
 *
 * @param string $layout 'grid', 'horizontal', 'vertical', 'scroll'
 * @param int|null $posts_per_page Anzahl der Logos, Standard: alle (-1)
 * @return string HTML der Logos
 */
function kunden_logos($layout = 'grid', $posts_per_page = -1) {
    $query = new WP_Query([
        'post_type'      => 'logo',
        'posts_per_page' => $posts_per_page,
        'post_status'    => 'publish',
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ]);

    if (!$query->have_posts()) {
        return '';
    }

    if ($layout !== 'scroll') {
        // normales Layout (grid/horizontal/vertical)
        $output = '<div class="kunden-logos-' . esc_attr($layout) . '">';
        while ($query->have_posts()) : $query->the_post();
            $post_title = get_the_title();
            $thumb_url  = get_the_post_thumbnail_url(get_the_ID(), 'sieben-rev-logos');
            $permalink  = get_permalink();

            if ($thumb_url) {
                $output .= '<div class="kunden-logo-item">';
                $output .= '<a href="' . esc_url($permalink) . '" title="' . esc_attr($post_title) . '">';
                $output .= '<img 
                                src="' . esc_url($thumb_url) . '" 
                                alt="' . esc_attr($post_title) . '" 
                                title="' . esc_attr($post_title) . '" 
                                role="img" 
                                aria-label="' . esc_attr($post_title) . '" 
                                loading="lazy">';
                $output .= '</a>';
                $output .= '</div>';
            }
        endwhile;
        $output .= '</div>';
    } 
    else {
        // scrollbarer Container
        $container_id = 'kundenScrollContainer' . rand(1000,9999);
        $output = '<div class="kunden-logos-scroll-wrapper" style="position: relative; overflow: hidden;">';
        $output .= '<button class="scroll-left" onclick="scrollLeft' . $container_id . '()" aria-label="Zurück">&lt;</button>';
        $output .= '<div id="' . $container_id . '" class="kunden-logos-scroll" style="display:flex; overflow-x:auto; scroll-behavior: smooth;">';

        while ($query->have_posts()) : $query->the_post();
            $post_title = get_the_title();
            $thumb_url  = get_the_post_thumbnail_url(get_the_ID(), 'sieben-rev-logos');
            $permalink  = get_permalink();

            if ($thumb_url) {
                $output .= '<div class="kunden-logo-item" style="flex: 0 0 auto; margin: 0 10px;">';
                $output .= '<a href="' . esc_url($permalink) . '" title="' . esc_attr($post_title) . '">';
                $output .= '<img src="' . esc_url($thumb_url) . '" 
                                alt="' . esc_attr($post_title) . '" 
                                title="' . esc_attr($post_title) . '" 
                                role="img" 
                                aria-label="' . esc_attr($post_title) . '" 
                                loading="lazy" 
                                style="height: 80px; object-fit: contain;">';
                $output .= '</a></div>';
            }
        endwhile;

        $output .= '</div>'; // scroll container
        $output .= '<button class="scroll-right" onclick="scrollRight' . $container_id . '()" aria-label="Weiter">&gt;</button>';
        $output .= '</div>';

        // JS für Buttons und langsamen Autoscroll
        $output .= '<script>
            const container' . $container_id . ' = document.getElementById("' . $container_id . '");
            function scrollLeft' . $container_id . '() { container' . $container_id . '.scrollBy({ left: -200, behavior: "smooth" }); }
            function scrollRight' . $container_id . '() { container' . $container_id . '.scrollBy({ left: 200, behavior: "smooth" }); }

            let scrollAmount = 1;
            function autoScroll' . $container_id . '() {
                container' . $container_id . '.scrollLeft += scrollAmount;
                if(container' . $container_id . '.scrollLeft + container' . $container_id . '.clientWidth >= container' . $container_id . '.scrollWidth || container' . $container_id . '.scrollLeft === 0) {
                    scrollAmount = -scrollAmount;
                }
            }
            setInterval(autoScroll' . $container_id . ', 30); // Geschwindigkeit anpassen
        </script>';

        // kleine CSS-Anpassungen
        $output .= '<style>
            .kunden-logos-scroll-wrapper button { position:absolute; top:50%; transform:translateY(-50%); z-index:10; background:#fff; border:1px solid #ccc; cursor:pointer; padding:5px; }
            .kunden-logos-scroll-wrapper .scroll-left { left:0; }
            .kunden-logos-scroll-wrapper .scroll-right { right:0; }
            .kunden-logos-scroll::-webkit-scrollbar { display:none; }
        </style>';
    }

    wp_reset_postdata();
    return $output;
}

/**
 * Shortcode für Kundenlogos
 *
 * [kunden_logos layout="grid|horizontal|vertical|scroll" posts_per_page="10"]
 */
function kunden_logos_shortcode($atts) {
    $atts = shortcode_atts([
        'layout'         => 'grid',
        'posts_per_page' => -1,
    ], $atts, 'kunden_logos');

    return kunden_logos($atts['layout'], $atts['posts_per_page']);
}
add_shortcode('kunden_logos', 'kunden_logos_shortcode');
