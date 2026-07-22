<?php
require_once '../../config/config.php';
require_once '../../includes/models.php';
require_once '../../includes/services/EmailService.php';
require_once '../../includes/services/RequirementValidator.php';
require_once '../../includes/helpers/InterviewScheduler.php';

requireAdmin();

$applicationModel = new Application();
$userModel = new User();
$positionModel = new Position();
$documentModel = new ApplicationDocument();
$emailService = new EmailService();

// Handle application archive (remove)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_application'])) {
    // Validate form submission (CSRF + Form Token)
    $validation = FormTokenManager::validateFormSubmission($_POST);
    
    if (!$validation['valid']) {
        if ($validation['error'] === 'duplicate') {
            $_SESSION['info_message'] = 'Application already archived.';
        } else {
            $_SESSION['error_message'] = $validation['error'];
        }
        header('Location: applications.php');
        exit();
    }
    
    $applicationId = (int)$_POST['application_id'];
    
    $application = $applicationModel->findById($applicationId);
    
    if (!$application) {
        $_SESSION['error_message'] = 'Application not found.';
        header('Location: applications.php');
        exit();
    }
    
    // Archive the application
    $archived = $applicationModel->archive($applicationId);
    
    if ($archived) {
        // Log activity
        $userModel->logActivity(
            $_SESSION['user_id'],
            'archive_application',
            "Archived application #{$applicationId} for {$application['user_id']}"
        );
        
        $_SESSION['success_message'] = 'Application has been moved to history.';
    } else {
        $_SESSION['error_message'] = 'Failed to archive application. Please try again.';
    }
    
    header('Location: applications.php');
    exit();
}

// Handle user profile data fetch (for modal)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_user_profile') {
    header('Content-Type: application/json');
    
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    
    if ($userId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
        exit();
    }
    
    try {
        $user = $userModel->findById($userId);
        
        if (!$user) {
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit();
        }
        
        // Remove password from user data
        unset($user['password']);
        
        // Fetch related data
        $experienceModel = new Experience();
        $trainingCertificateModel = new TrainingCertificate();
        $applicationDocumentModel = new UserApplicationDocument();
        
        $allExperiences = $experienceModel->getUserExperience($userId);
        $trainingCertificates = [];
        $applicationDocuments = [];
        
        try {
            $trainingCertificates = $trainingCertificateModel->getUserCertificates($userId);
            // Ensure we have an array
            if (!is_array($trainingCertificates)) {
                $trainingCertificates = [];
            }
        } catch (\Exception $e) {
            // Table might not exist
            error_log("Error fetching training certificates: " . $e->getMessage());
            $trainingCertificates = [];
        }
        
        try {
            $applicationDocuments = $applicationDocumentModel->getUserDocuments($userId);
            // Ensure we have an array
            if (!is_array($applicationDocuments)) {
                $applicationDocuments = [];
            }
        } catch (\Exception $e) {
            // Table might not exist
            error_log("Error fetching application documents: " . $e->getMessage());
            $applicationDocuments = [];
        }
        
        // Log for debugging
        error_log("User ID: $userId, Training Certificates: " . count($trainingCertificates) . ", Application Documents: " . count($applicationDocuments));
        error_log("Training Certificates data: " . print_r($trainingCertificates, true));
        error_log("Application Documents data: " . print_r($applicationDocuments, true));
        
        // Ensure arrays are properly formatted
        $response = [
            'success' => true,
            'user' => $user,
            'experiences' => is_array($allExperiences) ? $allExperiences : [],
            'trainingCertificates' => is_array($trainingCertificates) ? $trainingCertificates : [],
            'applicationDocuments' => is_array($applicationDocuments) ? $applicationDocuments : []
        ];
        
        echo json_encode($response, JSON_UNESCAPED_SLASHES);
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch user data: ' . $e->getMessage()]);
    }
    
    exit();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    // Debug logging
    error_log("=== STATUS UPDATE REQUEST ===");
    error_log("POST data: " . print_r($_POST, true));
    
    // Validate form submission (CSRF + Form Token)
    $validation = FormTokenManager::validateFormSubmission($_POST);
    
    if (!$validation['valid']) {
        error_log("Form validation failed: " . $validation['error']);
        if ($validation['error'] === 'duplicate') {
            // Duplicate submission detected
            $_SESSION['info_message'] = 'Status update already processed.';
        } else {
            $_SESSION['error_message'] = $validation['error'];
        }
        header('Location: applications.php');
        exit();
    }
    
    $applicationId = (int)$_POST['application_id'];
    $newStatus = $_POST['status'];
    
    error_log("Application ID: " . $applicationId);
    error_log("New Status: " . $newStatus);
    
    if (in_array($newStatus, [STATUS_PENDING, STATUS_APPROVED, STATUS_REJECTED])) {
        // Get application details before updating
        $application = $applicationModel->findById($applicationId);
        
        if (!$application) {
            $_SESSION['error_message'] = 'Application not found.';
            header('Location: applications.php');
            exit();
        }
        
        // Update status (reviewed_at is handled automatically by the model if column exists)
        $updateData = [
            'status' => $newStatus
        ];
        
        // If status is being approved, schedule interview automatically
        if ($newStatus === STATUS_APPROVED) {
            try {
                // Get database connection from the model
                $db = $applicationModel->getConnection();
                
                // Get next available interview slot
                $interviewSlot = InterviewScheduler::getNextAvailableSlot($db);
                
                // Add interview date and time to update data
                $updateData['interview_date'] = $interviewSlot['date'];
                $updateData['interview_time'] = $interviewSlot['time'];
                
                error_log("Scheduled interview for: " . $interviewSlot['date'] . " at " . $interviewSlot['time']);
            } catch (Exception $e) {
                error_log("Failed to schedule interview: " . $e->getMessage());
                // Continue without interview scheduling if it fails
            }
        }
        
        $updated = $applicationModel->update($applicationId, $updateData);
        
        error_log("Update result: " . ($updated ? 'SUCCESS' : 'FAILED'));
        error_log("Update data: " . print_r($updateData, true));
        
        if (!$updated) {
            error_log("Update failed - stopping execution");
            $_SESSION['error_message'] = 'Failed to update application status. Please try again.';
            header('Location: applications.php');
            exit();
        }
        
        error_log("Update successful, proceeding with email...");
        
        // Automatically archive applications when approved or rejected
        if ($newStatus === STATUS_APPROVED || $newStatus === STATUS_REJECTED) {
            // Archive the application to move it to history
            $archived = $applicationModel->archive($applicationId);
            if ($archived) {
                error_log("Application #{$applicationId} automatically archived after status change to {$newStatus}");
                
                // Log activity
                $userModel->logActivity(
                    $_SESSION['user_id'],
                    'auto_archive_application',
                    "Application #{$applicationId} automatically archived after being {$newStatus}"
                );
            } else {
                error_log("Warning: Failed to auto-archive application #{$applicationId}");
            }
        }
        
        // Send email notification if status is approved or rejected
        if ($newStatus === STATUS_APPROVED || $newStatus === STATUS_REJECTED) {
            try {
                $user = $userModel->findById($application['user_id']);
                $position = $positionModel->findById($application['position_id']);
                
                if (!$user || !$position) {
                    throw new Exception('User or position not found');
                }
                
                // Get updated application
                $updatedApp = $applicationModel->findById($applicationId);
                
                // If approved, send congratulation email
                if ($newStatus === STATUS_APPROVED) {
                    $emailSent = $emailService->sendApplicationApproved($user, $position, $updatedApp);
                } else {
                    // For rejected, send regular status update
                    $emailSent = $emailService->sendApplicationStatusUpdate($user, $position, $updatedApp);
                }
                
                if ($emailSent) {
                    // Success message with email confirmation
                    $statusText = $newStatus === STATUS_APPROVED ? 'approved' : 'rejected';
                    $_SESSION['success_message'] = "Application status updated to {$statusText} and moved to history. Confirmation email has been sent to {$user['email']}!";
                    $_SESSION['email_sent'] = true;
                } else {
                    throw new Exception('Email sending failed');
                }
            } catch (Exception $e) {
                // Log error but still show success for status update
                error_log("Failed to send status update email: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                
                $statusText = $newStatus === STATUS_APPROVED ? 'approved' : 'rejected';
                $_SESSION['success_message'] = "Application status updated to {$statusText} and moved to history, but email notification failed: " . $e->getMessage();
                $_SESSION['email_sent'] = false;
            }
        } else {
            $_SESSION['success_message'] = 'Application status updated to pending.';
        }
        
        header('Location: applications.php');
        exit();
    }
}

// Get filter parameters
$filterStatus = $_GET['status'] ?? 'all';
$filterPosition = $_GET['position'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');

// Get all applications with details (include archived so approved/rejected applications remain visible)
$applications = $applicationModel->getApplicationsWithDetails('', [], true);
$allApplicationsRaw = $applications;

// Apply status filter
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

// ── View-layer ATS metrics (derived from existing data, no additional queries) ──
$positionLocationMap = [];
foreach ($positions as $pos) {
    $positionLocationMap[$pos['id']] = $pos['location'] ?? 'General';
}

if (!function_exists('atsComputeMatchScore')) {
    function atsComputeMatchScore(array $validation): int
    {
        $checks = 0;
        $passed = 0;
        foreach ($validation['details'] ?? [] as $detail) {
            if (isset($detail['meets'])) {
                $checks++;
                if ($detail['meets']) {
                    $passed++;
                }
            }
        }
        if ($checks === 0) {
            return !empty($validation['meets_requirements']) ? 100 : 0;
        }
        return (int) round(($passed / $checks) * 100);
    }
}

$today = date('Y-m-d');
$weekAgo = date('Y-m-d', strtotime('-7 days'));
$atsToday = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$atsWeek = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$pipelineCounts = [
    'pending' => 0,
    'review' => 0,
    'validation' => 0,
    'interview' => 0,
    'approved' => 0,
    'rejected' => 0,
    'archived' => 0,
];
$monthlyChartLabels = [];
$monthlyChartValues = [];
$monthlyChartMap = [];
$departmentChartMap = [];
$processingDaysTotal = 0;
$processingDaysCount = 0;
$interviewScheduledCount = 0;
$shortlistedCount = 0;
$requirementValidatorView = new RequirementValidator();

foreach ($allApplicationsRaw as $metricApp) {
    $appliedDate = date('Y-m-d', strtotime($metricApp['applied_at']));
    $status = $metricApp['status'] ?? STATUS_PENDING;
    $isArchived = !empty($metricApp['is_archived']);

    if ($appliedDate === $today) {
        $atsToday['total']++;
        if (isset($atsToday[$status])) {
            $atsToday[$status]++;
        }
    }
    if ($appliedDate >= $weekAgo) {
        $atsWeek['total']++;
        if (isset($atsWeek[$status])) {
            $atsWeek[$status]++;
        }
    }

    if ($isArchived) {
        $pipelineCounts['archived']++;
    }

    try {
        $metricValidation = $requirementValidatorView->validateRequirements($metricApp['user_id'], $metricApp['position_id']);
        $matchScore = atsComputeMatchScore($metricValidation);
    } catch (\Exception $e) {
        $metricValidation = ['meets_requirements' => false, 'details' => []];
        $matchScore = 0;
    }

    if ($status === STATUS_APPROVED) {
        $pipelineCounts['approved']++;
    } elseif ($status === STATUS_REJECTED) {
        $pipelineCounts['rejected']++;
    } elseif (!empty($metricApp['interview_date'])) {
        $pipelineCounts['interview']++;
        $interviewScheduledCount++;
    } elseif ($matchScore >= 80) {
        $pipelineCounts['validation']++;
        $shortlistedCount++;
    } elseif ($matchScore >= 50) {
        $pipelineCounts['review']++;
    } else {
        $pipelineCounts['pending']++;
    }

    $monthKey = date('M Y', strtotime($metricApp['applied_at']));
    $monthlyChartMap[$monthKey] = ($monthlyChartMap[$monthKey] ?? 0) + 1;

    $deptKey = $positionLocationMap[$metricApp['position_id']] ?? 'General';
    $departmentChartMap[$deptKey] = ($departmentChartMap[$deptKey] ?? 0) + 1;

    $reviewedAt = !empty($metricApp['reviewed_at']) ? $metricApp['reviewed_at'] : ($metricApp['updated_at'] ?? null);
    if ($reviewedAt && $status !== STATUS_PENDING) {
        $days = max(0, (int) floor((strtotime($reviewedAt) - strtotime($metricApp['applied_at'])) / 86400));
        $processingDaysTotal += $days;
        $processingDaysCount++;
    }
}

$monthlyChartLabels = array_keys($monthlyChartMap);
$monthlyChartValues = array_values($monthlyChartMap);
if (count($monthlyChartLabels) > 6) {
    $monthlyChartLabels = array_slice($monthlyChartLabels, -6);
    $monthlyChartValues = array_slice($monthlyChartValues, -6);
}
$departmentChartLabels = array_keys($departmentChartMap);
$departmentChartValues = array_values($departmentChartMap);
$avgProcessingDays = $processingDaysCount > 0 ? round($processingDaysTotal / $processingDaysCount, 1) : 0;
$pipelineTotal = max(1, array_sum($pipelineCounts));
$approvalRate = count($allApplicationsRaw) > 0
    ? round((count(array_filter($allApplicationsRaw, function ($a) { return ($a['status'] ?? '') === STATUS_APPROVED; })) / count($allApplicationsRaw)) * 100, 1)
    : 0;
$adminUser = ['full_name' => $_SESSION['full_name'] ?? 'Admin'];

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
    <title>Applications Management - WPU Applicant System</title>
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/office-theme.css" rel="stylesheet">
    <link href="../../assets/css/admin-dashboard.css" rel="stylesheet">
    <link href="../../assets/css/applications-module.css" rel="stylesheet">
    <style>.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}</style>
</head>
<body class="applications-module admin-dashboard">
<?php require __DIR__ . '/_applications_ats_view.php'; ?>















