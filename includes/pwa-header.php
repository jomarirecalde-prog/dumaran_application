<?php
/**
 * PWA Header Include
 * Include this file in the <head> section of all applicant pages
 * to enable Progressive Web App functionality
 */

// PWA Meta Tags
?>
<!-- PWA Meta Tags -->
<meta name="application-name" content="<?= htmlspecialchars(SITE_SHORT_NAME, ENT_QUOTES, 'UTF-8') ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars(SITE_SHORT_NAME, ENT_QUOTES, 'UTF-8') ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="msapplication-TileColor" content="#1e3a5f">
<meta name="msapplication-TileImage" content="<?= BASE_URL ?>assets/pwa/icon-144x144.png">
<meta name="theme-color" content="#1e3a5f">
<meta name="format-detection" content="telephone=no">

<!-- PWA Manifest -->
<link rel="manifest" href="<?= BASE_URL ?>manifest.php">

<!-- PWA Icons -->
<link rel="apple-touch-icon" href="<?= BASE_URL ?>assets/pwa/icon-152x152.png">
<link rel="apple-touch-icon" sizes="72x72" href="<?= BASE_URL ?>assets/pwa/icon-72x72.png">
<link rel="apple-touch-icon" sizes="96x96" href="<?= BASE_URL ?>assets/pwa/icon-96x96.png">
<link rel="apple-touch-icon" sizes="128x128" href="<?= BASE_URL ?>assets/pwa/icon-128x128.png">
<link rel="apple-touch-icon" sizes="144x144" href="<?= BASE_URL ?>assets/pwa/icon-144x144.png">
<link rel="apple-touch-icon" sizes="152x152" href="<?= BASE_URL ?>assets/pwa/icon-152x152.png">
<link rel="apple-touch-icon" sizes="192x192" href="<?= BASE_URL ?>assets/pwa/icon-192x192.png">
<link rel="apple-touch-icon" sizes="384x384" href="<?= BASE_URL ?>assets/pwa/icon-384x384.png">
<link rel="apple-touch-icon" sizes="512x512" href="<?= BASE_URL ?>assets/pwa/icon-512x512.png">

<!-- Splash screens for iOS -->
<link rel="apple-touch-startup-image" href="<?= BASE_URL ?>assets/pwa/icon-512x512.png">

