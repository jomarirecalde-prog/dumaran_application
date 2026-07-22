<?php
require_once 'config/config.php';

// Prevent infinite redirects
$redirect_count = isset($_SESSION['index_redirect_count']) ? $_SESSION['index_redirect_count'] : 0;

// Auto-detect mobile and redirect
if (isMobile() && $redirect_count < 2) {
    $currentPath = $_SERVER['REQUEST_URI'];
    
    // Check if we're already in mobile folder
    if (strpos($currentPath, '/mobile/') === false) {
        $_SESSION['index_redirect_count'] = $redirect_count + 1;
        header('Location: ' . BASE_URL . 'mobile/dashboard.php');
        exit();
    }
}

// If not mobile or already logged in, redirect appropriately
if (isLoggedIn() && $redirect_count < 2) {
    $_SESSION['index_redirect_count'] = $redirect_count + 1;
    if (hasRole(ROLE_ADMIN)) {
        header('Location: ' . BASE_URL . 'admin/pages/dashboard.php');
    } else {
        header('Location: ' . BASE_URL . 'applicant/pages/dashboard.php');
    }
    exit();
}

// If we've had too many redirects or not logged in, go to login
if ($redirect_count >= 2) {
    // Reset redirect count and show a message
    unset($_SESSION['index_redirect_count']);
    header('Location: ' . BASE_URL . 'shared/login.php?message=Please login to continue');
} else {
    // Normal redirect to login
    header('Location: ' . BASE_URL . 'shared/login.php');
}
exit();
?>