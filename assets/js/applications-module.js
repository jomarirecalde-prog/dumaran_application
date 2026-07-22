/**
 * WPU Applications Module — Enterprise ATS Interactions
 */
(function () {
    'use strict';

    const STORAGE_THEME = 'wpu-admin-theme';
    const STORAGE_RECENT = 'wpu-ats-recent-searches';
    const cfg = window.ATS_CONFIG || {};

    let pendingArchiveFormId = null;
    let pendingStatusUpdate = null;

    /* ── Utilities ── */
    function $(sel, ctx) { return (ctx || document).querySelector(sel); }
    function $$(sel, ctx) { return Array.from((ctx || document).querySelectorAll(sel)); }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getMatchClass(score) {
        if (score >= 80) return 'high';
        if (score >= 50) return 'mid';
        return 'low';
    }

    function getMatchColor(score) {
        if (score >= 80) return '#16A34A';
        if (score >= 50) return '#F59E0B';
        return '#DC2626';
    }

    /* ── Theme ── */
    function getPreferredTheme() {
        const stored = localStorage.getItem(STORAGE_THEME);
        if (stored) return stored;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        document.body.setAttribute('data-theme', theme);
        localStorage.setItem(STORAGE_THEME, theme);
        const icon = $('#themeToggleIcon');
        if (icon) icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }

    function initTheme() {
        applyTheme(getPreferredTheme());
        const btn = $('#themeToggle');
        if (btn) {
            btn.addEventListener('click', () => {
                const next = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                applyTheme(next);
            });
        }
    }

    /* ── Search palette (Ctrl+K) ── */
    function initSearchPalette() {
        const palette = $('#atsSearchPalette');
        const paletteInput = $('#atsSearchPaletteInput');
        const mainSearch = $('#searchQuery');
        if (!palette || !mainSearch) return;

        function openPalette() {
            palette.classList.add('open');
            paletteInput.value = mainSearch.value;
            paletteInput.focus();
            document.body.classList.add('modal-open');
        }

        function closePalette() {
            palette.classList.remove('open');
            document.body.classList.remove('modal-open');
        }

        document.addEventListener('keydown', e => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                openPalette();
            }
            if (e.key === 'Escape' && palette.classList.contains('open')) {
                closePalette();
            }
        });

        $$('[data-open-search]').forEach(btn => btn.addEventListener('click', openPalette));
        palette.addEventListener('click', e => { if (e.target === palette) closePalette(); });

        paletteInput.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                mainSearch.value = paletteInput.value;
                saveRecentSearch(paletteInput.value);
                mainSearch.closest('form').submit();
            }
        });

        renderRecentSearches();
    }

    function saveRecentSearch(q) {
        if (!q.trim()) return;
        let recent = JSON.parse(localStorage.getItem(STORAGE_RECENT) || '[]');
        recent = [q, ...recent.filter(r => r !== q)].slice(0, 5);
        localStorage.setItem(STORAGE_RECENT, JSON.stringify(recent));
    }

    function renderRecentSearches() {
        const container = $('#atsRecentSearches');
        if (!container) return;
        const recent = JSON.parse(localStorage.getItem(STORAGE_RECENT) || '[]');
        if (!recent.length) {
            container.innerHTML = '<p style="color:var(--ats-text-muted);font-size:0.875rem;margin:0;">No recent searches</p>';
            return;
        }
        container.innerHTML = recent.map(q =>
            `<a href="applications.php?q=${encodeURIComponent(q)}" class="ats-search-palette__recent-item"><i class="fas fa-clock" style="margin-right:8px;opacity:0.5"></i>${escapeHtml(q)}</a>`
        ).join('');
    }

    /* ── Debounced highlight search ── */
    function initLiveHighlight() {
        const input = $('#searchQuery');
        if (!input) return;
        let timer;
        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => highlightMatches(input.value.trim()), 250);
        });
        if (input.value.trim()) highlightMatches(input.value.trim());
    }

    function highlightMatches(query) {
        $$('.ats-applicant-card__name, .ats-applicant-card__position, .ats-applicant-card__summary').forEach(el => {
            const original = el.dataset.original || el.textContent;
            el.dataset.original = original;
            if (!query) { el.textContent = original; return; }
            const re = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
            el.innerHTML = escapeHtml(original).replace(re, '<mark class="ats-highlight">$1</mark>');
        });
    }

    /* ── Client-side sort ── */
    function initClientSort() {
        const sortSelect = $('#atsClientSort');
        const grid = $('#atsCardsGrid');
        if (!sortSelect || !grid) return;

        sortSelect.addEventListener('change', () => {
            const cards = $$('.ats-applicant-card', grid);
            const mode = sortSelect.value;
            cards.sort((a, b) => {
                switch (mode) {
                    case 'oldest': return (a.dataset.appliedTs || 0) - (b.dataset.appliedTs || 0);
                    case 'experience': return (b.dataset.experience || 0) - (a.dataset.experience || 0);
                    case 'qualified': return (b.dataset.matchScore || 0) - (a.dataset.matchScore || 0);
                    case 'updated': return (b.dataset.updatedTs || 0) - (a.dataset.updatedTs || 0);
                    default: return (b.dataset.appliedTs || 0) - (a.dataset.appliedTs || 0);
                }
            });
            cards.forEach(c => grid.appendChild(c));
        });
    }

    /* ── Bulk selection ── */
    function initBulkSelection() {
        const bulkBar = $('#atsBulkBar');
        const selectAll = $('#atsSelectAll');
        if (!bulkBar) return;

        function updateBulkBar() {
            const checked = $$('.ats-applicant-card__checkbox:checked');
            bulkBar.classList.toggle('visible', checked.length > 0);
            const countEl = $('#atsBulkCount');
            if (countEl) countEl.textContent = checked.length;
        }

        $$('.ats-applicant-card__checkbox').forEach(cb => {
            cb.addEventListener('change', () => {
                cb.closest('.ats-applicant-card').classList.toggle('selected', cb.checked);
                updateBulkBar();
            });
        });

        if (selectAll) {
            selectAll.addEventListener('change', () => {
                $$('.ats-applicant-card__checkbox').forEach(cb => {
                    cb.checked = selectAll.checked;
                    cb.closest('.ats-applicant-card').classList.toggle('selected', selectAll.checked);
                });
                updateBulkBar();
            });
        }

        $('#atsBulkEmail')?.addEventListener('click', () => {
            const emails = $$('.ats-applicant-card__checkbox:checked')
                .map(cb => cb.dataset.email).filter(Boolean);
            if (emails.length) window.location.href = 'mailto:' + emails.join(',');
        });

        $('#atsBulkExport')?.addEventListener('click', exportSelectedCSV);
        $('#atsBulkCompare')?.addEventListener('click', openComparePanel);
        $('#atsBulkClear')?.addEventListener('click', () => {
            $$('.ats-applicant-card__checkbox').forEach(cb => {
                cb.checked = false;
                cb.closest('.ats-applicant-card').classList.remove('selected');
            });
            if (selectAll) selectAll.checked = false;
            updateBulkBar();
        });
    }

    function exportSelectedCSV() {
        const checked = $$('.ats-applicant-card__checkbox:checked');
        const rows = [['Name', 'Email', 'Position', 'Status', 'Experience', 'Match Score', 'Applied']];
        checked.forEach(cb => {
            const card = cb.closest('.ats-applicant-card');
            rows.push([
                card.dataset.name || '',
                card.dataset.email || '',
                card.dataset.position || '',
                card.dataset.status || '',
                card.dataset.experience || '0',
                card.dataset.matchScore || '0',
                card.dataset.applied || ''
            ]);
        });
        if (rows.length <= 1) {
            if (typeof showWarning === 'function') showWarning('Select applicants to export.', 3000, 'Export');
            return;
        }
        const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'applications-export.csv';
        a.click();
        if (typeof showSuccess === 'function') showSuccess('Export downloaded.', 3000, 'Export');
    }

    /* ── Comparison panel ── */
    function openComparePanel() {
        const checked = $$('.ats-applicant-card__checkbox:checked');
        const panel = $('#atsComparePanel');
        const body = $('#atsCompareBody');
        if (!panel || !body || checked.length < 2) {
            if (typeof showWarning === 'function') showWarning('Select at least 2 applicants to compare.', 3500, 'Compare');
            return;
        }
        const headers = checked.map(cb => cb.dataset.name || 'Applicant');
        const fields = [
            ['Position', 'position'],
            ['Experience', 'experience'],
            ['Match Score', 'matchScore'],
            ['Status', 'status'],
            ['Documents', 'documents'],
            ['Applied', 'applied']
        ];
        let html = '<table class="ats-compare-table"><thead><tr><th>Criteria</th>';
        headers.forEach(h => { html += `<th>${escapeHtml(h)}</th>`; });
        html += '</tr></thead><tbody>';
        fields.forEach(([label, key]) => {
            html += `<tr><th>${escapeHtml(label)}</th>`;
            checked.forEach(cb => {
                const card = cb.closest('.ats-applicant-card');
                html += `<td>${escapeHtml(card.dataset[key] || '—')}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody></table>';
        body.innerHTML = html;
        panel.classList.add('open');
    }

    function initComparePanel() {
        $('#atsCompareClose')?.addEventListener('click', () => {
            $('#atsComparePanel')?.classList.remove('open');
        });
    }

    /* ── Status workflow buttons ── */
    function initStatusWorkflow() {
        $$('.ats-status-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const card = btn.closest('.ats-applicant-card');
                const select = card?.querySelector('.status-select');
                if (!select) return;
                const newStatus = btn.dataset.status;
                if (newStatus === select.value) return;
                select.value = newStatus;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    }

    function syncStatusButtons(select) {
        const card = select.closest('.ats-applicant-card');
        if (!card) return;
        $$('.ats-status-btn', card).forEach(btn => {
            const isActive = btn.dataset.status === select.value;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    /* ── Details panel toggle ── */
    function toggleDetails(button, panel, show) {
        panel.classList.toggle('open', show);
        panel.style.display = show ? 'block' : 'none';
        button.setAttribute('aria-expanded', show ? 'true' : 'false');
        button.innerHTML = show
            ? '<i class="fas fa-eye-slash"></i> Hide Details'
            : '<i class="fas fa-folder-open"></i> Documents';
    }

    function initializeApplicationDetails() {
        $$('[data-action="toggle-details"]').forEach(button => {
            const targetId = button.getAttribute('data-target');
            const panel = document.getElementById(targetId);
            if (!panel) return;

            panel.querySelectorAll('[data-action="close-details"]').forEach(closeBtn => {
                closeBtn.addEventListener('click', () => toggleDetails(button, panel, false));
            });

            button.addEventListener('click', () => {
                const isOpen = panel.classList.contains('open');
                toggleDetails(button, panel, !isOpen);
            });
        });
    }

    /* ── Document view toggle ── */
    function initDocViewToggle() {
        document.addEventListener('click', e => {
            const btn = e.target.closest('[data-doc-view]');
            if (!btn) return;
            const manager = btn.closest('.ats-doc-manager');
            if (!manager) return;
            const view = btn.dataset.docView;
            $$('[data-doc-view]', manager).forEach(b => b.classList.toggle('active', b === btn));
            const grid = manager.querySelector('.ats-doc-grid');
            if (grid) grid.classList.toggle('list-view', view === 'list');
        });
    }

    /* ── Drawer ── */
    function openDrawer() {
        $('#atsDrawerOverlay')?.classList.add('open');
        $('#atsApplicantDrawer')?.classList.add('open');
        document.body.classList.add('drawer-open');
    }

    function closeDrawer() {
        $('#atsDrawerOverlay')?.classList.remove('open');
        $('#atsApplicantDrawer')?.classList.remove('open');
        document.body.classList.remove('drawer-open');
    }

    function initDrawerTabs() {
        $$('.ats-drawer__tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const drawer = tab.closest('.ats-drawer');
                $$('.ats-drawer__tab', drawer).forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                const target = tab.dataset.tab;
                $$('[data-drawer-panel]', drawer).forEach(p => {
                    p.style.display = p.dataset.drawerPanel === target ? 'block' : 'none';
                });
            });
        });
    }

    /* ── Cover letter ── */
    window.viewCoverLetter = function (app) {
        $('#coverLetterApplicant').textContent = app.applicant_name;
        $('#coverLetterPosition').textContent = app.position_title;
        const dateEl = $('#coverLetterDate');
        if (dateEl && app.applied_at) {
            dateEl.textContent = new Date(app.applied_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        }
        $('#coverLetterText').innerHTML = escapeHtml(app.cover_letter || '').replace(/\n/g, '<br>');
        openModal('coverLetterModal');
    };

    window.closeCoverLetterModal = function () {
        closeModal('coverLetterModal');
    };

    window.printCoverLetter = function () {
        const content = $('#coverLetterPrintArea');
        if (!content) return;
        const w = window.open('', '_blank');
        w.document.write(`<html><head><title>Cover Letter</title><style>body{font-family:Georgia,serif;padding:40px;line-height:1.75;max-width:700px;margin:0 auto;}</style></head><body>${content.innerHTML}</body></html>`);
        w.document.close();
        w.print();
    };

    /* ── User profile / drawer ── */
    window.viewUserInformation = function (userId, appData) {
        const loading = $('#userInfoLoading');
        const content = $('#userInfoContent');
        const error = $('#userInfoError');
        const drawerTitle = $('#atsDrawerTitle');

        if (loading) loading.style.display = 'block';
        if (content) { content.style.display = 'none'; content.innerHTML = ''; }
        if (error) error.style.display = 'none';

        if (appData && drawerTitle) {
            drawerTitle.textContent = appData.applicant_name || 'Applicant Profile';
        }

        openDrawer();

        fetch(`applications.php?action=get_user_profile&user_id=${userId}`)
            .then(r => { if (!r.ok) throw new Error('Network error'); return r.json(); })
            .then(data => {
                if (loading) loading.style.display = 'none';
                if (data.success) {
                    const trainingCerts = Array.isArray(data.trainingCertificates) ? data.trainingCertificates : [];
                    const appDocs = Array.isArray(data.applicationDocuments) ? data.applicationDocuments : [];
                    const experiences = Array.isArray(data.experiences) ? data.experiences : [];
                    if (content) {
                        content.innerHTML = formatUserInfo(data.user, experiences, trainingCerts, appDocs, appData);
                        content.style.display = 'block';
                    }
                    if (drawerTitle) drawerTitle.textContent = data.user.full_name || 'Applicant Profile';
                    initDrawerTabs();
                    $$('.ats-drawer__tab').forEach((t, i) => {
                        t.classList.toggle('active', i === 0);
                    });
                    $$('[data-drawer-panel]').forEach((p, i) => {
                        p.style.display = i === 0 ? 'block' : 'none';
                    });
                } else {
                    if (error) {
                        $('#userInfoErrorText').textContent = data.error || 'Failed to load profile';
                        error.style.display = 'block';
                    }
                }
            })
            .catch(err => {
                if (loading) loading.style.display = 'none';
                if (error) {
                    $('#userInfoErrorText').textContent = 'Error: ' + err.message;
                    error.style.display = 'block';
                }
            });
    };

    window.closeUserInfoModal = closeDrawer;

    window.openApplicantDrawer = function (userId, appJson) {
        let appData = null;
        try { appData = typeof appJson === 'string' ? JSON.parse(appJson) : appJson; } catch (e) { /* ignore */ }
        viewUserInformation(userId, appData);
    };

    function formatUserInfo(user, experiences, trainingCertificates, applicationDocuments, appData) {
        const initials = (user.full_name || 'A').charAt(0).toUpperCase();
        let html = '';

        html += `<div data-drawer-panel="profile">`;
        html += `<div class="ats-profile-hero"><div class="ats-avatar" aria-hidden="true">${escapeHtml(initials)}</div>`;
        html += `<h3 class="ats-profile-hero__name">${escapeHtml(user.full_name)}</h3>`;
        html += `<p class="ats-profile-hero__email">${escapeHtml(user.email)}</p></div>`;

        html += `<div class="ats-info-section"><h4 class="ats-info-section__title"><i class="fas fa-user"></i> Personal Information</h4><dl class="ats-info-grid">`;
        if (user.full_name) html += `<div><dt>Name</dt><dd>${escapeHtml(user.full_name)}</dd></div>`;
        if (user.age) html += `<div><dt>Age</dt><dd>${escapeHtml(user.age)}</dd></div>`;
        if (user.sex) html += `<div><dt>Sex</dt><dd>${escapeHtml(user.sex)}</dd></div>`;
        if (user.civil_status) html += `<div><dt>Civil Status</dt><dd>${escapeHtml(user.civil_status)}</dd></div>`;
        if (user.phone) html += `<div><dt>Phone</dt><dd>${escapeHtml(user.phone)}</dd></div>`;
        if (user.email) html += `<div><dt>Email</dt><dd>${escapeHtml(user.email)}</dd></div>`;
        if (user.address) html += `<div style="grid-column:1/-1"><dt>Address</dt><dd>${escapeHtml(user.address)}</dd></div>`;
        html += `</dl></div>`;

        html += `<div class="ats-info-section"><h4 class="ats-info-section__title"><i class="fas fa-graduation-cap"></i> Education</h4><dl class="ats-info-grid">`;
        if (user.highest_educational_attainment) html += `<div><dt>Highest Attainment</dt><dd>${escapeHtml(user.highest_educational_attainment)}</dd></div>`;
        if (user.bachelors_degree) html += `<div><dt>Bachelor's</dt><dd>${escapeHtml(user.bachelors_degree)}</dd></div>`;
        if (user.masters_degree) html += `<div><dt>Master's</dt><dd>${escapeHtml(user.masters_degree)}</dd></div>`;
        if (user.doctorate_degree) html += `<div><dt>Doctorate</dt><dd>${escapeHtml(user.doctorate_degree)}</dd></div>`;
        html += `</dl></div>`;
        html += `</div>`;

        html += `<div data-drawer-panel="experience" style="display:none">`;
        if (experiences.length) {
            html += `<div class="ats-info-section"><h4 class="ats-info-section__title"><i class="fas fa-briefcase"></i> Work Experience</h4>`;
            experiences.forEach(exp => {
                const start = new Date(exp.start_date).toLocaleDateString();
                const end = exp.is_current ? 'Present' : new Date(exp.end_date).toLocaleDateString();
                html += `<div class="ats-req-item pass"><div><div class="ats-req-item__title">${escapeHtml(exp.position_title)}</div>`;
                html += `<div class="ats-req-item__msg">${escapeHtml(exp.company_name)} · ${start} – ${end}</div></div></div>`;
            });
            html += `</div>`;
        } else {
            html += `<p style="color:var(--ats-text-muted)">No work experience recorded.</p>`;
        }
        html += `</div>`;

        html += `<div data-drawer-panel="training" style="display:none">`;
        if (user.training_programs) {
            html += `<div class="ats-info-section"><h4 class="ats-info-section__title"><i class="fas fa-certificate"></i> Training Programs</h4>`;
            html += `<div style="white-space:pre-line;line-height:1.6">${escapeHtml(user.training_programs)}</div></div>`;
        }
        html += `<div class="ats-info-section"><h4 class="ats-info-section__title"><i class="fas fa-file-certificate"></i> Certificates</h4>`;
        if (trainingCertificates.length) {
            trainingCertificates.forEach(cert => {
                if (!cert?.file_path) return;
                const isPdf = cert.file_path.toLowerCase().endsWith('.pdf');
                const filePath = `../../applicant/pages/uploads/documents/${encodeURIComponent(cert.file_path)}`;
                html += `<div class="ats-doc-item"><div class="ats-doc-item__icon"><i class="fas fa-${isPdf ? 'file-pdf' : 'file-image'}"></i></div>`;
                html += `<div class="ats-doc-item__name">${escapeHtml(cert.file_name || cert.file_path)}</div>`;
                html += `<a href="${filePath}" target="_blank" class="ats-btn ats-btn-sm ats-btn-outline"><i class="fas fa-external-link-alt"></i> View</a></div>`;
            });
        } else {
            html += `<p style="color:var(--ats-text-muted);margin:0">No certificates uploaded.</p>`;
        }
        html += `</div></div>`;

        html += `<div data-drawer-panel="documents" style="display:none">`;
        html += `<div class="ats-info-section"><h4 class="ats-info-section__title"><i class="fas fa-folder-open"></i> Application Documents</h4>`;
        if (applicationDocuments.length) {
            applicationDocuments.forEach(doc => {
                if (!doc?.file_path) return;
                const isPdf = doc.file_path.toLowerCase().endsWith('.pdf');
                const filePath = `../../applicant/pages/uploads/documents/${encodeURIComponent(doc.file_path)}`;
                html += `<div class="ats-doc-item"><div class="ats-doc-item__icon"><i class="fas fa-${isPdf ? 'file-pdf' : 'file-image'}"></i></div>`;
                html += `<div class="ats-doc-item__name">${escapeHtml(doc.document_type || 'Document')}</div>`;
                html += `<div class="ats-doc-item__meta">${escapeHtml(doc.file_name || doc.file_path)}</div>`;
                html += `<a href="${filePath}" target="_blank" class="ats-btn ats-btn-sm ats-btn-outline"><i class="fas fa-download"></i> Open</a></div>`;
            });
        } else {
            html += `<p style="color:var(--ats-text-muted);margin:0">No documents uploaded.</p>`;
        }
        if (user.ipcr_file_path) {
            const ipcrPath = `../../applicant/pages/uploads/documents/${encodeURIComponent(user.ipcr_file_path)}`;
            html += `<div class="ats-doc-item" style="margin-top:12px"><div class="ats-doc-item__name">IPCR Document</div><a href="${ipcrPath}" target="_blank" class="ats-btn ats-btn-sm ats-btn-outline">View</a></div>`;
        }
        if (user.pe_file_path) {
            const pePath = `../../applicant/pages/uploads/documents/${encodeURIComponent(user.pe_file_path)}`;
            html += `<div class="ats-doc-item" style="margin-top:12px"><div class="ats-doc-item__name">PE Document</div><a href="${pePath}" target="_blank" class="ats-btn ats-btn-sm ats-btn-outline">View</a></div>`;
        }
        html += `</div></div>`;

        if (user.career_service_eligibilities) {
            html += `<div data-drawer-panel="eligibility" style="display:none"><div class="ats-info-section">`;
            html += `<h4 class="ats-info-section__title"><i class="fas fa-id-card"></i> Career Service Eligibilities</h4>`;
            html += `<p>${escapeHtml(user.career_service_eligibilities)}</p>`;
            if (user.career_service_eligibilities_other) html += `<p><strong>Other:</strong> ${escapeHtml(user.career_service_eligibilities_other)}</p>`;
            html += `</div></div>`;
        }

        if (appData?.cover_letter) {
            html += `<div data-drawer-panel="cover" style="display:none"><div class="ats-cover-letter">`;
            html += `<div class="ats-cover-letter__header"><div class="ats-cover-letter__date">${escapeHtml(appData.applied_at ? new Date(appData.applied_at).toLocaleDateString() : '')}</div>`;
            html += `<strong>Re: ${escapeHtml(appData.position_title || '')}</strong></div>`;
            html += escapeHtml(appData.cover_letter).replace(/\n/g, '<br>') + `</div></div>`;
        }

        html += `<div data-drawer-panel="communication" style="display:none">`;
        html += `<div class="ats-comm-item"><div class="ats-comm-item__type"><i class="fas fa-envelope"></i> Email Applicant</div>`;
        html += `<p style="margin:8px 0 12px;font-size:0.875rem;color:var(--ats-text-muted)">Send a direct message to the applicant.</p>`;
        html += `<a href="mailto:${escapeHtml(user.email)}" class="ats-btn ats-btn-sm ats-btn-primary"><i class="fas fa-paper-plane"></i> Send Email</a></div>`;
        if (appData?.status === cfg.STATUS_APPROVED) {
            html += `<div class="ats-comm-item"><div class="ats-comm-item__type"><i class="fas fa-check-circle"></i> Approval Email</div><p style="margin:0;font-size:0.875rem">Congratulatory email sent upon approval.</p></div>`;
        } else if (appData?.status === cfg.STATUS_REJECTED) {
            html += `<div class="ats-comm-item"><div class="ats-comm-item__type"><i class="fas fa-times-circle"></i> Rejection Email</div><p style="margin:0;font-size:0.875rem">Status notification email sent upon rejection.</p></div>`;
        }
        html += `</div>`;

        return html;
    }

    /* ── Modals ── */
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('open');
            document.body.classList.add('modal-open');
        }
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('open');
            document.body.classList.remove('modal-open');
        }
    }

    /* ── Status confirmation (preserved logic) ── */
    window.showStatusConfirmation = function (status, applicantEmail, applicantName, positionTitle, form) {
        const isApproved = status === cfg.STATUS_APPROVED;
        const isRejected = status === cfg.STATUS_REJECTED;

        pendingStatusUpdate = {
            form: form,
            status: status,
            originalValue: form.querySelector('.status-select').dataset.originalStatus
        };

        const header = $('#statusModalHeader');
        const icon = $('#statusModalIcon');
        const title = $('#statusModalTitle');
        const message = $('#statusModalMessage');
        const infoBox = $('#statusModalInfoBox');
        const nextStep = $('#statusModalNextStep');
        const confirmButton = $('#statusConfirmButton');
        const confirmButtonText = $('#statusConfirmButtonText');

        if (isApproved) {
            header.className = 'ats-modal__header ats-status-modal-header--approved';
            icon.style.background = 'linear-gradient(135deg, #16A34A, #15803d)';
            icon.innerHTML = '<i class="fas fa-check-circle"></i>';
            title.textContent = 'Approve Application';
            message.innerHTML = 'Mark this application as <strong style="color:#16A34A">APPROVED</strong>?';
            infoBox.className = 'ats-info-box ats-info-box--success';
            nextStep.textContent = 'An interview will be automatically scheduled';
            confirmButton.className = 'ats-btn ats-btn-success';
            confirmButtonText.textContent = 'Approve Application';
        } else if (isRejected) {
            header.className = 'ats-modal__header ats-status-modal-header--rejected';
            icon.style.background = 'linear-gradient(135deg, #DC2626, #b91c1c)';
            icon.innerHTML = '<i class="fas fa-times-circle"></i>';
            title.textContent = 'Reject Application';
            message.innerHTML = 'Mark this application as <strong style="color:#DC2626">REJECTED</strong>?';
            infoBox.className = 'ats-info-box ats-info-box--danger';
            nextStep.textContent = 'The applicant will be notified of the rejection';
            confirmButton.className = 'ats-btn ats-btn-danger';
            confirmButtonText.textContent = 'Reject Application';
        }

        $('#statusModalEmail').textContent = applicantEmail;
        $('#statusModalApplicant').textContent = applicantName;
        $('#statusModalPosition').textContent = positionTitle;
        openModal('statusConfirmModal');
    };

    window.closeStatusConfirmModal = function () {
        closeModal('statusConfirmModal');
        if (pendingStatusUpdate) {
            const select = pendingStatusUpdate.form.querySelector('.status-select');
            if (select) select.value = pendingStatusUpdate.originalValue;
            syncStatusButtons(select);
        }
        pendingStatusUpdate = null;
    };

    window.confirmStatusUpdate = function () {
        if (!pendingStatusUpdate?.form) return;
        const form = pendingStatusUpdate.form;
        const select = form.querySelector('.status-select');
        const statusValue = pendingStatusUpdate.status;

        const statusInput = form.querySelector('input[name="status"][type="hidden"]');
        if (statusInput) statusInput.value = statusValue;

                if (select) {
                    select.value = statusValue;
                    select.disabled = true;
                    syncStatusButtons(select);
                    $$('.ats-status-btn', form.closest('.ats-applicant-card') || document).forEach(btn => btn.classList.add('is-loading'));
                }

        closeStatusConfirmModal();

        if (form.requestSubmit) form.requestSubmit();
        else form.submit();
    };

    /* ── Archive ── */
    window.showArchiveConfirmation = function (applicationId, applicantName, positionTitle) {
        pendingArchiveFormId = applicationId;
        $('#archiveConfirmApplicant').textContent = applicantName;
        $('#archiveConfirmPosition').textContent = positionTitle;
        openModal('archiveConfirmModal');
    };

    window.closeArchiveConfirmModal = function () {
        closeModal('archiveConfirmModal');
        pendingArchiveFormId = null;
    };

    window.confirmArchive = function () {
        if (pendingArchiveFormId) {
            const form = document.getElementById('archive-form-' + pendingArchiveFormId);
            if (form) form.submit();
        }
    };

    /* ── Status handlers (preserved) ── */
    function attachStatusHandlers() {
        $$('.status-inline-form').forEach(form => {
            if (form.dataset.inlineStatusInit === 'true') return;

            const select = form.querySelector('.status-select');
            if (!select) return;

            syncStatusButtons(select);

            let isSubmitting = false;
            select.addEventListener('change', function (event) {
                if (isSubmitting) {
                    event.target.value = event.target.dataset.originalStatus;
                    syncStatusButtons(event.target);
                    return;
                }

                const selectedStatus = event.target.value;
                const originalStatus = event.target.dataset.originalStatus;
                if (selectedStatus === originalStatus) return;

                syncStatusButtons(event.target);

                const submitForm = () => {
                    isSubmitting = true;
                    const statusInput = form.querySelector('input[name="status"][type="hidden"]');
                    if (statusInput) statusInput.value = selectedStatus;
                    event.target.disabled = true;
                    $$('.ats-status-btn', form.closest('.ats-applicant-card') || document).forEach(btn => btn.classList.add('is-loading'));
                    if (form.requestSubmit) form.requestSubmit();
                    else form.submit();
                };

                if (selectedStatus === cfg.STATUS_PENDING) {
                    submitForm();
                    return;
                }

                const card = form.closest('.ats-applicant-card');
                let applicantName = card?.dataset.name || 'Applicant';
                let positionTitle = card?.dataset.position || 'Position';
                const applicantEmail = event.target.dataset.applicantEmail;

                showStatusConfirmation(selectedStatus, applicantEmail, applicantName, positionTitle, form);
            });

            form.dataset.inlineStatusInit = 'true';
        });
    }

    /* ── Lazy load cards ── */
    function initLazyCards() {
        const cards = $$('.ats-applicant-card');
        if (!('IntersectionObserver' in window)) {
            cards.forEach(c => c.classList.add('loaded'));
            return;
        }
        const observer = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('loaded');
                    observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: '100px' });
        cards.forEach(c => observer.observe(c));
    }

    /* ── Global events ── */
    function initGlobalEvents() {
        window.addEventListener('click', e => {
            if (e.target.id === 'coverLetterModal') closeCoverLetterModal();
            if (e.target.id === 'archiveConfirmModal') closeArchiveConfirmModal();
            if (e.target.id === 'statusConfirmModal') closeStatusConfirmModal();
            if (e.target.id === 'atsDrawerOverlay') closeDrawer();
        });

        window.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeCoverLetterModal();
                closeArchiveConfirmModal();
                closeStatusConfirmModal();
                closeDrawer();
                $('#atsSearchPalette')?.classList.remove('open');
                $('#atsComparePanel')?.classList.remove('open');
            }
        });

        $('#atsDrawerClose')?.addEventListener('click', closeDrawer);
        $('#atsRefreshBtn')?.addEventListener('click', () => window.location.reload());

        document.addEventListener('click', e => {
            const profileBtn = e.target.closest('.btn-view-profile');
            if (profileBtn) {
                const userId = parseInt(profileBtn.dataset.userId, 10);
                let appData = null;
                try { appData = JSON.parse(profileBtn.dataset.appJson || '{}'); } catch (err) { /* ignore */ }
                openApplicantDrawer(userId, appData);
            }
        });

        $$('.ats-modal__close, [data-close-modal]').forEach(btn => {
            btn.addEventListener('click', () => {
                const modal = btn.closest('.ats-modal');
                if (modal) {
                    modal.classList.remove('open');
                    document.body.classList.remove('modal-open');
                }
            });
        });
    }

    /* ── Init ── */
    document.addEventListener('DOMContentLoaded', () => {
        initTheme();
        initSearchPalette();
        initLiveHighlight();
        initClientSort();
        initBulkSelection();
        initComparePanel();
        initStatusWorkflow();
        initializeApplicationDetails();
        initDocViewToggle();
        attachStatusHandlers();
        initLazyCards();
        initGlobalEvents();
    });
})();
