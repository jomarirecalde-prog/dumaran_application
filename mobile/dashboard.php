<?php
require_once '../config/config.php';
require_once '../includes/models.php';

requireAuth();

// Redirect admin to admin dashboard
if (hasRole(ROLE_ADMIN)) {
    header('Location: ' . BASE_URL . 'admin/dashboard.php');
    exit();
}

$userModel = new User();
$positionModel = new Position();
$applicationModel = new Application();

$userId = $_SESSION['user_id'];

// Get user data
$user = $userModel->findById($userId);

// Get user's total experience
$totalExperience = $userModel->getTotalExperience($userId);

// Get user's applications
$userApplications = $applicationModel->getUserApplications($userId);

// Get active positions (limited for mobile)
$activePositions = array_slice($positionModel->getActivePositions(), 0, 4);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - WPU Applicant System</title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }
        
        body {
            background-color: #f8f9fa;
            padding-bottom: 80px; /* Space for bottom navigation */
        }
        
        /* Mobile-optimized header */
        .mobile-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .welcome-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin: -40px 15px 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 15px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 25px 15px 15px;
            color: #333;
        }
        
        .position-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin: 0 15px 15px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            border-left: 4px solid var(--primary-color);
        }
        
        .position-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .position-meta {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 12px;
        }
        
        .btn-apply-mobile {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            width: 100%;
        }
        
        .application-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin: 0 15px 15px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            padding: 10px 0;
            z-index: 1000;
        }
        
        .bottom-nav-item {
            flex: 1;
            text-align: center;
            padding: 8px;
            color: #666;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .bottom-nav-item.active,
        .bottom-nav-item:hover {
            color: var(--primary-color);
        }
        
        .bottom-nav-item i {
            font-size: 1.2rem;
            display: block;
            margin-bottom: 4px;
        }
        
        .bottom-nav-item span {
            font-size: 0.75rem;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin: 20px 15px;
        }
        
        .quick-action {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            text-decoration: none;
            color: #333;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
        }
        
        .quick-action:hover {
            transform: translateY(-2px);
            color: var(--primary-color);
        }
        
        .quick-action i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        /* Empty states */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                margin: 15px 10px;
            }
            
            .position-card,
            .application-card {
                margin: 0 10px 10px;
                padding: 15px;
            }
            
            .welcome-card {
                margin: -30px 10px 15px;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <header class="mobile-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><i class="fas fa-graduation-cap"></i> WPU Jobs</h5>
                </div>
                <div class="dropdown">
                    <button class="btn btn-link text-white" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle fa-2x"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../auth/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <!-- Welcome Card -->
    <div class="welcome-card">
        <h6 class="mb-2">Welcome back!</h6>
        <h5 class="mb-3 text-primary"><?= htmlspecialchars($user['full_name']) ?></h5>
        <p class="text-muted small mb-0">Find your next career opportunity</p>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= count($userApplications) ?></div>
            <div class="stat-label">Applications</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= count(array_filter($userApplications, function($app) { return $app['status'] === STATUS_PENDING; })) ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($totalExperience, 1) ?></div>
            <div class="stat-label">Years Exp</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= count($activePositions) ?>+</div>
            <div class="stat-label">Open Jobs</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="positions.php" class="quick-action">
            <i class="fas fa-search"></i>
            <div>Browse Jobs</div>
        </a>
        <a href="my-applications.php" class="quick-action">
            <i class="fas fa-file-alt"></i>
            <div>My Applications</div>
        </a>
    </div>

    <!-- Latest Jobs Section -->
    <div class="section-title">
        <i class="fas fa-briefcase"></i> Latest Jobs
    </div>

    <?php if (empty($activePositions)): ?>
        <div class="empty-state">
            <i class="fas fa-briefcase"></i>
            <h6>No Jobs Available</h6>
            <p class="small">Check back later for new opportunities</p>
        </div>
    <?php else: ?>
        <?php foreach ($activePositions as $position): ?>
            <?php 
            $hasApplied = false;
            foreach ($userApplications as $app) {
                if ($app['position_id'] == $position['id']) {
                    $hasApplied = true;
                    break;
                }
            }
            ?>
            <div class="position-card">
                <div class="position-title"><?= htmlspecialchars($position['title']) ?></div>
                <div class="position-meta">
                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($position['location'] ?? 'Remote') ?>
                    <span class="ms-2">
                        <i class="fas fa-clock"></i> <?= $position['min_experience_years'] ?>+ years
                    </span>
                </div>
                <p class="small text-muted mb-3"><?= htmlspecialchars(substr($position['description'], 0, 80)) ?>...</p>
                
                <div class="d-flex justify-content-between align-items-center">
                    <?php if ($hasApplied): ?>
                        <span class="status-badge bg-success text-white">Applied</span>
                    <?php elseif ($totalExperience < $position['min_experience_years']): ?>
                        <span class="status-badge bg-warning text-dark">Need More Exp</span>
                    <?php else: ?>
                        <a href="apply.php?position=<?= $position['id'] ?>" class="btn btn-apply-mobile btn-sm">
                            Apply Now
                        </a>
                    <?php endif; ?>
                    <a href="position-details.php?id=<?= $position['id'] ?>" class="btn btn-outline-primary btn-sm">
                        View
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="text-center" style="margin: 20px;">
            <a href="positions.php" class="btn btn-outline-primary">
                View All Jobs <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    <?php endif; ?>

    <!-- Recent Applications Section -->
    <div class="section-title">
        <i class="fas fa-file-alt"></i> Recent Applications
    </div>

    <?php if (empty($userApplications)): ?>
        <div class="empty-state">
            <i class="fas fa-file-alt"></i>
            <h6>No Applications Yet</h6>
            <p class="small">Start applying to available positions</p>
            <a href="positions.php" class="btn btn-primary btn-sm">Browse Jobs</a>
        </div>
    <?php else: ?>
        <?php foreach (array_slice($userApplications, 0, 3) as $application): ?>
            <div class="application-card">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="mb-0"><?= htmlspecialchars($application['position_title']) ?></h6>
                    <span class="status-badge 
                        <?= $application['status'] === STATUS_PENDING ? 'bg-warning text-dark' : 
                           ($application['status'] === STATUS_APPROVED ? 'bg-success text-white' : 'bg-danger text-white') ?>">
                        <?= ucfirst($application['status']) ?>
                    </span>
                </div>
                <p class="small text-muted mb-2">
                    Applied: <?= date('M j, Y', strtotime($application['applied_at'])) ?>
                </p>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        Experience: <?= $application['total_experience_years'] ?> years
                    </small>
                    <a href="application-details.php?id=<?= $application['id'] ?>" class="btn btn-outline-primary btn-sm">
                        Details
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="text-center" style="margin: 20px;">
            <a href="my-applications.php" class="btn btn-outline-primary">
                View All Applications
            </a>
        </div>
    <?php endif; ?>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav d-flex">
        <a href="dashboard.php" class="bottom-nav-item active">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="positions.php" class="bottom-nav-item">
            <i class="fas fa-search"></i>
            <span>Jobs</span>
        </a>
        <a href="my-applications.php" class="bottom-nav-item">
            <i class="fas fa-file-alt"></i>
            <span>Applications</span>
        </a>
        <a href="profile.php" class="bottom-nav-item">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
    </nav>

    <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add smooth scrolling and touch-friendly interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add touch feedback for buttons
            const buttons = document.querySelectorAll('.btn, .quick-action, .position-card');
            buttons.forEach(btn => {
                btn.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.98)';
                });
                btn.addEventListener('touchend', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Auto-hide header on scroll down, show on scroll up
            let lastScrollTop = 0;
            const header = document.querySelector('.mobile-header');
            
            window.addEventListener('scroll', function() {
                let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                
                if (scrollTop > lastScrollTop && scrollTop > 100) {
                    // Scrolling down
                    header.style.transform = 'translateY(-100%)';
                } else {
                    // Scrolling up
                    header.style.transform = 'translateY(0)';
                }
                
                lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
            }, false);
        });
    </script>
</body>
</html>