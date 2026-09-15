<?php

/**
 * Template Name: hero no sidebar 
 *
 */
get_header();
?>

<?php
render_responsive_top_picture_bg([
	'title' => '<h1>' . get_the_title() . '</h1>' . '<h2 class="teaser">' . esc_html(get_post_meta( get_the_ID(), '_yoast_wpseo_metadesc', true )) . '</h2>',
]);
the_simple_breadcrumb();
?>


<div class="container pdtb-default">
	<div id="main-content" class="col-md-12">

		<?php the_content(); ?>

	</div>



</div>

<?php call_back() ?>
<?php get_footer();
