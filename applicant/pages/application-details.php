<?php
require_once '../../config/config.php';
requireAuth();

if (hasRole(ROLE_ADMIN)) {
    header('Location: ' . BASE_URL . 'admin/pages/applications.php');
    exit();
}

// Require privacy waiver acceptance
requirePrivacyWaiver();

require_once '../../includes/models.php';

$applicationModel = new Application();
$userId = $_SESSION['user_id'];
$appId = $_GET['id'] ?? 0;

$applications = $applicationModel->getApplicationsWithDetails('a.id = ? AND a.user_id = ?', [$appId, $userId]);

if (empty($applications)) {
    header('Location: my-applications.php');
    exit();
}

$application = $applications[0];
$userModel = new User();
$user = $userModel->findById($userId);

$statusConfig = [
    STATUS_PENDING => [
        'label' => 'Pending Review',
        'class' => 'pending',
        'icon' => 'fas fa-clock',
        'description' => 'Your application is currently being reviewed by the recruitment team.'
    ],
    STATUS_APPROVED => [
        'label' => 'Approved',
        'class' => 'approved',
        'icon' => 'fas fa-check-circle',
        'description' => 'Congratulations! The recruitment team approved your application.'
    ],
    STATUS_REJECTED => [
        'label' => 'Rejected',
        'class' => 'rejected',
        'icon' => 'fas fa-times-circle',
        'description' => 'The recruitment team decided not to move forward with this application.'
    ],
];

$statusInfo = $statusConfig[$application['status']] ?? $statusConfig[STATUS_PENDING];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Application Details - WPU Applicant System</title>
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/office-theme.css" rel="stylesheet">
    <?php include '../../includes/pwa-header.php'; ?>
    <style>
        .application-summary {
            border-left: 5px solid var(--primary-blue);
        }

        .application-summary.status-pending {
            border-left-color: var(--warning);
        }

        .application-summary.status-approved {
            border-left-color: var(--success);
        }

        .application-summary.status-rejected {
            border-left-color: var(--danger);
        }

        .application-summary-header {
            display: flex;
            gap: 24px;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .summary-label {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--gray-500);
            letter-spacing: 0.06em;
            margin-bottom: 6px;
        }

        .summary-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 12px;
        }

        .summary-meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            font-size: 0.9rem;
            color: var(--gray-500);
        }

        .summary-meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.85rem;
            background: rgba(30, 64, 175, 0.12);
            color: var(--primary-blue);
        }

        .status-pill.pending {
            background: rgba(245, 158, 11, 0.14);
            color: var(--warning);
        }

        .status-pill.approved {
            background: rgba(16, 185, 129, 0.14);
            color: var(--success);
        }

        .status-pill.rejected {
            background: rgba(239, 68, 68, 0.14);
            color: var(--danger);
        }

        .summary-description {
            margin-top: 16px;
            font-size: 0.95rem;
            color: var(--gray-600);
        }

        .summary-alert {
            margin-top: 16px;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.9rem;
            display: flex;
            gap: 10px;
            align-items: flex-start;
            background: rgba(245, 158, 11, 0.12);
            color: var(--warning);
        }

        .details-grid {
            display: grid;
            gap: 24px;
        }

        .details-main,
        .details-sidebar {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        @media (min-width: 992px) {
            .details-grid {
                grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr);
                align-items: start;
            }
        }

        .rich-text {
            color: var(--gray-700);
            font-size: 0.95rem;
            line-height: 1.7;
            white-space: pre-wrap;
        }

        .info-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            gap: 16px;
        }

        .info-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray-500);
        }

        .info-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--gray-800);
            text-align: right;
        }

        .info-item + .info-item {
            padding-top: 16px;
            border-top: 1px solid var(--gray-200);
        }

        .callout {
            border-radius: 12px;
            padding: 16px 18px;
            font-size: 0.9rem;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        .callout.info {
            background: rgba(59, 130, 246, 0.12);
            color: var(--info);
        }

        .callout.success {
            background: rgba(16, 185, 129, 0.12);
            color: var(--success);
        }

        .callout.danger {
            background: rgba(239, 68, 68, 0.12);
            color: var(--danger);
        }

        .timeline {
            list-style: none;
            padding: 0;
            margin: 0;
            position: relative;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 18px;
            width: 2px;
            background: var(--gray-200);
        }

        .timeline-item {
            position: relative;
            padding-left: 48px;
            padding-bottom: 24px;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-marker {
            position: absolute;
            left: 10px;
            top: 4px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--white);
            border: 3px solid var(--primary-blue);
            box-shadow: var(--shadow-sm);
        }

        .timeline-content {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .timeline-time {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--gray-500);
            letter-spacing: 0.05em;
        }

        .timeline-text {
            font-size: 0.95rem;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .timeline-text i {
            font-size: 1.05rem;
        }

        .btn-outline.btn-sm {
            padding: 6px 12px;
        }

        @media (max-width: 768px) {
            .summary-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body data-theme="system" data-application-id="<?= $appId ?>" data-status="<?= htmlspecialchars($application['status']) ?>">
<?php include '../../includes/applicant-theme-init.php'; ?>
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">
                <img src="../../assets/logo.png" alt="WPU Logo" style="height: 32px; width: auto;">
                <span>WPU Jobs</span>
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="sidebar-nav-item">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="positions.php" class="sidebar-nav-item">
                <i class="fas fa-briefcase"></i>
                <span>Browse Jobs</span>
            </a>
            <a href="my-applications.php" class="sidebar-nav-item active">
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
                    <?= strtoupper(substr($user['full_name'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?= htmlspecialchars($user['full_name'] ?? '') ?></div>
                    <div class="sidebar-user-role">Applicant</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-wrapper">
        <div class="topbar">
            <button class="mobile-menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="topbar-title">Application Details</h1>
            <div class="topbar-actions">
                <?php include '../../includes/applicant-theme-toggle.php'; ?>
                <a href="my-applications.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <a href="../../auth/logout.php" class="topbar-btn confirm-logout" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <div class="content">
            <div class="card application-summary status-<?= $statusInfo['class'] ?>">
                <div class="card-body">
                    <div class="application-summary-header">
                        <div>
                            <p class="summary-label">Position Applied</p>
                            <h2 class="summary-title"><?= htmlspecialchars($application['position_title']) ?></h2>
                            <div class="summary-meta">
                                <span><i class="fas fa-calendar"></i> Applied <?= date('F j, Y', strtotime($application['applied_at'])) ?></span>
                                <span><i class="fas fa-rotate-right"></i> Updated <?= date('F j, Y - g:i A', strtotime($application['updated_at'])) ?></span>
                            </div>
                        </div>
                        <div>
                            <span class="status-pill <?= $statusInfo['class'] ?>">
                                <i class="<?= $statusInfo['icon'] ?>"></i> <?= $statusInfo['label'] ?>
                            </span>
                        </div>
                    </div>
                    <p class="summary-description"><?= $statusInfo['description'] ?></p>
                    <?php if (!$application['meets_requirements']): ?>
                        <div class="summary-alert">
                            <i class="fas fa-exclamation-triangle"></i>
                            You currently fall short of the required experience for this role.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="details-grid">
                <div class="details-main">
                    <?php if ($application['cover_letter']): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-file-alt"></i> Cover Letter
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="rich-text">
                                    <?= nl2br(htmlspecialchars($application['cover_letter'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($application['admin_notes']): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-comment-dots"></i> Admin Feedback
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="rich-text">
                                    <?= nl2br(htmlspecialchars($application['admin_notes'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-history"></i> Application Timeline
                            </h3>
                        </div>
                        <div class="card-body">
                            <ul class="timeline">
                                <li class="timeline-item">
                                    <div class="timeline-marker"></div>
                                    <div class="timeline-content">
                                        <div class="timeline-time"><?= date('M j, Y - g:i A', strtotime($application['applied_at'])) ?></div>
                                        <div class="timeline-text">
                                            <i class="fas fa-paper-plane" style="color: var(--primary-blue);"></i>
                                            Application submitted
                                        </div>
                                    </div>
                                </li>

                                <?php if ($application['status'] !== STATUS_PENDING): ?>
                                    <?php if (!empty($application['reviewed_at'])): ?>
                                        <li class="timeline-item">
                                            <div class="timeline-marker"></div>
                                            <div class="timeline-content">
                                                <div class="timeline-time"><?= date('M j, Y - g:i A', strtotime($application['reviewed_at'])) ?></div>
                                                <div class="timeline-text">
                                                    <?php if ($application['status'] === STATUS_APPROVED): ?>
                                                        <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                                        Application approved
                                                    <?php elseif ($application['status'] === STATUS_REJECTED): ?>
                                                        <i class="fas fa-times-circle" style="color: var(--danger);"></i>
                                                        Application rejected
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </li>
                                    <?php else: ?>
                                        <li class="timeline-item">
                                            <div class="timeline-marker"></div>
                                            <div class="timeline-content">
                                                <div class="timeline-time"><?= date('M j, Y - g:i A', strtotime($application['updated_at'])) ?></div>
                                                <div class="timeline-text">
                                                    <?php if ($application['status'] === STATUS_APPROVED): ?>
                                                        <i class="fas fa-check-circle" style="color: var(--success);"></i>
                                                        Application approved
                                                    <?php elseif ($application['status'] === STATUS_REJECTED): ?>
                                                        <i class="fas fa-times-circle" style="color: var(--danger);"></i>
                                                        Application rejected
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endif; ?>

                                    <?php if ($application['admin_notes']): ?>
                                        <li class="timeline-item">
                                            <div class="timeline-marker"></div>
                                            <div class="timeline-content">
                                                <div class="timeline-time"><?= date('M j, Y - g:i A', strtotime($application['updated_at'])) ?></div>
                                                <div class="timeline-text">
                                                    <i class="fas fa-comment" style="color: var(--info);"></i>
                                                    Admin added feedback
                                                </div>
                                            </div>
                                        </li>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <li class="timeline-item">
                                        <div class="timeline-marker"></div>
                                        <div class="timeline-content">
                                            <div class="timeline-time"><?= date('M j, Y - g:i A', strtotime($application['updated_at'])) ?></div>
                                            <div class="timeline-text">
                                                <i class="fas fa-clock" style="color: var(--warning);"></i>
                                                Application under review
                                            </div>
                                        </div>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="details-sidebar">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-info-circle"></i> Application Info
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="info-list">
                                <div class="info-item">
                                    <span class="info-label">Your Experience</span>
                                    <span class="info-value"><?= number_format((float)$application['total_experience_years'], 1) ?> years</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Required Experience</span>
                                    <span class="info-value"><?= number_format((float)$application['min_experience_years'], 1) ?> years</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Requirements Met</span>
                                    <span class="info-value">
                                        <?php if ($application['meets_requirements']): ?>
                                            <span class="badge badge-success"><i class="fas fa-check"></i> Yes</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning"><i class="fas fa-times"></i> No</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if ($application['resume_file']): ?>
                                    <div class="info-item">
                                        <span class="info-label">Resume</span>
                                        <span class="info-value">
                                            <a class="btn btn-outline btn-sm" target="_blank" href="<?= BASE_URL ?>applicant/pages/uploads/resumes/<?= htmlspecialchars($application['resume_file']) ?>">
                                                <i class="fas fa-file-pdf"></i> View Resume
                                            </a>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($application['status'] === STATUS_PENDING): ?>
                        <div class="callout info">
                            <i class="fas fa-clock"></i>
                            We will let you know once a decision is made. Keep an eye on your email.
                        </div>
                    <?php elseif ($application['status'] === STATUS_APPROVED): ?>
                        <div class="callout success">
                            <i class="fas fa-check-circle"></i>
                            Our team will reach out with the next steps shortly. Congratulations!
                        </div>
                    <?php elseif ($application['status'] === STATUS_REJECTED): ?>
                        <div class="callout danger">
                            <i class="fas fa-times-circle"></i>
                            Thank you for applying. Feel free to browse other open positions.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Navigation -->
    <nav class="mobile-nav">
        <div class="mobile-nav-grid">
            <a href="dashboard.php" class="mobile-nav-item">
                <i class="fas fa-th-large"></i>
                <span>Home</span>
            </a>
            <a href="positions.php" class="mobile-nav-item">
                <i class="fas fa-briefcase"></i>
                <span>Jobs</span>
            </a>
            <a href="my-applications.php" class="mobile-nav-item active">
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
