<?php

/**
 * Template Name: startseite
 *
 */
get_header();
render_responsive_top_picture_bg([
	'show_buttons' => true,
	'show_logos'   => false,
]);


//search_element();
//echo get_course_search_form(); 
//echo get_course_search_form_material();
//echo course_search_form_material_simple();
?>
<div class="hidden-xs"><?php certs_line(); ?></div>
<div class="container">
	<div id="startseite-content" class="col-md-12">
	<?php // echo kunden_logos(); ?>	
	<?php the_content(); ?>
	</div>
</div>
<?php //slider(); ?>
<?php // faq_view(); ?>
<?php call_back() ?>
<?php //proven_expert() 
?>
<?php //vorteile();
?>
<?php //echo do_shortcode('[wbcr_snippet id="34799"]'); 
?>
<?php get_footer();
