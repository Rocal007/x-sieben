<?php

require_once dirname(__DIR__) . '/helpers/crm-status.php';
require_once dirname(__DIR__) . '/helpers/normalize.php';
require_once dirname(__DIR__) . '/helpers/crm-pdf-sections.php';
if (file_exists(dirname(__DIR__) . '/helpers/crm-friedelin.php')) {
    require_once dirname(__DIR__) . '/helpers/crm-friedelin.php';
}
if (file_exists(dirname(__DIR__) . '/crm-model.php')) {
    require_once dirname(__DIR__) . '/crm-model.php';
}

/**
 * Rendert den gehirn-gerechten 3-Punkte-Spickzettel (Birkenbihl 3-Sekunden-Orientierung)
 * im Kopfbereich von Screen 2 (Vorschau & Mail-Editor).
 *
 * @param int $entry_id
 * @param int $course_id
 * @return string HTML
 */
function crm_render_screen2_spickzettel(int $entry_id, int $course_id): string
{
    if (!$entry_id && !$course_id) {
        return '';
    }

    $model = class_exists('CRM_Model') ? new CRM_Model($course_id, $entry_id) : null;
    if (!$model) {
        return '';
    }

    // 1. Wer? (Kunde & Firma)
    $client_name = trim(($model->titel ? $model->titel . ' ' : '') . $model->vorname . ' ' . $model->nachname);
    if (empty($client_name)) {
        $client_name = __('Interessent / Kunde', 'custom-crm');
    }
    $client_company = !empty($model->customer_company) ? $model->customer_company : (!empty($model->kunden_firma) ? $model->kunden_firma : '');
    $client_email = $model->email ?: '';
    $client_phone = $model->telefon ?: '';
    $client_city = trim(($model->zip_code ? $model->zip_code . ' ' : '') . $model->city);
    $client_street = trim(($model->street ?: '') . ' ' . ($model->house_number ?: ''));
    $client_svr = $model->svr ?: '';

    // Original-Formulardaten & Kundennachricht
    $entry_fields = [];
    if (!empty($model->entry_data) && is_array($model->entry_data)) {
        $entry_fields = $model->entry_data;
    } elseif ($entry_id && function_exists('wpforms')) {
        $raw_entry = wpforms()->entry->get($entry_id);
        if ($raw_entry && !empty($raw_entry->fields)) {
            $entry_fields = is_string($raw_entry->fields) ? json_decode($raw_entry->fields, true) : $raw_entry->fields;
        }
    }

    $customer_message = '';
    if (!empty($entry_fields) && is_array($entry_fields)) {
        foreach ($entry_fields as $f) {
            $fname = isset($f['name']) ? mb_strtolower(trim((string)$f['name']), 'UTF-8') : '';
            if (strpos($fname, 'nachricht') !== false || strpos($fname, 'freitext') !== false || strpos($fname, 'anmerkung') !== false) {
                $val = is_array($f['value'] ?? '') ? implode(', ', $f['value']) : (string)($f['value'] ?? '');
                if (!empty($val)) {
                    $customer_message = $val;
                    break;
                }
            }
        }
    }

    // 2. Was & Wann? (Kurs & Termine)
    $status_data = function_exists('crm_get_entry_status') ? crm_get_entry_status($entry_id) : null;
    $inquiry_type = $status_data['inquiry_type'] ?? 'course';
    $custom_title = $status_data['custom_title'] ?? '';

    if ($inquiry_type === 'freie_anfrage') {
        $course_title = $custom_title ?: __('Freie Geschäftsanfrage / Inhouse', 'custom-crm');
        $course_dates = (!empty($status_data['course_start_date']) ? $status_data['course_start_date'] : __('Termine nach Vereinbarung', 'custom-crm'));
        if (!empty($status_data['course_start_date']) && !empty($status_data['course_end_date']) && strtotime($status_data['course_start_date']) && strtotime($status_data['course_end_date'])) {
            $course_dates = date_i18n('d.m.Y', strtotime($status_data['course_start_date'])) . ' – ' . date_i18n('d.m.Y', strtotime($status_data['course_end_date']));
        }
        $course_location = __('Inhouse / Nach Vereinbarung', 'custom-crm');
        $course_le = '';
    } else {
        $course_title = $model->title ?: __('Kein Kurs zugeordnet', 'custom-crm');
        $course_dates = trim(($model->start_datum ?: '') . ($model->end_datum ? ' – ' . $model->end_datum : ''));
        $course_location = $model->schulungsort ?: ($model->ort ?: __('Wien & Online', 'custom-crm'));
        $course_le = $model->anzahl_le ? ($model->anzahl_le . ' LE') : '';
    }

    // 3. Wieviel & Förderung? (Finanzen)
    $preis_netto = is_numeric($model->preis_netto) ? floatval($model->preis_netto) : 0.0;
    $cert_price = 0.0;
    if (function_exists('crm_resolve_course_certification')) {
        $certs = crm_resolve_course_certification($entry_id, $course_id);
        if (!empty($certs) && isset($certs[0]['price'])) {
            $p_str = str_replace('.', '', $certs[0]['price']);
            $p_str = str_replace(',', '.', $p_str);
            $cert_price = floatval($p_str);
        }
    }
    $total_price = $preis_netto + $cert_price;

    $foerd = function_exists('crm_get_entry_foerderung') ? crm_get_entry_foerderung($entry_id) : ['ams' => false, 'waff' => false];
    $foerd_label = __('Selbstzahler / Direkt', 'custom-crm');
    if (!empty($foerd['ams']) && !empty($foerd['waff'])) {
        $foerd_label = 'AMS & WAFF Förderung';
    } elseif (!empty($foerd['ams'])) {
        $foerd_label = 'AMS Förderung';
    } elseif (!empty($foerd['waff'])) {
        $foerd_label = 'WAFF Förderung';
    }

    // Status
    $status_data = function_exists('crm_get_entry_status') ? crm_get_entry_status($entry_id) : null;
    $status_label = $status_data['status_label'] ?? __('Neu / Anfrage', 'custom-crm');
    $status_key = $status_data['status_key'] ?? 'neu';

    ob_start();
    ?>
    <div class="crm-spickzettel-box" style="flex: 1 1 100%; width: 100%; background: #ffffff; border: 1px solid #cbd5e1; border-left: 5px solid #0284c7; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.04); margin-bottom: 18px; padding: 14px 18px;">
        <div class="crm-spickzettel-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
            <div class="crm-spickzettel-title" style="font-size: 13.5px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                <span class="dashicons dashicons-portfolio" style="color: #0284c7; font-size: 17px; width: 17px; height: 17px;"></span>
                <span><?php esc_html_e('Geschäftsvorfall & 3-Punkte-Spickzettel', 'custom-crm'); ?></span>
                <span style="font-size: 11px; font-weight: 500; color: #64748b; background: #f1f5f9; padding: 2px 8px; border-radius: 12px;">
                    Eintrag #<?php echo esc_html($entry_id); ?>
                </span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <span style="font-size: 11.5px; font-weight: 600; color: #475569;">
                    Status: <strong style="color: #0284c7;"><?php echo esc_html($status_label); ?></strong>
                </span>
                <button type="button" class="button button-small crm-history-btn" data-entry-id="<?php echo esc_attr($entry_id); ?>" title="<?php esc_attr_e('Status-Verlauf & Historie anzeigen', 'custom-crm'); ?>" style="display: inline-flex; align-items: center; gap: 3px; font-size: 11px;">
                    <span class="dashicons dashicons-backup" style="font-size: 14px; width: 14px; height: 14px;"></span>
                    <?php esc_html_e('Verlauf', 'custom-crm'); ?>
                </button>
                <button type="button" class="button button-small crm-snapshots-btn" data-entry-id="<?php echo esc_attr($entry_id); ?>" title="<?php esc_attr_e('Dokument- & Daten-Archiv anzeigen', 'custom-crm'); ?>" style="display: inline-flex; align-items: center; gap: 3px; font-size: 11px;">
                    <span class="dashicons dashicons-archive" style="font-size: 14px; width: 14px; height: 14px;"></span>
                    <?php esc_html_e('Archiv', 'custom-crm'); ?>
                </button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=custom-crm')); ?>" class="button button-small" style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; margin-left: 4px;">
                    <span class="dashicons dashicons-arrow-left-alt" style="font-size: 14px; width: 14px; height: 14px;"></span>
                    <?php esc_html_e('Zurück zur Tabelle', 'custom-crm'); ?>
                </a>
            </div>
        </div>

        <div class="crm-spickzettel-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px;">
            <!-- Spalte 1: WER? -->
            <div class="crm-spickzettel-col" style="display: flex; flex-direction: column; gap: 3px;">
                <div class="crm-spickzettel-col-title" style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; display: flex; align-items: center; justify-content: space-between; gap: 5px;">
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <span class="dashicons dashicons-admin-users" style="font-size: 14px; width: 14px; height: 14px; color: #0284c7;"></span>
                        <span><?php esc_html_e('1. Wer? (Kunde)', 'custom-crm'); ?></span>
                    </div>
                    <button type="button" class="button-link crm-quick-edit-btn" data-entry-id="<?php echo esc_attr($entry_id); ?>" data-course-id="<?php echo esc_attr($course_id); ?>" title="<?php esc_attr_e('Kundendaten bearbeiten', 'custom-crm'); ?>" style="font-size: 11px; text-decoration: none; color: #0284c7; display: inline-flex; align-items: center; gap: 2px;">
                        <span class="dashicons dashicons-edit" style="font-size: 12px; width: 12px; height: 12px;"></span> <?php esc_html_e('Bearbeiten', 'custom-crm'); ?>
                    </button>
                </div>
                <div class="crm-spickzettel-val-main" style="font-size: 14px; font-weight: 700; color: #1e293b;">
                    <?php echo esc_html($client_name); ?>
                </div>
                <?php if ($client_company): ?>
                    <div style="font-size: 11.5px; font-weight: 600; color: #475569;">
                        🏢 <?php echo esc_html($client_company); ?>
                    </div>
                <?php endif; ?>
                <div class="crm-spickzettel-val-sub" style="font-size: 11.5px; color: #64748b; line-height: 1.4;">
                    <?php if ($client_email): ?>
                        <div>✉️ <a href="mailto:<?php echo esc_attr($client_email); ?>" style="color: #0284c7; text-decoration: none;"><?php echo esc_html($client_email); ?></a></div>
                    <?php endif; ?>
                    <?php if ($client_phone): ?>
                        <div>📞 <?php echo esc_html($client_phone); ?></div>
                    <?php endif; ?>
                    <?php if ($client_street): ?>
                        <div>🏠 <?php echo esc_html($client_street); ?></div>
                    <?php endif; ?>
                    <?php if ($client_city): ?>
                        <div>📍 <?php echo esc_html($client_city); ?></div>
                    <?php endif; ?>
                    <?php if ($client_svr): ?>
                        <div style="margin-top: 2px;">🆔 SV-Nr: <strong><?php echo esc_html($client_svr); ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Spalte 2: WAS? -->
            <div class="crm-spickzettel-col" style="display: flex; flex-direction: column; gap: 3px;">
                <div class="crm-spickzettel-col-title" style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; display: flex; align-items: center; justify-content: space-between; gap: 5px;">
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <span class="dashicons <?php echo ($inquiry_type === 'freie_anfrage') ? 'dashicons-businessman' : 'dashicons-welcome-learn-more'; ?>" style="font-size: 14px; width: 14px; height: 14px; color: <?php echo ($inquiry_type === 'freie_anfrage') ? '#d97706' : '#7c3aed'; ?>;"></span>
                        <span><?php echo ($inquiry_type === 'freie_anfrage') ? esc_html__('2. Freie Geschäftsanfrage / Inhouse', 'custom-crm') : esc_html__('2. Was & Wann? (Kurs)', 'custom-crm'); ?></span>
                    </div>
                    <button type="button" class="button-link crm-link-course-btn" data-entry-id="<?php echo $entry_id; ?>" data-course-id="<?php echo $course_id; ?>" data-inquiry-type="<?php echo esc_attr($inquiry_type); ?>" data-custom-title="<?php echo esc_attr($custom_title); ?>" title="<?php esc_attr_e('Kurs wechseln oder Geschäftsanfrage anpassen', 'custom-crm'); ?>" style="font-size: 11px; text-decoration: none; color: #0284c7; display: inline-flex; align-items: center; gap: 2px;">
                        <span class="dashicons dashicons-edit" style="font-size: 12px; width: 12px; height: 12px;"></span> <?php esc_html_e('Ändern', 'custom-crm'); ?>
                    </button>
                </div>
                <div class="crm-spickzettel-val-main" style="font-size: 13.5px; font-weight: 700; color: #1e293b; line-height: 1.3;">
                    <?php echo esc_html($course_title); ?>
                </div>
                <div class="crm-spickzettel-val-sub" style="font-size: 11.5px; color: #475569; margin-top: 2px; line-height: 1.4;">
                    <?php if ($course_dates): ?>
                        <div>📅 <strong><?php echo esc_html($course_dates); ?></strong> <?php if ($course_le) echo '· ' . esc_html($course_le); ?></div>
                    <?php endif; ?>
                    <div>📍 <?php echo esc_html($course_location); ?></div>
                </div>
                <?php
                if ($course_id > 0) {
                    $dossier_certs = function_exists('crm_get_course_available_certifications') ? crm_get_course_available_certifications($course_id, $entry_id) : [];
                    if (!empty($dossier_certs)) {
                        echo function_exists('crm_render_course_cert_badges') ? crm_render_course_cert_badges($dossier_certs, 'dossier', $entry_id, $course_id) : '';
                    } else {
                        echo '<div class="crm-spickzettel-certs-box crm-spickzettel-certs-none" style="margin-top:6px; padding:6px 8px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:4px; font-size:11px; color:#64748b;"><span class="dashicons dashicons-welcome-learn-more" style="font-size:12px; width:12px; height:12px; line-height:12px;"></span> ' . esc_html__('X-SIEBEN Diplom (keine externe Zertifizierung)', 'custom-crm') . '</div>';
                    }
                }
                ?>
                <?php if (!$course_id && $inquiry_type !== 'freie_anfrage'): ?>
                    <div style="margin-top: 6px;">
                        <button type="button" class="button button-primary button-small crm-link-course-btn" data-entry-id="<?php echo $entry_id; ?>" style="font-size: 11px; padding: 2px 8px; height: 26px; display: inline-flex; align-items: center; gap: 4px;">
                            <span class="dashicons dashicons-admin-links"></span> <?php esc_html_e('Kurs / Geschäftsanfrage verknüpfen', 'custom-crm'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Spalte 3: WIEVIEL & FÖRDERUNG? -->
            <div class="crm-spickzettel-col" style="display: flex; flex-direction: column; gap: 3px;">
                <div class="crm-spickzettel-col-title" style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; display: flex; align-items: center; gap: 5px;">
                    <span class="dashicons dashicons-money-alt" style="font-size: 14px; width: 14px; height: 14px; color: #059669;"></span>
                    <?php esc_html_e('3. Wieviel & Förderung?', 'custom-crm'); ?>
                </div>
                <div class="crm-spickzettel-price-highlight" style="font-size: 15px; font-weight: 800; color: #047857;">
                    <?php echo esc_html(number_format($total_price, 2, ',', '.') . ' €'); ?>
                    <span style="font-size: 10px; font-weight: 500; color: #64748b; margin-left: 4px;">(USt-frei gem. § 6 Abs 1 Z 11a UStG)</span>
                </div>
                <div style="font-size: 11px; color: #475569; display: flex; align-items: center; gap: 6px; margin-top: 2px; flex-wrap: wrap;">
                    <span style="display: inline-block; padding: 2px 7px; border-radius: 4px; font-size: 10.5px; font-weight: 600; background: #e0f2fe; color: #0369a1;">
                        🏛️ <?php echo esc_html($foerd_label); ?>
                    </span>
                    <?php if ($cert_price > 0): ?>
                        <span style="font-size: 10.5px; color: #6b21a8; background: #f3e8ff; padding: 2px 6px; border-radius: 4px;">
                            + Zert: <?php echo esc_html(number_format($cert_price, 2, ',', '.') . ' €'); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($customer_message): ?>
            <div class="crm-spickzettel-message-box" style="margin-top: 14px; padding: 10px 14px; background: #fffbeb; border-left: 4px solid #f59e0b; border-radius: 6px; font-size: 12px; color: #334155;">
                <div style="font-weight: 700; color: #92400e; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                    <span class="dashicons dashicons-format-chat" style="font-size: 15px; width: 15px; height: 15px; color: #d97706;"></span>
                    <span><?php esc_html_e('Kundennachricht / Anfrage-Anmerkung:', 'custom-crm'); ?></span>
                </div>
                <div style="font-style: italic; line-height: 1.5; color: #451a03; white-space: pre-line;">
                    <?php echo esc_html($customer_message); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($entry_fields) && is_array($entry_fields)): ?>
            <div style="margin-top: 12px; display: flex; justify-content: flex-end;">
                <button type="button" class="button button-small" onclick="const w=document.getElementById('crm-all-fields-<?php echo esc_js($entry_id); ?>'); if(w.style.display==='none'){w.style.display='block'; this.innerHTML='<span class=\'dashicons dashicons-arrow-up-alt2\'></span> <?php echo esc_js(__('Alle Formularfelder ausblenden', 'custom-crm')); ?>';}else{w.style.display='none'; this.innerHTML='<span class=\'dashicons dashicons-list-view\'></span> <?php echo esc_js(__('Alle Formularfelder des Geschäftsvorfalls anzeigen', 'custom-crm')); ?>';}" style="font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">
                    <span class="dashicons dashicons-list-view" style="font-size: 13px; width: 13px; height: 13px;"></span>
                    <span><?php esc_html_e('Alle Formularfelder des Geschäftsvorfalls anzeigen', 'custom-crm'); ?></span>
                </button>
            </div>
            <div id="crm-all-fields-<?php echo esc_attr($entry_id); ?>" style="display: none; margin-top: 10px; border-top: 1px dashed #cbd5e1; padding-top: 10px;">
                <table class="widefat striped" style="font-size: 11.5px; border-radius: 4px; overflow: hidden;">
                    <tbody>
                        <?php foreach ($entry_fields as $f): 
                            if (empty($f['name']) && empty($f['value'])) continue;
                            $f_label = $f['name'] ?? ('Feld #' . ($f['id'] ?? ''));
                            $f_val   = is_array($f['value'] ?? '') ? implode(', ', $f['value']) : (string)($f['value'] ?? '');
                        ?>
                            <tr>
                                <th style="width: 28%; font-weight: 600; color: #475569; padding: 6px 10px;"><?php echo esc_html($f_label); ?></th>
                                <td style="color: #1e293b; padding: 6px 10px;"><?php echo esc_html($f_val); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Display a PDF preview with action buttons in WP Admin, or switch to the email mailer.
 */
function x_sieben_pdf_preview($pdf_url, $course_id, $entry_id = 0, $context = 'xsieben_angebot', $second_pdf_url = null)
{
  $foerd = ($entry_id && function_exists('crm_get_entry_foerderung')) ? crm_get_entry_foerderung($entry_id) : ['ams' => false, 'waff' => false];
  $is_foerd_active = !empty($foerd['ams']) || !empty($foerd['waff']);

  $offer_basis_url = '';
  $offer_zert_url  = '';
  $kb_url          = $second_pdf_url ?: '';
  $tb_url          = '';
  $diplom_url      = '';

  // Context mapping and initial active document
  if ($context === 'xsieben_diplom' || $context === 'diplom') {
    $active_doc = 'diplom';
    $diplom_url = $pdf_url;
  } elseif ($context === 'teilnahmebestaetigung' || $context === 'xsieben_teilnahmebestaetigung') {
    $active_doc = 'tb';
    $tb_url = $pdf_url;
  } elseif ($context === 'kurszeitenbestaetigung' || $context === 'xsieben_kurszeitenbestaetigung') {
    $active_doc = 'kb';
    $kb_url = $pdf_url;
  } elseif (is_array($pdf_url)) {
    $active_doc = 'angebot_basis';
    $offer_basis_url = $pdf_url['offer'] ?? ($pdf_url[0] ?? '');
    $kb_url = $pdf_url['kb'] ?? ($pdf_url[1] ?? $kb_url);
  } else {
    $active_doc = 'angebot_basis';
    $offer_basis_url = $pdf_url;
    if ($is_foerd_active && empty($kb_url) && function_exists('xsieben_kurszeitenbestaetigung_pdf')) {
      $kb_url = xsieben_kurszeitenbestaetigung_pdf($entry_id, $course_id, false);
    }
  }

  // Angebot 2 (Inkl. Zertifizierung): Nur prüfen/generieren wenn der Kurs eine echte Zertifizierungs-Option hat
  $has_cert_option = false;
  $cid_check = $course_id;
  if ((!$cid_check || $cid_check == 47) && class_exists('CRM_Model') && $entry_id) {
    $tmp_model = new CRM_Model(0, $entry_id);
    $cid_check = $tmp_model->post_id;
  }
  if ($entry_id && function_exists('crm_resolve_course_certification')) {
    $resolved_cert = crm_resolve_course_certification((int)$entry_id, (int)$cid_check);
    $has_cert_option = !empty($resolved_cert);
  }

  $save_dir = function_exists('crm_get_pdf_storage_dir') ? crm_get_pdf_storage_dir() : (get_template_directory() . '/angebote/');
  $storage_url = function_exists('crm_get_pdf_storage_url') ? crm_get_pdf_storage_url() : (get_template_directory_uri() . '/angebote/');

  if ($has_cert_option && empty($offer_zert_url) && $entry_id) {
    // 1. Suche nach vorhandener Angebot 2 PDF-Datei auf dem Server
    $matching_files = glob($save_dir . 'A_' . $entry_id . '-*_Angebot_2_*.pdf');
    if (!empty($matching_files)) {
      $latest_file = end($matching_files);
      $offer_zert_url = $storage_url . rawurlencode(basename($latest_file));
    } elseif ($cid_check && function_exists('xsieben_offer_pdf')) {
      // 2. Falls noch nicht generiert: Angebot 2 sofort autark vorrendern
      try {
        $offer_zert_url = xsieben_offer_pdf($entry_id, $cid_check, false, null, 'mit_zertifikat');
      } catch (\Throwable $e) {
        error_log('CRM Auto Angebot 2 Error: ' . $e->getMessage());
      }
    }
  }

  // 3. Suche nach bereits vorhandener Kurszeitenbestätigung (KB)
  if (empty($kb_url) && $entry_id) {
    $kb_token = function_exists('crm_generate_pdf_token') ? crm_generate_pdf_token($entry_id, 'kb') : '';
    $matching_kb = !empty($kb_token) ? glob($save_dir . 'Kurszeitenbestaetigung_*' . $kb_token . '*.pdf') : [];
    if (!empty($matching_kb)) {
      $kb_url = $storage_url . rawurlencode(basename(end($matching_kb)));
    }
  }

  // 4. Suche nach bereits vorhandener Teilnahmebestätigung (TB)
  if (empty($tb_url) && $entry_id) {
    $tb_token = function_exists('crm_generate_pdf_token') ? crm_generate_pdf_token($entry_id, 'tb') : '';
    $matching_tb = !empty($tb_token) ? glob($save_dir . 'Teilnahmebestaetigung_*' . $tb_token . '*.pdf') : [];
    if (!empty($matching_tb)) {
      $tb_url = $storage_url . rawurlencode(basename(end($matching_tb)));
    }
  }

  // 5. Suche nach bereits vorhandenem Diplom
  if (empty($diplom_url) && $entry_id) {
    $diplom_token = function_exists('crm_generate_pdf_token') ? crm_generate_pdf_token($entry_id, 'diplom') : '';
    $matching_diplom = !empty($diplom_token) ? glob($save_dir . 'Diplom_*' . $diplom_token . '*.pdf') : [];
    if (!empty($matching_diplom)) {
      $diplom_url = $storage_url . rawurlencode(basename(end($matching_diplom)));
    }
  }

  $cache_ts = time();
  $add_cache_buster = function ($url) use ($cache_ts) {
    if (empty($url)) return '';
    return $url . (strpos($url, '?') !== false ? '&' : '?') . 't=' . $cache_ts;
  };

  $active_url = '';
  $active_title = 'Angebot 1: Basis';
  if ($active_doc === 'diplom') {
    $active_url = $diplom_url;
    $active_title = 'Diplom';
  } elseif ($active_doc === 'tb') {
    $active_url = $tb_url;
    $active_title = 'Teilnahmebestätigung (TB)';
  } elseif ($active_doc === 'kb') {
    $active_url = $kb_url;
    $active_title = 'Kurszeiten (KB)';
  } else {
    $active_url = $offer_basis_url;
    $active_title = 'Angebot 1: Basis';
  }

  $active_embed_url = $add_cache_buster($active_url);
  $email_pdf_param  = !empty($kb_url) ? ($active_url . ',' . $kb_url) : $active_url;

  // Status-Badges für die 5 Dokumenten-Tabs (Birkenbihl: Erkennen statt Raten)
  $badge_basis  = !empty($offer_basis_url) ? '<span class="crm-tab-status-badge crm-status-ready">✓ Bereit</span>' : '<span class="crm-tab-status-badge crm-status-ondemand">⚡ Klick</span>';
  $badge_zert   = !empty($offer_zert_url)  ? '<span class="crm-tab-status-badge crm-status-ready">✓ Bereit</span>' : '<span class="crm-tab-status-badge crm-status-ondemand">⚡ Klick</span>';
  $badge_kb     = !empty($kb_url)          ? '<span class="crm-tab-status-badge crm-status-ready">✓ Bereit</span>' : '<span class="crm-tab-status-badge crm-status-ondemand">⚡ Klick</span>';
  $badge_tb     = !empty($tb_url)          ? '<span class="crm-tab-status-badge crm-status-ready">✓ Bereit</span>' : '<span class="crm-tab-status-badge crm-status-ondemand">⚡ Klick</span>';
  $badge_diplom = !empty($diplom_url)      ? '<span class="crm-tab-status-badge crm-status-ready">✓ Bereit</span>' : '<span class="crm-tab-status-badge crm-status-ondemand">⚡ Klick</span>';
?>
  <div id="x-sieben-container" class="wp-clearfix" style="display: flex; gap: 24px; align-items: flex-start; flex-wrap: wrap;">

    <?php echo crm_render_screen2_spickzettel((int)$entry_id, (int)$course_id); ?>

    <div id="x-sieben-pdf-preview" style="flex: 1 1 65%; min-width: 480px; position:relative;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
        <h2 class="title" style="margin:0; font-size:16px; display:flex; align-items:center; gap:8px;">
          <span class="dashicons dashicons-media-document" style="color:#007C90;"></span>
          <strong><?php esc_html_e('Dokument-Vorschau & Simulation', 'custom-crm'); ?></strong>
        </h2>
        <div class="crm-preview-doc-tabs" style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
          <button type="button" class="button crm-preview-switch-embed <?php echo ($active_doc === 'angebot_basis') ? 'active' : ''; ?>"
                  data-entry-id="<?php echo absint($entry_id); ?>"
                  data-course-id="<?php echo absint($course_id); ?>"
                  data-doc="angebot"
                  data-variant="basis"
                  data-label="Angebot 1: Basis"
                  data-url="<?php echo esc_url($add_cache_buster($offer_basis_url)); ?>"
                  style="<?php echo ($active_doc === 'angebot_basis') ? 'border-color:#7c3aed; color:#6d28d9; background:#faf5ff; font-weight:700;' : 'color:#334155;'; ?> font-size:12px; height:28px; line-height:26px; padding:0 10px;">
            <span>📑 <?php esc_html_e('Angebot 1: Basis', 'custom-crm'); ?></span>
            <?php echo $badge_basis; ?>
          </button>

          <?php if ($has_cert_option) : ?>
          <button type="button" class="button crm-preview-switch-embed <?php echo ($active_doc === 'angebot_zert') ? 'active' : ''; ?>"
                  data-entry-id="<?php echo absint($entry_id); ?>"
                  data-course-id="<?php echo absint($course_id); ?>"
                  data-doc="angebot"
                  data-variant="mit_zertifikat"
                  data-label="Angebot 2: Inkl. Zertifizierung"
                  data-url="<?php echo esc_url($add_cache_buster($offer_zert_url)); ?>"
                  style="<?php echo ($active_doc === 'angebot_zert') ? 'border-color:#7c3aed; color:#6d28d9; background:#faf5ff; font-weight:700;' : 'color:#334155;'; ?> font-size:12px; height:28px; line-height:26px; padding:0 10px;">
            <span>📑 <?php esc_html_e('Angebot 2: Inkl. Zert.', 'custom-crm'); ?></span>
            <?php echo $badge_zert; ?>
          </button>
          <?php endif; ?>

          <button type="button" class="button crm-preview-switch-embed <?php echo ($active_doc === 'kb') ? 'active' : ''; ?>"
                  data-entry-id="<?php echo absint($entry_id); ?>"
                  data-course-id="<?php echo absint($course_id); ?>"
                  data-doc="kb"
                  data-label="Kurszeitenbestätigung (KB)"
                  data-url="<?php echo esc_url($add_cache_buster($kb_url)); ?>"
                  style="<?php echo ($active_doc === 'kb') ? 'border-color:#0f766e; color:#0f766e; background:#f0fdfa; font-weight:700;' : 'color:#334155;'; ?> font-size:12px; height:28px; line-height:26px; padding:0 10px;">
            <span>📅 <?php esc_html_e('Kurszeiten (KB)', 'custom-crm'); ?></span>
            <?php echo $badge_kb; ?>
            <?php if ($is_foerd_active) : ?>
              <span style="font-size:10px; background:#ccfbf1; color:#0f766e; padding:1px 5px; border-radius:10px; margin-left:3px;">Förderung</span>
            <?php endif; ?>
          </button>

          <button type="button" class="button crm-preview-switch-embed <?php echo ($active_doc === 'tb') ? 'active' : ''; ?>"
                  data-entry-id="<?php echo absint($entry_id); ?>"
                  data-course-id="<?php echo absint($course_id); ?>"
                  data-doc="tb"
                  data-label="Teilnahmebestätigung (TB)"
                  data-url="<?php echo esc_url($add_cache_buster($tb_url)); ?>"
                  style="<?php echo ($active_doc === 'tb') ? 'border-color:#047857; color:#047857; background:#ecfdf5; font-weight:700;' : 'color:#334155;'; ?> font-size:12px; height:28px; line-height:26px; padding:0 10px;">
            <span>📜 <?php esc_html_e('Teilnahmebestätigung (TB)', 'custom-crm'); ?></span>
            <?php echo $badge_tb; ?>
          </button>

          <button type="button" class="button crm-preview-switch-embed <?php echo ($active_doc === 'diplom') ? 'active' : ''; ?>"
                  data-entry-id="<?php echo absint($entry_id); ?>"
                  data-course-id="<?php echo absint($course_id); ?>"
                  data-doc="diplom"
                  data-label="Diplom & Abschluss"
                  data-url="<?php echo esc_url($add_cache_buster($diplom_url)); ?>"
                  style="<?php echo ($active_doc === 'diplom') ? 'border-color:#b45309; color:#b45309; background:#fffbeb; font-weight:700;' : 'color:#334155;'; ?> font-size:12px; height:28px; line-height:26px; padding:0 10px;">
            <span>🎓 <?php esc_html_e('Diplom', 'custom-crm'); ?></span>
            <?php echo $badge_diplom; ?>
          </button>
        </div>
      </div>

      <div id="crm-embed-loading-overlay" style="display:none; position:absolute; top:48px; left:0; width:100%; height:680px; background:rgba(255,255,255,0.9); backdrop-filter:blur(3px); z-index:10; border-radius:8px; border:1px solid #cbd5e1; align-items:center; justify-content:center; flex-direction:column; gap:12px;">
        <span class="dashicons dashicons-update spin" style="font-size:38px; width:38px; height:38px; color:#6d28d9;"></span>
        <strong style="font-size:15px; color:#1e293b;"><?php esc_html_e('Dokument wird generiert & simuliert...', 'custom-crm'); ?></strong>
        <span style="font-size:12.5px; color:#64748b;"><?php esc_html_e('PDF wird erstellt und direkt im großen Arbeitsbereich angezeigt.', 'custom-crm'); ?></span>
      </div>

      <embed id="crm-active-pdf-embed" src="<?php echo esc_url($active_embed_url); ?>" type="application/pdf" width="100%" height="680px" style="border: 1px solid #cbd5e1; border-radius: 8px; min-height: 650px; box-shadow: 0 4px 14px rgba(0,0,0,0.06); background:#f8fafc;" />
    </div>

    <div id="x-sieben-button-row" style="flex: 0 0 310px; min-width: 280px; display:flex; flex-direction:column; gap:12px; margin-top:40px;">

      <ul style="list-style: none; margin: 0; padding: 0;">
        <li style="margin-bottom: 8px;">
          <a id="crm-preview-download-btn" href="<?php echo esc_url($active_embed_url); ?>" download class="button button-primary" style="display:flex; align-items:center; gap:6px; font-weight:600; width:100%; justify-content:center; height:34px;">
            <span class="dashicons dashicons-download"></span>
            <span class="crm-btn-text"><?php echo esc_html($active_title); ?> <?php esc_html_e('herunterladen', 'custom-crm'); ?></span>
          </a>
        </li>
        <li style="margin-bottom: 12px;">
          <a id="crm-preview-external-btn" href="<?php echo esc_url($active_embed_url); ?>" target="_blank" class="button" style="display:flex; align-items:center; gap:6px; width:100%; justify-content:center; height:32px;">
            <span class="dashicons dashicons-external"></span>
            <span><?php esc_html_e('In neuem Tab ansehen', 'custom-crm'); ?></span>
          </a>
        </li>

        <li style="margin-bottom: 8px;">
          <a href="#" class="x-sieben-email-btn button"
            data-pdf="<?php echo esc_attr($email_pdf_param); ?>"
            data-course="<?php echo absint($course_id); ?>"
            data-entry="<?php echo absint($entry_id); ?>"
            data-context="<?php echo esc_attr($context); ?>"
            style="display:flex; align-items:center; gap:6px; width:100%; justify-content:center; height:34px; background:#faf5ff; color:#6d28d9; border-color:#c4b5fd; font-weight:600;">
            <span class="dashicons dashicons-email"></span>
            <span><?php esc_html_e('E-Mail bearbeiten & senden', 'custom-crm'); ?></span>
          </a>
        </li>
        <li style="margin-bottom: 12px;">
          <a href="#" class="x-sieben-email-btn button"
            data-pdf="<?php echo esc_attr($email_pdf_param); ?>"
            data-course="<?php echo absint($course_id); ?>"
            data-entry="<?php echo absint($entry_id); ?>"
            data-context="<?php echo esc_attr($context); ?>"
            data-focus-test="1"
            style="display:flex; align-items:center; gap:6px; width:100%; justify-content:center; height:32px; color:#0284c7; font-weight:600;">
            <span class="dashicons dashicons-email-alt" style="color:#0284c7;"></span>
            <span>🧪 <?php esc_html_e('Test-Mail vorbereiten', 'custom-crm'); ?></span>
          </a>
        </li>
        <?php if ($entry_id) : ?>
          <li style="margin-bottom: 6px;">
            <button type="button" class="button crm-quick-edit-btn" data-entry-id="<?php echo absint($entry_id); ?>" data-course-id="<?php echo absint($course_id); ?>" style="display:flex; align-items:center; gap:6px; width:100%; justify-content:center; font-size:12px;">
              <span class="dashicons dashicons-edit"></span>
              <span><?php esc_html_e('Kundendaten bearbeiten', 'custom-crm'); ?></span>
            </button>
          </li>
        <?php endif; ?>
        <?php if ($course_id) : ?>
          <li style="margin-bottom: 10px;">
            <a href="<?php echo esc_url(get_edit_post_link($course_id)); ?>" class="button" style="display:flex; align-items:center; gap:6px; width:100%; justify-content:center; font-size:12px;">
              <span class="dashicons dashicons-admin-page"></span>
              <span><?php esc_html_e('Kurs bearbeiten', 'custom-crm'); ?></span>
            </a>
          </li>
        <?php endif; ?>
      </ul>

      <?php 
        $current_success = 'erfolgreich';
        if ($entry_id && class_exists('CRM_Model')) {
          $temp_course = new CRM_Model($course_id, $entry_id);
          $current_success = $temp_course->get_diplom_success() ?: 'erfolgreich';
        }
        $current_success_clean = mb_strtolower(trim($current_success), 'UTF-8');
        $is_ausgezeichnet = strpos($current_success_clean, 'ausgezeichnet') !== false;
        $is_sehr_gut      = strpos($current_success_clean, 'sehr gut') !== false;
        $is_gut           = !$is_sehr_gut && strpos($current_success_clean, 'gut') !== false;
        $is_erfolgreich   = !$is_ausgezeichnet && !$is_sehr_gut && !$is_gut;
      ?>
        <div id="crm-diplom-success-container" class="crm-diplom-success-box" style="<?php echo ($active_doc === 'diplom' || $context === 'xsieben_diplom') ? '' : 'display:none;'; ?> background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:14px; margin-top:5px; margin-bottom:15px; box-shadow:0 1px 3px rgba(0,0,0,0.06);">
          <div style="font-size:13px; font-weight:700; color:#0f172a; margin-bottom:6px; display:flex; align-items:center; gap:6px;">
            <span class="dashicons dashicons-awards" style="color:#d97706; font-size:18px;"></span>
            Abschluss-Erfolg auswählen
          </div>
          <p style="font-size:11.5px; color:#64748b; margin:0 0 10px 0; line-height:1.35;">
            Prüfungserfolg für das Diplom festlegen:
          </p>
          <div style="display:flex; flex-direction:column; gap:6px; margin-bottom:12px;">
            <label style="display:flex; align-items:center; gap:8px; font-size:12.5px; cursor:pointer; padding:3px 0;">
              <input type="radio" name="crm_diplom_success_choice" class="crm-diplom-success-radio" value="erfolgreich" <?php checked($is_erfolgreich); ?> data-entry="<?php echo absint($entry_id); ?>" data-course="<?php echo absint($course_id); ?>">
              <span><strong>Erfolgreich</strong> (abgeschlossen)</span>
            </label>
            <label style="display:flex; align-items:center; gap:8px; font-size:12.5px; cursor:pointer; padding:3px 0;">
              <input type="radio" name="crm_diplom_success_choice" class="crm-diplom-success-radio" value="mit gutem erfolg" <?php checked($is_gut); ?> data-entry="<?php echo absint($entry_id); ?>" data-course="<?php echo absint($course_id); ?>">
              <span><strong>Mit gutem Erfolg</strong></span>
            </label>
            <label style="display:flex; align-items:center; gap:8px; font-size:12.5px; cursor:pointer; padding:3px 0;">
              <input type="radio" name="crm_diplom_success_choice" class="crm-diplom-success-radio" value="mit ausgezeichnetem erfolg" <?php checked($is_ausgezeichnet); ?> data-entry="<?php echo absint($entry_id); ?>" data-course="<?php echo absint($course_id); ?>">
              <span><strong>Mit ausgezeichnetem Erfolg</strong></span>
            </label>
          </div>
          <button type="button" class="button button-primary crm-btn-update-diplom-success" 
                  data-entry="<?php echo absint($entry_id); ?>" 
                  data-course="<?php echo absint($course_id); ?>" 
                  style="width:100%; justify-content:center; display:flex; align-items:center; gap:5px; font-weight:600;">
            <span class="dashicons dashicons-update"></span>
            Diplom aktualisieren
          </button>
          <div class="crm-diplom-success-feedback" style="display:none; font-size:11.5px; color:#16a34a; font-weight:600; margin-top:8px; text-align:center;"></div>
        </div>
      <?php
      // PDF-Abschnitte Manager (Drag & Drop) in der Eintrags-Vorschau
      if (function_exists('crm_render_pdf_sections_manager')) : ?>
        <div class="crm-pdf-sections-preview-box crm-dual-sections-preview-box"
             data-context="<?php echo esc_attr($context); ?>"
             data-course="<?php echo absint($course_id); ?>"
             data-entry="<?php echo absint($entry_id); ?>"
             style="background:#ffffff; border:1px solid #cbd5e1; border-radius:8px; padding:14px; margin-top:12px; margin-bottom:15px; box-shadow:0 1px 3px rgba(0,0,0,0.06);">
          <div style="font-size:13px; font-weight:700; color:#0f172a; margin-bottom:8px; display:flex; align-items:center; gap:6px;">
            <span class="dashicons dashicons-menu" style="color:#007C90; font-size:18px;"></span>
            <span>Abschnitte anordnen (Drag & Drop)</span>
          </div>
          
          <div class="crm-dual-sec-tabs" style="display:flex; gap:6px; margin-bottom:10px; flex-wrap:wrap;">
            <button type="button" class="button crm-dual-sec-tab-btn <?php echo in_array($active_doc, ['angebot_basis', 'angebot_zert', 'angebot']) ? 'active' : ''; ?>" data-target="crm-dual-sec-angebot" style="<?php echo in_array($active_doc, ['angebot_basis', 'angebot_zert', 'angebot']) ? 'border-color:#7c3aed; color:#6d28d9; font-weight:600;' : ''; ?> font-size:11px; height:24px; line-height:22px; padding:0 8px;">
              Angebot
            </button>
            <button type="button" class="button crm-dual-sec-tab-btn <?php echo ($active_doc === 'kb') ? 'active' : ''; ?>" data-target="crm-dual-sec-kb" style="<?php echo ($active_doc === 'kb') ? 'border-color:#0f766e; color:#0f766e; font-weight:600;' : 'color:#0f766e;'; ?> font-size:11px; height:24px; line-height:22px; padding:0 8px;">
              Kurszeiten (KB)
            </button>
            <button type="button" class="button crm-dual-sec-tab-btn <?php echo ($active_doc === 'tb') ? 'active' : ''; ?>" data-target="crm-dual-sec-tb" style="<?php echo ($active_doc === 'tb') ? 'border-color:#047857; color:#047857; font-weight:600;' : 'color:#047857;'; ?> font-size:11px; height:24px; line-height:22px; padding:0 8px;">
              Teilnahme (TB)
            </button>
            <button type="button" class="button crm-dual-sec-tab-btn <?php echo ($active_doc === 'diplom') ? 'active' : ''; ?>" data-target="crm-dual-sec-diplom" style="<?php echo ($active_doc === 'diplom') ? 'border-color:#b45309; color:#b45309; font-weight:600;' : 'color:#b45309;'; ?> font-size:11px; height:24px; line-height:22px; padding:0 8px;">
              Diplom
            </button>
          </div>

          <div id="crm-dual-sec-angebot" class="crm-dual-sec-pane" style="<?php echo in_array($active_doc, ['angebot_basis', 'angebot_zert', 'angebot']) ? '' : 'display:none;'; ?>">
            <?php crm_render_pdf_sections_manager('angebot', $entry_id, true); ?>
          </div>
          <div id="crm-dual-sec-kb" class="crm-dual-sec-pane" style="<?php echo ($active_doc === 'kb') ? '' : 'display:none;'; ?>">
            <?php crm_render_pdf_sections_manager('kb', $entry_id, true); ?>
          </div>
          <div id="crm-dual-sec-tb" class="crm-dual-sec-pane" style="<?php echo ($active_doc === 'tb') ? '' : 'display:none;'; ?>">
            <?php crm_render_pdf_sections_manager('tb', $entry_id, true); ?>
          </div>
          <div id="crm-dual-sec-diplom" class="crm-dual-sec-pane" style="<?php echo ($active_doc === 'diplom') ? '' : 'display:none;'; ?>">
            <?php crm_render_pdf_sections_manager('diplom', $entry_id, true); ?>
          </div>
        </div>
      <?php endif; ?>

      <div style="margin-top: 10px; font-style: italic; font-size: 0.9em;">
        Kundendaten bearbeiten, um Zertifizierungen auszuwählen
      </div>

    </div>

  </div>
<?php
}

/**
 * Generates an email editor interface with a downloadable PDF attachment.
 * The email body changes based on the context provided.
 *
 * @param string $pdf_url The URL of the PDF to attach.
 * @param int $course_id The ID of the associated course.
 * @param int $entry_id The ID of the associated WPForms entry.
 * @param string $context The context that determines the email body ('anfrage', 'bestellung', etc.).
 */
function x_sieben_pdf_mailer($pdf_url, $course_id, $entry_id, $context)
{
  // Initialize data model
  $data = new CRM_Model($course_id, $entry_id);

  // Set default recipient and subject
  $recipient = !empty($data->email) ? $data->email : '';
  $subject = '';
  $body = '';

  // Map context to document type
  $context_doc_map = [
    'anmeldung'                      => 'anmeldung',
    'xsieben_angebot'                => 'angebot',
    'xsieben_diplom'                 => 'diplom',
    'teilnahmebestaetigung'          => 'tb',
    'kurszeitenbestaetigung'         => 'kb',
    'xsieben_angebot_kurszeiten'     => 'angebot_kb',
    'xsieben_angebot_und_kurszeiten' => 'angebot_kb',
    'invoice'                        => 'invoice',
    'honorarnote'                    => 'invoice',
  ];
  $doc_type = $context_doc_map[$context] ?? 'angebot';

  // Load customizable subject template from CRM settings
  require_once dirname(__DIR__) . '/helpers/crm-email-sections.php';
  if (function_exists('crm_get_email_subject_template')) {
    $subject = crm_get_email_subject_template($doc_type);
  }

  // Fallback for subject if template not defined or empty
  if (empty($subject)) {
    switch ($context) {
      case 'anmeldung':
        $subject = !empty($data->title) ? 'Ihre Anfrage für: ' . $data->title : 'Anfrage für Ihren Kurs';
        break;
      case 'xsieben_angebot':
        $subject = !empty($data->title) ? 'Angebot für: ' . $data->title : 'Angebot X SIEBEN';
        break;
      case 'xsieben_diplom':
        $subject = !empty($data->title) ? 'Diplom ' . $data->title : 'Diplom X SIEBEN';
        break;
      case 'teilnahmebestaetigung':
        $subject = !empty($data->title) ? 'Ihre Teilnahmebestätigung für: ' . $data->title : 'Teilnahmebestätigung';
        break;
      case 'xsieben_angebot_kurszeiten':
      case 'xsieben_angebot_und_kurszeiten':
      case 'kurszeitenbestaetigung':
        $subject = !empty($data->title) ? 'Angebot & Kurszeiten für: ' . $data->title : 'Angebot & Kurszeiten X SIEBEN';
        break;
      default:
        $subject = !empty($data->title) ? 'Anfrage zu: ' . $data->title : 'Ihre Anfrage';
        break;
    }
  }

  // Body switch
  switch ($context) {
    case 'anmeldung':
      $body = '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt;"><tr><td>' . $data->anmeldung_email . '</td></tr></table>';
      break;

    case 'xsieben_angebot':
    case 'xsieben_angebot_kurszeiten':
    case 'xsieben_angebot_und_kurszeiten':
    case 'kurszeitenbestaetigung':
      require_once dirname(__DIR__) . '/helpers/crm-email-sections.php';
      if (function_exists('crm_build_standard_offer_email')) {
        $std_offer = crm_build_standard_offer_email($entry_id, $course_id, ['context' => $context]);
        $body = $std_offer['body'];
        if (empty($subject)) {
          $subject = $std_offer['subject'];
        }
      } else {
        $body = '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt;"><tr><td>' . $data->angebot_email . '</td></tr></table>';
      }
      break;

    case 'xsieben_diplom':
      $body = '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt;"><tr><td>' . $data->diplom_email . '</td></tr></table>';
      break;

    case 'teilnahmebestaetigung':
      $body = '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt;"><tr><td>' . $data->teilnahmebestaetigung_email . '</td></tr></table>';
      break;

    default:
      $body = '<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt;"><tr><td>'
            . $data->salutation . ' ' . $data->titel . ' ' . $data->vorname . ' ' . $data->nachname . ',<br><br>'
            . 'vielen Dank für Ihre Nachricht. Wir werden uns in Kürze bei Ihnen melden.<br><br>'
            . $data->signatur_email
            . '</td></tr></table>'
            . $data->email_footer;
      break;
  }

  // Check if Friedelin AI draft exists for this entry
  $friedelin_draft = function_exists('crm_friedelin_get_entry_draft') ? crm_friedelin_get_entry_draft($entry_id) : null;
  if ($friedelin_draft) {
    if (!empty($friedelin_draft['subject'])) {
      $subject = $friedelin_draft['subject'];
    }
    if (!empty($friedelin_draft['body'])) {
      $body = $friedelin_draft['body'];
    }
    if (!empty($friedelin_draft['all_pdf_param'])) {
      $pdf_url = $friedelin_draft['all_pdf_param'];
    }
  }

  // Ensure subject and body are parsed with all course and placeholder data
  if (method_exists($data, 'parse_string_with_data')) {
    $subject = $data->parse_string_with_data($subject);
    $body    = $data->parse_string_with_data($body);
  }

  // Double-ensure NO internal AI notices/Friedelin tags enter the email editor
  if (function_exists('crm_strip_internal_ai_notices')) {
    $body = crm_strip_internal_ai_notices($body);
  }

  // Normalisierung und Anna-Brauer-Signaturschutz
  if (function_exists('crm_prepare_email_html_for_sending')) {
    $body = crm_prepare_email_html_for_sending($body);
  }

  // Default test email from settings or current WP user
  $current_user = wp_get_current_user();
  $default_test_email = get_option('crm_test_email');
  if (empty($default_test_email) && $current_user && !empty($current_user->user_email)) {
    $default_test_email = $current_user->user_email;
  }

  // Start container
  echo '<div id="x-sieben-container" class="wp-clearfix" style="display:flex; gap:20px;">';

  // Left: Email form & Editor
  echo '<div id="x-sieben-email-editor" style="flex:2;">';

  // Friedelin Banner
  if ($friedelin_draft) {
    echo '<div class="crm-friedelin-banner" style="background:#f5f3ff; border:2px solid #8b5cf6; border-radius:8px; padding:12px 16px; margin-bottom:16px; display:flex; align-items:center; justify-content:space-between; gap:12px; box-shadow:0 1px 3px rgba(124,58,237,0.1);">';
    echo '  <div style="display:flex; align-items:center; gap:10px;">';
    echo '    <span class="dashicons dashicons-email-alt2" style="color:#7c3aed; font-size:24px; width:24px; height:24px;"></span>';
    echo '    <div>';
    echo '      <strong style="color:#5b21b6; font-size:13px;">📦 Für den Versand vorbereitet</strong>';
    echo '      <p style="margin:2px 0 0 0; font-size:12px; color:#6d28d9;">' . esc_html($friedelin_draft['ai_summary']) . ' &bull; <em>Bitte prüfen & manuell freigeben.</em></p>';
    echo '    </div>';
    echo '  </div>';
    echo '  <span style="font-size:11px; background:#7c3aed; color:#fff; padding:4px 10px; border-radius:12px; font-weight:700; white-space:nowrap;">Freigabe erforderlich</span>';
    echo '</div>';
  }

  // Foerderung (AMS / WAFF) Badges Bar
  $foerderung_data = function_exists('crm_get_entry_foerderung') ? crm_get_entry_foerderung($entry_id) : ['ams' => false, 'waff' => false];
  $badges_html = function_exists('crm_render_foerderung_badges') ? crm_render_foerderung_badges($entry_id, $course_id, $foerderung_data) : '';

  echo '<div class="crm-mailer-foerderung-bar" style="display:flex; justify-content:space-between; align-items:center; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:8px 12px; margin-bottom:14px;">';
  echo '  <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:#475569; font-weight:600;">';
  echo '    <span class="dashicons dashicons-businessman" style="color:#0284c7; font-size:16px;"></span>';
  echo '    <span>' . esc_html__('Förderstelle / Kundentyp:', 'custom-crm') . '</span>';
  echo '  </div>';
  echo '  <div>' . $badges_html . '</div>';
  echo '</div>';

  echo '<p><label for="x_sieben_recipient">Empfänger:</label><br><input type="email" id="x_sieben_recipient" class="regular-text" value="' . esc_attr($recipient) . '"></p>';


  // --- CRM Subject Box (Editable with Chips) ---
  echo '<div class="crm-mailer-subject-box" style="margin-bottom:16px; background:#ffffff; border:1px solid #cbd5e1; border-left:4px solid #0284c7; border-radius:6px; padding:12px 14px; box-shadow:0 1px 3px rgba(0,0,0,0.03);">';
  echo '  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; flex-wrap:wrap; gap:6px;">';
  echo '    <label for="x_sieben_subject" style="font-weight:700; font-size:13px; color:#0f172a; display:flex; align-items:center; gap:6px;">';
  echo '      <span class="dashicons dashicons-email" style="color:#0284c7; font-size:18px;"></span>';
  echo '      <span>' . esc_html__('Betreffzeile:', 'custom-crm') . '</span>';
  echo '    </label>';
  echo '    <span style="font-size:11px; color:#64748b; font-style:italic;">' . esc_html__('Individuell bearbeitbar vor Versand', 'custom-crm') . '</span>';
  echo '  </div>';
  echo '  <input type="text" id="x_sieben_subject" name="x_sieben_subject" class="regular-text" style="width:100%; height:34px; font-size:13px; font-weight:600; color:#1e293b;" value="' . esc_attr($subject) . '" placeholder="' . esc_attr__('Betreffzeile eingeben...', 'custom-crm') . '">';
  echo '  <div class="crm-subject-chips-bar" style="margin-top:7px; display:flex; gap:5px; flex-wrap:wrap; align-items:center;">';
  echo '    <span style="font-size:11px; color:#64748b; font-weight:600;">' . esc_html__('Platzhalter einfügen:', 'custom-crm') . '</span>';
  echo '    <button type="button" class="button button-small crm-insert-mailer-chip" data-chip="{kurstitel}">{kurstitel}</button>';
  echo '    <button type="button" class="button button-small crm-insert-mailer-chip" data-chip="{vorname}">{vorname}</button>';
  echo '    <button type="button" class="button button-small crm-insert-mailer-chip" data-chip="{nachname}">{nachname}</button>';
  echo '    <button type="button" class="button button-small crm-insert-mailer-chip" data-chip="{titel}">{titel}</button>';
  echo '    <button type="button" class="button button-small crm-insert-mailer-chip" data-chip="{startdatum}">{startdatum}</button>';
  echo '    <button type="button" class="button button-small crm-insert-mailer-chip" data-chip="{kurs_id}">#{kurs_id}</button>';
  echo '  </div>';
  echo '</div>';

  // --- CRM Test-Versand & Audit-Trail Control Box ---
  echo '<div id="crm-test-mail-box" style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:12px 16px; margin:15px 0 20px 0;">';
  echo '  <div style="font-weight:700; color:#0369a1; font-size:13px; margin-bottom:8px; display:flex; align-items:center; gap:6px;">';
  echo '    <span class="dashicons dashicons-email-alt" style="color:#0284c7;"></span>';
  echo '    <span>' . esc_html__('E-Mail Versand-Optionen & Test-Modus', 'custom-crm') . '</span>';
  echo '  </div>';

  echo '  <div style="display:flex; flex-wrap:wrap; gap:16px; align-items:flex-start;">';
  echo '    <div style="flex:1; min-width:260px;">';
  echo '      <label for="x_sieben_test_recipient" style="font-weight:600; font-size:12px; color:#334155; display:block; margin-bottom:4px;">' . esc_html__('Test-Empfänger (E-Mail):', 'custom-crm') . '</label>';
  echo '      <input type="email" id="x_sieben_test_recipient" class="regular-text" style="width:100%; height:32px; font-size:13px;" value="' . esc_attr($default_test_email) . '" placeholder="ihre-adresse@domain.at">';
  echo '    </div>';

  echo '    <div style="flex:1; min-width:280px; font-size:12px; color:#334155;">';
  echo '      <label style="display:block; margin-bottom:5px; font-weight:600;">' . esc_html__('Versand-Option:', 'custom-crm') . '</label>';
  echo '      <label style="margin-right:15px; cursor:pointer; display:inline-flex; align-items:center; gap:4px;">';
  echo '        <input type="radio" name="x_sieben_test_mode_type" value="only_test" checked="checked"> ' . esc_html__('Nur an Test-Empfänger', 'custom-crm');
  echo '      </label>';
  echo '      <label style="cursor:pointer; display:inline-flex; align-items:center; gap:4px;">';
  echo '        <input type="radio" name="x_sieben_test_mode_type" value="both"> ' . esc_html__('An Test & Kunde gleichzeitig', 'custom-crm');
  echo '      </label>';
  echo '    </div>';
  echo '  </div>';

  echo '  <div style="margin-top:10px; font-size:12px; color:#64748b; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">';
  echo '    <label style="cursor:pointer; display:inline-flex; align-items:center; gap:4px;">';
  echo '      <input type="checkbox" id="x_sieben_test_prefix_subject" value="1" checked="checked"> ' . esc_html__('[TEST] im Betreff voranstellen', 'custom-crm');
  echo '    </label>';
  echo '    <span style="font-size:11px; color:#0369a1; font-style:italic;">' . esc_html__('Tipp: Bei "Nur an Test-Empfänger" bleibt der Kundenstatus erhalten (wird im Verlauf protokolliert).', 'custom-crm') . '</span>';
  echo '  </div>';
  echo '</div>';

  // --- CRM PDF-Anhänge Auswahl & Beilagen-Manager ---
  require_once dirname(__DIR__) . '/helpers/crm-email-sections.php';
  if (function_exists('crm_render_email_attachments_selector')) {
    crm_render_email_attachments_selector($pdf_url, $course_id, $entry_id, $context);
  }

  // Hier der Editor mit Tabs (Visuell / Text)
  echo '<p><label for="x_sieben_body">Nachricht:</label></p>';
  echo '<div id="x-sieben-mail-editor" class="wp-editor-wrap tmce-active">';
  echo '  <div class="wp-editor-tabs">';
  echo '    <button type="button" id="x_sieben_body-tmce" class="wp-switch-editor switch-tmce">Visuell</button>';
  echo '    <button type="button" id="x_sieben_body-html" class="wp-switch-editor switch-html">Text</button>';
  echo '  </div>';
  echo '  <div class="wp-editor-container">';
  $editor_initial_body = function_exists('crm_prepare_email_html_for_sending') ? crm_prepare_email_html_for_sending($body) : $body;
  echo '    <textarea id="x_sieben_body" name="x_sieben_body" rows="10" class="wp-editor-area">' . esc_textarea($editor_initial_body) . '</textarea>';
  echo '  </div>';
  echo '</div>';

  echo '<input type="hidden" id="x_sieben_pdf_url" value="' . esc_attr($pdf_url) . '">';
  echo '<input type="hidden" id="x_sieben_course_id" value="' . esc_attr($course_id) . '">';
  echo '<input type="hidden" id="x_sieben_entry_id" value="' . esc_attr($entry_id) . '">';
  echo '<input type="hidden" id="x_sieben_context" value="' . esc_attr($context) . '">';
  echo '<p style="text-align:right; display:flex; justify-content:flex-end; align-items:center; gap:10px; margin-top:15px;">';
  echo '  <button type="button" id="send-test-mail-btn-bottom" class="button" style="background:#0284c7; color:#fff; border-color:#0284c7; font-weight:600;">🧪 ' . esc_html__('Test-E-Mail senden', 'custom-crm') . '</button>';
  echo '  <button type="button" id="send-mail-btn" class="button button-primary" style="font-weight:600;">' . esc_html__('E-Mail an Kunden senden', 'custom-crm') . '</button>';
  echo '</p>';
  echo '</div>'; // end email editor

  // Right: Action buttons
  echo '<div id="x-sieben-button-row" style="flex:1; display:flex; flex-direction:column; gap:10px; align-items:flex-end; margin-top:70px;">';
  echo '<ul style="list-style:none; margin:0; padding:0; width:100%;">';
  echo '  <li id="crm-sidebar-attachments-wrapper">';
  echo '    <div style="font-weight:600; font-size:12px; color:#475569; margin-bottom:6px; display:flex; align-items:center; gap:4px;"><span class="dashicons dashicons-paperclip" style="font-size:15px; color:#0f766e;"></span> ' . esc_html__('Aktive PDF-Anhänge:', 'custom-crm') . '</div>';
  echo '    <ul id="crm-sidebar-attachments-list" style="list-style:none; margin:0 0 10px 0; padding:0; display:flex; flex-direction:column; gap:6px;">';
  $pdf_url_list = array_filter(array_map('trim', explode(',', $pdf_url)));
  if (!empty($pdf_url_list)) {
    foreach ($pdf_url_list as $single_url) {
      $filename = basename(parse_url($single_url, PHP_URL_PATH));
      $is_kb = (strpos($filename, 'KB_') === 0 || stristr($filename, 'Kurszeiten') !== false);
      $is_agb = (strpos($filename, 'AGB_') === 0);
      $doc_name = $is_agb ? __('AGB 2025 herunterladen', 'custom-crm') : ($is_kb ? __('Kurszeiten (KB) herunterladen', 'custom-crm') : __('Angebot herunterladen', 'custom-crm'));
      echo '      <li><a href="' . esc_url($single_url) . '" download class="button crm-sidebar-download-btn" data-url="' . esc_url($single_url) . '" style="display:flex; align-items:center; gap:5px; font-size:12px; width:100%; justify-content:center;"><span class="dashicons dashicons-download"></span> ' . esc_html($doc_name) . '</a></li>';
    }
  } else {
    echo '      <li class="crm-sidebar-no-att" style="font-size:11.5px; color:#94a3b8; font-style:italic;">Keine Anhänge ausgewählt</li>';
  }
  echo '    </ul>';
  echo '  </li>';
  if ($entry_id) {
    echo '  <li style="margin-bottom:10px;"><button type="button" class="button crm-quick-edit-btn" data-entry-id="' . absint($entry_id) . '" data-course-id="' . absint($course_id) . '" style="display:flex; align-items:center; gap:5px; font-size:12px; width:100%; justify-content:center;"><span class="dashicons dashicons-edit"></span> ' . esc_html__('Kundendaten bearbeiten', 'custom-crm') . '</button></li>';
  }
  if ($course_id) {
    echo '  <li style="margin-bottom:10px;"><a href="' . esc_url(get_edit_post_link($course_id)) . '" class="button" style="display:flex; align-items:center; gap:5px; font-size:12px; width:100%; justify-content:center;"><span class="dashicons dashicons-admin-page"></span> Kurs bearbeiten</a></li>';
  }
  echo '</ul>';
  echo '</div>'; // end buttons
  echo '</div>'; // end container
}


/**
 * AJAX loading mailer
 */
add_action('wp_ajax_x_sieben_load_mailer', function () {
  if (!current_user_can('manage_options')) {
    wp_send_json_error(['message' => __('Nicht autorisierter Zugriff.', 'custom-crm')], 403);
  }
  check_ajax_referer('x_sieben_mailer_nonce', 'security');

  $pdf_url_raw = isset($_POST['pdf_url']) ? wp_unslash($_POST['pdf_url']) : '';
  $pdf_urls    = array_filter(array_map('esc_url_raw', explode(',', $pdf_url_raw)));
  $pdf_url     = implode(',', $pdf_urls);
  $course_id   = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
  $entry_id    = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
  $context     = isset($_POST['context']) ? sanitize_key($_POST['context']) : 'default';

  if (!$pdf_url) {
    wp_send_json_error('PDF URL missing');
  }

  // Pass the context to the mailer function
  x_sieben_pdf_mailer($pdf_url, $course_id, $entry_id, $context);
  wp_die();
});

/**
 * AJAX handler for mailer (Supports Live Send & Test Send modes)
 */
add_action('wp_ajax_x_sieben_send_mail', function () {
  if (!current_user_can('manage_options')) {
    wp_send_json_error(['message' => __('Nicht autorisierter Zugriff.', 'custom-crm')], 403);
  }
  $nonce = $_POST['security'] ?? ($_POST['nonce'] ?? '');
  if (!wp_verify_nonce($nonce, 'x_sieben_mailer_nonce') && !wp_verify_nonce($nonce, 'crm_ajax_nonce')) {
    wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen. Bitte laden Sie die Seite neu.', 'custom-crm')], 403);
  }

  $recipient      = isset($_POST['x_sieben_recipient']) ? sanitize_email($_POST['x_sieben_recipient']) : '';
  $subject        = isset($_POST['x_sieben_subject']) ? sanitize_text_field($_POST['x_sieben_subject']) : '';
  $body           = isset($_POST['x_sieben_body']) ? wp_unslash($_POST['x_sieben_body']) : '';
  $entry_id       = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
  $course_id      = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
  $context        = isset($_POST['context']) ? sanitize_key($_POST['context']) : '';

  // Test mode parameters
  $is_test_mode   = !empty($_POST['is_test_mode']) && in_array(strval($_POST['is_test_mode']), ['1', 'true'], true);
  $test_recipient = isset($_POST['test_recipient']) ? sanitize_email($_POST['test_recipient']) : '';
  $test_mode_type = isset($_POST['test_mode_type']) ? sanitize_key($_POST['test_mode_type']) : 'only_test';
  $prefix_subject = !empty($_POST['prefix_subject']) && in_array(strval($_POST['prefix_subject']), ['1', 'true'], true);

  // Validation
  if ($is_test_mode) {
    if (empty($test_recipient) || !is_email($test_recipient)) {
      wp_send_json_error(['message' => __('Bitte geben Sie eine gültige Test-E-Mail-Adresse an.', 'custom-crm')]);
    }
    if ($test_mode_type === 'both' && (empty($recipient) || !is_email($recipient))) {
      wp_send_json_error(['message' => __('Für den Versand an Kunde & Test wird auch eine gültige Kunden-E-Mail-Adresse benötigt.', 'custom-crm')]);
    }
  } else {
    if (empty($recipient) || !is_email($recipient)) {
      wp_send_json_error(['message' => __('Bitte geben Sie eine gültige Kunden-E-Mail-Adresse an.', 'custom-crm')]);
    }
  }

  // Determine recipients
  $recipients_to_send = [];
  if ($is_test_mode) {
    if ($test_mode_type === 'both') {
      $recipients_to_send = array_unique(array_filter([$test_recipient, $recipient]));
    } else {
      $recipients_to_send = [$test_recipient];
    }
  } else {
    $recipients_to_send = [$recipient];
  }

  // Format subject for test mode
  if ($is_test_mode && $prefix_subject) {
    if (strpos($subject, '[TEST]') !== 0) {
      $subject = '[TEST] ' . $subject;
    }
  }

  // Audit info banner in test emails
  if ($is_test_mode) {
    $mode_label = ($test_mode_type === 'both') ? 'An Test & Kunde versendet' : 'Nur an Test-Empfänger (Kunde erhält nichts)';
    $test_banner = '<div style="background-color:#fff3cd; border:1px solid #ffeeba; color:#856404; padding:12px 16px; margin-bottom:20px; font-family:sans-serif; font-size:13px; border-radius:4px; line-height:1.5;">'
      . '<strong>🧪 TEST-MODUS (X SIEBEN CRM)</strong><br>'
      . 'Dies ist eine interne Test-Zustellung aus dem CRM.<br>'
      . '<strong>Kunden-Empfänger:</strong> ' . esc_html($recipient ?: 'Keine angegeben') . '<br>'
      . '<strong>WPForms Eintrag-ID:</strong> #' . esc_html($entry_id) . '<br>'
      . '<strong>Modus:</strong> ' . esc_html($mode_label) . '<br>'
      . '<strong>Zeitstempel:</strong> ' . esc_html(current_time('d.m.Y H:i:s'))
      . '</div>';
    $body = $test_banner . $body;
  }

  // Attachments (supports multiple comma-separated URLs with multi-strategy path resolution)
  $attachments = [];
  if (!empty($_POST['x_sieben_pdf_url'])) {
    $pdf_urls_raw = explode(',', wp_unslash($_POST['x_sieben_pdf_url']));
    foreach ($pdf_urls_raw as $raw_url) {
      $raw_url = trim($raw_url);
      if (empty($raw_url)) continue;
      // AGB werden laut Vorgabe niemals als Dateianhang mitgeschickt (nur als Online-Link in der E-Mail)
      if (stripos($raw_url, 'AGB_X_SIEBEN') !== false || stripos(basename(parse_url($raw_url, PHP_URL_PATH)), 'agb') !== false) {
        continue;
      }
      $clean_url = esc_url_raw($raw_url);
      $attachments_path = '';

      // Strategie 1 & 2: Pfadauflösung mit Dekodierung von Sonderzeichen/Umlauten (%C3%9C -> Ü)
      $decoded_url = rawurldecode($clean_url);

      // Strategie 1: Standard Ersetzung mit home_url
      $cand_path = str_replace(home_url('/'), ABSPATH, $decoded_url);
      if (file_exists($cand_path)) {
        $attachments_path = $cand_path;
      } elseif (strpos($decoded_url, '/wp-content/') !== false) {
        // Strategie 2: Relative Auflösung ab /wp-content/ (funktioniert verlässlich über Domain-Diskrepanzen hinweg)
        $rel = substr($decoded_url, strpos($decoded_url, '/wp-content/'));
        $cand_path = rtrim(ABSPATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($rel, '/\\'));
        if (file_exists($cand_path)) {
          $attachments_path = $cand_path;
        }
      }

      if (!empty($attachments_path) && file_exists($attachments_path)) {
        $attachments[] = $attachments_path;
      }
    }
  }

  // Dynamically resolve all placeholders in subject and body with course/entry data
  if ($course_id || $entry_id) {
    require_once dirname(__DIR__) . '/crm-model.php';
    $crm_model = new CRM_Model($course_id, $entry_id);
    if (method_exists($crm_model, 'parse_string_with_data')) {
      $subject = $crm_model->parse_string_with_data($subject);
      $body    = $crm_model->parse_string_with_data($body);
    }
  }

  // Set email headers
  $headers = [
    'Content-Type: text/html; charset=UTF-8',
    'From: X SIEBEN Wirtschaftstraining <office@x-sieben.at>'
  ];

  // Normalize HTML for email delivery:
  // 1. Force public absolute HTTPS URLs for all images & links (prevents relative path breakages in Gmail/Outlook).
  // 2. Strip destructive cookie banner attributes (e.g. consent-original-src-_).
  // 3. Remove webmail/TinyMCE artifacts.
  if (function_exists('crm_prepare_email_html_for_sending')) {
    $body = crm_prepare_email_html_for_sending($body);
  }

  // Send mail to all designated targets
  $sent_targets = [];
  foreach ($recipients_to_send as $target_email) {
    if (wp_mail($target_email, $subject, $body, $headers, $attachments)) {
      $sent_targets[] = $target_email;
    }
  }

  if (!empty($sent_targets)) {
    $now_mysql = current_time('mysql');

    // Case 1: ONLY TEST MODE (Audit log only, customer status remains unchanged)
    if ($is_test_mode && $test_mode_type === 'only_test') {
      if ($entry_id && function_exists('crm_add_entry_status_history')) {
        $note = sprintf(
          '🧪 Test-E-Mail gesendet an: %s (Kunde: %s). Betreff: %s',
          implode(', ', $sent_targets),
          $recipient ?: '-',
          $subject
        );
        crm_add_entry_status_history($entry_id, 'test_mail_gesendet', '🧪 Test-Mail gesendet', $note);
      }

      // Revisionssicherer Snapshot auch für Test-Versand archivieren
      if ($entry_id && function_exists('crm_create_document_snapshot')) {
        crm_create_document_snapshot([
          'entry_id'        => $entry_id,
          'course_id'       => $course_id,
          'doc_type'        => $context,
          'status_key'      => 'test_mail_gesendet',
          'recipient'       => $recipient,
          'sent_targets'    => $sent_targets,
          'subject'         => $subject,
          'email_body_html' => $body,
          'attachments'     => $attachments,
          'crm_model'       => isset($crm_model) ? $crm_model : null,
          'is_test'         => true,
          'sent_by'         => get_current_user_id(),
          'sent_at'         => $now_mysql,
        ]);
      }

      wp_send_json_success([
        'message'        => '🧪 Test-E-Mail erfolgreich gesendet an: ' . implode(', ', $sent_targets),
        'is_test'        => true,
        'entry_id'       => $entry_id,
      ]);
    }

    // Case 2: LIVE EMAIL OR BOTH TEST & CUSTOMER
    global $wpdb;
    $current_status_key = '';
    if ($entry_id) {
      $table_status = $wpdb->prefix . 'crm_entry_status';
      $current_status_key = $wpdb->get_var($wpdb->prepare("SELECT status_key FROM $table_status WHERE entry_id = %d", $entry_id));
    }

    $status_key = 'angebot_gesendet';
    $status_label = 'Angebot gesendet';

    if ($context === 'kurszeitenbestaetigung' || $context === 'xsieben_kurszeitenbestaetigung') {
      if ($current_status_key === 'angebot_gesendet') {
        // Angebot wurde bereits versendet, jetzt auch KB -> beide sind versendet!
        $status_key = 'angebot_und_kurszeiten_gesendet';
        $status_label = 'Angebot & KB gesendet';
      } else {
        $status_key = 'kurszeitenbestaetigung_gesendet';
        $status_label = 'Kurszeitenbestätigung gesendet';
      }
    } elseif ($context === 'xsieben_angebot_kurszeiten' || $context === 'xsieben_angebot_und_kurszeiten') {
      $status_key = 'angebot_und_kurszeiten_gesendet';
      $status_label = 'Angebot & KB gesendet';
    } elseif ($context === 'teilnahmebestaetigung' || $context === 'xsieben_teilnahmebestaetigung') {
      $status_key = 'teilnahmebestaetigung_gesendet';
      $status_label = 'Teilnahmebestätigung gesendet';
    } elseif ($context === 'xsieben_diplom' || $context === 'diplom') {
      $status_key = 'diplom_gesendet';
      $status_label = 'Diplom gesendet';
    } elseif ($context === 'anmeldung' || $context === 'xsieben_anmeldung') {
      $status_key = 'angemeldet';
      $status_label = 'Anmeldung gesendet';
    } else {
      // Angebot versendet
      if ($current_status_key === 'kurszeitenbestaetigung_gesendet') {
        // KB wurde bereits versendet, jetzt auch Angebot -> beide sind versendet!
        $status_key = 'angebot_und_kurszeiten_gesendet';
        $status_label = 'Angebot & KB gesendet';
      } else {
        $status_key = 'angebot_gesendet';
        $status_label = 'Angebot gesendet';
      }
    }

    if ($entry_id && function_exists('crm_set_entry_status')) {
      if ($is_test_mode && $test_mode_type === 'both') {
        $note = sprintf(
          'E-Mail an Kunden (%s) UND Test-Empfänger (%s) gesendet. Betreff: %s',
          $recipient,
          $test_recipient,
          $subject
        );
      } else {
        $note = sprintf('E-Mail an Kunden (%s) gesendet. Betreff: %s', $recipient, $subject);
      }
      crm_set_entry_status($entry_id, $status_key, $note, $now_mysql);
    }

    // Revisionssicherer Dokument- & Daten-Snapshot für Live-Versand archivieren
    if ($entry_id && function_exists('crm_create_document_snapshot')) {
      crm_create_document_snapshot([
        'entry_id'        => $entry_id,
        'course_id'       => $course_id,
        'doc_type'        => $context,
        'status_key'      => $status_key,
        'recipient'       => $recipient,
        'sent_targets'    => $sent_targets,
        'subject'         => $subject,
        'email_body_html' => $body,
        'attachments'     => $attachments,
        'crm_model'       => isset($crm_model) ? $crm_model : null,
        'is_test'         => false,
        'sent_by'         => get_current_user_id(),
        'sent_at'         => $now_mysql,
      ]);
    }

    wp_send_json_success([
      'message'        => 'E-Mail erfolgreich gesendet an: ' . implode(', ', $sent_targets),
      'is_test'        => false,
      'entry_id'       => $entry_id,
      'status_key'     => $status_key,
      'status_label'   => $status_label,
      'badge_html'     => function_exists('crm_render_status_badge') ? crm_render_status_badge($status_key, $status_label, $now_mysql) : '',
      'date_formatted' => date_i18n('d.m.Y, H:i', strtotime($now_mysql)),
      'actions_html'   => function_exists('crm_render_entry_actions') ? crm_render_entry_actions($entry_id, $course_id, $status_key) : '',
    ]);
  } else {
    wp_send_json_error(['message' => __('Fehler beim Senden der E-Mail. Bitte Mail-Konfiguration prüfen.', 'custom-crm')]);
  }
});

/**
 * AJAX Handler zur On-Demand-Generierung von CRM-PDFs beim Anwählen im Mailer
 */
add_action('wp_ajax_crm_generate_attachment_pdf', function () {
  if (!current_user_can('manage_options')) {
    wp_send_json_error(['message' => __('Nicht autorisierter Zugriff.', 'custom-crm')], 403);
  }

  $nonce = $_POST['nonce'] ?? ($_POST['security'] ?? '');
  if (!wp_verify_nonce($nonce, 'crm_ajax_nonce') && !wp_verify_nonce($nonce, 'x_sieben_mailer_nonce')) {
    wp_send_json_error(['message' => 'Sicherheitsprüfung fehlgeschlagen.']);
  }

  $doc_type  = isset($_POST['doc_type']) ? sanitize_key($_POST['doc_type']) : '';
  $entry_id  = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
  $course_id = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;

  if (!$entry_id || !$course_id || empty($doc_type)) {
    wp_send_json_error(['message' => __('Ungültige Parameter für PDF-Generierung.', 'custom-crm')]);
  }

  $pdf_url = '';

  if ($doc_type === 'kb') {
    require_once dirname(__DIR__) . '/pdf/kurszeitenbestaetigung.php';
    if (function_exists('xsieben_kurszeitenbestaetigung_pdf')) {
      $pdf_url = xsieben_kurszeitenbestaetigung_pdf($entry_id, $course_id, false);
    }
  } elseif ($doc_type === 'angebot') {
    require_once dirname(__DIR__) . '/pdf/offer.php';
    if (function_exists('xsieben_offer_pdf')) {
      $pdf_url = xsieben_offer_pdf($entry_id, $course_id, false);
    }
  } elseif ($doc_type === 'tb') {
    require_once dirname(__DIR__) . '/pdf/teilnamebestaetigung.php';
    if (function_exists('xsieben_teilnahmebestaetigung_pdf')) {
      $pdf_url = xsieben_teilnahmebestaetigung_pdf($entry_id, $course_id, false);
    }
  } elseif ($doc_type === 'diplom') {
    require_once dirname(__DIR__) . '/pdf/diplom.php';
    if (function_exists('xsieben_diplom_pdf')) {
      $pdf_url = xsieben_diplom_pdf($entry_id, $course_id, false);
    }
  } elseif ($doc_type === 'invoice') {
    require_once dirname(__DIR__) . '/pdf/invoice.php';
    if (function_exists('xsieben_invoice_pdf')) {
      $pdf_url = xsieben_invoice_pdf($entry_id, $course_id, false);
    }
  }

  if (empty($pdf_url)) {
    wp_send_json_error(['message' => __('PDF-Dokument konnte nicht generiert werden.', 'custom-crm')]);
  }

  $filename = rawurldecode(basename(parse_url($pdf_url, PHP_URL_PATH)));
  $filepath = rawurldecode(str_replace(home_url('/'), ABSPATH, $pdf_url));
  $filesize = file_exists($filepath) ? size_format(filesize($filepath), 1) : '';

  wp_send_json_success([
    'pdf_url'  => $pdf_url,
    'filename' => $filename,
    'filesize' => $filesize,
  ]);
});

/**
 * AJAX Handler zum Aktualisieren und Speichern des Diplom-Abschluss-Erfolgs
 */
add_action('wp_ajax_crm_update_diplom_success', function () {
  if (!current_user_can('manage_options')) {
    wp_send_json_error(['message' => 'Keine Berechtigung.']);
  }

  $nonce = $_POST['nonce'] ?? ($_POST['security'] ?? '');
  if (!wp_verify_nonce($nonce, 'crm_ajax_nonce') && !wp_verify_nonce($nonce, 'x_sieben_mailer_nonce')) {
    wp_send_json_error(['message' => 'Sicherheitsprüfung fehlgeschlagen.']);
  }

  $entry_id    = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;
  $course_id   = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
  $success_val = isset($_POST['success_val']) ? sanitize_text_field(wp_unslash($_POST['success_val'])) : 'erfolgreich';

  if (!$entry_id || !$course_id) {
    wp_send_json_error(['message' => 'Fehlende Parameter.']);
  }

  // Persistiere den Wert in WPForms-Eintrag (Feld 100)
  if (function_exists('wpforms')) {
    $entry = wpforms()->entry->get($entry_id);
    if ($entry) {
      $fields = is_string($entry->fields) ? json_decode($entry->fields, true) : $entry->fields;
      if (is_array($fields)) {
        // Label für saubere WPForms-Darstellung ermitteln
        $lower = mb_strtolower($success_val, 'UTF-8');
        $label = 'Erfolg';
        if (strpos($lower, 'ausgezeichnet') !== false) {
          $label = 'Ausgezeichnetem Erfolg';
        } elseif (strpos($lower, 'sehr gut') !== false) {
          $label = 'Sehr gutem Erfolg';
        } elseif (strpos($lower, 'gut') !== false) {
          $label = 'Gutem Erfolg';
        }

        if (!isset($fields[100])) {
          $fields[100] = [
            'id'    => 100,
            'name'  => 'Abschluss Erfolg',
            'type'  => 'checkbox',
            'value' => $label,
          ];
        } else {
          $fields[100]['value'] = $label;
          if (isset($fields[100]['value_raw'])) {
            $fields[100]['value_raw'] = $label;
          }
        }

        global $wpdb;
        $entry_table = isset(wpforms()->entry->table_name) ? wpforms()->entry->table_name : "{$wpdb->prefix}wpforms_entries";
        $wpdb->update(
          $entry_table,
          ['fields' => wp_json_encode($fields)],
          ['entry_id' => $entry_id]
        );
      }
    }
  }

  // Diplom neu generieren
  if (function_exists('xsieben_diplom_pdf')) {
    $new_pdf_url = xsieben_diplom_pdf($entry_id, $course_id, false, $success_val);

    // Audit-Trail
    if (function_exists('crm_add_entry_status_history')) {
      crm_add_entry_status_history(
        $entry_id,
        'diplom_erfolg_geaendert',
        'Diplom-Erfolg geändert: ' . esc_html($success_val),
        sprintf('Erfolg für Eintrag #%d auf "%s" gesetzt.', $entry_id, esc_html($success_val))
      );
    }

    wp_send_json_success([
      'message'     => sprintf('Diplom erfolgreich auf "%s" aktualisiert.', esc_html($success_val)),
      'pdf_url'     => $new_pdf_url,
      'success_val' => $success_val,
    ]);
  } else {
    wp_send_json_error(['message' => 'Diplom-Funktion nicht gefunden.']);
  }
});

