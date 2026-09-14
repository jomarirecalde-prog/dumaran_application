<?php
/**
 * PWA Configuration API
 * Returns PWA configuration including dynamic base paths
 */
require_once '../config/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

// Parse BASE_URL to get the path
$baseUrlParts = parse_url(BASE_URL);
$basePath = $baseUrlParts['path'] ?? '/Applicant_WPU/';

$config = [
    'basePath' => $basePath,
    'baseUrl' => BASE_URL,
    'offlineUrl' => $basePath . 'offline.html',
    'cacheName' => 'wpu-jobs-v2',
    'precacheAssets' => [
        $basePath . 'assets/css/office-theme.css',
        $basePath . 'assets/css/toast.css',
        $basePath . 'assets/js/office-theme.js',
        $basePath . 'assets/js/toast.js',
        $basePath . 'assets/js/notifications.js',
        $basePath . 'assets/logo.png',
        $basePath . 'assets/pwa/icon-192x192.png',
        $basePath . 'assets/pwa/icon-512x512.png',
        $basePath . 'favicon.ico',
        $basePath . 'offline.html',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
    ]
];

echo json_encode($config, JSON_UNESCAPED_SLASHES);
?>

