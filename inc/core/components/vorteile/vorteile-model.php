<?php
// The Query
$vorteile_query = new WP_Query(array(
    'post_type' => 'post',
    'category_name' => 'vorteile', // Category slug
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'title'
));

// The Loop
if ($vorteile_query->have_posts()) {
    while ($vorteile_query->have_posts()) {
        $vorteile_query->the_post();
        // Display the post content or other information
        ?>
        <h2><?php the_title(); ?></h2>
        <div class="post-content">
            <?php the_content(); ?>
        </div>
        <?php
    }
    // Restore original Post Data
    wp_reset_postdata();
} else {
    // no posts found
    echo 'No posts found';
}
?>