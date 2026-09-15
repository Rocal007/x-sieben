<?php

/** * The template for displaying search results pages * * * @package sieben */
get_header();
echo course_search_form_material_simple();
CourseRenderer::renderLayoutSwitcher('card');
?>
<div class="container">
<?php if (have_posts()) {
    get_template_part('template-parts/content', 'search');
} else {
    get_template_part('template-parts/content', 'none');
}
?> 
</div>
<?php
get_footer();
