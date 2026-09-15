<?php
class COURSE_Model
{
    public $post_id;

    // Basis-Infos
    public $title, $titel_short, $excerpt, $untertitel, $permalink;
    public $start_datum, $end_datum, $einstieg_datum;
    public $preis_netto, $preis_brutto, $le_single, $kosten, $preis_netto_formatted, $preis_brutto_formatted;

    // Adressdaten
    public $strasse, $addresszusatz, $plz, $ort_name, $ort_infos;

    // Kursdetails
    public $angebot_beschreibung, $anzahl_le, $uebungseinheiten;
    public $kurstyp, $abschluss, $kursart, $kurszeiten, $selbststudium;
    public $termin_auf_anfrage, $fernlehre_ohne_praesenz, $barrierefreier_zugang, $kinderbetreuung, $spezielles_uebungsangebot;
    public $teilnehmer_min, $seminarplatze, $unterrichtssprache, $lehrmethode, $ermaessigungen;
    public $video;
    public $content;

    // Trainer & Inhalte
    public $trainer = [];
    public $modules = [];
    public $inhalte = [];
    public $related_courses = [];
    public $desktop_image_url;
    public $mobile_image_url;
    public $image_3col;

    public $large_desktop_url;
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
    public $zielgruppe, $termine_pdf, $phone_number;

    public $meta_data = [];

    public array $sidebar_labels = [
        'abschluss' => 'ABSCHLUSS:',
        'start' => 'KURSBEGINN:',
        'end' => 'KURSENDE:',
        'dauer' => 'DAUER:',
        'kurszeiten' => 'KURSZEITEN:',
        'preis' => 'IHRE INVESTITION:',
        'ort' => 'ORT:',
        'zusatz' => '',
    ];

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

// Netto-Preis (numeric)
$this->preis_netto = !empty($tempKosten) ? (float)$tempKosten : 0;

// Brutto-Preis (numeric)
$this->preis_brutto = $this->preis_netto * 1.2;

// Formatted versions (strings for display)
$this->preis_netto_formatted = number_format($this->preis_netto, 2, ",", ".");
$this->preis_brutto_formatted = number_format($this->preis_brutto, 2, ",", ".");

// Lehreinheitspreis
$this->anzahl_le = (int)get_post_meta($post_id, 'lehreinheiten_gesamt', true);
$this->le_single = $this->anzahl_le > 0 ? number_format($this->preis_brutto / $this->anzahl_le, 2, ",", ".") : 0;

        // Inhalte
        $this->content = get_field('video', $post_id) ?: null;
        if ($this->content) {
            $raw_content = get_post_field('post_content', $post_id);
            $raw_content = preg_replace('/<img[^>]+./', '', $raw_content);
            $this->content = $this->content . apply_filters('the_content', $raw_content);
        } else {
            $this->content = apply_filters('the_content', get_post_field('post_content', $post_id));
        }

        // Related courses
        $this->related_courses = $this->load_related_courses();

        $this->angebot_beschreibung = get_post_meta($post_id, 'angebot_beschreibung', true);
        $this->modules = $this->get_modules();
        $this->inhalte = $this->get_inhalte_dyn();
        $this->desktop_image_url = get_the_post_thumbnail_url($post_id, 'hero-image');
		$this->large_desktop_url = get_the_post_thumbnail_url($post_id, 'tablet-desktop'); 
        $this->mobile_image_url  = get_the_post_thumbnail_url($post_id, 'mobile-top');
        $this->image_3col = get_the_post_thumbnail_url($post_id, 'home-3col');
   
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
        // Meta data for SEO
        $this->meta_data = [
            'yoast_title'       => $this->get_yoast_title(),
            'yoast_description' => $this->get_yoast_description(),
            'yoast_focuskw'     => get_post_meta($this->post_id, '_yoast_wpseo_focuskw', true), // optional
            'yoast_primary_cat' => get_post_meta($this->post_id, '_yoast_wpseo_primary_category', true), // optional
        ];
        // Kursarten
        $this->kursart = get_post_meta($post_id, 'tages_abend_wochenende_', true);
        $this->kurszeiten = $this->build_days(get_field("kurszeiten", $post_id));
        $this->selbststudium = $this->build_days(get_field("selbststudium", $post_id), true);

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
        $logos = new COURSE_assets();
        $this->logos = $logos->get_all();

        // Misc
        $this->phone_number = get_theme_mod('xsieben_telefon', '0800 700 170');
    }

    // ----------------------
    // Utility Methods
    // ----------------------

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
                    if ($thumb) $images[] = $thumb;
                }
            }
            wp_reset_postdata();
        }
        return $images;
    }


    private function get_coursetype(): string
    {
        $terms = ['Lehrgang', 'Seminar', 'Crashkurs', 'Bundle', 'eLearning', 'Blended Learning'];
        foreach ($terms as $term) {
            if (has_term($term, 'coursecategory', $this->post_id)) return $term;
        }
        return 'LEHRGANG';
    }

    public function xsieben_sidebar_row(string $label, string $value): void
    {
        if (trim($value) === '') return;
?>
        <div class="csei-row">
            <span class="csei-lab">
                <strong><?php echo esc_html($label); ?></strong>
                <?php echo $value; ?>
            </span>
        </div>
<?php
    }

    /**
     * Load related courses as array of ['id', 'title', 'permalink', 'thumbnail']
     */
    private function load_related_courses(): array
    {
        $related = [];
        $i = 1;

        while ($rel_id = (int) trim(get_post_meta($this->post_id, "related{$i}", true))) {
            if ($rel_id > 0) {
                $related_title = get_the_title($rel_id);
                $short_title = get_field('title_im_slider', $rel_id);
                $title_to_use = $short_title ? $short_title : $related_title;

                $related[] = [
                    'id' => $rel_id,
                    'title' => $title_to_use, // Use the short title if available
                    'permalink' => get_permalink($rel_id),
                    'desktop_image' => get_the_post_thumbnail_url($rel_id, 'hero-image'),
                    'mobile_image'  => get_the_post_thumbnail_url($rel_id, 'mobile-top'),
                ];
            }
            $i++;
        }

        return array_unique($related, SORT_REGULAR);
    }
    // ----------------------
    // Yoast Helpers
    // ----------------------
    private function get_yoast_title(): string
    {
        $yoast_title = get_post_meta($this->post_id, '_yoast_wpseo_title', true);

        if (!empty($yoast_title)) {
            return wp_strip_all_tags($yoast_title);
        }

        // fallback → WP title
        return get_the_title($this->post_id);
    }

    private function get_yoast_description(): string
    {
        $yoast_desc = get_post_meta($this->post_id, '_yoast_wpseo_metadesc', true);

        if (!empty($yoast_desc)) {
            return wp_strip_all_tags($yoast_desc);
        }

        // fallback → WP excerpt
        return has_excerpt($this->post_id)
            ? get_the_excerpt($this->post_id)
            : wp_trim_words(get_post_field('post_content', $this->post_id), 30, '…');
    }
    /**
     * Returns the preferred course title (short title if available).
     */
    public function getTitle(): string
    {
        return $this->titel_short ?: $this->title;
    }

    /**
     * Returns the featured image URL or a default if none exists.
     */
    public function getFeaturedImage(string $default = '/wp-content/uploads/2020/04/seo-scaled.jpg'): string
    {
        return $this->desktop_image_url ?: $default;
    }

    /**
     * Returns a truncated version of the subtitle or given text.
     */
    public function truncateText(string $text = '', int $length = 100): string
    {
        $text = $text ?? $this->untertitel ?? '';
        $text = mb_substr($text, 0, $length);
        return preg_replace('/\s+?(\S+)?$/u', '', $text);
    }

    /**
     * Optional: convenience method for truncated subtitle
     */
    public function getTruncatedSubtitle(int $length = 100): string
    {
        return $this->truncateText($this->untertitel, $length);
    }

    /**
     * Gibt die Accordion-Tabs des Kurses zurück, bereits aufbereitet für die Anzeige.
     *
     * @return array
     */
    public function getAccordionTabs(): array
    {
        $tabs_raw = get_post_meta($this->post_id, 'courses_accordion', true);
        $tabs = [];

        if (!empty($tabs_raw) && is_array($tabs_raw)) {
            foreach ($tabs_raw as $key => $tab) {
                $tab_title = !empty($tab['title']) ? $tab['title'] : '';
                if ($tab_title === 'Inhalte') {
                    $slider_title = get_field('title_im_slider', $this->post_id);
                    $tab_title = 'Inhalte' . (!empty($slider_title) ? ' - ' . $slider_title : '');
                }

                $tab_content = !empty($tab['content']) ? apply_filters('the_content', $tab['content']) : '';

                $tabs[] = [
                    'title'   => $tab_title,
                    'content' => $tab_content,
                ];
            }
        }

        return $tabs;
    }
    /**
     * Returns an array of coursetype term names.
     *
     * @return array
     */
    public function getCourseTypes(): array
    {
        $terms = get_the_terms($this->post_id, 'coursetype');
        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }
        return array_map(function ($term) {
            return esc_html($term->name);
        }, $terms);
    }

    /**
     * Returns an array of fieldofeducation term names.
     *
     * @return array
     */
    public function getFieldOfEducations(): array
    {
        $terms = get_the_terms($this->post_id, 'fieldofeducation');
        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }
        return array_map(function ($term) {
            return esc_html($term->name);
        }, $terms);
    }

    /**
     * Checks if the post has a featured image.
     *
     * @return bool
     */
    public function hasFeaturedImage(): bool
    {
        return has_post_thumbnail($this->post_id);
    }

    /**
     * Returns the permalink of the course.
     *
     * @return string
     */
    public function getPermalink(): string
    {
        return get_permalink($this->post_id);
    }
}
