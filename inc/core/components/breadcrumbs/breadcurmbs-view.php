<?php
function the_simple_breadcrumb()
{

    // Start the breadcrumb navigation with a semantic <nav> tag.
    echo '<nav aria-label="Breadcrumb" class="simple-breadcrumb hidden-xs">';

    // The home link is the first item.
    echo '<a href="' . esc_url(home_url('/')) . '">Home</a>';

    // The separator between breadcrumb items.
    $separator = '<span class="separator"> &gt; </span>';

    // Check if we are on a single post, a page, or a custom post type archive.
    if (is_single()) {
        echo $separator;

        // Get the post's ID and custom taxonomy 'coursecategory' terms.
        $post_id = get_the_ID();
        $categories = get_the_terms($post_id, 'coursecategory');

        // Handle categories if they exist.
        if (! empty($categories) && ! is_wp_error($categories)) {
            $primary_category = null;

            // Check if Yoast's primary term feature is available.
            if (class_exists('WPSEO_Primary_Term')) {
                $wpseo_primary_term = new WPSEO_Primary_Term('coursecategory', $post_id);
                $primary_term_id = $wpseo_primary_term->get_primary_term();
                $term = get_term($primary_term_id, 'coursecategory');

                // If a primary term is found and is not an error, use it.
                if (! is_wp_error($term) && $term) {
                    $primary_category = $term;
                }
            }

            // If no Yoast primary term was found, use the first category in the list.
            if (! $primary_category) {
                $primary_category = $categories[0];
            }

            // Display the category link.
            // Get the ACF field 'title_short' for the category
            $category_title = get_field('title_short', 'coursecategory_' . $primary_category->term_id);
            // var_dump(value: get_field('title_short', 'coursecategory_' . $primary_category->term_id));
            // Use the ACF field if it exists, otherwise fall back to the category name
            $display_title = !empty($category_title) ? $category_title : $primary_category->name;

            // Display the category link
            echo '<a href="' . esc_url(get_term_link($primary_category->term_id, 'coursecategory')) . '">';
            echo esc_html($display_title);
            echo '</a>';
            echo $separator;
        }

        // Get the short title if it exists, otherwise use the regular title.
        $short_title = get_field('title_im_slider', $post_id);
        $title_to_use = !empty($short_title) ? $short_title : get_the_title($post_id);

        // Display the current post's title (the last item in the trail).
        echo '<span class="current">' . esc_html($title_to_use) . '</span>';
    } elseif (is_page()) {
        echo $separator;
        // For pages, just show the page title.
        echo '<span class="current">' . get_the_title() . '</span>';
    } elseif (is_tax('coursecategory')) {
        echo $separator;

        // Get the current term
        $term = get_queried_object();

        // Get the ACF 'title_short' field for this term
        $category_title = get_field('title_short', 'coursecategory_' . $term->term_id);

        // Use the ACF field if it exists, otherwise fallback to the term name
        $display_title = !empty($category_title) ? $category_title : $term->name;

        // Display the term title
        echo '<span class="current">' . esc_html($display_title) . '</span>';
    }

    // Close the navigation tag.
    echo '</nav>';
}
