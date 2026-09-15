<?php
/**
 * The template for displaying 404 pages (not found)
 *
 *
 * @package sieben
 */
get_header(); ?>
<div class="container">
    <div class="row main-row">
		<div class="col-sm-9">	
			<div class="news-posts">
				<div class="sideArea">
                    <p><?php esc_html_e( 'It looks like nothing was found at this location. Maybe try onece again with a search?', 'sieben' ); ?></p>	
    				<?php get_search_form(); ?>
			    </div>    
            </div>
		</div>
        <?php get_sidebar(); ?>
	</div>
</div>
<?php get_footer();