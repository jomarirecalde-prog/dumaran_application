<?php
require_once '../config/config.php';
require_once '../includes/models.php';

// Log the logout activity if user is logged in
if (isLoggedIn()) {
    try {
        $userModel = new User();
        $userModel->logActivity(
            $_SESSION['user_id'],
            'logout',
            'User logged out',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        );
    } catch (Exception $e) {
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page with message
header('Location: ' . BASE_URL . 'shared/login.php?message=' . urlencode('You have been logged out successfully'));
exit();
?>