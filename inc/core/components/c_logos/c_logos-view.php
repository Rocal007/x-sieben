<?php
function logos_line()
{
    $args = array(
        'post_type' => 'your_custom_post_type', // Replace 'your_custom_post_type' with your actual custom post type slug
        'posts_per_page' => -1 // Retrieve all posts of the custom post type
    );
    $logos = get_posts($args);

    if ($logos): ?>
        <div class="custom-post-type-wrapper">
            <div class="row">
                <?php foreach ($logos as $logo):
                    setup_postdata($logo); ?>
                    <div class="col-md-2">
                        <?php if (has_post_thumbnail()): ?>
                            <a href="<?php the_permalink(); ?>">
                                <?php the_post_thumbnail('thumbnail'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php wp_reset_postdata(); ?>
    <?php endif; ?>

<?php }