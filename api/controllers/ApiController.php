<?php
/**
 * API Base Controller
 * Common functionality for all API endpoints
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/models.php';
require_once __DIR__ . '/../../includes/helpers/Response.php';
require_once __DIR__ . '/../../includes/helpers/Validator.php';
require_once __DIR__ . '/../../includes/exceptions/Exceptions.php';

class ApiController {
    protected $method;
    protected $requestData;

    public function __construct() {
        // Ensure no output before headers
        if (ob_get_level() == 0) {
            ob_start();
        }
        
        // Set JSON content type
        header('Content-Type: application/json', true);
        
        // Get request method
        $this->method = $_SERVER['REQUEST_METHOD'];
        
        // Get request data
        $this->requestData = $this->getRequestData();
        
        // Handle CORS if needed
        $this->handleCors();
    }

    /**
     * Get request data based on method
     */
    protected function getRequestData() {
        $data = [];
        
        switch ($this->method) {
            case 'GET':
                $data = $_GET;
                break;
            case 'POST':
            case 'PUT':
            case 'PATCH':
                $data = array_merge($_POST, (array)json_decode(file_get_contents('php://input'), true));
                break;
            case 'DELETE':
                // Try JSON first, then fall back to URL-encoded
                $input = file_get_contents('php://input');
                $jsonData = json_decode($input, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                    $data = $jsonData;
                } else {
                    parse_str($input, $data);
                }
                // Also merge GET parameters for action
                $data = array_merge($data, $_GET);
                break;
        }
        
        return $data;
    }

    /**
     * Handle CORS - supports port forwarding scenarios
     */
    protected function handleCors() {
        // For port forwarding, we need to allow the origin that's making the request
        // This works with SSH tunnels, ngrok, cloudflare tunnels, etc.
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        
        if ($origin) {
            // Validate origin to prevent open redirects
            // Allow localhost, 127.0.0.1, and common port forwarding services
            $allowedPatterns = [
                '/^https?:\/\/(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/i',
                '/^https?:\/\/.*\.ngrok\.(app|io)(:\d+)?$/i',
                '/^https?:\/\/.*\.cloudflare\.com(:443)?$/i',
                '/^https?:\/\/.*\.devtunnels\.ms(:443)?$/i',
                '/^https?:\/\/.*\.loca\.lt(:443)?$/i',
                '/^https?:\/\/.*\.trycloudflare\.com(:443)?$/i',
            ];
            
            $isAllowed = false;
            foreach ($allowedPatterns as $pattern) {
                if (preg_match($pattern, $origin)) {
                    $isAllowed = true;
                    break;
                }
            }
            
            // Also allow if origin matches the current host (for same-origin requests)
            $currentHost = ($_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '');
            if (strpos($origin, $currentHost) === 0) {
                $isAllowed = true;
            }
            
            if ($isAllowed) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Access-Control-Allow-Credentials: true');
            }
        } else {
            // If no origin header, allow from same origin (for direct access)
            header('Access-Control-Allow-Credentials: true');
        }
        
        header('Access-Control-Max-Age: 86400');

        if ($this->method === 'OPTIONS') {
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
                header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            }

            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
                header('Access-Control-Allow-Headers: ' . $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
            } else {
                // Default headers for port forwarding compatibility
                header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-TOKEN, X-Requested-With');
            }

            exit(0);
        }
    }

    /**
     * Verify authentication
     */
    protected function requireAuth() {
        if (!isLoggedIn()) {
            Response::unauthorized('Authentication required');
        }
        
        return $_SESSION['user_id'];
    }

    /**
     * Verify admin role
     */
    protected function requireAdmin() {
        $userId = $this->requireAuth();
        
        if (!hasRole(ROLE_ADMIN)) {
            Response::forbidden('Admin access required');
        }
        
        return $userId;
    }

    /**
     * Verify CSRF token
     */
    protected function verifyCsrf() {
        $token = $this->requestData['csrf_token'] ?? 
                 ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null) ?? 
                 ($_POST['csrf_token'] ?? null);
        
        if (!$token || !verifyCSRFToken($token)) {
            error_log('CSRF verification failed. Token provided: ' . ($token ? 'yes' : 'no'));
            error_log('Request data: ' . print_r($this->requestData, true));
            error_log('Headers: ' . print_r(getallheaders(), true));
            Response::error('Invalid CSRF token', 403);
        }
    }

    /**
     * Get pagination parameters
     */
    protected function getPagination() {
        $page = max(1, (int)($this->requestData['page'] ?? 1));
        $perPage = min(100, max(1, (int)($this->requestData['per_page'] ?? 10)));
        $offset = ($page - 1) * $perPage;
        
        return compact('page', 'perPage', 'offset');
    }

    /**
     * Handle exceptions
     */
    protected function handleException($e) {
        error_log("API Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        
        if ($e instanceof ValidationException) {
            Response::error($e->getMessage(), 422);
        } elseif ($e instanceof AuthenticationException) {
            Response::unauthorized($e->getMessage());
        } elseif ($e instanceof AuthorizationException) {
            Response::forbidden($e->getMessage());
        } elseif ($e instanceof NotFoundException) {
            Response::notFound($e->getMessage());
        } else {
            Response::serverError('An unexpected error occurred');
        }
    }
}
