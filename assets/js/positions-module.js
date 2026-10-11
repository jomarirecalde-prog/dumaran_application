/**
 * Dumaran Hiring Positions Module — Enterprise Recruitment UI
 * Frontend-only: search, filters, views, stepper, drawer, templates
 */
(function () {
    'use strict';

    const STORAGE_VIEW = 'dumaran_positions_view';
    const STORAGE_THEME = 'dumaran_positions_theme';
    const STORAGE_RECENT = 'dumaran_positions_recent_searches';
    const STORAGE_PRESETS = 'dumaran_positions_filter_presets';

    let allPositions = [];
    let filteredPositions = [];
    let selectedIds = new Set();
    let currentStep = 0;
    let clientFiltered = false;
    let clientPage = 1;
    let clientPerPage = 6;

    const STEPS = ['Basic Information', 'Job Details', 'Qualifications', 'Salary & Benefits', 'Eligibility', 'Preview', 'Publish'];

    const TEMPLATES = {
        instructor: {
            title: 'Instructor I',
            description: 'Teach assigned courses, develop syllabi, and participate in academic activities at Municipality of Dumaran.',
            requirements: 'Master\'s degree in relevant field; at least 2 years teaching experience; strong communication skills.',
            min_experience_years: 2,
            salary_grade: 'SG 18',
            salary_range: '₱35,000 - ₱45,000',
            training_hours: 40,
            employment_type: 'full-time',
            location: 'Municipal Hall, Dumaran, Palawan',
            career_service_eligibilities: ['Professional Eligibility']
        },
        admin_assistant: {
            title: 'Administrative Assistant III',
            description: 'Provide administrative support to university offices including document management and correspondence.',
            requirements: 'Bachelor\'s degree; proficiency in MS Office; 1 year relevant experience.',
            min_experience_years: 1,
            salary_grade: 'SG 9',
            salary_range: '₱22,000 - ₱28,000',
            training_hours: 24,
            employment_type: 'full-time',
            location: 'Municipal Hall, Dumaran, Palawan',
            career_service_eligibilities: ['Sub-Professional Eligibility', 'Professional Eligibility']
        },
        registrar: {
            title: 'Registrar Staff',
            description: 'Assist in student records management, enrollment processing, and transcript requests.',
            requirements: 'Bachelor\'s degree; attention to detail; customer service orientation.',
            min_experience_years: 1,
            salary_grade: 'SG 11',
            salary_range: '₱25,000 - ₱32,000',
            employment_type: 'full-time',
            location: 'Municipal Hall, Dumaran, Palawan',
            career_service_eligibilities: ['Professional Eligibility']
        },
        hr_officer: {
            title: 'HR Officer',
            description: 'Support human resource operations including recruitment, employee records, and HR programs.',
            requirements: 'Bachelor\'s degree in HR or related field; knowledge of CSC rules preferred.',
            min_experience_years: 2,
            salary_grade: 'SG 15',
            salary_range: '₱30,000 - ₱38,000',
            training_hours: 40,
            employment_type: 'full-time',
            location: 'Municipal Hall, Dumaran, Palawan',
            career_service_eligibilities: ['Professional Eligibility']
        },
        it_personnel: {
            title: 'IT Personnel',
            description: 'Maintain university IT infrastructure, provide technical support, and assist in system development.',
            requirements: 'Bachelor\'s degree in IT/CS; troubleshooting skills; network basics.',
            min_experience_years: 1,
            salary_grade: 'SG 15',
            salary_range: '₱30,000 - ₱40,000',
            employment_type: 'full-time',
            location: 'Municipal Hall, Dumaran, Palawan',
            career_service_eligibilities: ['Professional Eligibility']
        },
        security: {
            title: 'Security Personnel',
            description: 'Ensure campus safety, monitor access points, and respond to security incidents.',
            requirements: 'High school graduate; security training certificate; physically fit.',
            min_experience_years: 1,
            salary_grade: 'SG 4',
            salary_range: '₱16,000 - ₱20,000',
            employment_type: 'full-time',
            location: 'Municipal Hall, Dumaran, Palawan',
            career_service_eligibilities: ['Sub-Professional Eligibility']
        },
        faculty: {
            title: 'Faculty Member',
            description: 'Deliver quality instruction, conduct research, and engage in extension services.',
            requirements: 'Master\'s degree; research experience; commitment to university mission.',
            min_experience_years: 3,
            salary_grade: 'SG 20',
            salary_range: '₱45,000 - ₱55,000',
            training_hours: 80,
            employment_type: 'full-time',
            location: 'Municipal Hall, Dumaran, Palawan',
            career_service_eligibilities: ['Professional Eligibility', 'Bar/Board Eligibility (RA 1080)']
        }
    };

    const CSE_ICONS = {
        'Professional Eligibility': 'fa-user-tie',
        'Sub-Professional Eligibility': 'fa-user',
        'Career Service Executive Eligibility (CSE-E)': 'fa-crown',
        'Bar/Board Eligibility (RA 1080)': 'fa-gavel'
    };

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        const dataEl = document.getElementById('positionsData');
        if (dataEl) {
            try {
                allPositions = JSON.parse(dataEl.textContent);
            } catch (e) {
                allPositions = [];
            }
        }
        filteredPositions = [...allPositions];

        initTheme();
        initViewToggle();
        initSearch();
        initFilters();
        initStepper();
        initCseCards();
        initSalaryInputs();
        initCharCounters();
        initAutoResizeTextareas();
        initFab();
        initBulkActions();
        initDrawer();
        initTemplates();
        initAnimatedCounters();
        initEmploymentTypeSearch();
        bindGlobalShortcuts();
        animateCards();
        renderPresets();
        bindCardEvents(document.getElementById('tableView'));
    }

    /* ── Theme ── */
    function initTheme() {
        const saved = localStorage.getItem(STORAGE_THEME) || 'auto';
        applyTheme(saved);

        document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
            btn.addEventListener('click', () => {
                const current = document.documentElement.getAttribute('data-theme');
                const next = current === 'dark' ? 'light' : 'dark';
                localStorage.setItem(STORAGE_THEME, next);
                applyTheme(next);
            });
        });

        if (saved === 'auto') {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                if (localStorage.getItem(STORAGE_THEME) === 'auto' || !localStorage.getItem(STORAGE_THEME)) {
                    applyTheme('auto');
                }
            });
        }
    }

    function applyTheme(mode) {
        if (mode === 'auto') {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
        } else {
            document.documentElement.setAttribute('data-theme', mode);
        }
    }

    /* ── View Toggle ── */
    function initViewToggle() {
        const saved = localStorage.getItem(STORAGE_VIEW) || 'card';
        setView(saved);

        document.querySelectorAll('.pos-view-toggle__btn').forEach(btn => {
            btn.addEventListener('click', () => {
                setView(btn.dataset.view);
                localStorage.setItem(STORAGE_VIEW, btn.dataset.view);
            });
        });
    }

    function setView(view) {
        document.querySelectorAll('.pos-view-toggle__btn').forEach(b => {
            b.classList.toggle('active', b.dataset.view === view);
            b.setAttribute('aria-pressed', b.dataset.view === view ? 'true' : 'false');
        });
        const cardView = document.getElementById('cardView');
        const tableView = document.getElementById('tableView');
        if (cardView) cardView.style.display = view === 'card' ? '' : 'none';
        if (tableView) tableView.style.display = view === 'table' ? '' : 'none';
    }

    /* ── Search & Filters ── */
    function initSearch() {
        const input = document.getElementById('globalSearch');
        const palette = document.getElementById('searchPalette');
        const paletteInput = document.getElementById('searchPaletteInput');

        if (input) {
            let debounce;
            input.addEventListener('input', () => {
                clearTimeout(debounce);
                debounce = setTimeout(() => applyFilters(input.value), 250);
            });
        }

        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                openSearchPalette();
            }
            if (e.key === 'Escape') closeSearchPalette();
        });

        if (paletteInput) {
            let debounce;
            paletteInput.addEventListener('input', () => {
                clearTimeout(debounce);
                debounce = setTimeout(() => {
                    applyFilters(paletteInput.value);
                    if (document.getElementById('globalSearch')) {
                        document.getElementById('globalSearch').value = paletteInput.value;
                    }
                }, 200);
            });
        }

        document.getElementById('searchPaletteOverlay')?.addEventListener('click', closeSearchPalette);
    }

    function openSearchPalette() {
        const palette = document.getElementById('searchPalette');
        const overlay = document.getElementById('searchPaletteOverlay');
        const input = document.getElementById('searchPaletteInput');
        if (palette) palette.classList.add('open');
        if (overlay) overlay.classList.add('open');
        if (input) { input.value = ''; input.focus(); }
        renderRecentSearches();
    }

    function closeSearchPalette() {
        document.getElementById('searchPalette')?.classList.remove('open');
        document.getElementById('searchPaletteOverlay')?.classList.remove('open');
    }

    function saveRecentSearch(q) {
        if (!q.trim()) return;
        let recent = JSON.parse(localStorage.getItem(STORAGE_RECENT) || '[]');
        recent = [q, ...recent.filter(r => r !== q)].slice(0, 5);
        localStorage.setItem(STORAGE_RECENT, JSON.stringify(recent));
    }

    function renderRecentSearches() {
        const el = document.getElementById('recentSearches');
        if (!el) return;
        const recent = JSON.parse(localStorage.getItem(STORAGE_RECENT) || '[]');
        if (!recent.length) { el.innerHTML = '<span>No recent searches</span>'; return; }
        el.innerHTML = recent.map(r =>
            `<button type="button" class="pos-preset-chip" onclick="PositionsModule.search('${escapeAttr(r)}')">${escapeHtml(r)}</button>`
        ).join('');
    }

    function initFilters() {
        ['filterStatus', 'filterEmployment', 'filterCampus', 'filterSalaryGrade', 'filterDeadline', 'sortBy'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', () => applyFilters());
        });

        document.getElementById('resetFilters')?.addEventListener('click', resetFilters);
        document.getElementById('exportFiltered')?.addEventListener('click', exportCSV);
        document.getElementById('savePreset')?.addEventListener('click', saveFilterPreset);
    }

    function getFilterValues() {
        return {
            query: (document.getElementById('globalSearch')?.value || '').toLowerCase().trim(),
            status: document.getElementById('filterStatus')?.value || '',
            employment: document.getElementById('filterEmployment')?.value || '',
            campus: document.getElementById('filterCampus')?.value || '',
            salaryGrade: document.getElementById('filterSalaryGrade')?.value || '',
            deadline: document.getElementById('filterDeadline')?.value || '',
            sort: document.getElementById('sortBy')?.value || 'newest'
        };
    }

    function applyFilters(queryOverride) {
        const f = getFilterValues();
        if (queryOverride !== undefined) f.query = queryOverride.toLowerCase().trim();

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        filteredPositions = allPositions.filter(p => {
            if (f.query) {
                const hay = [p.title, p.description, p.location, p.employment_type_label, p.salary_grade].join(' ').toLowerCase();
                if (!hay.includes(f.query)) return false;
            }
            if (f.status === 'active' && p.is_active != 1) return false;
            if (f.status === 'inactive' && p.is_active != 0) return false;
            if (f.status === 'closing') {
                if (!p.deadline) return false;
                const dl = new Date(p.deadline);
                const diff = (dl - today) / 86400000;
                if (diff < 0 || diff > 7) return false;
            }
            if (f.status === 'expired' && p.deadline && new Date(p.deadline) >= today) return false;
            if (f.employment && p.employment_type !== f.employment) return false;
            if (f.campus && p.location !== f.campus) return false;
            if (f.salaryGrade && !(p.salary_grade || '').includes(f.salaryGrade)) return false;
            if (f.deadline === 'week') {
                if (!p.deadline) return false;
                const diff = (new Date(p.deadline) - today) / 86400000;
                if (diff < 0 || diff > 7) return false;
            }
            if (f.deadline === 'month') {
                if (!p.deadline) return false;
                const diff = (new Date(p.deadline) - today) / 86400000;
                if (diff < 0 || diff > 30) return false;
            }
            return true;
        });

        sortPositions(f.sort);

        const hasActiveFilter = f.query || f.status || f.employment || f.campus || f.salaryGrade || f.deadline || f.sort !== 'newest';
        clientFiltered = hasActiveFilter;
        clientPage = 1;

        const container = document.querySelector('.pos-view-container');
        if (container) container.classList.toggle('client-filtered', hasActiveFilter);

        if (hasActiveFilter) {
            if (f.query) saveRecentSearch(f.query);
            renderClientView();
        }

        highlightSearchTerms(f.query);
    }

    function sortPositions(sort) {
        filteredPositions.sort((a, b) => {
            switch (sort) {
                case 'oldest': return new Date(a.created_at) - new Date(b.created_at);
                case 'applications': return (b.application_count || 0) - (a.application_count || 0);
                case 'closing':
                    if (!a.deadline) return 1;
                    if (!b.deadline) return -1;
                    return new Date(a.deadline) - new Date(b.deadline);
                case 'alpha': return a.title.localeCompare(b.title);
                default: return new Date(b.created_at) - new Date(a.created_at);
            }
        });
    }

    function resetFilters() {
        ['globalSearch', 'filterStatus', 'filterEmployment', 'filterCampus', 'filterSalaryGrade', 'filterDeadline'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        const sort = document.getElementById('sortBy');
        if (sort) sort.value = 'newest';
        clientFiltered = false;
        document.querySelector('.pos-view-container')?.classList.remove('client-filtered');
        filteredPositions = [...allPositions];
    }

    function highlightSearchTerms(query) {
        if (!query) return;
        document.querySelectorAll('.pos-card__title').forEach(el => {
            const text = el.dataset.title || el.textContent;
            const re = new RegExp(`(${escapeRegex(query)})`, 'gi');
            el.innerHTML = escapeHtml(text).replace(re, '<mark>$1</mark>');
        });
    }

    function renderClientView() {
        const start = (clientPage - 1) * clientPerPage;
        const pageItems = filteredPositions.slice(start, start + clientPerPage);
        const cardContainer = document.getElementById('clientCardView');
        const tableContainer = document.getElementById('clientTableBody');
        const paginationInfo = document.getElementById('clientPaginationInfo');
        const paginationPages = document.getElementById('clientPaginationPages');

        if (cardContainer) cardContainer.innerHTML = pageItems.length ? pageItems.map(renderCardHTML).join('') : renderEmptyHTML();
        if (tableContainer) tableContainer.innerHTML = pageItems.map(renderTableRowHTML).join('');

        const total = filteredPositions.length;
        const totalPages = Math.ceil(total / clientPerPage) || 1;
        if (paginationInfo) {
            paginationInfo.textContent = total
                ? `Showing ${start + 1}–${Math.min(start + clientPerPage, total)} of ${total} positions`
                : 'No matching positions';
        }
        if (paginationPages) {
            paginationPages.innerHTML = buildClientPagination(totalPages);
        }

        bindCardEvents(cardContainer);
        bindCardEvents(document.getElementById('clientTableBody'));
    }

    function buildClientPagination(totalPages) {
        let html = '';
        for (let i = 1; i <= totalPages; i++) {
            html += `<button type="button" class="pos-pagination__page ${i === clientPage ? 'active' : ''}" onclick="PositionsModule.goClientPage(${i})">${i}</button>`;
        }
        return html;
    }

    function goClientPage(page) {
        clientPage = page;
        renderClientView();
        window.scrollTo({ top: document.querySelector('.pos-view-container')?.offsetTop || 0, behavior: 'smooth' });
    }

    /* ── Card / Table Rendering ── */
    function getStatusInfo(p) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (p.is_active == 0) return { label: 'Draft', cls: 'draft', hiring: 'Draft' };
        if (p.deadline && new Date(p.deadline) < today) return { label: 'Closed', cls: 'closed', hiring: 'Expired' };
        const max = parseInt(p.max_applications, 10);
        const count = parseInt(p.application_count, 10) || 0;
        if (max && count >= max) return { label: 'Closed', cls: 'closed', hiring: 'Closed' };
        if (max && count >= max * 0.8) return { label: 'Nearly Full', cls: 'closing', hiring: 'Nearly Full' };
        if (p.deadline) {
            const diff = (new Date(p.deadline) - today) / 86400000;
            if (diff <= 3 && diff >= 0) return { label: 'Closing Soon', cls: 'closing', hiring: 'Open' };
        }
        return { label: 'Active', cls: 'active', hiring: 'Open' };
    }

    function getDeadlineInfo(deadline) {
        if (!deadline) return { text: 'No deadline', cls: 'gray' };
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const dl = new Date(deadline);
        const diff = Math.round((dl - today) / 86400000);
        if (diff < 0) return { text: 'Expired', cls: 'red' };
        if (diff === 0) return { text: 'Closing Today', cls: 'orange' };
        if (diff === 1) return { text: 'Closing Tomorrow', cls: 'orange' };
        if (diff <= 3) return { text: `${diff} Days Left`, cls: 'orange' };
        if (diff <= 14) return { text: `${diff} days left`, cls: 'green' };
        return { text: formatDate(deadline), cls: 'green' };
    }

    function getProgressBlocks(count, max) {
        const total = max || 30;
        const filled = Math.min(Math.round((count / total) * 8), 8);
        let html = '';
        for (let i = 0; i < 8; i++) {
            html += `<div class="pos-progress__block ${i < filled ? 'filled' : ''}"></div>`;
        }
        return html;
    }

    function renderCardHTML(p) {
        const status = getStatusInfo(p);
        const deadline = getDeadlineInfo(p.deadline);
        const count = parseInt(p.application_count, 10) || 0;
        const max = parseInt(p.max_applications, 10) || 30;
        const pct = Math.min(100, Math.round((count / max) * 100));

        return `
        <article class="pos-card" data-id="${p.id}" style="animation-delay:${(p.id % 6) * 0.05}s">
            <div class="pos-card__accent"></div>
            ${status.cls === 'closing' ? '<div class="pos-card__ribbon" style="background:var(--pos-warning)">Closing</div>' : ''}
            <div class="pos-card__body">
                <div class="pos-card__header">
                    <input type="checkbox" class="pos-card__checkbox" data-select-id="${p.id}" aria-label="Select ${escapeAttr(p.title)}" ${selectedIds.has(p.id) ? 'checked' : ''}>
                    <div style="flex:1">
                        <h4 class="pos-card__title" data-title="${escapeAttr(p.title)}" onclick="PositionsModule.preview(${p.id})">${escapeHtml(p.title)}</h4>
                        <div class="pos-card__meta">
                            <span class="pos-badge pos-badge--${status.cls}"><i class="fas fa-circle" style="font-size:0.5rem"></i> ${status.label}</span>
                            <span class="pos-badge pos-badge--type">${escapeHtml(p.employment_type_label || p.employment_type)}</span>
                        </div>
                    </div>
                </div>
                <div class="pos-card__details">
                    <div class="pos-card__detail"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(p.location || '—')}</div>
                    <div class="pos-card__detail"><i class="fas fa-layer-group"></i> ${escapeHtml(p.salary_grade || '—')}</div>
                    <div class="pos-card__detail"><i class="fas fa-money-bill-wave"></i> ${escapeHtml(p.salary_range || '—')}</div>
                    <div class="pos-card__detail"><i class="fas fa-briefcase"></i> ${p.min_experience_years || 0}y exp</div>
                    <div class="pos-card__detail"><i class="fas fa-clock"></i> ${p.training_hours ? p.training_hours + 'h training' : '—'}</div>
                    <div class="pos-card__detail"><i class="fas fa-certificate"></i> ${p.career_service_eligibilities ? 'CSE Required' : '—'}</div>
                </div>
                ${p.deadline ? `<div class="pos-deadline pos-deadline--${deadline.cls}"><i class="fas fa-calendar-alt"></i> ${deadline.text}</div>` : ''}
                <div class="pos-progress">
                    <div class="pos-progress__label"><span>Applications</span><span>${count} / ${p.max_applications || '∞'}</span></div>
                    <div class="pos-progress__bar"><div class="pos-progress__fill" style="width:${pct}%"></div></div>
                    <div class="pos-progress__blocks">${getProgressBlocks(count, max)}</div>
                </div>
                <p class="pos-card__excerpt">${escapeHtml((p.description || '').substring(0, 120))}${(p.description || '').length > 120 ? '…' : ''}</p>
                <div class="pos-card__posted"><i class="fas fa-calendar-plus"></i> Posted ${formatDate(p.created_at)} · Hiring: ${status.hiring}</div>
                <div class="pos-card__footer">
                    <button type="button" class="pos-btn pos-btn--secondary pos-btn--sm" onclick="PositionsModule.preview(${p.id})" title="View"><i class="fas fa-eye"></i></button>
                    <button type="button" class="pos-btn pos-btn--secondary pos-btn--sm" onclick='PositionsModule.edit(${JSON.stringify(p).replace(/'/g, "&#39;")})' title="Edit"><i class="fas fa-edit"></i></button>
                    <button type="button" class="pos-btn pos-btn--secondary pos-btn--sm" onclick='PositionsModule.duplicate(${JSON.stringify(p).replace(/'/g, "&#39;")})' title="Duplicate"><i class="fas fa-copy"></i></button>
                    <button type="button" class="pos-btn pos-btn--secondary pos-btn--sm" onclick="PositionsModule.confirmDelete(${p.id}, '${escapeAttr(p.title)}', ${count})" title="Delete"><i class="fas fa-trash" style="color:var(--pos-danger)"></i></button>
                </div>
            </div>
        </article>`;
    }

    function renderTableRowHTML(p) {
        const status = getStatusInfo(p);
        const count = parseInt(p.application_count, 10) || 0;
        return `
        <tr data-id="${p.id}">
            <td><input type="checkbox" class="pos-card__checkbox" data-select-id="${p.id}" ${selectedIds.has(p.id) ? 'checked' : ''}></td>
            <td><strong>${escapeHtml(p.title)}</strong></td>
            <td>${escapeHtml(p.location || '—')}</td>
            <td>${escapeHtml(p.employment_type_label || '')}</td>
            <td><span class="pos-badge pos-badge--${status.cls}">${status.label}</span></td>
            <td>${count}</td>
            <td>${p.deadline ? formatDate(p.deadline) : '—'}</td>
            <td><div class="pos-table__actions">
                <button type="button" class="pos-btn pos-btn--secondary pos-btn--sm pos-btn--icon" onclick="PositionsModule.preview(${p.id})"><i class="fas fa-eye"></i></button>
                <button type="button" class="pos-btn pos-btn--secondary pos-btn--sm pos-btn--icon" onclick='PositionsModule.edit(${JSON.stringify(p).replace(/'/g, "&#39;")})'><i class="fas fa-edit"></i></button>
                <button type="button" class="pos-btn pos-btn--secondary pos-btn--sm pos-btn--icon" onclick="PositionsModule.confirmDelete(${p.id}, '${escapeAttr(p.title)}', ${count})"><i class="fas fa-trash"></i></button>
            </div></td>
        </tr>`;
    }

    function renderEmptyHTML() {
        return `<div class="pos-empty"><div class="pos-empty__icon"><i class="fas fa-search"></i></div><h3>No matching positions</h3><p>Try adjusting your filters or search terms.</p><button type="button" class="pos-btn pos-btn--primary" onclick="PositionsModule.resetFilters()">Reset Filters</button></div>`;
    }

    function bindCardEvents(container) {
        if (!container) return;
        container.querySelectorAll('[data-select-id]').forEach(cb => {
            cb.addEventListener('change', () => {
                const id = parseInt(cb.dataset.selectId, 10);
                if (cb.checked) selectedIds.add(id); else selectedIds.delete(id);
                updateBulkBar();
            });
        });
    }

    /* ── Stepper Modal ── */
    function initStepper() {
        document.getElementById('stepNext')?.addEventListener('click', () => {
            if (validateStep(currentStep)) goStep(currentStep + 1);
        });
        document.getElementById('stepPrev')?.addEventListener('click', () => goStep(currentStep - 1));
        document.querySelectorAll('.pos-stepper__step').forEach((step, i) => {
            step.addEventListener('click', () => { if (i < currentStep || validateStep(currentStep)) goStep(i); });
        });
        document.getElementById('positionForm')?.addEventListener('submit', syncSalaryRange);
    }

    function goStep(step) {
        if (step < 0 || step >= STEPS.length) return;
        currentStep = step;

        document.querySelectorAll('.pos-form-step').forEach((el, i) => el.classList.toggle('active', i === step));
        document.querySelectorAll('.pos-stepper__step').forEach((el, i) => {
            el.classList.toggle('active', i === step);
            el.classList.toggle('completed', i < step);
        });

        const prevBtn = document.getElementById('stepPrev');
        const nextBtn = document.getElementById('stepNext');
        const submitBtn = document.getElementById('submitBtn');

        if (prevBtn) prevBtn.style.display = step === 0 ? 'none' : '';
        if (nextBtn) nextBtn.style.display = step >= STEPS.length - 1 ? 'none' : '';
        if (submitBtn) submitBtn.style.display = step === STEPS.length - 1 ? '' : 'none';

        if (step === STEPS.length - 2) buildPreview();

        const statusDiv = document.getElementById('statusDiv');
        const isEdit = document.getElementById('formAction')?.value === 'update';
        if (statusDiv) statusDiv.style.display = (step === STEPS.length - 1 && isEdit) ? 'block' : 'none';
    }

    function validateStep(step) {
        const required = {
            0: ['title', 'employment_type'],
            1: ['description', 'requirements'],
        };
        const fields = required[step] || [];
        let valid = true;
        fields.forEach(id => {
            const el = document.getElementById(id);
            if (el && !el.value.trim()) {
                el.classList.add('pos-field-error');
                valid = false;
            } else if (el) {
                el.classList.remove('pos-field-error');
                el.classList.add('pos-field-valid');
            }
        });
        return valid;
    }

    function buildPreview() {
        const el = document.getElementById('previewContent');
        if (!el) return;
        const title = document.getElementById('title')?.value || '';
        const desc = document.getElementById('description')?.value || '';
        const req = document.getElementById('requirements')?.value || '';
        el.innerHTML = `
            <div class="pos-preview-card">
                <h3 style="margin-bottom:12px">${escapeHtml(title)}</h3>
                <div class="pos-preview-row"><span>Employment</span><span>${escapeHtml(document.getElementById('employment_type')?.selectedOptions[0]?.text || '')}</span></div>
                <div class="pos-preview-row"><span>Location</span><span>${escapeHtml(document.getElementById('location')?.value || '—')}</span></div>
                <div class="pos-preview-row"><span>Salary</span><span>${escapeHtml(document.getElementById('salary_min')?.value || '')} – ${escapeHtml(document.getElementById('salary_max')?.value || '')}</span></div>
                <div class="pos-preview-row"><span>Experience</span><span>${document.getElementById('min_experience_years')?.value || 0} years</span></div>
                <p style="margin-top:16px;font-size:0.85rem;color:var(--gray-600)"><strong>Description:</strong> ${escapeHtml(desc.substring(0, 200))}…</p>
                <p style="margin-top:8px;font-size:0.85rem;color:var(--gray-600)"><strong>Requirements:</strong> ${escapeHtml(req.substring(0, 200))}…</p>
            </div>`;
    }

    /* ── CSE Cards ── */
    function initCseCards() {
        document.querySelectorAll('.pos-cse-card').forEach(card => {
            card.addEventListener('click', () => {
                const input = card.querySelector('input[type="checkbox"]');
                if (input) {
                    input.checked = !input.checked;
                    card.classList.toggle('selected', input.checked);
                }
            });
        });
    }

    function syncCseCards(values) {
        document.querySelectorAll('.pos-cse-card').forEach(card => {
            const input = card.querySelector('input[type="checkbox"]');
            if (input) {
                input.checked = values.includes(input.value);
                card.classList.toggle('selected', input.checked);
            }
        });
    }

    /* ── Salary ── */
    function initSalaryInputs() {
        ['salary_min', 'salary_max'].forEach(id => {
            document.getElementById(id)?.addEventListener('blur', formatSalaryInput);
            document.getElementById(id)?.addEventListener('input', () => {
                document.getElementById(id)?.classList.remove('pos-field-error');
            });
        });
    }

    function formatSalaryInput(e) {
        const val = e.target.value.replace(/[^\d]/g, '');
        if (val) e.target.value = '₱' + parseInt(val, 10).toLocaleString('en-PH');
    }

    function syncSalaryRange() {
        const min = document.getElementById('salary_min')?.value || '';
        const max = document.getElementById('salary_max')?.value || '';
        const hidden = document.getElementById('salary_range');
        if (hidden) {
            hidden.value = min && max ? `${min} - ${max}` : (min || max);
        }
    }

    function parseSalaryRange(range) {
        if (!range) return { min: '', max: '' };
        const parts = range.split(/\s*[-–]\s*/);
        return { min: parts[0]?.trim() || '', max: parts[1]?.trim() || '' };
    }

    /* ── Char counters & textareas ── */
    function initCharCounters() {
        document.querySelectorAll('[data-max-length]').forEach(el => {
            const max = parseInt(el.dataset.maxLength, 10);
            const counter = el.parentElement.querySelector('.pos-char-count');
            const update = () => {
                if (counter) counter.textContent = `${el.value.length} / ${max}`;
            };
            el.addEventListener('input', update);
            update();
        });
    }

    function initAutoResizeTextareas() {
        document.querySelectorAll('.pos-float-textarea').forEach(ta => {
            ta.addEventListener('input', () => {
                ta.style.height = 'auto';
                ta.style.height = ta.scrollHeight + 'px';
            });
        });
    }

    /* ── FAB ── */
    function initFab() {
        const fab = document.getElementById('posFab');
        const menu = document.getElementById('posFabMenu');
        fab?.addEventListener('click', () => menu?.classList.toggle('open'));
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.pos-fab-wrap')) menu?.classList.remove('open');
        });
    }

    /* ── Bulk ── */
    function initBulkActions() {
        document.getElementById('selectAllPositions')?.addEventListener('change', (e) => {
            document.querySelectorAll('[data-select-id]').forEach(cb => {
                cb.checked = e.target.checked;
                const id = parseInt(cb.dataset.selectId, 10);
                if (e.target.checked) selectedIds.add(id); else selectedIds.delete(id);
            });
            updateBulkBar();
        });

        document.getElementById('bulkExport')?.addEventListener('click', exportSelectedCSV);
    }

    function updateBulkBar() {
        const bar = document.getElementById('bulkBar');
        const count = document.getElementById('bulkCount');
        if (bar) bar.classList.toggle('visible', selectedIds.size > 0);
        if (count) count.textContent = selectedIds.size;
    }

    function exportCSV() {
        exportPositionsToCSV(filteredPositions.length ? filteredPositions : allPositions);
    }

    function exportSelectedCSV() {
        const selected = allPositions.filter(p => selectedIds.has(p.id));
        exportPositionsToCSV(selected);
    }

    function exportPositionsToCSV(positions) {
        const headers = ['Title', 'Location', 'Employment Type', 'Status', 'Salary Grade', 'Salary Range', 'Applications', 'Deadline'];
        const rows = positions.map(p => [
            p.title, p.location || '', p.employment_type_label || p.employment_type,
            p.is_active == 1 ? 'Active' : 'Inactive', p.salary_grade || '', p.salary_range || '',
            p.application_count || 0, p.deadline || ''
        ]);
        downloadCSV([headers, ...rows], 'dumaran-positions-export.csv');
        showToast('Export downloaded successfully', 'success');
    }

    function downloadCSV(rows, filename) {
        const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
    }

    /* ── Drawer ── */
    function initDrawer() {
        document.getElementById('drawerClose')?.addEventListener('click', closeDrawer);
        document.getElementById('drawerOverlay')?.addEventListener('click', closeDrawer);
    }

    function openDrawer(p) {
        const drawer = document.getElementById('previewDrawer');
        const overlay = document.getElementById('drawerOverlay');
        const body = document.getElementById('drawerBody');
        const title = document.getElementById('drawerTitle');
        if (!drawer || !body) return;

        const status = getStatusInfo(p);
        const count = parseInt(p.application_count, 10) || 0;

        if (title) title.textContent = p.title;
        body.innerHTML = `
            <div class="pos-drawer__section">
                <div class="pos-drawer__section-title">Overview</div>
                <div class="pos-card__meta" style="margin-bottom:12px">
                    <span class="pos-badge pos-badge--${status.cls}">${status.label}</span>
                    <span class="pos-badge pos-badge--type">${escapeHtml(p.employment_type_label || '')}</span>
                </div>
                <div class="pos-card__details" style="grid-template-columns:1fr">
                    <div class="pos-card__detail"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(p.location || '—')}</div>
                    <div class="pos-card__detail"><i class="fas fa-money-bill-wave"></i> ${escapeHtml(p.salary_range || '—')} (${escapeHtml(p.salary_grade || '—')})</div>
                    <div class="pos-card__detail"><i class="fas fa-users"></i> ${count} applications received</div>
                    <div class="pos-card__detail"><i class="fas fa-calendar"></i> Deadline: ${p.deadline ? formatDate(p.deadline) : 'None'}</div>
                </div>
            </div>
            <div class="pos-drawer__section">
                <div class="pos-drawer__section-title">Description</div>
                <p style="font-size:0.9rem;line-height:1.6;color:var(--gray-600)">${escapeHtml(p.description || '')}</p>
            </div>
            <div class="pos-drawer__section">
                <div class="pos-drawer__section-title">Requirements</div>
                <p style="font-size:0.9rem;line-height:1.6;color:var(--gray-600)">${escapeHtml(p.requirements || '')}</p>
            </div>
            <div class="pos-drawer__section">
                <div class="pos-drawer__section-title">Hiring Statistics</div>
                <div class="pos-progress">
                    <div class="pos-progress__label"><span>Application Progress</span><span>${count} / ${p.max_applications || '∞'}</span></div>
                    <div class="pos-progress__bar"><div class="pos-progress__fill" style="width:${Math.min(100, Math.round((count / (p.max_applications || 30)) * 100))}%"></div></div>
                </div>
            </div>
            <div class="pos-drawer__section">
                <div class="pos-drawer__section-title">Timeline</div>
                <div style="font-size:0.85rem;color:var(--gray-500)">
                    <div style="padding:8px 0;border-bottom:1px solid var(--pos-border)"><i class="fas fa-plus-circle" style="color:var(--pos-success)"></i> Created ${formatDate(p.created_at)}</div>
                    ${p.deadline ? `<div style="padding:8px 0"><i class="fas fa-flag" style="color:var(--pos-warning)"></i> Deadline ${formatDate(p.deadline)}</div>` : ''}
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px">
                <button type="button" class="pos-btn pos-btn--primary pos-btn--sm" onclick='PositionsModule.edit(${JSON.stringify(p).replace(/'/g, "&#39;")}); PositionsModule.closeDrawer()'><i class="fas fa-edit"></i> Edit</button>
                <button type="button" class="pos-btn pos-btn--secondary pos-btn--sm" onclick='PositionsModule.duplicate(${JSON.stringify(p).replace(/'/g, "&#39;")}); PositionsModule.closeDrawer()'><i class="fas fa-copy"></i> Duplicate</button>
                <button type="button" class="pos-btn pos-btn--danger pos-btn--sm" onclick="PositionsModule.confirmDelete(${p.id}, '${escapeAttr(p.title)}', ${count}); PositionsModule.closeDrawer()"><i class="fas fa-trash"></i> Delete</button>
            </div>`;

        drawer.classList.add('open');
        overlay?.classList.add('open');
        drawer.setAttribute('aria-hidden', 'false');
    }

    function closeDrawer() {
        document.getElementById('previewDrawer')?.classList.remove('open');
        document.getElementById('drawerOverlay')?.classList.remove('open');
        document.getElementById('previewDrawer')?.setAttribute('aria-hidden', 'true');
    }

    /* ── Templates ── */
    function initTemplates() {
        document.querySelectorAll('[data-template]').forEach(btn => {
            btn.addEventListener('click', () => applyTemplate(btn.dataset.template));
        });
    }

    function applyTemplate(key) {
        const t = TEMPLATES[key];
        if (!t) return;
        openModal();
        document.getElementById('title').value = t.title;
        document.getElementById('description').value = t.description;
        document.getElementById('requirements').value = t.requirements;
        document.getElementById('min_experience_years').value = t.min_experience_years || 0;
        document.getElementById('salary_grade').value = t.salary_grade || '';
        if (t.salary_range) {
            const parsed = parseSalaryRange(t.salary_range);
            document.getElementById('salary_min').value = parsed.min;
            document.getElementById('salary_max').value = parsed.max;
        }
        document.getElementById('training_hours').value = t.training_hours || '';
        document.getElementById('location').value = t.location || '';
        if (t.employment_type) document.getElementById('employment_type').value = t.employment_type;
        syncCseCards(t.career_service_eligibilities || []);
        document.getElementById('templateModal')?.classList.remove('open');
        showToast(`Template "${t.title}" applied`, 'success');
    }

    /* ── Employment type search ── */
    function initEmploymentTypeSearch() {
        document.getElementById('etSearch')?.addEventListener('input', (e) => {
            const q = e.target.value.toLowerCase();
            document.querySelectorAll('.pos-et-item').forEach(item => {
                item.style.display = item.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    /* ── Animated counters ── */
    function initAnimatedCounters() {
        document.querySelectorAll('[data-count]').forEach(el => {
            const target = parseInt(el.dataset.count, 10) || 0;
            animateCounter(el, target);
        });
    }

    function animateCounter(el, target) {
        const duration = 1200;
        const start = performance.now();
        const step = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(target * eased);
            if (progress < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    }

    function animateCards() {
        document.querySelectorAll('.pos-card').forEach((card, i) => {
            card.style.animationDelay = `${i * 0.06}s`;
        });
        bindCardEvents(document.getElementById('cardView'));
    }

    function saveFilterPreset() {
        const f = getFilterValues();
        let presets = JSON.parse(localStorage.getItem(STORAGE_PRESETS) || '[]');
        presets.push({ ...f, name: `Preset ${presets.length + 1}`, savedAt: Date.now() });
        presets = presets.slice(-5);
        localStorage.setItem(STORAGE_PRESETS, JSON.stringify(presets));
        showToast('Filter preset saved', 'success');
        renderPresets();
    }

    function renderPresets() {
        const el = document.getElementById('filterPresets');
        if (!el) return;
        const presets = JSON.parse(localStorage.getItem(STORAGE_PRESETS) || '[]');
        el.innerHTML = presets.map((p, i) =>
            `<button type="button" class="pos-preset-chip" onclick="PositionsModule.applyPreset(${i})">${escapeHtml(p.name)}</button>`
        ).join('');
    }

    function applyPreset(index) {
        const presets = JSON.parse(localStorage.getItem(STORAGE_PRESETS) || '[]');
        const p = presets[index];
        if (!p) return;
        if (document.getElementById('globalSearch')) document.getElementById('globalSearch').value = p.query || '';
        ['filterStatus', 'filterEmployment', 'filterCampus', 'filterSalaryGrade', 'filterDeadline', 'sortBy'].forEach(id => {
            const el = document.getElementById(id);
            const key = id.replace('filter', '').replace('sortBy', 'sort').toLowerCase();
            const map = { status: 'status', employment: 'employment', campus: 'campus', salarygrade: 'salaryGrade', deadline: 'deadline', sort: 'sort' };
            if (el && p[map[key.replace('filter', '')]] !== undefined) el.value = p[map[key]] || '';
        });
        applyFilters();
    }

    function bindGlobalShortcuts() {
        document.querySelectorAll('.pos-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                const ripple = document.createElement('span');
                ripple.classList.add('pos-ripple');
                const rect = this.getBoundingClientRect();
                ripple.style.left = (e.clientX - rect.left) + 'px';
                ripple.style.top = (e.clientY - rect.top) + 'px';
                ripple.style.width = ripple.style.height = '20px';
                this.style.position = 'relative';
                this.appendChild(ripple);
                setTimeout(() => ripple.remove(), 600);
            });
        });
    }

    /* ── Modal API (global) ── */
    function openModal() {
        const form = document.getElementById('positionForm');
        form?.reset();
        document.getElementById('formAction').value = 'create';
        document.getElementById('modalTitle').textContent = 'Create New Position';
        document.getElementById('submitBtn').textContent = 'Publish Position';
        document.getElementById('statusDiv').style.display = 'none';
        document.getElementById('positionId').value = '';
        syncCseCards([]);
        currentStep = 0;
        goStep(0);
        document.getElementById('positionModal').classList.add('open');
        document.getElementById('positionModal').setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        document.getElementById('positionModal')?.classList.remove('open');
        document.getElementById('positionModal')?.setAttribute('aria-hidden', 'true');
    }

    function editPosition(position) {
        document.getElementById('formAction').value = 'update';
        document.getElementById('positionId').value = position.id;
        document.getElementById('title').value = position.title;
        document.getElementById('description').value = position.description;
        document.getElementById('requirements').value = position.requirements;
        document.getElementById('min_experience_years').value = position.min_experience_years;
        document.getElementById('max_applications').value = position.max_applications || '';
        document.getElementById('salary_grade').value = position.salary_grade || '';
        const salaries = parseSalaryRange(position.salary_range);
        document.getElementById('salary_min').value = salaries.min;
        document.getElementById('salary_max').value = salaries.max;
        document.getElementById('training_hours').value = position.training_hours || '';
        document.getElementById('location').value = position.location || '';
        document.getElementById('employment_type').value = position.employment_type;
        document.getElementById('deadline').value = position.deadline || '';
        document.getElementById('is_active').checked = position.is_active == 1;
        syncCseCards(position.career_service_eligibilities ? position.career_service_eligibilities.split(',') : []);
        document.getElementById('modalTitle').textContent = 'Edit Position';
        document.getElementById('submitBtn').innerHTML = '<i class="fas fa-check"></i> Update Position';
        currentStep = 0;
        goStep(0);
        document.getElementById('positionModal').classList.add('open');
        document.getElementById('positionModal').setAttribute('aria-hidden', 'false');
    }

    function duplicatePosition(position) {
        openModal();
        editPosition({ ...position, id: '' });
        document.getElementById('formAction').value = 'create';
        document.getElementById('positionId').value = '';
        document.getElementById('modalTitle').textContent = 'Duplicate Position';
        document.getElementById('submitBtn').textContent = 'Publish Position';
        document.getElementById('statusDiv').style.display = 'none';
        document.getElementById('title').value = position.title + ' (Copy)';
    }

    function confirmDelete(id, title, appCount) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deletePositionName').textContent = title || 'this position';
        document.getElementById('deleteAppCount').textContent = appCount || 0;
        document.getElementById('deleteModal').classList.add('open');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal')?.classList.remove('open');
    }

    function openEmploymentTypeModal() {
        document.getElementById('employmentTypeForm')?.reset();
        document.getElementById('employmentTypeModal').classList.add('open');
    }

    function closeEmploymentTypeModal() {
        document.getElementById('employmentTypeModal')?.classList.remove('open');
    }

    function confirmDeleteEmploymentType(id, name, isInUse) {
        const typeId = typeof id === 'string' ? parseInt(id, 10) : id;
        const inUse = isInUse === true || isInUse === 'true' || isInUse === '1';
        document.getElementById('deleteEmploymentTypeId').value = typeId;
        document.getElementById('employmentTypeDeleteName').textContent = name;
        document.getElementById('employmentTypeDeleteWarning').style.display = inUse ? 'none' : 'block';
        document.getElementById('employmentTypeInUseWarning').style.display = inUse ? 'block' : 'none';
        const btn = document.getElementById('deleteEmploymentTypeBtn');
        if (btn) { btn.disabled = inUse; btn.style.opacity = inUse ? '0.5' : '1'; }
        document.getElementById('deleteEmploymentTypeModal').classList.add('open');
    }

    function closeDeleteEmploymentTypeModal() {
        document.getElementById('deleteEmploymentTypeModal')?.classList.remove('open');
    }

    function openTemplateModal() {
        document.getElementById('templateModal')?.classList.add('open');
    }

    function closeTemplateModal() {
        document.getElementById('templateModal')?.classList.remove('open');
    }

    function showToast(msg, type) {
        if (typeof window.showToast === 'function') window.showToast(msg, type);
    }

    /* ── Utilities ── */
    function escapeHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function escapeAttr(str) {
        return escapeHtml(str).replace(/"/g, '&quot;');
    }

    function escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function formatDate(d) {
        if (!d) return '—';
        return new Date(d).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function sparklineSVG(seed) {
        const pts = [];
        for (let i = 0; i < 12; i++) pts.push(20 + ((seed + i * 7) % 15));
        const max = Math.max(...pts);
        const path = pts.map((v, i) => `${(i / 11) * 100},${100 - (v / max) * 80}`).join(' L ');
        return `<svg viewBox="0 0 100 100" preserveAspectRatio="none"><polyline fill="none" stroke="var(--pos-primary)" stroke-width="3" points="${path}" opacity="0.6"/></svg>`;
    }

    /* ── Public API ── */
    window.PositionsModule = {
        openModal, closeModal, edit: editPosition, duplicate: duplicatePosition,
        preview: (id) => { const p = allPositions.find(x => x.id == id); if (p) openDrawer(p); },
        closeDrawer, confirmDelete, closeDeleteModal,
        openEmploymentTypeModal, closeEmploymentTypeModal,
        confirmDeleteEmploymentType, closeDeleteEmploymentTypeModal,
        openTemplateModal, closeTemplateModal,
        search: (q) => { if (document.getElementById('globalSearch')) document.getElementById('globalSearch').value = q; applyFilters(q); closeSearchPalette(); },
        resetFilters, goClientPage, applyPreset, renderPresets, exportCSV, sparklineSVG
    };

    window.openModal = openModal;
    window.closeModal = closeModal;
    window.editPosition = editPosition;
    window.confirmDelete = (id) => confirmDelete(id, '', 0);
    window.closeDeleteModal = closeDeleteModal;
    window.openEmploymentTypeModal = openEmploymentTypeModal;
    window.closeEmploymentTypeModal = closeEmploymentTypeModal;
    window.confirmDeleteEmploymentType = confirmDeleteEmploymentType;
    window.closeDeleteEmploymentTypeModal = closeDeleteEmploymentTypeModal;

    window.onclick = function (event) {
        ['positionModal', 'deleteModal', 'employmentTypeModal', 'deleteEmploymentTypeModal', 'templateModal'].forEach(id => {
            const modal = document.getElementById(id);
            if (event.target === modal) {
                modal.classList.remove('open');
            }
        });
    };
})();
