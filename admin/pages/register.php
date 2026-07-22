<?php
require_once '../../config/config.php';
require_once '../../includes/models.php';

// Only allow authenticated admins to access this page
requireAdmin();

// Redirect to new settings page
header('Location: settings.php?tab=accounts');
exit();

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form submission
    $validation = FormTokenManager::validateFormSubmission($_POST);
    
    if (!$validation['valid']) {
        if ($validation['error'] === 'duplicate') {
            header('Location: ../pages/dashboard.php?info=Admin account already created');
            exit();
        }
        $error = $validation['error'];
    } else {
        // Sanitize input
        $username = sanitize($_POST['username'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $fullName = sanitize($_POST['full_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');

        // Validate input
        if (empty($username) || empty($email) || empty($password) || empty($fullName)) {
            $error = 'All required fields must be filled';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format';
        } else {
            try {
                $userModel = new User();
                
                // Check if email or username already exists
                if ($userModel->findByEmail($email)) {
                    $error = 'Email already exists';
                } elseif ($userModel->findByUsername($username)) {
                    $error = 'Username already exists';
                } else {
                    // Create new admin user
                    $userData = [
                        'username' => $username,
                        'email' => $email,
                        'password' => $password,
                        'role' => ROLE_ADMIN,
                        'full_name' => $fullName,
                        'phone' => $phone,
                        'address' => null
                    ];

                    if ($userModel->create($userData)) {
                        $success = true;
                        
                        // Log the admin creation activity
                        $stmt = $userModel->db->prepare("
                            INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
                            VALUES (?, 'admin_created', 'New admin account created for: {$email}', ?, ?)
                        ");
                        $stmt->execute([
                            $_SESSION['user_id'],
                            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                        ]);
                    } else {
                        $error = 'Failed to create admin account. Please try again.';
                    }
                }
            } catch (Exception $e) {
                error_log("Admin registration error: " . $e->getMessage());
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
    <title>Create Admin Account - WPU Applicant System</title>
    <link href="../../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --sidebar-width: 250px;
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 10px;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .navbar-custom {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 15px 30px;
            margin-left: var(--sidebar-width);
            margin-bottom: 20px;
        }
        
        .register-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .register-form {
            padding: 40px;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h2 {
            color: #333;
            font-weight: 600;
        }
        
        .form-floating input {
            border: 1px solid #ddd;
        }
        
        .form-floating input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-register:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .alert {
            border-radius: 10px;
        }
        
        .success-message {
            text-align: center;
            padding: 40px;
        }
        
        .success-message i {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content,
            .navbar-custom {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="p-3">
            <h4 class="text-center mb-4">
                <i class="fas fa-graduation-cap"></i> WPU Admin
            </h4>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="positions.php">
                        <i class="fas fa-briefcase me-2"></i> Manage Positions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="applications.php">
                        <i class="fas fa-file-alt me-2"></i> Applications
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-users me-2"></i> Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar me-2"></i> Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="settings.php">
                        <i class="fas fa-cog me-2"></i> Settings
                    </a>
                </li>
                <li class="nav-item mt-3">
                    <a class="nav-link confirm-logout" href="../auth/logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Top Navbar -->
    <nav class="navbar navbar-custom">
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-md-none" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h5 class="mb-0 ms-2">Create Admin Account</h5>
        </div>
        <div class="d-flex align-items-center">
            <span class="me-3">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></span>
                                <img src="../../assets/img/avatar-placeholder.png" class="rounded-circle" alt="Profile">
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="register-container">
            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <h3>Admin Account Created!</h3>
                    <p class="mb-4">The admin account has been created successfully. The new admin can now login to their account.</p>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <a href="register.php" class="btn btn-outline-primary">
                            <i class="fas fa-plus"></i> Create Another Admin
                        </a>
                        <a href="users.php" class="btn btn-primary">
                            <i class="fas fa-users"></i> Manage Users
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="register-form">
                    <div class="register-header">
                        <h2><i class="fas fa-user-shield text-primary"></i> Create Admin Account</h2>
                        <p>Add a new administrator to the system</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="registerForm" class="prevent-double-submit">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <?php FormTokenManager::renderFormTokenInput('register_admin'); ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="username" name="username" 
                                           placeholder="Username" required maxlength="50" 
                                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                                    <label for="username"><i class="fas fa-user"></i> Username</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="email" class="form-control" id="email" name="email" 
                                           placeholder="Email" required maxlength="100"
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                    <label for="email"><i class="fas fa-envelope"></i> Email</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   placeholder="Full Name" required maxlength="100"
                                   value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                            <label for="full_name"><i class="fas fa-id-card"></i> Full Name</label>
                        </div>

                        <div class="form-floating mb-3">
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   placeholder="Phone Number" maxlength="20"
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                            <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Password" required minlength="8">
                                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                                    <div class="form-text">Minimum 8 characters required</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           placeholder="Confirm Password" required minlength="8">
                                    <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> This will create a new administrator account with full system access.
                        </div>

                        <div class="d-grid mb-3">
                            <button class="btn btn-primary btn-register" type="submit">
                                <i class="fas fa-user-shield"></i> Create Admin Account
                            </button>
                        </div>

                        <div class="text-center">
                            <a href="dashboard.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = e.target.closest('[onclick="toggleSidebar()"]');
            
            if (!sidebar.contains(e.target) && !toggleBtn && window.innerWidth <= 768) {
                sidebar.classList.remove('show');
            }
        });

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                showError('Passwords do not match');
            }
        });
    </script>
    <script src="../../assets/js/form-submission-manager.js"></script>
</body>
</html>