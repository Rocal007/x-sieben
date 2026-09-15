<?php
/**
 * The template for displaying courses (Refactored)
 *
 * @package sieben
 */
get_header();

// Standard WordPress check to ensure content exists for the current page
if ( have_posts() ) :
    while ( have_posts() ) : the_post();
?>

<div class="container pdtb-default">
    <div id="ca-content-wrap" class="row">
        <div id="ca-content" class="col-md-12">
            <h1><?php the_title(); ?></h1>
            <?php the_content(); ?>
        </div>
    </div>
</div>

<?php
    endwhile; // End of the WordPress Loop
endif;

// Section for displaying the actual course list
$ca_id = get_the_ID();
?>
<div class="container pdb50">
    <?php
    if ( function_exists( 'display_courses_for_ca' ) ) {
        // Assume display_courses_for_ca handles its own output buffering/escaping
        //display_courses_for_ca( $ca_id );
    } else {
        // Fallback message if the required custom function is missing
        echo '<p class="alert alert-warning">Course display functionality is missing.</p>';
    }
    ?>
</div>

<?php
get_footer();