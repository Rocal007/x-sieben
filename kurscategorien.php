<?php

/**
 * Template Name: kurscategorien
 */
get_header();

$cat_id = get_field('cat_selected'); // selected category
$cat_array = ['crashkurs', 'Lehrgang', 'Seminar', 'blended-learning', 'e-learning', 'bundle', 'coaching'];

// Page title & teaser
$content = trim(strip_tags(get_the_content()));
$yoast_description = get_post_meta(get_the_ID(), '_yoast_wpseo_metadesc', true);
$title  = '<h1>' . get_the_title() . '</h1>';
$teaser = '<p class="teaser">' . esc_html($yoast_description) . '</p>';

render_responsive_top_picture_bg([
    'title'             => $title . $teaser,
]);

the_simple_breadcrumb();

// Layout switcher
CourseRenderer::renderLayoutSwitcher('card');

$featured_posts = get_posts([
    'post_type'      => 'courses',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'tax_query'      => [
        [
            'taxonomy' => 'coursecategory',
            'field'    => 'slug',
            'terms'    => $cat_id[0],
        ],
    ],
    'meta_query'     => [
        [
            'key'     => 'topseeller',
            'compare' => 'EXISTS',
        ],
    ],
]);


?>
<div class="container pdtb-default text-center punshline">
    <?php the_content(); ?>
</div>
<div class="container mrb50">
    <div id="courses-container" class="">
        <?php
        if (!empty($featured_posts)) :
            foreach ($featured_posts as $post) :
                setup_postdata($post);
                $course = COURSE_Registry::get(get_the_ID());
                if ($course) :
        ?>

                    <div class="course-wrapper" data-course-id="<?= get_the_ID(); ?>">
                        <?php
                        $layouts = [
                            'x-sieben-card-rich' => 'x-sieben-card-rich',
                            'grid-card' => 'layout-grid-card',
                            'list' => 'layout-list'
                        ];
                        foreach ($layouts as $layout => $class) :
                            $is_active = ($layout === 'x-sieben-card-rich') ? '' : 'style="display:none;"';
                        ?>
                            <div class="<?= esc_attr($class); ?>" <?= $is_active; ?>>
                                <?php ob_start();
                                CourseRenderer::render($course, $layout);
                                echo ob_get_clean(); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
            <?php
                endif;

            endforeach;
            ?>
    </div> <?php
            wp_reset_postdata();
        endif;
            ?>
</div>


<div class="container">
    <div class="pdtb50">
       <div class="x7-format-label">WEITERE FORMATE</div>
<h3 class="x7-format-title"><?php the_title(); ?></h3>
        <div class="panel-group" id="x-sieben-accordion">
            <?php
            $counter = 0;
            foreach ($cat_array as $cat) :
                $myposts = get_posts([
                    'post_type'      => 'courses',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                    'tax_query'      => [
                        'relation' => 'AND',
                        [
                            'taxonomy' => 'coursecategory',
                            'field'    => 'slug',
                            'terms'    => $cat_id[0],
                        ],
                        [
                            'taxonomy' => 'coursecategory',
                            'field'    => 'slug',
                            'terms'    => $cat,
                        ],
                    ],
                ]);

                if (!empty($myposts)) :
                    $counter++;
            ?>
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h4 class="panel-title">
                               <a data-toggle="collapse" data-parent="#x-sieben-accordion" href="#x-sieben-collapse-<?= $counter; ?>">
    <?= ucfirst($cat); ?>

  
</a>
                            </h4>
                        </div>
                        <div id="x-sieben-collapse-<?= $counter; ?>" class="panel-collapse collapse">
                            <div class="row">
                                <?php
                                foreach ($myposts as $post) :
                                    setup_postdata($post);
                                    $course = COURSE_Registry::get(get_the_ID());
                                    if ($course) :
                                ?>
                                        <div class="col-md-12" data-course-id="<?= get_the_ID(); ?>">
                                            <div class="x-sieben-card-rich" style="display:none;">
                                                <?php ob_start();
                                                CourseRenderer::render($course, 'x-sieben-card-rich');
                                                echo ob_get_clean(); ?>
                                            </div>
                                            <div class="layout-grid-card" style="display:none;">
                                                <?php ob_start();
                                                CourseRenderer::render($course, 'grid');
                                                echo ob_get_clean(); ?>
                                            </div>
                                            <div class="layout-list">
                                                <?php ob_start();
                                                CourseRenderer::render($course, 'list');
                                                echo ob_get_clean(); ?>
                                            </div>
                                        </div>
                                <?php
                                    endif;
                                endforeach;
                                wp_reset_postdata();
                                ?>
                            </div>
                        </div>
                    </div>
            <?php
                endif;
            endforeach;
            ?>
        </div>
    </div>
</div>

<?php

faq_view();
interest();
call_back();
get_footer();
