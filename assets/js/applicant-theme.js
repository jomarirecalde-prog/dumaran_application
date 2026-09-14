/**
 * WPU Applicant — Unified theme (light / dark / system)
 */
(function () {
    'use strict';

    const THEME_KEY = 'wpu-applicant-theme';
    const LEGACY_KEYS = ['dashboard-theme', 'profile-theme', 'wpu_applicant_jobs_theme'];

    function getSavedTheme() {
        let theme = localStorage.getItem(THEME_KEY);
        if (theme) return theme;

        for (const key of LEGACY_KEYS) {
            const legacy = localStorage.getItem(key);
            if (legacy) {
                theme = legacy === 'auto' ? 'system' : legacy;
                localStorage.setItem(THEME_KEY, theme);
                return theme;
            }
        }

        return 'system';
    }

    function applyTheme(theme) {
        document.body.setAttribute('data-theme', theme);
        localStorage.setItem(THEME_KEY, theme);

        document.querySelectorAll('[data-theme-set]').forEach(btn => {
            const value = btn.getAttribute('data-theme-set');
            const active = value === theme;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function closeThemeDropdowns() {
        document.querySelectorAll('.applicant-theme-dropdown.open, .dash-theme-dropdown.open').forEach(el => {
            el.classList.remove('open');
        });
    }

    function initThemeDropdown() {
        document.querySelectorAll(
            '.applicant-theme-menu [data-dropdown-toggle], .dash-theme-menu [data-dropdown-toggle]'
        ).forEach(toggle => {
            toggle.addEventListener('click', e => {
                e.stopPropagation();
                const targetId = toggle.getAttribute('data-dropdown-toggle');
                const menu = document.getElementById(targetId);
                if (!menu) return;

                const isOpen = menu.classList.contains('open');
                closeThemeDropdowns();
                if (!isOpen) menu.classList.add('open');
            });
        });

        document.addEventListener('click', closeThemeDropdowns);
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeThemeDropdowns();
        });
    }

    function initTheme() {
        applyTheme(getSavedTheme());

        document.querySelectorAll('[data-theme-set]').forEach(btn => {
            btn.addEventListener('click', () => {
                applyTheme(btn.getAttribute('data-theme-set'));
                closeThemeDropdowns();
            });
        });

        initThemeDropdown();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }

    window.ApplicantTheme = { applyTheme, getSavedTheme };
})();
