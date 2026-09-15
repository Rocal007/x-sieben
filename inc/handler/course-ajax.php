<?php
function sieben_load_courses() {
    $layout = isset($_GET['layout']) ? sanitize_text_field($_GET['layout']) : 'x-sieben-card-rich';
    $cat_id = isset($_GET['cat_id']) ? intval($_GET['cat_id']) : 0;

    ob_start();

    $args = [
        'post_type' => 'course', // or your custom post type
        'posts_per_page' => -1,
    ];

    if ($cat_id) {
        $args['cat'] = $cat_id; // filter by category
    }

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $course = COURSE_Registry::get(get_the_ID());
            CourseRenderer::render($course, $layout);
        }
        wp_reset_postdata();
    } else {
        echo '<p>No courses found.</p>';
    }

    echo ob_get_clean();
    wp_die();
}
add_action('wp_ajax_load_courses', 'sieben_load_courses');
add_action('wp_ajax_nopriv_load_courses', 'sieben_load_courses');
