<div class="main-content" style="margin-top: 50px;">
<?php
if ( function_exists('yoast_breadcrumb') ) {
  yoast_breadcrumb( '<p id="breadcrumbs">','</p>' );
}
?>
<?php
/**
 * Template part for displaying page content in page.php
 *
 *
 * @package sieben
 */
		the_content();
?>
</div>
