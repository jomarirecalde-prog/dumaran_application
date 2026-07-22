<?php
require_once '../../config/config.php';
requireAuth();

// Redirect admin to admin dashboard
if (hasRole(ROLE_ADMIN)) {
    header('Location: ' . BASE_URL . 'admin/pages/applications.php');
    exit();
}

// Require privacy waiver acceptance
requirePrivacyWaiver();

require_once '../../includes/models.php';

$applicationModel = new Application();
$documentModel = new ApplicationDocument();
$userId = $_SESSION['user_id'];

// Handle supporting document uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_documents') {
    $redirectParams = [];
    if (!empty($_POST['status_filter'])) {
        $redirectParams['status'] = $_POST['status_filter'];
    }
    if (!empty($_POST['page'])) {
        $redirectParams['page'] = max(1, (int)$_POST['page']);
    }

    $redirectWithMessage = function(string $type, string $message) use ($redirectParams) {
        $params = $redirectParams;
        $params[$type] = $message;
        $query = http_build_query($params);
        header('Location: my-applications.php' . ($query ? '?' . $query : ''));
        exit();
    };

    $applicationId = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
    if ($applicationId <= 0) {
        $redirectWithMessage('error', 'Invalid application selected.');
    }

    $application = $applicationModel->findById($applicationId);
    if (!$application || (int)$application['user_id'] !== (int)$userId) {
        $redirectWithMessage('error', 'You are not authorized to upload documents for this application.');
    }

    // Lock editing for approved or rejected applications
    if ($application['status'] === STATUS_APPROVED || $application['status'] === STATUS_REJECTED) {
        $redirectWithMessage('error', 'This application has been finalized and cannot be modified.');
    }

    $validation = FormTokenManager::validateFormSubmission($_POST);
    if (!$validation['valid']) {
        $errorMessage = $validation['error'] === 'duplicate'
            ? 'This upload has already been processed. Please refresh the page.'
            : $validation['error'];
        $redirectWithMessage('error', $errorMessage);
    }

    $allowedDocTypes = ['certificate', 'portfolio', 'other'];
    $uploadDir = 'uploads/documents/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filesProcessed = 0;
    $errors = [];
    $fileSizeLimit = defined('MAX_FILE_SIZE') ? (int)MAX_FILE_SIZE : (5 * 1024 * 1024);
    $uploadErrorMessages = [
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the maximum upload size.',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the allowed size for this form.',
        UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
    ];

    // Helper to process individual files
    $processFile = function(array $fileInfo, string $docType) use (
        $fileSizeLimit,
        $uploadDir,
        $userId,
        $applicationId,
        $documentModel,
        &$filesProcessed,
        &$errors
    ) {
        $originalName = $fileInfo['name'];
        $tmpName = $fileInfo['tmp_name'];
        $errorCode = $fileInfo['error'];
        $fileSize = $fileInfo['size'];

        if ($errorCode !== UPLOAD_ERR_OK) {
            return false;
        }

        if ($fileSize > $fileSizeLimit) {
            $errors[] = $originalName . ' exceeds the maximum file size limit.';
            return false;
        }

        // Validate PDF file type
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            $errors[] = $originalName . ' is not a PDF file. Only PDF files are allowed.';
            return false;
        }

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpName);
        finfo_close($finfo);
        
        if ($mimeType !== 'application/pdf') {
            $errors[] = $originalName . ' is not a valid PDF file. Only PDF files are allowed.';
            return false;
        }

        $safeName = time() . '_' . $userId . '_' . uniqid('', true) . '_' . basename($originalName);
        $targetPath = $uploadDir . $safeName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            $errors[] = 'Failed to move uploaded file: ' . $originalName;
            return false;
        }

        try {
            $documentModel->create([
                'application_id' => $applicationId,
                'document_type' => $docType,
                'file_name' => basename($originalName),
                'file_path' => $safeName,
                'file_size' => $fileSize
            ]);
            $filesProcessed++;
            return true;
        } catch (Exception $e) {
            // Clean up the file if database insert fails
            if (file_exists($targetPath)) {
                unlink($targetPath);
            }
            error_log('Failed to save supporting document: ' . $e->getMessage());
            $errors[] = 'Failed to save document ' . basename($originalName) . '. Please try again.';
            return false;
        }
    };

    // Process new form structure (document_type + supporting_files[])
    if (isset($_FILES['supporting_files'])) {
        $selectedDocType = $_POST['document_type'] ?? 'other';
        if (!in_array($selectedDocType, $allowedDocTypes, true)) {
            $selectedDocType = 'other';
        }

        $fileCount = is_array($_FILES['supporting_files']['name'])
            ? count($_FILES['supporting_files']['name'])
            : 0;

        for ($i = 0; $i < $fileCount; $i++) {
            $fileInfo = [
                'name' => $_FILES['supporting_files']['name'][$i],
                'type' => $_FILES['supporting_files']['type'][$i],
                'tmp_name' => $_FILES['supporting_files']['tmp_name'][$i],
                'error' => $_FILES['supporting_files']['error'][$i],
                'size' => $_FILES['supporting_files']['size'][$i]
            ];

            if ($fileInfo['error'] !== UPLOAD_ERR_OK) {
                if ($fileInfo['error'] !== UPLOAD_ERR_NO_FILE) {
                    $errors[] = $fileInfo['name'] . ': ' . ($uploadErrorMessages[$fileInfo['error']] ?? 'Upload failed.');
                }
                continue;
            }

            $processFile($fileInfo, $selectedDocType);
        }
    }

    // Process legacy structure (documents[certificate][], etc.)
    if (isset($_FILES['documents']) && is_array($_FILES['documents']['name'])) {
        foreach ($allowedDocTypes as $docType) {
            if (!isset($_FILES['documents']['name'][$docType]) || !is_array($_FILES['documents']['name'][$docType])) {
                continue;
            }

            $fileCount = count($_FILES['documents']['name'][$docType]);
            for ($i = 0; $i < $fileCount; $i++) {
                $fileInfo = [
                    'name' => $_FILES['documents']['name'][$docType][$i],
                    'type' => $_FILES['documents']['type'][$docType][$i],
                    'tmp_name' => $_FILES['documents']['tmp_name'][$docType][$i],
                    'error' => $_FILES['documents']['error'][$docType][$i],
                    'size' => $_FILES['documents']['size'][$docType][$i]
                ];

                if ($fileInfo['error'] !== UPLOAD_ERR_OK) {
                    if ($fileInfo['error'] !== UPLOAD_ERR_NO_FILE) {
                        $errors[] = $fileInfo['name'] . ': ' . ($uploadErrorMessages[$fileInfo['error']] ?? 'Upload failed.');
                    }
                    continue;
                }

                $processFile($fileInfo, $docType);
            }
        }
    }

    if ($filesProcessed > 0) {
        $message = $filesProcessed === 1
            ? 'Supporting document uploaded successfully.'
            : $filesProcessed . ' supporting documents uploaded successfully.';
        $redirectWithMessage('message', $message);
    }

    if (!empty($errors)) {
        $redirectWithMessage('error', $errors[0]);
    }

    $redirectWithMessage('error', 'No files were uploaded.');
}

// Get filter
$statusFilter = $_GET['status'] ?? '';

// Build query
$where = 'a.user_id = ?';
$params = [$userId];

if (!empty($statusFilter)) {
    $where .= ' AND a.status = ?';
    $params[] = $statusFilter;
}

// Include archived applications so approved/rejected applications remain visible
$allApplications = $applicationModel->getApplicationsWithDetails($where, $params, true);

// Count by status
$pendingCount = count(array_filter($allApplications, fn($app) => $app['status'] === STATUS_PENDING));
$approvedCount = count(array_filter($allApplications, fn($app) => $app['status'] === STATUS_APPROVED));
$rejectedCount = count(array_filter($allApplications, fn($app) => $app['status'] === STATUS_REJECTED));

// Pagination setup
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 5;
$offset = ($page - 1) * $perPage;

// Calculate pagination
$totalApplications_filtered = count($allApplications);
$totalPages = ceil($totalApplications_filtered / $perPage);
$applications = array_slice($allApplications, $offset, $perPage);

$documentsByApplication = [];
if (!empty($applications)) {
    foreach ($applications as $app) {
        $documentsByApplication[$app['id']] = $documentModel->getApplicationDocuments($app['id']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Applications - WPU Applicant System</title>
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/office-theme.css" rel="stylesheet">
    <?php include '../../includes/pwa-header.php'; ?>
</head>
<body>
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">
                <img src="../../assets/logo.png" alt="WPU Logo" style="height: 32px; width: auto;">
                <span>WPU Jobs</span>
            </a>
        </div>
        
        <nav class="sidebar-nav">
         <a href="profile.php" class="sidebar-nav-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="dashboard.php" class="sidebar-nav-item active">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="positions.php" class="sidebar-nav-item">
                <i class="fas fa-briefcase"></i>
                <span>Browse Jobs</span>
            </a>
            <a href="my-applications.php" class="sidebar-nav-item">
                <i class="fas fa-file-alt"></i>
                <span>My Applications</span>
            </a>
            
        </nav>
        
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">
                    <?php 
                    $userModel = new User();
                    $user = $userModel->findById($_SESSION['user_id']);
                    echo strtoupper(substr($user['full_name'], 0, 1));
                    ?>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?= htmlspecialchars($user['full_name']) ?></div>
                    <div class="sidebar-user-role">Applicant</div>
                </div>
                
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
            <h1 class="topbar-title">My Applications</h1>
            <div class="topbar-actions">
                
                <a href="../../auth/logout.php" class="topbar-btn confirm-logout" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content">
            <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-envelope-circle-check"></i>
                <div>
                    <?= htmlspecialchars($_GET['message']) ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div><?= htmlspecialchars($_GET['error']) ?></div>
            </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
          

            <!-- Filter Chips -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-body">
                    <div class="filter-chips">
                        <a href="my-applications.php" class="filter-chip <?= empty($statusFilter) ? 'active' : '' ?>">
                            <i class="fas fa-list"></i> All
                        </a>
                        <a href="my-applications.php?status=<?= STATUS_PENDING ?>" 
                           class="filter-chip <?= $statusFilter === STATUS_PENDING ? 'active' : '' ?>">
                            <i class="fas fa-clock"></i> Pending
                        </a>
                        <a href="my-applications.php?status=<?= STATUS_APPROVED ?>" 
                           class="filter-chip <?= $statusFilter === STATUS_APPROVED ? 'active' : '' ?>">
                            <i class="fas fa-check-circle"></i> Approved
                        </a>
                        <a href="my-applications.php?status=<?= STATUS_REJECTED ?>" 
                           class="filter-chip <?= $statusFilter === STATUS_REJECTED ? 'active' : '' ?>">
                            <i class="fas fa-times-circle"></i> Rejected
                        </a>
                    </div>
                </div>
            </div>

            <!-- Applications List -->
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="card-title">
                        <i class="fas fa-file-alt"></i> My Applications
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($applications)): ?>
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <h3>No Applications Yet</h3>
                            <p>You haven't submitted any applications. Start exploring available positions!</p>
                            <a href="positions.php" class="btn btn-primary">
                                <i class="fas fa-briefcase"></i> Browse Jobs
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($applications as $app): ?>
                            <div style="padding: 16px; border-bottom: 1px solid var(--gray-200); transition: all 0.2s;" data-application-id="<?= $app['id'] ?>" data-status="<?= htmlspecialchars($app['status']) ?>">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                                    <div style="flex: 1;">
                                        <h4 style="margin: 0 0 6px 0; font-size: 1rem; font-weight: 600; color: var(--gray-800);">
                                            <a href="application-details.php?id=<?= $app['id'] ?>" style="color: var(--gray-800); text-decoration: none;">
                                                <?= htmlspecialchars($app['position_title']) ?>
                                            </a>
                                        </h4>
                                        <div style="font-size: 0.85rem; color: var(--gray-600); display: flex; gap: 16px; flex-wrap: wrap;">
                                            <span><i class="fas fa-calendar"></i> Applied: <?= date('M j, Y', strtotime($app['applied_at'])) ?></span>
                                            <span><i class="fas fa-briefcase"></i> Experience: <?= number_format($app['total_experience_years'], 1) ?>y (Required: <?= $app['min_experience_years'] ?>y)</span>
                                        </div>
                                    </div>
                                    <div style="margin-left: 12px;">
                                        <span class="badge badge-<?= $app['status'] === STATUS_PENDING ? 'warning' : ($app['status'] === STATUS_APPROVED ? 'success' : 'danger') ?>">
                                            <?= ucfirst($app['status']) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php $documents = $documentsByApplication[$app['id']] ?? []; ?>

                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div style="font-size: 0.8rem; color: var(--gray-500);">
                                        <?php if (!empty($app['reviewed_at'] ?? '')): ?>
                                            <i class="fas fa-clock"></i> Reviewed: <?= date('M j, Y', strtotime($app['reviewed_at'])) ?>
                                        <?php else: ?>
                                            <i class="fas fa-hourglass-half"></i> Awaiting review
                                        <?php endif; ?>
                                    </div>
                                    <button 
                                        type="button" 
                                        class="btn btn-sm btn-primary toggle-details" 
                                        data-target="application-details-<?= $app['id'] ?>" 
                                        aria-expanded="false"
                                    >
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                </div>

                                <div 
                                    id="application-details-<?= $app['id'] ?>" 
                                    class="application-details-panel" 
                                    style="display: none; margin-top: 14px;"
                                >
                                    <?php if (!empty($app['admin_notes'] ?? '')): ?>
                                        <div style="background: rgba(59, 130, 246, 0.05); border-left: 4px solid var(--primary-blue); padding: 12px; border-radius: 8px; margin-bottom: 12px;">
                                            <div style="font-size: 0.875rem; font-weight: 600; color: var(--primary-blue); margin-bottom: 6px;">
                                                <i class="fas fa-comment-dots"></i> Admin Feedback
                                            </div>
                                            <div style="font-size: 0.875rem; color: var(--gray-700); line-height: 1.5;">
                                                <?= nl2br(htmlspecialchars($app['admin_notes'])) ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div style="background: rgba(15, 118, 255, 0.05); border-radius: 10px; padding: 14px; margin-bottom: 14px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                            <div style="font-size: 0.9rem; font-weight: 600; color: var(--primary-blue); display: flex; align-items: center; gap: 8px;">
                                                <i class="fas fa-paperclip"></i> Supporting Documents
                                            </div>
                                            <?php if (!empty($documents)): ?>
                                                <span style="font-size: 0.75rem; color: var(--gray-500);">
                                                    <?= count($documents) ?> file<?= count($documents) === 1 ? '' : 's' ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($documents)): ?>
                                            <ul style="list-style: none; padding: 0; margin: 0 0 12px 0; display: flex; flex-direction: column; gap: 8px;">
                                                <?php foreach ($documents as $doc): ?>
                                                    <li style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: var(--gray-700);">
                                                        <i class="fas fa-file-alt" style="color: var(--primary-blue);"></i>
                                                        <div style="flex: 1;">
                                                            <div style="font-weight: 600;"><?= htmlspecialchars($doc['file_name']) ?></div>
                                                            <div style="font-size: 0.75rem; color: var(--gray-500);">
                                                                <?= ucfirst($doc['document_type']) ?>
                                                                <?php if (!empty($doc['file_size'])): ?>
                                                                    &middot; <?= number_format($doc['file_size'] / 1024, 1) ?> KB
                                                                <?php endif; ?>
                                                                <?php if (!empty($doc['uploaded_at'])): ?>
                                                                    &middot; Uploaded <?= date('M j, Y', strtotime($doc['uploaded_at'])) ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p style="margin: 0 0 12px 0; font-size: 0.8rem; color: var(--gray-600);">
                                                No supporting documents uploaded yet.
                                            </p>
                                        <?php endif; ?>

                                        <?php 
                                        $isLocked = $app['status'] === STATUS_APPROVED || $app['status'] === STATUS_REJECTED;
                                        ?>
                                        
                                        <?php if ($isLocked): ?>
                                            <div style="background: rgba(156, 163, 175, 0.1); border: 1px solid rgba(156, 163, 175, 0.2); border-radius: 8px; padding: 12px; margin-top: 12px;">
                                                <div style="display: flex; align-items: center; gap: 8px; color: var(--gray-600); font-size: 0.85rem;">
                                                    <i class="fas fa-lock"></i>
                                                    <span>
                                                        This application has been <?= $app['status'] === STATUS_APPROVED ? 'approved' : 'rejected' ?> and is locked. No further modifications are allowed.
                                                    </span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <form method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 10px;">
                                                <input type="hidden" name="action" value="upload_documents">
                                                <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                                <input type="hidden" name="status_filter" value="<?= htmlspecialchars($statusFilter) ?>">
                                                <input type="hidden" name="page" value="<?= $page ?>">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <?php FormTokenManager::renderFormTokenInput('upload_documents_' . $app['id']); ?>

                                                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                                    <div style="flex: 1; min-width: 180px;">
                                                        <label style="display: block; font-size: 0.75rem; color: var(--gray-500); margin-bottom: 4px;">
                                                            Document Type
                                                        </label>
                                                        <select name="document_type" class="form-control" style="font-size: 0.85rem;">
                                                            <option value="certificate">Certificate</option>
                                                            <option value="portfolio">Portfolio</option>
                                                            <option value="other" selected>Other</option>
                                                        </select>
                                                    </div>
                                                    <div style="flex: 2; min-width: 220px;">
                                                        <label style="display: block; font-size: 0.75rem; color: var(--gray-500); margin-bottom: 4px;">
                                                            Select Files
                                                        </label>
                                                        <input type="file" class="form-control" name="supporting_files[]" multiple accept=".pdf,application/pdf">
                                                    </div>
                                                </div>

                                                <div style="display: flex; justify-content: flex-end; gap: 10px; align-items: center;">
                                                    <small style="color: var(--gray-500); font-size: 0.7rem;">
                                                        Max 5MB per file. Only PDF files are allowed.
                                                    </small>
                                                    <button type="submit" class="btn btn-sm btn-outline">
                                                        <i class="fas fa-upload"></i> Upload
                                                    </button>
                                                </div>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <div style="display: flex; justify-content: flex-end; gap: 10px; align-items: center;">
                                        <a href="application-details.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-light">
                                            Open Full Details
                                        </a>
                                        <button 
                                            type="button" 
                                            class="btn btn-sm btn-outline toggle-details-close" 
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
                            <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($statusFilter) ?>" class="pagination-btn">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <div class="pagination-info">
                            Page <?= $page ?> of <?= $totalPages ?> (<?= $totalApplications_filtered ?> total)
                        </div>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($statusFilter) ?>" class="pagination-btn">
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

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const togglePanels = (button, panel, show) => {
            panel.style.display = show ? 'block' : 'none';
            button.setAttribute('aria-expanded', show ? 'true' : 'false');
            button.innerHTML = show
                ? '<i class="fas fa-eye-slash"></i> Hide Details'
                : '<i class="fas fa-eye"></i> View Details';
        };

        document.querySelectorAll('.toggle-details').forEach(button => {
            const targetId = button.getAttribute('data-target');
            const panel = document.getElementById(targetId);
            if (!panel) {
                return;
            }

            // Initialize close buttons inside each panel
            panel.querySelectorAll('.toggle-details-close').forEach(closeBtn => {
                closeBtn.addEventListener('click', () => {
                    togglePanels(button, panel, false);
                });
            });

            button.addEventListener('click', () => {
                const isOpen = panel.style.display === 'block';
                togglePanels(button, panel, !isOpen);
            });
        });
    });
    </script>
    <script src="../../assets/js/toast.js"></script>
    <script src="../../assets/js/office-theme.js"></script>
    <?php include '../../includes/pwa-scripts.php'; ?>
</body>
</html>
