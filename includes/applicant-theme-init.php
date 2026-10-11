<script>
(function () {
    try {
        var KEY = 'dumaran-applicant-theme';
        var LEGACY = ['dashboard-theme', 'profile-theme', 'wpu_applicant_jobs_theme', 'wpu-applicant-theme'];
        var theme = localStorage.getItem(KEY);
        if (!theme) {
            for (var i = 0; i < LEGACY.length; i++) {
                var v = localStorage.getItem(LEGACY[i]);
                if (v) {
                    theme = v === 'auto' ? 'system' : v;
                    localStorage.setItem(KEY, theme);
                    break;
                }
            }
        }
        document.body.setAttribute('data-theme', theme || 'system');
    } catch (e) {}
})();
</script>
