<?php
/**
 * PWA Scripts Include
 * Include this file before the closing </body> tag of all applicant pages
 * to enable Progressive Web App functionality
 */
?>
<!-- PWA Scripts -->
<script>
    // Set PWA base URL for service worker scope
    window.PWA_BASE_URL = '<?= BASE_URL ?>';
    window.PWA_BASE_PATH = '<?= parse_url(BASE_URL, PHP_URL_PATH) ?? '/dumaran_hiring/' ?>';
</script>
<script src="<?= BASE_URL ?>assets/js/pwa.js"></script>

