<?php
require_once '../../config/config.php';

requireAuth();

// Redirect admin to admin dashboard
if (hasRole(ROLE_ADMIN)) {
    header('Location: ' . BASE_URL . 'admin/pages/dashboard.php');
    exit();
}

// Try to load models and data, with error handling
try {
    require_once '../../includes/models.php';
    
    $userModel = new User();
    $positionModel = new Position();
    $applicationModel = new Application();
    $experienceModel = new Experience();

    $userId = $_SESSION['user_id'];

    // Get user data
    $user = $userModel->findById($userId);
    
    // Check if user has accepted data privacy waiver
    // Handle case where column might not exist yet (for existing databases)
    $privacyWaiverAccepted = !empty($user['data_privacy_accepted_at'] ?? null);

    // Get user's total experience
    $totalExperience = $userModel->getTotalExperience($userId);

    // Get user's applications
    $userApplications = $applicationModel->getUserApplications($userId);

    // Get active positions
    $activePositions = $positionModel->getActivePositions();
    
    // Get user experience
    $userExperiences = $experienceModel->getUserExperience($userId);
    
} catch (Exception $e) {
    // Database error - show setup message
    $databaseError = true;
    $errorMessage = $e->getMessage();
    
    // Set default values to prevent undefined variable errors
    $user = ['full_name' => $_SESSION['full_name'] ?? 'User', 'email' => $_SESSION['email'] ?? ''];
    $totalExperience = 0;
    $userApplications = [];
    $activePositions = [];
    $userExperiences = [];
    $privacyWaiverAccepted = true; // Default to true if database error to prevent blocking
}

// Count applications by status
$pendingCount = count(array_filter($userApplications, fn($app) => $app['status'] === STATUS_PENDING));
$approvedCount = count(array_filter($userApplications, fn($app) => $app['status'] === STATUS_APPROVED));

// ── View-layer metrics (derived from existing data, no additional queries) ──
$rejectedCount = count(array_filter($userApplications, fn($app) => $app['status'] === STATUS_REJECTED));
$totalApplications = count($userApplications);
$hour = (int) date('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');

$nameParts = preg_split('/\s+/', trim($user['full_name'] ?? 'User'));
$profileInitials = strtoupper(substr($nameParts[0] ?? 'U', 0, 1) . substr($nameParts[count($nameParts) - 1] ?? '', 0, 1));
$hasProfilePhoto = !empty($user['profile_picture']) && file_exists(__DIR__ . '/uploads/' . $user['profile_picture']);

$profileSections = [
    'personal'    => ['label' => 'Personal Info', 'url' => 'profile.php', 'step' => 0, 'complete' => !empty($user['full_name']) && !empty($user['email']) && !empty($user['address']) && !empty($user['age']) && !empty($user['sex']) && !empty($user['civil_status'])],
    'education'   => ['label' => 'Education', 'url' => 'profile.php', 'step' => 1, 'complete' => !empty($user['highest_educational_attainment'])],
    'experience'  => ['label' => 'Experience', 'url' => 'profile.php', 'step' => 2, 'complete' => count($userExperiences) > 0],
    'eligibility' => ['label' => 'Eligibility', 'url' => 'profile.php', 'step' => 3, 'complete' => !empty($user['career_service_eligibilities'])],
    'documents'   => ['label' => 'Documents', 'url' => 'profile.php', 'step' => 4, 'complete' => !empty($user['ipcr_file_path']) || !empty($user['pe_file_path'])],
];
$profileCompletedCount = count(array_filter($profileSections, fn($s) => $s['complete']));
$profileCompletionPct = (int) round(($profileCompletedCount / count($profileSections)) * 100);
$profileMissing = array_filter($profileSections, fn($s) => !$s['complete']);

$appliedPositionIds = [];
foreach ($userApplications as $app) {
    $appliedPositionIds[$app['position_id']] = $app['status'];
}

$calcJobMatch = function ($position) use ($totalExperience) {
    $minExp = (float) ($position['min_experience_years'] ?? 0);
    if ($minExp <= 0) {
        return 92;
    }
    $ratio = min(1.2, $totalExperience / max(0.1, $minExp));
    return (int) min(100, max(45, round($ratio * 85 + 10)));
};

$scoredPositions = [];
foreach ($activePositions as $pos) {
    $pos['_match'] = $calcJobMatch($pos);
    $pos['_applied'] = isset($appliedPositionIds[$pos['id']]);
    $scoredPositions[] = $pos;
}
usort($scoredPositions, fn($a, $b) => $b['_match'] <=> $a['_match']);
$recommendedJobs = array_slice(array_filter($scoredPositions, fn($p) => !$p['_applied']), 0, 3);
$recentJobOpenings = array_slice($activePositions, 0, 4);

$interviewCount = count(array_filter($userApplications, fn($app) => !empty($app['interview_date'])));
$decidedCount = $approvedCount + $rejectedCount;
$successRate = $decidedCount > 0 ? (int) round(($approvedCount / $decidedCount) * 100) : 0;

$responseDays = [];
foreach ($userApplications as $app) {
    if (in_array($app['status'], [STATUS_APPROVED, STATUS_REJECTED], true) && !empty($app['reviewed_at'])) {
        $responseDays[] = max(1, (int) round((strtotime($app['reviewed_at']) - strtotime($app['applied_at'])) / 86400));
    }
}
$avgResponseDays = count($responseDays) > 0 ? (int) round(array_sum($responseDays) / count($responseDays)) : null;

$getTrackerSteps = function ($app) {
    if ($app['status'] === STATUS_APPROVED) {
        return [
            ['label' => 'Applied', 'state' => 'done'],
            ['label' => 'HR Review', 'state' => 'done'],
            ['label' => 'Interview', 'state' => 'done'],
            ['label' => 'Decision', 'state' => 'done'],
        ];
    }
    if ($app['status'] === STATUS_REJECTED) {
        return [
            ['label' => 'Applied', 'state' => 'done'],
            ['label' => 'HR Review', 'state' => 'done'],
            ['label' => 'Interview', 'state' => 'waiting'],
            ['label' => 'Decision', 'state' => 'active'],
        ];
    }
    return [
        ['label' => 'Applied', 'state' => 'done'],
        ['label' => 'HR Review', 'state' => 'active'],
        ['label' => 'Interview', 'state' => 'waiting'],
        ['label' => 'Decision', 'state' => 'waiting'],
    ];
};

$notifications = [];
foreach (array_slice($userApplications, 0, 5) as $app) {
    if ($app['status'] === STATUS_APPROVED) {
        $notifications[] = [
            'type' => 'success',
            'icon' => 'fa-check-circle',
            'title' => 'Application Reviewed',
            'text' => 'Your application for ' . $app['position_title'] . ' was approved.',
            'time' => $app['reviewed_at'] ?? $app['applied_at'],
            'unread' => false,
        ];
    } elseif ($app['status'] === STATUS_REJECTED) {
        $notifications[] = [
            'type' => 'warning',
            'icon' => 'fa-times-circle',
            'title' => 'Application Update',
            'text' => 'Update on your application for ' . $app['position_title'] . '.',
            'time' => $app['reviewed_at'] ?? $app['applied_at'],
            'unread' => false,
        ];
    } elseif (!empty($app['interview_date'])) {
        $notifications[] = [
            'type' => 'info',
            'icon' => 'fa-calendar-check',
            'title' => 'Interview Scheduled',
            'text' => 'Interview for ' . $app['position_title'] . '.',
            'time' => $app['interview_date'],
            'unread' => true,
        ];
    } else {
        $notifications[] = [
            'type' => 'info',
            'icon' => 'fa-file-alt',
            'title' => 'Application Received',
            'text' => 'Your application for ' . $app['position_title'] . ' is under review.',
            'time' => $app['applied_at'],
            'unread' => $app['status'] === STATUS_PENDING,
        ];
    }
}
if (!empty($activePositions)) {
    $notifications[] = [
        'type' => 'info',
        'icon' => 'fa-briefcase',
        'title' => 'New Positions Posted',
        'text' => count($activePositions) . ' job opening(s) currently available.',
        'time' => date('Y-m-d H:i:s'),
        'unread' => true,
    ];
}
usort($notifications, fn($a, $b) => strtotime($b['time']) - strtotime($a['time']));
$notifications = array_slice($notifications, 0, 6);
$unreadNotifCount = count(array_filter($notifications, fn($n) => $n['unread']));

$activityFeed = [];
foreach ($userApplications as $app) {
    $activityFeed[] = [
        'icon' => 'fa-paper-plane',
        'text' => 'Applied for <strong>' . htmlspecialchars($app['position_title']) . '</strong>',
        'time' => $app['applied_at'],
    ];
}
if (!empty($user['updated_at'])) {
    $activityFeed[] = [
        'icon' => 'fa-user-edit',
        'text' => 'Profile updated',
        'time' => $user['updated_at'],
    ];
}
usort($activityFeed, fn($a, $b) => strtotime($b['time']) - strtotime($a['time']));
$activityFeed = array_slice($activityFeed, 0, 8);

$calendarEvents = [];
foreach ($userApplications as $app) {
    if (!empty($app['interview_date'])) {
        $calendarEvents[] = ['date' => $app['interview_date'], 'title' => 'Interview: ' . $app['position_title']];
    }
}
foreach ($activePositions as $pos) {
    if (!empty($pos['deadline'])) {
        $calendarEvents[] = ['date' => $pos['deadline'], 'title' => 'Deadline: ' . $pos['title']];
    }
}

$achievements = [
    ['label' => 'Profile 100% Complete', 'icon' => 'fa-star', 'earned' => $profileCompletionPct >= 100],
    ['label' => 'First Application', 'icon' => 'fa-file-alt', 'earned' => $totalApplications >= 1],
    ['label' => 'Documents Verified', 'icon' => 'fa-shield-alt', 'earned' => $profileSections['documents']['complete']],
    ['label' => 'Interview Ready', 'icon' => 'fa-handshake', 'earned' => $interviewCount >= 1],
];

$applicantStatus = $profileCompletionPct >= 100 ? 'Profile Complete' : ($totalApplications > 0 ? 'Active Applicant' : 'New Applicant');
$showContinueCard = $profileCompletionPct < 100 || ($totalApplications === 0 && !empty($activePositions));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - Dumaran Hiring System</title>
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/office-theme.css" rel="stylesheet">
    <link href="../../assets/css/applicant-dashboard.css" rel="stylesheet">
    <?php include '../../includes/pwa-header.php'; ?>
</head>
<body class="dashboard-page" data-theme="system">
<?php include '../../includes/applicant-theme-init.php'; ?>
    <!-- Sidebar Navigation -->
    <aside class="sidebar" aria-label="Main navigation">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">
                <img src="../../assets/logo.webp" alt="Logo" style="height: 32px; width: auto;">
                <span>Dumaran Hiring</span>
            </a>
        </div>

        <button type="button" class="sidebar-collapse-btn" id="sidebarCollapseBtn" aria-label="Toggle sidebar">
            <i class="fas fa-angles-left"></i>
            <span>Collapse</span>
        </button>
        
        <nav class="sidebar-nav">
            <a href="profile.php" class="sidebar-nav-item">
                <i class="fas fa-user" aria-hidden="true"></i>
                <span>Profile</span>
            </a>
            <a href="dashboard.php" class="sidebar-nav-item active" aria-current="page">
                <i class="fas fa-th-large" aria-hidden="true"></i>
                <span>Dashboard</span>
            </a>
            <a href="positions.php" class="sidebar-nav-item">
                <i class="fas fa-briefcase" aria-hidden="true"></i>
                <span>Browse Jobs</span>
            </a>
            <a href="my-applications.php" class="sidebar-nav-item">
                <i class="fas fa-file-alt" aria-hidden="true"></i>
                <span>My Applications</span>
                <?php if ($pendingCount > 0): ?>
                <span class="sidebar-nav-badge" aria-label="<?= $pendingCount ?> pending"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar" aria-hidden="true">
                    <?= htmlspecialchars($profileInitials) ?>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?= htmlspecialchars($user['full_name']) ?></div>
                    <div class="sidebar-user-role">Applicant</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Notification Panel -->
    <aside class="dash-notif-panel" id="notifPanel" aria-label="Notifications" role="dialog" aria-hidden="true">
        <div class="dash-notif-panel__header">
            <strong>Notifications</strong>
            <button type="button" class="topbar-btn" id="notifPanelClose" aria-label="Close notifications"><i class="fas fa-times"></i></button>
        </div>
        <div class="dash-notif-panel__body">
            <?php if (empty($notifications)): ?>
                <p class="dash-empty__text">No notifications yet.</p>
            <?php else: ?>
                <div class="dash-notif-list">
                    <?php foreach ($notifications as $notif): ?>
                    <div class="dash-notif-item <?= $notif['unread'] ? 'unread' : '' ?>">
                        <div class="dash-notif-item__icon <?= $notif['type'] ?>"><i class="fas <?= $notif['icon'] ?>"></i></div>
                        <div>
                            <div class="dash-notif-item__title"><?= htmlspecialchars($notif['title']) ?></div>
                            <div class="dash-notif-item__text"><?= htmlspecialchars($notif['text']) ?></div>
                            <div class="dash-notif-item__time"><?= date('M j, g:i A', strtotime($notif['time'])) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <!-- Top Bar -->
        <header class="topbar">
            <button type="button" class="mobile-menu-toggle" aria-label="Open menu">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="topbar-title">Dashboard</h1>

            <form class="dash-topbar-search" id="dashSearchForm" action="positions.php" role="search">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" id="dashSearchInput" name="search" placeholder="Search jobs, positions..." aria-label="Search jobs">
            </form>

            <div class="topbar-actions">
                <button type="button" class="topbar-btn" id="notifPanelToggle" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadNotifCount > 0): ?>
                    <span class="topbar-btn__badge"><?= $unreadNotifCount ?></span>
                    <?php endif; ?>
                </button>
                <a href="chat.php" class="topbar-btn" title="Messages" aria-label="Messages">
                    <i class="fas fa-comment-dots"></i>
                </a>
                <div class="dash-theme-menu">
                    <button type="button" class="topbar-btn" data-dropdown-toggle="themeDropdown" aria-label="Theme settings">
                        <i class="fas fa-circle-half-stroke"></i>
                    </button>
                    <div class="dash-theme-dropdown" id="themeDropdown" role="menu">
                        <button type="button" class="dash-theme-option" data-theme-set="light" role="menuitem"><i class="fas fa-sun"></i> Light</button>
                        <button type="button" class="dash-theme-option" data-theme-set="dark" role="menuitem"><i class="fas fa-moon"></i> Dark</button>
                        <button type="button" class="dash-theme-option active" data-theme-set="system" role="menuitem"><i class="fas fa-desktop"></i> System</button>
                    </div>
                </div>
                <div class="dash-profile-menu">
                    <button type="button" class="topbar-btn" data-dropdown-toggle="profileDropdown" aria-label="Profile menu">
                        <i class="fas fa-user-circle"></i>
                    </button>
                    <div class="dash-profile-dropdown" id="profileDropdown" role="menu">
                        <a href="profile.php" role="menuitem"><i class="fas fa-user"></i> My Profile</a>
                        <a href="my-applications.php" role="menuitem"><i class="fas fa-file-alt"></i> Applications</a>
                        <a href="../../auth/logout.php" class="confirm-logout danger" role="menuitem"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="content">
            <div class="dash-skeleton-wrap" aria-hidden="true">
                <div class="dash-skeleton" style="height:180px;margin-bottom:24px"></div>
                <div class="dash-skeleton" style="height:120px;margin-bottom:24px"></div>
            </div>

            <div class="dash-content-wrap">
            <?php if (isset($databaseError) && $databaseError): ?>
            <div class="alert alert-warning" role="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Database Setup Required</strong><br>
                    The system database needs to be initialized before you can use all features. Please contact your system administrator.
                </div>
            </div>
            <?php endif; ?>

            <!-- Hero Section -->
            <section class="dash-hero" aria-label="Welcome">
                <div class="dash-hero__main">
                    <div class="dash-hero__avatar" aria-hidden="true">
                        <?php if ($hasProfilePhoto): ?>
                            <img src="uploads/<?= htmlspecialchars($user['profile_picture']) ?>" alt="">
                        <?php else: ?>
                            <?= htmlspecialchars($profileInitials) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="dash-hero__greeting"><?= $greeting ?>,</p>
                        <h2 class="dash-hero__name"><?= htmlspecialchars($user['full_name']) ?></h2>
                        <div class="dash-hero__meta">
                            <span class="dash-hero__badge"><i class="fas fa-id-badge"></i> ID #<?= (int) ($user['id'] ?? 0) ?></span>
                            <span class="dash-hero__badge"><i class="fas fa-user-check"></i> <?= htmlspecialchars($applicantStatus) ?></span>
                        </div>
                        <div class="dash-hero__stats">
                            <div class="dash-hero__stat">
                                <div class="dash-hero__stat-value"><?= $profileCompletionPct ?>%</div>
                                <div class="dash-hero__stat-label">Profile</div>
                            </div>
                            <div class="dash-hero__stat">
                                <div class="dash-hero__stat-value" data-count="<?= $totalApplications ?>">0</div>
                                <div class="dash-hero__stat-label">Applications</div>
                            </div>
                            <div class="dash-hero__stat">
                                <div class="dash-hero__stat-value" data-count="<?= $pendingCount ?>">0</div>
                                <div class="dash-hero__stat-label">Pending</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="dash-hero__actions">
                    <span class="dash-hero__actions-title">Quick Actions</span>
                    <a href="positions.php" class="dash-quick-link"><i class="fas fa-search"></i> Browse Jobs</a>
                    <a href="profile.php" class="dash-quick-link"><i class="fas fa-user-edit"></i> Complete Profile</a>
                    <a href="my-applications.php" class="dash-quick-link"><i class="fas fa-file-alt"></i> View Applications</a>
                </div>
            </section>

            <!-- Quick Action Cards -->
            <nav class="dash-actions-grid" aria-label="Dashboard quick actions">
                <a href="positions.php" class="dash-action-card"><div class="dash-action-card__icon"><i class="fas fa-briefcase"></i></div><span class="dash-action-card__label">Browse Jobs</span></a>
                <a href="my-applications.php" class="dash-action-card"><div class="dash-action-card__icon"><i class="fas fa-file-signature"></i></div><span class="dash-action-card__label">Continue Application</span></a>
                <a href="profile.php" class="dash-action-card"><div class="dash-action-card__icon"><i class="fas fa-user-edit"></i></div><span class="dash-action-card__label">Update Profile</span></a>
                <a href="profile.php" class="dash-action-card"><div class="dash-action-card__icon"><i class="fas fa-cloud-upload-alt"></i></div><span class="dash-action-card__label">Upload Documents</span></a>
                <a href="my-applications.php" class="dash-action-card"><div class="dash-action-card__icon"><i class="fas fa-folder-open"></i></div><span class="dash-action-card__label">My Applications</span></a>
                <a href="my-applications.php" class="dash-action-card"><div class="dash-action-card__icon"><i class="fas fa-calendar-check"></i></div><span class="dash-action-card__label">Interview Schedule</span></a>
            </nav>

            <?php if ($showContinueCard): ?>
            <div class="dash-continue-card" role="region" aria-label="Continue where you left off">
                <div class="dash-continue-card__icon"><i class="fas fa-route"></i></div>
                <div class="dash-continue-card__content">
                    <div class="dash-continue-card__title">Continue Where You Left Off</div>
                    <div class="dash-continue-card__text">
                        <?php if ($profileCompletionPct < 100): ?>
                            Your profile is <?= $profileCompletionPct ?>% complete. Finish your profile to unlock better job matches.
                        <?php else: ?>
                            Start exploring opportunities and submit your first application today.
                        <?php endif; ?>
                    </div>
                </div>
                <a href="<?= $profileCompletionPct < 100 ? 'profile.php' : 'positions.php' ?>" class="dash-btn dash-btn--primary">
                    <?= $profileCompletionPct < 100 ? 'Complete Profile' : 'Browse Jobs' ?>
                </a>
            </div>
            <?php endif; ?>

            <!-- Profile Completion -->
            <section class="dash-profile-card" aria-label="Profile completion">
                <div class="dash-profile-card__header">
                    <h3 class="dash-profile-card__title">Profile Completion</h3>
                    <span class="dash-profile-card__pct"><?= $profileCompletionPct ?>%</span>
                </div>
                <div class="dash-progress-bar" role="progressbar" aria-valuenow="<?= $profileCompletionPct ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="dash-progress-bar__fill" style="width: <?= $profileCompletionPct ?>%"></div>
                </div>
                <?php if (!empty($profileMissing)): ?>
                <div class="dash-missing-items">
                    <span style="font-size:0.8rem;color:var(--dash-text-muted);font-weight:600;margin-right:4px">Missing:</span>
                    <?php foreach ($profileMissing as $key => $section): ?>
                    <a href="<?= $section['url'] ?>" class="dash-missing-chip"><?= htmlspecialchars($section['label']) ?></a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="dash-missing-items">
                    <span class="dash-missing-chip complete"><i class="fas fa-check"></i> All sections complete</span>
                </div>
                <?php endif; ?>
                <?php if ($profileCompletionPct < 100): ?>
                <a href="profile.php" class="dash-btn dash-btn--primary"><i class="fas fa-arrow-right"></i> Complete Profile</a>
                <?php endif; ?>
            </section>

            <!-- Statistics -->
            <div class="dash-stats-grid" aria-label="Application statistics">
                <?php
                $statCards = [
                    ['value' => $totalApplications, 'label' => 'Applications', 'icon' => 'fa-file-alt', 'color' => '#2050A0', 'trend' => $totalApplications > 0 ? 'Active pipeline' : 'Get started', 'trendClass' => 'neutral'],
                    ['value' => $pendingCount, 'label' => 'Pending', 'icon' => 'fa-clock', 'color' => '#f59e0b', 'trend' => $pendingCount > 0 ? 'In review' : 'All clear', 'trendClass' => 'neutral'],
                    ['value' => $approvedCount, 'label' => 'Approved', 'icon' => 'fa-check-circle', 'color' => '#10b981', 'trend' => $approvedCount > 0 ? '+' . $approvedCount . ' success' : 'None yet', 'trendClass' => $approvedCount > 0 ? 'up' : 'neutral'],
                    ['value' => $rejectedCount, 'label' => 'Rejected', 'icon' => 'fa-times-circle', 'color' => '#ef4444', 'trend' => $rejectedCount > 0 ? $rejectedCount . ' closed' : 'None', 'trendClass' => 'neutral'],
                    ['value' => $interviewCount, 'label' => 'Interviews', 'icon' => 'fa-handshake', 'color' => '#6366f1', 'trend' => $interviewCount > 0 ? 'Scheduled' : 'Pending', 'trendClass' => 'neutral'],
                    ['value' => 0, 'label' => 'Saved Jobs', 'icon' => 'fa-bookmark', 'color' => '#8b5cf6', 'trend' => 'Bookmark jobs', 'trendClass' => 'neutral', 'id' => 'savedJobsCount'],
                    ['value' => $totalExperience, 'label' => 'Years Experience', 'icon' => 'fa-briefcase', 'color' => '#0ea5e9', 'trend' => count($userExperiences) . ' record(s)', 'trendClass' => 'neutral', 'decimal' => true],
                    ['value' => count($activePositions), 'label' => 'Available Jobs', 'icon' => 'fa-building', 'color' => '#2050A0', 'trend' => 'Open now', 'trendClass' => 'up'],
                ];
                foreach ($statCards as $stat):
                ?>
                <div class="dash-stat-card" style="--stat-color: <?= $stat['color'] ?>; --stat-bg: <?= $stat['color'] ?>18; --stat-accent: linear-gradient(90deg, <?= $stat['color'] ?>, transparent)">
                    <div class="dash-stat-card__top">
                        <div>
                            <div class="dash-stat-card__value" <?= !empty($stat['id']) ? 'id="' . $stat['id'] . '"' : '' ?> data-count="<?= $stat['value'] ?>" <?= !empty($stat['decimal']) ? 'data-decimal="1"' : '' ?>>0</div>
                            <div class="dash-stat-card__label"><?= $stat['label'] ?></div>
                        </div>
                        <div class="dash-stat-card__icon"><i class="fas <?= $stat['icon'] ?>"></i></div>
                    </div>
                    <div class="dash-stat-card__trend <?= $stat['trendClass'] ?>">
                        <?php if ($stat['trendClass'] === 'up'): ?><i class="fas fa-arrow-up"></i><?php endif; ?>
                        <?= htmlspecialchars($stat['trend']) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="dash-main-grid">
                <!-- Application Trackers -->
                <section class="dash-widget" data-widget="applications">
                    <div class="dash-widget__header">
                        <h3 class="dash-widget__title"><i class="fas fa-route"></i> Application Status Tracker</h3>
                        <div class="dash-widget__actions">
                            <a href="my-applications.php" class="dash-link-btn">View All</a>
                            <button type="button" class="dash-widget__toggle" data-widget-toggle aria-label="Collapse section"><i class="fas fa-chevron-up"></i></button>
                        </div>
                    </div>
                    <div class="dash-widget__body">
                        <?php if (empty($userApplications)): ?>
                        <div class="dash-empty">
                            <div class="dash-empty__illus"><i class="fas fa-file-alt"></i></div>
                            <h4 class="dash-empty__title">No Applications Yet</h4>
                            <p class="dash-empty__text">Start exploring opportunities and take the next step in your career.</p>
                            <a href="positions.php" class="dash-btn dash-btn--primary"><i class="fas fa-search"></i> Browse Jobs</a>
                        </div>
                        <?php else: ?>
                            <?php foreach (array_slice($userApplications, 0, 3) as $app): ?>
                            <article class="dash-app-tracker">
                                <div class="dash-app-tracker__title">
                                    <a href="application-details.php?id=<?= (int) $app['id'] ?>"><?= htmlspecialchars($app['position_title']) ?></a>
                                </div>
                                <div class="dash-tracker-steps" role="list" aria-label="Application progress">
                                    <?php foreach ($getTrackerSteps($app) as $step): ?>
                                    <div class="dash-tracker-step <?= $step['state'] ?>" role="listitem">
                                        <div class="dash-tracker-step__dot">
                                            <?php if ($step['state'] === 'done'): ?><i class="fas fa-check"></i><?php endif; ?>
                                        </div>
                                        <div class="dash-tracker-step__label"><?= htmlspecialchars($step['label']) ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="dash-timeline" style="margin-top:16px">
                                    <div class="dash-timeline-item">
                                        <div class="dash-timeline-item__dot success"></div>
                                        <div class="dash-timeline-item__title">Applied</div>
                                        <div class="dash-timeline-item__meta"><?= date('M j, Y', strtotime($app['applied_at'])) ?></div>
                                    </div>
                                    <div class="dash-timeline-item">
                                        <div class="dash-timeline-item__dot <?= $app['status'] !== STATUS_PENDING ? 'success' : 'pending' ?>"></div>
                                        <div class="dash-timeline-item__title">Screening</div>
                                        <div class="dash-timeline-item__meta"><?= $app['status'] !== STATUS_PENDING ? 'Completed' : 'In progress' ?></div>
                                    </div>
                                    <div class="dash-timeline-item">
                                        <div class="dash-timeline-item__dot <?= !empty($app['interview_date']) ? 'success' : 'pending' ?>"></div>
                                        <div class="dash-timeline-item__title">Interview</div>
                                        <div class="dash-timeline-item__meta"><?= !empty($app['interview_date']) ? date('M j, Y', strtotime($app['interview_date'])) : 'Pending' ?></div>
                                    </div>
                                    <div class="dash-timeline-item">
                                        <div class="dash-timeline-item__dot <?= in_array($app['status'], [STATUS_APPROVED, STATUS_REJECTED], true) ? ($app['status'] === STATUS_APPROVED ? 'success' : 'pending') : 'pending' ?>"></div>
                                        <div class="dash-timeline-item__title">Decision</div>
                                        <div class="dash-timeline-item__meta"><?= $app['status'] === STATUS_APPROVED ? 'Approved' : ($app['status'] === STATUS_REJECTED ? 'Rejected' : 'Waiting') ?></div>
                                    </div>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <div class="dash-secondary-grid">
                    <!-- Applicant Insights -->
                    <section class="dash-widget" data-widget="insights">
                        <div class="dash-widget__header">
                            <h3 class="dash-widget__title"><i class="fas fa-chart-pie"></i> Applicant Insights</h3>
                            <button type="button" class="dash-widget__toggle" data-widget-toggle aria-label="Collapse section"><i class="fas fa-chevron-up"></i></button>
                        </div>
                        <div class="dash-widget__body">
                            <div class="dash-insights-grid">
                                <div class="dash-insight-item">
                                    <div class="dash-insight-item__value" data-count="<?= $totalApplications ?>">0</div>
                                    <div class="dash-insight-item__label">Applications</div>
                                </div>
                                <div class="dash-insight-item">
                                    <div class="dash-insight-item__value" data-count="<?= $interviewCount ?>">0</div>
                                    <div class="dash-insight-item__label">Interviews</div>
                                </div>
                                <div class="dash-insight-item">
                                    <div class="dash-insight-item__value"><?= $successRate ?>%</div>
                                    <div class="dash-insight-item__label">Success Rate</div>
                                </div>
                                <div class="dash-insight-item">
                                    <div class="dash-insight-item__value"><?= $avgResponseDays !== null ? $avgResponseDays . 'd' : '—' ?></div>
                                    <div class="dash-insight-item__label">Avg Response</div>
                                </div>
                            </div>
                            <div style="margin-top:20px">
                                <div style="font-size:0.75rem;font-weight:700;text-transform:uppercase;color:var(--dash-text-muted);margin-bottom:10px">Achievements</div>
                                <div class="dash-badges">
                                    <?php foreach ($achievements as $badge): ?>
                                    <span class="dash-achievement <?= $badge['earned'] ? 'earned' : '' ?>"><i class="fas <?= $badge['icon'] ?>"></i> <?= htmlspecialchars($badge['label']) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Notifications -->
                    <section class="dash-widget" data-widget="notifications">
                        <div class="dash-widget__header">
                            <h3 class="dash-widget__title"><i class="fas fa-bell"></i> Notification Center</h3>
                            <button type="button" class="dash-widget__toggle" data-widget-toggle aria-label="Collapse section"><i class="fas fa-chevron-up"></i></button>
                        </div>
                        <div class="dash-widget__body">
                            <?php if (empty($notifications)): ?>
                                <p class="dash-empty__text">You're all caught up!</p>
                            <?php else: ?>
                            <div class="dash-notif-list">
                                <?php foreach (array_slice($notifications, 0, 4) as $notif): ?>
                                <div class="dash-notif-item <?= $notif['unread'] ? 'unread' : '' ?>">
                                    <div class="dash-notif-item__icon <?= $notif['type'] ?>"><i class="fas <?= $notif['icon'] ?>"></i></div>
                                    <div>
                                        <div class="dash-notif-item__title"><?= htmlspecialchars($notif['title']) ?></div>
                                        <div class="dash-notif-item__text"><?= htmlspecialchars($notif['text']) ?></div>
                                        <div class="dash-notif-item__time"><?= date('M j, g:i A', strtotime($notif['time'])) ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>

            <!-- Recommended Jobs -->
            <?php if (!empty($recommendedJobs)): ?>
            <section class="dash-widget" data-widget="recommended" style="margin-bottom:24px">
                <div class="dash-widget__header">
                    <h3 class="dash-widget__title"><i class="fas fa-wand-magic-sparkles"></i> Recommended for You</h3>
                    <a href="positions.php" class="dash-link-btn">View All Jobs</a>
                </div>
                <div class="dash-widget__body">
                    <div class="dash-jobs-grid">
                        <?php foreach ($recommendedJobs as $position):
                            $meetsRequirements = $totalExperience >= $position['min_experience_years'];
                        ?>
                        <article class="dash-job-card" data-position-id="<?= (int) $position['id'] ?>">
                            <span class="dash-job-card__match"><?= $position['_match'] ?>% Match</span>
                            <h4 class="dash-job-card__title">
                                <a href="position-details.php?id=<?= (int) $position['id'] ?>"><?= htmlspecialchars($position['title']) ?></a>
                            </h4>
                            <div class="dash-job-card__meta">
                                <?php if (!empty($position['location'])): ?>
                                <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($position['location']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($position['salary_range'])): ?>
                                <span><i class="fas fa-peso-sign"></i> <?= htmlspecialchars($position['salary_range']) ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-clock"></i> Min. <?= $position['min_experience_years'] ?>y</span>
                                <span><i class="fas fa-briefcase"></i> <?= ucfirst($position['employment_type']) ?></span>
                                <?php if (!empty($position['deadline'])): ?>
                                <span><i class="fas fa-calendar"></i> Closes <?= date('M j', strtotime($position['deadline'])) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="dash-job-card__actions">
                                <a href="position-details.php?id=<?= (int) $position['id'] ?>" class="dash-btn dash-btn--outline">View Details</a>
                                <?php if ($meetsRequirements): ?>
                                <a href="apply.php?position=<?= (int) $position['id'] ?>" class="dash-btn dash-btn--primary">Apply</a>
                                <?php endif; ?>
                                <button type="button" class="dash-btn dash-btn--icon" data-bookmark-id="<?= (int) $position['id'] ?>" aria-label="Bookmark job" title="Save job"><i class="fas fa-bookmark"></i></button>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <div class="dash-secondary-grid">
                <!-- Recent Job Openings -->
                <section class="dash-widget" data-widget="jobs">
                    <div class="dash-widget__header">
                        <h3 class="dash-widget__title"><i class="fas fa-briefcase"></i> Recent Job Openings</h3>
                        <a href="positions.php" class="dash-link-btn">View All</a>
                    </div>
                    <div class="dash-widget__body">
                        <?php if (empty($recentJobOpenings)): ?>
                        <div class="dash-empty">
                            <div class="dash-empty__illus"><i class="fas fa-briefcase"></i></div>
                            <h4 class="dash-empty__title">No Available Positions</h4>
                            <p class="dash-empty__text">Check back later for new job openings.</p>
                        </div>
                        <?php else: ?>
                        <div class="dash-jobs-grid">
                            <?php foreach ($recentJobOpenings as $position):
                                $hasApplied = isset($appliedPositionIds[$position['id']]);
                                $meetsRequirements = $totalExperience >= $position['min_experience_years'];
                                $matchPct = $calcJobMatch($position);
                            ?>
                            <article class="dash-job-card" data-position-id="<?= (int) $position['id'] ?>">
                                <span class="dash-job-card__match"><?= $matchPct ?>% Match</span>
                                <h4 class="dash-job-card__title">
                                    <a href="position-details.php?id=<?= (int) $position['id'] ?>"><?= htmlspecialchars($position['title']) ?></a>
                                </h4>
                                <div class="dash-job-card__meta">
                                    <?php if (!empty($position['location'])): ?>
                                    <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($position['location']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($position['salary_range'])): ?>
                                    <span><i class="fas fa-peso-sign"></i> <?= htmlspecialchars($position['salary_range']) ?></span>
                                    <?php endif; ?>
                                    <span><i class="fas fa-clock"></i> Min. <?= $position['min_experience_years'] ?>y</span>
                                    <span><i class="fas fa-briefcase"></i> <?= ucfirst($position['employment_type']) ?></span>
                                </div>
                                <div class="dash-job-card__actions">
                                    <?php if ($hasApplied): ?>
                                        <span class="dash-job-card__badge"><i class="fas fa-check"></i> Already Applied</span>
                                    <?php elseif (!$meetsRequirements): ?>
                                        <span class="dash-job-card__badge" style="background:rgba(245,158,11,0.12);color:var(--dash-warning)"><i class="fas fa-exclamation-triangle"></i> Below required experience</span>
                                    <?php else: ?>
                                        <a href="apply.php?position=<?= (int) $position['id'] ?>" class="dash-btn dash-btn--primary">Apply Now</a>
                                    <?php endif; ?>
                                    <a href="position-details.php?id=<?= (int) $position['id'] ?>" class="dash-btn dash-btn--outline">Details</a>
                                    <button type="button" class="dash-btn dash-btn--icon" data-bookmark-id="<?= (int) $position['id'] ?>" aria-label="Bookmark job"><i class="fas fa-bookmark"></i></button>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Activity & Calendar -->
                <div style="display:flex;flex-direction:column;gap:24px">
                    <section class="dash-widget" data-widget="activity">
                        <div class="dash-widget__header">
                            <h3 class="dash-widget__title"><i class="fas fa-stream"></i> Recent Activity</h3>
                            <button type="button" class="dash-widget__toggle" data-widget-toggle aria-label="Collapse section"><i class="fas fa-chevron-up"></i></button>
                        </div>
                        <div class="dash-widget__body">
                            <?php if (empty($activityFeed)): ?>
                                <p class="dash-empty__text">No recent activity.</p>
                            <?php else: ?>
                                <?php
                                $groupedActivity = [];
                                foreach ($activityFeed as $item) {
                                    $dayKey = date('Y-m-d', strtotime($item['time']));
                                    $label = $dayKey === date('Y-m-d') ? 'Today' : ($dayKey === date('Y-m-d', strtotime('-1 day')) ? 'Yesterday' : date('l, M j', strtotime($item['time'])));
                                    $groupedActivity[$label][] = $item;
                                }
                                foreach ($groupedActivity as $label => $items):
                                ?>
                                <div class="dash-activity-group">
                                    <div class="dash-activity-group__label"><?= htmlspecialchars($label) ?></div>
                                    <?php foreach ($items as $item): ?>
                                    <div class="dash-activity-item">
                                        <div class="dash-activity-item__icon"><i class="fas <?= $item['icon'] ?>"></i></div>
                                        <div><?= $item['text'] ?> · <span style="color:var(--dash-text-muted);font-size:0.8rem"><?= date('g:i A', strtotime($item['time'])) ?></span></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="dash-widget" data-widget="calendar">
                        <div class="dash-widget__header">
                            <h3 class="dash-widget__title"><i class="fas fa-calendar-alt"></i> Calendar</h3>
                            <button type="button" class="dash-widget__toggle" data-widget-toggle aria-label="Collapse section"><i class="fas fa-chevron-up"></i></button>
                        </div>
                        <div class="dash-widget__body">
                            <?php
                            $calMonth = date('n');
                            $calYear = date('Y');
                            $firstDay = mktime(0, 0, 0, $calMonth, 1, $calYear);
                            $daysInMonth = (int) date('t', $firstDay);
                            $startWeekday = (int) date('w', $firstDay);
                            $eventDates = array_column($calendarEvents, 'date');
                            $today = date('Y-m-d');
                            ?>
                            <div style="text-align:center;font-weight:700;margin-bottom:12px"><?= date('F Y') ?></div>
                            <div class="dash-calendar" role="grid" aria-label="Calendar">
                                <?php foreach (['Su','Mo','Tu','We','Th','Fr','Sa'] as $d): ?>
                                <div class="dash-calendar__head"><?= $d ?></div>
                                <?php endforeach; ?>
                                <?php for ($i = 0; $i < $startWeekday; $i++): ?><div></div><?php endfor; ?>
                                <?php for ($day = 1; $day <= $daysInMonth; $day++):
                                    $dateStr = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $day);
                                    $classes = 'dash-calendar__day';
                                    if ($dateStr === $today) $classes .= ' today';
                                    if (in_array($dateStr, $eventDates, true)) $classes .= ' has-event';
                                ?>
                                <div class="<?= $classes ?>"><?= $day ?></div>
                                <?php endfor; ?>
                            </div>
                            <?php if (!empty($calendarEvents)): ?>
                            <div class="dash-calendar-events">
                                <?php foreach (array_slice($calendarEvents, 0, 4) as $ev): ?>
                                <div class="dash-calendar-event">
                                    <span class="dash-calendar-event__date"><?= date('M j', strtotime($ev['date'])) ?></span>
                                    <span><?= htmlspecialchars($ev['title']) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <p class="dash-empty__text" style="margin-top:16px;margin-bottom:0">No upcoming deadlines or interviews.</p>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="dash-widget" data-widget="recent">
                        <div class="dash-widget__header">
                            <h3 class="dash-widget__title"><i class="fas fa-history"></i> Recently Viewed</h3>
                        </div>
                        <div class="dash-widget__body" id="recentlyViewedJobs"></div>
                    </section>
                </div>
            </div>

            </div><!-- /.dash-content-wrap -->
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-nav" aria-label="Mobile navigation">
        <div class="mobile-nav-grid">
            <a href="dashboard.php" class="mobile-nav-item active" aria-current="page">
                <i class="fas fa-th-large"></i>
                <span>Home</span>
            </a>
            <a href="positions.php" class="mobile-nav-item">
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

    <a href="positions.php" class="dash-mobile-fab" aria-label="Browse jobs"><i class="fas fa-search"></i></a>

    <!-- Data Privacy Waiver Modal -->
    <?php if (!$privacyWaiverAccepted): ?>
    <style>
        body.modal-open {
            overflow: hidden;
            pointer-events: none;
        }
        body.modal-open #privacyWaiverModal {
            pointer-events: auto;
        }
        body.modal-open #privacyWaiverModal .modal-overlay {
            pointer-events: auto;
        }
    </style>
    <div id="privacyWaiverModal" class="modal dash-privacy-modal" style="display: block;">
        <div class="modal-overlay">
            <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="privacyWizardTitle">
                <div class="dash-privacy-wizard__progress">
                    <h2 id="privacyWizardTitle" style="font-size:1.1rem;font-weight:800;text-align:center;margin-bottom:16px">Privacy Wizard</h2>
                    <div class="dash-privacy-wizard__steps" aria-label="Wizard progress">
                        <div class="dash-privacy-wizard__step active" data-wizard-step="1">
                            <div class="dash-privacy-wizard__step-icon"><i class="fas fa-book-open"></i></div>
                            <div class="dash-privacy-wizard__step-label">Application Guidelines</div>
                        </div>
                        <div class="dash-privacy-wizard__step" data-wizard-step="2">
                            <div class="dash-privacy-wizard__step-icon"><i class="fas fa-shield-alt"></i></div>
                            <div class="dash-privacy-wizard__step-label">Privacy Policy</div>
                        </div>
                        <div class="dash-privacy-wizard__step" data-wizard-step="3">
                            <div class="dash-privacy-wizard__step-icon"><i class="fas fa-check-circle"></i></div>
                            <div class="dash-privacy-wizard__step-label">Confirm Information</div>
                        </div>
                    </div>
                    <p class="dash-privacy-wizard__read-time"><i class="fas fa-clock"></i> Estimated reading time: ~5 minutes</p>
                </div>
                <div class="modal-body">
                    <!-- Step 1: Application Guidelines Section -->
                    <div id="step1Guidelines" class="modal-step">
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--gray-800); margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-building" style="color: var(--primary-blue);"></i>
                            <?= htmlspecialchars(strtoupper(SITE_NAME), ENT_QUOTES, 'UTF-8') ?>
                        </h3>
                        <div style="line-height: 1.8; color: var(--gray-700); font-size: 0.95rem; margin-bottom: 24px;">
                            <p style="margin-bottom: 16px;">
                                <?= htmlspecialchars(ORG_NAME, ENT_QUOTES, 'UTF-8') ?>, through its Human Resource Management Office, upholds the principles of merit, fitness, and equality of opportunity in all aspects of recruitment and selection. The local government unit strictly adheres to objective, fair, and transparent personnel actions, in full alignment with Civil Service Commission (CSC) guidelines and applicable local government recruitment practices.
                            </p>
                        </div>
                        
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--gray-800); margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
                            APPLICANT REMINDER
                        </h3>
                        <div style="line-height: 1.8; color: var(--gray-700); font-size: 0.95rem; margin-bottom: 24px;">
                            <p style="margin-bottom: 16px;">
                                All applicants are strongly advised to carefully read and comply with the application requirements for the position being applied for. Applicants must ensure that all required information is accurately and completely filled out, and that all supporting documents are properly submitted within the prescribed period. Any missing, incomplete, or inaccurate details and requirements may result in disqualification from consideration for the position. Applicants are enjoined to be guided accordingly to avoid any delays or adverse effects on their application.
                            </p>
                        </div>
                        
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--gray-800); margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-info-circle" style="color: var(--primary-blue);"></i>
                            APPLICATION GUIDELINES
                        </h3>
                        <div style="line-height: 1.8; color: var(--gray-700); font-size: 0.95rem;">
                            <p style="margin-bottom: 16px;">
                                The <strong><?= htmlspecialchars(ORG_NAME, ENT_QUOTES, 'UTF-8') ?></strong> invites interested and qualified applicants for the following vacant positions:
                            </p>
                            
                            <?php if (!empty($activePositions)): ?>
                            <div style="margin-bottom: 16px; padding-left: 20px;">
                                <h4 style="font-size: 1rem; font-weight: 600; color: var(--gray-800); margin-bottom: 12px;">Posted Vacant Positions</h4>
                                <ul style="margin: 0; padding-left: 20px; list-style-type: disc; color: var(--gray-700);">
                                    <?php foreach ($activePositions as $position): ?>
                                        <li style="margin-bottom: 6px; font-size: 0.95rem; font-weight: 600; color: blue;">
                                            <?= htmlspecialchars($position['title']) ?>
                                            
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php else: ?>
                            <div style="margin-bottom: 16px; padding-left: 20px;">
                                <h4 style="font-size: 1rem; font-weight: 600; color: var(--gray-800); margin-bottom: 12px;">Posted Vacant Positions</h4>
                                <p style="color: var(--gray-600); font-style: italic; margin: 0;">No active positions available at this time. Please check back later.</p>
                            </div>
                            <?php endif; ?>
                            
                            <p style="margin-bottom: 16px;">
                                Interested applicants may submit application letter until <strong>______</strong>, addressed to:
                            </p>
                            
                            <div style="margin-bottom: 16px; padding-left: 20px; font-style: italic; color: var(--gray-700);">
                                <p style="margin-bottom: 0;"><?= ORG_APPLICATION_LETTER_RECIPIENT_HTML ?></p>
                            </div>
                            
                            <p style="margin-bottom: 16px;">
                                Please specify the desired position to apply (The term "any vacant position" is hereby discouraged) and applications with incomplete attachments shall not be entertained. Please attach the following documents along with the application letter:
                            </p>
                            
                            <ol style="margin: 0 0 16px 0; padding-left: 20px; color: var(--gray-700);">
                                <li style="margin-bottom: 8px;">
                                    Fully accomplished Personal Data Sheet (PDS) including Work Experience Sheet with recent passport-sized picture (CS Form No. 212, Revised 2025) which can be downloaded at <a href="https://www.csc.gov.ph" target="_blank" style="color: var(--primary-blue); text-decoration: underline;">www.csc.gov.ph</a>;<br>
                                    <small style="display: block; margin-top: 4px; color: var(--gray-600); font-style: italic;">
                                        Note: The PDS form must be properly filled out, signed, and notarized or duly administered by an authorized administering officer.
                                    </small>
                                </li>
                                <li style="margin-bottom: 8px;">Performance rating in the last rating period (if applicable);</li>
                                <li style="margin-bottom: 8px;">Transcript of Records and Diploma;</li>
                                <li style="margin-bottom: 8px;">Photocopy of seminars and trainings attended relevant to the position (if applicable);</li>
                                <li style="margin-bottom: 8px;">Certificate of Employment and/or Service Record (if applicable); and</li>
                                <li style="margin-bottom: 8px;">Photocopy of authenticated certificate of eligibility/rating/license (if applicable).</li>
                            </ol>
                            
                            <div style="margin-top: 20px; padding: 16px; background: var(--gray-50); border-radius: 6px; border-left: 4px solid var(--warning-yellow);">
                                <p style="margin-bottom: 0; color: var(--gray-700); font-size: 0.9rem; line-height: 1.6;">
                                    <strong style="color: var(--gray-800);">Please be advised</strong> that upon submission of this Google Form, the following information associated with your Google account will be automatically collected, collated, and recorded as part of your employment application:
                                </p>
                            </div>
                            
                            <p style="margin-top: 16px; margin-bottom: 0; color: var(--gray-700); font-size: 0.9rem;">
                                Submission of this form constitutes your consent to the collection, recording, and use of the above information solely for employment screening, evaluation, and related administrative purposes. Strictly follow the instructions on every needed information for your application.
                            </p>
                        </div>
                    </div>
                    
                    <!-- Step 2: Data Privacy Waiver Section -->
                    <div id="step2Privacy" class="modal-step" style="display: none;">
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--gray-800); margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-shield-alt" style="color: var(--primary-blue);"></i>
                            DATA PRIVACY WAIVER
                        </h3>
                        <div style="line-height: 1.8; color: var(--gray-700); font-size: 0.95rem;">
                            <p style="margin-bottom: 16px;">
                                In accordance with Republic Act No. 10173, otherwise known as the <strong>Data Privacy Act of 2012</strong>, and its Implementing Rules and Regulations, I hereby authorize <strong><?= htmlspecialchars(ORG_NAME, ENT_QUOTES, 'UTF-8') ?></strong>, through its authorized offices, to collect, process, store, and use my personal information submitted in connection with my application for employment.
                            </p>
                            <p style="margin-bottom: 16px;">
                                I understand and agree that the personal data I provided, including but not limited to my personal details, educational background, employment history, and other supporting documents, shall be used solely for recruitment, evaluation, selection, and other legitimate human resource–related purposes. I further acknowledge that these data may be shared with authorized personnel and relevant government agencies, as required by law or applicable regulations, including the Civil Service Commission.
                            </p>
                            <p style="margin-bottom: 16px;">
                                I affirm that all information I have provided is true, correct, and complete to the best of my knowledge. I understand that any false, misleading, or incomplete information may be grounds for disqualification from the application process or other appropriate administrative action.
                            </p>
                            <p style="margin-bottom: 0;">
                                By submitting my application, I signify my voluntary consent to the processing of my personal data under the terms stated above and recognize my rights as a data subject under the Data Privacy Act of 2012.
                            </p>
                        </div>
                        <div style="margin-top: 24px; padding: 16px; background: var(--gray-50); border-radius: 6px; border-left: 4px solid var(--primary-blue);">
                            <label style="display: flex; align-items: flex-start; cursor: pointer; margin: 0;">
                                <input type="checkbox" id="privacyWaiverCheckbox" style="margin-right: 12px; margin-top: 2px; width: 18px; height: 18px; cursor: pointer;">
                                <span style="color: var(--gray-700); font-size: 0.95rem; line-height: 1.6;">
                                    I have read and understood the Application Guidelines and Data Privacy Waiver above, and I agree to the terms and conditions stated therein.
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Step 3: Employment Opportunity Section -->
                    <div id="step3Employment" class="modal-step" style="display: none;">
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--gray-800); margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-briefcase" style="color: var(--primary-blue);"></i>
                            EMPLOYMENT APPLICATION CONFIRMATION
                        </h3>
                        <div style="line-height: 1.8; color: var(--gray-700); font-size: 0.95rem;">
                            <p style="margin-bottom: 16px;">
                                The <?= htmlspecialchars(ORG_NAME, ENT_QUOTES, 'UTF-8') ?> is committed to public service, good governance, and responsive local employment. Applicants are expected to support the municipality's programs and comply with all applicable laws, policies, and ethical standards in government service.
                            </p>
                            <p style="margin-bottom: 16px;">
                                Please confirm that the contact information below is accurate so the Human Resource Management Office can reach you regarding your application status, interviews, and other recruitment-related updates.
                            </p>
                            
                            <div style="margin-top: 24px; padding: 16px; background: var(--gray-50); border-radius: 6px; border-left: 4px solid var(--primary-blue);">
                                <label for="applicantEmail" style="display: block; font-weight: 600; color: var(--gray-800); margin-bottom: 8px; font-size: 0.95rem;">
                                    <strong>2. Email</strong> <span style="color: var(--danger-red);">*</span>
                                </label>
                                <input 
                                    type="email" 
                                    id="applicantEmail" 
                                    name="email" 
                                    value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                                    required
                                    style="width: 100%; padding: 10px 12px; border: 1px solid var(--gray-300); border-radius: 6px; font-size: 0.95rem; color: var(--gray-800); background: white; transition: border-color 0.2s;"
                                    placeholder="Enter your email address"
                                >
                                <small style="display: block; margin-top: 6px; color: var(--gray-600); font-size: 0.85rem;">
                                    Please provide a valid email address for communication regarding your application.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="backBtn" class="btn btn-outline" style="display: none; min-width: 120px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <div style="display: flex; gap: 12px; margin-left: auto;">
                        <button type="button" id="nextBtn" class="btn btn-primary dash-btn dash-btn--primary" style="min-width: 120px;">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                        <button type="button" id="acceptPrivacyWaiverBtn" class="btn btn-primary dash-btn dash-btn--primary" disabled style="display: none; min-width: 120px;">
                            <i class="fas fa-check"></i> I Agree
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="../../assets/js/toast.js"></script>
    <script src="../../assets/js/office-theme.js"></script>
    <script src="../../assets/js/applicant-theme.js"></script>
    <script src="../../assets/js/applicant-dashboard.js"></script>
    <?php include '../../includes/pwa-scripts.php'; ?>
    <?php if (!$privacyWaiverAccepted): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add modal-open class to body to block interactions
            document.body.classList.add('modal-open');
            
            let currentStep = 1;
            const step1Guidelines = document.getElementById('step1Guidelines');
            const step2Privacy = document.getElementById('step2Privacy');
            const step3Employment = document.getElementById('step3Employment');
            const nextBtn = document.getElementById('nextBtn');
            const backBtn = document.getElementById('backBtn');
            const checkbox = document.getElementById('privacyWaiverCheckbox');
            const acceptBtn = document.getElementById('acceptPrivacyWaiverBtn');
            const applicantEmail = document.getElementById('applicantEmail');
            const modal = document.getElementById('privacyWaiverModal');
            const modalOverlay = modal.querySelector('.modal-overlay');
            const modalContent = modal.querySelector('.modal-content');
            
            // Prevent clicking outside modal to close it
            modalOverlay.addEventListener('click', function(e) {
                // Only allow clicks on the modal content itself
                if (e.target === modalOverlay) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });
            
            // Prevent keyboard shortcuts that might bypass modal
            document.addEventListener('keydown', function(e) {
                // Allow Tab, Enter, Space for form interaction
                if (e.key === 'Tab' || e.key === 'Enter' || e.key === ' ') {
                    return;
                }
                // Block Escape key
                if (e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });
            
            // Function to update step display
            function updateStepDisplay() {
                if (typeof updatePrivacyWizardUI === 'function') {
                    updatePrivacyWizardUI(currentStep);
                }
                if (currentStep === 1) {
                    step1Guidelines.style.display = 'block';
                    step2Privacy.style.display = 'none';
                    step3Employment.style.display = 'none';
                    nextBtn.style.display = 'inline-flex';
                    backBtn.style.display = 'none';
                    acceptBtn.style.display = 'none';
                } else if (currentStep === 2) {
                    step1Guidelines.style.display = 'none';
                    step2Privacy.style.display = 'block';
                    step3Employment.style.display = 'none';
                    nextBtn.style.display = 'inline-flex';
                    backBtn.style.display = 'inline-flex';
                    acceptBtn.style.display = 'none';
                    acceptBtn.innerHTML = '<i class="fas fa-check"></i> I Agree';
                    // Scroll to top of modal content
                    modalContent.scrollTop = 0;
                } else {
                    step1Guidelines.style.display = 'none';
                    step2Privacy.style.display = 'none';
                    step3Employment.style.display = 'block';
                    nextBtn.style.display = 'none';
                    backBtn.style.display = 'inline-flex';
                    acceptBtn.style.display = 'inline-flex';
                    acceptBtn.innerHTML = '<i class="fas fa-check"></i> Submit';
                    // Scroll to top of modal content
                    modalContent.scrollTop = 0;
                    // Validate email on step 3
                    validateStep3();
                }
            }
            
            // Handle Next button click
            nextBtn.addEventListener('click', function() {
                if (currentStep === 1) {
                    currentStep = 2;
                } else if (currentStep === 2) {
                    // Validate checkbox before proceeding to step 3
                    if (!checkbox.checked) {
                        alert('Please check the box to confirm you have read and understood the Application Guidelines and Data Privacy Waiver.');
                        return;
                    }
                    currentStep = 3;
                }
                updateStepDisplay();
            });
            
            // Handle Back button click
            backBtn.addEventListener('click', function() {
                if (currentStep === 3) {
                    currentStep = 2;
                } else if (currentStep === 2) {
                    currentStep = 1;
                }
                updateStepDisplay();
                // Scroll to top of modal content
                modalContent.scrollTop = 0;
            });
            
            // Enable/disable accept button based on checkbox and email validation
            function validateStep3() {
                if (currentStep === 3) {
                    const emailValue = applicantEmail.value.trim();
                    const emailValid = emailValue && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue);
                    acceptBtn.disabled = !emailValid;
                } else {
                    acceptBtn.disabled = !checkbox.checked;
                }
            }
            
            checkbox.addEventListener('change', function() {
                if (currentStep === 2) {
                    validateStep3();
                }
            });
            
            applicantEmail.addEventListener('input', function() {
                if (currentStep === 3) {
                    validateStep3();
                }
            });
            
            // Handle accept button click
            acceptBtn.addEventListener('click', function() {
                // Validate based on current step
                if (currentStep === 2) {
                    if (!checkbox.checked) {
                        return;
                    }
                } else if (currentStep === 3) {
                    const emailValue = applicantEmail.value.trim();
                    if (!emailValue || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue)) {
                        alert('Please enter a valid email address.');
                        applicantEmail.focus();
                        return;
                    }
                    if (!checkbox.checked) {
                        alert('Please check the box to confirm you have read and understood the Application Guidelines and Data Privacy Waiver.');
                        return;
                    }
                }
                
                // Disable button during request
                acceptBtn.disabled = true;
                acceptBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                
                // Create form data
                const formData = new FormData();
                formData.append('csrf_token', '<?= generateCSRFToken() ?>');
                
                // Add email if on step 3
                if (currentStep === 3) {
                    formData.append('email', applicantEmail.value.trim());
                }
                
                // Send request
                fetch('<?= BASE_URL ?>applicant/actions/accept_privacy_waiver.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove modal-open class
                        document.body.classList.remove('modal-open');
                        
                        // Hide modal
                        modal.style.display = 'none';
                        
                        // Show success message if toast is available
                        if (typeof showToast === 'function') {
                            showToast('Data Privacy Waiver accepted successfully', 'success');
                        }
                        
                        // Reload page to refresh user data
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } else {
                        acceptBtn.disabled = false;
                        acceptBtn.innerHTML = '<i class="fas fa-check"></i> I Agree';
                        alert(data.message || 'An error occurred. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    acceptBtn.disabled = false;
                    acceptBtn.innerHTML = '<i class="fas fa-check"></i> I Agree';
                    alert('An error occurred. Please try again.');
                });
            });
            updateStepDisplay();
        });
    </script>
    <?php endif; ?>
</body>
</html>
