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

// =============================================================================
// NEXUS 2-WORKER CONCURRENCY POOL (Live Server Protection)
// Garantiert maximal 2 gleichzeitige AJAX-Worker für Stapelverarbeitungen
// (z. B. Bulk-PDF-Generierung, Massen-Status-Updates), um den Timme-Hosting
// Live-Server (PHP-FPM) vor Überlastung zu schützen.
// =============================================================================
(function (window) {
    window.crmWorkerQueue = {
        maxWorkers: (typeof crmData !== 'undefined' && crmData.maxParallelWorkers) ? parseInt(crmData.maxParallelWorkers, 10) : 2,
        activeWorkers: 0,

        /**
         * Führt eine Liste von Task-Funktionen mit maximal `maxWorkers` (2) gleichzeitig aus.
         * @param {Array<Function>} taskFunctions - Array von Funktionen, die ein Promise zurückgeben.
         * @param {Function} onProgress - Callback (completed, total, activeWorkers, currentResult)
         * @return {Promise<Array>}
         */
        runAll: function (taskFunctions, onProgress) {
            const total = taskFunctions.length;
            let completed = 0;
            const results = new Array(total);
            const self = this;
            const limit = Math.max(1, self.maxWorkers || 2);

            return new Promise(function (resolve) {
                if (total === 0) {
                    resolve([]);
                    return;
                }

                let currentIndex = 0;

                function startNextWorker() {
                    while (self.activeWorkers < limit && currentIndex < total) {
                        const index = currentIndex++;
                        self.activeWorkers++;
                        const taskFn = taskFunctions[index];

                        Promise.resolve()
                            .then(function () { return taskFn(); })
                            .then(function (result) {
                                results[index] = { success: true, data: result };
                            })
                            .catch(function (err) {
                                results[index] = { success: false, error: err };
                            })
                            .finally(function () {
                                self.activeWorkers--;
                                completed++;
                                if (typeof onProgress === 'function') {
                                    try {
                                        onProgress(completed, total, self.activeWorkers, results[index]);
                                    } catch (cbErr) {
                                        console.warn('[CRM Worker Queue] Progress callback error:', cbErr);
                                    }
                                }
                                if (completed === total) {
                                    resolve(results);
                                } else {
                                    startNextWorker();
                                }
                            });
                    }
                }

                startNextWorker();
            });
        },

        /**
         * Schwebender Fortschrittsbanner im CRM-Admin
         */
        showProgressBanner: function (title, current, total) {
            let banner = document.getElementById('crm-worker-progress-banner');
            if (!banner) {
                banner = document.createElement('div');
                banner.id = 'crm-worker-progress-banner';
                banner.style.cssText = 'position:fixed; bottom:24px; right:24px; z-index:999999; background:#0f172a; color:#fff; padding:12px 18px; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.3); font-size:13px; font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif; display:flex; align-items:center; gap:12px; transition:all 0.3s ease; border-left:4px solid #3b82f6;';
                document.body.appendChild(banner);
            }
            const pct = total > 0 ? Math.round((current / total) * 100) : 0;
            banner.innerHTML = '<span class="dashicons dashicons-update spin" style="color:#3b82f6;"></span> <div><strong>' + crmEscapeHtml(title) + '</strong><div style="font-size:11.5px; color:#94a3b8; margin-top:2px;">' + current + ' von ' + total + ' verarbeitet (' + pct + '%) • <strong>2 Worker aktiv</strong></div></div>';
            if (current >= total) {
                banner.style.borderLeftColor = '#10b981';
                banner.innerHTML = '✅ <div><strong>' + crmEscapeHtml(title) + ' abgeschlossen!</strong><div style="font-size:11.5px; color:#94a3b8; margin-top:2px;">Alle ' + total + ' Dokumente erfolgreich generiert.</div></div>';
                setTimeout(function () {
                    if (banner && banner.parentNode) {
                        banner.style.opacity = '0';
                        setTimeout(function () { banner.remove(); }, 400);
                    }
                }, 3500);
            }
        }
    };
})(window);


// Universal HTML escaper available across all closures
function crmEscapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}
var escapeHtml = crmEscapeHtml;
window.crmEscapeHtml = crmEscapeHtml;

document.addEventListener("DOMContentLoaded", function () {
    // Ensure jQuery alias is safely available inside DOMContentLoaded scope
    const $ = window.jQuery || window.$;
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
        window.activeEntryId = activeEntryId;

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
    window.openEditorView = openEditorView;
    window.closeEditorView = closeEditorView;

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
            const wizardBackdrop = document.getElementById('crm-wizard-modal-backdrop');
            if (wizardBackdrop && (wizardBackdrop.style.display === 'flex' || wizardBackdrop.style.display === 'block')) {
                wizardBackdrop.style.display = 'none';
                return;
            }
            const modalBackdrop = document.getElementById('crm-history-modal-backdrop');
            if (modalBackdrop && (modalBackdrop.style.display === 'flex' || modalBackdrop.style.display === 'block')) {
                modalBackdrop.style.display = 'none';
                return;
            }
            const snapshotsBackdrop = document.getElementById('crm-snapshots-modal-backdrop');
            if (snapshotsBackdrop && (snapshotsBackdrop.style.display === 'flex' || snapshotsBackdrop.style.display === 'block')) {
                snapshotsBackdrop.style.display = 'none';
                return;
            }
            const snapshotDetailBackdrop = document.getElementById('crm-snapshot-detail-modal-backdrop');
            if (snapshotDetailBackdrop && (snapshotDetailBackdrop.style.display === 'flex' || snapshotDetailBackdrop.style.display === 'block')) {
                snapshotDetailBackdrop.style.display = 'none';
                return;
            }
            // Close any open Quick Edit rows on Escape
            const openQuickEdits = document.querySelectorAll('tr.crm-quick-edit-row');
            let quickEditWasOpen = false;
            openQuickEdits.forEach(r => {
                if (r.style.display !== 'none' && r.style.display !== '') {
                    r.style.display = 'none';
                    quickEditWasOpen = true;
                }
            });
            if (quickEditWasOpen) {
                document.querySelectorAll('tr.crm-entry-row.is-quick-editing').forEach(r => r.classList.remove('is-quick-editing'));
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

    // =========================================================================
    // 4-in-1 MULTI-VIEW DASHBOARD CONTROLLER (Cards, Split, Kanban, Table)
    // =========================================================================
    function loadSplitDossier(itemEl) {
        if (!itemEl) return;
        const panel = document.getElementById('crm-split-dossier-panel');
        if (!panel) return;

        const entryId = itemEl.dataset.entryId;
        const courseId = itemEl.dataset.courseId || 0;
        const clientName = itemEl.dataset.clientName || 'Kunde';

        // Mark active in split sidebar
        document.querySelectorAll('#crm-view-split .crm-split-item').forEach(i => i.classList.remove('is-active'));
        itemEl.classList.add('is-active');

        // If current panel already displays this exact entry, cache it and do nothing
        const currentInner = panel.querySelector('.crm-split-dossier-inner');
        if (currentInner && String(currentInner.dataset.entryId) === String(entryId)) {
            const cacheKey = 'split_dossier_' + entryId;
            if (window.crmJsCache && !window.crmJsCache.has(cacheKey)) {
                window.crmJsCache.set(cacheKey, panel.innerHTML);
            }
            return;
        }

        // Check client cache
        const cacheKey = 'split_dossier_' + entryId;
        if (window.crmJsCache && window.crmJsCache.has(cacheKey)) {
            panel.innerHTML = window.crmJsCache.get(cacheKey);
            return;
        }

        // Show loading skeleton
        panel.innerHTML = `
            <div class="crm-split-loading-state" style="padding: 50px 24px; text-align: center; color: #64748b;">
                <span class="dashicons dashicons-update spin" style="font-size: 32px; width: 32px; height: 32px; margin-bottom: 12px; color: #007C90;"></span>
                <h4 style="font-size: 16px; color: #1e293b; margin: 0 0 6px 0;">Dossier wird geladen: <strong>${crmEscapeHtml(clientName)}</strong></h4>
                <p style="font-size: 13px; color: #64748b; margin: 0;">Spickzettel, Dokumente & Wizard-Optionen werden vorbereitet...</p>
            </div>
        `;

        const crmNonce = (typeof crmData !== 'undefined' && crmData.nonce) ? crmData.nonce : ((typeof crmSettingsData !== 'undefined' && crmSettingsData.nonce) ? crmSettingsData.nonce : nonce);
        const formData = new FormData();
        formData.append('action', 'crm_get_split_dossier');
        formData.append('nonce', crmNonce);
        formData.append('security', crmNonce);
        formData.append('entry_id', entryId);
        formData.append('course_id', courseId);

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data && data.data.output) {
                    panel.innerHTML = data.data.output;
                    if (window.crmJsCache) {
                        window.crmJsCache.set(cacheKey, data.data.output);
                    }
                } else {
                    panel.innerHTML = `
                        <div style="padding: 40px; text-align: center; color: #dc2626;">
                            <span class="dashicons dashicons-warning" style="font-size: 32px; width: 32px; height: 32px; margin-bottom: 12px;"></span>
                            <h4 style="margin:0 0 8px 0; color:#b91c1c;">Fehler beim Laden des Dossiers</h4>
                            <p style="margin:0; font-size:13px; color:#64748b;">${(data && data.data && data.data.message) ? data.data.message : 'Dossier konnte nicht geöffnet werden.'}</p>
                        </div>
                    `;
                }
            })
            .catch(err => {
                console.error('Split Dossier Error:', err);
                panel.innerHTML = `
                    <div style="padding: 40px; text-align: center; color: #dc2626;">
                        <span class="dashicons dashicons-warning" style="font-size: 32px; width: 32px; height: 32px; margin-bottom: 12px;"></span>
                        <h4 style="margin:0 0 8px 0; color:#b91c1c;">Verbindungsfehler</h4>
                        <p style="margin:0; font-size:13px; color:#64748b;">Das Dossier konnte aufgrund eines Netzwerkfehlers nicht geladen werden.</p>
                    </div>
                `;
            });
    }

    function switchCrmView(viewName) {
        if (!viewName) return;
        const STORAGE_KEY = 'x7_crm_active_view';

        // Update button states across any switcher bars
        document.querySelectorAll('.crm-view-btn').forEach(btn => {
            const isActive = btn.dataset.view === viewName;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        // Hide all view panes
        document.querySelectorAll('.crm-view-pane').forEach(pane => {
            pane.style.display = 'none';
        });

        // Show target pane
        const targetPane = document.getElementById('crm-view-' + viewName);
        if (targetPane) {
            if (viewName === 'cards') {
                targetPane.style.display = 'grid';
            } else if (viewName === 'split') {
                targetPane.style.display = 'flex';
                const activeSplitItem = targetPane.querySelector('.crm-split-item.is-active') ||
                                       targetPane.querySelector('.crm-split-item:not([style*="display: none"])') ||
                                       targetPane.querySelector('.crm-split-item');
                const panel = document.getElementById('crm-split-dossier-panel');
                if (panel && activeSplitItem) {
                    activeSplitItem.classList.add('is-active');
                    const currentInner = panel.querySelector('.crm-split-dossier-inner');
                    if (!currentInner || String(currentInner.dataset.entryId) !== String(activeSplitItem.dataset.entryId)) {
                        loadSplitDossier(activeSplitItem);
                    } else if (window.crmJsCache && activeSplitItem.dataset.entryId) {
                        window.crmJsCache.set('split_dossier_' + activeSplitItem.dataset.entryId, panel.innerHTML);
                    }
                }
            } else if (viewName === 'kanban') {
                targetPane.style.display = 'flex';
                const board = targetPane;
                const globalBtn = document.getElementById('crm-kanban-load-all-global-btn');
                if (board && globalBtn) {
                    const loaded = parseInt(board.dataset.loadedCount, 10) || 0;
                    const total = parseInt(board.dataset.totalEntries, 10) || 0;
                    globalBtn.style.display = (total > loaded) ? 'inline-flex' : 'none';
                }
            } else if (viewName === 'table') {
                targetPane.style.display = 'block';
            }
        }

        if (viewName !== 'kanban') {
            const globalBtn = document.getElementById('crm-kanban-load-all-global-btn');
            if (globalBtn) globalBtn.style.display = 'none';
        }

        // Save to localStorage
        try {
            localStorage.setItem(STORAGE_KEY, viewName);
        } catch (e) {}
    }

    function initCrmViewSwitcher() {
        const STORAGE_KEY = 'x7_crm_active_view';
        let savedView = 'cards';
        try {
            savedView = localStorage.getItem(STORAGE_KEY) || 'cards';
        } catch (e) {
            savedView = 'cards';
        }

        // Initialize active view
        switchCrmView(savedView);
    }

    // =========================================================================
    // KANBAN TIMELINE CONTROLLER (Infinite Scroll for Open & Collapsible Done)
    // =========================================================================
    function toggleKanbanDoneColumn(forceExpand = null) {
        const doneCol = document.querySelector('.crm-kanban-column.crm-col-done');
        if (!doneCol) return;

        const cardsWrap = doneCol.querySelector('.crm-kanban-cards-wrap');
        const toggleBtn = doneCol.querySelector('.crm-kanban-col-toggle-btn');
        const isCurrentlyCollapsed = doneCol.classList.contains('crm-col-collapsed');
        const shouldExpand = (forceExpand !== null) ? forceExpand : isCurrentlyCollapsed;

        if (shouldExpand) {
            doneCol.classList.remove('crm-col-collapsed');
            doneCol.removeAttribute('data-is-collapsed');
            if (cardsWrap) cardsWrap.style.display = 'flex';
            if (toggleBtn) {
                toggleBtn.setAttribute('aria-expanded', 'true');
                const textSpan = toggleBtn.querySelector('.crm-toggle-text');
                if (textSpan) textSpan.textContent = 'Einklappen';
                const icon = toggleBtn.querySelector('.dashicons');
                if (icon) icon.className = 'dashicons dashicons-arrow-up-alt2';
            }
            try { localStorage.setItem('x7_crm_kanban_done_expanded', '1'); } catch (e) {}
        } else {
            doneCol.classList.add('crm-col-collapsed');
            doneCol.setAttribute('data-is-collapsed', '1');
            if (cardsWrap) cardsWrap.style.display = 'none';
            if (toggleBtn) {
                toggleBtn.setAttribute('aria-expanded', 'false');
                const textSpan = toggleBtn.querySelector('.crm-toggle-text');
                if (textSpan) textSpan.textContent = 'Aufklappen';
                const icon = toggleBtn.querySelector('.dashicons');
                if (icon) icon.className = 'dashicons dashicons-arrow-down-alt2';
            }
            try { localStorage.setItem('x7_crm_kanban_done_expanded', '0'); } catch (e) {}
        }
    }

    function revealKanbanDeferredCards(colEl, countToReveal = 10) {
        if (!colEl) return 0;
        const deferredCards = colEl.querySelectorAll('.crm-kanban-card.crm-kanban-card-deferred');
        let revealedNow = 0;

        deferredCards.forEach((card, idx) => {
            if (idx < countToReveal) {
                card.classList.remove('crm-kanban-card-deferred');
                card.classList.add('crm-card-revealed');
                card.style.display = '';
                revealedNow++;
            }
        });

        // Update column footer counters
        const totalInCol = colEl.querySelectorAll('.crm-kanban-card').length;
        const visibleInCol = colEl.querySelectorAll('.crm-kanban-card:not(.crm-kanban-card-deferred)').length;
        const remDeferred = colEl.querySelectorAll('.crm-kanban-card.crm-kanban-card-deferred').length;

        const footer = colEl.querySelector('.crm-kanban-infinite-footer');
        if (footer) {
            const statusBox = footer.querySelector('.crm-kanban-infinite-status');
            const showingText = footer.querySelector('.crm-showing-text');
            const loadedIndicator = footer.querySelector('.crm-kanban-all-loaded-indicator');

            if (remDeferred > 0) {
                if (showingText) showingText.textContent = `Zeige ${visibleInCol} von ${totalInCol}`;
                if (statusBox) statusBox.style.display = 'flex';
                if (loadedIndicator) loadedIndicator.style.display = 'none';
            } else {
                if (statusBox) statusBox.style.display = 'none';
                if (loadedIndicator) {
                    loadedIndicator.style.display = 'flex';
                    loadedIndicator.innerHTML = `<span class="dashicons dashicons-yes"></span> Alle ${totalInCol} geladen`;
                }
            }
        }

        return remDeferred;
    }

    function revealAllKanbanDeferredCards(colEl) {
        if (!colEl) return;
        const deferredCards = colEl.querySelectorAll('.crm-kanban-card.crm-kanban-card-deferred');
        deferredCards.forEach(card => {
            card.classList.remove('crm-kanban-card-deferred');
            card.classList.add('crm-card-revealed');
            card.style.display = '';
        });

        const totalInCol = colEl.querySelectorAll('.crm-kanban-card').length;
        const footer = colEl.querySelector('.crm-kanban-infinite-footer');
        if (footer) {
            const statusBox = footer.querySelector('.crm-kanban-infinite-status');
            const loadedIndicator = footer.querySelector('.crm-kanban-all-loaded-indicator');
            if (statusBox) statusBox.style.display = 'none';
            if (loadedIndicator) {
                loadedIndicator.style.display = 'flex';
                loadedIndicator.innerHTML = `<span class="dashicons dashicons-yes"></span> Alle ${totalInCol} geladen`;
            }
        }
    }

    let crmKanbanServerLoading = false;
    function fetchMoreKanbanServerEntries(onComplete = null) {
        const board = document.getElementById('crm-view-kanban');
        if (!board || crmKanbanServerLoading) return;

        let loadedCount = parseInt(board.dataset.loadedCount, 10) || 0;
        let totalEntries = parseInt(board.dataset.totalEntries, 10) || 0;

        if (loadedCount >= totalEntries) {
            const globalBtn = document.getElementById('crm-kanban-load-all-global-btn');
            if (globalBtn) globalBtn.style.display = 'none';
            if (onComplete) onComplete();
            return;
        }

        crmKanbanServerLoading = true;
        const globalBtn = document.getElementById('crm-kanban-load-all-global-btn');
        if (globalBtn) {
            globalBtn.classList.add('is-loading');
            globalBtn.querySelector('span:last-child').textContent = 'Lade Anfragen...';
        }

        const crmNonce = (typeof crmData !== 'undefined' && crmData.nonce) ? crmData.nonce : ((typeof crmSettingsData !== 'undefined' && crmSettingsData.nonce) ? crmSettingsData.nonce : nonce);
        const urlParams = new URLSearchParams(window.location.search);
        const formId = urlParams.get('form_id') || 'all';

        const formData = new FormData();
        formData.append('action', 'crm_get_more_kanban_entries');
        formData.append('nonce', crmNonce);
        formData.append('security', crmNonce);
        formData.append('offset', loadedCount);
        formData.append('limit', 30);
        formData.append('form_id', formId);

        fetch(ajaxUrl, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.data) {
                    const cardsByCol = data.data.cards_by_col || {};
                    for (const [colKey, cardStrings] of Object.entries(cardsByCol)) {
                        if (!Array.isArray(cardStrings) || !cardStrings.length) continue;
                        const col = board.querySelector(`.crm-kanban-column[data-col-key="${colKey}"]`);
                        if (!col) continue;
                        const wrap = col.querySelector('.crm-kanban-cards-wrap');
                        if (!wrap) continue;
                        const emptyNotice = wrap.querySelector('.crm-kanban-empty');
                        if (emptyNotice) emptyNotice.remove();
                        const footer = wrap.querySelector('.crm-kanban-infinite-footer');

                        cardStrings.forEach(cardHtml => {
                            const tmp = document.createElement('div');
                            tmp.innerHTML = cardHtml.trim();
                            const newCard = tmp.firstElementChild;
                            if (newCard) {
                                newCard.classList.add('crm-card-revealed');
                                if (footer) {
                                    wrap.insertBefore(newCard, footer);
                                } else {
                                    wrap.appendChild(newCard);
                                }
                            }
                        });

                        // Update column badge count
                        const countEl = col.querySelector('.crm-kanban-col-count');
                        const allCards = col.querySelectorAll('.crm-kanban-card');
                        if (countEl) countEl.textContent = allCards.length;

                        // Update column footer
                        const visibleInCol = col.querySelectorAll('.crm-kanban-card:not(.crm-kanban-card-deferred)').length;
                        const colFooter = col.querySelector('.crm-kanban-infinite-footer');
                        if (colFooter) {
                            const showingText = colFooter.querySelector('.crm-showing-text');
                            if (showingText) showingText.textContent = `Zeige ${visibleInCol} von ${allCards.length}`;
                        }
                    }

                    // Update board metadata
                    board.dataset.loadedCount = data.data.new_offset;
                    board.dataset.totalEntries = data.data.total_entries;

                    const visibleCountEl = document.getElementById('crm-visible-count');
                    if (visibleCountEl) visibleCountEl.textContent = data.data.new_offset;

                    const totalCountEl = document.getElementById('crm-total-count');
                    if (totalCountEl) totalCountEl.textContent = data.data.total_entries;

                    if (!data.data.has_more || data.data.new_offset >= data.data.total_entries) {
                        if (globalBtn) globalBtn.style.display = 'none';
                        board.querySelectorAll('.crm-kanban-all-loaded-indicator').forEach(el => {
                            el.style.display = 'flex';
                        });
                        board.querySelectorAll('.crm-kanban-infinite-status').forEach(el => {
                            el.style.display = 'none';
                        });
                    }
                }
            })
            .catch(err => console.error('Kanban server fetch error:', err))
            .finally(() => {
                crmKanbanServerLoading = false;
                if (globalBtn) {
                    globalBtn.classList.remove('is-loading');
                    globalBtn.querySelector('span:last-child').textContent = 'Alle laden (Infinite)';
                }
                if (onComplete) onComplete();
            });
    }

    function initCrmKanbanTimeline() {
        // 1. Restore collapsed/expanded state of 'Abgeschlossen'
        let savedDoneState = '0';
        try {
            savedDoneState = localStorage.getItem('x7_crm_kanban_done_expanded') || '0';
        } catch (e) {}
        if (savedDoneState === '1') {
            toggleKanbanDoneColumn(true);
        }

        // 2. Click delegation for 'Abgeschlossen' toggle button & header
        document.addEventListener('click', function (e) {
            const toggleBtn = e.target.closest('.crm-kanban-col-toggle-btn');
            if (toggleBtn) {
                e.preventDefault();
                e.stopPropagation();
                toggleKanbanDoneColumn();
                return;
            }

            const doneHeader = e.target.closest('.crm-kanban-done-header');
            if (doneHeader && !e.target.closest('a, button, select, input')) {
                toggleKanbanDoneColumn();
                return;
            }

            // Click on column "Alle anzeigen" button
            const showAllColBtn = e.target.closest('.crm-kanban-load-all-col-btn');
            if (showAllColBtn) {
                e.preventDefault();
                const col = showAllColBtn.closest('.crm-kanban-column');
                if (col) {
                    revealAllKanbanDeferredCards(col);
                    const board = document.getElementById('crm-view-kanban');
                    if (board) {
                        let loaded = parseInt(board.dataset.loadedCount, 10) || 0;
                        let total = parseInt(board.dataset.totalEntries, 10) || 0;
                        if (loaded < total) {
                            fetchMoreKanbanServerEntries();
                        }
                    }
                }
                return;
            }

            // Click on Global "Alle laden (Infinite)" button
            const globalLoadBtn = e.target.closest('#crm-kanban-load-all-global-btn');
            if (globalLoadBtn) {
                e.preventDefault();
                const board = document.getElementById('crm-view-kanban');
                if (board) {
                    board.querySelectorAll('.crm-kanban-column:not(.crm-col-done)').forEach(col => {
                        revealAllKanbanDeferredCards(col);
                    });
                    function loadAllLoop() {
                        let loaded = parseInt(board.dataset.loadedCount, 10) || 0;
                        let total = parseInt(board.dataset.totalEntries, 10) || 0;
                        if (loaded < total) {
                            fetchMoreKanbanServerEntries(loadAllLoop);
                        }
                    }
                    loadAllLoop();
                }
                return;
            }
        });

        // 3. Progressive Infinite Scroll on open columns
        let isThrottled = false;
        document.querySelectorAll('.crm-kanban-cards-wrap:not(.crm-kanban-done-wrap)').forEach(wrap => {
            wrap.addEventListener('scroll', function () {
                if (isThrottled) return;
                isThrottled = true;
                requestAnimationFrame(() => {
                    if (wrap.scrollTop + wrap.clientHeight >= wrap.scrollHeight - 70) {
                        const col = wrap.closest('.crm-kanban-column');
                        if (col) {
                            const rem = revealMoreCards(col, 10);
                            if (rem === 0) {
                                const board = document.getElementById('crm-view-kanban');
                                if (board) {
                                    let loaded = parseInt(board.dataset.loadedCount, 10) || 0;
                                    let total = parseInt(board.dataset.totalEntries, 10) || 0;
                                    if (loaded < total) {
                                        fetchMoreKanbanServerEntries();
                                    }
                                }
                            }
                        }
                    }
                    isThrottled = false;
                });
            }, { passive: true });
        });

        // 4. Update visibility of global infinite load button based on loaded vs total count
        const board = document.getElementById('crm-view-kanban');
        const globalBtn = document.getElementById('crm-kanban-load-all-global-btn');
        if (board && globalBtn) {
            let loaded = parseInt(board.dataset.loadedCount, 10) || 0;
            let total = parseInt(board.dataset.totalEntries, 10) || 0;
            if (total > loaded) {
                globalBtn.style.display = 'inline-flex';
            }
        }
    }

    // Expose helpers globally
    window.crmLoadSplitDossier = loadSplitDossier;
    window.crmSwitchView = switchCrmView;
    window.crmInitViewSwitcher = initCrmViewSwitcher;
    window.crmToggleKanbanDone = toggleKanbanDoneColumn;
    window.crmInitKanbanTimeline = initCrmKanbanTimeline;

    // Document-level delegation for View Switcher Buttons
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.crm-view-btn');
        if (btn) {
            e.preventDefault();
            const viewName = btn.dataset.view;
            if (viewName) switchCrmView(viewName);
            return;
        }

        const splitItem = e.target.closest('.crm-split-item');
        if (splitItem) {
            if (e.target.closest('a, button, select, input, label')) return;
            e.preventDefault();
            loadSplitDossier(splitItem);
            return;
        }
    });

    // Redundant jQuery event delegation
    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('click', '.crm-view-btn', function (e) {
            e.preventDefault();
            const viewName = jQuery(this).data('view');
            if (viewName) switchCrmView(viewName);
        });

        jQuery(document).on('click', '.crm-split-item', function (e) {
            if (jQuery(e.target).closest('a, button, select, input, label').length) return;
            e.preventDefault();
            loadSplitDossier(this);
        });
    }

    // Run view switcher and Kanban timeline initialization
    initCrmViewSwitcher();
    initCrmKanbanTimeline();

    if (table) {
        // --- Client-side Search & Status Filter ---
        const statusFilter = document.getElementById("crmStatusFilter");

        const updatePlaceholder = () => {
            const entryRows = table ? table.querySelectorAll("tbody tr.crm-entry-row") : [];
            const visibleRows = Array.from(entryRows).filter(row => row.style.display !== "none").length;
            if (searchInput) {
                searchInput.placeholder = `Search (${visibleRows} / ${entryRows.length})...`;
            }
        };

        const filterRows = () => {
            const searchVal = (searchInput ? searchInput.value : "").toLowerCase().trim();
            const statusVal = (statusFilter ? statusFilter.value : "").toLowerCase().trim();
            const entryRows = table ? table.querySelectorAll("tbody tr.crm-entry-row") : [];

            // 1. Table Rows Filter
            entryRows.forEach(row => {
                const entryId = row.dataset.entryId;
                const text = row.innerText.toLowerCase();
                const matchesSearch = !searchVal || text.includes(searchVal);
                const statusCell = row.querySelector('.crm-status-cell');
                const statusText = statusCell ? statusCell.innerText.toLowerCase() : '';
                const matchesStatus = !statusVal || statusText.includes(statusVal);

                const isVisible = matchesSearch && matchesStatus;
                row.style.display = isVisible ? "" : "none";
                const qRow = document.getElementById('crm-quick-edit-row-' + entryId);
                if (qRow && !isVisible) {
                    qRow.style.display = "none";
                }
            });

            // 2. Customer Cards Filter
            let visibleCards = 0;
            const cards = document.querySelectorAll('#crm-view-cards .crm-customer-card');
            cards.forEach(card => {
                const text = card.innerText.toLowerCase();
                const statusCell = card.querySelector('.crm-status-label');
                const statusText = statusCell ? statusCell.innerText.toLowerCase() : (card.dataset.statusKey || '').toLowerCase();
                const matchesSearch = !searchVal || text.includes(searchVal);
                const matchesStatus = !statusVal || statusText.includes(statusVal);

                const isVisible = matchesSearch && matchesStatus;
                card.style.display = isVisible ? "" : "none";
                if (isVisible) visibleCards++;
            });

            // 3. Kanban Cards Filter & Column Count Updates
            const kanbanBoard = document.getElementById('crm-view-kanban');
            if (kanbanBoard) {
                kanbanBoard.querySelectorAll('.crm-kanban-column').forEach(col => {
                    let colVisible = 0;
                    col.querySelectorAll('.crm-kanban-card').forEach(kCard => {
                        const text = kCard.innerText.toLowerCase();
                        const statusCell = kCard.querySelector('.crm-status-label');
                        const statusText = statusCell ? statusCell.innerText.toLowerCase() : (kCard.dataset.statusKey || '').toLowerCase();
                        const matchesSearch = !searchVal || text.includes(searchVal);
                        const matchesStatus = !statusVal || statusText.includes(statusVal);

                        const isVisible = matchesSearch && matchesStatus;
                        if (searchVal || statusVal) {
                            kCard.style.display = isVisible ? "" : "none";
                        } else {
                            if (kCard.classList.contains('crm-kanban-card-deferred')) {
                                kCard.style.display = "none";
                            } else {
                                kCard.style.display = isVisible ? "" : "none";
                            }
                        }
                        if (isVisible) colVisible++;
                    });
                    const countEl = col.querySelector('.crm-kanban-col-count');
                    if (countEl) countEl.textContent = colVisible;
                });
            }

            // 4. Split View Items Filter & Auto-Select
            const splitItems = document.querySelectorAll('#crm-view-split .crm-split-item');
            let splitVisible = 0;
            let firstVisibleSplit = null;
            let activeStillVisible = false;

            splitItems.forEach(item => {
                const text = item.innerText.toLowerCase();
                const statusCell = item.querySelector('.crm-status-label');
                const statusText = statusCell ? statusCell.innerText.toLowerCase() : (item.dataset.statusKey || '').toLowerCase();
                const matchesSearch = !searchVal || text.includes(searchVal);
                const matchesStatus = !statusVal || statusText.includes(statusVal);

                const isVisible = matchesSearch && matchesStatus;
                item.style.display = isVisible ? "" : "none";
                if (isVisible) {
                    splitVisible++;
                    if (!firstVisibleSplit) firstVisibleSplit = item;
                    if (item.classList.contains('is-active')) activeStillVisible = true;
                }
            });

            const splitCountEl = document.getElementById('crm-split-visible-count');
            if (splitCountEl) splitCountEl.textContent = splitVisible;

            if (!activeStillVisible && firstVisibleSplit) {
                splitItems.forEach(i => i.classList.remove('is-active'));
                firstVisibleSplit.classList.add('is-active');
                const splitPane = document.getElementById('crm-view-split');
                if (splitPane && splitPane.style.display !== 'none') {
                    loadSplitDossier(firstVisibleSplit);
                }
            }

            // 5. Total Count Badge in View Switcher Toolbar
            const totalVisible = (cards.length > 0) ? visibleCards : Array.from(entryRows).filter(r => r.style.display !== 'none').length;
            const visibleCountEl = document.getElementById('crm-visible-count');
            if (visibleCountEl) visibleCountEl.textContent = totalVisible;

            updatePlaceholder();
            reapplyZebra();
        };

        const reapplyZebra = () => {
            let visibleIdx = 0;
            table.querySelectorAll("tbody tr.crm-entry-row").forEach(row => {
                if (row.style.display !== "none") {
                    if (visibleIdx % 2 === 1) {
                        row.classList.add("alternate");
                    } else {
                        row.classList.remove("alternate");
                    }
                    visibleIdx++;
                }
            });
        };

        if (searchInput) {
            searchInput.addEventListener("input", filterRows);
        }
        if (statusFilter) {
            statusFilter.addEventListener("change", filterRows);
        }
        updatePlaceholder();
        reapplyZebra();

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

                Array.from(tbody.querySelectorAll("tr.crm-entry-row"))
                    .sort(comparer(idx, asc))
                    .forEach(tr => {
                        tbody.appendChild(tr);
                        const qRow = document.getElementById('crm-quick-edit-row-' + tr.dataset.entryId);
                        if (qRow) tbody.appendChild(qRow);
                    });
                reapplyZebra();
            });
        });

        // --- Order By / Sort Dropdown ---
        const sortOrderSelect = document.getElementById("crmSortOrder");
        if (sortOrderSelect) {
            sortOrderSelect.addEventListener("change", function () {
                const val = this.value;
                const tbody = table ? table.querySelector("tbody") : null;

                const itemComparator = (a, b) => {
                    const dateA = parseInt(a.dataset.entryDate, 10) || 0;
                    const dateB = parseInt(b.dataset.entryDate, 10) || 0;
                    const nameA = (a.dataset.clientName || '').trim();
                    const nameB = (b.dataset.clientName || '').trim();
                    const courseA = (a.dataset.courseTitle || '').trim();
                    const courseB = (b.dataset.courseTitle || '').trim();
                    const courseDateA = parseInt(a.dataset.courseDate, 10) || 0;
                    const courseDateB = parseInt(b.dataset.courseDate, 10) || 0;

                    switch (val) {
                        case 'date_asc':
                            return dateA - dateB;
                        case 'date_desc':
                            return dateB - dateA;
                        case 'name_asc':
                            return nameA.localeCompare(nameB, 'de', { numeric: true });
                        case 'name_desc':
                            return nameB.localeCompare(nameA, 'de', { numeric: true });
                        case 'course_asc':
                            return courseA.localeCompare(courseB, 'de', { numeric: true });
                        case 'course_desc':
                            return courseB.localeCompare(courseA, 'de', { numeric: true });
                        case 'course_date_asc':
                            if (!courseDateA && courseDateB) return 1;
                            if (courseDateA && !courseDateB) return -1;
                            return courseDateA - courseDateB;
                        default:
                            return dateB - dateA;
                    }
                };

                // 1. Sort Table Rows
                if (tbody) {
                    const rows = Array.from(tbody.querySelectorAll("tr.crm-entry-row"));
                    rows.sort(itemComparator);
                    rows.forEach(tr => {
                        tbody.appendChild(tr);
                        const qRow = document.getElementById('crm-quick-edit-row-' + tr.dataset.entryId);
                        if (qRow) tbody.appendChild(qRow);
                    });
                    reapplyZebra();

                    // Reset column header indicators
                    table.querySelectorAll("th").forEach(th => {
                        delete th.dataset.sort;
                        const indicator = th.querySelector('.sort-indicator');
                        if (indicator) indicator.textContent = " ⇅";
                    });
                }

                // 2. Sort Customer Cards
                const cardsGrid = document.getElementById("crm-view-cards");
                if (cardsGrid) {
                    const cards = Array.from(cardsGrid.querySelectorAll(".crm-customer-card"));
                    cards.sort(itemComparator);
                    cards.forEach(c => cardsGrid.appendChild(c));
                }

                // 3. Sort Split Sidebar Items
                const splitList = document.querySelector("#crm-view-split .crm-split-list");
                if (splitList) {
                    const splitItems = Array.from(splitList.querySelectorAll(".crm-split-item"));
                    splitItems.sort(itemComparator);
                    splitItems.forEach(item => splitList.appendChild(item));
                }
            });
        }

        // --- AJAX Notices Helper ---
        const displayNotice = (message, type = 'success') => {
            const notice = document.createElement('div');
            notice.className = `notice notice-${type} is-dismissible`;
            notice.innerHTML = `<p>${message}</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>`;
            noticeContainer.innerHTML = '';
            noticeContainer.appendChild(notice);

            notice.querySelector('.notice-dismiss').addEventListener('click', () => notice.remove());
        };

        // --- CRM Global AJAX Actions & More Actions Menu (Table, Cards, Split, Kanban) ---
        document.addEventListener('click', function (e) {
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

            // Handle Wizard trigger button across ALL views (ehemals Vorbereiten)
            const wizardBtn = e.target.closest('.crm-run-wizard-btn, .crm-run-friedelin-btn');
            if (wizardBtn) {
                e.preventDefault();
                e.stopPropagation();

                const entryId = wizardBtn.dataset.entryId || (wizardBtn.closest('[data-entry-id]') ? wizardBtn.closest('[data-entry-id]').dataset.entryId : null);
                const courseId = wizardBtn.dataset.courseId || (wizardBtn.closest('[data-entry-id]') ? wizardBtn.closest('[data-entry-id]').dataset.courseId : 0);
                const row = wizardBtn.closest('tr.crm-entry-row, .crm-customer-card, .crm-kanban-card, .crm-split-item') ||
                            (entryId ? document.querySelector(`tr.crm-entry-row[data-entry-id="${entryId}"], .crm-customer-card[data-entry-id="${entryId}"]`) : null);

                openWizardModal(entryId, courseId, row, wizardBtn);
                return;
            }

            const button = e.target.closest('.crm-action-btn, .crm-direct-editor-btn, .crm-simulate-pdf-btn');
            if (!button) return;

            e.preventDefault();
            e.stopPropagation();

            // Close more actions menu if clicked an item inside
            const parentDropdown = button.closest('.crm-more-actions-dropdown');
            if (parentDropdown) {
                parentDropdown.classList.remove('is-open');
            }
            const docsDropdown = button.closest('.crm-docs-dropdown-menu');
            if (docsDropdown) {
                docsDropdown.style.display = 'none';
            }

            let actionKey = button.dataset.action;
            const entryId = button.dataset.entryId || (button.closest('[data-entry-id]') ? button.closest('[data-entry-id]').dataset.entryId : null);
            const courseId = button.dataset.courseId || (button.closest('[data-entry-id]') ? button.closest('[data-entry-id]').dataset.courseId : 0);
            let context = button.dataset.context; // Get the context from the button
            const docType = button.dataset.doc;
            if (docType) {
                if (docType === 'kb') actionKey = 'xsieben_kurszeitenbestaetigung';
                else if (docType === 'ab') actionKey = 'xsieben_anmeldebestaetigung';
                else if (docType === 'antritt') actionKey = 'xsieben_antrittsbestaetigung';
                else if (docType === 'tb') actionKey = 'xsieben_teilnahmebestaetigung';
                else if (docType === 'diplom') actionKey = 'xsieben_diplom';
                else actionKey = 'xsieben_offer';
            }
            if (!actionKey) actionKey = 'xsieben_offer';
            if (!context) context = actionKey;
            const row = button.closest('tr.crm-entry-row, .crm-customer-card, .crm-kanban-card, .crm-split-item') ||
                        (entryId ? document.querySelector(`tr.crm-entry-row[data-entry-id="${entryId}"], .crm-customer-card[data-entry-id="${entryId}"]`) : null);

            // Open Screen 2 (Editor View)
            openEditorView(row, actionKey);

            button.disabled = true;
            const originalHtml = button.innerHTML;
            if (button.tagName && button.tagName.toLowerCase() !== 'a') {
                button.innerHTML = '<span class="dashicons dashicons-update spin" style="font-size:14px; width:14px; height:14px; vertical-align:text-bottom; margin-right:3px;"></span>...';
            }

            detailsContainer.innerHTML = '<div style="padding:40px 20px; text-align:center; color:#64748b;"><span class="dashicons dashicons-update spin" style="font-size:32px; width:32px; height:32px; margin-bottom:12px;"></span><br><strong style="font-size:15px; color:#1e293b;">Arbeitsbereich wird vorbereitet...</strong><p style="margin-top:6px; font-size:13px; color:#64748b;">PDF-Vorschau und Optionen werden geladen.</p></div>';
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
                    if (button.tagName && button.tagName.toLowerCase() !== 'a') {
                        button.innerHTML = originalHtml;
                    }
                });
        });

        // Close more actions on outside click
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.crm-more-actions-dropdown')) {
                document.querySelectorAll('.crm-more-actions-dropdown.is-open').forEach(d => d.classList.remove('is-open'));
            }
        });

        function updateJourneyTrackerUI(container, statusKey) {
            if (!container) return;
            const tracker = container.querySelector('.crm-journey-tracker');
            if (!tracker) return;

            const milestoneMap = {
                'storniert': 0,
                'neu': 1, 'in_bearbeitung': 1, 'versand_vorbereitet': 1, 'ai_prepared': 1, 'ki_vorbereitet': 1, 'angebot_erstellt': 1, 'test_mail_gesendet': 1,
                'angebot_gesendet': 2, 'kurszeitenbestaetigung_gesendet': 2, 'angebot_und_kurszeiten_gesendet': 2,
                'nachfassen': 3,
                'angemeldet': 4, 'rechnung_gestellt': 4, 'rechnung_bezahlt': 4,
                'teilnahmebestaetigung_gesendet': 5, 'diplom_gesendet': 5, 'abgeschlossen': 5
            };

            const milestoneLabels = {
                0: 'Storniert / Abgesagt',
                1: 'Stufe 1/5: Anfrage',
                2: 'Stufe 2/5: Angebot versendet',
                3: 'Stufe 3/5: Nachfassen',
                4: 'Stufe 4/5: Gebucht & Angemeldet',
                5: 'Stufe 5/5: Abgeschlossen'
            };

            const step = milestoneMap.hasOwnProperty(statusKey) ? milestoneMap[statusKey] : 1;
            const isStorno = (step === 0);

            tracker.setAttribute('data-current-step', step);
            if (isStorno) {
                tracker.classList.add('is-storno');
            } else {
                tracker.classList.remove('is-storno');
            }

            const segs = tracker.querySelectorAll('.crm-journey-seg');
            segs.forEach(seg => {
                const segStep = parseInt(seg.getAttribute('data-step'), 10);
                seg.className = 'crm-journey-seg crm-seg-' + segStep;
                if (isStorno) {
                    seg.classList.add('is-storno');
                } else if (segStep < step) {
                    seg.classList.add('is-completed');
                } else if (segStep === step) {
                    seg.classList.add('is-active');
                } else {
                    seg.classList.add('is-upcoming');
                }
            });

            const caption = tracker.querySelector('.crm-journey-step-text');
            if (caption) {
                caption.textContent = milestoneLabels[step] || ('Stufe ' + step + '/5');
                caption.className = 'crm-journey-step-text crm-text-step-' + step;
            }
        }

        // --- Helper: Move Kanban Card to Appropriate Stage Column ---
        function moveKanbanCard(entryId, newStatus) {
            const kCard = document.querySelector(`.crm-kanban-card[data-entry-id="${entryId}"]`);
            if (!kCard) return;

            const kanbanStageMap = {
                'col_neu': ['neu', 'ki_vorbereitet'],
                'col_ready': ['versand_vorbereitet', 'ai_prepared', 'versandbereit'],
                'col_sent': ['angebot_gesendet', 'angebot_und_kurszeiten_gesendet', 'kurszeitenbestaetigung_gesendet', 'nachfassen', 'angebot_erstellt'],
                'col_booked': ['angemeldet', 'gebucht', 'teilnahmebestaetigung_gesendet'],
                'col_done': ['abgeschlossen', 'diplom_gesendet', 'durchgefuehrt', 'storniert']
            };

            let targetColKey = 'col_neu';
            for (const [colKey, statuses] of Object.entries(kanbanStageMap)) {
                if (statuses.includes(newStatus)) {
                    targetColKey = colKey;
                    break;
                }
            }

            const targetCol = document.querySelector(`.crm-kanban-column[data-col-key="${targetColKey}"]`);
            if (targetCol) {
                const wrap = targetCol.querySelector('.crm-kanban-cards-wrap');
                if (wrap) {
                    const emptyNotice = wrap.querySelector('.crm-kanban-empty');
                    if (emptyNotice) emptyNotice.remove();

                    wrap.prepend(kCard);
                    if (targetColKey === 'col_sent') {
                        kCard.classList.add('crm-kanban-card-sent');
                    } else {
                        kCard.classList.remove('crm-kanban-card-sent');
                    }
                    kCard.classList.add('crm-row-highlight');
                    setTimeout(() => kCard.classList.remove('crm-row-highlight'), 1800);

                    // Recalculate column counters
                    document.querySelectorAll('#crm-view-kanban .crm-kanban-column').forEach(col => {
                        const countEl = col.querySelector('.crm-kanban-col-count');
                        const cards = col.querySelectorAll('.crm-kanban-card');
                        if (countEl) countEl.textContent = cards.length;
                    });
                }
            }
        }

        // --- CRM Status Quick Change via Dropdown (Document Delegation across all 4 Views) ---
        document.addEventListener('change', function (e) {
            if (!e.target.classList.contains('crm-status-dropdown')) return;

            const select = e.target;
            const entryId = select.dataset.entryId;
            const newStatus = select.value;
            const newLabel = select.options[select.selectedIndex] ? select.options[select.selectedIndex].text : newStatus;
            const row = select.closest('tr') || document.querySelector(`tr.crm-entry-row[data-entry-id="${entryId}"]`);
            const cardEl = select.closest('.crm-customer-card') || document.querySelector(`.crm-customer-card[data-entry-id="${entryId}"]`);
            const kanbanCard = select.closest('.crm-kanban-card') || document.querySelector(`.crm-kanban-card[data-entry-id="${entryId}"]`);
            const pill = select.closest('.crm-status-pill');
            const labelEl = pill ? pill.querySelector('.crm-status-label') : null;
            const badgeWrap = row ? row.querySelector('.crm-status-badge-wrap') : null;
            const dateVal = row ? row.querySelector('.crm-status-date-val') : null;

            // Synchronize all dropdowns for this entryId
            document.querySelectorAll(`.crm-status-dropdown[data-entry-id="${entryId}"]`).forEach(s => {
                if (s !== select) s.value = newStatus;
            });

            // Synchronize all pills for this entryId (<1ms feedback)
            document.querySelectorAll(`[data-entry-id="${entryId}"] .crm-status-pill, .crm-status-pill[data-entry-id="${entryId}"]`).forEach(p => {
                p.className = p.className.replace(/\bcrm-status-[a-z0-9_-]+\b/g, '').trim();
                p.classList.add('crm-status-' + newStatus);
                p.classList.add('crm-status-loading');
                p.setAttribute('data-status', newStatus);
                const lbl = p.querySelector('.crm-status-label');
                if (lbl) lbl.textContent = newLabel;
            });

            if (row) updateJourneyTrackerUI(row, newStatus);
            if (cardEl) updateJourneyTrackerUI(cardEl, newStatus);

            // Invalidate cached split dossier
            if (window.crmJsCache && window.crmJsCache.cache) {
                window.crmJsCache.cache.delete('split_dossier_' + entryId);
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
                        const finalStatus = data.data.status_key || newStatus;
                        const finalLabel = data.data.status_label || newLabel;

                        // Synchronize final status across all pills
                        document.querySelectorAll(`[data-entry-id="${entryId}"] .crm-status-pill, .crm-status-pill[data-entry-id="${entryId}"]`).forEach(p => {
                            p.classList.remove('crm-status-loading');
                            p.className = p.className.replace(/\bcrm-status-[a-z0-9_-]+\b/g, '').trim();
                            p.classList.add('crm-status-' + finalStatus);
                            p.setAttribute('data-status', finalStatus);
                            const lbl = p.querySelector('.crm-status-label');
                            if (lbl) lbl.textContent = finalLabel;
                        });

                        // Update data-status-key attributes
                        document.querySelectorAll(`[data-entry-id="${entryId}"]`).forEach(el => {
                            if (el.hasAttribute('data-status-key')) {
                                el.setAttribute('data-status-key', finalStatus);
                            }
                        });

                        // Move Kanban card if present
                        moveKanbanCard(entryId, finalStatus);

                        if (row) updateJourneyTrackerUI(row, finalStatus);
                        if (cardEl) updateJourneyTrackerUI(cardEl, finalStatus);

                        if (badgeWrap && data.data.badge_html) {
                            badgeWrap.innerHTML = data.data.badge_html;
                        }
                        if (dateVal && data.data.date_formatted) {
                            dateVal.textContent = data.data.date_formatted;
                        }
                        if (data.data.actions_html) {
                            if (row) {
                                const actionsCell = row.querySelector('.crm-actions');
                                if (actionsCell) actionsCell.innerHTML = data.data.actions_html;
                            }
                            if (cardEl) {
                                const cardWizard = cardEl.querySelector('.crm-card-wizard-block, .crm-card-next-action-wrap');
                                if (cardWizard) cardWizard.innerHTML = data.data.card_cta_html || data.data.actions_html;
                            }
                            if (kanbanCard) {
                                const kanbanWizard = kanbanCard.querySelector('.crm-kanban-wizard');
                                if (kanbanWizard) {
                                    if (['angebot_gesendet', 'angebot_und_kurszeiten_gesendet', 'kurszeitenbestaetigung_gesendet', 'nachfassen', 'angebot_erstellt'].includes(finalStatus)) {
                                        kanbanWizard.innerHTML = data.data.card_cta_html || data.data.actions_html;
                                    } else {
                                        kanbanWizard.innerHTML = data.data.actions_html;
                                    }
                                }
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
                });
        });

        // --- CRM Foerderung (AMS / WAFF) Checkbox Toggle ---
        document.addEventListener('change', function (e) {
            const cb = e.target.closest('.crm-foerder-cb');
            if (!cb) return;

            const entryId = cb.dataset.entryId;
            const courseId = cb.dataset.courseId || 0;
            const foerderType = cb.dataset.type; // 'ams' or 'waff'
            const isActive = cb.checked;
            const badgeLabel = cb.closest('.crm-foerder-badge');
            const row = cb.closest('tr') || document.querySelector('tr.crm-entry-row[data-entry-id="' + entryId + '"]');
            const statusDropdown = row ? row.querySelector('.crm-status-dropdown') : null;
            const statusKey = statusDropdown ? statusDropdown.value : 'neu';

            // Immediate visual feedback (<1ms)
            if (badgeLabel) {
                if (isActive) {
                    badgeLabel.classList.add('is-active');
                } else {
                    badgeLabel.classList.remove('is-active');
                }
            }

            cb.disabled = true;

            const formData = new FormData();
            formData.append('action', 'crm_toggle_foerderung');
            formData.append('nonce', nonce);
            formData.append('entry_id', entryId);
            formData.append('course_id', courseId);
            formData.append('type', foerderType);
            formData.append('active', isActive ? 1 : 0);
            formData.append('status_key', statusKey);

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (window.crmJsCache && typeof window.crmJsCache.cleanPartial === 'function') {
                            window.crmJsCache.cleanPartial('foerderung_' + entryId);
                        }
                        if (row) {
                            row.setAttribute('data-is-foerderung', data.data.is_foerderung ? '1' : '0');
                            const actionsCell = row.querySelector('.crm-actions');
                            if (actionsCell && data.data.actions_html) {
                                actionsCell.innerHTML = data.data.actions_html;
                            }
                        }
                        // Update any other badges container for this entry (e.g. in details view)
                        document.querySelectorAll('.crm-foerderung-badges[data-entry-id="' + entryId + '"]').forEach(bWrap => {
                            if (bWrap !== cb.closest('.crm-foerderung-badges')) {
                                bWrap.outerHTML = data.data.badges_html;
                            }
                        });
                        displayNotice(data.data.message, 'success');
                    } else {
                        // Revert checkbox state on error
                        cb.checked = !isActive;
                        if (badgeLabel) {
                            badgeLabel.classList.toggle('is-active', !isActive);
                        }
                        displayNotice((data.data && data.data.message) ? data.data.message : 'Fehler beim Ändern der Förderung.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Foerderung Update Error:', err);
                    cb.checked = !isActive;
                    if (badgeLabel) {
                        badgeLabel.classList.toggle('is-active', !isActive);
                    }
                    displayNotice('Förderstatus konnte nicht gespeichert werden.', 'error');
                })
                .finally(() => {
                    cb.disabled = false;
                });
        });

        // --- WordPress Native Quick Edit Drawer ---
        function toggleQuickEdit(entryId) {
            const qRow = document.getElementById('crm-quick-edit-row-' + entryId);
            const parentRow = document.querySelector('tr.crm-entry-row[data-entry-id="' + entryId + '"]');
            if (!qRow) return;

            const isCurrentlyOpen = qRow.style.display !== 'none' && qRow.style.display !== '';
            if (isCurrentlyOpen) {
                qRow.style.display = 'none';
                if (parentRow) parentRow.classList.remove('is-quick-editing');
            } else {
                // Close any other open quick edit row
                document.querySelectorAll('tr.crm-quick-edit-row').forEach(r => {
                    if (r !== qRow) r.style.display = 'none';
                });
                document.querySelectorAll('tr.crm-entry-row.is-quick-editing').forEach(r => {
                    if (r !== parentRow) r.classList.remove('is-quick-editing');
                });

                qRow.style.display = 'table-row';
                if (parentRow) parentRow.classList.add('is-quick-editing');
                const firstInput = qRow.querySelector('input:not([type=hidden]), select');
                if (firstInput) {
                    firstInput.focus();
                }
            }
        }

        // --- Universal Customer Data Edit Modal ---
        function openCustomerEditModal(entryId, courseId) {
            entryId = parseInt(entryId, 10);
            if (!entryId) return;

            const backdrop = document.getElementById('crm-customer-edit-modal-backdrop');
            const content = document.getElementById('crm-customer-edit-modal-content');
            const titleEl = document.getElementById('crm-customer-edit-modal-title');
            if (!backdrop || !content) {
                // Fallback to table inline row if modal not present in DOM
                toggleQuickEdit(entryId);
                return;
            }

            if (titleEl) {
                titleEl.textContent = 'Kundendaten bearbeiten – Anfrage #' + entryId;
            }
            content.innerHTML = `
                <div style="padding:40px 20px; text-align:center; color:#64748b;">
                    <span class="dashicons dashicons-update spin" style="font-size:32px; width:32px; height:32px; color:#0284c7; margin-bottom:12px;"></span>
                    <br>
                    <strong style="font-size:15px; color:#1e293b;">Kundendaten werden geladen...</strong>
                    <p style="margin-top:6px; font-size:13px; color:#64748b;">Persönliche Angaben, Anschrift, Förderung & Zertifizierungen.</p>
                </div>
            `;
            backdrop.style.display = 'flex';

            const formData = new FormData();
            formData.append('action', 'crm_get_entry_edit_form');
            formData.append('nonce', nonce);
            formData.append('entry_id', entryId);
            formData.append('course_id', courseId || 0);

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.data.html) {
                        content.innerHTML = data.data.html;
                        const firstInput = content.querySelector('input:not([type=hidden]), select');
                        if (firstInput) {
                            setTimeout(() => firstInput.focus(), 50);
                        }
                    } else {
                        content.innerHTML = '<p style="color:#dc2626; padding:20px; text-align:center;">' + (data.data?.message || 'Formular konnte nicht geladen werden.') + '</p>';
                    }
                })
                .catch(err => {
                    console.error('[CRM] Customer Edit Form Error:', err);
                    content.innerHTML = '<p style="color:#dc2626; padding:20px; text-align:center;">Fehler beim Laden des Formulars.</p>';
                });
        }

        // Close Customer Edit Modal handlers
        document.addEventListener('click', function(e) {
            const closeBtn = e.target.closest('.crm-close-customer-edit-modal');
            if (closeBtn) {
                e.preventDefault();
                const backdrop = document.getElementById('crm-customer-edit-modal-backdrop');
                if (backdrop) backdrop.style.display = 'none';
                return;
            }
            const backdrop = document.getElementById('crm-customer-edit-modal-backdrop');
            if (backdrop && e.target === backdrop) {
                backdrop.style.display = 'none';
            }
        });

        // Toggle on click on "Quick Edit" button anywhere in any CRM view
        document.addEventListener('click', function (e) {
            const trigger = e.target.closest('.crm-quick-edit-btn');
            if (trigger) {
                e.preventDefault();
                e.stopPropagation();
                const entryId = trigger.dataset.entryId || (trigger.closest('[data-entry-id]') ? trigger.closest('[data-entry-id]').dataset.entryId : null);
                const courseId = trigger.dataset.courseId || (trigger.closest('[data-entry-id]') ? trigger.closest('[data-entry-id]').dataset.courseId : 0);
                if (entryId) {
                    openCustomerEditModal(entryId, courseId);
                }
                return;
            }

            const cancelBtn = e.target.closest('.crm-cancel-quick-edit');
            if (cancelBtn) {
                e.preventDefault();
                const backdrop = document.getElementById('crm-customer-edit-modal-backdrop');
                if (backdrop && backdrop.style.display !== 'none') {
                    backdrop.style.display = 'none';
                }
                const entryId = cancelBtn.dataset.entryId;
                if (entryId) {
                    const qRow = document.getElementById('crm-quick-edit-row-' + entryId);
                    const parentRow = document.querySelector('tr.crm-entry-row[data-entry-id="' + entryId + '"]');
                    if (qRow) qRow.style.display = 'none';
                    if (parentRow) parentRow.classList.remove('is-quick-editing');
                }
                return;
            }
        });

        // Submit handler for Customer Edit Form (Modal or Inline Drawer)
        document.addEventListener('submit', function (e) {
            const form = e.target.closest('.crm-inline-entry-form');
            if (!form) return;

            e.preventDefault();
            const entryId = form.dataset.entryId;
            const courseId = form.dataset.courseId || 0;
            const submitBtn = form.querySelector('.crm-save-quick-edit-btn, button[type=submit]');
            const spinner = form.querySelector('.crm-quick-edit-spinner');
            const msgEl = form.querySelector('.crm-quick-edit-msg');

            const row = document.querySelector('tr.crm-entry-row[data-entry-id="' + entryId + '"]');
            const statusDropdown = row ? row.querySelector('.crm-status-dropdown') : null;
            const statusKey = statusDropdown ? statusDropdown.value : 'neu';

            if (submitBtn) submitBtn.disabled = true;
            if (spinner) spinner.classList.add('is-active');
            if (msgEl) msgEl.style.display = 'none';

            const formData = new FormData(form);
            formData.append('action', 'crm_save_entry_form_data');
            formData.append('nonce', nonce);
            formData.append('entry_id', entryId);
            formData.append('course_id', courseId);
            formData.append('status_key', statusKey);

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (window.crmJsCache && typeof window.crmJsCache.cleanPartial === 'function') {
                            window.crmJsCache.cleanPartial('form_data_' + entryId);
                        }

                        const clientName = data.data.client_display_name;

                        // 1. Update Table View
                        if (clientName) {
                            const nameLink = document.querySelector('tr.crm-entry-row[data-entry-id="' + entryId + '"] .crm-open-case-link, tr.crm-entry-row[data-entry-id="' + entryId + '"] .crm-quick-edit-trigger, tr.crm-entry-row[data-entry-id="' + entryId + '"] .row-title');
                            if (nameLink) nameLink.textContent = clientName;
                            if (row) row.setAttribute('data-client-name', clientName);
                        }
                        if (data.data.course_title && row) {
                            row.setAttribute('data-course-title', data.data.course_title);
                        }
                        if (row) {
                            row.setAttribute('data-is-foerderung', data.data.is_foerderung ? '1' : '0');
                            const actionsCell = row.querySelector('.crm-actions');
                            if (actionsCell && data.data.actions_html) {
                                actionsCell.innerHTML = data.data.actions_html;
                            }
                            const clientFoerderWrap = row.querySelector('.crm-course-foerder-badges');
                            if (clientFoerderWrap && typeof data.data.badges_html !== 'undefined') {
                                clientFoerderWrap.outerHTML = data.data.badges_html;
                            } else if (data.data.badges_html) {
                                const titleRow = row.querySelector('.crm-client-title-row') || row.querySelector('.crm-client-cell-inner');
                                if (titleRow) titleRow.insertAdjacentHTML('beforeend', data.data.badges_html);
                            }
                        }

                        // 2. Update Cards View
                        const card = document.querySelector('.crm-customer-card[data-entry-id="' + entryId + '"]');
                        if (card) {
                            if (clientName) {
                                card.setAttribute('data-client-name', clientName);
                                const cardNameA = card.querySelector('.crm-card-client-name a');
                                if (cardNameA) cardNameA.textContent = clientName;
                                const avatar = card.querySelector('.crm-card-avatar');
                                if (avatar) {
                                    const initChar = (data.data.first_name || data.data.last_name || 'K').charAt(0).toUpperCase();
                                    avatar.textContent = initChar;
                                }
                            }
                            if (data.data.company) {
                                const compEl = card.querySelector('.crm-contact-company strong');
                                if (compEl) {
                                    compEl.textContent = data.data.company;
                                } else {
                                    const zoneContent = card.querySelector('.crm-card-zone-client .crm-zone-content');
                                    if (zoneContent) {
                                        zoneContent.insertAdjacentHTML('afterbegin', '<div class="crm-contact-line crm-contact-company"><span class="dashicons dashicons-building"></span><strong>' + crmEscapeHtml(data.data.company) + '</strong></div>');
                                    }
                                }
                            }
                            if (data.data.email) {
                                const emailA = card.querySelector('.crm-contact-email a');
                                if (emailA) {
                                    emailA.href = 'mailto:' + data.data.email;
                                    emailA.textContent = data.data.email;
                                }
                            }
                            if (data.data.phone) {
                                const phoneA = card.querySelector('.crm-contact-phone a');
                                if (phoneA) {
                                    phoneA.href = 'tel:' + data.data.phone;
                                    phoneA.textContent = data.data.phone;
                                }
                            }
                            const addrSpan = card.querySelector('.crm-contact-address span');
                            if (addrSpan) {
                                const addrParts = [];
                                if (data.data.street) addrParts.push(data.data.street);
                                if (data.data.zip || data.data.city) addrParts.push((data.data.zip ? data.data.zip + ' ' : '') + (data.data.city || ''));
                                if (addrParts.length > 0) addrSpan.textContent = addrParts.join(', ');
                            }
                            if (data.data.svr) {
                                const svrStrong = card.querySelector('.crm-contact-svr strong');
                                if (svrStrong) svrStrong.textContent = data.data.svr;
                            }
                        }

                        // 3. Update Split View
                        const splitItem = document.querySelector('#crm-view-split .crm-split-item[data-entry-id="' + entryId + '"]');
                        if (splitItem && clientName) {
                            const sName = splitItem.querySelector('.crm-split-client-name');
                            if (sName) sName.textContent = clientName;
                        }
                        const dossierInner = document.querySelector('.crm-split-dossier-inner[data-entry-id="' + entryId + '"]');
                        if (dossierInner && clientName) {
                            const dTitle = dossierInner.querySelector('.crm-split-dossier-title');
                            if (dTitle) dTitle.textContent = clientName;
                            const spickTitle = dossierInner.querySelector('.crm-spickzettel-val-main');
                            if (spickTitle) spickTitle.textContent = clientName;
                        }

                        // 4. Update Kanban View
                        const kanbanCard = document.querySelector('#crm-view-kanban .crm-kanban-card[data-entry-id="' + entryId + '"]');
                        if (kanbanCard) {
                            if (clientName) {
                                kanbanCard.setAttribute('data-client-name', clientName);
                                const kNameA = kanbanCard.querySelector('.crm-kanban-card-name a');
                                if (kNameA) kNameA.textContent = clientName;
                            }
                            if (data.data.email) {
                                const kEmail = kanbanCard.querySelector('a[href^="mailto:"]');
                                if (kEmail) kEmail.href = 'mailto:' + data.data.email;
                            }
                            if (data.data.phone) {
                                const kPhone = kanbanCard.querySelector('a[href^="tel:"]');
                                if (kPhone) kPhone.href = 'tel:' + data.data.phone;
                            }
                        }

                        // 5. Update Lead Wizard (if currently open for this entry)
                        if (typeof currentWizardData !== 'undefined' && currentWizardData && currentWizardData.entry_id == entryId) {
                            currentWizardData.client_display_name = clientName;
                            if (data.data.email) currentWizardData.email = data.data.email;
                            if (data.data.svr) currentWizardData.svr = data.data.svr;
                            if (data.data.company) currentWizardData.company = data.data.company;
                            if (data.data.foerder_sel) {
                                currentWizardData.foerderung = {
                                    ams: data.data.foerder_sel === 'ams',
                                    waff: data.data.foerder_sel === 'waff'
                                };
                            }
                            const wizClientLbl = document.getElementById('crm-wizard-client-label');
                            if (wizClientLbl) wizClientLbl.textContent = clientName;

                            // Re-render active wizard step to show updated values
                            if (typeof currentWizardStep !== 'undefined' && typeof renderWizardStep === 'function') {
                                renderWizardStep(currentWizardStep);
                            }
                            if (typeof crmRefreshWizardEmailPreview === 'function') {
                                crmRefreshWizardEmailPreview();
                            }
                        }

                        // 6. Update Screen 2 Spickzettel (if visible)
                        const screen2SpickTitle = document.querySelector('#crm-entry-details-container .crm-spickzettel-val-main');
                        if (screen2SpickTitle && clientName) {
                            screen2SpickTitle.textContent = clientName;
                        }

                        if (msgEl) {
                            msgEl.textContent = '✓ Gespeichert';
                            msgEl.style.color = '#16a34a';
                            msgEl.style.display = 'inline';
                        }

                        displayNotice(data.data.message, 'success');

                        // Smoothly close modal if open
                        const modalBackdrop = document.getElementById('crm-customer-edit-modal-backdrop');
                        if (modalBackdrop && modalBackdrop.style.display !== 'none') {
                            setTimeout(() => {
                                modalBackdrop.style.display = 'none';
                                const modalContent = document.getElementById('crm-customer-edit-modal-content');
                                if (modalContent) modalContent.innerHTML = '';
                            }, 450);
                        }

                        // Smoothly close table quick edit row if open
                        setTimeout(() => {
                            const qRow = document.getElementById('crm-quick-edit-row-' + entryId);
                            if (qRow) qRow.style.display = 'none';
                            if (row) row.classList.remove('is-quick-editing');
                            if (msgEl) msgEl.style.display = 'none';
                        }, 400);
                    } else {
                        if (msgEl) {
                            msgEl.textContent = '✗ ' + (data.data?.message || 'Fehler beim Speichern');
                            msgEl.style.color = '#dc2626';
                            msgEl.style.display = 'inline';
                        }
                        displayNotice((data.data && data.data.message) ? data.data.message : 'Fehler beim Speichern der Formulardaten.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Save Entry Form Data Error:', err);
                    if (msgEl) {
                        msgEl.textContent = '✗ Fehler';
                        msgEl.style.color = '#dc2626';
                        msgEl.style.display = 'inline';
                    }
                    displayNotice('Formulardaten konnten nicht gespeichert werden.', 'error');
                })
                .finally(() => {
                    if (submitBtn) submitBtn.disabled = false;
                    if (spinner) spinner.classList.remove('is-active');
                });
        });

        // --- CRM Status History Timeline Modal & Split View Handler ---
        document.addEventListener('click', function (e) {
            // Split View inline business case content toggle
            const toggleBcaseBtn = e.target.closest('.crm-bcase-toggle-content');
            if (toggleBcaseBtn) {
                e.preventDefault();
                const targetId = toggleBcaseBtn.dataset.target;
                if (targetId) {
                    const drawer = document.getElementById(targetId);
                    if (drawer) {
                        const isHidden = (drawer.style.display === 'none' || getComputedStyle(drawer).display === 'none');
                        drawer.style.display = isHidden ? 'block' : 'none';
                        const labelEl = toggleBcaseBtn.querySelector('.crm-bcase-toggle-label');
                        if (labelEl) {
                            labelEl.textContent = isHidden ? 'E-Mail-Inhalt verbergen' : 'E-Mail-Inhalt lesen';
                        }
                    }
                }
                return;
            }

            const histBtn = e.target.closest('.crm-history-btn');
            if (!histBtn) return;

            // In Split View: If user clicks "Verlauf" in topbar or spickzettel, scroll directly to the embedded section!
            const splitDetail = histBtn.closest('.crm-split-detail');
            if (splitDetail && !histBtn.closest('.crm-split-history-header')) {
                const targetHistory = splitDetail.querySelector('.crm-split-history-section');
                if (targetHistory) {
                    e.preventDefault();
                    targetHistory.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    targetHistory.classList.remove('crm-highlight');
                    void targetHistory.offsetWidth;
                    targetHistory.classList.add('crm-highlight');
                    setTimeout(() => targetHistory.classList.remove('crm-highlight'), 1800);
                    return;
                }
            }

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

        // --- Row-Action "Aktionen" Click Handler ---
        document.addEventListener('click', function (e) {
            const actBtn = e.target.closest('.crm-row-actions-btn');
            if (!actBtn) return;
            e.preventDefault();

            const row = actBtn.closest('tr.crm-entry-row');
            if (!row) return;

            const actionsCell = row.querySelector('.crm-actions') || row.querySelector('.crm-actions-wrap');
            if (actionsCell) {
                actionsCell.classList.remove('crm-actions-highlight');
                // Trigger reflow to restart CSS animation
                void actionsCell.offsetWidth;
                actionsCell.classList.add('crm-actions-highlight');
                setTimeout(() => actionsCell.classList.remove('crm-actions-highlight'), 1300);

                const firstBtn = actionsCell.querySelector('button.crm-action-btn, button.crm-run-friedelin-btn, button');
                if (firstBtn) {
                    firstBtn.focus();
                }
            }
        });
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

        // ==========================================================================
        // CRM VORBEREITUNGS-WIZARD MODAL LOGIC
        // ==========================================================================
        // ==========================================================================
        // CRM WORKFLOW- & VORBEREITUNGS-WIZARD MODAL LOGIC
        // ==========================================================================
        const wizardBackdrop = document.getElementById('crm-wizard-modal-backdrop');
        const wizardBody = document.getElementById('crm-wizard-body');
        const wizardClientLabel = document.getElementById('crm-wizard-client-label');
        const wizardCourseLabel = document.getElementById('crm-wizard-course-label');
        const wizardStatusBadge = document.getElementById('crm-wizard-status-badge');
        const wizardHistoryToggle = document.getElementById('crm-wizard-history-toggle');
        const wizardHistoryCount = document.getElementById('crm-wizard-history-count');
        const wizardHistoryDrawer = document.getElementById('crm-wizard-history-drawer');
        const wizardHistoryContent = document.getElementById('crm-wizard-history-content');
        const wizardStageBar = document.getElementById('crm-wizard-stage-bar');
        const wizardPrevBtn = document.querySelector('.crm-wizard-prev-btn');
        const wizardNextBtn = document.querySelector('.crm-wizard-next-btn');
        const wizardOpenEditorBtn = document.querySelector('.crm-wizard-open-editor-btn');

        let currentWizardEntryId = null;
        let currentWizardCourseId = null;
        let currentWizardRow = null;
        let currentWizardBtn = null;
        let currentWizardData = null;
        let currentWizardStep = 1;

        function updateStepTabTitles(stage) {
            let titles = ['Lead & Förderung', 'Dokumente & PDFs', 'E-Mail & Freigabe'];
            if (stage === 'followup') {
                titles = ['Status & Verlauf', 'Nachfass-Dokumente', 'Nachfass-Mail & Freigabe'];
            } else if (stage === 'enrolled') {
                titles = ['Teilnehmer & Buchung', 'Teilnahmebestätigung (TB)', 'TB-Versand & Freigabe'];
            } else if (stage === 'diploma') {
                titles = ['Prüfung & Abschluss', 'Diplom & Zertifikat', 'Diplom-Versand & Freigabe'];
            } else if (stage === 'done') {
                titles = ['Abschluss-Übersicht', 'Dokumente-Archiv', 'Historie & Notizen'];
            } else if (stage === 'storno') {
                titles = ['Storno-Details', 'Archivierte Daten', 'Reaktivierung'];
            }
            const stepTitleEls = document.querySelectorAll('.crm-wizard-step-title');
            if (stepTitleEls.length >= 3) {
                stepTitleEls[0].textContent = titles[0];
                stepTitleEls[1].textContent = titles[1];
                stepTitleEls[2].textContent = titles[2];
            }
        }

        function crmWizardFindDocUrl(type, data) {
            if (!data) return null;
            const urls = (data.draft && data.draft.pdf_urls && Array.isArray(data.draft.pdf_urls)) ? data.draft.pdf_urls : [];
            for (let i = 0; i < urls.length; i++) {
                const u = urls[i];
                const lower = u.toLowerCase();
                if (type === 'offer_1') {
                    if (lower.includes('basis') || lower.includes('angebot_1') || (lower.includes('angebot') && !lower.includes('angebot_2') && !lower.includes('angebot_3') && !lower.includes('zert') && !lower.includes('ipma')) || (!lower.includes('angebot_2') && !lower.includes('angebot_3') && !lower.includes('zert') && !lower.includes('ipma') && !lower.includes('kurszeiten') && !lower.includes('agb') && !lower.includes('diplom') && !lower.includes('teilnahme') && (lower.includes('a_') || lower.includes('.pdf')))) {
                        return u;
                    }
                } else if (type === 'offer_2') {
                    if (lower.includes('angebot_2') || (!lower.includes('angebot_3') && !lower.includes('ipma') && (lower.includes('zertifikat') || lower.includes('zertifizierung')))) {
                        return u;
                    }
                } else if (type === 'offer_3') {
                    if (lower.includes('angebot_3') || lower.includes('ipma')) {
                        return u;
                    }
                } else if (type === 'kb') {
                    if (lower.includes('kurszeiten') || lower.includes('kb_')) {
                        return u;
                    }
                } else if (type === 'agb') {
                    if (lower.includes('agb')) {
                        return u;
                    }
                }
            }
            if (type === 'agb') {
                return data.agb_url || 'https://x-sieben.at/wp-content/uploads/2025/09/AGB_X_SIEBEN_2025.pdf';
            }
            return null;
        }

        function crmWizardGetActivePdfUrls(data) {
            if (!data) return [];
            const sel = data.selected_docs || {};
            const result = [];
            // AGB wird niemals als Dateianhang mitgeschickt, sondern immer als Online-Link in der E-Mail verlinkt
            const docKeys = ['offer_1', 'offer_2', 'kb'];
            if (data && (data.has_offer_3 || (data.selected_docs && data.selected_docs.offer_3))) {
                docKeys.splice(2, 0, 'offer_3');
            }
            docKeys.forEach(k => {
                if (sel[k]) {
                    const url = crmWizardFindDocUrl(k, data);
                    if (url && result.indexOf(url) === -1) {
                        result.push(url);
                    }
                }
            });
            return result;
        }

        function crmRefreshWizardEmailPreview() {
            if (!currentWizardEntryId || !currentWizardCourseId) return;
            const formData = new FormData();
            formData.append('action', 'crm_get_wizard_email_preview');
            formData.append('nonce', nonce);
            formData.append('entry_id', currentWizardEntryId);
            formData.append('course_id', currentWizardCourseId);
            formData.append('selected_docs', JSON.stringify(currentWizardData.selected_docs || {}));

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(res => {
                    if (res.success && res.data) {
                        if (!currentWizardData.draft) currentWizardData.draft = {};
                        currentWizardData.draft.subject = res.data.subject;
                        currentWizardData.draft.body = res.data.body;

                        const subjInput = document.getElementById('crm-wizard-mail-subject');
                        if (subjInput && !subjInput.dataset.userEdited) {
                            subjInput.value = res.data.subject;
                        }
                        const bodyPreview = document.getElementById('crm-wizard-mail-body-preview');
                        if (bodyPreview) {
                            bodyPreview.innerHTML = res.data.body;
                        }
                        const bodyInput = document.getElementById('crm-wizard-mail-body');
                        if (bodyInput) {
                            bodyInput.value = res.data.body;
                        }
                    }
                })
                .catch(err => console.error('Error refreshing wizard email preview:', err));
        }

        function openWizardModal(entryId, courseId, row, triggerBtn) {
            currentWizardEntryId = entryId;
            currentWizardCourseId = courseId;
            currentWizardRow = row || (entryId ? document.querySelector(`tr.crm-entry-row[data-entry-id="${entryId}"], .crm-customer-card[data-entry-id="${entryId}"]`) : null);
            currentWizardBtn = triggerBtn;
            currentWizardStep = 1;

            const backdrop = document.getElementById('crm-wizard-modal-backdrop');
            if (!backdrop) {
                console.error('[CRM Wizard] #crm-wizard-modal-backdrop nicht im DOM gefunden');
                return;
            }
            backdrop.style.display = 'flex';

            const historyDrawer = document.getElementById('crm-wizard-history-drawer');
            if (historyDrawer) historyDrawer.style.display = 'none';

            const body = document.getElementById('crm-wizard-body');
            if (body) {
                body.innerHTML = `
                    <div style="text-align:center; padding:50px 20px; color:#64748b;">
                        <span class="dashicons dashicons-update spin" style="font-size:36px; width:36px; height:36px; color:#6d28d9; margin-bottom:12px;"></span>
                        <br>
                        <strong style="font-size:15px; color:#1e293b;">Wizard lädt Anfrage- und Verlaufsdaten...</strong>
                        <p style="margin-top:6px; font-size:13px; color:#64748b;">Lead, Verlaufshistorie, Förderungsstatus und Dokumente werden synchronisiert.</p>
                    </div>
                `;
            }

            const formData = new FormData();
            formData.append('action', 'crm_get_wizard_data');
            formData.append('nonce', nonce);
            formData.append('entry_id', entryId);

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(res => {
                    if (res.success && res.data) {
                        currentWizardData = res.data;

                        // Initialize selected_docs for this wizard session
                        const draft = currentWizardData.draft;
                        const isAms = Boolean(currentWizardData.foerderung && (currentWizardData.foerderung.ams || currentWizardData.foerderung.waff));
                        const hasCert = Boolean(currentWizardData.has_cert_option);
                        const hasOffer3 = Boolean(currentWizardData.has_offer_3);
                        
                        let initialSel = {
                            offer_1: true,
                            offer_2: hasCert,
                            offer_3: hasOffer3,
                            kb: isAms,
                            agb: true
                        };

                        if (draft && draft.selected_docs && typeof draft.selected_docs === 'object') {
                            initialSel = {
                                offer_1: draft.selected_docs.offer_1 !== false,
                                offer_2: Boolean(draft.selected_docs.offer_2),
                                offer_3: typeof draft.selected_docs.offer_3 !== 'undefined' ? Boolean(draft.selected_docs.offer_3) : hasOffer3,
                                kb: Boolean(draft.selected_docs.kb),
                                agb: draft.selected_docs.agb !== false
                            };
                        } else if (draft && draft.pdf_urls && Array.isArray(draft.pdf_urls) && draft.pdf_urls.length > 0) {
                            const urls = draft.pdf_urls;
                            const hasA1 = urls.some(u => {
                                const l = u.toLowerCase();
                                return l.includes('basis') || l.includes('angebot_1') || (l.includes('angebot') && !l.includes('angebot_2') && !l.includes('angebot_3') && !l.includes('zert') && !l.includes('ipma'));
                            });
                            const hasA2 = urls.some(u => {
                                const l = u.toLowerCase();
                                return l.includes('angebot_2') || (!l.includes('angebot_3') && !l.includes('ipma') && (l.includes('zertifikat') || l.includes('zertifizierung')));
                            });
                            const hasA3 = urls.some(u => {
                                const l = u.toLowerCase();
                                return l.includes('angebot_3') || l.includes('ipma');
                            });
                            const hasKb = urls.some(u => {
                                const l = u.toLowerCase();
                                return l.includes('kurszeiten') || l.includes('kb_');
                            });
                            const hasAgb = urls.some(u => u.toLowerCase().includes('agb'));

                            initialSel = {
                                offer_1: hasA1 || (!hasA1 && !hasA2 && !hasA3),
                                offer_2: hasA2,
                                offer_3: hasA3 || hasOffer3,
                                kb: hasKb || isAms,
                                agb: hasAgb || true
                            };
                        }

                        currentWizardData.selected_docs = initialSel;

                        const clientLabel = document.getElementById('crm-wizard-client-label');
                        const courseLabel = document.getElementById('crm-wizard-course-label');
                        const statusBadge = document.getElementById('crm-wizard-status-badge');
                        const historyCount = document.getElementById('crm-wizard-history-count');
                        const historyContent = document.getElementById('crm-wizard-history-content');
                        const stageBar = document.getElementById('crm-wizard-stage-bar');
                        const editClientBtn = document.getElementById('crm-wizard-edit-client-btn');

                        if (clientLabel) clientLabel.textContent = currentWizardData.client_display_name || 'Kunde';
                        if (courseLabel) courseLabel.textContent = currentWizardData.course_title || 'Kurs';
                        if (editClientBtn) {
                            editClientBtn.dataset.entryId = entryId;
                            editClientBtn.dataset.courseId = courseId || (currentWizardData.course_id || 0);
                        }

                        if (statusBadge) {
                            statusBadge.innerHTML = `<span class="crm-status-pill crm-status-${currentWizardData.status_key}" style="font-size:11px; padding:2px 8px; font-weight:700;">${currentWizardData.status_label}</span>`;
                        }

                        if (historyCount) {
                            historyCount.textContent = currentWizardData.history_count || 0;
                        }

                        if (wizardHistoryContent) {
                            if (!currentWizardData.history || currentWizardData.history.length === 0) {
                                wizardHistoryContent.innerHTML = '<p style="color:#64748b; font-style:italic; margin:0;">Noch keine Aktionen aufgezeichnet.</p>';
                            } else {
                                let histHtml = '<div style="display:flex; flex-direction:column; gap:6px;">';
                                currentWizardData.history.forEach(item => {
                                    histHtml += `
                                        <div style="display:flex; align-items:flex-start; gap:8px; padding:5px 0; border-bottom:1px solid #f1f5f9;">
                                            <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#6d28d9; margin-top:5px; flex-shrink:0;"></span>
                                            <div style="flex:1;">
                                                <div style="font-weight:600; color:#1e293b;">
                                                    <span>📅 ${item.status_date}</span>
                                                    <span style="font-weight:normal; color:#64748b;">${item.user_name ? ' von ' + item.user_name : ''}</span>
                                                    &bull; <span style="font-weight:600; color:#4338ca;">${item.status_label}</span>
                                                </div>
                                                ${item.note ? `<div style="color:#475569; font-size:11.5px; margin-top:1px;">${item.note}</div>` : ''}
                                            </div>
                                        </div>
                                    `;
                                });
                                histHtml += '</div>';
                                wizardHistoryContent.innerHTML = histHtml;
                            }
                        }

                        if (wizardStageBar) {
                            wizardStageBar.querySelectorAll('.crm-stage-node').forEach(node => {
                                const nStage = node.dataset.stage;
                                if (nStage === currentWizardData.stage) {
                                    node.style.background = '#6d28d9';
                                    node.style.color = '#ffffff';
                                    node.style.borderColor = '#5b21b6';
                                    node.style.fontWeight = '700';
                                    node.style.boxShadow = '0 1px 4px rgba(109,40,217,0.3)';
                                } else {
                                    node.style.background = '#ffffff';
                                    node.style.color = '#64748b';
                                    node.style.borderColor = '#e2e8f0';
                                    node.style.fontWeight = '600';
                                    node.style.boxShadow = 'none';
                                }
                            });
                        }

                        updateStepTabTitles(currentWizardData.stage);

                        // Check if trigger button explicitly requested a target step (e.g. step 3 for "Versand freigeben")
                        const requestedStep = (currentWizardBtn && currentWizardBtn.dataset.step) ? parseInt(currentWizardBtn.dataset.step, 10) : null;

                        if (requestedStep && requestedStep >= 1 && requestedStep <= 3) {
                            currentWizardStep = requestedStep;
                        } else if (currentWizardData.stage === 'offer') {
                            const isReady = ['versand_vorbereitet', 'versandbereit', 'ai_prepared'].indexOf(currentWizardData.status_key) !== -1;
                            currentWizardStep = isReady ? 3 : (currentWizardData.is_prepared ? 2 : 1);
                        } else {
                            currentWizardStep = 1;
                        }
                        renderWizardStep(currentWizardStep);
                    } else {
                        if (wizardBody) {
                            wizardBody.innerHTML = `<p style="color:#d63638; padding:20px;">Fehler: ${(res.data && res.data.message) ? res.data.message : 'Daten konnten nicht geladen werden.'}</p>`;
                        }
                    }
                })
                .catch(err => {
                    console.error('Wizard load error:', err);
                    if (wizardBody) {
                        wizardBody.innerHTML = `<p style="color:#d63638; padding:20px;">Netzwerkfehler beim Laden des Wizards.</p>`;
                    }
                });
        }

        function renderWizardStep(step) {
            currentWizardStep = step;
            if (!currentWizardData || !wizardBody) return;

            const stage = currentWizardData.stage || 'offer';

            // Update step tabs UI
            document.querySelectorAll('.crm-wizard-step-tab').forEach(tab => {
                const tabStep = parseInt(tab.dataset.step, 10);
                const numSpan = tab.querySelector('.crm-wizard-step-num');
                if (tabStep === step) {
                    tab.style.borderBottomColor = '#6d28d9';
                    tab.style.color = '#6d28d9';
                    tab.classList.add('is-active');
                    if (numSpan) { numSpan.style.background = '#6d28d9'; numSpan.style.color = '#fff'; }
                } else if (tabStep < step) {
                    tab.style.borderBottomColor = '#10b981';
                    tab.style.color = '#047857';
                    tab.classList.remove('is-active');
                    if (numSpan) { numSpan.style.background = '#10b981'; numSpan.style.color = '#fff'; }
                } else {
                    tab.style.borderBottomColor = 'transparent';
                    tab.style.color = '#64748b';
                    tab.classList.remove('is-active');
                    if (numSpan) { numSpan.style.background = '#e2e8f0'; numSpan.style.color = '#64748b'; }
                }
            });

            // Update footer buttons
            if (wizardPrevBtn) wizardPrevBtn.style.display = (step > 1) ? 'inline-block' : 'none';
            if (wizardNextBtn) {
                if (step === 1) {
                    wizardNextBtn.textContent = 'Weiter zu Schritt 2: Dokumente →';
                    wizardNextBtn.style.display = 'inline-block';
                } else if (step === 2) {
                    wizardNextBtn.textContent = 'Weiter zu Schritt 3: E-Mail & Freigabe →';
                    wizardNextBtn.style.display = 'inline-block';
                } else if (step === 3) {
                    wizardNextBtn.style.display = 'none';
                }
            }

            // ==========================================
            // CASE 1: STAGE 'FOLLOWUP' (Angebot gesendet)
            // ==========================================
            if (stage === 'followup') {
                if (step === 1) {
                    const draft = currentWizardData.draft;
                    wizardBody.innerHTML = `
                        <div style="display:flex; flex-direction:column; gap:16px;">
                            <div style="background:#eff6ff; border:1px solid #bfdbfe; border-left:4px solid #1d4ed8; border-radius:6px; padding:14px 16px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                    <div>
                                        <strong style="color:#1e3a8a; font-size:14px;">Aktueller Verlauf: Angebot wurde versendet</strong>
                                        <div style="margin-top:3px; font-size:12.5px; color:#3b82f6;">
                                            Empfänger: <strong>${currentWizardData.email}</strong> &bull; Status: <strong>${currentWizardData.status_label}</strong>
                                        </div>
                                    </div>
                                    <span style="font-size:11.5px; background:#dbeafe; color:#1e40af; padding:3px 8px; border-radius:12px; font-weight:600;">
                                        Aktionen im Verlauf
                                    </span>
                                </div>
                            </div>

                            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:16px;">
                                <strong style="color:#166534; font-size:13.5px; display:block; margin-bottom:6px;">
                                    🎯 Nächste Status-Aktion nach Rückmeldung des Kunden:
                                </strong>
                                <p style="margin:0 0 12px 0; font-size:12px; color:#15803d;">
                                    Wählen Sie direkt per Klick, wie es mit dieser Anfrage im Verlauf weitergeht:
                                </p>
                                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                    <button type="button" class="button button-primary crm-wizard-quick-status-btn" data-status="angemeldet" style="background:#16a34a; border-color:#15803d; font-weight:700; height:34px; padding:0 14px;">
                                        🎉 Kunde hat gebucht → Als 'Angemeldet' übernehmen
                                    </button>
                                    <button type="button" class="button crm-wizard-quick-status-btn" data-status="nachfassen" style="background:#fff7ed; color:#c2410c; border-color:#fdba74; font-weight:600; height:34px;">
                                        ⏳ Bedenkzeit erbeten → Als 'Nachfassen' vormerken
                                    </button>
                                    <button type="button" class="button crm-wizard-quick-status-btn" data-status="storniert" style="color:#dc2626; border-color:#fca5a5; height:34px;">
                                        ✕ Absage / Storno
                                    </button>
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:14px;">
                                <div class="crm-wizard-card">
                                    <div class="crm-wizard-card-header">
                                        <span>Kundendaten</span>
                                        <span style="font-size:11px; color:#64748b; font-weight:normal;">#${currentWizardData.entry_id}</span>
                                    </div>
                                    <div style="font-size:12.5px; line-height:1.6; color:#334155;">
                                        <div><strong>Name:</strong> ${currentWizardData.client_display_name}</div>
                                        <div><strong>E-Mail:</strong> <a href="mailto:${currentWizardData.email}">${currentWizardData.email}</a></div>
                                        <div><strong>SV-Nummer:</strong> ${currentWizardData.svr || '–'}</div>
                                        <div><strong>Firma:</strong> ${currentWizardData.company || 'Privatkunde'}</div>
                                    </div>
                                </div>
                                <div class="crm-wizard-card">
                                    <div class="crm-wizard-card-header">
                                        <span>Kursdetails</span>
                                    </div>
                                    <div style="font-size:12.5px; line-height:1.6; color:#334155;">
                                        <div><strong>Kurs:</strong> ${currentWizardData.course_title}</div>
                                        <div><strong>Zeitraum:</strong> <span style="background:#f1f5f9; padding:2px 6px; border-radius:4px; font-weight:600;">${currentWizardData.dates_text}</span></div>
                                        <div><strong>Förderung:</strong> ${currentWizardData.foerderung && currentWizardData.foerderung.ams ? '<span style="color:#005db4; font-weight:700;">AMS</span>' : (currentWizardData.foerderung && currentWizardData.foerderung.waff ? '<span style="color:#e30613; font-weight:700;">WAFF</span>' : 'Standard')}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                } else if (step === 2) {
                    const draft = currentWizardData.draft;
                    let pdfListHtml = '';
                    if (draft && draft.pdf_urls && draft.pdf_urls.length > 0) {
                        pdfListHtml = draft.pdf_urls.map(url => {
                            const fname = url.split('/').pop();
                            return `<a href="${url}" target="_blank" class="button" style="display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:600;"><span class="dashicons dashicons-pdf" style="color:#dc2626;"></span> ${fname}</a>`;
                        }).join(' ');
                    } else {
                        pdfListHtml = '<p style="color:#64748b; font-size:12.5px; margin:0;">PDFs wurden versendet. Bei Bedarf können Unterlagen neu generiert werden.</p>';
                    }

                    wizardBody.innerHTML = `
                        <div style="display:flex; flex-direction:column; gap:16px;">
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid #1d4ed8; border-radius:6px; padding:12px 16px;">
                                <strong style="color:#0f172a; font-size:13.5px;">Schritt 2: Versendete Dokumente & Kurszeitenbestätigung</strong>
                                <p style="margin:4px 0 0 0; font-size:12.5px; color:#64748b;">
                                    Unterlagen einsehen oder bei Bedarf zusätzliche Bestätigungen für AMS/WAFF beilegen.
                                </p>
                            </div>
                            <div class="crm-wizard-card">
                                <div class="crm-wizard-card-header">
                                    <span>Bereitgestellte Unterlagen</span>
                                </div>
                                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:4px;">
                                    ${pdfListHtml}
                                </div>
                            </div>
                            <div style="background:#faf5ff; border:1px solid #e9d5ff; border-radius:8px; padding:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                                <div>
                                    <strong style="color:#581c87; font-size:13px;">Kurszeitenbestätigung (KB) nachreichen?</strong>
                                    <p style="margin:2px 0 0 0; font-size:12px; color:#6b21a8;">Falls die Förderstelle ein gesondertes Zeitbestätigungs-Formular verlangt.</p>
                                </div>
                                <button type="button" class="button crm-wizard-open-editor-btn" style="color:#6d28d9; border-color:#c4b5fd;">
                                    KB im Editor öffnen
                                </button>
                            </div>
                        </div>
                    `;
                } else if (step === 3) {
                    const recipient = currentWizardData.email || '';
                    const loggedInUserEmail = (typeof crmData !== 'undefined' && crmData.currentUserEmail) ? crmData.currentUserEmail : '';
                    const testEmail = currentWizardData.default_test_email || loggedInUserEmail || 'gajo@x-sieben.at';
                    const followupSubject = `Rückfrage zu Ihrem Kursangebot: ${currentWizardData.course_title}`;
                    const followupBody = `Sehr geehrte(r) ${currentWizardData.client_display_name},\n\nich hoffe, es geht Ihnen gut!\n\nIch beziehe mich auf unser Angebot für den Kurs „${currentWizardData.course_title}“. Konnten Sie die Kursinhalte und Termine bereits sichten oder sind noch Fragen offen?\n\nGerne unterstütze ich Sie auch bei Formalitäten mit Förderstellen (AMS / WAFF).\n\nIch freue mich auf Ihre Rückmeldung!\n\nMit besten Grüßen,\nX-SIEBEN Team`;

                    let quickUserBtnHtml = '';
                    if (loggedInUserEmail && loggedInUserEmail !== testEmail) {
                        quickUserBtnHtml = `<button type="button" class="button button-small crm-wizard-set-test-email-btn" data-email="${crmEscapeHtml(loggedInUserEmail)}" style="font-size:11.5px; height:30px; line-height:28px; background:#ffffff; color:#0284c7; border-color:#7dd3fc;" title="Meine E-Mail (${loggedInUserEmail}) als Test-Empfänger einsetzen">👤 An mich</button>`;
                    }

                    wizardBody.innerHTML = `
                        <div style="display:flex; flex-direction:column; gap:14px;">
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid #1d4ed8; border-radius:6px; padding:12px 16px;">
                                <strong style="color:#0f172a; font-size:13.5px;">Schritt 3: Nachfass-E-Mail & Kontaktaufnahme</strong>
                                <p style="margin:4px 0 0 0; font-size:12.5px; color:#64748b;">
                                    Freundliches Nachfassen beim Kunden per E-Mail oder Test-Versand.
                                </p>
                            </div>
                            <div class="crm-wizard-card">
                                <div style="display:grid; grid-template-columns: 110px 1fr; gap:10px; align-items:center; font-size:13px; margin-bottom:8px;">
                                    <strong style="color:#475569;">Empfänger:</strong>
                                    <input type="email" id="crm-wizard-mail-recipient" value="${recipient}" style="width:100%; height:32px; font-size:13px;">
                                </div>
                                <div style="display:grid; grid-template-columns: 110px 1fr; gap:10px; align-items:center; font-size:13px; margin-bottom:8px;">
                                    <strong style="color:#475569;">Betreff:</strong>
                                    <input type="text" id="crm-wizard-mail-subject" value="${crmEscapeHtml(followupSubject)}" style="width:100%; height:32px; font-size:13px; font-weight:600;">
                                </div>
                                <div style="display:grid; grid-template-columns: 110px 1fr auto; gap:10px; align-items:center; font-size:13px; background:#f0f9ff; padding:8px 12px; border-radius:6px; border:1px solid #bae6fd;">
                                    <strong style="color:#0369a1; display:flex; align-items:center; gap:5px;">
                                        <span class="dashicons dashicons-email-alt" style="font-size:16px; width:16px; height:16px;"></span> Test-Empfänger:
                                    </strong>
                                    <input type="email" id="crm-wizard-test-recipient" value="${crmEscapeHtml(testEmail)}" placeholder="ihre-adresse@domain.at" style="width:100%; height:32px; font-size:13px; border-color:#7dd3fc;">
                                    ${quickUserBtnHtml}
                                </div>
                            </div>
                            <div class="crm-wizard-card" style="padding:14px;">
                                <div style="font-weight:600; font-size:12px; color:#64748b; margin-bottom:8px;">Nachfass-Nachricht:</div>
                                <textarea id="crm-wizard-mail-body" rows="6" style="width:100%; font-size:12.5px; line-height:1.55; color:#1e293b; padding:10px; border-radius:6px; border:1px solid #e2e8f0;">${crmEscapeHtml(followupBody)}</textarea>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; padding:14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px;">
                                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                    <button type="button" class="button crm-wizard-send-test-btn" style="background:#0284c7; color:#fff; border-color:#0284c7; font-weight:600; height:34px;">
                                        🧪 Test-E-Mail senden
                                    </button>
                                    <button type="button" class="button button-primary crm-wizard-open-editor-btn" style="font-weight:600; height:34px;">
                                        ✏️ Im Vollbild-Editor bearbeiten
                                    </button>
                                </div>
                                <div id="crm-wizard-send-status" style="font-size:12px; color:#047857; font-weight:600;"></div>
                            </div>
                        </div>
                    `;
                }
                return;
            }

            // ==========================================
            // CASE 2: STAGE 'ENROLLED' (Angemeldet / TB)
            // ==========================================
            if (stage === 'enrolled') {
                if (step === 1) {
                    wizardBody.innerHTML = `
                        <div style="display:flex; flex-direction:column; gap:16px;">
                            <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-left:4px solid #059669; border-radius:6px; padding:14px 16px;">
                                <strong style="color:#065f46; font-size:14px;">Aktueller Verlauf: Kunde ist angemeldet & Kurs läuft</strong>
                                <div style="margin-top:3px; font-size:12.5px; color:#047857;">
                                    Nächster logischer Meilenstein: <strong>Teilnahmebestätigung (TB)</strong> nach Kursabsolvierung.
                                </div>
                            </div>
                            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:14px;">
                                <div class="crm-wizard-card">
                                    <div class="crm-wizard-card-header">
                                        <span>Teilnehmerdaten</span>
                                    </div>
                                    <div style="font-size:12.5px; line-height:1.6; color:#334155;">
                                        <div><strong>Name:</strong> ${currentWizardData.client_display_name}</div>
                                        <div><strong>E-Mail:</strong> <a href="mailto:${currentWizardData.email}">${currentWizardData.email}</a></div>
                                        <div><strong>SV-Nummer:</strong> ${currentWizardData.svr || '–'}</div>
                                    </div>
                                </div>
                                <div class="crm-wizard-card">
                                    <div class="crm-wizard-card-header">
                                        <span>Kurs & Termine</span>
                                    </div>
                                    <div style="font-size:12.5px; line-height:1.6; color:#334155;">
                                        <div><strong>Kurs:</strong> ${currentWizardData.course_title}</div>
                                        <div><strong>Zeitraum:</strong> <span style="background:#f1f5f9; padding:2px 6px; border-radius:4px; font-weight:600;">${currentWizardData.dates_text}</span></div>
                                    </div>
                                </div>
                            </div>
                            <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:16px;">
                                <strong style="color:#1e293b; font-size:13px;">Status-Aktion:</strong>
                                <div style="margin-top:8px; display:flex; gap:10px; flex-wrap:wrap;">
                                    <button type="button" class="button button-primary crm-wizard-quick-status-btn" data-status="teilnahmebestaetigung_gesendet" style="background:#059669; border-color:#047857; font-weight:700; height:34px;">
                                        ✓ Kurs erfolgreich abgeschlossen → TB erstellen & versenden
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                } else if (step === 2) {
                    wizardBody.innerHTML = `
                        <div style="display:flex; flex-direction:column; gap:16px;">
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid #059669; border-radius:6px; padding:12px 16px;">
                                <strong style="color:#0f172a; font-size:13.5px;">Schritt 2: Teilnahmebestätigung (TB PDF)</strong>
                                <p style="margin:4px 0 0 0; font-size:12.5px; color:#64748b;">
                                    Behördliches Dokument zur Vorlage bei Förderstellen oder Arbeitgebern.
                                </p>
                            </div>
                            <div class="crm-wizard-doc-item">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <span class="dashicons dashicons-yes-alt" style="color:#059669; font-size:22px;"></span>
                                    <div>
                                        <strong style="font-size:13px; color:#1e293b;">Teilnahmebestätigung (TB PDF)</strong>
                                        <div style="font-size:11.5px; color:#64748b;">Bestätigung über die ordnungsgemäße Absolvierung der Unterrichtseinheiten</div>
                                    </div>
                                </div>
                                <span class="crm-wizard-doc-badge" style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0;">Erforderlich</span>
                            </div>
                            <div style="background:#faf5ff; border:1px solid #e9d5ff; border-radius:8px; padding:20px; text-align:center;">
                                <button type="button" class="button button-primary crm-wizard-open-editor-btn" style="background:#059669; border-color:#047857; font-size:13px; font-weight:700; padding:4px 18px; height:36px;">
                                    ⚡ TB im Vollbild-Editor generieren & prüfen
                                </button>
                            </div>
                        </div>
                    `;
                } else if (step === 3) {
                    const recipient = currentWizardData.email || '';
                    const loggedInUserEmail = (typeof crmData !== 'undefined' && crmData.currentUserEmail) ? crmData.currentUserEmail : '';
                    const testEmail = currentWizardData.default_test_email || loggedInUserEmail || 'gajo@x-sieben.at';
                    const tbSubject = `Ihre Teilnahmebestätigung für ${currentWizardData.course_title}`;
                    let quickUserBtnHtml = '';
                    if (loggedInUserEmail && loggedInUserEmail !== testEmail) {
                        quickUserBtnHtml = `<button type="button" class="button button-small crm-wizard-set-test-email-btn" data-email="${crmEscapeHtml(loggedInUserEmail)}" style="font-size:11.5px; height:30px; line-height:28px; background:#ffffff; color:#0284c7; border-color:#7dd3fc;" title="Meine E-Mail (${loggedInUserEmail}) als Test-Empfänger einsetzen">👤 An mich</button>`;
                    }
                    wizardBody.innerHTML = `
                        <div style="display:flex; flex-direction:column; gap:14px;">
                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid #059669; border-radius:6px; padding:12px 16px;">
                                <strong style="color:#0f172a; font-size:13.5px;">Schritt 3: TB-Übermittlung & Abschluss</strong>
                            </div>
                            <div class="crm-wizard-card">
                                <div style="display:grid; grid-template-columns: 110px 1fr; gap:10px; align-items:center; font-size:13px; margin-bottom:8px;">
                                    <strong style="color:#475569;">Empfänger:</strong>
                                    <input type="email" id="crm-wizard-mail-recipient" value="${recipient}" style="width:100%; height:32px; font-size:13px;">
                                </div>
                                <div style="display:grid; grid-template-columns: 110px 1fr; gap:10px; align-items:center; font-size:13px; margin-bottom:8px;">
                                    <strong style="color:#475569;">Betreff:</strong>
                                    <input type="text" id="crm-wizard-mail-subject" value="${crmEscapeHtml(tbSubject)}" style="width:100%; height:32px; font-size:13px; font-weight:600;">
                                </div>
                                <div style="display:grid; grid-template-columns: 110px 1fr auto; gap:10px; align-items:center; font-size:13px; background:#f0f9ff; padding:8px 12px; border-radius:6px; border:1px solid #bae6fd;">
                                    <strong style="color:#0369a1; display:flex; align-items:center; gap:5px;">
                                        <span class="dashicons dashicons-email-alt" style="font-size:16px; width:16px; height:16px;"></span> Test-Empfänger:
                                    </strong>
                                    <input type="email" id="crm-wizard-test-recipient" value="${crmEscapeHtml(testEmail)}" placeholder="ihre-adresse@domain.at" style="width:100%; height:32px; font-size:13px; border-color:#7dd3fc;">
                                    ${quickUserBtnHtml}
                                </div>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; padding:14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px;">
                                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                    <button type="button" class="button crm-wizard-send-test-btn" style="background:#0284c7; color:#fff; border-color:#0284c7; font-weight:600; height:34px;">
                                        🧪 Test-E-Mail senden
                                    </button>
                                    <button type="button" class="button button-primary crm-wizard-open-editor-btn" style="font-weight:600; height:34px;">
                                        ✏️ Im Vollbild-Editor öffnen & versenden
                                    </button>
                                </div>
                                <div id="crm-wizard-send-status" style="font-size:12px; color:#047857; font-weight:600;"></div>
                            </div>
                        </div>
                    `;
                }
                return;
            }

            // ==========================================
            // CASE 3: STAGE 'DIPLOMA' (Diplom & Abschluss)
            // ==========================================
            if (stage === 'diploma') {
                wizardBody.innerHTML = `
                    <div style="display:flex; flex-direction:column; gap:16px;">
                        <div style="background:#fffbeb; border:1px solid #fde68a; border-left:4px solid #d97706; border-radius:6px; padding:14px 16px;">
                            <strong style="color:#92400e; font-size:14px;">Aktueller Verlauf: Diplom & Zertifizierung</strong>
                            <div style="margin-top:3px; font-size:12.5px; color:#b45309;">
                                Teilnehmer hat den Kurs erfolgreich abgeschlossen. Offizielles X-SIEBEN Diplom ausstellen.
                            </div>
                        </div>
                        <div class="crm-wizard-card">
                            <div class="crm-wizard-card-header">
                                <span>Abschluss- & Prüfungsdaten</span>
                            </div>
                            <div style="font-size:12.5px; line-height:1.6; color:#334155;">
                                <div><strong>Teilnehmer:</strong> ${currentWizardData.client_display_name}</div>
                                <div><strong>Kurs:</strong> ${currentWizardData.course_title}</div>
                                <div><strong>Erfolg:</strong> <span style="color:#047857; font-weight:700;">Mit Erfolg teilgenommen</span></div>
                            </div>
                        </div>
                        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; padding:14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
                            <button type="button" class="button button-primary crm-wizard-open-editor-btn" style="background:#b45309; border-color:#92400e; font-weight:700; height:36px;">
                                🎓 Diplom im Vollbild-Editor bearbeiten & erstellen
                            </button>
                            <button type="button" class="button crm-wizard-quick-status-btn" data-status="abgeschlossen" style="height:36px;">
                                ✓ Als 'Abgeschlossen' markieren
                            </button>
                        </div>
                    </div>
                `;
                return;
            }

            // ==========================================
            // CASE 4: DEFAULT STAGE 'OFFER' (Angebot & Lead)
            // ==========================================
            if (step === 1) {
                const amsActive = currentWizardData.foerderung && currentWizardData.foerderung.ams;
                const waffActive = currentWizardData.foerderung && currentWizardData.foerderung.waff;

                wizardBody.innerHTML = `
                    <div style="display:flex; flex-direction:column; gap:16px;">
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid #6d28d9; border-radius:6px; padding:12px 16px;">
                            <strong style="color:#0f172a; font-size:13.5px;">Schritt 1: Lead-Prüfung & Förderstellen-Zuordnung</strong>
                            <p style="margin:4px 0 0 0; font-size:12.5px; color:#64748b;">
                                Prüfen Sie die Kundendaten und aktivieren Sie bei Bedarf AMS- oder WAFF-Förderung. Die Dokumente und E-Mail-Texte werden automatisch darauf abgestimmt.
                            </p>
                        </div>

                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:14px;">
                            <div class="crm-wizard-card">
                                <div class="crm-wizard-card-header" style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="display:flex; align-items:center; gap:6px;">
                                        <span class="dashicons dashicons-admin-users" style="color:#6d28d9;"></span>
                                        Kundendaten
                                    </span>
                                    <div style="display:flex; align-items:center; gap:6px;">
                                        <span style="font-size:11px; color:#64748b; font-weight:normal;">ID #${currentWizardData.entry_id}</span>
                                        <button type="button" class="button button-small crm-quick-edit-btn" data-entry-id="${currentWizardData.entry_id}" data-course-id="${currentWizardData.course_id || 0}" style="font-size:11px; height:22px; line-height:20px; padding:0 8px; display:inline-flex; align-items:center; gap:3px;">
                                            <span class="dashicons dashicons-edit" style="font-size:12px; width:12px; height:12px;"></span>
                                            <span>Bearbeiten</span>
                                        </button>
                                    </div>
                                </div>
                                <div style="font-size:12.5px; line-height:1.6; color:#334155;">
                                    <div><strong>Name:</strong> ${currentWizardData.client_display_name}</div>
                                    <div><strong>E-Mail:</strong> <a href="mailto:${currentWizardData.email}">${currentWizardData.email || '–'}</a></div>
                                    <div><strong>SV-Nummer:</strong> ${currentWizardData.svr || '–'}</div>
                                    <div><strong>Unternehmen:</strong> ${currentWizardData.company || 'Privatkunde'}</div>
                                </div>
                            </div>

                            <div class="crm-wizard-card">
                                <div class="crm-wizard-card-header">
                                    <span style="display:flex; align-items:center; gap:6px;">
                                        <span class="dashicons dashicons-welcome-learn-more" style="color:#0284c7;"></span>
                                        Kurs & Zeitraum
                                    </span>
                                </div>
                                <div style="font-size:12.5px; line-height:1.6; color:#334155;">
                                    <div><strong>Kurstitel:</strong> ${currentWizardData.course_title}</div>
                                    <div><strong>Zeitraum:</strong> <span style="background:#f1f5f9; padding:2px 6px; border-radius:4px; font-weight:600;">${currentWizardData.dates_text}</span></div>
                                    <div><strong>Status aktuell:</strong> <span style="font-weight:600; color:#6d28d9;">${currentWizardData.status_label}</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="crm-wizard-card" style="border-left:4px solid #005db4;">
                            <div class="crm-wizard-card-header">
                                <span style="display:flex; align-items:center; gap:6px;">
                                    <span class="dashicons dashicons-awards" style="color:#005db4;"></span>
                                    Förderstelle auswählen (AMS / WAFF)
                                </span>
                                <span style="font-size:11px; color:#64748b; font-weight:normal;">Klick zum Umschalten</span>
                            </div>
                            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-top:6px;">
                                <button type="button" class="button crm-wizard-toggle-foerder-btn" data-type="ams" style="display:inline-flex; align-items:center; gap:8px; padding:6px 14px; font-size:12px; font-weight:700; border-radius:6px; cursor:pointer; ${amsActive ? 'background:#005db4; color:#fff; border-color:#00478a; box-shadow:0 2px 5px rgba(0,93,180,0.3);' : 'background:#f8fafc; color:#64748b; border-color:#cbd5e1;'}">
                                    <span>${amsActive ? '✓ AMS Aktiv' : 'AMS aktivieren'}</span>
                                </button>
                                <button type="button" class="button crm-wizard-toggle-foerder-btn" data-type="waff" style="display:inline-flex; align-items:center; gap:8px; padding:6px 14px; font-size:12px; font-weight:700; border-radius:6px; cursor:pointer; ${waffActive ? 'background:#e30613; color:#fff; border-color:#b8050f; box-shadow:0 2px 5px rgba(227,6,19,0.3);' : 'background:#f8fafc; color:#64748b; border-color:#cbd5e1;'}">
                                    <span>${waffActive ? '✓ WAFF Aktiv' : 'WAFF aktivieren'}</span>
                                </button>
                                <span style="font-size:11.5px; color:#64748b; margin-left:6px;">
                                    ${(amsActive || waffActive) ? '💡 Bei aktivierter Förderung wird automatisch eine Kurszeitenbestätigung (KB) beigelegt.' : 'Standard-Privatkunde (nur Angebot).'}
                                </span>
                            </div>
                        </div>
                    </div>
                `;
            } else if (step === 2) {
                const isAms = Boolean(currentWizardData.foerderung && (currentWizardData.foerderung.ams || currentWizardData.foerderung.waff));
                const isPrepared = currentWizardData.is_prepared;
                const draft = currentWizardData.draft;

                if (!currentWizardData.selected_docs) {
                    currentWizardData.selected_docs = {
                        offer_1: true,
                        offer_2: Boolean(currentWizardData.has_cert_option),
                        offer_3: Boolean(currentWizardData.has_offer_3),
                        kb: isAms,
                        agb: true
                    };
                }
                const sel = currentWizardData.selected_docs;
                const activeAttCount = ['offer_1', 'offer_2', 'offer_3', 'kb'].filter(k => Boolean(sel[k])).length;

                const docConfigs = [
                    {
                        key: 'offer_1',
                        icon: 'dashicons-media-document',
                        iconColor: '#0284c7',
                        title: 'Angebot 1: Basis (PDF)',
                        subtitle: 'Offizielles Kursangebot (Lehrgangsgebühr ohne Zertifizierung)',
                        badgeText: 'Standard',
                        badgeStyle: 'background:#ecfdf5; color:#047857; border:1px solid #a7f3d0;',
                        pdfUrl: crmWizardFindDocUrl('offer_1', currentWizardData)
                    },
                    {
                        key: 'offer_2',
                        icon: 'dashicons-awards',
                        iconColor: '#d97706',
                        title: 'Angebot 2: Inkl. Zertifizierung (PDF)',
                        subtitle: currentWizardData.cert_name 
                            ? `Kursgebühr inklusive passender Zertifizierung (${currentWizardData.cert_name})` 
                            : 'Kursgebühr inklusive passender / gewünschter Zertifizierungsgebühr',
                        badgeText: currentWizardData.cert_name ? `Option: ${currentWizardData.cert_name}` : 'Zertifizierungsoption',
                        badgeStyle: 'background:#fffbeb; color:#b45309; border:1px solid #fde68a;',
                        pdfUrl: crmWizardFindDocUrl('offer_2', currentWizardData)
                    }
                ];

                if (currentWizardData.has_offer_3) {
                    docConfigs.push({
                        key: 'offer_3',
                        icon: 'dashicons-awards',
                        iconColor: '#7c3aed',
                        title: 'Angebot 3: Inkl. IPMA (PDF)',
                        subtitle: 'Kursgebühr inklusive optionaler IPMA / pma - Level D Zertifizierung',
                        badgeText: 'Option: IPMA Level D',
                        badgeStyle: 'background:#faf5ff; color:#7c3aed; border:1px solid #e9d5ff;',
                        pdfUrl: crmWizardFindDocUrl('offer_3', currentWizardData)
                    });
                }
                    {
                        key: 'kb',
                        icon: 'dashicons-calendar-alt',
                        iconColor: '#8b5cf6',
                        title: 'Kurszeitenbestätigung (KB PDF)',
                        subtitle: 'Behördlich anerkanntes Dokument zur Vorlage bei Förderstellen',
                        badgeText: isAms ? 'Förderfall aktiv' : 'Optional',
                        badgeStyle: isAms ? 'background:#eff6ff; color:#005db4; border:1px solid #bfdbfe;' : 'background:#f1f5f9; color:#94a3b8; border:1px solid #e2e8f0;',
                        pdfUrl: crmWizardFindDocUrl('kb', currentWizardData)
                    },
                    {
                        key: 'agb',
                        icon: 'dashicons-admin-links',
                        iconColor: '#64748b',
                        title: 'Allgemeine Geschäftsbedingungen (AGB 2025)',
                        subtitle: 'Wird als Online-Link in der E-Mail verlinkt (kein Dateianhang)',
                        badgeText: 'Online-Link',
                        badgeStyle: 'background:#f1f5f9; color:#475569; border:1px solid #cbd5e1;',
                        pdfUrl: currentWizardData.agb_url || 'https://x-sieben.at/wp-content/uploads/2025/09/AGB_X_SIEBEN_2025.pdf'
                    }
                ];

                let docsListHtml = `
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:10px; font-size:12.5px;">
                        <span style="color:#475569;">
                            📄 <strong id="crm-wizard-selected-count-badge" style="color:#6d28d9;">${activeAttCount} von 3 PDF-Anhängen</strong> zum Mitsenden ausgewählt (AGB als Online-Link)
                        </span>
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="button button-small crm-wizard-quick-select-btn" data-action="all" style="font-size:11.5px; height:26px; line-height:24px; padding:0 8px;">
                                ✓ Alle auswählen
                            </button>
                            <button type="button" class="button button-small crm-wizard-quick-select-btn" data-action="basis" style="font-size:11.5px; height:26px; line-height:24px; padding:0 8px;">
                                Nur Basis & AGB-Link
                            </button>
                        </div>
                    </div>
                `;

                docConfigs.forEach(doc => {
                    const isSelected = Boolean(sel[doc.key]);
                    const previewBtnHtml = doc.pdfUrl 
                        ? `<a href="${doc.pdfUrl}" target="_blank" class="crm-wizard-doc-preview-link" title="Dokument / Link in neuem Tab ansehen" style="margin-left:6px;"><span class="dashicons dashicons-visibility" style="font-size:13px; line-height:13px; width:13px; height:13px;"></span> Vorschau</a>`
                        : '';

                    docsListHtml += `
                        <div class="crm-wizard-doc-item ${isSelected ? 'is-selected' : 'is-unselected'}" data-doc-key="${doc.key}" style="cursor:pointer;">
                            <div style="display:flex; align-items:center; gap:12px; flex:1; min-width:0;">
                                <input type="checkbox" class="crm-wizard-doc-checkbox" data-doc-key="${doc.key}" ${isSelected ? 'checked' : ''}>
                                <span class="dashicons ${doc.icon}" style="color:${doc.iconColor}; font-size:22px; width:22px; height:22px; flex-shrink:0;"></span>
                                <div style="min-width:0;">
                                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                        <strong style="font-size:13px; color:#1e293b;">${doc.title}</strong>
                                        <span class="crm-wizard-doc-badge" style="${doc.badgeStyle}">${doc.badgeText}</span>
                                        ${previewBtnHtml}
                                    </div>
                                    <div style="font-size:11.5px; color:#64748b; margin-top:2px;">${doc.subtitle}</div>
                                </div>
                            </div>
                            <div class="crm-wizard-doc-status" style="font-size:11.5px; font-weight:700; flex-shrink:0; margin-left:12px; color:${isSelected ? (doc.key === 'agb' ? '#0284c7' : '#6d28d9') : '#94a3b8'};">
                                ${doc.key === 'agb' ? (isSelected ? '✓ In E-Mail verlinkt' : '✕ Nicht verlinkt') : (isSelected ? '✓ Wird mitgesendet' : '✕ Nicht mitgesendet')}
                            </div>
                        </div>
                    `;
                });

                let generateBoxHtml = '';
                if (isPrepared && draft && draft.pdf_urls && draft.pdf_urls.length > 0) {
                    const activeUrls = crmWizardGetActivePdfUrls(currentWizardData);
                    const pdfBtns = activeUrls.map(url => {
                        const fname = url.split('/').pop();
                        return `<a href="${url}" target="_blank" class="button" style="display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:600;"><span class="dashicons dashicons-pdf" style="color:#dc2626;"></span> ${fname}</a>`;
                    }).join(' ');

                    generateBoxHtml = `
                        <div style="background:#ecfdf5; border:1px solid #a7f3d0; border-radius:8px; padding:16px; margin-top:14px;">
                            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <span class="dashicons dashicons-yes-alt" style="color:#10b981; font-size:24px; width:24px; height:24px;"></span>
                                    <div>
                                        <strong style="color:#065f46; font-size:13.5px;">Dokumente wurden erfolgreich vorbereitet!</strong>
                                        <div style="font-size:11.5px; color:#047857; margin-top:2px;">${activeUrls.length} Beilage(n) aktiv ausgewählt &bull; Bereit für Freigabe &amp; Versand</div>
                                    </div>
                                </div>
                                <button type="button" class="button crm-wizard-run-generate-btn" style="color:#6d28d9; border-color:#c4b5fd; font-weight:600;">
                                    <span class="dashicons dashicons-update" style="font-size:14px; line-height:26px;"></span> Auswahl neu generieren
                                </button>
                            </div>
                            <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
                                ${pdfBtns || '<span style="font-size:12px; color:#64748b;">Keine Beilagen angehängt.</span>'}
                            </div>
                        </div>
                    `;
                } else {
                    generateBoxHtml = `
                        <div style="background:#faf5ff; border:1px solid #e9d5ff; border-radius:8px; padding:24px; text-align:center; margin-top:14px;">
                            <span class="dashicons dashicons-superhero" style="font-size:36px; width:36px; height:36px; color:#6d28d9; margin-bottom:8px;"></span>
                            <h4 style="margin:0; font-size:15px; color:#1e293b; font-weight:700;">Ausgewählte Dokumente & Anschreiben jetzt automatisch erstellen</h4>
                            <p style="margin:6px 0 16px 0; font-size:12.5px; color:#64748b; max-width:480px; margin-left:auto; margin-right:auto;">
                                Der Wizard erstellt sekundenschnell das personalisierte PDF-Angebot, prüft Förderangaben und formuliert die Begleit-E-Mail vor.
                            </p>
                            <button type="button" class="button button-primary crm-wizard-run-generate-btn" style="background:#6d28d9; border-color:#5b21b6; font-size:13px; font-weight:700; padding:4px 18px; height:36px; line-height:34px;">
                                ⚡ Ausgewählte Dokumente jetzt automatisch generieren
                            </button>
                        </div>
                    `;
                }

                wizardBody.innerHTML = `
                    <div style="display:flex; flex-direction:column; gap:14px;">
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid #6d28d9; border-radius:6px; padding:12px 16px;">
                            <strong style="color:#0f172a; font-size:13.5px;">Schritt 2: Dokumentenauswahl & PDF-Generierung</strong>
                            <p style="margin:4px 0 0 0; font-size:12.5px; color:#64748b;">
                                Wählen Sie per Klick oder Checkbox, welche Dokumente generiert und als offizielle Anhänge mitgesendet werden sollen:
                            </p>
                        </div>
                        <div>${docsListHtml}</div>
                        <div id="crm-wizard-gen-container">${generateBoxHtml}</div>
                    </div>
                `;
            } else if (step === 3) {
                const draft = currentWizardData.draft;
                const subject = (draft && draft.subject) ? draft.subject : `Angebot: ${currentWizardData.course_title} | X SIEBEN Wirtschaftstraining`;
                const rawBody = (draft && draft.body && !draft.body.startsWith('Vorgang:')) ? draft.body : '';
                const recipient = currentWizardData.email || '';
                const loggedInUserEmail = (typeof crmData !== 'undefined' && crmData.currentUserEmail) ? crmData.currentUserEmail : '';
                const testEmail = currentWizardData.default_test_email || loggedInUserEmail || 'gajo@x-sieben.at';

                let quickUserBtnHtml = '';
                if (loggedInUserEmail && loggedInUserEmail !== testEmail) {
                    quickUserBtnHtml = `<button type="button" class="button button-small crm-wizard-set-test-email-btn" data-email="${crmEscapeHtml(loggedInUserEmail)}" style="font-size:11.5px; height:30px; line-height:28px; background:#ffffff; color:#0284c7; border-color:#7dd3fc;" title="Meine E-Mail (${loggedInUserEmail}) als Test-Empfänger einsetzen">👤 An mich (${crmEscapeHtml(loggedInUserEmail)})</button>`;
                } else if (currentWizardData.default_test_email && currentWizardData.default_test_email !== loggedInUserEmail && loggedInUserEmail) {
                    quickUserBtnHtml = `<button type="button" class="button button-small crm-wizard-set-test-email-btn" data-email="${crmEscapeHtml(currentWizardData.default_test_email)}" style="font-size:11.5px; height:30px; line-height:28px; background:#ffffff; color:#0284c7; border-color:#7dd3fc;" title="${currentWizardData.default_test_email} als Test-Empfänger einsetzen">👤 An Hannes (${crmEscapeHtml(currentWizardData.default_test_email)})</button>`;
                }

                const activeUrls = crmWizardGetActivePdfUrls(currentWizardData);
                let activeChipsHtml = '';
                if (activeUrls.length === 0) {
                    activeChipsHtml = '<span style="color:#d97706; font-size:12px; font-style:italic;">⚠️ Keine Anhänge ausgewählt (reine Text-E-Mail ohne PDF-Beilagen).</span>';
                } else {
                    activeChipsHtml = activeUrls.map(url => {
                        const fname = url.split('/').pop();
                        return `
                            <div style="display:inline-flex; align-items:center; gap:6px; background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; padding:4px 10px; font-size:12px; font-weight:600; color:#1e293b;">
                                <span class="dashicons dashicons-pdf" style="color:#dc2626; font-size:16px; width:16px; height:16px; line-height:16px;"></span>
                                <span>${fname}</span>
                                <a href="${url}" target="_blank" style="color:#0284c7; margin-left:2px;" title="Vorschau"><span class="dashicons dashicons-visibility" style="font-size:14px; width:14px; height:14px; line-height:14px;"></span></a>
                            </div>
                        `;
                    }).join(' ');
                }

                wizardBody.innerHTML = `
                    <div style="display:flex; flex-direction:column; gap:14px;">
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid #6d28d9; border-radius:6px; padding:12px 16px;">
                            <strong style="color:#0f172a; font-size:13.5px;">Schritt 3: E-Mail-Vorschau & Freigabe</strong>
                            <p style="margin:4px 0 0 0; font-size:12.5px; color:#64748b;">
                                Überprüfen Sie Betreff, Anschreiben und Beilagen vor dem Versand. Sie können eine Test-Mail senden oder das Angebot direkt an den Kunden übermitteln.
                            </p>
                        </div>

                        <div class="crm-wizard-card">
                            <div style="display:grid; grid-template-columns: 110px 1fr; gap:10px; align-items:center; font-size:13px; margin-bottom:8px;">
                                <strong style="color:#475569;">Empfänger:</strong>
                                <input type="email" id="crm-wizard-mail-recipient" value="${recipient}" style="width:100%; height:32px; font-size:13px;">
                            </div>
                            <div style="display:grid; grid-template-columns: 110px 1fr; gap:10px; align-items:center; font-size:13px; margin-bottom:8px;">
                                <strong style="color:#475569;">Betreff:</strong>
                                <input type="text" id="crm-wizard-mail-subject" value="${crmEscapeHtml(subject)}" style="width:100%; height:32px; font-size:13px; font-weight:600;">
                            </div>
                            <div style="display:grid; grid-template-columns: 110px 1fr auto; gap:10px; align-items:center; font-size:13px; background:#f0f9ff; padding:8px 12px; border-radius:6px; border:1px solid #bae6fd;">
                                <strong style="color:#0369a1; display:flex; align-items:center; gap:5px;">
                                    <span class="dashicons dashicons-email-alt" style="font-size:16px; width:16px; height:16px;"></span> Test-Empfänger:
                                </strong>
                                <input type="email" id="crm-wizard-test-recipient" value="${crmEscapeHtml(testEmail)}" placeholder="ihre-adresse@domain.at" style="width:100%; height:32px; font-size:13px; border-color:#7dd3fc;">
                                ${quickUserBtnHtml}
                            </div>
                        </div>

                        <div class="crm-wizard-card" style="padding:12px 16px; background:#f8fafc; border:1px solid #e2e8f0; border-left:4px solid #6d28d9;">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px; margin-bottom:8px;">
                                <strong style="font-size:12.5px; color:#1e293b; display:flex; align-items:center; gap:6px;">
                                    <span class="dashicons dashicons-paperclip" style="color:#6d28d9;"></span>
                                    Beigefügte Anhänge (${activeUrls.length} ausgewählt):
                                </strong>
                                <button type="button" class="crm-wizard-jump-step-btn" data-target-step="2" style="background:none; border:none; color:#6d28d9; font-size:11.5px; font-weight:600; cursor:pointer; text-decoration:underline;">
                                    ✏️ Dokumentenauswahl in Schritt 2 anpassen
                                </button>
                            </div>
                            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                ${activeChipsHtml}
                            </div>
                        </div>

                        <div class="crm-wizard-card" style="padding:14px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <strong style="font-size:12px; color:#475569;">Anschreiben (Vorschau laut X-SIEBEN E-Mail-Vorgaben):</strong>
                                <span style="font-size:11.5px; color:#059669; font-weight:600;">✓ Offizielle Standard-Vorlage aktiv</span>
                            </div>
                            <div id="crm-wizard-mail-body-preview" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:6px; padding:16px; font-size:13px; line-height:1.6; max-height:280px; overflow-y:auto; color:#1e293b; box-shadow:inset 0 1px 3px rgba(0,0,0,0.03);">
                                ${rawBody || '<div style="color:#64748b; font-style:italic;"><span class="dashicons dashicons-update spin"></span> E-Mail-Vorgaben werden synchronisiert...</div>'}
                            </div>
                            <textarea id="crm-wizard-mail-body" style="display:none;">${crmEscapeHtml(rawBody || '')}</textarea>
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; padding:14px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px;">
                            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                <button type="button" class="button crm-wizard-send-test-btn" style="background:#0284c7; color:#fff; border-color:#0284c7; font-weight:600; height:34px;">
                                    🧪 Test-E-Mail senden
                                </button>
                                <button type="button" class="button crm-wizard-send-customer-btn" style="background:#059669; color:#fff; border-color:#047857; font-weight:700; height:34px;">
                                    🚀 Verbindlich an Kunden versenden
                                </button>
                                <button type="button" class="button button-primary crm-wizard-open-editor-btn" style="font-weight:600; height:34px;">
                                    ✏️ Im Vollbild-Editor bearbeiten
                                </button>
                                <button type="button" class="button crm-wizard-quick-status-btn" data-status="angebot_gesendet" style="background:#10b981; color:#fff; border-color:#059669; font-weight:600; height:34px;">
                                    ✓ Als 'Angebot gesendet' markieren
                                </button>
                            </div>
                            <div id="crm-wizard-send-status" style="font-size:12px; color:#047857; font-weight:600;"></div>
                        </div>
                    </div>
                `;

                crmRefreshWizardEmailPreview();
            }
        }

        // Wizard event delegation (guaranteed at document level)
        document.addEventListener('click', function (e) {
            const backdrop = document.getElementById('crm-wizard-modal-backdrop');
            if (!backdrop || backdrop.style.display === 'none') return;

            // Close button or backdrop click
            if (e.target === backdrop || e.target.closest('.crm-close-wizard-modal')) {
                backdrop.style.display = 'none';
                return;
            }

            // History toggle inside Wizard
            if (e.target.closest('#crm-wizard-history-toggle')) {
                const historyDrawer = document.getElementById('crm-wizard-history-drawer');
                if (historyDrawer) {
                    const isOpen = historyDrawer.style.display === 'block';
                    historyDrawer.style.display = isOpen ? 'none' : 'block';
                }
                return;
            }

            // Quick toggle test email recipient (e.g. switch to current admin user)
            const setTestEmailBtn = e.target.closest('.crm-wizard-set-test-email-btn');
            if (setTestEmailBtn) {
                const targetEmail = setTestEmailBtn.dataset.email || setTestEmailBtn.getAttribute('data-email');
                if (targetEmail) {
                    const testInput = document.getElementById('crm-wizard-test-recipient');
                    if (testInput) {
                        testInput.value = targetEmail;
                        testInput.focus();
                    }
                }
                return;
            }

            // Step Tab Click
                const stepTab = e.target.closest('.crm-wizard-step-tab');
                if (stepTab && stepTab.dataset.step) {
                    renderWizardStep(parseInt(stepTab.dataset.step, 10));
                    return;
                }

                // Next Button Click
                if (e.target.closest('.crm-wizard-next-btn')) {
                    if (currentWizardStep < 3) {
                        renderWizardStep(currentWizardStep + 1);
                    }
                    return;
                }

                // Prev Button Click
                if (e.target.closest('.crm-wizard-prev-btn')) {
                    if (currentWizardStep > 1) {
                        renderWizardStep(currentWizardStep - 1);
                    }
                    return;
                }

                // Quick Status Change inside Wizard
                const quickStatusBtn = e.target.closest('.crm-wizard-quick-status-btn');
                if (quickStatusBtn) {
                    const nextStatus = quickStatusBtn.dataset.status;
                    if (!nextStatus) return;

                    quickStatusBtn.disabled = true;
                    const origHtml = quickStatusBtn.innerHTML;
                    quickStatusBtn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Status wird aktualisiert...';

                    const formData = new FormData();
                    formData.append('action', 'crm_update_entry_status');
                    formData.append('nonce', nonce);
                    formData.append('entry_id', currentWizardEntryId);
                    formData.append('course_id', currentWizardCourseId);
                    formData.append('status_key', nextStatus);
                    formData.append('note', `Status im Wizard geändert auf: ${nextStatus}`);

                    fetch(ajaxUrl, { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(res => {
                            if (res.success) {
                                // Update row in CRM table
                                if (currentWizardRow) {
                                    const statusCell = currentWizardRow.querySelector('.crm-status-cell');
                                    if (statusCell && res.data.badge_html) {
                                        const pill = statusCell.querySelector('.crm-status-pill');
                                        if (pill) {
                                            pill.className = `crm-status-pill crm-status-${nextStatus}`;
                                            pill.dataset.status = nextStatus;
                                            const labelEl = pill.querySelector('.crm-status-label');
                                            if (labelEl) labelEl.textContent = res.data.status_label || nextStatus;
                                        }
                                    }
                                    const actionsCell = currentWizardRow.querySelector('.crm-actions');
                                    if (actionsCell && res.data.actions_html) {
                                        actionsCell.innerHTML = res.data.actions_html;
                                    }
                                }
                                displayNotice('Status erfolgreich im Verlauf aktualisiert!', 'success');
                                openWizardModal(currentWizardEntryId, currentWizardCourseId, currentWizardRow, currentWizardBtn);
                            } else {
                                alert((res.data && res.data.message) ? res.data.message : 'Fehler beim Aktualisieren.');
                                quickStatusBtn.disabled = false;
                                quickStatusBtn.innerHTML = origHtml;
                            }
                        })
                        .catch(err => {
                            console.error('Wizard quick status error:', err);
                            alert('Netzwerkfehler.');
                            quickStatusBtn.disabled = false;
                            quickStatusBtn.innerHTML = origHtml;
                        });
                    return;
                }

                // Toggle Förderstelle inside Wizard (AMS / WAFF)
                const toggleFoerderBtn = e.target.closest('.crm-wizard-toggle-foerder-btn');
                if (toggleFoerderBtn) {
                    const fType = toggleFoerderBtn.dataset.type;
                    const currentlyActive = currentWizardData.foerderung && currentWizardData.foerderung[fType];
                    const nextActive = !currentlyActive;

                    toggleFoerderBtn.disabled = true;

                    const formData = new FormData();
                    formData.append('action', 'crm_toggle_foerderung');
                    formData.append('nonce', nonce);
                    formData.append('entry_id', currentWizardEntryId);
                    formData.append('type', fType);
                    formData.append('active', nextActive ? 1 : 0);

                    fetch(ajaxUrl, { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(res => {
                            if (res.success) {
                                currentWizardData.foerderung = res.data.foerderung;
                                const isAms = Boolean(currentWizardData.foerderung && (currentWizardData.foerderung.ams || currentWizardData.foerderung.waff));
                                if (!currentWizardData.selected_docs) currentWizardData.selected_docs = {};
                                currentWizardData.selected_docs.kb = isAms;
                                renderWizardStep(1);

                                // Update row badges in CRM table if present
                                if (currentWizardRow) {
                                    const clientFoerderWrap = currentWizardRow.querySelector('.crm-course-foerder-badges');
                                    if (clientFoerderWrap && typeof res.data.badges_html !== 'undefined') {
                                        clientFoerderWrap.outerHTML = res.data.badges_html;
                                    } else if (res.data.badges_html) {
                                        const titleRow = currentWizardRow.querySelector('.crm-client-title-row') || currentWizardRow.querySelector('.crm-client-cell-inner');
                                        if (titleRow) titleRow.insertAdjacentHTML('beforeend', res.data.badges_html);
                                    }
                                }
                                displayNotice(res.data.message, 'success');
                            }
                        })
                        .finally(() => {
                            toggleFoerderBtn.disabled = false;
                        });
                    return;
                }

                // Document Card / Checkbox toggle inside Wizard (Step 2)
                const docCard = e.target.closest('.crm-wizard-doc-item');
                if (docCard && docCard.dataset.docKey && currentWizardStep === 2) {
                    // Ignore clicks on preview links or anchors
                    if (e.target.closest('.crm-wizard-doc-preview-link') || e.target.tagName.toLowerCase() === 'a') {
                        return;
                    }
                    const docKey = docCard.dataset.docKey;
                    const cb = docCard.querySelector('.crm-wizard-doc-checkbox');
                    const nextState = (e.target === cb) ? cb.checked : !cb.checked;
                    if (e.target !== cb && cb) {
                        cb.checked = nextState;
                    }
                    if (!currentWizardData.selected_docs) {
                        currentWizardData.selected_docs = {};
                    }
                    currentWizardData.selected_docs[docKey] = nextState;

                    docCard.classList.toggle('is-selected', nextState);
                    docCard.classList.toggle('is-unselected', !nextState);

                    const statusEl = docCard.querySelector('.crm-wizard-doc-status');
                    if (statusEl) {
                        if (docKey === 'agb') {
                            statusEl.textContent = nextState ? '✓ In E-Mail verlinkt' : '✕ Nicht verlinkt';
                            statusEl.style.color = nextState ? '#0284c7' : '#94a3b8';
                        } else {
                            statusEl.textContent = nextState ? '✓ Wird mitgesendet' : '✕ Nicht mitgesendet';
                            statusEl.style.color = nextState ? '#6d28d9' : '#94a3b8';
                        }
                    }

                    const maxDocs = currentWizardData.has_offer_3 ? 4 : 3;
                    const activeAttCount = ['offer_1', 'offer_2', 'offer_3', 'kb'].filter(k => Boolean(currentWizardData.selected_docs[k])).length;
                    const countBadge = document.getElementById('crm-wizard-selected-count-badge');
                    if (countBadge) {
                        countBadge.textContent = `${activeAttCount} von ${maxDocs} PDF-Anhängen`;
                    }
                    return;
                }

                // Quick Select buttons inside Wizard Step 2 (Alle / Nur Basis & AGB)
                const quickSelectBtn = e.target.closest('.crm-wizard-quick-select-btn');
                if (quickSelectBtn) {
                    const action = quickSelectBtn.dataset.action;
                    if (!currentWizardData.selected_docs) {
                        currentWizardData.selected_docs = {};
                    }
                    if (action === 'all') {
                        currentWizardData.selected_docs = {
                            offer_1: true,
                            offer_2: true,
                            offer_3: Boolean(currentWizardData.has_offer_3),
                            kb: true,
                            agb: true
                        };
                    } else if (action === 'basis') {
                        currentWizardData.selected_docs = {
                            offer_1: true,
                            offer_2: false,
                            offer_3: false,
                            kb: false,
                            agb: true
                        };
                    }
                    renderWizardStep(2);
                    return;
                }

                // Jump to Step button inside Wizard (e.g. from Step 3 to Step 2)
                const jumpStepBtn = e.target.closest('.crm-wizard-jump-step-btn');
                if (jumpStepBtn) {
                    const targetStep = parseInt(jumpStepBtn.dataset.targetStep, 10);
                    if (!isNaN(targetStep) && targetStep >= 1 && targetStep <= 3) {
                        renderWizardStep(targetStep);
                    }
                    return;
                }

                // Run Document Generation inside Wizard
                const genBtn = e.target.closest('.crm-wizard-run-generate-btn');
                if (genBtn) {
                    genBtn.disabled = true;
                    genBtn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Dokumente werden erstellt...';

                    const formData = new FormData();
                    formData.append('action', 'crm_run_friedelin_preparation');
                    formData.append('nonce', nonce);
                    formData.append('entry_id', currentWizardEntryId);
                    formData.append('selected_docs', JSON.stringify(currentWizardData.selected_docs || {}));

                    fetch(ajaxUrl, { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(res => {
                            if (res.success) {
                                currentWizardData.is_prepared = true;
                                currentWizardData.status_key = 'versand_vorbereitet';
                                currentWizardData.status_label = 'Für den Versand vorbereitet';
                                currentWizardData.draft = {
                                    pdf_urls: res.data.pdf_urls || [],
                                    subject: res.data.subject || (currentWizardData.draft && currentWizardData.draft.subject) || '',
                                    body: res.data.body || (currentWizardData.draft && currentWizardData.draft.body) || '',
                                    summary: res.data.summary || '',
                                    selected_docs: currentWizardData.selected_docs || {}
                                };

                                // Update CRM table row
                                if (currentWizardRow) {
                                    const statusPill = currentWizardRow.querySelector('.crm-status-pill');
                                    const statusLabel = currentWizardRow.querySelector('.crm-status-label');
                                    if (statusPill) {
                                        statusPill.className = 'crm-status-pill crm-status-versand_vorbereitet';
                                        statusPill.dataset.status = 'versand_vorbereitet';
                                    }
                                    if (statusLabel) {
                                        statusLabel.textContent = 'Für den Versand vorbereitet';
                                    }
                                    if (currentWizardBtn) {
                                        currentWizardBtn.style.background = '#6d28d9';
                                        currentWizardBtn.style.color = '#fff';
                                        currentWizardBtn.style.borderColor = '#5b21b6';
                                        currentWizardBtn.innerHTML = '<span>✅ Wizard: Versandbereit</span>';
                                        currentWizardBtn.title = 'Angebot & Dokumente vorbereitet – Wizard zur Freigabe & zum Versand öffnen';
                                    }
                                }

                                displayNotice(res.data.message || 'Dokumente erfolgreich generiert!', 'success');
                                renderWizardStep(2);
                            } else {
                                alert((res.data && res.data.message) ? res.data.message : 'Fehler beim Generieren der Dokumente.');
                                genBtn.disabled = false;
                                genBtn.textContent = '⚡ Dokumente jetzt automatisch generieren';
                            }
                        })
                        .catch(err => {
                            console.error('Wizard generate error:', err);
                            alert('Netzwerkfehler beim Generieren.');
                            genBtn.disabled = false;
                            genBtn.textContent = '⚡ Dokumente jetzt automatisch generieren';
                        });
                    return;
                }

                // Robust Mail Sender Helper (supports window.jQuery.ajax with automatic native fetch fallback)
                function crmSendMailAjax(postData, onSuccess, onError, timeoutMs) {
                    timeoutMs = timeoutMs || 30000;
                    const targetUrl = (typeof xSiebenAjax !== 'undefined' && xSiebenAjax.ajax_url)
                        ? xSiebenAjax.ajax_url
                        : ((typeof ajaxUrl !== 'undefined') ? ajaxUrl : '/wp-admin/admin-ajax.php');
                    const targetNonce = (typeof xSiebenAjax !== 'undefined' && xSiebenAjax.nonce)
                        ? xSiebenAjax.nonce
                        : ((typeof nonce !== 'undefined') ? nonce : '');

                    if (!postData.security) postData.security = targetNonce;
                    if (!postData.nonce) postData.nonce = targetNonce;

                    const jq = window.jQuery || window.$;
                    if (jq && typeof jq.ajax === 'function') {
                        jq.ajax({
                            url: targetUrl,
                            method: 'POST',
                            timeout: timeoutMs || 30000,
                            data: postData,
                            success: onSuccess,
                            error: onError
                        });
                        return;
                    }

                    // Native fetch fallback with AbortController for timeout
                    const controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
                    const timer = controller ? setTimeout(() => controller.abort(), timeoutMs) : null;
                    const fd = new FormData();
                    for (const key in postData) {
                        if (Object.prototype.hasOwnProperty.call(postData, key)) {
                            fd.append(key, postData[key] !== null && postData[key] !== undefined ? postData[key] : '');
                        }
                    }

                    fetch(targetUrl, {
                        method: 'POST',
                        body: fd,
                        signal: controller ? controller.signal : undefined
                    })
                    .then(response => {
                        if (timer) clearTimeout(timer);
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        onSuccess(data);
                    })
                    .catch(err => {
                        if (timer) clearTimeout(timer);
                        const isTimeout = (err && (err.name === 'AbortError' || String(err).includes('AbortError')));
                        onError({ status: 0, statusText: err ? err.message : '' }, isTimeout ? 'timeout' : 'error');
                    });
                }

                // Send Test Mail from Wizard
                const testMailBtn = e.target.closest('.crm-wizard-send-test-btn');
                if (testMailBtn) {
                    const testInput = document.getElementById('crm-wizard-test-recipient');
                    const testEmail = (testInput ? testInput.value.trim() : '') || currentWizardData.default_test_email || (typeof crmData !== 'undefined' ? crmData.currentUserEmail : '') || 'gajo@x-sieben.at';

                    if (!testEmail || !testEmail.includes('@')) {
                        alert('Bitte geben Sie eine gültige Test-E-Mail-Adresse an.');
                        if (testInput) testInput.focus();
                        return;
                    }

                    const subject = document.getElementById('crm-wizard-mail-subject')?.value || '';
                    const draft = currentWizardData.draft;
                    const activeUrls = crmWizardGetActivePdfUrls(currentWizardData);
                    const pdfUrl = activeUrls.join(',');
                    const mailBody = document.getElementById('crm-wizard-mail-body')?.value || (draft ? draft.body : '');

                    testMailBtn.disabled = true;
                    testMailBtn.innerHTML = '<span class="dashicons dashicons-update spin" style="font-size:15px; width:15px; height:15px; line-height:15px; margin-right:4px;"></span> Wird gesendet...';

                    const statusEl = document.getElementById('crm-wizard-send-status');
                    if (statusEl) {
                        statusEl.textContent = `Test-E-Mail wird an ${testEmail} gesendet...`;
                        statusEl.style.color = '#0284c7';
                    }

                    crmSendMailAjax({
                        action: 'x_sieben_send_mail',
                        security: (typeof xSiebenAjax !== 'undefined' && xSiebenAjax.nonce) ? xSiebenAjax.nonce : nonce,
                        nonce: (typeof xSiebenAjax !== 'undefined' && xSiebenAjax.nonce) ? xSiebenAjax.nonce : nonce,
                        x_sieben_recipient: currentWizardData.email || '',
                        x_sieben_subject: subject,
                        x_sieben_body: mailBody,
                        x_sieben_pdf_url: pdfUrl,
                        course_id: currentWizardCourseId,
                        entry_id: currentWizardEntryId,
                        context: (currentWizardData && currentWizardData.stage === 'attendance') ? 'kurszeitenbestaetigung' : 'xsieben_offer',
                        is_test_mode: 1,
                        test_recipient: testEmail,
                        test_mode_type: 'only_test',
                        prefix_subject: 1
                    }, function (resp) {
                        testMailBtn.disabled = false;
                        testMailBtn.textContent = '🧪 Test-E-Mail senden';
                        if (resp && resp.success) {
                            const successMsg = (typeof resp.data === 'object' && resp.data.message)
                                ? resp.data.message
                                : (typeof resp.data === 'string' ? resp.data : `Test-E-Mail erfolgreich an ${testEmail} gesendet!`);
                            if (statusEl) {
                                statusEl.innerHTML = `✓ Test-E-Mail (${activeUrls.length} Anhänge) an <strong>${crmEscapeHtml(testEmail)}</strong> gesendet!`;
                                statusEl.style.color = '#16a34a';
                            }
                            displayNotice(`🧪 Test-E-Mail mit ${activeUrls.length} Anhang/Anhänge erfolgreich an ${testEmail} gesendet!`, 'success');
                        } else {
                            const errMsg = (resp && typeof resp.data === 'object' && resp.data.message)
                                ? resp.data.message
                                : (resp && typeof resp.data === 'string' ? resp.data : 'Fehler beim Testversand.');
                            if (statusEl) {
                                statusEl.textContent = errMsg;
                                statusEl.style.color = '#dc2626';
                            }
                            displayNotice(errMsg, 'error');
                        }
                    }, function (xhr, status) {
                        testMailBtn.disabled = false;
                        testMailBtn.textContent = '🧪 Test-E-Mail senden';
                        const errLabel = status === 'timeout'
                            ? 'Zeitüberschreitung beim Versand (Timeout nach 30s). Bitte Mail-Server prüfen.'
                            : ('Netzwerk- oder Serverfehler beim Testversand' + (xhr && xhr.status ? ` (HTTP ${xhr.status})` : '') + '.');
                        if (statusEl) {
                            statusEl.textContent = errLabel;
                            statusEl.style.color = '#dc2626';
                        }
                        displayNotice(errLabel, 'error');
                    }, 30000);
                    return;
                }

                // Send Customer Mail directly from Wizard Step 3
                const sendCustomerBtn = e.target.closest('.crm-wizard-send-customer-btn');
                if (sendCustomerBtn) {
                    const recipient = document.getElementById('crm-wizard-mail-recipient')?.value || currentWizardData.email || '';
                    if (!recipient) {
                        alert('Bitte geben Sie eine gültige Empfänger-E-Mail-Adresse an.');
                        return;
                    }

                    const activeUrls = crmWizardGetActivePdfUrls(currentWizardData);
                    const pdfCountText = activeUrls.length === 1 ? '1 Anhang' : `${activeUrls.length} Anhänge`;
                    if (!confirm(`Möchten Sie das Angebot jetzt verbindlich an den Kunden (${recipient}) mit ${pdfCountText} versenden?`)) {
                        return;
                    }

                    sendCustomerBtn.disabled = true;
                    sendCustomerBtn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Wird versendet...';

                    const subject = document.getElementById('crm-wizard-mail-subject')?.value || '';
                    const mailBody = document.getElementById('crm-wizard-mail-body')?.value || (currentWizardData.draft ? currentWizardData.draft.body : '');
                    const pdfUrl = activeUrls.join(',');

                    const statusEl = document.getElementById('crm-wizard-send-status');
                    if (statusEl) {
                        statusEl.textContent = 'Angebot wird an Kunden übertragen...';
                        statusEl.style.color = '#0284c7';
                    }

                    crmSendMailAjax({
                        action: 'x_sieben_send_mail',
                        security: (typeof xSiebenAjax !== 'undefined' && xSiebenAjax.nonce) ? xSiebenAjax.nonce : nonce,
                        nonce: (typeof xSiebenAjax !== 'undefined' && xSiebenAjax.nonce) ? xSiebenAjax.nonce : nonce,
                        x_sieben_recipient: recipient,
                        x_sieben_subject: subject,
                        x_sieben_body: mailBody,
                        x_sieben_pdf_url: pdfUrl,
                        course_id: currentWizardCourseId,
                        entry_id: currentWizardEntryId,
                        context: 'xsieben_offer',
                        is_test_mode: 0,
                        prefix_subject: 0
                    }, function (resp) {
                        sendCustomerBtn.disabled = false;
                        sendCustomerBtn.innerHTML = '🚀 Verbindlich an Kunden versenden';
                        if (resp && resp.success) {
                            if (statusEl) {
                                statusEl.innerHTML = `✓ Angebot (${activeUrls.length} Anhänge) erfolgreich an ${recipient} gesendet!`;
                                statusEl.style.color = '#16a34a';
                            }
                            displayNotice(`🚀 Angebot erfolgreich an ${recipient} versendet!`, 'success');

                            // Update entry status to 'angebot_gesendet'
                            const statusFormData = new FormData();
                            statusFormData.append('action', 'crm_update_entry_status');
                            statusFormData.append('nonce', nonce);
                            statusFormData.append('entry_id', currentWizardEntryId);
                            statusFormData.append('new_status', 'angebot_gesendet');
                            statusFormData.append('note', `Angebot verbindlich versendet (${activeUrls.length} Anhänge) via Wizard.`);

                            fetch(ajaxUrl, { method: 'POST', body: statusFormData })
                                .then(r => r.json())
                                .then(r => {
                                    if (r.success) {
                                        currentWizardData.status_key = 'angebot_gesendet';
                                        currentWizardData.status_label = 'Angebot gesendet';
                                        if (currentWizardRow) {
                                            const pill = currentWizardRow.querySelector('.crm-status-pill');
                                            if (pill) {
                                                pill.className = 'crm-status-pill crm-status-angebot_gesendet';
                                                pill.dataset.status = 'angebot_gesendet';
                                                const labelEl = pill.querySelector('.crm-status-label');
                                                if (labelEl) labelEl.textContent = 'Angebot gesendet';
                                            }
                                        }
                                    }
                                });
                        } else {
                            const errMsg = (resp && typeof resp.data === 'object' && resp.data.message)
                                ? resp.data.message
                                : (resp && typeof resp.data === 'string' ? resp.data : 'Fehler beim E-Mail-Versand.');
                            if (statusEl) {
                                statusEl.textContent = 'Fehler beim Senden: ' + errMsg;
                                statusEl.style.color = '#dc2626';
                            }
                            alert('Fehler beim E-Mail-Versand: ' + errMsg);
                        }
                    }, function (xhr, status) {
                        sendCustomerBtn.disabled = false;
                        sendCustomerBtn.innerHTML = '🚀 Verbindlich an Kunden versenden';
                        const errMsg = status === 'timeout' ? 'Zeitüberschreitung beim Versand (Timeout nach 30s).' : 'Netzwerkfehler beim Senden an den Kunden.';
                        if (statusEl) {
                            statusEl.textContent = errMsg;
                            statusEl.style.color = '#dc2626';
                        }
                        alert(errMsg);
                    }, 30000);
                    return;
                }

                // Open Editor from Wizard (aligned with stage)
                if (e.target.closest('.crm-wizard-open-editor-btn')) {
                    wizardBackdrop.style.display = 'none';
                    let targetActionKey = 'xsieben_offer';
                    if (currentWizardData.stage === 'enrolled') {
                        targetActionKey = 'xsieben_teilnahmebestaetigung';
                    } else if (currentWizardData.stage === 'diploma') {
                        targetActionKey = 'xsieben_diplom';
                    } else {
                        const isAms = currentWizardData.foerderung && (currentWizardData.foerderung.ams || currentWizardData.foerderung.waff);
                        targetActionKey = isAms ? 'xsieben_angebot_und_kurszeiten' : 'xsieben_offer';
                    }

                    const targetRow = currentWizardRow || document.querySelector(`tr.crm-entry-row[data-entry-id="${currentWizardEntryId}"]`);

                    if (targetRow) {
                        openEditorView(targetRow, targetActionKey);

                        detailsContainer.innerHTML = '<div style="padding:40px 20px; text-align:center; color:#64748b;"><span class="dashicons dashicons-update spin" style="font-size:32px; width:32px; height:32px; margin-bottom:12px;"></span><br><strong style="font-size:15px; color:#1e293b;">Arbeitsbereich wird vorbereitet...</strong><p style="margin-top:6px; font-size:13px; color:#64748b;">PDF-Vorschau und Optionen werden geladen.</p></div>';
                        detailsContainer.style.display = 'block';

                        const formData = new FormData();
                        formData.append('action', 'crm_entry_action');
                        formData.append('nonce', nonce);
                        formData.append('action_key', targetActionKey);
                        formData.append('entry_id', currentWizardEntryId);
                        formData.append('course_id', currentWizardCourseId);
                        formData.append('context', targetActionKey);

                        fetch(ajaxUrl, { method: 'POST', body: formData })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success && data.data.output) {
                                    detailsContainer.innerHTML = data.data.output;
                                    detailsContainer.style.display = 'block';
                                    initPdfSectionSortables();
                                }
                            });
                    }
                    return;
                }
            });

        // Expose openWizardModal globally
        window.crmOpenWizardModal = openWizardModal;

        // Dedicated delegated click listener for Wizard trigger buttons across ALL views (Table, Cards, Split, Kanban)
        jQuery(document).on('click', '.crm-run-wizard-btn, .crm-run-friedelin-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const btn = this;
            const entryId = btn.dataset.entryId || jQuery(btn).closest('[data-entry-id]').data('entry-id');
            const courseId = btn.dataset.courseId || jQuery(btn).closest('[data-entry-id]').data('course-id') || 0;
            const row = btn.closest('tr.crm-entry-row, .crm-customer-card, .crm-kanban-card, .crm-split-item') ||
                        (entryId ? document.querySelector(`tr.crm-entry-row[data-entry-id="${entryId}"], .crm-customer-card[data-entry-id="${entryId}"]`) : null);

            openWizardModal(entryId, courseId, row, btn);
        });

        // Dedicated delegated click listener to close Wizard modal
        jQuery(document).on('click', '.crm-close-wizard-modal', function (e) {
            e.preventDefault();
            const backdrop = document.getElementById('crm-wizard-modal-backdrop');
            if (backdrop) backdrop.style.display = 'none';
        });

        jQuery(document).on('click', '#crm-wizard-modal-backdrop', function (e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });

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
        document.addEventListener('click', function (e) {
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

                // Initialize the TinyMCE editor with absolute URL settings & live-sync
                if (typeof tinymce !== 'undefined') {
                    tinymce.init({
                        selector: '#x_sieben_body',
                        relative_urls: false,
                        remove_script_host: false,
                        convert_urls: false,
                        menubar: false,
                        toolbar: "undo redo | bold italic underline | bullist numlist | link unlink | code",
                        branding: false,
                        setup: function (editor) {
                            editor.on('change keyup NodeChange SetContent', function () {
                                editor.save();
                            });
                        }
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
            timeout: 25000,
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

                    // Real-time update for Split View if currently active
                    if (snapEntryId && window.crmJsCache && window.crmJsCache.cache) {
                        window.crmJsCache.cache.delete('split_dossier_' + snapEntryId);
                    }
                    const activeSplitItem = document.querySelector('#crm-view-split .crm-split-item.is-active');
                    if (activeSplitItem && String(activeSplitItem.dataset.entryId) === String(snapEntryId)) {
                        const splitPanel = document.getElementById('crm-split-dossier-panel');
                        if (splitPanel && typeof window.crmLoadSplitDossier === 'function') {
                            window.crmLoadSplitDossier(activeSplitItem);
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
                const msg = status === 'timeout' ? 'Zeitüberschreitung beim E-Mail-Versand (Timeout nach 25s).' : 'AJAX request failed. See console.';
                alert(msg);
            },
            complete: function () {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    }

    // =========================================================================
    // DOKUMENTE SIMULATION & DIREKT-EDITOR (Aktionen-Spalte)
    // =========================================================================

    // Dropdown-Menü für Dokumente umschalten
    $(document).on('click', '.crm-docs-dropdown-toggle', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $wrap = $(this).closest('.crm-docs-dropdown-wrap');
        const $menu = $wrap.find('.crm-docs-dropdown-menu');
        $('.crm-docs-dropdown-menu').not($menu).hide();
        $menu.toggle();
    });

    // Klick außerhalb schließt alle Dokumenten-Dropdowns
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.crm-docs-dropdown-wrap').length) {
            $('.crm-docs-dropdown-menu').hide();
        }
    });

    // Hinweis: Klicks auf .crm-simulate-pdf-btn, .crm-direct-editor-btn und .crm-docs-btn
    // werden scopesicher und einheitlich im nativen Tabellen-Handler (Block 1) verarbeitet.


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
                    else if (doc.docType === 'ab') label = 'Anmeldebestätigung herunterladen';
                    else if (doc.docType === 'antritt') label = 'Antrittsmeldung herunterladen';
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
    const ajaxUrl = (typeof crmData !== 'undefined' && crmData.ajaxUrl) ? crmData.ajaxUrl : ((typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php');
    const nonce   = (typeof crmData !== 'undefined' && crmData.nonce) ? crmData.nonce : '';

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
                connectWith: '.crm-sortable-subsections',
                handle: '.crm-sub-drag-handle',
                items: '> li.crm-pdf-subsection-item',
                placeholder: 'crm-sub-sortable-placeholder',
                cursor: 'grabbing',
                opacity: 0.88,
                tolerance: 'pointer',
                receive: function (event, ui) {
                    const $targetSec = jQuery(this).closest('.crm-pdf-section-item');
                    const $sourceSec = jQuery(ui.sender).closest('.crm-pdf-section-item');
                    updateSubsectionsCounter($targetSec);
                    updateSubsectionsCounter($sourceSec);
                },
                stop: function (event, ui) {
                    const $sec = jQuery(this).closest('.crm-pdf-section-item');
                    updateSubsectionsCounter($sec);
                }
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
        if (typeof tinymce !== 'undefined' && typeof tinymce.triggerSave === 'function') {
            tinymce.triggerSave();
        }
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

            let headerMarginTop = null;
            const $hMarginTopInput = $sec.find('.crm-hf-header-margin-top');
            if ($hMarginTopInput.length && $hMarginTopInput.val() !== '') {
                headerMarginTop = parseFloat($hMarginTopInput.val());
            } else if ($sec.data('header-margin-top') !== undefined && $sec.data('header-margin-top') !== '') {
                headerMarginTop = parseFloat($sec.data('header-margin-top'));
            }

            let headerMarginBottom = null;
            const $hMarginBottomInput = $sec.find('.crm-hf-header-margin-bottom');
            if ($hMarginBottomInput.length && $hMarginBottomInput.val() !== '') {
                headerMarginBottom = parseFloat($hMarginBottomInput.val());
            } else if ($sec.data('header-margin-bottom') !== undefined && $sec.data('header-margin-bottom') !== '') {
                headerMarginBottom = parseFloat($sec.data('header-margin-bottom'));
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

                let subSpacingTop = 0;
                let subSpacingBottom = 0;
                if ($drawer.length) {
                    const $subSpTopInput = $drawer.find('.crm-sub-spacing-top');
                    const $subSpBottomInput = $drawer.find('.crm-sub-spacing-bottom');
                    if ($subSpTopInput.length) {
                        subSpacingTop = parseFloat($subSpTopInput.val()) || 0;
                    }
                    if ($subSpBottomInput.length) {
                        subSpacingBottom = parseFloat($subSpBottomInput.val()) || 0;
                    }
                } else {
                    subSpacingTop = parseFloat($sub.data('spacing-top') || $sub.attr('data-spacing-top')) || 0;
                    subSpacingBottom = parseFloat($sub.data('spacing-bottom') || $sub.attr('data-spacing-bottom')) || 0;
                }

                if (subKey) {
                    subsections.push({
                        key: subKey,
                        enabled: subEnabled,
                        is_custom: subCustom,
                        title: subTitle,
                        content: subContent,
                        spacing_top: subSpacingTop,
                        spacing_bottom: subSpacingBottom
                    });
                }
            });

            // Spacing settings
            let spacingTop = 0;
            const $secSpTopInput = $sec.find('.crm-sec-spacing-top');
            if ($secSpTopInput.length) {
                spacingTop = parseFloat($secSpTopInput.val()) || 0;
            } else {
                spacingTop = parseFloat($sec.data('spacing-top') || $sec.attr('data-spacing-top')) || 0;
            }

            let spacingBottom = 0;
            const $secSpBottomInput = $sec.find('.crm-sec-spacing-bottom');
            if ($secSpBottomInput.length) {
                spacingBottom = parseFloat($secSpBottomInput.val()) || 0;
            } else {
                spacingBottom = parseFloat($sec.data('spacing-bottom') || $sec.attr('data-spacing-bottom')) || 0;
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
                    header_margin_top: headerMarginTop,
                    header_margin_bottom: headerMarginBottom,
                    footer_mode: footerMode,
                    footer_company: footerCompany,
                    footer_page_num: footerPageNum,
                    footer_date: footerDate,
                    footer_custom: footerCustom,
                    spacing_top: spacingTop,
                    spacing_bottom: spacingBottom,
                    subsections: subsections
                });
            }
        });
        return sections;
    }
    window.crmGetHierarchicalSections = crmGetHierarchicalSections;

    // Live-Update der Spacing-Pill Beschriftung
    jQuery(document).on('input change', '.crm-sec-spacing-top, .crm-sec-spacing-bottom', function() {
        const $item = jQuery(this).closest('.crm-pdf-section-item');
        const top = parseFloat($item.find('.crm-sec-spacing-top').val()) || 0;
        const bottom = parseFloat($item.find('.crm-sec-spacing-bottom').val()) || 0;
        $item.find('.crm-spacing-summary-text').text('Abstand: ↑' + top + ' pt / ↓' + bottom + ' pt');
    });

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
            data-header-margin-top=""
            data-header-margin-bottom=""
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
                        <div class="crm-hf-header-spacing-row" style="display:flex; gap:10px; margin-top:8px; padding-top:8px; border-top:1px dashed #cbd5e1;">
                            <div style="flex:1;">
                                <label style="display:block; font-size:10px; font-weight:600; color:#475569; margin-bottom:2px;">Header-Abstand oben (mm):</label>
                                <input type="number" step="0.5" min="0" max="100" class="crm-hf-header-margin-top regular-text" style="width:100%; height:26px; font-size:11px;" value="" placeholder="Master: 8">
                            </div>
                            <div style="flex:1;">
                                <label style="display:block; font-size:10px; font-weight:600; color:#475569; margin-bottom:2px;">Abstand Inhalt (mm):</label>
                                <input type="number" step="0.5" min="5" max="150" class="crm-hf-header-margin-bottom regular-text" style="width:100%; height:26px; font-size:11px;" value="" placeholder="Master: 32">
                            </div>
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
                    <small style="color:#64748b; font-size:10.5px;">Ziehen zum Sortieren & Seitenwechsel | Häkchen zum Ein-/Ausblenden</small>
                </div>
                <ul class="crm-sortable-subsections" style="list-style:none; margin:0 0 10px 0; padding:4px; min-height:35px; border-radius:4px;">
                    <li class="crm-pdf-subsection-item sub-active"
                        data-sub-key="body"
                        data-custom="0"
                        data-title="Seiteninhalt"
                        data-orig-title="Seiteninhalt"
                        data-content="${escapeHtml(content)}"
                        data-default-content=""
                        style="display:block; margin-bottom:6px; background:#ffffff; border:1px solid #cbd5e1; border-radius:5px; transition:all 0.12s ease; overflow:hidden;">
                        <div class="crm-sub-row" style="display:flex; align-items:center; gap:8px; padding:6px 10px; cursor:grab;">
                            <span class="crm-sub-drag-handle" title="Ziehen zum Sortieren & Seitenwechsel" style="color:#94a3b8; font-size:14px; cursor:grab; user-select:none;">&#x22EE;&#x22EE;</span>
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
            const $contentInput = $drawer.find('.crm-sub-input-content');
            const $loadBtn = $drawer.find('.crm-sub-load-standard-btn');
            const currentVal = $contentInput.length ? $contentInput.val().trim() : '';

            // Wenn es sich um eine Standard-Komponente handelt und das Feld {standard} oder leer ist:
            // Automatisch das echte Standard-HTML laden, damit der Benutzer den Code direkt bearbeiten kann
            if ($loadBtn.length && (currentVal === '{standard}' || currentVal === '')) {
                $loadBtn.trigger('click');
            } else if ($contentInput.length && !currentVal) {
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

    // Load Standard HTML into Subsection Editor
    jQuery(document).on('click', '.crm-sub-load-standard-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $btn = jQuery(this);
        const $sub = $btn.closest('.crm-pdf-subsection-item');
        const $drawer = $sub.find('> .crm-sub-edit-drawer');
        const $textarea = $drawer.find('.crm-sub-input-content');
        const $manager = $btn.closest('.crm-pdf-sections-manager');
        const docType = $btn.data('doc') || $manager.data('doc') || 'angebot';
        const secKey = $btn.data('sec') || $sub.closest('.crm-pdf-section-item').data('key');
        const subKey = $btn.data('sub') || $sub.data('sub-key');
        const entryId = $manager.data('entry') || $btn.closest('.crm-pdf-sections-preview-box').data('entry') || 0;
        const courseId = $btn.closest('.crm-pdf-sections-preview-box').data('course') || 0;

        const origHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Lade...');

        const ajaxEndpoint = (typeof crmData !== 'undefined' && crmData.ajaxUrl)
            ? crmData.ajaxUrl
            : ((typeof crmSettingsData !== 'undefined' && crmSettingsData.ajaxUrl)
                ? crmSettingsData.ajaxUrl
                : ((typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php'));

        const nonce = (typeof crmData !== 'undefined' && crmData.nonce)
            ? crmData.nonce
            : ((typeof crmSettingsData !== 'undefined' && crmSettingsData.nonce)
                ? crmSettingsData.nonce
                : '');

        jQuery.ajax({
            url: ajaxEndpoint,
            type: 'POST',
            data: {
                action: 'crm_get_subsection_default_html',
                nonce: nonce,
                doc_type: docType,
                sec_key: secKey,
                sub_key: subKey,
                entry_id: entryId,
                course_id: courseId
            },
            success: function (res) {
                $btn.prop('disabled', false).html(origHtml);
                if (res.success && res.data && typeof res.data.html === 'string') {
                    $textarea.val(res.data.html).focus();
                    $sub.data('content', res.data.html).attr('data-content', res.data.html);
                    $sub.find('.crm-sub-custom-badge').show();
                    $drawer.find('.crm-standard-notice-text').text('Aktuell angepasst. Klicken Sie auf den Button, um den System-Standard neu zu laden.');

                    if (res.data.html.length > 200) {
                        $textarea.attr('rows', 10);
                    }

                    $sub.css('background-color', '#f0fdf4');
                    setTimeout(function () {
                        $sub.css('background-color', '#ffffff');
                    }, 600);
                } else {
                    if (!e.isTrigger) {
                        alert('Standard-HTML konnte nicht geladen werden.');
                    }
                }
            },
            error: function () {
                $btn.prop('disabled', false).html(origHtml);
                if (!e.isTrigger) {
                    alert('Verbindungsfehler beim Laden des Standard-Inhalts.');
                }
            }
        });
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
        $drawer.find('.crm-sub-input-content').val(defContent).attr('rows', 4);
        $sub.find('.crm-sub-custom-badge').hide();
        $drawer.find('.crm-standard-notice-text').text('Dynamischer Systemstandard. Klicken Sie auf den Button, um das Original-HTML in diesen Editor zu laden und frei anzupassen.');

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
    // MULTI-DOCUMENT PDF PREVIEW & TAB CONTROLLERS
    // ==========================================
    function updateActiveDocPreview(url, label, doc) {
        const $embed = jQuery('#x-sieben-pdf-preview embed');
        if ($embed.length && url) {
            const freshUrl = url + (url.indexOf('?') !== -1 ? '&' : '?') + 't=' + new Date().getTime();
            const $newEmbed = jQuery('<embed id="crm-active-pdf-embed" type="application/pdf" width="100%" height="680px" style="border: 1px solid #cbd5e1; border-radius: 8px; min-height: 650px; box-shadow: 0 4px 14px rgba(0,0,0,0.06); background:#f8fafc;" />');
            $newEmbed.attr('src', freshUrl);
            $embed.replaceWith($newEmbed);
        }
        const $downloadBtn = jQuery('#crm-preview-download-btn');
        if ($downloadBtn.length && url) {
            $downloadBtn.attr('href', url);
            $downloadBtn.find('.crm-btn-text').text((label || 'Dokument') + ' herunterladen');
        }
        const $externalBtn = jQuery('#crm-preview-external-btn');
        if ($externalBtn.length && url) {
            $externalBtn.attr('href', url).show();
        }
        const $emailBtn = jQuery('#x-sieben-button-row .x-sieben-email-btn');
        if ($emailBtn.length && url) {
            let emailPdf = url;
            const $kbTab = jQuery('.crm-preview-switch-embed[data-doc="kb"]');
            const kbUrl = $kbTab.length ? ($kbTab.data('url') || $kbTab.attr('data-url')) : '';
            if (doc === 'angebot' && kbUrl) {
                emailPdf = url + ',' + kbUrl;
            }
            $emailBtn.attr('data-pdf', emailPdf).data('pdf', emailPdf);

            // Dynamischer Mail-Kontext nach Dokumententyp
            let mailContext = 'xsieben_angebot';
            if (doc === 'kb') {
                mailContext = 'kurszeitenbestaetigung';
            } else if (doc === 'tb') {
                mailContext = 'teilnahmebestaetigung';
            } else if (doc === 'diplom') {
                mailContext = 'xsieben_diplom';
            }
            $emailBtn.attr('data-context', mailContext).data('context', mailContext);
        }

        // Diplom Erfolgs-Auswahlbox ein-/ausblenden
        const $diplomBox = jQuery('#crm-diplom-success-container');
        if ($diplomBox.length) {
            if (doc === 'diplom') {
                $diplomBox.slideDown(200);
            } else {
                $diplomBox.slideUp(200);
            }
        }

        // Switch dual section manager pane if available
        if (doc) {
            let secTarget = 'crm-dual-sec-angebot';
            if (doc === 'kb') secTarget = 'crm-dual-sec-kb';
            else if (doc === 'tb') secTarget = 'crm-dual-sec-tb';
            else if (doc === 'diplom') secTarget = 'crm-dual-sec-diplom';

            const $secBtn = jQuery('.crm-dual-sec-tab-btn[data-target="' + secTarget + '"]');
            if ($secBtn.length && !$secBtn.hasClass('active')) {
                jQuery('.crm-dual-sec-tab-btn').removeClass('active').css({ borderColor: '', color: '', fontWeight: 'normal' });
                $secBtn.addClass('active').css({ borderColor: '#7c3aed', color: '#6d28d9', fontWeight: '600' });
                jQuery('.crm-dual-sec-pane').hide();
                jQuery('#' + secTarget).show();
            }
        }
    }

    jQuery(document).on('click', '.crm-preview-switch-embed', function (e) {
        e.preventDefault();
        const $btn = jQuery(this);
        const url = $btn.data('url') || $btn.attr('data-url');
        const doc = $btn.data('doc');
        const variant = $btn.data('variant') || '';
        const label = $btn.data('label') || $btn.text().trim();
        const entryId = $btn.data('entry-id') || (typeof activeEntryId !== 'undefined' ? activeEntryId : (window.activeEntryId || 0));
        const courseId = $btn.data('course-id') || 0;

        // Visual active state
        jQuery('.crm-preview-switch-embed').removeClass('active').css({ borderColor: '#cbd5e1', color: '#334155', background: '#ffffff', fontWeight: 'normal' });
        $btn.addClass('active').css({ borderColor: '#7c3aed', color: '#6d28d9', background: '#faf5ff', fontWeight: '700' });

        if (url) {
            updateActiveDocPreview(url, label, doc);
        } else {
            // PDF noch nicht generiert -> AJAX Simulation & Vorschau laden
            const $overlay = jQuery('#crm-embed-loading-overlay');
            if ($overlay.length) $overlay.css('display', 'flex');

            const postAjaxUrl = (typeof crmData !== 'undefined' && crmData.ajaxUrl) ? crmData.ajaxUrl : ((typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php');
            const postNonce   = (typeof crmData !== 'undefined' && crmData.nonce) ? crmData.nonce : '';

            jQuery.ajax({
                url: postAjaxUrl,
                method: 'POST',
                data: {
                    action: 'crm_simulate_pdf',
                    security: postNonce,
                    entry_id: entryId,
                    course_id: courseId,
                    doc_type: (variant === 'mit_zertifikat' || variant === 'angebot_3' || doc === 'angebot_zert' || doc === 'angebot_3') ? 'angebot' : doc,
                    variant: variant
                },
                success: function (res) {
                    if (res.success && res.data && res.data.pdf_url) {
                        $btn.data('url', res.data.pdf_url).attr('data-url', res.data.pdf_url);
                        const $badge = $btn.find('.crm-tab-status-badge');
                        if ($badge.length) {
                            $badge.removeClass('crm-status-ondemand').addClass('crm-status-ready').html('✓ Bereit');
                        }
                        updateActiveDocPreview(res.data.pdf_url, label, doc);
                    } else {
                        alert('Dokument konnte nicht simuliert werden: ' + ((res.data && res.data.message) ? res.data.message : 'Fehler'));
                    }
                },
                error: function (xhr, status, err) {
                    alert('Netzwerkfehler bei der PDF-Simulation: ' + (err || status));
                },
                complete: function () {
                    if ($overlay.length) $overlay.hide();
                }
            });
        }
    });

    jQuery(document).on('click', '.crm-dual-sec-tab-btn', function (e) {
        e.preventDefault();
        jQuery('.crm-dual-sec-tab-btn').removeClass('active').css({ borderColor: '', color: '', fontWeight: 'normal' });
        jQuery(this).addClass('active').css({ borderColor: '#7c3aed', color: '#6d28d9', fontWeight: '600' });
        const target = jQuery(this).data('target');
        jQuery('.crm-dual-sec-pane').hide();
        jQuery('#' + target).show();

        let docType = 'angebot';
        if (target === 'crm-dual-sec-kb') docType = 'kb';
        else if (target === 'crm-dual-sec-tb') docType = 'tb';
        else if (target === 'crm-dual-sec-diplom') docType = 'diplom';

        const $switchBtn = jQuery('.crm-preview-switch-embed[data-doc="' + docType + '"]');
        if ($switchBtn.length && !$switchBtn.hasClass('active')) {
            $switchBtn.trigger('click');
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

        const $activeTab = jQuery('.crm-preview-switch-embed.active');
        const activeVariant = $activeTab.data('variant') || '';

        jQuery.ajax({
            url: (typeof crmData !== 'undefined' && crmData.ajaxUrl) ? crmData.ajaxUrl : ((typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php'),
            type: 'POST',
            data: {
                action: 'crm_save_pdf_section_order',
                nonce: (typeof crmData !== 'undefined' && crmData.nonce) ? crmData.nonce : '',
                doc_type: docType,
                variant: activeVariant,
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
                        const $docTab = activeVariant
                            ? jQuery('.crm-preview-switch-embed[data-doc="' + respDoc + '"][data-variant="' + activeVariant + '"]')
                            : jQuery('.crm-preview-switch-embed[data-doc="' + respDoc + '"]');
                        if ($docTab.length) {
                            $docTab.data('url', res.data.pdf_url).attr('data-url', res.data.pdf_url);
                        }

                        // 2. Update embed if active doc matches or single mode
                        const $activeTab = jQuery('.crm-preview-switch-embed.active');
                        const isDocActive = !$activeTab.length || ($activeTab.data('doc') === respDoc);
                        const $embed = jQuery('#x-sieben-pdf-preview embed');
                        if ($embed.length && isDocActive) {
                            const $newEmbed = jQuery('<embed id="crm-active-pdf-embed" type="application/pdf" width="100%" height="680px" style="border: 1px solid #cbd5e1; border-radius: 8px; min-height: 650px; box-shadow: 0 4px 14px rgba(0,0,0,0.06); background:#f8fafc;" />');
                            $newEmbed.attr('src', freshUrl);
                            $embed.replaceWith($newEmbed);
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

    // =========================================================================
    // CRM KURS- & GESCHÄFTSANFRAGE VERKNÜPFUNGS-MODAL LOGIC
    // =========================================================================
    const $linkModal = jQuery('#crm-link-modal');
    const $linkForm = jQuery('#crm-link-form');
    const $linkEntryId = jQuery('#crm-link-entry-id');
    const $linkInquiryType = jQuery('#crm-link-inquiry-type');
    const $linkCourseSelect = jQuery('#crm-link-course-select');
    const $courseSearchInput = jQuery('#crm-course-search-input');
    const $coursePreviewBox = jQuery('#crm-selected-course-preview');
    const $businessTitle = jQuery('#crm-business-title');
    const $businessStart = jQuery('#crm-business-start-date');
    const $businessEnd = jQuery('#crm-business-end-date');
    const $linkNote = jQuery('#crm-link-note');
    const $saveLinkBtn = jQuery('#crm-save-link-btn');

    // 1. Modal öffnen bei Klick auf .crm-link-course-btn
    jQuery(document).on('click', '.crm-link-course-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const $btn = jQuery(this);
        const entryId = $btn.data('entry-id') || $btn.closest('tr').data('entry-id');
        if (!entryId) return;

        const $row = jQuery('tr.crm-entry-row[data-entry-id="' + entryId + '"]');
        const courseId = $btn.data('course-id') !== undefined ? $btn.data('course-id') : ($row.data('course-id') || 0);
        const inquiryType = $btn.data('inquiry-type') || $row.data('inquiry-type') || 'course';
        const customTitle = $btn.data('custom-title') || $row.data('custom-title') || '';
        const clientName = $btn.data('client-name') || $row.data('client-name') || ('Eintrag #' + entryId);

        // Header Title aktualisieren
        jQuery('#crm-link-modal-title').text('Anfrage #' + entryId + ' verknüpfen (' + clientName + ')');

        // Formular-Felder initialisieren
        $linkEntryId.val(entryId);
        $linkInquiryType.val(inquiryType);
        $linkNote.val('');
        $courseSearchInput.val('');

        // Reset & Filter Courses Select
        $linkCourseSelect.find('option').show();
        if (courseId && parseInt(courseId, 10) > 0) {
            $linkCourseSelect.val(courseId);
            updateCoursePreview(courseId);
        } else {
            $linkCourseSelect.val('');
            $coursePreviewBox.hide();
        }

        // Freie Anfrage Felder
        $businessTitle.val(customTitle || '');
        if (inquiryType === 'freie_anfrage') {
            switchLinkTab('business');
        } else {
            switchLinkTab('course');
        }

        // Modal anzeigen
        $linkModal.fadeIn(150).css('display', 'flex');
    });

    // 2. Tab-Umschaltung
    function switchLinkTab(tab) {
        jQuery('.crm-link-tab-btn').removeClass('is-active').css({
            'border-bottom-color': 'transparent',
            'color': '#64748b'
        });
        jQuery('.crm-link-tab-panel').hide();

        if (tab === 'business') {
            jQuery('.crm-link-tab-btn[data-tab="business"]').addClass('is-active').css({
                'border-bottom-color': '#d97706',
                'color': '#d97706'
            });
            jQuery('#crm-link-panel-business').show();
            $linkInquiryType.val('freie_anfrage');
            if (!$businessTitle.val()) {
                setTimeout(function () { $businessTitle.focus(); }, 100);
            }
        } else {
            jQuery('.crm-link-tab-btn[data-tab="course"]').addClass('is-active').css({
                'border-bottom-color': '#0284c7',
                'color': '#0284c7'
            });
            jQuery('#crm-link-panel-course').show();
            $linkInquiryType.val('course');
            setTimeout(function () { $courseSearchInput.focus(); }, 100);
        }
    }

    jQuery(document).on('click', '.crm-link-tab-btn', function (e) {
        e.preventDefault();
        const tab = jQuery(this).data('tab');
        switchLinkTab(tab);
    });

    // 3. Live-Suche im Kurs-Katalog
    $courseSearchInput.on('input', function () {
        const query = jQuery(this).val().toLowerCase().trim();
        $linkCourseSelect.find('option').each(function () {
            if (!jQuery(this).val()) return; // Skip placeholder
            const text = jQuery(this).text().toLowerCase();
            if (!query || text.indexOf(query) !== -1) {
                jQuery(this).show();
            } else {
                jQuery(this).hide();
            }
        });
    });

    // 4. Kursauswahl Vorschau
    function updateCoursePreview(cId) {
        const $opt = $linkCourseSelect.find('option[value="' + cId + '"]');
        if ($opt.length && cId) {
            jQuery('#crm-preview-course-title').text($opt.data('title') || $opt.text());
            jQuery('#crm-preview-course-dates').text($opt.data('dates') || 'Termine n. V.');
            jQuery('#crm-preview-course-kosten').text($opt.data('kosten') ? ($opt.data('kosten') + ' €') : 'Preis n. V.');
            jQuery('#crm-preview-course-typ').text($opt.data('kurstyp') || 'Lehrgang');
            $coursePreviewBox.slideDown(120);
        } else {
            $coursePreviewBox.hide();
        }
    }

    $linkCourseSelect.on('change', function () {
        const selectedId = jQuery(this).val();
        updateCoursePreview(selectedId);
    });

    // 5. Modal schließen
    function closeLinkModal() {
        $linkModal.fadeOut(150);
    }

    jQuery(document).on('click', '.crm-close-link-modal', function (e) {
        e.preventDefault();
        closeLinkModal();
    });

    $linkModal.on('click', function (e) {
        if (e.target === this) {
            closeLinkModal();
        }
    });

    jQuery(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $linkModal.is(':visible')) {
            closeLinkModal();
        }
    });

    // 6. Formular absenden via AJAX
    $linkForm.on('submit', function (e) {
        e.preventDefault();

        const entryId = parseInt($linkEntryId.val(), 10);
        const inquiryType = $linkInquiryType.val();
        const courseId = parseInt($linkCourseSelect.val(), 10) || 0;
        const customTitle = jQuery.trim($businessTitle.val());

        if (!entryId) {
            alert('Keine gültige Eintrags-ID gefunden.');
            return;
        }

        if (inquiryType === 'course' && !courseId) {
            alert('Bitte wählen Sie einen Kurs aus der Liste aus.');
            $linkCourseSelect.focus();
            return;
        }

        if (inquiryType === 'freie_anfrage' && !customTitle) {
            alert('Bitte geben Sie ein Thema / eine Bezeichnung für die Geschäftsanfrage ein.');
            $businessTitle.focus();
            return;
        }

        const originalBtnHtml = $saveLinkBtn.html();
        $saveLinkBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Speichern...');

        const ajaxEndpoint = (typeof crmData !== 'undefined' && crmData.ajaxUrl) ? crmData.ajaxUrl : ((typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php');
        const nonceVal = (typeof crmData !== 'undefined' && crmData.nonce) ? crmData.nonce : $linkForm.find('input[name="nonce"]').val();

        const formData = {
            action: 'crm_link_entry',
            nonce: nonceVal,
            entry_id: entryId,
            inquiry_type: inquiryType,
            course_id: courseId,
            custom_title: customTitle,
            start_date: (inquiryType === 'freie_anfrage') ? $businessStart.val() : '',
            end_date: (inquiryType === 'freie_anfrage') ? $businessEnd.val() : '',
            note: $linkNote.val()
        };

        jQuery.ajax({
            url: ajaxEndpoint,
            type: 'POST',
            data: formData,
            success: function (res) {
                $saveLinkBtn.prop('disabled', false).html(originalBtnHtml);
                if (res.success) {
                    closeLinkModal();

                    // Tabellenzeile live aktualisieren
                    const $row = jQuery('tr.crm-entry-row[data-entry-id="' + entryId + '"]');
                    if ($row.length) {
                        $row.attr('data-course-id', res.data.course_id || 0);
                        $row.attr('data-inquiry-type', res.data.inquiry_type || 'course');
                        $row.attr('data-custom-title', res.data.custom_title || '');
                        $row.attr('data-course-title', res.data.course_title || '');

                        // Kurs-Zelle mit neuem Widget befüllen
                        if (res.data.widget_html) {
                            $row.find('.crm-course-cell').html(res.data.widget_html);
                        }

                        // Namens-Link und Row-Actions data-course-id aktualisieren
                        $row.find('.crm-direct-editor-btn, .crm-link-course-btn').attr('data-course-id', res.data.course_id || 0);
                        $row.find('.crm-link-course-btn').attr('data-inquiry-type', res.data.inquiry_type || 'course');
                        $row.find('.crm-link-course-btn').attr('data-custom-title', res.data.custom_title || '');

                        // Visueller Erfolgs-Flash
                        $row.find('.crm-course-cell').css('background-color', '#ecfdf5')
                            .delay(200)
                            .animate({ backgroundColor: 'transparent' }, 1200);
                    }

                    // Screen 2 Spickzettel aktualisieren falls geladen
                    if (res.data.spickzettel_html) {
                        const $spickzettelBox = jQuery('#crm-entry-details-container .crm-spickzettel-box');
                        if ($spickzettelBox.length) {
                            $spickzettelBox.replaceWith(res.data.spickzettel_html);
                        }
                    }

                    // Notice anzeigen
                    const $noticeContainer = jQuery('#crm-ajax-notice-container');
                    if ($noticeContainer.length) {
                        $noticeContainer.html(
                            '<div class="notice notice-success is-dismissible crm-action-notice" style="margin:12px 0 16px 0; padding:10px 14px; font-weight:600; display:flex; align-items:center; justify-content:space-between;">' +
                            '<span>' + (res.data.message || 'Verknüpfung erfolgreich gespeichert.') + '</span>' +
                            '<button type="button" class="notice-dismiss crm-notice-close-btn"><span class="screen-reader-text">Diese Meldung ausblenden.</span></button>' +
                            '</div>'
                        );
                    }
                } else {
                    alert(res.data && res.data.message ? res.data.message : 'Fehler beim Speichern der Verknüpfung.');
                }
            },
            error: function (xhr, status, error) {
                $saveLinkBtn.prop('disabled', false).html(originalBtnHtml);
                alert('Netzwerk- oder Serverfehler: ' + error);
            }
        });
    });

    // =========================================================================
    // Interaktive Zertifizierungsauswahl & Mehrfachauswahl im Kurs-Widget
    // =========================================================================
    function syncEntryCertificationsAcrossViews(entryId, courseId, data) {
        if (!entryId || !data) return;

        // 1. Alle .crm-course-certs-row für diesen Eintrag aktualisieren (Cards & Tabelle)
        const $certRows = jQuery('.crm-course-certs-row[data-entry-id="' + entryId + '"]');
        if ($certRows.length && data.widget_html) {
            $certRows.each(function () {
                jQuery(this).replaceWith(data.widget_html);
            });
        }

        // 2. Spickzettel / Dossier auf Screen 2 aktualisieren
        const $dossierBoxes = jQuery('.crm-spickzettel-certs-box[data-entry-id="' + entryId + '"]');
        if ($dossierBoxes.length && data.dossier_html) {
            $dossierBoxes.each(function () {
                jQuery(this).replaceWith(data.dossier_html);
            });
        }

        // 3. Kanban & Split-View Kompakt-Badges aktualisieren
        const $compactRows = jQuery('.crm-kanban-certs-row[data-entry-id="' + entryId + '"], .crm-split-item-certs-row[data-entry-id="' + entryId + '"]');
        if ($compactRows.length && data.compact_html) {
            $compactRows.each(function () {
                jQuery(this).replaceWith(data.compact_html);
            });
        }

        // 4. Checkboxen im Kunden-Bearbeitungsmodal synchronisieren (falls geöffnet)
        const $modalForm = jQuery('.crm-universal-customer-form[data-entry-id="' + entryId + '"]');
        if ($modalForm.length && data.selected_cert_names) {
            $modalForm.find('input[name="field_zertifizierungen[]"]').each(function () {
                const val = (jQuery(this).val() || '').toLowerCase();
                let isChecked = false;
                for (let i = 0; i < data.selected_cert_names.length; i++) {
                    const selName = (data.selected_cert_names[i] || '').toLowerCase();
                    if (val === selName) {
                        isChecked = true;
                        break;
                    }
                    const valHasPsm = val.indexOf('psm') !== -1;
                    const valHasPspo = val.indexOf('pspo') !== -1;
                    const selHasPsm = selName.indexOf('psm') !== -1;
                    const selHasPspo = selName.indexOf('pspo') !== -1;
                    if ((valHasPsm || valHasPspo) && (selHasPsm || selHasPspo)) {
                        if (valHasPsm && valHasPspo && selHasPsm && selHasPspo) { isChecked = true; break; }
                        if (valHasPsm && !valHasPspo && selHasPsm && !selHasPspo) { isChecked = true; break; }
                        if (!valHasPsm && valHasPspo && !selHasPsm && selHasPspo) { isChecked = true; break; }
                        continue;
                    }
                    if (val.indexOf(selName) !== -1 || selName.indexOf(val) !== -1) {
                        isChecked = true;
                        break;
                    }
                }
                jQuery(this).prop('checked', isChecked);
            });
        }

        // 5. Screen 2 Vorschau-Tabs für Angebot 2 aktualisieren
        const $angebot2Tab = jQuery('.crm-preview-switch-embed[data-variant="mit_zertifikat"][data-entry-id="' + entryId + '"]');
        if ($angebot2Tab.length) {
            if (data.has_cert_option) {
                $angebot2Tab.show();
                if (data.offer_zert_url) {
                    $angebot2Tab.attr('data-url', data.offer_zert_url);
                    // Falls Angebot 2 aktuell aktiv ist: Iframe neu laden
                    if ($angebot2Tab.hasClass('active')) {
                        const $embed = jQuery('#x-sieben-pdf-preview embed');
                        if ($embed.length) {
                            $embed.attr('src', data.offer_zert_url + (data.offer_zert_url.indexOf('?') !== -1 ? '&' : '?') + 't=' + Date.now());
                        }
                    }
                }
            } else {
                // Keine Zertifizierungen mehr ausgewählt -> Tab ausblenden
                $angebot2Tab.hide();
                if ($angebot2Tab.hasClass('active')) {
                    // Zurück zu Angebot 1 (Basis) schalten
                    const $basisTab = jQuery('.crm-preview-switch-embed[data-variant="basis"][data-entry-id="' + entryId + '"]');
                    if ($basisTab.length) {
                        $basisTab.trigger('click');
                    }
                }
            }
        }

        // 6. Lead-Wizard aktualisieren (falls Datenobjekt aktiv)
        if (typeof currentWizardData !== 'undefined' && currentWizardData && currentWizardData.entry_id == entryId) {
            currentWizardData.has_cert_option = Boolean(data.has_cert_option);
            currentWizardData.cert_name = (data.selected_cert_names && data.selected_cert_names.length)
                ? data.selected_cert_names.join(', ')
                : '';
            if (typeof crmRefreshWizardEmailPreview === 'function') {
                crmRefreshWizardEmailPreview(currentWizardData);
            }
        }
    }
    window.syncEntryCertificationsAcrossViews = syncEntryCertificationsAcrossViews;

    jQuery(document).on('click', '.crm-cert-toggle-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const $btn = jQuery(this);
        if ($btn.hasClass('is-toggling')) return;

        const entryId = parseInt($btn.attr('data-entry-id'), 10);
        const courseId = parseInt($btn.attr('data-course-id'), 10) || 0;
        if (!entryId) return;

        const isCurrentlySelected = $btn.attr('data-selected') === '1';
        const nextSelected = !isCurrentlySelected;

        // Container ermitteln (Widget, Zeile oder Spickzettel-Box)
        const $container = $btn.closest('.crm-course-certs-row, .crm-spickzettel-certs-box, .crm-course-widget, .crm-kanban-certs-row');

        // Optimistischer UI-Zustand für diesen Button
        $btn.attr('data-selected', nextSelected ? '1' : '0');
        $btn.attr('aria-pressed', nextSelected ? 'true' : 'false');
        if (nextSelected) {
            $btn.addClass('crm-cert-selected');
            $btn.html($btn.html().replace(/🏅\s*/g, '✓ '));
            const curTitle = $btn.attr('title') || '';
            $btn.attr('title', curTitle.replace('zum Angebot hinzufügen. Klicken zum Auswählen', 'ist für das Angebot ausgewählt. Klicken zum Abwählen'));

            // Bei Auswahl: Gegenseitige Ausschlüsse auflösen (z.B. Scrum Kombi vs. PSM/PSPO Einzelfach, IPMA Level B vs C vs D)
            const certNameLower = ($btn.attr('data-cert-name') || '').toLowerCase();
            const isScrum = certNameLower.indexOf('scrum') !== -1 || certNameLower.indexOf('psm') !== -1 || certNameLower.indexOf('pspo') !== -1;
            const isIpma = certNameLower.indexOf('ipma') !== -1 || certNameLower.indexOf('pma') !== -1;
            const isKombi = isScrum && (certNameLower.indexOf('psm') !== -1 && certNameLower.indexOf('pspo') !== -1);

            if (isScrum || isIpma) {
                $container.find('.crm-cert-toggle-btn').not($btn).each(function () {
                    const otherName = (jQuery(this).attr('data-cert-name') || '').toLowerCase();
                    let shouldDeselect = false;
                    if (isScrum) {
                        const otherIsScrum = otherName.indexOf('scrum') !== -1 || otherName.indexOf('psm') !== -1 || otherName.indexOf('pspo') !== -1;
                        if (otherIsScrum) {
                            const otherIsKombi = (otherName.indexOf('psm') !== -1 && otherName.indexOf('pspo') !== -1);
                            if (isKombi || otherIsKombi) {
                                shouldDeselect = true;
                            }
                        }
                    }
                    if (isIpma) {
                        const otherIsIpma = otherName.indexOf('ipma') !== -1 || otherName.indexOf('pma') !== -1;
                        if (otherIsIpma) {
                            shouldDeselect = true;
                        }
                    }
                    if (shouldDeselect) {
                        jQuery(this).attr('data-selected', '0');
                        jQuery(this).attr('aria-pressed', 'false');
                        jQuery(this).removeClass('crm-cert-selected');
                        jQuery(this).html(jQuery(this).html().replace(/✓\s*/g, '🏅 '));
                        const oTitle = jQuery(this).attr('title') || '';
                        jQuery(this).attr('title', oTitle.replace('ist für das Angebot ausgewählt. Klicken zum Abwählen', 'zum Angebot hinzufügen. Klicken zum Auswählen'));
                    }
                });
            }
        } else {
            $btn.removeClass('crm-cert-selected');
            $btn.html($btn.html().replace(/✓\s*/g, '🏅 '));
            const curTitle = $btn.attr('title') || '';
            $btn.attr('title', curTitle.replace('ist für das Angebot ausgewählt. Klicken zum Abwählen', 'zum Angebot hinzufügen. Klicken zum Auswählen'));
        }

        // Alle aktuell angewählten Zertifizierungen im Container sammeln
        const selectedCerts = [];
        $container.find('.crm-cert-toggle-btn').each(function () {
            if (jQuery(this).attr('data-selected') === '1') {
                selectedCerts.push({
                    name: jQuery(this).attr('data-cert-name'),
                    price: jQuery(this).attr('data-cert-price'),
                    ust: jQuery(this).attr('data-cert-ust')
                });
            }
        });

        $btn.addClass('is-toggling');

        jQuery.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'crm_update_entry_certifications',
                nonce: nonce,
                entry_id: entryId,
                course_id: courseId,
                selected_certs: selectedCerts
            },
            success: function (res) {
                $btn.removeClass('is-toggling');
                if (res && res.success) {
                    syncEntryCertificationsAcrossViews(entryId, courseId, res.data);

                    // Dezenten Feedback-Toast anzeigen
                    const $toast = jQuery('<div class="crm-cert-toast" style="position:fixed; bottom:24px; right:24px; background:#0f172a; color:#fff; padding:10px 18px; border-radius:8px; font-size:13px; font-weight:600; box-shadow:0 8px 24px rgba(0,0,0,0.18); z-index:999999; display:flex; align-items:center; gap:8px; animation:fadeIn 0.2s ease;">' +
                        '<span class="dashicons dashicons-yes-alt" style="color:#10b981; font-size:18px; width:18px; height:18px;"></span> ' +
                        crmEscapeHtml(res.data.message || 'Zertifizierungen aktualisiert') +
                        '</div>');
                    jQuery('body').append($toast);
                    setTimeout(function () {
                        $toast.fadeOut(300, function () { jQuery(this).remove(); });
                    }, 3200);
                } else {
                    // Rollback bei Fehler
                    $btn.attr('data-selected', isCurrentlySelected ? '1' : '0');
                    $btn.attr('aria-pressed', isCurrentlySelected ? 'true' : 'false');
                    if (isCurrentlySelected) {
                        $btn.addClass('crm-cert-selected');
                        $btn.html($btn.html().replace(/🏅\s*/g, '✓ '));
                    } else {
                        $btn.removeClass('crm-cert-selected');
                        $btn.html($btn.html().replace(/✓\s*/g, '🏅 '));
                    }
                    alert(res && res.data && res.data.message ? res.data.message : 'Fehler beim Speichern der Zertifizierungsauswahl.');
                }
            },
            error: function (xhr, status, err) {
                $btn.removeClass('is-toggling');
                // Rollback bei Netzwerkfehler
                $btn.attr('data-selected', isCurrentlySelected ? '1' : '0');
                $btn.attr('aria-pressed', isCurrentlySelected ? 'true' : 'false');
                if (isCurrentlySelected) {
                    $btn.addClass('crm-cert-selected');
                    $btn.html($btn.html().replace(/🏅\s*/g, '✓ '));
                } else {
                    $btn.removeClass('crm-cert-selected');
                    $btn.html($btn.html().replace(/✓\s*/g, '🏅 '));
                }
                alert('Netzwerk- oder Serverfehler: ' + err);
            }
        });
    });

});


