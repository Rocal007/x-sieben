<?php
/**
 * CRM Settings Component: Placeholders Cheat Sheet
 *
 * Rendert die klickbare Platzhalter-Übersicht für E-Mail- und PDF-Bausteine.
 *
 * @param string $type 'email'|'pdf'
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('crm_render_placeholders_cheat_sheet')) {
    function crm_render_placeholders_cheat_sheet(string $type = 'email')
    {
        ?>
        <div class="crm-cheat-sheet" style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed #cbd5e1;">
            <details>
                <summary style="cursor: pointer; font-size: 12.5px; font-weight: 600; color: #0369a1; outline: none; user-select: none;">
                    <span class="dashicons dashicons-editor-code" style="vertical-align: text-top; font-size: 16px;"></span>
                    <?php esc_html_e('Klickbare Platzhalter / Variablen anzeigen (Klick kopiert in die Zwischenablage)', 'custom-crm'); ?>
                </summary>
                <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 8px;">
                    <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                        <span style="font-size:11.5px; font-weight:600; color:#475569; width:90px;">Person:</span>
                        <a href="#" class="crm-chip" data-code="{salutation}">{salutation} (Sehr geehrte/r...)</a>
                        <a href="#" class="crm-chip" data-code="{anrede}">{anrede}</a>
                        <a href="#" class="crm-chip" data-code="{titel}">{titel}</a>
                        <a href="#" class="crm-chip" data-code="{vorname}">{vorname}</a>
                        <a href="#" class="crm-chip" data-code="{nachname}">{nachname}</a>
                        <a href="#" class="crm-chip" data-code="{email}">{email}</a>
                        <a href="#" class="crm-chip" data-code="{svr}">{svr} (SV-Nummer)</a>
                        <a href="#" class="crm-chip" data-code="{street}">{street} (Straße & Nr.)</a>
                        <a href="#" class="crm-chip" data-code="{zip_code}">{zip_code} (PLZ)</a>
                        <a href="#" class="crm-chip" data-code="{city}">{city} (Ort)</a>
                    </div>
                    <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                        <span style="font-size:11.5px; font-weight:600; color:#475569; width:90px;">Kurs:</span>
                        <a href="#" class="crm-chip" data-code="{title}">{title} (Voller Kurstitel)</a>
                        <a href="#" class="crm-chip" data-code="{titel_short}">{titel_short}</a>
                        <a href="#" class="crm-chip" data-code="{kurstyp}">{kurstyp}</a>
                        <a href="#" class="crm-chip" data-code="{start_datum}">{start_datum}</a>
                        <a href="#" class="crm-chip" data-code="{end_datum}">{end_datum}</a>
                        <a href="#" class="crm-chip" data-code="{anzahl_le}">{anzahl_le} (LE)</a>
                        <a href="#" class="crm-chip" data-code="{preis_brutto}">{preis_brutto} €</a>
                        <a href="#" class="crm-chip" data-code="{preis_netto}">{preis_netto} €</a>
                        <a href="#" class="crm-chip" data-code="{trainer}">{trainer}</a>
                        <a href="#" class="crm-chip" data-code="{expire}">{expire} (Gültigkeit)</a>
                        <a href="#" class="crm-chip" data-code="{zielgruppe}">{zielgruppe}</a>
                    </div>
                    <?php if ($type === 'email') : ?>
                        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                            <span style="font-size:11.5px; font-weight:600; color:#475569; width:90px;">Bausteine:</span>
                            <a href="#" class="crm-chip" data-code="{signatur}">{signatur}</a>
                            <a href="#" class="crm-chip" data-code="{email_footer}">{email_footer}</a>
                            <a href="#" class="crm-chip" data-code="{bankverbindung}">{bankverbindung}</a>
                        </div>
                    <?php else : ?>
                        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                            <span style="font-size:11.5px; font-weight:600; color:#475569; width:90px;">PDF Spezial:</span>
                            <a href="#" class="crm-chip" data-code="{current_date}">{current_date} (Datum d.m.Y)</a>
                            <a href="#" class="crm-chip" data-code="{diplom_success}">{diplom_success} (mit [Erfolg])</a>
                        </div>
                    <?php endif; ?>
                    <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                        <span style="font-size:11.5px; font-weight:600; color:#475569; width:90px;">Institut & CI:</span>
                        <a href="#" class="crm-chip" data-code="{company_name}">{company_name}</a>
                        <a href="#" class="crm-chip" data-code="{company_address}">{company_address}</a>
                        <a href="#" class="crm-chip" data-code="{location_wien}">{location_wien}</a>
                        <a href="#" class="crm-chip" data-code="{company_phone}">{company_phone}</a>
                        <a href="#" class="crm-chip" data-code="{company_email}">{company_email}</a>
                        <a href="#" class="crm-chip" data-code="{company_uid}">{company_uid}</a>
                        <a href="#" class="crm-chip" data-code="{company_fn}">{company_fn}</a>
                        <a href="#" class="crm-chip" data-code="{company_bank}">{company_bank}</a>
                        <a href="#" class="crm-chip" data-code="{company_logo}">{company_logo}</a>
                        <a href="#" class="crm-chip" data-code="{backoffice_name}">{backoffice_name}</a>
                    </div>
                </div>
            </details>
        </div>
        <?php
    }
}
