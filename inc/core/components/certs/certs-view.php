<?php

/**
 * Zertifizierungen Funktionen und View
 *
 * @package sieben
 */

// Zeigt alle Zertifizierungen in Grid, Horizontal oder Vertikal mit Link, Titel und ARIA
function certs_line($layout = 'grid')
{
    $args = array(
        'post_type'      => array('ca'),
        'posts_per_page' => -1,
        'post__not_in'   => array(5793)
    );

    $loop = new WP_Query($args);

    if ($loop->have_posts()) : ?>
        <div class="container-fluid zerts-container">
			
			<div style="
    text-align:center;
    font-size:1.15rem;
    font-weight:700;
    letter-spacing:0.08em;
    color:#4b5563;
    margin:28px 0 24px 0;
">
    Anerkannt von
</div>
			
            <div class="container zerts-wrapper zert-images-<?php echo esc_attr($layout); ?>">
                <?php
                while ($loop->have_posts()) : $loop->the_post();
                    $post_title = get_the_title();
                    $thumb_url  = get_the_post_thumbnail_url(get_the_ID(), 'sieben-certs-mini');
                    $permalink  = get_permalink();

                    if ($thumb_url) {
                        echo '<div class="zert-image-item">';
                        echo '<a href="' . esc_url($permalink) . '" title="' . esc_attr($post_title) . '">';
                        echo '<img 
                                src="' . esc_url($thumb_url) . '" 
                                alt="' . esc_attr($post_title) . '" 
                                title="' . esc_attr($post_title) . '" 
                                role="img" 
                                aria-label="' . esc_attr($post_title) . '" 
                                class="certs-image-mini">';
                        echo '</a>';
                        echo '</div>';
                    }
                endwhile;
                ?>
            </div>
        </div>
    <?php endif;

    wp_reset_postdata();
}


// Zeigt Zertifizierungen für einen Kurs (Meta "zertifikate") mit Link, Title, und ARIA
function certs_courses($post_id, $layout = 'vertical', $image_class = "zert-image-item")
{
    $cert_images_url = [];
    $ca_meta = get_post_meta($post_id, "zertifikate", true);

    if (!empty($ca_meta) && is_array($ca_meta)) {
        $query = new WP_Query([
            "post__in"       => $ca_meta,
            "post_type"      => 'ca',
            "posts_per_page" => -1
        ]);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                if (get_the_ID() != 5793) {
                    // Choose image size based on layout
                    $image_size = $layout === 'horizontal' ? 'sieben-certs-horizontal' : 'sieben-certs-mini';
                    $image_url  = get_the_post_thumbnail_url(get_the_ID(), $image_size);

                    $post_title = get_the_title();
                    $permalink  = get_permalink();

                    if ($image_url) {
                        $cert_images_url[] = [
                            'url'   => $image_url,
                            'title' => $post_title,
                            'link'  => $permalink
                        ];
                    }
                }
            }
            wp_reset_postdata();
        }
    }

    if (empty($cert_images_url)) {
        return '';
    }

    $layout_class = $layout === 'horizontal' ? 'zert-images-horizontal' : 'zert-images-vertical';

    $cert_images = '<div class="' . esc_attr($layout_class) . '">';
    foreach ($cert_images_url as $cert) {
        $cert_images .= '
            <div class="' . $image_class . '">
                <a href="' . esc_url($cert['link']) . '" title="' . esc_attr($cert['title']) . '">
                    <img 
                        src="' . esc_url($cert['url']) . '" 
                        alt="' . esc_attr($cert['title']) . '" 
                        title="' . esc_attr($cert['title']) . '" 
                        role="img" 
                        aria-label="' . esc_attr($cert['title']) . '"
                        class="certs-image-mini">
                </a>
            </div>';
    }
    $cert_images .= '</div>';

    return $cert_images;
}



/**
 * Zeigt alle Kurse an, die mit einer Zertifizierung (CPT 'ca') verknüpft sind.
 * Nutzt CourseRenderer und rendert alle Layoutvarianten (für JS-Layout-Switcher).
 * Nur Sortierung nach Preis in der Listenansicht.
 *
 * @param int $ca_id Die ID der Zertifizierung
 */
function display_courses_for_ca($ca_id)
{
    if (!$ca_id) return;

    $courses_query = new WP_Query([
        'post_type' => 'courses',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'zertifikate',
                'value' => '"' . intval($ca_id) . '"',
                'compare' => 'LIKE',
            ]
        ]
    ]);

    if (!$courses_query->have_posts()) {
        echo '<p>Keine Kurse für diese Zertifizierung gefunden.</p>';
        return;
    }

    else {
        echo '<div class="container"><h2 class="pdb20">Kurse mit '. get_the_title() .' Zertifizierung</h2></div>';
    }



    // Collect courses
    $courses = [];
    while ($courses_query->have_posts()) {
        $courses_query->the_post();
        $course = class_exists('COURSE_Registry') ? COURSE_Registry::get(get_the_ID()) : new COURSE_Model(get_the_ID());
        $courses[] = $course;
    }
    wp_reset_postdata();
    ?>

    <div id="courses-container" class="">

        <!-- List Layout -->
        <div class="layout-list">
            <div class="row list-header" role="row">
                <div class="col-md-4">Kurs</div>
                <div class="col-md-3">
                    <button class="sort-btn" data-sort="date" data-order="asc">Datum <i class="fa fa-sort"></i></button>
                </div>
                <div class="col-md-1">
                    <button class="sort-btn" data-sort="units" data-order="asc">LE <i class="fa fa-sort"></i></button>
                </div>
                <div class="col-md-2">
                    <button class="sort-btn" data-sort="price" data-order="asc">Preis <i class="fa fa-sort"></i></button>
                </div>
                <div class="col-md-2">Aktionen</div>
            </div>

            <?php foreach ($courses as $course):
                $numeric_price = floatval(str_replace(',', '.', str_replace('.', '', $course->preis_netto)));
                $start_timestamp = !empty($course->start_datum) ? strtotime($course->start_datum) : 0;
                $units = intval($course->anzahl_le);
            ?>
                <article class="list-item"
                    id="post-<?= esc_attr($course->post_id); ?>"
                    data-price="<?= $numeric_price; ?>"
                    data-date="<?= $start_timestamp; ?>"
                    data-units="<?= $units; ?>">
                    <?php CourseRenderer::renderListItem($course); ?>
                </article>
            <?php endforeach; ?>
        </div>

    </div>
<?php
}
