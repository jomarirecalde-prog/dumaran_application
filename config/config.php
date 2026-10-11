<?php
require_once __DIR__ . '/load_env.php';

/**
 * Session and Security Configuration
 */
if (!defined('APP_DEBUG')) {
    $appDebugHost = $_SERVER['HTTP_HOST'] ?? '';
    $appDebugAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    define('APP_DEBUG', (
        $appDebugAddr === '127.0.0.1'
        || $appDebugAddr === '::1'
        || (is_string($appDebugHost) && strpos($appDebugHost, 'localhost') === 0)
    ));
}

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

// Configure session cookies for port forwarding compatibility
// This must be done before session_start()
if (session_status() === PHP_SESSION_NONE) {
    // Determine cookie domain - empty string allows any domain (needed for port forwarding)
    $cookieDomain = '';
    
    // Extract hostname from HTTP_HOST (removes port if present)
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!empty($host)) {
        // Remove port number if present (e.g., "localhost:8080" -> "localhost")
        $hostParts = explode(':', $host);
        $hostname = $hostParts[0];
        
        // For localhost/127.0.0.1, use empty domain (works with any port)
        // For other hosts, you might want to set a specific domain
        if ($hostname === 'localhost' || $hostname === '127.0.0.1' || $hostname === '::1') {
            $cookieDomain = '';
        } else {
            // For other hosts (like ngrok, cloudflare tunnels, etc.), use empty domain
            // This allows cookies to work across different ports and subdomains
            $cookieDomain = '';
        }
    }
    
    // Determine if we should use secure cookies (HTTPS)
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    $forwardedSsl = $_SERVER['HTTP_X_FORWARDED_SSL'] ?? '';
    $requestScheme = $_SERVER['REQUEST_SCHEME'] ?? '';
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443') ||
        (is_string($forwardedProto) && strtolower($forwardedProto) === 'https') ||
        (is_string($forwardedSsl) && strtolower($forwardedSsl) === 'on') ||
        (is_string($requestScheme) && strtolower($requestScheme) === 'https')
    );
    
    // Get the application path for cookie path
    // Calculate this independently since BASE_URL isn't defined yet
    $cookiePath = '/';
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT'])
        ? rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/')
        : '';
    $appRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    
    if ($documentRoot && $appRoot) {
        $documentRootLower = strtolower($documentRoot);
        $appRootLower = strtolower($appRoot);
        
        if (strpos($appRootLower, $documentRootLower) === 0) {
            $relativePath = substr($appRoot, strlen($documentRoot));
            $relativePath = '/' . ltrim($relativePath, '/');
            $cookiePath = rtrim($relativePath, '/') . '/';
        } else {
            // Fallback: derive path from known project directory name
            $projectDir = basename($appRoot);
            $cookiePath = '/' . trim($projectDir, '/') . '/';
        }
    }
    
    // Configure session cookie parameters for port forwarding
    session_set_cookie_params([
        'lifetime' => 0, // Session cookie (expires when browser closes)
        'path' => $cookiePath,
        'domain' => $cookieDomain, // Empty allows any domain/port
        'secure' => $isHttps, // Use secure cookies only for HTTPS
        'httponly' => true, // Prevent JavaScript access
        'samesite' => 'Lax' // CSRF protection while allowing port forwarding
    ]);
    
    session_start();
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Security headers (only if headers not already sent)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
}

// Constants
define('SITE_NAME', 'Dumaran Hiring System');
define('SITE_SHORT_NAME', 'Dumaran Hiring');
define('ORG_NAME', 'Municipality of Dumaran');
define('ORG_SHORT_NAME', 'Dumaran');
define('ORG_LOCATION', 'Dumaran, Palawan');
define('ORG_HR_EMAIL', 'hr@dumaran.gov.ph');
define('ORG_WEBSITE', 'https://www.dumaranpalawan.gov.ph');
define('ORG_DOCUMENT_SUFFIX', 'DUMHRMDO');
define('ORG_APPLICATION_LETTER_RECIPIENT_HTML', '<strong>THE MUNICIPAL MAYOR<br>Municipality of Dumaran<br>Dumaran, Palawan</strong>');

// Determine the application's base URL dynamically so assets still load through tunnels/port forwarding
if (!defined('BASE_URL')) {
    $fallbackBaseUrl = 'http://localhost/dumaran_hiring/';

    // CLI contexts or missing host should keep the fallback
    if (php_sapi_name() === 'cli' || empty($_SERVER['HTTP_HOST'])) {
        define('BASE_URL', $fallbackBaseUrl);
    } else {
        // Get the host from HTTP_HOST (preserves port forwarding URLs)
        // Also check forwarded headers for port forwarding services
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $forwardedHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? '';
        $originalHost = $_SERVER['HTTP_X_ORIGINAL_HOST'] ?? '';
        
        // Prioritize forwarded host headers (used by port forwarding services)
        // This is critical for port forwarding - if HTTP_HOST is localhost but we have
        // a forwarded host, use the forwarded host to preserve the tunnel URL
        if (!empty($forwardedHost)) {
            $host = $forwardedHost;
        } elseif (!empty($originalHost)) {
            $host = $originalHost;
        }
        
        // If HTTP_HOST is localhost but we're accessing through a tunnel,
        // check if we can detect the tunnel URL from other headers
        $isLocalhost = ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1');
        if ($isLocalhost && empty($forwardedHost) && empty($originalHost)) {
            // Check if we have a referer that might contain the tunnel URL
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if (!empty($referer) && preg_match('/^https?:\/\/([^\/]+)/', $referer, $matches)) {
                $refererHost = $matches[1];
                // If referer has a tunnel domain, use it instead of localhost
                $tunnelDomains = ['devtunnels.ms', 'ngrok.app', 'ngrok-free.app', 'loca.lt', 'trycloudflare.com'];
                foreach ($tunnelDomains as $domain) {
                    if (stripos($refererHost, $domain) !== false) {
                        $host = $refererHost;
                        break;
                    }
                }
            }
        }
        
        // If host is still empty, try SERVER_NAME as fallback
        if (empty($host)) {
            $host = $_SERVER['SERVER_NAME'] ?? '';
        }
        
        // Last resort: try to extract from REQUEST_URI if available
        if (empty($host) && !empty($_SERVER['REQUEST_URI'])) {
            // This is a fallback and shouldn't normally be needed
            // but can help in edge cases with port forwarding
            $requestUri = $_SERVER['REQUEST_URI'];
            if (preg_match('/^https?:\/\/([^\/]+)/', $requestUri, $matches)) {
                $host = $matches[1];
            }
        }
        
        // Check forwarded headers for protocol (important for port forwarding)
        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        $forwardedSsl = $_SERVER['HTTP_X_FORWARDED_SSL'] ?? '';
        $requestScheme = $_SERVER['REQUEST_SCHEME'] ?? '';
        $forwardedHeader = $_SERVER['HTTP_FORWARDED'] ?? '';
        $originalProto = $_SERVER['HTTP_X_ORIGINAL_PROTO'] ?? '';

        // Determine if HTTPS (check multiple sources for port forwarding compatibility)
        $isHttps = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443') ||
            (is_string($forwardedProto) && strtolower($forwardedProto) === 'https') ||
            (is_string($forwardedSsl) && strtolower($forwardedSsl) === 'on') ||
            (is_string($requestScheme) && strtolower($requestScheme) === 'https') ||
            (is_string($forwardedHeader) && stripos($forwardedHeader, 'proto=https') !== false) ||
            (is_string($originalProto) && strtolower($originalProto) === 'https')
        );

        $scheme = $isHttps ? 'https://' : 'http://';
        
        // Force HTTPS for known port forwarding/tunnel services
        if (!$isHttps && !empty($host)) {
            $forceHttpsHosts = [
                'devtunnels.ms',
                'ngrok.app',
                'ngrok-free.app',
                'loca.lt',
                'trycloudflare.com',
                'cloudflare.com'
            ];

            foreach ($forceHttpsHosts as $domain) {
                if (is_string($host) && stripos($host, $domain) !== false) {
                    $scheme = 'https://';
                    break;
                }
            }
        }

        // Calculate the application path
        $documentRoot = isset($_SERVER['DOCUMENT_ROOT'])
            ? rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/')
            : '';
        $appRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));

        $path = '/';
        if ($documentRoot && $appRoot) {
            $documentRootLower = strtolower($documentRoot);
            $appRootLower = strtolower($appRoot);

            if (strpos($appRootLower, $documentRootLower) === 0) {
                $relativePath = substr($appRoot, strlen($documentRoot));
                $relativePath = '/' . ltrim($relativePath, '/');
                $path = rtrim($relativePath, '/') . '/';
            } else {
                // Fallback: derive path from known project directory name
                $projectDir = basename($appRoot);
                $path = '/' . trim($projectDir, '/') . '/';
            }
        }

        // Construct base URL - preserve the original host (including port forwarding domains)
        // IMPORTANT: Never use localhost fallback if we have a valid non-localhost host
        // This prevents port forwarding URLs from being rewritten to localhost
        if (!empty($host) && $host !== 'localhost' && $host !== '127.0.0.1' && $host !== '::1') {
            // We have a valid non-localhost host (likely a port forwarding URL)
            $baseUrl = $scheme . $host . $path;
        } elseif (!empty($host)) {
            // We have localhost - use it only if no forwarded host was found
            $baseUrl = $scheme . $host . $path;
        } else {
            // No host found - use fallback
            $baseUrl = $fallbackBaseUrl;
        }

        // Validate and set BASE_URL
        $validatedUrl = filter_var($baseUrl, FILTER_VALIDATE_URL);
        if ($validatedUrl) {
            define('BASE_URL', $validatedUrl);
        } else {
            // If validation fails but we have a host, try to construct a valid URL
            if (!empty($host)) {
                $baseUrl = $scheme . $host . $path;
                define('BASE_URL', $baseUrl);
            } else {
                define('BASE_URL', $fallbackBaseUrl);
            }
        }
    }
}

define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// User roles
define('ROLE_ADMIN', 'admin');
define('ROLE_APPLICANT', 'applicant');

// Application status
define('STATUS_PENDING', 'pending');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');

// Email verification settings
define('EMAIL_VERIFICATION_CODE_LENGTH', 6);
define('EMAIL_VERIFICATION_EXPIRY_MINUTES', 10);
define('EMAIL_VERIFICATION_RESEND_INTERVAL', 60); // seconds
define('EMAIL_VERIFICATION_MAX_ATTEMPTS', 5);

// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', 'your-google-client-id.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'your-google-client-secret');
define('GOOGLE_REDIRECT_URI', BASE_URL . 'auth/google_oauth.php');

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Check if user has specific role
 */
function hasRole($role) {
    return isLoggedIn() && $_SESSION['role'] === $role;
}

/**
 * Redirect to login if not authenticated
 */
function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'shared/login.php');
        exit();
    }
}

/**
 * Redirect to login if not admin
 */
function requireAdmin() {
    requireAuth();
    if (!hasRole(ROLE_ADMIN)) {
        header('Location: ' . BASE_URL . 'applicant/pages/dashboard.php');
        exit();
    }
}

/**
 * Require applicant to accept data privacy waiver before using the system
 * Redirects to dashboard if waiver not accepted
 */
function requirePrivacyWaiver() {
    if (!hasRole(ROLE_APPLICANT)) {
        return; // Only applies to applicants
    }
    
    // Get current page to avoid redirect loop
    $currentPage = basename($_SERVER['PHP_SELF']);
    
    // Don't redirect if already on dashboard (where modal is shown)
    if ($currentPage === 'dashboard.php') {
        return;
    }
    
    try {
        require_once __DIR__ . '/../includes/models.php';
        $userModel = new User();
        $userId = $_SESSION['user_id'];
        $user = $userModel->findById($userId);
        
        // Check if user has accepted data privacy waiver
        if (empty($user['data_privacy_accepted_at'] ?? null)) {
            // Redirect to dashboard where modal will appear
            header('Location: ' . BASE_URL . 'applicant/pages/dashboard.php');
            exit();
        }
    } catch (Exception $e) {
        // If database error, allow access but log it
        error_log("Privacy waiver check error: " . $e->getMessage());
    }
}

/**
 * Sanitize input data
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Build display full name from name parts (First Middle Last).
 */
function buildFullNameFromParts($lastName, $firstName, $middleName = '') {
    $parts = array_filter([
        trim((string) $firstName),
        trim((string) $middleName),
        trim((string) $lastName),
    ], static function ($part) {
        return $part !== '';
    });

    return implode(' ', $parts);
}

/**
 * Normalize registration/profile name fields: ensure parts and full_name stay in sync.
 */
function normalizeUserNameFields(array &$data) {
    $lastName = trim($data['last_name'] ?? '');
    $firstName = trim($data['first_name'] ?? '');
    $middleName = trim($data['middle_name'] ?? '');
    $fullName = trim($data['full_name'] ?? '');

    if ($lastName !== '' || $firstName !== '') {
        $data['last_name'] = $lastName;
        $data['first_name'] = $firstName;
        $data['middle_name'] = $middleName !== '' ? $middleName : null;
        $data['full_name'] = buildFullNameFromParts($lastName, $firstName, $middleName);
    } elseif ($fullName !== '') {
        $data['full_name'] = $fullName;
    }
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    // Always ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    // Always ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($token)) {
        return false;
    }
    
    if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Detect mobile device
 */
function isMobile() {
    return preg_match('/(android|iphone|ipad|ipod|blackberry|iemobile|opera mini)/i', $_SERVER['HTTP_USER_AGENT']);
}

/**
 * Get appropriate template path
 */
function getTemplatePath() {
    return isMobile() ? 'mobile/' : 'desktop/';
}

// Load FormTokenManager for duplicate submission prevention
require_once __DIR__ . '/../includes/helpers/FormTokenManager.php';
?>