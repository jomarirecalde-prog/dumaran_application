/**
 * Applicant Dashboard — Premium UX Controller
 * UI-only: theme, animations, widgets, bookmarks, search
 */
(function () {
    'use strict';

    const WIDGETS_KEY = 'dumaran_dashboard_widgets';
    const BOOKMARKS_KEY = 'dumaran_saved_jobs';
    const RECENT_KEY = 'dumaran_recent_jobs';

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        initSidebarCollapse();
        initSearch();
        initDropdowns();
        initNotificationPanel();
        initWidgetCollapse();
        initCountUp();
        initBookmarks();
        initRecentlyViewed();
        initPageLoad();
        initSwipeCards();
    }

    /* ── Sidebar collapse ── */
    function initSidebarCollapse() {
        const btn = document.getElementById('sidebarCollapseBtn');
        if (!btn) return;

        const saved = localStorage.getItem('dashboard-sidebar-collapsed') === '1';
        if (saved && window.innerWidth > 768) {
            document.body.classList.add('sidebar-collapsed');
        }

        btn.addEventListener('click', () => {
            document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem(
                'dashboard-sidebar-collapsed',
                document.body.classList.contains('sidebar-collapsed') ? '1' : '0'
            );
        });
    }

    /* ── Search ── */
    function initSearch() {
        const form = document.getElementById('dashSearchForm');
        const input = document.getElementById('dashSearchInput');
        if (!form || !input) return;

        form.addEventListener('submit', e => {
            e.preventDefault();
            const q = input.value.trim();
            if (q) {
                window.location.href = form.action + '?search=' + encodeURIComponent(q);
            }
        });
    }

    /* ── Dropdowns ── */
    function initDropdowns() {
        document.querySelectorAll('[data-dropdown-toggle]').forEach(toggle => {
            if (toggle.closest('.dash-theme-menu, .applicant-theme-menu')) return;
            toggle.addEventListener('click', e => {
                e.stopPropagation();
                const targetId = toggle.getAttribute('data-dropdown-toggle');
                const menu = document.getElementById(targetId);
                if (!menu) return;

                const isOpen = menu.classList.contains('open');
                closeAllDropdowns();
                if (!isOpen) menu.classList.add('open');
            });
        });

        document.addEventListener('click', closeAllDropdowns);
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeAllDropdowns();
        });
    }

    function closeAllDropdowns() {
        document.querySelectorAll('.dash-theme-dropdown.open, .dash-profile-dropdown.open, .applicant-theme-dropdown.open').forEach(el => {
            el.classList.remove('open');
        });
    }

    /* ── Notification panel ── */
    function initNotificationPanel() {
        const toggle = document.getElementById('notifPanelToggle');
        const panel = document.getElementById('notifPanel');
        const closeBtn = document.getElementById('notifPanelClose');

        if (!toggle || !panel) return;

        toggle.addEventListener('click', e => {
            e.stopPropagation();
            panel.classList.toggle('open');
        });

        closeBtn?.addEventListener('click', () => panel.classList.remove('open'));

        document.addEventListener('click', e => {
            if (!panel.contains(e.target) && !toggle.contains(e.target)) {
                panel.classList.remove('open');
            }
        });
    }

    /* ── Widget collapse ── */
    function initWidgetCollapse() {
        const saved = JSON.parse(localStorage.getItem(WIDGETS_KEY) || '{}');

        document.querySelectorAll('[data-widget]').forEach(widget => {
            const id = widget.getAttribute('data-widget');
            if (saved[id] === false) widget.classList.add('collapsed');

            const toggle = widget.querySelector('[data-widget-toggle]');
            toggle?.addEventListener('click', () => {
                widget.classList.toggle('collapsed');
                saved[id] = !widget.classList.contains('collapsed');
                localStorage.setItem(WIDGETS_KEY, JSON.stringify(saved));

                const icon = toggle.querySelector('i');
                if (icon) {
                    icon.className = widget.classList.contains('collapsed')
                        ? 'fas fa-chevron-down'
                        : 'fas fa-chevron-up';
                }
            });
        });
    }

    /* ── Count-up animation ── */
    function initCountUp() {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        document.querySelectorAll('[data-count]').forEach(el => {
            const target = parseFloat(el.getAttribute('data-count'));
            if (isNaN(target)) return;

            const isDecimal = el.getAttribute('data-decimal') === '1';
            const duration = 800;
            const start = performance.now();

            function tick(now) {
                const progress = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                const current = target * eased;

                el.textContent = isDecimal ? current.toFixed(1) : Math.round(current);
                if (progress < 1) requestAnimationFrame(tick);
            }

            requestAnimationFrame(tick);
        });
    }

    /* ── Bookmarks (localStorage) ── */
    function initBookmarks() {
        const bookmarks = getBookmarks();

        document.querySelectorAll('[data-bookmark-id]').forEach(btn => {
            const id = btn.getAttribute('data-bookmark-id');
            if (bookmarks.includes(id)) btn.classList.add('saved');

            btn.addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();
                toggleBookmark(id, btn);
                updateSavedCount();
            });
        });

        updateSavedCount();
    }

    function getBookmarks() {
        try {
            return JSON.parse(localStorage.getItem(BOOKMARKS_KEY) || '[]');
        } catch {
            return [];
        }
    }

    function toggleBookmark(id, btn) {
        let bookmarks = getBookmarks();
        if (bookmarks.includes(id)) {
            bookmarks = bookmarks.filter(b => b !== id);
            btn.classList.remove('saved');
            btn.setAttribute('aria-pressed', 'false');
            btn.title = 'Save job';
        } else {
            bookmarks.push(id);
            btn.classList.add('saved');
            btn.setAttribute('aria-pressed', 'true');
            btn.title = 'Remove bookmark';
        }
        localStorage.setItem(BOOKMARKS_KEY, JSON.stringify(bookmarks));
    }

    function updateSavedCount() {
        const countEl = document.getElementById('savedJobsCount');
        if (countEl) countEl.textContent = getBookmarks().length;
    }

    /* ── Recently viewed jobs ── */
    function initRecentlyViewed() {
        const container = document.getElementById('recentlyViewedJobs');
        if (!container) return;

        let recent = [];
        try {
            recent = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
        } catch {
            recent = [];
        }

        if (!recent.length) {
            container.innerHTML = '<p class="dash-empty__text" style="padding:16px 0;margin:0">Jobs you view will appear here for quick access.</p>';
            return;
        }

        container.innerHTML = recent.slice(0, 5).map(job => `
            <a href="${escapeHtml(job.url)}" class="dash-quick-link" style="margin-bottom:8px">
                <i class="fas fa-briefcase"></i>
                <span>${escapeHtml(job.title)}</span>
            </a>
        `).join('');

        document.querySelectorAll('.dash-job-card[data-position-id]').forEach(card => {
            const link = card.querySelector('a[href*="position-details"]');
            if (!link) return;
            link.addEventListener('click', () => {
                saveRecentJob({
                    id: card.getAttribute('data-position-id'),
                    title: card.querySelector('.dash-job-card__title')?.textContent?.trim() || 'Job',
                    url: link.getAttribute('href'),
                });
            });
        });
    }

    function saveRecentJob(job) {
        let recent = [];
        try {
            recent = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
        } catch {
            recent = [];
        }
        recent = recent.filter(r => r.id !== job.id);
        recent.unshift(job);
        recent = recent.slice(0, 10);
        localStorage.setItem(RECENT_KEY, JSON.stringify(recent));
    }

    /* ── Page load skeleton ── */
    function initPageLoad() {
        requestAnimationFrame(() => {
            setTimeout(() => {
                document.body.classList.add('is-loaded');
            }, 150);
        });
    }

    /* ── Swipe hint on mobile cards ── */
    function initSwipeCards() {
        if (window.innerWidth > 768) return;

        document.querySelectorAll('.dash-job-card').forEach(card => {
            let startX = 0;
            card.addEventListener('touchstart', e => {
                startX = e.touches[0].clientX;
            }, { passive: true });

            card.addEventListener('touchend', e => {
                const diff = e.changedTouches[0].clientX - startX;
                if (Math.abs(diff) > 60) {
                    card.style.transform = diff > 0 ? 'translateX(8px)' : 'translateX(-8px)';
                    setTimeout(() => { card.style.transform = ''; }, 200);
                }
            }, { passive: true });
        });
    }

    /* ── Privacy wizard step indicator (UI only) ── */
    window.updatePrivacyWizardUI = function (step) {
        document.querySelectorAll('.dash-privacy-wizard__step').forEach((el, i) => {
            const stepNum = i + 1;
            el.classList.toggle('active', stepNum === step);
            el.classList.toggle('done', stepNum < step);
        });
    };

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }
})();
