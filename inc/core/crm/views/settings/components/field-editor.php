<?php
/**
 * CRM Settings Component: Field Editor Accordion Item
 *
 * Rendert einen einzelnen Textbaustein-Editor (E-Mail oder PDF).
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('crm_render_editor_field')) {
    function crm_render_editor_field($index, $title, $content, $category = 'email', $email_type = null)
    {
        $usage = function_exists('crm_get_field_usage_info') ? crm_get_field_usage_info($title) : [];
        $doc   = $usage['doc'] ?? ($category === 'email' ? 'email' : 'general');
        if ($category === 'email' && $email_type === null && function_exists('crm_get_email_field_type')) {
            $email_type = crm_get_email_field_type($title);
        }
        ?>
        <div class="crm-field-block" data-index="<?php echo esc_attr($index); ?>" data-doc="<?php echo esc_attr($doc); ?>" data-category="<?php echo esc_attr($category); ?>" data-email-type="<?php echo esc_attr($email_type ?: 'full_email'); ?>">
            <div class="crm-field-header">
                <h3>
                    <span class="dashicons dashicons-menu crm-field-drag-handle" title="<?php esc_attr_e('Verschieben', 'custom-crm'); ?>" style="color: #94a3b8; cursor: grab; font-size:16px; margin-right: 4px; vertical-align: middle;"></span>
                    <span class="dashicons dashicons-arrow-right crm-accordion-arrow" style="color: #64748b;"></span>
                    <span class="crm-field-title-text"><?php echo $title ? esc_html($title) : esc_html__('Neuer Textbaustein', 'custom-crm'); ?></span>
                    
                    <?php if ($category === 'email') : ?>
                        <?php if ($email_type === 'full_email') : ?>
                            <span class="crm-type-badge crm-type-badge-full" style="background:#e0f2fe; color:#0284c7; border:1px solid #bae6fd; font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:10px; margin-left:6px;">
                                📧 <?php esc_html_e('Gesamte E-Mail', 'custom-crm'); ?>
                            </span>
                        <?php else : 
                            $comp_code = function_exists('crm_get_component_placeholder_for_title') ? crm_get_component_placeholder_for_title($title) : '{' . sanitize_key($title) . '}';
                        ?>
                            <span class="crm-type-badge crm-type-badge-comp" style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:10px; margin-left:6px;">
                                🧩 <?php esc_html_e('Komponente', 'custom-crm'); ?>
                            </span>
                            <span class="crm-comp-code-pill" title="<?php esc_attr_e('Platzhalter zur Einbindung', 'custom-crm'); ?>" style="background:#f8fafc; color:#334155; border:1px solid #cbd5e1; font-family:monospace; font-size:11px; font-weight:600; padding:1px 7px; border-radius:4px; margin-left:4px;">
                                <?php echo esc_html($comp_code); ?>
                            </span>
                        <?php endif; ?>
                    <?php else : ?>
                        <span class="crm-usage-badge" style="background-color: <?php echo esc_attr($usage['color'] ?? '#64748b'); ?>;">
                            <?php echo esc_html($usage['badge'] ?? 'PDF'); ?>
                        </span>
                    <?php endif; ?>
                </h3>
                <div class="crm-action-group">
                    <span class="save-status"></span>
                    <button type="button" class="button crm-save-field" style="border-color: #cbd5e1;">
                        <span class="dashicons dashicons-saved" style="vertical-align: text-top; font-size: 15px;"></span>
                        <?php esc_html_e('Feld speichern', 'custom-crm'); ?>
                    </button>
                    <?php if ($category === 'pdf' && $doc !== 'general') : ?>
                        <button type="button" class="button crm-preview-this-doc" data-doc="<?php echo esc_attr($doc); ?>" title="<?php esc_attr_e('Dieses PDF in der Live-Vorschau anzeigen', 'custom-crm'); ?>" style="border-color: #cbd5e1; color: #7c3aed;">
                            <span class="dashicons dashicons-visibility" style="vertical-align: text-top; font-size: 15px;"></span>
                            <?php esc_html_e('Vorschau', 'custom-crm'); ?>
                        </button>
                    <?php endif; ?>
                    <?php if ($category === 'email') : 
                        $mail_doc = 'angebot';
                        $t_low = strtolower(trim($title));
                        if (strpos($t_low, 'kurszeit') !== false || strpos($t_low, 'kursantritt') !== false) {
                            $mail_doc = 'kb';
                        } elseif (strpos($t_low, 'anmelde') !== false || strpos($t_low, 'anmeldung') !== false || strpos($t_low, 'buchung') !== false) {
                            $mail_doc = 'anmeldung';
                        } elseif (strpos($t_low, 'diplom') !== false) {
                            $mail_doc = 'diplom';
                        } elseif (strpos($t_low, 'teilnahme') !== false) {
                            $mail_doc = 'tb';
                        } elseif (strpos($t_low, 'honorarnote') !== false || strpos($t_low, 'rechnung') !== false) {
                            $mail_doc = 'invoice';
                        }
                    ?>
                        <button type="button" class="button crm-preview-this-email" data-doc="<?php echo esc_attr($mail_doc); ?>" title="<?php esc_attr_e('Diese E-Mail in der Live-Vorschau anzeigen', 'custom-crm'); ?>" style="border-color: #cbd5e1; color: #0284c7;">
                            <span class="dashicons dashicons-visibility" style="vertical-align: text-top; font-size: 15px;"></span>
                            <?php esc_html_e('Vorschau', 'custom-crm'); ?>
                        </button>
                    <?php endif; ?>
                    <button type="button" class="button remove-crm-field" style="color: #dc2626; border-color: #fca5a5;">
                        <span class="dashicons dashicons-trash" style="vertical-align: text-top; font-size: 15px;"></span>
                        <?php esc_html_e('Löschen', 'custom-crm'); ?>
                    </button>
                </div>
            </div>

            <div class="crm-field-content">
                <div style="display: flex; gap: 16px; margin-bottom: 12px; align-items: flex-start; flex-wrap: wrap;">
                    <div style="flex: 2; min-width: 250px;">
                        <label style="font-weight: 600; font-size: 12.5px; color: #334155; display: block; margin-bottom: 4px;">
                            <?php esc_html_e('Titel des Bausteins (z.B. E-Mail Angebot oder E-Mail Signatur):', 'custom-crm'); ?>
                        </label>
                        <input type="text"
                               name="crm_fields[<?php echo esc_attr($index); ?>][title]"
                               value="<?php echo esc_attr($title); ?>"
                               class="regular-text widefat"
                               style="height: 34px; border-radius: 4px; font-weight: 600;"
                               required />
                    </div>

                    <div style="flex: 1; min-width: 180px;">
                        <label style="font-weight: 600; font-size: 12.5px; color: #334155; display: block; margin-bottom: 4px;">
                            <?php esc_html_e('Kategorie / Typ:', 'custom-crm'); ?>
                        </label>
                        <select name="crm_fields[<?php echo esc_attr($index); ?>][category_choice]" class="crm-field-category-choice-select" style="height: 34px; width: 100%; border-radius: 4px;">
                            <option value="email:full_email" <?php selected($category === 'email' && $email_type === 'full_email'); ?>><?php esc_html_e('📧 Gesamte E-Mail (Hauptvorlage)', 'custom-crm'); ?></option>
                            <option value="email:component" <?php selected($category === 'email' && $email_type === 'component'); ?>><?php esc_html_e('🧩 E-Mail Komponente (Baustein)', 'custom-crm'); ?></option>
                            <option value="pdf:general" <?php selected($category === 'pdf'); ?>><?php esc_html_e('📄 PDF Baustein', 'custom-crm'); ?></option>
                        </select>
                        <input type="hidden" name="crm_fields[<?php echo esc_attr($index); ?>][category]" class="crm-field-category-input" value="<?php echo esc_attr($category); ?>" />
                        <input type="hidden" name="crm_fields[<?php echo esc_attr($index); ?>][email_type]" class="crm-field-email-type-input" value="<?php echo esc_attr($email_type ?: 'full_email'); ?>" />
                    </div>
                </div>

                <?php if (!empty($usage['desc'])) : ?>
                    <p style="margin: 0 0 10px 0; font-size: 12px; color: #64748b; font-style: italic;">
                        <span class="dashicons dashicons-info" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
                        <?php echo esc_html($usage['desc']); ?>
                    </p>
                <?php endif; ?>

                <?php if ($category === 'email' && $email_type === 'component') : 
                    $comp_code = function_exists('crm_get_component_placeholder_for_title') ? crm_get_component_placeholder_for_title($title) : '{' . sanitize_key($title) . '}';
                ?>
                    <!-- Component Info & Copy Box -->
                    <div class="crm-component-helper-box" style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:10px 14px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <span class="dashicons dashicons-screenoptions" style="color:#059669; font-size:18px; width:18px; height:18px;"></span>
                            <strong style="font-size:12.5px; color:#166534;"><?php esc_html_e('Platzhalter zur Einbindung in gesamte E-Mails:', 'custom-crm'); ?></strong>
                            <button type="button" class="crm-copy-chip-btn" data-code="<?php echo esc_attr($comp_code); ?>" title="<?php esc_attr_e('In Zwischenablage kopieren', 'custom-crm'); ?>" style="background:#ffffff; border:1px solid #86efac; color:#15803d; font-family:monospace; font-weight:700; font-size:12px; padding:3px 10px; border-radius:6px; cursor:pointer; display:inline-flex; align-items:center; gap:5px; transition:all 0.15s ease;">
                                <span class="crm-copy-code-text"><?php echo esc_html($comp_code); ?></span>
                                <span class="dashicons dashicons-clipboard" style="font-size:13px; width:13px; height:13px; vertical-align:middle;"></span>
                            </button>
                        </div>
                        <span style="color:#15803d; font-size:11.5px; font-style:italic;">
                            <?php esc_html_e('Wird in allen E-Mail-Vorlagen automatisch an Stelle des Codes gerendert.', 'custom-crm'); ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ($category === 'email' && $email_type === 'full_email') : 
                    $component_chips = [
                        '{signatur_email}' => __('E-Mail Signatur', 'custom-crm'),
                        '{email_footer}'   => __('E-Mail Footer & AGB', 'custom-crm'),
                        '{buchung_email}'  => __('Buchungshinweis / Frist', 'custom-crm'),
                        '{agb_claim}'      => __('AGB-Klausel', 'custom-crm'),
                        '{bankverbindung}' => __('Bankverbindung', 'custom-crm'),
                        '{angebot_hinweis}'=> __('Angebots-Hinweis', 'custom-crm'),
                        '{angebot_ps}'     => __('P.S. ProvenExpert', 'custom-crm'),
                    ];
                    $system_chips = [
                        '{kurstitel}'      => __('Kurstitel', 'custom-crm'),
                        '{startdatum}'     => __('Startdatum', 'custom-crm'),
                        '{enddatum}'       => __('Enddatum', 'custom-crm'),
                        '{uhrzeit}'        => __('Kurszeiten', 'custom-crm'),
                        '{le}'             => __('Lehreinheiten (LE)', 'custom-crm'),
                        '{location_wien}'  => __('Schulungsort Wien', 'custom-crm'),
                        '{preis_netto}'    => __('Preis Netto', 'custom-crm'),
                        '{preis_brutto}'   => __('Preis Brutto', 'custom-crm'),
                        '{expire}'         => __('Gültigkeit / Frist', 'custom-crm'),
                        '{salutation}'     => __('Anrede (formell)', 'custom-crm'),
                        '{titel}'          => __('Akad. Titel', 'custom-crm'),
                        '{vorname}'        => __('Vorname', 'custom-crm'),
                        '{nachname}'       => __('Nachname', 'custom-crm'),
                    ];
                ?>
                    <!-- Full Email Chips Container -->
                    <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; padding: 12px 14px; margin-bottom: 12px;">
                        <div style="margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px dashed #bae6fd;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 5px; flex-wrap:wrap; gap:4px;">
                                <strong style="font-size: 11.5px; color: #047857; text-transform:uppercase; letter-spacing:0.4px; display:flex; align-items:center; gap:5px;">
                                    <span class="dashicons dashicons-screenoptions" style="font-size:15px; width:15px; height:15px; color:#059669;"></span>
                                    <?php esc_html_e('🧩 Wiederverwendbare Komponenten einbinden:', 'custom-crm'); ?>
                                </strong>
                                <small style="color: #64748b; font-size: 11px;"><?php esc_html_e('Klick fügt Komponente an Cursor-Position ein', 'custom-crm'); ?></small>
                            </div>
                            <div style="display:flex; flex-wrap:wrap; gap:5px;">
                                <?php foreach ($component_chips as $chip_code => $chip_desc) : ?>
                                    <button type="button" class="crm-insert-chip-to-editor" data-code="<?php echo esc_attr($chip_code); ?>" title="<?php echo esc_attr($chip_desc); ?>" style="background:#ecfdf5; border:1px solid #6ee7b7; color:#065f46; border-radius:12px; font-size:11px; font-family:monospace; padding:2px 8px; cursor:pointer; font-weight:600; transition:all 0.12s ease;">
                                        <?php echo esc_html($chip_code); ?> <span style="font-family:Arial,sans-serif; font-weight:normal; font-size:10px; color:#047857;">(<?php echo esc_html($chip_desc); ?>)</span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div>
                            <strong style="font-size: 11px; color: #0369a1; text-transform:uppercase; letter-spacing:0.4px; display:flex; align-items:center; gap:5px; margin-bottom: 5px;">
                                <span class="dashicons dashicons-database" style="font-size:14px; width:14px; height:14px; color:#0284c7;"></span>
                                <?php esc_html_e('🔤 Kurs- & Kundendaten-Platzhalter:', 'custom-crm'); ?>
                            </strong>
                            <div style="display:flex; flex-wrap:wrap; gap:5px;">
                                <?php foreach ($system_chips as $chip_code => $chip_desc) : ?>
                                    <button type="button" class="crm-insert-chip-to-editor" data-code="<?php echo esc_attr($chip_code); ?>" title="<?php echo esc_attr($chip_desc); ?>" style="background:#ffffff; border:1px solid #7dd3fc; color:#0369a1; border-radius:12px; font-size:11px; font-family:monospace; padding:2px 8px; cursor:pointer; font-weight:600; transition:all 0.12s ease;">
                                        <?php echo esc_html($chip_code); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div>
                    <label style="font-weight: 600; font-size: 12.5px; color: #334155; display: block; margin-bottom: 4px;">
                        <?php esc_html_e('Inhalt / Textvorlage:', 'custom-crm'); ?>
                    </label>
                    <?php
                    wp_editor(
                        $content,
                        "crm_fields_{$index}_content",
                        [
                            'textarea_name' => "crm_fields[{$index}][content]",
                            'textarea_rows' => 8,
                            'media_buttons' => true,
                            'tinymce'       => true,
                            'quicktags'     => true,
                        ]
                    );
                    ?>
                </div>
            </div>
        </div>
        <?php
    }
}
