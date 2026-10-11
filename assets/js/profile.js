/**
 * Applicant Profile — Premium UX Controller
 * Wizard navigation, completion tracking, uploads, auto-save draft
 */
(function () {
    'use strict';

    const STEPS = [
        { id: 'personal', label: 'Personal Information', icon: 'fa-user' },
        { id: 'education', label: 'Education', icon: 'fa-graduation-cap' },
        { id: 'experience', label: 'Experience', icon: 'fa-briefcase' },
        { id: 'eligibility', label: 'Eligibility', icon: 'fa-certificate' },
        { id: 'documents', label: 'Documents', icon: 'fa-folder-open' },
        { id: 'review', label: 'Review & Submit', icon: 'fa-check-circle' },
    ];

    const DRAFT_KEY = 'dumaran_profile_draft';

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
        initPasswordToggle();
        updateCompletion();
        buildReviewSummary();
        updateWizardHeader();

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
                if (Number.isNaN(idx)) return;
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
        if (index !== currentStep && formDirty) {
            if (!confirm('You have unsaved changes. Continue without saving?')) {
                return;
            }
        }
        currentStep = index;

        document.querySelectorAll('.profile-wizard-panel').forEach((panel, i) => {
            panel.classList.toggle('active', i === index);
            if (!animate) panel.style.animation = 'none';
        });

        document.querySelectorAll('[data-wizard-step]').forEach(btn => {
            const stepIdx = parseInt(btn.getAttribute('data-wizard-step'), 10);
            if (Number.isNaN(stepIdx)) return;
            btn.classList.toggle('active', stepIdx === index);
            btn.setAttribute('aria-current', stepIdx === index ? 'step' : 'false');
        });

        updateWizardHeader();

        const panel = document.getElementById('wizard-panel-' + STEPS[index].id);
        if (panel) {
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        if (index === STEPS.length - 1) buildReviewSummary();
    }

    function updateWizardHeader() {
        const stepLabel = document.getElementById('wizardStepLabel');
        const stepTitle = document.getElementById('wizardStepTitle');
        const fill = document.getElementById('wizardStepProgressFill');
        if (stepLabel) stepLabel.textContent = `Step ${currentStep + 1} of ${STEPS.length}`;
        if (stepTitle) stepTitle.textContent = STEPS[currentStep].label;
        if (fill) fill.style.width = `${((currentStep + 1) / STEPS.length) * 100}%`;
    }

    /* ── Dynamic sections (Promotion / Entry Level) ── */
    function initDynamicSections() {
        const promotionCb = document.querySelector('input[name="application_type[]"][value="PROMOTION"]');
        const entryCb = document.querySelector('input[name="application_type[]"][value="ENTRY LEVEL"]');
        const promotionSection = document.getElementById('promotion-section');
        const entrySection = document.getElementById('entry-level-section');

        function sync() {
            if (promotionSection) {
                const hasFile = promotionSection.querySelector('[data-file-ready="1"]');
                const show = (promotionCb && promotionCb.checked) || hasFile;
                promotionSection.classList.toggle('expanded', !!show);
            }
            if (entrySection) {
                const hasFile = entrySection.querySelector('[data-file-ready="1"]');
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
                    handleDropzoneFiles(zone, input);
                }
            });

            input.addEventListener('change', () => {
                handleDropzoneFiles(zone, input);
            });
        });
    }

    function handleDropzoneFiles(zone, input) {
        const errorEl = zone.querySelector('.profile-dropzone__error');
        const hint = zone.querySelector('.profile-dropzone__selected');
        zone.classList.remove('has-error');
        if (errorEl) errorEl.textContent = '';

        if (!input.files.length) {
            if (hint) hint.textContent = '';
            return;
        }

        const maxBytes = parseInt(zone.getAttribute('data-max-bytes') || '10485760', 10);
        const allowed = (zone.getAttribute('data-accept-ext') || '')
            .split(',')
            .map(s => s.trim().toLowerCase())
            .filter(Boolean);

        const invalid = [];
        const oversized = [];
        Array.from(input.files).forEach(file => {
            const ext = file.name.split('.').pop()?.toLowerCase() || '';
            if (allowed.length && !allowed.includes(ext)) invalid.push(file.name);
            if (file.size > maxBytes) oversized.push(file.name);
        });

        if (invalid.length || oversized.length) {
            input.value = '';
            if (hint) hint.textContent = '';
            zone.classList.add('has-error');
            if (errorEl) {
                const parts = [];
                if (invalid.length) parts.push(`Unsupported format: ${invalid.join(', ')}`);
                if (oversized.length) parts.push(`File exceeds 10MB: ${oversized.join(', ')}`);
                errorEl.textContent = parts.join(' ');
            }
            return;
        }

        markDirty();
        const summary = Array.from(input.files).map(f => {
            const kb = f.size >= 1024 ? (f.size / 1024).toFixed(1) + ' KB' : f.size + ' B';
            return `${f.name} (${kb})`;
        }).join('; ');
        if (hint) {
            hint.textContent = 'Selected (save profile to upload): ' + summary;
        }
    }

    /* ── Copy format ── */
    function initFormatCopyButtons() {
        document.querySelectorAll('[data-copy-format]').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                const text = btn.getAttribute('data-copy-format');
                navigator.clipboard.writeText(text).then(() => {
                    announceLive('Format copied to clipboard.');
                    if (typeof showSuccess === 'function') showSuccess('Format copied to clipboard');
                    const original = btn.textContent;
                    btn.textContent = 'Copied!';
                    setTimeout(() => { btn.textContent = original || 'Copy'; }, 2000);
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
                btn.classList.toggle('complete', done);
            });
        });

        const pct = Math.round((completed / checks.length) * 100);
        const fill = document.getElementById('profileProgressFill');
        const pctEl = document.getElementById('profileProgressPct');
        const heroPct = document.getElementById('profileHeroPct');

        if (fill) fill.style.width = pct + '%';
        const progressBar = document.getElementById('profileProgressBar');
        if (progressBar) progressBar.setAttribute('aria-valuenow', String(pct));
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
        const requiredTypes = ['APLET', 'PDS', 'WES'];
        let ready = 0;
        requiredTypes.forEach(type => {
            const section = document.querySelector(`.profile-doc-section[data-doc-type="${type}"]`);
            if (section && section.querySelector('[data-file-ready="1"]')) ready++;
        });
        return ready >= requiredTypes.length;
    }

    /* ── Review summary ── */
    function buildReviewSummary() {
        const container = document.getElementById('profileReviewSummary');
        const form = document.getElementById('profileForm');
        if (!container || !form) return;

        const sections = [
            {
                title: 'Personal Information',
                step: 0,
                complete: isPersonalComplete(),
                fields: [
                    { name: 'full_name', label: 'Full Name' },
                    { name: 'age', label: 'Age' },
                    { name: 'email', label: 'Email' },
                    { name: 'phone', label: 'Phone' },
                    { name: 'address', label: 'Address' },
                ],
                extra: () => {
                    const sex = form.querySelector('input[name="sex"]:checked');
                    const civil = form.querySelector('input[name="civil_status"]:checked');
                    return [
                        { label: 'Sex', value: sex ? sex.value : '—' },
                        { label: 'Civil Status', value: civil ? civil.value : '—' },
                    ];
                },
            },
            {
                title: 'Education',
                step: 1,
                complete: isEducationComplete(),
                fields: [
                    { name: 'highest_educational_attainment', label: 'Highest Attainment' },
                    { name: 'bachelors_degree', label: "Bachelor's Degree" },
                    { name: 'masters_degree', label: "Master's Degree" },
                    { name: 'doctorate_degree', label: 'Doctorate' },
                ],
            },
            {
                title: 'Work Experience',
                step: 2,
                complete: isExperienceComplete(),
                fields: [{ name: 'work_experience_display', label: 'Summary' }],
            },
            {
                title: 'Eligibility',
                step: 3,
                complete: isEligibilityComplete(),
                fields: [
                    { name: 'training_programs', label: 'Training Programs' },
                    { name: 'special_needs', label: 'Special Needs' },
                ],
                extra: () => {
                    const cse = Array.from(form.querySelectorAll('input[name="career_service_eligibilities[]"]:checked'))
                        .map(el => el.value);
                    const other = form.querySelector('[name="career_service_eligibilities_other"]');
                    if (cse.includes('Other') && other && other.value.trim()) {
                        cse.push(other.value.trim());
                    }
                    return [{ label: 'Career Service Eligibility', value: cse.length ? cse.join(', ') : '—' }];
                },
            },
            {
                title: 'Application Documents',
                step: 4,
                complete: isDocumentsComplete(),
                custom: () => {
                    const docSections = document.querySelectorAll('#wizard-panel-documents .profile-doc-section[data-doc-type]');
                    const items = [];
                    docSections.forEach(sec => {
                        const titleEl = sec.querySelector('.profile-doc-section__title');
                        const title = titleEl ? titleEl.textContent.trim() : sec.getAttribute('data-doc-type');
                        const ready = sec.querySelector('[data-file-ready="1"]');
                        items.push({
                            label: title,
                            value: ready ? 'Uploaded' : 'Missing or file not on server',
                        });
                    });
                    return items;
                },
            },
        ];

        container.innerHTML = sections.map(section => {
            let items = section.fields.map(f => {
                const el = form.querySelector(`[name="${f.name}"]`);
                const val = el ? (el.value || '—') : '—';
                return { label: f.label, value: val };
            });
            if (section.extra) items = items.concat(section.extra());
            if (section.custom) items = section.custom();

            const missingNote = section.complete
                ? ''
                : '<p class="profile-review-block__missing"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> Some required information in this section is incomplete.</p>';

            const grid = items.map(item =>
                `<div class="profile-review-item"><div class="profile-review-item__label">${escapeHtml(item.label)}</div><div class="profile-review-item__value">${escapeHtml(String(item.value))}</div></div>`
            ).join('');

            return `
                <section class="profile-review-block">
                    <div class="profile-review-block__head">
                        <h4>${escapeHtml(section.title)}</h4>
                        <button type="button" class="profile-btn profile-btn--sm profile-btn--secondary" data-goto-step="${section.step}"><i class="fas fa-pen" aria-hidden="true"></i> Edit</button>
                    </div>
                    ${missingNote}
                    <div class="profile-review-grid">${grid}</div>
                </section>`;
        }).join('');

        container.querySelectorAll('[data-goto-step]').forEach(btn => {
            btn.addEventListener('click', () => {
                const step = parseInt(btn.getAttribute('data-goto-step'), 10);
                if (!Number.isNaN(step)) goToStep(step);
            });
        });
    }

    function announceLive(message) {
        const live = document.getElementById('profileLiveStatus');
        if (live) live.textContent = message;
    }

    function initPasswordToggle() {
        document.querySelectorAll('.profile-password-toggle').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-password-target');
                const input = id ? document.getElementById(id) : null;
                if (!input) return;
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                const icon = btn.querySelector('i');
                if (icon) icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
            });
        });
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
            if (!formDirty || confirm('Discard unsaved changes?')) {
                restoreFormFromSnapshot(form);
                formDirty = false;
                bar.classList.remove('visible');
                document.querySelectorAll('.profile-dropzone__selected').forEach(el => { el.textContent = ''; });
                document.querySelectorAll('.profile-dropzone').forEach(z => {
                    z.classList.remove('has-error');
                    const err = z.querySelector('.profile-dropzone__error');
                    if (err) err.textContent = '';
                });
                initDynamicSections();
                initCSEOther();
                updateCompletion();
                buildReviewSummary();
            }
        });
    }

    function restoreFormFromSnapshot(form) {
        if (!initialFormSnapshot) {
            form.reset();
            return;
        }
        const snapshot = {};
        initialFormSnapshot.split('&').forEach(pair => {
            const [k, v] = pair.split('=');
            if (!k) return;
            const key = decodeURIComponent(k);
            const val = decodeURIComponent(v || '');
            if (!snapshot[key]) snapshot[key] = [];
            snapshot[key].push(val);
        });

        form.querySelectorAll('input, textarea, select').forEach(field => {
            const name = field.name;
            if (!name || name.includes('csrf') || name.includes('form_token')) return;
            if (field.type === 'file') {
                field.value = '';
                return;
            }
            if (field.type === 'checkbox' || field.type === 'radio') {
                const values = snapshot[name] || [];
                field.checked = values.includes(field.value);
                return;
            }
            const values = snapshot[name];
            if (values && values.length) {
                field.value = values[0];
            } else if (field.tagName === 'SELECT') {
                field.selectedIndex = 0;
            } else if (field.type !== 'hidden') {
                field.value = '';
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
