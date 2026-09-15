<?php
/**
 * The template for displaying all single posts
 *
 * @package sieben
 */
get_header(); 
// render_responsive_top_picture([
//     'desktop_image_url' => get_the_post_thumbnail_url(get_the_ID(), 'sieben-courses-top'),
//     'mobile_image_url' => get_the_post_thumbnail_url(get_the_ID(), 'sieben-single-thumbnail'),
// ]);     
the_simple_breadcrumb();
?>
    <div class="container">
        <div class="row">
            <div class="col-sm-12">
                <div class="members" id="single-member">
                    <?php while ( have_posts() ) : the_post();
                        get_template_part( 'template-parts/content','members' );
                    endwhile; ?>
                </div>
            </div>
        </div>
    </div>
<?php //all_trainers();?>
<?php get_footer();