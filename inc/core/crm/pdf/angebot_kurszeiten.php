<?php

/**
 * Generates both the Offer and the Course Times Confirmation PDFs,
 * and renders the interactive dual-PDF preview or returns the generated URLs.
 *
 * @param int $entry_id
 * @param int $course_id
 * @param bool $output_to_browser
 * @param array|null $custom_sections
 * @return array|void
 */
function xsieben_angebot_kurszeiten_pdf($entry_id, $course_id, $output_to_browser = true, $custom_sections = null)
{
    require_once __DIR__ . '/offer.php';
    require_once __DIR__ . '/kurszeitenbestaetigung.php';
    require_once dirname(__DIR__) . '/controler/output-controler.php';

    $offer_sections = (is_array($custom_sections) && isset($custom_sections['angebot'])) ? $custom_sections['angebot'] : null;
    $kb_sections    = (is_array($custom_sections) && isset($custom_sections['kb'])) ? $custom_sections['kb'] : null;

    // 1. Generate Offer PDF
    $offer_pdf_url = xsieben_offer_pdf($entry_id, $course_id, false, $offer_sections);

    // 2. Generate Course Times Confirmation PDF
    $kb_pdf_url = xsieben_kurszeitenbestaetigung_pdf($entry_id, $course_id, false, $kb_sections);

    if ($output_to_browser) {
        x_sieben_pdf_preview($offer_pdf_url, $course_id, $entry_id, 'xsieben_angebot_und_kurszeiten', $kb_pdf_url);
    } else {
        return [
            'offer_pdf_url' => $offer_pdf_url,
            'kb_pdf_url'    => $kb_pdf_url,
        ];
    }
}
