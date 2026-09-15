<?php
/**
 * Repository-Klasse für das Abrufen von Kursdaten (posts) und Referenzen.
 *
 * Kapselt die WP_Query-Logik und nutzt Transients zum Caching von Abfrageergebnissen.
 *
 * @package sieben
 */
class CourseRepository
{

    /**
     * Ruft eine gechunkte Liste von Posts für einen Slider ab.
     * Gibt ein Array von rohen WP_Post Objekten zurück.
     *
     * @param string $selected_category Der Slug der zu filternden Kategorie.
     * @param int $pic_amount Die Anzahl der Posts pro Chunk.
     * @return array Ein gechunktes Array von WP_Post Objekten.
     */
    public function get_slider_chunks($selected_category = '90-trend-themen-2018', $pic_amount = 3): array
    {
        $slider_pics = new WP_Query([
            'post_type' => 'courses',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'tax_query' => [
                'relation' => 'AND',
                [
                    'taxonomy' => 'coursecategory',
                    'field' => 'slug',
                    'terms' => $selected_category,
                ],
                [
                    'taxonomy' => 'coursecategory',
                    'field' => 'slug',
                    'terms' => 'trend-posts',
                ],
            ],
        ]);

        $slider_array = $slider_pics->posts;
        wp_reset_postdata();

        return array_chunk($slider_array, $pic_amount, true);
    }

    /**
     * Ruft eine gechunkte Liste von Kursmodellen für einen Slider ab.
     * Nutzt Transients, um die Datenbankabfrage selbst zu cachen.
     *
     * @param string $selected_category Der Slug der zu filternden Kategorie.
     * @param int $pic_amount Die Anzahl der Modelle pro Chunk.
     * @return array Ein gechunktes Array von COURSE_Model Objekten.
     */
    public function get_slider_chunks_as_models($selected_category = '90-trend-themen-2018', $pic_amount = 3): array
    {
        $transient_key = 'course_slider_' . sanitize_title($selected_category) . '_' . $pic_amount;
        $cached_result = get_transient($transient_key);
        
        if (false !== $cached_result) {
            return $cached_result;
        }

        $slider_pics = new WP_Query([
            'post_type' => 'courses',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'tax_query' => [
                'relation' => 'AND',
                [
                    'taxonomy' => 'coursecategory',
                    'field' => 'slug',
                    'terms' => $selected_category,
                ],
                [
                    'taxonomy' => 'coursecategory',
                    'field' => 'slug',
                    'terms' => 'trend-posts',
                ],
            ],
        ]);

        $course_models = [];
        foreach ($slider_pics->posts as $post) {
            $course_models[] = COURSE_Registry::get($post->ID);
        }

        wp_reset_postdata();

        $result = array_chunk($course_models, $pic_amount, true);

        set_transient($transient_key, $result, 3600); // Cache for 1 hour

        return $result;
    }

    /**
     * Ruft einen einzelnen Beitrag vom Typ 'reverenzen' ab.
     *
     * @return WP_Post|null Das einzelne Post-Objekt oder null, wenn nichts gefunden wurde.
     */
    public function get_reverenzen_post()
    {
        $args = [
            'numberposts' => 1,
            'post_type' => 'reverenzen'
        ];
        $reverenzen = get_posts($args);
        return !empty($reverenzen) ? $reverenzen[0] : null;
    }

    /**
     * Ruft ein Array von verwandten Kursmodellen für einen bestimmten Post ab.
     *
     * @param int $post_id Die ID des Posts, für den verwandte Kurse gefunden werden sollen.
     * @return array Ein Array von COURSE_Model Objekten.
     */
    public function get_related_courses(int $post_id): array
    {
        $related_ids = [];
        $i = 1;
        while ($rel_id = (int) trim(get_post_meta($post_id, "related{$i}", true))) {
            if ($rel_id > 0) {
                $related_ids[] = $rel_id;
            }
            $i++;
        }
        
        if (empty($related_ids)) {
            return [];
        }
        
        $related_models = [];
        foreach ($related_ids as $id) {
            $related_models[] = COURSE_Registry::get($id);
        }
        
        return $related_models;
    }

    /**
     * Ruft ein Array von Trainerdaten für einen bestimmten Kurs-Post ab.
     *
     * @param int $post_id Die ID des Kurses.
     * @return array Ein Array von Trainerdaten (als assoziative Arrays).
     */
    public function get_course_trainers(int $post_id): array
    {
        $ids = get_post_meta($post_id, 'vortragende', true);
        if (empty($ids) || !is_array($ids)) {
            return [];
        }

        $query = new WP_Query([
            'post__in' => $ids,
            'post_type' => 'members',
            'posts_per_page' => -1,
            'orderby' => 'post__in',
        ]);
        
        $trainers = [];
        while ($query->have_posts()) {
            $query->the_post();
            $trainers[] = [
                'id' => get_the_ID(),
                'name' => get_the_title(),
                'permalink' => get_permalink(),
            ];
        }
        wp_reset_postdata();
        return $trainers;
    }

    /**
     * Ruft ein Array von Bild-URLs für die Zertifikate eines Kurses ab.
     *
     * @param int $post_id Die ID des Kurses.
     * @return array Ein Array von Bild-URLs.
     */
    public function get_course_certifications_images(int $post_id): array
    {
        $images = [];
        $ca_meta = get_post_meta($post_id, "zertifikate", true);
        
        if (empty($ca_meta) || !is_array($ca_meta)) {
            return [];
        }

        $query = new WP_Query([
            "post__in" => $ca_meta,
            "post_type" => 'ca',
            "posts_per_page" => -1
        ]);
        
        while ($query->have_posts()) {
            $query->the_post();
            
            if (get_the_ID() != 5793) {
                $thumb = get_the_post_thumbnail_url(null, 'thumbnail');
                if ($thumb) {
                    $images[] = $thumb;
                }
            }
        }
        wp_reset_postdata();

        return $images;
    }
}