<?php
require_once '../config/config.php';
require_once '../includes/models.php';

// Load OAuth configuration
$oauthConfig = require_once '../config/oauth.php';
$googleConfig = $oauthConfig['google'];

// Check if OAuth is properly configured
if (!$googleConfig['configured'] || 
    $googleConfig['client_id'] === 'your-google-client-id-here.googleusercontent.com' ||
    empty($googleConfig['client_id']) || 
    empty($googleConfig['client_secret'])) {
    
    $errorMsg = 'Google OAuth is not configured. Please contact the system administrator.';
    error_log("Google OAuth Error: Client credentials not configured in config/oauth.php");
    header('Location: ' . BASE_URL . 'shared/login.php?error=' . urlencode($errorMsg));
    exit();
}

// Function to make HTTP requests
function makeHttpRequest($url, $data = null, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['data' => $result, 'http_code' => $httpCode];
}

// Check if this is the callback from Google
if (isset($_GET['code'])) {
    // Exchange authorization code for access token
    $tokenData = [
        'code' => $_GET['code'],
        'client_id' => $googleConfig['client_id'],
        'client_secret' => $googleConfig['client_secret'],
        'redirect_uri' => $googleConfig['redirect_uri'],
        'grant_type' => 'authorization_code'
    ];
    
    try {
        $tokenResponse = makeHttpRequest($googleConfig['token_url'], http_build_query($tokenData), [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        if ($tokenResponse['http_code'] !== 200) {
            throw new Exception('Failed to get access token');
        }
        
        $tokenResult = json_decode($tokenResponse['data'], true);
        
        if (!isset($tokenResult['access_token'])) {
            throw new Exception('No access token received');
        }
        
        // Get user info from Google
        $userInfoResponse = makeHttpRequest($googleConfig['userinfo_url'] . '?access_token=' . $tokenResult['access_token']);
        
        if ($userInfoResponse['http_code'] !== 200) {
            throw new Exception('Failed to get user info');
        }
        
        $userInfo = json_decode($userInfoResponse['data'], true);
        
        if (!isset($userInfo['email'])) {
            throw new Exception('No email received from Google');
        }
        
        // Process the user login/registration
        $userModel = new User();
        
        // Check if user exists by Google ID
        $user = $userModel->findByGoogleId($userInfo['id']);
        
        if (!$user) {
            // Check if user exists by email (regular account)
            $existingUser = $userModel->findByEmail($userInfo['email']);
            
            if ($existingUser) {
                if ($existingUser['oauth_provider'] === null) {
                    // User has a regular account, link Google account
                    $updateData = [
                        'google_id' => $userInfo['id'],
                        'oauth_provider' => 'google'
                    ];
                    $userModel->update($existingUser['id'], $updateData);
                    $user = $userModel->findById($existingUser['id']);
                } else {
                    throw new Exception('An account with this email already exists');
                }
            } else {
                // Create new user
                $user = $userModel->createGoogleUser([
                    'id' => $userInfo['id'],
                    'email' => $userInfo['email'],
                    'name' => $userInfo['name'] ?? $userInfo['email']
                ]);
                
                if (!$user) {
                    throw new Exception('Failed to create user account');
                }
            }
        }
        
        // Check if account is active and is an applicant
        if (!$user['is_active']) {
            header('Location: ' . BASE_URL . 'shared/login.php?error=Account is deactivated');
            exit();
        }
        
        if ($user['role'] !== ROLE_APPLICANT) {
            header('Location: ' . BASE_URL . 'shared/login.php?error=Google login is only available for applicants');
            exit();
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['last_activity'] = time();
        
        // Log the login activity
        $pdo = $userModel->getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
            VALUES (?, 'google_login', 'User logged in via Google OAuth', ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        // Redirect to dashboard
        header('Location: ' . BASE_URL . 'applicant/pages/dashboard.php');
        exit();
        
    } catch (Exception $e) {
        error_log("Google OAuth error: " . $e->getMessage());
        header('Location: ' . BASE_URL . 'shared/login.php?error=' . urlencode('Google authentication failed. Please try again.'));
        exit();
    }
    
} elseif (isset($_GET['error'])) {
    // User denied access or other error
    $error = $_GET['error'] === 'access_denied' ? 'Google login was cancelled' : 'Google authentication error';
    header('Location: ' . BASE_URL . 'shared/login.php?error=' . urlencode($error));
    exit();
    
} else {
    // Redirect to Google for authorization
    $params = [
        'client_id' => $googleConfig['client_id'],
        'redirect_uri' => $googleConfig['redirect_uri'],
        'scope' => $googleConfig['scope'],
        'response_type' => 'code',
        'access_type' => 'online'
    ];
    
    $authUrl = $googleConfig['auth_url'] . '?' . http_build_query($params);
    header('Location: ' . $authUrl);
    exit();
}
?>