<?php
/**
 * CRM Views Helper: 4-in-1 Multi-View Dashboard
 *
 * Provides rendering functions for:
 * 1. Split-View (Master-Detail)
 * 2. Card View (Modular 3-Zone Customer Cards)
 * 3. Kanban Pipeline Board (5 Workflow Stages)
 * 4. View Switcher Toolbar
 *
 * @package CustomCRM
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the Top View Switcher Toolbar.
 *
 * @param int $total_entries Total count of loaded entries.
 * @return string HTML output for the view switcher.
 */
function crm_render_view_switcher($total_entries = 0) {
    ob_start();
    ?>
    <div class="crm-view-switcher-bar">
        <div class="crm-view-switcher" role="tablist" aria-label="<?php esc_attr_e('Dashboard-Ansicht wählen', 'custom-crm'); ?>">
            <button type="button" class="crm-view-btn active" data-view="cards" role="tab" aria-selected="true" title="<?php esc_attr_e('Kunden-Karten: Modulare 3-Zonen-Übersicht pro Kunde', 'custom-crm'); ?>">
                <span class="dashicons dashicons-grid-view"></span>
                <span class="crm-view-label"><?php esc_html_e('Kunden-Karten', 'custom-crm'); ?></span>
            </button>
            <button type="button" class="crm-view-btn" data-view="split" role="tab" aria-selected="false" title="<?php esc_attr_e('Split-View: Kundenliste links & Live-Dossier rechts (Apple Mail / Linear Style)', 'custom-crm'); ?>">
                <span class="dashicons dashicons-columns"></span>
                <span class="crm-view-label"><?php esc_html_e('Split-View', 'custom-crm'); ?></span>
            </button>
            <button type="button" class="crm-view-btn" data-view="kanban" role="tab" aria-selected="false" title="<?php esc_attr_e('Kanban-Pipeline: Workflow-Phasen von Anfrage bis Diplom', 'custom-crm'); ?>">
                <span class="dashicons dashicons-image-filter"></span>
                <span class="crm-view-label"><?php esc_html_e('Kanban-Pipeline', 'custom-crm'); ?></span>
            </button>
            <button type="button" class="crm-view-btn" data-view="table" role="tab" aria-selected="false" title="<?php esc_attr_e('Kompakt-Tabelle: Klassische WordPress-Tabellenansicht', 'custom-crm'); ?>">
                <span class="dashicons dashicons-list-view"></span>
                <span class="crm-view-label"><?php esc_html_e('Kompakt-Tabelle', 'custom-crm'); ?></span>
            </button>
        </div>

        <div class="crm-view-meta">
            <span class="crm-view-count-badge">
                <span class="dashicons dashicons-groups"></span>
                <strong id="crm-visible-count"><?php echo intval($total_entries); ?></strong> / <span id="crm-total-count"><?php echo intval($total_entries); ?></span> <?php esc_html_e('Anfragen', 'custom-crm'); ?>
            </span>
            <button type="button" id="crm-kanban-load-all-global-btn" class="button button-secondary button-small" style="display:none; margin-left:8px; font-size:11px; height:24px; line-height:22px; align-items:center; gap:4px;" title="<?php esc_attr_e('Alle restlichen Einträge aus der Datenbank nachladen', 'custom-crm'); ?>">
                <span class="dashicons dashicons-update"></span>
                <span><?php esc_html_e('Alle laden (Infinite)', 'custom-crm'); ?></span>
            </button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render the prominent Call-to-Action (CTA) button for Customer Card Zone 3 & Live Dossier.
 *
 * @param array $item Entry data item or associative array with entry_id, course_id, status_key, is_foerderung.
 * @return string HTML for the CTA button.
 */
function crm_render_card_cta($item) {
    $entry_id   = absint($item['entry_id'] ?? 0);
    $course_id  = absint($item['course_id'] ?? 0);
    $status_key = $item['status_key'] ?? 'neu';
    $is_foerder = !empty($item['is_foerderung']);

    // Status: Versandbereit / Vorbereitet -> DER GROSSE VERSAND FREIGEBEN CTA!
    if (in_array($status_key, ['versand_vorbereitet', 'versandbereit', 'ai_prepared'], true)) {
        return sprintf(
            '<button type="button" class="crm-card-cta-btn crm-card-cta-send crm-run-wizard-btn crm-run-friedelin-btn" data-entry-id="%d" data-course-id="%d" data-stage="offer" data-step="3" title="%s">
                <span class="dashicons dashicons-email-alt crm-cta-icon"></span>
                <span class="crm-cta-label">%s</span>
                <span class="crm-cta-badge">%s</span>
            </button>',
            $entry_id,
            $course_id,
            esc_attr__('Klicken zur sofortigen E-Mail-Vorschau & Freigabe für den Versand', 'custom-crm'),
            esc_html__('Versand freigeben', 'custom-crm'),
            esc_html__('⚡ Bereit', 'custom-crm')
        );
    }

    // Status: Neu / Anfrage / KI Vorbereitet -> Angebot vorbereiten CTA
    if (in_array($status_key, ['neu', 'ki_vorbereitet'], true)) {
        $btn_label = $is_foerder ? __('Angebot & KB vorbereiten', 'custom-crm') : __('Angebot vorbereiten', 'custom-crm');
        return sprintf(
            '<button type="button" class="crm-card-cta-btn crm-card-cta-prepare crm-run-wizard-btn crm-run-friedelin-btn" data-entry-id="%d" data-course-id="%d" data-stage="offer" data-step="1" title="%s">
                <span class="dashicons dashicons-superhero crm-cta-icon"></span>
                <span class="crm-cta-label">%s</span>
            </button>',
            $entry_id,
            $course_id,
            esc_attr__('Wizard öffnen & Angebot automatisch erstellen', 'custom-crm'),
            esc_html($btn_label)
        );
    }

    // Status: Angebot gesendet / Nachfassen
    if (in_array($status_key, ['angebot_gesendet', 'angebot_und_kurszeiten_gesendet', 'kurszeitenbestaetigung_gesendet', 'nachfassen', 'angebot_erstellt'], true)) {
        return sprintf(
            '<button type="button" class="crm-card-cta-btn crm-card-cta-followup crm-run-wizard-btn crm-run-friedelin-btn" data-entry-id="%d" data-course-id="%d" data-stage="followup" data-step="1" title="%s">
                <span class="dashicons dashicons-controls-forward crm-cta-icon"></span>
                <span class="crm-cta-label">%s</span>
            </button>',
            $entry_id,
            $course_id,
            esc_attr__('Nachfassen oder Buchung im Wizard übernehmen', 'custom-crm'),
            esc_html__('Nachfassen & Status', 'custom-crm')
        );
    }

    // Status: Angemeldet / Gebucht -> TB erstellen
    if (in_array($status_key, ['angemeldet', 'gebucht'], true)) {
        return sprintf(
            '<button type="button" class="crm-card-cta-btn crm-card-cta-enrolled crm-run-wizard-btn crm-run-friedelin-btn" data-entry-id="%d" data-course-id="%d" data-stage="enrolled" data-step="1" title="%s">
                <span class="dashicons dashicons-welcome-write-blog crm-cta-icon"></span>
                <span class="crm-cta-label">%s</span>
            </button>',
            $entry_id,
            $course_id,
            esc_attr__('Teilnahmebestätigung (TB) nach Kursabschluss erstellen', 'custom-crm'),
            esc_html__('TB erstellen & abschließen', 'custom-crm')
        );
    }

    // Status: TB versendet -> Diplom
    if (in_array($status_key, ['teilnahmebestaetigung_gesendet'], true)) {
        return sprintf(
            '<button type="button" class="crm-card-cta-btn crm-card-cta-diploma crm-run-wizard-btn crm-run-friedelin-btn" data-entry-id="%d" data-course-id="%d" data-stage="diploma" data-step="1" title="%s">
                <span class="dashicons dashicons-awards crm-cta-icon"></span>
                <span class="crm-cta-label">%s</span>
            </button>',
            $entry_id,
            $course_id,
            esc_attr__('Diplom & Zertifikat erstellen', 'custom-crm'),
            esc_html__('Diplom & Zertifikat', 'custom-crm')
        );
    }

    // Status: Abgeschlossen / Durchgeführt / Storniert
    return sprintf(
        '<button type="button" class="crm-card-cta-btn crm-card-cta-done crm-snapshots-btn" data-entry-id="%d" title="%s">
            <span class="dashicons dashicons-archive crm-cta-icon"></span>
            <span class="crm-cta-label">%s</span>
        </button>',
        $entry_id,
        esc_attr__('Revisionssichere Dokument- & Daten-Snapshots öffnen', 'custom-crm'),
        esc_html__('Dossier & Snapshots', 'custom-crm')
    );
}

/**
 * Render the Modular 3-Zone Customer Cards View.
 *
 * @param array $prepared_entries Array of pre-extracted entry data models.
 * @param array $all_statuses_def All status definitions.
 * @return string HTML output for the card grid.
 */
function crm_render_card_view($prepared_entries, $all_statuses_def) {
    if (empty($prepared_entries)) {
        return '<p class="crm-empty-state">' . esc_html__('Keine Anfragen gefunden.', 'custom-crm') . '</p>';
    }

    ob_start();
    ?>
    <div id="crm-view-cards" class="crm-view-pane crm-cards-grid">
        <?php foreach ($prepared_entries as $item) : ?>
            <article class="crm-customer-card crm-entry-item"
                data-entry-id="<?php echo esc_attr($item['entry_id']); ?>"
                data-course-id="<?php echo esc_attr($item['course_id'] ?: 0); ?>"
                data-status-key="<?php echo esc_attr($item['status_key']); ?>"
                data-client-name="<?php echo esc_attr($item['client_display_name']); ?>"
                data-course-title="<?php echo esc_attr($item['course_title']); ?>"
                data-is-foerderung="<?php echo $item['is_foerderung'] ? '1' : '0'; ?>"
                data-entry-date="<?php echo esc_attr($item['entry_timestamp']); ?>"
                data-course-date="<?php echo esc_attr($item['course_start_ts']); ?>"
                data-inquiry-type="<?php echo esc_attr($item['inquiry_type']); ?>"
                data-custom-title="<?php echo esc_attr($item['custom_title']); ?>">

                <!-- Card Header -->
                <header class="crm-card-header">
                    <div class="crm-card-title-group">
                        <span class="crm-card-avatar"><?php echo esc_html(mb_strtoupper(mb_substr($item['first_name_val'] ?: ($item['last_name_val'] ?: 'K'), 0, 1))); ?></span>
                        <div class="crm-card-name-wrap">
                            <h3 class="crm-card-client-name">
                                <a href="#" class="crm-direct-editor-btn crm-open-case-link"
                                    data-entry-id="<?php echo esc_attr($item['entry_id']); ?>"
                                    data-course-id="<?php echo esc_attr($item['course_id'] ?: 0); ?>"
                                    data-action="<?php echo esc_attr($item['default_editor_action']); ?>"
                                    title="<?php esc_attr_e('Klicken, um den gesamten Geschäftsvorfall & Arbeitsbereich zu öffnen', 'custom-crm'); ?>">
                                    <?php echo esc_html($item['client_display_name']); ?>
                                </a>
                            </h3>
                            <div class="crm-card-badges">
                                <span class="crm-badge crm-badge-anfrage" title="<?php esc_attr_e('Anfrage vom', 'custom-crm'); ?> <?php echo esc_attr($item['full_date_tooltip']); ?>">
                                    📅 <?php echo esc_html($item['formatted_date']); ?>
                                </span>
                                <?php echo $item['foerder_pure_badges']; ?>
                                <span class="crm-badge crm-badge-id">#<?php echo esc_html($item['entry_id']); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="crm-card-header-actions">
                        <button type="button" class="crm-card-btn crm-quick-edit-btn"
                            data-entry-id="<?php echo esc_attr($item['entry_id']); ?>"
                            data-course-id="<?php echo esc_attr($item['course_id'] ?: 0); ?>"
                            title="<?php esc_attr_e('Kundendaten bearbeiten', 'custom-crm'); ?>">
                            <span class="dashicons dashicons-edit"></span>
                            <span><?php esc_html_e('Kundendaten', 'custom-crm'); ?></span>
                        </button>
                        <button type="button" class="crm-card-btn crm-direct-editor-btn crm-case-btn"
                            data-entry-id="<?php echo esc_attr($item['entry_id']); ?>"
                            data-course-id="<?php echo esc_attr($item['course_id'] ?: 0); ?>"
                            data-action="<?php echo esc_attr($item['default_editor_action']); ?>"
                            title="<?php esc_attr_e('Gesamten Geschäftsvorfall öffnen', 'custom-crm'); ?>">
                            <span class="dashicons dashicons-portfolio"></span>
                            <span><?php esc_html_e('Geschäftsvorfall', 'custom-crm'); ?></span>
                        </button>
                        <button type="button" class="crm-card-btn crm-history-btn"
                            data-entry-id="<?php echo esc_attr($item['entry_id']); ?>"
                            title="<?php esc_attr_e('Status-Verlauf & Historie anzeigen', 'custom-crm'); ?>">
                            <span class="dashicons dashicons-backup"></span>
                        </button>
                        <button type="button" class="crm-card-btn crm-snapshots-btn"
                            data-entry-id="<?php echo esc_attr($item['entry_id']); ?>"
                            title="<?php echo esc_attr(sprintf(__('Dokument- & Daten-Archiv (%d Snapshots)', 'custom-crm'), $item['snap_count'])); ?>">
                            <span class="dashicons dashicons-archive"></span>
                            <?php if ($item['snap_count'] > 0) : ?>
                                <span class="crm-snap-count-badge"><?php echo intval($item['snap_count']); ?></span>
                            <?php endif; ?>
                        </button>
                    </div>
                </header>

                <!-- Card Body: 3 Zones -->
                <div class="crm-card-body">
                    <!-- Zone 1: Kunde & Kontakt -->
                    <div class="crm-card-zone crm-card-zone-client">
                        <div class="crm-zone-title" style="display:flex; justify-content:space-between; align-items:center;">
                            <div style="display:flex; align-items:center; gap:6px;">
                                <span class="dashicons dashicons-admin-users"></span>
                                <span><?php esc_html_e('Kunde & Kontakt', 'custom-crm'); ?></span>
                            </div>
                            <button type="button" class="crm-quick-edit-btn crm-card-inline-edit-btn"
                                data-entry-id="<?php echo esc_attr($item['entry_id']); ?>"
                                data-course-id="<?php echo esc_attr($item['course_id'] ?: 0); ?>"
                                title="<?php esc_attr_e('Kundendaten bearbeiten', 'custom-crm'); ?>"
                                style="background:none; border:none; color:#0284c7; cursor:pointer; font-size:11px; display:inline-flex; align-items:center; gap:2px; padding:0; font-weight:600;">
                                <span class="dashicons dashicons-edit" style="font-size:12px; width:12px; height:12px;"></span>
                                <span><?php esc_html_e('Bearbeiten', 'custom-crm'); ?></span>
                            </button>
                        </div>
                        <div class="crm-zone-content">
                            <?php if (!empty($item['company_val'])) : ?>
                                <div class="crm-contact-line crm-contact-company">
                                    <span class="dashicons dashicons-building"></span>
                                    <strong><?php echo esc_html($item['company_val']); ?></strong>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($item['email_val'])) : ?>
                                <div class="crm-contact-line crm-contact-email">
                                    <span class="dashicons dashicons-email"></span>
                                    <a href="mailto:<?php echo esc_attr($item['email_val']); ?>" title="<?php esc_attr_e('E-Mail schreiben', 'custom-crm'); ?>">
                                        <?php echo esc_html($item['email_val']); ?>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($item['phone_val'])) : ?>
                                <div class="crm-contact-line crm-contact-phone">
                                    <span class="dashicons dashicons-phone"></span>
                                    <a href="tel:<?php echo esc_attr($item['phone_val']); ?>" title="<?php esc_attr_e('Anrufen', 'custom-crm'); ?>">
                                        <?php echo esc_html($item['phone_val']); ?>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($item['city_val']) || !empty($item['street_val'])) : ?>
                                <div class="crm-contact-line crm-contact-address">
                                    <span class="dashicons dashicons-location"></span>
                                    <span><?php echo esc_html(trim(($item['street_val'] ? $item['street_val'] . ', ' : '') . $item['zip_val'] . ' ' . $item['city_val'])); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($item['svr_val'])) : ?>
                                <div class="crm-contact-line crm-contact-svr">
                                    <span class="dashicons dashicons-id"></span>
                                    <span>SVR: <strong><?php echo esc_html($item['svr_val']); ?></strong></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($item['message_val'])) : ?>
                                <div class="crm-card-message-box" title="<?php esc_attr_e('Kundennachricht / Freitext', 'custom-crm'); ?>">
                                    <span class="crm-message-quote-icon">“</span>
                                    <p><?php echo esc_html(mb_strimwidth($item['message_val'], 0, 140, '…')); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Zone 2: Angefragter Kurs & Dokumente -->
                    <div class="crm-card-zone crm-card-zone-course">
                        <div class="crm-zone-title">
                            <span class="dashicons dashicons-welcome-learn-more"></span>
                            <span><?php esc_html_e('Kurs & Dokumente', 'custom-crm'); ?></span>
                        </div>
                        <div class="crm-zone-content">
                            <div class="crm-card-course-widget">
                                <?php echo $item['linked_course']; ?>
                            </div>

                            <div class="crm-card-docs-panel">
                                <span class="crm-docs-label"><?php esc_html_e('Dokument-Schnellzugriff:', 'custom-crm'); ?></span>
                                <div class="crm-card-doc-buttons">
                                    <button type="button" class="crm-mini-doc-btn crm-direct-editor-btn"
                                        data-entry-id="<?php echo esc_attr($item['entry_id']); ?>"
                                        data-course-id="<?php echo esc_attr($item['course_id'] ?: 0); ?>"
                                        data-doc="offer"
                                        data-action="xsieben_offer"
                                        title="<?php esc_attr_e('Angebot öffnen & simulieren', 'custom-crm'); ?>">
                                        📄 <?php esc_html_e('Angebot', 'custom-crm'); ?>
                                    </button>
                                    <button type="button" class="crm-mini-doc-btn crm-direct-editor-btn"
                                        data-entry-id="<?php echo esc_attr($item['entry_id']); ?>"
                                        data-course-id="<?php echo esc_attr($item['course_id'] ?: 0); ?>"
                                        data-doc="kb"
                                        data-action="xsieben_kurszeitenbestaetigung"
                                        title="<?php esc_attr_e('Kurszeitenbestätigung (KB) öffnen', 'custom-crm'); ?>">
                                        📋 <?php esc_html_e('KB', 'custom-crm'); ?>
                                    </button>
                                    <button type="button" class="crm-mini-doc-btn crm-direct-editor-btn"
                                        data-entry-id="<?php echo esc_attr($item['entry_id']); ?>"
                                        data-course-id="<?php echo esc_attr($item['course_id'] ?: 0); ?>"
                                        data-doc="ab"
                                        data-action="xsieben_anmeldebestaetigung"
                                        title="<?php esc_attr_e('Anmeldebestätigung (AB) öffnen', 'custom-crm'); ?>">
                                        📝 <?php esc_html_e('AB', 'custom-crm'); ?>
                                    </button>
                                    <button type="button" class="crm-mini-doc-btn crm-direct-editor-btn"
                                        data-entry-id="<?php echo esc_attr($item['entry_id']); ?>"
                                        data-course-id="<?php echo esc_attr($item['course_id'] ?: 0); ?>"
                                        data-doc="antritt"
                                        data-action="xsieben_antrittsbestaetigung"
                                        title="<?php esc_attr_e('Antrittsmeldung (AMS) öffnen', 'custom-crm'); ?>">
                                        📋 <?php esc_html_e('Antritt', 'custom-crm'); ?>
                                    </button>
                                    <button type="button" class="crm-mini-doc-btn crm-direct-editor-btn"
                                        data-entry-id="<?php echo esc_attr($item['entry_id']); ?>"
                                        data-course-id="<?php echo esc_attr($item['course_id'] ?: 0); ?>"
                                        data-doc="tb"
                                        data-action="xsieben_teilnahmebestaetigung"
                                        title="<?php esc_attr_e('Teilnahmebestätigung (TB) öffnen', 'custom-crm'); ?>">
                                        📜 <?php esc_html_e('TB', 'custom-crm'); ?>
                                    </button>
                                    <button type="button" class="crm-mini-doc-btn crm-direct-editor-btn"
                                        data-entry-id="<?php echo esc_attr($item['entry_id']); ?>"
                                        data-course-id="<?php echo esc_attr($item['course_id'] ?: 0); ?>"
                                        data-doc="diplom"
                                        data-action="xsieben_diplom"
                                        title="<?php esc_attr_e('Diplom / Zertifikat öffnen', 'custom-crm'); ?>">
                                        🎓 <?php esc_html_e('Diplom', 'custom-crm'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Zone 3: Status & Nächster Schritt -->
                    <div class="crm-card-zone crm-card-zone-action">
                        <div class="crm-zone-title">
                            <span class="dashicons dashicons-flag"></span>
                            <span><?php esc_html_e('Status & Nächster Schritt', 'custom-crm'); ?></span>
                        </div>
                        <div class="crm-zone-content crm-card-action-content">
                            <!-- Status Pill & Dropdown -->
                            <div class="crm-card-status-block">
                                <div class="crm-status-pill crm-status-<?php echo esc_attr($item['status_key']); ?>"
                                    data-status="<?php echo esc_attr($item['status_key']); ?>"
                                    title="<?php esc_attr_e('Klicken zum Ändern des Status', 'custom-crm'); ?>">
                                    <span class="crm-status-dot"></span>
                                    <span class="crm-status-label"><?php echo esc_html($item['status_label']); ?></span>
                                    <span class="crm-status-chevron dashicons dashicons-arrow-down-alt2"></span>
                                    <select class="crm-status-dropdown" data-entry-id="<?php echo esc_attr($item['entry_id']); ?>" title="<?php esc_attr_e('Status auswählen', 'custom-crm'); ?>">
                                        <?php foreach ($all_statuses_def as $sk => $sconf) : ?>
                                            <option value="<?php echo esc_attr($sk); ?>" <?php selected($item['status_key'], $sk); ?>>
                                                <?php echo esc_html($sconf['label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Journey Tracker -->
                            <div class="crm-card-journey-block">
                                <?php echo $item['journey_html']; ?>
                            </div>

                            <!-- Next Action / Wizard CTA Button -->
                            <div class="crm-card-wizard-block">
                                <?php echo crm_render_card_cta($item); ?>
                            </div>

                            <!-- Time Meta -->
                            <div class="crm-status-meta">
                                <span class="dashicons dashicons-clock"></span>
                                <span class="crm-status-date-val"><?php echo esc_html($item['status_date_formatted']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render a single visual Kanban card.
 *
 * @param array     $item Pre-extracted entry data model.
 * @param array     $all_statuses_def All status definitions.
 * @param bool      $is_deferred Whether card is initially hidden for progressive infinite scroll.
 * @param bool|null $is_sent Whether card should render in streamlined compact sent mode (only name, status, and CTA).
 * @return string HTML output for the card.
 */
function crm_render_kanban_card($item, $all_statuses_def, $is_deferred = false, $is_sent = null) {
    $sent_statuses = ['angebot_gesendet', 'angebot_und_kurszeiten_gesendet', 'kurszeitenbestaetigung_gesendet', 'nachfassen', 'angebot_erstellt'];
    $is_sent_card = ($is_sent === true) || ($is_sent === null && in_array($item['status_key'] ?? '', $sent_statuses, true));

    ob_start();
    if ($is_sent_card) : ?>
    <div class="crm-kanban-card crm-entry-item crm-kanban-card-sent<?php echo $is_deferred ? ' crm-kanban-card-deferred' : ''; ?>"
        <?php if ($is_deferred) : ?>style="display:none;"<?php endif; ?>
        data-entry-id="<?php echo esc_attr($item['entry_id']); ?>"
        data-course-id="<?php echo esc_attr($item['course_id'] ?: 0); ?>"
        data-status-key="<?php echo esc_attr($item['status_key']); ?>"
        data-client-name="<?php echo esc_attr($item['client_display_name']); ?>"
        data-course-title="<?php echo esc_attr($item['course_title']); ?>"
        data-is-foerderung="<?php echo !empty($item['is_foerderung']) ? '1' : '0'; ?>"
        data-entry-date="<?php echo esc_attr($item['entry_timestamp']); ?>"
        data-course-date="<?php echo esc_attr($item['course_start_ts']); ?>">

        <div class="crm-kanban-card-top crm-kanban-sent-top" style="display:flex; justify-content:space-between; align-items:center;">
            <h5 class="crm-kanban-card-name" style="margin:0; display:flex; align-items:center; gap:6px;">
                <a href="#" class="crm-direct-editor-btn crm-open-case-link"
                    data-entry-id="<?php echo esc_attr($item['entry_id']); ?>"
                    data-course-id="<?php echo esc_attr($item['course_id'] ?: 0); ?>"
                    data-action="<?php echo esc_attr($item['default_editor_action']); ?>"
                    title="<?php esc_attr_e('Geschäftsvorfall öffnen', 'custom-crm'); ?>">
                    <?php echo esc_html($item['client_display_name']); ?>
                </a>
                <button type="button" class="crm-kanban-icon-btn crm-quick-edit-btn"
                    data-entry-id="<?php echo esc_attr($item['entry_id']); ?>"
                    data-course-id="<?php echo esc_attr($item['course_id'] ?: 0); ?>"
                    title="<?php esc_attr_e('Kundendaten bearbeiten', 'custom-crm'); ?>"
                    style="background:none; border:none; color:#64748b; cursor:pointer; padding:2px; display:inline-flex; align-items:center;">
                    <span class="dashicons dashicons-edit" style="font-size:13px; width:13px; height:13px;"></span>
                </button>
            </h5>

            <!-- Status Pill with quick change dropdown -->
            <div class="crm-status-pill crm-status-<?php echo esc_attr($item['status_key']); ?>"
                data-status="<?php echo esc_attr($item['status_key']); ?>"
                title="<?php esc_attr_e('Status ändern', 'custom-crm'); ?>">
                <span class="crm-status-dot"></span>
                <span class="crm-status-label"><?php echo esc_html($item['status_label']); ?></span>
                <select class="crm-status-dropdown" data-entry-id="<?php echo esc_attr($item['entry_id']); ?>">
                    <?php foreach ($all_statuses_def as $sk => $sconf) : ?>
                        <option value="<?php echo esc_attr($sk); ?>" <?php selected($item['status_key'], $sk); ?>>
                            <?php echo esc_html($sconf['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Single CTA Button -->
        <div class="crm-kanban-sent-cta-wrap crm-kanban-wizard">
            <?php
            $cta_html = function_exists('crm_render_card_cta') ? crm_render_card_cta($item) : '';
            echo !empty($cta_html) ? $cta_html : ($item['actions_html'] ?? '');
            ?>
        </div>
    </div>
    <?php else : ?>
    <div class="crm-kanban-card crm-entry-item<?php echo $is_deferred ? ' crm-kanban-card-deferred' : ''; ?>"
        <?php if ($is_deferred) : ?>style="display:none;"<?php endif; ?>
        data-entry-id="<?php echo esc_attr($item['entry_id']); ?>"
        data-course-id="<?php echo esc_attr($item['course_id'] ?: 0); ?>"
        data-status-key="<?php echo esc_attr($item['status_key']); ?>"
        data-client-name="<?php echo esc_attr($item['client_display_name']); ?>"
        data-course-title="<?php echo esc_attr($item['course_title']); ?>"
        data-is-foerderung="<?php echo $item['is_foerderung'] ? '1' : '0'; ?>"
        data-entry-date="<?php echo esc_attr($item['entry_timestamp']); ?>"
        data-course-date="<?php echo esc_attr($item['course_start_ts']); ?>">

        <div class="crm-kanban-card-top">
            <h5 class="crm-kanban-card-name">
                <a href="#" class="crm-direct-editor-btn crm-open-case-link"
                    data-entry-id="<?php echo esc_attr($item['entry_id']); ?>"
                    data-course-id="<?php echo esc_attr($item['course_id'] ?: 0); ?>"
                    data-action="<?php echo esc_attr($item['default_editor_action']); ?>"
                    title="<?php esc_attr_e('Geschäftsvorfall öffnen', 'custom-crm'); ?>">
                    <?php echo esc_html($item['client_display_name']); ?>
                </a>
            </h5>
            <span class="crm-kanban-date"><?php echo esc_html($item['formatted_date']); ?></span>
        </div>

        <div class="crm-kanban-course-title" title="<?php echo esc_attr($item['course_title']); ?>">
            🎓 <?php echo esc_html(mb_strimwidth($item['course_title'], 0, 36, '…')); ?>
        </div>

        <?php
        $kanban_certs = function_exists('crm_get_course_available_certifications') ? crm_get_course_available_certifications($item['course_id'] ?: 0, $item['entry_id']) : [];
        if (!empty($kanban_certs)) {
            echo function_exists('crm_render_course_cert_badges') ? crm_render_course_cert_badges($kanban_certs, 'compact', $item['entry_id'], $item['course_id'] ?: 0) : '';
        }
        ?>

        <div class="crm-kanban-badges">
            <?php echo $item['foerder_pure_badges']; ?>
            <button type="button" class="crm-kanban-icon-btn crm-quick-edit-btn"
                data-entry-id="<?php echo esc_attr($item['entry_id']); ?>"
                data-course-id="<?php echo esc_attr($item['course_id'] ?: 0); ?>"
                title="<?php esc_attr_e('Kundendaten bearbeiten', 'custom-crm'); ?>">
                <span class="dashicons dashicons-edit"></span>
            </button>
            <?php if (!empty($item['email_val'])) : ?>
                <a href="mailto:<?php echo esc_attr($item['email_val']); ?>" class="crm-kanban-icon-btn" title="<?php echo esc_attr($item['email_val']); ?>">
                    <span class="dashicons dashicons-email"></span>
                </a>
            <?php endif; ?>
            <?php if (!empty($item['phone_val'])) : ?>
                <a href="tel:<?php echo esc_attr($item['phone_val']); ?>" class="crm-kanban-icon-btn" title="<?php echo esc_attr($item['phone_val']); ?>">
                    <span class="dashicons dashicons-phone"></span>
                </a>
            <?php endif; ?>
        </div>

        <div class="crm-kanban-card-bottom">
            <!-- Status Pill -->
            <div class="crm-status-pill crm-status-<?php echo esc_attr($item['status_key']); ?>"
                data-status="<?php echo esc_attr($item['status_key']); ?>"
                title="<?php esc_attr_e('Status ändern', 'custom-crm'); ?>">
                <span class="crm-status-dot"></span>
                <span class="crm-status-label"><?php echo esc_html($item['status_label']); ?></span>
                <select class="crm-status-dropdown" data-entry-id="<?php echo esc_attr($item['entry_id']); ?>">
                    <?php foreach ($all_statuses_def as $sk => $sconf) : ?>
                        <option value="<?php echo esc_attr($sk); ?>" <?php selected($item['status_key'], $sk); ?>>
                            <?php echo esc_html($sconf['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Next step Wizard button -->
            <div class="crm-kanban-wizard">
                <?php echo $item['actions_html']; ?>
            </div>
        </div>
    </div>
    <?php endif;
    return ob_get_clean();
}

/**
 * Render the Visual Kanban Pipeline View (5 Workflow Stages).
 *
 * @param array $prepared_entries Array of pre-extracted entry data models.
 * @param array $all_statuses_def All status definitions.
 * @param int   $total_entries Total count of entries in the database.
 * @return string HTML output for the kanban pipeline.
 */
function crm_render_kanban_view($prepared_entries, $all_statuses_def, $total_entries = 0) {
    // 5 Lifecycle Columns
    $columns = [
        'col_neu' => [
            'title'    => __('Neu / Anfrage', 'custom-crm'),
            'icon'     => 'dashicons-email-alt',
            'accent'   => '#7c3aed',
            'statuses' => ['neu', 'ki_vorbereitet'],
        ],
        'col_ready' => [
            'title'    => __('Versandbereit ⚡', 'custom-crm'),
            'icon'     => 'dashicons-yes-alt',
            'accent'   => '#16a34a',
            'statuses' => ['versand_vorbereitet', 'ai_prepared', 'versandbereit'],
        ],
        'col_sent' => [
            'title'    => __('Gesendet & Nachfassen', 'custom-crm'),
            'icon'     => 'dashicons-controls-forward',
            'accent'   => '#d97706',
            'statuses' => ['angebot_gesendet', 'angebot_und_kurszeiten_gesendet', 'kurszeitenbestaetigung_gesendet', 'nachfassen', 'angebot_erstellt'],
        ],
        'col_booked' => [
            'title'    => __('Gebucht & TB', 'custom-crm'),
            'icon'     => 'dashicons-welcome-write-blog',
            'accent'   => '#0284c7',
            'statuses' => ['angemeldet', 'gebucht', 'teilnahmebestaetigung_gesendet'],
        ],
        'col_done' => [
            'title'    => __('Abgeschlossen', 'custom-crm'),
            'icon'     => 'dashicons-awards',
            'accent'   => '#64748b',
            'statuses' => ['abgeschlossen', 'diplom_gesendet', 'durchgefuehrt', 'storniert'],
        ],
    ];

    // Group entries into columns
    $grouped = [
        'col_neu'    => [],
        'col_ready'  => [],
        'col_sent'   => [],
        'col_booked' => [],
        'col_done'   => [],
    ];

    foreach ($prepared_entries as $item) {
        $placed = false;
        foreach ($columns as $col_key => $col_conf) {
            if (in_array($item['status_key'], $col_conf['statuses'], true)) {
                $grouped[$col_key][] = $item;
                $placed = true;
                break;
            }
        }
        if (!$placed) {
            $grouped['col_neu'][] = $item;
        }
    }

    $initial_open_limit = 12; // Initial visible batch per open column before progressive infinite scroll
    $total_count = $total_entries ? intval($total_entries) : count($prepared_entries);

    ob_start();
    ?>
    <div id="crm-view-kanban" class="crm-view-pane crm-kanban-board" style="display:none;"
         data-total-entries="<?php echo esc_attr($total_count); ?>"
         data-loaded-count="<?php echo esc_attr(count($prepared_entries)); ?>">
        <?php foreach ($columns as $col_key => $col_conf) : 
            $items_in_col = $grouped[$col_key];
            $count = count($items_in_col);
            $is_done_col = ($col_key === 'col_done');
        ?>
            <div class="crm-kanban-column <?php echo $is_done_col ? 'crm-col-done crm-col-collapsed' : 'crm-col-open'; ?>"
                 data-col-key="<?php echo esc_attr($col_key); ?>"
                 <?php if ($is_done_col) : ?>data-is-collapsed="1"<?php endif; ?>>
                
                <!-- Column Header -->
                <div class="crm-kanban-col-header <?php echo $is_done_col ? 'crm-kanban-done-header' : ''; ?>"
                     style="border-top-color: <?php echo esc_attr($col_conf['accent']); ?>;"
                     <?php if ($is_done_col) : ?>title="<?php esc_attr_e('Klicken zum Aufklappen / Zuklappen', 'custom-crm'); ?>" role="button" tabindex="0"<?php endif; ?>>
                    <div class="crm-kanban-col-title-wrap">
                        <span class="dashicons <?php echo esc_attr($col_conf['icon']); ?>" style="color: <?php echo esc_attr($col_conf['accent']); ?>;"></span>
                        <h4 class="crm-kanban-col-title"><?php echo esc_html($col_conf['title']); ?></h4>
                    </div>
                    
                    <div class="crm-kanban-col-header-meta" style="display:inline-flex; align-items:center; gap:6px;">
                        <span class="crm-kanban-col-count <?php echo $is_done_col ? 'crm-kanban-done-badge' : ''; ?>"><?php echo intval($count); ?></span>
                        <?php if ($is_done_col) : ?>
                            <button type="button" class="crm-kanban-col-toggle-btn" aria-expanded="false" title="<?php esc_attr_e('Abgeschlossene Vorgänge aufklappen', 'custom-crm'); ?>">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                                <span class="crm-toggle-text"><?php esc_html_e('Aufklappen', 'custom-crm'); ?></span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Column Cards Container -->
                <div class="crm-kanban-cards-wrap <?php echo $is_done_col ? 'crm-kanban-done-wrap' : ''; ?>" <?php if ($is_done_col) : ?>style="display:none;"<?php endif; ?>>
                    <?php if (empty($items_in_col)) : ?>
                        <div class="crm-kanban-empty">
                            <span class="dashicons dashicons-marker"></span>
                            <p><?php echo $is_done_col ? esc_html__('Keine abgeschlossenen Einträge', 'custom-crm') : esc_html__('Keine Einträge', 'custom-crm'); ?></p>
                        </div>
                    <?php else : ?>
                        <?php foreach ($items_in_col as $idx => $item) : 
                            $is_deferred = (!$is_done_col && $idx >= $initial_open_limit);
                            $is_sent_col = ($col_key === 'col_sent');
                            echo crm_render_kanban_card($item, $all_statuses_def, $is_deferred, $is_sent_col);
                        endforeach; ?>
                    <?php endif; ?>

                    <!-- Infinite Scroll Footer for Open Columns -->
                    <?php if (!$is_done_col && !empty($items_in_col)) : ?>
                        <div class="crm-kanban-infinite-footer" data-col-key="<?php echo esc_attr($col_key); ?>" data-total="<?php echo intval($count); ?>">
                            <?php if ($count > $initial_open_limit) : ?>
                                <div class="crm-kanban-infinite-status">
                                    <span class="crm-showing-text"><?php printf(esc_html__('Zeige %d von %d', 'custom-crm'), min($initial_open_limit, $count), $count); ?></span>
                                    <button type="button" class="crm-kanban-load-all-col-btn button button-small" title="<?php esc_attr_e('Alle restlichen Karten dieser Spalte sofort einblenden', 'custom-crm'); ?>">
                                        ⚡ <?php esc_html_e('Alle anzeigen', 'custom-crm'); ?>
                                    </button>
                                </div>
                                <div class="crm-kanban-all-loaded-indicator" style="display:none;">
                                    <span class="dashicons dashicons-yes"></span> <?php printf(esc_html__('Alle %d geladen', 'custom-crm'), $count); ?>
                                </div>
                            <?php else : ?>
                                <div class="crm-kanban-all-loaded-indicator">
                                    <span class="dashicons dashicons-yes"></span> <?php printf(esc_html__('Alle %d geladen', 'custom-crm'), $count); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render the Live Dossier for a specific entry in Split-View.
 *
 * @param int $entry_id Entry ID.
 * @param int $course_id Course ID (optional).
 * @param array|null $item Pre-extracted entry data model (optional).
 * @return string HTML output for the dossier.
 */
function crm_render_split_dossier($entry_id, $course_id = 0, $item = null) {
    $entry_id  = absint($entry_id);
    $course_id = absint($course_id);

    if (!$entry_id) {
        return '<div class="crm-split-empty-state" style="padding:40px 20px; text-align:center; color:#64748b;">' .
               '<span class="dashicons dashicons-warning" style="font-size:32px; width:32px; height:32px; margin-bottom:12px; color:#f59e0b;"></span>' .
               '<h3 style="font-size:16px; color:#1e293b; margin:0 0 6px 0;">' . esc_html__('Kein Kunde ausgewählt', 'custom-crm') . '</h3>' .
               '</div>';
    }

    if (!class_exists('CRM_Model')) {
        require_once dirname(__DIR__) . '/crm-model.php';
    }
    if (!function_exists('crm_render_screen2_spickzettel')) {
        require_once dirname(__DIR__) . '/controler/output-controler.php';
    }
    if (!function_exists('crm_render_business_case_timeline')) {
        require_once __DIR__ . '/crm-status.php';
    }

    // Resolve data
    if (!empty($item) && is_array($item)) {
        $client_name    = $item['client_display_name'] ?? __('Kunde', 'custom-crm');
        $course_id      = $item['course_id'] ?: $course_id;
        $course_title   = $item['course_title'] ?? __('Kurs', 'custom-crm');
        $status_key     = $item['status_key'] ?? 'neu';
        $status_label   = $item['status_label'] ?? __('Neu / Anfrage', 'custom-crm');
        $is_foerderung  = !empty($item['is_foerderung']);
        $foerder_badges = $item['foerder_pure_badges'] ?? '';
        $default_action = $item['default_editor_action'] ?? ($is_foerderung ? 'xsieben_angebot_und_kurszeiten' : 'xsieben_offer');
        $snap_count     = $item['snap_count'] ?? 0;
        $actions_html   = $item['actions_html'] ?? (function_exists('crm_render_entry_actions') ? crm_render_entry_actions($entry_id, $course_id, $status_key, $is_foerderung) : '');
    } else {
        $status_data    = function_exists('crm_get_entry_status') ? crm_get_entry_status($entry_id) : [];
        $status_key     = $status_data['status_key'] ?? 'neu';
        $status_label   = $status_data['status_label'] ?? __('Neu / Anfrage', 'custom-crm');
        $course_id      = $course_id ?: ($status_data['course_id'] ?? 0);
        $foerd          = function_exists('crm_get_entry_foerderung') ? crm_get_entry_foerderung($entry_id) : ['ams' => false, 'waff' => false];
        $is_foerderung  = (!empty($foerd['ams']) || !empty($foerd['waff']));
        $foerder_badges = function_exists('crm_render_foerderung_pure_badges') ? crm_render_foerderung_pure_badges($entry_id, $foerd) : '';
        $default_action = $is_foerderung ? 'xsieben_angebot_und_kurszeiten' : 'xsieben_offer';
        $snap_count     = function_exists('crm_get_entry_snapshots_count') ? crm_get_entry_snapshots_count($entry_id) : 0;
        $actions_html   = function_exists('crm_render_entry_actions') ? crm_render_entry_actions($entry_id, $course_id, $status_key, $is_foerderung) : '';

        $model = class_exists('CRM_Model') ? new CRM_Model($course_id, $entry_id) : null;
        $client_name = $model ? trim(($model->titel ? $model->titel . ' ' : '') . $model->vorname . ' ' . $model->nachname) : '';
        if (empty($client_name)) {
            $client_name = __('Anfrage #' . $entry_id, 'custom-crm');
        }
        $course_title = $model ? ($model->title ?: __('Kurs', 'custom-crm')) : __('Kurs', 'custom-crm');
    }

    // Spickzettel HTML
    $spickzettel_html = function_exists('crm_render_screen2_spickzettel') ? crm_render_screen2_spickzettel($entry_id, $course_id) : '';

    ob_start();
    ?>
    <div class="crm-split-dossier-inner" data-entry-id="<?php echo esc_attr($entry_id); ?>" data-course-id="<?php echo esc_attr($course_id); ?>">
        <!-- 1. Dossier Header / Topbar -->
        <div class="crm-split-dossier-topbar">
            <div class="crm-split-dossier-meta">
                <span class="crm-split-dossier-id">Anfrage #<?php echo esc_html($entry_id); ?></span>
                <span class="crm-split-dossier-title"><?php echo esc_html($client_name); ?></span>
                <span class="crm-status-pill crm-status-<?php echo esc_attr($status_key); ?> crm-status-pill-mini">
                    <span class="crm-status-dot"></span>
                    <span class="crm-status-label"><?php echo esc_html($status_label); ?></span>
                </span>
                <div class="crm-split-dossier-badges">
                    <?php echo $foerder_badges; ?>
                </div>
            </div>

            <div class="crm-split-dossier-actions">
                <button type="button" class="crm-card-btn crm-quick-edit-btn"
                    data-entry-id="<?php echo esc_attr($entry_id); ?>"
                    data-course-id="<?php echo esc_attr($course_id); ?>"
                    title="<?php esc_attr_e('Kundendaten bearbeiten', 'custom-crm'); ?>">
                    <span class="dashicons dashicons-edit"></span>
                    <span><?php esc_html_e('Kundendaten', 'custom-crm'); ?></span>
                </button>
                <button type="button" class="crm-split-fullscreen-btn crm-direct-editor-btn"
                    data-entry-id="<?php echo esc_attr($entry_id); ?>"
                    data-course-id="<?php echo esc_attr($course_id); ?>"
                    data-action="<?php echo esc_attr($default_action); ?>"
                    title="<?php esc_attr_e('Im großen Vollbild-Editor bearbeiten', 'custom-crm'); ?>">
                    <span class="dashicons dashicons-editor-expand"></span>
                    <span><?php esc_html_e('Vollbild-Editor', 'custom-crm'); ?></span>
                </button>
                <button type="button" class="crm-card-btn crm-history-btn"
                    data-entry-id="<?php echo esc_attr($entry_id); ?>"
                    title="<?php esc_attr_e('Status-Verlauf & Historie anzeigen', 'custom-crm'); ?>">
                    <span class="dashicons dashicons-backup"></span>
                </button>
                <button type="button" class="crm-card-btn crm-snapshots-btn"
                    data-entry-id="<?php echo esc_attr($entry_id); ?>"
                    title="<?php echo esc_attr(sprintf(__('Dokument- & Daten-Archiv (%d Snapshots)', 'custom-crm'), $snap_count)); ?>">
                    <span class="dashicons dashicons-archive"></span>
                    <?php if ($snap_count > 0) : ?>
                        <span class="crm-snap-count-badge"><?php echo intval($snap_count); ?></span>
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <!-- 2. Workflow & Quick Document Center -->
        <div class="crm-split-workflow-banner" style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:18px; padding:12px 16px; background:linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border:1px solid #e2e8f0; border-radius:8px; flex-wrap:wrap;">
            <div class="crm-split-workflow-left" style="display:flex; align-items:center; gap:10px;">
                <div class="crm-card-wizard-block">
                    <?php echo crm_render_card_cta(['entry_id' => $entry_id, 'course_id' => $course_id, 'status_key' => $status_key, 'is_foerderung' => $is_foerderung]); ?>
                </div>
            </div>

            <div class="crm-split-workflow-docs" style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                <span style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-right:2px;"><?php esc_html_e('Dokumente:', 'custom-crm'); ?></span>
                <button type="button" class="crm-mini-doc-btn crm-direct-editor-btn"
                    data-entry-id="<?php echo esc_attr($entry_id); ?>"
                    data-course-id="<?php echo esc_attr($course_id); ?>"
                    data-doc="offer"
                    data-action="xsieben_offer"
                    title="<?php esc_attr_e('Angebot öffnen & simulieren', 'custom-crm'); ?>">
                    📄 <?php esc_html_e('Angebot', 'custom-crm'); ?>
                </button>
                <button type="button" class="crm-mini-doc-btn crm-direct-editor-btn"
                    data-entry-id="<?php echo esc_attr($entry_id); ?>"
                    data-course-id="<?php echo esc_attr($course_id); ?>"
                    data-doc="kb"
                    data-action="xsieben_kurszeitenbestaetigung"
                    title="<?php esc_attr_e('Kurszeitenbestätigung (KB) öffnen', 'custom-crm'); ?>">
                    📋 <?php esc_html_e('KB', 'custom-crm'); ?>
                </button>
                <button type="button" class="crm-mini-doc-btn crm-direct-editor-btn"
                    data-entry-id="<?php echo esc_attr($entry_id); ?>"
                    data-course-id="<?php echo esc_attr($course_id); ?>"
                    data-doc="ab"
                    data-action="xsieben_anmeldebestaetigung"
                    title="<?php esc_attr_e('Anmeldebestätigung (AB) öffnen', 'custom-crm'); ?>">
                    📝 <?php esc_html_e('AB', 'custom-crm'); ?>
                </button>
                <button type="button" class="crm-mini-doc-btn crm-direct-editor-btn"
                    data-entry-id="<?php echo esc_attr($entry_id); ?>"
                    data-course-id="<?php echo esc_attr($course_id); ?>"
                    data-doc="antritt"
                    data-action="xsieben_antrittsbestaetigung"
                    title="<?php esc_attr_e('Antrittsmeldung (AMS) öffnen', 'custom-crm'); ?>">
                    📋 <?php esc_html_e('Antritt', 'custom-crm'); ?>
                </button>
                <button type="button" class="crm-mini-doc-btn crm-direct-editor-btn"
                    data-entry-id="<?php echo esc_attr($entry_id); ?>"
                    data-course-id="<?php echo esc_attr($course_id); ?>"
                    data-doc="tb"
                    data-action="xsieben_teilnahmebestaetigung"
                    title="<?php esc_attr_e('Teilnahmebestätigung (TB) öffnen', 'custom-crm'); ?>">
                    📜 <?php esc_html_e('TB', 'custom-crm'); ?>
                </button>
                <button type="button" class="crm-mini-doc-btn crm-direct-editor-btn"
                    data-entry-id="<?php echo esc_attr($entry_id); ?>"
                    data-course-id="<?php echo esc_attr($course_id); ?>"
                    data-doc="diplom"
                    data-action="xsieben_diplom"
                    title="<?php esc_attr_e('Diplom / Zertifikat öffnen', 'custom-crm'); ?>">
                    🎓 <?php esc_html_e('Diplom', 'custom-crm'); ?>
                </button>
            </div>
        </div>

        <!-- 3. 3-Punkte-Spickzettel (Birkenbihl 3-Sekunden-Orientierung) -->
        <div class="crm-split-spickzettel-wrap">
            <?php echo $spickzettel_html; ?>
        </div>

        <!-- 4. Verlauf des Geschäftsvorfalls (Startet mit Test-E-Mail, immer nur das letzte) -->
        <div class="crm-split-history-wrap" id="crm-split-history-wrap-<?php echo esc_attr($entry_id); ?>">
            <?php echo function_exists('crm_render_business_case_timeline') ? crm_render_business_case_timeline($entry_id, $course_id) : ''; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render the Split-View (Master-Detail).
 * Left: Scrollable Customer Feed
 * Right: Instant Live Dossier & Document Center
 *
 * @param array $prepared_entries Array of pre-extracted entry data models.
 * @param array $all_statuses_def All status definitions.
 * @return string HTML output for the split view.
 */
function crm_render_split_view($prepared_entries, $all_statuses_def) {
    if (empty($prepared_entries)) {
        return '<p class="crm-empty-state">' . esc_html__('Keine Anfragen gefunden.', 'custom-crm') . '</p>';
    }

    $first_entry = reset($prepared_entries);

    ob_start();
    ?>
    <div id="crm-view-split" class="crm-view-pane crm-split-layout" style="display:none;">
        <!-- Left Sidebar: Customer Feed -->
        <aside class="crm-split-sidebar">
            <div class="crm-split-sidebar-header">
                <span class="crm-split-sidebar-title">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e('Kunden-Feed', 'custom-crm'); ?>
                </span>
                <span class="crm-split-count"><span id="crm-split-visible-count"><?php echo count($prepared_entries); ?></span> <?php esc_html_e('Einträge', 'custom-crm'); ?></span>
            </div>

            <div class="crm-split-list">
                <?php 
                $is_first = true;
                foreach ($prepared_entries as $item) : 
                    $active_class = $is_first ? 'is-active' : '';
                    $is_first = false;
                ?>
                    <div class="crm-split-item crm-entry-item <?php echo esc_attr($active_class); ?>"
                        data-entry-id="<?php echo esc_attr($item['entry_id']); ?>"
                        data-course-id="<?php echo esc_attr($item['course_id'] ?: 0); ?>"
                        data-status-key="<?php echo esc_attr($item['status_key']); ?>"
                        data-client-name="<?php echo esc_attr($item['client_display_name']); ?>"
                        data-course-title="<?php echo esc_attr($item['course_title']); ?>"
                        data-is-foerderung="<?php echo $item['is_foerderung'] ? '1' : '0'; ?>"
                        data-entry-date="<?php echo esc_attr($item['entry_timestamp']); ?>"
                        data-course-date="<?php echo esc_attr($item['course_start_ts']); ?>"
                        data-default-action="<?php echo esc_attr($item['default_editor_action']); ?>">

                        <div class="crm-split-item-top">
                            <h4 class="crm-split-item-name"><?php echo esc_html($item['client_display_name']); ?></h4>
                            <span class="crm-split-item-date"><?php echo esc_html($item['formatted_date']); ?></span>
                        </div>

                        <div class="crm-split-item-course" title="<?php echo esc_attr($item['course_title']); ?>">
                            <?php echo esc_html(mb_strimwidth($item['course_title'], 0, 38, '…')); ?>
                        </div>

                        <?php
                        $split_certs = function_exists('crm_get_course_available_certifications') ? crm_get_course_available_certifications($item['course_id'] ?: 0, $item['entry_id']) : [];
                        if (!empty($split_certs)) {
                            echo function_exists('crm_render_course_cert_badges') ? crm_render_course_cert_badges($split_certs, 'compact', $item['entry_id'], $item['course_id'] ?: 0) : '';
                        }
                        ?>

                        <div class="crm-split-item-bottom">
                            <span class="crm-status-pill crm-status-<?php echo esc_attr($item['status_key']); ?> crm-status-pill-mini">
                                <span class="crm-status-dot"></span>
                                <span class="crm-status-label"><?php echo esc_html($item['status_label']); ?></span>
                            </span>
                            <div class="crm-split-item-badges">
                                <?php echo $item['foerder_pure_badges']; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- Right Main: Live Dossier & Document Center -->
        <main class="crm-split-detail">
            <div id="crm-split-dossier-panel">
                <?php 
                if (!empty($first_entry)) {
                    echo crm_render_split_dossier((int)$first_entry['entry_id'], (int)$first_entry['course_id'], $first_entry);
                } else {
                ?>
                    <div class="crm-split-empty-state" style="padding: 40px 20px; text-align: center; color: #64748b;">
                        <span class="dashicons dashicons-id-alt" style="font-size: 32px; width: 32px; height: 32px; margin-bottom: 12px; color: #94a3b8;"></span>
                        <h3 style="font-size: 16px; color: #1e293b; margin: 0 0 6px 0;"><?php esc_html_e('Kein Kunde ausgewählt', 'custom-crm'); ?></h3>
                        <p style="font-size: 13px; color: #64748b; margin: 0;"><?php esc_html_e('Klicke links auf einen Kunden, um das Dossier zu öffnen.', 'custom-crm'); ?></p>
                    </div>
                <?php } ?>
            </div>
        </main>
    </div>
    <?php
    return ob_get_clean();
}
