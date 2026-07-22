<?php
require_once '../../config/config.php';
require_once '../../includes/models.php';

requireAdmin();

$applicationModel = new Application();
$userModel = new User();
$positionModel = new Position();
$documentModel = new ApplicationDocument();

// Handle restore application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_application'])) {
    // Validate form submission (CSRF + Form Token)
    $validation = FormTokenManager::validateFormSubmission($_POST);
    
    if (!$validation['valid']) {
        if ($validation['error'] === 'duplicate') {
            $_SESSION['info_message'] = 'Application already restored.';
        } else {
            $_SESSION['error_message'] = $validation['error'];
        }
        header('Location: application_history.php');
        exit();
    }
    
    $applicationId = (int)$_POST['application_id'];
    
    $application = $applicationModel->findById($applicationId);
    
    if (!$application) {
        $_SESSION['error_message'] = 'Application not found.';
        header('Location: application_history.php');
        exit();
    }
    
    // Restore the application
    $restored = $applicationModel->unarchive($applicationId);
    
    if ($restored) {
        // Log activity
        $userModel->logActivity(
            $_SESSION['user_id'],
            'restore_application',
            "Restored application #{$applicationId} for user {$application['user_id']}"
        );
        
        $_SESSION['success_message'] = 'Application has been restored to active applications.';
    } else {
        $_SESSION['error_message'] = 'Failed to restore application. Please try again.';
    }
    
    header('Location: application_history.php');
    exit();
}

// Get all archived applications with details
$applications = $applicationModel->getArchivedApplicationsWithDetails();

// Get filter parameters
$filterStatus = $_GET['status'] ?? 'all';
$filterPosition = $_GET['position'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');

// Apply filters
if ($filterStatus !== 'all') {
    $applications = array_filter($applications, function($app) use ($filterStatus) {
        return $app['status'] === $filterStatus;
    });
}

if ($filterPosition !== 'all') {
    $applications = array_filter($applications, function($app) use ($filterPosition) {
        return $app['position_id'] == $filterPosition;
    });
}

if ($searchQuery !== '') {
    $applications = array_filter($applications, function($app) use ($searchQuery) {
        $haystacks = [
            $app['applicant_name'] ?? '',
            $app['applicant_email'] ?? '',
            $app['position_title'] ?? '',
            $app['cover_letter'] ?? ''
        ];
        foreach ($haystacks as $haystack) {
            if (stripos($haystack, $searchQuery) !== false) {
                return true;
            }
        }
        return false;
    });
}

// Pagination setup
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 5;
$offset = ($page - 1) * $perPage;

// Calculate pagination
$totalApplications_filtered = count($applications);
$totalPages = ceil($totalApplications_filtered / $perPage);
$applications = array_slice($applications, $offset, $perPage);

// Get all positions for filter dropdown
$positions = $positionModel->findAll('is_active = 1');

if (!function_exists('formatFileSize')) {
    function formatFileSize($bytes, $precision = 1)
    {
        if (!is_numeric($bytes) || $bytes <= 0) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = (int) floor(log($bytes, 1024));
        $pow = max(0, min($pow, count($units) - 1));
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

$documentTypeIcons = [
    'resume' => 'fa-id-card',
    'cover_letter' => 'fa-file-lines',
    'certificate' => 'fa-certificate',
    'portfolio' => 'fa-folder-open',
    'other' => 'fa-file'
];

$documentExtensionIcons = [
    'pdf' => 'fa-file-pdf',
    'doc' => 'fa-file-word',
    'docx' => 'fa-file-word',
    'txt' => 'fa-file-lines',
    'jpg' => 'fa-file-image',
    'jpeg' => 'fa-file-image',
    'png' => 'fa-file-image',
    'gif' => 'fa-file-image',
    'zip' => 'fa-file-archive',
    'ppt' => 'fa-file-powerpoint',
    'pptx' => 'fa-file-powerpoint',
    'xls' => 'fa-file-excel',
    'xlsx' => 'fa-file-excel'
];?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Application History - WPU Applicant System</title>
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/office-theme.css" rel="stylesheet">
    <link href="../../assets/css/admin-dashboard.css" rel="stylesheet">
    <style>
        .application-item {
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            background: #fff;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
            opacity: 0.95;
        }

        body.modal-open {
            overflow: hidden;
        }

        .application-item:hover {
            box-shadow: 0 14px 30px -18px rgba(20, 57, 99, 0.35);
            transform: translateY(-2px);
            opacity: 1;
        }

        .application-item:last-child {
            margin-bottom: 0;
        }

        .application-header {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
        }

        .application-meta {
            flex: 1 1 320px;
        }

        .application-name {
            margin: 0;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .application-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 6px 0 12px;
            font-size: 0.85rem;
            color: var(--gray-600);
        }

        .application-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .application-summary {
            font-size: 0.92rem;
            line-height: 1.6;
            color: var(--gray-700);
            max-width: 60ch;
        }

        .documents-section {
            margin-top: 14px;
            padding: 14px;
            border-radius: 8px;
            background: var(--gray-50);
        }

        .documents-section.collapsed {
            display: none;
        }

        .documents-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .document-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .document-badges .badge {
            text-decoration: none;
            padding: 6px 10px;
            font-size: 0.8rem;
        }
        .documents-section.empty {
            background: var(--gray-100);
            border: 1px dashed var(--gray-200);
        }

        .documents-title {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .documents-count {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--gray-500);
        }

        .document-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .document-item {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid var(--gray-200);
        }

        .document-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 200px;
        }

        .document-meta i {
            font-size: 1.2rem;
            color: var(--primary-blue);
        }

        .document-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--gray-800);
        }

        .document-ext {
            font-weight: 500;
            color: var(--gray-500);
            margin-left: 4px;
        }

        .document-extra {
            font-size: 0.8rem;
            color: var(--gray-500);
        }

        .document-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .document-actions .btn {
            min-width: 110px;
        }

        .documents-empty-message {
            margin: 0;
            font-size: 0.85rem;
            color: var(--gray-600);
        }

        .badge-primary {
            background: var(--primary-blue);
            color: #fff;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .badge-primary:hover {
            background: var(--secondary-blue);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(33, 150, 243, 0.3);
        }

        .status-badge {
            flex: 0 0 auto;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 18px;
        }

        .quick-actions .btn {
            min-width: 140px;
        }

        .filter-section {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            width: 100%;
        }

        .filter-select {
            flex: 0 0 auto;
            min-width: 180px;
            width: auto;
        }

        .filter-search {
            flex: 1 1 260px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid var(--gray-300);
            background: #fff;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .filter-section .btn {
            flex: 0 0 auto;
        }

        .filter-section .form-select {
            width: auto;
        }

        .filter-search input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 0.95rem;
            background: transparent;
            color: var(--gray-800);
        }

        .filter-search:focus-within {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.12);
        }

        .filter-search i {
            color: var(--gray-500);
        }

        .applications-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 24px;
            border-bottom: 1px solid var(--gray-200);
        }

        .applications-toolbar h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--gray-800);
        }

        .applications-count {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: var(--gray-600);
        }

        .empty-state {
            padding: 48px 24px;
            text-align: center;
            color: var(--gray-600);
        }

        .empty-state i {
            display: block;
            font-size: 2.5rem;
            margin-bottom: 16px;
            color: var(--gray-300);
        }

        .empty-state h3 {
            margin: 0 0 8px;
            color: var(--gray-800);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            inset: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 0;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 14px 30px -18px rgba(20, 57, 99, 0.35);
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }

        .close {
            color: var(--gray-500);
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            background: none;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }

        .close:hover {
            color: var(--gray-800);
            background: var(--gray-100);
        }

        .modal-body {
            padding: 24px;
            max-height: calc(100vh - 250px);
            overflow-y: auto;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        .form-control,
        .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            font-size: 0.95rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
        }

        .btn[disabled] {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
        }

        .archived-badge {
            background: var(--gray-500);
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 20px auto;
            }

            .modal-body {
                padding: 18px;
                max-height: calc(100vh - 180px);
            }

            .application-item {
                padding: 16px;
            }

            .applications-toolbar {
                padding: 16px;
            }

            .filter-section {
                gap: 8px;
                flex-wrap: nowrap;
                align-items: center;
                overflow-x: auto;
                padding-bottom: 4px;
            }

            .filter-section .filter-search,
            .filter-section .form-select,
            .filter-section .btn {
                flex: 0 0 auto;
                width: auto;
            }

            .filter-section .filter-search {
                flex: 1 0 240px;
                min-width: 200px;
            }
        }
    </style>
</head>
<body class="admin-dashboard">
    <?php $adminSidebarActive = 'application_history'; require __DIR__ . '/_admin_sidebar.php'; ?>

<!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <!-- Top Bar -->
        <div class="topbar">
            <button class="mobile-menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="topbar-title">Application History</h1>
            <div class="topbar-actions">
                <a href="applications.php" class="topbar-btn" title="Active Applications">
                    <i class="fas fa-file-alt"></i>
                </a>
                <a href="settings.php" class="topbar-btn">
                    <i class="fas fa-cog"></i>
                </a>
                <a href="../../auth/logout.php" class="topbar-btn confirm-logout" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content">
            <?php if (isset($_SESSION['success_message'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showSuccess('<?= addslashes($_SESSION['success_message']) ?>', 5000, 'Success');
                });
            </script>
            <?php 
                unset($_SESSION['success_message']); 
            ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showError('<?= addslashes($_SESSION['error_message']) ?>', 5000, 'Error');
                });
            </script>
            <?php 
                unset($_SESSION['error_message']); 
            ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['info_message'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showInfo('<?= addslashes($_SESSION['info_message']) ?>', 5000, 'Info');
                });
            </script>
            <?php 
                unset($_SESSION['info_message']); 
            ?>
            <?php endif; ?>

            <!-- Info Alert -->
            <div class="card" style="margin-bottom: 24px; border-left: 4px solid var(--primary-blue);">
                <div class="card-body">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-info-circle" style="color: var(--primary-blue); font-size: 1.5rem;"></i>
                        <div>
                            <h3 style="margin: 0 0 4px 0; font-size: 1rem; color: var(--gray-800);">Application History</h3>
                            <p style="margin: 0; color: var(--gray-600); font-size: 0.9rem;">
                                This page shows all archived applications that have been removed from the active applications list. 
                                You can restore applications back to the active list if needed.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="filter-section">
                            <label class="filter-search" for="searchQuery">
                                <i class="fas fa-search"></i>
                                <input
                                    id="searchQuery"
                                    type="search"
                                    name="q"
                                    value="<?= htmlspecialchars($searchQuery) ?>"
                                    placeholder="Search by name, email, position or keywords..."
                                >
                            </label>
                            <select name="status" class="form-select filter-select" onchange="this.form.submit()">
                                <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Status</option>
                                <option value="<?= STATUS_PENDING ?>" <?= $filterStatus === STATUS_PENDING ? 'selected' : '' ?>>Pending</option>
                                <option value="<?= STATUS_APPROVED ?>" <?= $filterStatus === STATUS_APPROVED ? 'selected' : '' ?>>Approved</option>
                                <option value="<?= STATUS_REJECTED ?>" <?= $filterStatus === STATUS_REJECTED ? 'selected' : '' ?>>Rejected</option>
                            </select>
                            <select name="position" class="form-select filter-select" onchange="this.form.submit()">
                                <option value="all" <?= $filterPosition === 'all' ? 'selected' : '' ?>>All Positions</option>
                                <?php foreach ($positions as $position): ?>
                                    <option value="<?= $position['id'] ?>" <?= $filterPosition == $position['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($position['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply
                            </button>
                            <?php if ($filterStatus !== 'all' || $filterPosition !== 'all' || $searchQuery !== ''): ?>
                                <a href="application_history.php" class="btn btn-outline">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Applications List -->
            <div class="card">
                <div class="applications-toolbar">
                    <h3 class="card-title">
                        <i class="fas fa-history"></i>
                        Archived Applications
                    </h3>
                    <div class="applications-count">
                        <span class="badge badge-primary"><?= $totalApplications_filtered ?> result<?= $totalApplications_filtered === 1 ? '' : 's' ?></span>
                        <span>(<?= count($applications) ?> showing)</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($applications)): ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h3>No Archived Applications</h3>
                            <p>There are no archived applications in the history.</p>
                            <a href="applications.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i> Back to Applications
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($applications as $app): ?>
                            <?php 
                            $documents = $documentModel->getApplicationDocuments($app['id']);
                            $documentEntries = [];

                            if (!empty($app['resume_file'])) {
                                $resumePath = __DIR__ . '/../../applicant/pages/uploads/resumes/' . $app['resume_file'];
                                $resumeExt = strtolower(pathinfo($app['resume_file'], PATHINFO_EXTENSION));
                                $resumeSize = file_exists($resumePath) ? formatFileSize(filesize($resumePath)) : null;

                                $documentEntries[] = [
                                    'label' => 'Primary Resume',
                                    'filename' => basename($app['resume_file']),
                                    'type' => 'resume',
                                    'extension' => $resumeExt,
                                    'view_url' => 'view_resume.php?id=' . $app['id'],
                                    'download_url' => 'view_resume.php?id=' . $app['id'] . '&download=1',
                                    'size' => $resumeSize
                                ];
                            }

                            foreach ($documents as $doc) {
                                $docExt = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                                $docSize = !empty($doc['file_size']) ? formatFileSize((int) $doc['file_size']) : null;

                                if (!$docSize) {
                                    $docPath = __DIR__ . '/../../applicant/pages/uploads/documents/' . $doc['file_path'];

                                    if (file_exists($docPath)) {
                                        $docSize = formatFileSize(filesize($docPath));
                                    }
                                }

                                $documentEntries[] = [
                                    'label' => ucfirst($doc['document_type']),
                                    'filename' => $doc['file_name'],
                                    'type' => $doc['document_type'],
                                    'extension' => $docExt,
                                    'view_url' => 'view_document.php?id=' . $doc['id'],
                                    'download_url' => 'view_document.php?id=' . $doc['id'] . '&download=1',
                                    'size' => $docSize
                                ];
                            }

                            $totalDocuments = count($documentEntries);
                            $hasDocuments = $totalDocuments > 0;
                            ?>
                            <div class="application-item" data-app-id="<?= $app['id'] ?>" data-application-id="<?= $app['id'] ?>" data-status="<?= htmlspecialchars($app['status']) ?>">
                                <div class="application-header">
                                    <div class="application-meta">
                                        <h4 class="application-name">
                                            <?= htmlspecialchars($app['applicant_name']) ?>
                                            <span class="archived-badge">ARCHIVED</span>
                                        </h4>
                                        <div class="application-tags">
                                            <span class="application-tag">
                                                <i class="fas fa-envelope"></i>
                                                <?= htmlspecialchars($app['applicant_email']) ?>
                                            </span>
                                            <span class="application-tag">
                                                <i class="fas fa-briefcase"></i>
                                                <?= htmlspecialchars($app['position_title']) ?>
                                            </span>
                                            <span class="application-tag">
                                                <i class="fas fa-clock"></i>
                                                <?= $app['total_experience_years'] ?>y exp
                                            </span>
                                            <span class="application-tag">
                                                <i class="fas fa-calendar"></i>
                                                Applied: <?= date('M j, Y', strtotime($app['applied_at'])) ?>
                                            </span>
                                            <?php if (!empty($app['archived_at'])): ?>
                                            <span class="application-tag">
                                                <i class="fas fa-archive"></i>
                                                Archived: <?= date('M j, Y', strtotime($app['archived_at'])) ?>
                                            </span>
                                            <?php endif; ?>
                                            <span class="application-tag" style="color: <?= $hasDocuments ? 'var(--success-green)' : 'var(--danger-red)' ?>;">
                                                <i class="fas fa-<?= $hasDocuments ? 'check-circle' : 'times-circle' ?>"></i>
                                                <?= $totalDocuments ?> File<?= $totalDocuments === 1 ? '' : 's' ?>
                                            </span>
                                        </div>
                                        <div class="application-summary">
                                            <?= htmlspecialchars(substr($app['cover_letter'], 0, 160)) ?><?= strlen($app['cover_letter']) > 160 ? '...' : '' ?>
                                        </div>
                                    </div>

                                    <div style="flex: 0 0 auto; display: flex; align-items: center;">
                                        <span class="badge badge-<?= $app['status'] === STATUS_PENDING ? 'warning' : ($app['status'] === STATUS_APPROVED ? 'success' : 'danger') ?>">
                                            <?= ucfirst($app['status']) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="quick-actions">
                                    <button class="btn btn-sm btn-outline" type="button" onclick='viewCoverLetter(<?= json_encode($app) ?>)'>
                                        <i class="fas fa-eye"></i> Cover Letter
                                    </button>
                                    <?php if (!empty($app['resume_file'])): ?>
                                        <a href="view_resume.php?id=<?= $app['id'] ?>" target="_blank" class="btn btn-sm btn-outline" style="text-decoration: none;">
                                            <i class="fas fa-file-pdf"></i> Primary Resume
                                        </a>
                                    <?php endif; ?>
                                    <button 
                                        class="btn btn-sm btn-outline" 
                                        type="button" 
                                        data-action="toggle-details" 
                                        data-target="application-details-<?= $app['id'] ?>" 
                                        aria-expanded="false"
                                    >
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                    <a href="mailto:<?= htmlspecialchars($app['applicant_email']) ?>" class="btn btn-sm btn-outline" style="text-decoration: none;">
                                        <i class="fas fa-comments"></i> Email Applicant
                                    </a>
                                    <form method="POST" action="application_history.php" id="restore-form-<?= $app['id'] ?>" style="display: none;">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <?php FormTokenManager::renderFormTokenInput('restore_application_' . (int) $app['id']); ?>
                                        <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                        <input type="hidden" name="restore_application" value="1">
                                    </form>
                                    <button 
                                        type="button" 
                                        class="btn btn-sm btn-primary"
                                        onclick="showRestoreConfirmation(<?= $app['id'] ?>, '<?= htmlspecialchars(addslashes($app['applicant_name'])) ?>', '<?= htmlspecialchars(addslashes($app['position_title'])) ?>')"
                                    >
                                        <i class="fas fa-undo"></i> Restore
                                    </button>
                                </div>

                                <div 
                                    id="application-details-<?= $app['id'] ?>" 
                                    class="application-details-panel" 
                                    style="display: none; margin-top: 16px;"
                                >
                                    <div class="documents-section <?= $hasDocuments ? '' : 'empty' ?>">
                                        <div class="documents-header">
                                            <div class="documents-title">
                                                <i class="fas fa-paperclip"></i>
                                                Applicant Files
                                            </div>
                                            <span class="documents-count">
                                                <?= $hasDocuments ? ($totalDocuments === 1 ? '1 file' : $totalDocuments . ' files') : 'No files uploaded' ?>
                                            </span>
                                        </div>
                                        <?php if ($hasDocuments): ?>
                                            <div class="document-list">
                                                <?php foreach ($documentEntries as $entry): 
                                                    $extension = $entry['extension'] ?? '';
                                                    $extensionLabel = $extension ? strtoupper($extension) : '';
                                                    $icon = $documentExtensionIcons[$extension] ?? ($documentTypeIcons[$entry['type']] ?? 'fa-file');
                                                    $sizeLabel = $entry['size'] ?? null;
                                                ?>
                                                <div class="document-item">
                                                    <div class="document-meta">
                                                        <i class="fas <?= $icon ?>"></i>
                                                        <div>
                                                            <div class="document-name">
                                                                <?= htmlspecialchars($entry['label']) ?>
                                                                <?php if ($extensionLabel): ?>
                                                                    <span class="document-ext">(.<?= htmlspecialchars($extensionLabel) ?>)</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="document-extra">
                                                                <?= htmlspecialchars($entry['filename']) ?>
                                                                <?php if ($sizeLabel): ?>
                                                                    &bull; <?= htmlspecialchars($sizeLabel) ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="document-actions">
                                                        <a href="<?= htmlspecialchars($entry['view_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
                                                        <a href="<?= htmlspecialchars($entry['download_url']) ?>" class="btn btn-sm btn-primary" download="<?= htmlspecialchars($entry['filename']) ?>">
                                                            <i class="fas fa-download"></i> Download
                                                        </a>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="documents-empty-message">
                                                Applicant did not upload any supporting files.
                                            </p>
                                        <?php endif; ?>
                                    </div>

                                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 12px;">
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-outline" 
                                            data-action="close-details" 
                                            data-target="application-details-<?= $app['id'] ?>"
                                        >
                                            Close
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="card-footer" style="display: flex; justify-content: center; align-items: center; padding: 16px; border-top: 1px solid var(--gray-200);">
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($filterStatus) ?>&position=<?= urlencode($filterPosition) ?>&q=<?= urlencode($searchQuery) ?>" class="pagination-btn">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <div class="pagination-info">
                            Page <?= $page ?> of <?= $totalPages ?> (<?= $totalApplications_filtered ?> total)
                        </div>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($filterStatus) ?>&position=<?= urlencode($filterPosition) ?>&q=<?= urlencode($searchQuery) ?>" class="pagination-btn">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
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
            <a href="application_history.php" class="mobile-nav-item active">
                <i class="fas fa-history"></i>
                <span>History</span>
            </a>
        </div>
    </nav>

    <!-- Cover Letter Modal -->
    <div class="modal" id="coverLetterModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="coverLetterTitle">Cover Letter</h3>
                <button class="close" onclick="closeCoverLetterModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Applicant</label>
                    <p style="font-weight: 600; color: var(--gray-800);" id="coverLetterApplicant"></p>
                </div>
                <div class="form-group">
                    <label class="form-label">Position</label>
                    <p style="font-weight: 600; color: var(--gray-800);" id="coverLetterPosition"></p>
                </div>
                <div class="form-group">
                    <label class="form-label">Cover Letter</label>
                    <div style="padding: 16px; background: var(--gray-50); border-radius: 6px; line-height: 1.6;" id="coverLetterText"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeCoverLetterModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div class="modal" id="restoreConfirmModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header" style="border-bottom: 2px solid var(--primary-blue); background: linear-gradient(135deg, rgba(33, 150, 243, 0.1) 0%, rgba(33, 150, 243, 0.05) 100%);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--primary-blue); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.5rem;">
                        <i class="fas fa-undo"></i>
                    </div>
                    <h3 class="modal-title" style="margin: 0; color: var(--gray-800);">Confirm Restore</h3>
                </div>
                <button class="close" onclick="closeRestoreConfirmModal()">&times;</button>
            </div>
            <div class="modal-body" style="padding: 24px;">
                <div style="margin-bottom: 20px;">
                    <p style="font-size: 1rem; color: var(--gray-700); margin: 0 0 16px 0; line-height: 1.6;">
                        Are you sure you want to restore this application? It will be moved back to <strong>Active Applications</strong>.
                    </p>
                </div>
                <div style="background: var(--gray-50); border-left: 4px solid var(--primary-blue); padding: 16px; border-radius: 6px; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                        <i class="fas fa-user" style="color: var(--gray-600);"></i>
                        <strong style="color: var(--gray-800);">Applicant:</strong>
                        <span style="color: var(--gray-700);" id="restoreConfirmApplicant"></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-briefcase" style="color: var(--gray-600);"></i>
                        <strong style="color: var(--gray-800);">Position:</strong>
                        <span style="color: var(--gray-700);" id="restoreConfirmPosition"></span>
                    </div>
                </div>
                <div style="background: #d1ecf1; border: 1px solid #0dcaf0; border-radius: 6px; padding: 12px; display: flex; align-items: start; gap: 10px;">
                    <i class="fas fa-info-circle" style="color: #0c5460; margin-top: 2px;"></i>
                    <p style="margin: 0; font-size: 0.875rem; color: #0c5460; line-height: 1.5;">
                        Once restored, this application will appear in the <strong>Applications</strong> page and can be managed normally.
                    </p>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--gray-200); padding: 16px 24px; display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn btn-outline" onclick="closeRestoreConfirmModal()" style="min-width: 100px;">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="confirmRestore()" style="min-width: 100px;">
                    <i class="fas fa-undo"></i> Restore Application
                </button>
            </div>
        </div>
    </div>

    <script src="../../assets/js/toast.js"></script>
    <script src="../../assets/js/office-theme.js"></script>
    <script src="../../assets/js/admin-dashboard.js"></script>
    <script>
        function viewCoverLetter(app) {
            document.getElementById('coverLetterApplicant').textContent = app.applicant_name;
            document.getElementById('coverLetterPosition').textContent = app.position_title;
            document.getElementById('coverLetterText').innerHTML = app.cover_letter.replace(/\n/g, '<br>');
            document.getElementById('coverLetterModal').style.display = 'block';
            document.body.classList.add('modal-open');
        }

        function closeCoverLetterModal() {
            document.getElementById('coverLetterModal').style.display = 'none';
            document.body.classList.remove('modal-open');
        }

        let pendingRestoreFormId = null;

        function showRestoreConfirmation(applicationId, applicantName, positionTitle) {
            pendingRestoreFormId = applicationId;
            document.getElementById('restoreConfirmApplicant').textContent = applicantName;
            document.getElementById('restoreConfirmPosition').textContent = positionTitle;
            document.getElementById('restoreConfirmModal').style.display = 'block';
            document.body.classList.add('modal-open');
        }

        function closeRestoreConfirmModal() {
            document.getElementById('restoreConfirmModal').style.display = 'none';
            document.body.classList.remove('modal-open');
            pendingRestoreFormId = null;
        }

        function confirmRestore() {
            if (pendingRestoreFormId) {
                const form = document.getElementById('restore-form-' + pendingRestoreFormId);
                if (form) {
                    form.submit();
                }
            }
        }

        function toggleDetails(button, panel, show) {
            panel.style.display = show ? 'block' : 'none';
            button.setAttribute('aria-expanded', show ? 'true' : 'false');
            button.innerHTML = show
                ? '<i class="fas fa-eye-slash"></i> Hide Details'
                : '<i class="fas fa-eye"></i> View Details';
        }

        function initializeApplicationDetails() {
            document.querySelectorAll('[data-action="toggle-details"]').forEach(function(button) {
                const targetId = button.getAttribute('data-target');
                const panel = document.getElementById(targetId);
                if (!panel) {
                    return;
                }

                panel.querySelectorAll('[data-action="close-details"]').forEach(function(closeButton) {
                    closeButton.addEventListener('click', function() {
                        toggleDetails(button, panel, false);
                    });
                });

                button.addEventListener('click', function() {
                    const isOpen = panel.style.display === 'block';
                    toggleDetails(button, panel, !isOpen);
                });

                toggleDetails(button, panel, false);
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            initializeApplicationDetails();
        });

        window.addEventListener('click', function(event) {
            const coverLetterModal = document.getElementById('coverLetterModal');
            const restoreModal = document.getElementById('restoreConfirmModal');
            if (event.target === coverLetterModal) {
                closeCoverLetterModal();
            }
            if (event.target === restoreModal) {
                closeRestoreConfirmModal();
            }
        });

        window.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeCoverLetterModal();
                closeRestoreConfirmModal();
            }
        });
    </script>
    <script src="../../assets/js/form-submission-manager.js"></script>
</body>
</html>

