<?php require_once __DIR__ . '/config/config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WPU Applicant System - Start Here</title>
    <link href="<?= BASE_URL ?>assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .step {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .step:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 20px;
        }
        .btn-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .status-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .status-ok {
            color: #28a745;
            font-weight: bold;
        }
        .status-error {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h1><i class="fas fa-graduation-cap" style="color: #667eea;"></i> WPU Applicant System</h1>
                            <p class="lead">Welcome! Let's get you started.</p>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> <strong>First Time Setup</strong><br>
                            Follow these steps to set up and access the system.
                        </div>

                        <h4 class="mb-3">Quick Setup Steps:</h4>

                        <div class="step">
                            <div class="d-flex align-items-start">
                                <div class="step-number me-3">1</div>
                                <div class="flex-grow-1">
                                    <h5>Verify XAMPP is Running</h5>
                                    <p class="mb-2">Make sure Apache and MySQL services are started in XAMPP Control Panel.</p>
                                    <div class="status-box">
                                        <i class="fas fa-server"></i> <strong>Apache:</strong> <span class="status-ok">Must be GREEN/Started</span><br>
                                        <i class="fas fa-database"></i> <strong>MySQL:</strong> <span class="status-ok">Must be GREEN/Started</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="step">
                            <div class="d-flex align-items-start">
                                <div class="step-number me-3">2</div>
                                <div class="flex-grow-1">
                                    <h5>Setup Database</h5>
                                    <p class="mb-2">Initialize the database with all required tables.</p>
                                    <p class="text-muted">Database setup has been completed. If you need to reconfigure, please contact your system administrator.</p>
                                </div>
                            </div>
                        </div>

                        <div class="step">
                            <div class="d-flex align-items-start">
                                <div class="step-number me-3">3</div>
                                <div class="flex-grow-1">
                                    <h5>Login to System</h5>
                                    <p class="mb-2">Use the default admin credentials to login.</p>
                                    <div class="status-box">
                                        <strong>Default Admin Credentials:</strong><br>
                                        <i class="fas fa-envelope"></i> <strong>Email:</strong> admin@wpu.ac.id<br>
                                        <i class="fas fa-lock"></i> <strong>Password:</strong> password<br>
                                        <i class="fas fa-user-shield"></i> <strong>Role:</strong> Admin
                                    </div>
                                    <a href="login.php" class="btn btn-custom">
                                        <i class="fas fa-sign-in-alt"></i> Go to Login
                                    </a>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6><i class="fas fa-link"></i> Quick Links</h6>
                                <ul class="list-unstyled">
                                    <li><a href="login.php">Regular Login</a></li>
                                    <li><a href="register.php">Register New Applicant</a></li>
                                </ul>
                            </div>
                            <div class="col-md-6 mb-3">
                                <h6><i class="fas fa-tools"></i> Troubleshooting</h6>
                                <ul class="list-unstyled">
                                    <li><a href="TROUBLESHOOTING.md" target="_blank">View Troubleshooting Guide</a></li>
                                </ul>
                            </div>
                        </div>

                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle"></i> <strong>Security Warning:</strong><br>
                            Please change the default admin password immediately after first login!
                        </div>

                        <div class="alert alert-danger mt-3">
                            <i class="fas fa-exclamation-circle"></i> <strong>Having Issues?</strong><br>
                            If you see "ERR_TOO_MANY_REDIRECTS":
                            <ol class="mb-0 mt-2">
                                <li>Clear your browser cookies and cache</li>
                                <li>Make sure database is setup first</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <small class="text-white">
                        <i class="fas fa-info-circle"></i> For detailed instructions, see INSTALLATION.md or TROUBLESHOOTING.md
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= BASE_URL ?>assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>