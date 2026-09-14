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

// ── View-layer display metrics (no query/filter/business-rule changes) ──
$hour = (int) date('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
$firstName = explode(' ', trim($user['full_name'] ?? 'Applicant'))[0];
$hasDocuments = !empty($user['ipcr_file_path']) || !empty($user['pe_file_path']);

$validationCache = [];
$recommendedCount = 0;
$uniqueLocations = [];
$searchTitles = [];

$computeMatchScore = function ($validation) use ($hasDocuments) {
    $details = $validation['details'];
    $total = 0;
    $passed = 0;
    foreach (['experience', 'career_service_eligibilities', 'training_hours'] as $key) {
        if (isset($details[$key]['meets'])) {
            $total++;
            if ($details[$key]['meets']) {
                $passed++;
            }
        }
    }
    $total++;
    if ($hasDocuments) {
        $passed++;
    }
    if ($total === 0) {
        return 85;
    }
    $pct = (int) round(($passed / $total) * 100);
    if ($validation['meets_requirements']) {
        return min(100, max($pct, 85));
    }
    return max(25, $pct);
};

$getMatchMeta = function ($score) {
    if ($score >= 90) {
        return ['Excellent Match', 'Your profile meets almost all requirements.', 'green'];
    }
    if ($score >= 70) {
        return ['Good Match', 'You meet most requirements for this role.', 'yellow'];
    }
    return ['Partial Match', 'Some requirements still need attention.', 'red'];
};

$getJobRibbon = function ($position) {
    $today = strtotime('today');
    $created = !empty($position['created_at']) ? strtotime($position['created_at']) : 0;
    $deadline = !empty($position['deadline']) ? strtotime($position['deadline']) : null;
    $count = (int) ($position['application_count'] ?? 0);
    $max = (int) ($position['max_applications'] ?? 0);

    if ($deadline) {
        $daysLeft = (int) round(($deadline - $today) / 86400);
        if ($daysLeft >= 0 && $daysLeft <= 3) {
            return ['Urgent Hiring', 'urgent'];
        }
        if ($daysLeft >= 0 && $daysLeft <= 7) {
            return ['Closing Soon', 'closing'];
        }
    }
    if ($created && ($today - $created) <= 7 * 86400) {
        return ['New', 'new'];
    }
    if ($max && $count >= (int) ($max * 0.7)) {
        return ['High Demand', 'demand'];
    }
    if ($count >= 10) {
        return ['Popular', 'demand'];
    }
    return [null, null];
};

$buildRequirementChecks = function ($validation) use ($hasDocuments) {
    $checks = [];
    $details = $validation['details'];
    if (isset($details['experience']['meets'])) {
        $checks[] = ['label' => 'Experience', 'met' => $details['experience']['meets']];
    }
    if (isset($details['career_service_eligibilities']['meets'])) {
        $checks[] = ['label' => 'Eligibility', 'met' => $details['career_service_eligibilities']['meets']];
    }
    if (isset($details['training_hours']['meets'])) {
        $checks[] = ['label' => 'Training', 'met' => $details['training_hours']['meets']];
    }
    $checks[] = ['label' => 'Documents', 'met' => $hasDocuments];
    return $checks;
};

foreach ($allPositions as $pos) {
    if (!empty($pos['location'])) {
        $uniqueLocations[$pos['location']] = true;
    }
    $searchTitles[] = $pos['title'];
    $validationCache[$pos['id']] = $requirementValidator->validateRequirements($userId, $pos['id']);
    if ($validationCache[$pos['id']]['meets_requirements'] && !isset($appliedPositions[$pos['id']])) {
        $recommendedCount++;
    }
}

$scoredForRec = [];
foreach ($allPositions as $pos) {
    if (isset($appliedPositions[$pos['id']])) {
        continue;
    }
    $pos['_score'] = $computeMatchScore($validationCache[$pos['id']]);
    $pos['_validation'] = $validationCache[$pos['id']];
    $scoredForRec[] = $pos;
}
usort($scoredForRec, fn($a, $b) => $b['_score'] <=> $a['_score']);
$topRecommended = array_slice($scoredForRec, 0, 3);

$buildPositionData = function ($position) use (
    $appliedPositions, $validationCache, $requirementValidator, $userId, $user,
    $totalExperience, $computeMatchScore, $getMatchMeta, $buildRequirementChecks
) {
    $hasApplied = isset($appliedPositions[$position['id']]);
    $applicationStatus = $hasApplied ? $appliedPositions[$position['id']] : null;
    $requirementValidation = $validationCache[$position['id']] ?? $requirementValidator->validateRequirements($userId, $position['id']);
    $meetsRequirements = $requirementValidation['meets_requirements'];
    $cseValidation = $requirementValidation['details']['career_service_eligibilities'] ?? null;
    $meetsCSE = $cseValidation ? ($cseValidation['meets'] ?? false) : true;
    $hasCSERequirement = !empty($position['career_service_eligibilities']);

    $salaryRangeDisplay = $position['salary_range'] ?? '';
    if ($salaryRangeDisplay && !preg_match('/(₱|PHP|PESO)/i', $salaryRangeDisplay)) {
        $salaryRangeDisplay = 'PHP ' . $salaryRangeDisplay;
    }

    $applicantCSE = [];
    if (!empty($user['career_service_eligibilities'])) {
        $applicantCSE = array_map('trim', explode(',', $user['career_service_eligibilities']));
    }
    $requiredCSE = [];
    if (!empty($position['career_service_eligibilities'])) {
        $requiredCSE = array_map('trim', explode(',', $position['career_service_eligibilities']));
    }

    $matchScore = $computeMatchScore($requirementValidation);
    [$matchLabel, $matchDesc, $matchColor] = $getMatchMeta($matchScore);
    $reqChecks = $buildRequirementChecks($requirementValidation);
    $reqPassed = count(array_filter($reqChecks, fn($c) => $c['met']));
    $reqTotal = count($reqChecks);

    $postedLabel = !empty($position['created_at'])
        ? (strtotime($position['created_at']) >= strtotime('today') ? 'Today' : date('M j, Y', strtotime($position['created_at'])))
        : 'Recently';

    $daysRemaining = null;
    if (!empty($position['deadline'])) {
        $daysRemaining = (int) round((strtotime($position['deadline']) - strtotime('today')) / 86400);
    }

    return [
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
        'apply_url' => 'apply.php?position=' . $position['id'],
        'match_score' => $matchScore,
        'match_label' => $matchLabel,
        'match_desc' => $matchDesc,
        'match_color' => $matchColor,
        'requirement_checks' => json_encode($reqChecks),
        'req_passed' => $reqPassed,
        'req_total' => $reqTotal,
        'posted_label' => $postedLabel,
        'days_remaining' => $daysRemaining,
        'est_apply_minutes' => '3',
        '_hasApplied' => $hasApplied,
        '_applicationStatus' => $applicationStatus,
        '_meetsRequirements' => $meetsRequirements,
        '_meetsCSE' => $meetsCSE,
        '_hasCSERequirement' => $hasCSERequirement,
        '_salaryRangeDisplay' => $salaryRangeDisplay,
        '_reqChecks' => $reqChecks,
        '_reqPassed' => $reqPassed,
        '_reqTotal' => $reqTotal,
        '_matchScore' => $matchScore,
        '_matchLabel' => $matchLabel,
        '_matchColor' => $matchColor,
    ];
};

$filterQueryBase = function ($extraPage = null) use ($searchTerm, $employmentType, $minExperience, $page) {
    $q = '?search=' . urlencode($searchTerm) . '&type=' . urlencode($employmentType) . '&experience=' . urlencode($minExperience);
    if ($extraPage !== null) {
        $q .= '&page=' . (int) $extraPage;
    } elseif ($page > 1) {
        $q .= '&page=' . $page;
    }
    return $q;
};
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
    <link href="../../assets/css/applicant-positions.css" rel="stylesheet">
    <?php include '../../includes/pwa-header.php'; ?>
</head>
<body class="jobs-page" data-theme="system">
<?php include '../../includes/applicant-theme-init.php'; ?>
    <aside class="sidebar" aria-label="Main navigation">
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
            <a href="dashboard.php" class="sidebar-nav-item">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="positions.php" class="sidebar-nav-item active">
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

    <div class="main-wrapper">
        <div class="topbar">
            <button class="mobile-menu-toggle" aria-label="Open menu">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="topbar-title">Browse Jobs</h1>
            <div class="topbar-actions">
                <?php include '../../includes/applicant-theme-toggle.php'; ?>
                <a href="../../auth/logout.php" class="topbar-btn confirm-logout" title="Logout" aria-label="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <div class="content">
            <!-- Hero -->
            <section class="jobs-hero" aria-labelledby="jobs-hero-title">
                <div class="jobs-hero__main">
                    <div class="jobs-hero__eyebrow"><i class="fas fa-briefcase"></i> Career Portal</div>
                    <h2 class="jobs-hero__title" id="jobs-hero-title">Browse Opportunities</h2>
                    <p class="jobs-hero__greeting"><?= $greeting ?>, <?= htmlspecialchars($firstName) ?> — find roles that match your profile and career goals.</p>
                    <div class="jobs-hero__stats">
                        <div class="jobs-hero-stat">
                            <span class="jobs-hero-stat__value" data-count="<?= (int) $totalPositions_filtered ?>">0</span>
                            <span class="jobs-hero-stat__label">Open Positions</span>
                        </div>
                        <div class="jobs-hero-stat">
                            <span class="jobs-hero-stat__value" data-count="<?= (int) $recommendedCount ?>">0</span>
                            <span class="jobs-hero-stat__label">Recommended for You</span>
                        </div>
                    </div>
                    <form method="GET" action="" id="searchForm" class="jobs-search-wrap" role="search">
                        <div class="jobs-search">
                            <i class="fas fa-search jobs-search__icon" aria-hidden="true"></i>
                            <input type="text" name="search" class="jobs-search__input"
                                   placeholder="Search jobs by title, description, or location..."
                                   value="<?= htmlspecialchars($searchTerm) ?>"
                                   autocomplete="off"
                                   aria-label="Search jobs">
                            <kbd class="jobs-search__kbd" aria-hidden="true">Ctrl K</kbd>
                            <div class="jobs-search-dropdown" role="listbox" aria-label="Search suggestions"></div>
                        </div>
                        <input type="hidden" name="type" value="<?= htmlspecialchars($employmentType) ?>">
                        <input type="hidden" name="experience" value="<?= htmlspecialchars($minExperience) ?>">
                    </form>
                </div>
                <aside class="jobs-hero__aside" aria-hidden="true">
                    <div class="jobs-hero__illus"><i class="fas fa-building-columns"></i></div>
                    <div class="jobs-quick-guide">
                        <strong>Quick Apply Guide</strong>
                        Complete your profile, verify eligibility, then apply in about 3 minutes.
                    </div>
                </aside>
            </section>

            <!-- Mobile sticky search -->
            <div class="jobs-mobile-search-bar">
                <div class="jobs-search-wrap">
                    <div class="jobs-search">
                        <i class="fas fa-search jobs-search__icon"></i>
                        <input type="text" id="mobileSearchInput" class="jobs-search__input"
                               placeholder="Search jobs..."
                               value="<?= htmlspecialchars($searchTerm) ?>"
                               aria-label="Search jobs">
                        <div class="jobs-search-dropdown"></div>
                    </div>
                </div>
            </div>

            <!-- Toolbar -->
            <div class="jobs-toolbar">
                <div class="jobs-toolbar__actions">
                    <button type="button" class="jobs-btn jobs-btn--outline" id="openFilterSheet" aria-expanded="false">
                        <i class="fas fa-sliders-h"></i> Filters
                    </button>
                    <select class="jobs-filter-select" id="sortJobs" aria-label="Sort jobs" style="width:auto;min-width:160px">
                        <option value="newest">Newest</option>
                        <option value="match">Best Match</option>
                        <option value="salary">Highest Salary</option>
                        <option value="deadline">Deadline</option>
                        <option value="applications">Most Applied</option>
                    </select>
                    <button type="button" class="jobs-btn jobs-btn--ghost" id="showSavedBtn">
                        <i class="fas fa-bookmark"></i> Saved (<span id="savedJobsCount">0</span>)
                    </button>
                    <button type="button" class="jobs-btn jobs-btn--ghost" id="resetFiltersBtn">
                        <i class="fas fa-rotate-left"></i> Reset
                    </button>
                </div>
            </div>

            <!-- Compare bar -->
            <div class="jobs-compare-bar" id="compareBar" role="region" aria-label="Job comparison">
                <span><strong id="compareCount">0</strong> jobs selected for comparison</span>
                <button type="button" class="jobs-btn jobs-btn--primary jobs-btn--sm" id="compareJobsBtn">Compare Jobs</button>
                <button type="button" class="jobs-btn jobs-btn--outline jobs-btn--sm" id="clearCompareBtn">Clear</button>
            </div>

            <?php if (!empty($topRecommended) && empty($searchTerm) && empty($employmentType)): ?>
            <section class="jobs-section" aria-labelledby="recommended-title">
                <div class="jobs-section__header">
                    <h3 class="jobs-section__title" id="recommended-title">
                        <i class="fas fa-wand-magic-sparkles"></i> Jobs You Are Most Likely to Get
                    </h3>
                    <div class="jobs-tabs" role="tablist">
                        <button type="button" class="jobs-tab active" role="tab" aria-selected="true">Recommended</button>
                        <button type="button" class="jobs-tab" role="tab" aria-selected="false">Best Match</button>
                    </div>
                </div>
                <div class="jobs-grid">
                    <?php foreach ($topRecommended as $recPos):
                        $pd = $buildPositionData($recPos);
                        [$ribbonText, $ribbonClass] = $getJobRibbon($recPos);
                    ?>
                    <article class="position-item jobs-card jobs-card--recommended"
                             data-job-id="<?= (int) $recPos['id'] ?>"
                             data-position='<?= json_encode(array_diff_key($pd, array_flip(array_filter(array_keys($pd), function ($k) { return strpos($k, '_') === 0; })))) ?>'>
                        <?php if ($ribbonText): ?><div class="jobs-card__ribbon jobs-card__ribbon--<?= $ribbonClass ?>"><?= htmlspecialchars($ribbonText) ?></div><?php endif; ?>
                        <div class="jobs-card__accent"></div>
                        <div class="jobs-card__body">
                            <div class="jobs-card__top">
                                <div class="jobs-card__head">
                                    <div class="jobs-card__title-row">
                                        <h4 class="jobs-card__title"><?= htmlspecialchars($recPos['title']) ?></h4>
                                        <div class="jobs-match-badge jobs-match-badge--<?= $pd['_matchColor'] ?>">
                                            <span class="jobs-match-badge__pct"><?= $pd['_matchScore'] ?>%</span>
                                            <span class="jobs-match-badge__label">Match</span>
                                        </div>
                                    </div>
                                    <?php if (!empty($recPos['location'])): ?>
                                    <div class="jobs-card__dept"><i class="fas fa-building"></i> <?= htmlspecialchars($recPos['location']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="jobs-card__tags">
                                <span class="jobs-tag"><i class="fas fa-briefcase"></i> <?= ucfirst($recPos['employment_type']) ?></span>
                                <?php if ($pd['_salaryRangeDisplay']): ?>
                                <span class="jobs-tag"><i class="fas fa-peso-sign"></i> <?= htmlspecialchars($pd['_salaryRangeDisplay']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="jobs-card__footer">
                                <button type="button" class="jobs-btn jobs-btn--outline jobs-btn--sm view-details-btn" data-position-id="<?= (int) $recPos['id'] ?>">
                                    <i class="fas fa-info-circle"></i> Details
                                </button>
                                <?php if ($pd['_meetsRequirements']): ?>
                                <a href="apply.php?position=<?= (int) $recPos['id'] ?>" class="jobs-btn jobs-btn--primary jobs-btn--sm">
                                    <i class="fas fa-paper-plane"></i> Apply
                                </a>
                                <?php endif; ?>
                                <button type="button" class="jobs-btn jobs-btn--icon jobs-btn--outline" data-save-job="<?= (int) $recPos['id'] ?>" aria-label="Save job"><i class="fas fa-bookmark"></i></button>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Saved jobs -->
            <section class="jobs-section jobs-saved-section" id="savedJobsSection" aria-labelledby="saved-title">
                <div class="jobs-section__header">
                    <h3 class="jobs-section__title" id="saved-title"><i class="fas fa-bookmark"></i> Saved Jobs</h3>
                </div>
                <div class="jobs-grid jobs-grid--single" id="savedJobsGrid"></div>
            </section>

            <div class="jobs-layout">
                <!-- Desktop filter sidebar -->
                <aside class="jobs-filter-sidebar" aria-label="Job filters">
                    <h4 class="jobs-filter-sidebar__title"><i class="fas fa-filter"></i> Filters</h4>

                    <div class="jobs-filter-group">
                        <span class="jobs-filter-group__label">Employment Type</span>
                        <div class="jobs-filter-chips">
                            <a href="positions.php<?= $searchTerm ? '?search=' . urlencode($searchTerm) : '' ?>" class="jobs-filter-chip <?= empty($employmentType) ? 'active' : '' ?>">All</a>
                            <?php foreach (['full-time' => 'Full Time', 'part-time' => 'Part Time', 'contract' => 'Contract', 'internship' => 'Internship'] as $slug => $label): ?>
                            <a href="?type=<?= $slug ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?><?= !empty($minExperience) ? '&experience=' . urlencode($minExperience) : '' ?>"
                               class="jobs-filter-chip <?= $employmentType === $slug ? 'active' : '' ?>"><?= $label ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="jobs-filter-group">
                        <label class="jobs-filter-group__label" for="filterExperience">Experience (your years ≥ required)</label>
                        <select id="filterExperience" class="jobs-filter-select">
                            <option value="">Any experience level</option>
                            <?php for ($y = 0; $y <= 10; $y++): ?>
                            <option value="<?= $y ?>" <?= (string) $minExperience === (string) $y ? 'selected' : '' ?>><?= $y ?>+ years available</option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <?php if (!empty($uniqueLocations)): ?>
                    <div class="jobs-filter-group">
                        <span class="jobs-filter-group__label">Location</span>
                        <div class="jobs-filter-chips">
                            <?php foreach (array_keys($uniqueLocations) as $loc): ?>
                            <a href="?search=<?= urlencode($loc) ?><?= !empty($employmentType) ? '&type=' . urlencode($employmentType) : '' ?>"
                               class="jobs-filter-chip <?= $searchTerm === $loc ? 'active' : '' ?>"><?= htmlspecialchars($loc) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="jobs-filter-group">
                        <label class="jobs-filter-group__label" for="sortJobsSidebar">Sort By</label>
                        <select id="sortJobsSidebar" class="jobs-filter-select">
                            <option value="newest">Newest</option>
                            <option value="match">Best Match</option>
                            <option value="salary">Highest Salary</option>
                            <option value="deadline">Deadline</option>
                            <option value="applications">Most Applied</option>
                        </select>
                    </div>

                    <a href="positions.php" class="jobs-btn jobs-btn--outline" style="width:100%;margin-top:8px">
                        <i class="fas fa-rotate-left"></i> Reset Filters
                    </a>
                </aside>

                <!-- Main listings -->
                <div class="jobs-main">
                    <div class="jobs-section__header">
                        <h3 class="jobs-section__title"><i class="fas fa-briefcase"></i> All Job Openings</h3>
                        <span style="font-size:0.85rem;color:var(--gray-500)"><?= (int) $totalPositions_filtered ?> position<?= $totalPositions_filtered !== 1 ? 's' : '' ?></span>
                    </div>

                    <?php if (empty($positions)): ?>
                    <div class="jobs-empty" role="status">
                        <div class="jobs-empty__illus"><i class="fas fa-search"></i></div>
                        <h3>No Positions Found</h3>
                        <p>No positions match your search criteria. Try adjusting your filters or browse all open roles.</p>
                        <div class="jobs-empty__actions">
                            <a href="positions.php" class="jobs-btn jobs-btn--primary"><i class="fas fa-rotate-left"></i> Reset Filters</a>
                            <a href="positions.php" class="jobs-btn jobs-btn--outline"><i class="fas fa-briefcase"></i> Browse All</a>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="jobs-grid" id="jobsGrid">
                        <?php foreach ($positions as $position):
                            $pd = $buildPositionData($position);
                            [$ribbonText, $ribbonClass] = $getJobRibbon($position);
                            $lockReason = "You don't meet the requirements";
                            if ($pd['_hasCSERequirement'] && !$pd['_meetsCSE']) {
                                $lockReason = "You don't meet the required Career Service Eligibility";
                            } elseif (!$pd['_meetsRequirements']) {
                                $lockReason = "You don't meet the minimum experience requirement";
                            }
                            $reqPct = $pd['_reqTotal'] > 0 ? round(($pd['_reqPassed'] / $pd['_reqTotal']) * 100) : 100;
                            $jsonData = array_diff_key($pd, array_flip(array_filter(array_keys($pd), function ($k) { return strpos($k, '_') === 0; })));
                        ?>
                        <article class="position-item jobs-card"
                                 data-job-id="<?= (int) $position['id'] ?>"
                                 data-position='<?= json_encode($jsonData) ?>'
                                 style="animation-delay:<?= ($position['id'] % 6) * 0.05 ?>s">
                            <?php if ($ribbonText): ?>
                            <div class="jobs-card__ribbon jobs-card__ribbon--<?= $ribbonClass ?>"><?= htmlspecialchars($ribbonText) ?></div>
                            <?php endif; ?>
                            <div class="jobs-card__accent"></div>
                            <div class="jobs-card__body">
                                <div class="jobs-card__top">
                                    <input type="checkbox" class="jobs-card__compare-check" data-compare-id="<?= (int) $position['id'] ?>" aria-label="Compare <?= htmlspecialchars($position['title']) ?>">
                                    <div class="jobs-card__head">
                                        <div class="jobs-card__title-row">
                                            <h4 class="jobs-card__title"><?= htmlspecialchars($position['title']) ?></h4>
                                            <div class="jobs-match-badge jobs-match-badge--<?= $pd['_matchColor'] ?>" title="<?= htmlspecialchars($pd['_matchLabel']) ?>">
                                                <span class="jobs-match-badge__pct"><?= $pd['_matchScore'] ?>%</span>
                                                <span class="jobs-match-badge__label">Match</span>
                                            </div>
                                        </div>
                                        <?php if ($position['location']): ?>
                                        <div class="jobs-card__dept"><i class="fas fa-building"></i> <?= htmlspecialchars($position['location']) ?></div>
                                        <?php endif; ?>
                                        <div class="jobs-card__tags">
                                            <?php if ($pd['_hasApplied']): ?>
                                            <span class="jobs-tag jobs-tag--applied"><i class="fas fa-check"></i> Applied (<?= ucfirst($pd['_applicationStatus']) ?>)</span>
                                            <?php elseif (!$pd['_meetsRequirements']): ?>
                                            <span class="jobs-tag jobs-tag--locked" title="<?= htmlspecialchars($lockReason) ?>"><i class="fas fa-lock"></i> Locked</span>
                                            <?php endif; ?>
                                            <span class="jobs-tag"><i class="fas fa-briefcase"></i> <?= ucfirst($position['employment_type']) ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="jobs-card__details">
                                    <?php if ($position['location']): ?>
                                    <div class="jobs-card__detail"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($position['location']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($pd['_salaryRangeDisplay']): ?>
                                    <div class="jobs-card__detail"><i class="fas fa-money-bill-wave"></i> <?= htmlspecialchars($pd['_salaryRangeDisplay']) ?></div>
                                    <?php endif; ?>
                                    <div class="jobs-card__detail"><i class="fas fa-clock"></i> <?= $position['min_experience_years'] ?>y experience</div>
                                    <?php if ($position['training_hours']): ?>
                                    <div class="jobs-card__detail"><i class="fas fa-graduation-cap"></i> <?= (int) $position['training_hours'] ?>h training</div>
                                    <?php endif; ?>
                                    <?php if ($position['deadline']): ?>
                                    <div class="jobs-card__detail"><i class="fas fa-calendar"></i> Deadline: <?= date('M j', strtotime($position['deadline'])) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($position['career_service_eligibilities'])): ?>
                                    <div class="jobs-card__detail"><i class="fas fa-certificate"></i> CSE Required</div>
                                    <?php endif; ?>
                                    <?php if ($position['salary_grade']): ?>
                                    <div class="jobs-card__detail"><i class="fas fa-layer-group"></i> <?= htmlspecialchars($position['salary_grade']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="jobs-req-checklist">
                                    <div class="jobs-req-checklist__title">Requirements</div>
                                    <div class="jobs-req-checklist__progress"><div class="jobs-req-checklist__fill" style="width:<?= $reqPct ?>%"></div></div>
                                    <?php foreach ($pd['_reqChecks'] as $check): ?>
                                    <div class="jobs-req-item">
                                        <span><?= htmlspecialchars($check['label']) ?></span>
                                        <span class="jobs-req-item__status <?= $check['met'] ? 'jobs-req-item__status--ok' : 'jobs-req-item__status--miss' ?>">
                                            <?= $check['met'] ? 'Complete' : 'Missing' ?>
                                        </span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="jobs-card__insights">
                                    <span><i class="fas fa-users"></i> <?= (int) $position['application_count'] ?> applications</span>
                                    <?php if ($pd['days_remaining'] !== null && $pd['days_remaining'] >= 0): ?>
                                    <span><i class="fas fa-hourglass-half"></i> <?= $pd['days_remaining'] ?> days left</span>
                                    <?php endif; ?>
                                    <span><i class="fas fa-chart-line"></i> <?= $pd['_matchLabel'] ?></span>
                                </div>

                                <p class="jobs-card__excerpt"><?= nl2br(htmlspecialchars(substr($position['description'], 0, 120))) ?><?= strlen($position['description']) > 120 ? '...' : '' ?></p>

                                <div class="jobs-hover-preview" aria-hidden="true">
                                    <strong><?= htmlspecialchars($position['title']) ?></strong>
                                    <p style="margin:8px 0;font-size:0.82rem;color:var(--gray-600)"><?= htmlspecialchars($pd['_salaryRangeDisplay'] ?: 'Salary TBD') ?> · Deadline: <?= $position['deadline'] ? date('M j', strtotime($position['deadline'])) : 'Open' ?></p>
                                </div>

                                <div class="jobs-card__footer">
                                    <span class="jobs-card__apply-time"><i class="fas fa-stopwatch"></i> ~3 min application</span>
                                    <button type="button" class="jobs-btn jobs-btn--outline jobs-btn--sm view-details-btn" data-position-id="<?= (int) $position['id'] ?>">
                                        <i class="fas fa-info-circle"></i> Details
                                    </button>
                                    <button type="button" class="jobs-btn jobs-btn--icon jobs-btn--outline" data-save-job="<?= (int) $position['id'] ?>" aria-label="Save job" title="Save job">
                                        <i class="fas fa-bookmark"></i>
                                    </button>
                                    <?php if (!$pd['_hasApplied'] && $pd['_meetsRequirements']): ?>
                                    <a href="apply.php?position=<?= (int) $position['id'] ?>" class="jobs-btn jobs-btn--primary jobs-btn--sm">
                                        <i class="fas fa-paper-plane"></i> Apply Now
                                    </a>
                                    <?php elseif ($pd['_hasApplied']): ?>
                                    <span class="jobs-btn jobs-btn--outline jobs-btn--sm" style="cursor:default;opacity:0.75" aria-disabled="true">
                                        <i class="fas fa-check"></i> Applied
                                    </span>
                                    <?php else: ?>
                                    <span class="jobs-btn jobs-btn--outline jobs-btn--sm" style="cursor:not-allowed;opacity:0.7" title="<?= htmlspecialchars($lockReason) ?>" aria-disabled="true">
                                        <i class="fas fa-lock"></i> Locked
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                    <nav class="jobs-pagination" aria-label="Job listings pagination">
                        <?php if ($page > 1): ?>
                        <a href="<?= $filterQueryBase($page - 1) ?>" class="jobs-pagination__btn">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                        <?php else: ?>
                        <span class="jobs-pagination__btn disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                        <?php endif; ?>

                        <div class="jobs-pagination__pages">
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                            <a href="<?= $filterQueryBase($i) ?>" class="jobs-pagination__page <?= $i === $page ? 'active' : '' ?>" <?= $i === $page ? 'aria-current="page"' : '' ?>><?= $i ?></a>
                            <?php endfor; ?>
                        </div>

                        <span class="jobs-pagination__info"><?= (int) $totalPositions_filtered ?> total positions</span>

                        <?php if ($page < $totalPages): ?>
                        <a href="<?= $filterQueryBase($page + 1) ?>" class="jobs-pagination__btn">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php else: ?>
                        <span class="jobs-pagination__btn disabled">Next <i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </nav>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile filter bottom sheet -->
    <div class="jobs-filter-sheet-overlay" id="filterSheetOverlay"></div>
    <div class="jobs-filter-sheet" id="filterSheet" role="dialog" aria-label="Filters">
        <div class="jobs-filter-sheet__header">
            <h4 style="margin:0;font-weight:700">Filters</h4>
            <button type="button" class="jobs-btn jobs-btn--icon jobs-btn--outline" id="closeFilterSheet" aria-label="Close filters"><i class="fas fa-times"></i></button>
        </div>
        <div class="jobs-filter-sheet__body">
            <div class="jobs-filter-group">
                <span class="jobs-filter-group__label">Employment Type</span>
                <div class="jobs-filter-chips">
                    <a href="positions.php" class="jobs-filter-chip <?= empty($employmentType) ? 'active' : '' ?>">All</a>
                    <?php foreach (['full-time' => 'Full Time', 'part-time' => 'Part Time', 'contract' => 'Contract', 'internship' => 'Internship'] as $slug => $label): ?>
                    <a href="?type=<?= $slug ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>"
                       class="jobs-filter-chip <?= $employmentType === $slug ? 'active' : '' ?>"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="jobs-filter-group">
                <label class="jobs-filter-group__label" for="filterExperienceMobile">Experience</label>
                <select id="filterExperienceMobile" class="jobs-filter-select">
                    <option value="">Any</option>
                    <?php for ($y = 0; $y <= 10; $y++): ?>
                    <option value="<?= $y ?>" <?= (string) $minExperience === (string) $y ? 'selected' : '' ?>><?= $y ?>+ years</option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="jobs-filter-sheet__footer">
            <a href="positions.php" class="jobs-btn jobs-btn--outline" style="flex:1">Reset</a>
            <button type="button" class="jobs-btn jobs-btn--primary" style="flex:1" id="applyMobileFilters">Apply Filters</button>
        </div>
    </div>

    <!-- Compare modal -->
    <div class="jobs-compare-modal" id="compareModal" role="dialog" aria-label="Compare jobs">
        <div class="jobs-compare-panel">
            <div class="jobs-compare-panel__header">
                <h3 style="margin:0">Compare Jobs</h3>
                <button type="button" class="jobs-btn jobs-btn--icon jobs-btn--outline" id="closeCompareModal" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="jobs-compare-panel__body" id="compareTableBody"></div>
        </div>
    </div>

    <nav class="mobile-nav" aria-label="Mobile navigation">
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

    <!-- Job Details Side Panel Modal -->
    <div class="modal-overlay" id="positionModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-container">
            <div class="modal-header">
                <div class="modal-header-content">
                    <h2 id="modalTitle"></h2>
                    <div class="modal-header-meta" id="modalHeaderMeta"></div>
                </div>
                <button class="modal-close" id="modalClose" aria-label="Close job details">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div>
                    <div class="modal-main-col">
                        <div class="modal-section">
                            <h3><i class="fas fa-info-circle"></i> Overview</h3>
                            <div id="modalSalaryCard" class="jobs-salary-card"></div>
                            <div style="margin-bottom:16px">
                                <strong style="display:block;margin-bottom:6px;color:var(--gray-800)">Position Title</strong>
                                <div id="modalTitleValue" style="font-size:1.05rem"></div>
                            </div>
                            <div style="margin-bottom:16px">
                                <strong style="display:block;margin-bottom:6px;color:var(--gray-800)">Employment Type</strong>
                                <div id="modalEmploymentType"></div>
                            </div>
                            <div style="margin-bottom:16px">
                                <strong style="display:block;margin-bottom:6px;color:var(--gray-800)">Job Description</strong>
                                <div id="modalDescription" style="line-height:1.65;white-space:pre-wrap;color:var(--gray-700)"></div>
                            </div>
                        </div>

                        <div class="modal-section" id="modalRequirementsSection" style="display:none">
                            <h3><i class="fas fa-list-check"></i> Requirements</h3>
                            <ul class="modal-requirements-list" id="modalRequirements"></ul>
                        </div>

                        <div class="modal-section">
                            <h3><i class="fas fa-map-marker-alt"></i> Location & Details</h3>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:0.9rem">
                                <div><strong>Min. Experience</strong><div id="modalMinExperience"></div></div>
                                <div><strong>Training Hours</strong><div id="modalTrainingHours"></div></div>
                                <div><strong>Location</strong><div id="modalLocation"></div></div>
                                <div><strong>Deadline</strong><div id="modalDeadline"></div></div>
                                <div id="modalSalaryRangeSection"><strong>Salary</strong><div id="modalSalaryRange"></div></div>
                                <div id="modalSalaryGradeSection"><strong>Salary Grade</strong><div id="modalSalaryGrade"></div></div>
                                <div id="modalMaxApplicationsSection"><strong>Max Applications</strong><div id="modalMaxApplications"></div></div>
                            </div>
                        </div>

                        <div class="modal-section" id="modalCSESection">
                            <h3><i class="fas fa-certificate"></i> Career Service Eligibility</h3>
                            <div id="modalCSE"></div>
                        </div>

                        <div class="modal-section">
                            <h3><i class="fas fa-route"></i> Hiring Process Timeline</h3>
                            <div id="modalTimeline"></div>
                        </div>
                    </div>

                    <div class="modal-sidebar-col">
                        <div id="modalMatchPanel" class="jobs-match-panel"></div>

                        <div class="modal-section">
                            <h3><i class="fas fa-user-check"></i> Your Match</h3>
                            <div class="modal-status-card">
                                <div class="modal-status-value" id="modalUserExperience"></div>
                                <div class="modal-status-label">Your Experience (Years)</div>
                            </div>
                            <div class="modal-status-card">
                                <div class="modal-status-value" id="modalRequiredExperience"></div>
                                <div class="modal-status-label">Required Experience (Years)</div>
                            </div>
                            <div id="modalReqChecklist" class="modal-req-checklist"></div>
                            <div id="modalRequirementStatus"></div>
                        </div>

                        <div class="modal-section" id="modalApplicationsSection" style="display:none">
                            <h3><i class="fas fa-users"></i> Application Capacity</h3>
                            <div id="modalCapacityRing" class="jobs-capacity"></div>
                            <div style="background:var(--gray-200);height:6px;border-radius:999px;overflow:hidden;margin-bottom:8px;display:none">
                                <div id="modalApplicationProgress" style="height:100%;background:var(--info);width:0"></div>
                            </div>
                            <p style="text-align:center;color:var(--gray-600);font-size:0.85rem;margin:0" id="modalApplicationCount"></p>
                        </div>

                        <div class="modal-sticky-apply">
                            <div class="modal-actions" id="modalActions"></div>
                            <p class="modal-apply-time" id="modalApplyTime"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="jobsSearchData"><?= json_encode([
        'titles' => array_values(array_unique($searchTitles)),
        'locations' => array_keys($uniqueLocations),
        'keywords' => ['Administrative Assistant', 'Administrative Officer', 'Accounting', 'Instructor', 'Full Time']
    ]) ?></script>

    <script src="../../assets/js/toast.js"></script>
    <script src="../../assets/js/office-theme.js"></script>
    <script src="../../assets/js/applicant-theme.js"></script>
    <script src="../../assets/js/applicant-positions.js"></script>
    <?php include '../../includes/pwa-scripts.php'; ?>
</body>
</html>
