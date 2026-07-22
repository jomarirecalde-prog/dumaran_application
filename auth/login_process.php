<?php
require_once '../config/config.php';
require_once '../includes/models.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'shared/login.php');
    exit();
}

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    header('Location: ' . BASE_URL . 'shared/login.php?error=Invalid request');
    exit();
}

// Sanitize input
$email = sanitize($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validate input
if (empty($email) || empty($password)) {
    header('Location: ' . BASE_URL . 'shared/login.php?error=All fields are required');
    exit();
}

try {
    $userModel = new User();
    $user = $userModel->verifyPassword($email, $password);

    if ($user && $user['is_active']) {
        if (empty($user['email_verified_at'])) {
            $_SESSION['pending_verification_email'] = $user['email'];
            header('Location: ' . BASE_URL . 'shared/verify-email.php?email=' . urlencode($user['email']) . '&notice=Please verify your email before logging in.');
            exit();
        }

        // Check if user has a password (not OAuth user trying to login with password)
        if ($user['oauth_provider'] && !$user['password']) {
            header('Location: ' . BASE_URL . 'shared/login.php?error=Please use Google Sign-in for this account');
            exit();
        }

        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['last_activity'] = time();

        // Log the login activity
        $userModel->logActivity(
            $user['id'],
            'login',
            'User logged in successfully'
        );

        // Redirect based on user's role from database
        if ($user['role'] === ROLE_ADMIN) {
            header('Location: ' . BASE_URL . 'admin/pages/dashboard.php');
        } else {
            header('Location: ' . BASE_URL . 'applicant/pages/dashboard.php');
        }
        exit();
    } else {
        header('Location: ' . BASE_URL . 'shared/login.php?error=Invalid email or password');
        exit();
    }
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    header('Location: ' . BASE_URL . 'shared/login.php?error=An error occurred. Please try again.');
    exit();
}
?>