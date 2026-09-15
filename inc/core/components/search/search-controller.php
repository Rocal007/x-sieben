<?php 
/**
 * Modifies the main WordPress query for search results.
 *
 * This function ensures that search results are limited to the 'courses' CPT
 * and can be filtered by the 'coursecategory' custom taxonomy.
 *
 * @param object $query The WP_Query object.
 */
// function my_custom_search_filter( $query ) {
//     // Only proceed on the frontend main search query
//     if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {

//         // Set the post type to 'courses' to ensure the search is confined
//         $query->set( 'post_type', 'courses' );

//         // If a custom taxonomy term is selected, add it to the query
//         if ( isset( $_GET['coursecategory'] ) && ! empty( $_GET['coursecategory'] ) ) {
//             $tax_query = array(
//                 array(
//                     'taxonomy' => 'coursecategory', // Correct taxonomy slug
//                     'field'    => 'slug',
//                     'terms'    => sanitize_text_field( $_GET['coursecategory'] ),
//                 )
//             );
//             $query->set( 'tax_query', $tax_query );
//         }
//     }
// }
// add_action( 'pre_get_posts', 'my_custom_search_filter' );


function custom_search_filter($query) {
    if ($query->is_main_query() && !is_admin() && $query->is_search()) {

        $tax_query = array('relation' => 'AND');

        // Handle selected checkboxes
        if (isset($_GET['coursecategory']) && is_array($_GET['coursecategory']) && !empty($_GET['coursecategory'])) {
            $tax_query[] = array(
                'taxonomy' => 'coursecategory',
                'field'    => 'slug',
                'terms'    => $_GET['coursecategory'],
                'operator' => 'IN',
            );
        }

        // Handle selected dropdown
        if (isset($_GET['category_dropdown']) && !empty($_GET['category_dropdown'])) {
             $tax_query[] = array(
                'taxonomy' => 'coursecategory',
                'field'    => 'slug',
                'terms'    => array($_GET['category_dropdown']),
                'operator' => 'IN',
            );
        }

        // Set the tax_query if any filters are active
        if (count($tax_query) > 1) {
            $query->set('tax_query', $tax_query);
        }
    }
}
add_action('pre_get_posts', 'custom_search_filter');

function filter_search_for_courses($query) {
    if ($query->is_main_query() && $query->is_search()) {
        // Only modify search queries if post_type is set to 'courses'
       
            $query->set('post_type', 'courses');
       
    }
}
add_action('pre_get_posts', 'filter_search_for_courses');
