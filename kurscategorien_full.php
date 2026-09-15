<?php

/**
 * Template Name: kurscategorien-full
 *
 */

get_header(); 

the_simple_breadcrumb();
?>
<div class="pdtb-default">
<?php
the_content();
x_sieben_render_course_categories_overview();
?>
</div>
<?php
get_footer();