<?php
if (!defined('ABSPATH')) exit;
?>
                <div class="crm-tab-panel" id="crm-tab-general">
                    <!-- Intro Header -->
                    <div class="crm-settings-card" style="background: linear-gradient(to right, #f8fafc, #f1f5f9); border-left: 4px solid #007C90;">
                        <h2 style="margin: 0 0 6px 0; font-size: 17px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                            <span class="dashicons dashicons-admin-generic" style="color: #007C90; font-size: 24px;"></span>
                            <?php esc_html_e('Demographische Stammdaten & Corporate Identity (CI)', 'custom-crm'); ?>
                        </h2>
                        <p style="margin: 0; color: #475569; font-size: 13.5px; line-height: 1.5;">
                            <?php esc_html_e('Hinterlegen Sie hier die zentralen Unternehmens- und Demographiedaten des Schulungsinstituts sowie Ihr CI-Branding (Logo, Farbschema, Slogan). Alle Angaben werden im CRM-System, in generierten PDF-Dokumenten (Angebot, Bestätigungen, Diplome) und in E-Mail-Vorlagen über dynamische Platzhalter verwendet.', 'custom-crm'); ?>
                        </p>
                    </div>

                    <!-- CARD 1: Corporate Identity & Logo -->
                    <div class="crm-settings-card">
                        <div class="crm-settings-card-header">
                            <h2>
                                <span class="dashicons dashicons-format-image" style="color: #007C90; font-size: 22px;"></span>
                                <?php esc_html_e('Corporate Identity & Logo', 'custom-crm'); ?>
                            </h2>
                            <span class="crm-section-tag"><?php esc_html_e('Branding & CI', 'custom-crm'); ?></span>
                        </div>

                        <!-- Hauptlogo -->
                        <div style="margin-bottom: 22px;">
                            <label class="crm-form-label" for="crm_logo_url">
                                <?php esc_html_e('Instituts-Logo (Hauptlogo für Angebote, PDFs & CRM):', 'custom-crm'); ?>
                            </label>
                            <div class="crm-logo-upload-box">
                                <div class="crm-logo-preview-wrap">
                                    <?php
                                    $current_logo_url = !empty($general_settings['logo_url']) ? $general_settings['logo_url'] : get_template_directory_uri() . '/inc/core/crm/assets/xsieben_logo.png';
                                    ?>
                                    <img id="crm_logo_preview" src="<?php echo esc_url($current_logo_url); ?>" alt="Instituts-Logo" />
                                </div>
                                <div class="crm-logo-controls">
                                    <input type="url" name="crm_general[logo_url]" id="crm_logo_url" value="<?php echo esc_attr($general_settings['logo_url'] ?? ''); ?>" class="regular-text widefat" placeholder="https://x-sieben.at/wp-content/..." style="height: 36px; margin-bottom: 8px;" />
                                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                        <button type="button" class="button button-secondary crm-media-upload-btn" data-target-input="crm_logo_url" data-target-preview="crm_logo_preview">
                                            <span class="dashicons dashicons-upload" style="vertical-align: text-top; font-size: 15px;"></span>
                                            <?php esc_html_e('Logo aus Mediathek wählen / hochladen', 'custom-crm'); ?>
                                        </button>
                                        <button type="button" class="button crm-media-remove-btn" data-target-input="crm_logo_url" data-target-preview="crm_logo_preview" style="color: #dc2626; border-color: #fca5a5;">
                                            <span class="dashicons dashicons-trash" style="vertical-align: text-top; font-size: 15px;"></span>
                                            <?php esc_html_e('Entfernen', 'custom-crm'); ?>
                                        </button>
                                        <button type="button" class="button button-link crm-reset-default-logo-btn" data-default-logo="<?php echo esc_url(get_template_directory_uri() . '/inc/core/crm/assets/xsieben_logo.png'); ?>" data-target-input="crm_logo_url" data-target-preview="crm_logo_preview">
                                            <?php esc_html_e('Standard-Logo wiederherstellen', 'custom-crm'); ?>
                                        </button>
                                    </div>
                                    <p class="crm-form-help">
                                        <?php esc_html_e('Empfohlenes Format: PNG oder SVG mit transparentem Hintergrund. Erscheint im Header aller PDF-Dokumente und kann per {company_logo} eingebunden werden.', 'custom-crm'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Sekundärlogo -->
                        <div style="margin-bottom: 22px;">
                            <label class="crm-form-label" for="crm_logo_secondary_url">
                                <?php esc_html_e('Sekundär-Logo / Signatur-Logo (Optional):', 'custom-crm'); ?>
                            </label>
                            <div class="crm-logo-upload-box">
                                <div class="crm-logo-preview-wrap">
                                    <img id="crm_logo_secondary_preview" src="<?php echo esc_url($general_settings['logo_secondary_url'] ?? ''); ?>" alt="Sekundär-Logo" style="<?php echo empty($general_settings['logo_secondary_url']) ? 'display:none;' : ''; ?>" />
                                </div>
                                <div class="crm-logo-controls">
                                    <input type="url" name="crm_general[logo_secondary_url]" id="crm_logo_secondary_url" value="<?php echo esc_attr($general_settings['logo_secondary_url'] ?? ''); ?>" class="regular-text widefat" placeholder="https://x-sieben.at/wp-content/..." style="height: 36px; margin-bottom: 8px;" />
                                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                        <button type="button" class="button button-secondary crm-media-upload-btn" data-target-input="crm_logo_secondary_url" data-target-preview="crm_logo_secondary_preview">
                                            <span class="dashicons dashicons-upload" style="vertical-align: text-top; font-size: 15px;"></span>
                                            <?php esc_html_e('Logo auswählen / hochladen', 'custom-crm'); ?>
                                        </button>
                                        <button type="button" class="button crm-media-remove-btn" data-target-input="crm_logo_secondary_url" data-target-preview="crm_logo_secondary_preview" style="color: #dc2626; border-color: #fca5a5;">
                                            <span class="dashicons dashicons-trash" style="vertical-align: text-top; font-size: 15px;"></span>
                                            <?php esc_html_e('Entfernen', 'custom-crm'); ?>
                                        </button>
                                    </div>
                                    <p class="crm-form-help">
                                        <?php esc_html_e('Für alternative E-Mail-Signaturen oder Dokumenten-Fußzeilen.', 'custom-crm'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- CI-Farbschema -->
                        <div style="margin-bottom: 20px;">
                            <label class="crm-form-label"><?php esc_html_e('CI-Farbschema (Corporate Design Farben):', 'custom-crm'); ?></label>
                            <div class="crm-color-group">
                                <div class="crm-color-item">
                                    <input type="color" class="crm-color-picker" data-target-hex="crm_ci_primary_color" value="<?php echo esc_attr($general_settings['ci_primary_color'] ?? '#007C90'); ?>" />
                                    <div>
                                        <label for="crm_ci_primary_color" style="display:block; font-size:11px; font-weight:600; color:#475569;">Primärfarbe</label>
                                        <input type="text" id="crm_ci_primary_color" name="crm_general[ci_primary_color]" class="crm-color-hex-input" value="<?php echo esc_attr($general_settings['ci_primary_color'] ?? '#007C90'); ?>" />
                                    </div>
                                </div>
                                <div class="crm-color-item">
                                    <input type="color" class="crm-color-picker" data-target-hex="crm_ci_secondary_color" value="<?php echo esc_attr($general_settings['ci_secondary_color'] ?? '#0284c7'); ?>" />
                                    <div>
                                        <label for="crm_ci_secondary_color" style="display:block; font-size:11px; font-weight:600; color:#475569;">Sekundärfarbe</label>
                                        <input type="text" id="crm_ci_secondary_color" name="crm_general[ci_secondary_color]" class="crm-color-hex-input" value="<?php echo esc_attr($general_settings['ci_secondary_color'] ?? '#0284c7'); ?>" />
                                    </div>
                                </div>
                                <div class="crm-color-item">
                                    <input type="color" class="crm-color-picker" data-target-hex="crm_ci_accent_color" value="<?php echo esc_attr($general_settings['ci_accent_color'] ?? '#0f172a'); ?>" />
                                    <div>
                                        <label for="crm_ci_accent_color" style="display:block; font-size:11px; font-weight:600; color:#475569;">Akzent / Dunkel</label>
                                        <input type="text" id="crm_ci_accent_color" name="crm_general[ci_accent_color]" class="crm-color-hex-input" value="<?php echo esc_attr($general_settings['ci_accent_color'] ?? '#0f172a'); ?>" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Claim & Akkreditierungen -->
                        <div class="crm-form-row">
                            <div class="crm-form-col">
                                <label class="crm-form-label" for="crm_company_claim"><?php esc_html_e('Instituts-Slogan / Claim:', 'custom-crm'); ?></label>
                                <input type="text" name="crm_general[company_claim]" id="crm_company_claim" value="<?php echo esc_attr($general_settings['company_claim'] ?? ''); ?>" class="widefat" style="height: 36px;" />
                            </div>
                            <div class="crm-form-col">
                                <label class="crm-form-label" for="crm_company_accreditations"><?php esc_html_e('Akkreditierungen & Partnerschaften:', 'custom-crm'); ?></label>
                                <input type="text" name="crm_general[company_accreditations]" id="crm_company_accreditations" value="<?php echo esc_attr($general_settings['company_accreditations'] ?? ''); ?>" class="widefat" style="height: 36px;" />
                            </div>
                        </div>
                    </div>

                    <!-- CARD 2: Schulungsinstitut & Demographische Stammdaten -->
                    <div class="crm-settings-card">
                        <div class="crm-settings-card-header">
                            <h2>
                                <span class="dashicons dashicons-building" style="color: #007C90; font-size: 22px;"></span>
                                <?php esc_html_e('Schulungsinstitut & Demographie', 'custom-crm'); ?>
                            </h2>
                            <span class="crm-section-tag"><?php esc_html_e('Stammdaten & Standorte', 'custom-crm'); ?></span>
                        </div>

                        <!-- Firmenname & Kurzbezeichnung -->
                        <div class="crm-form-row">
                            <div class="crm-form-col" style="flex: 2;">
                                <label class="crm-form-label" for="crm_company_name"><?php esc_html_e('Offizieller Instituts- / Firmenname:', 'custom-crm'); ?></label>
                                <input type="text" name="crm_general[company_name]" id="crm_company_name" value="<?php echo esc_attr($general_settings['company_name'] ?? ''); ?>" class="widefat" style="height: 36px; font-weight: 600;" required />
                            </div>
                            <div class="crm-form-col">
                                <label class="crm-form-label" for="crm_company_short_name"><?php esc_html_e('Markenname / Kurzform:', 'custom-crm'); ?></label>
                                <input type="text" name="crm_general[company_short_name]" id="crm_company_short_name" value="<?php echo esc_attr($general_settings['company_short_name'] ?? ''); ?>" class="widefat" style="height: 36px;" />
                            </div>
                            <div class="crm-form-col">
                                <label class="crm-form-label" for="crm_company_legal_form"><?php esc_html_e('Rechtsform:', 'custom-crm'); ?></label>
                                <input type="text" name="crm_general[company_legal_form]" id="crm_company_legal_form" value="<?php echo esc_attr($general_settings['company_legal_form'] ?? ''); ?>" class="widefat" style="height: 36px;" />
                            </div>
                        </div>

                        <!-- Geschäftsführung -->
                        <div class="crm-form-row">
                            <div class="crm-form-col">
                                <label class="crm-form-label" for="crm_company_management"><?php esc_html_e('Geschäftsführung / Vertretungsberechtigte:', 'custom-crm'); ?></label>
                                <input type="text" name="crm_general[company_management]" id="crm_company_management" value="<?php echo esc_attr($general_settings['company_management'] ?? ''); ?>" class="widefat" style="height: 36px;" />
                            </div>
                        </div>

                        <!-- Kanzleisitz / Hauptadresse -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 18px; margin-bottom: 16px;">
                            <h4 style="margin: 0 0 10px 0; color: #1e293b; font-size: 13.5px; display: flex; align-items: center; gap: 6px;">
                                <span class="dashicons dashicons-location" style="color: #0284c7; font-size: 16px;"></span>
                                <?php esc_html_e('Kanzleisitz / Firmenzentrale (Wr. Neustadt / Lichtenwörth):', 'custom-crm'); ?>
                            </h4>
                            <div class="crm-form-row" style="margin-bottom: 0;">
                                <div class="crm-form-col" style="flex: 2;">
                                    <label class="crm-form-label" for="crm_company_street"><?php esc_html_e('Straße & Hausnummer:', 'custom-crm'); ?></label>
                                    <input type="text" name="crm_general[company_street]" id="crm_company_street" value="<?php echo esc_attr($general_settings['company_street'] ?? ''); ?>" class="widefat" style="height: 34px;" />
                                </div>
                                <div class="crm-form-col" style="flex: 1;">
                                    <label class="crm-form-label" for="crm_company_zip"><?php esc_html_e('PLZ:', 'custom-crm'); ?></label>
                                    <input type="text" name="crm_general[company_zip]" id="crm_company_zip" value="<?php echo esc_attr($general_settings['company_zip'] ?? ''); ?>" class="widefat" style="height: 34px;" />
                                </div>
                                <div class="crm-form-col" style="flex: 1.5;">
                                    <label class="crm-form-label" for="crm_company_city"><?php esc_html_e('Ort:', 'custom-crm'); ?></label>
                                    <input type="text" name="crm_general[company_city]" id="crm_company_city" value="<?php echo esc_attr($general_settings['company_city'] ?? ''); ?>" class="widefat" style="height: 34px;" />
                                </div>
                                <div class="crm-form-col" style="flex: 1;">
                                    <label class="crm-form-label" for="crm_company_country"><?php esc_html_e('Land:', 'custom-crm'); ?></label>
                                    <input type="text" name="crm_general[company_country]" id="crm_company_country" value="<?php echo esc_attr($general_settings['company_country'] ?? ''); ?>" class="widefat" style="height: 34px;" />
                                </div>
                            </div>
                        </div>

                        <!-- Schulungszentrum Wien -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 18px;">
                            <h4 style="margin: 0 0 10px 0; color: #1e293b; font-size: 13.5px; display: flex; align-items: center; gap: 6px;">
                                <span class="dashicons dashicons-welcome-learn-more" style="color: #0f766e; font-size: 16px;"></span>
                                <?php esc_html_e('Seminarzentrum & Schulungsort Wien:', 'custom-crm'); ?>
                            </h4>
                            <div class="crm-form-row">
                                <div class="crm-form-col" style="flex: 1.5;">
                                    <label class="crm-form-label" for="crm_location_wien_name"><?php esc_html_e('Standortbezeichnung:', 'custom-crm'); ?></label>
                                    <input type="text" name="crm_general[location_wien_name]" id="crm_location_wien_name" value="<?php echo esc_attr($general_settings['location_wien_name'] ?? ''); ?>" class="widefat" style="height: 34px;" />
                                </div>
                                <div class="crm-form-col" style="flex: 2;">
                                    <label class="crm-form-label" for="crm_location_wien_street"><?php esc_html_e('Straße & Hausnummer:', 'custom-crm'); ?></label>
                                    <input type="text" name="crm_general[location_wien_street]" id="crm_location_wien_street" value="<?php echo esc_attr($general_settings['location_wien_street'] ?? ''); ?>" class="widefat" style="height: 34px;" />
                                </div>
                                <div class="crm-form-col" style="flex: 1;">
                                    <label class="crm-form-label" for="crm_location_wien_zip"><?php esc_html_e('PLZ:', 'custom-crm'); ?></label>
                                    <input type="text" name="crm_general[location_wien_zip]" id="crm_location_wien_zip" value="<?php echo esc_attr($general_settings['location_wien_zip'] ?? ''); ?>" class="widefat" style="height: 34px;" />
                                </div>
                                <div class="crm-form-col" style="flex: 1.5;">
                                    <label class="crm-form-label" for="crm_location_wien_city"><?php esc_html_e('Ort:', 'custom-crm'); ?></label>
                                    <input type="text" name="crm_general[location_wien_city]" id="crm_location_wien_city" value="<?php echo esc_attr($general_settings['location_wien_city'] ?? ''); ?>" class="widefat" style="height: 34px;" />
                                </div>
                            </div>
                            <div class="crm-form-row" style="margin-bottom: 0;">
                                <div class="crm-form-col-full">
                                    <label class="crm-form-label" for="crm_location_wien_notice"><?php esc_html_e('Hinweis zur Schulungsdurchführung (z.B. für Angebot-PDF & Bestätigungen):', 'custom-crm'); ?></label>
                                    <input type="text" name="crm_general[location_wien_notice]" id="crm_location_wien_notice" value="<?php echo esc_attr($general_settings['location_wien_notice'] ?? ''); ?>" class="widefat" style="height: 34px;" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CARD 3: Kontaktdaten & Backoffice-Kommunikation -->
                    <div class="crm-settings-card">
                        <div class="crm-settings-card-header">
                            <h2>
                                <span class="dashicons dashicons-phone" style="color: #007C90; font-size: 22px;"></span>
                                <?php esc_html_e('Kontaktdaten & Backoffice', 'custom-crm'); ?>
                            </h2>
                            <span class="crm-section-tag"><?php esc_html_e('Kommunikation', 'custom-crm'); ?></span>
                        </div>

                        <!-- Allgemeine Kontaktdaten -->
                        <div class="crm-form-row">
                            <div class="crm-form-col">
                                <label class="crm-form-label" for="crm_company_phone"><?php esc_html_e('Zentrale Telefonnummer:', 'custom-crm'); ?></label>
                                <input type="text" name="crm_general[company_phone]" id="crm_company_phone" value="<?php echo esc_attr($general_settings['company_phone'] ?? ''); ?>" class="widefat" style="height: 36px;" />
                            </div>
                            <div class="crm-form-col">
                                <label class="crm-form-label" for="crm_company_email"><?php esc_html_e('Zentrale E-Mail-Adresse:', 'custom-crm'); ?></label>
                                <input type="email" name="crm_general[company_email]" id="crm_company_email" value="<?php echo esc_attr($general_settings['company_email'] ?? ''); ?>" class="widefat" style="height: 36px;" />
                            </div>
                            <div class="crm-form-col">
                                <label class="crm-form-label" for="crm_company_website"><?php esc_html_e('Website-URL:', 'custom-crm'); ?></label>
                                <input type="url" name="crm_general[company_website]" id="crm_company_website" value="<?php echo esc_attr($general_settings['company_website'] ?? ''); ?>" class="widefat" style="height: 36px;" />
                            </div>
                        </div>

                        <!-- Backoffice Betreuung (Anna Brauer) -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 18px;">
                            <h4 style="margin: 0 0 10px 0; color: #1e293b; font-size: 13.5px; display: flex; align-items: center; gap: 6px;">
                                <span class="dashicons dashicons-businesswoman" style="color: #6366f1; font-size: 16px;"></span>
                                <?php esc_html_e('Backoffice-Ansprechperson (z.B. für E-Mail-Signaturen & Angebote):', 'custom-crm'); ?>
                            </h4>
                            <div class="crm-form-row" style="margin-bottom: 0;">
                                <div class="crm-form-col">
                                    <label class="crm-form-label" for="crm_backoffice_name"><?php esc_html_e('Name:', 'custom-crm'); ?></label>
                                    <input type="text" name="crm_general[backoffice_name]" id="crm_backoffice_name" value="<?php echo esc_attr($general_settings['backoffice_name'] ?? ''); ?>" class="widefat" style="height: 34px;" />
                                </div>
                                <div class="crm-form-col">
                                    <label class="crm-form-label" for="crm_backoffice_email"><?php esc_html_e('E-Mail-Adresse:', 'custom-crm'); ?></label>
                                    <input type="email" name="crm_general[backoffice_email]" id="crm_backoffice_email" value="<?php echo esc_attr($general_settings['backoffice_email'] ?? ''); ?>" class="widefat" style="height: 34px;" />
                                </div>
                                <div class="crm-form-col">
                                    <label class="crm-form-label" for="crm_backoffice_phone"><?php esc_html_e('Direktwahl / Telefon:', 'custom-crm'); ?></label>
                                    <input type="text" name="crm_general[backoffice_phone]" id="crm_backoffice_phone" value="<?php echo esc_attr($general_settings['backoffice_phone'] ?? ''); ?>" class="widefat" style="height: 34px;" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CARD 4: Rechtliche Stammdaten, Bank & Compliance -->
                    <div class="crm-settings-card">
                        <div class="crm-settings-card-header">
                            <h2>
                                <span class="dashicons dashicons-shield" style="color: #007C90; font-size: 22px;"></span>
                                <?php esc_html_e('Behördliche Stammdaten & Bankverbindung', 'custom-crm'); ?>
                            </h2>
                            <span class="crm-section-tag"><?php esc_html_e('Compliance & Recht', 'custom-crm'); ?></span>
                        </div>

                        <!-- UID, FN, Gericht -->
                        <div class="crm-form-row">
                            <div class="crm-form-col">
                                <label class="crm-form-label" for="crm_company_uid"><?php esc_html_e('UID-Nummer:', 'custom-crm'); ?></label>
                                <input type="text" name="crm_general[company_uid]" id="crm_company_uid" value="<?php echo esc_attr($general_settings['company_uid'] ?? ''); ?>" class="widefat" style="height: 36px;" />
                            </div>
                            <div class="crm-form-col">
                                <label class="crm-form-label" for="crm_company_fn"><?php esc_html_e('Firmenbuchnummer (FN):', 'custom-crm'); ?></label>
                                <input type="text" name="crm_general[company_fn]" id="crm_company_fn" value="<?php echo esc_attr($general_settings['company_fn'] ?? ''); ?>" class="widefat" style="height: 36px;" />
                            </div>
                            <div class="crm-form-col">
                                <label class="crm-form-label" for="crm_company_court"><?php esc_html_e('Firmenbuchgericht:', 'custom-crm'); ?></label>
                                <input type="text" name="crm_general[company_court]" id="crm_company_court" value="<?php echo esc_attr($general_settings['company_court'] ?? ''); ?>" class="widefat" style="height: 36px;" />
                            </div>
                        </div>

                        <!-- Kammer & Bankverbindung -->
                        <div class="crm-form-row">
                            <div class="crm-form-col">
                                <label class="crm-form-label" for="crm_company_chamber"><?php esc_html_e('Kammer / Aufsichtsbehörde:', 'custom-crm'); ?></label>
                                <input type="text" name="crm_general[company_chamber]" id="crm_company_chamber" value="<?php echo esc_attr($general_settings['company_chamber'] ?? ''); ?>" class="widefat" style="height: 36px;" />
                            </div>
                            <div class="crm-form-col" style="flex: 2;">
                                <label class="crm-form-label" for="crm_company_bank"><?php esc_html_e('Standard-Bankverbindung (IBAN / BIC):', 'custom-crm'); ?></label>
                                <input type="text" name="crm_general[company_bank]" id="crm_company_bank" value="<?php echo esc_attr($general_settings['company_bank'] ?? ''); ?>" class="widefat" style="height: 36px;" />
                            </div>
                        </div>

                        <!-- Rechtliche URLs -->
                        <div class="crm-form-row" style="margin-bottom: 0;">
                            <div class="crm-form-col">
                                <label class="crm-form-label" for="crm_legal_agb_url"><?php esc_html_e('AGB-Link (PDF oder Seite):', 'custom-crm'); ?></label>
                                <input type="url" name="crm_general[legal_agb_url]" id="crm_legal_agb_url" value="<?php echo esc_attr($general_settings['legal_agb_url'] ?? ''); ?>" class="widefat" style="height: 36px;" />
                            </div>
                            <div class="crm-form-col">
                                <label class="crm-form-label" for="crm_legal_privacy_url"><?php esc_html_e('Datenschutzerklärung-Link:', 'custom-crm'); ?></label>
                                <input type="url" name="crm_general[legal_privacy_url]" id="crm_legal_privacy_url" value="<?php echo esc_attr($general_settings['legal_privacy_url'] ?? ''); ?>" class="widefat" style="height: 36px;" />
                            </div>
                            <div class="crm-form-col">
                                <label class="crm-form-label" for="crm_legal_imprint_url"><?php esc_html_e('Impressum-Link:', 'custom-crm'); ?></label>
                                <input type="url" name="crm_general[legal_imprint_url]" id="crm_legal_imprint_url" value="<?php echo esc_attr($general_settings['legal_imprint_url'] ?? ''); ?>" class="widefat" style="height: 36px;" />
                            </div>
                        </div>
                    </div>

                    <!-- CARD 5: Schnittstellen & Mailer-Einstellungen -->
                    <div class="crm-settings-card">
                        <div class="crm-settings-card-header">
                            <h2>
                                <span class="dashicons dashicons-admin-plugins" style="color: #007C90; font-size: 22px;"></span>
                                <?php esc_html_e('Schnittstellen & Mailer-Einstellungen', 'custom-crm'); ?>
                            </h2>
                            <span class="crm-section-tag"><?php esc_html_e('Integration', 'custom-crm'); ?></span>
                        </div>
                        <p style="color: #475569; font-size: 13.5px; line-height: 1.5; margin-top: 0;">
                            <?php esc_html_e('Konfigurieren Sie hier die Verknüpfung zum WPForms-Formular für Kursanfragen sowie die Standard-Test-E-Mail-Adresse für das Backoffice.', 'custom-crm'); ?>
                        </p>
                        <div class="crm-form-row" style="margin-bottom: 0; display: flex; gap: 24px; flex-wrap: wrap;">
                            <div class="crm-form-col" style="max-width: 320px; flex: 1;">
                                <label class="crm-form-label" for="crm_wpforms_form_id"><?php esc_html_e('WPForms Anfrage-Formular ID:', 'custom-crm'); ?></label>
                                <input name="crm_general[wpforms_form_id]" type="number" id="crm_wpforms_form_id" value="<?php echo esc_attr($general_settings['wpforms_form_id'] ?? '60468'); ?>" class="small-text" style="height: 36px; width: 140px; font-weight: 600;" min="1" step="1" required />
                                <p class="crm-form-help"><?php esc_html_e('ID des primären Kursanfrage-Formulars (Standard: 60468). Synchronisiert automatische Statuseinträge und Feld-Mappings.', 'custom-crm'); ?></p>
                            </div>
                            <div class="crm-form-col" style="max-width: 420px; flex: 1;">
                                <label class="crm-form-label" for="crm_test_email"><?php esc_html_e('Standard Test-Empfänger:', 'custom-crm'); ?></label>
                                <input name="crm_general[test_email]" type="email" id="crm_test_email" value="<?php echo esc_attr($general_settings['test_email'] ?? $current_test_email); ?>" class="widefat" style="height: 36px;" placeholder="test@x-sieben.at" required />
                                <p class="crm-form-help"><?php esc_html_e('Wird im CRM-Mailer automatisch bei jedem Testversand vorausgewählt.', 'custom-crm'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- CARD 6: Performance & Cache-Busting (NEXUS Cache Operator C) -->
                    <div class="crm-settings-card" style="border-left: 4px solid #0284c7;">
                        <div class="crm-settings-card-header">
                            <h2>
                                <span class="dashicons dashicons-update" style="color: #0284c7; font-size: 22px;"></span>
                                <?php esc_html_e('Performance & Cache-Busting (NEXUS Cache-Operator C)', 'custom-crm'); ?>
                            </h2>
                            <span class="crm-section-tag" style="background:#e0f2fe; color:#0369a1;"><?php esc_html_e('Fixpunkt C(X)', 'custom-crm'); ?></span>
                        </div>
                        <p style="color: #475569; font-size: 13.5px; line-height: 1.5; margin-top: 0;">
                            <?php esc_html_e('Steuert die automatische JS- und Asset-Cache-Invalidierung. Nach den NEXUS-Vorgaben wird der Cache im Browser und Server stabil gehalten ($C(X) = X$) und ausschließlich bei gezielten partiellen Änderungen (z. B. Verschieben von PDF-/E-Mail-Abschnitten, Ändern von Betreffzeilen oder Statusaktualisierungen) automatisch invalidiert.', 'custom-crm'); ?>
                        </p>
                        
                        <div class="crm-form-row" style="align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; background: #f8fafc; padding: 14px 18px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 16px;">
                            <div style="flex: 1; min-width: 280px;">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 600; color: #1e293b; font-size: 14px;">
                                    <input type="checkbox" name="crm_general[auto_js_cache_clean]" value="1" <?php checked(!empty($general_settings['auto_js_cache_clean'])); ?> style="width: 18px; height: 18px;" />
                                    <span><?php esc_html_e('Automatisches JS-Cache-Clean bei partiellem Cache-Update aktivieren (Flag)', 'custom-crm'); ?></span>
                                </label>
                                <p style="margin: 4px 0 0 28px; font-size: 12.5px; color: #64748b; line-height: 1.4;">
                                    <?php esc_html_e('Wenn aktiv, werden bei jedem partiellen Update (z.B. E-Mail-/PDF-Abschnitte speichern) die Browser-Asset-Version gebumpt und die clientseitigen JS-Caches automatisch bereinigt.', 'custom-crm'); ?>
                                </p>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <button type="button" class="button button-secondary" id="crm-btn-manual-purge-cache" style="height: 34px; line-height: 32px; padding: 0 14px; border-color: #0284c7; color: #0284c7;">
                                    <span class="dashicons dashicons-trash" style="font-size: 15px; vertical-align: text-top; margin-top: 1px;"></span>
                                    <?php esc_html_e('JS-Cache jetzt leeren', 'custom-crm'); ?>
                                </button>
                                <span id="crm-cache-purge-status" style="font-size: 12.5px; font-weight: 600;"></span>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 14px; font-size: 12.5px; color: #64748b;">
                            <span><strong><?php esc_html_e('Aktuelle Asset-Version:', 'custom-crm'); ?></strong> <code style="background:#e2e8f0; padding:2px 6px; border-radius:4px; font-weight:600; color:#0f172a;" id="crm-current-asset-version"><?php echo esc_html(function_exists('crm_get_asset_version') ? crm_get_asset_version() : CRM_VERSION); ?></code></span>
                            <span>•</span>
                            <span><strong><?php esc_html_e('Cache-Buster-Key:', 'custom-crm'); ?></strong> <code style="background:#e2e8f0; padding:2px 6px; border-radius:4px;" id="crm-current-cache-buster"><?php echo esc_html(function_exists('crm_get_js_cache_version') ? crm_get_js_cache_version() : time()); ?></code></span>
                        </div>
                    </div>

                    <!-- CARD 7: CRM-Platzhalter Übersicht für Demographie & CI -->
                    <div class="crm-settings-card" style="background: #f8fafc; border: 1px solid #cbd5e1;">
                        <h3 style="margin-top: 0; color: #1e293b; font-size: 15px; display: flex; align-items: center; gap: 8px;">
                            <span class="dashicons dashicons-info" style="color: #007C90;"></span>
                            <?php esc_html_e('Verfügbare CRM-Platzhalter für Vorlagen (Klick zum Kopieren)', 'custom-crm'); ?>
                        </h3>
                        <p style="font-size: 13px; color: #475569; margin: 0 0 14px 0;">
                            <?php esc_html_e('Diese Platzhalter können Sie in allen E-Mail-Vorlagen und PDF-Bausteinen verwenden. Sie werden beim Rendern automatisch durch die hier hinterlegten Stammdaten ersetzt:', 'custom-crm'); ?>
                        </p>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                <span style="font-size:11.5px; font-weight:600; color:#475569; width:130px;">Institut & Recht:</span>
                                <a href="#" class="crm-chip" data-code="{company_name}">{company_name}</a>
                                <a href="#" class="crm-chip" data-code="{company_short_name}">{company_short_name}</a>
                                <a href="#" class="crm-chip" data-code="{company_management}">{company_management}</a>
                                <a href="#" class="crm-chip" data-code="{company_uid}">{company_uid}</a>
                                <a href="#" class="crm-chip" data-code="{company_fn}">{company_fn}</a>
                                <a href="#" class="crm-chip" data-code="{company_court}">{company_court}</a>
                                <a href="#" class="crm-chip" data-code="{company_bank}">{company_bank}</a>
                            </div>
                            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                <span style="font-size:11.5px; font-weight:600; color:#475569; width:130px;">Standorte & Adressen:</span>
                                <a href="#" class="crm-chip" data-code="{company_address}">{company_address}</a>
                                <a href="#" class="crm-chip" data-code="{company_street}">{company_street}</a>
                                <a href="#" class="crm-chip" data-code="{company_zip}">{company_zip}</a>
                                <a href="#" class="crm-chip" data-code="{company_city}">{company_city}</a>
                                <a href="#" class="crm-chip" data-code="{location_wien}">{location_wien}</a>
                            </div>
                            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                <span style="font-size:11.5px; font-weight:600; color:#475569; width:130px;">Kontakt & Team:</span>
                                <a href="#" class="crm-chip" data-code="{company_phone}">{company_phone}</a>
                                <a href="#" class="crm-chip" data-code="{company_email}">{company_email}</a>
                                <a href="#" class="crm-chip" data-code="{company_website}">{company_website}</a>
                                <a href="#" class="crm-chip" data-code="{backoffice_name}">{backoffice_name}</a>
                                <a href="#" class="crm-chip" data-code="{backoffice_email}">{backoffice_email}</a>
                            </div>
                            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                <span style="font-size:11.5px; font-weight:600; color:#475569; width:130px;">CI & Links:</span>
                                <a href="#" class="crm-chip" data-code="{company_logo}">{company_logo} (Bild-Tag)</a>
                                <a href="#" class="crm-chip" data-code="{company_logo_url}">{company_logo_url} (URL)</a>
                                <a href="#" class="crm-chip" data-code="{agb_url}">{agb_url}</a>
                                <a href="#" class="crm-chip" data-code="{privacy_url}">{privacy_url}</a>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <p style="margin-top: 25px;">
                        <button type="submit" name="submit_general" class="button button-primary button-large" style="background:#007C90; border-color:#007C90; font-size:14px; height:40px; padding:0 26px;">
                            <span class="dashicons dashicons-saved" style="vertical-align:text-bottom; margin-right: 4px;"></span>
                            <?php esc_html_e('Allgemeine Einstellungen & Stammdaten speichern', 'custom-crm'); ?>
                        </button>
                    </p>
                </div>