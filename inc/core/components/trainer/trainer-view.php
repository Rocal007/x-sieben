<?php
/**
 * X SIEBEN Members Grid mit Cache und Schema
 */

function x_sieben_members_grid($atts = []) {
    $atts = shortcode_atts([
        'schema' => true,
    ], $atts, 'x_sieben_members');

    $schema_enabled = filter_var($atts['schema'], FILTER_VALIDATE_BOOLEAN);
    $transient_key = 'x_sieben_members_grid_' . ($schema_enabled ? 'with_schema' : 'no_schema');

    // Objektcache zuerst
    $cached = wp_cache_get($transient_key, 'x_sieben_members_grid');
    if ($cached) return $cached;

    // Fallback auf Transient
    if (false === $cached) {
        $cached = get_transient($transient_key);
        if ($cached) return $cached;
    }

    $args = [
        'post_type' => 'members',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ];
    $members = new WP_Query($args);
    if (!$members->have_posts()) return '';

    ob_start(); ?>
    <section class="x-sieben-members-grid-container" aria-label="X SIEBEN Mentoren">
        <h2 class="x-sieben-members-grid-title">Einige der Weiterentwicklungs-Mentoren des X SIEBEN Referent:innen-Pools</h2>
        <div class="x-sieben-members-grid" role="list">
            <?php while ($members->have_posts()) : $members->the_post();
                $name = get_the_title();
                $role = get_field('role') ?: 'X SIEBEN Referent';
                $position = get_field('position') ?: $role;
                $link = get_permalink();
                $img = get_the_post_thumbnail_url(get_the_ID(), 'full');
                $credentials = get_field('credentials') ?: null;

                $aria_label = $name . ', ' . $position;
                if ($credentials) $aria_label .= ', ' . $credentials;
            ?>
                <a href="<?php echo esc_url($link); ?>" target="_blank" class="x-sieben-members-card" role="listitem" aria-label="<?php echo esc_attr($aria_label); ?>" itemprop="url">
                    <div class="x-sieben-members-img-wrapper">
                        <?php if ($img) : ?>
                            <img src="<?php echo esc_url($img); ?>" class="x-sieben-members-img" alt="<?php echo esc_attr($aria_label); ?>" loading="lazy" itemprop="image">
                        <?php endif; ?>
                    </div>
                    <div class="x-sieben-members-name" itemprop="name"><?php echo esc_html($name); ?></div>
                    <div class="x-sieben-members-role" itemprop="jobTitle"><?php echo esc_html($position); ?></div>
                </a>
            <?php endwhile; ?>
        </div>
    </section>

    <?php if ($schema_enabled) :
        $members->rewind_posts();
        $items = [];
        $position_counter = 1;
        while ($members->have_posts()) : $members->the_post();
            $person = [
                "@type" => "Person",
                "name" => get_the_title(),
                "jobTitle" => get_field('position') ?: get_field('role') ?: 'X SIEBEN Referent',
                "url" => get_permalink()
            ];
            $img = get_the_post_thumbnail_url(get_the_ID(), 'full');
            if ($img) $person['image'] = $img;

            $items[] = json_encode([
                "@type" => "ListItem",
                "position" => $position_counter,
                "item" => $person
            ]);
            $position_counter++;
        endwhile;
        wp_reset_postdata(); ?>
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "ItemList",
            "itemListElement": [<?php echo implode(",", $items); ?>]
        }
        </script>
    <?php endif;

    $output = ob_get_clean();

    // Cache speichern
    wp_cache_set($transient_key, $output, 'x_sieben_members_grid', 12 * HOUR_IN_SECONDS);
    set_transient($transient_key, $output, 12 * HOUR_IN_SECONDS);

    return $output;
}

/**
 * Shortcode für X SIEBEN Members
 */
function x_sieben_members_shortcode($atts = []) {
    return x_sieben_members_grid($atts);
}
add_shortcode('x_sieben_members', 'x_sieben_members_shortcode');

/**
 * Cache löschen bei Update/Deletion
 */
function x_sieben_members_clear_cache($post_id) {
    if (get_post_type($post_id) !== 'members') return;

    delete_transient('x_sieben_members_grid_with_schema');
    delete_transient('x_sieben_members_grid_no_schema');
    wp_cache_delete('x_sieben_members_grid_with_schema', 'x_sieben_members_grid');
    wp_cache_delete('x_sieben_members_grid_no_schema', 'x_sieben_members_grid');
}
add_action('save_post', 'x_sieben_members_clear_cache');
add_action('delete_post', 'x_sieben_members_clear_cache');
add_action('trashed_post', 'x_sieben_members_clear_cache');