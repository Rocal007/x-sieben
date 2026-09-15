<?php
/**
 * The template for displaying archive pages
 *
 *
 * @package sieben
 */
get_header(); ?>   
<div class="container">
<?php //the_title('<h1>', '</h1>');?>
<?php get_template_part( 'template-parts/content-category' );?>	
</div>
<?php get_footer();?>