<?php
require_once '../../config/config.php';
require_once '../../includes/models.php';

requireAdmin();

$positionModel = new Position();
$employmentTypeModel = new EmploymentType();
$message = '';
$error = '';

// Check for success/error messages from redirects
if (isset($_GET['success'])) {
    $message = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
if (isset($_GET['info'])) {
    $message = $_GET['info'];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form submission (CSRF + Form Token)
    $validation = FormTokenManager::validateFormSubmission($_POST);
    
    if (!$validation['valid']) {
        if ($validation['error'] === 'duplicate') {
            header('Location: positions.php?info=Action already processed');
            exit();
        }
        $error = $validation['error'];
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create_employment_type') {
            $employmentTypeName = sanitize($_POST['employment_type_name'] ?? '');
            if (empty($employmentTypeName)) {
                $error = 'Employment type name is required';
            } else {
                try {
                    if ($employmentTypeModel->create(['name' => $employmentTypeName])) {
                        // Redirect to prevent token reuse and show success message
                        header('Location: positions.php?success=Employment type created successfully');
                        exit();
                    } else {
                        $error = 'Failed to create employment type';
                    }
                } catch (Exception $e) {
                    $error = 'An error occurred: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete_employment_type') {
            $id = (int)($_POST['id'] ?? 0);
            try {
                // Check if employment type is being used in positions
                if ($employmentTypeModel->isUsedInPositions($id)) {
                    $error = 'Cannot delete employment type: It is currently being used in one or more positions.';
                } else {
                    if ($employmentTypeModel->delete($id)) {
                        // Redirect to prevent token reuse and show success message
                        header('Location: positions.php?success=Employment type deleted successfully');
                        exit();
                    } else {
                        $error = 'Failed to delete employment type';
                    }
                }
            } catch (Exception $e) {
                $error = 'Cannot delete employment type: ' . $e->getMessage();
            }
        } elseif ($action === 'create') {
            // Handle CSE checkboxes
            $careerServiceEligibilities = isset($_POST['career_service_eligibilities']) && is_array($_POST['career_service_eligibilities']) 
                ? implode(',', array_map('sanitize', $_POST['career_service_eligibilities'])) 
                : '';
            
            $data = [
                'title' => sanitize($_POST['title'] ?? ''),
                'description' => sanitize($_POST['description'] ?? ''),
                'requirements' => sanitize($_POST['requirements'] ?? ''),
                'min_experience_years' => (int)($_POST['min_experience_years'] ?? 0),
                'max_applications' => !empty($_POST['max_applications']) ? (int)$_POST['max_applications'] : null,
                'salary_range' => sanitize($_POST['salary_range'] ?? ''),
                'salary_grade' => sanitize($_POST['salary_grade'] ?? ''),
                'training_hours' => !empty($_POST['training_hours']) ? (int)$_POST['training_hours'] : null,
                'career_service_eligibilities' => !empty($careerServiceEligibilities) ? $careerServiceEligibilities : null,
                'location' => sanitize($_POST['location'] ?? ''),
                'employment_type' => sanitize($_POST['employment_type'] ?? 'full-time'),
                'deadline' => !empty($_POST['deadline']) ? $_POST['deadline'] : null,
                'created_by' => $_SESSION['user_id']
            ];

            if (empty($data['title']) || empty($data['description']) || empty($data['requirements'])) {
                $error = 'Title, description, and requirements are required';
            } else {
                try {
                    if ($positionModel->create($data)) {
                        $message = 'Position created successfully';
                    } else {
                        $error = 'Failed to create position';
                    }
                } catch (Exception $e) {
                    $error = 'An error occurred: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'update') {
            // Handle CSE checkboxes
            $careerServiceEligibilities = isset($_POST['career_service_eligibilities']) && is_array($_POST['career_service_eligibilities']) 
                ? implode(',', array_map('sanitize', $_POST['career_service_eligibilities'])) 
                : '';
            
            $id = (int)($_POST['id'] ?? 0);
            $data = [
                'title' => sanitize($_POST['title'] ?? ''),
                'description' => sanitize($_POST['description'] ?? ''),
                'requirements' => sanitize($_POST['requirements'] ?? ''),
                'min_experience_years' => (int)($_POST['min_experience_years'] ?? 0),
                'max_applications' => !empty($_POST['max_applications']) ? (int)$_POST['max_applications'] : null,
                'salary_range' => sanitize($_POST['salary_range'] ?? ''),
                'salary_grade' => sanitize($_POST['salary_grade'] ?? ''),
                'training_hours' => !empty($_POST['training_hours']) ? (int)$_POST['training_hours'] : null,
                'career_service_eligibilities' => !empty($careerServiceEligibilities) ? $careerServiceEligibilities : null,
                'location' => sanitize($_POST['location'] ?? ''),
                'employment_type' => sanitize($_POST['employment_type'] ?? 'full-time'),
                'deadline' => !empty($_POST['deadline']) ? $_POST['deadline'] : null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];

            if (empty($data['title']) || empty($data['description']) || empty($data['requirements'])) {
                $error = 'Title, description, and requirements are required';
            } else {
                try {
                    if ($positionModel->update($id, $data)) {
                        $message = 'Position updated successfully';
                    } else {
                        $error = 'Failed to update position';
                    }
                } catch (Exception $e) {
                    $error = 'An error occurred: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            try {
                if ($positionModel->delete($id)) {
                    // Redirect to prevent token reuse and show success message
                    header('Location: positions.php?success=Position deleted successfully');
                    exit();
                } else {
                    $error = 'Failed to delete position';
                }
            } catch (Exception $e) {
                $error = 'Cannot delete position: ' . $e->getMessage();
            }
        }
    }
}

// Get all positions
$allPositions = $positionModel->findAll('1=1 ORDER BY created_at DESC');

// Get all employment types (including inactive for display)
try {
    $allEmploymentTypes = $employmentTypeModel->findAll('1=1 ORDER BY name ASC');
} catch (Exception $e) {
    $allEmploymentTypes = [];
}

// Get active employment types for dropdown
try {
    $activeEmploymentTypes = $employmentTypeModel->getActiveTypes();
} catch (Exception $e) {
    $activeEmploymentTypes = [];
}

// Create a lookup array for employment types (slug => name)
$employmentTypeLookup = [];
if (!empty($allEmploymentTypes)) {
    foreach ($allEmploymentTypes as $type) {
        if (isset($type['slug']) && isset($type['name'])) {
            $employmentTypeLookup[$type['slug']] = $type['name'];
        }
    }
}

// Check which employment types are in use
$employmentTypeUsage = [];
if (!empty($allEmploymentTypes)) {
    foreach ($allEmploymentTypes as $type) {
        if (isset($type['id'])) {
            try {
                $employmentTypeUsage[$type['id']] = $employmentTypeModel->isUsedInPositions($type['id']);
            } catch (Exception $e) {
                $employmentTypeUsage[$type['id']] = false;
            }
        }
    }
}

// Pagination setup
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 6;
$offset = ($page - 1) * $perPage;

// Calculate pagination
$totalPositions_filtered = count($allPositions);
$totalPages = ceil($totalPositions_filtered / $perPage);
$positions = array_slice($allPositions, $offset, $perPage);

// View-layer metrics (derived from existing data — no additional queries)
$today = date('Y-m-d');
$positionsWithCounts = [];
$totalApplicationsAll = 0;
$applicationsToday = 0;
$activeHiring = 0;
$closedPositions = 0;
$draftPositions = 0;
$departments = [];

$applicationCountsByPosition = $positionModel->getApplicationCountsByPositionIds(array_column($allPositions, 'id'));

foreach ($allPositions as $p) {
    $appCount = $applicationCountsByPosition[(int) $p['id']] ?? 0;
    $totalApplicationsAll += $appCount;
    $positionsWithCounts[] = array_merge($p, [
        'application_count' => (int) $appCount,
        'employment_type_label' => $employmentTypeLookup[$p['employment_type']] ?? ucfirst(str_replace('-', ' ', $p['employment_type'] ?? '')),
    ]);

    if ($p['is_active'] == 1 && (empty($p['deadline']) || $p['deadline'] >= $today)) {
        $activeHiring++;
    }
    if ($p['is_active'] == 0 || (!empty($p['deadline']) && $p['deadline'] < $today)) {
        $closedPositions++;
    }
    if ($p['is_active'] == 0) {
        $draftPositions++;
    }
    if (!empty($p['location'])) {
        $departments[$p['location']] = true;
    }
}

$statsTotal = count($allPositions);
$statsActive = count(array_filter($allPositions, fn($p) => $p['is_active'] == 1));
$statsInactive = count(array_filter($allPositions, fn($p) => $p['is_active'] == 0));
$openDepartments = count($departments);
$avgHiringDays = $statsTotal > 0 ? max(1, (int) round(array_sum(array_map(fn($p) => max(0, (int) ((strtotime($today) - strtotime($p['created_at'])) / 86400)), $allPositions)) / $statsTotal)) : 0;
$paginatedStart = $totalPositions_filtered > 0 ? $offset + 1 : 0;
$paginatedEnd = min($offset + $perPage, $totalPositions_filtered);

$uniqueCampuses = array_keys($departments);
$uniqueSalaryGrades = array_values(array_unique(array_filter(array_column($allPositions, 'salary_grade'))));
$employmentTypeCounts = [];
foreach ($allPositions as $p) {
    $employmentTypeCounts[$p['employment_type']] = ($employmentTypeCounts[$p['employment_type']] ?? 0) + 1;
}

function posDeadlineLabel($deadline) {
    if (empty($deadline)) return ['text' => 'No deadline', 'cls' => 'gray'];
    $today = strtotime(date('Y-m-d'));
    $dl = strtotime($deadline);
    $diff = (int) round(($dl - $today) / 86400);
    if ($diff < 0) return ['text' => 'Expired', 'cls' => 'red'];
    if ($diff === 0) return ['text' => 'Closing Today', 'cls' => 'orange'];
    if ($diff === 1) return ['text' => 'Closing Tomorrow', 'cls' => 'orange'];
    if ($diff <= 3) return ['text' => $diff . ' Days Left', 'cls' => 'orange'];
    return ['text' => date('M j, Y', $dl), 'cls' => 'green'];
}

function posStatusInfo($position, $appCount) {
    $today = date('Y-m-d');
    if ($position['is_active'] == 0) return ['label' => 'Draft', 'cls' => 'draft', 'hiring' => 'Draft'];
    if (!empty($position['deadline']) && $position['deadline'] < $today) return ['label' => 'Closed', 'cls' => 'closed', 'hiring' => 'Expired'];
    $max = (int) ($position['max_applications'] ?? 0);
    if ($max && $appCount >= $max) return ['label' => 'Closed', 'cls' => 'closed', 'hiring' => 'Closed'];
    if ($max && $appCount >= $max * 0.8) return ['label' => 'Nearly Full', 'cls' => 'closing', 'hiring' => 'Nearly Full'];
    if (!empty($position['deadline'])) {
        $diff = (strtotime($position['deadline']) - strtotime($today)) / 86400;
        if ($diff <= 3 && $diff >= 0) return ['label' => 'Closing Soon', 'cls' => 'closing', 'hiring' => 'Open'];
    }
    return ['label' => 'Active', 'cls' => 'active', 'hiring' => 'Open'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Job Hiring Management - Dumaran Hiring System</title>
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../../assets/css/office-theme.css" rel="stylesheet">
    <link href="../../assets/css/admin-dashboard.css" rel="stylesheet">
    <link href="../../assets/css/positions-module.css" rel="stylesheet">
    <style>
        /* Legacy compat shim — main styles in positions-module.css */
        .form-check { display: flex; align-items: center; gap: 8px; }
        .form-check-input { width: 18px; height: 18px; cursor: pointer; accent-color: var(--pos-primary, #2050A0); }
    </style>
</head>
<body class="admin-dashboard positions-page">
    <?php $adminSidebarActive = 'positions'; require __DIR__ . '/_admin_sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <!-- Top Bar -->
        <div class="topbar">
            <button class="mobile-menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="topbar-title">Job Hiring</h1>
            <div class="topbar-actions" style="position: relative;">
                <button type="button" class="pos-theme-toggle" data-theme-toggle aria-label="Toggle dark mode" title="Toggle theme">
                    <i class="fas fa-moon"></i>
                </button>
                <a href="notifications.php" id="notificationBell" class="notification-bell" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <span id="notificationBadge" class="notification-badge" style="display: none;">0</span>
                </a>
                <div id="notificationDropdown" class="notification-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <button id="markAllReadBtn" class="mark-all-read">Mark all as read</button>
                    </div>
                    <div id="notificationList" class="notification-list">
                        <div class="notification-empty">Loading...</div>
                    </div>
                    <div class="notification-footer">
                        <a href="notifications.php">View all notifications</a>
                    </div>
                </div>
                <a href="settings.php" class="topbar-btn">
                    <i class="fas fa-cog"></i>
                </a>
                <a href="../../auth/logout.php" class="topbar-btn confirm-logout" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content positions-page">
            <?php if ($message): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle"></i>
                <div><?= htmlspecialchars($message) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
            <?php endif; ?>

            <!-- Hero Header -->
            <header class="pos-hero" aria-labelledby="hero-title">
                <div class="pos-hero__content">
                    <div class="pos-hero__eyebrow"><i class="fas fa-building"></i> <?= htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') ?></div>
                    <h2 class="pos-hero__title" id="hero-title">Job Hiring Management</h2>
                    <p class="pos-hero__subtitle">Create, manage, publish, and monitor hiring positions across Municipality of Dumaran.</p>
                </div>
                <div class="pos-hero__actions">
                    <button type="button" class="pos-btn pos-btn--primary" onclick="openModal()" aria-label="Create new position">
                        <i class="fas fa-plus"></i> Create New Position
                    </button>
                    <button type="button" class="pos-btn pos-btn--ghost" onclick="PositionsModule.openTemplateModal()" title="Import from template">
                        <i class="fas fa-file-import"></i> Import
                    </button>
                    <button type="button" class="pos-btn pos-btn--ghost" id="exportFiltered" title="Export positions">
                        <i class="fas fa-file-export"></i> Export
                    </button>
                    <button type="button" class="pos-btn pos-btn--ghost" onclick="document.getElementById('filterStatus').value='inactive'; PositionsModule.search('')" title="View archived/inactive">
                        <i class="fas fa-archive"></i> Archive
                    </button>
                </div>
            </header>

            <!-- Smart Toolbar -->
            <section class="pos-toolbar" aria-label="Search and filters">
                <div class="pos-toolbar__row">
                    <div class="pos-search">
                        <i class="fas fa-search pos-search__icon" aria-hidden="true"></i>
                        <input type="search" id="globalSearch" class="pos-search__input" placeholder="Search positions, campus, salary grade…" aria-label="Global search">
                        <kbd class="pos-search__kbd" aria-hidden="true">Ctrl K</kbd>
                    </div>
                    <div class="pos-view-toggle" role="group" aria-label="View mode">
                        <button type="button" class="pos-view-toggle__btn active" data-view="card" aria-pressed="true"><i class="fas fa-th-large"></i> Cards</button>
                        <button type="button" class="pos-view-toggle__btn" data-view="table" aria-pressed="false"><i class="fas fa-list"></i> List</button>
                    </div>
                </div>
                <div class="pos-toolbar__row">
                    <select id="filterStatus" class="pos-filter-select" aria-label="Filter by status">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Draft / Inactive</option>
                        <option value="closing">Closing Soon</option>
                        <option value="expired">Expired</option>
                    </select>
                    <select id="filterEmployment" class="pos-filter-select" aria-label="Filter by employment type">
                        <option value="">Employment Type</option>
                        <?php foreach ($activeEmploymentTypes as $type): ?>
                        <option value="<?= htmlspecialchars($type['slug']) ?>"><?= htmlspecialchars($type['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="filterCampus" class="pos-filter-select" aria-label="Filter by campus">
                        <option value="">Campus / Location</option>
                        <?php foreach ($uniqueCampuses as $campus): ?>
                        <option value="<?= htmlspecialchars($campus) ?>"><?= htmlspecialchars($campus) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="filterSalaryGrade" class="pos-filter-select" aria-label="Filter by salary grade">
                        <option value="">Salary Grade</option>
                        <?php foreach ($uniqueSalaryGrades as $sg): ?>
                        <option value="<?= htmlspecialchars($sg) ?>"><?= htmlspecialchars($sg) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="filterDeadline" class="pos-filter-select" aria-label="Filter by deadline">
                        <option value="">Deadline</option>
                        <option value="week">Closing This Week</option>
                        <option value="month">Closing This Month</option>
                    </select>
                    <select id="sortBy" class="pos-filter-select" aria-label="Sort positions">
                        <option value="newest">Newest</option>
                        <option value="oldest">Oldest</option>
                        <option value="applications">Most Applications</option>
                        <option value="closing">Closing Soon</option>
                        <option value="alpha">Alphabetical</option>
                    </select>
                    <button type="button" class="pos-btn pos-btn--secondary pos-btn--sm" id="resetFilters"><i class="fas fa-undo"></i> Reset</button>
                    <button type="button" class="pos-btn pos-btn--secondary pos-btn--sm" id="savePreset"><i class="fas fa-bookmark"></i> Save Preset</button>
                </div>
                <div class="pos-toolbar__row">
                    <div class="pos-presets" id="filterPresets" aria-label="Saved filter presets"></div>
                </div>
            </section>

            <!-- Bulk Actions Bar -->
            <div class="pos-bulk-bar" id="bulkBar" role="toolbar" aria-label="Bulk actions">
                <span><strong id="bulkCount">0</strong> selected</span>
                <button type="button" class="pos-btn pos-btn--secondary pos-btn--sm" id="bulkExport"><i class="fas fa-file-export"></i> Export Selected</button>
                <label style="font-size:0.85rem;display:flex;align-items:center;gap:6px;margin-left:auto;">
                    <input type="checkbox" id="selectAllPositions" aria-label="Select all positions"> Select all on page
                </label>
            </div>

            <!-- Positions Views -->
            <div class="pos-view-container">
                <div class="pos-server-view">
                    <?php if (empty($positions)): ?>
                    <div class="pos-empty" role="status">
                        <div class="pos-empty__icon"><i class="fas fa-briefcase"></i></div>
                        <h3>No job positions available</h3>
                        <p>Create your first hiring position to begin receiving applications.</p>
                        <button type="button" class="pos-btn pos-btn--primary" onclick="openModal()">
                            <i class="fas fa-plus"></i> Create First Position
                        </button>
                    </div>
                    <?php else: ?>
                    <!-- Card View -->
                    <div id="cardView" class="pos-cards-grid">
                        <?php foreach ($positions as $position):
                            $appCount = $applicationCountsByPosition[(int) $position['id']] ?? 0;
                            $status = posStatusInfo($position, $appCount);
                            $deadline = posDeadlineLabel($position['deadline'] ?? null);
                            $max = (int)($position['max_applications'] ?? 30);
                            $pct = $max ? min(100, round(($appCount / $max) * 100)) : min(100, $appCount * 3);
                            $posData = array_merge($position, [
                                'application_count' => $appCount,
                                'employment_type_label' => $employmentTypeLookup[$position['employment_type']] ?? ucfirst(str_replace('-', ' ', $position['employment_type'])),
                            ]);
                            $posJsonSafe = json_encode($posData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG);
                        ?>
                        <article class="pos-card" data-id="<?= $position['id'] ?>">
                            <div class="pos-card__accent"></div>
                            <?php if ($status['cls'] === 'closing'): ?><div class="pos-card__ribbon" style="background:var(--pos-warning)">Closing</div><?php endif; ?>
                            <div class="pos-card__body">
                                <div class="pos-card__header">
                                    <input type="checkbox" class="pos-card__checkbox" data-select-id="<?= $position['id'] ?>" aria-label="Select <?= htmlspecialchars($position['title']) ?>">
                                    <div style="flex:1">
                                        <h4 class="pos-card__title" data-title="<?= htmlspecialchars($position['title']) ?>" onclick='PositionsModule.preview(<?= $position['id'] ?>)'><?= htmlspecialchars($position['title']) ?></h4>
                                        <div class="pos-card__meta">
                                            <span class="pos-badge pos-badge--<?= $status['cls'] ?>"><i class="fas fa-circle" style="font-size:0.5rem"></i> <?= $status['label'] ?></span>
                                            <span class="pos-badge pos-badge--type"><?= htmlspecialchars($employmentTypeLookup[$position['employment_type']] ?? ucfirst(str_replace('-', ' ', $position['employment_type']))) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="pos-card__details">
                                    <div class="pos-card__detail"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($position['location'] ?: '—') ?></div>
                                    <div class="pos-card__detail"><i class="fas fa-layer-group"></i> <?= htmlspecialchars($position['salary_grade'] ?: '—') ?></div>
                                    <div class="pos-card__detail"><i class="fas fa-money-bill-wave"></i> <?= htmlspecialchars($position['salary_range'] ?: '—') ?></div>
                                    <div class="pos-card__detail"><i class="fas fa-briefcase"></i> <?= (int)$position['min_experience_years'] ?>y exp</div>
                                    <div class="pos-card__detail"><i class="fas fa-clock"></i> <?= $position['training_hours'] ? (int)$position['training_hours'] . 'h training' : '—' ?></div>
                                    <div class="pos-card__detail"><i class="fas fa-certificate"></i> <?= $position['career_service_eligibilities'] ? 'CSE Required' : '—' ?></div>
                                </div>
                                <?php if (!empty($position['deadline'])): ?>
                                <div class="pos-deadline pos-deadline--<?= $deadline['cls'] ?>"><i class="fas fa-calendar-alt"></i> <?= htmlspecialchars($deadline['text']) ?></div>
                                <?php endif; ?>
                                <div class="pos-progress">
                                    <div class="pos-progress__label"><span>Applications</span><span><?= $appCount ?> / <?= $position['max_applications'] ?: '∞' ?></span></div>
                                    <div class="pos-progress__bar"><div class="pos-progress__fill" style="width:<?= $pct ?>%"></div></div>
                                </div>
                                <p class="pos-card__excerpt"><?= htmlspecialchars(substr($position['description'], 0, 120)) ?><?= strlen($position['description']) > 120 ? '…' : '' ?></p>
                                <div class="pos-card__posted"><i class="fas fa-calendar-plus"></i> Posted <?= date('M j, Y', strtotime($position['created_at'])) ?> · Hiring: <?= $status['hiring'] ?></div>
                                <div class="pos-card__footer">
                                    <button type="button" class="pos-btn pos-btn--secondary pos-btn--sm" onclick='PositionsModule.preview(<?= $position['id'] ?>)' title="View"><i class="fas fa-eye"></i></button>
                                    <button type="button" class="pos-btn pos-btn--secondary pos-btn--sm" onclick='PositionsModule.edit(<?= $posJsonSafe ?>)' title="Edit"><i class="fas fa-edit"></i></button>
                                    <button type="button" class="pos-btn pos-btn--secondary pos-btn--sm" onclick='PositionsModule.duplicate(<?= $posJsonSafe ?>)' title="Duplicate"><i class="fas fa-copy"></i></button>
                                    <button type="button" class="pos-btn pos-btn--secondary pos-btn--sm" onclick="PositionsModule.confirmDelete(<?= $position['id'] ?>, '<?= htmlspecialchars(addslashes($position['title'])) ?>', <?= $appCount ?>)" title="Delete"><i class="fas fa-trash" style="color:var(--pos-danger)"></i></button>
                                </div>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>

                    <!-- Table View -->
                    <div id="tableView" class="pos-table-wrap" style="display:none">
                        <table class="pos-table" role="grid" aria-label="Positions table">
                            <thead>
                                <tr>
                                    <th scope="col"><span class="sr-only">Select</span></th>
                                    <th scope="col">Position</th>
                                    <th scope="col">Campus</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Applications</th>
                                    <th scope="col">Deadline</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($positions as $position):
                                    $appCount = $applicationCountsByPosition[(int) $position['id']] ?? 0;
                                    $status = posStatusInfo($position, $appCount);
                                    $posData = array_merge($position, [
                                        'application_count' => $appCount,
                                        'employment_type_label' => $employmentTypeLookup[$position['employment_type']] ?? '',
                                    ]);
                                    $posJsonSafe = json_encode($posData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG);
                                ?>
                                <tr>
                                    <td><input type="checkbox" class="pos-card__checkbox" data-select-id="<?= $position['id'] ?>"></td>
                                    <td><strong><?= htmlspecialchars($position['title']) ?></strong></td>
                                    <td><?= htmlspecialchars($position['location'] ?: '—') ?></td>
                                    <td><?= htmlspecialchars($employmentTypeLookup[$position['employment_type']] ?? '') ?></td>
                                    <td><span class="pos-badge pos-badge--<?= $status['cls'] ?>"><?= $status['label'] ?></span></td>
                                    <td><?= $appCount ?></td>
                                    <td><?= $position['deadline'] ? date('M j, Y', strtotime($position['deadline'])) : '—' ?></td>
                                    <td><div class="pos-table__actions">
                                        <button type="button" class="pos-btn pos-btn--secondary pos-btn--sm pos-btn--icon" onclick='PositionsModule.preview(<?= $position['id'] ?>)'><i class="fas fa-eye"></i></button>
                                        <button type="button" class="pos-btn pos-btn--secondary pos-btn--sm pos-btn--icon" onclick='PositionsModule.edit(<?= $posJsonSafe ?>)'><i class="fas fa-edit"></i></button>
                                        <button type="button" class="pos-btn pos-btn--secondary pos-btn--sm pos-btn--icon" onclick="PositionsModule.confirmDelete(<?= $position['id'] ?>, '<?= htmlspecialchars(addslashes($position['title'])) ?>', <?= $appCount ?>)"><i class="fas fa-trash"></i></button>
                                    </div></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <nav class="pos-pagination" aria-label="Positions pagination">
                        <div class="pos-pagination__info">
                            Showing <?= $paginatedStart ?>–<?= $paginatedEnd ?> of <?= $totalPositions_filtered ?> positions
                        </div>
                        <?php if ($totalPages > 1): ?>
                        <div class="pos-pagination__controls">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" class="pos-btn pos-btn--secondary pos-btn--sm"><i class="fas fa-chevron-left"></i> Previous</a>
                            <?php endif; ?>
                            <div class="pos-pagination__pages">
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?= $i ?>" class="pos-pagination__page <?= $i === $page ? 'active' : '' ?>" aria-current="<?= $i === $page ? 'page' : 'false' ?>"><?= $i ?></a>
                                <?php endfor; ?>
                            </div>
                            <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>" class="pos-btn pos-btn--secondary pos-btn--sm">Next <i class="fas fa-chevron-right"></i></a>
                            <?php endif; ?>
                            <div class="pos-pagination__per-page">
                                <span>Per page:</span>
                                <select class="pos-filter-select" disabled title="Server pagination fixed at 6">
                                    <option selected>6</option>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                    </nav>
                    <?php endif; ?>
                </div>

                <!-- Client-filtered view (JS rendered) -->
                <div class="pos-client-view">
                    <div id="clientCardView" class="pos-cards-grid"></div>
                    <div id="clientTableView" class="pos-table-wrap" style="display:none">
                        <table class="pos-table"><thead><tr>
                            <th></th><th>Position</th><th>Campus</th><th>Type</th><th>Status</th><th>Apps</th><th>Deadline</th><th>Actions</th>
                        </tr></thead><tbody id="clientTableBody"></tbody></table>
                    </div>
                    <nav class="pos-pagination">
                        <div class="pos-pagination__info" id="clientPaginationInfo"></div>
                        <div class="pos-pagination__pages" id="clientPaginationPages"></div>
                    </nav>
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
                <span>Positions</span>
            </a>
            <a href="applications.php" class="mobile-nav-item">
                <i class="fas fa-file-alt"></i>
                <span>Applications</span>
            </a>
            <a href="users.php" class="mobile-nav-item">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
        </div>
    </nav>

    <!-- FAB -->
    <div class="pos-fab-wrap">
        <div class="pos-fab-menu" id="posFabMenu" role="menu">
            <button type="button" class="pos-fab-menu__item" onclick="openModal()" role="menuitem"><i class="fas fa-plus"></i> New Position</button>
            <button type="button" class="pos-fab-menu__item" onclick="PositionsModule.openTemplateModal()" role="menuitem"><i class="fas fa-file-import"></i> Import Positions</button>
            <button type="button" class="pos-fab-menu__item" id="fabExport" role="menuitem"><i class="fas fa-file-export"></i> Export Positions</button>
            <button type="button" class="pos-fab-menu__item" onclick="PositionsModule.openTemplateModal()" role="menuitem"><i class="fas fa-clone"></i> Templates</button>
        </div>
        <button type="button" class="pos-fab" id="posFab" aria-label="Quick actions" aria-expanded="false"><i class="fas fa-plus"></i></button>
    </div>

    <!-- Preview Drawer -->
    <div class="pos-drawer-overlay" id="drawerOverlay" aria-hidden="true"></div>
    <aside class="pos-drawer" id="previewDrawer" role="dialog" aria-labelledby="drawerTitle" aria-hidden="true">
        <div class="pos-drawer__header">
            <h3 id="drawerTitle" style="margin:0;font-size:1.1rem">Position Preview</h3>
            <button type="button" class="pos-modal__close" id="drawerClose" aria-label="Close preview"><i class="fas fa-times"></i></button>
        </div>
        <div class="pos-drawer__body" id="drawerBody"></div>
    </aside>

    <!-- Search Palette -->
    <div class="pos-drawer-overlay" id="searchPaletteOverlay" style="z-index:1199"></div>
    <div class="pos-search-palette" id="searchPalette" role="dialog" aria-label="Quick search">
        <input type="search" class="pos-search-palette__input" id="searchPaletteInput" placeholder="Search positions…" aria-label="Quick search">
        <div class="pos-search-palette__recent">Recent searches</div>
        <div id="recentSearches" style="padding:8px 20px 16px;display:flex;flex-wrap:wrap;gap:8px"></div>
    </div>

    <!-- Position Data for JS -->
    <script type="application/json" id="positionsData"><?= json_encode($positionsWithCounts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

    <!-- Position Modal (Stepper) -->
    <div class="pos-modal-overlay" id="positionModal" role="dialog" aria-labelledby="modalTitle" aria-hidden="true">
        <div class="pos-modal">
            <div class="pos-modal__header">
                <h3 class="pos-modal__title" id="modalTitle">Create New Position</h3>
                <button type="button" class="pos-modal__close" onclick="closeModal()" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <nav class="pos-stepper" aria-label="Form progress">
                <div class="pos-stepper__step active" data-step="0"><div class="pos-stepper__dot">1</div><div class="pos-stepper__label">Basic</div></div>
                <div class="pos-stepper__step" data-step="1"><div class="pos-stepper__dot">2</div><div class="pos-stepper__label">Details</div></div>
                <div class="pos-stepper__step" data-step="2"><div class="pos-stepper__dot">3</div><div class="pos-stepper__label">Qualifications</div></div>
                <div class="pos-stepper__step" data-step="3"><div class="pos-stepper__dot">4</div><div class="pos-stepper__label">Salary</div></div>
                <div class="pos-stepper__step" data-step="4"><div class="pos-stepper__dot">5</div><div class="pos-stepper__label">Eligibility</div></div>
                <div class="pos-stepper__step" data-step="5"><div class="pos-stepper__dot">6</div><div class="pos-stepper__label">Preview</div></div>
                <div class="pos-stepper__step" data-step="6"><div class="pos-stepper__dot">7</div><div class="pos-stepper__label">Publish</div></div>
            </nav>
            <form method="POST" id="positionForm" class="prevent-double-submit" novalidate>
                <div class="pos-modal__body">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <?php FormTokenManager::renderFormTokenInput('position_form'); ?>
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="positionId">
                    <input type="hidden" name="salary_range" id="salary_range">

                    <!-- Step 1: Basic Information -->
                    <div class="pos-form-step active" data-step="0">
                        <div class="pos-form-row">
                            <div class="pos-float-group pos-float-group--icon">
                                <i class="fas fa-briefcase pos-float-icon"></i>
                                <input type="text" class="pos-float-input" id="title" name="title" placeholder=" " required maxlength="100" data-max-length="100" aria-required="true">
                                <label for="title" class="pos-float-label pos-float-required">Position Title</label>
                                <div class="pos-char-count">0 / 100</div>
                            </div>
                            <div class="pos-float-group">
                                <select class="pos-float-select pos-float-input" id="employment_type" name="employment_type">
                                    <?php if (empty($activeEmploymentTypes)): ?>
                                    <option value="full-time">Full-time</option>
                                    <?php else: foreach ($activeEmploymentTypes as $type): ?>
                                    <option value="<?= htmlspecialchars($type['slug']) ?>"><?= htmlspecialchars($type['name']) ?></option>
                                    <?php endforeach; endif; ?>
                                </select>
                                <label for="employment_type" class="pos-float-label">Employment Type</label>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;margin-bottom:16px">
                            <button type="button" class="pos-btn pos-btn--secondary pos-btn--sm" onclick="openEmploymentTypeModal()"><i class="fas fa-plus"></i> Add Type</button>
                        </div>
                        <?php if (!empty($allEmploymentTypes)): ?>
                        <div class="pos-et-panel">
                            <div class="pos-et-panel__header">
                                <span><i class="fas fa-tags"></i> Employment Types</span>
                                <input type="search" class="pos-et-panel__search" id="etSearch" placeholder="Search types…" aria-label="Search employment types">
                            </div>
                            <div class="pos-et-list">
                                <?php foreach ($allEmploymentTypes as $type):
                                    $typeId = (int)($type['id'] ?? 0);
                                    if ($typeId <= 0) continue;
                                    $typeName = $type['name'] ?? 'Unknown';
                                    $isInUse = !empty($employmentTypeUsage[$typeId]);
                                    $isActive = ($type['is_active'] ?? 0) == 1;
                                    $usageCount = $employmentTypeCounts[$type['slug'] ?? ''] ?? 0;
                                ?>
                                <div class="pos-et-item">
                                    <div>
                                        <strong><?= htmlspecialchars($typeName) ?></strong>
                                        <?php if (!$isActive): ?><span class="pos-badge pos-badge--archived" style="margin-left:6px">Inactive</span><?php endif; ?>
                                        <div class="pos-et-item__meta"><?= $usageCount ?> position<?= $usageCount !== 1 ? 's' : '' ?> · <?= $isInUse ? 'In use' : 'Unused' ?></div>
                                    </div>
                                    <button type="button" class="pos-btn pos-btn--sm pos-btn--secondary"
                                        data-type-id="<?= $typeId ?>"
                                        data-type-name="<?= htmlspecialchars($typeName, ENT_QUOTES) ?>"
                                        data-in-use="<?= $isInUse ? '1' : '0' ?>"
                                        onclick="confirmDeleteEmploymentType(this.dataset.typeId, this.dataset.typeName, this.dataset.inUse === '1')"
                                        title="Delete type" <?= $isInUse ? 'disabled style="opacity:0.5"' : '' ?>>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="pos-float-group pos-float-group--icon" style="margin-top:16px">
                            <i class="fas fa-map-marker-alt pos-float-icon"></i>
                            <input type="text" class="pos-float-input" id="location" name="location" placeholder=" ">
                            <label for="location" class="pos-float-label">Campus / Location</label>
                        </div>
                    </div>

                    <!-- Step 2: Job Details -->
                    <div class="pos-form-step" data-step="1">
                        <div class="pos-float-group">
                            <textarea class="pos-float-textarea pos-float-input" id="description" name="description" placeholder=" " required data-max-length="5000" aria-required="true"></textarea>
                            <label for="description" class="pos-float-label pos-float-required">Job Description</label>
                            <div class="pos-char-count">0 / 5000</div>
                        </div>
                        <div class="pos-float-group">
                            <textarea class="pos-float-textarea pos-float-input" id="requirements" name="requirements" placeholder=" " required data-max-length="5000" aria-required="true"></textarea>
                            <label for="requirements" class="pos-float-label pos-float-required">Requirements</label>
                            <div class="pos-char-count">0 / 5000</div>
                        </div>
                        <div class="pos-form-row">
                            <div class="pos-float-group pos-float-group--icon">
                                <i class="fas fa-calendar pos-float-icon"></i>
                                <input type="date" class="pos-float-input" id="deadline" name="deadline" min="<?= date('Y-m-d') ?>">
                                <label for="deadline" class="pos-float-label">Application Deadline</label>
                            </div>
                            <div class="pos-float-group pos-float-group--icon">
                                <i class="fas fa-users pos-float-icon"></i>
                                <input type="number" class="pos-float-input" id="max_applications" name="max_applications" min="1" placeholder=" ">
                                <label for="max_applications" class="pos-float-label">Max Applications</label>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Qualifications -->
                    <div class="pos-form-step" data-step="2">
                        <div class="pos-form-row">
                            <div class="pos-float-group pos-float-group--icon">
                                <i class="fas fa-briefcase pos-float-icon"></i>
                                <input type="number" class="pos-float-input" id="min_experience_years" name="min_experience_years" min="0" value="0" placeholder=" ">
                                <label for="min_experience_years" class="pos-float-label">Minimum Experience (years)</label>
                            </div>
                            <div class="pos-float-group pos-float-group--icon">
                                <i class="fas fa-clock pos-float-icon"></i>
                                <input type="number" class="pos-float-input" id="training_hours" name="training_hours" min="0" placeholder=" ">
                                <label for="training_hours" class="pos-float-label">Training Hours</label>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Salary & Benefits -->
                    <div class="pos-form-step" data-step="3">
                        <div class="pos-form-row">
                            <div class="pos-float-group pos-float-group--icon">
                                <i class="fas fa-peso-sign pos-float-icon"></i>
                                <input type="text" class="pos-float-input" id="salary_min" placeholder=" " inputmode="numeric" aria-label="Minimum salary">
                                <label for="salary_min" class="pos-float-label">Minimum Salary (₱)</label>
                            </div>
                            <div class="pos-float-group pos-float-group--icon">
                                <i class="fas fa-peso-sign pos-float-icon"></i>
                                <input type="text" class="pos-float-input" id="salary_max" placeholder=" " inputmode="numeric" aria-label="Maximum salary">
                                <label for="salary_max" class="pos-float-label">Maximum Salary (₱)</label>
                            </div>
                        </div>
                        <div class="pos-float-group pos-float-group--icon">
                            <i class="fas fa-layer-group pos-float-icon"></i>
                            <input type="text" class="pos-float-input" id="salary_grade" name="salary_grade" placeholder=" " maxlength="50">
                            <label for="salary_grade" class="pos-float-label">Salary Grade (e.g., SG 24)</label>
                        </div>
                    </div>

                    <!-- Step 5: Eligibility -->
                    <div class="pos-form-step" data-step="4">
                        <p style="font-size:0.85rem;color:var(--gray-500);margin-bottom:16px">Select all required Career Service Eligibilities for this position.</p>
                        <div class="pos-cse-grid">
                            <?php
                            $cseOptions = [
                                ['value' => 'Professional Eligibility', 'icon' => 'fa-user-tie', 'desc' => 'First-level and second-level positions (clerical, technical, supervisory, managerial).'],
                                ['value' => 'Sub-Professional Eligibility', 'icon' => 'fa-user', 'desc' => 'Qualifies for first-level positions (clerical, custodial).'],
                                ['value' => 'Career Service Executive Eligibility (CSE-E)', 'icon' => 'fa-crown', 'desc' => 'For high-level executive positions.'],
                                ['value' => 'Bar/Board Eligibility (RA 1080)', 'icon' => 'fa-gavel', 'desc' => 'Licensed professionals (lawyers, doctors, engineers).'],
                            ];
                            foreach ($cseOptions as $cse): ?>
                            <label class="pos-cse-card">
                                <input type="checkbox" name="career_service_eligibilities[]" value="<?= htmlspecialchars($cse['value']) ?>">
                                <span class="pos-cse-card__check"><i class="fas fa-check"></i></span>
                                <div class="pos-cse-card__icon"><i class="fas <?= $cse['icon'] ?>"></i></div>
                                <div class="pos-cse-card__name"><?= htmlspecialchars($cse['value']) ?></div>
                                <div class="pos-cse-card__desc"><?= htmlspecialchars($cse['desc']) ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Step 6: Preview -->
                    <div class="pos-form-step" data-step="5">
                        <div id="previewContent"></div>
                    </div>

                    <!-- Step 7: Publish -->
                    <div class="pos-form-step" data-step="6">
                        <div style="text-align:center;padding:24px">
                            <div style="width:72px;height:72px;border-radius:50%;background:rgba(22,163,74,0.12);color:var(--pos-success);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:2rem"><i class="fas fa-rocket"></i></div>
                            <h4 style="margin-bottom:8px">Ready to Publish</h4>
                            <p style="color:var(--gray-500);font-size:0.9rem;margin-bottom:20px">Review your position details and click publish to make it live for applicants.</p>
                        </div>
                        <div id="statusDiv" style="display:none;padding:16px;background:var(--pos-bg);border-radius:var(--pos-radius-sm);border:1px solid var(--pos-border)">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                <label class="form-check-label" for="is_active">Active Position</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="pos-modal__footer">
                    <button type="button" class="pos-btn pos-btn--secondary" id="stepPrev" style="display:none"><i class="fas fa-arrow-left"></i> Back</button>
                    <div style="display:flex;gap:8px;margin-left:auto">
                        <button type="button" class="pos-btn pos-btn--secondary" onclick="closeModal()">Cancel</button>
                        <button type="button" class="pos-btn pos-btn--primary" id="stepNext">Next <i class="fas fa-arrow-right"></i></button>
                        <button type="submit" class="pos-btn pos-btn--primary" id="submitBtn" style="display:none"><i class="fas fa-check"></i> Publish Position</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Template Modal -->
    <div class="pos-modal-overlay" id="templateModal" role="dialog" aria-label="Position templates">
        <div class="pos-modal pos-modal--sm">
            <div class="pos-modal__header">
                <h3 class="pos-modal__title">Position Templates</h3>
                <button type="button" class="pos-modal__close" onclick="PositionsModule.closeTemplateModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="pos-modal__body">
                <p style="font-size:0.85rem;color:var(--gray-500);margin-bottom:16px">Choose a template to pre-fill common fields. You can edit everything before publishing.</p>
                <div class="pos-template-grid">
                    <button type="button" class="pos-template-card" data-template="instructor"><i class="fas fa-chalkboard-teacher"></i><span>Instructor</span></button>
                    <button type="button" class="pos-template-card" data-template="admin_assistant"><i class="fas fa-user-tie"></i><span>Admin Assistant</span></button>
                    <button type="button" class="pos-template-card" data-template="registrar"><i class="fas fa-folder-open"></i><span>Registrar Staff</span></button>
                    <button type="button" class="pos-template-card" data-template="hr_officer"><i class="fas fa-users-cog"></i><span>HR Officer</span></button>
                    <button type="button" class="pos-template-card" data-template="it_personnel"><i class="fas fa-laptop-code"></i><span>IT Personnel</span></button>
                    <button type="button" class="pos-template-card" data-template="security"><i class="fas fa-shield-alt"></i><span>Security</span></button>
                    <button type="button" class="pos-template-card" data-template="faculty"><i class="fas fa-graduation-cap"></i><span>Faculty</span></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Employment Type Modal -->
    <div class="pos-modal-overlay" id="employmentTypeModal" role="dialog" aria-hidden="true">
        <div class="pos-modal pos-modal--sm">
            <div class="pos-modal__header">
                <h3 class="pos-modal__title">Add Employment Type</h3>
                <button type="button" class="pos-modal__close" onclick="closeEmploymentTypeModal()"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" id="employmentTypeForm" class="prevent-double-submit">
                <div class="pos-modal__body">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <?php FormTokenManager::renderFormTokenInput('employment_type_form'); ?>
                    <input type="hidden" name="action" value="create_employment_type">
                    <div class="pos-float-group">
                        <input type="text" class="pos-float-input" id="employment_type_name" name="employment_type_name" required maxlength="50" placeholder=" ">
                        <label for="employment_type_name" class="pos-float-label pos-float-required">Employment Type Name</label>
                    </div>
                    <div class="alert alert-info" style="margin-top:16px"><i class="fas fa-info-circle"></i><div><strong>Note:</strong> Available immediately after creation.</div></div>
                </div>
                <div class="pos-modal__footer">
                    <button type="button" class="pos-btn pos-btn--secondary" onclick="closeEmploymentTypeModal()">Cancel</button>
                    <button type="submit" class="pos-btn pos-btn--primary"><i class="fas fa-plus"></i> Add Type</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Employment Type Modal -->
    <div class="pos-modal-overlay" id="deleteEmploymentTypeModal" role="alertdialog" aria-hidden="true">
        <div class="pos-modal pos-modal--sm">
            <div class="pos-modal__header">
                <h3 class="pos-modal__title">Delete Employment Type</h3>
                <button type="button" class="pos-modal__close" onclick="closeDeleteEmploymentTypeModal()"><i class="fas fa-times"></i></button>
            </div>
            <div class="pos-modal__body">
                <div class="alert alert-warning" id="employmentTypeDeleteWarning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>Delete "<strong id="employmentTypeDeleteName"></strong>"?</div>
                </div>
                <div class="alert alert-danger" id="employmentTypeInUseWarning" style="display:none">
                    <i class="fas fa-ban"></i>
                    <div>This type is in use. Update positions first.</div>
                </div>
            </div>
            <div class="pos-modal__footer">
                <button type="button" class="pos-btn pos-btn--secondary" onclick="closeDeleteEmploymentTypeModal()">Cancel</button>
                <form method="POST" id="deleteEmploymentTypeForm" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <?php FormTokenManager::renderFormTokenInput('delete_employment_type_form'); ?>
                    <input type="hidden" name="action" value="delete_employment_type">
                    <input type="hidden" name="id" id="deleteEmploymentTypeId">
                    <button type="submit" class="pos-btn pos-btn--danger" id="deleteEmploymentTypeBtn"><i class="fas fa-trash"></i> Delete</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Position Modal -->
    <div class="pos-modal-overlay" id="deleteModal" role="alertdialog" aria-hidden="true">
        <div class="pos-modal pos-modal--sm">
            <div class="pos-modal__body" style="text-align:center;padding:32px 24px">
                <div class="pos-delete-icon"><i class="fas fa-trash-alt"></i></div>
                <div class="pos-delete-name" id="deletePositionName">Position</div>
                <p style="color:var(--gray-500);font-size:0.9rem;margin-bottom:8px">
                    <strong id="deleteAppCount">0</strong> application(s) will be affected.
                </p>
                <p style="color:var(--pos-danger);font-size:0.85rem">This action cannot be undone.</p>
            </div>
            <div class="pos-modal__footer" style="justify-content:center">
                <button type="button" class="pos-btn pos-btn--secondary" onclick="closeDeleteModal()">Cancel</button>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <?php FormTokenManager::renderFormTokenInput('delete_position_form'); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteId">
                    <button type="submit" class="pos-btn pos-btn--danger"><i class="fas fa-trash"></i> Delete Position</button>
                </form>
            </div>
        </div>
    </div>

    <script src="../../assets/js/toast.js"></script>
    <script src="../../assets/js/office-theme.js"></script>
    <script src="../../assets/js/admin-dashboard.js"></script>
    <script src="../../assets/js/notifications.js"></script>
    <script>if (typeof BASE_URL === 'undefined') { var BASE_URL = '<?= BASE_URL ?>'; }</script>
    <script src="../../assets/js/positions-module.js"></script>
    <script src="../../assets/js/form-submission-manager.js"></script>
    <script>
        document.getElementById('fabExport')?.addEventListener('click', () => {
            if (window.PositionsModule) PositionsModule.exportCSV?.() || document.getElementById('exportFiltered')?.click();
        });
        document.addEventListener('DOMContentLoaded', () => {
            if (window.PositionsModule && PositionsModule.renderPresets) PositionsModule.renderPresets();
        });
    </script>
</body>
</html>