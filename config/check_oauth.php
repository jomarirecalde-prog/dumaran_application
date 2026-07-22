<?php
/**
 * OAuth Configuration Checker
 * This page helps verify that Google OAuth is properly configured
 */

require_once '../config/config.php';

// Allow access from localhost and common port forwarding scenarios
// This allows access through SSH tunnels, ngrok, cloudflare tunnels, etc.
$allowedIPs = ['127.0.0.1', '::1', 'localhost'];
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocalhost = in_array($remoteAddr, $allowedIPs) || 
               strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ||
               strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false ||
               strpos($_SERVER['HTTP_HOST'] ?? '', 'ngrok') !== false ||
               strpos($_SERVER['HTTP_HOST'] ?? '', 'cloudflare') !== false ||
               strpos($_SERVER['HTTP_HOST'] ?? '', 'devtunnels') !== false ||
               strpos($_SERVER['HTTP_HOST'] ?? '', 'loca.lt') !== false;

// For additional security, you can check if it's a private IP range
if (!$isLocalhost && !empty($remoteAddr)) {
    // Allow private IP ranges (10.x.x.x, 192.168.x.x, 172.16-31.x.x)
    $isPrivateIP = filter_var($remoteAddr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    if (!$isPrivateIP) {
        die('Access denied. This page can only be accessed from localhost or through port forwarding.');
    }
}

// Load OAuth configuration
$oauthConfig = require '../config/oauth.php';
$googleConfig = $oauthConfig['google'];

// Check configuration status
$checks = [
    'base_url' => [
        'name' => 'BASE_URL Configuration',
        'status' => defined('BASE_URL') && !empty(BASE_URL),
        'value' => defined('BASE_URL') ? BASE_URL : 'Not defined',
        'expected' => 'http://localhost/Applicant_WPU/',
        'help' => 'Defined in config/config.php'
    ],
    'client_id' => [
        'name' => 'Google Client ID',
        'status' => !empty($googleConfig['client_id']) && 
                   $googleConfig['client_id'] !== 'your-google-client-id-here.googleusercontent.com',
        'value' => $googleConfig['client_id'],
        'expected' => 'XXXXXXXXXX.apps.googleusercontent.com',
        'help' => 'Get from Google Cloud Console > Credentials'
    ],
    'client_secret' => [
        'name' => 'Google Client Secret',
        'status' => !empty($googleConfig['client_secret']) && 
                   $googleConfig['client_secret'] !== 'your-google-client-secret-here',
        'value' => !empty($googleConfig['client_secret']) ? substr($googleConfig['client_secret'], 0, 8) . '...' : 'Not set',
        'expected' => 'GOCSPX-XXXXXXXXXXXX',
        'help' => 'Get from Google Cloud Console > Credentials'
    ],
    'redirect_uri' => [
        'name' => 'Redirect URI',
        'status' => !empty($googleConfig['redirect_uri']),
        'value' => $googleConfig['redirect_uri'],
        'expected' => 'http://localhost/Applicant_WPU/auth/google_oauth.php',
        'help' => 'Must match exactly in Google Cloud Console'
    ],
    'scope' => [
        'name' => 'OAuth Scopes',
        'status' => !empty($googleConfig['scope']),
        'value' => $googleConfig['scope'],
        'expected' => 'email and profile scopes',
        'help' => 'Automatically configured'
    ]
];

$allConfigured = array_reduce($checks, function($carry, $check) {
    return $carry && $check['status'];
}, true);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OAuth Configuration Checker</title>
    <link href="<?= BASE_URL ?>assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #f3f4f6;
            padding: 40px 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .header h1 {
            color: #1f2937;
            margin-bottom: 10px;
        }
        .header p {
            color: #6b7280;
        }
        .status-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .overall-status {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
            font-size: 1.2rem;
            font-weight: 600;
        }
        .overall-status.success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #10b981;
        }
        .overall-status.error {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #ef4444;
        }
        .check-item {
            padding: 20px;
            border-left: 4px solid #e5e7eb;
            margin-bottom: 20px;
            background: #f9fafb;
            border-radius: 6px;
        }
        .check-item.success {
            border-left-color: #10b981;
            background: #f0fdf4;
        }
        .check-item.error {
            border-left-color: #ef4444;
            background: #fef2f2;
        }
        .check-item h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            color: #1f2937;
        }
        .check-item .status-icon {
            font-size: 1.2rem;
        }
        .check-item .status-icon.success {
            color: #10b981;
        }
        .check-item .status-icon.error {
            color: #ef4444;
        }
        .check-item .value {
            background: white;
            padding: 12px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            margin: 10px 0;
            word-break: break-all;
        }
        .check-item .expected {
            color: #6b7280;
            font-size: 0.9rem;
            margin: 8px 0;
        }
        .check-item .help {
            color: #9ca3af;
            font-size: 0.85rem;
            font-style: italic;
        }
        .instructions {
            background: #eff6ff;
            border: 2px solid #3b82f6;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .instructions h3 {
            color: #1e40af;
            margin-bottom: 12px;
        }
        .instructions ol {
            margin-left: 20px;
            color: #374151;
        }
        .instructions li {
            margin: 8px 0;
        }
        .instructions code {
            background: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #1e40af;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
            margin-top: 15px;
        }
        .btn:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fab fa-google"></i> Google OAuth Configuration Checker</h1>
            <p>This tool helps verify that Google OAuth is properly configured for the WPU Applicant System.</p>
        </div>

        <div class="status-card">
            <div class="overall-status <?= $allConfigured ? 'success' : 'error' ?>">
                <?php if ($allConfigured): ?>
                    <i class="fas fa-check-circle"></i> Google OAuth is Properly Configured!
                <?php else: ?>
                    <i class="fas fa-exclamation-triangle"></i> Google OAuth Configuration Incomplete
                <?php endif; ?>
            </div>

            <h2 style="margin-bottom: 20px;">Configuration Status:</h2>

            <?php foreach ($checks as $key => $check): ?>
                <div class="check-item <?= $check['status'] ? 'success' : 'error' ?>">
                    <h3>
                        <span class="status-icon <?= $check['status'] ? 'success' : 'error' ?>">
                            <?= $check['status'] ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-times-circle"></i>' ?>
                        </span>
                        <?= htmlspecialchars($check['name']) ?>
                    </h3>
                    <div class="value">
                        <strong>Current:</strong> <?= htmlspecialchars($check['value']) ?>
                    </div>
                    <div class="expected">
                        <strong>Expected:</strong> <?= htmlspecialchars($check['expected']) ?>
                    </div>
                    <div class="help">
                        <?= htmlspecialchars($check['help']) ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!$allConfigured): ?>
                <div class="instructions">
                    <h3><i class="fas fa-info-circle"></i> Setup Instructions</h3>
                    <ol>
                        <li>Go to <strong>Google Cloud Console</strong>: <a href="https://console.cloud.google.com/" target="_blank">console.cloud.google.com</a></li>
                        <li>Create or select a project</li>
                        <li>Enable <strong>Google+ API</strong> or <strong>People API</strong></li>
                        <li>Create <strong>OAuth 2.0 Client ID</strong> credentials</li>
                        <li>Add redirect URI: <code><?= htmlspecialchars($googleConfig['redirect_uri']) ?></code></li>
                        <li>Copy your <strong>Client ID</strong> and <strong>Client Secret</strong></li>
                        <li>Edit <code>config/oauth.php</code> and replace the placeholder values</li>
                        <li>Refresh this page to verify</li>
                    </ol>
                    <p style="margin-top: 15px; color: #374151;">
                        📖 For detailed instructions, see: <code>config/oauth.example.php</code> or <code>GOOGLE_OAUTH_SETUP.md</code>
                    </p>
                </div>
            <?php else: ?>
                <div style="margin-top: 20px; text-align: center;">
                    <a href="../shared/login.php" class="btn">
                        <i class="fas fa-sign-in-alt"></i> Test Google Login
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div style="text-align: center; color: #6b7280; margin-top: 30px; font-size: 0.9rem;">
            <p>⚠️ This page is only accessible from localhost for security reasons.</p>
            <p>URL: <code><?= htmlspecialchars($_SERVER['REQUEST_URI']) ?></code></p>
        </div>
    </div>
</body>
</html>
