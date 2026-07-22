<?php
/**
 * Bootstrap File
 * Central initialization point for the application
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Error reporting (adjust for production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// Load configuration
require_once __DIR__ . '/../config/config.php';

// Load exceptions
require_once __DIR__ . '/exceptions/Exceptions.php';

// Load helpers
require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/Validator.php';

// Load services
require_once __DIR__ . '/services/Logger.php';
require_once __DIR__ . '/services/Cache.php';
require_once __DIR__ . '/services/FileUploadService.php';
require_once __DIR__ . '/services/EmailService.php';

// Load business logic services
require_once __DIR__ . '/services/UserService.php';
require_once __DIR__ . '/services/ApplicationService.php';
require_once __DIR__ . '/services/PositionService.php';

// Load models
require_once __DIR__ . '/models.php';

// Initialize global services
global $logger, $cache;
$logger = new Logger();
$cache = new Cache();

// Set error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    global $logger;
    
    // Don't log suppressed errors
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $logger->error("PHP Error: {$errstr}", [
        'file' => $errfile,
        'line' => $errline,
        'type' => $errno
    ]);
    
    return true;
});

// Set exception handler
set_exception_handler(function($exception) {
    global $logger;
    
    $logger->exception($exception);
    
    // Show generic error page in production
    if ($_ENV['APP_ENV'] ?? 'production' === 'production') {
        http_response_code(500);
        echo "An unexpected error occurred. Please try again later.";
    } else {
        // Show detailed error in development
        echo "<pre>";
        echo "Exception: " . $exception->getMessage() . "\n";
        echo "File: " . $exception->getFile() . "\n";
        echo "Line: " . $exception->getLine() . "\n";
        echo "Trace:\n" . $exception->getTraceAsString();
        echo "</pre>";
    }
    
    exit;
});

// Register shutdown function for fatal errors
register_shutdown_function(function() {
    global $logger;
    
    $error = error_get_last();
    
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $logger->critical("Fatal Error: {$error['message']}", [
            'file' => $error['file'],
            'line' => $error['line']
        ]);
    }
});

// Clean expired cache periodically (1% chance on each request)
if (rand(1, 100) === 1) {
    $cache->clearExpired();
}

// Clean old logs periodically (0.1% chance)
if (rand(1, 1000) === 1) {
    $logger->clearOldLogs(30); // Keep logs for 30 days
}

/**
 * Helper function to get logger instance
 */
function logger() {
    global $logger;
    return $logger;
}

/**
 * Helper function to get cache instance
 */
function cache() {
    global $cache;
    return $cache;
}

/**
 * Helper function to handle exceptions in try-catch blocks
 */
function handleError($e, $redirectUrl = null, $defaultMessage = 'An error occurred') {
    global $logger;
    
    // Log the error
    $logger->exception($e);
    
    // Get appropriate error message
    if ($e instanceof ValidationException || $e instanceof AuthenticationException) {
        $message = $e->getMessage();
    } else {
        $message = $defaultMessage;
    }
    
    // Redirect or display error
    if ($redirectUrl) {
        $_SESSION['error_message'] = $message;
        header('Location: ' . $redirectUrl);
        exit;
    } else {
        return $message;
    }
}

/**
 * Helper function for debugging (only in development)
 */
function dd(...$vars) {
    if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
        echo '<pre>';
        foreach ($vars as $var) {
            var_dump($var);
        }
        echo '</pre>';
        exit;
    }
}
