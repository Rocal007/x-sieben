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

    // 1. Generate Offer 1 (Basis) PDF
    $offer_pdf_url = xsieben_offer_pdf($entry_id, $course_id, false, $offer_sections, 'basis');

    // 1b. Check if course has certification option: if so, also generate Offer 2 (inkl. Zertifizierung)
    $resolved_cert = function_exists('crm_resolve_course_certification') ? crm_resolve_course_certification($entry_id, $course_id, 'angebot_2') : [];
    $offer_2_url = '';
    if (!empty($resolved_cert)) {
        $offer_2_url = xsieben_offer_pdf($entry_id, $course_id, false, null, 'mit_zertifikat', $resolved_cert);
    }

    // 1c. Check if course has offer 3 option (e.g. Scrum Lehrgang with IPMA Level D)
    $offer_3_url = '';
    if (function_exists('crm_course_has_offer_3') && crm_course_has_offer_3($course_id)) {
        $offer_3_url = xsieben_offer_pdf($entry_id, $course_id, false, null, 'angebot_3');
    }

    // 2. Generate Course Times Confirmation PDF
    $kb_pdf_url = xsieben_kurszeitenbestaetigung_pdf($entry_id, $course_id, false, $kb_sections);

    if ($output_to_browser) {
        x_sieben_pdf_preview($offer_pdf_url, $course_id, $entry_id, 'xsieben_angebot_und_kurszeiten', $kb_pdf_url);
    } else {
        return [
            'offer_pdf_url'   => $offer_pdf_url,
            'offer_2_pdf_url' => $offer_2_url,
            'offer_3_pdf_url' => $offer_3_url,
            'kb_pdf_url'      => $kb_pdf_url,
        ];
    }
}
