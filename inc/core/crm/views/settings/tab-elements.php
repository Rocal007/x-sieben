<?php
/**
 * CRM Settings Tab: Reusable Elements & Components (Atomic Design)
 *
 * Verwaltet wiederverwendbare Textbausteine, Klauseln, Signaturen, Footer,
 * Bankverbindungen und Medien-Assets für alle E-Mail- und PDF-Workflows.
 *
 * @version 2.19.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure component helper is loaded
require_once __DIR__ . '/components/field-editor.php';
require_once __DIR__ . '/components/cheat-sheet.php';

// Prepare element items (Merge saved fields with all default PDF/system components)
$all_fields = function_exists('crm_get_merged_custom_fields') ? crm_get_merged_custom_fields() : get_option('crm_custom_fields', []);
if (!is_array($all_fields)) {
    $all_fields = [];
}

$element_fields = [];
foreach ($all_fields as $orig_idx => $field) {
    $cat = function_exists('crm_get_field_category') ? crm_get_field_category($field) : 'email';
    $title = $field['title'] ?? '';
    $sub_type = function_exists('crm_get_email_field_type') ? crm_get_email_field_type($title, $field) : 'component';

    // In Elements, we show all components, reusable email snippets, and custom modular snippets
    if ($cat === 'email' && $sub_type === 'component') {
        $element_fields[$orig_idx] = $field;
    } elseif ($cat === 'pdf') {
        $element_fields[$orig_idx] = $field;
    } elseif ($sub_type === 'component') {
        $element_fields[$orig_idx] = $field;
    }
}

uasort($element_fields, function ($a, $b) {
    return strcmp($a['title'] ?? '', $b['title'] ?? '');
});

$general_settings = function_exists('crm_get_general_settings') ? crm_get_general_settings() : [];
?>
<div class="crm-tab-panel" id="crm-tab-elements">
    <!-- Intro & Header Card -->
    <div class="crm-editor-header-box" style="background: #ffffff; border: 1px solid #cbd5e1; border-left: 4px solid #047857; border-radius: 8px; padding: 20px 24px; margin-bottom: 22px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
            <div>
                <h2 style="margin:0 0 6px 0; color:#0f172a; font-size:19px; display:flex; align-items:center; gap:10px;">
                    <span class="dashicons dashicons-screenoptions" style="color:#047857; font-size:26px; width:26px; height:26px;"></span>
                    <?php esc_html_e('Elemente & Wiederverwendbare Bausteine', 'custom-crm'); ?>
                    <span style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; font-size:11px; font-weight:700; padding:2px 9px; border-radius:12px;">Atomic Design</span>
                </h2>
                <p style="margin:0; color:#475569; font-size:13.5px; max-width:850px; line-height:1.5;">
                    <?php esc_html_e('Zentrale Verwaltung aller modularen Textbausteine, Signaturen, Footer, AGB-Klauseln und Bankverbindungen. Änderungen an einem Element wirken sofort und automatisch in allen 7 E-Mail-Workflows und 5 PDF-Vorlagen über Platzhalter wie {signatur_email}, {agb_claim} oder {bankverbindung}.', 'custom-crm'); ?>
                </p>
            </div>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <button type="button" class="button button-secondary" id="crm-toggle-all-accordions">
                    <span class="dashicons dashicons-sort" style="vertical-align:text-top;"></span> <?php esc_html_e('Alle auf-/zuklappen', 'custom-crm'); ?>
                </button>
                <button type="button" class="button button-primary" id="add-crm-component-field" style="background:#047857; border-color:#047857;">
                    <span class="dashicons dashicons-plus" style="vertical-align:text-top;"></span> <?php esc_html_e('Neuer Baustein / Komponente', 'custom-crm'); ?>
                </button>
            </div>
        </div>

        <!-- Variable Cheat Sheet Box -->
        <?php crm_render_placeholders_cheat_sheet('email'); ?>
    </div>

    <!-- Visuelle Medien & Siegel Übersicht (Media Assets Grid) -->
    <div class="crm-settings-card" style="margin-bottom: 22px;">
        <div class="crm-settings-card-header" style="border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 16px;">
            <h3 style="margin:0; font-size:16px; color:#0f172a; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-format-image" style="color:#0284c7; font-size:20px;"></span>
                <?php esc_html_e('Visuelle Siegel, Stempel & Signaturbilder', 'custom-crm'); ?>
            </h3>
            <span class="crm-section-tag" style="background:#f0fdf4; color:#166534; border:1px solid #bbf7d0;">
                <?php esc_html_e('Branding & Gütesiegel', 'custom-crm'); ?>
            </span>
        </div>
        <p style="margin:0 0 16px 0; color:#64748b; font-size:13px;">
            <?php esc_html_e('Diese Medien-Elemente werden in E-Mails, Angeboten, Bestätigungen und Diplomen eingebunden. Sie können unter „Allgemeine Einstellungen“ aktualisiert werden.', 'custom-crm'); ?>
        </p>

        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:14px;">
            <!-- Logo Box -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px; text-align:center;">
                <div style="height:55px; display:flex; align-items:center; justify-content:center; margin-bottom:8px;">
                    <?php $l_url = !empty($general_settings['logo_url']) ? $general_settings['logo_url'] : get_template_directory_uri() . '/inc/core/crm/assets/xsieben_logo.png'; ?>
                    <img src="<?php echo esc_url($l_url); ?>" alt="Instituts-Logo" style="max-height:48px; max-width:160px; object-fit:contain;" />
                </div>
                <div style="font-weight:600; font-size:12px; color:#1e293b;"><?php esc_html_e('Instituts-Hauptlogo', 'custom-crm'); ?></div>
                <div style="font-family:monospace; font-size:10.5px; color:#0284c7; background:#e0f2fe; padding:2px 6px; border-radius:4px; margin-top:4px; display:inline-block;">{company_logo}</div>
            </div>

            <!-- Signatur Blau Box -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px; text-align:center;">
                <div style="height:55px; display:flex; align-items:center; justify-content:center; margin-bottom:8px;">
                    <img src="<?php echo esc_url(get_template_directory_uri() . '/inc/core/crm/assets/Signatur_Blau.png'); ?>" alt="Geschäftsführung Signatur" style="max-height:45px; max-width:140px; object-fit:contain;" />
                </div>
                <div style="font-weight:600; font-size:12px; color:#1e293b;"><?php esc_html_e('Signatur GF (Dr. Gasberger)', 'custom-crm'); ?></div>
                <div style="font-family:monospace; font-size:10.5px; color:#0284c7; background:#e0f2fe; padding:2px 6px; border-radius:4px; margin-top:4px; display:inline-block;">{signature_image}</div>
            </div>

            <!-- Stampiglie / Stempel Box -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px; text-align:center;">
                <div style="height:55px; display:flex; align-items:center; justify-content:center; margin-bottom:8px;">
                    <img src="<?php echo esc_url(get_template_directory_uri() . '/inc/core/crm/assets/abschluss.png'); ?>" alt="Instituts-Stampiglie" style="max-height:48px; max-width:120px; object-fit:contain;" />
                </div>
                <div style="font-weight:600; font-size:12px; color:#1e293b;"><?php esc_html_e('Instituts-Stampiglie & Siegel', 'custom-crm'); ?></div>
                <div style="font-family:monospace; font-size:10.5px; color:#0284c7; background:#e0f2fe; padding:2px 6px; border-radius:4px; margin-top:4px; display:inline-block;">{stamp_image}</div>
            </div>

            <!-- Akkreditierungen Box -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:12px; text-align:center;">
                <div style="height:55px; display:flex; align-items:center; justify-content:center; margin-bottom:8px;">
                    <img src="<?php echo esc_url(get_template_directory_uri() . '/inc/core/crm/assets/PMA-1.png'); ?>" alt="Akkreditierung pma" style="max-height:42px; max-width:130px; object-fit:contain;" />
                </div>
                <div style="font-weight:600; font-size:12px; color:#1e293b;"><?php esc_html_e('Akkreditierung pma / IPMA®', 'custom-crm'); ?></div>
                <div style="font-family:monospace; font-size:10.5px; color:#0284c7; background:#e0f2fe; padding:2px 6px; border-radius:4px; margin-top:4px; display:inline-block;">{pma_badge}</div>
            </div>
        </div>
    </div>

    <!-- Filter Pills & Search for Elements -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <div class="crm-element-filter-pills" style="display:flex; gap:8px; flex-wrap:wrap;">
            <button type="button" class="button crm-doc-pill active" data-doc="all" style="border-color:#047857; color:#047857; font-weight:600;">
                <span class="dashicons dashicons-admin-generic" style="font-size:13px; vertical-align:text-top;"></span> <?php esc_html_e('Alle Bausteine', 'custom-crm'); ?> (<?php echo count($element_fields); ?>)
            </button>
            <button type="button" class="button crm-doc-pill" data-doc="email" style="color:#0284c7;">
                <span class="dashicons dashicons-email-alt" style="font-size:13px; vertical-align:text-top;"></span> <?php esc_html_e('E-Mail & Signatur Bausteine', 'custom-crm'); ?>
            </button>
            <button type="button" class="button crm-doc-pill" data-doc="rechtlich" style="color:#b45309;">
                <span class="dashicons dashicons-shield" style="font-size:13px; vertical-align:text-top;"></span> <?php esc_html_e('Rechtliches & Klauseln (AGB, Bank, Garantie)', 'custom-crm'); ?>
            </button>
            <button type="button" class="button crm-doc-pill" data-doc="pdf" style="color:#7c3aed;">
                <span class="dashicons dashicons-media-document" style="font-size:13px; vertical-align:text-top;"></span> <?php esc_html_e('PDF-Bausteine', 'custom-crm'); ?>
            </button>
        </div>

        <div style="display:flex; align-items:center; gap:8px;">
            <span class="dashicons dashicons-search" style="color:#64748b;"></span>
            <input type="text" id="crm-element-quick-search" placeholder="<?php esc_attr_e('Baustein filtern...', 'custom-crm'); ?>" style="height:30px; font-size:12px; width:200px; border-radius:4px; border:1px solid #cbd5e1;" />
        </div>
    </div>

    <!-- Accordion List: Elements / Reusable Components -->
    <div id="crm-fields-container" class="crm-elements-fields-list">
        <?php if (!empty($element_fields)) : ?>
            <?php foreach ($element_fields as $orig_idx => $field) : ?>
                <?php
                $f_title    = $field['title'] ?? '';
                $f_content  = $field['content'] ?? '';
                $f_cat      = function_exists('crm_get_field_category') ? crm_get_field_category($field) : 'email';
                $f_type     = function_exists('crm_get_email_field_type') ? crm_get_email_field_type($f_title, $field) : 'component';
                crm_render_editor_field($orig_idx, $f_title, $f_content, $f_cat, $f_type);
                ?>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="crm-no-fields-notice" style="background:#fff; border:1px dashed #cbd5e1; border-radius:8px; padding:30px; text-align:center; color:#64748b;">
                <span class="dashicons dashicons-screenoptions" style="font-size:36px; width:36px; height:36px; color:#94a3b8; margin-bottom:8px;"></span>
                <p style="margin:0 0 12px 0; font-size:14px; font-weight:600; color:#334155;">
                    <?php esc_html_e('Noch keine separaten Bausteine angelegt.', 'custom-crm'); ?>
                </p>
                <p style="margin:0 0 16px 0; font-size:13px; color:#64748b;">
                    <?php esc_html_e('Klicken Sie auf den Button unten, um Ihren ersten modularen Textbaustein (z. B. Signatur, AGB-Klausel oder Buchungshinweis) zu erstellen.', 'custom-crm'); ?>
                </p>
                <button type="button" class="button button-primary" id="add-crm-component-field-empty" style="background:#047857; border-color:#047857;">
                    <span class="dashicons dashicons-plus" style="vertical-align:text-top;"></span> <?php esc_html_e('Ersten Baustein anlegen', 'custom-crm'); ?>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bottom Action Row -->
    <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div style="color: #64748b; font-size: 13px;">
            <span class="dashicons dashicons-info" style="vertical-align: text-top; font-size: 16px; color: #047857;"></span>
            <?php esc_html_e('Alle Bausteine können einzeln im jeweiligen Kasten gespeichert werden oder global über den Haupt-Button.', 'custom-crm'); ?>
        </div>
        <div>
            <input type="submit" name="submit_elements" class="button button-primary button-hero" value="<?php esc_attr_e('Alle Bausteine & Elemente speichern', 'custom-crm'); ?>" style="background: #047857; border-color: #047857; box-shadow: 0 2px 4px rgba(4,120,87,0.25);" />
        </div>
    </div>
</div>
