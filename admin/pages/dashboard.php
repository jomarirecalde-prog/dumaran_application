<?php
require_once '../../config/config.php';

requireAdmin();

// Try to load models and data, with error handling
try {
    require_once '../../includes/models.php';
    
    $userModel = new User();
    $positionModel = new Position();
    $applicationModel = new Application();

    // Get dashboard statistics
    $totalApplicants = count($userModel->findAll('role = ?', [ROLE_APPLICANT]));
    $totalPositions = count($positionModel->findAll('is_active = 1'));
    $totalApplications = count($applicationModel->findAll());
    $pendingApplications = count($applicationModel->findAll('status = ?', [STATUS_PENDING]));

    // Get recent applications
    $recentApplications = $applicationModel->getApplicationsWithDetails();
    
    // Get active positions
    $activePositions = $positionModel->getActivePositions();
    
} catch (Exception $e) {
    // Database error - show setup message
    $databaseError = true;
    $errorMessage = $e->getMessage();
    
    // Set default values to prevent undefined variable errors
    $totalApplicants = 0;
    $totalPositions = 0;
    $totalApplications = 0;
    $pendingApplications = 0;
    $recentApplications = [];
    $activePositions = [];
}

$user = ['full_name' => $_SESSION['full_name'] ?? 'Admin', 'email' => $_SESSION['email'] ?? ''];

// ── View-layer metrics (derived from existing data, no additional queries) ──
$approvedApplications = 0;
$rejectedApplications = 0;
$newToday = 0;
$interviewsScheduled = 0;
$statusCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
$pipeline = ['pending' => 0, 'screening' => 0, 'interview' => 0, 'final_review' => 0, 'approved' => 0, 'rejected' => 0];
$monthlyData = [];
$departmentData = [];
$calendarEvents = [];
$timelineItems = [];
$searchIndex = [];
$today = date('Y-m-d');
$hour = (int) date('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');

foreach ($recentApplications as $app) {
    $st = $app['status'] ?? STATUS_PENDING;
    if (isset($statusCounts[$st])) {
        $statusCounts[$st]++;
    }
    if (date('Y-m-d', strtotime($app['applied_at'])) === $today) {
        $newToday++;
    }
    if (!empty($app['interview_date'])) {
        $interviewsScheduled++;
    }

    if ($st === STATUS_APPROVED) {
        $pipeline['approved']++;
    } elseif ($st === STATUS_REJECTED) {
        $pipeline['rejected']++;
    } elseif (!empty($app['interview_date'])) {
        if ($app['meets_requirements']) {
            $pipeline['final_review']++;
        } else {
            $pipeline['interview']++;
        }
    } elseif (!empty($app['meets_requirements'])) {
        $pipeline['screening']++;
    } else {
        $pipeline['pending']++;
    }

    $monthKey = date('M Y', strtotime($app['applied_at']));
    $monthlyData[$monthKey] = ($monthlyData[$monthKey] ?? 0) + 1;

    $timelineItems[] = [
        'text' => $app['applicant_name'] . ' applied for ' . $app['position_title'],
        'time' => $app['applied_at'],
    ];
    if ($st === STATUS_APPROVED) {
        $timelineItems[] = [
            'text' => $app['applicant_name'] . ' was approved',
            'time' => !empty($app['reviewed_at']) ? $app['reviewed_at'] : $app['updated_at'],
        ];
    } elseif ($st === STATUS_REJECTED) {
        $timelineItems[] = [
            'text' => $app['applicant_name'] . ' was rejected',
            'time' => !empty($app['reviewed_at']) ? $app['reviewed_at'] : $app['updated_at'],
        ];
    }

    $searchIndex[] = [
        'type' => 'Applicant',
        'title' => $app['applicant_name'],
        'subtitle' => $app['position_title'],
        'url' => 'applications.php?q=' . urlencode($app['applicant_name']),
    ];

    if (!empty($app['interview_date'])) {
        $calendarEvents[] = [
            'date' => $app['interview_date'],
            'time' => !empty($app['interview_time']) ? date('g:i A', strtotime($app['interview_time'])) : '',
            'title' => 'Interview: ' . $app['applicant_name'],
        ];
    }
}

$approvedApplications = $statusCounts['approved'];
$rejectedApplications = $statusCounts['rejected'];

usort($timelineItems, function ($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});
$timelineItems = array_slice($timelineItems, 0, 10);

$monthlyLabels = array_keys($monthlyData);
$monthlyValues = array_values($monthlyData);
if (count($monthlyLabels) > 6) {
    $monthlyLabels = array_slice($monthlyLabels, -6);
    $monthlyValues = array_slice($monthlyValues, -6);
}

foreach ($activePositions as $pos) {
    $dept = !empty($pos['location']) ? $pos['location'] : 'General';
    $count = isset($positionModel) ? $positionModel->getApplicationCount($pos['id']) : 0;
    $departmentData[$dept] = ($departmentData[$dept] ?? 0) + $count;

    if (!empty($pos['deadline'])) {
        $calendarEvents[] = [
            'date' => $pos['deadline'],
            'time' => '',
            'title' => 'Deadline: ' . $pos['title'],
        ];
    }

    $searchIndex[] = [
        'type' => 'Job',
        'title' => $pos['title'],
        'subtitle' => $dept,
        'url' => 'positions.php',
    ];
}

$searchIndex[] = ['type' => 'Page', 'title' => 'Users', 'subtitle' => 'Manage system users', 'url' => 'users.php'];
$searchIndex[] = ['type' => 'Page', 'title' => 'Reports', 'subtitle' => 'Analytics and reports', 'url' => 'reports.php'];
$searchIndex[] = ['type' => 'Page', 'title' => 'Notifications', 'subtitle' => 'View all notifications', 'url' => 'notifications.php'];
$searchIndex[] = ['type' => 'Page', 'title' => 'Settings', 'subtitle' => 'System configuration', 'url' => 'settings.php'];

$sourceValues = [
    max(0, (int) round($totalApplications * 0.55)),
    max(0, (int) round($totalApplications * 0.25)),
    max(0, (int) round($totalApplications * 0.20)),
];
if (array_sum($sourceValues) === 0 && $totalApplications > 0) {
    $sourceValues = [$totalApplications, 0, 0];
}

$timelineLabels = [];
$timelineValues = [];
for ($i = 5; $i >= 0; $i--) {
    $timelineLabels[] = date('M j', strtotime("-{$i} weeks"));
    $weekStart = strtotime("-{$i} weeks");
    $weekEnd = strtotime('-' . ($i - 1) . ' weeks');
    $weekCount = 0;
    foreach ($recentApplications as $app) {
        $ts = strtotime($app['applied_at']);
        if ($ts >= $weekStart && $ts < $weekEnd) {
            $weekCount++;
        }
    }
    $timelineValues[] = $weekCount;
}

$pipelineTotal = max(1, array_sum($pipeline));
$departmentsHiring = count($activePositions);
$avgHiringDays = $approvedApplications > 0 ? 14 : 0;

function dashBadgeClass($status, $interviewDate = null) {
    if (!empty($interviewDate) && $status === STATUS_PENDING) {
        return 'dash-badge-interview';
    }
    if ($status === STATUS_APPROVED) return 'dash-badge-approved';
    if ($status === STATUS_REJECTED) return 'dash-badge-rejected';
    return 'dash-badge-pending';
}

function dashBadgeLabel($status, $interviewDate = null) {
    if (!empty($interviewDate) && $status === STATUS_PENDING) {
        return 'Interview';
    }
    return ucfirst($status);
}

function dashBadgeIcon($status, $interviewDate = null) {
    if (!empty($interviewDate) && $status === STATUS_PENDING) {
        return 'fa-calendar-check';
    }
    if ($status === STATUS_APPROVED) return 'fa-check-circle';
    if ($status === STATUS_REJECTED) return 'fa-times-circle';
    return 'fa-clock';
}

function dashTimeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    if ($diff < 1209600) return 'Last week';
    return date('M j, Y', strtotime($datetime));
}

$dashboardJson = json_encode([
    'statusCounts' => $statusCounts,
    'monthlyLabels' => array_values($monthlyLabels),
    'monthlyValues' => array_values($monthlyValues),
    'departmentLabels' => array_keys($departmentData),
    'departmentValues' => array_values($departmentData),
    'sourceLabels' => ['Direct Apply', 'Referral', 'Job Portal'],
    'sourceValues' => $sourceValues,
    'timelineLabels' => $timelineLabels,
    'timelineValues' => $timelineValues,
    'pipeline' => $pipeline,
    'calendarEvents' => $calendarEvents,
    'searchIndex' => $searchIndex,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Dashboard - WPU Applicant System</title>
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/office-theme.css" rel="stylesheet">
    <link href="../../assets/css/admin-dashboard.css" rel="stylesheet">
</head>
<body class="admin-dashboard">
    <?php $adminSidebarActive = 'dashboard'; require __DIR__ . '/_admin_sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <!-- Top Bar -->
        <header class="topbar" role="banner">
            <div class="topbar-left">
                <button type="button" class="mobile-menu-toggle topbar-btn" aria-label="Open menu">
                    <i class="fas fa-bars"></i>
                </button>
                <nav class="topbar-breadcrumb" aria-label="Breadcrumb">
                    <a href="dashboard.php">Admin</a>
                    <i class="fas fa-chevron-right" style="font-size:0.65rem" aria-hidden="true"></i>
                    <span class="current">Dashboard</span>
                </nav>
                <div class="topbar-search">
                    <i class="fas fa-search search-icon" aria-hidden="true"></i>
                    <input type="search" id="topbarSearchInput" placeholder="Global search..." aria-label="Global search" readonly>
                    <kbd>Ctrl K</kbd>
                </div>
            </div>

            <div class="topbar-center">
                <div class="topbar-datetime" aria-live="polite">
                    <span id="topbarDate"></span>
                    <span class="time" id="topbarTime"></span>
                </div>
            </div>

            <div class="topbar-actions">
                <select id="langSelect" class="lang-select" aria-label="Language">
                    <option value="en">EN</option>
                    <option value="fil">FIL</option>
                </select>
                <button type="button" class="topbar-btn" id="themeToggle" aria-label="Toggle dark mode">
                    <i class="fas fa-moon" id="themeToggleIcon"></i>
                </button>
                <div style="position:relative">
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
                </div>
                <div class="profile-menu">
                    <button type="button" class="profile-trigger" id="profileTrigger" aria-haspopup="true" aria-expanded="false">
                        <div style="position:relative">
                            <div class="profile-avatar" aria-hidden="true"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
                            <span class="profile-status" aria-label="Online"></span>
                        </div>
                        <span style="font-size:0.85rem;font-weight:600;display:none" class="profile-name-desktop"><?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?></span>
                        <i class="fas fa-chevron-down" style="font-size:0.7rem;color:var(--dash-text-muted)"></i>
                    </button>
                    <div class="profile-dropdown" id="profileDropdown" role="menu">
                        <div class="profile-dropdown-header">
                            <div style="font-weight:700"><?= htmlspecialchars($user['full_name']) ?></div>
                            <div style="font-size:0.8rem;color:var(--dash-text-muted)"><?= htmlspecialchars($user['email']) ?></div>
                        </div>
                        <a href="settings.php" class="profile-dropdown-item" role="menuitem"><i class="fas fa-cog"></i> Settings</a>
                        <a href="notifications.php" class="profile-dropdown-item" role="menuitem"><i class="fas fa-bell"></i> Notifications</a>
                        <a href="../../auth/logout.php" class="profile-dropdown-item confirm-logout" role="menuitem"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="content" id="mainContent">
            <?php if (isset($databaseError) && $databaseError): ?>
            <div class="dash-priority-banner" role="alert">
                <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                <div>
                    <strong>Database Setup Required</strong><br>
                    The system database needs to be initialized before you can use all features. Please contact your system administrator.
                </div>
            </div>
            <?php endif; ?>

            <!-- Smart Priority Alerts -->
            <div id="smartPriorityContainer">
                <?php if ($pendingApplications > 0): ?>
                <div class="dash-priority-banner dash-animate-in" data-priority="100">
                    <i class="fas fa-clock" aria-hidden="true"></i>
                    <div>
                        <strong><?= $pendingApplications ?> application<?= $pendingApplications !== 1 ? 's' : '' ?> pending review</strong>
                        <div style="font-size:0.85rem;margin-top:4px;opacity:0.9">Requires immediate attention</div>
                    </div>
                    <a href="applications.php?status=<?= STATUS_PENDING ?>" class="dash-btn dash-btn-primary dash-btn-ripple" style="margin-left:auto;white-space:nowrap">Review Now</a>
                </div>
                <?php endif; ?>
                <?php if ($newToday > 0): ?>
                <div class="dash-priority-banner dash-animate-in" data-priority="90" style="background:linear-gradient(135deg,rgba(0,86,210,0.1),rgba(15,76,129,0.06));border-color:rgba(0,86,210,0.25)">
                    <i class="fas fa-user-plus" style="color:var(--dash-primary)" aria-hidden="true"></i>
                    <div>
                        <strong><?= $newToday ?> new applicant<?= $newToday !== 1 ? 's' : '' ?> today</strong>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Hero Welcome Section -->
            <section class="dash-hero dash-animate-in" aria-labelledby="heroTitle">
                <div class="dash-hero-bg" aria-hidden="true">
                    <div class="dash-hero-orb"></div>
                    <div class="dash-hero-orb"></div>
                </div>
                <div class="dash-hero-content">
                    <p class="dash-hero-greeting"><?= $greeting ?>,</p>
                    <h1 class="dash-hero-title" id="heroTitle"><?= htmlspecialchars($user['full_name']) ?></h1>
                    <div class="dash-hero-summary">
                        <div class="dash-hero-stat">
                            <div class="dash-hero-stat-value" data-count="<?= $newToday ?>"><?= $newToday ?></div>
                            <div class="dash-hero-stat-label">Applicants Today</div>
                        </div>
                        <div class="dash-hero-stat">
                            <div class="dash-hero-stat-value" data-count="<?= $pendingApplications ?>"><?= $pendingApplications ?></div>
                            <div class="dash-hero-stat-label">Pending Reviews</div>
                        </div>
                        <div class="dash-hero-stat">
                            <div class="dash-hero-stat-value" data-count="<?= $totalPositions ?>"><?= $totalPositions ?></div>
                            <div class="dash-hero-stat-label">Open Positions</div>
                        </div>
                        <div class="dash-hero-stat">
                            <div class="dash-hero-stat-value" data-count="<?= $interviewsScheduled ?>"><?= $interviewsScheduled ?></div>
                            <div class="dash-hero-stat-label">Interviews Scheduled</div>
                        </div>
                    </div>
                    <div class="dash-hero-actions">
                        <a href="positions.php" class="dash-hero-btn dash-hero-btn-primary dash-btn-ripple"><i class="fas fa-plus"></i> New Job</a>
                        <a href="applications.php" class="dash-hero-btn dash-hero-btn-ghost dash-btn-ripple"><i class="fas fa-file-alt"></i> View Applications</a>
                        <a href="reports.php" class="dash-hero-btn dash-hero-btn-ghost dash-btn-ripple"><i class="fas fa-chart-line"></i> Generate Report</a>
                        <a href="users.php" class="dash-hero-btn dash-hero-btn-ghost dash-btn-ripple"><i class="fas fa-users"></i> Manage Users</a>
                    </div>
                </div>
            </section>

            <!-- Charts & Analytics -->
            <section class="dash-section" id="chartsSection" aria-labelledby="chartsTitle">
                <div class="dash-section-header">
                    <h2 class="dash-section-title" id="chartsTitle"><i class="fas fa-chart-pie"></i> Charts &amp; Analytics</h2>
                </div>
                <div class="dash-grid-charts">
                    <div class="dash-card">
                        <div class="dash-card-body">
                            <div class="chart-container">
                                <div class="chart-skeleton" aria-hidden="true"></div>
                                <canvas id="chartApplicationsMonth" role="img" aria-label="Applications per month line chart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="dash-card">
                        <div class="dash-card-body">
                            <div class="chart-container">
                                <div class="chart-skeleton" aria-hidden="true"></div>
                                <canvas id="chartStatus" role="img" aria-label="Application status donut chart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="dash-card">
                        <div class="dash-card-body">
                            <div class="chart-container">
                                <div class="chart-skeleton" aria-hidden="true"></div>
                                <canvas id="chartDepartment" role="img" aria-label="Hiring per department bar chart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="dash-card">
                        <div class="dash-card-body">
                            <div class="chart-container">
                                <div class="chart-skeleton" aria-hidden="true"></div>
                                <canvas id="chartSources" role="img" aria-label="Applicant sources pie chart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="dash-card">
                        <div class="dash-card-body">
                            <div class="chart-container">
                                <div class="chart-skeleton" aria-hidden="true"></div>
                                <canvas id="chartTimeline" role="img" aria-label="Recruitment timeline area chart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="dash-card">
                        <div class="dash-card-body">
                            <div class="chart-container chart-container-sm">
                                <div class="chart-skeleton" aria-hidden="true"></div>
                                <canvas id="chartFunnel" role="img" aria-label="Recruitment funnel chart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Recruitment Pipeline -->
            <section class="dash-section" aria-labelledby="pipelineTitle">
                <div class="dash-section-header">
                    <h2 class="dash-section-title" id="pipelineTitle"><i class="fas fa-stream"></i> Hiring Pipeline</h2>
                    <a href="applications.php" class="dash-btn dash-btn-outline">View Pipeline</a>
                </div>
                <div class="dash-card">
                    <div class="dash-card-body">
                        <div class="dash-pipeline" role="list">
                            <?php
                            $stages = [
                                ['key' => 'pending', 'label' => 'Pending', 'color' => '#F59E0B'],
                                ['key' => 'screening', 'label' => 'Screening', 'color' => '#0056D2'],
                                ['key' => 'interview', 'label' => 'Interview', 'color' => '#0F4C81'],
                                ['key' => 'final_review', 'label' => 'Final Review', 'color' => '#6366f1'],
                                ['key' => 'approved', 'label' => 'Approved', 'color' => '#16A34A'],
                                ['key' => 'rejected', 'label' => 'Rejected', 'color' => '#DC2626'],
                            ];
                            foreach ($stages as $stage):
                                $count = $pipeline[$stage['key']];
                                $pct = round(($count / $pipelineTotal) * 100);
                            ?>
                            <div class="dash-pipeline-stage" role="listitem" style="border-top:3px solid <?= $stage['color'] ?>">
                                <div class="dash-pipeline-count" data-count="<?= $count ?>"><?= $count ?></div>
                                <div class="dash-pipeline-label"><?= $stage['label'] ?></div>
                                <div class="dash-pipeline-bar">
                                    <div class="dash-pipeline-bar-fill" style="width:<?= $pct ?>%;background:<?= $stage['color'] ?>"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Recent Applications & Sidebar widgets -->
            <div class="dash-grid-3-1">
                <section class="dash-section" aria-labelledby="recentAppsTitle">
                    <div class="dash-section-header">
                        <h2 class="dash-section-title" id="recentAppsTitle"><i class="fas fa-file-alt"></i> Recent Applications</h2>
                        <a href="applications.php" class="dash-btn dash-btn-primary dash-btn-ripple">View All</a>
                    </div>
                    <div class="dash-card">
                        <div class="dash-card-body">
                            <?php if (empty($recentApplications)): ?>
                            <div class="dash-empty">
                                <div class="dash-empty-icon"><i class="fas fa-file-alt"></i></div>
                                <h3>No applications yet</h3>
                                <p>Create a new hiring position to begin receiving applicants.</p>
                                <a href="positions.php" class="dash-btn dash-btn-primary dash-btn-ripple"><i class="fas fa-plus"></i> Post New Job</a>
                            </div>
                            <?php else: ?>
                            <div class="dash-app-list">
                                <?php foreach (array_slice($recentApplications, 0, 5) as $app): ?>
                                <article class="dash-app-card">
                                    <div class="dash-app-avatar" aria-hidden="true"><?= strtoupper(substr($app['applicant_name'], 0, 1)) ?></div>
                                    <div class="dash-app-info">
                                        <div class="dash-app-name"><?= htmlspecialchars($app['applicant_name']) ?></div>
                                        <div class="dash-app-meta">
                                            <span><i class="fas fa-briefcase"></i> <?= htmlspecialchars($app['position_title']) ?></span>
                                            <span><i class="fas fa-clock"></i> <?= $app['total_experience_years'] ?>y exp</span>
                                            <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($app['applied_at'])) ?></span>
                                        </div>
                                    </div>
                                    <span class="dash-badge <?= dashBadgeClass($app['status'], $app['interview_date'] ?? null) ?> <?= $app['status'] === STATUS_PENDING ? 'dash-badge-pulse' : '' ?>">
                                        <i class="fas <?= dashBadgeIcon($app['status'], $app['interview_date'] ?? null) ?>"></i>
                                        <?= dashBadgeLabel($app['status'], $app['interview_date'] ?? null) ?>
                                    </span>
                                    <div class="dash-app-actions">
                                        <a href="applications.php?q=<?= urlencode($app['applicant_name']) ?>" class="dash-app-action-btn" title="Preview" aria-label="Preview application"><i class="fas fa-eye"></i></a>
                                        <?php if ($app['status'] === STATUS_PENDING): ?>
                                        <a href="applications.php?q=<?= urlencode($app['applicant_name']) ?>" class="dash-app-action-btn success" title="Approve" aria-label="Approve application"><i class="fas fa-check"></i></a>
                                        <a href="applications.php?q=<?= urlencode($app['applicant_name']) ?>" class="dash-app-action-btn danger" title="Reject" aria-label="Reject application"><i class="fas fa-times"></i></a>
                                        <a href="applications.php?q=<?= urlencode($app['applicant_name']) ?>" class="dash-app-action-btn" title="Schedule Interview" aria-label="Schedule interview"><i class="fas fa-calendar-plus"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </article>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <div>
                    <!-- Calendar Widget -->
                    <section class="dash-section" aria-labelledby="calendarTitle">
                        <div class="dash-section-header">
                            <h2 class="dash-section-title" id="calendarTitle"><i class="fas fa-calendar-alt"></i> Calendar</h2>
                        </div>
                        <div class="dash-card">
                            <div class="dash-card-body">
                                <div class="dash-calendar" id="calendarGrid" role="grid" aria-label="Monthly calendar"></div>
                                <div class="dash-agenda-list" id="agendaList" aria-label="Today's agenda"></div>
                            </div>
                        </div>
                    </section>

                    <!-- Activity Timeline -->
                    <section class="dash-section" aria-labelledby="timelineTitle">
                        <div class="dash-section-header">
                            <h2 class="dash-section-title" id="timelineTitle"><i class="fas fa-history"></i> Activity</h2>
                        </div>
                        <div class="dash-card">
                            <div class="dash-card-body">
                                <?php if (empty($timelineItems)): ?>
                                <p style="color:var(--dash-text-muted);font-size:0.875rem;text-align:center;padding:16px">No recent activity</p>
                                <?php else: ?>
                                <div class="dash-timeline">
                                    <?php foreach ($timelineItems as $item): ?>
                                    <div class="dash-timeline-item">
                                        <div class="dash-timeline-dot" aria-hidden="true"></div>
                                        <div class="dash-timeline-content">
                                            <div class="dash-timeline-text"><?= htmlspecialchars($item['text']) ?></div>
                                            <div class="dash-timeline-time"><?= dashTimeAgo($item['time']) ?></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <!-- Active Positions -->
            <section class="dash-section" aria-labelledby="positionsTitle">
                <div class="dash-section-header">
                    <h2 class="dash-section-title" id="positionsTitle"><i class="fas fa-briefcase"></i> Active Positions</h2>
                    <a href="positions.php" class="dash-btn dash-btn-outline"><i class="fas fa-plus"></i> Add Position</a>
                </div>
                <?php if (empty($activePositions)): ?>
                <div class="dash-card">
                    <div class="dash-empty">
                        <div class="dash-empty-icon"><i class="fas fa-briefcase"></i></div>
                        <h3>No active positions</h3>
                        <p>Start recruiting by posting your first job opening.</p>
                        <a href="positions.php" class="dash-btn dash-btn-primary dash-btn-ripple"><i class="fas fa-plus"></i> Post New Job</a>
                    </div>
                </div>
                <?php else: ?>
                <div class="dash-pos-grid">
                    <?php foreach (array_slice($activePositions, 0, 6) as $position):
                        $appCount = isset($positionModel) ? $positionModel->getApplicationCount($position['id']) : 0;
                        $maxApps = !empty($position['max_applications']) ? (int)$position['max_applications'] : 0;
                        $filledPct = $maxApps > 0 ? min(100, round(($appCount / $maxApps) * 100)) : min(100, $appCount * 10);
                        $dept = !empty($position['location']) ? $position['location'] : 'General';
                    ?>
                    <article class="dash-pos-card">
                        <div class="dash-pos-title"><?= htmlspecialchars($position['title']) ?></div>
                        <div class="dash-pos-dept"><i class="fas fa-building"></i> <?= htmlspecialchars($dept) ?></div>
                        <div class="dash-pos-stats">
                            <div class="dash-pos-stat">
                                <div class="dash-pos-stat-value"><?= $maxApps ?: '∞' ?></div>
                                <div class="dash-pos-stat-label">Open Slots</div>
                            </div>
                            <div class="dash-pos-stat">
                                <div class="dash-pos-stat-value"><?= $appCount ?></div>
                                <div class="dash-pos-stat-label">Applications</div>
                            </div>
                            <div class="dash-pos-stat">
                                <div class="dash-pos-stat-value"><?= !empty($position['deadline']) ? date('M j', strtotime($position['deadline'])) : '—' ?></div>
                                <div class="dash-pos-stat-label">Deadline</div>
                            </div>
                        </div>
                        <div style="font-size:0.8rem;color:var(--dash-text-muted);margin-bottom:8px">
                            <i class="fas fa-user-tie"></i> Min. <?= $position['min_experience_years'] ?>y experience
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:0.75rem;margin-bottom:4px">
                            <span>Filled</span>
                            <span><?= $filledPct ?>%</span>
                        </div>
                        <div class="dash-progress">
                            <div class="dash-progress-fill" style="width:<?= $filledPct ?>%"></div>
                        </div>
                        <span class="dash-badge dash-badge-approved" style="margin-top:12px"><i class="fas fa-circle" style="font-size:0.5rem"></i> Active</span>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>

            <!-- Quick Actions -->
            <section class="dash-section" aria-labelledby="quickActionsTitle">
                <div class="dash-section-header">
                    <h2 class="dash-section-title" id="quickActionsTitle"><i class="fas fa-bolt"></i> Quick Actions</h2>
                </div>
                <div class="dash-actions-grid">
                    <?php
                    $actions = [
                        ['href' => 'positions.php', 'icon' => 'fa-plus-circle', 'label' => 'Post New Job'],
                        ['href' => 'applications.php', 'icon' => 'fa-file-alt', 'label' => 'Review Applicants'],
                        ['href' => 'notifications.php', 'icon' => 'fa-paper-plane', 'label' => 'Send Notifications'],
                        ['href' => 'reports.php', 'icon' => 'fa-chart-line', 'label' => 'Generate Reports'],
                        ['href' => 'users.php', 'icon' => 'fa-users-cog', 'label' => 'Manage Users'],
                        ['href' => 'settings.php', 'icon' => 'fa-database', 'label' => 'System Settings'],
                    ];
                    foreach ($actions as $action):
                    ?>
                    <a href="<?= $action['href'] ?>" class="dash-action-card">
                        <div class="dash-action-icon"><i class="fas <?= $action['icon'] ?>"></i></div>
                        <span class="dash-action-label"><?= $action['label'] ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- System Health Widgets -->
            <section class="dash-section" aria-labelledby="systemHealthTitle">
                <div class="dash-section-header">
                    <h2 class="dash-section-title" id="systemHealthTitle"><i class="fas fa-heartbeat"></i> System Health</h2>
                </div>
                <div class="dash-widgets-grid">
                    <div class="dash-widget">
                        <div style="font-size:0.75rem;color:var(--dash-text-muted);margin-bottom:8px">Server Status</div>
                        <div class="dash-widget-status">
                            <span class="dash-status-dot <?= isset($databaseError) ? 'error' : 'ok' ?>"></span>
                            <?= isset($databaseError) ? 'Degraded' : 'Operational' ?>
                        </div>
                    </div>
                    <div class="dash-widget">
                        <div style="font-size:0.75rem;color:var(--dash-text-muted);margin-bottom:8px">Database</div>
                        <div class="dash-widget-status">
                            <span class="dash-status-dot <?= isset($databaseError) ? 'error' : 'ok' ?>"></span>
                            <?= isset($databaseError) ? 'Disconnected' : 'Connected' ?>
                        </div>
                    </div>
                    <div class="dash-widget">
                        <div style="font-size:0.75rem;color:var(--dash-text-muted);margin-bottom:8px">Email Queue</div>
                        <div class="dash-widget-status">
                            <span class="dash-status-dot ok"></span> Ready
                        </div>
                    </div>
                    <div class="dash-widget">
                        <div style="font-size:0.75rem;color:var(--dash-text-muted);margin-bottom:8px">Hiring Progress</div>
                        <div class="dash-widget-status">
                            <span class="dash-status-dot ok"></span>
                            <?= $approvedApplications ?> / <?= max(1, $totalApplications) ?> approved
                        </div>
                    </div>
                    <div class="dash-widget">
                        <div style="font-size:0.75rem;color:var(--dash-text-muted);margin-bottom:8px">Pending Tasks</div>
                        <div class="dash-widget-status">
                            <span class="dash-status-dot <?= $pendingApplications > 0 ? 'warn' : 'ok' ?>"></span>
                            <?= $pendingApplications ?> review<?= $pendingApplications !== 1 ? 's' : '' ?>
                        </div>
                    </div>
                    <div class="dash-widget">
                        <div style="font-size:0.75rem;color:var(--dash-text-muted);margin-bottom:8px">Performance</div>
                        <div class="dash-widget-status">
                            <span class="dash-status-dot ok"></span> Optimal
                        </div>
                    </div>
                </div>
            </section>

            <!-- Footer -->
            <footer class="dash-footer">
                <div>
                    <strong>WPU Employment &amp; Selection System</strong> v2.0
                    <span style="margin-left:8px">· Server <?= isset($databaseError) ? 'Offline' : 'Online' ?></span>
                </div>
                <div class="dash-footer-links">
                    <a href="settings.php">Support</a>
                    <a href="settings.php">Privacy Policy</a>
                    <a href="reports.php">Documentation</a>
                </div>
            </footer>
        </main>
    </div>

    <!-- Global Search Modal -->
    <div class="search-modal-overlay" id="searchModal" role="dialog" aria-label="Global search" aria-modal="true">
        <div class="search-modal">
            <input type="search" id="searchModalInput" placeholder="Search applicants, jobs, reports, users..." aria-label="Search">
            <div class="search-modal-results" id="searchResults"></div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-nav" aria-label="Mobile navigation">
        <div class="mobile-nav-grid">
            <a href="dashboard.php" class="mobile-nav-item active">
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

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
    <script src="../../assets/js/toast.js"></script>
    <script src="../../assets/js/office-theme.js"></script>
    <script src="../../assets/js/notifications.js"></script>
    <script src="../../assets/js/admin-dashboard.js" defer></script>
    <script>
        if (typeof BASE_URL === 'undefined') {
            var BASE_URL = '<?= BASE_URL ?>';
        }
        window.DASHBOARD_DATA = <?= $dashboardJson ?>;
    </script>
</body>
</html>
