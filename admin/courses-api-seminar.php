<?php

// Generates the .csv File for the AMS
function generate_seminar_csv() {

    $exports = new WP_Query(
        [
            'post_type' => 'courses',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'api_seminar_publish',
                    'value' => 1,
                    'compare' => '>='
                ]
            ]
        ]
    );

    // create csv

    // remove old .csv
    $output_location = realpath(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . "sieben-api");

    if(!$output_location) {
        new \CIS\AdminNotice("X-Sieben courses API: Error finding output location.",CIS\NoticeTypes::ERROR);
        return;
    }

    $out = [];

    while($exports->have_posts()): $exports->the_post();
        global $post;

        $ort = get_post_meta($post->ID,"strasse",true);
        $zusatz = trim(get_post_meta($post->ID,"addresszusatz",true));

        if($zusatz) {
            $ort .= ", ".$zusatz;
        }
        $ort .= " ".get_post_meta($post->ID,"plz",true)." ".get_post_meta($post->ID,"ort",true);

        $ort_infos = trim(get_post_meta($post->ID,"ort_infos",true));



        if($ort_infos) {
            $ort .= ", ".$ort_infos;
        }

        $uhrzeit = get_post_meta($post->ID,"uhrzeit",true);
        $uhrzeit_ende = get_post_meta($post->ID,"uhrzeit_ende",true);

        if($uhrzeit_ende) {
            $uhrzeit .= " - ".$uhrzeit_ende;
        }

        array_push($out,
            [
                'ID' => get_the_ID(),
                'Titel' => html_entity_decode(get_the_title(),ENT_COMPAT, "UTF-8"),
                'Kategorie' => implode(", ",array_flatupshift(wp_get_object_terms($post->ID, 'coursecategory' ),"name")),
                'Kurzbeschreibung' => get_post_meta($post->ID,"untertitel",true),
                'Langbeschreibung' => cis_clean_text(wp_strip_all_tags(get_the_content())),
                'ID-Termin' => get_the_ID(),
                'Datum von' => date('dmY',strtotime(get_post_meta($post->ID,"start_datum",true))),
                'Datum bis' => date('dmY',strtotime(get_post_meta($post->ID,"end_datum",true))),
                'Uhrzeit' => $uhrzeit,
                'Zeithinweise' => get_post_meta($post->ID,"zeit_zusatz",true),
                'Ort' => get_post_meta($post->ID,"ort",true),
                'Land' => "Österreich",
                'Bundesland' => get_post_meta($post->ID,"bundesland",true),
                'PLZ' => get_post_meta($post->ID,"plz",true),
                'Preis' => get_post_meta($post->ID,"kosten",true),
                'Währung' => "EUR",
                'Steuersatz' => 0,
                'Rabatt' => 0,
                'Trainer' => get_post_meta($post->ID,"referent",true),
                'Informationen' => '',
                'Link-Termin' => get_the_permalink(),
                'Link-Seminar' => get_the_permalink()
            ]
        );
    endwhile;

    $csv = array2csv($out,false,";");

    if(!$csv) {
        new \CIS\AdminNotice("Fehler: Der Seminar-Export konnte nicht aktualisiert werden, da ein Fehler beim Erstellen der .csv Datei aufgetreten ist.",CIS\NoticeTypes::ERROR);
        return;
    }

    if(file_put_contents($output_location . DIRECTORY_SEPARATOR . "seminar.txt",mb_convert_encoding($csv, "Windows-1252", "UTF-8"))) {
        new \CIS\AdminNotice("Der Seminar Export wurde erfolgreich aktualisiert.",CIS\NoticeTypes::SUCCESS);
        return;
    }
    new \CIS\AdminNotice("Fehler: Der Seminar-Export konnte nicht aktualisiert werden, da die Datei nicht beschreibbar ist.",CIS\NoticeTypes::ERROR);
}