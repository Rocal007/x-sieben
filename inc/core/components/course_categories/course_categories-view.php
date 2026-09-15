<?php 
function x_sieben_render_course_categories_overview($args = []) {
    $defaults = ['hide_empty' => false];
    $args = wp_parse_args($args, $defaults);

    $terms = get_terms([
        'taxonomy'   => 'coursecategory',
        'hide_empty' => $args['hide_empty'],
    ]);

    if (empty($terms) || is_wp_error($terms)) {
        echo '<p>Keine Kurskategorien gefunden.</p>';
        return;
    }

    echo '<section class="x7-courses-grid" aria-label="Kurskategorien Übersicht">';

    foreach ($terms as $term) {
        $image = get_field('category_image', 'coursecategory_' . $term->term_id);
        $image_url = '';
        $image_alt = esc_attr($term->name);

        if ($image) {
            if (is_array($image)) {
                $image_url = $image['url'] ?? '';
                $image_alt = !empty($image['alt']) ? esc_attr($image['alt']) : esc_attr($term->name);
            } elseif (is_numeric($image)) {
                $image_url = wp_get_attachment_image_url($image, 'medium');
                $image_alt = get_post_meta($image, '_wp_attachment_image_alt', true) ?: $term->name;
            } else {
                $image_url = $image;
            }
        }

        if (!$image_url) {
            $image_url = get_template_directory_uri() . '/assets/img/default-category.jpg';
            $image_alt = 'Standardbild für Kurskategorie ' . esc_attr($term->name);
        }

        echo '<article class="x7-course-card" itemscope itemtype="https://schema.org/CollectionPage">';
        echo '  <a href="' . esc_url(get_term_link($term)) . '" aria-label="Mehr zu ' . esc_attr($term->name) . '" itemprop="url">';
        echo '      <div class="x7-card-image-wrapper">';
        echo '          <img src="' . esc_url($image_url) . '" alt="' . esc_attr($image_alt) . '" itemprop="image">';
        echo '      </div>';
        echo '      <h2 class="x7-card-title" itemprop="name">' . esc_html($term->name) . '</h2>';
        echo '  </a>';
        if (!empty($term->description)) {
            echo '<p class="x7-card-description" itemprop="description">' . esc_html(wp_trim_words($term->description, 25)) . '</p>';
        }
        echo '</article>';
    }

    echo '</section>';

    // JSON-LD für SEO
    $json_ld = [
        '@context' => 'https://schema.org',
        '@type'    => 'ItemList',
        'name'     => 'Kurskategorien',
        'itemListElement' => [],
    ];

    $position = 1;
    foreach ($terms as $term) {
        $image = get_field('category_image', 'coursecategory_' . $term->term_id);
        $image_url = '';
        if ($image) {
            if (is_array($image) && isset($image['url'])) {
                $image_url = $image['url'];
            } elseif (is_numeric($image)) {
                $image_url = wp_get_attachment_image_url($image, 'medium');
            } else {
                $image_url = $image;
            }
        }
        if (!$image_url) {
            $image_url = get_template_directory_uri() . '/assets/img/default-category.jpg';
        }

        $json_ld['itemListElement'][] = [
            '@type'    => 'ListItem',
            'position' => $position,
            'url'      => get_term_link($term),
            'name'     => $term->name,
            'image'    => $image_url,
            'description' => wp_trim_words($term->description, 25),
        ];

        $position++;
    }

    echo '<script type="application/ld+json">' . wp_json_encode($json_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>';
}
