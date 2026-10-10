<?php
// Gibt auf Kursseiten die Vortragenden mit Tooltip + Excerpt für Screenreader aus
function trainer_course_single() {
    $vt_meta = get_post_meta(get_the_ID(), 'vortragende', true);

    if (!empty($vt_meta)) {
        $vt_query = new WP_Query([
            'post__in'       => $vt_meta,
            'post_type'      => 'members',
            'posts_per_page' => -1
        ]);

        if ($vt_query->have_posts()) {
            while ($vt_query->have_posts()) {
                $vt_query->the_post();

                if ($vt_query->current_post > 0) {
                    echo '<span class="member-separator">, </span>';
                }

                $title = get_the_title();

                printf(
                    '<a href="%s">%s</a>',
                    esc_url(get_permalink()),
                    esc_html($title)
                );
            }
            wp_reset_postdata();
        }
    }
}



/**
 * Gibt auf Trainerseiten alle Kurse mit Tooltip + Excerpt für Screenreader aus.
 * Nutzt das Model/Renderer-Pattern.
 */
function trainer_courses_list() {
    $trainer_id = get_the_ID(); // ID des aktuellen Trainers

    $courses_query = new WP_Query([
        'post_type'      => 'courses',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'     => 'vortragende',
                'value'   => '"' . intval($trainer_id) . '"',
                'compare' => 'LIKE'
            ]
        ]
    ]);

    if (!$courses_query->have_posts()) {
        echo '<p>Dieser Trainer gibt derzeit keine Kurse.</p>';
        return;
    }

    // Collect courses (Model Instantiation Phase)
    $courses = [];
    while ($courses_query->have_posts()) {
        $courses_query->the_post();
        // Use your existing COURSE_Registry or fallback to COURSE_Model, mirroring the example
        $course = class_exists('COURSE_Registry') ? COURSE_Registry::get(get_the_ID()) : new COURSE_Model(get_the_ID());
        $courses[] = $course;
    }
    wp_reset_postdata();
    ?>

    <div class="trainer-courses-container">

        <ul class="trainer-courses layout-list">
            <?php foreach ($courses as $course): 
                // Since this function is the view, it assumes the data-attributes 
                // and list item structure are handled either by a separate model 
                // property or by the CourseRenderer itself. 
                // For simplicity, we just wrap the CourseRenderer call in the list item.
            ?>
                
                    <?php 
                        // Assuming CourseRenderer::renderListItem is implemented 
                        // to output the course link, tooltip, and sr-only excerpt 
                        // for a single list entry.
                        // NOTE: If your existing CourseRenderer does *not* handle 
                        // the list structure, you'd have to implement it here 
                        // or in a dedicated TrainerCourseListRenderer.
                        CourseRenderer::renderListItem($course); 
                    ?>
           
            <?php endforeach; ?>
        </ul>

    </div>
<?php
}