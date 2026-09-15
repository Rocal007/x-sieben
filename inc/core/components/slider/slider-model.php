<?php
function get_slider_chunks($selected_category = '90-trend-themen-2018', $pic_amount = 3) {
    $slider_pics = new WP_Query(array(
        'post_type' => 'courses',
        'coursecategory' => $selected_category,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title'
    ));

    $slider_array = $slider_pics->posts;

    return array_chunk($slider_array, $pic_amount, true);
}

function get_reverenzen_post() {
    $args = array(
        'numberposts' => 1,
        'post_type'   => 'reverenzen'
    );
    $reverenzen = get_posts($args);
    return !empty($reverenzen) ? $reverenzen[0] : null;
}
