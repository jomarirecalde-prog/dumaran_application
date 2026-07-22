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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - WPU Applicant System</title>
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
            <h1 class="topbar-title">Dashboard</h1>
            <div class="topbar-actions">
                
                <a href="../../auth/logout.php" class="topbar-btn confirm-logout" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content">
            <?php if (isset($databaseError) && $databaseError): ?>
            <!-- Database Error Alert -->
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Database Setup Required</strong><br>
                    The system database needs to be initialized before you can use all features. Please contact your system administrator.
                </div>
            </div>
            <?php endif; ?>

            <!-- Welcome Message -->
            <div class="card" style="background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%); border: none; color: white; margin-bottom: 24px;">
                <div class="card-body" style="padding: 32px;">
                    <h2 style="margin-bottom: 12px; font-size: 1.75rem; font-weight: 700;">Welcome back, <?= htmlspecialchars($user['full_name']) ?>! 👋</h2>
                    <p style="opacity: 0.9; margin-bottom: 20px; font-size: 1rem;">Discover new opportunities and take the next step in your career journey.</p>
                    <a href="positions.php" class="btn" style="background: white; color: var(--primary-blue); font-weight: 600;">
                        <i class="fas fa-search"></i> Browse Available Positions
                    </a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-value"><?= count($userApplications) ?></div>
                            <div class="stat-card-label">Total Applications</div>
                        </div>
                        <div class="stat-card-icon blue">
                            <i class="fas fa-file-alt"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-value"><?= $pendingCount ?></div>
                            <div class="stat-card-label">Pending</div>
                        </div>
                        <div class="stat-card-icon yellow">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-value"><?= number_format($totalExperience, 1) ?></div>
                            <div class="stat-card-label">Years Experience</div>
                        </div>
                        <div class="stat-card-icon green">
                            <i class="fas fa-briefcase"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-value"><?= count($activePositions) ?></div>
                            <div class="stat-card-label">Available Jobs</div>
                        </div>
                        <div class="stat-card-icon blue">
                            <i class="fas fa-building"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Responsive Dashboard Sections -->
            <div class="dashboard-sections">
                <!-- Recent Applications -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 class="card-title">
                            <i class="fas fa-file-alt"></i> Recent Applications
                        </h3>
                        <a href="my-applications.php" class="btn btn-sm btn-outline">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($userApplications)): ?>
                            <div class="empty-state" style="padding: 30px 20px;">
                                <i class="fas fa-file-alt" style="font-size: 2.5rem;"></i>
                                <p style="margin: 12px 0 0 0; font-size: 0.875rem;">No applications yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_slice($userApplications, 0, 3) as $app): ?>
                                <div style="padding: 12px 0; border-bottom: 1px solid var(--gray-200);">
                                    <div style="font-weight: 600; font-size: 0.9rem; color: var(--gray-800); margin-bottom: 4px;">
                                        <?= htmlspecialchars($app['position_title']) ?>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                                        <span style="font-size: 0.75rem; color: var(--gray-500);">
                                            <?= date('M j, Y', strtotime($app['applied_at'])) ?>
                                        </span>
                                        <span class="badge badge-<?= $app['status'] === STATUS_PENDING ? 'warning' : ($app['status'] === STATUS_APPROVED ? 'success' : 'danger') ?>">
                                            <?= ucfirst($app['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Work Experience Summary -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 class="card-title">
                            <i class="fas fa-briefcase"></i> Experience
                        </h3>
                        <a href="profile.php" class="btn btn-sm btn-outline">Edit</a>
                    </div>
                    <div class="card-body">
                        <div style="text-align: center; padding: 20px;">
                            <div style="font-size: 2.5rem; font-weight: 700; color: var(--primary-blue);">
                                <?= number_format($totalExperience, 1) ?>
                            </div>
                            <div style="font-size: 0.875rem; color: var(--gray-500); font-weight: 600;">
                                TOTAL YEARS
                            </div>
                            <div style="margin-top: 16px; font-size: 0.85rem; color: var(--gray-600);">
                                <?= count($userExperiences) ?> work experience<?= count($userExperiences) != 1 ? 's' : '' ?> listed
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Job Openings -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 class="card-title">
                            <i class="fas fa-briefcase"></i> Recent Job Openings
                        </h3>
                        <a href="positions.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($activePositions)): ?>
                            <div class="empty-state">
                                <i class="fas fa-briefcase"></i>
                                <h3>No Available Positions</h3>
                                <p>Check back later for new job openings.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_slice($activePositions, 0, 5) as $position): ?>
                                <?php
                                $hasApplied = false;
                                foreach ($userApplications as $app) {
                                    if ($app['position_id'] == $position['id']) {
                                        $hasApplied = true;
                                        break;
                                    }
                                }
                                $meetsRequirements = $totalExperience >= $position['min_experience_years'];
                                ?>
                                <div style="padding: 14px; border-bottom: 1px solid var(--gray-200);">
                                    <h4 style="margin: 0 0 8px 0; font-size: 0.95rem; font-weight: 600; color: var(--gray-800);">
                                        <a href="position-details.php?id=<?= $position['id'] ?>" style="color: var(--gray-800); text-decoration: none;">
                                            <?= htmlspecialchars($position['title']) ?>
                                        </a>
                                    </h4>
                                    <div style="font-size: 0.8rem; color: var(--gray-600); display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 10px;">
                                        <?php if ($position['location']): ?>
                                            <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($position['location']) ?></span>
                                        <?php endif; ?>
                                        <span><i class="fas fa-clock"></i> Min. <?= $position['min_experience_years'] ?>y</span>
                                        <span class="badge badge-primary"><?= ucfirst($position['employment_type']) ?></span>
                                    </div>
                                    <div>
                                        <?php if ($hasApplied): ?>
                                            <span class="badge badge-success">Applied</span>
                                        <?php elseif (!$meetsRequirements): ?>
                                            <span class="badge badge-warning" title="Below required experience">
                                                <i class="fas fa-exclamation-triangle"></i> Below required experience
                                            </span>
                                        <?php else: ?>
                                            <a href="apply.php?position=<?= $position['id'] ?>" class="btn btn-sm btn-primary">
                                                Apply Now
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-nav">
        <div class="mobile-nav-grid">
            <a href="dashboard.php" class="mobile-nav-item active">
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

    <!-- Data Privacy Waiver Modal -->
    <?php if (!$privacyWaiverAccepted): ?>
    <style>
        /* Block all interactions when modal is shown */
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
    <div id="privacyWaiverModal" class="modal" style="display: block; z-index: 10000; position: fixed; top: 0; left: 0; right: 0; bottom: 0;">
        <div class="modal-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.85); display: flex; align-items: center; justify-content: center; padding: 20px; cursor: default;">
            <div class="modal-content" style="background: white; border-radius: 8px; max-width: 700px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); position: relative; z-index: 10001;">
                
                <div class="modal-body" style="padding: 24px;">
                    <!-- Step 1: Application Guidelines Section -->
                    <div id="step1Guidelines" class="modal-step">
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--gray-800); margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-building" style="color: var(--primary-blue);"></i>
                            WPU EMPLOYMENT AND SELECTION
                        </h3>
                        <div style="line-height: 1.8; color: var(--gray-700); font-size: 0.95rem; margin-bottom: 24px;">
                            <p style="margin-bottom: 16px;">
                                Western Philippines University, through the Human Resource Management and Development Office, (WPU-HRMDO), holds its firm commitment to upholding the principles of merit, fitness, and equality of opportunity in all aspects of recruitment and selection. The University strictly adheres to the provisions governing objective, fair, and transparent personnel actions, in full alignment with Civil Service Commission (CSC) guidelines and established recruitment practices observed across State Universities and Colleges.
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
                                The <strong>Western Philippines University</strong> invites interested and qualified applicants on the following vacant positions on permanent status:
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
                                <p style="margin-bottom: 4px;"><strong>DR. AMABEL S. LIAO</strong></p>
                                <p style="margin-bottom: 4px;">University President</p>
                                <p style="margin-bottom: 0;">Aborlan, Palawan</p>
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
                                In accordance with Republic Act No. 10173, otherwise known as the <strong>Data Privacy Act of 2012</strong>, and its Implementing Rules and Regulations, I hereby authorize <strong>Western Philippines University (WPU)</strong>, through its authorized offices, to collect, process, store, and use my personal information submitted in connection with my application for employment.
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
                            EMPLOYMENT OPPORTUNITY FOR EXTENSION AND RESEARCH DIVISION
                        </h3>
                        <div style="line-height: 1.8; color: var(--gray-700); font-size: 0.95rem;">
                            <p style="margin-bottom: 16px;">
                                The Research and Extension Services of Western Philippines University (WPU) serve as a core functional division that advances the University's mandate in instruction, research, and community engagement. It is responsible for promoting a strong culture of research, innovation, and knowledge generation that supports institutional development and national and regional priorities. Through systematic research initiatives and responsive extension programs, the division facilitates the translation of academic expertise into practical solutions that contribute to sustainable development and community empowerment.
                            </p>
                            <p style="margin-bottom: 16px;">
                                The Research and Extension Services also oversee the formulation, implementation, and monitoring of research and extension projects, ensuring alignment with ethical standards, quality assurance mechanisms, and applicable government policies. Applicants under this division are expected to support and uphold WPU's commitment to scholarly excellence, public service, and evidence-based decision-making in the pursuit of the University's strategic goals.
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
                <div class="modal-footer" style="padding: 20px 24px; border-top: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; gap: 12px;">
                    <button type="button" id="backBtn" class="btn btn-outline" style="display: none; min-width: 120px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <div style="display: flex; gap: 12px; margin-left: auto;">
                        <button type="button" id="nextBtn" class="btn btn-primary" style="min-width: 120px;">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                        <button type="button" id="acceptPrivacyWaiverBtn" class="btn btn-primary" disabled style="display: none; min-width: 120px;">
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
        });
    </script>
    <?php endif; ?>
</body>
</html>
