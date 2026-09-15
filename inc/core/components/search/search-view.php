<?php
function search_element()
{ ?>
    <div id="search">
        <form role="search" method="get" id="searchform" class="searchform" action="<?php echo esc_url(home_url('/')); ?>">
            <div class="input-group search-box-input">
                <input type="text" value="" name="s" id="s" class="form-control search-box-form" placeholder="Kurse finden (z.B. Agile Coach, ...)">
                <input type="hidden" name="post_type" value="courses">
                <span class="input-group-btn search-button-span">
                    <button class="btn btn-info search-button" type="submit"><i class="fa fa-search"></i></button>
                </span>
            </div>
        </form>
        <div class="clear"> </div>
    </div>
<?php }

/**
 * Custom search form for the 'courses' CPT.
 *
 * This function generates and returns the HTML for a search form
 * that includes a dropdown for a custom taxonomy, and a hidden input
 * to filter the search results by the 'courses' custom post type.
 */
function course_search_form()
{
    ob_start(); // Start output buffering to capture the HTML

    // Assuming your custom taxonomy slug is 'course_category'
    $taxonomy = 'course_category';

    $terms = get_terms(array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC'
    ));
?>

    <form role="search" method="get" class="search-form" action="<?php echo esc_url(home_url('/')); ?>">
        <label>
            <span class="screen-reader-text">Search for:</span>
            <input type="search" class="search-field" placeholder="Search courses..." value="<?php echo get_search_query(); ?>" name="s" />

        </label>

        <?php
        if (! empty($terms) && ! is_wp_error($terms)) {
        ?>
            <select name="<?php echo esc_attr($taxonomy); ?>">
                <option value="">All Categories</option>
                <?php
                foreach ($terms as $term) {
                    $selected = (isset($_GET[$taxonomy]) && $_GET[$taxonomy] == $term->slug) ? 'selected' : '';
                    echo '<option value="' . esc_attr($term->slug) . '" ' . $selected . '>' . esc_html($term->name) . '</option>';
                }
                ?>
            </select>
        <?php
        }
        ?>

        <input type="hidden" name="post_type" value="courses" />
        <input type="submit" class="search-submit" value="Search" />
    </form>

<?php
    $form = ob_get_clean(); // Get the buffered content and clean the buffer
    return $form;
}

function get_course_search_form()
{
    ob_start(); // Start output buffering to capture the HTML

    // The correct custom taxonomy slug is 'coursecategory'
    $taxonomy = 'coursecategory';

    $terms = get_terms(array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC'
    ));
?>
    <div class="container-fluid">
        <div class="container">
            <form role="search" method="get" class="search-form form-inline" action="<?php echo esc_url(home_url('/')); ?>">
                <div class="form-group">
                    <label class="sr-only" for="search-field">Search for:</label>
                    <input type="search" id="search-field" class="search-field form-control" placeholder="Kurs suchen..." value="<?php echo get_search_query(); ?>" name="s" />
                </div>

                <div class="form-group">
                    <label class="sr-only">Kurstypen:</label>
                    <?php
                    $coursecategories_checkboxes = array(
                        'Lehrgang' => 'lehrgang',
                        'Seminar'  => 'seminar',
                        // 'Crashkurs' => 'crashkurs'
                    );
                    // $selected_coursecategories = isset($_GET['coursecategory']) ? (array)$_GET['coursecategory'] : array();

                    // foreach ($coursecategories_checkboxes as $name => $slug) {
                    //     $checked = in_array($slug, $selected_coursecategories) ? 'checked' : '';
                    //     echo '<label class="checkbox-inline">';
                    //     echo '<input type="checkbox" name="coursecategory[]" value="' . esc_attr($slug) . '" ' . $checked . '> ' . esc_html($name);
                    //     echo '</label>';
                    // }
                    ?>
                </div>

                <?php
                // Assuming $terms is an array of all terms from the `coursecategory` taxonomy
                if (! empty($terms) && ! is_wp_error($terms)) {
                    $filtered_terms = array_filter($terms, function ($term) {
                        // Filter out the specific slugs used for the checkboxes
                        return ! in_array($term->slug, array('lehrgang', 'seminar'));
                    });

                    if (! empty($filtered_terms)) {
                ?>
                        <div class="form-group">
                            <label class="sr-only" for="category-select">Select a category</label>
                            <select id="category-select" name="category_dropdown" class="form-control">
                                <option value="">Alle Kategorien</option>
                                <?php
                                $selected_dropdown_term = isset($_GET['category_dropdown']) ? sanitize_text_field($_GET['category_dropdown']) : '';
                                foreach ($filtered_terms as $term) {
                                    $selected = ($selected_dropdown_term == $term->slug) ? 'selected' : '';
                                    echo '<option value="' . esc_attr($term->slug) . '" ' . $selected . '>' . esc_html($term->name) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                <?php
                    }
                }
                ?>

                <input type="hidden" name="post_type" value="courses" />
                <button type="submit" class="search-submit btn btn-primary">
                    <i class="fa fa-search"></i>
                </button>
            </form>
        </div>
    </div>
<?php
    $form = ob_get_clean(); // Get the buffered content and clean the buffer
    return $form;
}



/**
 * Generates a compact, Material Design-styled course search form.
 *
 * @return string The minified HTML for the search form.
 */
function get_course_search_form_material()
{
    // --- Data Fetching ---
    $taxonomy = 'coursecategory';
    $all_terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
    $selected_cats = isset($_GET['coursecategory']) ? (array)$_GET['coursecategory'] : [];
    $selected_dropdown = isset($_GET['category_dropdown']) ? sanitize_text_field($_GET['category_dropdown']) : '';
    $search_query = get_search_query();

    // --- HTML String Builder ---
    $html = '<div class="container-fluid search-form-wrapper"><form role="search" method="get" class="material-search-form container #F3F2EE" action="' . esc_url(home_url('/')) . '">';

    // 1. Search Input Field
    $html .= '<div class="material-form-field"><input type="search" id="search-field" name="s" value="' . esc_attr($search_query) . '" placeholder=" " /><label for="search-field">Kurs suchen...</label></div>';

    // 2. Checkboxes
    $checkbox_types = ['Lehrgang' => 'lehrgang', 'Seminar' => 'seminar'];
    $checkbox_html = '<div class="material-checkbox-group">';
    foreach ($checkbox_types as $name => $slug) {
        $checked = in_array($slug, $selected_cats) ? ' checked' : '';
        $checkbox_html .= '<label class="material-checkbox"><input type="checkbox" name="coursecategory[]" value="' . esc_attr($slug) . '"' . $checked . '><span>' . esc_html($name) . '</span></label>';
    }
    $checkbox_html .= '</div>';
    $html .= $checkbox_html;

    // 3. Categories Dropdown
    // if (!empty($all_terms) && !is_wp_error($all_terms)) {
    //     $dropdown_terms = array_filter($all_terms, fn($term) => !in_array($term->slug, ['lehrgang', 'seminar', 'crashkurs']));
    //     if (!empty($dropdown_terms)) {
    //         $dropdown_html = '<div class="material-form-field"><select id="category-select" name="category_dropdown"><option value="">Alle Kategorien</option>';
    //         foreach ($dropdown_terms as $term) {
    //             $selected_attr = selected($selected_dropdown, $term->slug, false);
    //             $dropdown_html .= '<option value="' . esc_attr($term->slug) . '"' . $selected_attr . '>' . esc_html($term->name) . '</option>';
    //         }
    //         $dropdown_html .= '</select><label for="category-select">Kategorie wählen</label></div>';
    //         $html .= $dropdown_html;
    //     }
    // }

    // 4. Hidden Fields & Submit Button
    $html .= '<input type="hidden" name="post_type" value="courses" /><button type="submit" class="material-submit-button" aria-label="Suchen"><i class="fa fa-search"></i></button>';
    $html .= '</form></div>';

    // Minify HTML to a single line before returning
    return preg_replace('/\s+/', ' ', str_replace(array("\r", "\n", "\t"), '', $html));
}


function course_search_form_material_simple()
{
    // --- Data Fetching ---
    $search_query = get_search_query();

    // --- HTML String Builder ---
    $html = '<div class="container-fluid search-form-wrapper"><form role="search" method="get" class="material-search-form container #F3F2EE" action="' . esc_url(home_url('/')) . '">';

    // 1. Search Input Field
    $html .= '<div class="material-form-field"><input type="search" id="search-field" name="s" value="' . esc_attr($search_query) . '" placeholder="" /><label for="search-field">Kurse finden ... (z.B. Agile Coach)</label></div>';

    // 4. Hidden Fields & Submit Button
    $html .= '<input type="hidden" name="post_type" value="courses" /><button type="submit" class="material-submit-button" aria-label="Suchen"><i class="fa fa-search"></i></button>';
    $html .= '</form></div>';

    // Minify HTML to a single line before returning
    return preg_replace('/\s+/', ' ', str_replace(array("\r", "\n", "\t"), '', $html));
}