<?php

/**
 * Template for displaying courses safely (Bootstrap 3)
 *
 * @package sieben
 */

$post_id = get_the_ID();
$course = COURSE_Registry::get($post_id); // ✅ Load via registry
$repository = new CourseRepository();
$related_courses = $repository->get_related_courses(get_the_ID());

get_header();

// Safely get title and teaser
$subtitle = get_post_meta(get_the_ID(), 'untertitel', true);
render_responsive_top_picture_bg([
    'title' => '<h1>' . esc_html($course->titel_short) . '</h1><p class="teaser">' . esc_html($course->meta_data['yoast_description']) . '</p>',
    'show_logos' => true,
]);

the_simple_breadcrumb();
?>

<div class="container" id="termine-anchor">
    <div id="courses_content_wrap" class="row">

        <!-- Sidebar first in HTML (mobile on top) -->
        <div class="col-xs-12 col-md-4 col-md-push-8" id="sidebar-desktop">
            <?php get_sidebar('courses'); ?>
        </div>

        <!-- Main content second in HTML -->
        <div class="col-xs-12 col-md-8 col-md-pull-4 pdtb-default" id="coures-content">
            <div class="inner-content-mobile-padding">

                <!-- Your main content here: video, excerpt, accordion, trainers -->
                <?php CourseRenderer::renderSingleCourseContent($course); ?>
                <!-- Accordion -->
                <?php CourseRenderer::renderSingleAccordion($course); ?>
            </div>
        </div>
    </div>
</div>

  
    <?php 
    CourseRenderer::renderInterestSection($course);
    CourseRenderer::renderRelatedCourses(); 
    if (function_exists('call_back')) call_back();
    get_footer();
