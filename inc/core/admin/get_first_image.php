<?php
// Include WordPress core files
require_once( trailingslashit( ABSPATH ) .'wp-load.php' );

// Get all articles (posts)
$args = array(
    'post_type' => 'post', // Change post type if needed
    'posts_per_page' => -1, // Retrieve all posts
);
$articles_query = new WP_Query($args);

// Loop through each article
if ($articles_query->have_posts()) {
    while ($articles_query->have_posts()) {
        $articles_query->the_post();
        
        // Check if the article has a featured image set
        if (!has_post_thumbnail()) {
            // Get the content of the article
            $content = get_the_content();

            // Extract the first image from the content
            preg_match('/<img.+?src="(.+?)"/', $content, $matches);

            // If an image is found, set it as the featured image
            if (!empty($matches[1])) {
                $image_url = $matches[1];
                $image_id = attachment_url_to_postid($image_url);
                if ($image_id) {
                    // Set the image as the featured image
                    set_post_thumbnail(get_the_ID(), $image_id);
                }
            }
        }
    }
    wp_reset_postdata(); // Restore global post data
}