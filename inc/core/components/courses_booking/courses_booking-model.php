<?php
class CRM_Model
{

    public $post_id;
    public $title;
    public $permalink;
    public $start_datum;
    public $end_datum;
    public $preis;
    public $title_preis;
    public $angebot_beschreibung;
    public $anzahl_le;
    public $kursart;
    public $kursart_t;
    public $kursart_a;
    public $kursart_we;
    public $voraussetzungen;
    public $kurszeiten = [];
    public $selbststudium = [];
    public $termine_pdf;
    public $zertifizierungen = [];
    public $address_components = [];

    // NEW: loaded from WPForms entry
    public $adresse_raw;

    public function __construct($post_id)
    {
        $this->post_id = $post_id;
        $this->load_course_data();
    }

    private function load_course_data()
    {
        $this->title = get_the_title($this->post_id);
        $this->permalink = get_permalink($this->post_id);
        $this->start_datum = get_post_meta($this->post_id, 'start_datum', true);
        $this->end_datum = get_post_meta($this->post_id, 'end_datum', true);
        $this->preis = get_post_meta($this->post_id, 'preis', true);
        $this->title_preis = get_post_meta($this->post_id, 'title_preis', true);
        $this->angebot_beschreibung = get_post_meta($this->post_id, 'angebot_beschreibung', true);
        $this->anzahl_le = get_post_meta($this->post_id, 'anzahl_le', true);
        $this->kursart = get_post_meta($this->post_id, 'kursart', true);
        $this->kursart_t = get_post_meta($this->post_id, 'kursart_t', true);
        $this->kursart_a = get_post_meta($this->post_id, 'kursart_a', true);
        $this->kursart_we = get_post_meta($this->post_id, 'kursart_we', true);
        $this->voraussetzungen = get_post_meta($this->post_id, 'voraussetzungen', true);
        $this->kurszeiten = get_post_meta($this->post_id, 'kurszeiten', true) ?: [];
        $this->selbststudium = get_post_meta($this->post_id, 'selbststudium', true) ?: [];
        $this->termine_pdf = get_post_meta($this->post_id, 'termine_pdf', true);
        $this->zertifizierungen = get_post_meta($this->post_id, 'zertifizierungen', true) ?: [];
        $this->address_components = get_post_meta($this->post_id, 'address_components', true) ?: [];
    }

    public function load_entry_data($entry_id)
    {
        $entry = wpforms()->entry->get($entry_id);
        if ($entry && isset($entry->fields[35]['value'])) {
            $this->adresse_raw = $entry->fields[35]['value'];
            var_dump($this->adresse_raw);
        } else {
            $this->adresse_raw = null;
        }
    }

    public function debug_output()
    {
        echo '<pre>';
        echo "CRM_Model Object:\n";
        print_r($this);
        echo '</pre>';
    }
}
