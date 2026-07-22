<?php
require_once '../../config/config.php';
require_once '../../includes/models.php';

requireAdmin();

$userModel = new User();

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    // Validate form submission
    $validation = FormTokenManager::validateFormSubmission($_POST);
    
    if (!$validation['valid']) {
        if ($validation['error'] === 'duplicate') {
            $_SESSION['info_message'] = 'Action already processed.';
        } else {
            $_SESSION['error_message'] = $validation['error'] ?? 'Form validation failed. Please refresh and try again.';
        }
        header('Location: users.php');
        exit();
    }
    
    $userId = (int)($_POST['user_id'] ?? 0);
    
    // Validate user ID
    if ($userId <= 0) {
        $_SESSION['error_message'] = 'Invalid user ID provided.';
        header('Location: users.php');
        exit();
    }
    
    // Don't allow deleting yourself
    if ($userId === $_SESSION['user_id']) {
        $_SESSION['error_message'] = 'You cannot delete your own account!';
        header('Location: users.php');
        exit();
    }
    
    // Check if user exists
    $user = $userModel->findById($userId);
    if (!$user) {
        $_SESSION['error_message'] = 'User not found.';
        header('Location: users.php');
        exit();
    }
    
    // Attempt to delete the user - using a fresh direct database connection
    try {
        // Ensure Database class is available
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../../config/database.php';
        }
        
        // Create a fresh database connection to ensure no transaction/caching issues
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            throw new Exception('Database connection failed');
        }
        
        // First, verify user exists and get user info
        $checkStmt = $db->prepare("SELECT id, full_name, email FROM users WHERE id = ?");
        $checkStmt->execute([$userId]);
        $userToDelete = $checkStmt->fetch();
        
        if (!$userToDelete) {
            $_SESSION['error_message'] = 'User not found.';
            header('Location: users.php');
            exit();
        }
        
        // Log the attempt
        error_log("Attempting to delete user ID: $userId, Name: " . $userToDelete['full_name']);
        
        // Method 1: Try using prepared statement first
        $deleteStmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $deleteStmt->bindValue(1, $userId, PDO::PARAM_INT);
        $executed = $deleteStmt->execute();
        
        if (!$executed) {
            throw new Exception('DELETE statement execution returned false');
        }
        
        // Small delay to ensure database has processed the delete
        usleep(50000); // 0.05 seconds
        
        // Verify deletion with a completely fresh query
        $verifyStmt = $db->prepare("SELECT COUNT(*) as cnt FROM users WHERE id = ?");
        $verifyStmt->execute([$userId]);
        $verifyResult = $verifyStmt->fetch();
        $userCount = (int)$verifyResult['cnt'];
        
        if ($userCount > 0) {
            // Still exists - try direct SQL exec as fallback
            error_log("User still exists after prepared DELETE, trying exec() method");
            $userIdSafe = (int)$userId;
            $affected = $db->exec("DELETE FROM users WHERE id = $userIdSafe");
            
            // Verify again
            $verifyStmt2 = $db->prepare("SELECT COUNT(*) as cnt FROM users WHERE id = ?");
            $verifyStmt2->execute([$userId]);
            $verifyResult2 = $verifyStmt2->fetch();
            $userCount2 = (int)$verifyResult2['cnt'];
            
            if ($userCount2 > 0) {
                // Check for foreign key constraints by trying to see what's blocking
                $fkCheck = $db->query("
                    SELECT 
                        (SELECT COUNT(*) FROM applications WHERE user_id = $userIdSafe) as app_count,
                        (SELECT COUNT(*) FROM positions WHERE created_by = $userIdSafe) as pos_count,
                        (SELECT COUNT(*) FROM user_experience WHERE user_id = $userIdSafe) as exp_count
                ")->fetch();
                
                error_log("DELETE FAILED: User ID $userId still exists. References - Apps: {$fkCheck['app_count']}, Positions: {$fkCheck['pos_count']}, Experience: {$fkCheck['exp_count']}");
                
                $_SESSION['error_message'] = 'Failed to delete user. The user is referenced by other records. ' .
                    'Applications: ' . $fkCheck['app_count'] . ', Positions: ' . $fkCheck['pos_count'] . 
                    ', Experience records: ' . $fkCheck['exp_count'];
            } else {
                error_log("DELETE SUCCESS: User ID $userId deleted using exec() fallback");
                $_SESSION['success_message'] = 'User deleted successfully!';
            }
        } else {
            error_log("DELETE SUCCESS: User ID $userId deleted using prepared statement");
            $_SESSION['success_message'] = 'User deleted successfully!';
        }
        
    } catch (PDOException $e) {
        error_log('User deletion PDO error: ' . $e->getMessage());
        error_log('Error code: ' . $e->getCode());
        error_log('SQL State: ' . $e->getCode());
        
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        // Check for foreign key constraint violations
        if ($errorCode == '23000' || strpos($errorMessage, 'foreign key') !== false || 
            strpos($errorMessage, '1451') !== false || strpos($errorMessage, 'Cannot delete') !== false ||
            strpos($errorMessage, 'a foreign key constraint fails') !== false) {
            $_SESSION['error_message'] = 'Cannot delete user. The user is referenced by other records (applications, positions, etc.).';
        } else {
            $_SESSION['error_message'] = 'Database error occurred: ' . htmlspecialchars($errorMessage);
        }
    } catch (Exception $e) {
        error_log('User deletion error: ' . $e->getMessage());
        $_SESSION['error_message'] = 'An error occurred while deleting the user: ' . htmlspecialchars($e->getMessage());
    }
    
    header('Location: users.php');
    exit();
}

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    // Validate form submission
    $validation = FormTokenManager::validateFormSubmission($_POST);
    
    if (!$validation['valid']) {
        if ($validation['error'] === 'duplicate') {
            $_SESSION['info_message'] = 'Action already processed.';
        } else {
            $_SESSION['error_message'] = $validation['error'];
        }
        header('Location: users.php');
        exit();
    }
    
    $userId = (int)$_POST['user_id'];
    $newRole = $_POST['role'];
    
    if (in_array($newRole, [ROLE_ADMIN, ROLE_APPLICANT]) && $userId !== $_SESSION['user_id']) {
        $userModel->update($userId, ['role' => $newRole]);
        $_SESSION['success_message'] = 'User role updated successfully!';
    } else if ($userId === $_SESSION['user_id']) {
        $_SESSION['error_message'] = 'You cannot change your own role!';
    }
    header('Location: users.php');
    exit();
}

// Get filter parameters
$filterRole = $_GET['role'] ?? 'all';
$searchQuery = trim($_GET['search'] ?? '');

// Pagination setup
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 5;
$offset = ($page - 1) * $perPage;

// Build filter conditions
$whereClauses = [];
$params = [];

if ($filterRole !== 'all') {
    $whereClauses[] = 'role = ?';
    $params[] = $filterRole;
}

if ($searchQuery !== '') {
    $whereClauses[] = '(full_name LIKE ? OR email LIKE ? OR username LIKE ?)';
    $searchPattern = '%' . $searchQuery . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}

$where = implode(' AND ', $whereClauses);

// Get users based on filter
$allUsers = $userModel->findAll($where, $params);

// Calculate pagination
$totalUsers_filtered = count($allUsers);
$totalPages = ceil($totalUsers_filtered / $perPage);
$users = array_slice($allUsers, $offset, $perPage);

// Count users by role
$totalUsers = count($userModel->findAll());
$totalAdmins = count($userModel->findAll('role = ?', [ROLE_ADMIN]));
$totalApplicants = count($userModel->findAll('role = ?', [ROLE_APPLICANT]));

// ── View-layer metrics (derived from existing fetched data, no additional queries) ──
$adminUser = ['full_name' => $_SESSION['full_name'] ?? 'Admin', 'email' => $_SESSION['email'] ?? ''];
$statsUsers = ($filterRole === 'all' && $searchQuery === '') ? $allUsers : [];
$newThisWeek = 0;
$pendingVerification = 0;
$lockedAccounts = 0;
$activeToday = 0;
$verifiedCount = 0;
$weekAgo = strtotime('-7 days');
$todayStart = strtotime('today');

foreach ($statsUsers as $su) {
    if (strtotime($su['created_at']) >= $weekAgo) {
        $newThisWeek++;
    }
    if (!empty($su['email_verified_at'])) {
        $verifiedCount++;
    } else {
        $pendingVerification++;
    }
    if (isset($su['is_active']) && (int)$su['is_active'] === 0) {
        $lockedAccounts++;
    }
    if (!empty($su['updated_at']) && strtotime($su['updated_at']) >= $todayStart) {
        $activeToday++;
    }
}

function umHighlightSearch($text, $query) {
    if ($query === '') {
        return htmlspecialchars($text);
    }
    $safe = htmlspecialchars($text);
    $pattern = '/' . preg_quote($query, '/') . '/i';
    return preg_replace($pattern, '<mark class="um-highlight">$0</mark>', $safe);
}

function umTimeAgo($datetime) {
    if (empty($datetime)) {
        return 'Never';
    }
    $diff = time() - strtotime($datetime);
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $m = floor($diff / 60);
        return $m . ' minute' . ($m !== 1 ? 's' : '') . ' ago';
    }
    if ($diff < 86400) {
        $h = floor($diff / 3600);
        return $h . ' hour' . ($h !== 1 ? 's' : '') . ' ago';
    }
    if ($diff < 604800) {
        $d = floor($diff / 86400);
        return $d . ' day' . ($d !== 1 ? 's' : '') . ' ago';
    }
    return date('M j, Y', strtotime($datetime));
}

function umIsOnline($user) {
    return !empty($user['updated_at']) && (time() - strtotime($user['updated_at'])) < 900;
}

function umAccountStatus($user) {
    if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
        return ['label' => 'Locked', 'class' => 'um-badge-locked'];
    }
    if (umIsOnline($user)) {
        return ['label' => 'Online', 'class' => 'um-badge-online'];
    }
    return ['label' => 'Offline', 'class' => 'um-badge-offline'];
}

function umVerificationStatus($user) {
    if (!empty($user['email_verified_at'])) {
        return ['label' => 'Verified', 'class' => 'um-badge-verified'];
    }
    return ['label' => 'Pending', 'class' => 'um-badge-pending'];
}

$showingStart = $totalUsers_filtered > 0 ? $offset + 1 : 0;
$showingEnd = min($offset + $perPage, $totalUsers_filtered);
$adminPct = $totalUsers > 0 ? round(($totalAdmins / $totalUsers) * 100) : 0;
$applicantPct = $totalUsers > 0 ? 100 - $adminPct : 0;
$chipBaseQuery = $searchQuery !== '' ? '&search=' . urlencode($searchQuery) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="<?= generateCSRFToken() ?>">
    <title>Users Management - WPU Applicant System</title>
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/office-theme.css" rel="stylesheet">
    <link href="../../assets/css/admin-dashboard.css" rel="stylesheet">
    <link href="../../assets/css/users-dashboard.css" rel="stylesheet">
    <style>.visually-hidden{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}</style>
</head>
<body class="admin-dashboard users-page">
    <?php $adminSidebarActive = 'users'; require __DIR__ . '/_admin_sidebar.php'; ?>

    <div class="main-wrapper">
        <header class="topbar" role="banner">
            <div class="topbar-left">
                <button type="button" class="mobile-menu-toggle topbar-btn" aria-label="Open menu">
                    <i class="fas fa-bars"></i>
                </button>
                <nav class="topbar-breadcrumb" aria-label="Breadcrumb">
                    <a href="dashboard.php">Admin</a>
                    <i class="fas fa-chevron-right" style="font-size:0.65rem" aria-hidden="true"></i>
                    <span class="current">Users Management</span>
                </nav>
            </div>
            <div class="topbar-center">
                <div class="topbar-datetime" aria-live="polite">
                    <span id="topbarDate"></span>
                    <span class="time" id="topbarTime"></span>
                </div>
            </div>
            <div class="topbar-actions">
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
                <a href="settings.php" class="topbar-btn" aria-label="Settings">
                    <i class="fas fa-cog"></i>
                </a>
                <a href="../../auth/logout.php" class="topbar-btn confirm-logout" title="Logout" aria-label="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </header>

        <main class="content" id="mainContent" role="main">
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle"></i>
                <div><?= htmlspecialchars($_SESSION['success_message']) ?></div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <div><?= htmlspecialchars($_SESSION['error_message']) ?></div>
            </div>
            <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- Dashboard Header -->
            <section class="um-hero um-animate-d1" aria-labelledby="umHeroTitle">
                <div class="um-hero-bg" aria-hidden="true">
                    <div class="um-hero-orb"></div>
                    <div class="um-hero-orb"></div>
                </div>
                <div class="um-hero-inner">
                    <div class="um-hero-main">
                        <nav class="um-breadcrumb" aria-label="Dashboard breadcrumb">
                            <a href="dashboard.php"><i class="fas fa-home" aria-hidden="true"></i> Admin</a>
                            <i class="fas fa-chevron-right" style="font-size:0.6rem" aria-hidden="true"></i>
                            <span>Users Management</span>
                        </nav>
                        <div class="um-hero-title-row">
                            <div class="um-hero-icon" aria-hidden="true"><i class="fas fa-users-cog"></i></div>
                            <h1 class="um-hero-title" id="umHeroTitle">Users Management</h1>
                        </div>
                        <p class="um-hero-subtitle">Manage administrator and applicant accounts, permissions, roles, and account activities.</p>
                        <div class="um-hero-meta">
                            <span><i class="fas fa-calendar-day" aria-hidden="true"></i> <span id="umHeaderDate"></span></span>
                            <span><i class="fas fa-users" aria-hidden="true"></i> <?= number_format($totalUsers) ?> total users</span>
                            <span><i class="fas fa-filter" aria-hidden="true"></i> <?= number_format($totalUsers_filtered) ?> matching</span>
                        </div>
                    </div>
                    <div class="um-hero-actions">
                        <button type="button" class="um-btn um-btn-glass" id="umRefreshBtn" aria-label="Refresh users list">
                            <i class="fas fa-sync-alt" aria-hidden="true"></i> Refresh
                        </button>
                        <div class="um-export-wrap">
                            <button type="button" class="um-btn um-btn-glass" id="umExportHeaderBtn" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-download" aria-hidden="true"></i> Export
                            </button>
                            <div class="um-export-menu" id="umExportHeaderMenu" role="menu">
                                <button type="button" data-export="csv" role="menuitem"><i class="fas fa-file-csv"></i> CSV</button>
                                <button type="button" data-export="excel" role="menuitem"><i class="fas fa-file-excel"></i> Excel</button>
                                <button type="button" data-export="pdf" role="menuitem"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button type="button" data-export="print" role="menuitem"><i class="fas fa-print"></i> Print</button>
                                <button type="button" data-export="copy" role="menuitem"><i class="fas fa-copy"></i> Copy</button>
                            </div>
                        </div>
                        <a href="register.php" class="um-btn um-btn-solid"><i class="fas fa-user-plus" aria-hidden="true"></i> Add User</a>
                    </div>
                </div>
            </section>

            <!-- Search Toolbar -->
            <div class="um-toolbar" id="umToolbar" role="search" aria-label="Search and filter users">
                <form method="GET" action="" class="um-toolbar-form" id="umFilterForm">
                    <div class="um-field um-field-grow">
                        <label for="searchUsers">Global Search</label>
                        <div class="um-search-wrap">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input
                                type="text"
                                name="search"
                                id="searchUsers"
                                class="form-control"
                                placeholder="Search by name, email, or username…"
                                value="<?= htmlspecialchars($searchQuery) ?>"
                                autocomplete="off"
                                aria-describedby="umSearchHint"
                            >
                            <button type="button" class="um-search-clear" id="umSearchClear" aria-label="Clear search" title="Clear search">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <span id="umSearchHint" class="visually-hidden">Press Enter or click Search to filter users</span>
                    </div>
                    <div class="um-field">
                        <label for="filterRole">Role</label>
                        <select name="role" id="filterRole" class="form-select" aria-label="Filter by role">
                            <option value="all" <?= $filterRole === 'all' ? 'selected' : '' ?>>All Roles</option>
                            <option value="<?= ROLE_ADMIN ?>" <?= $filterRole === ROLE_ADMIN ? 'selected' : '' ?>>Administrators</option>
                            <option value="<?= ROLE_APPLICANT ?>" <?= $filterRole === ROLE_APPLICANT ? 'selected' : '' ?>>Applicants</option>
                        </select>
                    </div>
                    <div class="um-field">
                        <label for="umFilterStatus">Status</label>
                        <select id="umFilterStatus" class="form-select" aria-label="Filter by status (display only)">
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="locked">Locked</option>
                            <option value="verified">Verified</option>
                            <option value="pending">Pending Verification</option>
                        </select>
                    </div>
                    <div class="um-field">
                        <label for="umSortBy">Sort By</label>
                        <select id="umSortBy" class="form-select" aria-label="Sort users (display only)">
                            <option value="name">Name A–Z</option>
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="role">Role</option>
                        </select>
                    </div>
                    <div class="um-field">
                        <label for="umRowsPerPage">Rows</label>
                        <select id="umRowsPerPage" class="form-select" aria-label="Rows per page">
                            <option value="5" selected>5 / page</option>
                            <option value="10">10 / page</option>
                            <option value="25">25 / page</option>
                        </select>
                    </div>
                    <div class="um-toolbar-actions">
                        <button type="submit" class="um-toolbar-btn primary" aria-label="Search users">
                            <i class="fas fa-search" aria-hidden="true"></i> Search
                        </button>
                        <?php if ($filterRole !== 'all' || $searchQuery !== ''): ?>
                        <a href="users.php" class="um-toolbar-btn" aria-label="Reset filters">
                            <i class="fas fa-undo" aria-hidden="true"></i> Reset
                        </a>
                        <?php endif; ?>
                        <button type="button" class="um-toolbar-btn" id="umToolbarRefresh" aria-label="Refresh">
                            <i class="fas fa-sync-alt" aria-hidden="true"></i> Refresh
                        </button>
                        <div class="um-export-wrap">
                            <button type="button" class="um-toolbar-btn" id="umExportToolbarBtn" aria-haspopup="true">
                                <i class="fas fa-file-export" aria-hidden="true"></i> Export
                            </button>
                            <div class="um-export-menu" id="umExportToolbarMenu" role="menu">
                                <button type="button" data-export="csv" role="menuitem"><i class="fas fa-file-csv"></i> CSV</button>
                                <button type="button" data-export="excel" role="menuitem"><i class="fas fa-file-excel"></i> Excel</button>
                                <button type="button" data-export="pdf" role="menuitem"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button type="button" data-export="print" role="menuitem"><i class="fas fa-print"></i> Print</button>
                                <button type="button" data-export="copy" role="menuitem"><i class="fas fa-copy"></i> Copy</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Quick Filter Chips -->
            <nav class="um-chips" aria-label="Quick filters">
                <a href="users.php<?= $searchQuery !== '' ? '?search=' . urlencode($searchQuery) : '' ?>" class="um-chip <?= $filterRole === 'all' ? 'active' : '' ?>"><i class="fas fa-users"></i> All</a>
                <a href="?role=<?= urlencode(ROLE_ADMIN) ?><?= $chipBaseQuery ?>" class="um-chip <?= $filterRole === ROLE_ADMIN ? 'active' : '' ?>"><i class="fas fa-user-shield"></i> Administrators</a>
                <a href="?role=<?= urlencode(ROLE_APPLICANT) ?><?= $chipBaseQuery ?>" class="um-chip <?= $filterRole === ROLE_APPLICANT ? 'active' : '' ?>"><i class="fas fa-user-tie"></i> Applicants</a>
                <a href="users.php" class="um-chip"><i class="fas fa-clock"></i> Recently Added</a>
                <span class="um-chip" data-um-chip="online"><i class="fas fa-circle" style="color:var(--dash-success);font-size:0.5rem"></i> Online</span>
                <span class="um-chip" data-um-chip="offline"><i class="fas fa-circle" style="color:#94a3b8;font-size:0.5rem"></i> Offline</span>
                <span class="um-chip" data-um-chip="inactive"><i class="fas fa-ban"></i> Inactive</span>
                <span class="um-chip" data-um-chip="verified"><i class="fas fa-check-circle"></i> Verified</span>
                <span class="um-chip" data-um-chip="unverified"><i class="fas fa-hourglass-half"></i> Unverified</span>
            </nav>

            <div class="um-layout">
                <section aria-labelledby="umDirectoryTitle">
                    <div class="um-directory-header">
                        <h2 class="um-directory-title" id="umDirectoryTitle">
                            <i class="fas fa-address-book" aria-hidden="true"></i> Users Directory
                            <span class="badge badge-primary"><?= number_format($totalUsers_filtered) ?> matching</span>
                        </h2>
                        <label class="um-toolbar-btn" style="cursor:pointer;margin:0">
                            <input type="checkbox" id="umSelectAll" aria-label="Select all users on this page" style="margin-right:8px">
                            Select All
                        </label>
                    </div>

                    <!-- Bulk Actions -->
                    <div class="um-bulk-bar" id="umBulkBar" role="toolbar" aria-label="Bulk actions">
                        <span class="um-bulk-count" id="umBulkCount">0 selected</span>
                        <button type="button" class="um-bulk-btn" data-bulk="role" disabled title="Requires backend API"><i class="fas fa-user-tag"></i> Change Role</button>
                        <button type="button" class="um-bulk-btn" data-bulk="export" title="Export selected"><i class="fas fa-file-export"></i> Export Selected</button>
                        <button type="button" class="um-bulk-btn" data-bulk="email" disabled title="Requires backend API"><i class="fas fa-envelope"></i> Send Email</button>
                        <button type="button" class="um-bulk-btn" data-bulk="lock" disabled title="Requires backend API"><i class="fas fa-lock"></i> Lock</button>
                        <button type="button" class="um-bulk-btn" data-bulk="unlock" disabled title="Requires backend API"><i class="fas fa-unlock"></i> Unlock</button>
                        <button type="button" class="um-bulk-btn danger" data-bulk="delete" disabled title="Requires backend API"><i class="fas fa-trash"></i> Delete Selected</button>
                    </div>

                    <div class="um-skeleton-grid" aria-hidden="true">
                        <div class="um-skeleton-card"></div>
                        <div class="um-skeleton-card"></div>
                    </div>

                    <?php if (empty($users)): ?>
                    <div class="empty-state" role="status">
                        <div class="um-empty-icon" aria-hidden="true"><i class="fas fa-user-slash"></i></div>
                        <h3>No Users Found</h3>
                        <p>Try changing your filters or search keywords.</p>
                        <a href="users.php" class="um-toolbar-btn primary" style="display:inline-flex;text-decoration:none">
                            <i class="fas fa-undo" aria-hidden="true"></i> Clear Filters
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="users-grid" id="usersGrid">
                        <?php foreach ($users as $index => $user):
                            $isCurrent = $user['id'] === $_SESSION['user_id'];
                            $isOnline = umIsOnline($user);
                            $accountStatus = umAccountStatus($user);
                            $verifyStatus = umVerificationStatus($user);
                            $roleLabel = $user['role'] === ROLE_ADMIN ? 'Administrator' : 'Applicant';
                            $roleBadgeClass = $user['role'] === ROLE_ADMIN ? 'um-badge-admin' : 'um-badge-applicant';
                            $initials = strtoupper(substr($user['full_name'], 0, 1));
                            $phone = !empty($user['phone']) ? $user['phone'] : '—';
                            $userJson = json_encode($user, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        ?>
                        <article class="user-item<?= $isCurrent ? ' is-current' : '' ?> um-animate-d<?= min(($index % 6) + 1, 6) ?>"
                                 data-user-id="<?= (int)$user['id'] ?>"
                                 data-role="<?= htmlspecialchars($user['role']) ?>"
                                 data-status="<?= (isset($user['is_active']) && (int)$user['is_active'] === 0) ? 'locked' : ($isOnline ? 'online' : 'offline') ?>"
                                 data-verified="<?= !empty($user['email_verified_at']) ? '1' : '0' ?>">
                            <input type="checkbox" class="um-card-select um-user-checkbox" value="<?= (int)$user['id'] ?>" aria-label="Select <?= htmlspecialchars($user['full_name']) ?>">
                            <div class="um-card-head">
                                <div class="user-avatar" aria-hidden="true">
                                    <?= $initials ?>
                                    <span class="um-online-dot <?= $isOnline ? 'online' : '' ?>" aria-hidden="true"></span>
                                </div>
                                <div class="um-card-identity">
                                    <h3 class="um-card-name">
                                        <?= umHighlightSearch($user['full_name'], $searchQuery) ?>
                                        <?php if ($isCurrent): ?>
                                        <span class="um-badge um-badge-you">Current User</span>
                                        <?php endif; ?>
                                    </h3>
                                    <div class="um-card-username">@<?= umHighlightSearch($user['username'], $searchQuery) ?></div>
                                    <div class="um-badge-row">
                                        <span class="um-badge <?= $roleBadgeClass ?>"><?= $roleLabel ?></span>
                                        <span class="um-badge <?= $accountStatus['class'] ?>"><?= $accountStatus['label'] ?></span>
                                        <span class="um-badge <?= $verifyStatus['class'] ?>"><?= $verifyStatus['label'] ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="um-details-grid">
                                <div class="um-detail-row">
                                    <span class="um-detail-label">Email</span>
                                    <span class="um-detail-value"><?= umHighlightSearch($user['email'], $searchQuery) ?></span>
                                </div>
                                <div class="um-detail-row">
                                    <span class="um-detail-label">Phone</span>
                                    <span class="um-detail-value"><?= htmlspecialchars($phone) ?></span>
                                </div>
                                <div class="um-detail-row">
                                    <span class="um-detail-label">Registration</span>
                                    <span class="um-detail-value"><?= date('M j, Y', strtotime($user['created_at'])) ?></span>
                                </div>
                                <div class="um-detail-row">
                                    <span class="um-detail-label">Last Activity</span>
                                    <span class="um-detail-value"><?= umTimeAgo($user['updated_at'] ?? null) ?></span>
                                </div>
                                <div class="um-detail-row">
                                    <span class="um-detail-label">Last Login</span>
                                    <span class="um-detail-value"><?= umTimeAgo($user['updated_at'] ?? null) ?></span>
                                </div>
                                <div class="um-detail-row">
                                    <span class="um-detail-label">Account Type</span>
                                    <span class="um-detail-value"><?= !empty($user['oauth_provider']) ? ucfirst($user['oauth_provider']) . ' OAuth' : 'Local Account' ?></span>
                                </div>
                            </div>
                            <?php if (!$isCurrent): ?>
                            <div class="um-card-actions" role="toolbar" aria-label="User actions">
                                <button type="button" class="um-action-btn" data-tooltip="View profile" aria-label="View profile" disabled title="Coming soon">
                                    <i class="fas fa-eye" aria-hidden="true"></i> <span class="um-action-text">View</span>
                                </button>
                                <button type="button" class="um-action-btn" data-tooltip="Edit role" aria-label="Edit role" onclick='updateRole(<?= $userJson ?>)'>
                                    <i class="fas fa-user-tag" aria-hidden="true"></i> <span class="um-action-text">Edit Role</span>
                                </button>
                                <button type="button" class="um-action-btn" data-tooltip="Reset password" aria-label="Reset password" disabled title="Requires backend API">
                                    <i class="fas fa-key" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="um-action-btn" data-tooltip="Lock account" aria-label="Lock account" disabled title="Requires backend API">
                                    <i class="fas fa-lock" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="um-action-btn danger" data-tooltip="Delete user" aria-label="Delete user" onclick='confirmDelete(<?= $userJson ?>)'>
                                    <i class="fas fa-trash" aria-hidden="true"></i> <span class="um-action-text">Delete</span>
                                </button>
                                <div class="um-more-wrap">
                                    <button type="button" class="um-action-btn um-more-btn" data-tooltip="More actions" aria-label="More actions" aria-haspopup="true">
                                        <i class="fas fa-ellipsis-h" aria-hidden="true"></i>
                                    </button>
                                    <div class="um-more-menu" role="menu">
                                        <button type="button" role="menuitem" disabled><i class="fas fa-unlock"></i> Unlock Account</button>
                                        <button type="button" role="menuitem" disabled><i class="fas fa-envelope"></i> Send Email</button>
                                        <button type="button" role="menuitem" onclick='updateRole(<?= $userJson ?>)'><i class="fas fa-edit"></i> Change Role</button>
                                        <button type="button" role="menuitem" class="danger" onclick='confirmDelete(<?= $userJson ?>)'><i class="fas fa-trash"></i> Delete</button>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="um-self-notice" role="note">
                                <i class="fas fa-info-circle" aria-hidden="true"></i> This is your account — role and deletion are restricted.
                            </div>
                            <?php endif; ?>
                        </article>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Pagination -->
                    <div class="um-pagination-wrap" aria-label="Pagination">
                        <div class="um-pagination-info">
                            Showing <strong><?= $showingStart ?>–<?= $showingEnd ?></strong> of <strong><?= number_format($totalUsers_filtered) ?></strong> users
                            <?php if ($totalPages > 1): ?>
                            · Page <?= $page ?> of <?= $totalPages ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&role=<?= urlencode($filterRole) ?>&search=<?= urlencode($searchQuery) ?>" class="pagination-btn" aria-label="Previous page">
                                <i class="fas fa-chevron-left" aria-hidden="true"></i> Previous
                            </a>
                            <?php endif; ?>
                            <div class="pagination-info" aria-current="page">Page <?= $page ?> / <?= $totalPages ?></div>
                            <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&role=<?= urlencode($filterRole) ?>&search=<?= urlencode($searchQuery) ?>" class="pagination-btn" aria-label="Next page">
                                Next <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                        <div class="um-jump-page">
                            <label for="umJumpPage">Jump to</label>
                            <input type="number" id="umJumpPage" min="1" max="<?= $totalPages ?>" value="<?= $page ?>" aria-label="Jump to page">
                            <button type="button" class="um-toolbar-btn" id="umJumpBtn">Go</button>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- User Analytics Side Panel -->
                <aside class="um-analytics" aria-label="User analytics">
                    <h3 class="um-analytics-title"><i class="fas fa-chart-pie" aria-hidden="true"></i> User Analytics</h3>
                    <div class="um-stat-row"><span>Total Admins</span><strong><?= number_format($totalAdmins) ?></strong></div>
                    <div class="um-stat-row"><span>Total Applicants</span><strong><?= number_format($totalApplicants) ?></strong></div>
                    <div class="um-stat-row"><span>Online Users</span><strong id="umOnlineCount">—</strong></div>
                    <div class="um-stat-row"><span>New Users Today</span><strong><?= number_format($activeToday) ?></strong></div>
                    <div class="um-stat-row"><span>New This Week</span><strong><?= number_format($newThisWeek) ?></strong></div>
                    <div class="um-stat-row"><span>Verified Accounts</span><strong><?= number_format($verifiedCount) ?></strong></div>
                    <div class="um-stat-row"><span>Pending Verification</span><strong><?= number_format($pendingVerification) ?></strong></div>
                    <div class="um-stat-row"><span>Locked Accounts</span><strong><?= number_format($lockedAccounts) ?></strong></div>
                    <div class="um-role-bar">
                        <div class="um-role-bar-label">
                            <span>Role Distribution</span>
                            <span><?= $adminPct ?>% / <?= $applicantPct ?>%</span>
                        </div>
                        <div class="um-role-bar-track" role="img" aria-label="Admin <?= $adminPct ?> percent, Applicant <?= $applicantPct ?> percent">
                            <?php if ($adminPct > 0): ?><div class="um-role-bar-admin" style="width:<?= $adminPct ?>%"></div><?php endif; ?>
                            <?php if ($applicantPct > 0): ?><div class="um-role-bar-applicant" style="width:<?= $applicantPct ?>%"></div><?php endif; ?>
                        </div>
                    </div>
                </aside>
            </div>
        </main>
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
            <a href="users.php" class="mobile-nav-item active">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
        </div>
    </nav>

    <!-- Update Role Modal -->
    <div class="modal" id="updateRoleModal" role="dialog" aria-labelledby="updateRoleTitle" aria-modal="true">
        <div class="modal-content um-modal-lg">
            <form method="POST" class="prevent-double-submit">
                <div class="modal-header premium">
                    <h3 class="modal-title" id="updateRoleTitle"><i class="fas fa-user-tag" aria-hidden="true"></i> Update User Role</h3>
                    <button class="close" type="button" onclick="closeUpdateModal()" aria-label="Close dialog">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <?php FormTokenManager::renderFormTokenInput('update_user_role'); ?>
                    <input type="hidden" name="user_id" id="updateUserId">
                    <div class="um-modal-user">
                        <div class="um-modal-avatar" id="updateUserAvatar" aria-hidden="true">U</div>
                        <div>
                            <p style="font-weight:700;color:var(--dash-text);margin:0 0 4px" id="updateUserName"></p>
                            <p style="color:var(--dash-text-muted);margin:0;font-size:0.85rem" id="updateUserEmail"></p>
                            <span class="um-badge um-badge-applicant" id="updateCurrentRoleBadge" style="margin-top:8px">Applicant</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="updateRoleSelect" class="form-label">New Role *</label>
                        <select name="role" id="updateRoleSelect" class="form-select" required aria-describedby="umRoleDesc">
                            <option value="<?= ROLE_APPLICANT ?>">Applicant</option>
                            <option value="<?= ROLE_ADMIN ?>">Administrator</option>
                        </select>
                    </div>
                    <div class="um-role-summary" id="umRoleDesc">
                        <strong id="umRoleDescTitle">Applicant</strong> — Can submit applications, manage profile, and track application status.
                    </div>
                    <div class="um-role-warning" id="umRoleDowngradeWarning" role="alert">
                        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                        <span>Warning: Downgrading an administrator removes elevated permissions immediately.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeUpdateModal()">Cancel</button>
                    <button type="submit" name="update_role" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Role
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal" role="dialog" aria-labelledby="deleteModalTitle" aria-modal="true">
        <div class="modal-content um-modal-lg">
            <div class="modal-header danger">
                <h3 class="modal-title" id="deleteModalTitle"><i class="fas fa-trash-alt" aria-hidden="true"></i> Confirm Delete</h3>
                <button class="close" type="button" onclick="closeDeleteModal()" aria-label="Close dialog">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="deleteUserId">
                <div class="um-delete-warning-icon" aria-hidden="true"><i class="fas fa-exclamation-triangle"></i></div>
                <p style="text-align:center;font-size:1rem;margin:0 0 20px;color:var(--dash-text)">Are you sure you want to permanently delete this user?</p>
                <div class="um-delete-details">
                    <div class="um-modal-user" style="margin-bottom:0;background:transparent;padding:0">
                        <div class="um-modal-avatar" id="deleteUserAvatar" aria-hidden="true">U</div>
                        <div>
                            <strong id="deleteUserName"></strong><br>
                            <span id="deleteUserEmail" style="color:var(--dash-text-muted);font-size:0.85rem"></span><br>
                            <span style="font-size:0.85rem">Role: <span id="deleteUserRole"></span></span><br>
                            <span style="font-size:0.85rem;color:var(--dash-text-muted)">Joined: <span id="deleteUserJoined">—</span></span>
                        </div>
                    </div>
                </div>
                <div class="um-delete-fk-warning" role="alert">
                    <i class="fas fa-database" aria-hidden="true"></i>
                    <div>
                        <strong>Foreign key warning</strong><br>
                        Deleting may affect related records (applications, positions, experience). Users referenced by other records cannot be removed.
                    </div>
                </div>
                <label class="um-delete-confirm" for="deleteConfirmCheckbox">
                    <input type="checkbox" id="deleteConfirmCheckbox" aria-describedby="deleteConfirmHint">
                    <span id="deleteConfirmHint">I understand this action is permanent and may affect linked records.</span>
                </label>
                <p style="color:var(--danger-red);font-weight:600;margin:0;font-size:0.9rem">
                    <i class="fas fa-exclamation-circle"></i> This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="btn" style="background:var(--dash-danger);color:#fff;opacity:0.5" id="deleteSubmitBtn" onclick="executeDeleteUser()" disabled aria-disabled="true">
                    <i class="fas fa-trash"></i> Delete User
                </button>
            </div>
        </div>
    </div>

    <script src="../../assets/js/toast.js"></script>
    <script src="../../assets/js/office-theme.js"></script>
    <script src="../../assets/js/admin-dashboard.js"></script>
    <script src="../../assets/js/notifications.js"></script>
    <script>
        if (typeof BASE_URL === 'undefined') {
            var BASE_URL = '<?= BASE_URL ?>';
        }
    </script>
    <script>
        const ROLE_ADMIN = '<?= ROLE_ADMIN ?>';
        const ROLE_APPLICANT = '<?= ROLE_APPLICANT ?>';
        const UM_PAGE = {
            page: <?= (int)$page ?>,
            totalPages: <?= (int)$totalPages ?>,
            role: <?= json_encode($filterRole) ?>,
            search: <?= json_encode($searchQuery) ?>
        };

        function openModal(el) {
            if (el) {
                el.style.display = 'flex';
                el.classList.add('um-modal-open');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeModalEl(el) {
            if (el) {
                el.style.display = 'none';
                el.classList.remove('um-modal-open');
                document.body.style.overflow = '';
            }
        }

        function updateRole(user) {
            document.getElementById('updateUserId').value = user.id;
            document.getElementById('updateUserName').textContent = user.full_name;
            document.getElementById('updateUserEmail').textContent = user.email;
            document.getElementById('updateRoleSelect').value = user.role;
            const avatar = document.getElementById('updateUserAvatar');
            if (avatar) avatar.textContent = (user.full_name || 'U').charAt(0).toUpperCase();
            const badge = document.getElementById('updateCurrentRoleBadge');
            if (badge) {
                badge.textContent = user.role === ROLE_ADMIN ? 'Administrator' : 'Applicant';
                badge.className = 'um-badge ' + (user.role === ROLE_ADMIN ? 'um-badge-admin' : 'um-badge-applicant');
            }
            umUpdateRoleDescription();
            openModal(document.getElementById('updateRoleModal'));
        }

        function closeUpdateModal() {
            closeModalEl(document.getElementById('updateRoleModal'));
        }

        function confirmDelete(user) {
            const userIdInput = document.getElementById('deleteUserId');
            if (!userIdInput) return;
            userIdInput.value = user.id;
            document.getElementById('deleteUserName').textContent = user.full_name;
            document.getElementById('deleteUserEmail').textContent = user.email;
            document.getElementById('deleteUserRole').textContent = user.role === ROLE_ADMIN ? 'Administrator' : 'Applicant';
            const joined = document.getElementById('deleteUserJoined');
            if (joined && user.created_at) {
                joined.textContent = new Date(user.created_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
            }
            const avatar = document.getElementById('deleteUserAvatar');
            if (avatar) avatar.textContent = (user.full_name || 'U').charAt(0).toUpperCase();
            const confirmCb = document.getElementById('deleteConfirmCheckbox');
            const deleteBtn = document.getElementById('deleteSubmitBtn');
            if (confirmCb) confirmCb.checked = false;
            if (deleteBtn) {
                deleteBtn.disabled = true;
                deleteBtn.setAttribute('aria-disabled', 'true');
                deleteBtn.style.opacity = '0.5';
            }
            openModal(document.getElementById('deleteModal'));
        }

        function closeDeleteModal() {
            closeModalEl(document.getElementById('deleteModal'));
        }

        function executeDeleteUser() {
            const userId = document.getElementById('deleteUserId').value;
            const deleteBtn = document.getElementById('deleteSubmitBtn');
            
            if (!userId || userId === '0' || userId === '') {
                if (typeof Toast !== 'undefined') {
                    Toast.show('Error: User ID is missing. Please try again.', 'error');
                } else {
                    alert('Error: User ID is missing. Please try again.');
                }
                return;
            }
            
            deleteBtn.disabled = true;
            const originalHtml = deleteBtn.innerHTML;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                             document.querySelector('input[name="csrf_token"]')?.value || '';
            
            const apiUrl = `../../api/users.php?action=delete&user_id=${userId}`;
            
            fetch(apiUrl, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    user_id: userId,
                    csrf_token: csrfToken
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.message || data.error || `HTTP ${response.status}: ${response.statusText}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    if (typeof Toast !== 'undefined') {
                        Toast.show(data.message || 'User deleted successfully!', 'success');
                    } else {
                        alert(data.message || 'User deleted successfully!');
                    }
                    closeDeleteModal();
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    const errorMsg = data.message || data.error || 'Failed to delete user';
                    if (typeof Toast !== 'undefined') {
                        Toast.show(errorMsg, 'error');
                    } else {
                        alert(errorMsg);
                    }
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = originalHtml;
                }
            })
            .catch(error => {
                const errorMsg = error.message || 'An error occurred while deleting the user. Please try again.';
                if (typeof Toast !== 'undefined') {
                    Toast.show(errorMsg, 'error');
                } else {
                    alert(errorMsg);
                }
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = originalHtml;
            });
        }

        window.onclick = function(event) {
            const updateModal = document.getElementById('updateRoleModal');
            const deleteModal = document.getElementById('deleteModal');
            if (event.target == updateModal) {
                closeUpdateModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        };

        function umUpdateRoleDescription() {
            const select = document.getElementById('updateRoleSelect');
            const title = document.getElementById('umRoleDescTitle');
            const desc = document.getElementById('umRoleDesc');
            const warn = document.getElementById('umRoleDowngradeWarning');
            const currentBadge = document.getElementById('updateCurrentRoleBadge');
            if (!select || !title || !desc) return;
            const isAdmin = select.value === ROLE_ADMIN;
            title.textContent = isAdmin ? 'Administrator' : 'Applicant';
            desc.innerHTML = '<strong>' + title.textContent + '</strong> — ' + (
                isAdmin
                    ? 'Full access to admin dashboard, user management, reports, and system settings.'
                    : 'Can submit applications, manage profile, and track application status.'
            );
            if (warn) {
                const wasAdmin = currentBadge && currentBadge.textContent === 'Administrator';
                warn.classList.toggle('show', wasAdmin && !isAdmin);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const headerDate = document.getElementById('umHeaderDate');
            if (headerDate) {
                headerDate.textContent = new Date().toLocaleDateString(undefined, {
                    weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
                });
            }

            const roleSelect = document.getElementById('updateRoleSelect');
            if (roleSelect) roleSelect.addEventListener('change', umUpdateRoleDescription);

            const filterRole = document.getElementById('filterRole');
            const filterForm = document.getElementById('umFilterForm');
            if (filterRole && filterForm) {
                filterRole.addEventListener('change', function() {
                    filterForm.submit();
                });
            }

            const deleteConfirm = document.getElementById('deleteConfirmCheckbox');
            const deleteBtn = document.getElementById('deleteSubmitBtn');
            if (deleteConfirm && deleteBtn) {
                deleteConfirm.addEventListener('change', function() {
                    deleteBtn.disabled = !this.checked;
                    deleteBtn.setAttribute('aria-disabled', String(!this.checked));
                    deleteBtn.style.opacity = this.checked ? '1' : '0.5';
                });
            }

            ['umRefreshBtn', 'umToolbarRefresh'].forEach(function(id) {
                const btn = document.getElementById(id);
                if (btn) btn.addEventListener('click', function() {
                    document.body.classList.add('um-loading');
                    window.location.reload();
                });
            });

            const searchInput = document.getElementById('searchUsers');
            const searchClear = document.getElementById('umSearchClear');
            if (searchInput && searchClear) {
                function toggleClear() {
                    searchClear.classList.toggle('visible', searchInput.value.length > 0);
                }
                toggleClear();
                searchInput.addEventListener('input', toggleClear);
                searchClear.addEventListener('click', function() {
                    searchInput.value = '';
                    toggleClear();
                    searchInput.focus();
                });
            }

            document.querySelectorAll('[data-export]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    umHandleExport(this.dataset.export);
                    document.querySelectorAll('.um-export-menu.show').forEach(function(m) { m.classList.remove('show'); });
                });
            });

            [['umExportHeaderBtn', 'umExportHeaderMenu'], ['umExportToolbarBtn', 'umExportToolbarMenu']].forEach(function(pair) {
                const trigger = document.getElementById(pair[0]);
                const menu = document.getElementById(pair[1]);
                if (!trigger || !menu) return;
                trigger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    document.querySelectorAll('.um-export-menu.show').forEach(function(m) {
                        if (m !== menu) m.classList.remove('show');
                    });
                    menu.classList.toggle('show');
                    trigger.setAttribute('aria-expanded', menu.classList.contains('show'));
                });
            });

            document.addEventListener('click', function() {
                document.querySelectorAll('.um-export-menu.show').forEach(function(m) { m.classList.remove('show'); });
                document.querySelectorAll('.um-more-menu.show').forEach(function(m) { m.classList.remove('show'); });
            });

            document.querySelectorAll('.um-more-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const menu = this.parentElement.querySelector('.um-more-menu');
                    if (menu) menu.classList.toggle('show');
                });
            });

            const jumpBtn = document.getElementById('umJumpBtn');
            const jumpInput = document.getElementById('umJumpPage');
            if (jumpBtn && jumpInput && UM_PAGE.totalPages > 1) {
                jumpBtn.addEventListener('click', function() {
                    let p = parseInt(jumpInput.value, 10);
                    if (isNaN(p) || p < 1) p = 1;
                    if (p > UM_PAGE.totalPages) p = UM_PAGE.totalPages;
                    const q = '?page=' + p + '&role=' + encodeURIComponent(UM_PAGE.role) + '&search=' + encodeURIComponent(UM_PAGE.search);
                    window.location.href = 'users.php' + q;
                });
            }

            const selectAll = document.getElementById('umSelectAll');
            const bulkBar = document.getElementById('umBulkBar');
            const bulkCount = document.getElementById('umBulkCount');
            const checkboxes = document.querySelectorAll('.um-user-checkbox');

            function updateBulkBar() {
                const checked = document.querySelectorAll('.um-user-checkbox:checked');
                if (bulkBar) bulkBar.classList.toggle('visible', checked.length > 0);
                if (bulkCount) bulkCount.textContent = checked.length + ' selected';
                if (selectAll) {
                    selectAll.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
                    selectAll.checked = checked.length === checkboxes.length && checkboxes.length > 0;
                }
            }

            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
                    updateBulkBar();
                });
            }
            checkboxes.forEach(function(cb) {
                cb.addEventListener('change', updateBulkBar);
            });

            document.querySelectorAll('[data-um-chip]').forEach(function(chip) {
                chip.addEventListener('click', function() {
                    const filter = this.dataset.umChip;
                    document.querySelectorAll('.user-item').forEach(function(card) {
                        let show = true;
                        if (filter === 'online') show = card.dataset.status === 'online';
                        else if (filter === 'offline') show = card.dataset.status === 'offline';
                        else if (filter === 'inactive') show = card.dataset.status === 'locked';
                        else if (filter === 'verified') show = card.dataset.verified === '1';
                        else if (filter === 'unverified') show = card.dataset.verified === '0';
                        card.style.display = show ? '' : 'none';
                    });
                    document.querySelectorAll('[data-um-chip]').forEach(function(c) { c.classList.remove('active'); });
                    this.classList.add('active');
                    if (typeof Toast !== 'undefined') {
                        Toast.show('Client-side filter applied: ' + filter, 'info');
                    }
                });
            });

            const statusFilter = document.getElementById('umFilterStatus');
            if (statusFilter) {
                statusFilter.addEventListener('change', function() {
                    const val = this.value;
                    document.querySelectorAll('.user-item').forEach(function(card) {
                        if (!val) { card.style.display = ''; return; }
                        let show = true;
                        if (val === 'active') show = card.dataset.status !== 'locked';
                        else if (val === 'locked') show = card.dataset.status === 'locked';
                        else if (val === 'verified') show = card.dataset.verified === '1';
                        else if (val === 'pending') show = card.dataset.verified === '0';
                        card.style.display = show ? '' : 'none';
                    });
                });
            }

            const sortBy = document.getElementById('umSortBy');
            const grid = document.getElementById('usersGrid');
            if (sortBy && grid) {
                sortBy.addEventListener('change', function() {
                    const cards = Array.from(grid.querySelectorAll('.user-item'));
                    cards.sort(function(a, b) {
                        const nameA = a.querySelector('.um-card-name')?.textContent.trim().toLowerCase() || '';
                        const nameB = b.querySelector('.um-card-name')?.textContent.trim().toLowerCase() || '';
                        if (sortBy.value === 'name') return nameA.localeCompare(nameB);
                        if (sortBy.value === 'role') return (a.dataset.role || '').localeCompare(b.dataset.role || '');
                        return 0;
                    });
                    cards.forEach(function(c) { grid.appendChild(c); });
                });
            }

            const onlineEl = document.getElementById('umOnlineCount');
            if (onlineEl) {
                onlineEl.textContent = document.querySelectorAll('.user-item[data-status="online"]').length;
            }

            document.querySelectorAll('.um-bulk-btn[data-bulk="export"]').forEach(function(btn) {
                btn.disabled = false;
                btn.addEventListener('click', function() {
                    umHandleExport('csv', true);
                });
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeUpdateModal();
                    closeDeleteModal();
                }
            });
        });

        function umHandleExport(type, selectedOnly) {
            const rows = [];
            const cards = selectedOnly
                ? Array.from(document.querySelectorAll('.um-user-checkbox:checked')).map(function(cb) {
                    return cb.closest('.user-item');
                })
                : Array.from(document.querySelectorAll('.user-item'));

            cards.forEach(function(card) {
                if (!card) return;
                const get = function(label) {
                    const rows = card.querySelectorAll('.um-detail-row');
                    for (let i = 0; i < rows.length; i++) {
                        if (rows[i].querySelector('.um-detail-label')?.textContent.trim() === label) {
                            return rows[i].querySelector('.um-detail-value')?.textContent.trim() || '';
                        }
                    }
                    return '';
                };
                rows.push([
                    card.querySelector('.um-card-name')?.textContent.trim().replace(/\s*Current User\s*/, '') || '',
                    card.querySelector('.um-card-username')?.textContent.trim().replace('@', '') || '',
                    get('Email'),
                    get('Phone'),
                    card.querySelector('.um-badge-admin, .um-badge-applicant')?.textContent.trim() || '',
                    get('Registration')
                ].join(','));
            });

            const header = 'Name,Username,Email,Phone,Role,Registration\n';
            const csv = header + rows.join('\n');

            if (type === 'copy') {
                navigator.clipboard.writeText(csv).then(function() {
                    if (typeof Toast !== 'undefined') Toast.show('Copied to clipboard', 'success');
                });
                return;
            }
            if (type === 'print' || type === 'pdf') {
                const w = window.open('', '_blank');
                if (w) {
                    w.document.write('<pre>' + csv.replace(/</g, '&lt;') + '</pre>');
                    w.document.close();
                    if (type === 'print') w.print();
                }
                return;
            }
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'users-export.' + (type === 'excel' ? 'xls' : 'csv');
            a.click();
            if (typeof Toast !== 'undefined') Toast.show('Export downloaded', 'success');
        }
    </script>
    <script src="../../assets/js/form-submission-manager.js"></script>
</body>
</html>
