<?php
require_once '../../config/config.php';
require_once '../../includes/models.php';

// Prevent caching of this page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

requireAuth();

// Require privacy waiver acceptance
requirePrivacyWaiver();

$positionModel = new Position();
$applicationModel = new Application();

$userId = $_SESSION['user_id'];

// Get position details
$positionId = (int)($_GET['position'] ?? 0);
$position = $positionModel->findById($positionId);

if (!$position || !$position['is_active']) {
    header('Location: ' . BASE_URL . 'applicant/pages/positions.php?error=Position not found or inactive');
    exit();
}

// Check if user already applied
$existingApplication = $applicationModel->findByUserAndPosition($userId, $positionId);
if ($existingApplication) {
    header('Location: ' . BASE_URL . 'applicant/pages/my-applications.php?error=You have already applied for this position');
    exit();
}

// Get user's total experience
$userModel = new User();
$totalExperience = $userModel->getTotalExperience($userId);

// Validate all requirements
require_once '../../includes/services/RequirementValidator.php';
$requirementValidator = new RequirementValidator();
$requirementValidation = $requirementValidator->validateRequirements($userId, $positionId);

// Check if position has CSE requirements
$hasCSERequirement = !empty($position['career_service_eligibilities']);
$cseValidation = $requirementValidation['details']['career_service_eligibilities'] ?? null;
$meetsCSE = $cseValidation ? ($cseValidation['meets'] ?? false) : true; // If no CSE requirement, consider it met

// If CSE requirement exists and is not met, block access to application page
if ($hasCSERequirement && !$meetsCSE) {
    $cseMessage = $cseValidation['message'] ?? 'You do not meet the required Career Service Eligibility for this position.';
    header('Location: ' . BASE_URL . 'applicant/pages/positions.php?error=' . urlencode($cseMessage . ' Please update your profile with the required eligibility.'));
    exit();
}

$message = '';
$error = '';

// Handle application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form submission (CSRF + Form Token)
    $validation = FormTokenManager::validateFormSubmission($_POST);
    
    if (!$validation['valid']) {
        // Provide more specific error messages
        if ($validation['error'] === 'duplicate') {
            // Redirect to avoid resubmission on refresh
            header('Location: ' . BASE_URL . 'applicant/pages/my-applications.php?info=Application already submitted');
            exit();
        }
        $error = $validation['error'];
    } else {
        $coverLetter = sanitize($_POST['cover_letter'] ?? '');
        
        // Validate all requirements comprehensively (re-validate on submit)
        $requirementValidation = $requirementValidator->validateRequirements($userId, $positionId);
        $meetsRequirements = $requirementValidation['meets_requirements'];
        
        // Check CSE requirements again on submit
        $hasCSERequirement = !empty($position['career_service_eligibilities']);
        $cseValidation = $requirementValidation['details']['career_service_eligibilities'] ?? null;
        $meetsCSE = $cseValidation ? ($cseValidation['meets'] ?? false) : true;
        
        // Block submission if CSE requirements are not met
        if ($hasCSERequirement && !$meetsCSE) {
            $cseMessage = $cseValidation['message'] ?? 'You do not meet the required Career Service Eligibility for this position.';
            $error = $cseMessage . ' Please update your profile with the required eligibility before applying.';
        }
        
        // Validate documents before processing (PDF only) - only if no CSE error
        if (empty($error)) {
            $documentErrors = [];
            $documentTypes = ['resume', 'certificate', 'portfolio', 'other'];
            
            foreach ($documentTypes as $docType) {
                if (isset($_FILES['documents']['name'][$docType]) && is_array($_FILES['documents']['name'][$docType])) {
                    $fileCount = count($_FILES['documents']['name'][$docType]);
                    
                    for ($i = 0; $i < $fileCount; $i++) {
                        if ($_FILES['documents']['error'][$docType][$i] === UPLOAD_ERR_OK) {
                            $originalName = $_FILES['documents']['name'][$docType][$i];
                            $tmpName = $_FILES['documents']['tmp_name'][$docType][$i];
                            
                            // Validate PDF file type by extension
                            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                            if ($extension !== 'pdf') {
                                $documentErrors[] = $originalName . ' is not a PDF file. Only PDF files are allowed.';
                                continue;
                            }
                            
                            // Validate MIME type
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mimeType = finfo_file($finfo, $tmpName);
                            finfo_close($finfo);
                            
                            if ($mimeType !== 'application/pdf') {
                                $documentErrors[] = $originalName . ' is not a valid PDF file. Only PDF files are allowed.';
                                continue;
                            }
                        }
                    }
                }
            }
            
            // If there were document validation errors, show error and don't create application
            if (!empty($documentErrors)) {
                $error = $documentErrors[0];
                if (count($documentErrors) > 1) {
                    $error .= ' (and ' . (count($documentErrors) - 1) . ' more file(s))';
                }
            }
        }
        
        // Only proceed with application creation if no errors
        if (empty($error)) {
            // Handle single resume file (for backward compatibility)
            $resumeFile = null;
            if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/resumes/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = time() . '_' . $userId . '_' . basename($_FILES['resume']['name']);
                $uploadPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['resume']['tmp_name'], $uploadPath)) {
                    $resumeFile = $fileName;
                }
            }
            
            try {
                $applicationData = [
                    'user_id' => $userId,
                    'position_id' => $positionId,
                    'cover_letter' => $coverLetter,
                    'resume_file' => $resumeFile,
                    'total_experience_years' => $totalExperience,
                    'meets_requirements' => $meetsRequirements
                ];
                
                if ($applicationModel->create($applicationData)) {
                    // Get the application ID
                    $applicationId = $applicationModel->findByUserAndPosition($userId, $positionId)['id'];
                    
                    // Handle multiple document uploads (now that we know they're valid PDFs)
                    $documentModel = new ApplicationDocument();
                    $uploadDir = 'uploads/documents/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    foreach ($documentTypes as $docType) {
                        if (isset($_FILES['documents']['name'][$docType]) && is_array($_FILES['documents']['name'][$docType])) {
                            $fileCount = count($_FILES['documents']['name'][$docType]);
                            
                            for ($i = 0; $i < $fileCount; $i++) {
                                if ($_FILES['documents']['error'][$docType][$i] === UPLOAD_ERR_OK) {
                                    $originalName = $_FILES['documents']['name'][$docType][$i];
                                    $tmpName = $_FILES['documents']['tmp_name'][$docType][$i];
                                    $fileSize = $_FILES['documents']['size'][$docType][$i];
                                    
                                    $fileName = time() . '_' . $userId . '_' . uniqid() . '_' . basename($originalName);
                                    $uploadPath = $uploadDir . $fileName;
                                    
                                    if (move_uploaded_file($tmpName, $uploadPath)) {
                                        $documentModel->create([
                                            'application_id' => $applicationId,
                                            'document_type' => $docType,
                                            'file_name' => basename($originalName),
                                            'file_path' => $fileName,
                                            'file_size' => $fileSize
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                    
                    // Get user and application for notifications
                    $user = $userModel->findById($userId);
                    $application = $applicationModel->findByUserAndPosition($userId, $positionId);
                    
                    // Create in-app notifications for all admins
                    try {
                        $notificationModel = new Notification();
                        $notificationModel->notifyAllAdmins(
                            'application_submitted',
                            'New Application Submitted',
                            "{$user['full_name']} has submitted an application for {$position['title']}",
                            $application['id'],
                            'application'
                        );
                    } catch (Exception $e) {
                        error_log("Failed to create admin notifications: " . $e->getMessage());
                    }
                    
                    // Send confirmation email
                    try {
                        require_once '../../includes/services/EmailService.php';
                        $emailService = new EmailService();
                        
                        $emailService->sendApplicationSubmitted($user, $position, $application);
                        
                        header('Location: ' . BASE_URL . 'applicant/pages/my-applications.php?message=Application submitted successfully! A confirmation email has been sent to your email address.');
                    } catch (Exception $e) {
                        error_log("Failed to send application confirmation email: " . $e->getMessage());
                        header('Location: ' . BASE_URL . 'applicant/pages/my-applications.php?message=Application submitted successfully!');
                    }
                    exit();
                } else {
                    $error = 'Failed to submit application';
                }
            } catch (Exception $e) {
                $error = 'An error occurred: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Apply for <?= htmlspecialchars($position['title']) ?> - WPU Applicant System</title>
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
                <i class="fas fa-graduation-cap"></i>
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
            <h1 class="topbar-title">Apply for Position</h1>
            <div class="topbar-actions">
                <?php include '../../includes/applicant-theme-toggle.php'; ?>
                <a href="positions.php" class="topbar-btn">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content">
            <div class="card" style="max-width: 900px; margin: 0 auto;">
                <!-- Position Header -->
                <div class="card-header" style="background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%); color: white; border: none; padding: 24px;">
                        <h3 style="margin-bottom: 12px; font-size: 1.5rem; font-weight: 700;"><?= htmlspecialchars($position['title']) ?></h3>
                        <div style="display: flex; flex-wrap: wrap; gap: 16px; font-size: 0.9rem; opacity: 0.95;">
                            <?php if ($position['location']): ?>
                                <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($position['location']) ?></span>
                            <?php endif; ?>
                            <span><i class="fas fa-briefcase"></i> <?= ucfirst($position['employment_type']) ?></span>
                            <?php if ($position['deadline']): ?>
                                <span><i class="fas fa-calendar"></i> Deadline: <?= date('M j, Y', strtotime($position['deadline'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div><?= htmlspecialchars($error) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($message): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <div><?= htmlspecialchars($message) ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- Comprehensive Requirements Check -->
                        <div class="alert <?= $requirementValidation['meets_requirements'] ? 'alert-success' : 'alert-warning' ?>" style="margin-bottom: 20px;">
                            <div style="display: flex; align-items: start; gap: 12px;">
                                <i class="fas fa-<?= $requirementValidation['meets_requirements'] ? 'check-circle' : 'exclamation-triangle' ?>" style="font-size: 1.2rem;"></i>
                                <div style="flex: 1;">
                                    <strong style="display: block; margin-bottom: 12px; font-size: 1.1rem;">
                                        <?= $requirementValidation['meets_requirements'] ? 'You Meet All Requirements' : 'Requirement Status' ?>
                                    </strong>
                                    
                                    <?php foreach ($requirementValidation['details'] as $key => $detail): ?>
                                        <?php if (isset($detail['meets'])): ?>
                                            <div style="margin-bottom: 12px; padding: 10px; background: rgba(0,0,0,0.02); border-radius: 6px; border-left: 3px solid <?= $detail['meets'] ? '#28a745' : '#ffc107' ?>;">
                                                <div style="display: flex; align-items: start; gap: 8px; margin-bottom: 4px;">
                                                    <i class="fas fa-<?= $detail['meets'] ? 'check-circle' : 'times-circle' ?>" style="color: <?= $detail['meets'] ? '#28a745' : '#ffc107' ?>; margin-top: 2px;"></i>
                                                    <div style="flex: 1;">
                                                        <strong style="display: block; margin-bottom: 4px; text-transform: capitalize;">
                                                            <?= str_replace('_', ' ', $key) ?>
                                                        </strong>
                                                        <div style="font-size: 0.9rem; color: var(--gray-600);">
                                                            <?= htmlspecialchars($detail['message']) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php elseif (isset($detail['info'])): ?>
                                            <div style="margin-bottom: 8px; padding: 8px; background: rgba(0,0,0,0.02); border-radius: 6px;">
                                                <small style="font-size: 0.9rem; color: var(--gray-600);">
                                                    <i class="fas fa-info-circle"></i> <?= htmlspecialchars($detail['info']) ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    
                                    <?php 
                                    $cseValidation = $requirementValidation['details']['career_service_eligibilities'] ?? null;
                                    $hasCSERequirement = !empty($position['career_service_eligibilities']);
                                    $meetsCSE = $cseValidation ? ($cseValidation['meets'] ?? false) : true;
                                    ?>
                                    <?php if (!$requirementValidation['meets_requirements']): ?>
                                        <div style="margin-top: 12px; padding: 10px; background: <?= ($hasCSERequirement && !$meetsCSE) ? 'rgba(220,53,69,0.1)' : 'rgba(255,193,7,0.1)' ?>; border-radius: 6px; border-left: 3px solid <?= ($hasCSERequirement && !$meetsCSE) ? '#dc3545' : '#ffc107' ?>;">
                                            <small style="display: block; color: var(--gray-700);">
                                                <i class="fas fa-<?= ($hasCSERequirement && !$meetsCSE) ? 'times-circle' : 'info-circle' ?>"></i> 
                                                <strong>Note:</strong> 
                                                <?php if ($hasCSERequirement && !$meetsCSE): ?>
                                                    <strong style="color: #dc3545;">You must meet the Career Service Eligibility requirement to apply for this position.</strong> 
                                                    Please update your profile with the required eligibility.
                                                <?php else: ?>
                                                    You can still submit your application even if you don't meet all requirements. 
                                                    However, applications that meet all requirements will be given priority consideration.
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Job Description -->
                        <div style="margin-bottom: 24px;">
                            <h5 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 12px;">
                                <i class="fas fa-file-alt"></i> Job Description
                            </h5>
                            <p style="color: var(--gray-600); line-height: 1.6;"><?= nl2br(htmlspecialchars($position['description'])) ?></p>
                        </div>

                        <!-- Requirements -->
                        <?php if ($position['requirements']): ?>
                            <div style="margin-bottom: 24px;">
                                <h5 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 12px;">
                                    <i class="fas fa-list"></i> Requirements
                                </h5>
                                <p style="color: var(--gray-600); line-height: 1.6;"><?= nl2br(htmlspecialchars($position['requirements'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <?php if ($position['salary_range']): ?>
                            <?php
                                $salaryRangeDisplay = $position['salary_range'];
                                if (!preg_match('/(₱|PHP|PESO)/i', $salaryRangeDisplay)) {
                                    $salaryRangeDisplay = 'PHP ' . $salaryRangeDisplay;
                                }
                            ?>
                            <div style="margin-bottom: 24px;">
                                <h5 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 12px;">
                                    <i class="fas fa-money-bill-wave"></i> Salary Range (PHP)
                                </h5>
                                <p style="color: var(--gray-600);"><?= htmlspecialchars($salaryRangeDisplay) ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <hr style="margin: 24px 0; border-color: var(--gray-200);"
>

                        <!-- Application Form -->
                        <form method="POST" enctype="multipart/form-data" id="applicationForm" class="prevent-double-submit">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <?php FormTokenManager::renderFormTokenInput('apply_position'); ?>
                            
                            <h5 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 20px;">
                                <i class="fas fa-edit"></i> Submit Your Application
                            </h5>
                            
                            <div class="form-group">
                                <label class="form-label">Cover Letter (Optional)</label>
                                <textarea class="form-control" name="cover_letter" rows="6" 
                                         placeholder="Tell us why you're the perfect fit for this position..."><?= htmlspecialchars($_POST['cover_letter'] ?? '') ?></textarea>
                                <small style="display: block; margin-top: 6px; color: var(--gray-500);">
                                    <i class="fas fa-lightbulb"></i> 
                                    Tip: Mention specific skills and experiences that align with the job requirements.
                                </small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Personal Data Sheet *</label>
                                <input type="file" class="form-control" name="resume" 
                                       accept=".pdf,.doc,.docx" required>
                                <small style="display: block; margin-top: 6px; color: var(--gray-500);">
                                    <i class="fas fa-info-circle"></i> 
                                    Personal Data Sheet (CSC Form 212).
                                </small>
                            </div>

                            <hr style="margin: 24px 0; border-color: var(--gray-200);">

                            <h5 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 16px;">
                                <i class="fas fa-paperclip"></i> Additional Documents (Optional)
                            </h5>
                            <p style="color: var(--gray-600); margin-bottom: 20px; font-size: 0.9rem;">
                                Upload supporting documents to strengthen your application. You can upload multiple PDF files for each category. Only PDF files are allowed.
                            </p>

                           

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-certificate"></i> Certificates
                                </label>
                                <input type="file" class="form-control" name="documents[certificate][]" 
                                       accept=".pdf,application/pdf" multiple>
                                <small style="display: block; margin-top: 6px; color: var(--gray-500);">
                                    Certifications, training, awards, etc. (PDF files only)
                                </small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-folder"></i> Eligibilty
                                </label>
                                <input type="file" class="form-control" name="documents[portfolio][]" 
                                       accept=".pdf,application/pdf" multiple>
                                <small style="display: block; margin-top: 6px; color: var(--gray-500);">
                                Civil Service Commission (CSC), PRC license, etc 
                                </small>
                            </div>

                            <!-- <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-file"></i> Other Documents
                                </label>
                                <input type="file" class="form-control" name="documents[other][]" 
                                       accept=".pdf,application/pdf" multiple>
                                <small style="display: block; margin-top: 6px; color: var(--gray-500);">
                                    Any other relevant documents (PDF files only)
                                </small>
                            </div> -->

                            <div style="display: flex; gap: 12px; justify-content: flex-end; flex-wrap: wrap;">
                                <a href="positions.php" class="btn btn-outline">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Submit Application
                                </button>
                            </div>
                        </form>
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
    <script src="../../assets/js/form-submission-manager.js"></script>
    <script src="../../assets/js/office-theme.js"></script>
    <script src="../../assets/js/applicant-theme.js"></script>
    <script>
        // Prevent cached page issues - reload if page was loaded from back/forward cache
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                window.location.reload(true);
            }
        });
    </script>
    <?php include '../../includes/pwa-scripts.php'; ?>
</body>
</html>