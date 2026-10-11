<?php
require_once '../config/config.php';
require_once '../includes/models.php';
require_once '../includes/services/UserService.php';
require_once '../includes/exceptions/Exceptions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $redirect = hasRole(ROLE_ADMIN) ? 'admin/pages/dashboard.php' : 'applicant/pages/dashboard.php';
    header('Location: ' . BASE_URL . $redirect);
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form submission (CSRF + Form Token)
    $validation = FormTokenManager::validateFormSubmission($_POST);
    
    if (!$validation['valid']) {
        if ($validation['error'] === 'duplicate') {
            // Redirect to login page to avoid resubmission
            header('Location: ' . BASE_URL . 'shared/login.php?info=Registration already processed. Please login.');
            exit();
        }
        $error = $validation['error'];
    } else {
        // Sanitize input
        $username = sanitize($_POST['username'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $lastName = sanitize($_POST['last_name'] ?? '');
        $firstName = sanitize($_POST['first_name'] ?? '');
        $middleName = sanitize($_POST['middle_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');

        // Validate input
        if (empty($username) || empty($email) || empty($password) || empty($lastName) || empty($firstName)) {
            $error = 'All required fields must be filled';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format';
        } else {
            try {
                $userService = new UserService();
                $userData = [
                    'username' => $username,
                    'email' => $email,
                    'password' => $password,
                    'role' => ROLE_APPLICANT,
                    'last_name' => $lastName,
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'phone' => $phone,
                    'address' => $address
                ];

                $user = $userService->registerUser($userData);

                $_SESSION['pending_verification_email'] = $user['email'];
                if (!$user['verification_email_sent']) {
                    $_SESSION['pending_verification_notice'] = 'We created your account but could not send the verification email. Please click "Resend code" to try again.';
                }

                FormTokenManager::clearFormTokens('register_user');

                header('Location: ' . BASE_URL . 'shared/verify-email.php?email=' . urlencode($user['email']) . '&new=1');
                exit();
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            } catch (Exception $e) {
                error_log("Registration error: " . $e->getMessage());
                $error = 'An error occurred. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Dumaran Hiring System</title>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>favicon.ico">
    <link href="<?= BASE_URL ?>assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/office-theme.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: linear-gradient(135deg, #2050A0 0%, #2050A0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 600px;
            width: 100%;
            animation: slideUp 0.4s ease;
            margin: 20px auto;
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .register-header {
            background: linear-gradient(135deg, #2050A0 0%, #2050A0 100%);
            padding: 40px 40px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .register-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 3s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.3; }
        }
        .register-header-content {
            position: relative;
            z-index: 1;
        }
        .register-header i {
            font-size: 3rem;
            margin-bottom: 12px;
            opacity: 0.95;
        }
        .register-header h2 {
            font-size: 1.85rem;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        .register-header p {
            opacity: 0.9;
            margin: 0;
            font-size: 1rem;
            font-weight: 300;
        }
        .register-form {
            padding: 36px 40px;
            max-height: calc(120vh - 220px);
            overflow-y: auto;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            background: #f9fafb;
        }
        .form-control:focus {
            outline: none;
            border-color: #2050A0;
            background: white;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        .form-control::placeholder {
            color: #9ca3af;
        }
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        .btn-register {
            width: 100%;
            background: linear-gradient(135deg, #2050A0 0%, #2050A0 100%);
            color: white;
            border: none;
            padding: 15px 24px;
            font-weight: 600;
            font-size: 1rem;
            text-transform: none;
            letter-spacing: 0.3px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
            margin-bottom: 20px;
        }
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.5);
        }
        .btn-register:active {
            transform: translateY(0);
        }
        .google-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 13px 24px;
            border: 2px solid #e5e7eb;
            background: white;
            color: #374151;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
            margin-bottom: 20px;
        }
        .google-btn:hover {
            background: #f9fafb;
            border-color: #d1d5db;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        .google-btn i {
            font-size: 1.2rem;
            color: #ea4335;
        }
        .link-primary {
            color: #2050A0;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }
        .link-primary:hover {
            color: #2050A0;
            text-decoration: underline;
        }
        .divider {
            text-align: center;
            margin: 24px 0;
            position: relative;
        }
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e5e7eb;
        }
        .divider span {
            background: white;
            padding: 0 20px;
            position: relative;
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .text-center {
            text-align: center;
        }
        .text-center p {
            margin: 0;
            color: #6b7280;
            font-size: 0.95rem;
        }
        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: start;
            gap: 12px;
            font-size: 0.9rem;
        }
        .alert i {
            margin-top: 2px;
            flex-shrink: 0;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .success-message {
            text-align: center;
            padding: 60px 40px;
        }
        .success-message i {
            font-size: 5rem;
            color: #10b981;
            margin-bottom: 24px;
            animation: scaleIn 0.5s ease;
        }
        @keyframes scaleIn {
            from {
                transform: scale(0);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }
        .success-message h3 {
            font-size: 1.75rem;
            color: #1f2937;
            margin-bottom: 12px;
            font-weight: 700;
        }
        .success-message p {
            color: #6b7280;
            margin-bottom: 28px;
            font-size: 1rem;
            line-height: 1.6;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-row--names {
            grid-template-columns: 1fr 1fr 1fr;
        }
        @media (max-width: 640px) {
            .form-row,
            .form-row--names {
                grid-template-columns: 1fr;
            }
            .register-header {
                padding: 32px 28px;
            }
            .register-header i {
                font-size: 2.5rem;
            }
            .register-header h2 {
                font-size: 1.5rem;
            }
            .register-form {
                padding: 28px 24px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
            <div class="register-form">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>
                
            

                <form method="POST" id="registerForm" class="prevent-double-submit">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <?php FormTokenManager::renderFormTokenInput('register_user'); ?>
                    
                    <div class="form-row form-row--names">
                        <div class="form-group">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" placeholder="Last name"
                                   required maxlength="80" autocomplete="family-name"
                                   value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" placeholder="First name"
                                   required maxlength="80" autocomplete="given-name"
                                   value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Middle Name <span style="font-weight:400;color:#6b7280;">(Optional)</span></label>
                            <input type="text" class="form-control" name="middle_name" placeholder="Middle name"
                                   maxlength="80" autocomplete="additional-name"
                                   value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" placeholder="Choose a username" 
                               required maxlength="50" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email" placeholder="Enter your email" 
                               required maxlength="100" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number (Optional)</label>
                        <input type="tel" class="form-control" name="phone" placeholder="Enter your phone number" 
                               maxlength="20" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address (Optional)</label>
                        <textarea class="form-control" name="address" placeholder="Enter your address" 
                                  rows="3"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" placeholder="Create a password" 
                                   required minlength="6">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" name="confirm_password" placeholder="Confirm your password" 
                                   required minlength="6">
                        </div>
                    </div>

                    <button class="btn-register" type="submit">
                        <i class="fas fa-user-plus"></i> CREATE ACCOUNT
                    </button>

                    <div class="text-center">
                        <p>
                            Already have an account? 
                            <a href="login.php" class="link-primary">Login here</a>
                        </p>
                    </div>
                </form>
            </div>
    </div>

    <script>
        // Password confirmation validation
        const passwordInput = document.querySelector('input[name="password"]');
        const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');
        
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', function() {
                if (passwordInput.value !== this.value) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
        }

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            if (passwordInput.value !== confirmPasswordInput.value) {
                e.preventDefault();
                showError('Passwords do not match');
            }
        });
    </script>
    <script src="<?= BASE_URL ?>assets/js/form-submission-manager.js"></script>
</body>
</html>