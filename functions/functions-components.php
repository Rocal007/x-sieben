<?php
/**
 * @author Blackbam
 *
 * - This file is for managing all general SETTINGS as well as the settings for post types and taxonomies.
 * - This file is for managing all the DYNAMIC COMPONENTS of WordPress such as Dynamic Sidebars, Menus and the creation of Post types / taxonomies.
 */
/**
 * Option definition
 *
 * Each Array has:
 *
 * 0 - ID:              The unique ID of the setting.
 * 1 - Element Type:
 *      - _heading_ :   A Heading (not a real field)
 *      - _callback_:   A Callback for a completely custom output (not a real field)
 *      - text :        A Text field.
 *      - textarea:     A Textarea
 *      - editor:       A visual editor
 *      - checkbox:     A checkbox
 *      - custom_html:  A Custom HTML field
 *      - post_select:  An ajaxified selector for posts
 *      - select:       A HTML Selector
 *      - image:        An image selector field
 *      - video:        A selector for custom
 *      - repeater:     A repeater component
 * 2 - Label:           The Label for the field
 * 3 - Description:     Optional. A Description for the field.
 * 4 - Input Type:      Optional.
 *                      text: One of these: https://www.w3.org/wiki/HTML/Elements/input (e.g. url, email, date, color)
 *                      select: "multiple" or false
 * 5 - Options:         Optional. Depends on type:
 *                      type select: The input array
 *                      custom_html: The HTML as string
 *                      post_select: The (optional) post type.
 *                      repeater: An entire array of fields exactly like described here
 * 6 - Il8n             If multilanguage is active, repeat this field for every language. Default is to false.
 * 7 - Attributes:      An array of attributes for the main field. Does not work for special element types.
 *                      (Example: array("class"=>"someclass","placeholder"=>"lol","max"=>"10").
 * 8 - Validator CB:    A callback function or comma seperated functions to be mapped on the input.
 * 9 - Sanitizer CB:    A callback function or comma seperated functions to be mapped on the input.
 */
/************ GENERAL OPTIONS *************/
// Define the base theme settings
function get_cis_settings() {
    return [
        ["meta_blogcatalog","text","blogcatalog - Meta Value"],
        ["meta_alexaverify","text","alexaVerifyID - Meta Value"],
        ["meta_googlesiteverification","text","google-site-verification - Meta Value"],
    ];
}
/************** Post/Page/Post-Type Options *****************/
add_action('admin_init', 'cis_register_post_type_options');
function cis_register_post_type_options() {
    $cfbox_post_types = ['post','courses','ca']; // insert post types here
    foreach ($cfbox_post_types as $cfpt) {
        add_meta_box("cis_post_settings", __("Theme Post Settings", CIS_TEXTDOMAIN), "cis_post_settings", $cfpt, "normal", "high");
    }
}
function get_cis_post_settings($post_type) {
    /**
     * As with settings
     */
    if ($post_type == "courses") {
        return [
            ["","_heading_","Basis & Format"],
            ["durchfuehrungsmodus","select","Durchführungsmodus","",false,[""=>"--- Bitte wählen ---","praesenz"=>"Präsenzseminar","online"=>"Live-Online (Fernlehre)","blended"=>"Blended Learning (Hybrid)","elearning"=>"E-Learning (Selbststudium)","inhouse"=>"Inhouse-Training"]],
            ["tages_abend_wochenende_","select","Kursart / Zeitmodell","",false,["Tageskurs"=>"Tageskurs","Abendkurs"=>"Abendkurs","Wochenendkurs"=>"Wochenendkurs","Individuell"=>"Individuelle Kurszeiten"]],
            ["plaetze_ampel","select","Startseite Ampel-Widget","",false,[""=>"--- NICHT ANZEIGEN ---","frei"=>'Plätze verfügbar',"knapp"=>"Fast ausgebucht","ausgebucht"=>"Ausgebucht"]],
            ["plaetze_kurspage","select","Kursseite Ampel-Widget","",false,[""=>"--- NICHT ANZEIGEN ---","frei"=>'Plätze verfügbar',"knapp"=>"Fast ausgebucht","ausgebucht"=>"Ausgebucht"]],
            ["startgarantie","checkbox","Startgarantie (Garantierte Durchführung)"],
            ["teilnehmer_min","text","Mindest-Teilnehmeranzahl","","number"],
            ["seminarplatze","text","Maximale Seminarplätze","","number"],
            ["unterrichtssprache","text","Unterrichtssprache","z. B. Deutsch, Englisch"],
            ["veranstalter","text","Veranstalter","Default: X SIEBEN Wirtschaftstraining GmbH"],
            ["vortragende","select","Veranstaltungsleiter / Referenten","","multiple",get_selector_collection_for_post_type('members')],
            ["zertifikate","select","Zertifikatstellen / CA-Partner","","multiple",get_selector_collection_for_post_type('ca')],

            ["","_heading_","Inhalte & Didaktik"],
            ["untertitel","textarea","Untertitel / Slogan für den Kurs"],
            ["course_quote","textarea","Zitat / Leitgedanke für Seitenanfang"],
            ["angebot_beschreibung","textarea","Angebots-Einleitungstext / Kundennutzen (CRM-Vorlage)"],
            ["ziele","editor","Lernziele & Kompetenzerwerb (Learning Outcomes)"],
            ["zielgruppe","editor","Zielgruppe & Teilnehmerprofil"],
            ["methodik","editor","Methodik / Didaktik / Vorgehensweise"],
            ["voraussetzungen","textarea","Zugangsvoraussetzungen (formal & inhaltlich)"],

            ["","_heading_","Zeit, Dauer & LE-Splitting"],
            ["start_datum","text","Startdatum","","date"],
            ["end_datum","text","Enddatum","","date"],
            ["einstieg_datum","text","Möglicher Einstieg / Rollierender Beginn","","date"],
            ["uhrzeit","text","Uhrzeit: Beginn","","time"],
            ["uhrzeit_ende","text","Uhrzeit: Ende","","time"],
            ["dauer","text","Seminardauer (z. B. '4 Wochen / 142 LE')"],
            ["lehreinheiten_gesamt","text","Gesamte Lehreinheiten (LE)","Gesamtanzahl aller UE/LE","number"],
            ["le_praesenz","text","davon LE Präsenz / Live-Online","Synchrone Unterrichtseinheiten","number"],
            ["le_selbststudium","text","davon LE Selbststudium / E-Learning","Asynchrone Lerneinheiten","number"],
            ["le_projekt_transfer","text","davon LE Projektarbeit / Praxistransfer","Projekt-, Fallstudien- & Prüfungs-LE","number"],
            ["anzahl_termine","text","Anzahl der Termine","Anzahl der Termine für fortlaufende Seminare","number"],
            ["anmeldeschluss","text","Anmeldeschluss","","date"],
            ["kurszeiten_details_pdf","text","PDF Kurszeiten-Details / Terminplan"],
            ["zeit_zusatz","textarea","Anmerkungen zu Zeit und Ort"],

            ["","_heading_","Kosten, USt & Gebühren"],
            ["kosten","text","Kursgebühr Netto in EUR","Netto-Betrag ohne USt","number","",false,["min"=>"0","step"=>"0.01"]],
            ["ust_satz","select","USt-Satz","",false,["20"=>"20% Normalsteuersatz (Österreich)","0"=>"0% Steuerfrei gem. § 6 Abs 1 Z 11 UStG"]],
            ["kosten_text","textarea","Angezeigte Kosten (z. B. '€ 2.450,- zzgl. 20% USt')"],
            ["kosten_pruefung","text","Prüfungsgebühr in EUR (falls separat)","","number","",false,["min"=>"0","step"=>"0.01"]],
            ["kosten_unterlagen_inkl","checkbox","Lernunterlagen & Skripten inkludiert"],
            ["foerdermoeglichkeit","text","Fördermöglichkeiten (z. B. AMS, WAFF, Bildungskonto)"],
            ["ermaessigungen","text","Ermäßigungen / Rabatte / Ratenzahlung"],

            ["","_heading_","Förderstellen & Akkreditierung"],
            ["api_ams_publish","checkbox","AMS-Schnittstelle aktiv (für ams.csv Export)"],
            ["seminarnummer","text","AMS Seminarnummer / Maßnahmennummer"],
            ["api_waff_publish","checkbox","WAFF-Schnittstelle aktiv (für waff.csv Export)"],
            ["waff_themencode","text","WAFF Themen-Code","Der Themen-Code aus der WAFF Themencode Liste"],
            ["waff_number","text","WAFF Kursnummer"],
            ["bildungskarenz_geeignet","checkbox","Für Bildungskarenz / Bildungsteilzeit geeignet (16-20h/Woche)"],
            ["wba_punkte","text","wba-Akkreditierung (Punkte / ECTS)"],
            ["isced_kategorie","text","ISCED-F Kategorie (optional)"],
            ["nqr_kategorie","text","NQR-Kategorie (z. B. NQR 4 / NQR 5 / NQR 6)"],
            ["kinderbetreuung","checkbox","Kinderbetreuung möglich?"],
            ["spezielles_ubungsangebot","text","Spezielles Übungsangebot (optional)"],

            ["","_heading_","Abschluss & Diplom"],
            ["abschluss_typ","select","Abschlussart","",false,["diplom"=>"X-SIEBEN Diplom","zertifikat"=>"Personenzertifikat (ISO 17024 / IPMA / TÜV)","teilnahmebestaetigung"=>"Teilnahmebestätigung","zeugnis"=>"Zeugnis / Zertifikat"]],
            ["zertifikat","text","Offizielle Abschluss- / Diplombezeichnung"],
            ["mindestanwesenheit_prozent","text","Mindestanwesenheit in %","Default: 75%","number"],
            ["pruefungsmodus","select","Prüfungsmodus","",false,["ohne"=>"Keine Prüfung (reine Teilnahmebestätigung)","schriftlich"=>"Schriftliche Prüfung / Multiple-Choice","muendlich"=>"Mündliches Fachgespräch / Hearing","projektarbeit"=>"Projekt- / Diplomarbeit","kombiniert"=>"Schriftlich + Mündlich + Projektarbeit"]],
            ["diplom_text_links","textarea","Diplom Zusatztext Links (z. B. Themenschwerpunkte)"],
            ["diplom_text_rechts","textarea","Diplom Zusatztext Rechts (z. B. Prüfungskommission)"],

            ["","_heading_","Durchführungsort & Technik"],
            ["strasse","text","Straße & Hausnummer"],
            ["addresszusatz","text","Adresszusatz (z. B. Stiege / Top / Raum)"],
            ["plz","text","Postleitzahl","","number"],
            ["ort","text","Ort"],
            ["bundesland","select","Bundesland","",false,["","Wien", "Burgenland", "Kärnten","Niederösterreich","Oberösterreich","Salzburg","Steiermark","Tirol","Vorarlberg","Online / Virtuell"]],
            ["ort_infos","text","Veranstaltungsort: Zusätzliche Rauminfos / Anfahrt"],
            ["online_plattform","text","Online-Meeting Plattform (z. B. Zoom, MS Teams, Miro)"],
            ["barrierefreier_zugang","checkbox","Barrierefreier Zugang gewährleistet"],

            ["","_heading_","Kontaktperson"],
            ["kontakt_vorname","text","Vorname"],
            ["kontakt_nachname","text","Nachname"],
            ["kontakt_titel","text","Titel vorangestellt (z. B. Mag., Dr.)"],
            ["kontakt_titel_nachgestellt","text","Titel nachgestellt (z. B. MA, MSc, MBA)"],
            ["kontakt_funktion","text","Funktion (z. B. Lehrgangsleitung)"],
            ["kontakt_email","text","E-Mail","","email"],
            ["kontakt_telefon","text","Telefonnummer"],

            ["","_heading_","Additional Settings"],
            ["header_meta_tags","textarea","Arbitrary header meta tags (SEO) (HTML)","Add arbitrary meta tags which might be important for the SEO of this concrete site."],
            ["paypal_button_directpay","text","Paypal Button ID für direkte Bezahlung"],
            ["anmeldelink","text","Direkter Anmeldelink / Online-Shop URL"],
            ["__related_title__","_heading_","Similar Posts"],
            ["related_posts_title","text","Überschrift für ähnliche Beiträge",""],
            ["related1","post_select","Related 1","","","courses"],
            ["related2","post_select","Related 2","","","courses"],
            ["related3","post_select","Related 3","","","courses"],
            ["related4","post_select","Related 4","","","courses"],
            ["related5","post_select","Related 5","","","courses"],
            ["related6","post_select","Related 6","","","courses"],
            ["related7","post_select","Related 7","","","courses"],
            ["related8","post_select","Related 8","","","courses"],
            ["related9","post_select","Related 9","","","courses"],
            ["related10","post_select","Related 10","","","courses"],
        ];
    } else if ($post_type == "page") {

    } else if($post_type == "ca") {
        return [
            ["url","text","URL zur Website der CA","","url"]
        ];
    }
    return [
    ];
}
/***************** SIDEBARS *****************/
/* Regester the default Widgets */
if (function_exists('register_sidebar')) {
    /* Content-top dynamic areas */
    register_sidebar([
        'name' => __('Courses Sidebar', CIS_TEXTDOMAIN),
        'id' => 'courses_sidebar',
        'before_widget' => '<div class="cis_widget">',
        'after_widget' => '</div><!-- uss_widget div -->',
        'before_title' => '<div class="cis_widget_title">',
        'after_title' => '</div>',
    ]);
}
/*************** MENUS *****************/
// add_action( 'init', 'my_custom_menus' );
// call with: wp_nav_menu(array('theme_location'=>'mainmenu','menu'=>'mainmenu'));
function my_custom_menus() {
    register_nav_menus(
        [
            'Main Menu' => __('Main Menu', CIS_TEXTDOMAIN)
        ]
    );
}
/************* Post Types and Taxonomys **************/
// add_action('init','cis_register_typetax');
/************* Custom Permalinks *********************/
/************** Permalink Rewrite *********************/
// Add custom rewrite rules to handle things like years in custom post archives
function add_rewrite_rules($aRules) {
    $aNewRules = array(
        'calendar-dates/([0-9]{4})/month/([0-9]{2})/?$' => 'index.php?post_type=date&year=$matches[1]&month=$matches[2]',
        'calendar-dates/([0-9]{4})/?$' => 'index.php?post_type=date&year=$matches[1]]'
    );
    $aRules = $aNewRules + $aRules;
    return $aRules;
}
// hook add_rewrite_rules function into rewrite_rules_array
// add_filter('rewrite_rules_array', 'add_rewrite_rules');
/********************** Add (sortable) Columns to the overview **********************/
// add_filter('manage_edit-{post_type}_columns', 'cis_add_new_{post_type}_columns');
function uss_add_new_item_columns($product_columns) {
    $product_columns['slug'] = __('Label');
    return $product_columns;
}
// add_filter('manage_edit-{post_type}_sortable_columns', 'cis_add_new_{post_type}_sortable_columns');
function cis_add_new_item_sortable_columns($product_columns) {
    $product_columns['slug'] = 'slug';
    return $product_columns;
}
// add_action('manage_product_{post_type}_custom_column', 'cis_manage_{post_type}_columns', 10, 2);
function cis_manage_item_columns($column_name, $post_id) {
    switch ($column_name) {
        case 'custom_column':
            // echo custom value here
            break;
        default:
            break;
    } // end switch
}
/********************* CUSTOM TERM FIELDS ****************************/
add_action('admin_init', 'cis_register_term_options');
function cis_register_term_options() {
    $add_to_terms = ['category']; // insert terms here
    foreach ($add_to_terms as $term) {
        add_action($term . '_add_form_fields', 'cis_terms_additional_fields_add', 10, 2);
        add_action($term . '_edit_form_fields', 'cis_terms_additional_fields', 10, 2);
        add_action('created_' . $term, 'save_cis_term_settings', 10, 2);
        add_action('edited_' . $term, 'save_cis_term_settings', 10, 2);
    }
}
function get_cis_term_settings($taxonomy) {
    /**
     * As with settings and post types ...
     */
    if ($taxonomy == "category") {
        return [
        ];
    }
    return [];
}
/*************************** Custom user fields ****************************/
add_action('show_user_profile', 'add_cis_user_fields');
add_action('edit_user_profile', 'add_cis_user_fields');
add_action('personal_options_update', 'save_cis_user_fields');
add_action('edit_user_profile_update', 'save_cis_user_fields');
function get_cis_user_settings() {
    /**
     * As with settings and post types ...
     */
    return [
    ];
}




/************ CMB2 HELPER **********/
add_action( 'cmb2_admin_init', 'cmb2_sample_metaboxes', 5 );
/**
 * Define the metabox and field configurations.
 */
function cmb2_sample_metaboxes() {

    // Start with an underscore to hide fields from custom fields list
    $prefix = '_coursescmb2_';

    /**
     * Initiate the metabox
     */
    $cmb = new_cmb2_box( array(
        'id'            => 'courses_accordion_box',
        'title'         => __( 'Kurs Accordion Einstellungen', 'cmb2' ),
        'object_types'  => array( 'courses', ), // Post type
        'context'       => 'normal',
        'priority'      => 'high',
        'show_names'    => true, // Show field names on the left
        // 'cmb_styles' => false, // false to disable the CMB stylesheet
        // 'closed'     => true, // Keep the metabox closed by default
    ) );

    $group_field_id = $cmb->add_field( array(
        'id'          => 'courses_accordion',
        'type'        => 'group',
        'description' => __( 'Hier wird das Kurs Accordion festgelegt', 'cmb2' ),
        // 'repeatable'  => false, // use false if you want non-repeatable group
        'options'     => array(
            'group_title'   => __( 'Entry {#}', 'cmb2' ), // since version 1.1.4, {#} gets replaced by row number
            'add_button'    => __( 'Add Another Entry', 'cmb2' ),
            'remove_button' => __( 'Remove Entry', 'cmb2' ),
            'sortable'      => true, // beta
            // 'closed'     => true, // true to have the groups closed by default
        ),
    ) );

// Id's for group's fields only need to be unique for the group. Prefix is not needed.
    $cmb->add_group_field( $group_field_id, array(
        'name' => 'Tab Titel',
        'id'   => 'title',
        'type' => 'text'
        // 'repeatable' => true, // Repeatable fields are supported w/in repeatable groups (for most types)
    ) );

    $cmb->add_group_field( $group_field_id, array(
        'name' => 'Tab am Anfang öffnen?',
        'desc' => 'Soll dieser Tab am Anfang geöffnet sein?',
        'id'   => 'opentab',
        'type' => 'checkbox',
    ) );

    $cmb->add_group_field($group_field_id,  array(
        'name'             => 'Vordefinierter Inhalt',
        'desc'             => 'Einen vordefinierten Inhalt anzeigen',
        'id'               => 'predefined',
        'type'             => 'select',
        'show_option_none' => true,
        'default'          => 'custom',
        'options'          => array(
            'ziele'        => 'Ziele',
            'zielgruppe'   => "Zielgruppe",
            'methodik'     => "Methodik / Didaktik / Vorgehensweise",
            'info'         => "Weitere Informationen anfordern",
            'buchung'      => "Buchung / Anmeldung zum Kurs"
        ),
    ) );

    $cmb->add_group_field( $group_field_id, array(
        'name' => "Tab Content",
        'id'   => 'content',
        'type' => 'wysiwyg',
        'sanitization_cb' => false
        // 'repeatable' => true, // Repeatable fields are supported w/in repeatable groups (for most types)
    ) );

    // Top Picture Layout Box
    $cmb_top_picture = new_cmb2_box( array(
        'id'            => 'top_picture_layout_box',
        'title'         => __( 'Top Picture Layout Einstellungen', 'cmb2' ),
        'object_types'  => array( 'page', 'courses' ), // Post types
        'context'       => 'normal',
        'priority'      => 'high',
        'show_names'    => true,
    ) );

    $cmb_top_picture->add_field( array(
        'name'             => 'Layout Typ',
        'desc'             => 'Wählen Sie das Layout für das obere Bild (Top Picture)',
        'id'               => 'top_picture_layout',
        'type'             => 'select',
        'default'          => 'split_screen',
        'options'          => array(
            'split_screen' => 'Split Screen (Bild rechts, Text links)',
            'full_width'   => 'Full Width (Bild als Hintergrund, Text mittig)',
        ),
    ) );

	
	
function format_my_number($atts) {
$num = $atts["value"];return number_format($num, 0, '.', ',');
}
add_shortcode("custom-number-format", "format_my_number");	
	
}

function xsieben_setup_adds() {
  add_theme_support('custom-logo', array(
    'height'      => 100,
    'width'       => 400,
    'flex-height' => true,
    'flex-width'  => true,
  ));
}
add_action('after_setup_theme', 'xsieben_setup_adds');