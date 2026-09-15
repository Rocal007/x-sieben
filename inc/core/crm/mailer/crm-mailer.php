<?php 
/**
 * Display a PDF preview with an email editor in WP Admin.
 *
 * @param string $pdf_url    URL to the PDF file.
 * @param int    $course_id Course post ID (for edit link).
 * @param int    $entry_id  Optional. WPForms entry ID (for edit entry link).
 */
if (!function_exists('x_sieben_pdf_mailer')) {
function x_sieben_pdf_mailer($pdf_url, $course_id, $entry_id)
{

    // Define the default email content
    $default_subject = 'Angebot für Ihren Kurs';
    $default_body = 'Sehr geehrte/r Kund/in,

im Anhang finden Sie das Angebot für den Kurs.

Mit freundlichen Grüßen,
Ihr Team';

    // Container
    echo '<div id="x-sieben-container" class="wp-clearfix" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 20px;">';

    // Left: Email Editor Form
    echo '<div id="x-sieben-email-editor" style="flex: 0 0 50%;">'; // Adjust flex basis for the form
    echo '<h2 class="title">E-Mail verfassen</h2>';
    echo '<form method="post" action="">';
    
    // Nonce for security
    wp_nonce_field('send_pdf_email', 'x_sieben_email_nonce');

    // To Field (can be dynamically populated with customer data)
    echo '<p>';
    echo '<label for="x_sieben_recipient">Empfänger-E-Mail:</label>';
    echo '<input type="email" name="x_sieben_recipient" id="x_sieben_recipient" class="regular-text" style="width: 100%;" required />';
    echo '</p>';

    // Subject Field
    echo '<p>';
    echo '<label for="x_sieben_subject">Betreff:</label>';
    echo '<input type="text" name="x_sieben_subject" id="x_sieben_subject" class="regular-text" style="width: 100%;" value="' . esc_attr($default_subject) . '" required />';
    echo '</p>';

    // Body Field (TinyMCE)
    echo '<p>';
    echo '<label for="x_sieben_body">Nachricht:</label>';
    echo '<textarea name="x_sieben_body" id="x_sieben_body" class="large-text" rows="10" style="width: 100%;">' . esc_textarea($default_body) . '</textarea>';
    echo '</p>';
    
    // Hidden field to pass PDF URL for processing
    echo '<input type="hidden" name="x_sieben_pdf_url" value="' . esc_url($pdf_url) . '">';

    // Buttons for submission and other actions
    echo '<p style="text-align: right;">';
    echo '<input type="submit" name="x_sieben_send_email" class="button button-primary" value="E-Mail senden" />';
    echo '</p>';

    echo '</form>';
    echo '</div>'; // end email editor

    // Right: Action Buttons
    echo '<div id="x-sieben-button-row" style="flex: 1; display: flex; flex-direction: column; justify-content: flex-start; gap: 10px; align-items: flex-end; margin-top: 70px">';

    // Download Button
    echo '<a href="' . esc_url($pdf_url) . '" download class="button button-primary">PDF herunterladen</a>';

    // Edit Entry Button (admins only)
    $edit_url = admin_url('admin.php?page=wpforms-entries&view=edit&entry_id=' . absint($entry_id));
    echo '<a href="' . esc_url($edit_url) . '" id="x-sieben-edit-entry" class="button button-red">Kundendaten bearbeiten</a>';

    // Edit Course Button (green)
    $edit_url_post = get_edit_post_link($course_id);
    echo '<a href="' . esc_url($edit_url_post) . '" class="button button-primary x-sieben-success">Kurs bearbeiten</a>';

    echo '</div>'; // end button row
    echo '</div>'; // end container
    
}
}
