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
$totalArchivedAll = count($applications);

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

// Statistics for hero (filtered set, before pagination)
$statsPending = count(array_filter($applications, function ($a) {
    return $a['status'] === STATUS_PENDING;
}));
$statsApproved = count(array_filter($applications, function ($a) {
    return $a['status'] === STATUS_APPROVED;
}));
$statsRejected = count(array_filter($applications, function ($a) {
    return $a['status'] === STATUS_REJECTED;
}));

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

if (!function_exists('getApplicantInitials')) {
    function getApplicantInitials($name)
    {
        $parts = preg_split('/\s+/', trim((string) $name));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
        }

        return strtoupper(substr((string) $name, 0, 2));
    }
}

if (!function_exists('formatRelativeArchiveTime')) {
    function formatRelativeArchiveTime($datetime)
    {
        if (empty($datetime)) {
            return '';
        }

        $ts = strtotime($datetime);
        if ($ts === false) {
            return '';
        }

        $diff = time() - $ts;
        if ($diff < 86400) {
            return 'today';
        }
        if ($diff < 604800) {
            $days = (int) floor($diff / 86400);
            return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
        }
        if ($diff < 2592000) {
            $weeks = (int) floor($diff / 604800);
            return $weeks . ' week' . ($weeks === 1 ? '' : 's') . ' ago';
        }

        return date('M j, Y', $ts);
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
    <title>Application History - Dumaran Hiring System</title>
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/office-theme.css" rel="stylesheet">
    <link href="../../assets/css/admin-dashboard.css" rel="stylesheet">
    <link href="../../assets/css/applications-module.css" rel="stylesheet">
    <link href="../../assets/css/application-history.css" rel="stylesheet">
</head>
<body class="admin-dashboard applications-module application-history-page">
    <?php $adminSidebarActive = 'application_history'; require __DIR__ . '/_admin_sidebar.php'; ?>

    <div class="main-wrapper">
        <div class="topbar">
            <button class="mobile-menu-toggle" aria-label="Open menu">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="topbar-title">Application History</h1>
            <div class="topbar-actions">
                <a href="applications.php" class="topbar-btn" title="Active Applications" aria-label="Active Applications">
                    <i class="fas fa-file-alt"></i>
                </a>
                <a href="settings.php" class="topbar-btn" aria-label="Settings">
                    <i class="fas fa-cog"></i>
                </a>
                <a href="../../auth/logout.php" class="topbar-btn confirm-logout" title="Logout" aria-label="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

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

            <!-- Hero -->
            <section class="ats-hero" aria-labelledby="historyHeroTitle">
                <div class="ats-hero__content" style="width:100%;">
                    <div class="ats-hero__eyebrow"><i class="fas fa-archive"></i> Applicant Tracking System</div>
                    <h2 class="ats-hero__title" id="historyHeroTitle">Application History</h2>
                    <p class="ats-hero__subtitle">Archived applications that can be restored to the active hiring pipeline.</p>
                    <div class="ats-hero-stats" role="list" aria-label="Archive statistics">
                        <div class="ats-hero-stat" role="listitem">
                            <div class="ats-hero-stat__icon" aria-hidden="true"><i class="fas fa-archive"></i></div>
                            <div>
                                <div class="ats-hero-stat__value"><?= $totalArchivedAll ?></div>
                                <div class="ats-hero-stat__label">Total Archived</div>
                            </div>
                        </div>
                        <div class="ats-hero-stat" role="listitem">
                            <div class="ats-hero-stat__icon" aria-hidden="true"><i class="fas fa-user"></i></div>
                            <div>
                                <div class="ats-hero-stat__value"><?= $totalApplications_filtered ?></div>
                                <div class="ats-hero-stat__label">Showing</div>
                            </div>
                        </div>
                        <div class="ats-hero-stat" role="listitem">
                            <div class="ats-hero-stat__icon" aria-hidden="true"><i class="fas fa-clock"></i></div>
                            <div>
                                <div class="ats-hero-stat__value"><?= $statsPending ?></div>
                                <div class="ats-hero-stat__label">Pending</div>
                            </div>
                        </div>
                        <div class="ats-hero-stat" role="listitem">
                            <div class="ats-hero-stat__icon" aria-hidden="true"><i class="fas fa-check"></i></div>
                            <div>
                                <div class="ats-hero-stat__value"><?= $statsApproved ?></div>
                                <div class="ats-hero-stat__label">Approved</div>
                            </div>
                        </div>
                        <div class="ats-hero-stat" role="listitem">
                            <div class="ats-hero-stat__icon" aria-hidden="true"><i class="fas fa-times"></i></div>
                            <div>
                                <div class="ats-hero-stat__value"><?= $statsRejected ?></div>
                                <div class="ats-hero-stat__label">Rejected</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Search & Filters -->
            <section class="ats-toolbar ats-toolbar--sticky" aria-label="Search and filters">
                <form method="GET" action="">
                    <div class="ats-toolbar__row">
                        <label class="ats-search-wrap" for="searchQuery">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input
                                id="searchQuery"
                                type="search"
                                name="q"
                                value="<?= htmlspecialchars($searchQuery) ?>"
                                placeholder="Search applicants..."
                                autocomplete="off"
                                aria-label="Search archived applications"
                            >
                        </label>
                        <select name="status" class="ats-select" aria-label="Filter by status" onchange="this.form.submit()">
                            <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Status ▼ All</option>
                            <option value="<?= STATUS_PENDING ?>" <?= $filterStatus === STATUS_PENDING ? 'selected' : '' ?>>Pending</option>
                            <option value="<?= STATUS_APPROVED ?>" <?= $filterStatus === STATUS_APPROVED ? 'selected' : '' ?>>Approved</option>
                            <option value="<?= STATUS_REJECTED ?>" <?= $filterStatus === STATUS_REJECTED ? 'selected' : '' ?>>Rejected</option>
                        </select>
                        <select name="position" class="ats-select" aria-label="Filter by position" onchange="this.form.submit()">
                            <option value="all" <?= $filterPosition === 'all' ? 'selected' : '' ?>>Position ▼ All</option>
                            <?php foreach ($positions as $position): ?>
                                <option value="<?= $position['id'] ?>" <?= $filterPosition == $position['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($position['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($filterStatus !== 'all' || $filterPosition !== 'all' || $searchQuery !== ''): ?>
                            <a href="application_history.php" class="ats-btn ats-btn-outline" aria-label="Reset filters">
                                <i class="fas fa-rotate-left"></i> Reset
                            </a>
                        <?php endif; ?>
                        <button type="submit" class="ats-btn ats-btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>
                </form>
            </section>

            <!-- Archived Applications -->
            <section aria-labelledby="archivedAppsTitle">
                <div class="ats-section-header">
                    <h3 class="ats-section-header__title" id="archivedAppsTitle">
                        <i class="fas fa-box-archive"></i> Archived Applications
                    </h3>
                    <span class="ats-results-badge">
                        <?= $totalApplications_filtered ?> result<?= $totalApplications_filtered === 1 ? '' : 's' ?> · <?= count($applications) ?> showing
                    </span>
                </div>

                <?php if (empty($applications)): ?>
                    <div class="ats-empty">
                        <div class="ats-empty__icon" aria-hidden="true"><i class="fas fa-archive"></i></div>
                        <h3 class="ats-empty__title">No archived applications found</h3>
                        <p class="ats-empty__text">Try adjusting your search or filters, or return to active applications.</p>
                        <a href="applications.php" class="ats-btn ats-btn-primary">
                            <i class="fas fa-arrow-left"></i> Return to Applications
                        </a>
                    </div>
                <?php else: ?>
                    <div class="ats-cards-grid" id="archivedCardsGrid">
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
                            $initials = getApplicantInitials($app['applicant_name']);
                            $statusClass = $app['status'] === STATUS_PENDING ? 'pending' : ($app['status'] === STATUS_APPROVED ? 'approved' : 'rejected');
                            $coverPreview = htmlspecialchars(substr($app['cover_letter'], 0, 200));
                            $coverTruncated = strlen($app['cover_letter']) > 200;
                            $archiveRelative = !empty($app['archived_at']) ? formatRelativeArchiveTime($app['archived_at']) : '';
                            ?>
                            <article class="ats-applicant-card ats-archive-card application-item"
                                data-app-id="<?= $app['id'] ?>"
                                data-application-id="<?= $app['id'] ?>"
                                data-status="<?= htmlspecialchars($app['status']) ?>">
                                <div class="ats-archive-card__header">
                                    <div class="ats-avatar" aria-hidden="true"><?= htmlspecialchars($initials) ?></div>
                                    <div class="ats-archive-card__identity">
                                        <h4 class="ats-applicant-card__name application-name"><?= htmlspecialchars($app['applicant_name']) ?></h4>
                                        <p class="ats-archive-card__email">
                                            <i class="fas fa-envelope" aria-hidden="true"></i>
                                            <?= htmlspecialchars($app['applicant_email']) ?>
                                        </p>
                                        <p class="ats-applicant-card__position"><?= htmlspecialchars($app['position_title']) ?></p>
                                        <div class="ats-archive-card__badges">
                                            <span class="ats-badge ats-badge--<?= $statusClass ?>">
                                                <i class="fas fa-circle" style="font-size:0.5rem" aria-hidden="true"></i>
                                                <?= ucfirst($app['status']) ?>
                                            </span>
                                            <span class="ats-badge ats-badge--archived">
                                                <i class="fas fa-archive" aria-hidden="true"></i> Archived
                                            </span>
                                        </div>
                                        <div class="ats-meta-chips" aria-label="Application metadata">
                                            <span class="ats-meta-chip"><i class="fas fa-briefcase" aria-hidden="true"></i> <?= $app['total_experience_years'] ?> years</span>
                                            <span class="ats-meta-chip"><i class="fas fa-paperclip" aria-hidden="true"></i> <?= $totalDocuments ?> Document<?= $totalDocuments === 1 ? '' : 's' ?></span>
                                            <?php if ($archiveRelative): ?>
                                            <span class="ats-meta-chip"><i class="fas fa-clock" aria-hidden="true"></i> Archived <?= htmlspecialchars($archiveRelative) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="ats-date-timeline" aria-label="Application timeline">
                                    <div class="ats-date-timeline__item">
                                        <div class="ats-date-timeline__icon" aria-hidden="true"><i class="fas fa-paper-plane"></i></div>
                                        <div>
                                            <span class="ats-date-timeline__label">Applied</span>
                                            <span class="ats-date-timeline__value"><?= date('M j', strtotime($app['applied_at'])) ?></span>
                                        </div>
                                    </div>
                                    <?php if (!empty($app['archived_at'])): ?>
                                    <div class="ats-date-timeline__item">
                                        <div class="ats-date-timeline__icon" aria-hidden="true"><i class="fas fa-box-archive"></i></div>
                                        <div>
                                            <span class="ats-date-timeline__label">Archived</span>
                                            <span class="ats-date-timeline__value"><?= date('M j', strtotime($app['archived_at'])) ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="ats-date-timeline__item">
                                        <div class="ats-date-timeline__icon" aria-hidden="true"><i class="fas fa-user-clock"></i></div>
                                        <div>
                                            <span class="ats-date-timeline__label">Experience</span>
                                            <span class="ats-date-timeline__value"><?= $app['total_experience_years'] ?> years</span>
                                        </div>
                                    </div>
                                </div>

                                <?php if (!empty(trim($app['cover_letter']))): ?>
                                <div class="ats-cover-preview">
                                    <div class="ats-cover-preview__label"><i class="fas fa-file-lines" aria-hidden="true"></i> Quick Overview</div>
                                    <p class="ats-cover-preview__text application-summary"><?= $coverPreview ?><?= $coverTruncated ? '…' : '' ?></p>
                                    <?php if ($coverTruncated): ?>
                                    <div class="ats-cover-preview__fade" aria-hidden="true"></div>
                                    <button type="button" class="ats-cover-preview__read-more" onclick='viewCoverLetter(<?= json_encode($app) ?>)' aria-label="Read full cover letter for <?= htmlspecialchars($app['applicant_name']) ?>">
                                        Read More <i class="fas fa-arrow-right" aria-hidden="true"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <div class="ats-doc-chips<?= $hasDocuments ? '' : ' ats-doc-chips--empty' ?>" aria-label="Documents">
                                    <span class="ats-doc-chips__label"><i class="fas fa-folder-open" aria-hidden="true"></i> Documents</span>
                                    <?php if ($hasDocuments): ?>
                                        <?php foreach ($documentEntries as $entry):
                                            $extension = $entry['extension'] ?? '';
                                            $extensionLabel = $extension ? strtoupper($extension) : 'FILE';
                                            $chipIcon = $documentExtensionIcons[$extension] ?? ($documentTypeIcons[$entry['type']] ?? 'fa-file');
                                        ?>
                                        <span class="ats-doc-chip">
                                            <i class="fas <?= $chipIcon ?> ats-doc-chip__icon" aria-hidden="true"></i>
                                            <span><?= htmlspecialchars($entry['label']) ?></span>
                                            <span class="ats-doc-chip__type"><?= htmlspecialchars($extensionLabel) ?></span>
                                            <span class="ats-doc-chip__actions">
                                                <a href="<?= htmlspecialchars($entry['view_url']) ?>" target="_blank" rel="noopener" aria-label="View <?= htmlspecialchars($entry['label']) ?>">
                                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                                </a>
                                                <a href="<?= htmlspecialchars($entry['download_url']) ?>" download="<?= htmlspecialchars($entry['filename']) ?>" aria-label="Download <?= htmlspecialchars($entry['label']) ?>">
                                                    <i class="fas fa-download" aria-hidden="true"></i>
                                                </a>
                                            </span>
                                        </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="ats-doc-chip">No documents uploaded</span>
                                    <?php endif; ?>
                                </div>

                                <div class="ats-archive-card__footer">
                                    <div class="ats-archive-card__quick-actions quick-actions">
                                        <button type="button" class="ats-accordion-toggle"
                                            data-action="toggle-details"
                                            data-target="application-details-<?= $app['id'] ?>"
                                            aria-expanded="false"
                                            aria-controls="application-details-<?= $app['id'] ?>">
                                            <span>View Details</span>
                                            <i class="fas fa-chevron-down ats-accordion-chevron" aria-hidden="true"></i>
                                        </button>
                                        <button type="button" class="ats-icon-btn" data-tooltip="Cover Letter"
                                            onclick='viewCoverLetter(<?= json_encode($app) ?>)'
                                            aria-label="View cover letter">
                                            <i class="fas fa-eye" aria-hidden="true"></i>
                                        </button>
                                        <a href="mailto:<?= htmlspecialchars($app['applicant_email']) ?>" class="ats-icon-btn" data-tooltip="Email"
                                            aria-label="Email applicant">
                                            <i class="fas fa-envelope" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                    <form method="POST" action="application_history.php" id="restore-form-<?= $app['id'] ?>" style="display: none;">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <?php FormTokenManager::renderFormTokenInput('restore_application_' . (int) $app['id']); ?>
                                        <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                        <input type="hidden" name="restore_application" value="1">
                                    </form>
                                    <button type="button" class="ats-btn-restore"
                                        id="restore-btn-<?= $app['id'] ?>"
                                        onclick="showRestoreConfirmation(<?= $app['id'] ?>, '<?= htmlspecialchars(addslashes($app['applicant_name'])) ?>', '<?= htmlspecialchars(addslashes($app['position_title'])) ?>', '<?= htmlspecialchars(addslashes($initials)) ?>')"
                                        aria-label="Restore application for <?= htmlspecialchars($app['applicant_name']) ?>">
                                        <i class="fas fa-undo" aria-hidden="true"></i>
                                        <span class="ats-btn-restore__label">Restore Application</span>
                                    </button>
                                </div>

                                <div id="application-details-<?= $app['id'] ?>"
                                    class="ats-details-panel application-details-panel"
                                    role="region"
                                    aria-label="Application details"
                                    hidden>
                                    <div class="ats-doc-manager">
                                        <div class="ats-doc-manager__toolbar">
                                            <strong><i class="fas fa-paperclip" aria-hidden="true"></i> All Documents (<?= $totalDocuments ?>)</strong>
                                        </div>
                                        <?php if ($hasDocuments): ?>
                                        <div class="ats-doc-grid">
                                            <?php foreach ($documentEntries as $entry):
                                                $extension = $entry['extension'] ?? '';
                                                $icon = $documentExtensionIcons[$extension] ?? ($documentTypeIcons[$entry['type']] ?? 'fa-file');
                                            ?>
                                            <div class="ats-doc-item">
                                                <div class="ats-doc-item__icon"><i class="fas <?= $icon ?>" aria-hidden="true"></i></div>
                                                <div class="ats-doc-item__name"><?= htmlspecialchars($entry['label']) ?></div>
                                                <div class="ats-doc-item__meta">
                                                    <?= htmlspecialchars($entry['filename']) ?>
                                                    <?php if (!empty($entry['size'])): ?>
                                                        · <?= htmlspecialchars($entry['size']) ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="ats-doc-item__actions">
                                                    <a href="<?= htmlspecialchars($entry['view_url']) ?>" target="_blank" rel="noopener" class="ats-btn ats-btn-sm ats-btn-outline" aria-label="View"><i class="fas fa-eye"></i></a>
                                                    <a href="<?= htmlspecialchars($entry['download_url']) ?>" class="ats-btn ats-btn-sm ats-btn-primary" download="<?= htmlspecialchars($entry['filename']) ?>" aria-label="Download"><i class="fas fa-download"></i></a>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php else: ?>
                                        <p style="color:var(--text-secondary);font-size:0.875rem;margin:0">Applicant did not upload any supporting files.</p>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display:flex;justify-content:flex-end;margin-top:12px">
                                        <button type="button" class="ats-btn ats-btn-sm ats-btn-outline"
                                            data-action="close-details"
                                            data-target="application-details-<?= $app['id'] ?>">
                                            Close
                                        </button>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($totalPages > 1): ?>
                <nav class="ats-pagination" aria-label="Pagination">
                    <div class="ats-pagination-pills">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($filterStatus) ?>&position=<?= urlencode($filterPosition) ?>&q=<?= urlencode($searchQuery) ?>" class="ats-pagination-pill" aria-label="Previous page">
                                <i class="fas fa-chevron-left" aria-hidden="true"></i> Previous
                            </a>
                        <?php else: ?>
                            <span class="ats-pagination-pill is-disabled" aria-hidden="true"><i class="fas fa-chevron-left"></i> Previous</span>
                        <?php endif; ?>

                        <span class="ats-pagination-pill is-active" aria-current="page"><?= $page ?></span>

                        <span class="ats-pagination__info">of <?= $totalPages ?> (<?= $totalApplications_filtered ?> total)</span>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($filterStatus) ?>&position=<?= urlencode($filterPosition) ?>&q=<?= urlencode($searchQuery) ?>" class="ats-pagination-pill" aria-label="Next page">
                                Next <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </a>
                        <?php else: ?>
                            <span class="ats-pagination-pill is-disabled" aria-hidden="true">Next <i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                </nav>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <nav class="mobile-nav" aria-label="Mobile navigation">
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
    <div class="ats-modal" id="coverLetterModal" role="dialog" aria-modal="true" aria-labelledby="coverLetterTitle">
        <div class="ats-modal__content ats-modal__content--lg">
            <div class="ats-modal__header">
                <h3 class="ats-modal__title" id="coverLetterTitle">Cover Letter</h3>
                <button type="button" class="ats-modal__close" onclick="closeCoverLetterModal()" aria-label="Close cover letter">&times;</button>
            </div>
            <div class="ats-modal__body">
                <div style="margin-bottom:16px">
                    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-secondary);margin-bottom:4px">Applicant</div>
                    <p style="font-weight:700;font-size:1.05rem;margin:0;color:var(--text-primary)" id="coverLetterApplicant"></p>
                </div>
                <div style="margin-bottom:20px">
                    <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-secondary);margin-bottom:4px">Position</div>
                    <p style="font-weight:600;margin:0;color:var(--primary)" id="coverLetterPosition"></p>
                </div>
                <div id="coverLetterText" class="ats-cover-letter-body" role="document"></div>
            </div>
            <div class="ats-modal__footer ats-modal__footer--sticky">
                <button type="button" class="ats-btn ats-btn-outline" onclick="copyCoverLetter()" aria-label="Copy cover letter">
                    <i class="fas fa-copy"></i> Copy
                </button>
                <button type="button" class="ats-btn ats-btn-outline" onclick="printCoverLetter()" aria-label="Print cover letter">
                    <i class="fas fa-print"></i> Print
                </button>
                <button type="button" class="ats-btn ats-btn-primary" onclick="closeCoverLetterModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div class="ats-modal" id="restoreConfirmModal" role="dialog" aria-modal="true" aria-labelledby="restoreModalTitle">
        <div class="ats-modal__content">
            <div class="ats-modal__header" style="border-bottom:2px solid var(--primary);background:linear-gradient(135deg,rgba(0,86,210,0.1),rgba(0,86,210,0.04))">
                <div style="display:flex;align-items:center;gap:12px">
                    <div class="ats-status-modal-icon" style="background:var(--primary)" aria-hidden="true">
                        <i class="fas fa-undo"></i>
                    </div>
                    <h3 class="ats-modal__title" id="restoreModalTitle">Restore Application</h3>
                </div>
                <button type="button" class="ats-modal__close" onclick="closeRestoreConfirmModal()" aria-label="Close restore confirmation">&times;</button>
            </div>
            <div class="ats-modal__body">
                <p style="font-size:1rem;color:var(--text-primary);margin:0 0 20px;line-height:1.65">
                    Are you sure you want to restore this application? It will be moved back to <strong>Active Applications</strong>.
                </p>
                <div class="ats-restore-preview">
                    <div class="ats-restore-preview__avatar" id="restoreConfirmAvatar" aria-hidden="true"></div>
                    <div>
                        <p class="ats-restore-preview__name" id="restoreConfirmApplicant"></p>
                        <p class="ats-restore-preview__position">
                            <i class="fas fa-briefcase" aria-hidden="true"></i>
                            <span id="restoreConfirmPosition"></span>
                        </p>
                    </div>
                </div>
                <div class="ats-info-box ats-info-box--info" style="display:flex;align-items:flex-start;gap:10px">
                    <i class="fas fa-info-circle" style="color:var(--primary);margin-top:2px" aria-hidden="true"></i>
                    <p style="margin:0;font-size:0.875rem;line-height:1.55;color:var(--text-secondary)">
                        Once restored, this application will appear in the <strong>Applications</strong> page and can be managed normally.
                    </p>
                </div>
            </div>
            <div class="ats-modal__footer">
                <button type="button" class="ats-btn ats-btn-outline" onclick="closeRestoreConfirmModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="ats-btn ats-btn-primary" id="restoreConfirmBtn" onclick="confirmRestore()">
                    <i class="fas fa-undo"></i> Restore Application
                </button>
            </div>
        </div>
    </div>

    <script src="../../assets/js/toast.js"></script>
    <script src="../../assets/js/office-theme.js"></script>
    <script src="../../assets/js/admin-dashboard.js"></script>
    <script>
        let coverLetterPlainText = '';

        function openModal(modal) {
            if (!modal) return;
            modal.classList.add('open');
            document.body.classList.add('modal-open');
        }

        function closeModal(modal) {
            if (!modal) return;
            modal.classList.remove('open');
            if (!document.querySelector('.ats-modal.open')) {
                document.body.classList.remove('modal-open');
            }
        }

        function viewCoverLetter(app) {
            document.getElementById('coverLetterApplicant').textContent = app.applicant_name;
            document.getElementById('coverLetterPosition').textContent = app.position_title;
            coverLetterPlainText = app.cover_letter || '';
            document.getElementById('coverLetterText').innerHTML = coverLetterPlainText.replace(/\n/g, '<br>');
            openModal(document.getElementById('coverLetterModal'));
        }

        function closeCoverLetterModal() {
            closeModal(document.getElementById('coverLetterModal'));
        }

        function copyCoverLetter() {
            if (!coverLetterPlainText) return;
            navigator.clipboard.writeText(coverLetterPlainText).then(function() {
                if (typeof showSuccess === 'function') {
                    showSuccess('Cover letter copied to clipboard.', 3000, 'Copied');
                }
            }).catch(function() {
                if (typeof showError === 'function') {
                    showError('Unable to copy cover letter.', 3000, 'Error');
                }
            });
        }

        function printCoverLetter() {
            const applicant = document.getElementById('coverLetterApplicant').textContent;
            const position = document.getElementById('coverLetterPosition').textContent;
            const printWindow = window.open('', '_blank');
            if (!printWindow) return;
            printWindow.document.write('<html><head><title>Cover Letter - ' + applicant + '</title></head><body>');
            printWindow.document.write('<h1>' + applicant + '</h1>');
            printWindow.document.write('<h2>' + position + '</h2>');
            printWindow.document.write('<div style="line-height:1.75;font-size:14px">' + document.getElementById('coverLetterText').innerHTML + '</div>');
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        }

        let pendingRestoreFormId = null;

        function showRestoreConfirmation(applicationId, applicantName, positionTitle, initials) {
            pendingRestoreFormId = applicationId;
            document.getElementById('restoreConfirmApplicant').textContent = applicantName;
            document.getElementById('restoreConfirmPosition').textContent = positionTitle;
            document.getElementById('restoreConfirmAvatar').textContent = initials || applicantName.substring(0, 2).toUpperCase();
            openModal(document.getElementById('restoreConfirmModal'));
        }

        function closeRestoreConfirmModal() {
            closeModal(document.getElementById('restoreConfirmModal'));
            pendingRestoreFormId = null;
            const confirmBtn = document.getElementById('restoreConfirmBtn');
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.classList.remove('is-loading');
            }
        }

        function confirmRestore() {
            if (!pendingRestoreFormId) return;

            const form = document.getElementById('restore-form-' + pendingRestoreFormId);
            const cardBtn = document.getElementById('restore-btn-' + pendingRestoreFormId);
            const confirmBtn = document.getElementById('restoreConfirmBtn');

            if (confirmBtn) {
                confirmBtn.disabled = true;
                confirmBtn.classList.add('is-loading');
            }
            if (cardBtn) {
                cardBtn.disabled = true;
                cardBtn.classList.add('is-loading');
            }

            if (form) {
                form.submit();
            }
        }

        function toggleDetails(button, panel, show) {
            panel.hidden = !show;
            panel.classList.toggle('open', show);
            button.setAttribute('aria-expanded', show ? 'true' : 'false');
            const label = button.querySelector('span');
            if (label) {
                label.textContent = show ? 'Hide Details' : 'View Details';
            }
        }

        function initializeApplicationDetails() {
            document.querySelectorAll('[data-action="toggle-details"]').forEach(function(button) {
                const targetId = button.getAttribute('data-target');
                const panel = document.getElementById(targetId);
                if (!panel) return;

                panel.querySelectorAll('[data-action="close-details"]').forEach(function(closeButton) {
                    closeButton.addEventListener('click', function() {
                        toggleDetails(button, panel, false);
                    });
                });

                button.addEventListener('click', function() {
                    const isOpen = panel.classList.contains('open');
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

