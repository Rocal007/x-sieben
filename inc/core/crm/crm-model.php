<?php

require_once __DIR__ . '/helpers/normalize.php';
require_once __DIR__ . '/helpers/crm-pdf-presenter.php';

class CRM_Model
{
    public $post_id, $title, $titel_short, $permalink, $start_datum, $end_datum, $preis_netto, $preis_brutto, $title_preis, $kurstyp, $abschluss;
    public $angebot_beschreibung, $anzahl_le, $le_single, $kursart, $kursart_t = '', $kursart_a = '', $kursart_we = '';
    public $voraussetzungen = [], $kurszeiten = [], $module_html = '', $module_gliederung_html = '', $zeiteinteilung_html = '', $selbststudium = [], $termine_pdf, $zertifizierungen = [], $zertifizierungen_images = [];
    public $address_components = [], $nummer, $kurszeiten_datum, $pdfAuthor;
    public $ams_img, $web_icon, $mail_icon, $fax_icon, $phone_icon;
    public $calender_icon, $ort_icon, $abschluss_icon, $diplom_icon, $proven_icon;
    public $sitting_icon, $danger_icon, $signatur_icon, $proven_wide;
    public $wba_logo, $cert_noe_logo, $tuef_logo, $sys_zert_logo, $pma_logo, $ipma_logo, $xsieben_logo;
    public $email_logos, $inhalte, $zielgruppe, $anmeldung_agb, $trainer;
    public $current, $expire;
    public $zertifizierungen_images_html, $ps, $garantie;
    public $zertifizierungen_loop_html;
    public $signatur;
    public $entry_data;
    public $voraussetzungen_html;
    public $anrede;
    public $salutation;
    public $titel;
    public $vorname;
    public $nachname;
    public $email;
    public $svr;
    public $customer_company;
    public $customer_type;

    // Eigenschaften für getrennte Adressfelder
    public $street;
    public $house_number;
    public $city;
    public $zip_code;
    public $country;
    public $form_certifications;
    public $agb_claim;
    public $bankverbindung;
    public $texte_fur_diplom_links;
    public $texte_fur_diplom_rechts;
    public $diplom_success;
    public $termine_link;
    public $email_footer;
    public $kursgebuehr_html;
    public $beratung_email;
    public $buchung_email;
    public $signatur_email;
    public $teilnahmebestaetigung_email;
    public $teilnahmebestaetigung_f_email;
    public $angebot_email;
    public $anmeldebestaetigung_email;
    public $anmeldung_email;
    public $diplom_email;

    // Erweiterte Kurs- und Förderdaten
    public $le_praesenz = 0;
    public $le_selbststudium = 0;
    public $le_projekt_transfer = 0;
    public $durchfuehrungsmodus = '';
    public $startgarantie = 0;
    public $ust_satz = 20.0;
    public $kosten_pruefung = 0.0;
    public $kosten_unterlagen_inkl = 0;
    public $seminarnummer = '';
    public $waff_themencode = '';
    public $bildungskarenz_geeignet = 0;
    public $wba_punkte = '';
    public $abschluss_typ = '';
    public $mindestanwesenheit_prozent = 75;
    public $pruefungsmodus = '';
    public $online_plattform = '';
    public $durchfuehrung = '';

    // Standardisierte Platzhalter & Aliase für E-Mails und PDFs
    public $kurstitel;
    public $kurstitel_short;
    public $startdatum;
    public $enddatum;
    public $uhrzeit;
    public $le;
    public $schulungsort;
    public $ort;
    public $preis;
    public $gesamtpreis;
    public $kunden_firma;
    public $telefon;
    public $datum;

    // Demographie, Firmendaten & CI-Stammdaten
    public $company_name;
    public $company_short_name;
    public $company_legal_form;
    public $company_management;
    public $company_street;
    public $company_zip;
    public $company_city;
    public $company_country;
    public $company_address;
    public $location_wien;
    public $location_wien_name;
    public $location_wien_street;
    public $location_wien_zip;
    public $location_wien_city;
    public $location_wien_notice;
    public $company_phone;
    public $company_email;
    public $company_website;
    public $backoffice_name;
    public $backoffice_email;
    public $backoffice_phone;
    public $company_uid;
    public $company_fn;
    public $company_court;
    public $company_chamber;
    public $company_bank;
    public $company_slogan;
    public $company_accreditations;
    public $agb_url;
    public $privacy_url;
    public $imprint_url;
    public $ci_primary_color;
    public $ci_secondary_color;
    public $ci_accent_color;
    public $company_logo;
    public $company_logo_url;
    public $company_logo_secondary_url;

    /**
     * Konstruktor der Klasse.
     * @param int $post_id Die ID des Beitrags.
     * @param int|null $entry_id The ID of the WPForms entry (optional).
     */
    public function __construct($post_id = 0, $entry_id = null)
    {
        $this->post_id = absint($post_id);
        $entry_id      = !empty($entry_id) ? absint($entry_id) : null;

        // Fallback: Falls keine gültige Kurs-ID oder Page-ID 47 (Kontakt) übergeben wurde: Aus Anfrage-Nachricht ermitteln
        if (($this->post_id <= 0 || $this->post_id == 47 || get_post_type($this->post_id) !== 'courses') && !empty($entry_id)) {
            if (function_exists('crm_detect_course_from_message')) {
                $det = crm_detect_course_from_message('', [], $entry_id);
                if ($det && !empty($det['course_id'])) {
                    $this->post_id = (int)$det['course_id'];
                }
            }
        }

        // Letzter Fallback: ersten publizierten Kurs als Muster wählen falls weiterhin 0
        if ($this->post_id <= 0 || get_post_type($this->post_id) !== 'courses') {
            $fallback_courses = get_posts([
                'post_type'      => 'courses',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
            ]);
            if (!empty($fallback_courses)) {
                $this->post_id = $fallback_courses[0]->ID;
            }
        }
        $post_id = $this->post_id;

        // WP-Forms Entry
        $this->entry_data = $this->get_wpforms_entry_data($entry_id);

        // NEU: Adress- und Namensdaten aus WPForms-Daten aufteilen
        $this->set_address_components();
        $this->set_personal_data();


        // Basic Fields
        $raw_title = get_the_title($post_id);
        $this->title = trim(wp_strip_all_tags(html_entity_decode(html_entity_decode($raw_title, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8')));
        $raw_short = get_field('title_im_slider', $post_id);
        $this->titel_short = !empty($raw_short) ? trim(wp_strip_all_tags(html_entity_decode(html_entity_decode($raw_short, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8'))) : '';
        $this->permalink = get_permalink($post_id);
        // Persistent snapshot dates: Use frozen inquiry dates if available, otherwise fallback to course post meta
        $snapshot_dates = ($entry_id && function_exists('crm_get_entry_course_dates')) ? crm_get_entry_course_dates($entry_id) : null;
        if (!empty($snapshot_dates['start_date'])) {
            $this->start_datum = date('d.m.Y', strtotime($snapshot_dates['start_date']));
        } else {
            $this->start_datum = $this->format_date_meta('start_datum');
        }

        if (!empty($snapshot_dates['end_date'])) {
            $this->end_datum = date('d.m.Y', strtotime($snapshot_dates['end_date']));
        } else {
            $this->end_datum = $this->format_date_meta('end_datum');
        }
        $this->ende_datum = $this->end_datum;
        $this->enddatum = $this->end_datum;
        $this->startdatum = $this->start_datum;
        $raw_kosten = get_post_meta($post_id, 'kosten', true);
        $netto_kurs_float = CRM_Pdf_Presenter::parse_price_float($raw_kosten);
        $this->preis_netto = number_format($netto_kurs_float, 2, ',', '');
        $this->preis_brutto = number_format(round($netto_kurs_float * 1.20, 2), 2, '.', '');
        $this->title_preis = $this->title;
        $this->angebot_beschreibung = get_post_meta($post_id, 'angebot_beschreibung', true);

        // Unterstützung für freie Geschäftsanfragen
        $entry_status = ($entry_id && function_exists('crm_get_entry_status')) ? crm_get_entry_status($entry_id) : null;
        if (!empty($entry_status['inquiry_type']) && $entry_status['inquiry_type'] === 'freie_anfrage') {
            if (!empty($entry_status['custom_title'])) {
                $this->title = $entry_status['custom_title'];
                $this->title_preis = $entry_status['custom_title'];
                $this->titel_short = $entry_status['custom_title'];
            }
            $this->kurstyp = __('Inhouse / Freie Geschäftsanfrage', 'custom-crm');
            if (!empty($entry_status['course_start_date'])) {
                $this->start_datum = $entry_status['course_start_date'];
            }
            if (!empty($entry_status['course_end_date'])) {
                $this->end_datum = $entry_status['course_end_date'];
            }
        }

        // Direktaufrufe statt Cache
        $this->zertifizierungen_images = $this->get_zertifizierungen_images();
        $this->zertifizierungen_images_html = $this->get_zertifizierungen_images_html();
        $this->module_gliederung_html = $this->get_module_gliederung_html();
        $this->zeiteinteilung_html = $this->get_zeiteinteilung_html();
        $this->module_html = $this->get_module_html();
        $this->anmeldung_agb = $this->get_anmeldung_agb_html();
        $this->inhalte = $this->get_inhalte_dyn();
        $this->zertifizierungen_loop_html = $this->get_zertifizierungen_loop_html();
        $this->voraussetzungen = get_field('voraussetzungen_abschluss', $post_id);
        $this->voraussetzungen_html = $this->get_voraussetzungen_list_html();
        $this->garantie = $this->get_garantie_html();
        $this->trainer = $this->get_trainer_html();
        $this->ps = $this->get_ps_html();

        $this->anzahl_le = get_post_meta($post_id, 'lehreinheiten_gesamt', true);
        $this->termine_pdf = get_field('kurszeiten_details_pdf', $post_id);
        $this->zielgruppe = sanitize_text_field(get_field('teilnehmeruberblick', $post_id));
        $anzahl_le_val = absint($this->anzahl_le);
        $preis_brutto_val = CRM_Pdf_Presenter::parse_price_float($this->preis_brutto);
        $this->le_single = ($anzahl_le_val > 0) ? number_format($preis_brutto_val / $anzahl_le_val, 2, ',', '.') : '0,00';
        if (empty($entry_status['inquiry_type']) || $entry_status['inquiry_type'] !== 'freie_anfrage') {
            $this->kurstyp = $this->get_coursetype();
        }
        $this->abschluss = get_post_meta($post_id, ["zertifikat"][0], true);

        $this->form_certifications = $this->get_form_zertifizierungen_loop_html();
        $this->kursgebuehr_html = $this->get_kursgebuehr_html();
        $this->texte_fur_diplom_links = get_field('texte_fur_diplom_links', $post_id) ?: get_post_meta($post_id, 'diplom_text_links', true) ?: '';
        $this->texte_fur_diplom_rechts = get_field('texte_fur_diplom_rechts', $post_id) ?: get_post_meta($post_id, 'diplom_text_rechts', true) ?: '';
        $this->termine_link = get_field('kurszeiten_details_pdf', $post_id) ?? '';

        // Kursart & Durchführungsmodus
        $this->set_kursart(get_post_meta($post_id, 'tages_abend_wochenende_', true));
        $this->durchfuehrungsmodus = get_post_meta($post_id, 'durchfuehrungsmodus', true) ?: '';
        $this->startgarantie = (int)get_post_meta($post_id, 'startgarantie', true);

        // Durchführungsform ermitteln (inkl. DaF/DaZ Spezifikationen)
        $is_ams_aktion = (stripos($this->title, 'AMS Aktion') !== false || stripos($this->permalink, 'ams-aktion') !== false || $post_id == 65629);
        $is_daf_daz    = (stripos($this->title, 'DaF') !== false || stripos($this->title, 'DaZ') !== false || $post_id == 701 || $post_id == 65629);
        if ($is_daf_daz && !$is_ams_aktion) {
            $this->durchfuehrung = 'via Live-Online-Event und in 1070 Wien (spezifische DaF/DaZ-Grammatikvermittlung mit Mag. Isabella Lichtenegger)';
        } elseif ($is_ams_aktion) {
            $this->durchfuehrung = 'als Live-Online-Event';
        } elseif (!empty($this->durchfuehrungsmodus)) {
            $this->durchfuehrung = $this->durchfuehrungsmodus;
        } else {
            $this->durchfuehrung = 'Live-Online-Event (Zoom)';
        }

        // LE-Splitting & Gebühren
        $this->le_praesenz = (int)get_post_meta($post_id, 'le_praesenz', true);
        $this->le_selbststudium = (int)get_post_meta($post_id, 'le_selbststudium', true);
        $this->le_projekt_transfer = (int)get_post_meta($post_id, 'le_projekt_transfer', true);
        $this->ust_satz = get_post_meta($post_id, 'ust_satz', true) !== '' ? (float)get_post_meta($post_id, 'ust_satz', true) : 20.0;
        $this->kosten_pruefung = (float)get_post_meta($post_id, 'kosten_pruefung', true);
        $this->kosten_unterlagen_inkl = (int)get_post_meta($post_id, 'kosten_unterlagen_inkl', true);

        // Förderungen & Abschluss
        $this->seminarnummer = trim((string)get_post_meta($post_id, 'seminarnummer', true));
        $this->waff_themencode = trim((string)get_post_meta($post_id, 'waff_themencode', true));
        $this->bildungskarenz_geeignet = (int)get_post_meta($post_id, 'bildungskarenz_geeignet', true);
        $this->wba_punkte = trim((string)get_post_meta($post_id, 'wba_punkte', true));
        $this->abschluss_typ = get_post_meta($post_id, 'abschluss_typ', true) ?: 'diplom';
        $this->mindestanwesenheit_prozent = get_post_meta($post_id, 'mindestanwesenheit_prozent', true) ?: 75;
        $this->pruefungsmodus = get_post_meta($post_id, 'pruefungsmodus', true) ?: 'ohne';
        $this->online_plattform = trim((string)get_post_meta($post_id, 'online_plattform', true));

        // Kurszeiten
        $this->kurszeiten    = $this->build_days(get_field("kurszeiten", $post_id));
        $this->selbststudium = $this->build_days(get_field("selbstudium", $post_id) ?: get_field("selbststudium", $post_id));


        // Zertifizierungen
        if (have_rows('zertifizierungen', $post_id)) {
            while (have_rows('zertifizierungen', $post_id)) {
                the_row();
                $this->zertifizierungen[] = [
                    'name' => get_sub_field('name-zert'),
                    'preis' => get_sub_field('preis'),
                    'ust' => get_sub_field('Ust_satz')
                ];
            }
        }

        // Misc
        $this->nummer = time();
        $this->kurszeiten_datum = date("d.m.Y");
        $this->pdfAuthor = "X-Sieben Wirtschaftstraining";
        $this->current = date('d.m.Y');
        $this->_setExpireDate();

        $this->bankverbindung = $this->get_crm_field('Bankverbindung');
        $this->agb_claim = $this->get_crm_field('AGB text');

        $this->load_company_settings();
        $this->load_icons();

        // Kurszeiten / Uhrzeit berechnen
        $uhr_von = trim((string)get_post_meta($post_id, 'uhrzeit', true));
        $uhr_bis = trim((string)get_post_meta($post_id, 'uhrzeit_ende', true));
        if (!empty($uhr_von) && !empty($uhr_bis)) {
            $this->uhrzeit = $uhr_von . ' – ' . $uhr_bis . ' Uhr';
        } elseif (!empty($uhr_von)) {
            $this->uhrzeit = $uhr_von . ' Uhr';
        } elseif (!empty($this->kurszeiten) && is_array($this->kurszeiten)) {
            $first_time = reset($this->kurszeiten);
            $this->uhrzeit = !empty($first_time) ? $first_time : '09:00 – 17:00 Uhr';
        } else {
            $this->uhrzeit = '09:00 – 17:00 Uhr';
        }

        // Standardisierte Platzhalter & Aliase (vor get_crm_field() initialisieren!)
        $this->kurstitel       = $this->title;
        $this->kurstitel_short = $this->titel_short;
        $this->startdatum      = $this->start_datum;
        $this->enddatum        = $this->end_datum;
        $this->le              = $this->anzahl_le;
        $this->schulungsort    = !empty($this->location_wien) ? $this->location_wien : 'Rochusgasse 6, 1030 Wien';
        $this->ort             = $this->schulungsort;
        $this->preis           = $this->preis_netto;
        $this->gesamtpreis     = $this->preis_brutto;
        $this->kunden_firma    = $this->customer_company;
        $this->telefon         = !empty($this->company_phone) ? $this->company_phone : '0800 700 170';
        $this->datum           = date('d.m.Y');

        $this->signatur = $this->get_signature_html();
        $this->beratung_email = $this->get_crm_field('Beratung E-Mail Text');
        $this->buchung_email = $this->get_crm_field('Anmeldung Buchung E-Mail Text');
        $this->signatur_email = $this->get_crm_field('E-Mail Signatur');
        $this->email_footer = $this->get_crm_field('E-Mail-Footer');
        $this->teilnahmebestaetigung_email = $this->get_crm_field('E-Mail Teilnahmebestätigung - Allgemein');
        $this->teilnahmebestaetigung_f_email = $this->get_crm_field('E-Mail Teilnahmebestätigung - Förderung');
        $this->angebot_email = $this->get_crm_field('E-Mail Angebot');
        $this->anmeldung_email = $this->get_crm_field('E-Mail Anmeldebestätigung');
        $this->diplom_email = $this->get_crm_field('E-Mail Diplom');
    }

    /**
     * Extrahiert Titel, Vorname und Nachname aus den WPForms-Eintragsdaten.
     */
    private function set_personal_data(): void
    {
        $raw_anrede = (string)$this->get_wpforms_field_by_id(88);
        $this->titel = (string)$this->get_wpforms_field_by_id(90);
        $this->vorname = (string)$this->get_wpforms_field_by_id(86);
        $this->nachname = (string)$this->get_wpforms_field_by_id(89);
        $this->email = (string)$this->get_wpforms_field_by_id(93);
        $this->svr = (string)$this->get_wpforms_field_by_id(29);
        $raw_company = trim((string)$this->get_wpforms_field_by_id(25));
        $this->customer_company = (stripos($raw_company, 'Privatperson') !== false || stripos($raw_company, 'Angebot für') !== false || stripos($raw_company, 'Angebot') !== false || strcasecmp($raw_company, 'Unternehmen') === 0) ? '' : $raw_company;
        $this->customer_type    = trim((string)$this->get_wpforms_field_by_id(3));

        // Intelligente Fallbacks über Feld-Synonyme (falls IDs durch Formularänderung abweichen)
        if (function_exists('crm_match_field_value') && !empty($this->entry_data) && is_array($this->entry_data)) {
            if (empty($raw_anrede))             $raw_anrede = (string)crm_match_field_value($this->entry_data, 'anrede');
            if (empty($this->titel))            $this->titel = (string)crm_match_field_value($this->entry_data, 'titel');
            if (empty($this->vorname))          $this->vorname = (string)crm_match_field_value($this->entry_data, 'vorname');
            if (empty($this->nachname))         $this->nachname = (string)crm_match_field_value($this->entry_data, 'nachname');
            if (empty($this->email))            $this->email = (string)crm_match_field_value($this->entry_data, 'e-mail');
            if (empty($this->customer_company)) {
                $matched_c = (string)crm_match_field_value($this->entry_data, 'firma');
                if (stripos($matched_c, 'Privatperson') === false && stripos($matched_c, 'Angebot für') === false && stripos($matched_c, 'Angebot') === false && strcasecmp($matched_c, 'Unternehmen') !== 0) {
                    $this->customer_company = $matched_c;
                }
            }
        }

        // Fallback: Falls Feld 25 leer ist, im gesamten Entry nach Feldern wie 'firma' suchen
        if (empty($this->customer_company) && !empty($this->entry_data) && is_array($this->entry_data)) {
            foreach ($this->entry_data as $fld) {
                if (!empty($fld['name']) && (stripos($fld['name'], 'firma') !== false || stripos($fld['name'], 'unternehmung') !== false)) {
                    if (!empty($fld['value']) && is_string($fld['value']) && stripos($fld['name'], 'privat') === false && stripos($fld['value'], 'Privatperson') === false && stripos($fld['value'], 'Angebot für') === false) {
                        $this->customer_company = trim($fld['value']);
                        break;
                    }
                }
            }
        }

        // Anrede bereinigen: "Angebot für eine Privatperson" / Formulartexte ausfiltern
        if (stripos($raw_anrede, 'Herr') !== false) {
            $this->anrede = 'Herr';
            $this->salutation = "Sehr geehrter Herr";
        } elseif (stripos($raw_anrede, 'Frau') !== false) {
            $this->anrede = 'Frau';
            $this->salutation = "Sehr geehrte Frau";
        } else {
            if (stripos($raw_anrede, 'Privatperson') !== false || stripos($raw_anrede, 'Unternehmen') !== false || stripos($raw_anrede, 'Angebot') !== false) {
                $this->anrede = '';
            } else {
                $this->anrede = trim($raw_anrede);
            }
            $this->salutation = "Sehr geehrte Damen und Herren";
        }
    }

    /**
     * Formatiert die Empfängeradresse normgerecht nach DIN 5008 / ÖNORM A 1080.
     * Unterstützt Privatpersonen, akademische Titel sowie Unternehmen (Variante A Geschäftlich vs. Variante B Vertraulich).
     *
     * @param string $variant 'A' (Standard: Geschäftlich, Firma zuerst) oder 'B' (Persönlich/Vertraulich, Person zuerst)
     * @param bool $as_html Wenn true, wird HTML mit <br> zurückgegeben, sonst Plaintext mit \n
     * @return string
     */
    public function format_postal_address(string $variant = 'A', bool $as_html = true): string
    {
        $lines = [];

        // 1. Postalischer Akkusativ für Anrede
        $salutation_clean = trim((string)($this->anrede ?? ''));
        $postal_salutation = '';
        if (strcasecmp($salutation_clean, 'Herr') === 0 || strcasecmp($salutation_clean, 'Herrn') === 0) {
            $postal_salutation = 'Herrn';
        } elseif (strcasecmp($salutation_clean, 'Frau') === 0) {
            $postal_salutation = 'Frau';
        } elseif (strcasecmp($salutation_clean, 'Familie') === 0) {
            $postal_salutation = 'Familie';
        } elseif (strcasecmp($salutation_clean, 'Eheleute') === 0) {
            $postal_salutation = 'Eheleute';
        } elseif (strcasecmp($salutation_clean, 'Firma') === 0 || stripos($salutation_clean, 'Privatperson') !== false || stripos($salutation_clean, 'Angebot') !== false || stripos($salutation_clean, 'Unternehmen') !== false) {
            $postal_salutation = '';
        } else {
            $postal_salutation = $salutation_clean;
        }

        $title_clean    = trim((string)($this->titel ?? ''));
        $vorname_clean  = trim((string)($this->vorname ?? ''));
        $nachname_clean = trim((string)($this->nachname ?? ''));
        $raw_firma      = trim((string)($this->customer_company ?? ''));
        $firma_clean    = (stripos($raw_firma, 'Privatperson') !== false || stripos($raw_firma, 'Angebot für') !== false || stripos($raw_firma, 'Angebot') !== false || strcasecmp($raw_firma, 'Unternehmen') === 0) ? '' : $raw_firma;

        // Namenszusammensetzung (Vermeidung von Dopplungen falls Titel bereits im Namen steht)
        $name_parts = [];
        if (!empty($vorname_clean)) $name_parts[] = $vorname_clean;
        if (!empty($nachname_clean)) $name_parts[] = $nachname_clean;
        $full_name = implode(' ', $name_parts);

        $person_line = '';
        if (!empty($full_name)) {
            if (!empty($title_clean) && stripos($full_name, $title_clean) === false) {
                // Mit akademischem Titel: Anrede + Titel + Name in einer Zeile (DIN 5008 / ÖNORM)
                $person_line = trim($postal_salutation . ' ' . $title_clean . ' ' . $full_name);
            } else {
                $person_line = $full_name;
            }
        }

        if (!empty($firma_clean)) {
            // Fall 4: Firmen und Unternehmen
            $company_person = trim($postal_salutation . ' ' . (!empty($title_clean) && stripos($full_name, $title_clean) === false ? $title_clean . ' ' : '') . $full_name);
            if (strtoupper($variant) === 'B') {
                // Variante B: Persönlich/Vertraulich (Person zuerst, Firma darunter)
                if (!empty($company_person)) $lines[] = $company_person;
                $lines[] = $firma_clean;
            } else {
                // Variante A: Geschäftlich (Firma in der ersten Zeile, Ansprechpartner darunter)
                $lines[] = $firma_clean;
                if (!empty($company_person)) $lines[] = $company_person;
            }
        } else {
            // Fall 2 & 3: Privatpersonen
            if (!empty($title_clean) && !empty($person_line)) {
                // Mit Titel: z. B. "Frau Dr. Martina Muster" bzw. "Herrn Prof. Dr. Max Muster"
                $lines[] = $person_line;
            } else {
                // Ohne Titel: "Frau Nina Biskup" bzw. "Herrn Max Muster"
                if (!empty($postal_salutation) && !empty($person_line)) {
                    $lines[] = trim($postal_salutation . ' ' . $person_line);
                } elseif (!empty($person_line)) {
                    $lines[] = $person_line;
                }
            }
        }

        // Straße und Hausnummer
        $street_full = trim(($this->street ?? '') . ' ' . ($this->house_number ?? ''));
        if (!empty($street_full)) {
            $lines[] = $street_full;
        }

        // PLZ und Ort
        $city_parts = [];
        if (!empty($this->zip_code)) $city_parts[] = trim((string)$this->zip_code);
        if (!empty($this->city))     $city_parts[] = trim((string)$this->city);
        if (!empty($city_parts)) {
            $lines[] = implode(' ', $city_parts);
        }

        // Land (nur wenn Ausland und nicht Österreich)
        $country_clean = trim((string)($this->country ?? ''));
        if (!empty($country_clean) && !in_array(strtoupper($country_clean), ['AT', 'AUT', 'ÖSTERREICH', 'OESTERREICH'], true)) {
            $lines[] = strtoupper($country_clean);
        }

        if ($as_html) {
            return implode("<br>\n", array_map('htmlspecialchars', $lines));
        }
        return implode("\n", $lines);
    }

    /**
     * Get the value of a CRM custom field by title
     *
     * @param string $title  The field title to search for
     * @return string        The content of the field or empty string
     */

    // Inside the CRM_Model class
    public function get_crm_field($title)
    {
        $fields = function_exists('crm_get_merged_custom_fields') ? crm_get_merged_custom_fields() : get_option('crm_custom_fields', []);
        if (empty($fields) || !is_array($fields)) {
            return '';
        }

        $target = strtolower(trim($title));
        foreach ($fields as $field) {
            if (isset($field['title']) && strtolower(trim($field['title'])) === $target) {
                $content = $field['content'] ?? '';
                // --- Add the parsing step here ---
                $content = $this->parse_string_with_data($content);
                // --- End of parsing step ---
                // Format content for emails using wpautop and do_shortcode directly.
                // We intentionally do NOT use apply_filters('the_content') here because global theme
                // filters (such as in functions.php) strip domain names from image and link URLs,
                // and cookie consent plugins mangle <img> src attributes into consent attributes.
                $content = wpautop($content);
                $content = do_shortcode($content);
                if (function_exists('crm_prepare_email_html_for_sending')) {
                    $content = crm_prepare_email_html_for_sending($content);
                }
                return $content;
            }
        }

        // If not found, return an empty string
        return '';
    }

    /**
     * Ersetzt dynamische CRM-Platzhalter, Kursdaten, Personenmerkmale und Komponenten in einem Text.
     * Führt bis zu 3 Ersetzungsrunden durch, um verschachtelte Platzhalter (z.B. {signatur_email} enthält {company_name})
     * vollständig aufzulösen.
     *
     * @param string $template_string
     * @return string
     */
    public function parse_string_with_data(string $template_string): string
    {
        if (empty($template_string)) {
            return '';
        }

        $max_passes = 3;
        $current = $template_string;

        for ($pass = 0; $pass < $max_passes; $pass++) {
            if (strpos($current, '{') === false) {
                break;
            }

            $prev = $current;
            $current = preg_replace_callback('/\{([a-zA-Z0-9_\-\.]+)\}/', function ($matches) {
                $key = strtolower(trim($matches[1]));

                // 1. Spezielle dynamische Werte
                if ($key === 'current_date' || $key === 'datum') {
                    return date('d.m.Y');
                }
                if ($key === 'diplom_success') {
                    $succ = $this->get_diplom_success();
                    return !empty($succ) ? 'mit ' . esc_html($succ) . ' ' : '';
                }
                if ($key === 'kurstyp_upper') {
                    return mb_strtoupper((string)($this->kurstyp ?: 'Lehrgang'), 'UTF-8');
                }
                if ($key === 'kurstyp_lower') {
                    return mb_strtolower((string)($this->kurstyp ?: 'lehrgang'), 'UTF-8');
                }
                if ($key === 'hat_den_kurstyp' || $key === 'kurstyp_phrase') {
                    return function_exists('crm_get_diplom_kurstyp_phrase')
                        ? crm_get_diplom_kurstyp_phrase($this->kurstyp)
                        : ('HAT DEN ' . mb_strtoupper((string)($this->kurstyp ?: 'LEHRGANG'), 'UTF-8'));
                }

                // 2. Direkte Objekt-Eigenschaften prüfen
                if (property_exists($this, $key) && is_scalar($this->$key)) {
                    return (string)$this->$key;
                }

                // 3. Umfassende Aliase (Kursdaten, Personen, Firma & CI Stammdaten)
                $aliases = [
                    // Kurs- und Veranstaltungsdaten
                    'kurstitel'          => 'title',
                    'kurstitel_short'    => 'titel_short',
                    'kurs_titel'         => 'title',
                    'startdatum'         => 'start_datum',
                    'start_datum'        => 'start_datum',
                    'enddatum'           => 'end_datum',
                    'end_datum'          => 'end_datum',
                    'uhrzeit'            => 'uhrzeit',
                    'kurszeiten'         => 'uhrzeit',
                    'zeiten'             => 'uhrzeit',
                    'le'                 => 'anzahl_le',
                    'anzahl_le'          => 'anzahl_le',
                    'lehreinheiten'      => 'anzahl_le',
                    'preis'              => 'preis_netto',
                    'preis_netto'        => 'preis_netto',
                    'preis_brutto'       => 'preis_brutto',
                    'gesamtpreis'        => 'preis_brutto',
                    'le_single'          => 'le_single',
                    'schulungsort'       => 'location_wien',
                    'ort'                => 'location_wien',
                    'standort_wien'      => 'location_wien',
                    'schulungsort_wien'  => 'location_wien',
                    'dauer'              => 'anzahl_le',
                    'expire'             => 'expire',

                    // Personen- & Kundendaten
                    'kunden_firma'       => 'customer_company',
                    'firma'              => 'customer_company',
                    'anrede_brief'       => 'salutation',
                    'telefon'            => 'company_phone',

                    // Instituts- & Firmendaten (Stammdaten-Vererbung)
                    'schulungsinstitut'  => 'company_name',
                    'institut_name'      => 'company_name',
                    'institut'           => 'company_name',
                    'firmenname'         => 'company_name',
                    'company_name'       => 'company_name',
                    'institut_kurz'      => 'company_short_name',
                    'company_short_name' => 'company_short_name',
                    'company_legal_form' => 'company_legal_form',
                    'geschaeftsfuehrung' => 'company_management',
                    'institutsleiter'    => 'company_management',
                    'company_management' => 'company_management',
                    'institut_adresse'   => 'company_address',
                    'company_address'    => 'company_address',
                    'firmenadresse'      => 'company_address',
                    'adresse'            => 'company_address',
                    'company_street'     => 'company_street',
                    'company_zip'        => 'company_zip',
                    'company_city'       => 'company_city',
                    'company_country'    => 'company_country',
                    'location_wien'      => 'location_wien',
                    'location_wien_name' => 'location_wien_name',
                    'location_wien_street' => 'location_wien_street',
                    'location_wien_zip'  => 'location_wien_zip',
                    'location_wien_city' => 'location_wien_city',
                    'location_wien_notice' => 'location_wien_notice',
                    'institut_telefon'   => 'company_phone',
                    'company_phone'      => 'company_phone',
                    'institut_email'     => 'company_email',
                    'company_email'      => 'company_email',
                    'institut_website'   => 'company_website',
                    'company_website'    => 'company_website',
                    'website'            => 'company_website',
                    'backoffice_name'    => 'backoffice_name',
                    'backoffice_email'   => 'backoffice_email',
                    'backoffice_phone'   => 'backoffice_phone',
                    'institut_uid'       => 'company_uid',
                    'company_uid'        => 'company_uid',
                    'uid'                => 'company_uid',
                    'institut_fn'        => 'company_fn',
                    'company_fn'         => 'company_fn',
                    'fn'                 => 'company_fn',
                    'institut_gericht'   => 'company_court',
                    'company_court'      => 'company_court',
                    'gericht'            => 'company_court',
                    'company_chamber'    => 'company_chamber',
                    'kammer'             => 'company_chamber',
                    'institut_bank'      => 'company_bank',
                    'company_bank'       => 'company_bank',
                    'bank'               => 'company_bank',
                    'bankverbindung'     => 'company_bank',
                    'institut_logo'      => 'company_logo',
                    'company_logo'       => 'company_logo',
                    'logo'               => 'company_logo',
                    'company_logo_url'   => 'company_logo_url',
                    'logo_url'           => 'company_logo_url',
                    'agb_url'            => 'agb_url',
                    'legal_agb_url'      => 'agb_url',
                    'privacy_url'        => 'privacy_url',
                    'legal_privacy_url'  => 'privacy_url',
                    'imprint_url'        => 'imprint_url',
                    'legal_imprint_url'  => 'imprint_url',
                    'durchfuehrung'      => 'durchfuehrung',
                    'durchfuehrungsform' => 'durchfuehrung',
                ];

                if (isset($aliases[$key])) {
                    $prop = $aliases[$key];
                    if (property_exists($this, $prop) && is_scalar($this->$prop)) {
                        return (string)$this->$prop;
                    }
                }

                // 4. E-Mail-Komponenten (Bausteine) auflösen
                // Verbindliche Signatur für Kunden-E-Mails stammt direkt aus dem Baustein 'E-Mail Signatur'

                $component_map = [
                    'signatur_email'          => 'E-Mail Signatur',
                    'signatur'                => 'E-Mail Signatur',
                    'email_footer'            => 'E-Mail-Footer',
                    'footer'                  => 'E-Mail-Footer',
                    'buchung_email'           => 'Anmeldung Buchung E-Mail Text',
                    'agb_claim'               => 'AGB text',
                    'bankverbindung'          => 'Bankverbindung',
                    'angebot_hinweis'         => 'Angebot E-Mail Hinweis',
                    'angebot_ps'              => 'Angebot PS',
                    'durchfuehrungs_garantie' => 'Durchführungs Garantie',
                    'anhang_2'                => 'Anhang 2 | Exklusive Zusatzleistungen',
                    'teilnahme_fee'           => 'Teilnahme_Fee',
                ];

                if (isset($component_map[$key])) {
                    $comp_content = $this->get_crm_field($component_map[$key]);
                    if ($comp_content !== '') {
                        return $comp_content;
                    }
                }

                // 5. Dynamische Prüfung in crm_merged_custom_fields für alle definierten Bausteine
                $custom_fields = function_exists('crm_get_merged_custom_fields') ? crm_get_merged_custom_fields() : get_option('crm_custom_fields', []);
                if (is_array($custom_fields)) {
                    foreach ($custom_fields as $cf) {
                        if (empty($cf['title'])) {
                            continue;
                        }
                        if (function_exists('crm_get_component_placeholder_for_title')) {
                            $token = trim(crm_get_component_placeholder_for_title($cf['title']), '{}');
                            if (strcasecmp($token, $key) === 0) {
                                return !empty($cf['content']) ? wp_kses_post($cf['content']) : '';
                            }
                        }
                    }
                }

                // Falls kein passender Wert gefunden wird, Platzhalter unverändert beibehalten
                return $matches[0];
            }, $current);

            if ($current === $prev) {
                break;
            }
        }

        return $current;
    }

    /**
     * Get the value of a CRM custom field by title with a fallback default.
     *
     * @param string $title   The field title to search for
     * @param string $default Fallback string if field is missing or empty
     * @return string
     */
    public function get_crm_field_with_default(string $title, string $default = ''): string
    {
        $content = $this->get_crm_field($title);
        if (!empty(trim(strip_tags($content)))) {
            return $content;
        }

        // Parse default string with data placeholders as well
        $default = $this->parse_string_with_data($default);
        if (function_exists('crm_prepare_email_html_for_sending')) {
            $default = crm_prepare_email_html_for_sending($default);
        }
        return $default;
    }


    /**
     * Extrahiert separate Adresskomponenten aus den WPForms-Eintragsdaten.
     * Nutzt die spezifische WPForms-Feld-ID für das Adressfeld (ID 35).
     */
    private function set_address_components(): void
    {
        // 1. Primär: Spezifische WPForms-Feld-ID für das Adressfeld (ID 35)
        if (!empty($this->entry_data[35]) && is_array($this->entry_data[35])) {
            $address_fields = $this->entry_data[35];
            $this->street = $address_fields['address1'] ?? '';
            $this->house_number = '';
            $this->city = $address_fields['city'] ?? '';
            $this->zip_code = $address_fields['postal'] ?? '';
            $this->country = $address_fields['country'] ?? '';
        }

        // 2. Intelligenter Fallback über Feld-Synonyme (falls Feld 35 nicht existiert oder leer ist)
        if (function_exists('crm_match_field_value') && !empty($this->entry_data) && is_array($this->entry_data)) {
            if (empty($this->street))   $this->street = crm_match_field_value($this->entry_data, 'straße');
            if (empty($this->city))     $this->city = crm_match_field_value($this->entry_data, 'ort');
            if (empty($this->zip_code)) $this->zip_code = crm_match_field_value($this->entry_data, 'plz');
            if (empty($this->country))  $this->country = 'AT';
        }
    }
    public function get_certifications_from_form_field(): array
    {
        if (isset($this->override_certifications) && is_array($this->override_certifications)) {
            return $this->override_certifications;
        }

        // Check for the entry data from the form field with ID 99.
        $cert_data_string = $this->get_wpforms_field_by_id(99);

        // Return an empty array if no data is found.
        if (empty($cert_data_string)) {
            return [];
        }

        // Split the string into individual lines.
        $lines = explode("\n", $cert_data_string);
        $data = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Regex to capture the name, price, and optional percentage
            $pattern = '/(.+?) - € ([\d\.,]+)(?: \((\d+)%\))?/';

            if (preg_match($pattern, $line, $matches)) {
                $name = trim($matches[1]);
                $price = trim($matches[2]);
                $percentage = isset($matches[3]) ? $matches[3] . '%' : 'N/A';

                $data[] = [
                    "name" => $name,
                    "price" => $price,
                    "percentage" => $percentage
                ];
            } else {
                // Special case for "FachtrainerIn" which doesn't have a price in the original text.
                if (strpos($line, 'FachtrainerIn') !== false) {
                    $data[] = [
                        "name" => $line,
                        "price" => "324,00",
                        "percentage" => "N/A"
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * Formatiert ein Datum, das aus Post-Metadaten abgerufen wird.
     * @param string $meta_key Der Schlüssel der Post-Meta.
     * @return string Das formatierte Datum (d.m.Y) oder ein leerer String, wenn kein Datum gefunden wird.
     */
    private function format_date_meta($meta_key)
    {
        $raw = get_post_meta($this->post_id, $meta_key, true);
        return $raw ? date('d.m.Y', strtotime($raw)) : '';
    }

    /**
     * Erstellt ein Array von Tagen mit Standardzeiten, basierend auf einem Eingabearray.
     * @param array $days_array Ein Array von Wochentagen (z.B. ['Montag', 'Dienstag']).
     * @param bool $suffix Wenn true, wird '_s' an den Schlüssel angehängt (für Selbststudium).
     * @return array Ein assoziatives Array mit Wochentagen als Schlüsseln und Zeiten als Werten.
     */
    private function build_days($days_array, $suffix = false)
    {
        $map = [];
        $timeslot = "09.00 - 17.00 Uhr";
        $weekdays = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

        foreach ($weekdays as $day) {
            if (is_array($days_array) && in_array($day, $days_array)) {
                $key = strtolower($day) . ($suffix ? '_s' : '');
                $map[$key] = $timeslot;
            }
        }
        return $map;
    }

    /**
     * Setzt die Kursart-Eigenschaften basierend auf dem übergebenen Array.
     * @param array|string $kursart_array Ein Array von Kursarten oder ein einzelner String.
     */
    private function set_kursart($kursart_array)
    {
        $this->kursart = is_array($kursart_array) ? implode(', ', $kursart_array) : $kursart_array;

        $map = [
            'Tageskurs' => 'kursart_t',
            'Abendkurs' => 'kursart_a',
            'Wochenendkurs' => 'kursart_we',
        ];

        foreach ($map as $label => $prop) {
            if (
                (is_array($kursart_array) && in_array($label, $kursart_array)) ||
                (!is_array($kursart_array) && $kursart_array === $label)
            ) {
                $this->$prop = "<strong>X</strong>";
            }
        }
    }

    /**
     * Lädt die demographischen Stammdaten und CI-Einstellungen aus dem CRM.
     */
    private function load_company_settings(): void
    {
        $settings = function_exists('crm_get_general_settings') ? crm_get_general_settings() : [];
        $this->company_name        = $settings['company_name'] ?? 'X SIEBEN Wirtschaftstraining GmbH';
        $this->company_short_name  = $settings['company_short_name'] ?? 'X SIEBEN';
        $this->company_legal_form  = $settings['company_legal_form'] ?? 'GmbH';
        $this->company_management  = $settings['company_management'] ?? 'Mag. Dr. Johannes Gasberger';
        $this->company_street      = $settings['company_street'] ?? 'Kurzegasse 7';
        $this->company_zip         = $settings['company_zip'] ?? '2493';
        $this->company_city        = $settings['company_city'] ?? 'Lichtenwörth';
        $this->company_country     = $settings['company_country'] ?? 'Österreich';
        $this->company_address     = trim($this->company_street . ', ' . $this->company_zip . ' ' . $this->company_city);
        $this->location_wien_name  = $settings['location_wien_name'] ?? 'Seminarzentrum Wien';
        $this->location_wien_street= $settings['location_wien_street'] ?? 'Rochusgasse 6';
        $this->location_wien_zip   = $settings['location_wien_zip'] ?? '1030';
        $this->location_wien_city  = $settings['location_wien_city'] ?? 'Wien';
        $this->location_wien       = trim($this->location_wien_street . ', ' . $this->location_wien_zip . ' ' . $this->location_wien_city);
        $this->location_wien_notice= $settings['location_wien_notice'] ?? 'Online Unterricht | vor Ort in unseren Veranstaltungsräumen | Blended Learning';
        $this->company_phone       = $settings['company_phone'] ?? '0800 700 170';
        $this->company_email       = $settings['company_email'] ?? 'office@x-sieben.at';
        $this->company_website     = $settings['company_website'] ?? 'https://x-sieben.at';
        $this->backoffice_name     = $settings['backoffice_name'] ?? 'Anna Brauer';
        $this->backoffice_email    = $settings['backoffice_email'] ?? 'abrauer@x-sieben.at';
        $this->backoffice_phone    = $settings['backoffice_phone'] ?? '0800 700 170';
        $this->company_uid         = $settings['company_uid'] ?? 'ATU76624137';
        $this->company_fn          = $settings['company_fn'] ?? 'FN 550277 g';
        $this->company_court       = $settings['company_court'] ?? 'Landesgericht Wiener Neustadt';
        $this->company_chamber     = $settings['company_chamber'] ?? 'Wirtschaftskammer Niederösterreich / Wien';
        $this->company_bank        = $settings['company_bank'] ?? 'Erste Bank | IBAN: AT29 3293 7001 0012 5260 | BIC: RLNWATWWWRN';
        $this->company_slogan      = $settings['company_claim'] ?? 'Wirtschaftstraining, Seminare & Personenzertifizierungen';
        $this->company_accreditations = $settings['company_accreditations'] ?? 'pma / IPMA®, SystemCERT (ISO 17024), TÜV Austria, wba, CERT NÖ, AMS';
        $this->agb_url             = $settings['legal_agb_url'] ?? 'https://x-sieben.at/wp-content/uploads/2025/09/AGB_X_SIEBEN_2025.pdf';
        $this->privacy_url         = $settings['legal_privacy_url'] ?? 'https://x-sieben.at/datenschutzerklaerung/';
        $this->imprint_url         = $settings['legal_imprint_url'] ?? 'https://x-sieben.at/impressum/';
        $this->ci_primary_color    = $settings['ci_primary_color'] ?? '#007C90';
        $this->ci_secondary_color  = $settings['ci_secondary_color'] ?? '#0284c7';
        $this->ci_accent_color     = $settings['ci_accent_color'] ?? '#0f172a';
        $this->company_logo_url    = $settings['logo_url'] ?? '';
        $this->company_logo_secondary_url = $settings['logo_secondary_url'] ?? '';
    }

    /**
     * Lädt und formatiert HTML-Tags für verschiedene Icons und Logos aus dem Theme-Assets-Verzeichnis.
     */
    private function load_icons()
    {
        $assets_dir = get_template_directory() . '/inc/core/crm/assets/';
        $assets_url = get_template_directory_uri() . '/inc/core/crm/assets/';
        $icons = [
            'web_icon'       => ['file' => 'kontakt.png', 'width' => '25px'],
            'mail_icon'      => ['file' => 'email.png', 'width' => '25px'],
            'fax_icon'       => ['file' => 'fax.png', 'width' => '25px'],
            'phone_icon'     => ['file' => 'tel.png', 'width' => '25px'],
            'calender_icon'  => ['file' => 'kalender.png', 'width' => '25px'],
            'ort_icon'       => ['file' => 'ort.png', 'width' => '25px'],
            'abschluss_icon' => ['file' => 'abschluss.png', 'width' => '25px'],
            'diplom_icon'    => ['file' => 'diplom.png', 'width' => '25px'],
            'danger_icon'    => ['file' => 'danger.png', 'width' => '25px'],
            'sitting_icon'   => ['file' => 'sitting.png', 'width' => '25px'],
            'proven_icon'    => ['file' => 'proven.png', 'width' => ''],
            'proven_wide'    => ['file' => 'proven_wide.png', 'width' => ''],
            'wba_logo'       => ['file' => 'wba-1.png', 'width' => '100px'],
            'cert_noe_logo'  => ['file' => 'cert-1.png', 'width' => '100px'],
            'tuef_logo'      => ['file' => 'tuef.png', 'width' => ''],
            'sys_zert_logo'  => ['file' => 'system-1.png', 'width' => ''],
            'pma_logo'       => ['file' => 'PMA-1.png', 'width' => ''],
            'ipma_logo'      => ['file' => 'impa.png', 'width' => ''],
            'email_logos'    => ['file' => 'email_zerts.png', 'width' => '100px', 'style' => 'padding-left: 54px;'],
            'ams_img'        => ['file' => 'ams.png', 'width' => '160px'],
            'signatur_icon'  => ['file' => 'Signatur_Blau.png', 'width' => '125px'],
            'xsieben_logo'   => ['file' => 'xsieben_logo.png', 'width' => '200px'],
        ];
        foreach ($icons as $prop => $config) {
            $width_attr = !empty($config['width']) ? " width=\"{$config['width']}\"" : '';
            $style_attr = isset($config['style']) ? " style=\"{$config['style']}\"" : '';
            // Use centralized resolver: local filesystem path preferred, URL fallback
            $img_src = function_exists('crm_resolve_asset_path')
                ? crm_resolve_asset_path($config['file'])
                : ((file_exists($assets_dir . $config['file'])) ? ($assets_dir . $config['file']) : ($assets_url . $config['file']));
            $this->$prop = sprintf(
                '<img%s%s src="%s">',
                $width_attr,
                $style_attr,
                $img_src
            );
        }

        // Falls ein benutzerdefiniertes Logo in den CRM-Einstellungen hinterlegt ist, dieses für das Hauptlogo verwenden
        if (!empty($this->company_logo_url)) {
            $logo_src = function_exists('crm_resolve_asset_path')
                ? crm_resolve_asset_path($this->company_logo_url)
                : $this->company_logo_url;
            $this->xsieben_logo = sprintf(
                '<img width="200" style="max-width:200px; height:auto;" src="%s">',
                esc_attr($logo_src)
            );
        }
        $this->company_logo = $this->xsieben_logo;
    }

    /**
     * Zählt die Anzahl der Module, die einem Post zugewiesen sind.
     * @return int Die Anzahl der Module oder 0, wenn keine gefunden werden.
     */
    public function get_module_count()
    {
        $modules = get_field('module', $this->post_id);
        return is_array($modules) ? count($modules) : 0;
    }

    /**
     * Generiert HTML für die Gliederung der Module (Modulübersicht).
     *
     * @return string
     */
    public function get_module_gliederung_html(): string
    {
        return CRM_Pdf_Presenter::render_module_gliederung($this->post_id);
    }

    /**
     * Generiert HTML für die Zeiteinteilung und Lehreinheiten-Aufteilung.
     *
     * @return string
     */
    public function get_zeiteinteilung_html(): string
    {
        return CRM_Pdf_Presenter::render_zeiteinteilung($this->post_id);
    }

    /**
     * Generiert HTML für die Module und Zeiteinteilung, basierend auf den ACF-Daten.
     * Delegiert an CRM_Pdf_Presenter zur Wahrung von Separation of Concerns (SoC).
     *
     * @return string Der generierte HTML-Tabellen-String der Module.
     */
    public function get_module_html(): string
    {
        return CRM_Pdf_Presenter::render_module_html($this->post_id);
    }

    /**
     * Gibt den HTML-Code für die Anmelde- und AGB-Hinweise zurück.
     * Delegiert an CRM_Pdf_Presenter.
     *
     * @return string Der HTML-String mit AGB- und Datenschutzlinks.
     */
    private function get_anmeldung_agb_html(): string
    {
        return CRM_Pdf_Presenter::render_anmeldung_agb((string)$this->get_crm_field('AGB text'));
    }

    /**
     * Ruft Daten eines WPForms-Eintrags ab und gibt sie als assoziatives Array zurück,
     * wobei die Schlüssel die Feld-IDs sind.
     * @param int $entry_id Die ID des WPForms-Eintrags.
     * @return array Ein assoziatives Array der Felddaten des Eintrags oder ein leeres Array bei Fehler.
     */
    public function get_wpforms_entry_data($entry_id)
    {
        if (!function_exists('wpforms')) return [];
        $entry = wpforms()->entry->get($entry_id);
        if (!$entry) return [];

        $fields = is_string($entry->fields) ? json_decode($entry->fields, true) : $entry->fields;

        $result = [];
        if (is_array($fields)) {
            foreach ($fields as $field_id => $field) {
                $result[$field_id] = $field;
            }
        }

        return $result;
    }

    /**
     * Ruft den Wert eines WPForms-Feldes über seine ID ab.
     * @param int $field_id Die ID des Feldes, das ausgelesen werden soll.
     * @return mixed Der Wert des Feldes oder ein leerer String, wenn das Feld nicht existiert.
     */
    public function get_wpforms_field_by_id($field_id)
    {
        // Überprüft, ob die Eintragsdaten vorhanden sind
        if (empty($this->entry_data)) {
            return '';
        }

        // Überprüft, ob das Feld mit der gegebenen ID existiert und gibt den Wert zurück
        return $this->entry_data[$field_id]['value'] ?? '';
    }

    /**
     * Ruft die Thumbnail-Bilder von zertifizierenden Organisationen ab, die mit dem Post verknüpft sind.
     * @return array Ein Array von HTML-Thumbnail-Bild-Tags.
     */
    private function get_zertifizierungen_images()
    {
        $images = [];
        $ca_meta = get_post_meta($this->post_id, "zertifikate", true);
        if (!empty($ca_meta) && is_array($ca_meta)) {
            $query = new WP_Query([
                "post__in" => $ca_meta,
                "post_type" => 'ca',
                "posts_per_page" => -1
            ]);
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    if (get_the_ID() != 5793) {
                        $thumb = get_the_post_thumbnail(null, 'thumbnail');
                        if ($thumb) {
                            $images[] = $thumb;
                        }
                    }
                }
                wp_reset_postdata();
            }
        }
        return $images;
    }

    /**
     * Generiert einen HTML-Tabellen-String mit den vollständigen Bildern der Zertifizierungen.
     * Delegiert die Tabellenerstellung an CRM_Pdf_Presenter.
     *
     * @return string Der HTML-Tabellen-String mit Zertifizierungsbildern.
     */
    private function get_zertifizierungen_images_html(): string
    {
        $zert_images_src = [];
        $ca_meta = get_post_meta($this->post_id, "zertifikate", true);
        if (!empty($ca_meta) && is_array($ca_meta)) {
            $query = new WP_Query([
                "post__in" => $ca_meta,
                "post_type" => 'ca',
                "posts_per_page" => -1
            ]);
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    if (get_the_ID() != 5793) {
                        $thumb_id = get_post_thumbnail_id(get_the_ID());
                        $local_file = '';
                        if ($thumb_id) {
                            $im_data = function_exists('image_get_intermediate_size')
                                ? (image_get_intermediate_size($thumb_id, 'medium') ?: image_get_intermediate_size($thumb_id, 'thumbnail'))
                                : null;
                            if (!empty($im_data['file'])) {
                                $orig_file = get_attached_file($thumb_id);
                                if ($orig_file) {
                                    $cand = dirname($orig_file) . '/' . $im_data['file'];
                                    if (file_exists($cand)) {
                                        $local_file = $cand;
                                    }
                                }
                            }
                            if (!$local_file) {
                                $local_file = get_attached_file($thumb_id) ?: '';
                            }
                        }
                        if ($local_file && file_exists($local_file)) {
                            $zert_images_src[] = $local_file;
                        } else {
                            $image_url = get_the_post_thumbnail_url(get_the_ID(), 'medium') ?: get_the_post_thumbnail_url(get_the_ID(), 'thumbnail') ?: get_the_post_thumbnail_url(get_the_ID(), 'full');
                            if ($image_url) {
                                $zert_images_src[] = $image_url;
                            }
                        }
                    }
                }
                wp_reset_postdata();
            }
        }
        return CRM_Pdf_Presenter::render_zertifizierungen_images($zert_images_src);
    }

    private function get_accordion_content_by_title($accordionData, $partialTitle)
    {
        if (!is_array($accordionData)) {
            return null;
        }
        foreach ($accordionData as $item) {
            if (
                isset($item['title']) &&
                stripos($item['title'], $partialTitle) !== false
            ) {
                return $item['content'];
            }
        }
        return null;
    }

    /**
     * Ruft dynamische Inhalte aus den Post-Metadaten ab und formatiert sie als Modul-HTML.
     * Delegiert die Formatierung an CRM_Pdf_Presenter.
     *
     * @param string $accordion_title Der Titel des Akkordeon-Eintrags.
     * @return string Der generierte HTML-Inhalt für die Module oder eine Standardnachricht.
     */
    private function get_inhalte_dyn(string $accordion_title = 'Inhalte'): string
    {
        $accordion_data = get_post_meta($this->post_id, 'courses_accordion', true);
        $content = $this->get_accordion_content_by_title($accordion_data, $accordion_title);
        if ($content) {
            return CRM_Pdf_Presenter::_format_content_modules($content);
        }
        return '<p>Keine Inhalte gefunden.</p>';
    }

    /**
     * Teilt den gegebenen String in Module auf und formatiert sie als sauberes HTML für TCPDF.
     * Delegiert an CRM_Pdf_Presenter.
     *
     * @param string $content Der String, der die Moduldaten enthält.
     * @return string Der generierte HTML-Code für die Module.
     */
    private function _format_content_modules(string $content): string
    {
        return CRM_Pdf_Presenter::_format_content_modules($content);
    }

    /**
     * Teilt den gegebenen String in einzelne Module auf, basierend auf dem "MODUL X" Muster (Fallback / Abwärtskompatibilität).
     * @param string $content Der zu teilende String.
     * @return array Ein Array von Strings, wobei jeder String ein Modul darstellt.
     */
    private function splitByModul(string $content): array
    {
        $pattern = '/(?=modul\s*\d+\b(?![\)\.\w]))/i';
        $parts = preg_split($pattern, $content, -1, PREG_SPLIT_NO_EMPTY);
        $modules = array_map('trim', $parts);
        return $modules;
    }

    private function get_trainer_html(): string
    {
        return CRM_Pdf_Presenter::render_trainer($this->post_id);
    }

    private function get_coursetype(): string
    {
        // 1. Priorität: Taxonomie 'coursetype'
        $types = get_the_terms($this->post_id, 'coursetype');
        if (!empty($types) && !is_wp_error($types)) {
            $first_type = reset($types);
            if (!empty($first_type->name)) {
                return $first_type->name;
            }
        }

        // 2. Fallback: Taxonomie 'coursecategory'
        if (has_term('Lehrgang', 'coursecategory', $this->post_id)) {
            return 'Lehrgang';
        } elseif (has_term('Seminar', 'coursecategory', $this->post_id)) {
            return 'Seminar';
        } elseif (has_term('Crashkurs', 'coursecategory', $this->post_id)) {
            return 'Crashkurs';
        } elseif (has_term('Bundle', 'coursecategory', $this->post_id)) {
            return 'Bundle';
        } elseif (has_term('eLearning', 'coursecategory', $this->post_id)) {
            return 'eLearning';
        } elseif (has_term('Blended Learning', 'coursecategory', $this->post_id)) {
            return 'Blended Learning';
        } elseif (has_term('Coaching', 'coursecategory', $this->post_id)) {
            return 'Coaching';
        }
        return 'Lehrgang';
    }

    private function get_garantie_html(): string
    {
        return CRM_Pdf_Presenter::render_garantie((string)$this->get_crm_field('Anhang 2 | Exklusive Zusatzleistungen'));
    }

    private function get_zertifizierungen_loop_html(): string
    {
        return CRM_Pdf_Presenter::render_zertifizierungen_loop($this->post_id);
    }

    public function get_form_zertifizierungen_loop_html(): string
    {
        return CRM_Pdf_Presenter::render_form_zertifizierungen_loop($this->get_certifications_from_form_field());
    }

    public function get_gesamt_kosten_html(): string
    {
        return CRM_Pdf_Presenter::render_gesamt_kosten($this);
    }

    /**
     * Generates an HTML unordered list from an array of prerequisites.
     * Delegiert an CRM_Pdf_Presenter.
     *
     * @return string The generated HTML list, or an empty string if no prerequisites are provided.
     */
    private function get_voraussetzungen_list_html(): string
    {
        return CRM_Pdf_Presenter::render_voraussetzungen_list($this->voraussetzungen);
    }

    public function get_contact_info_html(): string
    {
        return CRM_Pdf_Presenter::render_contact_info($this);
    }

    private function get_ps_html(): string
    {
        return CRM_Pdf_Presenter::render_ps((string)$this->get_crm_field('Angebot PS'));
    }

    private function _setExpireDate()
    {
        $fourteen_days_from_now_YMD = date('Y-m-d', strtotime('+14 days'));
        $start_date_formatted_YMD = $this->start_datum ? date('Y-m-d', strtotime($this->start_datum)) : null;
        $expire_date_YMD = $fourteen_days_from_now_YMD;
        if (!empty($start_date_formatted_YMD) && $start_date_formatted_YMD < $expire_date_YMD) {
            $expire_date_YMD = $start_date_formatted_YMD;
        }
        $this->expire = date('d.m.Y', strtotime($expire_date_YMD));
    }

    public function get_kursgebuehr_html(): string
    {
        return CRM_Pdf_Presenter::render_kursgebuehr($this);
    }

    private function get_signature_html(): string
    {
        $name  = !empty($this->company_management) ? $this->company_management : 'Mag. Dr. Johannes Gasberger';
        $title = 'Geschäftsführer | ' . (!empty($this->company_name) ? $this->company_name : 'X SIEBEN Wirtschaftstraining GmbH');
        return CRM_Pdf_Presenter::render_signature($this->signatur_icon, $name, $title);
    }

    public function get_diplom_success(): ?string
    {
        if (!empty($this->diplom_success)) {
            return $this->diplom_success;
        }

        // Hole den Wert aus WPForms Feld 100 (Abschluss Erfolg) mit Fallback auf 101
        $status_string = $this->get_wpforms_field_by_id(100);
        if (empty($status_string)) {
            $status_string = $this->get_wpforms_field_by_id(101);
        }

        if (empty($status_string)) {
            return null;
        }

        // Nur den ersten nicht-leeren Eintrag nehmen
        $lines = explode("\n", (string)$status_string);
        $raw_val = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $raw_val = $line;
                break;
            }
        }

        if (empty($raw_val)) {
            return null;
        }

        $lower = mb_strtolower($raw_val, 'UTF-8');
        if (strpos($lower, 'ausgezeichnet') !== false) {
            return 'mit ausgezeichnetem Erfolg';
        } elseif (strpos($lower, 'sehr gut') !== false) {
            return 'mit sehr gutem Erfolg';
        } elseif (strpos($lower, 'gut') !== false) {
            return 'mit gutem Erfolg';
        } elseif (strpos($lower, 'erfolg') !== false) {
            return 'erfolgreich';
        }

        return $raw_val;
    }

    /**
     * Generates a formatted HTML title block.
     * Delegiert an CRM_Pdf_Presenter zur Gewährleistung von Separation of Concerns (SoC).
     *
     * @param string $title The title (e.g., 'Exklusive Zusatzleistungen').
     * @param string|null $prefix  (e.g., 'Anhang 1').
     * @return string The HTML table string for the appendix title.
     */
    public function get_pdf_title(string $title, ?string $prefix = null): string
    {
        return CRM_Pdf_Presenter::render_pdf_title($title, $prefix);
    }
}
