<?php 
// Generates the .csv File for the AMS
function generate_ams_csv() {

    $exports = new WP_Query([
        'post_type' => 'courses',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'api_ams_publish',
                'value' => 1,
                'compare' => '>='
            ]
        ]
    ]);

    // Define output directory
    $output_dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . "sieben-api";

    if (!is_dir($output_dir)) {
        // Try to create directory if not exists
        if (!wp_mkdir_p($output_dir)) {
            // Could not create directory, log admin notice or error here
            error_log("X-Sieben courses API: Error creating output directory $output_dir");
            return;
        }
    }

    $out = [];

    while ($exports->have_posts()) : $exports->the_post();
        global $post;

        $ort = get_post_meta($post->ID, "strasse", true);
        $zusatz = trim(get_post_meta($post->ID, "addresszusatz", true));
        $kosten = get_post_meta($post->ID, "kosten", true);
        $kosten = mb_convert_encoding(str_replace(".", ",", $kosten), "Windows-1252", "UTF-8");

        if ($zusatz) {
            $ort .= ", " . $zusatz;
        }
        $ort .= " " . get_post_meta($post->ID, "plz", true) . " " . get_post_meta($post->ID, "ort", true);

        $ort_infos = trim(get_post_meta($post->ID, "ort_infos", true));

        if ($ort_infos) {
            $ort .= ", " . $ort_infos;
        }

        $dateval = function ($key) use (&$post) {
            $dtime = strtotime(get_post_meta($post->ID, $key, true));
            if ($dtime === false || $dtime < 100) {
                return "";
            }
            return date("d.m.Y", $dtime);
        };

        // Replace array_flatupshift with wp_list_pluck and implode
        $education_terms = wp_get_object_terms($post->ID, 'fieldofeducation');
        $education_names = !is_wp_error($education_terms) ? wp_list_pluck($education_terms, 'name') : [];

        // Simple clean text function (remove tags and decode entities)
        $clean_text = function ($text) {
            $text = strip_tags($text);
            return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        };

        $out[] = [
            'Seminartitel' => html_entity_decode(get_the_title(), ENT_COMPAT, "UTF-8"),
            'Bildungsbereich' => implode(", ", $education_names),
            'Datum Beginn' => $dateval("start_datum"),
            'Datum Ende' => $dateval("end_datum"),
            'Uhrzeit' => get_post_meta($post->ID, "uhrzeit", true),
            'Seminardauer' => get_post_meta($post->ID, "dauer", true),
            'Inhalt' => strip_tags(get_the_content(), '<ul><ol><li><h1><h2><h3><i><em><b><strong><font><br><p><a>'),
            'Voraussetzungen' => get_post_meta($post->ID, "voraussetzungen", true),
            'Ziele' => $clean_text(get_post_meta($post->ID, "ziele", true)),
            'Zielgruppe' => $clean_text(get_post_meta($post->ID, "zielgruppe", true)),
            'Kosten in Euro - für Suche' => $kosten,
            'Angezeigte Kosten (Text)' => get_post_meta($post->ID, "kosten_text", true),
            'Zertifikat' => get_post_meta($post->ID, "zertifikat", true),
            'Fördermöglichkeit' => get_post_meta($post->ID, "foerdermoeglichkeit", true),
            'Seminarplätze' => get_post_meta($post->ID, "seminarplatze", true),
            'Kontaktperson' => trim(get_post_meta($post->ID, "kontakt_titel", true) . " " . get_post_meta($post->ID, "kontakt_vorname", true) . " " . get_post_meta($post->ID, "kontakt_nachname", true)),
            'eMail' => "office@x-sieben.at",
            'Veranstaltungsort' => $ort,
            'Referent' => get_post_meta($post->ID, "referent", true),
            'Seminarnummer' => get_post_meta($post->ID, "seminarnummer", true),
            'Anmeldelink' => get_the_permalink($post->ID),
        ];

    endwhile;
    wp_reset_postdata();

    // Create CSV string
    $csv = array_to_csv($out, true, ";");

    if (!$csv) {
        error_log("Fehler: Der AMS-Export konnte nicht aktualisiert werden, da ein Fehler beim Erstellen der .csv Datei aufgetreten ist.");
        return;
    }

    $prepared = mb_convert_encoding(str_replace("–", "-", $csv), "Windows-1252", "UTF-8");

    $file_path = $output_dir . DIRECTORY_SEPARATOR . "ams.csv";

    if (file_put_contents($file_path, $prepared)) {
        // Success message (log or admin notice)
        error_log("Der AMS Export wurde erfolgreich aktualisiert.");
        return;
    }

    error_log("Fehler: Der AMS-Export konnte nicht aktualisiert werden, da die Datei nicht beschreibbar ist.");
}

// Helper function to convert array to CSV string
function array_to_csv(array $data, bool $include_headers = true, string $delimiter = ','): string|false {
    if (empty($data)) {
        return false;
    }

    ob_start();
    $df = fopen("php://output", 'w');

    if ($include_headers) {
        fputcsv($df, array_keys(reset($data)), $delimiter);
    }

    foreach ($data as $row) {
        fputcsv($df, $row, $delimiter);
    }
    fclose($df);

    return ob_get_clean();
}
