<?php
/**
 * CRM Business Case Timeline & History Presenter
 * Manages chronological business case milestones, deduped history,
 * snapshot enrichment, and the interactive split-view timeline.
 *
 * 100% self-contained within inc/core/crm/
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extrahiert den bereinigten, chronologischen Verlauf des Geschäftsvorfalls.
 *
 * Filter-Regeln:
 * 1. Startpunkt: Beginnt erst mit dem Test-E-Mail (alle vorgelagerten System-Logs
 *    wie Auto-Detect, Checkboxen, PDF-Generierungen, WPForms-Eingang werden ignoriert).
 * 2. Immer nur das letzte:
 *    - Bei mehreren Test-E-Mails wird nur das LETZTE Test-E-Mail als Startpunkt genommen.
 *    - Für alle weiteren Meilensteine (Angebot, KB, Nachfassen, Anmeldung, etc.)
 *      wird bei mehrfachem Auftreten ebenfalls immer nur das LETZTE Ereignis behalten.
 * 3. Chronologische Sortierung vom letzten Test-E-Mail bis zum aktuellsten Ereignis.
 * 4. Das letzte Ereignis wird als 'is_latest' = true markiert.
 *
 * @param int $entry_id
 * @return array
 */
function crm_get_business_case_history(int $entry_id): array
{
    global $wpdb;
    $entry_id = absint($entry_id);
    if (!$entry_id) {
        return [
            'has_started'   => false,
            'has_test_mail' => false,
            'entries'       => [],
        ];
    }

    $table_history   = $wpdb->prefix . 'crm_entry_status_history';
    $table_snapshots = $wpdb->prefix . 'crm_document_snapshots';

    // 1. Raw history from DB
    $raw_history = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table_history WHERE entry_id = %d ORDER BY status_date ASC, id ASC", $entry_id),
        ARRAY_A
    );
    if (empty($raw_history)) {
        $raw_history = [];
    }

    // 2. Snapshots from DB
    $snapshots = [];
    $snap_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_snapshots));
    if ($snap_table_exists) {
        $snapshots = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table_snapshots WHERE entry_id = %d ORDER BY sent_at ASC, id ASC", $entry_id),
            ARRAY_A
        );
        if (empty($snapshots)) {
            $snapshots = [];
        }
    }

    // 3. System noise to strictly exclude
    $ignored_statuses = [
        'certs_updated',
        'form_data_updated',
        'course_ai_detected',
        'linked_business',
        'foerderung_ams',
        'foerderung_waff',
        'versand_vorbereitet',
        'pdf_sections_reordered',
    ];

    // Filter out pure PDF generation & technical logs
    $filtered_raw = [];
    foreach ($raw_history as $row) {
        $key = $row['status_key'] ?? '';
        if (in_array($key, $ignored_statuses, true)) {
            continue;
        }
        if (str_starts_with($key, 'pdf_')) {
            continue;
        }
        $filtered_raw[] = $row;
    }

    // 4. Find all test emails
    $test_email_indices = [];
    foreach ($filtered_raw as $idx => $row) {
        $is_test = ($row['status_key'] === 'test_mail_gesendet')
            || (stripos($row['note'] ?? '', 'Test-E-Mail') !== false)
            || (stripos($row['note'] ?? '', 'Test-Mail') !== false);
        if ($is_test) {
            $test_email_indices[] = $idx;
        }
    }

    $has_test_mail = !empty($test_email_indices);
    $last_test_idx = $has_test_mail ? end($test_email_indices) : -1;

    $candidates = [];
    if ($has_test_mail) {
        // Start STRICTLY with the LAST test email
        // All events prior to the last test email are omitted!
        $candidates[] = $filtered_raw[$last_test_idx];

        // All subsequent events after the last test email
        $total = count($filtered_raw);
        for ($i = $last_test_idx + 1; $i < $total; $i++) {
            $candidates[] = $filtered_raw[$i];
        }
    } else {
        // Fallback: If no test email was sent, check if real customer emails exist
        $milestone_keys = [
            'angebot_gesendet',
            'angebot_und_kurszeiten_gesendet',
            'kurszeitenbestaetigung_gesendet',
            'nachfassen',
            'angemeldet',
            'rechnung_gestellt',
            'rechnung_bezahlt',
            'teilnahmebestaetigung_gesendet',
            'diplom_gesendet',
            'abgeschlossen',
            'storniert',
        ];
        foreach ($filtered_raw as $row) {
            if (in_array($row['status_key'], $milestone_keys, true)) {
                $candidates[] = $row;
            }
        }
    }

    if (empty($candidates)) {
        return [
            'has_started'   => false,
            'has_test_mail' => false,
            'entries'       => [],
        ];
    }

    // 5. "und dann immer nur das letzte":
    // For each distinct status_key / event type, keep ONLY the LAST occurrence!
    $deduped_by_key = [];
    foreach ($candidates as $row) {
        $key = $row['status_key'];
        // Overwrite earlier ones: keeps only the LAST occurrence of this status
        $deduped_by_key[$key] = $row;
    }

    // Sort chronologically by status_date, id
    $final_entries = array_values($deduped_by_key);
    usort($final_entries, function ($a, $b) {
        $t1 = strtotime($a['status_date']);
        $t2 = strtotime($b['status_date']);
        if ($t1 === $t2) {
            return ($a['id'] <=> $b['id']);
        }
        return ($t1 <=> $t2);
    });

    // 6. Enrich with Snapshot data and formatted metadata
    $enriched = [];
    $count = count($final_entries);
    foreach ($final_entries as $idx => $entry) {
        $entry_date_ts = strtotime($entry['status_date']);
        $is_latest = ($idx === $count - 1);
        $status_key = $entry['status_key'];

        // Determine user display name
        $user_name = '';
        if (!empty($entry['created_by'])) {
            $user = get_userdata($entry['created_by']);
            if ($user) {
                $user_name = $user->display_name;
            }
        }

        // Parse recipient and subject from note fallback
        $note = $entry['note'] ?? '';
        $recipient = '';
        $subject = '';
        if (preg_match('/(?:an:\s*|an Kunden\s*\()([^\)\s]+(?:\s*,\s*[^\)\s]+)*)/i', $note, $m)) {
            $recipient = trim($m[1]);
        }
        if (preg_match('/Betreff:\s*(.+)$/i', $note, $m)) {
            $subject = trim($m[1]);
        }

        // Match with snapshot
        $matched_snap = null;
        foreach ($snapshots as $snap) {
            $snap_ts = strtotime($snap['sent_at']);
            // Close in time (within 10 minutes) and matching test mode
            $is_test_match = ($status_key === 'test_mail_gesendet' && !empty($snap['is_test']))
                || ($status_key !== 'test_mail_gesendet' && empty($snap['is_test']));
            if ($is_test_match && abs($snap_ts - $entry_date_ts) <= 600) {
                $matched_snap = $snap;
                break;
            }
        }

        // If snapshot matched, use high-fidelity data
        $pdf_url   = '';
        $pdf_name  = '';
        $email_html = '';
        if ($matched_snap) {
            if (!empty($matched_snap['recipient'])) {
                $recipient = $matched_snap['recipient'];
            }
            if (!empty($matched_snap['sent_targets']) && $status_key === 'test_mail_gesendet') {
                $recipient = $matched_snap['sent_targets'];
            }
            if (!empty($matched_snap['subject'])) {
                $subject = $matched_snap['subject'];
            }
            $pdf_url   = $matched_snap['pdf_file_url'] ?? '';
            $pdf_name  = $matched_snap['pdf_filename'] ?? '';
            $email_html = $matched_snap['email_body_html'] ?? '';
        }

        $all_statuses = crm_get_statuses();
        $status_label = $entry['status_label'] ?: ($all_statuses[$status_key]['label'] ?? $status_key);

        $enriched[] = [
            'id'             => (int) $entry['id'],
            'entry_id'       => (int) $entry['entry_id'],
            'status_key'     => $status_key,
            'status_label'   => $status_label,
            'status_date'    => $entry['status_date'],
            'formatted_date' => date_i18n('d.m.Y, H:i', $entry_date_ts),
            'user_name'      => $user_name,
            'note'           => $note,
            'recipient'      => $recipient,
            'subject'        => $subject,
            'pdf_url'        => $pdf_url,
            'pdf_name'       => $pdf_name,
            'email_html'     => $email_html,
            'is_test'        => ($status_key === 'test_mail_gesendet'),
            'is_latest'      => $is_latest,
        ];
    }

    return [
        'has_started'   => true,
        'has_test_mail' => $has_test_mail,
        'entries'       => $enriched,
    ];
}

/**
 * Rendert die visuelle Zeitachse für den Verlauf des Geschäftsvorfalls im Split View.
 *
 * @param int $entry_id
 * @param int $course_id
 * @return string HTML
 */
function crm_render_business_case_timeline(int $entry_id, int $course_id = 0): string
{
    $entry_id = absint($entry_id);
    if (!$entry_id) {
        return '';
    }

    $history_data = crm_get_business_case_history($entry_id);
    $has_started  = !empty($history_data['has_started']) && !empty($history_data['entries']);
    $entries      = $history_data['entries'] ?? [];

    ob_start();
    ?>
    <div class="crm-split-history-section" style="margin-top:20px; background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.04); overflow:hidden;">
        <!-- Header -->
        <div class="crm-split-history-header" style="display:flex; justify-content:space-between; align-items:center; padding:12px 18px; background:linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom:1px solid #e2e8f0; flex-wrap:wrap; gap:8px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-backup" style="color:#0284c7; font-size:18px; width:18px; height:18px;"></span>
                <h4 style="margin:0; font-size:13.5px; font-weight:700; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    <span><?php esc_html_e('Verlauf des Geschäftsvorfalls', 'custom-crm'); ?></span>
                </h4>
                <span style="font-size:11px; font-weight:500; color:#64748b; background:#e2e8f0; padding:2px 8px; border-radius:12px;">
                    <?php if ($has_started) : ?>
                        <?php echo count($entries); ?> <?php echo count($entries) === 1 ? esc_html__('Ereignis', 'custom-crm') : esc_html__('Ereignisse', 'custom-crm'); ?> (ab Test-Mail)
                    <?php else : ?>
                        <?php esc_html_e('Wartet auf Start', 'custom-crm'); ?>
                    <?php endif; ?>
                </span>
            </div>

            <div style="font-size:11px; color:#64748b; display:flex; align-items:center; gap:10px;">
                <span style="font-style:italic;">
                    <?php esc_html_e('Startet mit Test-E-Mail · Je Schritt nur das letzte', 'custom-crm'); ?>
                </span>
                <button type="button" class="crm-card-btn crm-history-btn" data-entry-id="<?php echo esc_attr($entry_id); ?>" title="<?php esc_attr_e('Vollständiges technisches System-Audit (alle Roh-Logs) anzeigen', 'custom-crm'); ?>" style="font-size:11px; height:24px; padding:0 8px; display:inline-flex; align-items:center; gap:3px;">
                    <span class="dashicons dashicons-list-view" style="font-size:13px; width:13px; height:13px;"></span>
                    <span><?php esc_html_e('Voll-Audit', 'custom-crm'); ?></span>
                </button>
            </div>
        </div>

        <!-- Content -->
        <div class="crm-split-history-body" style="padding:16px 18px;">
            <?php if (!$has_started) : ?>
                <div class="crm-bcase-empty-state" style="padding:22px 20px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px; text-align:center;">
                    <span class="dashicons dashicons-email-alt" style="font-size:32px; width:32px; height:32px; color:#94a3b8; margin-bottom:8px;"></span>
                    <h4 style="margin:0 0 6px 0; font-size:14px; color:#334155; font-weight:700;">
                        <?php esc_html_e('Noch keine Test-E-Mail versendet', 'custom-crm'); ?>
                    </h4>
                    <p style="margin:0 0 12px 0; font-size:12px; color:#64748b; line-height:1.5; max-width:540px; margin-left:auto; margin-right:auto;">
                        <?php esc_html_e('Der lesbare Verlauf dieses Geschäftsvorfalls startet automatisch mit dem ersten Test-E-Mail. Sämtliche interne Vorbereitungen vor dem Testversand werden ignoriert.', 'custom-crm'); ?>
                    </p>
                    <button type="button" class="button button-small button-primary crm-direct-editor-btn"
                        data-entry-id="<?php echo esc_attr($entry_id); ?>"
                        data-course-id="<?php echo esc_attr($course_id); ?>"
                        data-action="xsieben_offer"
                        style="font-size:11.5px; display:inline-flex; align-items:center; gap:5px; padding:3px 10px; height:28px;">
                        <span class="dashicons dashicons-media-document" style="font-size:14px; width:14px; height:14px;"></span>
                        <span><?php esc_html_e('Dokument öffnen & Test-E-Mail senden', 'custom-crm'); ?></span>
                    </button>
                </div>
            <?php else : ?>
                <div class="crm-bcase-timeline" style="display:flex; flex-direction:column; gap:14px; position:relative; padding-left:14px; border-left:2px solid #e2e8f0; margin-left:8px;">
                    <?php foreach ($entries as $index => $item) : 
                        $is_test = !empty($item['is_test']);
                        $is_latest = !empty($item['is_latest']);
                        $drawer_id = 'crm-bcase-preview-' . $entry_id . '-' . $item['id'];
                    ?>
                        <div class="crm-bcase-item <?php echo $is_latest ? 'is-latest' : ''; ?>" style="position:relative; background:#ffffff; border:1px solid <?php echo $is_latest ? '#93c5fd' : '#e2e8f0'; ?>; border-left:4px solid <?php echo $is_latest ? '#0284c7' : ($is_test ? '#06b6d4' : '#10b981'); ?>; border-radius:6px; padding:12px 14px; box-shadow:<?php echo $is_latest ? '0 2px 8px rgba(2,132,199,0.08)' : '0 1px 3px rgba(0,0,0,0.02)'; ?>;">
                            <!-- Node bullet on timeline -->
                            <span style="position:absolute; left:-21px; top:14px; width:12px; height:12px; border-radius:50%; background:<?php echo $is_latest ? '#0284c7' : ($is_test ? '#06b6d4' : '#10b981'); ?>; border:2px solid #ffffff; box-shadow:0 0 0 1px <?php echo $is_latest ? '#0284c7' : ($is_test ? '#06b6d4' : '#10b981'); ?>;"></span>

                            <!-- Top row: Status, Badges, Timestamp & User -->
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; margin-bottom:6px; flex-wrap:wrap;">
                                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                    <?php echo crm_render_status_badge($item['status_key'], $item['status_label']); ?>
                                    <?php if ($is_latest) : ?>
                                        <span class="crm-bcase-pill-latest" style="display:inline-flex; align-items:center; gap:3px; background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; font-size:10.5px; font-weight:700; padding:1px 6px; border-radius:4px;">
                                            <span>✨</span>
                                            <span><?php esc_html_e('Aktueller Stand / Letztes Ereignis', 'custom-crm'); ?></span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:11.5px; color:#64748b;">
                                    <span>📅 <strong><?php echo esc_html($item['formatted_date']); ?></strong></span>
                                    <?php if (!empty($item['user_name'])) : ?>
                                        <span><?php echo sprintf(esc_html__('von %s', 'custom-crm'), '<strong>' . esc_html($item['user_name']) . '</strong>'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Middle row: Recipient & Subject -->
                            <?php if (!empty($item['recipient']) || !empty($item['subject'])) : ?>
                                <div style="margin-top:6px; padding:6px 10px; background:#f8fafc; border:1px solid #f1f5f9; border-radius:4px; font-size:12px; color:#334155;">
                                    <?php if (!empty($item['recipient'])) : ?>
                                        <div style="margin-bottom:2px;">
                                            <strong style="color:#475569;"><?php esc_html_e('Empfänger:', 'custom-crm'); ?></strong>
                                            <code style="background:transparent; color:#0369a1; padding:0; font-size:11.5px;"><?php echo esc_html($item['recipient']); ?></code>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($item['subject'])) : ?>
                                        <div>
                                            <strong style="color:#475569;"><?php esc_html_e('Betreff:', 'custom-crm'); ?></strong>
                                            <span style="color:#0f172a; font-weight:600;"><?php echo esc_html($item['subject']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php elseif (!empty($item['note'])) : ?>
                                <div style="margin-top:6px; padding:6px 10px; background:#f8fafc; border:1px solid #f1f5f9; border-radius:4px; font-size:12px; color:#475569; line-height:1.4;">
                                    <?php echo nl2br(esc_html($item['note'])); ?>
                                </div>
                            <?php endif; ?>

                            <!-- Bottom row: PDF attachments & E-Mail reader toggle -->
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px; gap:8px; flex-wrap:wrap;">
                                <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                                    <?php if (!empty($item['pdf_url'])) : ?>
                                        <a href="<?php echo esc_url($item['pdf_url']); ?>" target="_blank" class="button button-small" style="font-size:11px; height:24px; line-height:22px; padding:0 8px; display:inline-flex; align-items:center; gap:4px; color:#0369a1; border-color:#bae6fd; background:#f0f9ff;" title="<?php esc_attr_e('Archiviertes PDF öffnen', 'custom-crm'); ?>">
                                            <span class="dashicons dashicons-pdf" style="font-size:13px; width:13px; height:13px; color:#0284c7;"></span>
                                            <span><?php echo esc_html($item['pdf_name'] ?: __('PDF-Beilage', 'custom-crm')); ?></span>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($item['email_html'])) : ?>
                                    <button type="button" class="button button-small crm-bcase-toggle-content" data-target="<?php echo esc_attr($drawer_id); ?>" style="font-size:11px; height:24px; line-height:22px; padding:0 8px; display:inline-flex; align-items:center; gap:4px; color:#475569;" title="<?php esc_attr_e('Vollständigen E-Mail-Inhalt anzeigen/verbergen', 'custom-crm'); ?>">
                                        <span class="dashicons dashicons-visibility" style="font-size:13px; width:13px; height:13px;"></span>
                                        <span class="crm-bcase-toggle-label"><?php esc_html_e('E-Mail-Inhalt lesen', 'custom-crm'); ?></span>
                                    </button>
                                <?php endif; ?>
                            </div>

                            <!-- Expandable email preview drawer -->
                            <?php if (!empty($item['email_html'])) : ?>
                                <div id="<?php echo esc_attr($drawer_id); ?>" class="crm-bcase-preview-drawer" style="display:none; margin-top:10px; border-top:1px dashed #cbd5e1; padding-top:10px;">
                                    <div style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
                                        <span><?php esc_html_e('Archivierter E-Mail-Inhalt:', 'custom-crm'); ?></span>
                                        <button type="button" class="button-link crm-bcase-toggle-content" data-target="<?php echo esc_attr($drawer_id); ?>" style="font-size:11px; text-decoration:none; color:#64748b;">&times; <?php esc_html_e('Schließen', 'custom-crm'); ?></button>
                                    </div>
                                    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:14px; max-height:360px; overflow-y:auto; font-size:12px; color:#1e293b; box-shadow:inset 0 1px 3px rgba(0,0,0,0.03);">
                                        <?php echo wp_kses_post($item['email_html']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

