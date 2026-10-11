<?php
require_once '../../config/config.php';
requireAuth();

if (hasRole(ROLE_ADMIN)) {
    header('Location: ' . BASE_URL . 'admin/pages/positions.php');
    exit();
}

// Require privacy waiver acceptance
requirePrivacyWaiver();

require_once '../../includes/models.php';

$positionModel = new Position();
$applicationModel = new Application();
$userModel = new User();

$positionId = $_GET['id'] ?? 0;
$position = $positionModel->findById($positionId);

if (!$position) {
    header('Location: positions.php');
    exit();
}

$userId = $_SESSION['user_id'];
$totalExperience = $userModel->getTotalExperience($userId);
$hasApplied = $applicationModel->findByUserAndPosition($userId, $positionId);
$meetsRequirements = $totalExperience >= $position['min_experience_years'];
$applicationCount = $positionModel->getApplicationCount($positionId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($position['title']) ?> - Dumaran Hiring System</title>
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/office-theme.css" rel="stylesheet">
    <?php include '../../includes/pwa-header.php'; ?>
</head>
<body data-theme="system">
<?php include '../../includes/applicant-theme-init.php'; ?>
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">
                <img src="../../assets/logo.webp" alt="Logo" style="height: 32px; width: auto;">
                <span>Dumaran Hiring</span>
            </a>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="sidebar-nav-item">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="positions.php" class="sidebar-nav-item active">
                <i class="fas fa-briefcase"></i>
                <span>Browse Jobs</span>
            </a>
            <a href="my-applications.php" class="sidebar-nav-item">
                <i class="fas fa-file-alt"></i>
                <span>My Applications</span>
            </a>
            <a href="profile.php" class="sidebar-nav-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">
                    <?php 
                    $user = $userModel->findById($userId);
                    echo strtoupper(substr($user['full_name'], 0, 1));
                    ?>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?= htmlspecialchars($user['full_name']) ?></div>
                    <div class="sidebar-user-role">Applicant</div>
                </div>
                <a href="auth/logout.php" class="confirm-logout" style="color: var(--gray-500); text-decoration: none;">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <!-- Top Bar -->
        <div class="topbar">
            <button class="mobile-menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="topbar-title">Job Details</h1>
            <div class="topbar-actions">
                <?php include '../../includes/applicant-theme-toggle.php'; ?>
                <a href="positions.php" class="topbar-btn">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <a href="../../auth/logout.php" class="topbar-btn confirm-logout" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content">
            <!-- Position Header -->
            <div class="card" style="background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%); border: none; color: white; margin-bottom: 24px;">
                <div class="card-body" style="padding: 32px;">
                    <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 16px;"><?= htmlspecialchars($position['title']) ?></h1>
                    <div style="display: flex; flex-wrap: wrap; gap: 20px; font-size: 0.95rem;">
                        <span><i class="fas fa-briefcase"></i> <?= ucfirst(str_replace('-', ' ', $position['employment_type'])) ?></span>
                        <?php if ($position['location']): ?>
                            <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($position['location']) ?></span>
                        <?php endif; ?>
                        <span><i class="fas fa-clock"></i> Min. <?= $position['min_experience_years'] ?> years</span>
                        <?php if ($position['salary_range']): ?>
                            <?php
                                $salaryRangeDisplay = $position['salary_range'];
                                if (!preg_match('/(₱|PHP|PESO)/i', $salaryRangeDisplay)) {
                                    $salaryRangeDisplay = 'PHP ' . $salaryRangeDisplay;
                                }
                            ?>
                            <span><i class="fas fa-money-bill-wave"></i> <?= htmlspecialchars($salaryRangeDisplay) ?></span>
                        <?php endif; ?>
                        <?php if ($position['deadline']): ?>
                            <span><i class="fas fa-calendar"></i> Deadline: <?= date('M j, Y', strtotime($position['deadline'])) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
                <div>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-info-circle"></i> Job Description
                            </h3>
                        </div>
                        <div class="card-body">
                            <?= nl2br(htmlspecialchars($position['description'])) ?>
                        </div>
                    </div>

                    <?php if ($position['requirements']): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-check-circle"></i> Requirements
                                </h3>
                            </div>
                            <div class="card-body">
                                <?php
                                $requirements = explode("\n", $position['requirements']);
                                foreach ($requirements as $req):
                                    $req = trim($req);
                                    if (!empty($req)):
                                ?>
                                    <div style="padding: 12px 16px; background: var(--gray-50); border-radius: 8px; margin-bottom: 12px; display: flex; align-items: start; gap: 12px;">
                                        <i class="fas fa-check-circle" style="color: var(--primary-blue); margin-top: 4px;"></i>
                                        <span><?= htmlspecialchars($req) ?></span>
                                    </div>
                                <?php
                                    endif;
                                endforeach;
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-user-check"></i> Your Status
                            </h3>
                        </div>
                        <div class="card-body">
                            <div style="background: var(--gray-50); border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 16px;">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--primary-blue);"><?= number_format($totalExperience, 1) ?></div>
                                <div style="font-size: 0.9rem; color: var(--gray-500);">Your Experience (Years)</div>
                            </div>
                            
                            <div style="background: var(--gray-50); border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 16px;">
                                <div style="font-size: 2rem; font-weight: 700; color: var(--primary-blue);"><?= $position['min_experience_years'] ?></div>
                                <div style="font-size: 0.9rem; color: var(--gray-500);">Required Experience (Years)</div>
                            </div>
                            
                            <div style="margin-bottom: 20px;">
                                <?php if ($meetsRequirements): ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle"></i>
                                        <div>You meet the requirements!</div>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <div>You need <?= $position['min_experience_years'] - $totalExperience ?> more years</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($hasApplied): ?>
                                <button class="btn btn-primary w-100" disabled style="width: 100%;">
                                    <i class="fas fa-check-circle"></i> Already Applied
                                </button>
                                <p style="text-align: center; color: var(--gray-500); margin-top: 12px; font-size: 0.875rem;">
                                    Status: <?= ucfirst($hasApplied['status']) ?>
                                </p>
                            <?php elseif (!$meetsRequirements): ?>
                                <button class="btn btn-primary w-100" style="width: 100%; background: var(--gray-400); cursor: not-allowed;" disabled>
                                    <i class="fas fa-lock"></i> Insufficient Experience
                                </button>
                            <?php else: ?>
                                <a href="apply.php?position=<?= $position['id'] ?>" class="btn btn-success w-100" style="width: 100%;">
                                    <i class="fas fa-paper-plane"></i> Apply Now
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($position['max_applications']): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-users"></i> Applications
                                </h3>
                            </div>
                            <div class="card-body">
                                <div style="background: var(--gray-200); height: 8px; border-radius: 4px; overflow: hidden; margin-bottom: 12px;">
                                    <?php $percentage = ($applicationCount / $position['max_applications']) * 100; ?>
                                    <div style="width: <?= $percentage ?>%; height: 100%; background: var(--info);"></div>
                                </div>
                                <p style="text-align: center; color: var(--gray-600); font-size: 0.875rem; margin: 0;">
                                    <?= $applicationCount ?> of <?= $position['max_applications'] ?> applications
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
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
            <a href="positions.php" class="mobile-nav-item active">
                <i class="fas fa-briefcase"></i>
                <span>Jobs</span>
            </a>
            <a href="my-applications.php" class="mobile-nav-item">
                <i class="fas fa-file-alt"></i>
                <span>Applications</span>
            </a>
            <a href="profile.php" class="mobile-nav-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </div>
    </nav>

    <script src="../../assets/js/toast.js"></script>
    <script src="../../assets/js/office-theme.js"></script>
    <script src="../../assets/js/applicant-theme.js"></script>
    <?php include '../../includes/pwa-scripts.php'; ?>
</body>
</html>
