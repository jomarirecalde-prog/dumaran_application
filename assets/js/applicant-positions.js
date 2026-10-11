/**
 * Dumaran Hiring System — Browse Jobs UI
 * Preserves modal / apply workflow; adds search, bookmarks, compare, theme.
 */
(function () {
    'use strict';

    const STORAGE_SAVED = 'dumaran_applicant_saved_jobs';
    const STORAGE_RECENT = 'dumaran_applicant_job_searches';

    let searchData = { titles: [], locations: [], keywords: [] };
    let compareIds = new Set();

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        loadSearchData();
        initSearch();
        initFilters();
        initModal();
        initBookmarks();
        initCompare();
        initSort();
        initCountUp();
        initFilterSheet();
        renderSavedSection();
    }

    function loadSearchData() {
        const el = document.getElementById('jobsSearchData');
        if (!el) return;
        try {
            searchData = JSON.parse(el.textContent);
        } catch (e) {
            searchData = { titles: [], locations: [], keywords: [] };
        }
    }

    /* ── Smart Search ── */
    function initSearch() {
        const inputs = [
            document.querySelector('#searchForm input[name="search"]'),
            document.getElementById('mobileSearchInput')
        ].filter(Boolean);

        inputs.forEach(input => {
            input.addEventListener('input', () => {
                syncSearchInputs(input.value);
                showSuggestions(input);
            });
            input.addEventListener('focus', () => showSuggestions(input));
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    syncSearchInputs(input.value);
                    saveRecentSearch(input.value);
                    hideAllDropdowns();
                    document.getElementById('searchForm')?.submit();
                }
            });
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.jobs-search-wrap')) hideAllDropdowns();
        });

        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const main = document.querySelector('#searchForm input[name="search"]');
                main?.focus();
            }
        });
    }

    function showSuggestions(input) {
        const wrap = input.closest('.jobs-search-wrap');
        const dropdown = wrap?.querySelector('.jobs-search-dropdown');
        if (!dropdown) return;

        const q = input.value.toLowerCase().trim();
        let html = '';

        const recent = JSON.parse(localStorage.getItem(STORAGE_RECENT) || '[]');
        if (recent.length) {
            html += '<div class="jobs-search-dropdown__section">Recent Searches</div>';
            recent.slice(0, 4).forEach(term => {
                if (!q || term.toLowerCase().includes(q)) {
                    html += suggestionItem(term, 'fa-history');
                }
            });
        }

        const popular = searchData.keywords?.length ? searchData.keywords : ['Administrative', 'Accounting', 'Instructor', 'Full Time'];
        html += '<div class="jobs-search-dropdown__section">Popular Keywords</div>';
        popular.filter(k => !q || k.toLowerCase().includes(q)).slice(0, 4).forEach(k => {
            html += suggestionItem(k, 'fa-fire');
        });

        const titles = (searchData.titles || []).filter(t => !q || t.toLowerCase().includes(q)).slice(0, 5);
        if (titles.length) {
            html += '<div class="jobs-search-dropdown__section">Job Titles</div>';
            titles.forEach(t => html += suggestionItem(t, 'fa-briefcase'));
        }

        dropdown.innerHTML = html || '<div class="jobs-search-dropdown__section">No suggestions</div>';
        dropdown.classList.add('open');

        dropdown.querySelectorAll('.jobs-search-dropdown__item').forEach(btn => {
            btn.addEventListener('click', () => {
                input.value = btn.dataset.value;
                syncSearchInputs(input.value);
                saveRecentSearch(btn.dataset.value);
                hideAllDropdowns();
                document.getElementById('searchForm')?.submit();
            });
        });
    }

    function suggestionItem(value, icon) {
        return `<button type="button" class="jobs-search-dropdown__item" data-value="${escapeAttr(value)}"><i class="fas ${icon}"></i>${escapeHtml(value)}</button>`;
    }

    function syncSearchInputs(val) {
        document.querySelectorAll('#searchForm input[name="search"], #mobileSearchInput').forEach(el => {
            el.value = val;
        });
    }

    function hideAllDropdowns() {
        document.querySelectorAll('.jobs-search-dropdown').forEach(d => d.classList.remove('open'));
    }

    function saveRecentSearch(q) {
        if (!q.trim()) return;
        let recent = JSON.parse(localStorage.getItem(STORAGE_RECENT) || '[]');
        recent = [q, ...recent.filter(r => r !== q)].slice(0, 8);
        localStorage.setItem(STORAGE_RECENT, JSON.stringify(recent));
    }

    /* ── Filters ── */
    function initFilters() {
        document.getElementById('filterExperience')?.addEventListener('change', (e) => {
            const hidden = document.querySelector('#searchForm input[name="experience"]');
            if (hidden) hidden.value = e.target.value;
            document.getElementById('searchForm')?.submit();
        });

        document.getElementById('filterExperienceMobile')?.addEventListener('change', (e) => {
            const hidden = document.querySelector('#searchForm input[name="experience"]');
            if (hidden) hidden.value = e.target.value;
        });

        document.getElementById('applyMobileFilters')?.addEventListener('click', () => {
            const exp = document.getElementById('filterExperienceMobile');
            const hidden = document.querySelector('#searchForm input[name="experience"]');
            if (hidden && exp) hidden.value = exp.value;
            closeFilterSheet();
            document.getElementById('searchForm')?.submit();
        });

        document.getElementById('resetFiltersBtn')?.addEventListener('click', () => {
            window.location.href = 'positions.php';
        });
    }

    function initFilterSheet() {
        document.getElementById('openFilterSheet')?.addEventListener('click', openFilterSheet);
        document.getElementById('closeFilterSheet')?.addEventListener('click', closeFilterSheet);
        document.getElementById('filterSheetOverlay')?.addEventListener('click', closeFilterSheet);
    }

    function openFilterSheet() {
        document.getElementById('filterSheetOverlay')?.classList.add('open');
        document.getElementById('filterSheet')?.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeFilterSheet() {
        document.getElementById('filterSheetOverlay')?.classList.remove('open');
        document.getElementById('filterSheet')?.classList.remove('open');
        document.body.style.overflow = '';
    }

    /* ── Client-side sort (current page only) ── */
    function initSort() {
        document.getElementById('sortJobs')?.addEventListener('change', (e) => {
            sortVisibleCards(e.target.value);
        });
        document.getElementById('sortJobsSidebar')?.addEventListener('change', (e) => {
            const other = document.getElementById('sortJobs');
            if (other) other.value = e.target.value;
            sortVisibleCards(e.target.value);
        });
    }

    function sortVisibleCards(sort) {
        const grid = document.getElementById('jobsGrid');
        if (!grid) return;
        const cards = Array.from(grid.querySelectorAll('.position-item'));
        cards.sort((a, b) => {
            const da = JSON.parse(a.getAttribute('data-position') || '{}');
            const db = JSON.parse(b.getAttribute('data-position') || '{}');
            switch (sort) {
                case 'salary':
                    return parseSalary(db.salary_range) - parseSalary(da.salary_range);
                case 'match':
                    return (parseInt(db.match_score, 10) || 0) - (parseInt(da.match_score, 10) || 0);
                case 'deadline':
                    return (da.deadline_raw || '9999') > (db.deadline_raw || '9999') ? 1 : -1;
                case 'applications':
                    return (parseInt(db.application_count, 10) || 0) - (parseInt(da.application_count, 10) || 0);
                default:
                    return (parseInt(db.id, 10) || 0) - (parseInt(da.id, 10) || 0);
            }
        });
        cards.forEach(c => grid.appendChild(c));
    }

    function parseSalary(range) {
        if (!range) return 0;
        const m = String(range).replace(/[^\d]/g, '');
        return parseInt(m, 10) || 0;
    }

    /* ── Modal (preserved logic + enhancements) ── */
    function initModal() {
        const modal = document.getElementById('positionModal');
        const modalClose = document.getElementById('modalClose');
        if (!modal) return;

        modalClose?.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('active')) closeModal();
        });

        document.querySelectorAll('.view-details-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const item = this.closest('.position-item');
                const json = item?.getAttribute('data-position');
                if (json) {
                    try {
                        openModal(JSON.parse(json));
                    } catch (err) {
                        console.error('Error parsing position data:', err);
                    }
                }
            });
        });

        document.querySelectorAll('.jobs-card--recommended').forEach(card => {
            card.addEventListener('click', (e) => {
                if (e.target.closest('a, button')) return;
                const json = card.getAttribute('data-position');
                if (json) {
                    try { openModal(JSON.parse(json)); } catch (err) { /* ignore */ }
                }
            });
        });
    }

    function closeModal() {
        const modal = document.getElementById('positionModal');
        modal?.classList.remove('active');
        document.body.style.overflow = '';
    }

    function openModal(positionData) {
        const modal = document.getElementById('positionModal');
        if (!modal) return;

        const modalTitleElement = document.querySelector('.modal-header-content h2');
        if (modalTitleElement) modalTitleElement.textContent = positionData.title;
        setText('modalTitleValue', positionData.title);

        const headerMeta = document.getElementById('modalHeaderMeta');
        if (headerMeta) {
            const type = (positionData.employment_type || '').replace(/-/g, ' ');
            headerMeta.innerHTML = `
                <span><i class="fas fa-briefcase"></i> ${escapeHtml(type)}</span>
                ${positionData.location ? `<span><i class="fas fa-map-marker-alt"></i> ${escapeHtml(positionData.location)}</span>` : ''}
                ${positionData.match_score ? `<span><i class="fas fa-chart-line"></i> ${positionData.match_score}% Match</span>` : ''}
            `;
        }

        const employmentType = (positionData.employment_type || '').replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        setText('modalEmploymentType', employmentType);
        setText('modalDescription', positionData.description || '');

        const requirementsSection = document.getElementById('modalRequirementsSection');
        const requirementsList = document.getElementById('modalRequirements');
        if (positionData.requirements && positionData.requirements.trim() && requirementsSection && requirementsList) {
            requirementsSection.style.display = 'block';
            const requirements = positionData.requirements.split('\n').filter(req => req.trim());
            requirementsList.innerHTML = requirements.map(req => {
                const temp = document.createElement('div');
                temp.textContent = req.trim();
                return `<li><i class="fas fa-check-circle"></i><span>${temp.innerHTML}</span></li>`;
            }).join('');
        } else if (requirementsSection) {
            requirementsSection.style.display = 'none';
        }

        setText('modalMinExperience', positionData.min_experience_years || '0');
        setText('modalMaxApplications', positionData.max_applications || 'N/A');
        setText('modalSalaryRange', positionData.salary_range || 'N/A');
        setText('modalSalaryGrade', positionData.salary_grade || 'N/A');
        setText('modalTrainingHours', positionData.training_hours || 'N/A');
        setText('modalLocation', positionData.location || 'N/A');

        populateSalaryCard(positionData);
        populateCSE(positionData);
        populateDeadline(positionData);
        populateMatchPanel(positionData);
        populateRequirementChecklist(positionData);
        populateTimeline(positionData);

        setText('modalUserExperience', parseFloat(positionData.user_experience).toFixed(1));
        setText('modalRequiredExperience', positionData.min_experience_years);

        renderRequirementStatus(positionData);
        renderModalActions(positionData);
        renderApplicationCapacity(positionData);

        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function setText(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    function populateSalaryCard(data) {
        const el = document.getElementById('modalSalaryCard');
        if (!el) return;
        el.innerHTML = `
            <div class="jobs-salary-card__item"><label>Salary</label><span>${escapeHtml(data.salary_range || 'N/A')}</span></div>
            <div class="jobs-salary-card__item"><label>Salary Grade</label><span>${escapeHtml(data.salary_grade || 'N/A')}</span></div>
            <div class="jobs-salary-card__item"><label>Employment Type</label><span>${escapeHtml((data.employment_type || '').replace(/-/g, ' '))}</span></div>
            <div class="jobs-salary-card__item"><label>Training Hours</label><span>${escapeHtml(String(data.training_hours || 'N/A'))}</span></div>
        `;
    }

    function populateCSE(positionData) {
        const cseElement = document.getElementById('modalCSE');
        if (!cseElement) return;

        const cseLabels = {
            'Professional Eligibility': 'For first-level and second-level positions.',
            'Sub-Professional Eligibility': 'Qualifies for first-level positions.',
            'Career Service Executive Eligibility (CSE-E)': 'For high-level executive positions.',
            'Bar/Board Eligibility (RA 1080)': 'For licensed professionals.'
        };

        const applicantCSE = JSON.parse(positionData.applicant_cse || '[]');

        if (positionData.career_service_eligibilities && positionData.career_service_eligibilities.trim()) {
            const cseValues = positionData.career_service_eligibilities.split(',').map(v => v.trim()).filter(Boolean);
            cseElement.innerHTML = '<div class="modal-cse-cards">' + cseValues.map(value => {
                const met = applicantCSE.includes(value);
                const desc = cseLabels[value] || '';
                return `<div class="modal-cse-card ${met ? 'met' : 'miss'}">
                    <div class="modal-cse-card__head">
                        <span>${escapeHtml(value)}</span>
                        <span class="modal-cse-card__status ${met ? 'ok' : 'miss'}">${met ? 'Completed' : 'Missing'}</span>
                    </div>
                    <div class="modal-cse-card__desc">${escapeHtml(desc)}${!met ? ' Update your profile to add this eligibility.' : ''}</div>
                </div>`;
            }).join('') + '</div>';
        } else {
            cseElement.innerHTML = '<span style="color:var(--gray-500)">No CSE requirement for this position.</span>';
        }
    }

    function populateDeadline(positionData) {
        if (positionData.deadline || positionData.deadline_raw) {
            setText('modalDeadline', positionData.deadline ||
                (positionData.deadline_raw ? new Date(positionData.deadline_raw).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'));
        } else {
            setText('modalDeadline', 'N/A');
        }
    }

    function populateMatchPanel(data) {
        const panel = document.getElementById('modalMatchPanel');
        if (!panel) return;
        const score = parseInt(data.match_score, 10) || 0;
        const label = data.match_label || 'Match';
        const desc = data.match_desc || '';
        const color = data.match_color || 'green';
        panel.innerHTML = `
            <div class="jobs-match-panel__score jobs-match-panel__score--${color}">${score}%</div>
            <div class="jobs-match-panel__title">${escapeHtml(label)}</div>
            <div class="jobs-match-panel__desc">${escapeHtml(desc)}</div>
        `;
    }

    function populateRequirementChecklist(data) {
        const el = document.getElementById('modalReqChecklist');
        if (!el) return;
        const checks = JSON.parse(data.requirement_checks || '[]');
        if (!checks.length) {
            el.innerHTML = '';
            return;
        }
        const passed = checks.filter(c => c.met).length;
        const pct = Math.round((passed / checks.length) * 100);
        el.innerHTML = `
            <div class="jobs-req-checklist">
                <div class="jobs-req-checklist__title">Requirements</div>
                <div class="jobs-req-checklist__progress"><div class="jobs-req-checklist__fill" style="width:${pct}%"></div></div>
                ${checks.map(c => `
                    <div class="jobs-req-item">
                        <span>${escapeHtml(c.label)}</span>
                        <span class="jobs-req-item__status ${c.met ? 'jobs-req-item__status--ok' : 'jobs-req-item__status--miss'}">${c.met ? 'Complete' : 'Missing'}</span>
                    </div>
                `).join('')}
            </div>
        `;
    }

    function populateTimeline(data) {
        const el = document.getElementById('modalTimeline');
        if (!el) return;
        el.innerHTML = `
            <div class="jobs-timeline">
                <div class="jobs-timeline__item">
                    <div class="jobs-timeline__label">Posted</div>
                    <div class="jobs-timeline__date">${escapeHtml(data.posted_label || 'Recently')}</div>
                </div>
                <div class="jobs-timeline__item">
                    <div class="jobs-timeline__label">Application Deadline</div>
                    <div class="jobs-timeline__date">${escapeHtml(data.deadline || 'Open')}</div>
                </div>
                <div class="jobs-timeline__item">
                    <div class="jobs-timeline__label">Interview</div>
                    <div class="jobs-timeline__date">After shortlisting</div>
                </div>
                <div class="jobs-timeline__item">
                    <div class="jobs-timeline__label">Hiring Decision</div>
                    <div class="jobs-timeline__date">Upon completion of process</div>
                </div>
            </div>
        `;
    }

    function renderRequirementStatus(positionData) {
        const requirementStatus = document.getElementById('modalRequirementStatus');
        if (!requirementStatus) return;

        const meetsRequirements = positionData.meets_requirements === '1';
        const meetsCSE = positionData.meets_cse === '1';
        const hasCSERequirement = positionData.has_cse_requirement === '1';
        const userExp = parseFloat(positionData.user_experience);
        const minExp = parseFloat(positionData.min_experience_years);
        const needed = minExp - userExp;

        let statusHTML = '';
        let allRequirementsMet = true;

        if (needed > 0) {
            allRequirementsMet = false;
            statusHTML += `<div class="alert alert-warning" style="margin-bottom:12px"><i class="fas fa-exclamation-triangle"></i><div><strong>Experience:</strong> You need ${needed.toFixed(1)} more years of experience</div></div>`;
        }

        if (hasCSERequirement) {
            if (!meetsCSE) {
                allRequirementsMet = false;
                const requiredCSE = JSON.parse(positionData.required_cse || '[]');
                const applicantCSE = JSON.parse(positionData.applicant_cse || '[]');
                const cseMessage = positionData.cse_validation_message || '';
                statusHTML += `<div class="alert alert-danger" style="margin-bottom:12px"><i class="fas fa-times-circle"></i><div><strong>Career Service Eligibility:</strong> ${escapeHtml(cseMessage)}</div><div style="margin-top:8px;font-size:0.9rem"><strong>Required:</strong> ${requiredCSE.length ? requiredCSE.join(', ') : 'N/A'}<br><strong>You have:</strong> ${applicantCSE.length ? applicantCSE.join(', ') : 'None'}</div></div>`;
            } else {
                const matchingCSE = JSON.parse(positionData.required_cse || '[]').filter(cse =>
                    JSON.parse(positionData.applicant_cse || '[]').includes(cse)
                );
                statusHTML += `<div class="alert alert-success" style="margin-bottom:12px"><i class="fas fa-check-circle"></i><div><strong>Career Service Eligibility:</strong> You meet the requirement!</div><div style="margin-top:8px;font-size:0.9rem">Matching eligibility: ${matchingCSE.join(', ')}</div></div>`;
            }
        }

        if (allRequirementsMet && meetsRequirements) {
            statusHTML = `<div class="alert alert-success"><i class="fas fa-check-circle"></i><div>You meet all requirements!</div></div>` + statusHTML;
        } else if (!allRequirementsMet && !statusHTML.includes('alert-danger') && !statusHTML.includes('alert-warning')) {
            statusHTML = `<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i><div>You don't meet all requirements</div></div>` + statusHTML;
        }

        requirementStatus.innerHTML = statusHTML || `<div class="alert alert-info"><i class="fas fa-info-circle"></i><div>Checking requirements...</div></div>`;
    }

    function renderModalActions(positionData) {
        const actions = document.getElementById('modalActions');
        const applyTime = document.getElementById('modalApplyTime');
        if (!actions) return;

        const hasApplied = positionData.has_applied === '1';
        const meetsAllRequirements = positionData.meets_requirements === '1';
        const meetsCSE = positionData.meets_cse === '1';
        const hasCSERequirement = positionData.has_cse_requirement === '1';
        const userExp = parseFloat(positionData.user_experience);
        const minExp = parseFloat(positionData.min_experience_years);
        const needed = minExp - userExp;
        const estMin = positionData.est_apply_minutes || '3';

        if (hasApplied) {
            actions.innerHTML = `<button class="jobs-btn jobs-btn--primary jobs-btn--lg" disabled aria-disabled="true"><i class="fas fa-check-circle"></i> Already Applied</button><p style="text-align:center;color:var(--gray-500);margin-top:12px;font-size:0.875rem">Status: ${positionData.application_status.charAt(0).toUpperCase() + positionData.application_status.slice(1)}</p>`;
            if (applyTime) applyTime.textContent = '';
        } else if (!meetsAllRequirements) {
            let lockMessage = 'Requirements Not Met';
            if (hasCSERequirement && !meetsCSE) lockMessage = 'Missing Required CSE Eligibility';
            else if (needed > 0) lockMessage = 'Insufficient Experience';
            actions.innerHTML = `<button class="jobs-btn jobs-btn--primary jobs-btn--lg" disabled aria-disabled="true" title="${lockMessage}"><i class="fas fa-lock"></i> ${lockMessage}</button>`;
            if (applyTime) applyTime.textContent = 'Complete missing requirements to unlock application';
        } else {
            actions.innerHTML = `<a href="${positionData.apply_url}" class="jobs-btn jobs-btn--primary jobs-btn--lg"><i class="fas fa-paper-plane"></i> Apply Now</a>`;
            if (applyTime) applyTime.textContent = `Estimated ${estMin}-minute application`;
        }
    }

    function renderApplicationCapacity(positionData) {
        const section = document.getElementById('modalApplicationsSection');
        const ring = document.getElementById('modalCapacityRing');
        const countEl = document.getElementById('modalApplicationCount');
        if (!section) return;

        if (positionData.max_applications) {
            section.style.display = 'block';
            const count = parseInt(positionData.application_count, 10) || 0;
            const max = parseInt(positionData.max_applications, 10) || 1;
            const pct = Math.min(100, Math.round((count / max) * 100));
            const remaining = Math.max(0, max - count);
            const circumference = 2 * Math.PI * 42;
            const offset = circumference - (pct / 100) * circumference;

            if (ring) {
                ring.innerHTML = `
                    <div class="jobs-capacity__ring">
                        <svg width="100" height="100" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="42" fill="none" stroke="var(--gray-200)" stroke-width="8"/>
                            <circle cx="50" cy="50" r="42" fill="none" stroke="var(--jobs-primary)" stroke-width="8"
                                stroke-dasharray="${circumference}" stroke-dashoffset="${offset}" stroke-linecap="round"
                                style="transition:stroke-dashoffset 0.8s ease"/>
                        </svg>
                        <div class="jobs-capacity__ring-text">
                            <span class="jobs-capacity__count">${count}</span>
                            <span class="jobs-capacity__max">of ${max}</span>
                        </div>
                    </div>
                    <div class="jobs-capacity__slots">${remaining} slot${remaining !== 1 ? 's' : ''} remaining</div>
                `;
            }
            if (countEl) countEl.textContent = `${count} of ${max} applications received`;
            const progress = document.getElementById('modalApplicationProgress');
            if (progress) progress.style.width = pct + '%';
        } else {
            section.style.display = 'none';
        }
    }

    /* ── Bookmarks ── */
    function initBookmarks() {
        document.querySelectorAll('[data-save-job]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                toggleSave(parseInt(btn.dataset.saveJob, 10), btn);
            });
        });
        syncBookmarkButtons();
        document.getElementById('showSavedBtn')?.addEventListener('click', () => {
            const section = document.getElementById('savedJobsSection');
            section?.classList.toggle('visible');
            section?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    function getSavedJobs() {
        return JSON.parse(localStorage.getItem(STORAGE_SAVED) || '[]');
    }

    function toggleSave(id, btn) {
        let saved = getSavedJobs();
        if (saved.includes(id)) {
            saved = saved.filter(x => x !== id);
            btn.classList.remove('saved');
            btn.setAttribute('aria-pressed', 'false');
        } else {
            saved.push(id);
            btn.classList.add('saved');
            btn.setAttribute('aria-pressed', 'true');
            if (typeof window.showToast === 'function') window.showToast('Job saved to bookmarks', 'success');
        }
        localStorage.setItem(STORAGE_SAVED, JSON.stringify(saved));
        renderSavedSection();
        updateSavedCount();
    }

    function syncBookmarkButtons() {
        const saved = getSavedJobs();
        document.querySelectorAll('[data-save-job]').forEach(btn => {
            const id = parseInt(btn.dataset.saveJob, 10);
            const isSaved = saved.includes(id);
            btn.classList.toggle('saved', isSaved);
            btn.setAttribute('aria-pressed', isSaved ? 'true' : 'false');
        });
        updateSavedCount();
    }

    function updateSavedCount() {
        const count = getSavedJobs().length;
        const el = document.getElementById('savedJobsCount');
        if (el) el.textContent = count;
    }

    function renderSavedSection() {
        const container = document.getElementById('savedJobsGrid');
        if (!container) return;
        const saved = getSavedJobs();
        if (!saved.length) {
            container.innerHTML = '<p style="color:var(--gray-500);font-size:0.9rem">No saved jobs yet. Click the bookmark icon on any job card.</p>';
            return;
        }
        const cards = saved.map(id => document.querySelector(`.position-item[data-job-id="${id}"]`)).filter(Boolean);
        if (!cards.length) {
            container.innerHTML = '<p style="color:var(--gray-500);font-size:0.9rem">Saved jobs are on other pages. Browse all jobs to find them.</p>';
            return;
        }
        container.innerHTML = '';
        cards.forEach(card => {
            const clone = card.cloneNode(true);
            clone.classList.add('jobs-card--saved-clone');
            rebindCloneEvents(clone);
            container.appendChild(clone);
        });
    }

    function rebindCloneEvents(card) {
        card.querySelector('.view-details-btn')?.addEventListener('click', function () {
            const json = card.getAttribute('data-position');
            if (json) try { openModal(JSON.parse(json)); } catch (e) { /* ignore */ }
        });
        card.querySelector('[data-save-job]')?.addEventListener('click', (e) => {
            e.preventDefault();
            toggleSave(parseInt(e.currentTarget.dataset.saveJob, 10), e.currentTarget);
            renderSavedSection();
        });
    }

    /* ── Compare ── */
    function initCompare() {
        document.querySelectorAll('[data-compare-id]').forEach(cb => {
            cb.addEventListener('change', () => {
                const id = parseInt(cb.dataset.compareId, 10);
                if (cb.checked) compareIds.add(id); else compareIds.delete(id);
                updateCompareBar();
            });
        });
        document.getElementById('compareJobsBtn')?.addEventListener('click', openCompareModal);
        document.getElementById('clearCompareBtn')?.addEventListener('click', () => {
            compareIds.clear();
            document.querySelectorAll('[data-compare-id]').forEach(cb => { cb.checked = false; });
            updateCompareBar();
        });
        document.getElementById('closeCompareModal')?.addEventListener('click', closeCompareModal);
        document.getElementById('compareModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'compareModal') closeCompareModal();
        });
    }

    function updateCompareBar() {
        const bar = document.getElementById('compareBar');
        const count = document.getElementById('compareCount');
        if (count) count.textContent = compareIds.size;
        bar?.classList.toggle('visible', compareIds.size >= 2);
    }

    function openCompareModal() {
        const modal = document.getElementById('compareModal');
        const body = document.getElementById('compareTableBody');
        if (!modal || !body || compareIds.size < 2) return;

        const rows = [
            ['Title', 'title'],
            ['Location', 'location'],
            ['Employment Type', 'employment_type'],
            ['Salary', 'salary_range'],
            ['Experience', d => (d.min_experience_years || 0) + ' years'],
            ['Training', d => d.training_hours ? d.training_hours + ' hours' : '—'],
            ['Deadline', 'deadline'],
            ['Match', d => (d.match_score || 0) + '%']
        ];

        const jobs = Array.from(compareIds).map(id => {
            const card = document.querySelector(`.position-item[data-job-id="${id}"]`);
            return card ? JSON.parse(card.getAttribute('data-position') || '{}') : null;
        }).filter(Boolean);

        let html = '<table class="jobs-compare-table"><thead><tr><th>Attribute</th>';
        jobs.forEach(j => { html += `<th>${escapeHtml(j.title)}</th>`; });
        html += '</tr></thead><tbody>';

        rows.forEach(([label, key]) => {
            html += `<tr><th>${label}</th>`;
            jobs.forEach(j => {
                const val = typeof key === 'function' ? key(j) : (j[key] || '—');
                html += `<td>${escapeHtml(String(val))}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody></table>';
        body.innerHTML = html;
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeCompareModal() {
        document.getElementById('compareModal')?.classList.remove('open');
        document.body.style.overflow = '';
    }

    /* ── Count-up stats ── */
    function initCountUp() {
        document.querySelectorAll('[data-count]').forEach(el => {
            const target = parseInt(el.dataset.count, 10) || 0;
            animateCount(el, target);
        });
    }

    function animateCount(el, target) {
        const duration = 1000;
        const start = performance.now();
        const step = (now) => {
            const p = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(target * eased);
            if (p < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
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

    window.ApplicantJobs = { openModal, closeModal, toggleSave };
})();
