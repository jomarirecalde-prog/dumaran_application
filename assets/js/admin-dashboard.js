/**
 * Dumaran Hiring Admin Dashboard — Premium Enterprise Interactions
 */
(function () {
    'use strict';

    const STORAGE_THEME = 'dumaran-admin-theme';
    const STORAGE_SIDEBAR = 'dumaran-admin-sidebar-collapsed';
    const STORAGE_LANG = 'dumaran-admin-lang';
    const STORAGE_RECENT_SEARCH = 'dumaran-admin-recent-searches';

    let chartInstances = [];
    let chartsInitialized = false;

    /* ── Theme ── */
    function getPreferredTheme() {
        const stored = localStorage.getItem(STORAGE_THEME);
        if (stored) return stored;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        document.body.setAttribute('data-theme', theme);
        localStorage.setItem(STORAGE_THEME, theme);
        const icon = document.getElementById('themeToggleIcon');
        if (icon) {
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
        if (chartsInitialized) {
            updateChartColors();
        }
    }

    function initTheme() {
        applyTheme(getPreferredTheme());
        const btn = document.getElementById('themeToggle');
        if (btn) {
            btn.addEventListener('click', () => {
                const next = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                applyTheme(next);
            });
        }
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!localStorage.getItem(STORAGE_THEME)) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    }

    function getChartColors() {
        const dark = document.body.getAttribute('data-theme') === 'dark';
        return {
            text: dark ? '#94a3b8' : '#64748b',
            grid: dark ? 'rgba(148,163,184,0.1)' : 'rgba(148,163,184,0.2)',
            primary: '#2050A0',
            secondary: '#0F4C81',
            accent: '#FFC107',
            success: '#16A34A',
            warning: '#F59E0B',
            danger: '#DC2626'
        };
    }

    /* ── Sidebar ── */
    function initSidebarCollapse() {
        const btn = document.getElementById('sidebarCollapseBtn');
        if (localStorage.getItem(STORAGE_SIDEBAR) === 'true') {
            document.body.classList.add('sidebar-collapsed');
        }
        if (btn) {
            btn.addEventListener('click', () => {
                document.body.classList.toggle('sidebar-collapsed');
                localStorage.setItem(STORAGE_SIDEBAR, document.body.classList.contains('sidebar-collapsed'));
            });
        }
    }

    /* ── Clock ── */
    function initClock() {
        const dateEl = document.getElementById('topbarDate');
        const timeEl = document.getElementById('topbarTime');
        if (!dateEl || !timeEl) return;

        function tick() {
            const now = new Date();
            dateEl.textContent = now.toLocaleDateString(undefined, {
                weekday: 'short', year: 'numeric', month: 'short', day: 'numeric'
            });
            timeEl.textContent = now.toLocaleTimeString(undefined, {
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
        }
        tick();
        setInterval(tick, 1000);
    }

    /* ── Profile dropdown ── */
    function initProfileMenu() {
        const trigger = document.getElementById('profileTrigger');
        const dropdown = document.getElementById('profileDropdown');
        if (!trigger || !dropdown) return;

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });
        document.addEventListener('click', () => dropdown.classList.remove('show'));
    }

    /* ── Language ── */
    function initLanguage() {
        const select = document.getElementById('langSelect');
        if (!select) return;
        const saved = localStorage.getItem(STORAGE_LANG);
        if (saved) select.value = saved;
        select.addEventListener('change', () => {
            localStorage.setItem(STORAGE_LANG, select.value);
        });
    }

    /* ── Animated counters ── */
    function initCounters() {
        document.querySelectorAll('[data-count]').forEach(el => {
            const target = parseInt(el.getAttribute('data-count'), 10) || 0;
            const duration = 1200;
            const start = performance.now();

            function step(now) {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.round(target * eased);
                if (progress < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        });
    }

    /* ── Sparklines ── */
    function initSparklines() {
        if (typeof Chart === 'undefined') return;
        const colors = getChartColors();

        document.querySelectorAll('.dash-kpi-sparkline canvas').forEach(canvas => {
            const data = JSON.parse(canvas.dataset.points || '[]');
            if (!data.length) return;

            new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: data.map((_, i) => i),
                    datasets: [{
                        data: data,
                        borderColor: canvas.dataset.color || colors.primary,
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        pointRadius: 0,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { enabled: false } },
                    scales: {
                        x: { display: false },
                        y: { display: false }
                    },
                    animation: { duration: 800 }
                }
            });
        });
    }

    /* ── Main charts (lazy) ── */
    function initChartsLazy() {
        const section = document.getElementById('chartsSection');
        if (!section || typeof Chart === 'undefined') return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !chartsInitialized) {
                    chartsInitialized = true;
                    initMainCharts();
                    observer.disconnect();
                }
            });
        }, { rootMargin: '100px' });

        observer.observe(section);
    }

    function initMainCharts() {
        const data = window.DASHBOARD_DATA || {};
        const colors = getChartColors();

        document.querySelectorAll('.chart-container').forEach(container => {
            container.classList.add('chart-loaded');
        });

        /* Applications per month — line */
        createChart('chartApplicationsMonth', {
            type: 'line',
            data: {
                labels: data.monthlyLabels || [],
                datasets: [{
                    label: 'Applications',
                    data: data.monthlyValues || [],
                    borderColor: colors.primary,
                    backgroundColor: 'rgba(32, 80, 160, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointBackgroundColor: colors.primary,
                    pointRadius: 4
                }]
            },
            options: chartOptions('Applications Per Month')
        });

        /* Status — donut */
        createChart('chartStatus', {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Approved', 'Rejected'],
                datasets: [{
                    data: [
                        data.statusCounts?.pending || 0,
                        data.statusCounts?.approved || 0,
                        data.statusCounts?.rejected || 0
                    ],
                    backgroundColor: [colors.warning, colors.success, colors.danger],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                ...chartOptions('Application Status'),
                cutout: '65%'
            }
        });

        /* Department — bar */
        createChart('chartDepartment', {
            type: 'bar',
            data: {
                labels: data.departmentLabels || [],
                datasets: [{
                    label: 'Applications',
                    data: data.departmentValues || [],
                    backgroundColor: 'rgba(32, 80, 160, 0.75)',
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: chartOptions('Hiring Per Department')
        });

        /* Sources — pie (derived from employment type) */
        createChart('chartSources', {
            type: 'pie',
            data: {
                labels: data.sourceLabels || ['Direct', 'Referral', 'Portal'],
                datasets: [{
                    data: data.sourceValues || [0, 0, 0],
                    backgroundColor: [colors.primary, colors.secondary, colors.accent],
                    borderWidth: 2,
                    borderColor: document.body.getAttribute('data-theme') === 'dark' ? '#111827' : '#fff'
                }]
            },
            options: chartOptions('Applicant Sources')
        });

        /* Timeline — area */
        createChart('chartTimeline', {
            type: 'line',
            data: {
                labels: data.timelineLabels || [],
                datasets: [{
                    label: 'Recruitment Activity',
                    data: data.timelineValues || [],
                    borderColor: colors.secondary,
                    backgroundColor: 'rgba(15, 76, 129, 0.2)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2
                }]
            },
            options: chartOptions('Recruitment Timeline')
        });

        /* Funnel — horizontal bar */
        createChart('chartFunnel', {
            type: 'bar',
            data: {
                labels: ['Pending', 'Screening', 'Interview', 'Final Review', 'Approved', 'Rejected'],
                datasets: [{
                    data: [
                        data.pipeline?.pending || 0,
                        data.pipeline?.screening || 0,
                        data.pipeline?.interview || 0,
                        data.pipeline?.final_review || 0,
                        data.pipeline?.approved || 0,
                        data.pipeline?.rejected || 0
                    ],
                    backgroundColor: [
                        colors.warning, colors.primary, colors.secondary,
                        '#6366f1', colors.success, colors.danger
                    ],
                    borderRadius: 6
                }]
            },
            options: {
                ...chartOptions('Recruitment Funnel'),
                indexAxis: 'y'
            }
        });
    }

    function chartOptions(title) {
        const colors = getChartColors();
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: colors.text, font: { family: 'Inter' } }
                },
                title: {
                    display: true,
                    text: title,
                    color: colors.text,
                    font: { family: 'Inter', size: 13, weight: '600' }
                }
            },
            scales: {
                x: {
                    ticks: { color: colors.text },
                    grid: { color: colors.grid }
                },
                y: {
                    ticks: { color: colors.text },
                    grid: { color: colors.grid },
                    beginAtZero: true
                }
            },
            animation: { duration: 1000, easing: 'easeOutQuart' }
        };
    }

    function createChart(canvasId, config) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const noScales = config.type === 'doughnut' || config.type === 'pie';
        if (noScales && config.options.scales) {
            delete config.options.scales;
        }
        const instance = new Chart(canvas.getContext('2d'), config);
        chartInstances.push(instance);
    }

    function updateChartColors() {
        const colors = getChartColors();
        chartInstances.forEach(chart => {
            if (chart.options.plugins?.legend?.labels) {
                chart.options.plugins.legend.labels.color = colors.text;
            }
            if (chart.options.plugins?.title) {
                chart.options.plugins.title.color = colors.text;
            }
            if (chart.options.scales?.x) {
                chart.options.scales.x.ticks.color = colors.text;
                chart.options.scales.x.grid.color = colors.grid;
            }
            if (chart.options.scales?.y) {
                chart.options.scales.y.ticks.color = colors.text;
                chart.options.scales.y.grid.color = colors.grid;
            }
            chart.update('none');
        });
    }

    /* ── Calendar ── */
    function initCalendar() {
        const grid = document.getElementById('calendarGrid');
        if (!grid) return;

        const events = window.DASHBOARD_DATA?.calendarEvents || [];
        const eventDates = new Set(events.map(e => e.date));
        const now = new Date();
        const year = now.getFullYear();
        const month = now.getMonth();
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const today = now.getDate();

        const dayNames = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
        dayNames.forEach(d => {
            const h = document.createElement('div');
            h.className = 'dash-cal-header';
            h.textContent = d;
            grid.appendChild(h);
        });

        for (let i = 0; i < firstDay; i++) {
            const cell = document.createElement('div');
            cell.className = 'dash-cal-day other-month';
            grid.appendChild(cell);
        }

        for (let d = 1; d <= daysInMonth; d++) {
            const cell = document.createElement('div');
            cell.className = 'dash-cal-day';
            cell.textContent = d;
            cell.setAttribute('role', 'gridcell');
            if (d === today) cell.classList.add('today');
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            if (eventDates.has(dateStr)) cell.classList.add('has-event');
            grid.appendChild(cell);
        }

        const agenda = document.getElementById('agendaList');
        if (agenda) {
            const todayStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(today).padStart(2, '0')}`;
            const todayEvents = events.filter(e => e.date === todayStr);
            if (todayEvents.length === 0) {
                agenda.innerHTML = '<div class="dash-agenda-item"><span>No events scheduled for today</span></div>';
            } else {
                agenda.innerHTML = todayEvents.map(e =>
                    `<div class="dash-agenda-item">
                        <span class="dash-agenda-time">${e.time || 'All day'}</span>
                        <span>${escapeHtml(e.title)}</span>
                    </div>`
                ).join('');
            }
        }
    }

    /* ── Global search ── */
    function initSearch() {
        const overlay = document.getElementById('searchModal');
        const input = document.getElementById('searchModalInput');
        const topbarInput = document.getElementById('topbarSearchInput');
        const results = document.getElementById('searchResults');
        if (!overlay || !input) return;

        const index = window.DASHBOARD_DATA?.searchIndex || [];

        function openSearch() {
            overlay.classList.add('show');
            input.value = '';
            input.focus();
            renderResults('');
        }

        function closeSearch() {
            overlay.classList.remove('show');
        }

        function renderResults(query) {
            const q = query.toLowerCase().trim();
            let items = index;
            if (q) {
                items = index.filter(item =>
                    item.title.toLowerCase().includes(q) ||
                    item.subtitle?.toLowerCase().includes(q)
                );
            } else {
                const recent = JSON.parse(localStorage.getItem(STORAGE_RECENT_SEARCH) || '[]');
                items = recent.length ? recent.map(r => ({ ...r, isRecent: true })) : index.slice(0, 6);
            }

            if (!items.length) {
                results.innerHTML = '<div class="search-result-item"><span>No results found</span></div>';
                return;
            }

            results.innerHTML = items.map((item, i) =>
                `<a href="${item.url}" class="search-result-item${i === 0 ? ' focused' : ''}" data-index="${i}">
                    <span class="search-result-type">${item.type}</span>
                    <div>
                        <div style="font-weight:600">${escapeHtml(item.title)}</div>
                        ${item.subtitle ? `<div style="font-size:0.8rem;color:var(--dash-text-muted)">${escapeHtml(item.subtitle)}</div>` : ''}
                    </div>
                </a>`
            ).join('');

            results.querySelectorAll('.search-result-item').forEach(el => {
                el.addEventListener('click', () => {
                    saveRecentSearch(items[parseInt(el.dataset.index, 10)]);
                });
            });
        }

        function saveRecentSearch(item) {
            if (!item || item.isRecent) return;
            let recent = JSON.parse(localStorage.getItem(STORAGE_RECENT_SEARCH) || '[]');
            recent = recent.filter(r => r.url !== item.url);
            recent.unshift(item);
            recent = recent.slice(0, 5);
            localStorage.setItem(STORAGE_RECENT_SEARCH, JSON.stringify(recent));
        }

        input.addEventListener('input', () => renderResults(input.value));
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeSearch();
        });

        if (topbarInput) {
            topbarInput.addEventListener('focus', openSearch);
            topbarInput.addEventListener('click', openSearch);
        }

        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                openSearch();
            }
            if (e.key === 'Escape') closeSearch();
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /* ── Scroll animations ── */
    function initScrollAnimations() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('dash-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

        document.querySelectorAll('.dash-section, .dash-kpi-card, .dash-card').forEach(el => {
            el.classList.add('dash-animate-in');
            observer.observe(el);
        });
    }

    /* ── Ripple on buttons ── */
    function initRipple() {
        document.querySelectorAll('.dash-btn-ripple, .dash-hero-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                const rect = this.getBoundingClientRect();
                const ripple = document.createElement('span');
                ripple.style.cssText = `
                    position:absolute;border-radius:50%;background:rgba(255,255,255,0.5);
                    width:80px;height:80px;left:${e.clientX - rect.left - 40}px;
                    top:${e.clientY - rect.top - 40}px;transform:scale(0);
                    animation:dashRipple 0.6s ease-out;pointer-events:none;
                `;
                this.appendChild(ripple);
                setTimeout(() => ripple.remove(), 600);
            });
        });
    }

    /* ── Smart priority reorder ── */
    function initSmartPriority() {
        const container = document.getElementById('smartPriorityContainer');
        if (!container) return;

        const items = Array.from(container.children);
        items.sort((a, b) => {
            const pa = parseInt(a.dataset.priority || '0', 10);
            const pb = parseInt(b.dataset.priority || '0', 10);
            return pb - pa;
        });
        items.forEach(item => container.appendChild(item));
    }

    /* ── Init ── */
    document.addEventListener('DOMContentLoaded', () => {
        initTheme();
        initSidebarCollapse();
        initClock();
        initProfileMenu();
        initLanguage();
        initCounters();
        initSparklines();
        initChartsLazy();
        initCalendar();
        initSearch();
        initScrollAnimations();
        initRipple();
        initSmartPriority();
    });

    /* Ripple keyframe injection */
    const style = document.createElement('style');
    style.textContent = '@keyframes dashRipple{to{transform:scale(4);opacity:0}}';
    document.head.appendChild(style);
})();
