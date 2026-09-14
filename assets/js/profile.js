/**
 * Applicant Profile — Premium UX Controller
 * Wizard navigation, completion tracking, uploads, auto-save draft
 */
(function () {
    'use strict';

    const STEPS = [
        { id: 'personal', label: 'Personal', icon: 'fa-user' },
        { id: 'education', label: 'Education', icon: 'fa-graduation-cap' },
        { id: 'experience', label: 'Experience', icon: 'fa-briefcase' },
        { id: 'eligibility', label: 'Eligibility', icon: 'fa-certificate' },
        { id: 'documents', label: 'Documents', icon: 'fa-folder-open' },
        { id: 'review', label: 'Review', icon: 'fa-check-circle' },
    ];

    const DRAFT_KEY = 'wpu_profile_draft';

    let currentStep = 0;
    let formDirty = false;
    let initialFormSnapshot = '';

    /* ── Init ── */
    document.addEventListener('DOMContentLoaded', init);

    function init() {
        const form = document.getElementById('profileForm');
        if (!form) return;

        initWizard();
        initDropzones();
        initFormatCopyButtons();
        initDynamicSections();
        initCSEOther();
        initValidation();
        initCharCounters();
        initExperienceSearch();
        initConfirmDialogs();
        initPreviewModal();
        initNotifications();
        initActionBar(form);
        initAutoSaveDraft(form);
        initUnsavedWarning(form);
        updateCompletion();
        buildReviewSummary();

        if (form) {
            initialFormSnapshot = serializeForm(form);
        }

        // Hide skeleton after load
        document.querySelectorAll('.profile-skeleton-wrap').forEach(el => {
            el.classList.remove('profile-skeleton-wrap');
        });

        // Restore draft prompt
        maybeRestoreDraft(form);
    }

    /* ── Wizard ── */
    function initWizard() {
        const navButtons = document.querySelectorAll('[data-wizard-step]');
        navButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = parseInt(btn.getAttribute('data-wizard-step'), 10);
                goToStep(idx);
            });
        });

        document.querySelectorAll('[data-wizard-next]').forEach(btn => {
            btn.addEventListener('click', () => goToStep(currentStep + 1));
        });

        document.querySelectorAll('[data-wizard-prev]').forEach(btn => {
            btn.addEventListener('click', () => goToStep(currentStep - 1));
        });

        document.querySelectorAll('.profile-checklist-item').forEach(item => {
            item.addEventListener('click', () => {
                const step = item.getAttribute('data-goto-step');
                if (step !== null) goToStep(parseInt(step, 10));
            });
        });

        goToStep(0, false);
    }

    function goToStep(index, animate = true) {
        if (index < 0 || index >= STEPS.length) return;
        currentStep = index;

        document.querySelectorAll('.profile-wizard-panel').forEach((panel, i) => {
            panel.classList.toggle('active', i === index);
            if (!animate) panel.style.animation = 'none';
        });

        document.querySelectorAll('[data-wizard-step]').forEach(btn => {
            const stepIdx = parseInt(btn.getAttribute('data-wizard-step'), 10);
            btn.classList.toggle('active', stepIdx === index);
            btn.setAttribute('aria-current', stepIdx === index ? 'step' : 'false');
        });

        const panel = document.getElementById('wizard-panel-' + STEPS[index].id);
        if (panel) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        if (index === STEPS.length - 1) buildReviewSummary();
    }

    /* ── Dynamic sections (Promotion / Entry Level) ── */
    function initDynamicSections() {
        const promotionCb = document.querySelector('input[name="application_type[]"][value="PROMOTION"]');
        const entryCb = document.querySelector('input[name="application_type[]"][value="ENTRY LEVEL"]');
        const promotionSection = document.getElementById('promotion-section');
        const entrySection = document.getElementById('entry-level-section');

        function sync() {
            if (promotionSection) {
                const hasFile = promotionSection.querySelector('[data-has-file="1"]');
                const show = (promotionCb && promotionCb.checked) || hasFile;
                promotionSection.classList.toggle('expanded', !!show);
            }
            if (entrySection) {
                const hasFile = entrySection.querySelector('[data-has-file="1"]');
                const show = (entryCb && entryCb.checked) || hasFile;
                entrySection.classList.toggle('expanded', !!show);
            }
            updateCompletion();
        }

        document.querySelectorAll('input[name="application_type[]"]').forEach(cb => {
            cb.addEventListener('change', sync);
        });

        sync();
    }

    function initCSEOther() {
        const otherCb = document.getElementById('cse-other-checkbox');
        const otherInput = document.getElementById('cse-other-input');
        if (!otherCb || !otherInput) return;

        function sync() {
            otherInput.style.display = otherCb.checked ? 'block' : 'none';
            if (!otherCb.checked) otherInput.value = '';
            updateCompletion();
        }

        otherCb.addEventListener('change', sync);
        sync();
    }

    /* ── Dropzones ── */
    function initDropzones() {
        document.querySelectorAll('.profile-dropzone').forEach(zone => {
            const input = zone.querySelector('input[type="file"]');
            if (!input) return;

            ['dragenter', 'dragover'].forEach(evt => {
                zone.addEventListener(evt, e => {
                    e.preventDefault();
                    zone.classList.add('dragover');
                });
            });

            ['dragleave', 'drop'].forEach(evt => {
                zone.addEventListener(evt, e => {
                    e.preventDefault();
                    zone.classList.remove('dragover');
                });
            });

            zone.addEventListener('drop', e => {
                if (e.dataTransfer.files.length && input) {
                    input.files = e.dataTransfer.files;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });

            input.addEventListener('change', () => {
                if (!input.files.length) return;
                markDirty();
                simulateUploadProgress(zone);
                const names = Array.from(input.files).map(f => f.name).join(', ');
                const hint = zone.querySelector('.profile-dropzone__selected');
                if (hint) hint.textContent = 'Selected: ' + names;
            });
        });
    }

    function simulateUploadProgress(zone) {
        zone.classList.add('uploading');
        const fill = zone.querySelector('.profile-dropzone__progress-fill');
        if (!fill) return;
        let pct = 0;
        const interval = setInterval(() => {
            pct += 15;
            fill.style.width = Math.min(pct, 100) + '%';
            if (pct >= 100) {
                clearInterval(interval);
                setTimeout(() => zone.classList.remove('uploading'), 400);
            }
        }, 80);
    }

    /* ── Copy format ── */
    function initFormatCopyButtons() {
        document.querySelectorAll('[data-copy-format]').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                const text = btn.getAttribute('data-copy-format');
                navigator.clipboard.writeText(text).then(() => {
                    if (typeof showSuccess === 'function') showSuccess('Format copied to clipboard');
                    else btn.textContent = 'Copied!';
                    setTimeout(() => { btn.textContent = 'Copy'; }, 2000);
                });
            });
        });
    }

    /* ── Validation ── */
    function initValidation() {
        const form = document.getElementById('profileForm');
        if (!form) return;

        form.querySelectorAll('[required]').forEach(field => {
            const events = field.type === 'radio' || field.type === 'checkbox' ? ['change'] : ['blur', 'input'];
            events.forEach(evt => {
                field.addEventListener(evt, () => validateField(field));
            });
        });

        form.addEventListener('input', () => {
            markDirty();
            updateCompletion();
        });

        form.addEventListener('change', () => {
            markDirty();
            updateCompletion();
        });
    }

    function validateField(field) {
        const wrap = field.closest('.profile-field');
        if (!wrap) return true;

        let valid = true;
        if (field.type === 'email') {
            valid = !field.value || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value);
        } else if (field.hasAttribute('required')) {
            if (field.type === 'radio') {
                const group = document.querySelectorAll(`input[name="${field.name}"]`);
                valid = Array.from(group).some(r => r.checked);
            } else {
                valid = field.value.trim() !== '';
            }
        }

        wrap.classList.toggle('invalid', !valid && field.dataset.touched === '1');
        field.addEventListener('blur', () => { field.dataset.touched = '1'; }, { once: true });
        return valid;
    }

    /* ── Character counters ── */
    function initCharCounters() {
        document.querySelectorAll('[data-max-length]').forEach(field => {
            const max = parseInt(field.getAttribute('data-max-length'), 10);
            const counter = field.parentElement.querySelector('.profile-char-count');
            if (!counter) return;

            function update() {
                counter.textContent = `${field.value.length} / ${max}`;
            }
            field.addEventListener('input', update);
            update();
        });
    }

    /* ── Experience search ── */
    function initExperienceSearch() {
        const search = document.getElementById('expSearch');
        if (!search) return;

        search.addEventListener('input', () => {
            const q = search.value.toLowerCase();
            document.querySelectorAll('.profile-timeline-item').forEach(item => {
                const text = item.textContent.toLowerCase();
                item.classList.toggle('hidden', q.length > 0 && !text.includes(q));
            });
        });
    }

    /* ── Completion ── */
    function updateCompletion() {
        const checks = [
            { key: 'personal', test: isPersonalComplete, step: 0 },
            { key: 'education', test: isEducationComplete, step: 1 },
            { key: 'experience', test: isExperienceComplete, step: 2 },
            { key: 'eligibility', test: isEligibilityComplete, step: 3 },
            { key: 'documents', test: isDocumentsComplete, step: 4 },
        ];

        let completed = 0;
        const missing = [];

        checks.forEach(({ key, test, step }) => {
            const done = test();
            if (done) completed++;
            else missing.push({ key, step });

            const checklistItem = document.querySelector(`.profile-checklist-item[data-section="${key}"]`);
            if (checklistItem) {
                checklistItem.classList.toggle('complete', done);
                checklistItem.classList.toggle('incomplete', !done);
                checklistItem.querySelector('i').className = done ? 'fas fa-check-circle' : 'fas fa-circle';
            }

            document.querySelectorAll(`[data-wizard-step="${step}"]`).forEach(btn => {
                btn.classList.toggle('incomplete', !done);
            });
        });

        const pct = Math.round((completed / checks.length) * 100);
        const fill = document.getElementById('profileProgressFill');
        const pctEl = document.getElementById('profileProgressPct');
        const heroPct = document.getElementById('profileHeroPct');

        if (fill) fill.style.width = pct + '%';
        if (pctEl) pctEl.textContent = pct + '%';
        if (heroPct) heroPct.textContent = pct + '%';

        const missingEl = document.getElementById('profileMissingList');
        if (missingEl) {
            missingEl.innerHTML = missing.length
                ? missing.map(m => `<span class="profile-badge profile-badge--incomplete">${formatSectionName(m.key)}</span>`).join(' ')
                : '<span class="profile-badge profile-badge--verified"><i class="fas fa-check"></i> All complete</span>';
        }

        const statusBadge = document.getElementById('profileStatusBadge');
        if (statusBadge) {
            if (pct >= 100) {
                statusBadge.className = 'profile-badge profile-badge--verified';
                statusBadge.innerHTML = '<i class="fas fa-shield-check"></i> Verified';
            } else {
                statusBadge.className = 'profile-badge profile-badge--incomplete';
                statusBadge.innerHTML = '<i class="fas fa-clock"></i> In Progress';
            }
        }
    }

    function formatSectionName(key) {
        const map = { personal: 'Personal Info', education: 'Education', experience: 'Experience', eligibility: 'Eligibility', documents: 'Documents' };
        return map[key] || key;
    }

    function isPersonalComplete() {
        const form = document.getElementById('profileForm');
        if (!form) return false;
        const fields = ['full_name', 'age', 'email', 'address'];
        for (const name of fields) {
            const el = form.querySelector(`[name="${name}"]`);
            if (!el || !el.value.trim()) return false;
        }
        const sex = form.querySelector('input[name="sex"]:checked');
        const civil = form.querySelector('input[name="civil_status"]:checked');
        return !!(sex && civil);
    }

    function isEducationComplete() {
        const form = document.getElementById('profileForm');
        if (!form) return false;
        const h = form.querySelector('[name="highest_educational_attainment"]');
        return h && h.value.trim() !== '';
    }

    function isExperienceComplete() {
        const countEl = document.getElementById('experienceCount');
        const count = countEl ? parseInt(countEl.dataset.count, 10) : 0;
        return count > 0 || document.querySelectorAll('.profile-timeline-item:not(.hidden)').length > 0;
    }

    function isEligibilityComplete() {
        const form = document.getElementById('profileForm');
        if (!form) return false;
        return form.querySelectorAll('input[name="career_service_eligibilities[]"]:checked').length > 0;
    }

    function isDocumentsComplete() {
        const fileCards = document.querySelectorAll('.profile-file-card[data-has-file="1"]');
        return fileCards.length >= 3;
    }

    /* ── Review summary ── */
    function buildReviewSummary() {
        const container = document.getElementById('profileReviewSummary');
        const form = document.getElementById('profileForm');
        if (!container || !form) return;

        const fields = [
            { name: 'full_name', label: 'Full Name' },
            { name: 'age', label: 'Age' },
            { name: 'email', label: 'Email' },
            { name: 'phone', label: 'Phone' },
            { name: 'address', label: 'Address' },
            { name: 'highest_educational_attainment', label: 'Education' },
        ];

        container.innerHTML = fields.map(f => {
            const el = form.querySelector(`[name="${f.name}"]`);
            const val = el ? (el.value || '—') : '—';
            return `<div class="profile-review-item"><div class="profile-review-item__label">${f.label}</div><div class="profile-review-item__value">${escapeHtml(val)}</div></div>`;
        }).join('');

        const sex = form.querySelector('input[name="sex"]:checked');
        const civil = form.querySelector('input[name="civil_status"]:checked');
        container.innerHTML += `
            <div class="profile-review-item"><div class="profile-review-item__label">Sex</div><div class="profile-review-item__value">${sex ? escapeHtml(sex.value) : '—'}</div></div>
            <div class="profile-review-item"><div class="profile-review-item__label">Civil Status</div><div class="profile-review-item__value">${civil ? escapeHtml(civil.value) : '—'}</div></div>
        `;
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    /* ── Confirm dialogs ── */
    function initConfirmDialogs() {
        document.querySelectorAll('[data-confirm]').forEach(el => {
            el.addEventListener('click', e => {
                e.preventDefault();
                const msg = el.getAttribute('data-confirm') || 'Are you sure?';
                const href = el.getAttribute('href');
                const deleteField = el.dataset.deleteField;

                showConfirmModal(msg, () => {
                    if (deleteField) {
                        const hidden = document.getElementById(deleteField);
                        const form = document.getElementById('profileForm');
                        if (hidden && form) {
                            hidden.value = '1';
                            form.submit();
                            return;
                        }
                    }
                    if (href && href !== '#') window.location.href = href;
                });
            });
        });
    }

    function showConfirmModal(message, onConfirm) {
        let modal = document.getElementById('profileConfirmModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'profileConfirmModal';
            modal.className = 'profile-modal';
            modal.innerHTML = `
                <div class="profile-modal__backdrop"></div>
                <div class="profile-modal__dialog" role="alertdialog" aria-modal="true">
                    <div class="profile-modal__header">
                        <div class="profile-modal__avatar" id="confirmModalAvatar">!</div>
                        <h3 class="profile-modal__title">Confirm Action</h3>
                    </div>
                    <div class="profile-modal__body"><p id="confirmModalMessage"></p></div>
                    <div class="profile-modal__footer">
                        <button type="button" class="profile-btn profile-btn--secondary" id="confirmModalCancel">Cancel</button>
                        <button type="button" class="profile-btn profile-btn--danger-outline" id="confirmModalOk">Confirm</button>
                    </div>
                </div>`;
            document.body.appendChild(modal);
        }

        document.getElementById('confirmModalMessage').textContent = message;
        modal.classList.add('open');

        const avatar = document.getElementById('confirmModalAvatar');
        const heroAvatar = document.querySelector('.profile-avatar');
        if (avatar && heroAvatar) avatar.textContent = heroAvatar.textContent.trim().charAt(0);

        const close = () => modal.classList.remove('open');
        document.getElementById('confirmModalCancel').onclick = close;
        modal.querySelector('.profile-modal__backdrop').onclick = close;
        document.getElementById('confirmModalOk').onclick = () => {
            close();
            if (onConfirm) onConfirm();
        };
    }

    /* ── File preview modal ── */
    function initPreviewModal() {
        document.querySelectorAll('[data-preview]').forEach(link => {
            link.addEventListener('click', e => {
                e.preventDefault();
                const url = link.getAttribute('data-preview') || link.getAttribute('href');
                const isPdf = url.toLowerCase().endsWith('.pdf');
                openPreviewModal(url, isPdf);
            });
        });
    }

    function openPreviewModal(url, isPdf) {
        let modal = document.getElementById('profilePreviewModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'profilePreviewModal';
            modal.className = 'profile-modal';
            modal.innerHTML = `
                <div class="profile-modal__backdrop"></div>
                <div class="profile-modal__dialog profile-modal__dialog--wide" role="dialog" aria-modal="true" aria-label="File preview">
                    <div class="profile-modal__header">
                        <h3 class="profile-modal__title">File Preview</h3>
                        <button type="button" class="profile-btn profile-btn--icon profile-btn--secondary" id="previewModalClose" aria-label="Close"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="profile-modal__body" id="previewModalBody"></div>
                </div>`;
            document.body.appendChild(modal);
            modal.querySelector('.profile-modal__backdrop').addEventListener('click', () => modal.classList.remove('open'));
            document.getElementById('previewModalClose').addEventListener('click', () => modal.classList.remove('open'));
        }

        const body = document.getElementById('previewModalBody');
        if (isPdf) {
            body.innerHTML = `<iframe src="${url}" class="profile-preview-frame" title="PDF preview"></iframe>`;
        } else {
            body.innerHTML = `<div class="profile-preview-frame"><img src="${url}" alt="Document preview" loading="lazy"></div>`;
        }
        modal.classList.add('open');
    }

    /* ── Notifications auto-hide ── */
    function initNotifications() {
        document.querySelectorAll('.profile-notification[data-auto-hide]').forEach(n => {
            setTimeout(() => {
                n.style.opacity = '0';
                n.style.transform = 'translateY(-8px)';
                setTimeout(() => n.remove(), 300);
            }, 6000);
        });

        document.querySelectorAll('.profile-notification__close').forEach(btn => {
            btn.addEventListener('click', () => btn.closest('.profile-notification')?.remove());
        });

        // URL success/error toasts
        const params = new URLSearchParams(window.location.search);
        if (params.get('success') === 'profile_updated' && typeof showSuccess === 'function') {
            showSuccess('Profile updated successfully!');
        }
        if (params.get('error') && typeof showError === 'function') {
            showError(decodeURIComponent(params.get('error')));
        }
        if (params.get('info') && typeof showInfo === 'function') {
            showInfo(decodeURIComponent(params.get('info')));
        }
    }

    /* ── Sticky action bar ── */
    function initActionBar(form) {
        const bar = document.getElementById('profileActionBar');
        if (!bar) return;

        bar.querySelector('[data-action-cancel]')?.addEventListener('click', () => {
            if (confirm('Discard unsaved changes?')) {
                form.reset();
                formDirty = false;
                bar.classList.remove('visible');
                updateCompletion();
            }
        });
    }

    function markDirty() {
        const form = document.getElementById('profileForm');
        if (!form) return;
        formDirty = serializeForm(form) !== initialFormSnapshot;
        const bar = document.getElementById('profileActionBar');
        if (bar) bar.classList.toggle('visible', formDirty);
    }

    /* ── Auto-save draft ── */
    function initAutoSaveDraft(form) {
        let timer;
        form.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => saveDraft(form), 2000);
        });
    }

    function saveDraft(form) {
        const data = {};
        new FormData(form).forEach((val, key) => {
            if (key.includes('csrf') || key.includes('form_token') || key.includes('password')) return;
            if (data[key]) {
                if (!Array.isArray(data[key])) data[key] = [data[key]];
                data[key].push(val);
            } else {
                data[key] = val;
            }
        });
        try {
            localStorage.setItem(DRAFT_KEY, JSON.stringify({ savedAt: Date.now(), data }));
            const indicator = document.getElementById('draftSavedIndicator');
            if (indicator) {
                indicator.textContent = 'Draft saved';
                indicator.style.opacity = '1';
                setTimeout(() => { indicator.style.opacity = '0'; }, 2000);
            }
        } catch (_) { /* quota */ }
    }

    function maybeRestoreDraft(form) {
        try {
            const raw = localStorage.getItem(DRAFT_KEY);
            if (!raw) return;
            const { savedAt, data } = JSON.parse(raw);
            if (Date.now() - savedAt > 7 * 24 * 60 * 60 * 1000) {
                localStorage.removeItem(DRAFT_KEY);
                return;
            }
            if (!confirm('A saved draft was found. Restore it?')) return;

            Object.entries(data).forEach(([key, val]) => {
                const fields = form.querySelectorAll(`[name="${key}"], [name="${key}[]"]`);
                if (!fields.length) return;
                const values = Array.isArray(val) ? val : [val];
                fields.forEach((field, i) => {
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        field.checked = values.includes(field.value);
                    } else if (field.tagName === 'SELECT') {
                        field.value = values[0];
                    } else {
                        field.value = values[i] ?? values[0];
                    }
                });
            });
            initDynamicSections();
            initCSEOther();
            updateCompletion();
            markDirty();
        } catch (_) { /* ignore */ }
    }

    function serializeForm(form) {
        const fd = new FormData(form);
        const pairs = [];
        fd.forEach((v, k) => {
            if (!k.includes('csrf') && !k.includes('form_token') && !k.includes('password') && !k.includes('file')) {
                pairs.push(k + '=' + v);
            }
        });
        return pairs.sort().join('&');
    }

    /* ── Unsaved changes warning ── */
    function initUnsavedWarning(form) {
        window.addEventListener('beforeunload', e => {
            if (formDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        form.addEventListener('submit', () => {
            formDirty = false;
            localStorage.removeItem(DRAFT_KEY);
        });
    }

    // Expose for inline handlers compatibility
    window.toggleApplicationSections = function () {
        initDynamicSections();
    };
    window.toggleCSEOther = function () {
        initCSEOther();
    };
    window.profileGoToStep = goToStep;
})();
