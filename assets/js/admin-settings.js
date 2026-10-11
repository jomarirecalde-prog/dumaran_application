/**
 * Dumaran Hiring Admin Settings — Client-side UX enhancements
 * Presentation layer only; does not alter form submission logic.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        initGeneralSettings();
        initAdminAccounts();
        initSystemLogs();
        initQuickActions();
        initSessionToasts();
        adjustAdminGrid();
        window.addEventListener('resize', adjustAdminGrid);
    }

    /* ── General Settings ── */
    function initGeneralSettings() {
        const form = document.getElementById('generalSettingsForm');
        if (!form) return;

        initEmailValidation(form);
        initStepper(form);
        initToggleSync(form);
        initStickySave(form);
    }

    function initEmailValidation(form) {
        form.querySelectorAll('input[type="email"]').forEach(function (input) {
            input.addEventListener('input', function () {
                const wrap = input.closest('.input-icon-wrap');
                if (!wrap) return;
                const icon = wrap.querySelector('.field-validation');
                if (!input.value) {
                    wrap.classList.remove('valid', 'invalid');
                    if (icon) icon.className = 'field-validation fas';
                    return;
                }
                const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value);
                wrap.classList.toggle('valid', valid);
                wrap.classList.toggle('invalid', !valid);
                if (icon) {
                    icon.className = 'field-validation fas ' + (valid ? 'fa-check-circle' : 'fa-times-circle');
                }
            });
        });
    }

    function initStepper(form) {
        form.querySelectorAll('.stepper-control').forEach(function (stepper) {
            const input = stepper.querySelector('input[type="number"]');
            const minus = stepper.querySelector('[data-step="down"]');
            const plus = stepper.querySelector('[data-step="up"]');
            if (!input || !minus || !plus) return;

            minus.addEventListener('click', function () {
                const min = parseInt(input.min, 10) || 0;
                const val = parseInt(input.value, 10) || 0;
                input.value = Math.max(min, val - 1);
                input.dispatchEvent(new Event('change'));
            });

            plus.addEventListener('click', function () {
                const max = parseInt(input.max, 10) || 20;
                const val = parseInt(input.value, 10) || 0;
                input.value = Math.min(max, val + 1);
                input.dispatchEvent(new Event('change'));
            });
        });
    }

    function initToggleSync(form) {
        const toggle = form.querySelector('#autoApproveToggle');
        const select = form.querySelector('#autoApproveSelect');
        const status = form.querySelector('#autoApproveStatus');
        if (!toggle || !select) return;

        function syncStatus() {
            if (status) {
                status.textContent = toggle.checked
                    ? 'ON — Auto-approve qualified applications'
                    : 'OFF — Manual review required';
            }
        }

        toggle.checked = select.value === '1';
        syncStatus();
        toggle.addEventListener('change', function () {
            select.value = toggle.checked ? '1' : '0';
            syncStatus();
        });
    }

    function initStickySave(form) {
        const btn = form.querySelector('.settings-save-btn');
        if (!btn) return;

        form.addEventListener('submit', function () {
            btn.classList.add('is-saving');
            btn.setAttribute('aria-busy', 'true');
        });
    }

    /* ── Admin Accounts ── */
    function initAdminAccounts() {
        initAvatarPreview();
        initPasswordFields(document.getElementById('createAdminForm'));
        initPasswordFields(document.getElementById('updatePasswordForm'));
        initAdminSearch();
        initPhoneFormatting();

        const createForm = document.getElementById('createAdminForm');
        if (createForm) initEmailValidation(createForm);
    }

    function initAvatarPreview() {
        const nameInput = document.querySelector('#createAdminForm input[name="full_name"]');
        const preview = document.getElementById('avatarPreview');
        const previewName = document.getElementById('avatarPreviewName');
        if (!nameInput || !preview) return;

        nameInput.addEventListener('input', function () {
            const name = nameInput.value.trim();
            const initials = getInitials(name);
            preview.textContent = initials || '?';
            if (previewName) previewName.textContent = name || 'New Administrator';
        });
    }

    function getInitials(name) {
        if (!name) return '';
        return name.split(/\s+/).filter(Boolean).slice(0, 2).map(function (p) {
            return p.charAt(0).toUpperCase();
        }).join('');
    }

    function initPasswordFields(form) {
        if (!form) return;

        form.querySelectorAll('.password-field-wrap').forEach(function (wrap) {
            const input = wrap.querySelector('input[type="password"]');
            const toggle = wrap.querySelector('.password-toggle-btn');
            if (toggle && input) {
                toggle.addEventListener('click', function () {
                    const show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    toggle.innerHTML = show
                        ? '<i class="fas fa-eye-slash"></i>'
                        : '<i class="fas fa-eye"></i>';
                    toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                });
            }
        });

        const passwordInput = form.querySelector('input[name="password"], input[name="new_password"]');
        const confirmInput = form.querySelector('input[name="confirm_password"], input[name="confirm_password_update"]');

        if (passwordInput) {
            passwordInput.addEventListener('input', function () {
                updatePasswordStrength(passwordInput.value, form);
            });
        }

        if (confirmInput && passwordInput) {
            confirmInput.addEventListener('input', function () {
                const wrap = confirmInput.closest('.input-icon-wrap');
                if (!wrap) return;
                const match = confirmInput.value === passwordInput.value && confirmInput.value.length > 0;
                wrap.classList.toggle('valid', match);
                wrap.classList.toggle('invalid', confirmInput.value.length > 0 && !match);
                const icon = wrap.querySelector('.field-validation');
                if (icon && confirmInput.value) {
                    icon.className = 'field-validation fas ' + (match ? 'fa-check-circle' : 'fa-times-circle');
                }
            });
        }

        const usernameInput = form.querySelector('input[name="username"]');
        if (usernameInput) {
            usernameInput.addEventListener('input', function () {
                const wrap = usernameInput.closest('.input-icon-wrap');
                if (!wrap) return;
                const valid = /^[a-zA-Z0-9_]{3,}$/.test(usernameInput.value);
                if (!usernameInput.value) {
                    wrap.classList.remove('valid', 'invalid');
                    return;
                }
                wrap.classList.toggle('valid', valid);
                wrap.classList.toggle('invalid', !valid);
            });
        }
    }

    function updatePasswordStrength(password, form) {
        const bar = form.querySelector('.password-strength-fill');
        const label = form.querySelector('.password-strength-label');
        const reqs = form.querySelectorAll('.password-req');

        let score = 0;
        const checks = {
            length: password.length >= 8,
            upper: /[A-Z]/.test(password),
            lower: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[^A-Za-z0-9]/.test(password)
        };

        Object.keys(checks).forEach(function (key) {
            if (checks[key]) score++;
        });

        let level = 'weak';
        let text = 'Weak';
        if (score >= 5) { level = 'very-strong'; text = 'Very Strong'; }
        else if (score >= 4) { level = 'strong'; text = 'Strong'; }
        else if (score >= 3) { level = 'medium'; text = 'Medium'; }

        if (bar) {
            bar.className = 'password-strength-fill ' + (password ? level : '');
        }
        if (label) {
            label.textContent = password ? text : 'Enter a password';
        }

        if (reqs.length) {
            const reqMap = ['length', 'upper', 'lower', 'number', 'special'];
            reqs.forEach(function (el, i) {
                const key = el.dataset.req || reqMap[i];
                if (key && checks[key] !== undefined) {
                    el.classList.toggle('met', checks[key]);
                    const icon = el.querySelector('i');
                    if (icon) {
                        icon.className = checks[key] ? 'fas fa-check-circle' : 'fas fa-circle';
                    }
                }
            });
        }
    }

    function initAdminSearch() {
        const search = document.getElementById('adminSearchInput');
        const filter = document.getElementById('adminSortFilter');
        const list = document.getElementById('adminCardsList');
        if (!search || !list) return;

        function filterAdmins() {
            const query = search.value.toLowerCase().trim();
            const cards = Array.from(list.querySelectorAll('.admin-item'));

            cards.forEach(function (card) {
                const text = (card.dataset.search || '').toLowerCase();
                card.style.display = !query || text.includes(query) ? '' : 'none';
            });

            const sort = filter ? filter.value : 'newest';
            const visible = cards.filter(function (c) { return c.style.display !== 'none'; });

            visible.sort(function (a, b) {
                if (sort === 'oldest') return (a.dataset.created || '').localeCompare(b.dataset.created || '');
                if (sort === 'alpha') return (a.dataset.name || '').localeCompare(b.dataset.name || '');
                if (sort === 'active') return (b.dataset.updated || '').localeCompare(a.dataset.updated || '');
                return (b.dataset.created || '').localeCompare(a.dataset.created || '');
            });

            visible.forEach(function (card) { list.appendChild(card); });
        }

        search.addEventListener('input', filterAdmins);
        if (filter) filter.addEventListener('change', filterAdmins);
    }

    function initPhoneFormatting() {
        const phoneInput = document.querySelector('#createAdminForm input[name="phone"]');
        if (!phoneInput) return;

        phoneInput.addEventListener('input', function () {
            let val = phoneInput.value.replace(/\D/g, '').slice(0, 11);
            if (val.length > 4 && val.length <= 7) {
                val = val.slice(0, 4) + ' ' + val.slice(4);
            } else if (val.length > 7) {
                val = val.slice(0, 4) + ' ' + val.slice(4, 7) + ' ' + val.slice(7);
            }
            phoneInput.value = val;
        });

        const form = phoneInput.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                phoneInput.value = phoneInput.value.replace(/\D/g, '');
            });
        }
    }

    function initQuickActions() {
        document.querySelectorAll('[data-scroll-to]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(btn.getAttribute('data-scroll-to'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    target.classList.add('settings-highlight');
                    setTimeout(function () { target.classList.remove('settings-highlight'); }, 1200);
                }
            });
        });
    }

    /* ── System Logs ── */
    function initSystemLogs() {
        initLogViewToggle();
        initLogSorting();
        initLogExport();
        initLogQuickFilters();
        initLogPageJump();
        initLogSearch();
    }

    function initLogViewToggle() {
        const tableBtn = document.getElementById('logsViewTable');
        const timelineBtn = document.getElementById('logsViewTimeline');
        const tableView = document.getElementById('logsTableView');
        const timelineView = document.getElementById('logsTimelineView');
        if (!tableBtn || !timelineBtn) return;

        tableBtn.addEventListener('click', function () {
            tableBtn.classList.add('active');
            timelineBtn.classList.remove('active');
            if (tableView) tableView.classList.remove('hidden');
            if (timelineView) timelineView.classList.remove('active');
        });

        timelineBtn.addEventListener('click', function () {
            timelineBtn.classList.add('active');
            tableBtn.classList.remove('active');
            if (tableView) tableView.classList.add('hidden');
            if (timelineView) timelineView.classList.add('active');
        });
    }

    function initLogSorting() {
        const table = document.getElementById('logsDataTable');
        if (!table) return;

        table.querySelectorAll('th[data-sort]').forEach(function (th) {
            th.addEventListener('click', function () {
                const col = th.cellIndex;
                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));
                const asc = th.classList.toggle('sorted-asc');
                th.classList.toggle('sorted-desc', !asc);
                table.querySelectorAll('th').forEach(function (h) {
                    if (h !== th) h.classList.remove('sorted-asc', 'sorted-desc', 'sorted');
                });
                th.classList.add('sorted');

                rows.sort(function (a, b) {
                    const aText = (a.cells[col]?.textContent || '').trim();
                    const bText = (b.cells[col]?.textContent || '').trim();
                    return asc ? aText.localeCompare(bText) : bText.localeCompare(aText);
                });

                rows.forEach(function (row) { tbody.appendChild(row); });
            });
        });
    }

    function initLogExport() {
        const exportCsv = document.getElementById('logsExportCsv');
        const exportExcel = document.getElementById('logsExportExcel');
        const printBtn = document.getElementById('logsPrint');
        const refreshBtn = document.getElementById('logsRefresh');

        if (exportCsv) exportCsv.addEventListener('click', function () { exportLogs('csv'); });
        if (exportExcel) exportExcel.addEventListener('click', function () { exportLogs('xls'); });
        if (printBtn) printBtn.addEventListener('click', function () { window.print(); });
        if (refreshBtn) refreshBtn.addEventListener('click', function () { window.location.reload(); });
    }

    function exportLogs(type) {
        const table = document.getElementById('logsDataTable');
        if (!table) return;

        const rows = [];
        table.querySelectorAll('tr').forEach(function (tr) {
            const cells = Array.from(tr.querySelectorAll('th, td')).map(function (c) {
                return '"' + (c.textContent || '').replace(/"/g, '""').trim() + '"';
            });
            rows.push(cells.join(','));
        });

        const blob = new Blob([rows.join('\n')], {
            type: type === 'csv' ? 'text/csv;charset=utf-8;' : 'application/vnd.ms-excel;charset=utf-8;'
        });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'activity-logs.' + (type === 'csv' ? 'csv' : 'xls');
        link.click();
        URL.revokeObjectURL(link.href);

        if (typeof showSuccess === 'function') {
            showSuccess('Activity logs exported successfully.', 3000, 'Export Complete');
        }
    }

    function initLogQuickFilters() {
        document.querySelectorAll('.log-filter-chip[data-date-filter]').forEach(function (chip) {
            chip.addEventListener('click', function () {
                const form = document.getElementById('logsFilterForm');
                if (!form) return;

                const fromInput = form.querySelector('[name="filter_date_from"]');
                const toInput = form.querySelector('[name="filter_date_to"]');
                const range = chip.dataset.dateFilter;
                const today = new Date();

                document.querySelectorAll('.log-filter-chip[data-date-filter]').forEach(function (c) {
                    c.classList.remove('active');
                });
                chip.classList.add('active');

                if (range === 'all') {
                    if (fromInput) fromInput.value = '';
                    if (toInput) toInput.value = '';
                } else {
                    const from = new Date(today);
                    if (range === 'today') { /* same day */ }
                    else if (range === 'yesterday') from.setDate(from.getDate() - 1);
                    else if (range === '7days') from.setDate(from.getDate() - 7);
                    else if (range === '30days') from.setDate(from.getDate() - 30);

                    if (fromInput) fromInput.value = formatDate(from);
                    if (toInput) toInput.value = formatDate(today);
                }

                form.submit();
            });
        });
    }

    function formatDate(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function initLogPageJump() {
        const jumpInput = document.getElementById('logsPageJump');
        if (!jumpInput) return;

        jumpInput.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const page = parseInt(jumpInput.value, 10);
            const max = parseInt(jumpInput.max, 10);
            if (page >= 1 && page <= max) {
                const base = jumpInput.dataset.baseUrl;
                if (base) window.location.href = base + '&logs_page=' + page;
            }
        });
    }

    function initLogSearch() {
        const search = document.getElementById('logsSearchInput');
        const table = document.getElementById('logsDataTable');
        if (!search || !table) return;

        search.addEventListener('input', function () {
            const q = search.value.toLowerCase();
            table.querySelectorAll('tbody tr').forEach(function (row) {
                row.style.display = !q || row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }

    /* ── Session Toasts ── */
    function initSessionToasts() {
        const successAlert = document.querySelector('.alert-success, .settings-alert-success');
        if (successAlert && typeof showSuccess === 'function') {
            const msg = successAlert.querySelector('span')?.textContent?.trim();
            if (msg) showSuccess(msg, 4000, 'Success');
        }
    }

    /* ── Grid (preserve existing hook) ── */
    function adjustAdminGrid() {
        const adminGrid = document.querySelector('.admin-grid');
        if (!adminGrid) return;
        if (window.innerWidth <= 768) {
            adminGrid.style.gridTemplateColumns = '1fr';
        } else {
            adminGrid.style.gridTemplateColumns = 'minmax(320px, 1fr) minmax(400px, 1.6fr)';
        }
    }
})();
