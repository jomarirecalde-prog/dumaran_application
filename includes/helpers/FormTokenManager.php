<?php
/**
 * FormTokenManager - Prevents duplicate form submissions
 * 
 * This class manages unique form tokens to prevent duplicate submissions
 * when users refresh the page after submitting a form.
 * 
 * Usage:
 *   // In your form:
 *   FormTokenManager::generateFormToken('application_form');
 *   
 *   // In your form processor:
 *   if (!FormTokenManager::validateFormToken($_POST['form_token'] ?? '')) {
 *       // Handle duplicate submission
 *   }
 */
class FormTokenManager {
    
    /** Max age of a form token in seconds (1 hour) */
    private static $tokenMaxAge = 3600;

    /**
     * Generate a unique token for a form (reuses existing valid token if present)
     * 
     * Reusing an existing unused, non-expired token prevents "Invalid form token"
     * when the user has multiple tabs or refreshes before submitting.
     * 
     * @param string $formName Unique identifier for the form
     * @return string The generated token
     */
    public static function generateFormToken($formName = 'default') {
        // Initialize tokens array if not exists
        if (!isset($_SESSION['form_tokens'])) {
            $_SESSION['form_tokens'] = [];
        }
        
        $tokenKey = self::getTokenKey($formName);
        
        // Clean old tokens first
        self::cleanOldTokens();
        
        // Reuse existing token if it's unused and not expired
        if (isset($_SESSION['form_tokens'][$tokenKey])) {
            $stored = $_SESSION['form_tokens'][$tokenKey];
            $age = time() - ($stored['timestamp'] ?? 0);
            if (!$stored['used'] && $age < self::$tokenMaxAge) {
                return $stored['token'];
            }
        }
        
        // Generate new token
        $token = bin2hex(random_bytes(32));
        $_SESSION['form_tokens'][$tokenKey] = [
            'token' => $token,
            'timestamp' => time(),
            'used' => false
        ];
        
        return $token;
    }
    
    /**
     * Validate and consume a form token
     * 
     * @param string $token The token to validate
     * @param string $formName Unique identifier for the form
     * @return bool True if token is valid and not used, false otherwise
     */
    public static function validateFormToken($token, $formName = 'default') {
        if (empty($token)) {
            return false;
        }
        
        $tokenKey = self::getTokenKey($formName);
        
        // Check if token exists
        if (!isset($_SESSION['form_tokens'][$tokenKey])) {
            return false;
        }
        
        $storedToken = $_SESSION['form_tokens'][$tokenKey];
        
        // Check if token matches and hasn't been used
        if ($storedToken['token'] === $token && !$storedToken['used']) {
            // Mark token as used
            $_SESSION['form_tokens'][$tokenKey]['used'] = true;
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if a token has been used (for detecting duplicate submissions)
     * 
     * @param string $token The token to check
     * @param string $formName Unique identifier for the form
     * @return bool True if token was already used
     */
    public static function isTokenUsed($token, $formName = 'default') {
        if (empty($token)) {
            return false;
        }
        
        $tokenKey = self::getTokenKey($formName);
        
        if (!isset($_SESSION['form_tokens'][$tokenKey])) {
            return false;
        }
        
        $storedToken = $_SESSION['form_tokens'][$tokenKey];
        
        return $storedToken['token'] === $token && $storedToken['used'];
    }
    
    /**
     * Render hidden form token input field
     * 
     * @param string $formName Unique identifier for the form
     * @return void
     */
    public static function renderFormTokenInput($formName = 'default') {
        $token = self::generateFormToken($formName);
        echo '<input type="hidden" name="form_token" value="' . htmlspecialchars($token) . '">';
        echo '<input type="hidden" name="form_name" value="' . htmlspecialchars($formName) . '">';
    }
    
    /**
     * Get form token from POST data
     * 
     * @return array Array with 'token' and 'form_name' keys
     */
    public static function getFormTokenFromPost() {
        return [
            'token' => $_POST['form_token'] ?? '',
            'form_name' => $_POST['form_name'] ?? 'default'
        ];
    }
    
    /**
     * Validate form submission (checks both CSRF and form token)
     * 
     * @param array $postData POST data array
     * @return array Array with 'valid' (bool) and 'error' (string) keys
     */
    public static function validateFormSubmission($postData) {
        // Check CSRF token first
        if (!isset($postData['csrf_token']) || !verifyCSRFToken($postData['csrf_token'])) {
            return [
                'valid' => false,
                'error' => 'Invalid security token. Please refresh the page and try again.'
            ];
        }
        
        // Get form token
        $formToken = $postData['form_token'] ?? '';
        $formName = $postData['form_name'] ?? 'default';
        
        // Check if this is a duplicate submission
        if (self::isTokenUsed($formToken, $formName)) {
            return [
                'valid' => false,
                'error' => 'duplicate',
                'message' => 'This form has already been submitted. Please do not refresh the page.'
            ];
        }
        
        // Validate and consume token
        if (!self::validateFormToken($formToken, $formName)) {
            return [
                'valid' => false,
                'error' => 'Invalid form token. Please refresh the page and try again.'
            ];
        }
        
        return [
            'valid' => true,
            'error' => ''
        ];
    }
    
    /**
     * Generate token key for session storage
     * 
     * @param string $formName Form identifier
     * @return string Token key
     */
    private static function getTokenKey($formName) {
        return 'form_' . $formName;
    }
    
    /**
     * Clean up old tokens (older than 1 hour)
     * 
     * @return void
     */
    private static function cleanOldTokens() {
        if (!isset($_SESSION['form_tokens'])) {
            return;
        }
        
        $currentTime = time();
        foreach ($_SESSION['form_tokens'] as $key => $tokenData) {
            if (($currentTime - ($tokenData['timestamp'] ?? 0)) > self::$tokenMaxAge) {
                unset($_SESSION['form_tokens'][$key]);
            }
        }
    }
    
    /**
     * Clear all form tokens for a specific form
     * 
     * @param string $formName Form identifier
     * @return void
     */
    public static function clearFormTokens($formName = null) {
        if ($formName === null) {
            // Clear all tokens
            $_SESSION['form_tokens'] = [];
        } else {
            // Clear specific form tokens
            $tokenKey = self::getTokenKey($formName);
            if (isset($_SESSION['form_tokens'][$tokenKey])) {
                unset($_SESSION['form_tokens'][$tokenKey]);
            }
        }
    }
}
?>
