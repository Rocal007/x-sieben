<?php
/**
 * CRM Settings Tab: E-Mail & IMAP Postfach-Synchronisation
 *
 * Konfiguration der E-Mail-Postfächer, IMAP-Verbindung,
 * Filterregeln, automatischer Lead-Ingestion und Synchronisations-Historie.
 *
 * @package Sieben\CRM
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$imap_settings = function_exists('crm_get_imap_settings') ? crm_get_imap_settings() : [];
$sync_history  = get_option('crm_imap_sync_history', []);
if (!is_array($sync_history)) {
    $sync_history = [];
}
?>

<div class="crm-tab-panel" id="crm-tab-imap">

    <!-- Intro Header Box -->
    <div class="crm-settings-card" style="background: linear-gradient(to right, #f0fdf4, #f8fafc); border-left: 4px solid #007C90; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
            <div>
                <h2 style="margin: 0 0 6px 0; font-size: 18px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                    <span class="dashicons dashicons-email-alt" style="color: #007C90; font-size: 26px;"></span>
                    <?php esc_html_e('E-Mail Postfach & IMAP-Synchronisation', 'custom-crm'); ?>
                </h2>
                <p style="margin: 0; color: #475569; font-size: 13.5px; line-height: 1.5;">
                    <?php esc_html_e('Verbinden Sie Ihr zentrales E-Mail-Postfach (z. B. office@x-sieben.at oder anfragen@x-sieben.at) via IMAP. Neue Kundenanfragen, Kursbuchungen und weitergeleitete E-Mails werden automatisch abgeglichen, analysiert und als vollwertige Leads im CRM angelegt.', 'custom-crm'); ?>
                </p>
            </div>
            <div>
                <span class="crm-status-pill" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; <?php echo !empty($imap_settings['enabled']) ? 'background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;' : 'background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1;'; ?>">
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; <?php echo !empty($imap_settings['enabled']) ? 'background: #16a34a;' : 'background: #94a3b8;'; ?>"></span>
                    <?php echo !empty($imap_settings['enabled']) ? esc_html__('Synchronisation Aktiv', 'custom-crm') : esc_html__('Synchronisation Pausiert', 'custom-crm'); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Live-Aktionen & Test-Zentrum -->
    <div class="crm-settings-card" style="border-left: 4px solid #4f46e5; background: #ffffff;">
        <div class="crm-settings-card-header">
            <h2>
                <span class="dashicons dashicons-controls-play" style="color: #4f46e5; font-size: 22px;"></span>
                <?php esc_html_e('Direkt-Aktionen & Test-Zentrum', 'custom-crm'); ?>
            </h2>
            <span class="crm-section-tag" style="background:#e0e7ff; color:#3730a3;"><?php esc_html_e('Sicherer Test-Modus', 'custom-crm'); ?></span>
        </div>
        <p style="color: #475569; font-size: 13.5px; line-height: 1.5; margin-top: 0;">
            <?php esc_html_e('Testen Sie die Verbindung und simulieren Sie den Abgleich im sicheren Trockenlauf. Sie sehen genau, welche E-Mails gefunden, welche Kunden und Kurse erkannt und wie sie eingeteilt werden — bevor eine einzige Änderung in der CRM-Datenbank vorgenommen wird.', 'custom-crm'); ?>
        </p>

        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 12px;">
            <!-- 1. Verbindung testen -->
            <button type="button" class="button button-secondary" id="crm-btn-test-imap" style="height: 38px; line-height: 36px; padding: 0 16px; font-size: 13.5px; font-weight: 600; border-color: #0284c7; color: #0284c7;">
                <span class="dashicons dashicons-networking" style="vertical-align: text-top; font-size: 17px;"></span>
                <?php esc_html_e('1. Verbindung testen', 'custom-crm'); ?>
            </button>

            <!-- 2. Test-Simulation (Trockenlauf) -->
            <button type="button" class="button button-primary" id="crm-btn-simulate-imap" style="height: 38px; line-height: 36px; padding: 0 18px; font-size: 13.5px; font-weight: 600; background: #4f46e5; border-color: #4338ca; box-shadow: 0 1px 3px rgba(79, 70, 229, 0.3);">
                <span class="dashicons dashicons-visibility" style="vertical-align: text-top; font-size: 17px;"></span>
                <?php esc_html_e('2. Test-Simulation: Postfach analysieren', 'custom-crm'); ?>
                <span style="background: rgba(255,255,255,0.24); padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: 6px; font-weight: 700;"><?php esc_html_e('Trockenlauf (0 DB-Schreibvorgänge)', 'custom-crm'); ?></span>
            </button>

            <!-- 3. Echter Abgleich -->
            <button type="button" class="button button-primary" id="crm-btn-sync-imap" style="height: 38px; line-height: 36px; padding: 0 18px; font-size: 13.5px; font-weight: 600; background: #007C90; border-color: #007C90;">
                <span class="dashicons dashicons-update" style="vertical-align: text-top; font-size: 17px;"></span>
                <?php esc_html_e('3. Echten Abgleich jetzt ausführen', 'custom-crm'); ?>
            </button>

            <span id="crm-imap-action-spinner" class="spinner" style="float: none; margin: 0;"></span>
        </div>

        <!-- Live Feedback Container -->
        <div id="crm-imap-feedback-box" style="display: none; padding: 12px 16px; border-radius: 6px; font-size: 13.5px; margin-top: 10px; line-height: 1.5;"></div>

        <!-- Test-Simulation Live-Vorschau Container -->
        <div id="crm-imap-simulation-box" style="display: none; margin-top: 18px; border: 1px solid #c7d2fe; border-radius: 8px; background: #faf5ff; overflow: hidden; box-shadow: 0 2px 8px rgba(99, 102, 241, 0.08);">
            <!-- Simulation Header & KPI Bar -->
            <div style="background: #eef2ff; border-bottom: 1px solid #c7d2fe; padding: 14px 18px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span class="dashicons dashicons-search" style="color: #4f46e5; font-size: 24px;"></span>
                        <h3 style="margin: 0; font-size: 16px; color: #1e1b4b; font-weight: 700;">
                            <?php esc_html_e('Ergebnis der Test-Simulation (Trockenlauf)', 'custom-crm'); ?>
                        </h3>
                    </div>
                    <span style="display: inline-flex; align-items: center; gap: 6px; background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 12px;">
                        <span class="dashicons dashicons-shield" style="font-size: 14px; width: 14px; height: 14px;"></span>
                        <?php esc_html_e('CRM-Datenbank geschützt — 0 Schreibzugriffe & Mails unberührt', 'custom-crm'); ?>
                    </span>
                </div>
                <p id="crm-simulation-summary-text" style="margin: 0 0 12px 0; color: #4338ca; font-size: 13px;"></p>

                <!-- KPI Metric Badges -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px;">
                    <div style="background: #ffffff; border: 1px solid #e0e7ff; border-radius: 6px; padding: 8px 12px;">
                        <div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 600;"><?php esc_html_e('Mails Geprüft', 'custom-crm'); ?></div>
                        <div id="crm-sim-kpi-scanned" style="font-size: 20px; font-weight: 700; color: #1e293b;">0</div>
                    </div>
                    <div style="background: #ffffff; border: 1px solid #bbf7d0; border-radius: 6px; padding: 8px 12px;">
                        <div style="font-size: 11px; color: #15803d; text-transform: uppercase; font-weight: 600;"><?php esc_html_e('Erkannte Leads / Kunden', 'custom-crm'); ?></div>
                        <div id="crm-sim-kpi-qualified" style="font-size: 20px; font-weight: 700; color: #16a34a;">0</div>
                    </div>
                    <div style="background: #ffffff; border: 1px solid #fed7aa; border-radius: 6px; padding: 8px 12px;">
                        <div style="font-size: 11px; color: #c2410c; text-transform: uppercase; font-weight: 600;"><?php esc_html_e('Übersprungen (Filter / Intern)', 'custom-crm'); ?></div>
                        <div id="crm-sim-kpi-skipped" style="font-size: 20px; font-weight: 700; color: #ea580c;">0</div>
                    </div>
                </div>
            </div>

            <!-- Simulation Items Cards Container -->
            <div id="crm-simulation-items-list" style="padding: 16px; display: flex; flex-direction: column; gap: 14px; max-height: 520px; overflow-y: auto;">
                <!-- Dynamisch via JavaScript befüllt -->
            </div>

            <!-- Simulation Footer Call-to-Action -->
            <div style="background: #ffffff; border-top: 1px solid #e2e8f0; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div style="font-size: 12.5px; color: #64748b;">
                    <?php esc_html_e('Passen die Zuordnungen? Mit einem Klick auf "Echten Abgleich jetzt starten" werden die Leads live ins CRM übernommen.', 'custom-crm'); ?>
                </div>
                <button type="button" class="button button-primary" id="crm-btn-sim-execute-real" style="background: #007C90; border-color: #007C90; font-weight: 600;">
                    <span class="dashicons dashicons-yes-alt" style="vertical-align: text-top;"></span>
                    <?php esc_html_e('Passt so: Echten Abgleich jetzt starten', 'custom-crm'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- CARD 1: IMAP-Server Stammdaten -->
    <div class="crm-settings-card">
        <div class="crm-settings-card-header">
            <h2>
                <span class="dashicons dashicons-admin-generic" style="color: #007C90; font-size: 22px;"></span>
                <?php esc_html_e('IMAP-Server & Postfach-Zugangsdaten', 'custom-crm'); ?>
            </h2>
            <span class="crm-section-tag"><?php esc_html_e('Serververbindung', 'custom-crm'); ?></span>
        </div>

        <!-- Synchronisation aktivieren -->
        <div style="margin-bottom: 20px; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 600; color: #1e293b; font-size: 14px;">
                <input type="checkbox" name="crm_imap[enabled]" value="1" <?php checked(!empty($imap_settings['enabled'])); ?> style="width: 18px; height: 18px;" />
                <span><?php esc_html_e('E-Mail Postfach-Synchronisation aktivieren (Hintergrund-Abgleich & Ingestion)', 'custom-crm'); ?></span>
            </label>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; margin-bottom: 18px;">
            <!-- Server Host -->
            <div>
                <label class="crm-form-label" for="crm_imap_host">
                    <?php esc_html_e('IMAP-Server (Host):', 'custom-crm'); ?> <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="crm_imap[host]" id="crm_imap_host" value="<?php echo esc_attr($imap_settings['host'] ?? 'mail.x-sieben.at'); ?>" class="widefat" style="height: 36px; font-family: monospace;" placeholder="mail.x-sieben.at oder imap.ionos.de" required />
                <p class="crm-form-help"><?php esc_html_e('Hostname Ihres E-Mail-Providers (z. B. mail.x-sieben.at, imap.ionos.de, imap.gmail.com).', 'custom-crm'); ?></p>
            </div>

            <!-- Server Port -->
            <div>
                <label class="crm-form-label" for="crm_imap_port">
                    <?php esc_html_e('Port:', 'custom-crm'); ?> <span style="color: #ef4444;">*</span>
                </label>
                <input type="number" name="crm_imap[port]" id="crm_imap_port" value="<?php echo esc_attr($imap_settings['port'] ?? 993); ?>" class="widefat" style="height: 36px; font-family: monospace;" min="1" max="65535" required />
                <p class="crm-form-help"><?php esc_html_e('Standard: 993 (für SSL/TLS) oder 143 (für STARTTLS).', 'custom-crm'); ?></p>
            </div>

            <!-- Verschlüsselung -->
            <div>
                <label class="crm-form-label" for="crm_imap_encryption">
                    <?php esc_html_e('Verschlüsselung:', 'custom-crm'); ?>
                </label>
                <select name="crm_imap[encryption]" id="crm_imap_encryption" class="widefat" style="height: 36px;">
                    <option value="ssl" <?php selected(($imap_settings['encryption'] ?? 'ssl'), 'ssl'); ?>>SSL / TLS (Port 993 — Empfohlen)</option>
                    <option value="tls" <?php selected(($imap_settings['encryption'] ?? ''), 'tls'); ?>>STARTTLS (Port 143)</option>
                    <option value="none" <?php selected(($imap_settings['encryption'] ?? ''), 'none'); ?>>Keine Verschlüsselung (Unsicher)</option>
                </select>
                <p class="crm-form-help"><?php esc_html_e('Sicherheitszertifikat & Protokoll der Verbindung.', 'custom-crm'); ?></p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 18px; margin-bottom: 18px;">
            <!-- Benutzername / E-Mail -->
            <div>
                <label class="crm-form-label" for="crm_imap_username">
                    <?php esc_html_e('E-Mail-Adresse / Benutzername:', 'custom-crm'); ?> <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="crm_imap[username]" id="crm_imap_username" value="<?php echo esc_attr($imap_settings['username'] ?? 'office@x-sieben.at'); ?>" class="widefat" style="height: 36px;" placeholder="office@x-sieben.at" required />
                <p class="crm-form-help"><?php esc_html_e('Vollständige E-Mail-Adresse oder Login-Benutzername Ihres Postfachs.', 'custom-crm'); ?></p>
            </div>

            <!-- Passwort -->
            <div>
                <label class="crm-form-label" for="crm_imap_password">
                    <?php esc_html_e('Passwort / App-Passwort:', 'custom-crm'); ?>
                </label>
                <div style="position: relative;">
                    <input type="password" name="crm_imap[password]" id="crm_imap_password" value="<?php echo !empty($imap_settings['password']) ? esc_attr($imap_settings['password']) : ''; ?>" class="widefat" style="height: 36px; padding-right: 40px;" placeholder="<?php echo !empty($imap_settings['password']) ? '••••••••••••' : 'Passwort eingeben'; ?>" autocomplete="new-password" />
                    <button type="button" id="crm-toggle-imap-password" style="position: absolute; right: 5px; top: 5px; border: none; background: transparent; cursor: pointer; color: #64748b; padding: 4px;" title="<?php esc_attr_e('Passwort einblenden/ausblenden', 'custom-crm'); ?>">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                </div>
                <p class="crm-form-help"><?php esc_html_e('Wird verschlüsselt gespeichert. Bei Gmail/Microsoft bitte ein anwendungsspezifisches App-Passwort nutzen.', 'custom-crm'); ?></p>
            </div>

            <!-- Ordner -->
            <div>
                <label class="crm-form-label" for="crm_imap_mailbox">
                    <?php esc_html_e('Postfach-Ordner:', 'custom-crm'); ?>
                </label>
                <input type="text" name="crm_imap[mailbox]" id="crm_imap_mailbox" value="<?php echo esc_attr($imap_settings['mailbox'] ?? 'INBOX'); ?>" class="widefat" style="height: 36px; font-family: monospace;" placeholder="INBOX" />
                <p class="crm-form-help"><?php esc_html_e('Standard: INBOX (oder Unterordner wie z. B. INBOX.Anfragen oder Leads).', 'custom-crm'); ?></p>
            </div>
        </div>
    </div>

    <!-- CARD 2: Synchronisations-Regeln & Lead-Ingestion -->
    <div class="crm-settings-card">
        <div class="crm-settings-card-header">
            <h2>
                <span class="dashicons dashicons-filter" style="color: #007C90; font-size: 22px;"></span>
                <?php esc_html_e('Filterregeln & Lead-Erkennung', 'custom-crm'); ?>
            </h2>
            <span class="crm-section-tag"><?php esc_html_e('Automatisierung', 'custom-crm'); ?></span>
        </div>

        <p style="color: #475569; font-size: 13.5px; line-height: 1.5; margin-top: 0;">
            <?php esc_html_e('Definieren Sie, welche eingehenden Nachrichten als Leads erfasst werden und was nach erfolgreichem Import mit ihnen geschieht.', 'custom-crm'); ?>
        </p>

        <!-- Checkbox-Optionen -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 14px; margin-bottom: 20px;">
            <div style="padding: 12px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; color: #1e293b; font-size: 13.5px;">
                    <input type="checkbox" name="crm_imap[filter_unseen]" value="1" <?php checked(!empty($imap_settings['filter_unseen'])); ?> style="margin-top: 2px;" />
                    <div>
                        <strong><?php esc_html_e('Nur ungelesene E-Mails abgleichen (UNSEEN)', 'custom-crm'); ?></strong>
                        <p style="margin: 3px 0 0 0; color: #64748b; font-size: 12px;"><?php esc_html_e('Ignoriert bereits gelesene Mails im Postfach, um die Serverlast zu minimieren.', 'custom-crm'); ?></p>
                    </div>
                </label>
            </div>

            <div style="padding: 12px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; color: #1e293b; font-size: 13.5px;">
                    <input type="checkbox" name="crm_imap[mark_as_read]" value="1" <?php checked(!empty($imap_settings['mark_as_read'])); ?> style="margin-top: 2px;" />
                    <div>
                        <strong><?php esc_html_e('Als gelesen markieren (\Seen)', 'custom-crm'); ?></strong>
                        <p style="margin: 3px 0 0 0; color: #64748b; font-size: 12px;"><?php esc_html_e('Setzt das Gelesen-Flag auf dem IMAP-Server, sobald ein Lead erfolgreich angelegt wurde.', 'custom-crm'); ?></p>
                    </div>
                </label>
            </div>

            <div style="padding: 12px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; color: #1e293b; font-size: 13.5px;">
                    <input type="checkbox" name="crm_imap[auto_create_leads]" value="1" <?php checked(!empty($imap_settings['auto_create_leads'])); ?> style="margin-top: 2px;" />
                    <div>
                        <strong><?php esc_html_e('Automatisch CRM-Leads anlegen', 'custom-crm'); ?></strong>
                        <p style="margin: 3px 0 0 0; color: #64748b; font-size: 12px;"><?php esc_html_e('Erstellt bei erkannter Kursanfrage sofort einen neuen Eintrag in WPForms & CRM-Status "neu".', 'custom-crm'); ?></p>
                    </div>
                </label>
            </div>

            <div style="padding: 12px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; color: #1e293b; font-size: 13.5px;">
                    <input type="checkbox" name="crm_imap[run_friedelin]" value="1" <?php checked(!empty($imap_settings['run_friedelin'])); ?> style="margin-top: 2px;" />
                    <div>
                        <strong><?php esc_html_e('Angebots-Vorbereitung durch Friedelin', 'custom-crm'); ?></strong>
                        <p style="margin: 3px 0 0 0; color: #64748b; font-size: 12px;"><?php esc_html_e('Erzeugt automatisch das Angebots- & Kurszeiten-PDF im Hintergrund für sofortigen Freigabeversand.', 'custom-crm'); ?></p>
                    </div>
                </label>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 18px;">
            <!-- Filter Keywords -->
            <div>
                <label class="crm-form-label" for="crm_imap_keywords">
                    <?php esc_html_e('Betreff- & Inhalts-Suchbegriffe (Kommagetrennt):', 'custom-crm'); ?>
                </label>
                <input type="text" name="crm_imap[filter_keywords]" id="crm_imap_keywords" value="<?php echo esc_attr($imap_settings['filter_keywords'] ?? ''); ?>" class="widefat" style="height: 36px;" placeholder="Anfrage, Kurs, Kursfinder, Buchung, Ausbildung, Seminar" />
                <p class="crm-form-help"><?php esc_html_e('Nur Nachrichten mit mindestens einem dieser Begriffe werden analysiert. Leer lassen, um alle ungesehenen Mails zu analysieren.', 'custom-crm'); ?></p>
            </div>

            <!-- Max Mails -->
            <div>
                <label class="crm-form-label" for="crm_imap_max">
                    <?php esc_html_e('Max. Mails pro Abgleich:', 'custom-crm'); ?>
                </label>
                <select name="crm_imap[max_emails]" id="crm_imap_max" class="widefat" style="height: 36px;">
                    <option value="10" <?php selected(($imap_settings['max_emails'] ?? 20), 10); ?>>10 E-Mails</option>
                    <option value="20" <?php selected(($imap_settings['max_emails'] ?? 20), 20); ?>>20 E-Mails (Standard)</option>
                    <option value="50" <?php selected(($imap_settings['max_emails'] ?? 20), 50); ?>>50 E-Mails</option>
                    <option value="100" <?php selected(($imap_settings['max_emails'] ?? 20), 100); ?>>100 E-Mails</option>
                </select>
                <p class="crm-form-help"><?php esc_html_e('Sicherheitsgrenze gegen Server-Timeouts.', 'custom-crm'); ?></p>
            </div>
        </div>
    </div>

    <!-- CARD 3: Automatischer Hintergrund-Abgleich (WP-Cron) -->
    <div class="crm-settings-card">
        <div class="crm-settings-card-header">
            <h2>
                <span class="dashicons dashicons-clock" style="color: #007C90; font-size: 22px;"></span>
                <?php esc_html_e('Automatischer Hintergrund-Abgleich (WP-Cron)', 'custom-crm'); ?>
            </h2>
            <span class="crm-section-tag"><?php esc_html_e('Zeitsteuerung', 'custom-crm'); ?></span>
        </div>

        <p style="color: #475569; font-size: 13.5px; line-height: 1.5; margin-top: 0;">
            <?php esc_html_e('Legen Sie fest, wie häufig das WordPress-System das Postfach im Hintergrund nach neuen Kundenmails abfragen soll.', 'custom-crm'); ?>
        </p>

        <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
            <div style="max-width: 320px; flex: 1;">
                <label class="crm-form-label" for="crm_imap_sync_interval">
                    <?php esc_html_e('Abgleich-Intervall:', 'custom-crm'); ?>
                </label>
                <select name="crm_imap[sync_interval]" id="crm_imap_sync_interval" class="widefat" style="height: 36px;">
                    <option value="every15min" <?php selected(($imap_settings['sync_interval'] ?? 'hourly'), 'every15min'); ?>><?php esc_html_e('Alle 15 Minuten (Empfohlen)', 'custom-crm'); ?></option>
                    <option value="hourly" <?php selected(($imap_settings['sync_interval'] ?? 'hourly'), 'hourly'); ?>><?php esc_html_e('Stündlich', 'custom-crm'); ?></option>
                    <option value="twicedaily" <?php selected(($imap_settings['sync_interval'] ?? 'hourly'), 'twicedaily'); ?>><?php esc_html_e('Zweimal täglich (alle 12 Std.)', 'custom-crm'); ?></option>
                    <option value="daily" <?php selected(($imap_settings['sync_interval'] ?? 'hourly'), 'daily'); ?>><?php esc_html_e('Einmal täglich', 'custom-crm'); ?></option>
                    <option value="disabled" <?php selected(($imap_settings['sync_interval'] ?? 'hourly'), 'disabled'); ?>><?php esc_html_e('Deaktiviert (Nur manueller Abgleich)', 'custom-crm'); ?></option>
                </select>
            </div>

            <div style="flex: 2; min-width: 280px; padding: 10px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px; color: #475569;">
                <div>
                    <strong><?php esc_html_e('Nächster geplanter Cron-Lauf:', 'custom-crm'); ?></strong>
                    <?php
                    $next_cron = wp_next_scheduled('crm_imap_sync_cron');
                    if ($next_cron) {
                        $diff = human_time_diff($next_cron);
                        echo '<span style="color: #007C90; font-weight: 600; margin-left: 6px;">' . date_i18n('d.m.Y H:i', $next_cron) . ' (in ' . esc_html($diff) . ')</span>';
                    } else {
                        echo '<span style="color: #94a3b8; margin-left: 6px;">' . esc_html__('Kein automatischer Lauf geplant (oder deaktiviert).', 'custom-crm') . '</span>';
                    }
                    ?>
                </div>
                <div style="margin-top: 4px;">
                    <strong><?php esc_html_e('Letzter Abgleich:', 'custom-crm'); ?></strong>
                    <span style="color: #1e293b; margin-left: 6px;"><?php echo !empty($imap_settings['last_sync_time']) ? esc_html($imap_settings['last_sync_time']) : esc_html__('Noch nie', 'custom-crm'); ?></span>
                    <?php if (!empty($imap_settings['last_sync_status'])) : ?>
                        <span style="margin-left: 8px; color: #0284c7;">(<?php echo esc_html($imap_settings['last_sync_status']); ?>)</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- CARD 4: Historie & Audit-Protokoll der letzten Synchronisationen -->
    <div class="crm-settings-card">
        <div class="crm-settings-card-header">
            <h2>
                <span class="dashicons dashicons-list-view" style="color: #007C90; font-size: 22px;"></span>
                <?php esc_html_e('Synchronisations-Protokoll & Letzte Importe', 'custom-crm'); ?>
            </h2>
            <span class="crm-section-tag"><?php esc_html_e('Audit-Log', 'custom-crm'); ?></span>
        </div>

        <?php if (!empty($sync_history)) : ?>
            <div style="overflow-x: auto;">
                <table class="wp-list-table widefat fixed striped" style="border: 1px solid #cbd5e1; border-radius: 4px;">
                    <thead>
                        <tr>
                            <th style="width: 140px;"><?php esc_html_e('Zeitpunkt', 'custom-crm'); ?></th>
                            <th style="width: 80px;"><?php esc_html_e('Status', 'custom-crm'); ?></th>
                            <th style="width: 90px; text-align: center;"><?php esc_html_e('Geprüft', 'custom-crm'); ?></th>
                            <th style="width: 90px; text-align: center;"><?php esc_html_e('Leads', 'custom-crm'); ?></th>
                            <th><?php esc_html_e('Details & Angelegte Leads', 'custom-crm'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($sync_history, 0, 10) as $entry) : ?>
                            <tr>
                                <td style="font-family: monospace; font-size: 12px; color: #475569;">
                                    <?php echo esc_html($entry['time'] ?? '—'); ?>
                                </td>
                                <td>
                                    <?php if (!empty($entry['success'])) : ?>
                                        <span style="display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; background: #dcfce7; color: #15803d;">ERFOLG</span>
                                    <?php else : ?>
                                        <span style="display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; background: #fee2e2; color: #b91c1c;">FEHLER</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center; font-weight: 600;">
                                    <?php echo intval($entry['scanned'] ?? 0); ?>
                                </td>
                                <td style="text-align: center; font-weight: 700; color: #007C90;">
                                    <?php echo intval($entry['imported'] ?? 0); ?>
                                </td>
                                <td style="font-size: 13px;">
                                    <div><?php echo esc_html($entry['message'] ?? '—'); ?></div>
                                    <?php if (!empty($entry['leads']) && is_array($entry['leads'])) : ?>
                                        <div style="margin-top: 4px; display: flex; gap: 6px; flex-wrap: wrap;">
                                            <?php foreach ($entry['leads'] as $l) : ?>
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=crm&entry_id=' . intval($l['entry_id']))); ?>" target="_blank" class="button button-small" style="font-size: 11px; height: 22px; line-height: 20px;">
                                                    <span class="dashicons dashicons-admin-users" style="font-size: 12px; vertical-align: text-top;"></span>
                                                    #<?php echo intval($l['entry_id']); ?>: <?php echo esc_html($l['client_name'] ?? 'Lead'); ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else : ?>
            <div style="padding: 24px; text-align: center; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; color: #64748b;">
                <span class="dashicons dashicons-email-alt" style="font-size: 32px; width: 32px; height: 32px; color: #94a3b8; margin-bottom: 8px;"></span>
                <p style="margin: 0; font-size: 13.5px;"><?php esc_html_e('Noch keine Synchronisations-Durchläufe aufgezeichnet. Klicken Sie oben auf "Verbindung jetzt testen" oder "Mails jetzt abgleichen", um den ersten Lauf zu starten.', 'custom-crm'); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Speichern Button -->
    <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #cbd5e1; display: flex; justify-content: flex-end;">
        <button type="submit" name="submit_imap" class="button button-primary button-hero" style="background: #007C90; border-color: #007C90; font-weight: 600; padding: 0 28px;">
            <span class="dashicons dashicons-saved" style="vertical-align: text-top; font-size: 20px;"></span>
            <?php esc_html_e('IMAP- & E-Mail-Einstellungen speichern', 'custom-crm'); ?>
        </button>
    </div>

</div>

<script>
jQuery(document).ready(function($) {
    // Password toggle
    $('#crm-toggle-imap-password').on('click', function(e) {
        e.preventDefault();
        const input = $('#crm_imap_password');
        const icon = $(this).find('.dashicons');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
            input.attr('type', 'password');
            icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
    });

    const ajaxUrl = (typeof ajaxurl !== 'undefined' && ajaxurl) || (window.crmSettingsData && window.crmSettingsData.ajaxUrl) || '/wp-admin/admin-ajax.php';
    const nonce   = (window.crmSettingsData && window.crmSettingsData.nonce) || '';

    // Verbindung testen via AJAX
    $('#crm-btn-test-imap').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const $box = $('#crm-imap-feedback-box');
        const $spinner = $('#crm-imap-action-spinner');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $box.css({ background: '#f0f9ff', color: '#0369a1', border: '1px solid #bae6fd' })
            .html('<span class="dashicons dashicons-update spin"></span> Verbindung zum IMAP-Server wird aufgebaut...').show();

        const data = {
            action:     'crm_test_imap_connection',
            nonce:      nonce,
            host:       $('#crm_imap_host').val(),
            port:       $('#crm_imap_port').val(),
            encryption: $('#crm_imap_encryption').val(),
            username:   $('#crm_imap_username').val(),
            password:   $('#crm_imap_password').val(),
            mailbox:    $('#crm_imap_mailbox').val()
        };

        $.post(ajaxUrl, data, function(res) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (res && res.success) {
                $box.css({ background: '#f0fdf4', color: '#15803d', border: '1px solid #bbf7d0' })
                    .html('<strong>✓ Erfolg:</strong> ' + (res.data && res.data.message ? res.data.message : 'Verbindung hergestellt!'));
            } else {
                const msg = (res && res.data && res.data.message) ? res.data.message : 'Verbindung fehlgeschlagen.';
                $box.css({ background: '#fef2f2', color: '#b91c1c', border: '1px solid #fecaca' })
                    .html('<strong>✕ Fehler:</strong> ' + msg);
            }
        }).fail(function(xhr, status, err) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $box.css({ background: '#fef2f2', color: '#b91c1c', border: '1px solid #fecaca' })
                .html('<strong>✕ Serverfehler:</strong> ' + err);
        });
    });

    // Hilfsfunktion: Sicheres HTML-Escaping
    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // 2. Test-Simulation (Trockenlauf ohne DB-Änderung) via AJAX
    $('#crm-btn-simulate-imap').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const $box = $('#crm-imap-feedback-box');
        const $simBox = $('#crm-imap-simulation-box');
        const $simList = $('#crm-simulation-items-list');
        const $spinner = $('#crm-imap-action-spinner');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $simBox.hide();
        $box.css({ background: '#f5f3ff', color: '#4338ca', border: '1px solid #c7d2fe' })
            .html('<span class="dashicons dashicons-update spin"></span> <strong>Trockenlauf läuft:</strong> Postfach wird abgefragt und E-Mails simulierend analysiert (ohne Datenbankänderungen)...').show();

        const data = {
            action:          'crm_sync_imap_emails',
            nonce:           nonce,
            dry_run:         1,
            host:            $('#crm_imap_host').val(),
            port:            $('#crm_imap_port').val(),
            encryption:      $('#crm_imap_encryption').val(),
            username:        $('#crm_imap_username').val(),
            password:        $('#crm_imap_password').val(),
            mailbox:         $('#crm_imap_mailbox').val(),
            filter_unseen:   $('input[name="crm_imap[filter_unseen]"]').is(':checked') ? 1 : 0,
            filter_keywords: $('#crm_imap_keywords').val(),
            max_emails:      $('#crm_imap_max').val()
        };

        $.post(ajaxUrl, data, function(res) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (res && res.success) {
                const d = res.data || {};
                $box.css({ background: '#f0fdf4', color: '#15803d', border: '1px solid #bbf7d0' })
                    .html('<strong>✓ ' + escapeHtml(d.message || 'Simulation erfolgreich abgeschlossen!') + '</strong>');

                // KPIs befüllen
                $('#crm-sim-kpi-scanned').text(d.scanned || 0);
                $('#crm-sim-kpi-qualified').text(d.qualified || 0);
                $('#crm-sim-kpi-skipped').text(d.skipped || 0);

                let summaryNotice = d.message || '';
                if (d.used_fallback) {
                    summaryNotice += ' (Hinweis: Keine ungelesenen Mails vorgefunden — zur Ansicht der Einteilung wurden vorhandene Mails des Postfachs herangezogen).';
                }
                $('#crm-simulation-summary-text').text(summaryNotice);

                // Simulation Items rendern
                $simList.empty();
                const items = d.items || [];

                if (items.length === 0) {
                    $simList.html('<div style="padding: 24px; text-align: center; color: #64748b; background: #ffffff; border-radius: 6px; border: 1px dashed #cbd5e1;">Keine E-Mails im Postfach für die gewählten Kriterien gefunden.</div>');
                } else {
                    items.forEach(function(item) {
                        let badgeBg = '#f1f5f9';
                        let badgeColor = '#475569';
                        let badgeBorder = '#cbd5e1';

                        if (item.verdict === 'qualified_lead') {
                            badgeBg = '#dcfce7';
                            badgeColor = '#15803d';
                            badgeBorder = '#86efac';
                        } else if (item.verdict === 'existing_lead') {
                            badgeBg = '#e0f2fe';
                            badgeColor = '#0369a1';
                            badgeBorder = '#7dd3fc';
                        } else if (item.verdict === 'skipped_keyword') {
                            badgeBg = '#fef3c7';
                            badgeColor = '#b45309';
                            badgeBorder = '#fde68a';
                        }

                        let cardHtml = '<div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.03);">';

                        // Header
                        cardHtml += '<div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 8px; flex-wrap: wrap;">';
                        cardHtml += '  <div style="flex: 1; min-width: 250px;">';
                        cardHtml += '    <div style="font-size: 14px; font-weight: 700; color: #0f172a;">' + escapeHtml(item.subject) + '</div>';
                        cardHtml += '    <div style="font-size: 12px; color: #64748b; margin-top: 2px;">Von: <strong style="color: #334155;">' + escapeHtml(item.from) + '</strong> &bull; ' + escapeHtml(item.date) + '</div>';
                        cardHtml += '  </div>';
                        cardHtml += '  <div>';
                        cardHtml += '    <span style="display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11.5px; font-weight: 700; background: ' + badgeBg + '; color: ' + badgeColor + '; border: 1px solid ' + badgeBorder + ';">' + escapeHtml(item.verdict_label) + '</span>';
                        cardHtml += '  </div>';
                        cardHtml += '</div>';

                        // Reason banner
                        cardHtml += '<div style="font-size: 12px; color: #475569; background: #f8fafc; padding: 6px 10px; border-radius: 4px; margin-bottom: 10px; border-left: 3px solid ' + (item.verdict_color || '#94a3b8') + ';">';
                        cardHtml += '  <strong>Einteilung:</strong> ' + escapeHtml(item.reason);
                        cardHtml += '</div>';

                        // Extrahierte Daten Grid
                        if (item.client_name || item.email || item.course_title) {
                            cardHtml += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px; font-size: 12.5px; background: #fafafa; border: 1px solid #f1f5f9; padding: 10px 12px; border-radius: 4px; margin-bottom: 8px;">';

                            cardHtml += '<div><span style="color: #64748b;">Kunde:</span> <strong style="color: #1e293b;">' + escapeHtml(item.client_name || '—') + '</strong></div>';
                            cardHtml += '<div><span style="color: #64748b;">E-Mail:</span> <span style="font-family: monospace; color: #0284c7;">' + escapeHtml(item.email || '—') + '</span></div>';

                            if (item.phone) {
                                cardHtml += '<div><span style="color: #64748b;">Telefon:</span> <span>' + escapeHtml(item.phone) + '</span></div>';
                            }
                            if (item.company) {
                                cardHtml += '<div><span style="color: #64748b;">Firma:</span> <span>' + escapeHtml(item.company) + '</span></div>';
                            }

                            cardHtml += '<div style="grid-column: 1 / -1;"><span style="color: #64748b;">Erkannter Kurs:</span> <strong style="color: #007C90;">' + escapeHtml(item.course_title) + '</strong>' + (item.course_id ? ' <span style="font-size: 11px; color: #64748b;">(Kurs-ID #' + item.course_id + ')</span>' : '') + '</div>';

                            if (item.dates) {
                                cardHtml += '<div><span style="color: #64748b;">Termin:</span> <span>' + escapeHtml(item.dates) + '</span></div>';
                            }
                            if (item.funding && item.funding !== 'Keine') {
                                cardHtml += '<div><span style="color: #64748b;">Förderung / AMS:</span> <strong style="color: #15803d;">' + escapeHtml(item.funding) + '</strong></div>';
                            }
                            if (item.address) {
                                cardHtml += '<div><span style="color: #64748b;">Ort / Adresse:</span> <span>' + escapeHtml(item.address) + '</span></div>';
                            }

                            cardHtml += '</div>';
                        }

                        // Message Snippet
                        if (item.preview_snippet) {
                            cardHtml += '<div style="font-size: 11.5px; color: #64748b; font-style: italic; background: #ffffff; border: 1px solid #f1f5f9; padding: 6px 10px; border-radius: 4px;">';
                            cardHtml += '  <span class="dashicons dashicons-format-quote" style="font-size: 13px; vertical-align: text-top; color: #94a3b8;"></span> ' + escapeHtml(item.preview_snippet);
                            cardHtml += '</div>';
                        }

                        cardHtml += '</div>';
                        $simList.append(cardHtml);
                    });
                }

                $simBox.slideDown(250);
            } else {
                const msg = (res && res.data && res.data.message) ? res.data.message : 'Simulation fehlgeschlagen.';
                $box.css({ background: '#fef2f2', color: '#b91c1c', border: '1px solid #fecaca' })
                    .html('<strong>✕ Fehler bei der Simulation:</strong> ' + escapeHtml(msg));
            }
        }).fail(function(xhr, status, err) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $box.css({ background: '#fef2f2', color: '#b91c1c', border: '1px solid #fecaca' })
                .html('<strong>✕ Serverfehler:</strong> ' + escapeHtml(err));
        });
    });

    // 3. Echten Abgleich via AJAX ausführen
    $('#crm-btn-sync-imap, #crm-btn-sim-execute-real').on('click', function(e) {
        e.preventDefault();
        const $btn = $('#crm-btn-sync-imap');
        const $box = $('#crm-imap-feedback-box');
        const $spinner = $('#crm-imap-action-spinner');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $box.css({ background: '#f0f9ff', color: '#0369a1', border: '1px solid #bae6fd' })
            .html('<span class="dashicons dashicons-update spin"></span> Postfach wird abgefragt und Mails analysiert...').show();

        const data = {
            action:          'crm_sync_imap_emails',
            nonce:           nonce,
            dry_run:         0,
            host:            $('#crm_imap_host').val(),
            port:            $('#crm_imap_port').val(),
            encryption:      $('#crm_imap_encryption').val(),
            username:        $('#crm_imap_username').val(),
            password:        $('#crm_imap_password').val(),
            mailbox:         $('#crm_imap_mailbox').val(),
            filter_unseen:   $('input[name="crm_imap[filter_unseen]"]').is(':checked') ? 1 : 0,
            filter_keywords: $('#crm_imap_keywords').val(),
            max_emails:      $('#crm_imap_max').val()
        };

        $.post(ajaxUrl, data, function(res) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (res && res.success) {
                let html = '<strong>✓ ' + (res.data && res.data.message ? escapeHtml(res.data.message) : 'Abgleich erfolgreich!') + '</strong>';
                if (res.data && res.data.leads && res.data.leads.length > 0) {
                    html += '<ul style="margin: 8px 0 0 16px; padding: 0;">';
                    res.data.leads.forEach(function(l) {
                        html += '<li>Lead #' + parseInt(l.entry_id, 10) + ': ' + escapeHtml(l.client_name) + ' (' + escapeHtml(l.course) + ')</li>';
                    });
                    html += '</ul>';
                }
                $box.css({ background: '#f0fdf4', color: '#15803d', border: '1px solid #bbf7d0' }).html(html);

                // Nach Import kurz neu laden um Logs zu aktualisieren
                if (res.data && res.data.imported > 0) {
                    setTimeout(function() {
                        window.location.reload();
                    }, 2200);
                }
            } else {
                const msg = (res && res.data && res.data.message) ? res.data.message : 'Abgleich fehlgeschlagen.';
                $box.css({ background: '#fef2f2', color: '#b91c1c', border: '1px solid #fecaca' })
                    .html('<strong>✕ Fehler:</strong> ' + escapeHtml(msg));
            }
        }).fail(function(xhr, status, err) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $box.css({ background: '#fef2f2', color: '#b91c1c', border: '1px solid #fecaca' })
                .html('<strong>✕ Serverfehler:</strong> ' + escapeHtml(err));
        });
    });
});
</script>
