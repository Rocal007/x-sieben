<?php

/**
 * The template for displaying all single posts
 *
 * @package sieben
 */
get_header();
?>
<div id="single-post">

    <?php the_simple_breadcrumb(); ?>

    <div class="container">
        <div class="col-md-8">


            <?php while (have_posts()):
                the_title('<h1 class="entry-title pdtb-default">', '</h1>');
                the_post();
                get_template_part('template-parts/content', 'single');

            endwhile; ?>


        </div>
        <div class="col-md-4">
            <?php get_sidebar('blog'); ?>
        </div>
    </div>

</div>
</div>

<?php call_back() ?>
<?php slider(3) ?>
<?php // interest() 
?>