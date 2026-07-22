<?php
require_once '../../config/config.php';
require_once '../../includes/models.php';

requireAdmin();

$userModel = new User();
$success = false;
$error = '';
$activeTab = $_GET['tab'] ?? 'general';

// Debug: Log POST data when form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    error_log("=== Admin Account Creation Attempt ===");
    error_log("POST data received: " . print_r($_POST, true));
}

// Handle admin account creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admin'])) {
    error_log("Step 1: Form submission detected");
    
    // Validate form submission
    $validation = FormTokenManager::validateFormSubmission($_POST);
    error_log("Step 2: Validation result: " . print_r($validation, true));
    
    if (!$validation['valid']) {
        error_log("Step 3: Validation failed");
        if ($validation['error'] === 'duplicate') {
            error_log("Step 4: Duplicate submission detected");
            $_SESSION['info_message'] = 'Admin account already created.';
            header('Location: settings.php?tab=accounts');
            exit();
        }
        $error = $validation['error'];
        error_log("Step 5: Validation error set: " . $error);
    } else {
        error_log("Step 6: Validation passed, processing form data");
        $username = sanitize($_POST['username'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $fullName = sanitize($_POST['full_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        
        error_log("Step 7: Data sanitized - Username: {$username}, Email: {$email}, Full Name: {$fullName}");

        if (empty($username) || empty($email) || empty($password) || empty($fullName)) {
            error_log("Step 8: Required fields validation failed");
            $error = 'All required fields must be filled';
        } elseif ($password !== $confirmPassword) {
            error_log("Step 9: Password mismatch");
            $error = 'Passwords do not match';
        } elseif (strlen($password) < 8) {
            error_log("Step 10: Password too short");
            $error = 'Password must be at least 8 characters long';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("Step 11: Invalid email format");
            $error = 'Invalid email format';
        } else {
            error_log("Step 12: All validations passed, checking for existing user");
            if ($userModel->findByEmail($email)) {
                error_log("Step 13: Email already exists");
                $error = 'Email already exists';
            } elseif ($userModel->findByUsername($username)) {
                error_log("Step 14: Username already exists");
                $error = 'Username already exists';
            } else {
                error_log("Step 15: Creating new admin account");
                $userData = [
                    'username' => $username,
                    'email' => $email,
                    'password' => $password,
                    'role' => ROLE_ADMIN,
                    'full_name' => $fullName,
                    'phone' => $phone,
                    'address' => null
                ];
                
                error_log("Step 16: User data prepared: " . print_r($userData, true));

                try {
                    error_log("Step 17: Attempting to create user in database");
                    $createResult = $userModel->create($userData);
                    error_log("Step 18: Create result: " . ($createResult ? 'SUCCESS' : 'FAILED'));
                    
                    if ($createResult) {
                        error_log("Step 19: User created successfully, logging activity");
                        // Log the admin creation activity
                        $newAdmin = $userModel->findByEmail($email);
                        if ($newAdmin) {
                            error_log("Step 20: New admin found, ID: {$newAdmin['id']}");
                            $userModel->logActivity(
                                $_SESSION['user_id'],
                                'admin_created',
                                "New admin account created for: {$fullName} ({$email})",
                                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                            );
                            error_log("Step 21: Activity logged successfully");
                        }
                        
                        $success = true;
                        $_SESSION['success_message'] = "Admin account created successfully for {$fullName}!";
                        error_log("Step 22: Redirecting to accounts tab");
                        header('Location: settings.php?tab=accounts');
                        exit();
                    } else {
                        error_log("Step 23: Failed to create admin account in database");
                        $error = 'Failed to create admin account. Please try again.';
                    }
                } catch (Exception $e) {
                    error_log("Step 24: Exception caught: " . $e->getMessage());
                    error_log("Exception trace: " . $e->getTraceAsString());
                    $error = 'An error occurred while creating the admin account. Please try again.';
                }
            }
        }
    }
}

// Handle password update for current admin
$passwordError = '';
$passwordSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    // Validate form submission
    $validation = FormTokenManager::validateFormSubmission($_POST);
    
    if (!$validation['valid']) {
        if ($validation['error'] === 'duplicate') {
            $_SESSION['info_message'] = 'Password update already processed.';
            header('Location: settings.php?tab=accounts');
            exit();
        }
        $passwordError = $validation['error'];
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password_update'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $passwordError = 'All password fields are required';
        } elseif ($newPassword !== $confirmPassword) {
            $passwordError = 'New passwords do not match';
        } elseif (strlen($newPassword) < 8) {
            $passwordError = 'New password must be at least 8 characters long';
        } else {
            // Get current user data
            $currentUser = $userModel->findById($_SESSION['user_id']);
            
            if (!$currentUser) {
                $passwordError = 'User not found';
            } elseif (!$currentUser['password'] || !password_verify($currentPassword, $currentUser['password'])) {
                $passwordError = 'Current password is incorrect';
            } else {
                // Update password
                $updateResult = $userModel->update($_SESSION['user_id'], ['password' => $newPassword]);
                
                if ($updateResult) {
                    // Log the password change activity
                    $userModel->logActivity(
                        $_SESSION['user_id'],
                        'password_changed',
                        'Admin password updated successfully',
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                    );
                    
                    $passwordSuccess = true;
                    $_SESSION['success_message'] = 'Password updated successfully!';
                    header('Location: settings.php?tab=accounts');
                    exit();
                } else {
                    $passwordError = 'Failed to update password. Please try again.';
                }
            }
        }
    }
}

// Get current admin user data
$currentAdmin = $userModel->findById($_SESSION['user_id']);

// Get all admin accounts
$adminAccounts = $userModel->findAll('role = ?', [ROLE_ADMIN]);

// Handle System Logs
$logsPage = isset($_GET['logs_page']) ? max(1, (int)$_GET['logs_page']) : 1;
$logsPerPage = 50;
$logsOffset = ($logsPage - 1) * $logsPerPage;

// Build filters for system logs
$logsFilters = [];
if (isset($_GET['filter_role']) && !empty($_GET['filter_role'])) {
    $logsFilters['role'] = sanitize($_GET['filter_role']);
}
if (isset($_GET['filter_action']) && !empty($_GET['filter_action'])) {
    $logsFilters['action'] = sanitize($_GET['filter_action']);
}
if (isset($_GET['filter_date_from']) && !empty($_GET['filter_date_from'])) {
    $logsFilters['date_from'] = sanitize($_GET['filter_date_from']);
}
if (isset($_GET['filter_date_to']) && !empty($_GET['filter_date_to'])) {
    $logsFilters['date_to'] = sanitize($_GET['filter_date_to']);
}

// Get system activity logs
$systemLogs = $userModel->getAllActivityLogs($logsPerPage, $logsOffset, $logsFilters);
$totalLogs = $userModel->getActivityLogsCount($logsFilters);
$totalLogsPages = ceil($totalLogs / $logsPerPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Settings - WPU Applicant System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/office-theme.css" rel="stylesheet">
    <link href="../../assets/css/admin-dashboard.css" rel="stylesheet">
    <link href="../../assets/css/admin-settings.css" rel="stylesheet">
    <style>
        /* Highlight pulse for quick-action scroll targets */
        .settings-highlight { animation: settingsHighlight 1.2s ease; }
        @keyframes settingsHighlight {
            0%, 100% { box-shadow: var(--set-shadow, 0 4px 24px rgba(15,23,42,.06)); }
            50% { box-shadow: 0 0 0 3px rgba(0, 86, 210, 0.35); }
        }
    </style>
</head>
<body class="admin-dashboard settings-page">
    <?php $adminSidebarActive = 'settings'; require __DIR__ . '/_admin_sidebar.php'; ?>

    <div class="main-wrapper">
        <header class="topbar" role="banner">
            <button type="button" class="mobile-menu-toggle topbar-btn" aria-label="Open menu">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="topbar-title">Settings</h1>
            <div class="topbar-actions" style="position: relative;">
                <a href="notifications.php" id="notificationBell" class="notification-bell" title="Notifications" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    <span id="notificationBadge" class="notification-badge" style="display: none;">0</span>
                </a>
                <div id="notificationDropdown" class="notification-dropdown" role="dialog" aria-label="Notification center">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <button type="button" id="markAllReadBtn" class="mark-all-read">Mark all as read</button>
                    </div>
                    <div id="notificationList" class="notification-list">
                        <div class="notification-empty">Loading...</div>
                    </div>
                    <div class="notification-footer">
                        <a href="notifications.php">View all notifications</a>
                    </div>
                </div>
                <a href="settings.php" class="topbar-btn" aria-label="Settings">
                    <i class="fas fa-cog"></i>
                </a>
                <a href="../../auth/logout.php" class="topbar-btn confirm-logout" title="Logout" aria-label="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </header>

        <div class="content">
            <!-- Page Header -->
            <div class="settings-page-header">
                <nav class="settings-breadcrumb" aria-label="Breadcrumb">
                    <a href="dashboard.php">Dashboard</a>
                    <i class="fas fa-chevron-right" aria-hidden="true" style="font-size:0.65rem"></i>
                    <span class="current">Settings</span>
                </nav>
                <div class="settings-header-content">
                    <div class="settings-header-icon" aria-hidden="true">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="settings-header-text">
                        <h1>Settings</h1>
                        <p>Manage your system configuration, administrator accounts, security settings, and maintenance tools.</p>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert-success settings-alert-success" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($_SESSION['success_message']) ?></span>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['info_message'])): ?>
                <div class="alert-info settings-alert" role="alert">
                    <i class="fas fa-info-circle"></i>
                    <span><?= htmlspecialchars($_SESSION['info_message']) ?></span>
                </div>
                <?php unset($_SESSION['info_message']); ?>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert-danger settings-alert" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Settings Navigation -->
            <nav class="settings-nav-wrap" aria-label="Settings sections">
                <div class="settings-tabs" role="tablist">
                    <a class="settings-tab <?= $activeTab === 'general' ? 'active' : '' ?>"
                       href="?tab=general" role="tab" aria-selected="<?= $activeTab === 'general' ? 'true' : 'false' ?>">
                        <i class="fas fa-sliders-h" aria-hidden="true"></i> General
                    </a>
                    <a class="settings-tab <?= $activeTab === 'accounts' ? 'active' : '' ?>"
                       href="?tab=accounts" role="tab" aria-selected="<?= $activeTab === 'accounts' ? 'true' : 'false' ?>">
                        <i class="fas fa-user-shield" aria-hidden="true"></i> Admin Accounts
                    </a>
                    <a class="settings-tab <?= $activeTab === 'system' ? 'active' : '' ?>"
                       href="?tab=system" role="tab" aria-selected="<?= $activeTab === 'system' ? 'true' : 'false' ?>">
                        <i class="fas fa-server" aria-hidden="true"></i> System
                    </a>
                </div>
            </nav>

            <div class="tab-content">
                <!-- General Settings Tab -->
                <?php if ($activeTab === 'general'): ?>
                    <form method="POST" id="generalSettingsForm" class="prevent-double-submit">
                        <!-- System Information -->
                        <div class="settings-card" style="margin-bottom:24px">
                            <div class="settings-card-header">
                                <h2><i class="fas fa-building"></i> System Information</h2>
                            </div>
                            <div class="settings-card-body">
                                <div class="info-cards-grid">
                                    <div class="info-card">
                                        <div class="info-card-label">System Name</div>
                                        <div class="info-card-value">WPU Applicant System</div>
                                    </div>
                                    <div class="info-card">
                                        <div class="info-card-label">Version</div>
                                        <div class="info-card-value">v1.0.0</div>
                                    </div>
                                    <div class="info-card">
                                        <div class="info-card-label">Status</div>
                                        <div class="info-card-value">
                                            <span class="status-dot" aria-hidden="true"></span> Running
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Email Configuration -->
                        <div class="settings-card" style="margin-bottom:24px">
                            <div class="settings-card-header">
                                <h2><i class="fas fa-envelope"></i> Email Configuration</h2>
                            </div>
                            <div class="settings-card-body">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="systemEmail">System Email</label>
                                        <div class="input-icon-wrap">
                                            <input type="email" class="form-control" id="systemEmail" placeholder="noreply@wpu.edu" aria-describedby="systemEmailHelp">
                                            <i class="field-icon fas fa-envelope" aria-hidden="true"></i>
                                            <i class="field-validation fas" aria-hidden="true"></i>
                                        </div>
                                        <div class="field-helper" id="systemEmailHelp">
                                            <i class="fas fa-info-circle"></i> Used for automated system notifications
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="replyToEmail">Reply-To Email</label>
                                        <div class="input-icon-wrap">
                                            <input type="email" class="form-control" id="replyToEmail" placeholder="support@wpu.edu" aria-describedby="replyToEmailHelp">
                                            <i class="field-icon fas fa-reply" aria-hidden="true"></i>
                                            <i class="field-validation fas" aria-hidden="true"></i>
                                        </div>
                                        <div class="field-helper" id="replyToEmailHelp">
                                            <i class="fas fa-info-circle"></i> Address for applicant replies
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Application Settings -->
                        <div class="settings-card">
                            <div class="settings-card-header">
                                <h2><i class="fas fa-clock"></i> Application Settings</h2>
                            </div>
                            <div class="settings-card-body">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="minExperience">
                                            Minimum Experience Required (years)
                                            <span class="tooltip-trigger" title="Applicants below this threshold may be flagged during review"><i class="fas fa-question-circle"></i></span>
                                        </label>
                                        <div class="stepper-control">
                                            <button type="button" data-step="down" aria-label="Decrease experience years"><i class="fas fa-minus"></i></button>
                                            <input type="number" class="form-control" id="minExperience" value="0" min="0" max="20" aria-label="Minimum experience years">
                                            <button type="button" data-step="up" aria-label="Increase experience years"><i class="fas fa-plus"></i></button>
                                        </div>
                                        <div class="field-helper"><i class="fas fa-briefcase"></i> Set to 0 to accept all experience levels</div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="autoApproveToggle">
                                            Auto-Approve Applications
                                            <span class="tooltip-trigger" title="When enabled, qualified applications are approved automatically"><i class="fas fa-question-circle"></i></span>
                                        </label>
                                        <div class="toggle-control">
                                            <div class="toggle-control-info">
                                                <strong>Auto Approve Applications</strong>
                                                <span id="autoApproveStatus">OFF — Manual review required</span>
                                            </div>
                                            <label class="toggle-switch">
                                                <input type="checkbox" id="autoApproveToggle" aria-labelledby="autoApproveStatus">
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                        <select class="form-select" id="autoApproveSelect" style="position:absolute;opacity:0;pointer-events:none;height:0;width:0" tabindex="-1" aria-hidden="true">
                                            <option value="0">No - Manual Review Required</option>
                                            <option value="1">Yes - Auto-Approve Qualified</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="settings-save-bar">
                                    <button type="submit" class="settings-save-btn" aria-live="polite">
                                        <span class="spinner" aria-hidden="true"></span>
                                        <i class="fas fa-save btn-label-default" aria-hidden="true"></i>
                                        <span class="btn-label-default">Save Changes</span>
                                        <span class="btn-label-saving">Saving...</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>

                <!-- Admin Accounts Tab -->
                <?php if ($activeTab === 'accounts'): ?>
                    <div class="admin-grid">
                        <!-- Left Panel -->
                        <div class="admin-left-panel">
                            <!-- Quick Actions -->
                            <div class="settings-card">
                                <div class="settings-card-header">
                                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                                </div>
                                <div class="settings-card-body">
                                    <div class="quick-actions-list">
                                        <button type="button" class="quick-action-btn" data-scroll-to="#createAdminSection">
                                            <i class="fas fa-user-plus"></i>
                                            Create Admin
                                        </button>
                                        <button type="button" class="quick-action-btn" data-scroll-to="#updatePasswordSection">
                                            <i class="fas fa-key"></i>
                                            Update Password
                                        </button>
                                    </div>
                                    <div class="security-tips" style="margin-top:16px">
                                        <h4><i class="fas fa-shield-alt"></i> Security Tips</h4>
                                        <ul>
                                            <li>Use unique passwords for each admin account</li>
                                            <li>Enable strong passwords with mixed characters</li>
                                            <li>Review admin accounts regularly</li>
                                            <li>Update passwords every 90 days</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Create New Admin Account -->
                            <div class="settings-card" id="createAdminSection">
                                <div class="settings-card-header">
                                    <h2><i class="fas fa-user-plus"></i> Create Admin</h2>
                                </div>
                                <div class="settings-card-body">
                                    <form method="POST" class="prevent-double-submit" id="createAdminForm">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <?php FormTokenManager::renderFormTokenInput('create_admin'); ?>

                                        <div class="avatar-preview-wrap">
                                            <div class="avatar-preview" id="avatarPreview" aria-hidden="true">?</div>
                                            <div class="avatar-preview-text">
                                                <strong id="avatarPreviewName">New Administrator</strong>
                                                Initials preview from full name
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label" for="createFullName">Full Name *</label>
                                            <div class="input-icon-wrap">
                                                <input type="text" class="form-control" id="createFullName" name="full_name" required autocomplete="name">
                                                <i class="field-icon fas fa-user" aria-hidden="true"></i>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label" for="createUsername">Username *</label>
                                            <div class="input-icon-wrap">
                                                <input type="text" class="form-control" id="createUsername" name="username" required autocomplete="username">
                                                <i class="field-icon fas fa-at" aria-hidden="true"></i>
                                                <i class="field-validation fas" aria-hidden="true"></i>
                                            </div>
                                            <div class="field-helper"><i class="fas fa-info-circle"></i> Letters, numbers, and underscores only (min. 3 chars)</div>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label" for="createEmail">Email Address *</label>
                                            <div class="input-icon-wrap">
                                                <input type="email" class="form-control" id="createEmail" name="email" required autocomplete="email">
                                                <i class="field-icon fas fa-envelope" aria-hidden="true"></i>
                                                <i class="field-validation fas" aria-hidden="true"></i>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label" for="createPhone">Phone Number</label>
                                            <div class="input-icon-wrap">
                                                <input type="tel" class="form-control" id="createPhone" name="phone" autocomplete="tel">
                                                <i class="field-icon fas fa-phone" aria-hidden="true"></i>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label" for="createPassword">Password *</label>
                                            <div class="input-icon-wrap password-field-wrap">
                                                <input type="password" class="form-control" id="createPassword" name="password" minlength="8" required autocomplete="new-password">
                                                <i class="field-icon fas fa-lock" aria-hidden="true"></i>
                                                <button type="button" class="password-toggle-btn" aria-label="Show password"><i class="fas fa-eye"></i></button>
                                            </div>
                                            <div class="password-strength-wrap">
                                                <div class="password-strength-bar"><div class="password-strength-fill"></div></div>
                                                <div class="password-strength-label">Enter a password</div>
                                                <div class="password-requirements">
                                                    <span class="password-req" data-req="length"><i class="fas fa-circle"></i> 8+ characters</span>
                                                    <span class="password-req" data-req="upper"><i class="fas fa-circle"></i> Uppercase letter</span>
                                                    <span class="password-req" data-req="lower"><i class="fas fa-circle"></i> Lowercase letter</span>
                                                    <span class="password-req" data-req="number"><i class="fas fa-circle"></i> Number</span>
                                                    <span class="password-req" data-req="special"><i class="fas fa-circle"></i> Special character</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label" for="createConfirmPassword">Confirm Password *</label>
                                            <div class="input-icon-wrap password-field-wrap">
                                                <input type="password" class="form-control" id="createConfirmPassword" name="confirm_password" minlength="8" required autocomplete="new-password">
                                                <i class="field-icon fas fa-lock" aria-hidden="true"></i>
                                                <i class="field-validation fas" aria-hidden="true"></i>
                                                <button type="button" class="password-toggle-btn" aria-label="Show password"><i class="fas fa-eye"></i></button>
                                            </div>
                                        </div>

                                        <div class="alert-info" style="margin-bottom: 16px;">
                                            <i class="fas fa-info-circle"></i>
                                            <span>This will create a new administrator with full system access.</span>
                                        </div>

                                        <button type="submit" name="create_admin" class="btn-success" style="width: 100%;">
                                            <i class="fas fa-user-shield"></i> Create Admin Account
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Update Current Admin Password -->
                            <div class="settings-card" id="updatePasswordSection">
                                <div class="settings-card-header">
                                    <h2><i class="fas fa-key"></i> Update Password</h2>
                                </div>
                                <div class="settings-card-body">
                                    <?php if ($passwordError): ?>
                                        <div class="alert-danger" style="margin-bottom: 16px;" role="alert">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <span><?= htmlspecialchars($passwordError) ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($currentAdmin && $currentAdmin['password']): ?>
                                        <form method="POST" class="prevent-double-submit" id="updatePasswordForm">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <?php FormTokenManager::renderFormTokenInput('update_password'); ?>

                                            <div class="security-tips" style="margin-bottom:20px">
                                                <h4><i class="fas fa-lock"></i> Security Reminder</h4>
                                                <ul>
                                                    <li>Never share your password with anyone</li>
                                                    <li>Use a password you don't use elsewhere</li>
                                                    <li>Minimum 8 characters required</li>
                                                </ul>
                                            </div>

                                            <div class="form-group">
                                                <label class="form-label" for="currentPassword">Current Password *</label>
                                                <div class="input-icon-wrap password-field-wrap">
                                                    <input type="password" class="form-control" id="currentPassword" name="current_password" required autocomplete="current-password">
                                                    <i class="field-icon fas fa-lock" aria-hidden="true"></i>
                                                    <button type="button" class="password-toggle-btn" aria-label="Show password"><i class="fas fa-eye"></i></button>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="form-label" for="newPassword">New Password *</label>
                                                <div class="input-icon-wrap password-field-wrap">
                                                    <input type="password" class="form-control" id="newPassword" name="new_password" minlength="8" required autocomplete="new-password">
                                                    <i class="field-icon fas fa-lock" aria-hidden="true"></i>
                                                    <button type="button" class="password-toggle-btn" aria-label="Show password"><i class="fas fa-eye"></i></button>
                                                </div>
                                                <div class="password-strength-wrap">
                                                    <div class="password-strength-bar"><div class="password-strength-fill"></div></div>
                                                    <div class="password-strength-label">Enter a password</div>
                                                    <div class="password-requirements">
                                                        <span class="password-req" data-req="length"><i class="fas fa-circle"></i> 8+ characters</span>
                                                        <span class="password-req" data-req="upper"><i class="fas fa-circle"></i> Uppercase letter</span>
                                                        <span class="password-req" data-req="lower"><i class="fas fa-circle"></i> Lowercase letter</span>
                                                        <span class="password-req" data-req="number"><i class="fas fa-circle"></i> Number</span>
                                                        <span class="password-req" data-req="special"><i class="fas fa-circle"></i> Special character</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label class="form-label" for="confirmNewPassword">Confirm New Password *</label>
                                                <div class="input-icon-wrap password-field-wrap">
                                                    <input type="password" class="form-control" id="confirmNewPassword" name="confirm_password_update" minlength="8" required autocomplete="new-password">
                                                    <i class="field-icon fas fa-lock" aria-hidden="true"></i>
                                                    <i class="field-validation fas" aria-hidden="true"></i>
                                                    <button type="button" class="password-toggle-btn" aria-label="Show password"><i class="fas fa-eye"></i></button>
                                                </div>
                                            </div>

                                            <button type="submit" name="update_password" class="btn-primary" style="width: 100%;">
                                                <i class="fas fa-save"></i> Update Password
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <div class="alert-info">
                                            <i class="fas fa-info-circle"></i>
                                            <span>Your account uses OAuth authentication. Password update is not available.</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Right Panel: Administrator Directory -->
                        <div>
                            <div class="settings-card">
                                <div class="settings-card-header">
                                    <h2><i class="fas fa-users-cog"></i> Administrator Directory</h2>
                                    <span class="badge-primary"><?= count($adminAccounts) ?> total</span>
                                </div>
                                <div class="settings-card-body">
                                    <!-- Admin Statistics -->
                                    <div class="admin-stats-grid">
                                        <div class="admin-stat-card">
                                            <div class="stat-icon" style="background:rgba(0,86,210,0.1);color:#0056D2"><i class="fas fa-users"></i></div>
                                            <div class="stat-value"><?= count($adminAccounts) ?></div>
                                            <div class="stat-label">Total Admins</div>
                                        </div>
                                        <div class="admin-stat-card">
                                            <div class="stat-icon" style="background:rgba(22,163,74,0.1);color:#16A34A"><i class="fas fa-circle"></i></div>
                                            <div class="stat-value">1</div>
                                            <div class="stat-label">Online Now</div>
                                        </div>
                                        <div class="admin-stat-card">
                                            <div class="stat-icon" style="background:rgba(59,130,246,0.1);color:#2563eb"><i class="fas fa-sign-in-alt"></i></div>
                                            <div class="stat-value"><?= count(array_filter($systemLogs, function($l) { return stripos($l['action'], 'login') !== false && date('Y-m-d', strtotime($l['created_at'])) === date('Y-m-d'); })) ?></div>
                                            <div class="stat-label">Logins Today</div>
                                        </div>
                                        <div class="admin-stat-card">
                                            <div class="stat-icon" style="background:rgba(139,92,246,0.1);color:#7c3aed"><i class="fas fa-key"></i></div>
                                            <div class="stat-value"><?= count(array_filter($systemLogs, function($l) { return $l['action'] === 'password_changed' && strtotime($l['created_at']) >= strtotime('-7 days'); })) ?></div>
                                            <div class="stat-label">Password Updates</div>
                                        </div>
                                    </div>

                                    <!-- Search & Filter -->
                                    <div class="admin-toolbar">
                                        <div class="admin-search-wrap">
                                            <i class="fas fa-search" aria-hidden="true"></i>
                                            <input type="search" id="adminSearchInput" placeholder="Search by name, email, or username..." aria-label="Search administrators">
                                        </div>
                                        <select id="adminSortFilter" class="admin-filter-select" aria-label="Sort administrators">
                                            <option value="newest">Newest</option>
                                            <option value="oldest">Oldest</option>
                                            <option value="alpha">Alphabetical</option>
                                            <option value="active">Recently Active</option>
                                        </select>
                                    </div>

                                    <?php if (empty($adminAccounts)): ?>
                                        <div class="admin-empty-state">
                                            <i class="fas fa-users-slash"></i>
                                            <p>No admin accounts found</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="admin-cards-list" id="adminCardsList">
                                            <?php foreach ($adminAccounts as $admin): ?>
                                                <?php
                                                $initials = '';
                                                $parts = preg_split('/\s+/', trim($admin['full_name']));
                                                foreach (array_slice($parts, 0, 2) as $p) {
                                                    $initials .= strtoupper(substr($p, 0, 1));
                                                }
                                                ?>
                                                <div class="admin-item"
                                                     data-search="<?= htmlspecialchars(strtolower($admin['full_name'] . ' ' . $admin['email'] . ' ' . $admin['username'])) ?>"
                                                     data-name="<?= htmlspecialchars(strtolower($admin['full_name'])) ?>"
                                                     data-created="<?= htmlspecialchars($admin['created_at']) ?>"
                                                     data-updated="<?= htmlspecialchars($admin['updated_at'] ?? $admin['created_at']) ?>">
                                                    <div class="admin-item-header">
                                                        <div class="admin-info">
                                                            <div class="admin-avatar" aria-hidden="true"><?= htmlspecialchars($initials ?: strtoupper(substr($admin['full_name'], 0, 1))) ?></div>
                                                            <div class="admin-details">
                                                                <div class="admin-name">
                                                                    <?= htmlspecialchars($admin['full_name']) ?>
                                                                    <?php if ($admin['id'] === $_SESSION['user_id']): ?>
                                                                        <span class="badge-you">Current User</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="admin-meta"><i class="fas fa-envelope"></i> <?= htmlspecialchars($admin['email']) ?></div>
                                                                <div class="admin-meta"><i class="fas fa-user"></i> <?= htmlspecialchars($admin['username']) ?></div>
                                                                <?php if (!empty($admin['phone'])): ?>
                                                                    <div class="admin-meta"><i class="fas fa-phone"></i> <?= htmlspecialchars($admin['phone']) ?></div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="admin-badges">
                                                            <span class="badge-primary"><i class="fas fa-shield-alt"></i> Admin</span>
                                                            <?php if (!empty($admin['is_active'])): ?>
                                                                <span class="badge-status-active"><i class="fas fa-check-circle"></i> Active</span>
                                                            <?php else: ?>
                                                                <span class="badge-status-inactive"><i class="fas fa-times-circle"></i> Inactive</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="admin-item-footer">
                                                        <span><i class="fas fa-calendar-plus"></i> Created <?= date('M j, Y', strtotime($admin['created_at'])) ?></span>
                                                        <span><i class="fas fa-clock"></i> Last activity <?= date('M j, Y', strtotime($admin['updated_at'] ?? $admin['created_at'])) ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- System Tab -->
                <?php if ($activeTab === 'system'): ?>
                    <?php
                    $memUsage = round(memory_get_usage(true) / 1024 / 1024, 1);
                    $memLimit = ini_get('memory_limit');
                    $sessionStatus = session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive';
                    ?>
                    <!-- System Health Dashboard -->
                    <div class="settings-card" style="margin-bottom:24px">
                        <div class="settings-card-header">
                            <h2><i class="fas fa-heartbeat"></i> System Health Dashboard</h2>
                        </div>
                        <div class="settings-card-body">
                            <div class="health-grid">
                                <div class="health-card">
                                    <div class="health-card-icon" style="background:rgba(139,92,246,0.1);color:#7c3aed"><i class="fab fa-php"></i></div>
                                    <div class="health-card-label">PHP Version</div>
                                    <div class="health-card-value"><?= phpversion() ?></div>
                                    <div class="health-progress"><div class="health-progress-fill" style="width:100%;background:#7c3aed"></div></div>
                                    <span class="health-badge ok"><i class="fas fa-check"></i> OK</span>
                                </div>
                                <div class="health-card">
                                    <div class="health-card-icon" style="background:rgba(59,130,246,0.1);color:#2563eb"><i class="fas fa-server"></i></div>
                                    <div class="health-card-label">Server</div>
                                    <div class="health-card-value" style="font-size:0.85rem"><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') ?></div>
                                    <div class="health-progress"><div class="health-progress-fill" style="width:90%;background:#2563eb"></div></div>
                                    <span class="health-badge ok"><i class="fas fa-check"></i> Running</span>
                                </div>
                                <div class="health-card">
                                    <div class="health-card-icon" style="background:rgba(22,163,74,0.1);color:#16A34A"><i class="fas fa-database"></i></div>
                                    <div class="health-card-label">Database</div>
                                    <div class="health-card-value">MySQL</div>
                                    <div class="health-progress"><div class="health-progress-fill" style="width:100%;background:#16A34A"></div></div>
                                    <span class="health-badge ok"><i class="fas fa-check"></i> Connected</span>
                                </div>
                                <div class="health-card">
                                    <div class="health-card-icon" style="background:rgba(245,158,11,0.1);color:#F59E0B"><i class="fas fa-memory"></i></div>
                                    <div class="health-card-label">Memory</div>
                                    <div class="health-card-value"><?= $memUsage ?> MB</div>
                                    <div class="health-progress"><div class="health-progress-fill" style="width:<?= min(100, ($memUsage / 128) * 100) ?>%;background:#F59E0B"></div></div>
                                    <span class="health-badge ok">Limit: <?= htmlspecialchars($memLimit) ?></span>
                                </div>
                                <div class="health-card">
                                    <div class="health-card-icon" style="background:rgba(0,86,210,0.1);color:#0056D2"><i class="fas fa-user-clock"></i></div>
                                    <div class="health-card-label">Sessions</div>
                                    <div class="health-card-value"><?= $sessionStatus ?></div>
                                    <div class="health-progress"><div class="health-progress-fill" style="width:100%;background:#0056D2"></div></div>
                                    <span class="health-badge ok"><i class="fas fa-check"></i> Active</span>
                                </div>
                                <div class="health-card">
                                    <div class="health-card-icon" style="background:rgba(236,72,153,0.1);color:#ec4899"><i class="fas fa-link"></i></div>
                                    <div class="health-card-label">Base URL</div>
                                    <div class="health-card-value" style="font-size:0.78rem;word-break:break-all"><?= BASE_URL ?></div>
                                    <span class="health-badge ok"><i class="fas fa-check"></i> Configured</span>
                                </div>
                            </div>

                            <!-- Database Status -->
                            <div class="settings-label" style="margin-top:8px"><i class="fas fa-database"></i> Database Status</div>
                            <div class="db-status-card">
                                <div class="db-status-item">
                                    <div class="label">Connection</div>
                                    <div class="value db-connected"><span aria-hidden="true">🟢</span> Connected</div>
                                </div>
                                <div class="db-status-item">
                                    <div class="label">Database Type</div>
                                    <div class="value">MySQL</div>
                                </div>
                                <div class="db-status-item">
                                    <div class="label">Server Version</div>
                                    <div class="value"><?= phpversion() ?></div>
                                </div>
                                <div class="db-status-item">
                                    <div class="label">Activity Logs</div>
                                    <div class="value"><?= number_format($totalLogs) ?> entries</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Maintenance -->
                    <div class="settings-card" style="margin-bottom:24px">
                        <div class="settings-card-header">
                            <h2><i class="fas fa-tools"></i> Maintenance</h2>
                        </div>
                        <div class="settings-card-body">
                            <div class="maintenance-grid">
                                <div class="maintenance-card">
                                    <div class="maintenance-card-icon" style="background:rgba(59,130,246,0.1);color:#2563eb"><i class="fas fa-sync"></i></div>
                                    <h4>Clear Cache</h4>
                                    <p>Remove cached files and temporary data to free up storage and refresh system performance.</p>
                                    <div class="maintenance-card-meta"><i class="fas fa-folder"></i> Cached files · Est. size varies</div>
                                    <button type="button" class="btn-run btn-secondary"><i class="fas fa-play"></i> Run</button>
                                </div>
                                <div class="maintenance-card">
                                    <div class="maintenance-card-icon" style="background:rgba(22,163,74,0.1);color:#16A34A"><i class="fas fa-database"></i></div>
                                    <h4>Backup Database</h4>
                                    <p>Create a full backup of your database to protect against data loss.</p>
                                    <div class="maintenance-card-meta"><i class="fas fa-clock"></i> Last backup: Not recorded</div>
                                    <button type="button" class="btn-run btn-success"><i class="fas fa-download"></i> Create Backup</button>
                                </div>
                                <div class="maintenance-card">
                                    <div class="maintenance-card-icon" style="background:rgba(139,92,246,0.1);color:#7c3aed"><i class="fas fa-tachometer-alt"></i></div>
                                    <h4>Optimize Database</h4>
                                    <p>Run optimization routines to improve query performance and reclaim unused space.</p>
                                    <div class="maintenance-card-meta"><i class="fas fa-chart-line"></i> Performance tuning</div>
                                    <button type="button" class="btn-run btn-primary"><i class="fas fa-magic"></i> Optimize</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Logs -->
                    <div class="settings-card">
                        <div class="settings-card-header">
                            <h2><i class="fas fa-history"></i> System Activity Logs</h2>
                            <div class="logs-view-toggle" role="group" aria-label="Log view mode">
                                <button type="button" id="logsViewTable" class="active" aria-pressed="true"><i class="fas fa-table"></i> Table</button>
                                <button type="button" id="logsViewTimeline" aria-pressed="false"><i class="fas fa-stream"></i> Timeline</button>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <!-- Toolbar -->
                            <div class="logs-toolbar">
                                <div class="logs-toolbar-group">
                                    <div class="admin-search-wrap" style="min-width:200px">
                                        <i class="fas fa-search" aria-hidden="true"></i>
                                        <input type="search" id="logsSearchInput" placeholder="Search logs..." aria-label="Search activity logs">
                                    </div>
                                </div>
                                <div class="logs-toolbar-group">
                                    <button type="button" id="logsExportCsv" class="logs-toolbar-btn"><i class="fas fa-file-csv"></i> CSV</button>
                                    <button type="button" id="logsExportExcel" class="logs-toolbar-btn"><i class="fas fa-file-excel"></i> Excel</button>
                                    <button type="button" id="logsPrint" class="logs-toolbar-btn"><i class="fas fa-print"></i> Print</button>
                                    <button type="button" id="logsRefresh" class="logs-toolbar-btn"><i class="fas fa-sync-alt"></i> Refresh</button>
                                </div>
                            </div>

                            <!-- Quick Date Filters -->
                            <div class="logs-quick-filters" role="group" aria-label="Quick date filters">
                                <button type="button" class="log-filter-chip" data-date-filter="today">Today</button>
                                <button type="button" class="log-filter-chip" data-date-filter="yesterday">Yesterday</button>
                                <button type="button" class="log-filter-chip" data-date-filter="7days">7 Days</button>
                                <button type="button" class="log-filter-chip" data-date-filter="30days">30 Days</button>
                                <button type="button" class="log-filter-chip" data-date-filter="all">All</button>
                            </div>

                            <!-- Advanced Filters -->
                            <form method="GET" action="?tab=system" id="logsFilterForm">
                                <input type="hidden" name="tab" value="system">
                                <div class="logs-filters">
                                    <div class="form-group">
                                        <label class="form-label">Filter by Role</label>
                                        <select class="form-select" name="filter_role" onchange="this.form.submit()">
                                            <option value="">All Roles</option>
                                            <option value="<?= ROLE_ADMIN ?>" <?= (!empty($logsFilters['role']) && $logsFilters['role'] === ROLE_ADMIN) ? 'selected' : '' ?>>Admin</option>
                                            <option value="<?= ROLE_APPLICANT ?>" <?= (!empty($logsFilters['role']) && $logsFilters['role'] === ROLE_APPLICANT) ? 'selected' : '' ?>>Applicant</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Filter by Action</label>
                                        <input type="text" class="form-control" name="filter_action"
                                               value="<?= htmlspecialchars($logsFilters['action'] ?? '') ?>"
                                               placeholder="e.g., login, logout">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Date From</label>
                                        <input type="date" class="form-control" name="filter_date_from"
                                               value="<?= htmlspecialchars($logsFilters['date_from'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Date To</label>
                                        <input type="date" class="form-control" name="filter_date_to"
                                               value="<?= htmlspecialchars($logsFilters['date_to'] ?? '') ?>">
                                    </div>
                                    <div class="form-group" style="display:flex;align-items:flex-end;gap:8px">
                                        <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Apply Filters</button>
                                        <a href="?tab=system" class="btn-secondary"><i class="fas fa-times"></i> Clear</a>
                                    </div>
                                </div>
                            </form>

                            <div class="logs-container">
                                <?php if (empty($systemLogs)): ?>
                                    <div class="admin-empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>No activity logs found</p>
                                    </div>
                                <?php else: ?>
                                    <!-- Table View -->
                                    <div id="logsTableView" class="logs-table-view">
                                        <table class="logs-table" id="logsDataTable">
                                            <thead>
                                                <tr>
                                                    <th data-sort="0">Time <i class="fas fa-sort sort-icon"></i></th>
                                                    <th data-sort="1">User <i class="fas fa-sort sort-icon"></i></th>
                                                    <th data-sort="2">Role <i class="fas fa-sort sort-icon"></i></th>
                                                    <th data-sort="3">Action <i class="fas fa-sort sort-icon"></i></th>
                                                    <th data-sort="4">Description <i class="fas fa-sort sort-icon"></i></th>
                                                    <th data-sort="5">IP Address <i class="fas fa-sort sort-icon"></i></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($systemLogs as $log): ?>
                                                    <?php
                                                    $act = strtolower($log['action']);
                                                    $actClass = 'log-action-default';
                                                    if (strpos($act, 'login') !== false) $actClass = 'log-action-login';
                                                    elseif (strpos($act, 'logout') !== false) $actClass = 'log-action-logout';
                                                    elseif (strpos($act, 'delete') !== false) $actClass = 'log-action-delete';
                                                    elseif (strpos($act, 'password') !== false || strpos($act, 'update') !== false) $actClass = 'log-action-update';
                                                    elseif (strpos($act, 'create') !== false || strpos($act, 'admin_created') !== false || strpos($act, 'register') !== false) $actClass = 'log-action-create';
                                                    elseif (strpos($act, 'error') !== false) $actClass = 'log-action-error';
                                                    ?>
                                                    <tr>
                                                        <td class="log-time">
                                                            <?= date('M j, Y', strtotime($log['created_at'])) ?><br>
                                                            <small style="color:var(--set-muted)"><?= date('H:i:s', strtotime($log['created_at'])) ?></small>
                                                        </td>
                                                        <td>
                                                            <?php if ($log['user_id']): ?>
                                                                <div class="log-user">
                                                                    <span class="log-user-name"><?= htmlspecialchars($log['full_name'] ?? 'Unknown User') ?></span>
                                                                    <span class="log-user-meta"><?= htmlspecialchars($log['email'] ?? $log['username'] ?? 'N/A') ?></span>
                                                                </div>
                                                            <?php else: ?>
                                                                <span style="color:var(--set-muted);font-style:italic">System / Unknown</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($log['role']): ?>
                                                                <?php if ($log['role'] === ROLE_ADMIN): ?>
                                                                    <span class="badge-primary"><i class="fas fa-shield-alt"></i> Admin</span>
                                                                <?php else: ?>
                                                                    <span class="badge-info"><i class="fas fa-user"></i> Applicant</span>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span style="color:var(--set-muted)">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="log-action-badge <?= $actClass ?>"><?= htmlspecialchars($log['action']) ?></span>
                                                        </td>
                                                        <td><?= htmlspecialchars($log['description'] ?? '-') ?></td>
                                                        <td><span class="log-ip"><?= htmlspecialchars($log['ip_address'] ?? 'Unknown') ?></span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Timeline View -->
                                    <div id="logsTimelineView" class="logs-timeline">
                                        <?php foreach ($systemLogs as $log): ?>
                                            <?php
                                            $act = strtolower($log['action']);
                                            $actClass = 'log-action-default';
                                            $dotBg = 'rgba(0,86,210,0.1)';
                                            $dotColor = '#0056D2';
                                            if (strpos($act, 'login') !== false) { $actClass = 'log-action-login'; $dotBg = 'rgba(22,163,74,0.1)'; $dotColor = '#16A34A'; }
                                            elseif (strpos($act, 'logout') !== false) { $actClass = 'log-action-logout'; $dotBg = 'rgba(100,116,139,0.12)'; $dotColor = '#64748b'; }
                                            elseif (strpos($act, 'delete') !== false) { $actClass = 'log-action-delete'; $dotBg = 'rgba(220,38,38,0.1)'; $dotColor = '#DC2626'; }
                                            elseif (strpos($act, 'password') !== false || strpos($act, 'update') !== false) { $actClass = 'log-action-update'; $dotBg = 'rgba(59,130,246,0.1)'; $dotColor = '#2563eb'; }
                                            elseif (strpos($act, 'create') !== false || strpos($act, 'admin_created') !== false || strpos($act, 'register') !== false) { $actClass = 'log-action-create'; $dotBg = 'rgba(139,92,246,0.1)'; $dotColor = '#7c3aed'; }
                                            elseif (strpos($act, 'error') !== false) { $actClass = 'log-action-error'; $dotBg = 'rgba(245,158,11,0.12)'; $dotColor = '#F59E0B'; }
                                            $timeAgo = time() - strtotime($log['created_at']);
                                            if ($timeAgo < 60) $relative = 'Just now';
                                            elseif ($timeAgo < 3600) $relative = floor($timeAgo / 60) . ' mins ago';
                                            elseif ($timeAgo < 86400) $relative = floor($timeAgo / 3600) . ' hours ago';
                                            elseif ($timeAgo < 604800) $relative = floor($timeAgo / 86400) . ' days ago';
                                            else $relative = date('M j, Y', strtotime($log['created_at']));
                                            ?>
                                            <div class="timeline-item">
                                                <div class="timeline-dot" style="background:<?= $dotBg ?>;color:<?= $dotColor ?>">
                                                    <i class="fas fa-circle" style="font-size:0.5rem"></i>
                                                </div>
                                                <div class="timeline-content">
                                                    <div class="timeline-time">
                                                        <?= date('g:i A', strtotime($log['created_at'])) ?> · <?= $relative ?>
                                                    </div>
                                                    <div class="timeline-title">
                                                        <?= $log['user_id'] ? htmlspecialchars($log['full_name'] ?? 'Unknown User') : 'System' ?>
                                                        <span class="log-action-badge <?= $actClass ?>" style="margin-left:8px;font-size:0.72rem"><?= htmlspecialchars($log['action']) ?></span>
                                                    </div>
                                                    <div class="timeline-desc"><?= htmlspecialchars($log['description'] ?? '-') ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Pagination -->
                            <?php
                            $queryParams = $_GET;
                            unset($queryParams['logs_page']);
                            $baseUrl = '?' . http_build_query(array_merge($queryParams, ['tab' => 'system']));
                            ?>
                            <div class="logs-pagination">
                                <div class="logs-pagination-info">
                                    Showing <?= $totalLogs > 0 ? ($logsOffset + 1) : 0 ?>–<?= min($logsOffset + $logsPerPage, $totalLogs) ?> of <?= number_format($totalLogs) ?> logs
                                    <span style="margin-left:12px">· <?= $logsPerPage ?> rows per page</span>
                                </div>
                                <?php if ($totalLogsPages > 1): ?>
                                    <div class="logs-pagination-controls">
                                        <?php if ($logsPage > 1): ?>
                                            <a href="<?= $baseUrl ?>&logs_page=<?= $logsPage - 1 ?>" class="btn-secondary" aria-label="Previous page">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php
                                        $startPage = max(1, $logsPage - 2);
                                        $endPage = min($totalLogsPages, $logsPage + 2);

                                        if ($startPage > 1): ?>
                                            <a href="<?= $baseUrl ?>&logs_page=1" class="btn-secondary">1</a>
                                            <?php if ($startPage > 2): ?><span class="btn-secondary" style="cursor:default">…</span><?php endif; ?>
                                        <?php endif; ?>

                                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                            <?php if ($i == $logsPage): ?>
                                                <span class="active-page"><?= $i ?></span>
                                            <?php else: ?>
                                                <a href="<?= $baseUrl ?>&logs_page=<?= $i ?>" class="btn-secondary"><?= $i ?></a>
                                            <?php endif; ?>
                                        <?php endfor; ?>

                                        <?php if ($endPage < $totalLogsPages): ?>
                                            <?php if ($endPage < $totalLogsPages - 1): ?><span class="btn-secondary" style="cursor:default">…</span><?php endif; ?>
                                            <a href="<?= $baseUrl ?>&logs_page=<?= $totalLogsPages ?>" class="btn-secondary"><?= $totalLogsPages ?></a>
                                        <?php endif; ?>

                                        <?php if ($logsPage < $totalLogsPages): ?>
                                            <a href="<?= $baseUrl ?>&logs_page=<?= $logsPage + 1 ?>" class="btn-secondary" aria-label="Next page">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="logs-page-jump">
                                        <label for="logsPageJump">Jump to page</label>
                                        <input type="number" id="logsPageJump" min="1" max="<?= $totalLogsPages ?>"
                                               data-base-url="<?= htmlspecialchars($baseUrl) ?>" aria-label="Jump to page">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-nav">
        <div class="mobile-nav-grid">
            <a href="dashboard.php" class="mobile-nav-item">
                <i class="fas fa-th-large"></i>
                <span>Home</span>
            </a>
            <a href="positions.php" class="mobile-nav-item">
                <i class="fas fa-briefcase"></i>
                <span>Positions</span>
            </a>
            <a href="applications.php" class="mobile-nav-item">
                <i class="fas fa-file-alt"></i>
                <span>Applications</span>
            </a>
            <a href="users.php" class="mobile-nav-item">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
        </div>
    </nav>

    <script src="../../assets/js/toast.js"></script>
    <script src="../../assets/js/form-submission-manager.js"></script>
    <script src="../../assets/js/office-theme.js"></script>
    <script src="../../assets/js/admin-dashboard.js"></script>
    <script src="../../assets/js/notifications.js"></script>
    <script src="../../assets/js/admin-settings.js"></script>
    <script>
        if (typeof BASE_URL === 'undefined') {
            var BASE_URL = '<?= BASE_URL ?>';
        }
    </script>
</body>
</html>
