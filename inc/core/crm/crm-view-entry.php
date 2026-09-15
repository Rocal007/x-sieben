<?php
function xsieben_view_entry($entry_id, $course_id = 0)
{
    if (!function_exists('wpforms') || !$entry_id) {
        echo '<p>' . esc_html__('Could not retrieve entry details.', 'custom-crm') . '</p>';
        return;
    }
    $entry = wpforms()->entry->get($entry_id);
    if (!$entry) {
        echo '<p>' . esc_html__('Entry not found.', 'custom-crm') . '</p>';
        return;
    }

    $fields = is_string($entry->fields) ? json_decode($entry->fields, true) : $entry->fields;

    if (!is_array($fields)) {
        echo '<p>' . esc_html__('Could not decode entry fields.', 'custom-crm') . '</p>';
        return;
    }

    echo '<h3>' . sprintf(esc_html__('Details for Entry ID: %d', 'custom-crm'), esc_html($entry_id)) . '</h3>';
    echo '<table class="widefat striped"><tbody>';
    foreach ($fields as $field) {
        $value = is_array($field['value']) ? implode(', ', $field['value']) : $field['value'];
        echo '<tr>';
        echo '<th style="width: 30%;">' . esc_html($field['name']) . '</th>';
        echo '<td>' . esc_html($value) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    // Add the "Edit Entry in WPForms" button
    if (current_user_can('edit_others_posts')) { // Or a more specific capability if desired
        $edit_url = admin_url('admin.php?page=wpforms-entries&view=edit&entry_id=' . absint($entry_id));
        echo '<p>';
        echo '<a href="' . esc_url($edit_url) . '" class="button button-primary">' . esc_html__('Edit Entry in WPForms', 'custom-crm') . '</a>';
        echo '</p>';
    }

    // crm_form($course_id, $entry_id);
}