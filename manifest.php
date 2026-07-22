<?php
/**
 * Dynamic PWA Manifest
 * This generates the manifest.json dynamically to support dynamic BASE_URL
 */
require_once 'config/config.php';

// Set content type for JSON
header('Content-Type: application/json');

// Parse BASE_URL to get the path
$baseUrlParts = parse_url(BASE_URL);
$basePath = $baseUrlParts['path'] ?? '/Applicant_WPU/';

$manifest = [
    'name' => 'WPU Jobs - Applicant Portal',
    'short_name' => 'WPU Jobs',
    'description' => 'Western Philippines University Job Application Portal - Apply for positions and track your applications',
    'start_url' => $basePath . 'applicant/pages/dashboard.php',
    'scope' => $basePath,
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#1e3a5f',
    'theme_color' => '#1e3a5f',
    'categories' => ['business', 'productivity'],
    'lang' => 'en',
    'dir' => 'ltr',
    'icons' => [
        [
            'src' => $basePath . 'assets/pwa/icon-72x72.png',
            'sizes' => '72x72',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $basePath . 'assets/pwa/icon-96x96.png',
            'sizes' => '96x96',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $basePath . 'assets/pwa/icon-128x128.png',
            'sizes' => '128x128',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $basePath . 'assets/pwa/icon-144x144.png',
            'sizes' => '144x144',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $basePath . 'assets/pwa/icon-152x152.png',
            'sizes' => '152x152',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $basePath . 'assets/pwa/icon-192x192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $basePath . 'assets/pwa/icon-384x384.png',
            'sizes' => '384x384',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $basePath . 'assets/pwa/icon-512x512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ]
    ],
    'shortcuts' => [
        [
            'name' => 'Browse Jobs',
            'short_name' => 'Jobs',
            'description' => 'View available job positions',
            'url' => $basePath . 'applicant/pages/positions.php',
            'icons' => [['src' => $basePath . 'assets/pwa/icon-96x96.png', 'sizes' => '96x96']]
        ],
        [
            'name' => 'My Applications',
            'short_name' => 'Applications',
            'description' => 'View your job applications',
            'url' => $basePath . 'applicant/pages/my-applications.php',
            'icons' => [['src' => $basePath . 'assets/pwa/icon-96x96.png', 'sizes' => '96x96']]
        ],
        [
            'name' => 'Profile',
            'short_name' => 'Profile',
            'description' => 'Manage your profile',
            'url' => $basePath . 'applicant/pages/profile.php',
            'icons' => [['src' => $basePath . 'assets/pwa/icon-96x96.png', 'sizes' => '96x96']]
        ]
    ],
    'related_applications' => [],
    'prefer_related_applications' => false
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>

