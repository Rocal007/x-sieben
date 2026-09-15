<?php

/**
 * The sidebar containing the main widget area for courses
 *
 * @package sieben
 */



if (is_single() && $post->post_type === 'courses') :

    // Load course via registry
    $course = COURSE_Registry::get(get_the_ID()); // ✅ Load via registry
?>
    <aside id="courses-sidebar" class="right" role="complementary" aria-label="Course Sidebar">
        <div class="courses-sidebar-inner">

            <!-- Course Details -->
            <section class="courses-sidebar-element clearfix" aria-labelledby="sidebar-course-details">
                <div class="h4" id="sidebar-course-details" class="sidebar-heading">
                    Wichtige <b>Kursdetails</b>
                </div>

                <div class="sidebar-inner-container">
                    <div class="courses-sidebar-icon zertifikat"></div>
                    <div class="courses-sidebar-element-inner abschluss">
                        <?php $course->xsieben_sidebar_row("ABSCHLUSS:", $course->abschluss); ?>
                    </div>
                </div>

                <div class="courses-sidebar-icon clock"></div>
                <div class="courses-sidebar-element-inner">
                    <?php
                    $course->xsieben_sidebar_row("KURSBEGINN:", $course->start_datum);
                    $course->xsieben_sidebar_row("KURSENDE:", $course->end_datum);
                    $course->xsieben_sidebar_row("DAUER:", $course->dauer ?? '');
                    $course->xsieben_sidebar_row("IHRE INVESTITION:", "€ " . $course->preis_netto . ".-");
                    // if (!empty($course->kurszeiten)) {
                    //     $times = array_values($course->kurszeiten);
                    //     $time = $times[0];
                    //     $course->xsieben_sidebar_row("KURSZEITEN:", $time);
                    // }


                    ?>
                </div>

            </section>

            <!-- Direct Help -->
            <section class="courses-sidebar-element clearfix" aria-labelledby="sidebar-direct-help">
                <div class="h4" id="sidebar-direct-help" class="screen-reader-text">Direkte Hilfe</div>
                <a href="tel:<?php echo esc_attr($course->phone_number); ?>"
                    class="courses-sidebar-link"
                    aria-label="Direkte Hilfe anrufen <?php echo esc_html($course->phone_number); ?>">
                    <div class="courses-sidebar-icon phone" aria-hidden="true"></div>
                    <div class="courses-sidebar-element-inner">
                        <strong>DIREKTE HILFE:</strong> <?php echo esc_html($course->phone_number); ?>
                    </div>
                </a>
            </section>

            <!-- Reviews -->
            <section class="courses-sidebar-element clearfix" aria-labelledby="sidebar-reviews">
                <div class="h4" id="sidebar-reviews" class="screen-reader-text">Rezensionen</div>
                <div class="courses-sidebar-icon star" aria-hidden="true"></div>
                <div class="courses-sidebar-element-inner">
                    <strong>REZENSIONEN:</strong>
                    <div class="sidebar-link">
                        <a href="https://www.provenexpert.com/x-sieben-wirtschaftstraining/?utm_source=Widget&utm_medium=Widget&utm_campaign=Widget"
                            target="_blank" rel="noopener noreferrer" aria-label="Externe Bewertungen anzeigen auf ProvenExpert">
                            Externe Bewertungen anzeigen
                        </a>
                    </div>
                    <div class="hidden-xs"><?php proven_expert_sidebar(); ?></div>
                </div>
            </section>

            <!-- 3-Fach Guarantee -->
            <section class="courses-sidebar-element clearfix hidden-xs" aria-labelledby="sidebar-guarantee">
                <div class="h4" id="sidebar-guarantee" class="screen-reader-text">3-Fach Garantie</div>
                <div class="courses-sidebar-icon finger" aria-hidden="true"></div>
                <div class="courses-sidebar-element-inner vierfachgarantie">
                    <strong>X SIEBEN 3-FACH GARANTIE:</strong>
                    <ul>
                        <li>Rücktrittsgarantie</li>
                        <li>Kleingruppen-Garantie</li>
                        <li>Durchführungsgarantie</li>
                    </ul>
                </div>
            </section>

            <!-- Share Buttons -->
            <section class="courses-sidebar-element clearfix hidden-xs" aria-labelledby="sidebar-share">
                <div class="h4" id="sidebar-share" class="screen-reader-text">Veranstaltung teilen</div>
                <div class="courses-sidebar-icon share" aria-hidden="true"></div>
                <div class="courses-sidebar-element-inner">
                    <strong>DIESE VERANSTALTUNG TEILEN:</strong>
                    <?php display_share_buttons(); ?>
                </div>
            </section>

        </div>
    </aside>
<?php endif; ?>