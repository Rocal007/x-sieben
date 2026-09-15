<?php
function vorteile_view($anzeige_typ = 'liste')
{
    $args = array(
        'post_type'      => 'post',
        'category_name'  => 'vorteile',
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
        'posts_per_page' => 3,
    );

    $vorteile_query = new WP_Query($args);

    if ($vorteile_query->have_posts()) {

        if ($anzeige_typ === 'tooltip') {

            echo '<div class="vorteile-tooltips-wrapper hidden-xs">';

            while ($vorteile_query->have_posts()) {
                $vorteile_query->the_post();

                $post_id = get_the_ID();
                $title   = get_the_title();
                $excerpt = get_the_excerpt();
                // Voller Inhalt — sichere, erlaubte HTML-Tags zulassen
                $content = apply_filters('the_content', get_the_content());
                $content = wp_kses_post($content); // erlaubt nur sichere HTML-Tags
?>
                <span class="vorteile-single tooltip-wrapper">
                    <button type="button"
                            class="tooltip-trigger"
                            aria-describedby="vorteil-tooltip-<?php echo esc_attr($post_id); ?>">
                        <span class="check-icon">✔</span>
                        <strong><?php echo esc_html($title); ?></strong>
                    </button>

                    <div id="vorteil-tooltip-<?php echo esc_attr($post_id); ?>"
                         class="tooltip-content"
                         role="tooltip">
                        <?php echo $content; // bereits gesäubert mit wp_kses_post ?>
                    </div>

                    <br>
                    <span class="vorteile-excerpt"><?php echo esc_html($excerpt); ?></span>
                </span>
<?php
            } // endwhile

            echo '</div>'; // wrapper

        } else {
            // Standard-Liste
            echo '<div id="vorteile" class="vorteile-titles hidden-xs">';
            while ($vorteile_query->have_posts()) {
                $vorteile_query->the_post();
?>
                <span class="vorteile-single">
                    <span class="check-icon">✔</span>
                    <b><?php echo get_the_title(); ?></b><br>
                    <span class="vorteile-excerpt"><?php echo get_the_excerpt(); ?></span>
                </span>
<?php
            }
            echo '</div>';
        }

        wp_reset_postdata();
    } else {
        echo '<p>Es wurden keine Vorteile gefunden.</p>';
    }
}
