<?php

function related_courses_view()
{  
// Aktuellen Kurs laden
$course = COURSE_Registry::get(get_the_ID());

// Related Courses aus dem Model
$related = $course->related_courses;
// var_dump($related);
if (!empty($related)) : ?>
    <section id="related" class="pdtb-default" role="region" aria-labelledby="related-courses-heading">
        <div class="container">
            <h4 id="related-courses-heading" class="related-courses-heading">
                Passend zu.
            </h4>
        </div>
        <div class="container pdtb-default x-sieben-grid">
            <div class="row">
                <?php foreach ($related as $rel):
                    $title = esc_html($rel['title']);
                    $permalink = esc_url($rel['permalink']);
                    $thumb = esc_url($rel['desktop_image'] ?: get_template_directory_uri() . '/images/default-260x165.png');
                ?>
                    <div class="col-md-4 col-sm-6 mb-4">
                        <article class="x7-course-card related-courses-single-wrapper">
                            <a href="<?= $permalink; ?>" title="<?= $title; ?>">
                                <img src="<?= $thumb; ?>" alt="<?= $title; ?>" loading="lazy">
                            </a>
                            <h4 class="related-courses-title">
                                <a href="<?= $permalink; ?>" title="<?= $title; ?>">
                                    <?= $title; ?>
                                </a>
                            </h4>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; 
} ?>