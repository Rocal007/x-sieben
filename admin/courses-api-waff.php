<?php
// Generates the .csv File for the WAFF
function generate_waff_csv()
{

    $exports = new WP_Query([
        'post_type' => 'courses',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'api_waff_publish',
                'value' => 1,
                'compare' => '>='
            ]
        ]
    ]);

    $output_dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . "sieben-api";

    if (!is_dir($output_dir)) {
        if (!wp_mkdir_p($output_dir)) {
            error_log("X-Sieben courses API: Error creating output directory $output_dir");
            return;
        }
    }

    $out = [];

    $clean_text = function ($text) {
        // 1. Remove specific Visual Composer shortcodes and their closing tags
        $text = preg_replace('/\[vc_row.*?\]/s', '', $text);
        $text = preg_replace('/\[\/vc_row\]/s', '', $text);
        $text = preg_replace('/\[vc_column.*?\]/s', '', $text);
        $text = preg_replace('/\[\/vc_column\]/s', '', $text);
        $text = preg_replace('/\[vc_column_text.*?\]/s', '', $text);
        $text = preg_replace('/\[\/vc_column_text\]/s', '', $text);

        // 2. Process any other standard WordPress shortcodes
        $text = do_shortcode($text);

        // 3. Strip all remaining HTML tags
        $text = strip_tags($text);

        // 4. Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 5. Replace multiple whitespace characters (including newlines) with a single space
        $text = preg_replace('/\s+/', ' ', $text);

        // 6. Trim leading and trailing whitespace
        $text = trim($text);

        return $text;
    };

    // --- Initialize the counter for 'Angebotsnummer *' ---
    $angebotsnummer_index = 1;

    while ($exports->have_posts()) : $exports->the_post();
        global $post;

        // --- Existing Meta Data Extraction ---
        $strasse = get_post_meta($post->ID, "strasse", true);
        $addresszusatz = trim(get_post_meta($post->ID, "addresszusatz", true));
        $plz = get_post_meta($post->ID, "plz", true);
        $ort_name = get_post_meta($post->ID, "ort", true); // Renamed to avoid conflict with function parameter
        $ort_infos = trim(get_post_meta($post->ID, "ort_infos", true));

        // Retrieve and apply fallback (assuming a fallback of 0 for calculation if costs are empty)
       $kosten = !empty($tempKosten = get_post_meta($post->ID, "kosten", true)) ? round((float)$tempKosten * 1.2, 1) : 0;

        // Now, convert the decimal point to a comma for display
        $kosten = str_replace(".", ",", (string)$kosten);

        $dateval = function ($key) use (&$post) {
            $dtime = strtotime(get_post_meta($post->ID, $key, true));
            return ($dtime === false || $dtime < 100) ? "" : date("d.m.Y", $dtime);
        };

        $timeval = function ($key) use (&$post) {
            // Assuming time is stored in HH:MM format
            return trim(get_post_meta($post->ID, $key, true));
        };

        $education_term = get_post_meta($post->ID, "waff_number", true);

        // Get the raw content for processing by clean_text
        $the_content = get_the_content();

        // --- New/Re-mapped Data for Columns ---
        // $seminarnummer is no longer needed as Angebotsnummer is now an index.
        $untertitel = get_post_meta($post->ID, "untertitel", true); // Assuming a new meta key for subtitle
        $uebungseinheiten = trim($clean_text(get_post_meta($post->ID, "lehreinheiten_gesamt", true)));
        // Booleans, ensure 0 or 1
        $termin_auf_anfrage = (int) get_post_meta($post->ID, "termin_auf_anfrage", true); // Assuming meta key 'termin_auf_anfrage'
        $fernlehre_ohne_praesenz = !empty($fernlehre_ohne_praesenz = get_post_meta($post->ID, "fernlehre_ohne_praesenz", true)) ? (int)$fernlehre_ohne_praesenz : 1;
        $barrierefreier_zugang = (int) get_post_meta($post->ID, "barrierefreier_zugang", true); // Assuming meta key 'barrierefreier_zugang'
        $kinderbetreuung = (int) get_post_meta($post->ID, "kinderbetreuung", true); // Assuming meta key 'kinderbetreuung'
        $spezielles_uebungsangebot = (int) get_post_meta($post->ID, "spezielles_uebungsangebot", true); // Assuming meta key 'spezielles_uebungsangebot'

        $einstieg_datum = $dateval("einstieg_datum"); // Assuming meta key 'einstieg_datum'
        $teilnehmer_min = !empty($teilnehmer_min = get_post_meta($post->ID, "teilnehmer_min", true)) ? (int)$teilnehmer_min : 1;
        $seminarplatze = get_post_meta($post->ID, "seminarplatze", true); // This was 'Seminarplätze'
        $unterrichtssprache = trim($clean_text(get_post_meta($post->ID, "unterrichtssprache", true))); // Assuming meta key 'unterrichtssprache'
        $lehrmethode = trim($clean_text(get_post_meta($post->ID, "lehrmethode", true))); // Assuming meta key 'lehrmethode'
        $ermaessigungen = trim($clean_text(get_post_meta($post->ID, "ermaessigungen", true))); // Assuming meta key 'ermaessigungen'

        $kontakt_titel = trim(get_post_meta($post->ID, "kontakt_titel", true));
        $kontakt_vorname = trim(get_post_meta($post->ID, "kontakt_vorname", true));
        $kontakt_nachname = trim(get_post_meta($post->ID, "kontakt_nachname", true));
        $kontakt_titel_nachgestellt = trim(get_post_meta($post->ID, "kontakt_titel_nachgestellt", true)); // Assuming meta key
        $kontakt_funktion = trim(get_post_meta($post->ID, "kontakt_funktion", true)); // Assuming meta key
        $kontakt_telefon = trim(get_post_meta($post->ID, "kontakt_telefon", true)); // Assuming meta key

        $isced_kategorie = trim(get_post_meta($post->ID, "isced_kategorie", true)); // Assuming meta key
        $nqr_kategorie = trim(get_post_meta($post->ID, "nqr_kategorie", true)); // Assuming meta key
        $kampagne = !empty($kampagne = trim(get_post_meta($post->ID, "kampagne", true))) ? $kampagne : 'elearn,digi';


        $out[] = [
            'Angebotsnummer *' => $angebotsnummer_index, // Using the incrementing index here
            'Angebotstitel *' => trim($clean_text(get_the_title())),
            'Untertitel' => $untertitel,
            'Beschreibung *' => trim($clean_text($the_content)),
            'Preis brutto € *' => $kosten,
            'Anmerkung zum Preis' => trim($clean_text(get_post_meta($post->ID, "kosten_text", true))),
            'Übungseinheiten (UE) *' => $uebungseinheiten,
            'Klassenzuordnung/Themenbaum *' => $education_term,
            'Erster Kurstag*' => $dateval("start_datum"),
            'Erster Kurstag Beginn - Uhrzeit' => $timeval("uhrzeit"),
            'Letzter Kurstag*' => $dateval("end_datum"),
            'Letzter Kurstag Ende - Uhrzeit' => $timeval("end_uhrzeit"),
            'Termin auf Anfrage**' => $termin_auf_anfrage,
            'Fernlehre ohne Präsenz**' => $fernlehre_ohne_praesenz,
            'Einstieg möglich bis - Datum' => $einstieg_datum,
            'Angebotsort - Anschrift' => trim($strasse . " " . $addresszusatz),
            'Angebotsort - PLZ' => $plz,
            'Angebotsort - Ort' => $ort_name,
            'Angebotsort - Raum/Anmerkung' => $ort_infos,
            'TeilnehmerInnen min.' => $teilnehmer_min,
            'TeilnehmerInnen max.' => $seminarplatze,
            'ReferentIn' => trim($clean_text(get_post_meta($post->ID, "referent", true))),
            'Unterrichtssprache' => $unterrichtssprache,
            'Teilnahmevoraussetzungen' => trim($clean_text(get_post_meta($post->ID, "voraussetzungen", true))),
            'Zielgruppe' => trim($clean_text(get_post_meta($post->ID, "zielgruppe", true))),
            'Lehrmethode' => $lehrmethode,
            'Lernziel' => trim($clean_text(get_post_meta($post->ID, "ziele", true))),
            'Ermässigungen' => $ermaessigungen,
            'Abschlüsse' => trim($clean_text(get_post_meta($post->ID, "zertifikat", true))),
            'Direktlink zum Angebot' => get_the_permalink($post->ID),
            'Ansprechperson - Titel vorgestellt' => $kontakt_titel,
            'Ansprechperson - Vorname' => $kontakt_vorname,
            'Ansprechperson - Nachname' => $kontakt_nachname,
            'Ansprechperson - Titel nachgestellt' => $kontakt_titel_nachgestellt,
            'Ansprechperson - Funktion' => $kontakt_funktion,
            'Ansprechperson - Emailadresse' => "office@x-sieben.at",
            'Ansprechperson - Telefonnummer' => $kontakt_telefon,
            'barrierefreier Zugang' => $barrierefreier_zugang,
            'Kinderbetreuung' => $kinderbetreuung,
            'spezielles Übungsangebot' => $spezielles_uebungsangebot,
            'ISCED Kategorie' => $isced_kategorie,
            'NQR-Kategorie' => $nqr_kategorie,
            'Kampagne' => $kampagne,
        ];

        // --- Increment the counter for the next course ---
        $angebotsnummer_index++;
    endwhile;
    wp_reset_postdata();

    $csv = array_to_csv($out, true, ";");

    if (!$csv) {
        error_log("Fehler: Der WAFF-Export konnte nicht aktualisiert werden, da ein Fehler beim Erstellen der .csv Datei aufgetreten ist.");
        return;
    }

    // Add BOM for UTF-8 compatibility with Excel
    $prepared = "\xEF\xBB\xBF" . $csv;

    $file_path = $output_dir . DIRECTORY_SEPARATOR . "waff.csv";

    if (file_put_contents($file_path, $prepared)) {
        error_log("Der WAFF Export wurde erfolgreich aktualisiert.");
        return;
    }

    error_log("Fehler: Der WAFF-Export konnte nicht aktualisiert werden, da die Datei nicht beschreibbar ist.");
}
