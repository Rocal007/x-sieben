<?php

/**
 * Template Name: unternehmen
 *
 */
get_header();
render_responsive_top_picture([
	'title' => '<h1>' . get_the_title() . '</h1>' . '<h2 class="teaser">' . esc_html(get_post_meta( get_the_ID(), '_yoast_wpseo_metadesc', true )) . '</h2>',
	'desktop_image_url' => get_the_post_thumbnail_url(get_the_ID(), 'sieben-courses-top'),
	'mobile_image_url' => get_the_post_thumbnail_url(get_the_ID(), 'sieben-single-thumbnail'),
]);
the_simple_breadcrumb();
?>

<div class="container pdt30">
	<?php the_content(); ?>
</div>
<?php interest() ?>
<div class="container">
	<?php echo do_shortcode('[smartslider3 slider="25"]'); ?>
</div>
<?php call_back() ?>
<?php get_footer();
