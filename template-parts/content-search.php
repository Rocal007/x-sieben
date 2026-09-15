<div id="courses-container" class="pdtb-default mrt20">
    <?php while (have_posts()) : the_post(); 
        $course = COURSE_Registry::get(get_the_ID());
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
                    <?php ob_start(); CourseRenderer::render($course, $layout); echo ob_get_clean(); ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endwhile; ?>
</div>
