// crm-admin.js
// =============================================================================
// NEXUS CRM JS CACHE OPERATOR — C(X) Idempotent State Operator | V2.18.12
// High-performance client-side cache & automatic cleaner for partial updates
// =============================================================================
(function (window) {
    const isFlagActive = function () {
        if (typeof crmData !== 'undefined' && typeof crmData.autoJsCacheClean !== 'undefined') {
            return Boolean(crmData.autoJsCacheClean);
        }
        return true;
    };

    window.crmJsCache = {
        flag: isFlagActive(),
        cache: new Map(),

        isFlagActive: function () {
            if (typeof crmData !== 'undefined' && typeof crmData.autoJsCacheClean !== 'undefined') {
                return Boolean(crmData.autoJsCacheClean);
            }
            return Boolean(this.flag);
        },

        setFlag: function (enabled) {
            this.flag = Boolean(enabled);
            if (typeof crmData !== 'undefined') {
                crmData.autoJsCacheClean = this.flag;
            }
            console.log('[CRM Cache] Flag toggled to:', this.flag);
        },

        get: function (key) {
            const entry = this.cache.get(key);
            if (!entry) return null;
            return entry.data;
        },

        set: function (key, data) {
            this.cache.set(key, { data: data, timestamp: Date.now() });
        },

        has: function (key) {
            return this.cache.has(key);
        },

        /**
         * Automatisches JS Cache Clean:
         * Wird AUSSCHLIESSLICH bei partiellem Cache-Update ausgeführt, wenn Flag aktiv ist.
         */
        cleanPartial: function (componentKey, options) {
            options = options || {};
            if (!this.isFlagActive()) {
                console.log('[CRM Cache] Partial update detected, but automatic JS cache clean is DISABLED by flag.');
                return false;
            }

            const freshTimestamp = Date.now();
            let clearedCount = 0;

            // 1. In-Memory Cache Invalidation for matching component
            if (componentKey) {
                const normKey = String(componentKey).toLowerCase();
                for (let k of Array.from(this.cache.keys())) {
                    if (k.toLowerCase().indexOf(normKey) !== -1 || k.startsWith('preview_') || k.startsWith('partial_')) {
                        this.cache.delete(k);
                        clearedCount++;
                    }
                }
            } else {
                clearedCount = this.cache.size;
                this.cache.clear();
            }

            // 2. SessionStorage / LocalStorage partial cleanup
            try {
                if (typeof sessionStorage !== 'undefined') {
                    Object.keys(sessionStorage).forEach(function (k) {
                        if (k.startsWith('crm_') && (!componentKey || k.indexOf(componentKey) !== -1)) {
                            sessionStorage.removeItem(k);
                            clearedCount++;
                        }
                    });
                }
            } catch (e) { }

            // 3. Update preview iframe/embed cache busters (SWR freshness)
            try {
                const embeds = document.querySelectorAll('#x-sieben-pdf-preview embed, embed[data-crm-pdf]');
                embeds.forEach(function (emb) {
                    if (emb.src) {
                        const cleanUrl = emb.src.split('?')[0];
                        emb.src = cleanUrl + '?t=' + freshTimestamp + '&cv=' + freshTimestamp;
                    }
                });

                const emailFrame = document.getElementById('crm-email-preview-iframe');
                if (emailFrame && emailFrame.src) {
                    let curSrc = emailFrame.src.replace(/([?&])(t|reload|cv)=[^&]*/g, '');
                    let glue = curSrc.indexOf('?') === -1 ? '?' : '&';
                    emailFrame.src = curSrc + glue + 'cv=' + freshTimestamp + '&reload=1';
                }
            } catch (e) { }

            const versionStr = (typeof crmData !== 'undefined' && crmData.cacheVersion) ? crmData.cacheVersion : freshTimestamp;
            console.log(`[CRM Cache] ✓ Automatisches JS-Cache-Clean durchgeführt für: "${componentKey || 'all'}" (Flag: AKTIV, Keys bereinigt: ${clearedCount}, Version: ${versionStr})`);

            // 4. Dispatch Custom Event for external listeners
            document.dispatchEvent(new CustomEvent('crm:js-cache-cleaned', {
                detail: {
                    component: componentKey,
                    timestamp: freshTimestamp,
                    clearedCount: clearedCount
                }
            }));

            return true;
        },

        cleanAll: function () {
            return this.cleanPartial(null);
        }
    };
})(window);

document.addEventListener("DOMContentLoaded", function () {
    // --- Variables ---
    const table = document.querySelector(".js-sort-table");
    const searchInput = document.getElementById("courseTableSearch");
    const ajaxUrl = (typeof crmData !== 'undefined' && crmData.ajaxUrl) ? crmData.ajaxUrl : ((typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php');
    const nonce = (typeof crmData !== 'undefined' && crmData.nonce) ? crmData.nonce : '';
    const noticeContainer = document.getElementById('crm-ajax-notice-container');
    const detailsContainer = document.getElementById('crm-entry-details-container');
    const listView = document.getElementById('crm-list-view');
    const editorView = document.getElementById('crm-editor-view');
    const editorClientName = document.getElementById('crm-editor-client-name-val');
    const editorCourseTitle = document.getElementById('crm-editor-course-title-val');
    const editorEntryId = document.getElementById('crm-editor-entry-id-val');
    let activeEntryId = null;

    /**
     * Switch from Screen 1 (List) to Screen 2 (Editor / Workspace)
     */
    function openEditorView(row, actionKey) {
        if (!editorView || !listView) return;
        activeEntryId = row ? row.dataset.entryId : null;

        const clientName = row ? (row.dataset.clientName || 'Kunde') : 'Kunde';
        const courseTitle = row ? (row.dataset.courseTitle || 'Kurs') : 'Kurs';
        const entryId = row ? row.dataset.entryId : '---';

        if (editorClientName) editorClientName.textContent = clientName;
        if (editorCourseTitle) editorCourseTitle.textContent = courseTitle;
        if (editorEntryId) editorEntryId.textContent = entryId;

        // Hide List, Show Editor
        listView.style.display = 'none';
        editorView.style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /**
     * Switch from Screen 2 (Editor / Workspace) back to Screen 1 (List)
     */
    function closeEditorView() {
        if (!editorView || !listView) return;

        editorView.style.display = 'none';
        listView.style.display = 'block';

        // Scroll back to active row and give a subtle visual pulse
        if (activeEntryId) {
            const targetRow = document.querySelector('tr.crm-entry-row[data-entry-id="' + activeEntryId + '"]');
            if (targetRow) {
                targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                targetRow.classList.add('crm-row-highlight');
                setTimeout(() => targetRow.classList.remove('crm-row-highlight'), 2000);
            }
        }
    }

    // Expose globally
    window.crmOpenEditorView = openEditorView;
    window.crmCloseEditorView = closeEditorView;

    // Return to list on any back button click
    document.addEventListener('click', function (e) {
        if (e.target.closest('.crm-back-to-list-btn')) {
            e.preventDefault();
            closeEditorView();
        }
    });

    // Escape key handling
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const modalBackdrop = document.getElementById('crm-history-modal-backdrop');
            if (modalBackdrop && modalBackdrop.style.display === 'flex') {
                return;
            }
            if (editorView && editorView.style.display !== 'none') {
                const activeEl = document.activeElement;
                const isTyping = activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.isContentEditable);
                if (!isTyping) {
                    closeEditorView();
                }
            }
        }
    });

    if (table) {
        // --- Client-side Search & Status Filter ---
        const tableRows = table.querySelectorAll("tbody tr");
        const statusFilter = document.getElementById("crmStatusFilter");

        const updatePlaceholder = () => {
            const visibleRows = Array.from(tableRows).filter(row => row.style.display !== "none").length;
            if (searchInput) {
                searchInput.placeholder = `Search (${visibleRows} / ${tableRows.length})...`;
            }
        };

        const filterRows = () => {
            const searchVal = (searchInput ? searchInput.value : "").toLowerCase().trim();
            const statusVal = (statusFilter ? statusFilter.value : "").toLowerCase().trim();

            tableRows.forEach(row => {
                const text = row.innerText.toLowerCase();
                const matchesSearch = !searchVal || text.includes(searchVal);
                const statusCell = row.querySelector('.crm-status-cell');
                const statusText = statusCell ? statusCell.innerText.toLowerCase() : '';
                const matchesStatus = !statusVal || statusText.includes(statusVal);

                row.style.display = (matchesSearch && matchesStatus) ? "" : "none";
            });
            updatePlaceholder();
        };

        if (searchInput) {
            searchInput.addEventListener("input", filterRows);
        }
        if (statusFilter) {
            statusFilter.addEventListener("change", filterRows);
        }
        updatePlaceholder();

        // --- Client-side Sorting ---
        const getCellValue = (tr, idx) => {
            const cell = tr.children[idx];
            if (!cell) return '';
            return cell.dataset.sort || cell.innerText.trim();
        };
        const comparer = (idx, asc) => (a, b) =>
            getCellValue(a, idx).localeCompare(getCellValue(b, idx), 'de', { numeric: true }) * (asc ? 1 : -1);

        table.querySelectorAll("th").forEach((th, idx) => {
            if (idx >= table.querySelector("th").length - 1) return;

            const indicator = document.createElement("span");
            indicator.className = "sort-indicator";
            indicator.textContent = " ⇅";
            th.appendChild(indicator);
            th.style.cursor = 'pointer';

            th.addEventListener("click", () => {
                const tbody = table.querySelector("tbody");
                const asc = th.dataset.sort !== "asc";
                th.dataset.sort = asc ? "asc" : "desc";

                table.querySelectorAll("th").forEach(otherTh => {
                    const otherIndicator = otherTh.querySelector('.sort-indicator');
                    if (otherIndicator) {
                        otherIndicator.textContent = otherTh === th ? (asc ? " ↑" : " ↓") : " ⇅";
                    }
                });

                Array.from(tbody.querySelectorAll("tr"))
                    .sort(comparer(idx, asc))
                    .forEach(tr => tbody.appendChild(tr));
            });
        });

        // --- AJAX Notices Helper ---
        const displayNotice = (message, type = 'success') => {
            const notice = document.createElement('div');
            notice.className = `notice notice-${type} is-dismissible`;
            notice.innerHTML = `<p>${message}</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>`;
            noticeContainer.innerHTML = '';
            noticeContainer.appendChild(notice);

            notice.querySelector('.notice-dismiss').addEventListener('click', () => notice.remove());
        };

        // --- CRM Table AJAX Actions & More Actions Menu ---
        table.addEventListener('click', function (e) {
            // Toggle "more actions" dropdown
            const toggleBtn = e.target.closest('.crm-more-toggle-btn');
            if (toggleBtn) {
                e.preventDefault();
                e.stopPropagation();
                const dropdown = toggleBtn.closest('.crm-more-actions-dropdown');
                if (dropdown) {
                    const isOpen = dropdown.classList.contains('is-open');
                    document.querySelectorAll('.crm-more-actions-dropdown.is-open').forEach(d => {
                        if (d !== dropdown) d.classList.remove('is-open');
                    });
                    dropdown.classList.toggle('is-open', !isOpen);
                }
                return;
            }

            const button = e.target.closest('.crm-action-btn');
            if (!button) return;

            // Close more actions menu if clicked an item inside
            const parentDropdown = button.closest('.crm-more-actions-dropdown');
            if (parentDropdown) {
                parentDropdown.classList.remove('is-open');
            }

            const actionKey = button.dataset.action;
            const entryId = button.dataset.entryId;
            const courseId = button.dataset.courseId;
            const context = button.dataset.context; // Get the context from the button
            const row = button.closest('tr.crm-entry-row');

            // Open Screen 2 (Editor View)
            openEditorView(row, actionKey);

            button.disabled = true;
            const originalText = button.textContent;
            button.textContent = '...';

            detailsContainer.innerHTML = '<div style="padding:40px 20px; text-align:center; color:#64748b;"><span class="dashicons dashicons-update spin" style="font-size:32px; width:32px; height:32px; margin-bottom:12px;"></span><br><strong style="font-size:15px; color:#1e293b;">Dokument wird vorbereitet...</strong><p style="margin-top:6px; font-size:13px; color:#64748b;">PDF-Vorschau und Optionen werden geladen.</p></div>';
            detailsContainer.style.display = 'block';

            const formData = new FormData();
            formData.append('action', 'crm_entry_action');
            formData.append('nonce', nonce);
            formData.append('action_key', actionKey);
            formData.append('entry_id', entryId);
            formData.append('course_id', courseId);
            formData.append('context', context); // Add the context to the form data

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.data.output) {
                            detailsContainer.innerHTML = data.data.output;
                            detailsContainer.style.display = 'block';
                            initPdfSectionSortables();
                        } else {
                            detailsContainer.innerHTML = '<div style="padding:40px 20px; text-align:center; color:#16a34a;"><span class="dashicons dashicons-yes-alt" style="font-size:36px; width:36px; height:36px; margin-bottom:10px;"></span><br><strong style="font-size:15px; color:#1e293b;">' + (data.data.message || 'Aktion erfolgreich ausgeführt.') + '</strong></div>';
                            detailsContainer.style.display = 'block';
                        }
                        displayNotice(data.data.message, 'success');
                    } else {
                        displayNotice(data.data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('CRM Action Error:', error);
                    displayNotice('An unexpected error occurred. Please check the console.', 'error');
                })
                .finally(() => {
                    button.disabled = false;
                    button.textContent = originalText;
                });
        });

        // Close more actions on outside click
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.crm-more-actions-dropdown')) {
                document.querySelectorAll('.crm-more-actions-dropdown.is-open').forEach(d => d.classList.remove('is-open'));
            }
        });

        // --- CRM Status Quick Change via Dropdown ---
        table.addEventListener('change', function (e) {
            if (!e.target.classList.contains('crm-status-dropdown')) return;

            const select = e.target;
            const entryId = select.dataset.entryId;
            const newStatus = select.value;
            const newLabel = select.options[select.selectedIndex] ? select.options[select.selectedIndex].text : newStatus;
            const row = select.closest('tr');
            const pill = select.closest('.crm-status-pill');
            const labelEl = pill ? pill.querySelector('.crm-status-label') : null;
            const badgeWrap = row ? row.querySelector('.crm-status-badge-wrap') : null;
            const dateVal = row ? row.querySelector('.crm-status-date-val') : null;

            // Immediate visual feedback (<1ms)
            if (labelEl) {
                labelEl.textContent = newLabel;
            }
            if (pill) {
                pill.className = pill.className.replace(/\bcrm-status-[a-z0-9_-]+\b/g, '').trim();
                pill.classList.add('crm-status-' + newStatus);
                pill.classList.add('crm-status-loading');
                pill.setAttribute('data-status', newStatus);
            }

            select.disabled = true;

            const formData = new FormData();
            formData.append('action', 'crm_update_entry_status');
            formData.append('nonce', nonce);
            formData.append('entry_id', entryId);
            formData.append('course_id', row ? (row.dataset.courseId || 0) : 0);
            formData.append('status_key', newStatus);

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (window.crmJsCache && typeof window.crmJsCache.cleanPartial === 'function') {
                            window.crmJsCache.cleanPartial('status_' + entryId);
                        }
                        if (pill && data.data.status_key) {
                            pill.className = pill.className.replace(/\bcrm-status-[a-z0-9_-]+\b/g, '').trim();
                            pill.classList.add('crm-status-' + data.data.status_key);
                            pill.setAttribute('data-status', data.data.status_key);
                        }
                        if (labelEl && data.data.status_label) {
                            labelEl.textContent = data.data.status_label;
                        }
                        if (badgeWrap && data.data.badge_html) {
                            badgeWrap.innerHTML = data.data.badge_html;
                        }
                        if (dateVal && data.data.date_formatted) {
                            dateVal.textContent = data.data.date_formatted;
                        }
                        if (data.data.actions_html) {
                            const actionsCell = row ? row.querySelector('.crm-actions') : null;
                            if (actionsCell) {
                                actionsCell.innerHTML = data.data.actions_html;
                            }
                        }
                        displayNotice(data.data.message, 'success');
                    } else {
                        displayNotice((data.data && data.data.message) ? data.data.message : 'Fehler beim Ändern des Status.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Status Update Error:', err);
                    displayNotice('Status konnte nicht aktualisiert werden.', 'error');
                })
                .finally(() => {
                    select.disabled = false;
                    if (pill) {
                        pill.classList.remove('crm-status-loading');
                    }
                });
        });

        // --- CRM Status History Timeline Modal ---
        table.addEventListener('click', function (e) {
            const histBtn = e.target.closest('.crm-history-btn');
            if (!histBtn) return;

            const entryId = histBtn.dataset.entryId;
            const modalBackdrop = document.getElementById('crm-history-modal-backdrop');
            const modalContent = document.getElementById('crm-history-modal-content');

            if (!modalBackdrop || !modalContent) return;

            modalContent.innerHTML = '<p style="text-align:center; padding:20px; color:#64748b;">⏳ Verlauf wird geladen...</p>';
            modalBackdrop.style.display = 'flex';

            const formData = new FormData();
            formData.append('action', 'crm_get_entry_history');
            formData.append('nonce', nonce);
            formData.append('entry_id', entryId);

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data.html) {
                        modalContent.innerHTML = data.data.html;
                        const closeBtn = modalContent.querySelector('.crm-close-modal');
                        if (closeBtn) {
                            closeBtn.addEventListener('click', () => {
                                modalBackdrop.style.display = 'none';
                            });
                        }
                    } else {
                        modalContent.innerHTML = '<p style="color:#d63638; padding:15px;">' + (data.data?.message || 'Verlauf konnte nicht geladen werden.') + '</p>';
                    }
                })
                .catch(err => {
                    console.error('History Fetch Error:', err);
                    modalContent.innerHTML = '<p style="color:#d63638; padding:15px;">Verlauf konnte nicht geladen werden.</p>';
                });
        });

        // --- CRM Modals Close Handlers ---
        const modalBackdrop = document.getElementById('crm-history-modal-backdrop');
        const snapshotsBackdrop = document.getElementById('crm-snapshots-modal-backdrop');
        const snapshotsContent = document.getElementById('crm-snapshots-modal-content');
        const snapshotDetailBackdrop = document.getElementById('crm-snapshot-detail-modal-backdrop');
        const snapshotDetailTitle = document.getElementById('crm-snapshot-detail-title');
        const snapshotDetailBody = document.getElementById('crm-snapshot-detail-body');

        if (modalBackdrop) {
            modalBackdrop.addEventListener('click', function (e) {
                if (e.target === modalBackdrop) {
                    modalBackdrop.style.display = 'none';
                }
            });
        }

        // --- Document Snapshots Archive Modal Logic ---
        function escapeHtmlHelper(str) {
            if (typeof str !== 'string') return str;
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function renderJsonSnapshotHtml(data) {
            if (!data || typeof data !== 'object') {
                return '<p style="color:#64748b; padding:12px;">Keine Daten vorhanden.</p>';
            }

            const labels = {
                'doc_type': 'Dokumenttyp',
                'client_name': 'Kunde Name',
                'client_company': 'Unternehmen',
                'client_email': 'E-Mail',
                'client_phone': 'Telefon',
                'client_address': 'Adresse',
                'client_sv_nr': 'SV-Nummer',
                'course_title': 'Kurstitel',
                'course_type': 'Kursart',
                'start_date': 'Startdatum',
                'end_date': 'Enddatum',
                'course_times': 'Kurszeiten',
                'duration': 'Dauer',
                'ue_units': 'Unterrichtseinheiten (UE)',
                'location': 'Kursort',
                'trainer': 'Trainer',
                'price_netto': 'Kursgebühr Netto',
                'price_brutto': 'Kursgebühr Brutto',
                'vat_rate': 'USt-Satz',
                'discount': 'Rabatt / Aktion',
                'final_price': 'Endbetrag',
                'created_at': 'Snapshot archiviert am'
            };

            let rows = '';
            for (const [key, label] of Object.entries(labels)) {
                if (data[key] !== undefined && data[key] !== null && String(data[key]).trim() !== '') {
                    rows += '<tr>' +
                        '<td style="padding:9px 14px; font-weight:600; color:#334155; width:36%; border-bottom:1px solid #f1f5f9; background:#f8fafc;">' + escapeHtmlHelper(label) + '</td>' +
                        '<td style="padding:9px 14px; color:#0f172a; border-bottom:1px solid #f1f5f9;">' + escapeHtmlHelper(String(data[key])) + '</td>' +
                    '</tr>';
                }
            }

            for (const [key, val] of Object.entries(data)) {
                if (!labels[key] && key !== 'raw' && val !== undefined && val !== null && String(val).trim() !== '') {
                    rows += '<tr>' +
                        '<td style="padding:9px 14px; font-weight:600; color:#64748b; width:36%; border-bottom:1px solid #f1f5f9; background:#f8fafc;">' + escapeHtmlHelper(key) + '</td>' +
                        '<td style="padding:9px 14px; color:#0f172a; border-bottom:1px solid #f1f5f9;">' + escapeHtmlHelper(typeof val === 'object' ? JSON.stringify(val) : String(val)) + '</td>' +
                    '</tr>';
                }
            }

            const jsonPretty = JSON.stringify(data, null, 2);

            return '<div style="border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; margin-bottom:16px;">' +
                '<table style="width:100%; border-collapse:collapse; font-size:13px; text-align:left;">' +
                    '<tbody>' + rows + '</tbody>' +
                '</table>' +
            '</div>' +
            '<details style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px 14px;">' +
                '<summary style="cursor:pointer; font-weight:600; font-size:12px; color:#475569;">' +
                    '🔍 Vollständiges Rohdaten-JSON einsehen' +
                '</summary>' +
                '<pre style="margin-top:10px; background:#0f172a; color:#f8fafc; padding:14px; border-radius:6px; font-size:11.5px; overflow-x:auto; line-height:1.4;">' + escapeHtmlHelper(jsonPretty) + '</pre>' +
            '</details>';
        }

        // Open Snapshots Archive Modal on Click
        table.addEventListener('click', function (e) {
            const snapBtn = e.target.closest('.crm-snapshots-btn');
            if (!snapBtn) return;

            const entryId = snapBtn.dataset.entryId;
            if (!snapshotsBackdrop || !snapshotsContent) return;

            snapshotsContent.innerHTML = '<p style="text-align:center; padding:20px; color:#64748b;">⏳ Snapshots werden geladen...</p>';
            snapshotsBackdrop.style.display = 'flex';

            const formData = new FormData();
            formData.append('action', 'crm_get_entry_snapshots');
            formData.append('nonce', nonce);
            formData.append('entry_id', entryId);

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data.html) {
                        snapshotsContent.innerHTML = data.data.html;

                        // Synchronize badge count with live DB result
                        if (typeof data.data.count !== 'undefined') {
                            let badge = snapBtn.querySelector('.crm-snap-count-badge');
                            if (data.data.count > 0) {
                                if (!badge) {
                                    badge = document.createElement('span');
                                    badge.className = 'crm-snap-count-badge';
                                    snapBtn.appendChild(badge);
                                }
                                badge.textContent = data.data.count;
                            } else if (badge) {
                                badge.remove();
                            }
                        }
                    } else {
                        snapshotsContent.innerHTML = '<p style="color:#d63638; padding:15px;">' + (data.data?.message || 'Snapshots konnten nicht geladen werden.') + '</p>';
                    }
                })
                .catch(err => {
                    console.error('Snapshots Fetch Error:', err);
                    snapshotsContent.innerHTML = '<p style="color:#d63638; padding:15px;">Snapshots konnten nicht geladen werden.</p>';
                });
        });

        // Click delegates inside Snapshots Modal for E-Mail / Data preview
        if (snapshotsContent) {
            snapshotsContent.addEventListener('click', function (e) {
                // View E-Mail Snapshot
                const emailBtn = e.target.closest('.crm-btn-view-snapshot-email');
                if (emailBtn) {
                    const snapId = emailBtn.dataset.snapshotId;
                    const emailHolder = document.getElementById('crm-snapshot-email-' + snapId);
                    if (emailHolder && snapshotDetailBackdrop && snapshotDetailBody) {
                        const emailHtml = emailHolder.getAttribute('data-email-body') || '';
                        if (snapshotDetailTitle) {
                            snapshotDetailTitle.innerHTML = '<span class="dashicons dashicons-email-alt" style="color:#007C90; margin-right:6px;"></span> Revisionssicherer E-Mail-Inhalt (1:1 Snapshot)';
                        }
                        const iframe = document.createElement('iframe');
                        iframe.style.width = '100%';
                        iframe.style.height = '580px';
                        iframe.style.border = '1px solid #e2e8f0';
                        iframe.style.borderRadius = '6px';
                        iframe.style.background = '#ffffff';
                        snapshotDetailBody.innerHTML = '';
                        snapshotDetailBody.appendChild(iframe);
                        iframe.srcdoc = emailHtml;

                        snapshotDetailBackdrop.style.display = 'flex';
                    }
                    return;
                }

                // View Data Snapshot
                const dataBtn = e.target.closest('.crm-btn-view-snapshot-data');
                if (dataBtn) {
                    const snapId = dataBtn.dataset.snapshotId;
                    const dataHolder = document.getElementById('crm-snapshot-data-' + snapId);
                    if (dataHolder && snapshotDetailBackdrop && snapshotDetailBody) {
                        const jsonStr = dataHolder.getAttribute('data-snapshot-json') || '{}';
                        let parsed = {};
                        try {
                            parsed = JSON.parse(jsonStr);
                        } catch (err) {
                            parsed = { raw: jsonStr };
                        }
                        if (snapshotDetailTitle) {
                            snapshotDetailTitle.innerHTML = '<span class="dashicons dashicons-database" style="color:#007C90; margin-right:6px;"></span> Eingefrorene Konditionen & Daten (JSON-Snapshot)';
                        }
                        snapshotDetailBody.innerHTML = renderJsonSnapshotHtml(parsed);
                        snapshotDetailBackdrop.style.display = 'flex';
                    }
                    return;
                }
            });
        }

        // Close Snapshots Modal via Close Button or Backdrop Click
        if (snapshotsBackdrop) {
            snapshotsBackdrop.addEventListener('click', function (e) {
                if (e.target === snapshotsBackdrop || e.target.closest('.crm-close-snapshots-modal')) {
                    snapshotsBackdrop.style.display = 'none';
                }
            });
        }

        // Close Snapshot Detail Modal via Close Button or Backdrop Click
        if (snapshotDetailBackdrop) {
            snapshotDetailBackdrop.addEventListener('click', function (e) {
                if (e.target === snapshotDetailBackdrop || e.target.closest('.crm-close-snapshot-detail')) {
                    snapshotDetailBackdrop.style.display = 'none';
                }
            });
        }

        // Unified Escape Key Handler (hierarchical closing)
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (snapshotDetailBackdrop && snapshotDetailBackdrop.style.display === 'flex') {
                    snapshotDetailBackdrop.style.display = 'none';
                    e.stopPropagation();
                } else if (snapshotsBackdrop && snapshotsBackdrop.style.display === 'flex') {
                    snapshotsBackdrop.style.display = 'none';
                    e.stopPropagation();
                } else if (modalBackdrop && modalBackdrop.style.display === 'flex') {
                    modalBackdrop.style.display = 'none';
                    e.stopPropagation();
                }
            }
        });

    }

}); // End DOMContentLoaded
jQuery(document).ready(function ($) {
    // Handler for clicking the "E-Mail versenden" or "Test-Mail vorbereiten" button
    $('body').on('click', '.x-sieben-email-btn', function (e) {
        e.preventDefault();

        const pdfUrl = $(this).data('pdf');
        const courseId = $(this).data('course');
        const entryId = $(this).data('entry');
        const context = $(this).data('context');
        const focusTest = $(this).data('focus-test');

        // Remove any existing editor instance before loading new content
        if (typeof tinymce !== 'undefined' && tinymce.get('x_sieben_body')) {
            tinymce.get('x_sieben_body').remove();
        }

        $.ajax({
            url: xSiebenAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'x_sieben_load_mailer',
                pdf_url: pdfUrl,
                course_id: courseId,
                entry_id: entryId,
                context: context,
                security: xSiebenAjax.nonce
            },
            beforeSend: function () {
                $('#crm-entry-details-container').html('<p style="padding:15px; color:#64748b;">⏳ Mailer wird geladen...</p>');
            },
            success: function (response) {
                $('#crm-entry-details-container').html(response);

                // Initialize the TinyMCE editor with absolute URL settings
                if (typeof tinymce !== 'undefined') {
                    tinymce.init({
                        selector: '#x_sieben_body',
                        relative_urls: false,
                        remove_script_host: false,
                        convert_urls: false,
                        menubar: false,
                        toolbar: "undo redo | bold italic underline | bullist numlist | link unlink | code",
                        branding: false
                    });
                }

                // Re-initialize Quicktags (Text tab)
                if (typeof quicktags !== 'undefined') {
                    quicktags({ id: 'x_sieben_body' });
                }

                // If test button was clicked in preview, scroll to and highlight the test box
                if (focusTest) {
                    setTimeout(function () {
                        const testBox = document.getElementById('crm-test-mail-box');
                        if (testBox) {
                            testBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            testBox.style.transition = 'box-shadow 0.3s ease';
                            testBox.style.boxShadow = '0 0 0 3px #0284c7';
                            setTimeout(function () { testBox.style.boxShadow = ''; }, 1500);
                            const testInput = document.getElementById('x_sieben_test_recipient');
                            if (testInput) testInput.focus();
                        }
                    }, 350);
                }
            },
            error: function () {
                $('#crm-entry-details-container').html('<p style="color:#d63638; padding:15px;">Fehler beim Laden des Mailers. Bitte versuchen Sie es erneut.</p>');
            }
        });
    });

    // Helper to send either Live Mail or Test Mail
    function executeSendMail(isTestMode, triggerButton) {
        const $btn = $(triggerButton);
        const originalText = $btn.text();

        const recipient = ($('#x_sieben_recipient').val() || '').trim();
        const subject = ($('#x_sieben_subject').val() || '').trim();
        const pdf_url = $('#x_sieben_pdf_url').val();
        const course_id = $('#x_sieben_course_id').val();
        const entry_id = $('#x_sieben_entry_id').val();
        const context = $('#x_sieben_context').val();

        const testRecipient = ($('#x_sieben_test_recipient').val() || '').trim();
        const testModeType = $('input[name="x_sieben_test_mode_type"]:checked').val() || 'only_test';
        const prefixSubject = $('#x_sieben_test_prefix_subject').is(':checked') ? 1 : 0;

        if (isTestMode && (!testRecipient || !testRecipient.includes('@'))) {
            alert('Bitte geben Sie eine gültige Test-E-Mail-Adresse ein.');
            $('#x_sieben_test_recipient').focus();
            return;
        }

        if (!isTestMode && (!recipient || !recipient.includes('@'))) {
            alert('Bitte geben Sie eine gültige Kunden-E-Mail-Adresse ein.');
            $('#x_sieben_recipient').focus();
            return;
        }

        // Domain-Tippfehler-Prüfung (Heuristik für gängige Vertipper)
        const checkRecipient = isTestMode ? testRecipient : recipient;
        const typoMatch = checkRecipient.match(/@(?:[a-z0-9-]+\.)*(con|cmo|cm|ocm|gmai\.com|hotmial\.com|outlok\.com)$/i);
        if (typoMatch) {
            const typoEnding = typoMatch[1] || typoMatch[0];
            const typoWarning = '⚠️ ACHTUNG: Die E-Mail-Adresse "' + checkRecipient + '" enthält möglicherweise einen Tippfehler (' + typoEnding + ').\n\nMöchten Sie den Versand trotzdem fortsetzen?';
            if (!window.confirm(typoWarning)) {
                if (isTestMode) {
                    $('#x_sieben_test_recipient').focus();
                } else {
                    $('#x_sieben_recipient').focus();
                }
                return;
            }
        }

        // Sicherheitsabfrage vor verbindlichem Kundenversand (System 2 Kontrollschritt)
        if (!isTestMode) {
            const confirmPrompt = 'Möchten Sie diese E-Mail verbindlich an die offizielle Kunden-Adresse senden?\n\n' +
                                  '• Empfänger: ' + recipient + '\n' +
                                  '• Betreff: ' + (subject || '(Kein Betreff)') + '\n\n' +
                                  'Klicken Sie auf "OK", um den Versand unwiderruflich auszulösen.';
            if (!window.confirm(confirmPrompt)) {
                return;
            }
        }

        $btn.prop('disabled', true).text(isTestMode ? '🧪 Wird gesendet...' : 'Wird gesendet...');

        let body;
        if (typeof tinymce !== 'undefined' && tinymce.get('x_sieben_body')) {
            body = tinymce.get('x_sieben_body').getContent();
        } else {
            body = $('#x_sieben_body').val();
        }

        $.ajax({
            url: xSiebenAjax.ajax_url,
            method: 'POST',
            data: {
                action: 'x_sieben_send_mail',
                security: xSiebenAjax.nonce,
                x_sieben_recipient: recipient,
                x_sieben_subject: subject,
                x_sieben_body: body,
                x_sieben_pdf_url: pdf_url,
                course_id: course_id,
                entry_id: entry_id,
                context: context,
                is_test_mode: isTestMode ? 1 : 0,
                test_recipient: testRecipient,
                test_mode_type: testModeType,
                prefix_subject: prefixSubject
            },
            success: function (response) {
                if (response.success) {
                    const msg = (typeof response.data === 'object' && response.data.message) ? response.data.message : response.data;
                    const isTest = (typeof response.data === 'object' && response.data.is_test) || isTestMode;

                    // Build in-page success notice
                    const nowStr = new Date().toLocaleTimeString('de-AT', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    const noticeHtml = `
                        <div class="notice notice-success is-dismissible crm-action-notice" style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; background:#ecfdf5; border:1px solid #a7f3d0; border-left:5px solid #10b981; border-radius:6px; margin:12px 0 16px 0; box-shadow:0 2px 5px rgba(0,0,0,0.04); animation:crmFadeIn 0.3s ease-out;">
                            <div style="display:flex; align-items:flex-start; gap:10px;">
                                <span class="dashicons dashicons-yes-alt" style="color:#10b981; font-size:24px; width:24px; height:24px; margin-top:2px;"></span>
                                <div>
                                    <div style="color:#065f46; font-size:14px; font-weight:700;">
                                        ${isTest ? '🧪 Test-E-Mail erfolgreich versendet!' : '✉️ E-Mail erfolgreich an Kunden versendet!'}
                                    </div>
                                    <div style="color:#047857; font-size:12.5px; margin-top:3px; line-height:1.4;">
                                        ${msg}
                                    </div>
                                    <div style="color:#0f766e; font-size:11px; margin-top:4px;">
                                        Sendezeit: <strong>${nowStr}</strong>
                                    </div>
                                    ${!isTest ? `
                                    <div style="margin-top:10px;">
                                        <button type="button" class="button button-primary crm-back-to-list-btn" style="display:inline-flex; align-items:center; gap:6px; font-weight:600;">
                                            <span class="dashicons dashicons-arrow-left-alt" style="line-height:22px; font-size:16px;"></span>
                                            Zurück zur Anfragen-Übersicht
                                        </button>
                                    </div>` : ''}
                                </div>
                            </div>
                            <button type="button" class="crm-notice-close-btn" style="background:transparent; border:none; color:#047857; font-size:22px; line-height:1; cursor:pointer; padding:0 4px;" title="Hinweis schließen">&times;</button>
                        </div>
                    `;

                    // Inject notice right above the test box (if test) or mailer top (if live)
                    if (isTest) {
                        const testNotice = document.getElementById('crm-test-mail-notice');
                        if (testNotice) {
                            testNotice.innerHTML = noticeHtml;
                            testNotice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    } else {
                        const mailerNotice = document.getElementById('crm-mailer-notice');
                        if (mailerNotice) {
                            mailerNotice.innerHTML = noticeHtml;
                            mailerNotice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    }

                    // Also show in top global notice container
                    const globalNotice = document.getElementById('crm-ajax-notice-container');
                    if (globalNotice) {
                        globalNotice.innerHTML = noticeHtml;
                    }

                    // Real-time table row update for status & timestamp
                    if (typeof response.data === 'object' && response.data.entry_id && !response.data.is_test) {
                        const targetRow = document.querySelector('tr.crm-entry-row[data-entry-id="' + response.data.entry_id + '"]');
                        if (targetRow) {
                            const pill = targetRow.querySelector('.crm-status-pill');
                            const labelEl = targetRow.querySelector('.crm-status-label');
                            const dropdown = targetRow.querySelector('.crm-status-dropdown');
                            const dateVal = targetRow.querySelector('.crm-status-date-val');
                            const badgeWrap = targetRow.querySelector('.crm-status-badge-wrap');

                            if (response.data.status_key) {
                                if (pill) {
                                    pill.className = pill.className.replace(/\bcrm-status-[a-z0-9_-]+\b/g, '').trim();
                                    pill.classList.add('crm-status-' + response.data.status_key);
                                    pill.setAttribute('data-status', response.data.status_key);
                                }
                                if (dropdown) dropdown.value = response.data.status_key;
                            }
                            if (labelEl && response.data.status_label) {
                                labelEl.textContent = response.data.status_label;
                            }
                            if (badgeWrap && response.data.badge_html) {
                                badgeWrap.innerHTML = response.data.badge_html;
                            }
                            if (dateVal && response.data.date_formatted) {
                                dateVal.textContent = response.data.date_formatted;
                            }
                            if (response.data.actions_html) {
                                const actionsCell = targetRow.querySelector('.crm-actions');
                                if (actionsCell) {
                                    actionsCell.innerHTML = response.data.actions_html;
                                }
                            }
                        }
                    }

                    // Real-time update for snapshot count badge
                    const snapEntryId = (typeof response.data === 'object' && response.data.entry_id) ? response.data.entry_id : entry_id;
                    if (snapEntryId) {
                        const targetRowSnap = document.querySelector('tr.crm-entry-row[data-entry-id="' + snapEntryId + '"]');
                        if (targetRowSnap) {
                            const snapBtn = targetRowSnap.querySelector('.crm-snapshots-btn');
                            if (snapBtn) {
                                let badge = snapBtn.querySelector('.crm-snap-count-badge');
                                let currentCount = badge ? (parseInt(badge.textContent.trim(), 10) || 0) : 0;
                                currentCount++;
                                if (!badge) {
                                    badge = document.createElement('span');
                                    badge.className = 'crm-snap-count-badge';
                                    snapBtn.appendChild(badge);
                                }
                                badge.textContent = currentCount;
                                snapBtn.title = 'Dokument- & Daten-Archiv (' + currentCount + ' Snapshots)';
                            }
                        }
                    }
                } else {
                    const errMsg = (typeof response.data === 'object' && response.data.message) ? response.data.message : response.data;
                    const errHtml = `
                        <div class="notice notice-error is-dismissible crm-action-notice" style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; background:#fef2f2; border:1px solid #fecaca; border-left:5px solid #ef4444; border-radius:6px; margin:12px 0 16px 0;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <span class="dashicons dashicons-dismiss" style="color:#ef4444; font-size:24px; width:24px; height:24px;"></span>
                                <div style="color:#991b1b; font-size:13px;"><strong>Fehler:</strong> ${errMsg}</div>
                            </div>
                            <button type="button" class="crm-notice-close-btn" style="background:transparent; border:none; color:#991b1b; font-size:22px; line-height:1; cursor:pointer; padding:0 4px;" title="Hinweis schließen">&times;</button>
                        </div>
                    `;
                    const targetContainer = isTestMode ? document.getElementById('crm-test-mail-notice') : document.getElementById('crm-mailer-notice');
                    if (targetContainer) {
                        targetContainer.innerHTML = errHtml;
                        targetContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    } else {
                        alert('Fehler: ' + errMsg);
                    }
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX error:', xhr.responseText);
                alert('AJAX request failed. See console.');
            },
            complete: function () {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    }

    // Dismiss custom action notices
    $(document).on('click', '.crm-notice-close-btn', function () {
        $(this).closest('.crm-action-notice').fadeOut(200, function () { $(this).remove(); });
    });

    // Handler for clicking the "E-Mail an Kunden senden" button
    $(document).on('click', '#send-mail-btn', function (e) {
        e.preventDefault();
        executeSendMail(false, this);
    });

    // Handler for clicking the Test Mail buttons
    $(document).on('click', '#send-test-mail-btn, #send-test-mail-btn-bottom', function (e) {
        e.preventDefault();
        executeSendMail(true, this);
    });

    // =========================================================================
    // CRM PDF-Anhänge & Beilagen Manager (Interactive Selector & Media Picker)
    // =========================================================================

    /**
     * Synchronisiert alle aktiv angehakten PDF-Anhänge mit dem Hidden-Input #x_sieben_pdf_url,
     * dem Zähler-Badge und den Download-Links in der rechten Sidebar.
     */
    function crmSyncAttachmentsState() {
        const $checkedBoxes = $('#crm-attachments-list .crm-attachment-checkbox:checked');
        const urls = [];
        const activeDocs = [];

        $checkedBoxes.each(function () {
            const url = $(this).val();
            if (url && url.trim() !== '') {
                urls.push(url.trim());
                const $card = $(this).closest('.crm-attachment-card');
                const docType = $(this).data('doc-type') || '';
                const title = $card.find('strong').first().text() || 'PDF-Dokument';
                activeDocs.push({
                    url: url.trim(),
                    docType: docType,
                    title: title
                });
            }
        });

        // 1. Verstecktes Input aktualisieren
        const joinedUrls = urls.join(',');
        $('#x_sieben_pdf_url').val(joinedUrls);

        // 2. Zähler-Badge aktualisieren
        const count = urls.length;
        const $badge = $('#crm-attachments-count-badge');
        if ($badge.length) {
            if (count === 0) {
                $badge.text('Keine Anhänge (reine Text-Mail)').css({ background: '#94a3b8' });
            } else if (count === 1) {
                $badge.text('1 Anhang aktiv').css({ background: '#0f766e' });
            } else {
                $badge.text(count + ' Anhänge aktiv').css({ background: '#0f766e' });
            }
        }

        // 3. Rechte Sidebar Download-Buttons synchronisieren
        const $sidebarList = $('#crm-sidebar-attachments-list');
        if ($sidebarList.length) {
            $sidebarList.empty();
            if (activeDocs.length === 0) {
                $sidebarList.append('<li class="crm-sidebar-no-att" style="font-size:11.5px; color:#94a3b8; font-style:italic;">Keine Anhänge ausgewählt</li>');
            } else {
                activeDocs.forEach(function (doc) {
                    let label = doc.title;
                    if (doc.docType === 'agb') label = 'AGB 2025 herunterladen';
                    else if (doc.docType === 'kb') label = 'Kurszeiten (KB) herunterladen';
                    else if (doc.docType === 'angebot') label = 'Angebot herunterladen';
                    else if (doc.docType === 'tb') label = 'Teilnahmebestätigung herunterladen';
                    else if (doc.docType === 'diplom') label = 'Diplom herunterladen';
                    else if (doc.docType === 'invoice') label = 'Honorarnote herunterladen';
                    else if (!label.toLowerCase().includes('herunterladen')) label += ' herunterladen';

                    $sidebarList.append(
                        '<li><a href="' + doc.url + '" download class="button crm-sidebar-download-btn" data-url="' + doc.url + '" style="display:flex; align-items:center; gap:5px; font-size:12px; width:100%; justify-content:center;"><span class="dashicons dashicons-download"></span> ' + label + '</a></li>'
                    );
                });
            }
        }
    }

    // Checkbox Umschaltung: An-/Abwählen und On-Demand Generierung
    $(document).on('change', '.crm-attachment-checkbox', function () {
        const $checkbox = $(this);
        const $card = $checkbox.closest('.crm-attachment-card');
        const isChecked = $checkbox.is(':checked');
        const docType = $checkbox.data('doc-type');
        const currentUrl = $checkbox.val();

        if (isChecked) {
            // Falls noch keine URL vorhanden ist -> On-the-Fly generieren
            if (!currentUrl || currentUrl.trim() === '') {
                const $list = $('#crm-attachments-list');
                const entryId = $list.data('entry');
                const courseId = $list.data('course');

                $checkbox.prop('disabled', true);
                $card.addClass('is-loading').css({ opacity: 0.65 });
                $card.find('.crm-att-status-indicator').text('⏳ Wird generiert...').css({ color: '#0284c7' });

                $.ajax({
                    url: xSiebenAjax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'crm_generate_attachment_pdf',
                        doc_type: docType,
                        entry_id: entryId,
                        course_id: courseId,
                        security: xSiebenAjax.nonce
                    },
                    success: function (res) {
                        $checkbox.prop('disabled', false);
                        $card.removeClass('is-loading').css({ opacity: 1 });

                        if (res.success && res.data && res.data.pdf_url) {
                            $checkbox.val(res.data.pdf_url);
                            $card.attr('data-doc-url', res.data.pdf_url);
                            $card.addClass('is-attached').css({ 'border-color': '#0f766e', background: '#f0fdf4' });
                            $card.find('.crm-att-status-indicator').text('✓ Angehängt').css({ color: '#16a34a' });

                            const sizeInfo = res.data.filesize ? ' (' + res.data.filesize + ')' : '';
                            $card.find('.crm-att-filename-line').html('<span>📄 ' + (res.data.filename || 'PDF-Dokument') + sizeInfo + '</span>');

                            const $previewBtn = $card.find('.crm-att-preview-btn');
                            $previewBtn.attr('href', res.data.pdf_url).css('display', 'inline-flex');

                            crmSyncAttachmentsState();
                        } else {
                            $checkbox.prop('checked', false);
                            $card.removeClass('is-attached').css({ 'border-color': '#e2e8f0', background: '#f8fafc' });
                            $card.find('.crm-att-status-indicator').text('Fehler').css({ color: '#dc2626' });
                            alert('PDF konnte nicht generiert werden: ' + ((res.data && res.data.message) || 'Unbekannter Fehler'));
                        }
                    },
                    error: function () {
                        $checkbox.prop('disabled', false).prop('checked', false);
                        $card.removeClass('is-loading is-attached').css({ opacity: 1, 'border-color': '#e2e8f0', background: '#f8fafc' });
                        $card.find('.crm-att-status-indicator').text('Fehler').css({ color: '#dc2626' });
                        alert('Serverfehler beim Generieren des PDFs.');
                    }
                });
                return;
            }

            // Normales Aktivieren bei bestehender URL
            $card.addClass('is-attached').css({ 'border-color': '#0f766e', background: '#f0fdf4' });
            $card.find('.crm-att-status-indicator').text('✓ Angehängt').css({ color: '#16a34a' });
            $card.find('.crm-att-preview-btn').css('display', 'inline-flex');
        } else {
            // Deaktivieren
            $card.removeClass('is-attached').css({ 'border-color': '#e2e8f0', background: '#f8fafc' });
            $card.find('.crm-att-status-indicator').text('Nicht angehängt').css({ color: '#94a3b8' });
        }

        crmSyncAttachmentsState();
    });

    // Manuelles Hinzufügen von PDFs über die WordPress Mediathek
    $(document).on('click', '.crm-add-custom-attachment-btn', function (e) {
        e.preventDefault();

        let customMediaFrame;
        customMediaFrame = wp.media({
            title: 'PDF-Anhang für E-Mail auswählen oder hochladen',
            button: { text: 'Als Anhang beilegen' },
            library: { type: 'application/pdf' },
            multiple: true
        });

        customMediaFrame.on('select', function () {
            const selection = customMediaFrame.state().get('selection');
            const $list = $('#crm-attachments-list');

            selection.each(function (attachment) {
                const mediaData = attachment.toJSON();
                const fileUrl = mediaData.url;
                const fileName = mediaData.filename || mediaData.title || 'Anhang.pdf';
                const fileTitle = mediaData.title || fileName;
                const fileSize = mediaData.filesizeHumanReadable || '';

                // Prüfen ob URL schon in der Liste existiert
                let alreadyExists = false;
                $list.find('.crm-attachment-checkbox').each(function () {
                    if ($(this).val() === fileUrl) {
                        alreadyExists = true;
                        $(this).prop('checked', true).trigger('change');
                    }
                });

                if (alreadyExists) return;

                const customId = 'crm_att_custom_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
                const cardHtml = `
                    <div class="crm-attachment-card is-attached is-custom" 
                         data-doc-type="custom"
                         data-doc-url="${fileUrl}"
                         style="display:flex; align-items:center; justify-content:space-between; border:1px solid #0f766e; background:#f0fdf4; border-radius:6px; padding:8px 12px; transition:all 0.15s ease;">
                        <div style="display:flex; align-items:center; gap:10px; flex:1; min-width:0;">
                            <input type="checkbox" 
                                   class="crm-attachment-checkbox" 
                                   id="${customId}" 
                                   value="${fileUrl}" 
                                   data-doc-type="custom"
                                   checked="checked" 
                                   style="margin:0; width:17px; height:17px; cursor:pointer;">
                            <label for="${customId}" style="cursor:pointer; display:flex; align-items:center; gap:8px; margin:0; min-width:0;">
                                <span class="dashicons dashicons-paperclip" style="color:#4f46e5; font-size:18px;"></span>
                                <div style="min-width:0;">
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        <strong style="font-size:12.5px; color:#0f172a;">${fileTitle}</strong>
                                        <span class="crm-badge" style="background:#e0e7ff; color:#3730a3; font-size:10px; font-weight:600; padding:1px 6px; border-radius:10px;">📎 Mediathek</span>
                                        ${fileSize ? `<span style="font-size:11px; color:#64748b;">(${fileSize})</span>` : ''}
                                    </div>
                                    <div class="crm-att-filename-line" style="font-size:11px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:420px;">
                                        <span>📄 ${fileName}</span>
                                    </div>
                                </div>
                            </label>
                        </div>
                        <div class="crm-att-actions" style="display:flex; align-items:center; gap:6px; margin-left:12px;">
                            <span class="crm-att-status-indicator" style="font-size:11px; font-weight:600; color:#16a34a;">✓ Angehängt</span>
                            <a href="${fileUrl}" target="_blank" class="button button-small crm-att-preview-btn" style="display:inline-flex; align-items:center; gap:3px; font-size:11px; height:24px; line-height:22px; padding:0 7px;" title="PDF ansehen">
                                <span class="dashicons dashicons-visibility" style="font-size:13px; line-height:13px; width:13px; height:13px;"></span> Vorschau
                            </a>
                            <button type="button" class="button button-small crm-remove-custom-att-btn" style="color:#dc2626; height:24px; line-height:22px; padding:0 6px;" title="Anhang entfernen">
                                <span class="dashicons dashicons-trash" style="font-size:13px; line-height:13px; width:13px; height:13px;"></span>
                            </button>
                        </div>
                    </div>
                `;

                $list.append(cardHtml);
            });

            crmSyncAttachmentsState();
        });

        customMediaFrame.open();
    });

    // Entfernen eines manuell hinzugefügten Anhangs
    $(document).on('click', '.crm-remove-custom-att-btn', function (e) {
        e.preventDefault();
        const $card = $(this).closest('.crm-attachment-card');
        $card.fadeOut(150, function () {
            $(this).remove();
            crmSyncAttachmentsState();
        });
    });
});
jQuery(document).ready(function ($) {

    // Umschalten zwischen Visuell (TinyMCE) und Text (Quicktags)
    $('body').on('click', '.wp-switch-editor', function () {
        const textareaId = 'x_sieben_body';

        if ($(this).hasClass('switch-tmce')) {
            // TinyMCE aktivieren
            if (typeof tinymce !== 'undefined' && !tinymce.get(textareaId)) {
                tinymce.execCommand('mceAddEditor', true, textareaId);
            }
            $('#x-sieben-mail-editor')
                .removeClass('html-active')
                .addClass('tmce-active');
        } else {
            // TinyMCE deaktivieren → Quicktags aktiv
            if (typeof tinymce !== 'undefined' && tinymce.get(textareaId)) {
                tinymce.execCommand('mceRemoveEditor', true, textareaId);
            }
            $('#x-sieben-mail-editor')
                .removeClass('tmce-active')
                .addClass('html-active');
        }
    });

    // --- Diplom Abschluss-Erfolg Umschalten & Aktualisieren ---
    $('body').on('click', '.crm-btn-update-diplom-success', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const entryId = $btn.data('entry');
        const courseId = $btn.data('course');
        const successVal = $('input[name="crm_diplom_success_choice"]:checked').val() || 'erfolgreich';
        updateDiplomSuccess(entryId, courseId, successVal, $btn);
    });

    $('body').on('change', 'input.crm-diplom-success-radio', function () {
        const entryId = $(this).data('entry');
        const courseId = $(this).data('course');
        const successVal = $(this).val();
        const $btn = $('.crm-btn-update-diplom-success');
        updateDiplomSuccess(entryId, courseId, successVal, $btn);
    });

    function updateDiplomSuccess(entryId, courseId, successVal, $btn) {
        if (!entryId || !courseId) return;

        const originalHtml = $btn && $btn.length ? $btn.html() : '';
        if ($btn && $btn.length) {
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Aktualisiere...');
        }
        const $feedback = $('.crm-diplom-success-feedback');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'crm_update_diplom_success',
                nonce: nonce,
                entry_id: entryId,
                course_id: courseId,
                success_val: successVal
            },
            success: function (res) {
                if (res.success && res.data && res.data.pdf_url) {
                    const freshUrl = res.data.pdf_url + '?t=' + new Date().getTime();
                    // Update preview embed
                    const $embed = $('#x-sieben-pdf-preview embed');
                    if ($embed.length) {
                        $embed.attr('src', freshUrl);
                    }
                    // Update download button
                    const $downloadLink = $('#x-sieben-button-row a[download]');
                    if ($downloadLink.length) {
                        $downloadLink.attr('href', res.data.pdf_url);
                    }
                    // Update email button data-pdf
                    const $emailBtns = $('#x-sieben-button-row .x-sieben-email-btn');
                    $emailBtns.each(function () {
                        $(this).data('pdf', res.data.pdf_url).attr('data-pdf', res.data.pdf_url);
                    });

                    if ($feedback.length) {
                        $feedback.text('✓ ' + res.data.message).css('color', '#16a34a').fadeIn();
                        setTimeout(function () { $feedback.fadeOut(); }, 3500);
                    }
                } else {
                    alert((res.data && res.data.message) ? res.data.message : 'Fehler beim Aktualisieren des Diploms.');
                }
            },
            error: function () {
                alert('Netzwerkfehler beim Aktualisieren des Diploms.');
            },
            complete: function () {
                if ($btn && $btn.length) {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            }
        });
    }

    // ==========================================
    // PDF SECTIONS DRAG & DROP CONTROLLER (HIERARCHICAL)
    // ==========================================
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function initPdfSectionSortables() {
        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.sortable !== 'undefined') {
            jQuery('.crm-sortable-sections').sortable({
                handle: '.crm-section-drag-handle',
                items: '> li.crm-pdf-section-item',
                placeholder: 'crm-section-sortable-placeholder',
                axis: 'y',
                cursor: 'grabbing',
                opacity: 0.88,
                tolerance: 'pointer'
            });

            jQuery('.crm-sortable-subsections').sortable({
                handle: '.crm-sub-drag-handle',
                items: '> li.crm-pdf-subsection-item',
                placeholder: 'crm-sub-sortable-placeholder',
                axis: 'y',
                cursor: 'grabbing',
                opacity: 0.88,
                tolerance: 'pointer'
            });

            if (jQuery('#crm-fields-wrapper').length) {
                jQuery('#crm-fields-wrapper').sortable({
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
    window.initPdfSectionSortables = initPdfSectionSortables;
    initPdfSectionSortables();

    function crmGetHierarchicalSections($manager) {
        const sections = [];
        $manager.find('> .crm-sortable-sections > .crm-pdf-section-item').each(function () {
            const $sec = jQuery(this);
            const isCustom = ($sec.data('custom') == 1 || $sec.attr('data-custom') === '1') ? 1 : 0;
            const key = $sec.data('key') || $sec.attr('data-key');
            const enabled = $sec.find('> .crm-section-header-row .crm-section-checkbox').is(':checked') ? 1 : 0;
            const title = $sec.data('title') || $sec.find('.crm-section-title').text().trim();
            const badge = $sec.data('badge') || '';
            const color = $sec.data('color') || '';
            const content = $sec.data('content') || '';

            // Header & Footer settings
            let headerMode = $sec.find('.crm-hf-header-mode').val() || $sec.data('header-mode') || $sec.attr('data-header-mode') || 'master';
            let headerLogo = 0;
            const $hLogoCb = $sec.find('.crm-hf-header-logo');
            if ($hLogoCb.length) {
                headerLogo = $hLogoCb.is(':checked') ? 1 : 0;
            } else {
                headerLogo = ($sec.data('header-logo') == 1 || $sec.attr('data-header-logo') === '1') ? 1 : 0;
            }

            let headerAddress = 0;
            const $hAddrCb = $sec.find('.crm-hf-header-address');
            if ($hAddrCb.length) {
                headerAddress = $hAddrCb.is(':checked') ? 1 : 0;
            } else {
                headerAddress = ($sec.data('header-address') == 1 || $sec.attr('data-header-address') === '1') ? 1 : 0;
            }

            let headerCustom = '';
            const $hCustomInput = $sec.find('.crm-hf-header-custom');
            if ($hCustomInput.length) {
                headerCustom = $hCustomInput.val();
            } else {
                headerCustom = $sec.data('header-custom') || $sec.attr('data-header-custom') || '';
            }

            let footerMode = $sec.find('.crm-hf-footer-mode').val() || $sec.data('footer-mode') || $sec.attr('data-footer-mode') || 'master';
            let footerCompany = 0;
            const $fCompCb = $sec.find('.crm-hf-footer-company');
            if ($fCompCb.length) {
                footerCompany = $fCompCb.is(':checked') ? 1 : 0;
            } else {
                footerCompany = ($sec.data('footer-company') == 1 || $sec.attr('data-footer-company') === '1') ? 1 : 0;
            }

            let footerPageNum = 0;
            const $fPageCb = $sec.find('.crm-hf-footer-page-num');
            if ($fPageCb.length) {
                footerPageNum = $fPageCb.is(':checked') ? 1 : 0;
            } else {
                footerPageNum = ($sec.data('footer-page-num') == 1 || $sec.attr('data-footer-page-num') === '1') ? 1 : 0;
            }

            let footerDate = 0;
            const $fDateCb = $sec.find('.crm-hf-footer-date');
            if ($fDateCb.length) {
                footerDate = $fDateCb.is(':checked') ? 1 : 0;
            } else {
                footerDate = ($sec.data('footer-date') == 1 || $sec.attr('data-footer-date') === '1') ? 1 : 0;
            }

            let footerCustom = '';
            const $fCustomInput = $sec.find('.crm-hf-footer-custom');
            if ($fCustomInput.length) {
                footerCustom = $fCustomInput.val();
            } else {
                footerCustom = $sec.data('footer-custom') || $sec.attr('data-footer-custom') || '';
            }

            const subsections = [];
            $sec.find('.crm-sortable-subsections > .crm-pdf-subsection-item').each(function () {
                const $sub = jQuery(this);
                const subKey = $sub.data('sub-key') || $sub.attr('data-sub-key');
                const subEnabled = $sub.find('.crm-sub-checkbox').is(':checked') ? 1 : 0;
                const subCustom = ($sub.data('custom') == 1 || $sub.attr('data-custom') === '1') ? 1 : 0;
                let subTitle = $sub.data('title') || $sub.find('.crm-sub-title').text().trim();
                let subContent = $sub.data('content');
                if (typeof subContent === 'undefined') {
                    subContent = $sub.attr('data-content') || '';
                }

                // Drawer-Werte übernehmen (auch wenn Drawer vor dem Speichern wieder geschlossen wurde)
                const $drawer = $sub.find('> .crm-sub-edit-drawer');
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

                // Standard-Unterabschnitte bereinigen: Wenn Inhalt leer, {standard} oder Standard-Text ist, als leer ('') speichern
                const defaultContent = $sub.data('default-content') || $sub.attr('data-default-content') || '';
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
        return sections;
    }
    window.crmGetHierarchicalSections = crmGetHierarchicalSections;

    function updateSubsectionsCounter($sec) {
        const total = $sec.find('.crm-sortable-subsections > .crm-pdf-subsection-item').length;
        const active = $sec.find('.crm-sortable-subsections > .crm-pdf-subsection-item .crm-sub-checkbox:checked').length;
        $sec.find('.crm-subs-counter-badge').html(active + '/' + total + ' aktiv &#x25BE;');
    }

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
    jQuery(document).on('click', '.crm-toggle-hf-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $item = jQuery(this).closest('.crm-pdf-section-item');
        const $drawer = $item.find('> .crm-hf-drawer');
        $drawer.slideToggle(180);
    });

    // Section Header Mode Change
    jQuery(document).on('change', '.crm-hf-header-mode', function () {
        const $sec = jQuery(this).closest('.crm-pdf-section-item');
        const mode = jQuery(this).val();
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
    jQuery(document).on('change', '.crm-hf-header-logo, .crm-hf-header-address', function () {
        const $sec = jQuery(this).closest('.crm-pdf-section-item');
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
    jQuery(document).on('change', '.crm-hf-footer-mode', function () {
        const $sec = jQuery(this).closest('.crm-pdf-section-item');
        const mode = jQuery(this).val();
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

    // Footer Checkboxes change (manual override updates mode)
    jQuery(document).on('change', '.crm-hf-footer-company, .crm-hf-footer-page-num, .crm-hf-footer-date', function () {
        const $sec = jQuery(this).closest('.crm-pdf-section-item');
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
        } else if (!hasComp && !hasPage && !hasDate) {
            $mode.val('none');
        }
        $sec.find('.crm-hf-footer-custom-box').hide();
        $sec.attr('data-footer-mode', $mode.val()).data('footer-mode', $mode.val());
        updateHfSummaryBadge($sec);
    });

    // Master Header Mode in Settings Box
    jQuery(document).on('change', '.crm-master-header-mode', function () {
        const mode = jQuery(this).val();
        const $box = jQuery(this).closest('.crm-pdf-master-hf-box');
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

    // Master Footer Mode in Settings Box
    jQuery(document).on('change', '.crm-master-footer-mode', function () {
        const mode = jQuery(this).val();
        const $box = jQuery(this).closest('.crm-pdf-master-hf-box');
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

    // Accordion Toggle on Section Header Row
    jQuery(document).on('click', '.crm-section-header-row', function (e) {
        if (jQuery(e.target).closest('.crm-section-toggle-label, .crm-section-checkbox, .crm-section-actions, .crm-section-drag-handle, .crm-toggle-hf-btn').length) {
            return;
        }
        const $item = jQuery(this).closest('.crm-pdf-section-item');
        const $drawer = $item.find('> .crm-subsections-drawer');
        const $chevron = $item.find('.crm-section-chevron');

        if ($drawer.is(':visible')) {
            $drawer.slideUp(180);
            $chevron.html('&#x25B8;');
        } else {
            $drawer.slideDown(200);
            $chevron.html('&#x25BE;');
        }
    });

    // Section Checkbox toggle (active/disabled state)
    jQuery(document).on('change', '.crm-section-checkbox', function () {
        const $item = jQuery(this).closest('.crm-pdf-section-item');
        if (jQuery(this).is(':checked')) {
            $item.removeClass('is-disabled').addClass('is-active').css('opacity', '1');
        } else {
            $item.removeClass('is-active').addClass('is-disabled').css('opacity', '0.55');
        }
    });

    // Subsection Checkbox toggle
    jQuery(document).on('change', '.crm-sub-checkbox', function () {
        const $sub = jQuery(this).closest('.crm-pdf-subsection-item');
        const $sec = jQuery(this).closest('.crm-pdf-section-item');
        if (jQuery(this).is(':checked')) {
            $sub.removeClass('sub-disabled').addClass('sub-active').css('opacity', '1');
        } else {
            $sub.removeClass('sub-active').addClass('sub-disabled').css('opacity', '0.5');
        }
        updateSubsectionsCounter($sec);
    });

    // Move Up / Move Down for Sections
    jQuery(document).on('click', '.crm-move-up-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $item = jQuery(this).closest('.crm-pdf-section-item');
        const $prev = $item.prev('.crm-pdf-section-item');
        if ($prev.length) {
            $item.insertBefore($prev).hide().fadeIn(150);
        }
    });
    jQuery(document).on('click', '.crm-move-down-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $item = jQuery(this).closest('.crm-pdf-section-item');
        const $next = $item.next('.crm-pdf-section-item');
        if ($next.length) {
            $item.insertAfter($next).hide().fadeIn(150);
        }
    });

    // Move Up / Move Down for Subsections
    jQuery(document).on('click', '.crm-sub-move-up', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $sub = jQuery(this).closest('.crm-pdf-subsection-item');
        const $prev = $sub.prev('.crm-pdf-subsection-item');
        if ($prev.length) {
            $sub.insertBefore($prev).hide().fadeIn(150);
        }
    });
    jQuery(document).on('click', '.crm-sub-move-down', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $sub = jQuery(this).closest('.crm-pdf-subsection-item');
        const $next = $sub.next('.crm-pdf-subsection-item');
        if ($next.length) {
            $sub.insertAfter($next).hide().fadeIn(150);
        }
    });

    // Toggle Add Section Drawer
    jQuery(document).on('click', '.crm-toggle-add-section-btn', function (e) {
        e.preventDefault();
        const $manager = jQuery(this).closest('.crm-pdf-sections-manager');
        $manager.find('.crm-add-section-drawer').slideToggle(180);
    });
    jQuery(document).on('click', '.crm-cancel-add-sec-btn', function (e) {
        e.preventDefault();
        const $manager = jQuery(this).closest('.crm-pdf-sections-manager');
        $manager.find('.crm-add-section-drawer').slideUp(180);
    });

    // Create New Custom Section
    jQuery(document).on('click', '.crm-create-section-btn', function (e) {
        e.preventDefault();
        const $manager = jQuery(this).closest('.crm-pdf-sections-manager');
        const title = $manager.find('.crm-new-sec-title').val().trim();
        if (!title) {
            alert('Bitte einen Titel für den neuen Abschnitt eingeben.');
            $manager.find('.crm-new-sec-title').focus();
            return;
        }
        const badge = $manager.find('.crm-new-sec-badge').val().trim() || 'Zusatz';
        const color = $manager.find('.crm-new-sec-color').val() || '#007C90';
        const content = $manager.find('.crm-new-sec-content').val().trim();
        const key = 'custom_sec_' + Date.now();

        const secHtml = `
        <li class="crm-pdf-section-item is-active"
            data-key="${escapeHtml(key)}"
            data-custom="1"
            data-title="${escapeHtml(title)}"
            data-badge="${escapeHtml(badge)}"
            data-color="${escapeHtml(color)}"
            data-content="${escapeHtml(content)}"
            data-header-mode="master"
            data-header-logo="1"
            data-header-address="1"
            data-header-custom=""
            data-footer-mode="master"
            data-footer-company="1"
            data-footer-page-num="1"
            data-footer-date="0"
            data-footer-custom=""
            style="margin-bottom:9px; background:#ffffff; border:1px solid #cbd5e1; border-left:4px solid ${escapeHtml(color)}; border-radius:6px; box-shadow:0 1px 2px rgba(0,0,0,0.03); transition:all 0.15s ease;">
            <div class="crm-section-header-row" style="display:flex; align-items:center; gap:10px; padding:9px 12px; cursor:pointer;">
                <span class="crm-section-drag-handle" title="Ziehen zum Verschieben" style="color:#94a3b8; cursor:grab; font-size:16px; display:flex; align-items:center; user-select:none;">&#x2630;</span>
                <label class="crm-section-toggle-label" style="display:flex; align-items:center; margin:0; cursor:pointer;" onclick="event.stopPropagation();">
                    <input type="checkbox" class="crm-section-checkbox" value="1" checked style="margin:0; width:15px; height:15px; cursor:pointer;">
                </label>
                <span class="crm-section-chevron" style="color:#64748b; font-size:14px; width:16px; height:16px; display:inline-flex; align-items:center; justify-content:center; transition:transform 0.15s ease; user-select:none;">&#x25BE;</span>
                <div class="crm-section-clickable-info" style="flex:1; min-width:0;">
                    <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                        <strong class="crm-section-title" style="font-size:12.5px; color:#0f172a;">${escapeHtml(title)}</strong>
                        <span class="crm-section-badge" style="font-size:9.5px; font-weight:700; text-transform:uppercase; padding:1px 5px; border-radius:8px; background:#f1f5f9; color:${escapeHtml(color)}; border:1px solid #e2e8f0;">${escapeHtml(badge)}</span>
                        <span style="font-size:9px; font-weight:600; padding:1px 4px; border-radius:4px; background:#e0e7ff; color:#4338ca;">Benutzerdefiniert</span>
                    </div>
                </div>
                <button type="button" class="button-link crm-toggle-hf-btn" title="Kopf- & Fußzeile für diese Seite anpassen" style="font-size:10px; font-weight:600; padding:2px 7px; border-radius:12px; background:#f5f3ff; color:#6d28d9; border:1px solid #ddd6fe; display:inline-flex; align-items:center; gap:3px; text-decoration:none; cursor:pointer; user-select:none; white-space:nowrap;" onclick="event.preventDefault(); event.stopPropagation(); jQuery(this).closest('.crm-pdf-section-item').find('> .crm-hf-drawer').slideToggle(180);">
                    <span class="dashicons dashicons-editor-kitchensink" style="font-size:12px; width:12px; height:12px; line-height:12px;"></span>
                    <span class="crm-hf-summary-text">H: Master | F: Master</span>
                    <span class="crm-hf-chevron">&#x25BE;</span>
                </button>
                <span class="crm-subs-counter-badge" style="font-size:10px; font-weight:600; padding:2px 7px; border-radius:12px; background:#f8fafc; color:#475569; border:1px solid #e2e8f0; white-space:nowrap; user-select:none;">1 Unterabschnitt &#x25BE;</span>
                <div class="crm-section-actions" style="display:flex; gap:3px; align-items:center;" onclick="event.stopPropagation();">
                    <button type="button" class="button-link crm-delete-section-btn" title="Abschnitt löschen" style="color:#dc2626; font-size:13px; text-decoration:none; padding:1px 4px;">✕</button>
                    <button type="button" class="button-link crm-move-up-btn" title="Nach oben verschieben" style="color:#64748b; font-size:13px; text-decoration:none; padding:1px 3px;">&uarr;</button>
                    <button type="button" class="button-link crm-move-down-btn" title="Nach unten verschieben" style="color:#64748b; font-size:13px; text-decoration:none; padding:1px 3px;">&darr;</button>
                </div>
            </div>
            <div class="crm-hf-drawer" style="display:none; padding:12px 14px; background:#fcfdff; border-top:1px solid #e2e8f0; border-bottom:1px solid #cbd5e1;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; padding-bottom:6px; border-bottom:1px solid #e2e8f0;">
                    <span style="font-size:11.5px; font-weight:700; color:#4338ca; text-transform:uppercase; letter-spacing:0.5px; display:flex; align-items:center; gap:5px;">
                        <span class="dashicons dashicons-admin-appearance" style="font-size:14px; width:14px; height:14px;"></span>
                        Kopf- & Fußzeile dieser Seite:
                    </span>
                    <small style="color:#64748b; font-size:10.5px;">Master-Voreinstellung oder gezielte Steuerung</small>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:14px;">
                    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:10px 12px;">
                        <div style="font-size:12px; font-weight:700; color:#0f172a; margin-bottom:8px; display:flex; align-items:center; gap:5px;">
                            <span class="dashicons dashicons-heading" style="color:#007C90; font-size:15px; width:15px; height:15px;"></span>
                            <span>Kopfzeile (Header)</span>
                        </div>
                        <div style="margin-bottom:8px;">
                            <label style="display:block; font-size:10.5px; font-weight:600; color:#334155; margin-bottom:3px;">Header-Modus / Vorlage:</label>
                            <select class="crm-hf-header-mode regular-text" style="width:100%; height:28px; font-size:11.5px;">
                                <option value="master" selected>⚡ Wie Master-Einstellung</option>
                                <option value="full">Logo & Firmenadresse (Standard)</option>
                                <option value="logo_only">Nur Logo (ohne Adresse)</option>
                                <option value="address_only">Nur Firmenadresse (ohne Logo)</option>
                                <option value="none">🚫 Keine Kopfzeile (ausblenden)</option>
                                <option value="custom">✏️ Eigener HTML-Header</option>
                            </select>
                        </div>
                        <div class="crm-hf-header-checkboxes" style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:8px; font-size:11px; color:#334155;">
                            <label style="display:inline-flex; align-items:center; gap:4px; cursor:pointer;">
                                <input type="checkbox" class="crm-hf-header-logo" value="1" checked style="margin:0;">
                                <span>Logo anzeigen</span>
                            </label>
                            <label style="display:inline-flex; align-items:center; gap:4px; cursor:pointer;">
                                <input type="checkbox" class="crm-hf-header-address" value="1" checked style="margin:0;">
                                <span>Adresse & Kontakt anzeigen</span>
                            </label>
                        </div>
                        <div class="crm-hf-header-custom-box" style="display:none; margin-top:6px;">
                            <label style="display:block; font-size:10px; font-weight:600; color:#475569; margin-bottom:2px;">Eigener Header HTML / Platzhalter:</label>
                            <textarea class="crm-hf-header-custom" rows="2" style="width:100%; font-size:11px; font-family:monospace;" placeholder="HTML oder Platzhalter wie {kurstitel}..."></textarea>
                        </div>
                    </div>
                    <div style="background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:10px 12px;">
                        <div style="font-size:12px; font-weight:700; color:#0f172a; margin-bottom:8px; display:flex; align-items:center; gap:5px;">
                            <span class="dashicons dashicons-editor-insertmore" style="color:#007C90; font-size:15px; width:15px; height:15px;"></span>
                            <span>Fußzeile (Footer)</span>
                        </div>
                        <div style="margin-bottom:8px;">
                            <label style="display:block; font-size:10.5px; font-weight:600; color:#334155; margin-bottom:3px;">Footer-Modus / Vorlage:</label>
                            <select class="crm-hf-footer-mode regular-text" style="width:100%; height:28px; font-size:11.5px;">
                                <option value="master" selected>⚡ Wie Master-Einstellung</option>
                                <option value="standard">Firmendaten + Seitenzahlen (Standard)</option>
                                <option value="full">Firmendaten + Seitenzahlen + Datum</option>
                                <option value="page_numbers_only">Nur Seitenzahlen</option>
                                <option value="company_only">Nur Firmendaten</option>
                                <option value="none">🚫 Keine Fußzeile (ausblenden)</option>
                                <option value="custom">✏️ Eigener Text / Footer</option>
                            </select>
                        </div>
                        <div class="crm-hf-footer-checkboxes" style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:8px; font-size:11px; color:#334155;">
                            <label style="display:inline-flex; align-items:center; gap:4px; cursor:pointer;">
                                <input type="checkbox" class="crm-hf-footer-company" value="1" checked style="margin:0;">
                                <span>Firmendaten</span>
                            </label>
                            <label style="display:inline-flex; align-items:center; gap:4px; cursor:pointer;">
                                <input type="checkbox" class="crm-hf-footer-page-num" value="1" checked style="margin:0;">
                                <span>Seitenzahlen</span>
                            </label>
                            <label style="display:inline-flex; align-items:center; gap:4px; cursor:pointer;">
                                <input type="checkbox" class="crm-hf-footer-date" value="1" style="margin:0;">
                                <span>Datum</span>
                            </label>
                        </div>
                        <div class="crm-hf-footer-custom-box" style="display:none; margin-top:6px;">
                            <label style="display:block; font-size:10px; font-weight:600; color:#475569; margin-bottom:2px;">Eigener Footer-Text ({PAGENO}, {NB}, {datum}):</label>
                            <input type="text" class="crm-hf-footer-custom regular-text" style="width:100%; height:26px; font-size:11px;" placeholder="z. B. Vertraulich | Seite {PAGENO} von {NB}">
                        </div>
                    </div>
                </div>
            </div>
            <div class="crm-subsections-drawer" style="display:block; padding:10px 14px 12px 14px; background:#f8fafc; border-top:1px solid #e2e8f0; border-radius:0 0 6px 6px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; padding-bottom:4px; border-bottom:1px solid #e2e8f0;">
                    <span style="font-size:11px; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:0.5px;">Unterabschnitte dieser Seite:</span>
                    <small style="color:#64748b; font-size:10.5px;">Ziehen zum Sortieren | Häkchen zum Ein-/Ausblenden</small>
                </div>
                <ul class="crm-sortable-subsections" style="list-style:none; margin:0 0 10px 0; padding:0;">
                    <li class="crm-pdf-subsection-item sub-active"
                        data-sub-key="body"
                        data-custom="0"
                        data-title="Seiteninhalt"
                        data-orig-title="Seiteninhalt"
                        data-content="${escapeHtml(content)}"
                        data-default-content=""
                        style="display:block; margin-bottom:6px; background:#ffffff; border:1px solid #cbd5e1; border-radius:5px; transition:all 0.12s ease; overflow:hidden;">
                        <div class="crm-sub-row" style="display:flex; align-items:center; gap:8px; padding:6px 10px; cursor:grab;">
                            <span class="crm-sub-drag-handle" title="Ziehen zum Sortieren" style="color:#94a3b8; font-size:14px; cursor:grab; user-select:none;">&#x22EE;&#x22EE;</span>
                            <label style="display:flex; align-items:center; margin:0; cursor:pointer;" title="Unterabschnitt ein-/ausblenden">
                                <input type="checkbox" class="crm-sub-checkbox" value="1" checked style="margin:0; width:14px; height:14px; cursor:pointer;">
                            </label>
                            <div style="flex:1; min-width:0;">
                                <span class="crm-sub-title" style="font-size:11.5px; font-weight:600; color:#1e293b;">Seiteninhalt</span>
                                <span class="crm-sub-custom-badge" style="${content ? 'display:inline-block;' : 'display:none;'} font-size:8.5px; font-weight:600; padding:1px 4px; border-radius:3px; background:#fef3c7; color:#b45309; border:1px solid #fde68a; margin-left:4px;">Angepasst</span>
                            </div>
                            <div class="crm-sub-actions" style="display:flex; gap:3px; align-items:center;">
                                <button type="button" class="button-link crm-edit-sub-btn" title="Unterabschnitt bearbeiten" style="color:#007C90; font-size:10.5px; font-weight:600; padding:1px 6px; text-decoration:none; display:inline-flex; align-items:center; gap:2px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:3px; cursor:pointer;">✎ <span class="crm-edit-sub-text">Bearbeiten</span></button>
                                <button type="button" class="button-link crm-sub-move-up" title="Nach oben" style="color:#64748b; font-size:11px; padding:0 2px; text-decoration:none;">&uarr;</button>
                                <button type="button" class="button-link crm-sub-move-down" title="Nach unten" style="color:#64748b; font-size:11px; padding:0 2px; text-decoration:none;">&darr;</button>
                            </div>
                        </div>
                        <div class="crm-sub-edit-drawer" style="display:none; padding:10px 12px; background:#f8fafc; border-top:1px solid #e2e8f0; cursor:default;">
                            <div style="margin-bottom:6px;">
                                <label style="display:block; font-size:10.5px; font-weight:600; color:#334155; margin-bottom:2px;">Titel des Unterabschnitts:</label>
                                <input type="text" class="crm-sub-input-title regular-text" value="Seiteninhalt" style="width:100%; height:26px; font-size:11.5px;">
                            </div>
                            <div style="margin-bottom:6px;">
                                <label style="display:block; font-size:10.5px; font-weight:600; color:#334155; margin-bottom:2px;">Inhalt / Text / HTML:</label>
                                <textarea class="crm-sub-input-content" rows="4" style="width:100%; font-size:11.5px; font-family:monospace; line-height:1.4;" placeholder="Freitext oder HTML für diesen Unterabschnitt eingeben...">${escapeHtml(content)}</textarea>
                            </div>
                            <div class="crm-sub-chips-bar" style="margin-bottom:8px; display:flex; gap:4px; flex-wrap:wrap; align-items:center;">
                                <span style="font-size:9.5px; color:#475569; font-weight:600;">Platzhalter:</span>
                                <button type="button" class="button-link crm-chip-btn" data-tag="{vorname}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{vorname}</button>
                                <button type="button" class="button-link crm-chip-btn" data-tag="{nachname}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{nachname}</button>
                                <button type="button" class="button-link crm-chip-btn" data-tag="{kurstitel}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{kurstitel}</button>
                                <button type="button" class="button-link crm-chip-btn" data-tag="{startdatum}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{startdatum}</button>
                                <button type="button" class="button-link crm-chip-btn" data-tag="{preis}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{preis}</button>
                                <button type="button" class="button-link crm-chip-btn" data-tag="{datum}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{datum}</button>
                                <button type="button" class="button-link crm-chip-btn" data-tag="{expire}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{expire}</button>
                                <button type="button" class="button-link crm-chip-btn" data-tag="{anrede_brief}" title="Postalisches Herrn / Frau" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{anrede_brief}</button>
                                <button type="button" class="button-link crm-chip-btn" data-tag="{kunden_firma}" title="Firmenname des Kunden" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{kunden_firma}</button>
                                <button type="button" class="button-link crm-chip-btn" data-tag="{empfaenger_adresse}" title="Kompletter normgerechter Adressblock" style="font-size:9.5px; padding:1px 5px; background:#ecfdf5; color:#065f46; border-radius:3px; text-decoration:none; border:1px solid #a7f3d0; font-weight:600;">{empfaenger_adresse}</button>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:6px; padding-top:4px;">
                                <div style="display:flex; gap:6px;">
                                    <button type="button" class="button button-primary crm-sub-apply-edit-btn" style="background:#007C90; border-color:#007C90; font-size:11px; height:24px; line-height:22px; padding:0 8px;">✓ Übernehmen</button>
                                    <button type="button" class="button crm-sub-close-edit-btn" style="font-size:11px; height:24px; line-height:22px; padding:0 6px;">Schließen</button>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>
                <div class="crm-add-sub-wrapper">
                    <button type="button" class="button button-secondary crm-toggle-add-sub-btn" style="font-size:11px; height:24px; line-height:22px; padding:0 8px; display:inline-flex; align-items:center; gap:3px;">
                        <span class="dashicons dashicons-plus" style="font-size:12px; width:12px; height:12px;"></span>
                        Unterabschnitt hinzufügen
                    </button>
                    <div class="crm-add-sub-drawer" style="display:none; margin-top:8px; padding:10px; background:#ffffff; border:1px solid #cbd5e1; border-radius:4px;">
                        <div style="margin-bottom:6px;">
                            <label style="display:block; font-size:10.5px; font-weight:600; color:#334155; margin-bottom:2px;">Titel des Unterabschnitts *</label>
                            <input type="text" class="crm-new-sub-title regular-text" placeholder="z. B. Zusätzlicher Hinweis oder Textabsatz" style="width:100%; height:26px; font-size:11.5px;">
                        </div>
                        <div style="margin-bottom:6px;">
                            <label style="display:block; font-size:10.5px; font-weight:600; color:#334155; margin-bottom:2px;">Inhalt / Freitext (HTML erlaubt)</label>
                            <textarea class="crm-new-sub-content" rows="2" placeholder="Text oder HTML für diesen Unterabschnitt..." style="width:100%; font-size:11.5px; font-family:monospace;"></textarea>
                        </div>
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="button button-primary crm-create-sub-btn" style="background:#007C90; border-color:#007C90; font-size:11px; height:24px; line-height:22px; padding:0 8px;">Hinzufügen</button>
                            <button type="button" class="button crm-cancel-add-sub-btn" style="font-size:11px; height:24px; line-height:22px; padding:0 6px;">Abbrechen</button>
                        </div>
                    </div>
                </div>
            </div>
        </li>
        `;

        $manager.find('> .crm-sortable-sections').append(secHtml);
        $manager.find('.crm-new-sec-title').val('');
        $manager.find('.crm-new-sec-badge').val('');
        $manager.find('.crm-new-sec-content').val('');
        $manager.find('.crm-add-section-drawer').slideUp(180);

        initPdfSectionSortables();
    });

    // Delete custom section
    jQuery(document).on('click', '.crm-delete-section-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (confirm('Diesen benutzerdefinierten Abschnitt wirklich löschen?')) {
            jQuery(this).closest('.crm-pdf-section-item').fadeOut(180, function () {
                jQuery(this).remove();
            });
        }
    });

    // Toggle Add Subsection Drawer
    jQuery(document).on('click', '.crm-toggle-add-sub-btn', function (e) {
        e.preventDefault();
        const $wrapper = jQuery(this).closest('.crm-add-sub-wrapper');
        $wrapper.find('.crm-add-sub-drawer').slideToggle(150);
    });
    jQuery(document).on('click', '.crm-cancel-add-sub-btn', function (e) {
        e.preventDefault();
        const $wrapper = jQuery(this).closest('.crm-add-sub-wrapper');
        $wrapper.find('.crm-add-sub-drawer').slideUp(150);
    });

    // Create New Custom Subsection
    jQuery(document).on('click', '.crm-create-sub-btn', function (e) {
        e.preventDefault();
        const $drawer = jQuery(this).closest('.crm-add-sub-drawer');
        const $sec = jQuery(this).closest('.crm-pdf-section-item');
        const subTitle = $drawer.find('.crm-new-sub-title').val().trim();
        if (!subTitle) {
            alert('Bitte einen Titel für den Unterabschnitt eingeben.');
            $drawer.find('.crm-new-sub-title').focus();
            return;
        }
        const subContent = $drawer.find('.crm-new-sub-content').val().trim();
        const subKey = 'custom_sub_' + Date.now();

        const subHtml = `
        <li class="crm-pdf-subsection-item sub-active"
            data-sub-key="${escapeHtml(subKey)}"
            data-custom="1"
            data-title="${escapeHtml(subTitle)}"
            data-orig-title="${escapeHtml(subTitle)}"
            data-content="${escapeHtml(subContent)}"
            data-default-content=""
            style="display:block; margin-bottom:6px; background:#ffffff; border:1px solid #cbd5e1; border-radius:5px; transition:all 0.12s ease; overflow:hidden;">
            <div class="crm-sub-row" style="display:flex; align-items:center; gap:8px; padding:6px 10px; cursor:grab;">
                <span class="crm-sub-drag-handle" title="Ziehen zum Sortieren" style="color:#94a3b8; font-size:14px; cursor:grab; user-select:none;">&#x22EE;&#x22EE;</span>
                <label style="display:flex; align-items:center; margin:0; cursor:pointer;" title="Unterabschnitt ein-/ausblenden">
                    <input type="checkbox" class="crm-sub-checkbox" value="1" checked style="margin:0; width:14px; height:14px; cursor:pointer;">
                </label>
                <div style="flex:1; min-width:0;">
                    <span class="crm-sub-title" style="font-size:11.5px; font-weight:600; color:#1e293b;">${escapeHtml(subTitle)}</span>
                    <span style="font-size:8.5px; font-weight:600; padding:1px 4px; border-radius:3px; background:#e0e7ff; color:#4338ca; margin-left:4px;">Eigen</span>
                    <span class="crm-sub-custom-badge" style="${subContent ? 'display:inline-block;' : 'display:none;'} font-size:8.5px; font-weight:600; padding:1px 4px; border-radius:3px; background:#fef3c7; color:#b45309; border:1px solid #fde68a; margin-left:4px;">Angepasst</span>
                </div>
                <div class="crm-sub-actions" style="display:flex; gap:3px; align-items:center;">
                    <button type="button" class="button-link crm-edit-sub-btn" title="Unterabschnitt bearbeiten" style="color:#007C90; font-size:10.5px; font-weight:600; padding:1px 6px; text-decoration:none; display:inline-flex; align-items:center; gap:2px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:3px; cursor:pointer;">✎ <span class="crm-edit-sub-text">Bearbeiten</span></button>
                    <button type="button" class="button-link crm-delete-sub-btn" title="Unterabschnitt löschen" style="color:#dc2626; font-size:12px; padding:0 3px; text-decoration:none;">✕</button>
                    <button type="button" class="button-link crm-sub-move-up" title="Nach oben" style="color:#64748b; font-size:11px; padding:0 2px; text-decoration:none;">&uarr;</button>
                    <button type="button" class="button-link crm-sub-move-down" title="Nach unten" style="color:#64748b; font-size:11px; padding:0 2px; text-decoration:none;">&darr;</button>
                </div>
            </div>
            <div class="crm-sub-edit-drawer" style="display:none; padding:10px 12px; background:#f8fafc; border-top:1px solid #e2e8f0; cursor:default;">
                <div style="margin-bottom:6px;">
                    <label style="display:block; font-size:10.5px; font-weight:600; color:#334155; margin-bottom:2px;">Titel des Unterabschnitts:</label>
                    <input type="text" class="crm-sub-input-title regular-text" value="${escapeHtml(subTitle)}" style="width:100%; height:26px; font-size:11.5px;">
                </div>
                <div style="margin-bottom:6px;">
                    <label style="display:block; font-size:10.5px; font-weight:600; color:#334155; margin-bottom:2px;">Inhalt / Text / HTML:</label>
                    <textarea class="crm-sub-input-content" rows="4" style="width:100%; font-size:11.5px; font-family:monospace; line-height:1.4;" placeholder="Freitext oder HTML eingeben...">${escapeHtml(subContent)}</textarea>
                </div>
                <div class="crm-sub-chips-bar" style="margin-bottom:8px; display:flex; gap:4px; flex-wrap:wrap; align-items:center;">
                    <span style="font-size:9.5px; color:#475569; font-weight:600;">Platzhalter:</span>
                    <button type="button" class="button-link crm-chip-btn" data-tag="{vorname}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{vorname}</button>
                    <button type="button" class="button-link crm-chip-btn" data-tag="{nachname}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{nachname}</button>
                    <button type="button" class="button-link crm-chip-btn" data-tag="{kurstitel}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{kurstitel}</button>
                    <button type="button" class="button-link crm-chip-btn" data-tag="{startdatum}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{startdatum}</button>
                    <button type="button" class="button-link crm-chip-btn" data-tag="{preis}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{preis}</button>
                    <button type="button" class="button-link crm-chip-btn" data-tag="{datum}" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{datum}</button>
                    <button type="button" class="button-link crm-chip-btn" data-tag="{anrede_brief}" title="Postalisches Herrn / Frau" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{anrede_brief}</button>
                    <button type="button" class="button-link crm-chip-btn" data-tag="{kunden_firma}" title="Firmenname des Kunden" style="font-size:9.5px; padding:1px 5px; background:#e0f2fe; color:#0369a1; border-radius:3px; text-decoration:none; border:1px solid #bae6fd;">{kunden_firma}</button>
                    <button type="button" class="button-link crm-chip-btn" data-tag="{empfaenger_adresse}" title="Kompletter normgerechter Adressblock" style="font-size:9.5px; padding:1px 5px; background:#ecfdf5; color:#065f46; border-radius:3px; text-decoration:none; border:1px solid #a7f3d0; font-weight:600;">{empfaenger_adresse}</button>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; gap:6px; padding-top:4px;">
                    <div style="display:flex; gap:6px;">
                        <button type="button" class="button button-primary crm-sub-apply-edit-btn" style="background:#007C90; border-color:#007C90; font-size:11px; height:24px; line-height:22px; padding:0 8px;">✓ Übernehmen</button>
                        <button type="button" class="button crm-sub-close-edit-btn" style="font-size:11px; height:24px; line-height:22px; padding:0 6px;">Schließen</button>
                    </div>
                </div>
            </div>
        </li>
        `;

        $sec.find('.crm-sortable-subsections').append(subHtml);
        $drawer.find('.crm-new-sub-title').val('');
        $drawer.find('.crm-new-sub-content').val('');
        $drawer.slideUp(150);

        updateSubsectionsCounter($sec);
        initPdfSectionSortables();
    });

    // Delete custom subsection
    jQuery(document).on('click', '.crm-delete-sub-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $sub = jQuery(this).closest('.crm-pdf-subsection-item');
        const $sec = jQuery(this).closest('.crm-pdf-section-item');
        if (confirm('Diesen benutzerdefinierten Unterabschnitt wirklich löschen?')) {
            $sub.fadeOut(150, function () {
                jQuery(this).remove();
                updateSubsectionsCounter($sec);
            });
        }
    });

    // Toggle Subsection Inline Edit Drawer
    jQuery(document).on('click', '.crm-edit-sub-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $sub = jQuery(this).closest('.crm-pdf-subsection-item');
        const $drawer = $sub.find('> .crm-sub-edit-drawer');
        const $btnText = jQuery(this).find('.crm-edit-sub-text');

        if ($drawer.is(':visible')) {
            $drawer.slideUp(160);
            $btnText.text('Bearbeiten');
        } else {
            // Falls Textarea leer ist, originalen Standardtext vorausfüllen
            const $contentInput = $drawer.find('.crm-sub-input-content');
            if ($contentInput.length && !$contentInput.val().trim()) {
                const defContent = $sub.data('default-content') || $sub.attr('data-default-content') || '';
                if (defContent) {
                    $contentInput.val(defContent);
                }
            }
            $drawer.slideDown(180, function () {
                $drawer.find('.crm-sub-input-title').focus();
            });
            $btnText.text('Schließen');
        }
    });

    // Close Subsection Edit Drawer
    jQuery(document).on('click', '.crm-sub-close-edit-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $sub = jQuery(this).closest('.crm-pdf-subsection-item');
        $sub.find('> .crm-sub-edit-drawer').slideUp(160);
        $sub.find('.crm-edit-sub-btn .crm-edit-sub-text').text('Bearbeiten');
    });

    // Insert Placeholder Chips
    jQuery(document).on('click', '.crm-chip-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const tag = jQuery(this).data('tag');
        const $drawer = jQuery(this).closest('.crm-sub-edit-drawer');
        const textarea = $drawer.find('.crm-sub-input-content')[0];
        if (!textarea) return;

        const start = textarea.selectionStart || 0;
        const end = textarea.selectionEnd || 0;
        const text = textarea.value;
        const before = text.substring(0, start);
        const after = text.substring(end, text.length);

        textarea.value = before + tag + after;
        textarea.selectionStart = textarea.selectionEnd = start + tag.length;
        textarea.focus();
    });

    // Apply Subsection Edits
    jQuery(document).on('click', '.crm-sub-apply-edit-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $sub = jQuery(this).closest('.crm-pdf-subsection-item');
        const $drawer = $sub.find('> .crm-sub-edit-drawer');
        const newTitle = $drawer.find('.crm-sub-input-title').val().trim();
        const newContent = $drawer.find('.crm-sub-input-content').val();

        if (newTitle) {
            $sub.data('title', newTitle).attr('data-title', newTitle);
            $sub.find('.crm-sub-title').first().text(newTitle);
        }

        $sub.data('content', newContent).attr('data-content', newContent);

        // Show badge "Angepasst" if custom content is active
        const defaultContent = $sub.data('default-content') || $sub.attr('data-default-content') || '';
        const isCustomSub = ($sub.data('custom') == 1 || $sub.attr('data-custom') === '1');
        const hasCustomContent = (newContent.trim().length > 0 && newContent.trim() !== defaultContent.trim() && newContent.trim() !== '{standard}');
        const $badge = $sub.find('.crm-sub-custom-badge');

        if (hasCustomContent || (isCustomSub && newContent.trim().length > 0)) {
            $badge.show();
        } else {
            $badge.hide();
            $sub.data('content', '').attr('data-content', '');
        }

        $drawer.slideUp(160);
        $sub.find('.crm-edit-sub-btn .crm-edit-sub-text').text('Bearbeiten');

        // Visual flash confirmation
        $sub.css('background-color', '#f0fdf4');
        setTimeout(function () {
            $sub.css('background-color', '#ffffff');
        }, 500);
    });

    // Reset Subsection to Default
    jQuery(document).on('click', '.crm-sub-reset-default-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $sub = jQuery(this).closest('.crm-pdf-subsection-item');
        const $drawer = $sub.find('> .crm-sub-edit-drawer');
        const origTitle = $sub.data('orig-title') || $sub.attr('data-orig-title') || '';
        const defContent = $sub.data('default-content') || $sub.attr('data-default-content') || '';

        if (origTitle) {
            $sub.data('title', origTitle).attr('data-title', origTitle);
            $drawer.find('.crm-sub-input-title').val(origTitle);
            $sub.find('.crm-sub-title').first().text(origTitle);
        }

        $sub.data('content', '').attr('data-content', '');
        $drawer.find('.crm-sub-input-content').val(defContent);
        $sub.find('.crm-sub-custom-badge').hide();

        $drawer.slideUp(160);
        $sub.find('.crm-edit-sub-btn .crm-edit-sub-text').text('Bearbeiten');

        // Visual flash confirmation
        $sub.css('background-color', '#fef2f2');
        setTimeout(function () {
            $sub.css('background-color', '#ffffff');
        }, 500);
    });

    // ==========================================
    // E-MAIL SECTIONS INLINE EDITING & ORGANIZER
    // ==========================================
    function initEmailSectionSortables() {
        if (typeof jQuery !== 'undefined' && typeof jQuery.fn.sortable !== 'undefined') {
            jQuery('.crm-sortable-email-sections').sortable({
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
    window.initEmailSectionSortables = initEmailSectionSortables;
    initEmailSectionSortables();

    // Toggle E-Mail Section Inline Edit Drawer via "Bearbeiten" Button
    jQuery(document).on('click', '.crm-edit-email-sec-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $item = jQuery(this).closest('.crm-email-section-item');
        const $drawer = $item.find('> .crm-email-sec-drawer');
        const $btnText = jQuery(this).find('.crm-edit-email-sec-text');
        const $chevron = $item.find('> .crm-email-sec-header-row .crm-email-sec-chevron');

        if ($drawer.is(':visible')) {
            $drawer.slideUp(160);
            $btnText.text('Bearbeiten');
            $chevron.css('transform', 'rotate(0deg)');
        } else {
            $drawer.slideDown(180, function () {
                $drawer.find('.crm-email-sec-input-title').focus();
            });
            $btnText.text('Schließen');
            $chevron.css('transform', 'rotate(90deg)');
        }
    });

    // Accordion Click on Header Row (clicking anywhere on the row)
    jQuery(document).on('click', '.crm-email-sec-header-row', function (e) {
        if (jQuery(e.target).closest('.crm-email-sec-checkbox, .crm-email-sec-drag-handle, .crm-edit-email-sec-btn, .crm-delete-email-sec-btn, .crm-email-sec-move-up, .crm-email-sec-move-down').length) {
            return;
        }
        const $item = jQuery(this).closest('.crm-email-section-item');
        const $drawer = $item.find('> .crm-email-sec-drawer');
        const $btnText = $item.find('.crm-edit-email-sec-btn .crm-edit-email-sec-text');
        const $chevron = jQuery(this).find('.crm-email-sec-chevron');

        if ($drawer.is(':visible')) {
            $drawer.slideUp(160);
            $btnText.text('Bearbeiten');
            $chevron.css('transform', 'rotate(0deg)');
        } else {
            $drawer.slideDown(180, function () {
                $drawer.find('.crm-email-sec-input-title').focus();
            });
            $btnText.text('Schließen');
            $chevron.css('transform', 'rotate(90deg)');
        }
    });

    // Close Button inside Drawer
    jQuery(document).on('click', '.crm-email-sec-close-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $item = jQuery(this).closest('.crm-email-section-item');
        $item.find('> .crm-email-sec-drawer').slideUp(160);
        $item.find('.crm-edit-email-sec-btn .crm-edit-email-sec-text').text('Bearbeiten');
        $item.find('.crm-email-sec-chevron').css('transform', 'rotate(0deg)');
    });

    // Insert Chips into E-Mail Section Textarea
    jQuery(document).on('click', '.crm-email-sec-drawer .crm-chip-btn, .crm-email-sec-drawer .crm-email-insert-chip', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const tag = jQuery(this).data('tag') || jQuery(this).data('code') || jQuery(this).text().trim();
        const $drawer = jQuery(this).closest('.crm-email-sec-drawer');
        const textarea = $drawer.find('.crm-email-sec-input-content')[0];
        if (!textarea || !tag) return;

        const start = textarea.selectionStart || 0;
        const end = textarea.selectionEnd || 0;
        const text = textarea.value;
        const before = text.substring(0, start);
        const after = text.substring(end, text.length);

        textarea.value = before + tag + after;
        textarea.selectionStart = textarea.selectionEnd = start + tag.length;
        textarea.focus();
        jQuery(textarea).trigger('input').trigger('change');

        // Visual flash feedback on chip
        const $btn = jQuery(this);
        $btn.css({ background: '#0284c7', color: '#ffffff' });
        setTimeout(function () {
            $btn.css({ background: '#ffffff', color: '#0369a1' });
        }, 250);
    });

    // Apply E-Mail Section Edits ("Übernehmen")
    jQuery(document).on('click', '.crm-email-sec-apply-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $item = jQuery(this).closest('.crm-email-section-item');
        const $drawer = $item.find('> .crm-email-sec-drawer');
        const newTitle = $drawer.find('.crm-email-sec-input-title').val().trim();
        const newBadge = $drawer.find('.crm-email-sec-input-badge').val().trim();
        const newContent = $drawer.find('.crm-email-sec-input-content').val();

        if (newTitle) {
            $item.data('title', newTitle).attr('data-title', newTitle);
            $item.find('.crm-email-sec-title-text').first().text(newTitle);
        }

        $item.data('badge', newBadge).attr('data-badge', newBadge);
        let $badgeEl = $item.find('.crm-email-sec-badge').first();
        if (newBadge) {
            if ($badgeEl.length) {
                $badgeEl.text(newBadge).show();
            } else {
                const color = $item.data('color') || '#0284c7';
                $item.find('.crm-email-sec-title-text').after('<span class="crm-email-sec-badge" style="font-size:9.5px; font-weight:700; text-transform:uppercase; padding:1px 6px; border-radius:8px; background:#f0f9ff; color:' + color + '; border:1px solid #e0f2fe; margin-left:4px;">' + escapeHtml(newBadge) + '</span>');
            }
        } else if ($badgeEl.length) {
            $badgeEl.hide();
        }

        $item.data('content', newContent).attr('data-content', newContent);

        // Show badge "Angepasst" if custom content is active
        const defaultContent = $item.data('default-content') || $item.attr('data-default-content') || '';
        const isCustom = ($item.data('custom') == 1 || $item.attr('data-custom') === '1');
        const hasCustomContent = (newContent.trim().length > 0 && newContent.trim() !== defaultContent.trim());
        const $customBadge = $item.find('.crm-email-sec-custom-badge');

        if (hasCustomContent || (isCustom && newContent.trim().length > 0)) {
            $customBadge.show();
        } else {
            $customBadge.hide();
        }

        $drawer.slideUp(160);
        $item.find('.crm-edit-email-sec-btn .crm-edit-email-sec-text').text('Bearbeiten');
        $item.find('.crm-email-sec-chevron').css('transform', 'rotate(0deg)');

        // Visual flash confirmation
        $item.css('background-color', '#f0fdf4');
        setTimeout(function () {
            $item.css('background-color', '#ffffff');
        }, 500);

        // Reload preview if available
        const $manager = $item.closest('.crm-email-sections-manager');
        const docType = $manager.data('doc');
        if (docType && typeof window.loadEmailPreview === 'function') {
            window.loadEmailPreview(docType, true);
        }
    });

    // Reset E-Mail Section to Default ("Auf Standard zurücksetzen")
    jQuery(document).on('click', '.crm-email-sec-reset-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $item = jQuery(this).closest('.crm-email-section-item');
        const $drawer = $item.find('> .crm-email-sec-drawer');
        const origTitle = $item.data('default-title') || $item.attr('data-default-title') || '';
        const defaultBadge = $item.data('default-badge') || $item.attr('data-default-badge') || '';
        const defaultContent = $item.data('default-content') || $item.attr('data-default-content') || '';

        if (origTitle) {
            $item.data('title', origTitle).attr('data-title', origTitle);
            $drawer.find('.crm-email-sec-input-title').val(origTitle);
            $item.find('.crm-email-sec-title-text').first().text(origTitle);
        }

        $item.data('badge', defaultBadge).attr('data-badge', defaultBadge);
        $drawer.find('.crm-email-sec-input-badge').val(defaultBadge);
        let $badgeEl = $item.find('.crm-email-sec-badge').first();
        if (defaultBadge && $badgeEl.length) {
            $badgeEl.text(defaultBadge).show();
        }

        $item.data('content', '').attr('data-content', '');
        $drawer.find('.crm-email-sec-input-content').val(defaultContent);
        $item.find('.crm-email-sec-custom-badge').hide();

        $drawer.slideUp(160);
        $item.find('.crm-edit-email-sec-btn .crm-edit-email-sec-text').text('Bearbeiten');
        $item.find('.crm-email-sec-chevron').css('transform', 'rotate(0deg)');

        // Visual flash confirmation
        $item.css('background-color', '#fef2f2');
        setTimeout(function () {
            $item.css('background-color', '#ffffff');
        }, 500);

        // Reload preview if available
        const $manager = $item.closest('.crm-email-sections-manager');
        const docType = $manager.data('doc');
        if (docType && typeof window.loadEmailPreview === 'function') {
            window.loadEmailPreview(docType, true);
        }
    });

    // Checkbox toggle opacity
    jQuery(document).on('change', '.crm-email-sec-checkbox', function () {
        const $item = jQuery(this).closest('.crm-email-section-item');
        if (jQuery(this).is(':checked')) {
            $item.removeClass('is-disabled').addClass('is-active').css('opacity', '1');
        } else {
            $item.removeClass('is-active').addClass('is-disabled').css('opacity', '0.55');
        }
        const $manager = $item.closest('.crm-email-sections-manager');
        const docType = $manager.data('doc');
        if (docType && typeof window.loadEmailPreview === 'function') {
            window.loadEmailPreview(docType, true);
        }
    });

    // Move Up / Move Down buttons
    jQuery(document).on('click', '.crm-email-sec-move-up', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $item = jQuery(this).closest('.crm-email-section-item');
        const $prev = $item.prev('.crm-email-section-item');
        if ($prev.length) {
            $item.insertBefore($prev).hide().fadeIn(150);
            const $manager = $item.closest('.crm-email-sections-manager');
            const docType = $manager.data('doc');
            if (docType && typeof window.loadEmailPreview === 'function') {
                window.loadEmailPreview(docType, true);
            }
        }
    });

    jQuery(document).on('click', '.crm-email-sec-move-down', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $item = jQuery(this).closest('.crm-email-section-item');
        const $next = $item.next('.crm-email-section-item');
        if ($next.length) {
            $item.insertAfter($next).hide().fadeIn(150);
            const $manager = $item.closest('.crm-email-sections-manager');
            const docType = $manager.data('doc');
            if (docType && typeof window.loadEmailPreview === 'function') {
                window.loadEmailPreview(docType, true);
            }
        }
    });

    // Delete custom email section
    jQuery(document).on('click', '.crm-delete-email-sec-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (confirm('Diesen E-Mail-Abschnitt wirklich löschen?')) {
            const $item = jQuery(this).closest('.crm-email-section-item');
            const $manager = $item.closest('.crm-email-sections-manager');
            const docType = $manager.data('doc');
            $item.fadeOut(200, function () {
                jQuery(this).remove();
                if (docType && typeof window.loadEmailPreview === 'function') {
                    window.loadEmailPreview(docType, true);
                }
            });
        }
    });

    // Toggle Add Section Drawer
    jQuery(document).on('click', '.crm-toggle-add-email-sec-btn', function (e) {
        e.preventDefault();
        const $manager = jQuery(this).closest('.crm-email-sections-manager');
        $manager.find('.crm-add-email-sec-drawer').slideToggle(180);
    });

    jQuery(document).on('click', '.crm-cancel-add-email-sec-btn', function (e) {
        e.preventDefault();
        jQuery(this).closest('.crm-add-email-sec-drawer').slideUp(180);
    });

    // Create New Custom Email Section
    jQuery(document).on('click', '.crm-create-email-sec-btn', function (e) {
        e.preventDefault();
        const $drawer = jQuery(this).closest('.crm-add-email-sec-drawer');
        const $manager = $drawer.closest('.crm-email-sections-manager');
        const $titleInput = $drawer.find('.crm-new-email-sec-title');
        const title = $titleInput.val().trim();
        if (!title) {
            alert('Bitte geben Sie einen Titel ein.');
            $titleInput.focus();
            return;
        }
        const badge = $drawer.find('.crm-new-email-sec-badge').val().trim() || 'Custom';
        const color = $drawer.find('.crm-new-email-sec-color').val() || '#0284c7';
        const content = $drawer.find('.crm-new-email-sec-content').val();
        const key = 'custom_' + Date.now();
        const docType = $manager.data('doc') || 'angebot';

        const newItemHtml = `
            <li class="crm-email-section-item is-active"
                data-key="${escapeHtml(key)}"
                data-custom="1"
                data-title="${escapeHtml(title)}"
                data-default-title="${escapeHtml(title)}"
                data-badge="${escapeHtml(badge)}"
                data-default-badge="${escapeHtml(badge)}"
                data-color="${escapeHtml(color)}"
                data-content="${escapeHtml(content)}"
                data-default-content=""
                style="margin-bottom:8px; background:#ffffff; border:1px solid #cbd5e1; border-left:4px solid ${escapeHtml(color)}; border-radius:6px; box-shadow:0 1px 2px rgba(0,0,0,0.03); transition:all 0.15s ease;">
                <div class="crm-email-sec-header-row" style="display:flex; align-items:center; gap:10px; padding:9px 12px; cursor:pointer;">
                    <span class="crm-email-sec-drag-handle" title="Ziehen zum Verschieben" style="color:#94a3b8; cursor:grab; font-size:16px; display:flex; align-items:center; user-select:none;">&#x2630;</span>
                    <label class="crm-email-sec-toggle-label" style="display:flex; align-items:center; margin:0; cursor:pointer;" onclick="event.stopPropagation();">
                        <input type="checkbox" class="crm-email-sec-checkbox" value="1" checked style="margin:0; width:15px; height:15px; cursor:pointer;">
                    </label>
                    <span class="crm-email-sec-chevron" style="color:#64748b; font-size:14px; width:16px; height:16px; display:inline-flex; align-items:center; justify-content:center; transition:transform 0.15s ease; user-select:none;">&#x25B8;</span>
                    <div class="crm-email-sec-info" style="flex:1; min-width:0;">
                        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                            <strong class="crm-email-sec-title-text" style="font-size:12.5px; color:#0f172a;">${escapeHtml(title)}</strong>
                            <span class="crm-email-sec-badge" style="font-size:9.5px; font-weight:700; text-transform:uppercase; padding:1px 6px; border-radius:8px; background:#f0f9ff; color:${escapeHtml(color)}; border:1px solid #e0f2fe;">${escapeHtml(badge)}</span>
                            <span style="font-size:9px; font-weight:600; padding:1px 4px; border-radius:4px; background:#e0e7ff; color:#4338ca;">Benutzerdefiniert</span>
                            <span class="crm-email-sec-custom-badge" style="${content ? 'display:inline-block;' : 'display:none;'} font-size:8.5px; font-weight:600; padding:1px 4px; border-radius:3px; background:#fef3c7; color:#b45309; border:1px solid #fde68a;">Angepasst</span>
                        </div>
                    </div>
                    <div class="crm-email-sec-actions" style="display:flex; gap:4px; align-items:center;" onclick="event.stopPropagation();">
                        <button type="button" class="button-link crm-edit-email-sec-btn" title="Abschnitt bearbeiten" style="color:#0284c7; font-size:10.5px; font-weight:600; padding:1px 6px; text-decoration:none; display:inline-flex; align-items:center; gap:2px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:3px; cursor:pointer;">
                            ✎ <span class="crm-edit-email-sec-text">Bearbeiten</span>
                        </button>
                        <button type="button" class="button-link crm-delete-email-sec-btn" title="Abschnitt löschen" style="color:#dc2626; font-size:13px; text-decoration:none; padding:1px 4px;">✕</button>
                        <button type="button" class="button-link crm-email-sec-move-up" title="Nach oben verschieben" style="color:#64748b; font-size:13px; text-decoration:none; padding:1px 3px;">&uarr;</button>
                        <button type="button" class="button-link crm-email-sec-move-down" title="Nach unten verschieben" style="color:#64748b; font-size:13px; text-decoration:none; padding:1px 3px;">&darr;</button>
                    </div>
                </div>
                <div class="crm-email-sec-drawer" style="display:none; padding:12px 14px; background:#f8fafc; border-top:1px solid #e2e8f0; border-radius:0 0 6px 6px; cursor:default;">
                    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:10px; margin-bottom:10px;">
                        <div>
                            <label style="display:block; font-size:10.5px; font-weight:600; color:#334155; margin-bottom:2px;">Block-Titel:</label>
                            <input type="text" class="crm-email-sec-input-title regular-text" value="${escapeHtml(title)}" style="width:100%; height:28px; font-size:11.5px;">
                        </div>
                        <div>
                            <label style="display:block; font-size:10.5px; font-weight:600; color:#334155; margin-bottom:2px;">Badge-Text:</label>
                            <input type="text" class="crm-email-sec-input-badge regular-text" value="${escapeHtml(badge)}" style="width:100%; height:28px; font-size:11.5px;">
                        </div>
                    </div>
                    <div>
                        <label style="display:block; font-size:10.5px; font-weight:600; color:#334155; margin-bottom:2px;">Block-Inhalt (HTML & Platzhalter):</label>
                        <textarea class="crm-email-sec-input-content" rows="5" style="width:100%; font-size:11.5px; font-family:monospace; line-height:1.4;">${escapeHtml(content)}</textarea>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:6px; padding-top:10px; margin-top:10px; border-top:1px solid #e2e8f0;">
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="button button-primary crm-email-sec-apply-btn" style="background:#0284c7; border-color:#0284c7; font-size:11px; height:26px; line-height:24px; padding:0 10px; cursor:pointer;">✓ Übernehmen</button>
                            <button type="button" class="button crm-email-sec-close-btn" style="font-size:11px; height:26px; line-height:24px; padding:0 8px; cursor:pointer;">Schließen</button>
                        </div>
                    </div>
                </div>
            </li>
        `;

        $manager.find('.crm-sortable-email-sections').append(newItemHtml);
        initEmailSectionSortables();

        $titleInput.val('');
        $drawer.find('.crm-new-email-sec-badge').val('');
        $drawer.find('.crm-new-email-sec-content').val('');
        $drawer.slideUp(180);

        if (docType && typeof window.loadEmailPreview === 'function') {
            window.loadEmailPreview(docType, true);
        }
    });

    // ==========================================
    // DUAL PDF PREVIEW & TAB CONTROLLERS
    // ==========================================
    jQuery(document).on('click', '.crm-preview-switch-embed', function (e) {
        e.preventDefault();
        jQuery('.crm-preview-switch-embed').removeClass('active').css({ borderColor: '', color: '', fontWeight: 'normal' });
        jQuery(this).addClass('active').css({ borderColor: '#7c3aed', color: '#6d28d9', fontWeight: '600' });
        const url = jQuery(this).data('url');
        const doc = jQuery(this).data('doc');
        const $embed = jQuery('#x-sieben-pdf-preview embed');
        if ($embed.length && url) {
            $embed.attr('src', url + '?t=' + new Date().getTime());
        }
        if (doc) {
            const $secBtn = jQuery('.crm-dual-sec-tab-btn[data-target="crm-dual-sec-' + doc + '"]');
            if ($secBtn.length && !$secBtn.hasClass('active')) {
                jQuery('.crm-dual-sec-tab-btn').removeClass('active').css({ borderColor: '', color: '', fontWeight: 'normal' });
                $secBtn.addClass('active').css({ borderColor: '#7c3aed', color: '#6d28d9', fontWeight: '600' });
                jQuery('.crm-dual-sec-pane').hide();
                jQuery('#crm-dual-sec-' + doc).show();
            }
        }
    });

    jQuery(document).on('click', '.crm-dual-sec-tab-btn', function (e) {
        e.preventDefault();
        jQuery('.crm-dual-sec-tab-btn').removeClass('active').css({ borderColor: '', color: '', fontWeight: 'normal' });
        jQuery(this).addClass('active').css({ borderColor: '#7c3aed', color: '#6d28d9', fontWeight: '600' });
        const target = jQuery(this).data('target');
        jQuery('.crm-dual-sec-pane').hide();
        jQuery('#' + target).show();

        const docType = (target === 'crm-dual-sec-kb') ? 'kb' : 'angebot';
        const $switchBtn = jQuery('.crm-preview-switch-embed[data-doc="' + docType + '"]');
        if ($switchBtn.length && !$switchBtn.hasClass('active')) {
            jQuery('.crm-preview-switch-embed').removeClass('active').css({ borderColor: '', color: '', fontWeight: 'normal' });
            $switchBtn.addClass('active').css({ borderColor: '#7c3aed', color: '#6d28d9', fontWeight: '600' });
            const url = $switchBtn.data('url');
            const $embed = jQuery('#x-sieben-pdf-preview embed');
            if ($embed.length && url) {
                $embed.attr('src', url + '?t=' + new Date().getTime());
            }
        }
    });

    // Save section order in Entry Preview Sidebar
    jQuery(document).on('click', '.crm-pdf-sections-preview-box .crm-save-sections-btn', function (e) {
        e.preventDefault();
        const $btn = jQuery(this);
        const $box = $btn.closest('.crm-pdf-sections-preview-box');
        const $manager = $btn.closest('.crm-pdf-sections-manager');
        const docType = $manager.data('doc') || 'angebot';
        const entryId = $box.data('entry') || $manager.data('entry') || 0;
        const courseId = $box.data('course') || 0;
        const $status = $manager.find('.crm-sections-status');

        const sections = crmGetHierarchicalSections($manager);

        const origHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Wird angewendet...');

        jQuery.ajax({
            url: (typeof crmData !== 'undefined' && crmData.ajaxUrl) ? crmData.ajaxUrl : ((typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php'),
            type: 'POST',
            data: {
                action: 'crm_save_pdf_section_order',
                nonce: (typeof crmData !== 'undefined' && crmData.nonce) ? crmData.nonce : '',
                doc_type: docType,
                entry_id: entryId,
                course_id: courseId,
                sections: sections
            },
            success: function (res) {
                $btn.prop('disabled', false).html(origHtml);
                if (res.success) {
                    if (window.crmJsCache && typeof window.crmJsCache.cleanPartial === 'function') {
                        window.crmJsCache.cleanPartial('pdf_' + (res.data ? res.data.doc_type : docType));
                    }
                    $status.text('✓ Aktualisiert!').css({ color: '#16a34a' }).fadeIn().delay(2500).fadeOut();
                    if (res.data && res.data.pdf_url) {
                        const freshUrl = res.data.pdf_url + '?t=' + new Date().getTime();
                        const respDoc = res.data.doc_type;

                        // 1. Update doc tab url
                        const $docTab = jQuery('.crm-preview-switch-embed[data-doc="' + respDoc + '"]');
                        if ($docTab.length) {
                            $docTab.data('url', res.data.pdf_url).attr('data-url', res.data.pdf_url);
                        }

                        // 2. Update embed if active doc matches or single mode
                        const $activeTab = jQuery('.crm-preview-switch-embed.active');
                        const isDocActive = !$activeTab.length || ($activeTab.data('doc') === respDoc);
                        const $embed = jQuery('#x-sieben-pdf-preview embed');
                        if ($embed.length && isDocActive) {
                            $embed.attr('src', freshUrl);
                        }

                        // 3. Update download link
                        const $docDl = jQuery('a[data-doc-download="' + respDoc + '"]');
                        if ($docDl.length) {
                            $docDl.attr('href', res.data.pdf_url);
                        } else {
                            const $downloadLink = jQuery('#x-sieben-button-row a[download]');
                            if ($downloadLink.length) {
                                $downloadLink.attr('href', res.data.pdf_url);
                            }
                        }

                        // 4. Update email buttons data-pdf
                        const $emailBtns = jQuery('#x-sieben-button-row .x-sieben-email-btn');
                        $emailBtns.each(function () {
                            const currentPdf = jQuery(this).data('pdf') || '';
                            if (currentPdf.indexOf(',') !== -1) {
                                const parts = currentPdf.split(',');
                                if (respDoc === 'angebot') {
                                    parts[0] = res.data.pdf_url;
                                } else if (respDoc === 'kb') {
                                    parts[1] = res.data.pdf_url;
                                }
                                const combined = parts.join(',');
                                jQuery(this).data('pdf', combined).attr('data-pdf', combined);
                            } else {
                                jQuery(this).data('pdf', res.data.pdf_url).attr('data-pdf', res.data.pdf_url);
                            }
                        });
                    }
                } else {
                    const msg = (res.data && res.data.message) ? res.data.message : 'Fehler beim Speichern.';
                    $status.text(msg).css({ color: '#dc2626' }).fadeIn().delay(3000).fadeOut();
                }
            },
            error: function () {
                $btn.prop('disabled', false).html(origHtml);
                $status.text('Serverfehler').css({ color: '#dc2626' }).fadeIn().delay(3000).fadeOut();
            }
        });
    });

    // Reset section order in Entry Preview Sidebar
    jQuery(document).on('click', '.crm-pdf-sections-preview-box .crm-reset-sections-btn', function (e) {
        e.preventDefault();
        const $btn = jQuery(this);
        const $box = $btn.closest('.crm-pdf-sections-preview-box');
        const $manager = $btn.closest('.crm-pdf-sections-manager');
        const docType = $manager.data('doc') || 'angebot';
        const entryId = $box.data('entry') || $manager.data('entry') || 0;
        const courseId = $box.data('course') || 0;
        const $parent = $manager.parent();

        $btn.prop('disabled', true);

        jQuery.ajax({
            url: (typeof crmData !== 'undefined' && crmData.ajaxUrl) ? crmData.ajaxUrl : ((typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php'),
            type: 'POST',
            data: {
                action: 'crm_reset_pdf_section_order',
                nonce: (typeof crmData !== 'undefined' && crmData.nonce) ? crmData.nonce : '',
                doc_type: docType,
                entry_id: entryId,
                course_id: courseId,
                is_sidebar: 1
            },
            success: function (res) {
                $btn.prop('disabled', false);
                if (res.success && res.data && res.data.html) {
                    if (window.crmJsCache && typeof window.crmJsCache.cleanPartial === 'function') {
                        window.crmJsCache.cleanPartial('pdf_' + (res.data ? res.data.doc_type : docType));
                    }
                    $parent.html(res.data.html);
                    initPdfSectionSortables();
                    if (res.data.pdf_url) {
                        const freshUrl = res.data.pdf_url + '?t=' + new Date().getTime();
                        const respDoc = res.data.doc_type;

                        const $docTab = jQuery('.crm-preview-switch-embed[data-doc="' + respDoc + '"]');
                        if ($docTab.length) {
                            $docTab.data('url', res.data.pdf_url).attr('data-url', res.data.pdf_url);
                        }

                        const $activeTab = jQuery('.crm-preview-switch-embed.active');
                        const isDocActive = !$activeTab.length || ($activeTab.data('doc') === respDoc);
                        const $embed = jQuery('#x-sieben-pdf-preview embed');
                        if ($embed.length && isDocActive) {
                            $embed.attr('src', freshUrl);
                        }

                        const $docDl = jQuery('a[data-doc-download="' + respDoc + '"]');
                        if ($docDl.length) {
                            $docDl.attr('href', res.data.pdf_url);
                        } else {
                            const $downloadLink = jQuery('#x-sieben-button-row a[download]');
                            if ($downloadLink.length) {
                                $downloadLink.attr('href', res.data.pdf_url);
                            }
                        }

                        const $emailBtns = jQuery('#x-sieben-button-row .x-sieben-email-btn');
                        $emailBtns.each(function () {
                            const currentPdf = jQuery(this).data('pdf') || '';
                            if (currentPdf.indexOf(',') !== -1) {
                                const parts = currentPdf.split(',');
                                if (respDoc === 'angebot') {
                                    parts[0] = res.data.pdf_url;
                                } else if (respDoc === 'kb') {
                                    parts[1] = res.data.pdf_url;
                                }
                                const combined = parts.join(',');
                                jQuery(this).data('pdf', combined).attr('data-pdf', combined);
                            } else {
                                jQuery(this).data('pdf', res.data.pdf_url).attr('data-pdf', res.data.pdf_url);
                            }
                        });
                    }
                }
            },
            error: function () {
                $btn.prop('disabled', false);
            }
        });
    });

    // =========================================================================
    // CRM E-Mail Subject Editor & Mailer Subject Handlers
    // =========================================================================

    // E-Mail Subject Editor: Save template subject via AJAX
    jQuery(document).on('click', '.crm-btn-save-subject', function (e) {
        e.preventDefault();
        const $btn = jQuery(this);
        const docType = $btn.data('doc-type');
        const $box = $btn.closest('.crm-email-subject-editor-box');
        const $input = $box.find('.crm-email-subject-input');
        const subjectVal = $input.val();
        const $status = $box.find('.crm-subject-save-status');

        $btn.prop('disabled', true);
        const ajaxUrl = (typeof crmData !== 'undefined' && crmData.ajaxUrl) ? crmData.ajaxUrl : ((typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php');
        const nonce = (typeof crmData !== 'undefined' && crmData.nonce) ? crmData.nonce : '';

        jQuery.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'crm_save_email_subject',
                nonce: nonce,
                doc_type: docType,
                subject: subjectVal
            },
            success: function (res) {
                $btn.prop('disabled', false);
                if (res.success) {
                    if (window.crmJsCache && typeof window.crmJsCache.cleanPartial === 'function') {
                        window.crmJsCache.cleanPartial('email_subject_' + docType);
                    }
                    const msg = (res.data && res.data.message) ? res.data.message : 'Betreffzeile gespeichert.';
                    $status.text('✓ ' + msg).css({ color: '#16a34a' }).fadeIn().delay(3000).fadeOut();
                } else {
                    const msg = (res.data && res.data.message) ? res.data.message : 'Fehler beim Speichern.';
                    $status.text('✗ ' + msg).css({ color: '#dc2626' }).fadeIn().delay(4000).fadeOut();
                }
            },
            error: function () {
                $btn.prop('disabled', false);
                $status.text('✗ Serverfehler beim Speichern').css({ color: '#dc2626' }).fadeIn().delay(4000).fadeOut();
            }
        });
    });

    // E-Mail Subject Editor: Reset to default template subject
    jQuery(document).on('click', '.crm-btn-reset-subject', function (e) {
        e.preventDefault();
        const $btn = jQuery(this);
        const $box = $btn.closest('.crm-email-subject-editor-box');
        const $input = $box.find('.crm-email-subject-input');
        const defaultSubject = $input.data('default') || '';
        if (defaultSubject) {
            $input.val(defaultSubject);
            $box.find('.crm-btn-save-subject').trigger('click');
        }
    });

    // E-Mail Subject Editor: Insert chip into settings subject input at cursor position
    jQuery(document).on('click', '.crm-insert-subject-chip', function (e) {
        e.preventDefault();
        const targetId = jQuery(this).data('target');
        const chip = jQuery(this).data('chip');
        const el = document.getElementById(targetId);
        if (!el) return;

        const start = el.selectionStart || 0;
        const end = el.selectionEnd || 0;
        const val = el.value;
        el.value = val.substring(0, start) + chip + val.substring(end);
        el.focus();
        el.selectionStart = el.selectionEnd = start + chip.length;
    });

    // Mailer Subject Box: Insert chip into #x_sieben_subject at cursor position
    jQuery(document).on('click', '.crm-insert-mailer-chip', function (e) {
        e.preventDefault();
        const chip = jQuery(this).data('chip');
        const el = document.getElementById('x_sieben_subject');
        if (!el) return;

        const start = el.selectionStart || 0;
        const end = el.selectionEnd || 0;
        const val = el.value;
        el.value = val.substring(0, start) + chip + val.substring(end);
        el.focus();
        el.selectionStart = el.selectionEnd = start + chip.length;
    });

});


