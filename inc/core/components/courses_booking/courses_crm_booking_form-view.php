<?php
function crm_booking_form()
{ ?>


    <!--Angebot in 8 Stunden-->


    <?php
    if (is_user_logged_in()) {

        $kursart = get_post_meta(get_the_ID(), 'tages_abend_wochenende_', true);
        $kursart = $kursart[0];
        $voraussetzungen = get_post_meta(get_the_ID(), 'voraussetzungen_abschluss', true);
        if ($kursart == "Tageskurs") {
            $kursart_t = "<strong>X</strong>";
        }

        if ($kursart == "Abendkurs") {
            $kursart_a = "<strong>X</strong>";
        }

        if ($kursart == "Wochenendkurs") {
            $kursart_we = "<strong>X</strong>";
        }
        $kurszeiten = get_field("kurszeiten");
        if (in_array("Montag", $kurszeiten)) {
            $montag = "09.00 - 17.00 Uhr";
        }
        if (in_array("Dienstag", $kurszeiten)) {
            $dienstag = "09.00 - 17.00 Uhr";
        }
        if (in_array("Mittwoch", $kurszeiten)) {
            $mittwoch = "09.00 - 17.00 Uhr";
        }
        if (in_array("Donnerstag", $kurszeiten)) {
            $donnerstag = "09.00 - 17.00 Uhr";
        }
        if (in_array("Freitag", $kurszeiten)) {
            $freitag = "09.00 - 17.00 Uhr";
        }
        if (in_array("Samstag", $kurszeiten)) {
            $samstag = "09.00 - 17.00 Uhr";
        }
        if (in_array("Sonntag", $kurszeiten)) {
            $sonntag = "09.00 - 17.00 Uhr";
        }

        $selbststudium = get_field("selbstudium");
        if (in_array("Montag", $selbststudium)) {
            $montag_s = "09.00 - 17.00 Uhr";
        }
        if (in_array("Dienstag", $selbststudium)) {
            $dienstag_s = "09.00 - 17.00 Uhr";
        }
        if (in_array("Mittwoch", $selbststudium)) {
            $mittwoch_s = "09.00 - 17.00 Uhr";
        }
        if (in_array("Donnerstag", $selbststudium)) {
            $donnerstag_s = "09.00 - 17.00 Uhr";
        }
        if (in_array("Freitag", $selbststudium)) {
            $freitag_s = "09.00 - 17.00 Uhr";
        }
        if (in_array("Samstag", $selbststudium)) {
            $samstag_s = "09.00 - 17.00 Uhr";
        }
        if (in_array("Sonntag", $selbststudium)) {
            $sonntag_s = "09.00 - 17.00 Uhr";
        }

        $title_preis = get_the_title();
        $preis = get_post_meta(get_the_ID(), 'kosten', true);
        $angebot_beschreibung = get_post_meta(get_the_ID(), 'angebot_beschreibung', true);
        $anzahl_le = get_post_meta(get_the_ID(), 'lehreinheiten_gesamt', true);
        $termine_pdf = get_field('kurszeiten_details_pdf');
        $title = esc_html(get_the_title());
        $kurszeiten = get_field("kurszeiten");
        ?>
        <div id="angebot-anchor" class="crm-booking-form">
            <div class="container">
                <form id="contact-form" method="post" class="form left-margin" action="" role="form" name="courses_booking">

                    <h2>Nur für Admin User</h2>

                    <div class="controls">

                        <div class="row">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <?php $start = date('d.m.Y', strtotime(get_post_meta(get_the_ID(), 'start_datum', true)));
                                    //var_dump($start);
                            
                                    ?>
                                    <?php $end = date('d.m.Y', strtotime(get_post_meta(get_the_ID(), 'end_datum', true)));
                                    //var_dump($end);
                            
                                    ?>

                                    <select id="anrede" name="anrede" class="form-control" required="required"
                                        placeholder="Anrede" data-error="Bitte geben Sie Ihre Anrede ein">
                                        <option value="Anrede">Anrede</option>
                                        <option value="Herr">Herr</option>
                                        <option value="Frau">Frau</option>
                                        <option value="Firma">Firma</option>
                                    </select>
                                    <div class="help-block with-errors"></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">

                                    <input id="angebot-vorname" type="text" name="vorname" class="form-control"
                                        placeholder="Vorname *" required="required"
                                        data-error="bitte geben Sie Ihren Vornamen ein">
                                    <div class="help-block with-errors"></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">

                                    <input id="angebot-nachname" type="text" name="nachname" class="form-control"
                                        placeholder="Nachname *" required="required"
                                        data-error="bitte geben Sie Ihren Nachnamen ein">
                                    <div class="help-block with-errors"></div>
                                </div>
                            </div>


                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">

                                    <input id="angebot-email" type="email" name="email" class="form-control"
                                        placeholder="E-Mail *" required="required"
                                        data-error="bitte geben Sie eine gültige E-Mail Adresse ein.">
                                    <div class="help-block with-errors"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">

                                    <input id="angebot-firma" type="text" name="firma" class="form-control" placeholder="Firma"
                                        data-error="">
                                    <div class="help-block with-errors"></div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">

                                    <input id="angebot-firma-abteilung" type="text" name="firma-abteilung" class="form-control"
                                        placeholder="Abteilung" data-error="">
                                    <div class="help-block with-errors"></div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">

                                    <input id="angebot-strasse" type="text" name="strasse" class="form-control"
                                        placeholder="Straße" data-error="bitte geben Sie Ihre Strasse ein">
                                    <div class="help-block with-errors"></div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">

                                    <input id="angebot-nummer" type="text" name="text" class="form-control" placeholder="Nr"
                                        data-error="itte geben Sie Ihre Hausnummer ein ein">
                                    <div class="help-block with-errors"></div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-2">
                                <div class="form-group">

                                    <input id="angebot-plz" type="text" name="plz" class="form-control" placeholder="PLZ"
                                        data-error="Bitte geben Sie Ihre Postleitzahl ein">
                                    <div class="help-block with-errors"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">

                                    <input id="angebot-ort" type="text" name="ort" class="form-control" placeholder="Ort"
                                        data-error="Bitte geben Sie den Ort ein">
                                    <div class="help-block with-errors"></div>

                                </div>
                            </div>
                        </div>
                        <div class="row" style="">

                            <div class="col-md-6">
                                <p>Ich möchte ein förderfähiges Angebot für:</p>
                            </div>
                            <div class="col-md-1">
                                <div class="form-group inner-checkbox">

                                    <input type="checkbox" id="ams" name="ams" value="ams">
                                    <label class="wpcf7-list-item-label" for="ams">AMS</label>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="form-group inner-checkbox">

                                    <input type="checkbox" id="waff" name="waff" value="waff">
                                    <label class="wpcf7-list-item-label" for="waff">waff, Landesförderungen,...</label>
                                </div>
                            </div>
                        </div>

                        <div class="row hide-it" id="svr" name="svr">
                            <div class="col-md-8">
                                Bitte geben Sie Ihre Sozialversicherungsnummer für Ihre Teilnahmebestätigung ein (optional)
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">

                                    <input class="form-control" type="text" id="svrnum" name="svrnum" placeholder="1234 010190">
                                    <div class="help-block with-errors"></div>
                                </div>
                            </div>
                        </div>
                        <div class="row" style="">
                            <div class="col-md-12">
                                <div class="form-group inner-checkbox">
                                    <input type="checkbox" id="reservierung" name="reservierung" value="reservierung">
                                    <label class="wpcf7-list-item-label" for="reservierung">Teilnahmeplatz für 10 Tage
                                        reservieren</label>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <?php
                            if (have_rows('zertifizierungen')):
                                echo '<div class="col-md-12">';
                                echo '<div class="form-group inner-checkbox">';

                                while (have_rows('zertifizierungen')):
                                    the_row();

                                    echo '<input type="checkbox" id="' . get_sub_field('name-zert') . '" name="zertifizierungen" value="' . get_sub_field('name-zert') . '" price="' . get_sub_field('preis') . '" ust="' . get_sub_field('Ust_satz') . '"> ';

                                    echo '<label class="wpcf7-list-item-label" for="' . get_sub_field('name-zert') . '"> ' . get_sub_field('name-zert') . ' (zzg. € ' . get_sub_field('preis') . '.-)</label><br />';
                                endwhile;
                                echo '</div></div>';
                            else:

                            endif; ?>
                        </div>
                        <div id="zert_single" style="display: none">

                            <?php
                            if (have_rows('zertifizierungen')):


                                while (have_rows('zertifizierungen')):
                                    the_row();

                                    echo '<div style="font-size: 11pt; line-height: 18pt;"><span style="color: white"><span> - ' . get_sub_field('name-zert') . ' zzgl.  € ' . get_sub_field('preis') . '.- (inkl. ' . get_sub_field('Ust_satz') . '% MwSt)</div>';
                                endwhile;

                            else:

                            endif;
                            ?>

                        </div>

                        <div id="zert_images" style="display: none">
                            <?php echo do_shortcode("[zertifizierungen_images]"); ?>
                        </div>
                        <div id="zert_new" style="display: none"></div>

                        <div id="module" style="display: none">
                            <?php echo do_shortcode("[module]"); ?>
                        </div>
                        <div id="preise" style="display: none">
                            <?php echo do_shortcode("[preis]"); ?>
                        </div>
                        <div id="voraussetzungen" style="display: none">
                            <?php echo $voraussetzungen; ?>
                        </div>
                        <div id="inhalte" style="display: none">
                            <?php echo do_shortcode("[inhalte_dyn]"); ?>
                        </div>
                        <div id="module_text" style="display: none">
                            <?php echo do_shortcode("[module_text]"); ?>
                        </div>
                        <div id="module_solo" style="display: none">
                            <?php echo do_shortcode("[module_solo]"); ?>
                        </div>
                        <div id="requirements" style="display: none">
                            <?php echo do_shortcode("[requirements]"); ?>
                        </div>
                        <div id="zielgruppe_filter" style="display: none">
                            <?php echo do_shortcode("[zielgruppe_filter_dyn]"); ?>
                        </div>



                        <input type="hidden" id="termine_pdf" value="<?php echo $termine_pdf; ?>" />
                        <input type="hidden" id="permalink" value="<?php echo the_permalink(); ?>" />
                        <input type="hidden" id="title" value="<?php echo $title; ?>" />
                        <input type="hidden" id="ajax_url" value="<?php echo admin_url('admin-ajax.php'); ?>" />
                        <input type="hidden" id="expire" value="<?php echo do_shortcode("[expire]"); ?>" />
                        <input type="hidden" id="kurstyp" value="<?php echo do_shortcode("[kurstyp]"); ?>" />
                        <input type="hidden" id="startdatum" value="<?php echo $start; ?>" />
                        <input type="hidden" id="enddatum" value="<?php echo $end; ?>" />
                        <input type="hidden" id="abschluss" value="<?php echo do_shortcode("[abschluss]"); ?>" />
                        <input type="hidden" id="current" value="<?php echo do_shortcode("[current]"); ?>" />
                        <input type="hidden" id="module_count" value="<?php echo count(get_field('module')); ?>" />

                        <input type="hidden" id="kursart_t" value="<?php echo $kursart_t; ?>" />
                        <input type="hidden" id="kursart_a" value="<?php echo $kursart_a; ?>" />
                        <input type="hidden" id="kursart_we" value="<?php echo $kursart_we; ?>" />

                        <input type="hidden" id="montag" value="<?php echo $montag; ?>" />
                        <input type="hidden" id="dienstag" value="<?php echo $dienstag; ?>" />
                        <input type="hidden" id="mittwoch" value="<?php echo $mittwoch; ?>" />
                        <input type="hidden" id="donnerstag" value="<?php echo $donnerstag; ?>" />
                        <input type="hidden" id="freitag" value="<?php echo $freitag; ?>" />
                        <input type="hidden" id="samstag" value="<?php echo $samstag; ?>" />
                        <input type="hidden" id="sonntag" value="<?php echo $sonntag; ?>" />

                        <input type="hidden" id="montag_s" value="<?php echo $montag_s; ?>" />
                        <input type="hidden" id="dienstag_s" value="<?php echo $dienstag_s; ?>" />
                        <input type="hidden" id="mittwoch_s" value="<?php echo $mittwoch_s; ?>" />
                        <input type="hidden" id="donnerstag_s" value="<?php echo $donnerstag_s; ?>" />
                        <input type="hidden" id="freitag_s" value="<?php echo $freitag_s; ?>" />
                        <input type="hidden" id="samstag_s" value="<?php echo $samstag_s ?>" />
                        <input type="hidden" id="sonntag_s" value="<?php echo $sonntag_s; ?>" />

                        <input type="hidden" id="title_preis" value="<?php echo $title_preis; ?>" />
                        <input type="hidden" id="preis" value="<?php echo $preis; ?>" />
                        <input type="hidden" id="angebot_beschreibung" value="<?php echo $angebot_beschreibung; ?>" />
                        <input type="hidden" id="anzahl_le" value="<?php echo $anzahl_le; ?>" />


                        <div class="row" style="">
                            <div class="col-md-12">
                                <div class="form-group inner-checkbox">
                                    <input type="checkbox" id="datenschutz" name="datenschutz" value="datenschutz"
                                        required="required" data-error="Bitte akzeptieren Sie die Datenschutzerklärung">
                                    <label class="wpcf7-list-item-label" for="datenschutz">Ich bin mit der Verarbeitung meiner
                                        persönlichen Daten gemäß der <a href="/datenschutz">Datenschutzerklärung</a>
                                        einverstanden*</label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <input type="submit" id="submit-form" class="wpcf7-form-control wpcf7-submit"
                                    value="Angebot anfordern">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <p class="text-muted">
                                    <strong>*</strong> warum zeigsts Diese Felder sind erforderlich
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php }
}