<?php get_header(); ?>

<?php
// Get current taxonomy term object
$term = get_queried_object();

$title   = '<h1>' . esc_html($term->name) . '</h1>';

// Try to get plain term description
$content = trim(strip_tags(term_description($term->term_id, $term->taxonomy)));

// Fallback to Yoast meta description if no taxonomy description
if (empty($content)) {
	$yoast_description = get_term_meta($term->term_id, '_yoast_wpseo_metadesc', true);
	$content = $yoast_description ?: '';
}

// Wrap description in <p>
$description = ! empty($content) ? '<h2>' . esc_html($content) . '</h2>' : '';

// Render top picture with title + description
render_responsive_top_picture_bg([
	'title'             => $title . $description,
	'desktop_image_url' => get_the_post_thumbnail_url(get_the_ID(), 'mobile-hero-image'),
	'mobile_image_url'  => get_the_post_thumbnail_url(get_the_ID(), 'mobile-hero-image'),
	'show_buttons' => false,
]);

the_simple_breadcrumb();
echo course_search_form_material_simple();
CourseRenderer::renderLayoutSwitcher('card');

?>
<div class="container">
	<?php get_template_part('template-parts/content-category'); ?>
</div>
<?php get_footer(); ?>