<?php
function display_related_posts($related_posts_query) {
    if ($related_posts_query->have_posts()) :
?>
        <h2>Related Posts:</h2>
        <ul>
            <?php while ($related_posts_query->have_posts()) : $related_posts_query->the_post(); ?>
                <li>
                    <a href="<?php the_permalink(); ?>">
                        <?php if (has_post_thumbnail()) : ?>
                            <?php the_post_thumbnail('thumbnail'); ?>
                        <?php endif; ?>
                        <?php the_title(); ?>
                    </a>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php
        wp_reset_postdata();
    else :
        echo '<p>No related posts found.</p>';
    endif;
}

// Query all pages with a specific template
$args = array(
    'post_type' => 'page',
    'meta_key' => '_wp_page_template',
    'meta_value' => 'your-template-name.php', // Replace 'your-template-name.php' with the filename of your template
    'posts_per_page' => -1, // Set to -1 to retrieve all pages
);
$pages_query = new WP_Query($args);

// Check if there are any pages with the specified template
if ($pages_query->have_posts()) :
    while ($pages_query->have_posts()) : $pages_query->the_post();
        $related_category = get_field('cat_selected'); // Replace 'your_acf_field_name' with the name of your ACF field

        if ($related_category) :
            $related_posts_args = array(
                'post_type' => 'post',
                'posts_per_page' => -1,
                'category_name' => $related_category, // Use category slug here
            );
            $related_posts_query = new WP_Query($related_posts_args);
            display_related_posts($related_posts_query);
        else :
            echo '<p>No related category selected for ' . get_the_title() . '.</p>';
        endif;
    endwhile;
    wp_reset_postdata();
else :
    echo 'No pages found with the specified template.';
endif;
