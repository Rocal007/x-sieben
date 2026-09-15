/**
 * CRM Settings & Template Editors Controller
 *
 * Verwaltet Accordions, Live-Filter, Media-Uploader, asynchrone Vorschauen (PDF & E-Mail),
 * Drag-and-Drop Sortierung und Testversand für die CRM-Settings-Seite.
 *
 * Subprojekt inc/core/crm/
 * @version 2.18.16
 */
(function($) {
    'use strict';

    const crmSettings = window.crmSettingsData || {};
    const i18n = crmSettings.i18n || {};

    jQuery(document).ready(function($) {
        let fieldCount = (crmSettings.fieldCount || 100);
        let activeDocFilter = 'all';

        // WordPress Media Uploader for Logos
        $(document).on('click', '.crm-media-upload-btn', function(e) {
            e.preventDefault();
            const btn = $(this);
            const targetInputId = btn.data('target-input');
            const targetPreviewId = btn.data('target-preview');
            const inputField = $('#' + targetInputId);
            const previewImg = $('#' + targetPreviewId);

            if (typeof wp === 'undefined' || !wp.media) {
                alert(i18n.mediaError || "Die WordPress Medienverwaltung konnte nicht geladen werden.");
                return;
            }

            const customUploader = wp.media({
                title: i18n.chooseLogo || "Logo auswählen oder hochladen",
                button: {
                    text: i18n.useLogo || "Als Logo verwenden"
                },
                multiple: false
            });

            customUploader.on('select', function() {
                const attachment = customUploader.state().get('selection').first().toJSON();
                inputField.val(attachment.url);
                previewImg.attr('src', attachment.url).show();
            });

            customUploader.open();
        });

        // Remove Logo Button
        $(document).on('click', '.crm-media-remove-btn', function(e) {
            e.preventDefault();
            const btn = $(this);
            const targetInputId = btn.data('target-input');
            const targetPreviewId = btn.data('target-preview');
            $('#' + targetInputId).val('');
            $('#' + targetPreviewId).attr('src', '').hide();
        });

        // Reset to default Logo
        $(document).on('click', '.crm-reset-default-logo-btn', function(e) {
            e.preventDefault();
            const btn = $(this);
            const defaultLogo = btn.data('default-logo');
            const targetInputId = btn.data('target-input');
            const targetPreviewId = btn.data('target-preview');
            $('#' + targetInputId).val(defaultLogo);
            $('#' + targetPreviewId).attr('src', defaultLogo).show();
        });

        // CI Color Picker Synchronizer
        $(document).on('input change', '.crm-color-picker', function() {
            const hexInputId = $(this).data('target-hex');
            $('#' + hexInputId).val($(this).val());
        });
        $(document).on('input', '.crm-color-hex-input', function() {
            const val = $(this).val().trim();
            const picker = $(this).closest('.crm-color-item').find('.crm-color-picker');
            if (/^#[0-9A-F]{6}$/i.test(val)) {
                picker.val(val);
            }
        });

        // Toggle Accordion for single block
        $(document).on('click', '.crm-field-header', function(e) {
            if ($(e.target).closest('button, input, select, .crm-action-group').length) {
                return; // don't toggle when clicking actions
            }
            const content = $(this).next('.crm-field-content');
            content.slideToggle(180);
            $(this).find('.crm-accordion-arrow').toggleClass('dashicons-arrow-down dashicons-arrow-right');
        });

        // Toggle all accordions
        $('#crm-toggle-all-accordions').on('click', function(e) {
            e.preventDefault();
            const contents = $('.crm-field-content');
            const anyVisible = contents.is(':visible');
            if (anyVisible) {
                contents.slideUp(180);
                $('.crm-accordion-arrow').removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
            } else {
                contents.slideDown(180);
                $('.crm-accordion-arrow').removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');
            }
        });

        // Combined Live Filter (Document Pill + Email Type Pill + Keyword Search)
        let activeEmailTypeFilter = 'all';

        function filterFields() {
            const val = $('#crm-field-search').val().toLowerCase().trim();
            $('.crm-field-block').each(function() {
                const docType   = $(this).attr('data-doc') || 'general';
                const emailType = $(this).attr('data-email-type') || 'full_email';
                const category  = $(this).attr('data-category') || 'email';
                const title     = $(this).find('.crm-field-title-text').text().toLowerCase();
                const badge     = $(this).find('.crm-usage-badge, .crm-type-badge').text().toLowerCase();

                let matchesType = true;
                if (category === 'email') {
                    matchesType = (activeEmailTypeFilter === 'all' || emailType === activeEmailTypeFilter);
                } else {
                    matchesType = (activeDocFilter === 'all' || docType === activeDocFilter);
                }

                const matchesSearch = (!val || title.indexOf(val) !== -1 || badge.indexOf(val) !== -1);

                if (matchesType && matchesSearch) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });

            // Update group container visibility
            if (activeEmailTypeFilter === 'all') {
                $('.crm-email-group').show();
            } else if (activeEmailTypeFilter === 'full_email') {
                $('.crm-email-group-full').show();
                $('.crm-email-group-components').hide();
            } else if (activeEmailTypeFilter === 'component') {
                $('.crm-email-group-full').hide();
                $('.crm-email-group-components').show();
            }
        }

        // Live Filter / Search
        $('#crm-field-search').on('keyup', function() {
            filterFields();
        });

        // Email type filter pills (Alle / Gesamte Mails / Komponenten)
        $(document).on('click', '.crm-email-field-filter-btn', function(e) {
            e.preventDefault();
            $('.crm-email-field-filter-btn').removeClass('active').css({
                background: '',
                color: '',
                borderColor: '',
                fontWeight: 'normal'
            });

            const type = $(this).data('type');
            activeEmailTypeFilter = type;

            if (type === 'all') {
                $(this).addClass('active').css({background: '#0284c7', color: '#ffffff', borderColor: '#0284c7', fontWeight: '700'});
            } else if (type === 'full_email') {
                $(this).addClass('active').css({background: '#0284c7', color: '#ffffff', borderColor: '#0284c7', fontWeight: '700'});
            } else if (type === 'component') {
                $(this).addClass('active').css({background: '#059669', color: '#ffffff', borderColor: '#059669', fontWeight: '700'});
            }

            filterFields();
        });

        // Document filter pills (for PDF tab)
        $(document).on('click', '.crm-doc-pill', function(e) {
            e.preventDefault();
            $('.crm-doc-pill').removeClass('active');
            $(this).addClass('active');
            activeDocFilter = $(this).attr('data-doc') || 'all';
            filterFields();

            // Synchronize PDF preview if on PDF tab
            if (activeDocFilter !== 'all' && activeDocFilter !== 'general') {
                loadPdfPreview(activeDocFilter, false);
            }
        });

        // Click-to-copy placeholder chips
        $('.crm-chip').on('click', function(e) {
            e.preventDefault();
            const code = $(this).data('code');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(code).then(() => {
                    const original = $(this).text();
                    $(this).text('✓ Kopiert!').css('background', '#dcfce7');
                    setTimeout(() => {
                        $(this).text(original).css('background', '');
                    }, 1200);
                });
            }
        });

        // Click-to-copy component placeholder chip
        $(document).on('click', '.crm-copy-chip-btn', function(e) {
            e.preventDefault();
            const code = $(this).attr('data-code');
            const $btn = $(this);
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(function() {
                    $btn.find('.crm-copy-code-text').text('✓ Kopiert!');
                    $btn.css({'background': '#dcfce7', 'border-color': '#16a34a'});
                    setTimeout(function() {
                        $btn.find('.crm-copy-code-text').text(code);
                        $btn.css({'background': '#ffffff', 'border-color': '#86efac'});
                    }, 1600);
                });
            }
        });

        // Dropdown change for Category / Type Choice
        $(document).on('change', '.crm-field-category-choice-select', function() {
            const val = $(this).val(); // "email:full_email", "email:component", "pdf:general"
            const parts = val.split(':');
            const cat = parts[0];
            const subType = parts[1] || '';
            const block = $(this).closest('.crm-field-block');

            block.find('.crm-field-category-input').val(cat);
            block.find('.crm-field-email-type-input').val(subType);
            block.attr('data-category', cat);
            block.attr('data-email-type', subType);

            if (cat === 'email') {
                if (subType === 'full_email') {
                    block.find('.crm-type-badge').replaceWith('<span class="crm-type-badge crm-type-badge-full" style="background:#e0f2fe; color:#0284c7; border:1px solid #bae6fd; font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:10px; margin-left:6px;">📧 Gesamte E-Mail</span>');
                    block.find('.crm-comp-code-pill').hide();
                } else {
                    block.find('.crm-type-badge').replaceWith('<span class="crm-type-badge crm-type-badge-comp" style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; font-size:10.5px; font-weight:700; padding:2px 8px; border-radius:10px; margin-left:6px;">🧩 Komponente</span>');
                    block.find('.crm-comp-code-pill').show();
                }
            }
        });

        // Add new field via AJAX (parameterized by category and email_type)
        function addFieldAjax(category, emailType) {
            const newIndex = fieldCount++;
            emailType = emailType || (category === 'email' ? 'full_email' : '');
            const ajaxData = {
                action: 'crm_add_field_editor',
                index: newIndex,
                category: category,
                email_type: emailType
            };
            $.post(ajaxurl, ajaxData, function(response) {
                let targetContainer = $('#crm-fields-wrapper');
                if (category === 'email') {
                    if (emailType === 'component') {
                        targetContainer = $('.crm-email-group-components .crm-email-group-items');
                    } else {
                        targetContainer = $('.crm-email-group-full .crm-email-group-items');
                    }
                }
                if (!targetContainer.length) {
                    targetContainer = $('#crm-fields-wrapper');
                }

                targetContainer.append(response);
                const newBlock = $('.crm-field-block[data-index="' + newIndex + '"]');

                if (category === 'email') {
                    if (activeEmailTypeFilter !== 'all' && activeEmailTypeFilter !== emailType) {
                        $('.crm-email-field-filter-btn[data-type="all"]').trigger('click');
                    }
                } else if (category === 'pdf') {
                    activeDocFilter = 'all';
                    $('.crm-doc-pill').removeClass('active').filter('[data-doc="all"]').addClass('active');
                    $('#crm-field-search').val('');
                    $('.crm-field-block').show();
                }

                newBlock.find('.crm-field-content').show();
                newBlock.find('.crm-accordion-arrow').removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');
                $('html, body').animate({
                    scrollTop: newBlock.offset().top - 100
                }, 300);
            });
        }

        $('#add-crm-email-field').on('click', function(e) {
            e.preventDefault();
            addFieldAjax('email', 'full_email');
        });

        $('#add-crm-component-field').on('click', function(e) {
            e.preventDefault();
            addFieldAjax('email', 'component');
        });

        // Remove field
        $(document).on('click', '.remove-crm-field', function(e) {
            e.preventDefault();
            if (confirm(i18n.confirmDelete || "Diesen Textbaustein wirklich löschen?")) {
                $(this).closest('.crm-field-block').slideUp(180, function() {
                    $(this).remove();
                });
            }
        });

        // Individual field save via AJAX
        $(document).on('click', '.crm-save-field', function(e) {
            e.preventDefault();
            const btn = $(this);
            const fieldBlock = btn.closest('.crm-field-block');
            const index = fieldBlock.data('index');
            const title = fieldBlock.find('input[name="crm_fields[' + index + '][title]"]').val();
            const category = fieldBlock.find('.crm-field-category-input').val() || fieldBlock.attr('data-category') || 'email';
            const emailType = fieldBlock.find('.crm-field-email-type-input').val() || fieldBlock.attr('data-email-type') || 'full_email';
            const statusIndicator = fieldBlock.find('.save-status');

            let content = '';
            const editorId = 'crm_fields_' + index + '_content';
            if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                content = tinymce.get(editorId).getContent();
            } else {
                content = fieldBlock.find('textarea[name="crm_fields[' + index + '][content]"]').val();
            }

            btn.prop('disabled', true).text('Speichern...');

            const ajaxData = {
                action: 'crm_save_field_individual',
                nonce: (crmSettings.nonceSaveField || crmSettings.nonce || ""),
                index: index,
                title: title,
                content: content,
                category: category,
                email_type: emailType
            };

            $.post(ajaxurl, ajaxData, function(response) {
                btn.prop('disabled', false).text(i18n.saveField || "Feld speichern");
                if (response.success) {
                    fieldBlock.find('.crm-field-title-text').text(title || (i18n.newField || "Neues Feld"));
                    if (response.data && response.data.email_type) {
                        fieldBlock.attr('data-email-type', response.data.email_type);
                        if (response.data.type_badge) {
                            fieldBlock.find('.crm-type-badge').text(response.data.type_badge);
                        }
                    }
                    if (response.data && response.data.usage) {
                        fieldBlock.attr('data-doc', response.data.usage.doc || 'general');
                    }
                    statusIndicator.text('✓ Gespeichert!').css({color: '#16a34a', fontWeight: '600'}).fadeIn().delay(2500).fadeOut();

                    // Auto-refresh PDF preview if on PDF tab
                    if ($('#crm-pdf-preview-section').length) {
                        loadPdfPreview(currentPreviewDoc, true);
                    }
                    // Auto-refresh E-Mail preview if on E-Mail tab
                    if ($('#crm-email-preview-section').length) {
                        loadEmailPreview(currentEmailPreviewDoc, true);
                    }
                } else {
                    statusIndicator.text('Fehler beim Speichern.').css({color: '#dc2626'}).fadeIn().delay(2500).fadeOut();
                }
            });
        });

        // ==========================================
        // PDF LIVE PREVIEW CONTROLLER
        // ==========================================
        let currentPreviewDoc = 'kb';
        let isPreviewExpanded = false;
        const docTitles = {
            'kb': '"Kurszeitenbestätigung (KB)"',
            'tb': '"Teilnahmebestätigung (TB)"',
            'diplom': '"Diplom / Zertifikat"',
            'angebot': '"Angebot & Anhang"',
            'invoice': '"Honorarnote / Rechnung"'
        };

        function loadPdfPreview(docType, forceReload) {
            if (!$('#crm-pdf-preview-section').length) {
                return;
            }

            if (!docType || docType === 'all' || docType === 'general') {
                docType = currentPreviewDoc || 'kb';
            }

            currentPreviewDoc = docType;

            // Update UI state in preview header
            $('#crm-preview-doc-title').text(docTitles[docType] || docType.toUpperCase());
            $('.crm-preview-switch-btn').removeClass('active');
            $('.crm-preview-switch-btn[data-doc="' + docType + '"]').addClass('active');

            // Show loading overlay
            $('#crm-preview-error').hide();
            $('#crm-preview-loading').fadeIn(150);
            $('#crm-preview-reload-btn .crm-reload-icon').addClass('spin');

            const previewNonce = window.crmPreviewNonce || (typeof crmData !== 'undefined' && crmData.nonce ? crmData.nonce : (crmSettings.noncePdfPreview || crmSettings.nonce || ""));

            const ajaxData = {
                action: 'crm_get_pdf_preview_url',
                nonce: previewNonce,
                doc_type: docType
            };

            $.post(ajaxurl, ajaxData, function(response) {
                if (response.success && response.data && response.data.url) {
                    if (response.data.new_nonce) {
                        window.crmPreviewNonce = response.data.new_nonce;
                    }
                    if (response.data.sample_info) {
                        $('#crm-preview-sample-info').text(response.data.sample_info);
                    }
                    const bustParam = (forceReload ? '&reload=' : '&t=') + Date.now();
                    const previewUrl = response.data.url + (response.data.url.indexOf('?') !== -1 ? bustParam : '?' + bustParam.substr(1)) + '#toolbar=0';

                    $('#crm-pdf-preview-iframe').attr('src', previewUrl);
                    $('#crm-preview-newtab-btn').attr('href', response.data.url);
                    $('#crm-preview-download-btn').attr('href', response.data.url);
                    $('#crm-preview-updated-at').text('Stand: ' + new Date().toLocaleTimeString());

                    $('#crm-pdf-preview-iframe').off('load').on('load', function() {
                        $('#crm-preview-loading').fadeOut(200);
                    });
                    setTimeout(function() {
                        $('#crm-preview-loading').fadeOut(200);
                    }, 1800);
                } else {
                    const errMsg = (response.data && response.data.message) ? response.data.message : (i18n.previewError || "Fehler beim Generieren der PDF-Vorschau.");
                    $('#crm-preview-error-msg').text(errMsg);
                    $('#crm-preview-loading').hide();
                    $('#crm-preview-error').fadeIn(150);
                }
            }).fail(function() {
                $('#crm-preview-error-msg').text(i18n.serverError || "Serververbindung fehlgeschlagen.");
                $('#crm-preview-loading').hide();
                $('#crm-preview-error').fadeIn(150);
            }).always(function() {
                $('#crm-preview-reload-btn .crm-reload-icon').removeClass('spin');
            });
        }

        // Preview document switcher inside preview card
        $(document).on('click', '.crm-preview-switch-btn', function(e) {
            e.preventDefault();
            const targetDoc = $(this).attr('data-doc');
            if (targetDoc) {
                loadPdfPreview(targetDoc, false);
                // Also sync with section pill in the organizer above
                if ($('.crm-sec-pill[data-doc="' + targetDoc + '"]').length) {
                    $('.crm-sec-pill').removeClass('active');
                    $('.crm-sec-pill[data-doc="' + targetDoc + '"]').addClass('active');
                    $('.crm-sec-tab-pane').hide();
                    $('#crm-sec-pane-' + targetDoc).fadeIn(150);
                }
            }
        });

        // Preview reload button
        $('#crm-preview-reload-btn').on('click', function(e) {
            e.preventDefault();
            loadPdfPreview(currentPreviewDoc, true);
        });

        // Preview height expand/contract button
        $('#crm-preview-toggle-size-btn').on('click', function(e) {
            e.preventDefault();
            isPreviewExpanded = !isPreviewExpanded;
            const newHeight = isPreviewExpanded ? '960px' : '680px';
            $('#crm-pdf-preview-iframe').css('height', newHeight);
            $('.crm-preview-body').css('min-height', newHeight);
            $(this).find('.dashicons').toggleClass('dashicons-editor-expand dashicons-editor-contract');
        });

        // "Vorschau" button on individual field block
        $(document).on('click', '.crm-preview-this-doc', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const docType = $(this).attr('data-doc');
            if (docType && docType !== 'general') {
                loadPdfPreview(docType, false);
                if ($('#crm-pdf-preview-section').length) {
                    $('html, body').animate({
                        scrollTop: $('#crm-pdf-preview-section').offset().top - 40
                    }, 350);
                }
            }
        });

        // Master Header & Footer Mode sync in Settings Box
        $(document).on('change', '.crm-master-header-mode', function() {
            const mode = $(this).val();
            const $box = $(this).closest('.crm-pdf-master-hf-box');
            const $logoCb = $box.find('input[name="crm_pdf_master_hf[header_logo]"]');
            const $addrCb = $box.find('input[name="crm_pdf_master_hf[header_address]"]');

            if (mode === 'full') {
                $logoCb.prop('checked', true);
                $addrCb.prop('checked', true);
            } else if (mode === 'logo_only') {
                $logoCb.prop('checked', true);
                $addrCb.prop('checked', false);
            } else if (mode === 'address_only') {
                $logoCb.prop('checked', false);
                $addrCb.prop('checked', true);
            } else if (mode === 'none') {
                $logoCb.prop('checked', false);
                $addrCb.prop('checked', false);
            }
        });

        $(document).on('change', '.crm-master-footer-mode', function() {
            const mode = $(this).val();
            const $box = $(this).closest('.crm-pdf-master-hf-box');
            const $compCb = $box.find('input[name="crm_pdf_master_hf[footer_company]"]');
            const $pageCb = $box.find('input[name="crm_pdf_master_hf[footer_page_num]"]');
            const $dateCb = $box.find('input[name="crm_pdf_master_hf[footer_date]"]');

            if (mode === 'standard') {
                $compCb.prop('checked', true);
                $pageCb.prop('checked', true);
                $dateCb.prop('checked', false);
            } else if (mode === 'full') {
                $compCb.prop('checked', true);
                $pageCb.prop('checked', true);
                $dateCb.prop('checked', true);
            } else if (mode === 'page_numbers_only') {
                $compCb.prop('checked', false);
                $pageCb.prop('checked', true);
                $dateCb.prop('checked', false);
            } else if (mode === 'company_only') {
                $compCb.prop('checked', true);
                $pageCb.prop('checked', false);
                $dateCb.prop('checked', false);
            } else if (mode === 'none') {
                $compCb.prop('checked', false);
                $pageCb.prop('checked', false);
                $dateCb.prop('checked', false);
            }
        });

        // ==========================================
        // PDF SECTIONS DRAG & DROP CONTROLLER
        // ==========================================
        $(document).on('click', '.crm-sec-pill', function(e) {
            e.preventDefault();
            $('.crm-sec-pill').removeClass('active').css({borderColor: '', color: '', fontWeight: 'normal'});
            $(this).addClass('active').css({borderColor: '#7c3aed', color: '#6d28d9', fontWeight: '600'});
            const targetDoc = $(this).data('doc');
            $('.crm-sec-tab-pane').hide();
            $('#crm-sec-pane-' + targetDoc).fadeIn(120);

            initCrmSortables();

            if (typeof loadPdfPreview === 'function') {
                loadPdfPreview(targetDoc, false);
            }
        });

        function initCrmSortables() {
            if (typeof $.fn.sortable !== 'undefined') {
                $('.crm-sortable-sections').sortable({
                    handle: '.crm-section-drag-handle',
                    items: '> li.crm-pdf-section-item',
                    placeholder: 'crm-section-sortable-placeholder',
                    axis: 'y',
                    cursor: 'grabbing',
                    opacity: 0.88,
                    tolerance: 'pointer'
                });

                $('.crm-sortable-subsections').sortable({
                    handle: '.crm-sub-drag-handle',
                    items: '> li.crm-pdf-subsection-item',
                    placeholder: 'crm-sub-sortable-placeholder',
                    axis: 'y',
                    cursor: 'grabbing',
                    opacity: 0.88,
                    tolerance: 'pointer'
                });

                if ($('#crm-fields-wrapper').length) {
                    $('#crm-fields-wrapper').sortable({
                        handle: '.crm-field-drag-handle',
                        items: '> .crm-field-block',
                        placeholder: 'crm-field-sortable-placeholder',
                        axis: 'y',
                        cursor: 'grabbing',
                        opacity: 0.88,
                        tolerance: 'pointer'
                    });
                }
            }
        }
        initCrmSortables();

        $(document).on('change', '.crm-section-checkbox', function() {
            const item = $(this).closest('.crm-pdf-section-item');
            if ($(this).is(':checked')) {
                item.removeClass('is-disabled').addClass('is-active').css('opacity', '1');
            } else {
                item.removeClass('is-active').addClass('is-disabled').css('opacity', '0.55');
            }
        });

        function updateHfSummaryBadge($sec) {
            const hMode = $sec.find('.crm-hf-header-mode').val() || $sec.data('header-mode') || 'master';
            const fMode = $sec.find('.crm-hf-footer-mode').val() || $sec.data('footer-mode') || 'master';

            let hLabel = 'H: Standard';
            if (hMode === 'master') hLabel = 'H: Master';
            else if (hMode === 'none') hLabel = 'H: Ohne';
            else if (hMode === 'logo_only') hLabel = 'H: Nur Logo';
            else if (hMode === 'address_only') hLabel = 'H: Nur Adr';
            else if (hMode === 'custom') hLabel = 'H: Eigen';
            else if (hMode === 'full') hLabel = 'H: Logo+Adr';

            let fLabel = 'F: Standard';
            if (fMode === 'master') fLabel = 'F: Master';
            else if (fMode === 'none') fLabel = 'F: Ohne';
            else if (fMode === 'page_numbers_only') fLabel = 'F: Nur Seite';
            else if (fMode === 'company_only') fLabel = 'F: Nur Firma';
            else if (fMode === 'full') fLabel = 'F: Firma+Dat+Seite';
            else if (fMode === 'custom') fLabel = 'F: Eigen';

            $sec.find('.crm-hf-summary-text').text(hLabel + ' | ' + fLabel);
        }

        // Toggle Header & Footer Drawer per Section
        $(document).on('click', '.crm-toggle-hf-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const item = $(this).closest('.crm-pdf-section-item');
            item.find('> .crm-hf-drawer').slideToggle(180);
        });

        // Section Header Mode Change
        $(document).on('change', '.crm-hf-header-mode', function() {
            const $sec = $(this).closest('.crm-pdf-section-item');
            const mode = $(this).val();
            const $logoCb = $sec.find('.crm-hf-header-logo');
            const $addrCb = $sec.find('.crm-hf-header-address');
            const $customBox = $sec.find('.crm-hf-header-custom-box');

            if (mode === 'full') {
                $logoCb.prop('checked', true);
                $addrCb.prop('checked', true);
                $customBox.hide();
            } else if (mode === 'logo_only') {
                $logoCb.prop('checked', true);
                $addrCb.prop('checked', false);
                $customBox.hide();
            } else if (mode === 'address_only') {
                $logoCb.prop('checked', false);
                $addrCb.prop('checked', true);
                $customBox.hide();
            } else if (mode === 'none') {
                $logoCb.prop('checked', false);
                $addrCb.prop('checked', false);
                $customBox.hide();
            } else if (mode === 'custom') {
                $customBox.slideDown(150);
            } else if (mode === 'master') {
                $customBox.hide();
            }
            $sec.attr('data-header-mode', mode).data('header-mode', mode);
            updateHfSummaryBadge($sec);
        });

        // Header Checkboxes change (manual override updates mode)
        $(document).on('change', '.crm-hf-header-logo, .crm-hf-header-address', function() {
            const $sec = $(this).closest('.crm-pdf-section-item');
            const hasLogo = $sec.find('.crm-hf-header-logo').is(':checked');
            const hasAddr = $sec.find('.crm-hf-header-address').is(':checked');
            const $mode = $sec.find('.crm-hf-header-mode');

            if (hasLogo && hasAddr) {
                $mode.val('full');
            } else if (hasLogo && !hasAddr) {
                $mode.val('logo_only');
            } else if (!hasLogo && hasAddr) {
                $mode.val('address_only');
            } else {
                $mode.val('none');
            }
            $sec.find('.crm-hf-header-custom-box').hide();
            $sec.attr('data-header-mode', $mode.val()).data('header-mode', $mode.val());
            updateHfSummaryBadge($sec);
        });

        // Section Footer Mode Change
        $(document).on('change', '.crm-hf-footer-mode', function() {
            const $sec = $(this).closest('.crm-pdf-section-item');
            const mode = $(this).val();
            const $compCb = $sec.find('.crm-hf-footer-company');
            const $pageCb = $sec.find('.crm-hf-footer-page-num');
            const $dateCb = $sec.find('.crm-hf-footer-date');
            const $customBox = $sec.find('.crm-hf-footer-custom-box');

            if (mode === 'standard') {
                $compCb.prop('checked', true);
                $pageCb.prop('checked', true);
                $dateCb.prop('checked', false);
                $customBox.hide();
            } else if (mode === 'full') {
                $compCb.prop('checked', true);
                $pageCb.prop('checked', true);
                $dateCb.prop('checked', true);
                $customBox.hide();
            } else if (mode === 'page_numbers_only') {
                $compCb.prop('checked', false);
                $pageCb.prop('checked', true);
                $dateCb.prop('checked', false);
                $customBox.hide();
            } else if (mode === 'company_only') {
                $compCb.prop('checked', true);
                $pageCb.prop('checked', false);
                $dateCb.prop('checked', false);
                $customBox.hide();
            } else if (mode === 'none') {
                $compCb.prop('checked', false);
                $pageCb.prop('checked', false);
                $dateCb.prop('checked', false);
                $customBox.hide();
            } else if (mode === 'custom') {
                $customBox.slideDown(150);
            } else if (mode === 'master') {
                $customBox.hide();
            }
            $sec.attr('data-footer-mode', mode).data('footer-mode', mode);
            updateHfSummaryBadge($sec);
        });

        // Footer Checkboxes change
        $(document).on('change', '.crm-hf-footer-company, .crm-hf-footer-page-num, .crm-hf-footer-date', function() {
            const $sec = $(this).closest('.crm-pdf-section-item');
            const hasComp = $sec.find('.crm-hf-footer-company').is(':checked');
            const hasPage = $sec.find('.crm-hf-footer-page-num').is(':checked');
            const hasDate = $sec.find('.crm-hf-footer-date').is(':checked');
            const $mode = $sec.find('.crm-hf-footer-mode');

            if (hasComp && hasPage && hasDate) {
                $mode.val('full');
            } else if (hasComp && hasPage && !hasDate) {
                $mode.val('standard');
            } else if (!hasComp && hasPage && !hasDate) {
                $mode.val('page_numbers_only');
            } else if (hasComp && !hasPage && !hasDate) {
                $mode.val('company_only');
            } else {
                $mode.val('none');
            }
            $sec.find('.crm-hf-footer-custom-box').hide();
            $sec.attr('data-footer-mode', $mode.val()).data('footer-mode', $mode.val());
            updateHfSummaryBadge($sec);
        });

        $(document).on('click', '.crm-move-up-btn', function(e) {
            e.preventDefault();
            const item = $(this).closest('.crm-pdf-section-item');
            const prev = item.prev('.crm-pdf-section-item');
            if (prev.length) {
                item.insertBefore(prev).hide().fadeIn(150);
            }
        });
        $(document).on('click', '.crm-move-down-btn', function(e) {
            e.preventDefault();
            const item = $(this).closest('.crm-pdf-section-item');
            const next = item.next('.crm-pdf-section-item');
            if (next.length) {
                item.insertAfter(next).hide().fadeIn(150);
            }
        });

        $(document).on('click', '.crm-save-sections-btn', function(e) {
            e.preventDefault();
            const btn = $(this);
            const manager = btn.closest('.crm-pdf-sections-manager');
            const docType = manager.data('doc');
            const entryId = manager.data('entry') || 0;
            const statusEl = manager.find('.crm-sections-status');

            let sections = [];
            if (typeof window.crmGetHierarchicalSections === 'function') {
                sections = window.crmGetHierarchicalSections(manager);
            } else {
                manager.find('> .crm-sortable-sections > .crm-pdf-section-item').each(function() {
                    const sec = $(this);
                    const isCustom = (sec.data('custom') == 1 || sec.attr('data-custom') === '1') ? 1 : 0;
                    const key = sec.data('key') || sec.attr('data-key');
                    const enabled = sec.find('> .crm-section-header-row .crm-section-checkbox').is(':checked') ? 1 : 0;
                    const title = sec.data('title') || sec.find('.crm-section-title').text().trim();
                    const badge = sec.data('badge') || '';
                    const color = sec.data('color') || '';
                    const content = sec.data('content') || '';

                    const subsections = [];
                    sec.find('.crm-sortable-subsections > .crm-pdf-subsection-item').each(function() {
                        const sub = $(this);
                        const subKey = sub.data('sub-key') || sub.attr('data-sub-key');
                        const subEnabled = sub.find('.crm-sub-checkbox').is(':checked') ? 1 : 0;
                        const subCustom = (sub.data('custom') == 1 || sub.attr('data-custom') === '1') ? 1 : 0;
                        let subTitle = sub.data('title') || sub.find('.crm-sub-title').text().trim();
                        let subContent = sub.data('content');
                        if (typeof subContent === 'undefined') {
                            subContent = sub.attr('data-content') || '';
                        }

                        // Drawer-Werte übernehmen (auch wenn Drawer vor dem Speichern wieder geschlossen wurde)
                        const $drawer = sub.find('> .crm-sub-edit-drawer');
                        if ($drawer.length) {
                            const $inputTitle = $drawer.find('.crm-sub-input-title');
                            const $inputContent = $drawer.find('.crm-sub-input-content');
                            if ($inputTitle.length && $inputTitle.val().trim()) {
                                subTitle = $inputTitle.val().trim();
                            }
                            if ($inputContent.length) {
                                const currentVal = $inputContent.val();
                                if (currentVal !== '') {
                                    subContent = currentVal;
                                }
                            }
                        }

                        // Standard-Unterabschnitte bereinigen
                        const defaultContent = sub.data('default-content') || sub.attr('data-default-content') || '';
                        if (!subCustom) {
                            if (typeof subContent === 'string' && (subContent.trim() === defaultContent.trim() || subContent.trim() === '{standard}')) {
                                subContent = '';
                            }
                        }

                        if (subKey) {
                            subsections.push({
                                key: subKey,
                                enabled: subEnabled,
                                is_custom: subCustom,
                                title: subTitle,
                                content: subContent
                            });
                        }
                    });

                    // Header & Footer
                    let headerMode = sec.find('.crm-hf-header-mode').val() || sec.data('header-mode') || sec.attr('data-header-mode') || 'master';
                    let headerLogo = 0;
                    const $hLogoCb = sec.find('.crm-hf-header-logo');
                    if ($hLogoCb.length) {
                        headerLogo = $hLogoCb.is(':checked') ? 1 : 0;
                    } else {
                        headerLogo = (sec.data('header-logo') == 1 || sec.attr('data-header-logo') === '1') ? 1 : 0;
                    }

                    let headerAddress = 0;
                    const $hAddrCb = sec.find('.crm-hf-header-address');
                    if ($hAddrCb.length) {
                        headerAddress = $hAddrCb.is(':checked') ? 1 : 0;
                    } else {
                        headerAddress = (sec.data('header-address') == 1 || sec.attr('data-header-address') === '1') ? 1 : 0;
                    }

                    let headerCustom = '';
                    const $hCustomInput = sec.find('.crm-hf-header-custom');
                    if ($hCustomInput.length) {
                        headerCustom = $hCustomInput.val();
                    } else {
                        headerCustom = sec.data('header-custom') || sec.attr('data-header-custom') || '';
                    }

                    let footerMode = sec.find('.crm-hf-footer-mode').val() || sec.data('footer-mode') || sec.attr('data-footer-mode') || 'master';
                    let footerCompany = 0;
                    const $fCompCb = sec.find('.crm-hf-footer-company');
                    if ($fCompCb.length) {
                        footerCompany = $fCompCb.is(':checked') ? 1 : 0;
                    } else {
                        footerCompany = (sec.data('footer-company') == 1 || sec.attr('data-footer-company') === '1') ? 1 : 0;
                    }

                    let footerPageNum = 0;
                    const $fPageCb = sec.find('.crm-hf-footer-page-num');
                    if ($fPageCb.length) {
                        footerPageNum = $fPageCb.is(':checked') ? 1 : 0;
                    } else {
                        footerPageNum = (sec.data('footer-page-num') == 1 || sec.attr('data-footer-page-num') === '1') ? 1 : 0;
                    }

                    let footerDate = 0;
                    const $fDateCb = sec.find('.crm-hf-footer-date');
                    if ($fDateCb.length) {
                        footerDate = $fDateCb.is(':checked') ? 1 : 0;
                    } else {
                        footerDate = (sec.data('footer-date') == 1 || sec.attr('data-footer-date') === '1') ? 1 : 0;
                    }

                    let footerCustom = '';
                    const $fCustomInput = sec.find('.crm-hf-footer-custom');
                    if ($fCustomInput.length) {
                        footerCustom = $fCustomInput.val();
                    } else {
                        footerCustom = sec.data('footer-custom') || sec.attr('data-footer-custom') || '';
                    }

                    if (key) {
                        sections.push({
                            key: key,
                            enabled: enabled,
                            is_custom: isCustom,
                            title: title,
                            badge: badge,
                            color: color,
                            content: content,
                            header_mode: headerMode,
                            header_logo: headerLogo,
                            header_address: headerAddress,
                            header_custom: headerCustom,
                            footer_mode: footerMode,
                            footer_company: footerCompany,
                            footer_page_num: footerPageNum,
                            footer_date: footerDate,
                            footer_custom: footerCustom,
                            subsections: subsections
                        });
                    }
                });
            }

            btn.prop('disabled', true).text(i18n.saving || "Speichern...");

            const postNonce = window.crmPreviewNonce || (typeof crmData !== 'undefined' ? crmData.nonce : (crmSettings.nonce || ""));

            $.post(ajaxurl, {
                action: 'crm_save_pdf_section_order',
                nonce: postNonce,
                doc_type: docType,
                entry_id: entryId,
                sections: sections
            }, function(res) {
                btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="vertical-align:text-top; font-size:14px;"></span> ' + (i18n.applyOrder || "Reihenfolge anwenden"));
                if (res.success) {
                    if (window.crmJsCache && typeof window.crmJsCache.cleanPartial === 'function') {
                        window.crmJsCache.cleanPartial('pdf_' + docType);
                    }
                    statusEl.text('✓ ' + (i18n.saved || "Gespeichert!")).css({color: '#16a34a'}).fadeIn().delay(2000).fadeOut();
                    if (typeof loadPdfPreview === 'function') {
                        loadPdfPreview(docType, true);
                    }
                } else {
                    const msg = (res.data && res.data.message) ? res.data.message : (i18n.errorSaving || "Fehler beim Speichern");
                    statusEl.text(msg).css({color: '#dc2626'}).fadeIn().delay(2500).fadeOut();
                }
            }).fail(function() {
                btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="vertical-align:text-top; font-size:14px;"></span> ' + (i18n.applyOrder || "Reihenfolge anwenden"));
                statusEl.text(i18n.serverError || "Serverfehler").css({color: '#dc2626'}).fadeIn().delay(2500).fadeOut();
            });
        });

        $(document).on('click', '.crm-reset-sections-btn', function(e) {
            e.preventDefault();
            const btn = $(this);
            const manager = btn.closest('.crm-pdf-sections-manager');
            const docType = manager.data('doc');
            const entryId = manager.data('entry') || 0;
            const isSidebar = manager.hasClass('crm-sections-sidebar') ? 1 : 0;
            const parentContainer = manager.parent();

            const postNonce = window.crmPreviewNonce || (typeof crmData !== 'undefined' ? crmData.nonce : (crmSettings.nonce || ""));

            btn.prop('disabled', true);

            $.post(ajaxurl, {
                action: 'crm_reset_pdf_section_order',
                nonce: postNonce,
                doc_type: docType,
                entry_id: entryId,
                is_sidebar: isSidebar
            }, function(res) {
                btn.prop('disabled', false);
                if (res.success && res.data && res.data.html) {
                    if (window.crmJsCache && typeof window.crmJsCache.cleanPartial === 'function') {
                        window.crmJsCache.cleanPartial('pdf_' + docType);
                    }
                    parentContainer.html(res.data.html);
                    initCrmSortables();
                    if (typeof loadPdfPreview === 'function') {
                        loadPdfPreview(docType, true);
                    }
                }
            }).fail(function() {
                btn.prop('disabled', false);
            });
        });

        // ==========================================
        // E-MAIL LIVE PREVIEW CONTROLLER
        // ==========================================
        let currentEmailPreviewDoc = 'angebot';
        let currentEmailViewport   = 'desktop';
        let isEmailPreviewExpanded = false;

        const emailDocTitles = {
            'angebot': '"Kursangebot & Beratung"',
            'kb': '"Kurszeitenbestätigung (KB)"',
            'angebot_kb': '"Angebot & Kurszeiten (Kombi)"',
            'anmeldung': '"Anmeldebestätigung & Buchung"',
            'tb': '"Teilnahmebestätigung (TB)"',
            'diplom': '"Diplom / Zertifikat"',
            'invoice': '"Honorarnote / Rechnung"'
        };

        function loadEmailPreview(docType, forceReload) {
            if (!$('#crm-email-preview-section').length) {
                return;
            }

            if (!docType || docType === 'all' || docType === 'general') {
                docType = currentEmailPreviewDoc || 'angebot';
            }

            currentEmailPreviewDoc = docType;

            // Update UI title and switcher pills
            $('#crm-email-preview-doc-title').text(emailDocTitles[docType] || docType.toUpperCase());
            $('.crm-email-preview-switch-btn').removeClass('active');
            $('.crm-email-preview-switch-btn[data-doc="' + docType + '"]').addClass('active');

            // Show loading overlay
            $('#crm-email-preview-error').hide();
            $('#crm-email-preview-loading').fadeIn(150);
            $('#crm-email-preview-reload-btn .crm-email-reload-icon').addClass('spin');

            const previewNonce = window.crmPreviewNonce || (typeof crmData !== 'undefined' && crmData.nonce ? crmData.nonce : (crmSettings.nonce || ""));

            // Standalone iframe URL
            const bustParam = (forceReload ? '&reload=' : '&t=') + Date.now();
            const frameUrl  = ajaxurl + '?action=crm_render_email_preview_frame&doc_type=' + encodeURIComponent(docType) + '&nonce=' + encodeURIComponent(previewNonce) + bustParam;

            $('#crm-email-preview-iframe').attr('src', frameUrl);
            $('#crm-email-preview-newtab-btn').attr('href', frameUrl);
            $('#crm-email-preview-updated-at').text('Stand: ' + new Date().toLocaleTimeString());

            // Also fetch preview data via JSON for sample info and HTML copy
            $.post(ajaxurl, {
                action: 'crm_get_email_preview_data',
                nonce: previewNonce,
                doc_type: docType
            }, function(res) {
                if (res.success && res.data) {
                    if (res.data.sample_info) {
                        $('#crm-email-preview-sample-info').text(res.data.sample_info);
                    }
                    if (res.data.html) {
                        window.lastEmailPreviewHtml = res.data.html;
                    }
                }
            });

            $('#crm-email-preview-iframe').off('load').on('load', function() {
                $('#crm-email-preview-loading').fadeOut(200);
            });
            setTimeout(function() {
                $('#crm-email-preview-loading').fadeOut(200);
            }, 1800);
            setTimeout(function() {
                $('#crm-email-preview-reload-btn .crm-email-reload-icon').removeClass('spin');
            }, 500);
        }

        // Viewport Switcher (Desktop 600px vs Mobile 375px vs Full 100%)
        $(document).on('click', '.crm-viewport-btn', function(e) {
            e.preventDefault();
            $('.crm-viewport-btn').removeClass('active').css({background: 'transparent', color: '#475569', fontWeight: 'normal'});
            $(this).addClass('active').css({background: '#ffffff', color: '#0f172a', fontWeight: '600'});
            const vp = $(this).data('viewport');
            currentEmailViewport = vp;
            const $wrapper = $('#crm-email-iframe-wrapper');
            if (vp === 'mobile') {
                $wrapper.css({'width': '375px', 'max-width': '100%'});
            } else if (vp === 'full') {
                $wrapper.css({'width': '100%', 'max-width': '100%'});
            } else {
                $wrapper.css({'width': '600px', 'max-width': '100%'});
            }
        });

        // Email Preview Reload
        $('#crm-email-preview-reload-btn').on('click', function(e) {
            e.preventDefault();
            loadEmailPreview(currentEmailPreviewDoc, true);
        });

        // Email Preview Height Expand / Contract
        $('#crm-email-preview-toggle-size-btn').on('click', function(e) {
            e.preventDefault();
            isEmailPreviewExpanded = !isEmailPreviewExpanded;
            const newHeight = isEmailPreviewExpanded ? '920px' : '620px';
            $('#crm-email-preview-iframe').css('height', newHeight);
            $('.crm-email-preview-body').css('min-height', newHeight);
            $(this).find('.dashicons').toggleClass('dashicons-editor-expand dashicons-editor-contract');
        });

        // Copy HTML to Clipboard
        $('#crm-email-copy-html-btn').on('click', function(e) {
            e.preventDefault();
            const btn = $(this);
            const htmlToCopy = window.lastEmailPreviewHtml || '';
            if (!htmlToCopy) {
                alert(i18n.noHtmlAvailable || "Kein HTML-Inhalt verfügbar. Bitte Vorschau neu laden.");
                return;
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(htmlToCopy).then(function() {
                    btn.html('✓ ' + (i18n.copied || "Kopiert!"));
                    setTimeout(function() {
                        btn.html('<span class="dashicons dashicons-clipboard" style="font-size:14px; vertical-align:text-top;"></span> ' + (i18n.copyHtml || "HTML kopieren"));
                    }, 2000);
                });
            } else {
                const $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(htmlToCopy).select();
                document.execCommand('copy');
                $temp.remove();
                btn.html('✓ ' + (i18n.copied || "Kopiert!"));
                setTimeout(function() {
                    btn.html('<span class="dashicons dashicons-clipboard" style="font-size:14px; vertical-align:text-top;"></span> ' + (i18n.copyHtml || "HTML kopieren"));
                }, 2000);
            }
        });

        // Send Test Mail
        $('#crm-email-send-test-btn').on('click', function(e) {
            e.preventDefault();
            const btn = $(this);
            const origHtml = btn.html();
            const testEmail = prompt(i18n.promptTestEmail || "An welche E-Mail-Adresse soll die Test-Vorschau gesendet werden?", crmSettings.currentUserEmail || "");
            if (!testEmail) return;

            btn.prop('disabled', true).text(i18n.sending || "Senden...");
            const postNonce = window.crmPreviewNonce || (typeof crmData !== 'undefined' ? crmData.nonce : (crmSettings.nonce || ""));

            $.post(ajaxurl, {
                action: 'crm_send_email_preview_test',
                nonce: postNonce,
                doc_type: currentEmailPreviewDoc,
                recipient: testEmail
            }, function(res) {
                btn.prop('disabled', false).html(origHtml);
                if (res.success) {
                    alert('✓ ' + (res.data && res.data.message ? res.data.message : (i18n.testMailSuccess || "Test-Mail erfolgreich versendet!")));
                } else {
                    alert('❌ ' + (res.data && res.data.message ? res.data.message : (i18n.testMailError || "Fehler beim Versand.")));
                }
            }).fail(function() {
                btn.prop('disabled', false).html(origHtml);
                alert('❌ ' + (i18n.serverError || "Serverfehler beim Versand der Test-Mail."));
            });
        });

        // Switch preview doc via header switcher buttons
        $(document).on('click', '.crm-email-preview-switch-btn', function(e) {
            e.preventDefault();
            const targetDoc = $(this).attr('data-doc');
            if (targetDoc) {
                loadEmailPreview(targetDoc, false);
                // Sync with organizer pill above
                if ($('.crm-email-sec-pill[data-doc="' + targetDoc + '"]').length) {
                    $('.crm-email-sec-pill').removeClass('active');
                    $('.crm-email-sec-pill[data-doc="' + targetDoc + '"]').addClass('active');
                    $('.crm-email-sec-tab-pane').hide();
                    $('#crm-email-sec-pane-' + targetDoc).fadeIn(150);
                }
            }
        });

        // Switch organizer tab via top pills
        $(document).on('click', '.crm-email-sec-pill', function(e) {
            e.preventDefault();
            $('.crm-email-sec-pill').removeClass('active').css({borderColor: '', color: '', fontWeight: 'normal'});
            $(this).addClass('active').css({borderColor: '#0284c7', color: '#0369a1', fontWeight: '600'});
            const targetDoc = $(this).data('doc');
            $('.crm-email-sec-tab-pane').hide();
            $('#crm-email-sec-pane-' + targetDoc).fadeIn(120);

            initEmailSortables();

            if (typeof loadEmailPreview === 'function') {
                loadEmailPreview(targetDoc, false);
            }
        });

        // ==========================================
        // E-MAIL SECTIONS DRAG & DROP CONTROLLER
        // ==========================================
        function initEmailSortables() {
            if (typeof $.fn.sortable !== 'undefined') {
                $('.crm-sortable-email-sections').sortable({
                    handle: '.crm-email-sec-drag-handle',
                    items: '> li.crm-email-section-item',
                    placeholder: 'crm-email-sec-sortable-placeholder',
                    axis: 'y',
                    cursor: 'grabbing',
                    opacity: 0.88,
                    tolerance: 'pointer'
                });
            }
        }
        initEmailSortables();

        // Initialize Email Section Sortables (unified with crm-admin.js)
        if (typeof window.initEmailSectionSortables === 'function') {
            window.initEmailSectionSortables();
        } else if (typeof $.fn.sortable !== 'undefined') {
            $('.crm-sortable-email-sections').sortable({
                handle: '.crm-email-sec-drag-handle',
                items: '> li.crm-email-section-item',
                placeholder: 'crm-email-sec-sortable-placeholder',
                axis: 'y',
                cursor: 'grabbing',
                opacity: 0.88,
                tolerance: 'pointer'
            });
        }

        // Save Email Sections Order
        $(document).on('click', '.crm-save-email-sections-btn', function(e) {
            e.preventDefault();
            const btn = $(this);
            const manager = btn.closest('.crm-email-sections-manager');
            const docType = manager.data('doc');
            const entryId = manager.data('entry') || 0;
            const statusEl = manager.find('.crm-email-sections-status');

            const sections = [];
            manager.find('> .crm-sortable-email-sections > .crm-email-section-item').each(function() {
                const item = $(this);
                const key = item.data('key') || item.attr('data-key');
                const enabled = item.find('> .crm-email-sec-header-row .crm-email-sec-checkbox').is(':checked') ? 1 : 0;
                const isCustom = (item.data('custom') == 1 || item.attr('data-custom') === '1') ? 1 : 0;
                let title = item.data('title') || item.find('.crm-email-sec-title-text').text().trim();
                const badge = item.data('badge') || '';
                const color = item.data('color') || '#0284c7';

                const $inputTitle = item.find('.crm-email-sec-input-title');
                const $inputContent = item.find('.crm-email-sec-input-content');
                if ($inputTitle.length && $inputTitle.val().trim()) {
                    title = $inputTitle.val().trim();
                }
                let content = '';
                if ($inputContent.length) {
                    content = $inputContent.val();
                }

                if (key) {
                    sections.push({
                        key: key,
                        enabled: enabled,
                        is_custom: isCustom,
                        title: title,
                        badge: badge,
                        color: color,
                        content: content
                    });
                }
            });

            btn.prop('disabled', true).text(i18n.saving || "Speichern...");
            const postNonce = window.crmPreviewNonce || (typeof crmData !== 'undefined' ? crmData.nonce : (crmSettings.nonce || ""));

            $.post(ajaxurl, {
                action: 'crm_save_email_section_order',
                nonce: postNonce,
                doc_type: docType,
                entry_id: entryId,
                sections: sections
            }, function(res) {
                btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="vertical-align:text-top; font-size:14px;"></span> ' + (i18n.applyEmailOrder || "E-Mail-Reihenfolge anwenden"));
                if (res.success) {
                    if (window.crmJsCache && typeof window.crmJsCache.cleanPartial === 'function') {
                        window.crmJsCache.cleanPartial('email_' + docType);
                    }
                    statusEl.text('✓ ' + (i18n.saved || "Gespeichert!")).css({color: '#16a34a'}).fadeIn().delay(2000).fadeOut();
                    if (typeof loadEmailPreview === 'function') {
                        loadEmailPreview(docType, true);
                    }
                } else {
                    const msg = (res.data && res.data.message) ? res.data.message : (i18n.errorSaving || "Fehler beim Speichern");
                    statusEl.text(msg).css({color: '#dc2626'}).fadeIn().delay(2500).fadeOut();
                }
            }).fail(function() {
                btn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="vertical-align:text-top; font-size:14px;"></span> ' + (i18n.applyEmailOrder || "E-Mail-Reihenfolge anwenden"));
                statusEl.text(i18n.serverError || "Serverfehler").css({color: '#dc2626'}).fadeIn().delay(2500).fadeOut();
            });
        });

        // Reset Email Sections
        $(document).on('click', '.crm-reset-email-sections-btn', function(e) {
            e.preventDefault();
            const btn = $(this);
            const manager = btn.closest('.crm-email-sections-manager');
            const docType = manager.data('doc');
            const entryId = manager.data('entry') || 0;
            const isSidebar = manager.hasClass('crm-email-sections-sidebar') ? 1 : 0;
            const parentContainer = manager.parent();

            const postNonce = window.crmPreviewNonce || (typeof crmData !== 'undefined' ? crmData.nonce : (crmSettings.nonce || ""));
            btn.prop('disabled', true);

            $.post(ajaxurl, {
                action: 'crm_reset_email_section_order',
                nonce: postNonce,
                doc_type: docType,
                entry_id: entryId,
                is_sidebar: isSidebar
            }, function(res) {
                btn.prop('disabled', false);
                if (res.success && res.data && res.data.html) {
                    if (window.crmJsCache && typeof window.crmJsCache.cleanPartial === 'function') {
                        window.crmJsCache.cleanPartial('email_' + docType);
                    }
                    parentContainer.html(res.data.html);
                    initEmailSortables();
                    if (typeof loadEmailPreview === 'function') {
                        loadEmailPreview(docType, true);
                    }
                }
            }).fail(function() {
                btn.prop('disabled', false);
            });
        });

        // "Vorschau" button on individual E-Mail field block
        $(document).on('click', '.crm-preview-this-email', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const docType = $(this).attr('data-doc') || 'angebot';
            loadEmailPreview(docType, false);
            if ($('#crm-email-preview-section').length) {
                $('html, body').animate({
                    scrollTop: $('#crm-email-preview-section').offset().top - 40
                }, 350);
            }
        });

        // Placeholder chip insertion into editor in field blocks
        $(document).on('click', '.crm-insert-chip-to-editor', function(e) {
            e.preventDefault();
            const code = $(this).attr('data-code');
            const fieldBlock = $(this).closest('.crm-field-block');
            const textarea = fieldBlock.find('textarea[name*="[content]"]');
            if (!textarea.length || !code) return;

            const editorId = textarea.attr('id');
            let inserted = false;

            // 1. Try TinyMCE if active and not in HTML mode
            if (typeof tinymce !== 'undefined') {
                const editor = tinymce.get(editorId);
                if (editor && !editor.isHidden()) {
                    editor.execCommand('mceInsertContent', false, code);
                    inserted = true;
                }
            }

            // 2. Fallback to raw textarea cursor insertion
            if (!inserted && textarea.length) {
                const domEl = textarea[0];
                const startPos = domEl.selectionStart || 0;
                const endPos = domEl.selectionEnd || 0;
                const val = domEl.value;
                domEl.value = val.substring(0, startPos) + code + val.substring(endPos);
                domEl.selectionStart = domEl.selectionEnd = startPos + code.length;
                domEl.focus();
                $(domEl).trigger('input').trigger('change');
            }

            // Visual feedback on chip button
            const $btn = $(this);
            $btn.css({'background-color': '#0284c7', 'color': '#ffffff', 'border-color': '#0284c7'});
            setTimeout(function() {
                $btn.css({'background-color': '#ffffff', 'color': '#0369a1', 'border-color': '#7dd3fc'});
            }, 300);
        });

        // Placeholder chip insertion in Section manager
        $(document).on('click', '.crm-email-insert-chip', function(e) {
            e.preventDefault();
            const code = $(this).attr('data-code');
            const textarea = $(this).closest('.crm-email-sec-body-row').find('.crm-email-sec-input-content');
            if (textarea.length && code) {
                const domEl = textarea[0];
                const startPos = domEl.selectionStart || 0;
                const endPos = domEl.selectionEnd || 0;
                const val = domEl.value;
                domEl.value = val.substring(0, startPos) + code + val.substring(endPos);
                domEl.selectionStart = domEl.selectionEnd = startPos + code.length;
                domEl.focus();
                $(domEl).trigger('input').trigger('change');
            }
        });

        // Manual Purge JS Cache
        $('#crm-btn-manual-purge-cache').on('click', function (e) {
            e.preventDefault();
            const btn = $(this);
            const status = $('#crm-cache-purge-status');
            const origHtml = btn.html();

            btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + (i18n.clearingCache || "Leeren..."));
            status.text(i18n.cacheInvalidating || "Cache wird invalidiert...").css({ color: '#0284c7' }).fadeIn();

            const postNonce = window.crmPreviewNonce || (typeof crmData !== 'undefined' ? crmData.nonce : (crmSettings.nonce || ""));

            $.post(ajaxurl, {
                action: 'crm_clear_js_cache',
                nonce: postNonce
            }, function (res) {
                btn.prop('disabled', false).html(origHtml);
                if (res.success) {
                    if (window.crmJsCache && typeof window.crmJsCache.cleanAll === 'function') {
                        window.crmJsCache.cleanAll();
                    }
                    const msg = (res.data && res.data.message) ? res.data.message : (i18n.cacheCleared || "JS-Cache erfolgreich geleert!");
                    status.text('✓ ' + msg).css({ color: '#16a34a' }).fadeIn().delay(3500).fadeOut();
                    if (res.data && res.data.asset_ver) {
                        $('#crm-current-asset-version').text(res.data.asset_ver);
                    }
                    if (res.data && res.data.new_version) {
                        $('#crm-current-cache-buster').text(res.data.new_version);
                    }
                } else {
                    const msg = (res.data && res.data.message) ? res.data.message : (i18n.cacheError || "Fehler beim Leeren.");
                    status.text('✗ ' + msg).css({ color: '#dc2626' }).fadeIn().delay(4000).fadeOut();
                }
            }).fail(function () {
                btn.prop('disabled', false).html(origHtml);
                status.text('✗ ' + (i18n.serverError || "Serverfehler beim Leeren.")).css({ color: '#dc2626' }).fadeIn().delay(4000).fadeOut();
            });
        });

        // Initial preview load if on PDF tab
        if ($('#crm-pdf-preview-section').length) {
            const initialDoc = $('.crm-sec-pill.active').length
                ? $('.crm-sec-pill.active').attr('data-doc')
                : 'angebot';
            loadPdfPreview(initialDoc, false);
        }

        // Initial preview load if on Email tab
        if ($('#crm-email-preview-section').length) {
            const initialEmailDoc = $('.crm-email-sec-pill.active').length
                ? $('.crm-email-sec-pill.active').attr('data-doc')
                : 'angebot';
            loadEmailPreview(initialEmailDoc, false);
        }
    });

})(jQuery);
