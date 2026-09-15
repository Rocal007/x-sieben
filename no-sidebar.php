<?php
/**
 * Template Name: full width
 *
 */
get_header();
?>
    <div class="container main-content">
        <div class="row">
            <div class="col-sm-12 no-padding">
                <div class="news-posts">
                    <?php
                    if (have_posts()):
                        while (have_posts()) : the_post();
                            get_template_part('template-parts/content', 'page');
                            if (comments_open() || get_comments_number()) :
                                comments_template();
                            endif;
                        endwhile;
                    endif;
                    ?>
                </div>
            </div>
        </div>
    </div>
<?php get_footer();