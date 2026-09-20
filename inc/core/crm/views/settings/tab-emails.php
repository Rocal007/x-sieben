<?php
if (!defined('ABSPATH')) exit;
?>
                <div class="crm-tab-panel" id="crm-tab-emails">
                    <!-- Intro & Header -->
                    <div class="crm-editor-header-box" style="background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 18px 22px; margin-bottom: 20px;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
                            <div>
                                <h2 style="margin:0 0 6px 0; color:#0f172a; font-size:18px; display:flex; align-items:center; gap:8px;">
                                    <span class="dashicons dashicons-email-alt" style="color:#0284c7; font-size:24px;"></span>
                                    <?php esc_html_e('E-Mail Vorlagen & Texte', 'custom-crm'); ?>
                                </h2>
                                <p style="margin:0; color:#475569; font-size:13.5px;">
                                    <?php esc_html_e('Verwalten Sie hier alle E-Mail-Texte, die beim Versenden von Angeboten, Anmeldungen, Diplomen und Bestätigungen dynamisch generiert werden.', 'custom-crm'); ?>
                                </p>
                            </div>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=crm-elements')); ?>" class="button button-secondary" style="border-color:#10b981; color:#047857; font-weight:600; background:#ecfdf5; height:32px; line-height:30px; padding:0 14px;">
                                    <span class="dashicons dashicons-screenoptions" style="vertical-align:text-top; font-size:16px;"></span> <?php esc_html_e('Elemente & Bausteine verwalten ➔', 'custom-crm'); ?>
                                </a>
                                <a href="#crm-email-preview-section" class="button button-secondary" style="border-color:#0284c7; color:#0284c7; height:32px; line-height:30px; padding:0 14px;">
                                    <span class="dashicons dashicons-visibility" style="vertical-align:text-top; font-size:16px;"></span> <?php esc_html_e('Zur E-Mail Live-Vorschau springen ↓', 'custom-crm'); ?>
                                </a>
                                <button type="button" class="button button-secondary" id="crm-toggle-all-accordions">
                                    <span class="dashicons dashicons-sort" style="vertical-align:text-top;"></span> <?php esc_html_e('Alle auf-/zuklappen', 'custom-crm'); ?>
                                </button>
                                <button type="button" class="button button-primary" id="add-crm-email-field" style="background:#0284c7; border-color:#0284c7;">
                                    <span class="dashicons dashicons-email-alt" style="vertical-align:text-top;"></span> <?php esc_html_e('Neue Gesamte E-Mail', 'custom-crm'); ?>
                                </button>
                        </div>

                        <!-- Variable Cheat Sheet Box -->
                        <?php crm_render_placeholders_cheat_sheet('email'); ?>
                    </div>

                    <!-- E-Mail Sections Drag & Drop Organizer (Verfügbare Abschnitte) -->
                    <div class="crm-email-sections-box" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 18px 22px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom: 14px;">
                            <div>
                                <h3 style="margin:0 0 4px 0; color:#0f172a; font-size:16px; display:flex; align-items:center; gap:8px;">
                                    <span class="dashicons dashicons-menu" style="color:#0284c7; font-size:20px;"></span>
                                    <?php esc_html_e('E-Mail-Abschnitte anordnen (Drag & Drop) & Verfügbare Abschnitte', 'custom-crm'); ?>
                                </h3>
                                <p style="margin:0; color:#475569; font-size:13px;">
                                    <?php esc_html_e('Bringen Sie die modularen Blöcke per Ziehen in Ihre Wunsch-Reihenfolge oder blenden Sie einzelne Abschnitte aus.', 'custom-crm'); ?>
                                </p>
                            </div>
                            <div class="crm-email-sections-doc-pills" style="display:flex; gap:6px; flex-wrap:wrap;">
                                <button type="button" class="button crm-email-sec-pill active" data-doc="angebot" style="border-color:#0284c7; color:#0284c7; font-weight:600;">
                                    <span class="dashicons dashicons-media-document" style="font-size:13px; vertical-align:text-top;"></span> Angebot (10)
                                </button>
                                <button type="button" class="button crm-email-sec-pill" data-doc="kb" style="color:#0f766e;">
                                    <span class="dashicons dashicons-calendar-alt" style="font-size:13px; vertical-align:text-top;"></span> Kurszeiten KB (7)
                                </button>
                                <button type="button" class="button crm-email-sec-pill" data-doc="angebot_kb" style="color:#7c3aed;">
                                    <span class="dashicons dashicons-paperclip" style="font-size:13px; vertical-align:text-top;"></span> Angebot & KB (7)
                                </button>
                                <button type="button" class="button crm-email-sec-pill" data-doc="anmeldung" style="color:#059669;">
                                    <span class="dashicons dashicons-saved" style="font-size:13px; vertical-align:text-top;"></span> Anmeldung (6)
                                </button>
                                <button type="button" class="button crm-email-sec-pill" data-doc="tb" style="color:#047857;">
                                    <span class="dashicons dashicons-id-alt" style="font-size:13px; vertical-align:text-top;"></span> Teilnahme TB (6)
                                </button>
                                <button type="button" class="button crm-email-sec-pill" data-doc="diplom" style="color:#b45309;">
                                    <span class="dashicons dashicons-awards" style="font-size:13px; vertical-align:text-top;"></span> Diplom (6)
                                </button>
                                <button type="button" class="button crm-email-sec-pill" data-doc="invoice" style="color:#be185d;">
                                    <span class="dashicons dashicons-money-alt" style="font-size:13px; vertical-align:text-top;"></span> Honorarnote (7)
                                </button>
                            </div>
                        </div>

                        <div class="crm-email-sec-tab-pane" id="crm-email-sec-pane-angebot" style="display:block;">
                            <?php if (function_exists('crm_render_email_subject_editor')) crm_render_email_subject_editor('angebot'); ?>
                            <?php if (function_exists('crm_render_email_type_attachments_info')) crm_render_email_type_attachments_info('angebot'); ?>
                            <?php crm_render_email_sections_manager('angebot', null, false); ?>
                        </div>
                        <div class="crm-email-sec-tab-pane" id="crm-email-sec-pane-kb" style="display:none;">
                            <?php if (function_exists('crm_render_email_subject_editor')) crm_render_email_subject_editor('kb'); ?>
                            <?php if (function_exists('crm_render_email_type_attachments_info')) crm_render_email_type_attachments_info('kb'); ?>
                            <?php crm_render_email_sections_manager('kb', null, false); ?>
                        </div>
                        <div class="crm-email-sec-tab-pane" id="crm-email-sec-pane-angebot_kb" style="display:none;">
                            <?php if (function_exists('crm_render_email_subject_editor')) crm_render_email_subject_editor('angebot_kb'); ?>
                            <?php if (function_exists('crm_render_email_type_attachments_info')) crm_render_email_type_attachments_info('angebot_kb'); ?>
                            <?php crm_render_email_sections_manager('angebot_kb', null, false); ?>
                        </div>
                        <div class="crm-email-sec-tab-pane" id="crm-email-sec-pane-anmeldung" style="display:none;">
                            <?php if (function_exists('crm_render_email_subject_editor')) crm_render_email_subject_editor('anmeldung'); ?>
                            <?php if (function_exists('crm_render_email_type_attachments_info')) crm_render_email_type_attachments_info('anmeldung'); ?>
                            <?php crm_render_email_sections_manager('anmeldung', null, false); ?>
                        </div>
                        <div class="crm-email-sec-tab-pane" id="crm-email-sec-pane-tb" style="display:none;">
                            <?php if (function_exists('crm_render_email_subject_editor')) crm_render_email_subject_editor('tb'); ?>
                            <?php if (function_exists('crm_render_email_type_attachments_info')) crm_render_email_type_attachments_info('tb'); ?>
                            <?php crm_render_email_sections_manager('tb', null, false); ?>
                        </div>
                        <div class="crm-email-sec-tab-pane" id="crm-email-sec-pane-diplom" style="display:none;">
                            <?php if (function_exists('crm_render_email_subject_editor')) crm_render_email_subject_editor('diplom'); ?>
                            <?php if (function_exists('crm_render_email_type_attachments_info')) crm_render_email_type_attachments_info('diplom'); ?>
                            <?php crm_render_email_sections_manager('diplom', null, false); ?>
                        </div>
                        <div class="crm-email-sec-tab-pane" id="crm-email-sec-pane-invoice" style="display:none;">
                            <?php if (function_exists('crm_render_email_subject_editor')) crm_render_email_subject_editor('invoice'); ?>
                            <?php if (function_exists('crm_render_email_type_attachments_info')) crm_render_email_type_attachments_info('invoice'); ?>
                            <?php crm_render_email_sections_manager('invoice', null, false); ?>
                        </div>
                    </div>

                    <!-- Filter / Search bar with Pills: Alle / Gesamte Mails / Komponenten -->
                    <div style="margin-bottom: 16px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                        <div class="crm-email-field-filter-pills" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <span style="font-size: 12px; font-weight: 700; color: #475569; margin-right: 4px;"><?php esc_html_e('Ansicht filtern:', 'custom-crm'); ?></span>
                            <button type="button" class="button crm-email-field-filter-btn active" data-type="all" style="background:#0284c7; color:#ffffff; border-color:#0284c7; font-weight:700; font-size:12px; height:30px; line-height:28px; border-radius:15px; padding:0 14px; cursor:pointer;">
                                <?php esc_html_e('Alle Vorlagen', 'custom-crm'); ?> (<?php echo count($email_fields); ?>)
                            </button>
                            <button type="button" class="button crm-email-field-filter-btn" data-type="full_email" style="font-size:12px; height:30px; line-height:28px; border-radius:15px; padding:0 14px; color:#0369a1; border-color:#bae6fd; background:#f0f9ff; cursor:pointer;">
                                <span class="dashicons dashicons-email-alt" style="font-size:14px; vertical-align:text-top; margin-right:2px;"></span>
                                <?php esc_html_e('Gesamte E-Mails', 'custom-crm'); ?> (<?php echo count($full_emails); ?>)
                            </button>
                            <button type="button" class="button crm-email-field-filter-btn" data-type="component" style="font-size:12px; height:30px; line-height:28px; border-radius:15px; padding:0 14px; color:#047857; border-color:#a7f3d0; background:#ecfdf5; cursor:pointer;">
                                <span class="dashicons dashicons-screenoptions" style="font-size:14px; vertical-align:text-top; margin-right:2px;"></span>
                                <?php esc_html_e('Komponenten & Bausteine', 'custom-crm'); ?> (<?php echo count($component_emails); ?>)
                            </button>
                        </div>

                        <div style="display: flex; align-items: center; gap: 8px;">
                            <input type="text" id="crm-field-search" placeholder="<?php esc_attr_e('Vorlagen & Bausteine suchen...', 'custom-crm'); ?>" style="width: 250px; height: 32px; border-radius: 4px; border: 1px solid #cbd5e1; padding: 0 10px; font-size:12px;" />
                        </div>
                    </div>

                    <div id="crm-fields-wrapper">
                        <!-- GROUP 1: GESAMTE E-MAILS -->
                        <div class="crm-email-group crm-email-group-full" style="margin-bottom: 26px;">
                            <div style="background:#f0f9ff; border:1px solid #bae6fd; border-left:4px solid #0284c7; border-radius:6px; padding:12px 16px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                                <div>
                                    <strong style="font-size:14px; color:#0369a1; display:flex; align-items:center; gap:6px;">
                                        <span class="dashicons dashicons-email-alt" style="font-size:18px; width:18px; height:18px; color:#0284c7;"></span>
                                        <?php esc_html_e('Gesamte E-Mails (Master-Vorlagen)', 'custom-crm'); ?>
                                    </strong>
                                    <p style="margin:3px 0 0 0; font-size:12px; color:#0284c7;">
                                        <?php esc_html_e('Vollständige E-Mail-Vorlagen für den automatischen Versand an Kunden. Binden Sie modulare Komponenten über Platzhalter wie {signatur_email} oder {buchung_email} ein.', 'custom-crm'); ?>
                                    </p>
                                </div>
                                <span style="background:#0284c7; color:#ffffff; font-size:11px; font-weight:700; padding:3px 10px; border-radius:12px;">
                                    <?php echo count($full_emails); ?> <?php esc_html_e('Vorlagen', 'custom-crm'); ?>
                                </span>
                            </div>
                            <div class="crm-email-group-items">
                                <?php
                                if (!empty($full_emails)) {
                                    foreach ($full_emails as $orig_index => $field) {
                                        crm_render_editor_field($orig_index, $field['title'] ?? '', $field['content'] ?? '', 'email', 'full_email');
                                    }
                                } else {
                                    echo '<p class="description" style="margin:10px 0;">' . esc_html__('Keine gesamten E-Mail-Vorlagen vorhanden.', 'custom-crm') . '</p>';
                                }
                                ?>
                            </div>
                        </div>

                        <!-- GROUP 2: WIEDERVERWENDBARE KOMPONENTEN -->
                        <div class="crm-email-group crm-email-group-components" style="margin-bottom: 26px;">
                            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-left:4px solid #059669; border-radius:6px; padding:12px 16px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                                <div>
                                    <strong style="font-size:14px; color:#166534; display:flex; align-items:center; gap:6px;">
                                        <span class="dashicons dashicons-screenoptions" style="font-size:18px; width:18px; height:18px; color:#059669;"></span>
                                        <?php esc_html_e('Wiederverwendbare Komponenten & Bausteine', 'custom-crm'); ?>
                                    </strong>
                                    <p style="margin:3px 0 0 0; font-size:12px; color:#15803d;">
                                        <?php esc_html_e('Modulare Textbausteine (Signatur, Footer, Buchungshinweis, AGB, Bankverbindung etc.), die flexibel per Platzhalter in alle gesamten E-Mails eingebunden werden.', 'custom-crm'); ?>
                                    </p>
                                </div>
                                <span style="background:#059669; color:#ffffff; font-size:11px; font-weight:700; padding:3px 10px; border-radius:12px;">
                                    <?php echo count($component_emails); ?> <?php esc_html_e('Komponenten', 'custom-crm'); ?>
                                </span>
                            </div>
                            <div class="crm-email-group-items">
                                <?php
                                if (!empty($component_emails)) {
                                    foreach ($component_emails as $orig_index => $field) {
                                        crm_render_editor_field($orig_index, $field['title'] ?? '', $field['content'] ?? '', 'email', 'component');
                                    }
                                } else {
                                    echo '<p class="description" style="margin:10px 0;">' . esc_html__('Keine Komponenten vorhanden.', 'custom-crm') . '</p>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- E-Mail Live-Vorschau Bereich -->
                    <div id="crm-email-preview-section" class="crm-preview-card" style="margin-top: 28px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden;">
                        <div class="crm-preview-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px;">
                                    <span class="dashicons dashicons-email-alt" style="color: #0284c7; font-size: 19px;"></span>
                                    <span><?php esc_html_e('E-Mail Live-Vorschau:', 'custom-crm'); ?></span>
                                    <span id="crm-email-preview-doc-title" style="color: #0284c7;"><?php esc_html_e('Kursangebot & Beratung', 'custom-crm'); ?></span>
                                </h3>
                                <span id="crm-email-preview-sample-info" style="font-size: 11.5px; color: #0369a1; background: #e0f2fe; padding: 2px 8px; border-radius: 12px; font-weight: 500;">
                                    <?php esc_html_e('Wird geladen...', 'custom-crm'); ?>
                                </span>
                            </div>

                            <!-- Preview switcher pills inside preview card -->
                            <div class="crm-preview-doc-switcher" style="display: flex; align-items: center; gap: 5px; flex-wrap: wrap;">
                                <span style="font-size: 12px; font-weight: 600; color: #64748b; margin-right: 2px;"><?php esc_html_e('Vorlage:', 'custom-crm'); ?></span>
                                <button type="button" class="button crm-email-preview-switch-btn active" data-doc="angebot" title="<?php esc_attr_e('Kursangebot & Beratung', 'custom-crm'); ?>">
                                    <span class="dashicons dashicons-media-document" style="font-size:13px; vertical-align:text-top;"></span> Angebot
                                </button>
                                <button type="button" class="button crm-email-preview-switch-btn" data-doc="kb" title="<?php esc_attr_e('Kurszeitenbestätigung', 'custom-crm'); ?>">
                                    <span class="dashicons dashicons-calendar-alt" style="font-size:13px; vertical-align:text-top;"></span> KB
                                </button>
                                <button type="button" class="button crm-email-preview-switch-btn" data-doc="angebot_kb" title="<?php esc_attr_e('Angebot & Kurszeiten (Kombi)', 'custom-crm'); ?>">
                                    <span class="dashicons dashicons-paperclip" style="font-size:13px; vertical-align:text-top;"></span> Kombi
                                </button>
                                <button type="button" class="button crm-email-preview-switch-btn" data-doc="anmeldung" title="<?php esc_attr_e('Anmeldebestätigung', 'custom-crm'); ?>">
                                    <span class="dashicons dashicons-saved" style="font-size:13px; vertical-align:text-top;"></span> Anmeldung
                                </button>
                                <button type="button" class="button crm-email-preview-switch-btn" data-doc="tb" title="<?php esc_attr_e('Teilnahmebestätigung', 'custom-crm'); ?>">
                                    <span class="dashicons dashicons-id-alt" style="font-size:13px; vertical-align:text-top;"></span> TB
                                </button>
                                <button type="button" class="button crm-email-preview-switch-btn" data-doc="diplom" title="<?php esc_attr_e('Diplom / Zertifikat', 'custom-crm'); ?>">
                                    <span class="dashicons dashicons-awards" style="font-size:13px; vertical-align:text-top;"></span> Diplom
                                </button>
                                <button type="button" class="button crm-email-preview-switch-btn" data-doc="invoice" title="<?php esc_attr_e('Honorarnote / Rechnung', 'custom-crm'); ?>">
                                    <span class="dashicons dashicons-money-alt" style="font-size:13px; vertical-align:text-top;"></span> Honorarnote
                                </button>
                            </div>

                            <!-- Viewport switcher: Desktop vs Mobile vs Full -->
                            <div class="crm-email-viewport-switcher" style="display: flex; align-items: center; background: #e2e8f0; padding: 2px; border-radius: 6px;">
                                <button type="button" class="button button-small crm-viewport-btn active" data-viewport="desktop" title="<?php esc_attr_e('Desktop Ansicht (600px - Outlook Standard)', 'custom-crm'); ?>" style="font-size: 11px; height: 26px; line-height: 24px; padding: 0 8px; border: none; background: #ffffff; color: #0f172a; font-weight: 600; border-radius: 4px;">
                                    🖥️ Desktop (600px)
                                </button>
                                <button type="button" class="button button-small crm-viewport-btn" data-viewport="mobile" title="<?php esc_attr_e('Smartphone Ansicht (375px - Mobile Clients)', 'custom-crm'); ?>" style="font-size: 11px; height: 26px; line-height: 24px; padding: 0 8px; border: none; background: transparent; color: #475569; border-radius: 4px;">
                                    📱 Mobile (375px)
                                </button>
                                <button type="button" class="button button-small crm-viewport-btn" data-viewport="full" title="<?php esc_attr_e('Vollbreite Ansicht (100%)', 'custom-crm'); ?>" style="font-size: 11px; height: 26px; line-height: 24px; padding: 0 8px; border: none; background: transparent; color: #475569; border-radius: 4px;">
                                    ↔️ 100%
                                </button>
                            </div>

                            <!-- Actions toolbar -->
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <button type="button" class="button button-secondary" id="crm-email-preview-reload-btn" title="<?php esc_attr_e('Vorschau aktualisieren / neu generieren', 'custom-crm'); ?>" style="height: 30px; line-height: 28px; padding: 0 10px;">
                                    <span class="dashicons dashicons-update crm-email-reload-icon" style="font-size: 14px; vertical-align: text-top;"></span>
                                    <span class="crm-btn-text"><?php esc_html_e('Neu laden', 'custom-crm'); ?></span>
                                </button>
                                <button type="button" class="button button-secondary" id="crm-email-send-test-btn" title="<?php esc_attr_e('Diese Vorschau als Test-Mail versenden', 'custom-crm'); ?>" style="height: 30px; line-height: 28px; padding: 0 10px; color:#0284c7; border-color:#0284c7;">
                                    <span class="dashicons dashicons-email" style="font-size: 14px; vertical-align: text-top;"></span>
                                    <?php esc_html_e('Test-Mail', 'custom-crm'); ?>
                                </button>
                                <button type="button" class="button button-secondary" id="crm-email-copy-html-btn" title="<?php esc_attr_e('HTML-Quellcode in Zwischenablage kopieren', 'custom-crm'); ?>" style="height: 30px; line-height: 28px; padding: 0 10px;">
                                    <span class="dashicons dashicons-clipboard" style="font-size: 14px; vertical-align: text-top;"></span>
                                    <?php esc_html_e('HTML kopieren', 'custom-crm'); ?>
                                </button>
                                <a href="#" target="_blank" class="button button-secondary" id="crm-email-preview-newtab-btn" title="<?php esc_attr_e('In neuem Tab / Vollbild öffnen', 'custom-crm'); ?>" style="height: 30px; line-height: 28px; padding: 0 10px;">
                                    <span class="dashicons dashicons-external" style="font-size: 14px; vertical-align: text-top;"></span>
                                    <?php esc_html_e('Vollbild', 'custom-crm'); ?>
                                </a>
                                <button type="button" class="button button-secondary" id="crm-email-preview-toggle-size-btn" title="<?php esc_attr_e('Vorschau-Höhe vergrößern / verkleinern', 'custom-crm'); ?>" style="height: 30px; line-height: 28px; padding: 0 8px;">
                                    <span class="dashicons dashicons-editor-expand" style="font-size: 14px; vertical-align: text-top;"></span>
                                </button>
                            </div>
                        </div>

                        <!-- Iframe & Loading Container -->
                        <div class="crm-email-preview-body" style="position: relative; width: 100%; min-height: 600px; background: #cbd5e1; display: flex; justify-content: center; align-items: flex-start; padding: 20px 0; overflow-x: auto;">
                            <!-- Spinner Overlay -->
                            <div id="crm-email-preview-loading" style="position: absolute; inset: 0; background: rgba(255,255,255,0.88); display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 10;">
                                <span class="dashicons dashicons-update spin" style="font-size: 40px; width: 40px; height: 40px; color: #0284c7;"></span>
                                <p style="margin-top: 12px; font-weight: 600; color: #334155; font-size: 14px;" id="crm-email-preview-loading-text">
                                    <?php esc_html_e('E-Mail Live-Vorschau wird generiert...', 'custom-crm'); ?>
                                </p>
                            </div>

                            <!-- Error Message Box -->
                            <div id="crm-email-preview-error" style="display: none; position: absolute; inset: 0; background: #fff; padding: 40px; text-align: center; z-index: 10;">
                                <span class="dashicons dashicons-warning" style="font-size: 48px; width: 48px; height: 48px; color: #dc2626;"></span>
                                <h4 style="color: #dc2626; margin: 10px 0 6px 0; font-size: 16px;"><?php esc_html_e('Vorschau konnte nicht gerendert werden', 'custom-crm'); ?></h4>
                                <p id="crm-email-preview-error-msg" style="color: #64748b; font-size: 13px; max-width: 500px; margin: 0 auto 16px auto;"></p>
                                <button type="button" class="button button-primary" onclick="jQuery('#crm-email-preview-reload-btn').trigger('click');">
                                    <?php esc_html_e('Erneut versuchen', 'custom-crm'); ?>
                                </button>
                            </div>

                            <!-- Embedded Iframe with responsive wrapper -->
                            <div id="crm-email-iframe-wrapper" class="viewport-desktop" style="width: 600px; max-width: 100%; transition: width 0.25s ease, box-shadow 0.25s ease; box-shadow: 0 4px 14px rgba(0,0,0,0.12); border-radius: 8px; overflow: hidden; background: #ffffff;">
                                <iframe id="crm-email-preview-iframe"
                                        src="about:blank"
                                        style="width: 100%; height: 620px; border: none; display: block;"
                                        title="<?php esc_attr_e('E-Mail Live-Vorschau', 'custom-crm'); ?>">
                                </iframe>
                            </div>
                        </div>

                        <div class="crm-preview-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 8px 18px; display: flex; align-items: center; justify-content: space-between; font-size: 11.5px; color: #64748b; flex-wrap: wrap; gap: 8px;">
                            <span>
                                <span class="dashicons dashicons-info" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; color: #0284c7;"></span>
                                <?php esc_html_e('Hinweis: Nach Änderungen an Textbausteinen oder Abschnitten und Speichern aktualisiert sich diese E-Mail-Vorschau automatisch.', 'custom-crm'); ?>
                            </span>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <span id="crm-email-preview-updated-at" style="font-weight: 500;"></span>
                                <a href="#crm-tab-emails" class="button button-link" style="text-decoration:none; color:#0284c7; font-size:11.5px;">
                                    <span class="dashicons dashicons-arrow-up-alt2" style="font-size:14px; vertical-align:text-top;"></span> <?php esc_html_e('Nach oben zu den Vorlagen ↑', 'custom-crm'); ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <p style="margin-top: 25px;">
                        <button type="submit" name="submit_emails" class="button button-primary button-large" style="background:#007C90; border-color:#007C90; font-size:14px; height:38px; padding:0 24px;">
                            <span class="dashicons dashicons-saved" style="vertical-align:text-bottom;"></span> <?php esc_html_e('Alle E-Mail-Vorlagen speichern', 'custom-crm'); ?>
                        </button>
                    </p>
                </div>