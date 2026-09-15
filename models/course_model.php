<?php
class COURSE_Model
{
    public $post_id;

    // Basis-Infos
    public $title, $titel_short, $excerpt, $untertitel, $permalink;
    public $start_datum, $end_datum, $einstieg_datum;
    public $preis_netto, $preis_brutto, $le_single, $kosten;

    // Adressdaten
    public $strasse, $addresszusatz, $plz, $ort_name, $ort_infos;

    // Kursdetails
    public $angebot_beschreibung, $anzahl_le, $uebungseinheiten;
    public $kurstyp, $abschluss, $kursart, $kurszeiten, $selbststudium;
    public $termin_auf_anfrage, $fernlehre_ohne_praesenz, $barrierefreier_zugang, $kinderbetreuung, $spezielles_uebungsangebot;
    public $teilnehmer_min, $seminarplatze, $unterrichtssprache, $lehrmethode, $ermaessigungen;

    // Trainer & Inhalte
    public $trainer = [];
    public $modules = [];
    public $inhalte = [];

    // Zertifizierungen
    public $zertifizierungen = [];
    public $zertifizierungen_images = [];

    // Kampagne & Klassifizierungen
    public $education_term, $isced_kategorie, $nqr_kategorie, $kampagne;

    // Kontaktperson
    public $kontakt = [];

    // Logos (aus externer Klasse)
    public $logos = [];

    // Sonstiges
    public $zielgruppe, $termine_pdf, $ps, $phone_number;

    public function __construct(int $post_id)
    {
        $this->post_id = $post_id;

        // Basisdaten
        $this->title = esc_html(get_the_title($post_id));
        $this->titel_short = get_field('title_im_slider', $post_id);
        $this->untertitel = get_post_meta($post_id, "untertitel", true);
        $this->permalink = get_permalink($post_id);
        $this->excerpt = has_excerpt($post_id) ? get_the_excerpt($post_id) : wp_trim_words(get_post_field('post_content', $post_id), 20, '…');

        // Adressdaten
        $this->strasse = get_post_meta($post_id, "strasse", true);
        $this->addresszusatz = trim(get_post_meta($post_id, "addresszusatz", true));
        $this->plz = get_post_meta($post_id, "plz", true);
        $this->ort_name = get_post_meta($post_id, "ort", true);
        $this->ort_infos = trim(get_post_meta($post_id, "ort_infos", true));

        // Datum und Preise
        $this->start_datum = $this->format_date_meta('start_datum');
        $this->end_datum = $this->format_date_meta('end_datum');
        $this->einstieg_datum = $this->format_date_meta('einstieg_datum');

        $tempKosten = get_post_meta($post_id, "kosten", true);
        $this->preis_netto = (float) $tempKosten;
        $this->preis_brutto = !empty($tempKosten) ? round((float)$tempKosten * 1.2, 2) : 0;
        $this->kosten = str_replace(".", ",", (string)$this->preis_brutto);

        $this->anzahl_le = (int)get_post_meta($post_id, 'lehreinheiten_gesamt', true);
        $this->uebungseinheiten = trim((string)$this->anzahl_le);
        $this->le_single = $this->anzahl_le > 0 ? number_format($this->preis_brutto / $this->anzahl_le, 2) : 0;

        // Inhalte
        $this->angebot_beschreibung = get_post_meta($post_id, 'angebot_beschreibung', true);
        $this->modules = $this->get_modules();
        $this->inhalte = $this->get_inhalte_dyn();

        // Trainer
        $this->trainer = $this->get_trainers();

        // Zertifizierungen
        $this->zertifizierungen_images = $this->get_zertifizierungen_images();
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

        // Kursarten
        $this->kursart = get_post_meta($post_id, 'tages_abend_wochenende_', true);
        $this->kurszeiten = $this->build_days(get_field("kurszeiten", $post_id));
        $this->selbststudium = $this->build_days(get_field("selbstudium", $post_id), true);

        // Typ & Abschluss
        $this->kurstyp = $this->get_coursetype();
        $this->abschluss = get_post_meta($post_id, "zertifikat", true);

        // Zielgruppe & PDF
        $this->zielgruppe = sanitize_text_field(get_field('teilnehmeruberblick', $post_id));
        $this->termine_pdf = get_field('kurszeiten_details_pdf', $post_id);

        // Booleans & Teilnehmer
        $this->termin_auf_anfrage = (int)get_post_meta($post_id, "termin_auf_anfrage", true);
        $this->fernlehre_ohne_praesenz = (int)(get_post_meta($post_id, "fernlehre_ohne_praesenz", true) ?: 1);
        $this->barrierefreier_zugang = (int)get_post_meta($post_id, "barrierefreier_zugang", true);
        $this->kinderbetreuung = (int)get_post_meta($post_id, "kinderbetreuung", true);
        $this->spezielles_uebungsangebot = (int)get_post_meta($post_id, "spezielles_uebungsangebot", true);
        $this->teilnehmer_min = (int)(get_post_meta($post_id, "teilnehmer_min", true) ?: 1);
        $this->seminarplatze = get_post_meta($post_id, "seminarplatze", true);
        $this->unterrichtssprache = trim((string)get_post_meta($post_id, "unterrichtssprache", true));
        $this->lehrmethode = trim((string)get_post_meta($post_id, "lehrmethode", true));
        $this->ermaessigungen = trim((string)get_post_meta($post_id, "ermaessigungen", true));

        // Klassifizierungen
        $this->education_term = get_post_meta($post_id, "waff_number", true);
        $this->isced_kategorie = trim(get_post_meta($post_id, "isced_kategorie", true));
        $this->nqr_kategorie = trim(get_post_meta($post_id, "nqr_kategorie", true));
        $this->kampagne = !empty(trim(get_post_meta($post_id, "kampagne", true))) ? trim(get_post_meta($post_id, "kampagne", true)) : 'elearn,digi';

        // Kontaktperson
        $this->kontakt = [
            'titel' => trim(get_post_meta($post_id, "kontakt_titel", true)),
            'vorname' => trim(get_post_meta($post_id, "kontakt_vorname", true)),
            'nachname' => trim(get_post_meta($post_id, "kontakt_nachname", true)),
            'titel_nachgestellt' => trim(get_post_meta($post_id, "kontakt_titel_nachgestellt", true)),
            'funktion' => trim(get_post_meta($post_id, "kontakt_funktion", true)),
            'telefon' => trim(get_post_meta($post_id, "kontakt_telefon", true)),
        ];

        // Logos laden aus externer Klasse
        $logos = new COURSE_Logos();
        $this->logos = $logos->get_all();

        // Misc
        $this->ps = $this->get_ps_html();
        $this->phone_number = get_theme_mod('xsieben_telefon', '0800 700 170');
    }

    private function format_date_meta($meta_key): string
    {
        $raw = get_post_meta($this->post_id, $meta_key, true);
        return $raw ? date('d.m.Y', strtotime($raw)) : '';
    }

    private function build_days($days_array, $suffix = false): array
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

    private function get_modules(): array
    {
        $modules = [];
        if (have_rows('module', $this->post_id)) {
            while (have_rows('module', $this->post_id)) {
                the_row();
                $modules[] = [
                    'nr' => get_sub_field('modul'),
                    'titel' => get_sub_field('modul_titel'),
                    'le' => get_sub_field('anzahl_le'),
                ];
            }
        }
        return $modules;
    }

    private function get_inhalte_dyn(string $accordion_title = 'Inhalte'): array
    {
        $accordion_data = get_post_meta($this->post_id, 'courses_accordion', true);
        $content = $this->get_accordion_content_by_title($accordion_data, $accordion_title);
        return $content ? $this->splitByModul($content) : [];
    }

    private function get_accordion_content_by_title($accordionData, $partialTitle)
    {
        if (!is_array($accordionData)) return null;
        foreach ($accordionData as $item) {
            if (isset($item['title']) && stripos($item['title'], $partialTitle) !== false) {
                return $item['content'];
            }
        }
        return null;
    }

    private function splitByModul(string $content): array
    {
        $pattern = '/(?=modul\s*\d+\b(?![\)\.\w]))/i';
        $parts = preg_split($pattern, $content, -1, PREG_SPLIT_NO_EMPTY);
        return array_map('trim', $parts);
    }

    private function get_trainers(): array
    {
        $ids = get_post_meta($this->post_id, 'vortragende', true);
        if (empty($ids) || !is_array($ids)) return [];

        $query = new WP_Query([
            'post__in' => $ids,
            'post_type' => 'members',
            'posts_per_page' => -1,
            'orderby' => 'post__in',
        ]);

        $trainers = [];
        while ($query->have_posts()) {
            $query->the_post();
            $trainers[] = [
                'id' => get_the_ID(),
                'name' => get_the_title(),
                'permalink' => get_permalink(),
            ];
        }
        wp_reset_postdata();
        return $trainers;
    }

    private function get_zertifizierungen_images(): array
    {
        $images = [];
        $ca_meta = get_post_meta($this->post_id, "zertifikate", true);
        if (!empty($ca_meta) && is_array($ca_meta)) {
            $query = new WP_Query([
                "post__in" => $ca_meta,
                "post_type" => 'ca',
                "posts_per_page" => -1
            ]);
            while ($query->have_posts()) {
                $query->the_post();
                if (get_the_ID() != 5793) {
                    $thumb = get_the_post_thumbnail_url(null, 'thumbnail');
                    if ($thumb) {
                        $images[] = $thumb;
                    }
                }
            }
            wp_reset_postdata();
        }
        return $images;
    }

    private function get_ps_html()
    {
        return get_post_meta($this->post_id, 'ps', true);
    }

    private function get_coursetype(): string
    {
        if (has_term('Lehrgang', 'coursecategory', $this->post_id)) return 'Lehrgang';
        if (has_term('Seminar', 'coursecategory', $this->post_id)) return 'Seminar';
        if (has_term('Crashkurs', 'coursecategory', $this->post_id)) return 'Crashkurs';
        if (has_term('Bundle', 'coursecategory', $this->post_id)) return 'Bundle';
        if (has_term('eLearning', 'coursecategory', $this->post_id)) return 'eLearning';
        if (has_term('Blended Learning', 'coursecategory', $this->post_id)) return 'Blended-learning';
        return 'LEHRGANG';
    }
}
class COURSE_Logos
{
    private $assets_url;

    public function __construct()
    {
        $this->assets_url = get_template_directory_uri() . '/inc/core/crm/assets/';
    }

    public function get_all(): array
    {
        return [
            'web' => $this->logo('kontakt.png', 25, 'Webseite'),
            'mail' => $this->logo('email.png', 25, 'E-Mail'),
            'fax' => $this->logo('fax.png', 25, 'Fax'),
            'phone' => $this->logo('tel.png', 25, 'Telefon'),
            'calender' => $this->logo('kalender.png', 25, 'Kalender'),
            'ort' => $this->logo('ort.png', 25, 'Ort'),
            'abschluss' => $this->logo('abschluss.png', 25, 'Abschluss'),
            'diplom' => $this->logo('diplom.png', 25, 'Diplom'),
            'danger' => $this->logo('danger.png', 25, 'Hinweis'),
            'sitting' => $this->logo('sitting.png', 25, 'Garantie'),
            'proven' => $this->logo('proven.png', 25, 'Proven Expert Bewertungen'),
            'proven_wide' => $this->logo('proven_wide.png', null, 'Proven Expert'),
            'share' => $this->logo('share.png', 25, 'Teilen'),
            'wba' => $this->logo('wba-1.png', 100, 'WBA Logo'),
            'cert_noe' => $this->logo('cert-1.png', 100, 'Cert NÖ Logo'),
            'tuef' => $this->logo('tuef.png', null, 'TÜV Logo'),
            'sys_zert' => $this->logo('system-1.png', null, 'System Zert Logo'),
            'pma' => $this->logo('PMA-1.png', null, 'PMA Logo'),
            'ipma' => $this->logo('impa.png', null, 'IPMA Logo'),
            'email_logos' => $this->logo('email_zerts.png', 100, 'Email Zertifikate', 'padding-left: 54px;'),
            'ams' => $this->logo('ams.png', 160, 'AMS Logo'),
            'signatur' => $this->logo('Signatur_Blau.png', 180, 'Signatur'),
            'xsieben' => $this->logo('xsieben_logo.png', 200, 'X-Sieben Logo'),
        ];
    }
	
public function render(string $key): string
    {
        $logos = $this->get_all();
        if (!isset($logos[$key])) {
            return '';
        }
        $logo = $logos[$key];

        $width = !empty($logo['width']) ? ' width="' . intval($logo['width']) . '"' : '';
        $style = !empty($logo['style']) ? ' style="' . esc_attr($logo['style']) . '"' : '';

        return sprintf(
            '<img src="%s" alt="%s"%s%s>',
            esc_url($logo['src']),
            esc_attr($logo['alt']),
            $width,
            $style
        );
    }

    private function logo(string $file, ?int $width, string $alt, string $style = null): array
    {
        return [
            'src'   => $this->assets_url . $file,
            'width' => $width,
            'alt'   => $alt,
            'style' => $style,
        ];
    }
}


class COURSE_Registry
{
    private static array $courses = [];

    public static function get(int $post_id): COURSE_Model
    {
        // 1️⃣ Prüfen, ob schon im Runtime Cache
        if (isset(self::$courses[$post_id])) {
            return self::$courses[$post_id];
        }

        // 2️⃣ Prüfen, ob im WP Object Cache
        $cache_key = 'course_model_' . $post_id;
        $cached = wp_cache_get($cache_key, 'course_models');
        if ($cached instanceof COURSE_Model) {
            self::$courses[$post_id] = $cached;
            return $cached;
        }

        // 3️⃣ Wenn nicht vorhanden, neu laden
        $course = new COURSE_Model($post_id);

        // 4️⃣ In WP Object Cache speichern (10 Minuten TTL)
        wp_cache_set($cache_key, $course, 'course_models', 600);

        // 5️⃣ In Runtime Cache speichern
        self::$courses[$post_id] = $course;

        return $course;
    }
}

