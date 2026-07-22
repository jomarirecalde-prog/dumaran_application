/**
 * WPU Reports & Analytics — Executive Dashboard UI
 * Preserves existing statusChart and positionChart datasets.
 */
(function () {
    'use strict';

    let extraCharts = [];
    let chartsReady = false;

    function getColors() {
        const dark = document.body.getAttribute('data-theme') === 'dark';
        return {
            text: dark ? '#94a3b8' : '#64748b',
            grid: dark ? 'rgba(148,163,184,0.1)' : 'rgba(148,163,184,0.18)',
            primary: '#0056D2',
            secondary: '#0F4C81',
            accent: '#FFC107',
            success: '#16A34A',
            warning: '#F59E0B',
            danger: '#DC2626',
            purple: '#7C3AED',
            teal: '#0F766E'
        };
    }

    function toast(msg, type) {
        if (typeof showToast === 'function') {
            showToast(msg, type || 'info');
        }
    }

    function formatNum(n) {
        return Number(n).toLocaleString();
    }

    /* ── Animated counters ── */
    function initCounters() {
        document.querySelectorAll('[data-count]').forEach(function (el) {
            if (el.dataset.animated === '1') return;
            el.dataset.animated = '1';
            const raw = el.getAttribute('data-count');
            const isPercent = el.dataset.format === 'percent';
            const target = parseFloat(raw) || 0;
            const duration = 1400;
            const start = performance.now();

            function step(now) {
                const p = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - p, 3);
                const val = target * eased;
                if (isPercent) {
                    el.textContent = (Math.round(val * 10) / 10) + '%';
                } else if (String(raw).indexOf('.') >= 0) {
                    el.textContent = (Math.round(val * 10) / 10).toLocaleString();
                } else {
                    el.textContent = Math.round(val).toLocaleString();
                }
                if (p < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        });
    }

    /* ── Sparklines ── */
    function initSparklines() {
        if (typeof Chart === 'undefined') return;
        const colors = getColors();
        document.querySelectorAll('.rpt-kpi-sparkline canvas').forEach(function (canvas) {
            const pts = JSON.parse(canvas.dataset.points || '[]');
            if (!pts.length) return;
            new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: pts.map(function (_, i) { return i; }),
                    datasets: [{
                        data: pts,
                        borderColor: canvas.dataset.color || colors.primary,
                        backgroundColor: canvas.dataset.fill || 'rgba(0,86,210,0.08)',
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 0,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { enabled: false } },
                    scales: { x: { display: false }, y: { display: false } },
                    animation: { duration: 900 }
                }
            });
        });
    }

    function baseChartOptions(title) {
        const c = getColors();
        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 1200, easing: 'easeOutQuart' },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    labels: {
                        color: c.text,
                        font: { family: 'Inter', size: 11, weight: '600' },
                        padding: 16,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                title: {
                    display: !!title,
                    text: title || '',
                    color: c.text,
                    font: { family: 'Inter', size: 13, weight: '700' },
                    padding: { bottom: 12 }
                },
                tooltip: {
                    backgroundColor: document.body.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#0f172a',
                    titleFont: { family: 'Inter', weight: '600' },
                    bodyFont: { family: 'Inter' },
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: true
                }
            },
            scales: {
                x: {
                    ticks: { color: c.text, font: { family: 'Inter', size: 10 } },
                    grid: { color: c.grid }
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: c.text, font: { family: 'Inter', size: 10 }, stepSize: 1 },
                    grid: { color: c.grid }
                }
            }
        };
    }

    function markChartsLoaded() {
        document.querySelectorAll('.rpt-chart-wrap, .rpt-donut-canvas').forEach(function (el) {
            el.closest('.rpt-chart-body')?.classList.add('chart-loaded');
            el.classList.add('chart-loaded');
        });
    }

    /* ── Existing charts: legend/sort only (datasets init in inline script) ── */
    function sortPositionChart(mode) {
        var chart = window.positionChartInstance;
        if (!chart) return;
        var labels = (window.REPORTS_DATA.positionLabels || []).slice();
        var values = (window.REPORTS_DATA.positionValues || []).slice();
        var pairs = labels.map(function (l, i) { return { l: l, v: values[i] || 0 }; });

        if (mode === 'highest') pairs.sort(function (a, b) { return b.v - a.v; });
        else if (mode === 'lowest') pairs.sort(function (a, b) { return a.v - b.v; });
        else if (mode === 'alphabetical') pairs.sort(function (a, b) { return a.l.localeCompare(b.l); });

        var sliced = (mode === 'top10' || mode === 'highest') ? pairs.slice(0, 10) : pairs;
        chart.data.labels = sliced.map(function (p) { return p.l; });
        chart.data.datasets[0].data = sliced.map(function (p) { return p.v; });
        chart.update('active');
    }

    /* ── Additional analytics charts ── */
    function initExtraCharts() {
        if (typeof Chart === 'undefined') return;
        const c = getColors();
        const data = window.REPORTS_DATA || {};

        const configs = [
            {
                id: 'chartMonthly',
                type: 'line',
                labels: data.monthlyLabels,
                values: data.monthlyValues,
                label: 'Applications',
                color: c.primary,
                fill: 'rgba(0,86,210,0.12)'
            },
            {
                id: 'chartDepartment',
                type: 'bar',
                labels: data.departmentLabels,
                values: data.departmentValues,
                label: 'By Position',
                color: c.teal,
                indexAxis: 'y'
            },
            {
                id: 'chartApprovalTrend',
                type: 'line',
                labels: data.monthlyLabels,
                values: data.approvalTrendValues,
                label: 'Approved',
                color: c.success,
                fill: 'rgba(22,163,74,0.15)'
            },
            {
                id: 'chartDaily',
                type: 'line',
                labels: data.dailyLabels,
                values: data.dailyValues,
                label: 'Daily Applications',
                color: c.secondary,
                fill: 'rgba(15,76,129,0.12)'
            },
            {
                id: 'chartSources',
                type: 'pie',
                labels: data.sourceLabels,
                values: data.sourceValues,
                colors: [c.primary, c.secondary, c.accent]
            },
            {
                id: 'chartTimeline',
                type: 'line',
                labels: data.timelineLabels,
                values: data.timelineValues,
                label: 'Weekly Activity',
                color: c.purple,
                fill: 'rgba(124,58,237,0.12)'
            },
            {
                id: 'chartGrowth',
                type: 'line',
                labels: data.monthlyLabels,
                datasets: [
                    { label: 'Applications', data: data.monthlyValues, color: c.primary },
                    { label: 'Approved', data: data.approvalTrendValues, color: c.success }
                ]
            }
        ];

        configs.forEach(function (cfg) {
            const el = document.getElementById(cfg.id);
            if (!el) return;

            let chartConfig;
            if (cfg.id === 'chartGrowth') {
                chartConfig = {
                    type: 'line',
                    data: {
                        labels: cfg.labels || [],
                        datasets: (cfg.datasets || []).map(function (ds) {
                            return {
                                label: ds.label,
                                data: ds.data || [],
                                borderColor: ds.color,
                                backgroundColor: 'transparent',
                                tension: 0.4,
                                borderWidth: 2.5,
                                pointRadius: 3
                            };
                        })
                    },
                    options: baseChartOptions('')
                };
            } else if (cfg.type === 'pie') {
                chartConfig = {
                    type: 'pie',
                    data: {
                        labels: cfg.labels || [],
                        datasets: [{
                            data: cfg.values || [],
                            backgroundColor: cfg.colors,
                            borderWidth: 2,
                            borderColor: document.body.getAttribute('data-theme') === 'dark' ? '#111827' : '#fff',
                            hoverOffset: 10
                        }]
                    },
                    options: Object.assign(baseChartOptions(''), { scales: {} })
                };
            } else if (cfg.indexAxis === 'y') {
                chartConfig = {
                    type: 'bar',
                    data: {
                        labels: cfg.labels || [],
                        datasets: [{
                            label: cfg.label,
                            data: cfg.values || [],
                            backgroundColor: cfg.color,
                            borderRadius: 6,
                            borderSkipped: false
                        }]
                    },
                    options: Object.assign(baseChartOptions(''), { indexAxis: 'y' })
                };
            } else {
                chartConfig = {
                    type: cfg.type,
                    data: {
                        labels: cfg.labels || [],
                        datasets: [{
                            label: cfg.label,
                            data: cfg.values || [],
                            borderColor: cfg.color,
                            backgroundColor: cfg.fill || cfg.color,
                            fill: !!cfg.fill,
                            tension: 0.4,
                            borderWidth: 2.5,
                            pointRadius: 3
                        }]
                    },
                    options: baseChartOptions('')
                };
            }

            extraCharts.push(new Chart(el.getContext('2d'), chartConfig));
        });

        /* Funnel chart */
        const funnelEl = document.getElementById('chartFunnel');
        if (funnelEl) {
            const p = data.pipeline || {};
            extraCharts.push(new Chart(funnelEl.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['Pending', 'Approved', 'Rejected'],
                    datasets: [{
                        data: [p.pending || 0, p.approved || 0, p.rejected || 0],
                        backgroundColor: [c.warning, c.success, c.danger],
                        borderRadius: 8,
                        borderSkipped: false
                    }]
                },
                options: Object.assign(baseChartOptions(''), { indexAxis: 'y' })
            }));
        }
    }

    function initChartsLazy() {
        const section = document.getElementById('rptChartsSection');
        if (!section || typeof Chart === 'undefined') return;

        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting && !chartsReady) {
                    chartsReady = true;
                    initExtraCharts();
                    markChartsLoaded();
                    observer.disconnect();
                }
            });
        }, { rootMargin: '80px' });

        observer.observe(section);
    }

    /* ── Header & toolbar ── */
    function initHeader() {
        const updatedEl = document.getElementById('rptLastUpdated');
        function tick() {
            const now = new Date();
            if (updatedEl) {
                updatedEl.textContent = now.toLocaleString(undefined, {
                    month: 'short', day: 'numeric', year: 'numeric',
                    hour: '2-digit', minute: '2-digit', second: '2-digit'
                });
            }
            const dateEl = document.getElementById('rptHeaderDate');
            if (dateEl) {
                dateEl.textContent = now.toLocaleDateString(undefined, {
                    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
                });
            }
        }
        tick();
        setInterval(tick, 1000);

        document.getElementById('rptRefreshBtn')?.addEventListener('click', function () {
            toast('Refreshing dashboard…', 'info');
            setTimeout(function () { location.reload(); }, 400);
        });

        document.getElementById('rptExportHeaderBtn')?.addEventListener('click', function () {
            document.getElementById('rptExportSection')?.scrollIntoView({ behavior: 'smooth' });
        });

        document.getElementById('rptFullscreenBtn')?.addEventListener('click', toggleFullscreen);
    }

    function toggleFullscreen() {
        document.body.classList.toggle('rpt-fullscreen');
        const btn = document.getElementById('rptFullscreenBtn');
        const active = document.body.classList.contains('rpt-fullscreen');
        if (btn) {
            btn.innerHTML = active
                ? '<i class="fas fa-compress" aria-hidden="true"></i> Exit Fullscreen'
                : '<i class="fas fa-expand" aria-hidden="true"></i> Fullscreen';
        }
        toast(active ? 'Fullscreen mode enabled' : 'Fullscreen mode disabled', 'info');
    }

    /* ── Filters (frontend UI) ── */
    function initFilters() {
        const bar = document.getElementById('rptFilters');
        if (!bar) return;

        window.addEventListener('scroll', function () {
            bar.classList.toggle('is-stuck', window.scrollY > 120);
        }, { passive: true });

        document.querySelectorAll('.rpt-date-pill').forEach(function (pill) {
            pill.addEventListener('click', function () {
                document.querySelectorAll('.rpt-date-pill').forEach(function (p) {
                    p.classList.remove('active');
                    p.setAttribute('aria-pressed', 'false');
                });
                pill.classList.add('active');
                pill.setAttribute('aria-pressed', 'true');
            });
        });

        document.getElementById('rptApplyFilters')?.addEventListener('click', function () {
            toast('Filters applied to dashboard view', 'success');
        });

        document.getElementById('rptResetFilters')?.addEventListener('click', function () {
            bar.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
            document.querySelectorAll('.rpt-date-pill').forEach(function (p, i) {
                p.classList.toggle('active', i === 3);
                p.setAttribute('aria-pressed', i === 3 ? 'true' : 'false');
            });
            toast('Filters reset', 'info');
        });

        document.getElementById('rptFilterExport')?.addEventListener('click', function () {
            document.getElementById('rptExportSection')?.scrollIntoView({ behavior: 'smooth' });
        });
    }

    /* ── Position chart sort ── */
    function initPositionSort() {
        const sel = document.getElementById('positionChartSort');
        if (sel) {
            sel.addEventListener('change', function () {
                sortPositionChart(sel.value);
            });
        }
    }

    /* ── Activity view toggle ── */
    function initActivityToggle() {
        const listView = document.getElementById('rptActivityList');
        const timelineView = document.getElementById('rptActivityTimeline');
        document.querySelectorAll('.rpt-view-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.rpt-view-btn').forEach(function (b) {
                    b.classList.remove('active');
                    b.setAttribute('aria-pressed', 'false');
                });
                btn.classList.add('active');
                btn.setAttribute('aria-pressed', 'true');
                const view = btn.dataset.view;
                if (listView) listView.hidden = view !== 'list';
                if (timelineView) timelineView.hidden = view !== 'timeline';
            });
        });
    }

    /* ── Section nav ── */
    function initSectionNav() {
        document.querySelectorAll('.rpt-section-link').forEach(function (link) {
            link.addEventListener('click', function (e) {
                const href = link.getAttribute('href');
                if (href && href.charAt(0) === '#') {
                    e.preventDefault();
                    document.querySelector(href)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    document.querySelectorAll('.rpt-section-link').forEach(function (l) { l.classList.remove('active'); });
                    link.classList.add('active');
                }
            });
        });
    }

    /* ── Export & FAB ── */
    function initExport() {
        document.querySelectorAll('[data-export]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                toast('Export: ' + btn.dataset.export + ' — connect backend export to enable downloads.', 'info');
            });
        });

        document.getElementById('rptPrintBtn')?.addEventListener('click', function () {
            window.print();
        });

        document.querySelectorAll('.rpt-schedule-chip').forEach(function (chip) {
            chip.addEventListener('click', function () {
                document.querySelectorAll('.rpt-schedule-chip').forEach(function (c) {
                    c.classList.remove('active');
                    c.setAttribute('aria-pressed', 'false');
                });
                chip.classList.add('active');
                chip.setAttribute('aria-pressed', 'true');
            });
        });

        document.getElementById('rptScheduleSave')?.addEventListener('click', function () {
            const email = document.getElementById('rptScheduleEmail')?.value;
            if (email) {
                toast('Report schedule saved for ' + email + ' (frontend preview)', 'success');
            } else {
                toast('Please enter an email recipient', 'warning');
            }
        });
    }

    function initFab() {
        const panel = document.getElementById('rptFabPanel');
        const main = document.getElementById('rptFabMain');
        if (!panel || !main) return;

        main.addEventListener('click', function () {
            panel.classList.toggle('open');
            main.setAttribute('aria-expanded', panel.classList.contains('open') ? 'true' : 'false');
        });

        document.querySelectorAll('.rpt-fab-action').forEach(function (action) {
            action.addEventListener('click', function () {
                const act = action.dataset.action;
                if (act === 'refresh') document.getElementById('rptRefreshBtn')?.click();
                else if (act === 'export') document.getElementById('rptExportSection')?.scrollIntoView({ behavior: 'smooth' });
                else if (act === 'print') window.print();
                else if (act === 'fullscreen') toggleFullscreen();
                else if (act === 'charts') toast('Use browser save or export tools for chart images', 'info');
                else if (act === 'share') {
                    if (navigator.share) {
                        navigator.share({ title: 'WPU Reports', url: location.href }).catch(function () {});
                    } else {
                        toast('Dashboard link copied concept — share: ' + location.href, 'info');
                    }
                }
                panel.classList.remove('open');
            });
        });
    }

    /* ── KPI card interactions ── */
    function initKpiCards() {
        document.querySelectorAll('.rpt-kpi-card').forEach(function (card) {
            card.setAttribute('tabindex', '0');
            card.addEventListener('click', function () {
                card.classList.toggle('is-expanded');
            });
            card.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    card.click();
                }
            });
        });
    }

    function init() {
        initHeader();
        initFilters();
        initSectionNav();
        initActivityToggle();
        initExport();
        initFab();
        initKpiCards();
        initCounters();
        initSparklines();
        initPositionSort();
        initChartsLazy();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
