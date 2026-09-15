<?php
if (!defined('ABSPATH')) exit;
?>
                <div class="crm-tab-panel" id="crm-tab-pdf">
                    <!-- Intro & Header -->
                    <div class="crm-editor-header-box" style="background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 18px 22px; margin-bottom: 20px;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px;">
                            <div>
                                <h2 style="margin:0 0 6px 0; color:#0f172a; font-size:18px; display:flex; align-items:center; gap:8px;">
                                    <span class="dashicons dashicons-media-document" style="color:#7c3aed; font-size:24px;"></span>
                                    <?php esc_html_e('PDF Bausteine, Anhänge & Klauseln', 'custom-crm'); ?>
                                </h2>
                                <p style="margin:0; color:#475569; font-size:13.5px;">
                                    <?php esc_html_e('Verwalten Sie hier alle Textbausteine, AGBs, Bankverbindungen und Klauseln aller PDF-Dokumente: Kurszeitenbestätigung (KB), Teilnahmebestätigung (TB), Diplom, Angebot & Honorarnote.', 'custom-crm'); ?>
                                </p>
                            </div>
                            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                <a href="#crm-pdf-preview-section" class="button button-secondary" style="border-color:#7c3aed; color:#6d28d9; height:32px; line-height:30px; padding:0 14px;">
                                    <span class="dashicons dashicons-visibility" style="vertical-align:text-top; font-size:16px;"></span> <?php esc_html_e('Zur Live-Vorschau springen ↓', 'custom-crm'); ?>
                                </a>
                            </div>
                        </div>

                        <!-- Variable Cheat Sheet Box -->
                        <?php crm_render_placeholders_cheat_sheet('pdf'); ?>
                    </div>

                    <!-- Master Header & Footer Box (Angebot-PDF) -->
                    <?php
                    $master_hf = function_exists('crm_get_pdf_master_header_footer') ? crm_get_pdf_master_header_footer() : [];
                    ?>
                    <div class="crm-pdf-master-hf-box" style="background: #ffffff; border: 1px solid #cbd5e1; border-left: 4px solid #7c3aed; border-radius: 8px; padding: 18px 22px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom: 14px;">
                            <div>
                                <h3 style="margin:0 0 4px 0; color:#0f172a; font-size:16px; display:flex; align-items:center; gap:8px;">
                                    <span class="dashicons dashicons-admin-appearance" style="color:#7c3aed; font-size:20px;"></span>
                                    <?php esc_html_e('Kopf- & Fußzeilen Master-Einstellungen (Angebot-PDF)', 'custom-crm'); ?>
                                </h3>
                                <p style="margin:0; color:#475569; font-size:13px;">
                                    <?php esc_html_e('Definieren Sie hier die Standard-Vorlage für Kopf- und Fußzeilen des Angebots. Jede Seite im Abschnitt-Manager kann diese Master-Einstellung erben oder individuell übersteuern.', 'custom-crm'); ?>
                                </p>
                            </div>
                            <div>
                                <span class="crm-section-tag" style="background:#f5f3ff; color:#6d28d9; border:1px solid #ddd6fe; font-size:11px; padding:3px 8px; border-radius:4px; font-weight:600;">
                                    <?php esc_html_e('Master-Vorlage', 'custom-crm'); ?>
                                </span>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                            <!-- MASTER HEADER -->
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:14px;">
                                <h4 style="margin:0 0 10px 0; font-size:13px; color:#0f172a; display:flex; align-items:center; gap:6px;">
                                    <span class="dashicons dashicons-heading" style="color:#007C90;"></span>
                                    <?php esc_html_e('Standard-Kopfzeile (Master-Header)', 'custom-crm'); ?>
                                </h4>

                                <div style="margin-bottom:10px;">
                                    <label style="display:block; font-size:11.5px; font-weight:600; color:#334155; margin-bottom:3px;">
                                        <?php esc_html_e('Standard Header-Modus:', 'custom-crm'); ?>
                                    </label>
                                    <select name="crm_pdf_master_hf[header_mode]" class="crm-master-header-mode regular-text" style="width:100%; height:32px; font-size:12px;">
                                        <option value="full" <?php selected(($master_hf['header_mode'] ?? 'full'), 'full'); ?>><?php esc_html_e('Logo links & Firmenadresse rechts (Vollständig)', 'custom-crm'); ?></option>
                                        <option value="logo_only" <?php selected(($master_hf['header_mode'] ?? 'full'), 'logo_only'); ?>><?php esc_html_e('Nur Logo (ohne Adresse)', 'custom-crm'); ?></option>
                                        <option value="address_only" <?php selected(($master_hf['header_mode'] ?? 'full'), 'address_only'); ?>><?php esc_html_e('Nur Firmenadresse & Kontaktdaten (ohne Logo)', 'custom-crm'); ?></option>
                                        <option value="none" <?php selected(($master_hf['header_mode'] ?? 'full'), 'none'); ?>><?php esc_html_e('🚫 Keine Kopfzeile (Standardmäßig ausblenden)', 'custom-crm'); ?></option>
                                    </select>
                                </div>

                                <div style="display:flex; flex-wrap:wrap; gap:14px; font-size:12px; color:#334155; margin-bottom:6px;">
                                    <label style="display:inline-flex; align-items:center; gap:5px; cursor:pointer;">
                                        <input type="checkbox" name="crm_pdf_master_hf[header_logo]" value="1" <?php checked(!empty($master_hf['header_logo'])); ?>>
                                        <span><?php esc_html_e('Logo einblenden', 'custom-crm'); ?></span>
                                    </label>
                                    <label style="display:inline-flex; align-items:center; gap:5px; cursor:pointer;">
                                        <input type="checkbox" name="crm_pdf_master_hf[header_address]" value="1" <?php checked(!empty($master_hf['header_address'])); ?>>
                                        <span><?php esc_html_e('Firmenadresse & Kontakt einblenden', 'custom-crm'); ?></span>
                                    </label>
                                </div>
                                <p style="margin:4px 0 0 0; font-size:11px; color:#64748b;">
                                    <?php esc_html_e('Firmendaten und Logo stammen aus den Allgemeinen CRM-Einstellungen.', 'custom-crm'); ?>
                                </p>
                            </div>

                            <!-- MASTER FOOTER -->
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:14px;">
                                <h4 style="margin:0 0 10px 0; font-size:13px; color:#0f172a; display:flex; align-items:center; gap:6px;">
                                    <span class="dashicons dashicons-editor-insertmore" style="color:#007C90;"></span>
                                    <?php esc_html_e('Standard-Fußzeile (Master-Footer)', 'custom-crm'); ?>
                                </h4>

                                <div style="margin-bottom:10px;">
                                    <label style="display:block; font-size:11.5px; font-weight:600; color:#334155; margin-bottom:3px;">
                                        <?php esc_html_e('Standard Footer-Modus:', 'custom-crm'); ?>
                                    </label>
                                    <select name="crm_pdf_master_hf[footer_mode]" class="crm-master-footer-mode regular-text" style="width:100%; height:32px; font-size:12px;">
                                        <option value="standard" <?php selected(($master_hf['footer_mode'] ?? 'standard'), 'standard'); ?>><?php esc_html_e('Firmendaten + Seitenzahlen (Standard)', 'custom-crm'); ?></option>
                                        <option value="full" <?php selected(($master_hf['footer_mode'] ?? 'standard'), 'full'); ?>><?php esc_html_e('Firmendaten + Seitenzahlen + Datum', 'custom-crm'); ?></option>
                                        <option value="page_numbers_only" <?php selected(($master_hf['footer_mode'] ?? 'standard'), 'page_numbers_only'); ?>><?php esc_html_e('Nur Seitenzahlen', 'custom-crm'); ?></option>
                                        <option value="company_only" <?php selected(($master_hf['footer_mode'] ?? 'standard'), 'company_only'); ?>><?php esc_html_e('Nur Firmendaten (ohne Seitenzahlen)', 'custom-crm'); ?></option>
                                        <option value="none" <?php selected(($master_hf['footer_mode'] ?? 'standard'), 'none'); ?>><?php esc_html_e('🚫 Keine Fußzeile (Standardmäßig ausblenden)', 'custom-crm'); ?></option>
                                    </select>
                                </div>

                                <div style="display:flex; flex-wrap:wrap; gap:14px; font-size:12px; color:#334155; margin-bottom:6px;">
                                    <label style="display:inline-flex; align-items:center; gap:5px; cursor:pointer;">
                                        <input type="checkbox" name="crm_pdf_master_hf[footer_company]" value="1" <?php checked(!empty($master_hf['footer_company'])); ?>>
                                        <span><?php esc_html_e('Firmendaten (UID, FN, Gericht)', 'custom-crm'); ?></span>
                                    </label>
                                    <label style="display:inline-flex; align-items:center; gap:5px; cursor:pointer;">
                                        <input type="checkbox" name="crm_pdf_master_hf[footer_page_num]" value="1" <?php checked(!empty($master_hf['footer_page_num'])); ?>>
                                        <span><?php esc_html_e('Seitenzahlen', 'custom-crm'); ?></span>
                                    </label>
                                    <label style="display:inline-flex; align-items:center; gap:5px; cursor:pointer;">
                                        <input type="checkbox" name="crm_pdf_master_hf[footer_date]" value="1" <?php checked(!empty($master_hf['footer_date'])); ?>>
                                        <span><?php esc_html_e('Datum', 'custom-crm'); ?></span>
                                    </label>
                                </div>
                                <p style="margin:4px 0 0 0; font-size:11px; color:#64748b;">
                                    <?php esc_html_e('Kann in jeder einzelnen Angebotsseite flexibel ein- oder ausgeschaltet werden.', 'custom-crm'); ?>
                                </p>
                            </div>
                        </div>

                        <div style="margin-top:14px; display:flex; justify-content:flex-end;">
                            <button type="submit" name="submit_pdf_master_hf" class="button button-secondary" style="border-color:#7c3aed; color:#6d28d9; height:30px; line-height:28px;">
                                <span class="dashicons dashicons-saved" style="vertical-align:text-top; font-size:15px;"></span>
                                <?php esc_html_e('Master-Einstellungen speichern', 'custom-crm'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- PDF Sections Drag & Drop Organizer -->
                    <div class="crm-pdf-sections-box" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 18px 22px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,0.04);">
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom: 14px;">
                            <div>
                                <h3 style="margin:0 0 4px 0; color:#0f172a; font-size:16px; display:flex; align-items:center; gap:8px;">
                                    <span class="dashicons dashicons-menu" style="color:#007C90; font-size:20px;"></span>
                                    <?php esc_html_e('PDF-Abschnitte anordnen (Drag & Drop)', 'custom-crm'); ?>
                                </h3>
                                <p style="margin:0; color:#475569; font-size:13px;">
                                    <?php esc_html_e('Bringen Sie die Abschnitte per Ziehen in Ihre Wunsch-Reihenfolge oder blenden Sie einzelne Abschnitte aus.', 'custom-crm'); ?>
                                </p>
                            </div>
                            <div class="crm-sections-doc-pills" style="display:flex; gap:6px; flex-wrap:wrap;">
                                <button type="button" class="button crm-sec-pill active" data-doc="angebot" style="border-color:#7c3aed; color:#6d28d9; font-weight:600;">
                                    <span class="dashicons dashicons-media-document" style="font-size:13px; vertical-align:text-top;"></span> Angebot (7)
                                </button>
                                <button type="button" class="button crm-sec-pill" data-doc="kb" style="color:#0f766e;">
                                    <span class="dashicons dashicons-calendar-alt" style="font-size:13px; vertical-align:text-top;"></span> Kurszeiten KB (7)
                                </button>
                                <button type="button" class="button crm-sec-pill" data-doc="tb" style="color:#047857;">
                                    <span class="dashicons dashicons-id-alt" style="font-size:13px; vertical-align:text-top;"></span> Teilnahme TB (6)
                                </button>
                                <button type="button" class="button crm-sec-pill" data-doc="diplom" style="color:#b45309;">
                                    <span class="dashicons dashicons-awards" style="font-size:13px; vertical-align:text-top;"></span> Diplom (7)
                                </button>
                                <button type="button" class="button crm-sec-pill" data-doc="invoice" style="color:#be185d;">
                                    <span class="dashicons dashicons-money-alt" style="font-size:13px; vertical-align:text-top;"></span> Honorarnote (6)
                                </button>
                            </div>
                        </div>

                        <div class="crm-sec-tab-pane" id="crm-sec-pane-angebot" style="display:block;">
                            <?php crm_render_pdf_sections_manager('angebot', null, false); ?>
                        </div>
                        <div class="crm-sec-tab-pane" id="crm-sec-pane-kb" style="display:none;">
                            <?php crm_render_pdf_sections_manager('kb', null, false); ?>
                        </div>
                        <div class="crm-sec-tab-pane" id="crm-sec-pane-tb" style="display:none;">
                            <?php crm_render_pdf_sections_manager('tb', null, false); ?>
                        </div>
                        <div class="crm-sec-tab-pane" id="crm-sec-pane-diplom" style="display:none;">
                            <?php crm_render_pdf_sections_manager('diplom', null, false); ?>
                        </div>
                        <div class="crm-sec-tab-pane" id="crm-sec-pane-invoice" style="display:none;">
                            <?php crm_render_pdf_sections_manager('invoice', null, false); ?>
                        </div>
                    </div>


                    <!-- PDF Live-Vorschau Bereich -->
                    <div id="crm-pdf-preview-section" class="crm-preview-card" style="margin-top: 28px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden;">
                        <div class="crm-preview-header" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px;">
                                    <span class="dashicons dashicons-visibility" style="color: #7c3aed; font-size: 19px;"></span>
                                    <span><?php esc_html_e('Live-Vorschau:', 'custom-crm'); ?></span>
                                    <span id="crm-preview-doc-title" style="color: #6d28d9;"><?php esc_html_e('Kurszeitenbestätigung (KB)', 'custom-crm'); ?></span>
                                </h3>
                                <span id="crm-preview-sample-info" style="font-size: 11.5px; color: #5b21b6; background: #ede9fe; padding: 2px 8px; border-radius: 12px; font-weight: 500;">
                                    <?php esc_html_e('Wird geladen...', 'custom-crm'); ?>
                                </span>
                            </div>

                            <!-- Preview switcher pills inside preview card -->
                            <div class="crm-preview-doc-switcher" style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                <span style="font-size: 12px; font-weight: 600; color: #64748b; margin-right: 4px;"><?php esc_html_e('Dokument:', 'custom-crm'); ?></span>
                                <button type="button" class="button crm-preview-switch-btn active" data-doc="kb" title="<?php esc_attr_e('Kurszeitenbestätigung', 'custom-crm'); ?>">
                                    <span class="dashicons dashicons-calendar-alt" style="font-size:13px; vertical-align:text-top;"></span> KB
                                </button>
                                <button type="button" class="button crm-preview-switch-btn" data-doc="tb" title="<?php esc_attr_e('Teilnahmebestätigung', 'custom-crm'); ?>">
                                    <span class="dashicons dashicons-id-alt" style="font-size:13px; vertical-align:text-top;"></span> TB
                                </button>
                                <button type="button" class="button crm-preview-switch-btn" data-doc="diplom" title="<?php esc_attr_e('Diplom / Zertifikat', 'custom-crm'); ?>">
                                    <span class="dashicons dashicons-awards" style="font-size:13px; vertical-align:text-top;"></span> Diplom
                                </button>
                                <button type="button" class="button crm-preview-switch-btn" data-doc="angebot" title="<?php esc_attr_e('Angebot & Anhang', 'custom-crm'); ?>">
                                    <span class="dashicons dashicons-media-document" style="font-size:13px; vertical-align:text-top;"></span> Angebot
                                </button>
                                <button type="button" class="button crm-preview-switch-btn" data-doc="invoice" title="<?php esc_attr_e('Honorarnote / Rechnung', 'custom-crm'); ?>">
                                    <span class="dashicons dashicons-money-alt" style="font-size:13px; vertical-align:text-top;"></span> Honorarnote
                                </button>
                            </div>

                            <!-- Actions toolbar -->
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <button type="button" class="button button-secondary" id="crm-preview-reload-btn" title="<?php esc_attr_e('Vorschau aktualisieren / neu generieren', 'custom-crm'); ?>" style="height: 30px; line-height: 28px; padding: 0 10px;">
                                    <span class="dashicons dashicons-update crm-reload-icon" style="font-size: 14px; vertical-align: text-top;"></span>
                                    <span class="crm-btn-text"><?php esc_html_e('Neu laden', 'custom-crm'); ?></span>
                                </button>
                                <a href="#" target="_blank" class="button button-secondary" id="crm-preview-newtab-btn" title="<?php esc_attr_e('In neuem Tab / Vollbild öffnen', 'custom-crm'); ?>" style="height: 30px; line-height: 28px; padding: 0 10px;">
                                    <span class="dashicons dashicons-external" style="font-size: 14px; vertical-align: text-top;"></span>
                                    <?php esc_html_e('Vollbild', 'custom-crm'); ?>
                                </a>
                                <a href="#" download class="button button-secondary" id="crm-preview-download-btn" title="<?php esc_attr_e('PDF-Datei herunterladen', 'custom-crm'); ?>" style="height: 30px; line-height: 28px; padding: 0 10px;">
                                    <span class="dashicons dashicons-download" style="font-size: 14px; vertical-align: text-top;"></span>
                                    <?php esc_html_e('Download', 'custom-crm'); ?>
                                </a>
                                <button type="button" class="button button-secondary" id="crm-preview-toggle-size-btn" title="<?php esc_attr_e('Vorschau-Höhe vergrößern / verkleinern', 'custom-crm'); ?>" style="height: 30px; line-height: 28px; padding: 0 8px;">
                                    <span class="dashicons dashicons-editor-expand" style="font-size: 14px; vertical-align: text-top;"></span>
                                </button>
                            </div>
                        </div>

                        <!-- Iframe & Loading Container -->
                        <div class="crm-preview-body" style="position: relative; width: 100%; min-height: 640px; background: #525659;">
                            <!-- Spinner Overlay -->
                            <div id="crm-preview-loading" style="position: absolute; inset: 0; background: rgba(255,255,255,0.88); display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 10;">
                                <span class="dashicons dashicons-update spin" style="font-size: 40px; width: 40px; height: 40px; color: #7c3aed;"></span>
                                <p style="margin-top: 12px; font-weight: 600; color: #334155; font-size: 14px;" id="crm-preview-loading-text">
                                    <?php esc_html_e('PDF-Vorschau wird mit aktuellen Bausteinen generiert...', 'custom-crm'); ?>
                                </p>
                            </div>

                            <!-- Error Message Box -->
                            <div id="crm-preview-error" style="display: none; position: absolute; inset: 0; background: #fff; padding: 40px; text-align: center; z-index: 10;">
                                <span class="dashicons dashicons-warning" style="font-size: 48px; width: 48px; height: 48px; color: #dc2626;"></span>
                                <h4 style="color: #dc2626; margin: 10px 0 6px 0; font-size: 16px;"><?php esc_html_e('Vorschau konnte nicht gerendert werden', 'custom-crm'); ?></h4>
                                <p id="crm-preview-error-msg" style="color: #64748b; font-size: 13px; max-width: 500px; margin: 0 auto 16px auto;"></p>
                                <button type="button" class="button button-primary" onclick="jQuery('#crm-preview-reload-btn').trigger('click');">
                                    <?php esc_html_e('Erneut versuchen', 'custom-crm'); ?>
                                </button>
                            </div>

                            <!-- Embedded Iframe -->
                            <iframe id="crm-pdf-preview-iframe"
                                    src="about:blank"
                                    style="width: 100%; height: 680px; border: none; display: block;"
                                    title="<?php esc_attr_e('PDF Live-Vorschau', 'custom-crm'); ?>">
                            </iframe>
                        </div>

                        <div class="crm-preview-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 8px 18px; display: flex; align-items: center; justify-content: space-between; font-size: 11.5px; color: #64748b; flex-wrap: wrap; gap: 8px;">
                            <span>
                                <span class="dashicons dashicons-info" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle; color: #7c3aed;"></span>
                                <?php esc_html_e('Hinweis: Nach Bearbeitung eines Abschnitts oder Unterabschnitts und Klick auf „Reihenfolge & Struktur anwenden“ aktualisiert sich diese PDF-Vorschau automatisch.', 'custom-crm'); ?>
                            </span>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <span id="crm-preview-updated-at" style="font-weight: 500;"></span>
                                <a href="#crm-tab-pdf" class="button button-link" style="text-decoration:none; color:#6d28d9; font-size:11.5px;">
                                    <span class="dashicons dashicons-arrow-up-alt2" style="font-size:14px; vertical-align:text-top;"></span> <?php esc_html_e('Nach oben zu den Abschnitten ↑', 'custom-crm'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>