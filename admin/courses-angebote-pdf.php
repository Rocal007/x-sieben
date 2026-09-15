<?php
// Admin menu setup
add_action('admin_menu', 'angebote_pdf_menu');
function angebote_pdf_menu() {
    add_menu_page(
        'Angebote PDFs',
        'Angebote PDFs',
        'manage_options',
        'angebote-pdfs',
        'angebote_pdf_page_callback',
        'dashicons-media-document',
        25
    );
}

// Handle single and bulk deletion
add_action('admin_init', 'handle_angebote_pdf_delete');
function handle_angebote_pdf_delete() {
    // Single file delete
    if (
        isset($_GET['action'], $_GET['file'], $_GET['_wpnonce']) &&
        $_GET['action'] === 'delete_pdf' &&
        current_user_can('manage_options') &&
        wp_verify_nonce($_GET['_wpnonce'], 'delete_pdf_' . $_GET['file'])
    ) {
        $file = basename($_GET['file']);
        $file_path = get_template_directory() . '/angebote/' . $file;

        if (file_exists($file_path)) {
            unlink($file_path);
            wp_redirect(admin_url('admin.php?page=angebote-pdfs&deleted=1'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=angebote-pdfs&error=not_found'));
            exit;
        }
    }

    // Bulk delete
    if (
        isset($_POST['bulk_delete'], $_POST['pdf_files'], $_POST['_wpnonce']) &&
        current_user_can('manage_options') &&
        wp_verify_nonce($_POST['_wpnonce'], 'bulk_delete_pdfs')
    ) {
        $folder = get_template_directory() . '/angebote/';
        $deleted = 0;

        foreach ($_POST['pdf_files'] as $file) {
            $filename = basename($file);
            $file_path = $folder . $filename;
            if (file_exists($file_path)) {
                unlink($file_path);
                $deleted++;
            }
        }

        wp_redirect(admin_url('admin.php?page=angebote-pdfs&bulk_deleted=' . $deleted));
        exit;
    }
}

// Admin page UI
function angebote_pdf_page_callback() {
    $folder = get_template_directory() . '/angebote/';
    $url_folder = get_template_directory_uri() . '/angebote/';

    echo '<div class="wrap"><h1>Angebote PDFs</h1>';

    if (isset($_GET['deleted'])) {
        echo '<div class="notice notice-success"><p>Datei gelöscht.</p></div>';
    } elseif (isset($_GET['bulk_deleted'])) {
        echo '<div class="notice notice-success"><p>' . intval($_GET['bulk_deleted']) . ' Datei(en) gelöscht.</p></div>';
    } elseif (isset($_GET['error'])) {
        echo '<div class="notice notice-error"><p>Datei nicht gefunden oder Fehler beim Löschen.</p></div>';
    }

    if (!is_dir($folder)) {
        echo '<div class="notice notice-error"><p>Ordner nicht gefunden: <code>' . esc_html($folder) . '</code></p></div>';
        return;
    }

    $pdf_files = glob($folder . '*.pdf');

    if (!empty($pdf_files)) {
        usort($pdf_files, function($a, $b) {
            return filemtime($b) - filemtime($a); // newest first
        });
    }

    if (empty($pdf_files)) {
        echo '<p>Keine PDFs gefunden im Ordner <code>/angebote/</code>.</p>';
    } else {
        echo '<form method="post">';
        wp_nonce_field('bulk_delete_pdfs');
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th style="width:30px;"><input type="checkbox" id="select-all"></th>';
        echo '<th>Datei</th><th>Datum</th><th>Aktion</th></tr></thead><tbody>';

        foreach ($pdf_files as $file_path) {
            $file_name = basename($file_path);
            $file_url = $url_folder . $file_name;
            $file_date = date("Y-m-d H:i", filemtime($file_path));
            $nonce = wp_create_nonce('delete_pdf_' . $file_name);
            $delete_url = admin_url('admin.php?page=angebote-pdfs&action=delete_pdf&file=' . urlencode($file_name) . '&_wpnonce=' . $nonce);

            echo '<tr>';
            echo '<td><input type="checkbox" name="pdf_files[]" value="' . esc_attr($file_name) . '"></td>';
            echo '<td><a href="' . esc_url($file_url) . '" download>' . esc_html($file_name) . '</a></td>';
            echo '<td>' . esc_html($file_date) . '</td>';
            echo '<td><a href="' . esc_url($delete_url) . '" onclick="return confirm(\'Möchtest du diese Datei wirklich löschen?\')" class="button button-small">Löschen</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table><br>';
        echo '<input type="submit" name="bulk_delete" class="button button-secondary" value="Ausgewählte löschen" onclick="return confirm(\'Ausgewählte Dateien wirklich löschen?\')">';
        echo '</form>';
    }

    echo '</div>';

    // Select All JavaScript
    echo '<script>
    document.getElementById("select-all").addEventListener("click", function() {
        const checkboxes = document.querySelectorAll(\'input[name="pdf_files[]"]\');
        for (const cb of checkboxes) {
            cb.checked = this.checked;
        }
    });
    </script>';
}
