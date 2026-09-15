<?php


add_action('admin_menu', 'register_wpcf7_everbill_export_page',90);


function register_wpcf7_everbill_export_page() {
    add_submenu_page( 'wpcf7',
        __( 'Everbill Export', 'sieben' ),
        __( 'Everbill Export', 'contact-form-7' ),
        'wpcf7_manage_integration', 'wpcf7-everbill',
        'wpcf7_everbill_export_page' );
}

function wpcf7_everbill_export_page() { ?>

    <div class="wrap">
        <h2><span class="dashicons dashicons-visibility" style="font-size:28px;display:inline-block;margin-right:10px;"></span>
            Everbill Export von Formulardaten</h2>

        <p>Auf dieser Seite können Daten aus Kontkatformularen für das Everbill System exportiert werden.</p>

        <form action="" method="post">
            Startdatum: <input type="datetime-local" name="startdate" max="<?= date('Y-m-d\TH:i:sP') ?>" value="" />
            Enddatum: <input type="datetime-local" name="enddate" max="<?= date('Y-m-d\TH:i:sP') ?>" value="" />

            <input type="hidden" name="export_everbill" value="export_it" />
            <button type="submit" class="button-primary">Jetzt Export-Datei herunterladen</button>
        </form>

        <div>
            <h3>Export Protokoll</h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Export Datum/Uhrzeit</th>
                        <th>Start</th>
                        <th>Ende</th>
                        <th>Erneut herunterladen</th>
                    </tr>
                </thead>
                <tfoot>
                    <tr>
                        <th>Export Datum/Uhrzeit</th>
                        <th>Start</th>
                        <th>Ende</th>
                        <th>Erneut herunterladen</th>
                    </tr>
                </tfoot>
                <tbody>
                <?php
                foreach(get_option("xsieben_everbill_export_history",[]) as $history) {
                    ?>
                <tr>
                    <td><?= $history["exported"] ?></td>
                    <td><?= $history["start"] ?></td>
                    <td><?= $history["end"] ?></td>
                    <td>
                        <form action="" method="post">
                            <input type="hidden" name="startdate" value="<?= date('Y-m-d\TH:i:sP',strtotime($history["start"])) ?>" />
                            <input type="hidden" name="enddate" value="<?= date('Y-m-d\TH:i:sP',strtotime($history["end"])) ?>" />
                            <input type="hidden" name="export_everbill" value="export_it" />
                            <button type="submit" class="button-secondary">Erneut herunterladen</button>
                        </form>
                    </td>
                </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

add_action('init','xsieben_check_export_everbill');

function xsieben_check_export_everbill() {
    if(isset($_POST['export_everbill']) && $_POST["export_everbill"] === "export_it") {

        global $wpdb;

        $start = strtotime($_POST["startdate"]);
        $end = strtotime($_POST["enddate"]);

        if(!$start) {
            $start = 0;
        }

        if(!$end) {
            $end = time()+30;
        }

        $columns = $wpdb->get_col("SELECT DISTINCT name FROM ".$wpdb->prefix."cf7_vdata_entry");

        $aggregations = [];

        foreach($columns as $column) {
            $column = esc_sql($column);
            array_push($aggregations,"MAX(CASE WHEN a.name = '".$column."' THEN a.value END) as `$column`");
        }

        $query = "SELECT a.data_id,
        ".implode(", ",$aggregations)."
        FROM wp_cf7_vdata_entry a
        WHERE a.data_id 
        IN (SELECT id FROM wp_cf7_vdata as base WHERE base.created BETWEEN FROM_UNIXTIME($start) AND FROM_UNIXTIME($end))
        GROUP BY a.data_id ";

        $result = $wpdb->get_results($query);

        // we got the result from the database - now lets create the csv
        header('Content-Disposition: attachment; filename="filename.csv";');
        $csv_ready = [];

        $base_array = [
            "Kd. Nr.(frei lassen)",
            "Herr / Frau / Titel",
            "Ansprechpartner Vorname + Nachname",
            "Strasse - Kunde",
            "PLZ - Kunde",
            "Ort - Kunde",
            "Land - Kunde",
            "Festnetz - Kunde (Tel .1)",
            "Tel. 2 - Kunde",
            "Mobil - Kunde",
            "Fax - Kunde",
            "eMail - Kunde",
            "Website - Kunde",
            "Kontonummer - Kunde",
            "BLZ - Kunde",
            "IBAN - Kunde",
            "BIC - Kunde",
            "Interne Kd. Kt. Nr.",
            "Anzahl LE",
            "UID - Kunde",
            "Geburtsdatum / SV. Nr.-Kunde",
            "Notizen",
            "Kundenkategorie",
            "Herr  / Frau",
            "Vorname - Kunde",
            "Nachname - Kunde",
            "Position - Kunde",
            "Mobil - Allgemein",
            "Festnetz - Ansprechpartner Firma (unten)",
            "Mobil- Ansprechpartner Firma (unten)",
            "eMail - Ansprechpartner Firma (unten)",
            "Filiale - X7 - Name",
            "Filiale - X7 Strasse",
            "Filiale - X7 PLZ",
            "Filiale - X7 Stadt",
            "Filiale - X7 Land",
            "Seminar-Start",
            "Seminar-Ende",
            "Erstelldatum Angebot",
            "",
            "",
            "",
            "Rabatt - Kunde",
            "In %"
        ];

        array_push($csv_ready,$base_array);

        $title_pos = array_search("Herr / Frau / Titel",$base_array);
        $name_pos = array_search("Ansprechpartner Vorname + Nachname",$base_array);
        $street_pos = array_search("Strasse - Kunde",$base_array);
        $zip_pos = array_search("PLZ - Kunde",$base_array);
        $city_pos = array_search("Ort - Kunde",$base_array);
        $firstname_pos = array_search("Vorname - Kunde",$base_array);
        $lastname_pos = array_search("Nachname - Kunde",$base_array);
        $email_pos = array_search("eMail - Kunde", $base_array);
        $festnetz_pos = array_search("Festnetz - Kunde (Tel .1)", $base_array);
        $notizen_pos = array_search("Notizen", $base_array);

        foreach($result as $submission) {
            $entry = array_fill(0, count($base_array), null);
            $entry[$title_pos] = trim(cis_resempty($submission,"title"));
            array_push($csv_ready,$entry);
            $entry[$name_pos] = trim(implode(" ",
                array_filter([
                    cis_resempty($submission,"firstname"),
                    cis_resempty($submission,"lastname"),
                    cis_resempty($submission,"your-name")
                    ],
                    function($val) {
                        return ($val) ? true : false;
                    })));
            $entry[$street_pos] = trim(cis_resempty($submission,"street"));
            $entry[$zip_pos] = trim(cis_resempty($submission,"zip"));
            $entry[$city_pos] = trim(cis_resempty($submission,"city"));
            $entry[$firstname_pos] = trim(cis_resempty($submission,"firstname"));
            $entry[$lastname_pos] = trim(cis_resempty($submission,"lastname"));
            $entry[$email_pos] = trim(cis_resempty($submission,"your-email"));
            $entry[$festnetz_pos] = trim(cis_resempty($submission,"phone"));
            $entry[$notizen_pos] =  trim(implode("\r\n\r\n",
                array_filter([
                    cis_resempty($submission,"forcourse"),
                    "Firma: ".cis_resempty($submission,"company"),
                    cis_resempty($submission,"foerderung"),
                    cis_resempty($submission,"your-subject"),
                    cis_resempty($submission,"your-message")
                        ],
                    function($val) {
                        return ($val && $val !=="Firma: ") ? true : false;
                    })));
            array_push($csv_ready,$entry);
        }

        $history = get_option('xsieben_everbill_export_history',[]);

        if(count($history) > 500) {
            array_pop($history);
            array_pop($history);
        }

        array_unshift($history,[
            'start' => date("Y-m-d H:i:s",$start),
            'end' => date("Y-m-d H:i:s",$end),
            'exported' => date("Y-m-d H:i:s")
        ]);

        update_option('xsieben_everbill_export_history',$history);

        $outstream = fopen("php://output", 'w');
        function __outputCSV(&$vals, $key, $filehandler) {
            fputcsv($filehandler, $vals, ';', '"');
        }
        array_walk($csv_ready, '__outputCSV', $outstream);
        fclose($outstream);
        die;
    }
}