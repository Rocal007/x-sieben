<?php

/**
 * Template Name: Courses-Material
 *
 */
get_header();
$args = array(
    'post_type' => 'kurs_materialen',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'title',
    'order' => 'ASC',
    'cat' => 'wordpress',

);

$loop = new WP_Query($args);
echo '<div class="container" style="margin-top: 250px;"> 
        <div class="col-md-8">';
while ($loop->have_posts()) : $loop->the_post();
    $featured_img = wp_get_attachment_image_src($post->ID);
    print the_title('<h1>', '</h1>');
    if ($feature_img) {
    }
    the_content();
    echo '<hr>';
endwhile;
echo '</div></div>';
wp_reset_postdata();


get_sidebar();
get_footer();
