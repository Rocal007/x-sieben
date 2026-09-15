<?php
function crm_form($course_id = null, $entry_id = null)
{
?>
  <div id="crm-form">
    <?php
    if (is_user_logged_in()) {
      // Post-Meta und ACF-Felder laden
      $kursart = get_post_meta($course_id, 'tages_abend_wochenende_', true);
      $kursart = is_array($kursart) ? $kursart[0] : $kursart;

      $voraussetzungen = get_post_meta($course_id, 'voraussetzungen_abschluss', true);

      $kursart_t = $kursart == "Tageskurs" ? "<strong>X</strong>" : "";
      $kursart_a = $kursart == "Abendkurs" ? "<strong>X</strong>" : "";
      $kursart_we = $kursart == "Wochenendkurs" ? "<strong>X</strong>" : "";

      $kurszeiten = get_field("kurszeiten", $course_id) ?: [];
      $selbststudium = get_field("selbstudium", $course_id) ?: [];

      $tage = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

      // Kurszeiten und Selbststudium Zeiten vorbereiten
      $zeit_format = "09.00 - 17.00 Uhr";
      $zeiten = [];
      $zeiten_s = [];
      foreach ($tage as $tag) {
        $zeiten[$tag] = in_array($tag, $kurszeiten) ? $zeit_format : "";
        $zeiten_s[$tag] = in_array($tag, $selbststudium) ? $zeit_format : "";
      }

      $title_preis = get_the_title($course_id);
      $preis = get_post_meta($course_id, 'kosten', true);
      $angebot_beschreibung = get_post_meta($course_id, 'angebot_beschreibung', true);
      $anzahl_le = get_post_meta($course_id, 'lehreinheiten_gesamt', true);
      $termine_pdf = get_field('kurszeiten_details_pdf', $course_id);
      $title = esc_html(get_the_title($course_id));

      // WPForms Daten falls $entry_id gesetzt ist
      $wpform_data = [];
      if ($entry_id) {
        // Beispiel: hole WPForms-Eintragsdaten (funktioniert nur, wenn WPForms installiert)
        if (function_exists('wpforms()->entry')) {
          $entry = wpforms()->entry->get($entry_id);
          if ($entry) {
            $wpform_data = $entry->fields; // Array mit Feld-Daten
          }
        }
      }

      // Optional: Beispiel für Vorname aus WPForms (falls gesetzt)
      $vorname = $wpform_data['vorname'] ?? '';
      $nachname = $wpform_data['nachname'] ?? '';
      $email = $wpform_data['email'] ?? '';
      $firma = $wpform_data['firma'] ?? '';
      $firma_abteilung = $wpform_data['firma-abteilung'] ?? '';
      $strasse = $wpform_data['strasse'] ?? '';
      $nummer = $wpform_data['nummer'] ?? '';
      $plz = $wpform_data['plz'] ?? '';
      $ort = $wpform_data['ort'] ?? '';

      $email = '';
      if ($entry_id && function_exists('wpforms()->entry')) {
        $entry = wpforms()->entry->get($entry_id);
        if ($entry && isset($entry->fields)) {
          // WPForms speichert Felder in $entry->fields als array mit Feld-ID als Key
          // Beispiel: E-Mail Feld hat z.B. die Feld-ID 4 (muss geprüft werden)
          // Wir suchen im Array nach dem E-Mail-Feld (Text oder Email Feld)
          foreach ($entry->fields as $field) {
            if (isset($field['type']) && $field['type'] === 'email') {
              $email = $field['value'];
              break;
            }
            // Falls der Typ nicht gesetzt ist, ggf. noch per Feld-Label prüfen:
            // if (isset($field['name']) && stripos($field['name'], 'email') !== false) { ... }
          }
        }
      }


      // Hier kannst du beliebig weitere Felder aus WPForms holen und als Default in Inputs setzen

    ?>
      <form id="contact-form" method="post" class="form left-margin" action="" role="form" name="courses_booking">

        <div class="messages"></div>
        <div class="controls">

          <div class="row">
            <div class="col-md-2">
              <div class="form-group">
                <select id="anrede" name="anrede" class="form-control" required="required" placeholder="Anrede"
                  data-error="Bitte geben Sie Ihre Anrede ein">
                  <option value="">Anrede</option>
                  <option value="Herr" <?php if (($wpform_data['anrede'] ?? '') === 'Herr') echo 'selected'; ?>>Herr</option>
                  <option value="Frau" <?php if (($wpform_data['anrede'] ?? '') === 'Frau') echo 'selected'; ?>>Frau</option>
                  <option value="Firma" <?php if (($wpform_data['anrede'] ?? '') === 'Firma') echo 'selected'; ?>>Firma</option>
                </select>
                <div class="help-block with-errors"></div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <input id="angebot-vorname" type="text" name="vorname" class="form-control"
                  placeholder="Vorname *" required="required" data-error="bitte geben Sie Ihren Vornamen ein"
                  value="<?php echo esc_attr($vorname); ?>">
                <div class="help-block with-errors"></div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <input id="angebot-nachname" type="text" name="nachname" class="form-control"
                  placeholder="Nachname *" required="required" data-error="bitte geben Sie Ihren Nachnamen ein"
                  value="<?php echo esc_attr($nachname); ?>">
                <div class="help-block with-errors"></div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="email">E-Mail *</label>
                <input id="email" type="email" name="email" value="<?php echo esc_attr($email); ?>" required>

                <div class="help-block with-errors"></div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <input id="angebot-firma" type="text" name="firma" class="form-control" placeholder="Firma"
                  value="<?php echo esc_attr($firma); ?>">
                <div class="help-block with-errors"></div>
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <input id="angebot-firma-abteilung" type="text" name="firma-abteilung" class="form-control"
                  placeholder="Abteilung" value="<?php echo esc_attr($firma_abteilung); ?>">
                <div class="help-block with-errors"></div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <input id="angebot-strasse" type="text" name="strasse" class="form-control" placeholder="Straße"
                  value="<?php echo esc_attr($strasse); ?>">
                <div class="help-block with-errors"></div>
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <input id="angebot-nummer" type="text" name="nummer" class="form-control" placeholder="Nr"
                  value="<?php echo esc_attr($nummer); ?>">
                <div class="help-block with-errors"></div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-2">
              <div class="form-group">
                <input id="angebot-plz" type="text" name="plz" class="form-control" placeholder="PLZ"
                  value="<?php echo esc_attr($plz); ?>">
                <div class="help-block with-errors"></div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <input id="angebot-ort" type="text" name="ort" class="form-control" placeholder="Ort"
                  value="<?php echo esc_attr($ort); ?>">
                <div class="help-block with-errors"></div>
              </div>
            </div>
          </div>

          <!-- Restlicher Formular-HTML bleibt gleich -->

          <!-- Checkboxen, Zertifizierungen etc. -->

          <?php if (have_rows('zertifizierungen', $course_id)): ?>
            <div class="row">
              <div class="col-md-12">
                <div class="form-group inner-checkbox">
                  <?php while (have_rows('zertifizierungen', $course_id)) : the_row();
                    $zert_name = get_sub_field('name-zert');
                    $zert_preis = get_sub_field('preis');
                    $zert_ust = get_sub_field('Ust_satz');
                  ?>
                    <input type="checkbox" id="<?php echo esc_attr($zert_name); ?>" name="zertifizierungen[]"
                      value="<?php echo esc_attr($zert_name); ?>" price="<?php echo esc_attr($zert_preis); ?>" ust="<?php echo esc_attr($zert_ust); ?>">
                    <label class="wpcf7-list-item-label" for="<?php echo esc_attr($zert_name); ?>"> <?php echo esc_html($zert_name); ?> (zzgl. € <?php echo esc_html($zert_preis); ?>.-)</label><br />
                  <?php endwhile; ?>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <!-- Hidden Inputs -->
          <input type="hidden" name="course_id" value="<?php echo esc_attr($course_id); ?>" />
          <?php if ($entry_id): ?>
            <input type="hidden" name="entry_id" value="<?php echo esc_attr($entry_id); ?>" />
          <?php endif; ?>

        </div> <!-- .controls -->

      </form>

      <!-- Die zusätzlichen hidden divs mit den Shortcodes -->
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
        <?php echo isset($voraussetzungen) ? $voraussetzungen : ''; ?>
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

    <?php
    } else {
      echo '<p>Bitte melden Sie sich an, um das Formular auszufüllen.</p>';
    }
    ?>
  </div> <!-- Ende #crm-form -->
<?php
}
