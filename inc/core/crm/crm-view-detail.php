<?php 
if (!function_exists('xsieben_view_entry')) {
function xsieben_view_entry($entry_id, $course_id = 0) {
    if (!function_exists('wpforms')) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('WPForms not available.', 'custom-crm') . '</p></div>';
        return;
    }

    $entry = wpforms()->entry->get($entry_id);
    $fields = is_string($entry->fields) ? json_decode($entry->fields, true) : $entry->fields;

    if (is_array($fields)) {
        echo '<h2>' . sprintf(esc_html__('Entry Details (ID: %d)', 'custom-crm'), esc_html($entry_id)) . '</h2><table class="widefat striped"><tbody>';
        foreach ($fields as $field) {
            echo '<tr><th>' . esc_html($field['name']) . '</th><td>' . esc_html(is_array($field['value']) ? implode(' ', $field['value']) : $field['value']) . '</td></tr>';
        }
        echo '</tbody></table><hr>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Could not retrieve entry details.', 'custom-crm') . '</p></div>';
    }

    crm_form($course_id, $entry_id);
}
}