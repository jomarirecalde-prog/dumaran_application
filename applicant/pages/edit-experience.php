<?php
require_once '../../config/config.php';
requireAuth();

if (hasRole(ROLE_ADMIN)) {
    header('Location: ' . BASE_URL . 'admin/pages/dashboard.php');
    exit();
}

// Require privacy waiver acceptance
requirePrivacyWaiver();

require_once '../../includes/models.php';

$experienceModel = new Experience();
$userId = $_SESSION['user_id'];
$expId = $_GET['id'] ?? 0;

$experience = $experienceModel->findById($expId);

if (!$experience || $experience['user_id'] != $userId) {
    header('Location: profile.php?error=experience_not_found');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form submission
    $validation = FormTokenManager::validateFormSubmission($_POST);
    
    if (!$validation['valid']) {
        if ($validation['error'] === 'duplicate') {
            header('Location: profile.php?info=Experience already updated');
            exit();
        }
        $error = $validation['error'];
    } else {
        $companyName = sanitize($_POST['company_name']);
        $positionTitle = sanitize($_POST['position_title']);
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'] ?? null;
        $isCurrent = isset($_POST['is_current']) ? 1 : 0;
        $description = sanitize($_POST['description']);
        
        if (empty($companyName) || empty($positionTitle) || empty($startDate)) {
        $error = "Please fill in all required fields.";
    } elseif (!$isCurrent && empty($endDate)) {
        $error = "End date is required if this is not your current position.";
    } else {
        $data = [
            'company_name' => $companyName,
            'position_title' => $positionTitle,
            'start_date' => $startDate,
            'end_date' => $isCurrent ? null : $endDate,
            'is_current' => $isCurrent,
            'description' => $description
        ];
        
        if ($experienceModel->update($expId, $data)) {
            header('Location: profile.php?success=experience_updated');
            exit();
        } else {
            $error = "Failed to update experience.";
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
    <title>Edit Experience - WPU Applicant System</title>
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
            <a href="profile.php" class="sidebar-nav-item active">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">
                    <?php 
                    $userModel = new User();
                    $user = $userModel->findById($userId);
                    echo strtoupper(substr($user['full_name'], 0, 1));
                    ?>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?= htmlspecialchars($user['full_name']) ?></div>
                    <div class="sidebar-user-role">Applicant</div>
                </div>
                <a href="../../auth/logout.php" class="confirm-logout" style="color: var(--gray-500); text-decoration: none;">
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
            <h1 class="topbar-title">Edit Experience</h1>
            <div class="topbar-actions">
                <?php include '../../includes/applicant-theme-toggle.php'; ?>
                <a href="profile.php" class="topbar-btn">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content">
            <div class="card" style="max-width: 700px; margin: 0 auto;">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-edit"></i> Edit Work Experience
                    </h3>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div><?= htmlspecialchars($error) ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="prevent-double-submit">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <?php FormTokenManager::renderFormTokenInput('edit_experience'); ?>
                        <div class="form-group">
                            <label class="form-label">Company Name *</label>
                            <input type="text" name="company_name" class="form-control" 
                                   value="<?= htmlspecialchars($experience['company_name']) ?>" 
                                   placeholder="Enter company name" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Position Title *</label>
                            <input type="text" name="position_title" class="form-control" 
                                   value="<?= htmlspecialchars($experience['position_title']) ?>" 
                                   placeholder="Enter position title" required>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label">Start Date *</label>
                                <input type="date" name="start_date" class="form-control" 
                                       value="<?= $experience['start_date'] ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" id="endDate" class="form-control" 
                                       value="<?= $experience['end_date'] ?? '' ?>" 
                                       <?= $experience['is_current'] ? 'disabled' : '' ?>>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="is_current" id="isCurrent" 
                                       style="width: 18px; height: 18px;" 
                                       <?= $experience['is_current'] ? 'checked' : '' ?>>
                                <span>I currently work here</span>
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Description (Optional)</label>
                            <textarea name="description" class="form-control" rows="4" 
                                      placeholder="Describe your role and responsibilities..."><?= htmlspecialchars($experience['description'] ?? '') ?></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 12px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Experience
                            </button>
                            <a href="profile.php" class="btn btn-outline">
                                <i class="fas fa-times"></i> Cancel
                            </a>
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
            <a href="positions.php" class="mobile-nav-item">
                <i class="fas fa-briefcase"></i>
                <span>Jobs</span>
            </a>
            <a href="my-applications.php" class="mobile-nav-item">
                <i class="fas fa-file-alt"></i>
                <span>Applications</span>
            </a>
            <a href="profile.php" class="mobile-nav-item active">
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
        const isCurrentCheckbox = document.getElementById('isCurrent');
        const endDateInput = document.getElementById('endDate');
        
        isCurrentCheckbox.addEventListener('change', function() {
            endDateInput.disabled = this.checked;
            if (this.checked) {
                endDateInput.value = '';
                endDateInput.required = false;
            } else {
                endDateInput.required = false;
            }
        });
    </script>
    <?php include '../../includes/pwa-scripts.php'; ?>
</body>
</html>
