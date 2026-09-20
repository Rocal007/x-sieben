<?php
add_action('admin_menu', 'register_course_edit_submenu_page', 90);

function register_course_edit_submenu_page()
{
    add_submenu_page(
        'edit.php?post_type=courses',
        'Schnellbearbeitung',
        'Schnellbearbeitung',
        'manage_options',
        'fast-course-edit',
        'course_edit_submenu_page'
    );
}

function course_edit_submenu_page()
{ ?>
    <div class="wrap">
        <h2><span class="dashicons dashicons-visibility" style="font-size:28px;display:inline-block;margin-right:10px;"></span> Kurse schnell bearbeiten</h2>

        <h3>Info</h3>
        <p>Auf dieser Seite können Sie Kurse bearbeiten sowie deren API-Verknüpfungen festlegen.</p>
        <p>Derzeitige URL für die AMS-API:
            <a href="<?php echo esc_url(content_url("sieben-api/ams.csv") . "?v=" . time()); ?>"><?php echo esc_html(content_url("sieben-api/ams.csv") . "?v=" . time()); ?></a>
        </p>
        <p>Derzeitige URL für die Waff-API:
            <a href="<?php echo esc_url(content_url("sieben-api/waff.csv") . "?v=" . time()); ?>"><?php echo esc_html(content_url("sieben-api/waff.csv") . "?v=" . time()); ?></a>
        </p>

        <h3>Kurse bearbeiten</h3>
        <form method="post" action="">

            <input type="text" id="courseTableSearch" placeholder="Tabelle durchsuchen..." style="width: 300px; padding: 6px; font-size: 14px; margin-bottom: 12px;">

            <table class="widefat js-sort-table" id="fastCourses">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Format / Modus</th>
                        <th>Kurs Kategorie</th>
                        <th>Startdatum</th>
                        <th>Enddatum</th>
                        <th>LE Gesamt</th>
                        <th>Preis Netto (€)</th>
                        <th>Startgarantie</th>
                        <th>Waff Nr.</th>
                        <th>API: AMS</th>
                        <th>API: WAFF</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th>Name</th>
                        <th>Format / Modus</th>
                        <th>Kurs Kategorie</th>
                        <th>Startdatum</th>
                        <th>Enddatum</th>
                        <th>LE Gesamt</th>
                        <th>Preis Netto (€)</th>
                        <th>Startgarantie</th>
                        <th>Waff Nr.</th>
                        <th>API: AMS</th>
                        <th>API: WAFF</th>
                    </tr>
                </tfoot>
                <tbody>
                    <?php
                    $courses = new WP_Query(["post_type" => "courses", "posts_per_page" => -1]);
                    if ($courses->have_posts()) :
                        while ($courses->have_posts()) : $courses->the_post();
                            global $post;

                            $category = get_terms(array(
                                'taxonomy' => 'coursecategory',
                                'hide_empty' => false,
                                'order' => 'DESC'
                            ));

                            $category_display = '';
                            if ($category) {
                                if (class_exists('WPSEO_Primary_Term')) {
                                    $primary_term = new WPSEO_Primary_Term('coursecategory', get_the_ID());
                                    $term_id = $primary_term->get_primary_term();
                                    $term = get_term($term_id);
                                    if (!is_wp_error($term)) {
                                        $category_display = $term->name;
                                    } else {
                                        $category_display = $category[0]->name;
                                    }
                                } else {
                                    $category_display = $category[0]->name;
                                }
                            }

                            $start_datum = get_post_meta($post->ID, 'start_datum', true);
                            $end_datum = get_post_meta($post->ID, 'end_datum', true);
                            $durchfuehrungsmodus = get_post_meta($post->ID, 'durchfuehrungsmodus', true);
                            $lehreinheiten_gesamt = get_post_meta($post->ID, 'lehreinheiten_gesamt', true);
                            $kosten = get_post_meta($post->ID, 'kosten', true);
                            $startgarantie = get_post_meta($post->ID, 'startgarantie', true);
                            $api_ams_publish = get_post_meta($post->ID, 'api_ams_publish', true);
                            $api_waff_publish = get_post_meta($post->ID, 'api_waff_publish', true);
                            $waff_number = get_post_meta($post->ID, 'waff_number', true);

                            $start_datum = (!empty($start_datum) && strtotime($start_datum)) ? date('Y-m-d', strtotime($start_datum)) : '';
                            $end_datum = (!empty($end_datum) && strtotime($end_datum)) ? date('Y-m-d', strtotime($end_datum)) : '';
                            ?>
                            <tr>
                                <td><a href="<?php echo esc_url(get_edit_post_link(get_the_ID())); ?>"><strong><?php the_title(); ?></strong></a></td>
                                <td>
                                    <select name="post-<?php the_ID(); ?>[durchfuehrungsmodus]" style="max-width:140px; font-size:12px;">
                                        <option value="">-- Standard --</option>
                                        <option value="praesenz" <?php selected($durchfuehrungsmodus, 'praesenz'); ?>>Präsenz</option>
                                        <option value="online" <?php selected($durchfuehrungsmodus, 'online'); ?>>Live-Online</option>
                                        <option value="blended" <?php selected($durchfuehrungsmodus, 'blended'); ?>>Blended</option>
                                        <option value="elearning" <?php selected($durchfuehrungsmodus, 'elearning'); ?>>E-Learning</option>
                                        <option value="inhouse" <?php selected($durchfuehrungsmodus, 'inhouse'); ?>>Inhouse</option>
                                    </select>
                                </td>
                                <td><?php echo esc_html($category_display); ?></td>
                                <td>
                                    <input type="hidden" name="post-<?php the_ID(); ?>[ID]" value="<?php the_ID(); ?>" />
                                    <input type="date" name="post-<?php the_ID(); ?>[start_datum]" value="<?php echo esc_attr($start_datum); ?>" />
                                </td>
                                <td>
                                    <input type="date" name="post-<?php the_ID(); ?>[end_datum]" value="<?php echo esc_attr($end_datum); ?>" />
                                </td>
                                <td>
                                    <input type="number" name="post-<?php the_ID(); ?>[lehreinheiten_gesamt]" value="<?php echo esc_attr($lehreinheiten_gesamt); ?>" style="width:70px;" placeholder="LE" />
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="post-<?php the_ID(); ?>[kosten]" value="<?php echo esc_attr($kosten); ?>" style="width:90px;" placeholder="€ Netto" />
                                </td>
                                <td style="text-align:center;">
                                    <input type="checkbox" name="post-<?php the_ID(); ?>[startgarantie]" value="1" <?php checked(intval($startgarantie), 1); ?> title="Startgarantie aktiv" />
                                </td>
                                <td>
                                    <input type="number" name="post-<?php the_ID(); ?>[waff_number]" value="<?php echo esc_attr($waff_number); ?>" style="width:80px;" />
                                </td>
                                <td style="text-align:center;">
                                    <input type="checkbox" name="post-<?php the_ID(); ?>[api_ams_publish]" value="1" <?php checked(intval($api_ams_publish), 1); ?> />
                                </td>
                                <td style="text-align:center;">
                                    <input type="checkbox" name="post-<?php the_ID(); ?>[api_waff_publish]" value="1" <?php checked(intval($api_waff_publish), 1); ?> />
                                </td>
                            </tr>
                    <?php
                        endwhile;
                    endif;
                    wp_reset_postdata();
                    ?>
                </tbody>
            </table>

            <input type="hidden" name="xsieben_coursefastedit" value="perform" />
            <p class="submit">
                <input type="submit" name="submit" class="button button-primary" value="Änderungen speichern">
            </p>
        </form>
    </div>
<?php
}

add_action('admin_init', 'xsieben_coursefastedit_check');

function xsieben_coursefastedit_check()
{
    if (isset($_POST["xsieben_coursefastedit"]) && $_POST["xsieben_coursefastedit"] === "perform") {
        $count = 0;
        foreach ($_POST as $key => $course) {
            if (strpos($key, "post-") === 0 && is_array($course)) {
                $course_id = intval($course["ID"] ?? 0);
                if ($course_id > 0) {
                    $start_datum = !empty($course["start_datum"]) && strtotime($course["start_datum"]) ? date('Y-m-d', strtotime($course["start_datum"])) : '';
                    $end_datum = !empty($course["end_datum"]) && strtotime($course["end_datum"]) ? date('Y-m-d', strtotime($course["end_datum"])) : '';
                    $durchfuehrungsmodus = sanitize_text_field($course["durchfuehrungsmodus"] ?? '');
                    $lehreinheiten_gesamt = sanitize_text_field($course["lehreinheiten_gesamt"] ?? '');
                    $kosten = sanitize_text_field($course["kosten"] ?? '');
                    $startgarantie = isset($course["startgarantie"]) && $course["startgarantie"] == '1' ? 1 : 0;
					$waff_number = sanitize_text_field($course["waff_number"] ?? '');
                    $api_ams_publish = isset($course["api_ams_publish"]) && $course["api_ams_publish"] == '1' ? 1 : 0;
                    $api_waff_publish = isset($course["api_waff_publish"]) && $course["api_waff_publish"] == '1' ? 1 : 0;

                    update_post_meta($course_id, "start_datum", $start_datum);
                    update_post_meta($course_id, "end_datum", $end_datum);
                    update_post_meta($course_id, "durchfuehrungsmodus", $durchfuehrungsmodus);
                    update_post_meta($course_id, "lehreinheiten_gesamt", $lehreinheiten_gesamt);
                    update_post_meta($course_id, "kosten", $kosten);
                    update_post_meta($course_id, "startgarantie", $startgarantie);
					update_post_meta($course_id, "waff_number", $waff_number);
                    update_post_meta($course_id, "api_ams_publish", $api_ams_publish);
                    update_post_meta($course_id, "api_waff_publish", $api_waff_publish);

                    $count++;
                }
            }
        }

        set_transient('xsieben_coursefastedit_success', $count, 30);

        generate_ams_csv();
        generate_waff_csv();
    }
}

add_action('admin_notices', function () {
    if ($count = get_transient('xsieben_coursefastedit_success')) {
        delete_transient('xsieben_coursefastedit_success');
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html("$count Kurse wurden erfolgreich aktualisiert."); ?></p>
        </div>
        <?php
    }
});
