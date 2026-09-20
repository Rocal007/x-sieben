<?php
/**
 * CRM Settings & Preview AJAX Controller
 *
 * Behandelt alle asynchronen Anfragen der CRM-Einstellungen:
 * - Dynamisches Hinzufügen von Bausteinen
 * - Individuelles Speichern von Bausteinen & Vorlagen
 * - Generierung der asynchronen PDF-Live-Vorschau
 * - Rendering des isolierten HTML-E-Mail-Vorschau-Frames
 * - Testversand von E-Mail-Vorschauen
 * - Speichern von E-Mail-Betreffzeilen
 *
 * Teil des autarken Subprojekts inc/core/crm/
 * @version 2.18.16
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX handler to add a new field editor dynamically.
 */
add_action('wp_ajax_crm_add_field_editor', 'crm_add_field_editor_ajax_handler');
function crm_add_field_editor_ajax_handler()
{
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    $index      = intval($_POST['index'] ?? 0);
    $category   = sanitize_text_field($_POST['category'] ?? 'email');
    $email_type = sanitize_key($_POST['email_type'] ?? 'full_email');
    if (!in_array($category, ['email', 'pdf'], true)) {
        $category = 'email';
    }

    if ($category === 'email') {
        $default_title = ($email_type === 'component') ? 'Neuer E-Mail Baustein' : 'Neue E-Mail Vorlage';
    } else {
        $default_title = 'Neuer PDF Baustein';
    }

    if (function_exists('crm_render_editor_field')) {
        crm_render_editor_field($index, $default_title, '', $category, $email_type);
    }
    wp_die();
}

/**
 * AJAX handler to save a single field individually.
 */
add_action('wp_ajax_crm_save_field_individual', 'crm_save_field_individual_ajax_handler');
function crm_save_field_individual_ajax_handler()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $nonce = $_POST['nonce'] ?? ($_REQUEST['nonce'] ?? '');
    $nonce_valid = false;
    if (!empty($nonce)) {
        if (wp_verify_nonce($nonce, 'save_crm_field_individual') || wp_verify_nonce($nonce, 'crm_ajax_nonce') || wp_verify_nonce($nonce, 'save_crm_settings')) {
            $nonce_valid = true;
        }
    }

    if (!$nonce_valid) {
        wp_send_json_error(['message' => 'Invalid nonce']);
    }

    $index      = intval($_POST['index'] ?? 0);
    $title      = sanitize_text_field($_POST['title'] ?? '');
    $content    = wp_kses_post($_POST['content'] ?? '');
    $category   = sanitize_text_field($_POST['category'] ?? 'email');
    $email_type = sanitize_key($_POST['email_type'] ?? '');

    if (!in_array($category, ['email', 'pdf'], true) && function_exists('crm_get_field_category')) {
        $category = crm_get_field_category(['title' => $title]);
    }
    if ($category === 'email' && !in_array($email_type, ['full_email', 'component'], true) && function_exists('crm_get_email_field_type')) {
        $email_type = crm_get_email_field_type($title);
    }

    $fields = get_option('crm_custom_fields', []);
    if (!is_array($fields)) {
        $fields = [];
    }

    $fields[$index] = [
        'title'      => $title,
        'content'    => $content,
        'category'   => $category,
        'email_type' => $email_type,
    ];

    update_option('crm_custom_fields', $fields);

    $usage = function_exists('crm_get_field_usage_info') ? crm_get_field_usage_info($title) : [];
    wp_send_json_success([
        'message'    => __('Field saved successfully.', 'custom-crm'),
        'usage'      => $usage,
        'email_type' => $email_type,
        'type_badge' => ($email_type === 'full_email' ? '📧 Gesamte E-Mail' : '🧩 Komponente'),
    ]);
}

/**
 * AJAX handler to generate and return a PDF preview URL.
 */
add_action('wp_ajax_crm_get_pdf_preview_url', 'crm_get_pdf_preview_url_ajax_handler');
function crm_get_pdf_preview_url_ajax_handler()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $nonce = $_POST['nonce'] ?? ($_REQUEST['nonce'] ?? '');
    $nonce_valid = false;
    if (!empty($nonce)) {
        if (wp_verify_nonce($nonce, 'crm_pdf_preview_nonce') || wp_verify_nonce($nonce, 'crm_ajax_nonce') || wp_verify_nonce($nonce, 'save_crm_field_individual') || wp_verify_nonce($nonce, 'save_crm_settings')) {
            $nonce_valid = true;
        }
    }

    if (!$nonce_valid) {
        wp_send_json_error(['message' => __('Ungültige Sicherheitsprüfung (Nonce).', 'custom-crm')]);
    }

    $doc_type = sanitize_key($_POST['doc_type'] ?? 'angebot');
    $sample   = function_exists('crm_get_preview_sample_data') ? crm_get_preview_sample_data() : ['entry_id' => null, 'course_id' => null];

    $entry_id  = $sample['entry_id'] ?? null;
    $course_id = $sample['course_id'] ?? null;

    if (!$entry_id || !$course_id) {
        wp_send_json_error(['message' => __('Keine Beispieldaten (Kurs oder Anfrage) in der Datenbank gefunden.', 'custom-crm')]);
    }

    require_once dirname(__DIR__) . '/crm-model.php';
    require_once dirname(__DIR__) . '/helpers/crm-pdf-sections.php';

    $prev_error_reporting = error_reporting(0);
    ob_start();

    // Ermitteln der Abschnitte für die Vorschau:
    // Auf der globalen Einstellungsseite (keine spezifische entry_id via POST) immer die globale
    // Struktur verwenden, damit individuelle Overrides des Muster-Datensatzes die globale Vorschau nicht verfälschen!
    $requested_entry_id = !empty($_POST['entry_id']) ? intval($_POST['entry_id']) : null;
    $preview_sections   = crm_get_pdf_section_order($doc_type, $requested_entry_id);

    $url = '';
    $base_dir = dirname(__DIR__);
    try {
        switch ($doc_type) {
            case 'kb':
                require_once $base_dir . '/pdf/kurszeitenbestaetigung.php';
                $url = xsieben_kurszeitenbestaetigung_pdf($entry_id, $course_id, false, $preview_sections);
                break;

            case 'tb':
                require_once $base_dir . '/pdf/teilnamebestaetigung.php';
                $url = xsieben_teilnahmebestaetigung_pdf($entry_id, $course_id, false, $preview_sections);
                break;

            case 'diplom':
                require_once $base_dir . '/pdf/diplom.php';
                $url = xsieben_diplom_pdf($entry_id, $course_id, false, null, $preview_sections);
                break;

            case 'angebot':
                require_once $base_dir . '/pdf/offer.php';
                $url = xsieben_offer_pdf($entry_id, $course_id, false, $preview_sections);
                break;

            case 'angebot_2':
                require_once $base_dir . '/pdf/offer.php';
                $url = xsieben_offer_pdf($entry_id, $course_id, false, $preview_sections, 'mit_zertifikat');
                break;

            case 'invoice':
                require_once $base_dir . '/pdf/invoice.php';
                $url = xsieben_invoice_pdf($entry_id, $course_id, false, $preview_sections);
                break;

            default:
                $url = '';
        }
    } catch (\Throwable $e) {
        error_log('CRM PDF Preview Error: ' . $e->getMessage());
    }

    ob_end_clean();
    error_reporting($prev_error_reporting);

    if (!empty($url)) {
        wp_send_json_success([
            'url'         => $url,
            'doc_type'    => $doc_type,
            'new_nonce'   => wp_create_nonce('crm_pdf_preview_nonce'),
            'sample_info' => sprintf(
                __('Musterdaten: Kurs #%d (%s) & Anfrage #%d (%s)', 'custom-crm'),
                $course_id,
                $sample['course_title'] ?? '',
                $entry_id,
                $sample['client_name'] ?? ''
            ),
        ]);
    } else {
        wp_send_json_error(['message' => __('Das PDF konnte nicht gerendert werden.', 'custom-crm')]);
    }
}

/**
 * AJAX handler to render the standalone HTML preview frame for E-Mails.
 */
add_action('wp_ajax_crm_render_email_preview_frame', 'crm_render_email_preview_frame_ajax_handler');
function crm_render_email_preview_frame_ajax_handler()
{
    if (!current_user_can('manage_options')) {
        status_header(403);
        wp_die('Unauthorized');
    }

    $doc_type = sanitize_key($_GET['doc_type'] ?? 'angebot');
    $sample   = function_exists('crm_get_preview_sample_data') ? crm_get_preview_sample_data() : [];

    $entry_id  = !empty($sample['entry_id']) ? $sample['entry_id'] : null;
    $course_id = !empty($sample['course_id']) ? $sample['course_id'] : null;

    require_once dirname(__DIR__) . '/helpers/crm-email-sections.php';

    $html = function_exists('crm_get_email_preview_html') ? crm_get_email_preview_html($doc_type, $entry_id, $course_id) : '';

    header('Content-Type: text/html; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow');
    echo $html;
    exit;
}

/**
 * AJAX handler to get E-Mail preview JSON data.
 */
add_action('wp_ajax_crm_get_email_preview_data', 'crm_get_email_preview_data_ajax_handler');
function crm_get_email_preview_data_ajax_handler()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $doc_type = sanitize_key($_POST['doc_type'] ?? 'angebot');
    $sample   = function_exists('crm_get_preview_sample_data') ? crm_get_preview_sample_data() : [];

    $entry_id  = !empty($sample['entry_id']) ? $sample['entry_id'] : null;
    $course_id = !empty($sample['course_id']) ? $sample['course_id'] : null;

    require_once dirname(__DIR__) . '/helpers/crm-email-sections.php';

    $html = function_exists('crm_get_email_preview_html') ? crm_get_email_preview_html($doc_type, $entry_id, $course_id) : '';

    wp_send_json_success([
        'doc_type'    => $doc_type,
        'html'        => $html,
        'sample_info' => sprintf(
            __('Musterdaten: Kurs #%d (%s) & Anfrage #%d (%s)', 'custom-crm'),
            $course_id ?: 0,
            $sample['course_title'] ?? 'Musterkurs',
            $entry_id ?: 0,
            $sample['client_name'] ?? 'Musterteilnehmer'
        ),
    ]);
}

/**
 * AJAX handler to send a preview E-Mail to a specified test address.
 */
add_action('wp_ajax_crm_send_email_preview_test', 'crm_send_email_preview_test_ajax_handler');
function crm_send_email_preview_test_ajax_handler()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $doc_type  = sanitize_key($_POST['doc_type'] ?? 'angebot');
    $recipient = sanitize_email($_POST['recipient'] ?? '');

    if (!is_email($recipient)) {
        wp_send_json_error(['message' => __('Ungültige E-Mail-Adresse.', 'custom-crm')]);
    }

    $sample    = function_exists('crm_get_preview_sample_data') ? crm_get_preview_sample_data() : [];
    $entry_id  = !empty($sample['entry_id']) ? $sample['entry_id'] : null;
    $course_id = !empty($sample['course_id']) ? $sample['course_id'] : null;

    require_once dirname(__DIR__) . '/helpers/crm-email-sections.php';

    $html = function_exists('crm_get_email_preview_html') ? crm_get_email_preview_html($doc_type, $entry_id, $course_id) : '';

    $titles = [
        'angebot'    => 'Kursangebot & Beratung',
        'kb'         => 'Kurszeitenbestätigung',
        'angebot_kb' => 'Kursangebot & Kurszeiten (Kombi)',
        'anmeldung'  => 'Anmeldebestätigung',
        'tb'         => 'Teilnahmebestätigung',
        'diplom'     => 'Diplom / Zertifikat',
        'invoice'    => 'Honorarnote / Rechnung',
    ];

    $doc_label = $titles[$doc_type] ?? strtoupper($doc_type);
    $course_label = !empty($sample['course_title']) ? $sample['course_title'] : 'Musterkurs';
    $subject   = sprintf('[TEST-VORSCHAU] X SIEBEN %s — %s', $doc_label, $course_label);

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: X SIEBEN Wirtschaftstraining <office@x-sieben.at>',
        'Reply-To: X SIEBEN Backoffice <office@x-sieben.at>',
    ];

    $sent = wp_mail($recipient, $subject, $html, $headers);

    if ($sent) {
        wp_send_json_success([
            'message' => sprintf(__('Test-Mail für "%s" wurde erfolgreich an %s gesendet.', 'custom-crm'), $doc_label, $recipient),
        ]);
    } else {
        wp_send_json_error([
            'message' => __('wp_mail konnte die E-Mail nicht versenden. Bitte Mailserver-Konfiguration prüfen.', 'custom-crm'),
        ]);
    }
}

/**
 * AJAX handler to save an email template subject individually.
 */
add_action('wp_ajax_crm_save_email_subject', 'crm_save_email_subject_ajax_handler');
function crm_save_email_subject_ajax_handler()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }

    $nonce = $_POST['nonce'] ?? '';
    if (!wp_verify_nonce($nonce, 'crm_ajax_nonce') && !wp_verify_nonce($nonce, 'save_crm_settings') && !wp_verify_nonce($nonce, 'crm_email_preview_nonce')) {
        wp_send_json_error(['message' => 'Sicherheitsüberprüfung fehlgeschlagen.']);
    }

    $doc_type = sanitize_key($_POST['doc_type'] ?? '');
    $subject  = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));

    if (empty($doc_type)) {
        wp_send_json_error(['message' => 'Ungültiger Vorlagentyp.']);
    }

    require_once dirname(__DIR__) . '/helpers/crm-email-sections.php';
    if (function_exists('crm_save_email_subject_template')) {
        crm_save_email_subject_template($doc_type, $subject);
    } else {
        update_option('crm_email_subject_' . $doc_type, $subject);
    }

    $cache_res = function_exists('crm_on_partial_cache_update')
        ? crm_on_partial_cache_update('email_subject_' . $doc_type)
        : [];

    wp_send_json_success([
        'message'  => __('Betreffzeile erfolgreich gespeichert.', 'custom-crm'),
        'doc_type' => $doc_type,
        'subject'  => $subject,
        'js_cache' => $cache_res,
    ]);
}
