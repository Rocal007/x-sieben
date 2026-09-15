<?php 
function crm_form()
{
    if (!is_user_logged_in()) {
        return;
    }

    $daten = new CRM_Angebotsdaten(get_the_ID());
    ?>

    <div id="angebot-anchor" class="crm-booking-form">
        <div class="container">
            <form id="contact-form" method="post" class="form left-margin" action="" role="form" name="courses_booking">

                <h2>Nur für Admin User</h2>

                <div class="controls">
                    <!-- 🧑 Persönliche Angaben -->
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <select id="anrede" name="anrede" class="form-control" required>
                                    <option value="Anrede">Anrede</option>
                                    <option value="Herr">Herr</option>
                                    <option value="Frau">Frau</option>
                                    <option value="Firma">Firma</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <input id="angebot-vorname" type="text" name="vorname" class="form-control" placeholder="Vorname *" required>
                        </div>
                        <div class="col-md-3">
                            <input id="angebot-nachname" type="text" name="nachname" class="form-control" placeholder="Nachname *" required>
                        </div>
                    </div>

                    <!-- 📧 Kontakt -->
                    <div class="row">
                        <div class="col-md-6">
                            <input id="angebot-email" type="email" name="email" class="form-control" placeholder="E-Mail *" required>
                        </div>
                        <div class="col-md-4">
                            <input id="angebot-firma" type="text" name="firma" class="form-control" placeholder="Firma">
                        </div>
                        <div class="col-md-2">
                            <input id="angebot-firma-abteilung" type="text" name="firma-abteilung" class="form-control" placeholder="Abteilung">
                        </div>
                    </div>

                    <!-- 📍 Adresse -->
                    <div class="row">
                        <div class="col-md-6">
                            <input id="angebot-strasse" type="text" name="strasse" class="form-control" placeholder="Straße">
                        </div>
                        <div class="col-md-2">
                            <input id="angebot-nummer" type="text" name="nummer" class="form-control" placeholder="Nr">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-2">
                            <input id="angebot-plz" type="text" name="plz" class="form-control" placeholder="PLZ">
                        </div>
                        <div class="col-md-4">
                            <input id="angebot-ort" type="text" name="ort" class="form-control" placeholder="Ort">
                        </div>
                    </div>

                    <!-- ✅ Förderwunsch -->
                    <div class="row">
                        <div class="col-md-6">
                            <p>Ich möchte ein förderfähiges Angebot für:</p>
                        </div>
                        <div class="col-md-1">
                            <input type="checkbox" id="ams" name="ams" value="ams">
                            <label for="ams">AMS</label>
                        </div>
                        <div class="col-md-5">
                            <input type="checkbox" id="waff" name="waff" value="waff">
                            <label for="waff">waff, Landesförderungen,...</label>
                        </div>
                    </div>

                    <!-- 🔒 SV Nummer -->
                    <div class="row hide-it" id="svr">
                        <div class="col-md-8">Bitte geben Sie Ihre Sozialversicherungsnummer für Ihre Teilnahmebestätigung ein (optional)</div>
                        <div class="col-md-3">
                            <input class="form-control" type="text" id="svrnum" name="svrnum" placeholder="1234 010190">
                        </div>
                    </div>

                    <!-- ⏳ Reservierung -->
                    <div class="row">
                        <div class="col-md-12">
                            <input type="checkbox" id="reservierung" name="reservierung" value="reservierung">
                            <label for="reservierung">Teilnahmeplatz für 10 Tage reservieren</label>
                        </div>
                    </div>

                    <!-- 📜 Zertifizierungen -->
                    <div class="row">
                        <div class="col-md-12">
                            <?php if (have_rows('zertifizierungen')): ?>
                                <div class="form-group inner-checkbox">
                                    <?php while (have_rows('zertifizierungen')): the_row(); ?>
                                        <input type="checkbox"
                                               id="<?php the_sub_field('name-zert'); ?>"
                                               name="zertifizierungen[]"
                                               value="<?php the_sub_field('name-zert'); ?>"
                                               price="<?php the_sub_field('preis'); ?>"
                                               ust="<?php the_sub_field('Ust_satz'); ?>">
                                        <label for="<?php the_sub_field('name-zert'); ?>">
                                            <?php the_sub_field('name-zert'); ?> (zzgl. € <?php the_sub_field('preis'); ?>.-)
                                        </label><br>
                                    <?php endwhile; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 📂 Weitere Infos -->
                    <div id="zert_single" style="display: none">
                        <?php if (have_rows('zertifizierungen')):
                            while (have_rows('zertifizierungen')): the_row();
                                echo '<div style="font-size: 11pt;"><span style="color: white"> - ' . get_sub_field('name-zert') . ' zzgl.  € ' . get_sub_field('preis') . '.- (inkl. ' . get_sub_field('Ust_satz') . '% MwSt)</span></div>';
                            endwhile;
                        endif; ?>
                    </div>

                    <div id="zert_images" style="display: none"><?php echo do_shortcode("[zertifizierungen_images]"); ?></div>
                    <div id="zert_new" style="display: none"></div>
                    <div id="module" style="display: none"><?php echo do_shortcode("[module]"); ?></div>
                    <div id="preise" style="display: none"><?php echo do_shortcode("[preis]"); ?></div>
                    <div id="voraussetzungen" style="display: none"><?php echo $daten->voraussetzungen; ?></div>
                    <div id="inhalte" style="display: none"><?php echo do_shortcode("[inhalte_dyn]"); ?></div>
                    <div id="module_text" style="display: none"><?php echo do_shortcode("[module_text]"); ?></div>
                    <div id="module_solo" style="display: none"><?php echo do_shortcode("[module_solo]"); ?></div>
                    <div id="requirements" style="display: none"><?php echo do_shortcode("[requirements]"); ?></div>
                    <div id="zielgruppe_filter" style="display: none"><?php echo do_shortcode("[zielgruppe_filter_dyn]"); ?></div>

                    <!-- 🔐 Hidden Fields mit Kursinfos -->
                    <input type="hidden" id="termine_pdf" value="<?php echo $daten->termine_pdf; ?>" />
                    <input type="hidden" id="permalink" value="<?php echo get_permalink(); ?>" />
                    <input type="hidden" id="title" value="<?php echo esc_attr(get_the_title()); ?>" />
                    <input type="hidden" id="ajax_url" value="<?php echo admin_url('admin-ajax.php'); ?>" />
                    <input type="hidden" id="expire" value="<?php echo do_shortcode("[expire]"); ?>" />
                    <input type="hidden" id="kurstyp" value="<?php echo do_shortcode("[kurstyp]"); ?>" />
                    <input type="hidden" id="startdatum" value="<?php echo $daten->start_datum; ?>" />
                    <input type="hidden" id="enddatum" value="<?php echo $daten->end_datum; ?>" />
                    <input type="hidden" id="abschluss" value="<?php echo do_shortcode("[abschluss]"); ?>" />
                    <input type="hidden" id="current" value="<?php echo do_shortcode("[current]"); ?>" />
                    <input type="hidden" id="module_count" value="<?php echo count(get_field('module')); ?>" />

                    <?php foreach ($daten->kurszeiten as $tag => $zeit): ?>
                        <input type="hidden" id="<?php echo strtolower($tag); ?>" value="<?php echo $zeit; ?>" />
                    <?php endforeach; ?>

                    <?php foreach ($daten->selbststudium as $tag => $zeit): ?>
                        <input type="hidden" id="<?php echo strtolower($tag) . '_s'; ?>" value="<?php echo $zeit; ?>" />
                    <?php endforeach; ?>

                    <input type="hidden" id="kursart_t" value="<?php echo $daten->kursart_t; ?>" />
                    <input type="hidden" id="kursart_a" value="<?php echo $daten->kursart_a; ?>" />
                    <input type="hidden" id="kursart_we" value="<?php echo $daten->kursart_we; ?>" />
                    <input type="hidden" id="title_preis" value="<?php echo $daten->title_preis; ?>" />
                    <input type="hidden" id="preis" value="<?php echo $daten->preis; ?>" />
                    <input type="hidden" id="angebot_beschreibung" value="<?php echo $daten->angebot_beschreibung; ?>" />
                    <input type="hidden" id="anzahl_le" value="<?php echo $daten->anzahl_le; ?>" />

                    <!-- 🔒 Datenschutz -->
                    <div class="row">
                        <div class="col-md-12">
                            <input type="checkbox" id="datenschutz" name="datenschutz" value="datenschutz" required>
                            <label for="datenschutz">Ich bin mit der Verarbeitung meiner persönlichen Daten gemäß der <a href="/datenschutz">Datenschutzerklärung</a> einverstanden*</label>
                        </div>
                    </div>

                    <!-- 🚀 Abschicken -->
                    <div class="row">
                        <div class="col-md-6">
                            <input type="submit" id="submit-form" class="wpcf7-form-control wpcf7-submit" value="Angebot anfordern">
                        </div>
                    </div>

                    <!-- 📎 Hinweis -->
                    <div class="row">
                        <div class="col-md-12">
                            <p class="text-muted"><strong>*</strong> Diese Felder sind erforderlich</p>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php
}
