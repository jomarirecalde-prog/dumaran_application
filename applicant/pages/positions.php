<?php
require_once '../../config/config.php';
requireAuth();

// Redirect admin to admin dashboard
if (hasRole(ROLE_ADMIN)) {
    header('Location: ' . BASE_URL . 'admin/pages/positions.php');
    exit();
}

// Require privacy waiver acceptance
requirePrivacyWaiver();

require_once '../../includes/models.php';
require_once '../../includes/services/RequirementValidator.php';

$positionModel = new Position();
$applicationModel = new Application();
$userModel = new User();
$requirementValidator = new RequirementValidator();

$userId = $_SESSION['user_id'];
$user = $userModel->findById($userId);

// Get filters
$searchTerm = $_GET['search'] ?? '';
$employmentType = $_GET['type'] ?? '';
$minExperience = $_GET['experience'] ?? '';

// Build query
$where = 'is_active = 1 AND (deadline IS NULL OR deadline >= CURDATE())';
$params = [];

if (!empty($searchTerm)) {
    $where .= ' AND (title LIKE ? OR description LIKE ? OR location LIKE ?)';
    $searchParam = '%' . $searchTerm . '%';
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if (!empty($employmentType)) {
    $where .= ' AND employment_type = ?';
    $params[] = $employmentType;
}

if (!empty($minExperience)) {
    $where .= ' AND min_experience_years <= ?';
    $params[] = $minExperience;
}

$allPositions = $positionModel->findAll($where, $params);
$userApplications = $applicationModel->getUserApplications($userId);
$totalExperience = $userModel->getTotalExperience($userId);

// Create a lookup array for applied positions
$appliedPositions = [];
foreach ($userApplications as $app) {
    $appliedPositions[$app['position_id']] = $app['status'];
}

// Get application counts for all positions
$positionsWithCounts = [];
foreach ($allPositions as $pos) {
    $pos['application_count'] = $positionModel->getApplicationCount($pos['id']);
    $positionsWithCounts[] = $pos;
}
$allPositions = $positionsWithCounts;

// Pagination setup
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 6;
$offset = ($page - 1) * $perPage;

// Calculate pagination
$totalPositions_filtered = count($allPositions);
$totalPages = ceil($totalPositions_filtered / $perPage);
$positions = array_slice($allPositions, $offset, $perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Browse Jobs - WPU Applicant System</title>
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/office-theme.css" rel="stylesheet">
    <?php include '../../includes/pwa-header.php'; ?>
    <style>
        /* Positions grid container */
        .positions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        /* Position card styles */
        .position-item {
            padding: 20px;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            background: white;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
        }
        
        .position-item:hover {
            background: var(--gray-50);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        /* Compact mobile layout */
        @media (max-width: 768px) {
            .positions-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .position-item {
                padding: 16px;
            }
        }
        
        /* Tablet layout */
        @media (min-width: 769px) and (max-width: 1024px) {
            .positions-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
            padding: 20px;
        }
        
        .modal-overlay.active {
            display: flex;
            align-items: flex-start;
            justify-content: center;
        }
        
        .modal-container {
            background: white;
            border-radius: 12px;
            width: 100%;
            max-width: 900px;
            margin: 20px auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
            padding: 24px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .modal-header-content h2 {
            margin: 0 0 12px 0;
            font-size: 1.75rem;
            font-weight: 700;
        }
        
        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: background 0.2s;
        }
        
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-body > div {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        
        .modal-section {
            margin-bottom: 24px;
        }
        
        .modal-section:last-child {
            margin-bottom: 0;
        }
        
        .modal-section h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .modal-requirements-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .modal-requirements-list li {
            padding: 12px 16px;
            background: var(--gray-50);
            border-radius: 8px;
            margin-bottom: 12px;
            display: flex;
            align-items: start;
            gap: 12px;
        }
        
        .modal-requirements-list li:last-child {
            margin-bottom: 0;
        }
        
        .modal-requirements-list li i {
            color: var(--primary-blue);
            margin-top: 4px;
        }
        
        .modal-status-card {
            background: var(--gray-50);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            margin-bottom: 16px;
        }
        
        .modal-status-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-blue);
        }
        
        .modal-status-label {
            font-size: 0.9rem;
            color: var(--gray-500);
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .modal-overlay {
                padding: 10px;
            }
            
            .modal-container {
                margin: 10px auto;
                max-height: 95vh;
            }
            
            .modal-header {
                padding: 20px;
            }
            
            .modal-header-content h2 {
                font-size: 1.5rem;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .modal-body > div {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
    </style>
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
                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
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
            <h1 class="topbar-title">Browse Jobs</h1>
            <div class="topbar-actions">
                
                <a href="../../auth/logout.php" class="topbar-btn confirm-logout" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content">
            <!-- Statistics Cards -->
            

            <!-- Search and Filters -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-body">
                    <form method="GET" action="" id="searchForm">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" 
                                   placeholder="Search by title, description, or location..." 
                                   value="<?= htmlspecialchars($searchTerm) ?>">
                        </div>
                        <div class="filter-chips">
                            <a href="positions.php" class="filter-chip <?= empty($employmentType) ? 'active' : '' ?>">All Types</a>
                            <a href="?type=full-time<?= !empty($searchTerm) ? '&search='.urlencode($searchTerm) : '' ?>" class="filter-chip <?= $employmentType === 'full-time' ? 'active' : '' ?>">Full Time</a>
                            <a href="?type=part-time<?= !empty($searchTerm) ? '&search='.urlencode($searchTerm) : '' ?>" class="filter-chip <?= $employmentType === 'part-time' ? 'active' : '' ?>">Part Time</a>
                            <a href="?type=contract<?= !empty($searchTerm) ? '&search='.urlencode($searchTerm) : '' ?>" class="filter-chip <?= $employmentType === 'contract' ? 'active' : '' ?>">Contract</a>
                            <a href="?type=internship<?= !empty($searchTerm) ? '&search='.urlencode($searchTerm) : '' ?>" class="filter-chip <?= $employmentType === 'internship' ? 'active' : '' ?>">Internship</a>
                        </div>
                        <input type="hidden" name="type" value="<?= htmlspecialchars($employmentType) ?>">
                        <input type="hidden" name="experience" value="<?= htmlspecialchars($minExperience) ?>">
                    </form>
                </div>
            </div>

            <!-- Positions List -->
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="card-title">
                        <i class="fas fa-briefcase"></i> Job Openings
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($positions)): ?>
                        <div class="empty-state">
                            <i class="fas fa-search"></i>
                            <h3>No Positions Found</h3>
                            <p>No positions match your search criteria. Try adjusting your filters.</p>
                            <a href="positions.php" class="btn btn-primary">
                                <i class="fas fa-refresh"></i> Clear Filters
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="positions-grid">
                            <?php foreach ($positions as $position): ?>
                                <?php
                                $hasApplied = isset($appliedPositions[$position['id']]);
                                $applicationStatus = $hasApplied ? $appliedPositions[$position['id']] : null;
                                
                                // Validate all requirements including CSE
                                $requirementValidation = $requirementValidator->validateRequirements($userId, $position['id']);
                                $meetsRequirements = $requirementValidation['meets_requirements'];
                                $cseValidation = $requirementValidation['details']['career_service_eligibilities'] ?? null;
                                $meetsCSE = $cseValidation ? ($cseValidation['meets'] ?? false) : true; // If no CSE requirement, consider it met
                                $hasCSERequirement = !empty($position['career_service_eligibilities']);
                                
                                // Prepare data for modal
                                $salaryRangeDisplay = $position['salary_range'] ?? '';
                                if ($salaryRangeDisplay && !preg_match('/(₱|PHP|PESO)/i', $salaryRangeDisplay)) {
                                    $salaryRangeDisplay = 'PHP ' . $salaryRangeDisplay;
                                }
                                
                                // Parse applicant CSE for display
                                $applicantCSE = [];
                                if (!empty($user['career_service_eligibilities'])) {
                                    $applicantCSE = array_map('trim', explode(',', $user['career_service_eligibilities']));
                                }
                                
                                // Parse required CSE for display
                                $requiredCSE = [];
                                if (!empty($position['career_service_eligibilities'])) {
                                    $requiredCSE = array_map('trim', explode(',', $position['career_service_eligibilities']));
                                }
                                
                                $positionData = [
                                    'id' => $position['id'],
                                    'title' => htmlspecialchars($position['title'], ENT_QUOTES),
                                    'description' => htmlspecialchars($position['description'] ?? '', ENT_QUOTES),
                                    'requirements' => htmlspecialchars($position['requirements'] ?? '', ENT_QUOTES),
                                    'employment_type' => htmlspecialchars($position['employment_type'] ?? '', ENT_QUOTES),
                                    'location' => htmlspecialchars($position['location'] ?? '', ENT_QUOTES),
                                    'min_experience_years' => $position['min_experience_years'] ?? 0,
                                    'salary_range' => htmlspecialchars($salaryRangeDisplay, ENT_QUOTES),
                                    'salary_grade' => htmlspecialchars($position['salary_grade'] ?? '', ENT_QUOTES),
                                    'training_hours' => $position['training_hours'] ?? null,
                                    'career_service_eligibilities' => htmlspecialchars($position['career_service_eligibilities'] ?? '', ENT_QUOTES),
                                    'deadline' => $position['deadline'] ? date('M j, Y', strtotime($position['deadline'])) : '',
                                    'deadline_raw' => $position['deadline'] ?? null,
                                    'max_applications' => $position['max_applications'] ?? null,
                                    'application_count' => $position['application_count'] ?? 0,
                                    'has_applied' => $hasApplied ? '1' : '0',
                                    'application_status' => $applicationStatus ? htmlspecialchars($applicationStatus, ENT_QUOTES) : '',
                                    'meets_requirements' => $meetsRequirements ? '1' : '0',
                                    'meets_cse' => $meetsCSE ? '1' : '0',
                                    'has_cse_requirement' => $hasCSERequirement ? '1' : '0',
                                    'required_cse' => json_encode($requiredCSE),
                                    'applicant_cse' => json_encode($applicantCSE),
                                    'cse_validation_message' => $cseValidation ? htmlspecialchars($cseValidation['message'] ?? '', ENT_QUOTES) : '',
                                    'user_experience' => $totalExperience,
                                    'apply_url' => 'apply.php?position=' . $position['id']
                                ];
                                ?>
                                <div class="position-item" 
                                     data-position='<?= json_encode($positionData) ?>'>
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                                        <div style="flex: 1;">
                                            <h4 style="margin: 0 0 8px 0; font-size: 1.1rem; font-weight: 600; color: var(--gray-800);">
                                                <?= htmlspecialchars($position['title']) ?>
                                            </h4>
                                            <div style="margin-bottom: 8px;">
                                                <?php if ($hasApplied): ?>
                                                    <span class="badge badge-success" style="margin-right: 8px;">Applied (<?= ucfirst($applicationStatus) ?>)</span>
                                                <?php elseif (!$meetsRequirements): ?>
                                                    <?php if ($hasCSERequirement && !$meetsCSE): ?>
                                                        <span class="badge badge-danger" style="margin-right: 8px;" title="Missing required Career Service Eligibility">
                                                            <i class="fas fa-times-circle"></i> Missing CSE Requirement
                                                        </span>
                                                    <?php elseif (!$meetsRequirements): ?>
                                                        <span class="badge badge-warning" style="margin-right: 8px;" title="Below required experience">
                                                            <i class="fas fa-exclamation-triangle"></i> Insufficient Experience
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <span class="badge badge-primary">
                                                    <?= ucfirst($position['employment_type']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div style="font-size: 0.85rem; color: var(--gray-600); margin-bottom: 12px; display: flex; flex-direction: column; gap: 6px;">
                                        <div style="display: flex; flex-wrap: wrap; gap: 12px;">
                                            <?php if ($position['location']): ?>
                                                <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($position['location']) ?></span>
                                            <?php endif; ?>
                                            <span><i class="fas fa-clock"></i> Min. <?= $position['min_experience_years'] ?>y exp</span>
                                        </div>
                                        <?php if ($position['salary_range']): ?>
                                            <span><i class="fas fa-money-bill-wave"></i> <?= htmlspecialchars($salaryRangeDisplay) ?></span>
                                        <?php endif; ?>
                                        <?php if ($position['deadline']): ?>
                                            <span><i class="fas fa-calendar"></i> Deadline: <?= date('M j, Y', strtotime($position['deadline'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div style="font-size: 0.9rem; color: var(--gray-700); margin-bottom: 16px; flex: 1; line-height: 1.5;">
                                        <?= nl2br(htmlspecialchars(substr($position['description'], 0, 120))) ?><?= strlen($position['description']) > 120 ? '...' : '' ?>
                                    </div>
                                    
                                    <div style="display: flex; gap: 8px; margin-top: auto; padding-top: 12px; border-top: 1px solid var(--gray-200); justify-content: flex-start;">
                                        <button type="button" class="btn btn-sm btn-outline view-details-btn" 
                                                style="padding: 6px 12px; font-size: 0.8rem;"
                                                data-position-id="<?= $position['id'] ?>">
                                            <i class="fas fa-info-circle"></i> Details
                                        </button>
                                        <?php if (!$hasApplied && $meetsRequirements): ?>
                                            <a href="apply.php?position=<?= $position['id'] ?>" class="btn btn-sm btn-primary" style="padding: 6px 12px; font-size: 0.8rem;">
                                                <i class="fas fa-paper-plane"></i> Apply
                                            </a>
                                        <?php elseif ($hasApplied): ?>
                                            <span class="btn btn-sm btn-outline" style="padding: 6px 12px; font-size: 0.8rem; cursor: default; opacity: 0.7;">
                                                <i class="fas fa-check"></i> Applied
                                            </span>
                                        <?php else: ?>
                                            <?php 
                                            $lockReason = "You don't meet the requirements";
                                            if ($hasCSERequirement && !$meetsCSE) {
                                                $lockReason = "You don't meet the required Career Service Eligibility";
                                            } elseif (!$meetsRequirements) {
                                                $lockReason = "You don't meet the minimum experience requirement";
                                            }
                                            ?>
                                            <span class="btn btn-sm btn-outline" style="padding: 6px 12px; font-size: 0.8rem; cursor: not-allowed; opacity: 0.7;" title="<?= htmlspecialchars($lockReason) ?>">
                                                <i class="fas fa-lock"></i> Locked
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="card-footer" style="display: flex; justify-content: center; align-items: center; padding: 16px; border-top: 1px solid var(--gray-200);">
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($searchTerm) ?>&type=<?= urlencode($employmentType) ?>&experience=<?= urlencode($minExperience) ?>" class="pagination-btn">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <div class="pagination-info">
                            Page <?= $page ?> of <?= $totalPages ?> (<?= $totalPositions_filtered ?> total)
                        </div>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($searchTerm) ?>&type=<?= urlencode($employmentType) ?>&experience=<?= urlencode($minExperience) ?>" class="pagination-btn">
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

    <!-- Position Details Modal -->
    <div class="modal-overlay" id="positionModal">
        <div class="modal-container">
            <div class="modal-header">
                <div class="modal-header-content">
                    <h2 id="modalTitle"></h2>
                </div>
                <button class="modal-close" id="modalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div>
                    <div>
                        <div class="modal-section">
                            <h3>
                                <i class="fas fa-info-circle"></i> Job Description
                            </h3>
                            <div style="margin-bottom: 20px;">
                                <div style="margin-bottom: 16px;">
                                    <strong style="color: var(--gray-800); display: block; margin-bottom: 8px;">Position Title *</strong>
                                    <div id="modalTitleValue" style="color: var(--gray-700); font-size: 1.1rem;"></div>
                                </div>
                                
                                <div style="margin-bottom: 16px;">
                                    <strong style="color: var(--gray-800); display: block; margin-bottom: 8px;">Employment Type</strong>
                                    <div id="modalEmploymentType" style="color: var(--gray-700);"></div>
                                </div>
                                
                                <div style="margin-bottom: 16px;">
                                    <strong style="color: var(--gray-800); display: block; margin-bottom: 8px;">Job Description *</strong>
                                    <div id="modalDescription" style="line-height: 1.6; color: var(--gray-700); white-space: pre-wrap;"></div>
                                </div>

                                <div style="margin-bottom: 16px; margin-top: 16px; display: none;" id="modalRequirementsSection">
                                    <strong style="color: var(--gray-800); display: block; margin-bottom: 8px;">Requirements *</strong>
                                    <ul class="modal-requirements-list" id="modalRequirements"></ul>
                                </div>

                                <div style="margin-bottom: 16px; margin-top: 16px;">
                                    <strong style="color: var(--gray-800); display: block; margin-bottom: 8px;">Minimum Experience (years)</strong>
                                    <div id="modalMinExperience" style="color: var(--gray-700);"></div>
                                </div>

                                <div style="margin-bottom: 16px;" id="modalMaxApplicationsSection">
                                    <strong style="color: var(--gray-800); display: block; margin-bottom: 8px;">Max Applications (optional)</strong>
                                    <div id="modalMaxApplications" style="color: var(--gray-700);"></div>
                                </div>

                                <div style="margin-bottom: 16px;" id="modalSalaryRangeSection">
                                    <strong style="color: var(--gray-800); display: block; margin-bottom: 8px;">Salary Range (PHP)</strong>
                                    <div id="modalSalaryRange" style="color: var(--gray-700);"></div>
                                </div>

                                <div style="margin-bottom: 16px;" id="modalSalaryGradeSection">
                                    <strong style="color: var(--gray-800); display: block; margin-bottom: 8px;">Salary Grade</strong>
                                    <div id="modalSalaryGrade" style="color: var(--gray-700);"></div>
                                </div>

                                <div style="margin-bottom: 16px;" id="modalTrainingHoursSection">
                                    <strong style="color: var(--gray-800); display: block; margin-bottom: 8px;">Training Hours</strong>
                                    <div id="modalTrainingHours" style="color: var(--gray-700);"></div>
                                </div>

                                <div style="margin-bottom: 16px;" id="modalLocationSection">
                                    <strong style="color: var(--gray-800); display: block; margin-bottom: 8px;">Location</strong>
                                    <div id="modalLocation" style="color: var(--gray-700);"></div>
                                </div>

                                <div style="margin-bottom: 16px;" id="modalCSESection">
                                    <strong style="color: var(--gray-800); display: block; margin-bottom: 8px;">CAREER SERVICE ELIGIBILITIES (CSE)</strong>
                                    <div style="margin-top: 8px; padding: 12px; background: var(--gray-50); border-radius: 6px; border: 1px solid var(--gray-200);">
                                        <div id="modalCSE" style="color: var(--gray-700);"></div>
                                    </div>
                                    <small style="color: var(--gray-500); font-size: 0.85rem; margin-top: 8px; display: block;">
                                        Check all required career service eligibility for this position.
                                    </small>
                                </div>

                                <div style="margin-bottom: 16px;" id="modalDeadlineSection">
                                    <strong style="color: var(--gray-800); display: block; margin-bottom: 8px;">Application Deadline</strong>
                                    <div id="modalDeadline" style="color: var(--gray-700);"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="modal-section">
                            <h3>
                                <i class="fas fa-user-check"></i> Your Status
                            </h3>
                            <div class="modal-status-card">
                                <div class="modal-status-value" id="modalUserExperience"></div>
                                <div class="modal-status-label">Your Experience (Years)</div>
                            </div>
                            
                            <div class="modal-status-card">
                                <div class="modal-status-value" id="modalRequiredExperience"></div>
                                <div class="modal-status-label">Required Experience (Years)</div>
                            </div>
                            
                            <div id="modalRequirementStatus"></div>
                            
                            <div class="modal-actions" id="modalActions"></div>
                        </div>

                        <div class="modal-section" id="modalApplicationsSection" style="display: none;">
                            <h3>
                                <i class="fas fa-users"></i> Applications
                            </h3>
                            <div style="background: var(--gray-200); height: 8px; border-radius: 4px; overflow: hidden; margin-bottom: 12px;">
                                <div id="modalApplicationProgress" style="height: 100%; background: var(--info);"></div>
                            </div>
                            <p style="text-align: center; color: var(--gray-600); font-size: 0.875rem; margin: 0;" id="modalApplicationCount"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/toast.js"></script>
    <script src="../../assets/js/office-theme.js"></script>
    <script>
        // Modal functionality
        const modal = document.getElementById('positionModal');
        const modalClose = document.getElementById('modalClose');
        const viewDetailsButtons = document.querySelectorAll('.view-details-btn');

        // Close modal handlers
        modalClose.addEventListener('click', closeModal);
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal();
            }
        });

        // ESC key to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeModal();
            }
        });

        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        function openModal(positionData) {
            // Set title
            const modalTitleElement = document.querySelector('.modal-header-content h2');
            modalTitleElement.textContent = positionData.title;
            document.getElementById('modalTitleValue').textContent = positionData.title;
            
            // Set employment type
            const employmentType = positionData.employment_type.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            document.getElementById('modalEmploymentType').textContent = employmentType;
            
            // Set description
            const description = document.getElementById('modalDescription');
            description.textContent = positionData.description || '';
            
            // Set requirements
            const requirementsSection = document.getElementById('modalRequirementsSection');
            const requirementsList = document.getElementById('modalRequirements');
            if (positionData.requirements && positionData.requirements.trim()) {
                requirementsSection.style.display = 'block';
                const requirements = positionData.requirements.split('\n').filter(req => req.trim());
                requirementsList.innerHTML = requirements.map(req => {
                    const reqText = req.trim();
                    const temp = document.createElement('div');
                    temp.textContent = reqText;
                    return `<li><i class="fas fa-check-circle"></i><span>${temp.innerHTML}</span></li>`;
                }).join('');
            } else {
                requirementsSection.style.display = 'none';
            }
            
            // Set minimum experience
            document.getElementById('modalMinExperience').textContent = positionData.min_experience_years || '0';
            
            // Set max applications
            document.getElementById('modalMaxApplications').textContent = positionData.max_applications || 'N/A';
            
            // Set salary range
            document.getElementById('modalSalaryRange').textContent = positionData.salary_range || 'N/A';
            
            // Set salary grade
            document.getElementById('modalSalaryGrade').textContent = positionData.salary_grade || 'N/A';
            
            // Set training hours
            document.getElementById('modalTrainingHours').textContent = positionData.training_hours || 'N/A';
            
            // Set location
            document.getElementById('modalLocation').textContent = positionData.location || 'N/A';
            
            // Set Career Service Eligibilities
            const cseElement = document.getElementById('modalCSE');
            // Helper function to escape HTML
            const escapeHtml = (text) => {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            };
            
            if (positionData.career_service_eligibilities && positionData.career_service_eligibilities.trim()) {
                const cseValues = positionData.career_service_eligibilities.split(',').map(v => v.trim());
                const cseLabels = {
                    'Professional Eligibility': 'Professional Eligibility: For first-level (clerical, trades) and second-level (technical, supervisory, managerial) positions.',
                    'Sub-Professional Eligibility': 'Sub-Professional Eligibility: Qualifies for first-level positions (clerical, custodial).',
                    'Career Service Executive Eligibility (CSE-E)': 'Career Service Executive Eligibility (CSE-E): For high-level executive positions.',
                    'Bar/Board Eligibility (RA 1080)': 'Bar/Board Eligibility (RA 1080): For licensed professionals (e.g., lawyers, doctors, engineers).'
                };
                
                let cseHTML = '';
                cseValues.forEach(value => {
                    const escapedValue = escapeHtml(value);
                    if (cseLabels[value]) {
                        const description = escapeHtml(cseLabels[value].split(': ')[1]);
                        cseHTML += `<div style="padding: 8px; margin-bottom: 8px; display: flex; align-items: flex-start; gap: 8px;">
                            <i class="fas fa-check-circle" style="color: var(--primary-blue); margin-top: 4px;"></i>
                            <span>${escapedValue}: ${description}</span>
                        </div>`;
                    } else if (value) {
                        cseHTML += `<div style="padding: 8px; margin-bottom: 8px; display: flex; align-items: flex-start; gap: 8px;">
                            <i class="fas fa-check-circle" style="color: var(--primary-blue); margin-top: 4px;"></i>
                            <span>${escapedValue}</span>
                        </div>`;
                    }
                });
                cseElement.innerHTML = cseHTML || '<span style="color: var(--gray-500);">N/A</span>';
            } else {
                cseElement.innerHTML = '<span style="color: var(--gray-500);">N/A</span>';
            }
            
            // Set deadline
            if (positionData.deadline || positionData.deadline_raw) {
                document.getElementById('modalDeadline').textContent = positionData.deadline || 
                    (positionData.deadline_raw ? new Date(positionData.deadline_raw).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A');
            } else {
                document.getElementById('modalDeadline').textContent = 'N/A';
            }
            
            // Set user experience
            document.getElementById('modalUserExperience').textContent = parseFloat(positionData.user_experience).toFixed(1);
            document.getElementById('modalRequiredExperience').textContent = positionData.min_experience_years;
            
            // Set requirement status
            const requirementStatus = document.getElementById('modalRequirementStatus');
            const meetsRequirements = positionData.meets_requirements === '1';
            const meetsCSE = positionData.meets_cse === '1';
            const hasCSERequirement = positionData.has_cse_requirement === '1';
            
            let statusHTML = '';
            let allRequirementsMet = true;
            
            // Check experience
            const userExp = parseFloat(positionData.user_experience);
            const minExp = parseFloat(positionData.min_experience_years);
            const needed = minExp - userExp;
            if (needed > 0) {
                allRequirementsMet = false;
                statusHTML += `
                    <div class="alert alert-warning" style="margin-bottom: 12px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div><strong>Experience:</strong> You need ${needed.toFixed(1)} more years of experience</div>
                    </div>
                `;
            }
            
            // Check CSE requirements
            if (hasCSERequirement) {
                if (!meetsCSE) {
                    allRequirementsMet = false;
                    const requiredCSE = JSON.parse(positionData.required_cse || '[]');
                    const applicantCSE = JSON.parse(positionData.applicant_cse || '[]');
                    let cseMessage = positionData.cse_validation_message || '';
                    
                    statusHTML += `
                        <div class="alert alert-danger" style="margin-bottom: 12px;">
                            <i class="fas fa-times-circle"></i>
                            <div><strong>Career Service Eligibility:</strong> ${cseMessage}</div>
                            <div style="margin-top: 8px; font-size: 0.9rem;">
                                <strong>Required:</strong> ${requiredCSE.length > 0 ? requiredCSE.join(', ') : 'N/A'}<br>
                                <strong>You have:</strong> ${applicantCSE.length > 0 ? applicantCSE.join(', ') : 'None'}
                            </div>
                        </div>
                    `;
                } else {
                    const matchingCSE = JSON.parse(positionData.required_cse || '[]').filter(cse => 
                        JSON.parse(positionData.applicant_cse || '[]').includes(cse)
                    );
                    statusHTML += `
                        <div class="alert alert-success" style="margin-bottom: 12px;">
                            <i class="fas fa-check-circle"></i>
                            <div><strong>Career Service Eligibility:</strong> You meet the requirement!</div>
                            <div style="margin-top: 8px; font-size: 0.9rem;">
                                Matching eligibility: ${matchingCSE.join(', ')}
                            </div>
                        </div>
                    `;
                }
            }
            
            // Show overall status
            if (allRequirementsMet && meetsRequirements) {
                statusHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div>You meet all requirements!</div>
                    </div>
                ` + statusHTML;
            } else if (!allRequirementsMet) {
                if (!statusHTML.includes('alert-danger') && !statusHTML.includes('alert-warning')) {
                    statusHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>You don't meet all requirements</div>
                        </div>
                    ` + statusHTML;
                }
            }
            
            requirementStatus.innerHTML = statusHTML || `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>Checking requirements...</div>
                </div>
            `;
            
            // Set actions
            const actions = document.getElementById('modalActions');
            const hasApplied = positionData.has_applied === '1';
            const meetsAllRequirements = positionData.meets_requirements === '1';
            
            if (hasApplied) {
                actions.innerHTML = `
                    <button class="btn btn-primary w-100" disabled style="width: 100%;">
                        <i class="fas fa-check-circle"></i> Already Applied
                    </button>
                    <p style="text-align: center; color: var(--gray-500); margin-top: 12px; font-size: 0.875rem; width: 100%;">
                        Status: ${positionData.application_status.charAt(0).toUpperCase() + positionData.application_status.slice(1)}
                    </p>
                `;
            } else if (!meetsAllRequirements) {
                let lockMessage = "Requirements Not Met";
                if (hasCSERequirement && !meetsCSE) {
                    lockMessage = "Missing Required CSE Eligibility";
                } else if (needed > 0) {
                    lockMessage = "Insufficient Experience";
                }
                actions.innerHTML = `
                    <button class="btn btn-primary w-100" style="width: 100%; background: var(--gray-400); cursor: not-allowed;" disabled>
                        <i class="fas fa-lock"></i> ${lockMessage}
                    </button>
                `;
            } else {
                actions.innerHTML = `
                    <a href="${positionData.apply_url}" class="btn btn-success w-100" style="width: 100%;">
                        <i class="fas fa-paper-plane"></i> Apply Now
                    </a>
                `;
            }
            
            // Set applications count
            const applicationsSection = document.getElementById('modalApplicationsSection');
            if (positionData.max_applications) {
                applicationsSection.style.display = 'block';
                const percentage = (positionData.application_count / positionData.max_applications) * 100;
                document.getElementById('modalApplicationProgress').style.width = percentage + '%';
                document.getElementById('modalApplicationCount').textContent = 
                    `${positionData.application_count} of ${positionData.max_applications} applications`;
            } else {
                applicationsSection.style.display = 'none';
            }
            
            // Show modal
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Add click handlers to all view details buttons
        viewDetailsButtons.forEach(button => {
            button.addEventListener('click', function() {
                const positionItem = this.closest('.position-item');
                const positionDataJson = positionItem.getAttribute('data-position');
                if (positionDataJson) {
                    try {
                        const positionData = JSON.parse(positionDataJson);
                        openModal(positionData);
                    } catch (e) {
                        console.error('Error parsing position data:', e);
                    }
                }
            });
        });

    </script>
    <?php include '../../includes/pwa-scripts.php'; ?>
</body>
</html>
