<?php
// ATS view partial — included by applications.php only
if (!isset($applicationModel)) {
    exit;
}

$pipelineStages = [
    ['key' => 'pending', 'label' => 'Pending', 'color' => '#F59E0B', 'href' => 'applications.php?status=' . STATUS_PENDING],
    ['key' => 'review', 'label' => 'Doc Review', 'color' => '#0056D2', 'href' => 'applications.php?status=' . STATUS_PENDING],
    ['key' => 'validation', 'label' => 'Requirements', 'color' => '#0F4C81', 'href' => 'applications.php?status=' . STATUS_PENDING],
    ['key' => 'interview', 'label' => 'Interview', 'color' => '#6366f1', 'href' => 'applications.php?status=' . STATUS_APPROVED],
    ['key' => 'approved', 'label' => 'Approved', 'color' => '#16A34A', 'href' => 'applications.php?status=' . STATUS_APPROVED],
    ['key' => 'rejected', 'label' => 'Rejected', 'color' => '#DC2626', 'href' => 'applications.php?status=' . STATUS_REJECTED],
    ['key' => 'archived', 'label' => 'Archived', 'color' => '#64748b', 'href' => 'application_history.php'],
];

?>
    <?php $adminSidebarActive = 'applications'; require __DIR__ . '/_admin_sidebar.php'; ?>

    <div class="main-wrapper">
        <div class="topbar">
            <button class="mobile-menu-toggle" aria-label="Open menu"><i class="fas fa-bars"></i></button>
            <h1 class="topbar-title">Recruitment ATS</h1>
            <div class="topbar-actions" style="position:relative;">
                <button type="button" class="topbar-theme-btn" id="themeToggle" aria-label="Toggle dark mode">
                    <i class="fas fa-moon" id="themeToggleIcon"></i>
                </button>
                <a href="notifications.php" id="notificationBell" class="notification-bell" title="Notifications" aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    <span id="notificationBadge" class="notification-badge" style="display:none;">0</span>
                </a>
                <div id="notificationDropdown" class="notification-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <button id="markAllReadBtn" class="mark-all-read">Mark all as read</button>
                    </div>
                    <div id="notificationList" class="notification-list"><div class="notification-empty">Loading...</div></div>
                    <div class="notification-footer"><a href="notifications.php">View all notifications</a></div>
                </div>
                <a href="settings.php" class="topbar-btn" aria-label="Settings"><i class="fas fa-cog"></i></a>
                <a href="../../auth/logout.php" class="topbar-btn confirm-logout" title="Logout" aria-label="Logout"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>

        <div class="content">
            <?php if (isset($_SESSION['success_message'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    <?php if (isset($_SESSION['email_sent']) && $_SESSION['email_sent']): ?>
                        showSuccess('<?= addslashes($_SESSION['success_message']) ?>', 5000, 'Status Updated');
                    <?php else: ?>
                        showWarning('<?= addslashes($_SESSION['success_message']) ?>', 5000, 'Status Updated');
                    <?php endif; ?>
                });
            </script>
            <?php unset($_SESSION['success_message']); unset($_SESSION['email_sent']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
            <script>document.addEventListener('DOMContentLoaded',function(){showError('<?= addslashes($_SESSION['error_message']) ?>',5000,'Error');});</script>
            <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- Hero -->
            <section class="ats-hero" aria-labelledby="atsHeroTitle">
                <div class="ats-hero__content">
                    <div class="ats-hero__eyebrow"><i class="fas fa-users-between-lines"></i> Applicant Tracking System</div>
                    <h2 class="ats-hero__title" id="atsHeroTitle">Applications Management</h2>
                    <p class="ats-hero__subtitle">Review, evaluate, communicate with applicants, and manage the recruitment lifecycle efficiently.</p>
                </div>
                <div class="ats-hero__actions">
                    <button type="button" class="ats-btn ats-btn-ghost" id="atsBulkExportHero" onclick="document.getElementById('atsBulkExport')?.click()"><i class="fas fa-download"></i> Export</button>
                    <a href="application_history.php" class="ats-btn ats-btn-ghost"><i class="fas fa-calendar-check"></i> Interview Schedule</a>
                    <button type="button" class="ats-btn ats-btn-ghost" data-open-search aria-label="Search"><i class="fas fa-layer-group"></i> Bulk Actions</button>
                    <a href="reports.php" class="ats-btn ats-btn-ghost"><i class="fas fa-chart-pie"></i> Reports</a>
                    <button type="button" class="ats-btn ats-btn-ghost" id="atsRefreshBtn"><i class="fas fa-sync-alt"></i> Refresh</button>
                </div>
            </section>

            <!-- Pipeline -->
            <section class="ats-pipeline" aria-labelledby="pipelineTitle">
                <div class="ats-pipeline__header">
                    <h3 class="ats-pipeline__title" id="pipelineTitle"><i class="fas fa-stream"></i> Recruitment Pipeline</h3>
                    <span class="ats-results-badge"><?= count($allApplicationsRaw) ?> in pipeline</span>
                </div>
                <div class="ats-pipeline__stages" role="list">
                    <?php foreach ($pipelineStages as $stage):
                        $count = $pipelineCounts[$stage['key']] ?? 0;
                        $pct = round(($count / $pipelineTotal) * 100, 1);
                    ?>
                    <a href="<?= htmlspecialchars($stage['href']) ?>" class="ats-pipeline__stage" style="--stage-color: <?= $stage['color'] ?>" role="listitem" title="Filter by <?= htmlspecialchars($stage['label']) ?>">
                        <div class="ats-pipeline__stage-count"><?= $count ?></div>
                        <div class="ats-pipeline__stage-label"><?= htmlspecialchars($stage['label']) ?></div>
                        <div class="ats-pipeline__stage-pct"><?= $pct ?>%</div>
                        <div class="ats-pipeline__stage-bar"><div class="ats-pipeline__stage-bar-fill" style="width:<?= $pct ?>%"></div></div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Smart Toolbar -->
            <section class="ats-toolbar" aria-label="Search and filters">
                <form method="GET" action="" id="atsFilterForm">
                    <div class="ats-toolbar__row">
                        <label class="ats-search-wrap" for="searchQuery">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input id="searchQuery" type="search" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search name, email, position, keywords…" autocomplete="off" aria-label="Search applications">
                            <span class="ats-search-kbd" aria-hidden="true">Ctrl K</span>
                        </label>
                        <select name="status" class="ats-select" aria-label="Filter by status" onchange="this.form.submit()">
                            <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="<?= STATUS_PENDING ?>" <?= $filterStatus === STATUS_PENDING ? 'selected' : '' ?>>Pending</option>
                            <option value="<?= STATUS_APPROVED ?>" <?= $filterStatus === STATUS_APPROVED ? 'selected' : '' ?>>Approved</option>
                            <option value="<?= STATUS_REJECTED ?>" <?= $filterStatus === STATUS_REJECTED ? 'selected' : '' ?>>Rejected</option>
                        </select>
                        <select name="position" class="ats-select" aria-label="Filter by position" onchange="this.form.submit()">
                            <option value="all" <?= $filterPosition === 'all' ? 'selected' : '' ?>>All Positions</option>
                            <?php foreach ($positions as $position): ?>
                            <option value="<?= $position['id'] ?>" <?= $filterPosition == $position['id'] ? 'selected' : '' ?>><?= htmlspecialchars($position['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="atsClientSort" class="ats-select" aria-label="Sort applications">
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="experience">Highest Experience</option>
                            <option value="qualified">Most Qualified</option>
                            <option value="updated">Recently Updated</option>
                        </select>
                        <button type="submit" class="ats-btn ats-btn-primary"><i class="fas fa-filter"></i> Apply</button>
                        <?php if ($filterStatus !== 'all' || $filterPosition !== 'all' || $searchQuery !== ''): ?>
                        <a href="applications.php" class="ats-btn ats-btn-outline"><i class="fas fa-times"></i> Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <!-- Bulk bar -->
            <div class="ats-bulk-bar" id="atsBulkBar" role="toolbar" aria-label="Bulk actions">
                <span class="ats-bulk-bar__count"><span id="atsBulkCount">0</span> selected</span>
                <label style="display:flex;align-items:center;gap:6px;font-size:0.8125rem;cursor:pointer">
                    <input type="checkbox" id="atsSelectAll"> Select all on page
                </label>
                <button type="button" class="ats-btn ats-btn-sm ats-btn-outline" id="atsBulkCompare"><i class="fas fa-columns"></i> Compare</button>
                <button type="button" class="ats-btn ats-btn-sm ats-btn-outline" id="atsBulkEmail"><i class="fas fa-envelope"></i> Email</button>
                <button type="button" class="ats-btn ats-btn-sm ats-btn-outline" id="atsBulkExport"><i class="fas fa-download"></i> Export</button>
                <button type="button" class="ats-btn ats-btn-sm ats-btn-outline" id="atsBulkClear"><i class="fas fa-times"></i> Clear</button>
            </div>

            <!-- Applicant cards -->
            <section aria-labelledby="applicantsTitle">
                <div class="ats-section-header">
                    <h3 class="ats-section-header__title" id="applicantsTitle"><i class="fas fa-id-badge"></i> Applicant Cards</h3>
                    <span class="ats-results-badge"><?= $totalApplications_filtered ?> result<?= $totalApplications_filtered === 1 ? '' : 's' ?> · <?= count($applications) ?> showing</span>
                </div>

                <?php if (empty($applications)): ?>
                <div class="ats-empty">
                    <div class="ats-empty__icon"><i class="fas fa-inbox"></i></div>
                    <h3 class="ats-empty__title">No applications found</h3>
                    <p class="ats-empty__text">Try changing your filters or search terms to find applicants.</p>
                    <a href="applications.php" class="ats-btn ats-btn-primary"><i class="fas fa-refresh"></i> Clear Filters</a>
                </div>
                <?php else: ?>
                <div class="ats-cards-grid" id="atsCardsGrid">
                    <?php foreach ($applications as $app):
                        $documents = $documentModel->getApplicationDocuments($app['id']);
                        $documentEntries = [];
                        $requirementValidator = new RequirementValidator();
                        $requirementValidation = $requirementValidator->validateRequirements($app['user_id'], $app['position_id']);
                        $matchScore = atsComputeMatchScore($requirementValidation);
                        $matchClass = $matchScore >= 80 ? 'high' : ($matchScore >= 50 ? 'mid' : 'low');
                        $location = $positionLocationMap[$app['position_id']] ?? 'General';
                        $initials = strtoupper(substr($app['applicant_name'], 0, 1));
                        $statusClass = $app['status'] === STATUS_PENDING ? 'pending' : ($app['status'] === STATUS_APPROVED ? 'approved' : 'rejected');

                        if (!empty($app['resume_file'])) {
                            $resumePath = __DIR__ . '/../../applicant/pages/uploads/resumes/' . $app['resume_file'];
                            $resumeExt = strtolower(pathinfo($app['resume_file'], PATHINFO_EXTENSION));
                            $resumeSize = file_exists($resumePath) ? formatFileSize(filesize($resumePath)) : null;
                            $documentEntries[] = [
                                'label' => 'Primary Resume', 'filename' => basename($app['resume_file']), 'type' => 'resume',
                                'extension' => $resumeExt, 'view_url' => 'view_resume.php?id=' . $app['id'],
                                'download_url' => 'view_resume.php?id=' . $app['id'] . '&download=1', 'size' => $resumeSize
                            ];
                        }
                        foreach ($documents as $doc) {
                            $docExt = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                            $docSize = !empty($doc['file_size']) ? formatFileSize((int) $doc['file_size']) : null;
                            if (!$docSize) {
                                $docPath = __DIR__ . '/../../applicant/pages/uploads/documents/' . $doc['file_path'];
                                if (file_exists($docPath)) $docSize = formatFileSize(filesize($docPath));
                            }
                            $documentEntries[] = [
                                'label' => ucfirst($doc['document_type']), 'filename' => $doc['file_name'], 'type' => $doc['document_type'],
                                'extension' => $docExt, 'view_url' => 'view_document.php?id=' . $doc['id'],
                                'download_url' => 'view_document.php?id=' . $doc['id'] . '&download=1', 'size' => $docSize
                            ];
                        }
                        $totalDocuments = count($documentEntries);
                        $hasDocuments = $totalDocuments > 0;
                        $updatedTs = !empty($app['reviewed_at']) ? strtotime($app['reviewed_at']) : strtotime($app['applied_at']);
                        $hasInterview = !empty($app['interview_date']);
                        $appJson = htmlspecialchars(json_encode([
                            'applicant_name' => $app['applicant_name'],
                            'position_title' => $app['position_title'],
                            'cover_letter' => $app['cover_letter'],
                            'applied_at' => $app['applied_at'],
                            'status' => $app['status'],
                            'interview_date' => $app['interview_date'] ?? null,
                            'interview_time' => $app['interview_time'] ?? null,
                        ], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
                    ?>
                    <article class="ats-applicant-card application-item"
                        data-app-id="<?= $app['id'] ?>"
                        data-application-id="<?= $app['id'] ?>"
                        data-status="<?= htmlspecialchars($app['status']) ?>"
                        data-name="<?= htmlspecialchars($app['applicant_name']) ?>"
                        data-email="<?= htmlspecialchars($app['applicant_email']) ?>"
                        data-position="<?= htmlspecialchars($app['position_title']) ?>"
                        data-experience="<?= (float)($app['total_experience_years'] ?? 0) ?>"
                        data-match-score="<?= $matchScore ?>"
                        data-applied="<?= date('M j, Y', strtotime($app['applied_at'])) ?>"
                        data-applied-ts="<?= strtotime($app['applied_at']) ?>"
                        data-updated-ts="<?= $updatedTs ?>"
                        data-documents="<?= $totalDocuments ?>"
                        data-location="<?= htmlspecialchars($location) ?>">
                        <div class="ats-applicant-card__top">
                            <input type="checkbox" class="ats-applicant-card__checkbox" aria-label="Select <?= htmlspecialchars($app['applicant_name']) ?>" data-email="<?= htmlspecialchars($app['applicant_email']) ?>">
                            <div class="ats-avatar" aria-hidden="true"><?= $initials ?></div>
                            <div class="ats-applicant-card__info">
                                <h4 class="ats-applicant-card__name application-name"><?= htmlspecialchars($app['applicant_name']) ?></h4>
                                <p class="ats-applicant-card__position"><?= htmlspecialchars($app['position_title']) ?></p>
                                <div class="ats-applicant-card__tags application-tags">
                                    <span class="ats-tag"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($location) ?></span>
                                    <span class="ats-tag"><i class="fas fa-briefcase"></i> <?= $app['total_experience_years'] ?>y exp</span>
                                    <span class="ats-tag"><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($app['applied_at'])) ?></span>
                                    <span class="ats-tag"><i class="fas fa-paperclip"></i> <?= $totalDocuments ?> docs</span>
                                </div>
                            </div>
                            <div class="ats-match-score <?= $matchClass ?>">
                                <div class="ats-match-ring">
                                    <svg viewBox="0 0 56 56" aria-hidden="true">
                                        <circle cx="28" cy="28" r="24" fill="none" stroke="var(--ats-border)" stroke-width="4"/>
                                        <circle cx="28" cy="28" r="24" fill="none" stroke="<?= $matchScore >= 80 ? '#16A34A' : ($matchScore >= 50 ? '#F59E0B' : '#DC2626') ?>" stroke-width="4" stroke-dasharray="<?= 150.8 * $matchScore / 100 ?> 150.8" stroke-linecap="round"/>
                                    </svg>
                                    <span class="ats-match-ring__value"><?= $matchScore ?>%</span>
                                </div>
                                <div class="ats-match-ring__label">Match</div>
                            </div>
                        </div>

                        <div class="ats-applicant-card__body">
                            <span class="ats-badge ats-badge--<?= $statusClass ?>"><i class="fas fa-circle" style="font-size:0.5rem"></i> <?= ucfirst($app['status']) ?></span>

                            <!-- Timeline -->
                            <div class="ats-timeline-mini" aria-label="Application timeline">
                                <div class="ats-timeline-step done"><div class="ats-timeline-step__dot"></div><span class="ats-timeline-step__label">Applied</span></div>
                                <div class="ats-timeline-connector <?= $hasDocuments ? 'done' : '' ?>"></div>
                                <div class="ats-timeline-step <?= $hasDocuments ? 'done' : '' ?>"><div class="ats-timeline-step__dot"></div><span class="ats-timeline-step__label">Docs</span></div>
                                <div class="ats-timeline-connector <?= $matchScore >= 50 ? 'done' : '' ?>"></div>
                                <div class="ats-timeline-step <?= $matchScore >= 50 ? 'done' : ($matchScore > 0 ? 'active' : '') ?>"><div class="ats-timeline-step__dot"></div><span class="ats-timeline-step__label">Reqs</span></div>
                                <div class="ats-timeline-connector <?= $hasInterview ? 'done' : '' ?>"></div>
                                <div class="ats-timeline-step <?= $hasInterview ? 'done' : '' ?>"><div class="ats-timeline-step__dot"></div><span class="ats-timeline-step__label">Interview</span></div>
                                <div class="ats-timeline-connector <?= $app['status'] !== STATUS_PENDING ? 'done' : '' ?>"></div>
                                <div class="ats-timeline-step <?= $app['status'] !== STATUS_PENDING ? 'done' : '' ?>"><div class="ats-timeline-step__dot"></div><span class="ats-timeline-step__label">Decision</span></div>
                            </div>

                            <p class="ats-applicant-card__summary application-summary"><?= htmlspecialchars(substr($app['cover_letter'], 0, 160)) ?><?= strlen($app['cover_letter']) > 160 ? '…' : '' ?></p>

                            <?php if ($hasInterview): ?>
                            <div class="ats-interview-block">
                                <div class="ats-interview-block__row"><i class="fas fa-calendar"></i> <strong>Interview:</strong> <?= date('M j, Y', strtotime($app['interview_date'])) ?><?= !empty($app['interview_time']) ? ' at ' . date('g:i A', strtotime($app['interview_time'])) : '' ?></div>
                                <div class="ats-interview-block__row"><a href="mailto:<?= htmlspecialchars($app['applicant_email']) ?>" class="ats-btn ats-btn-sm ats-btn-outline"><i class="fas fa-envelope"></i> Send Reminder</a></div>
                            </div>
                            <?php endif; ?>

                            <!-- Status workflow -->
                            <div class="status-control ats-status-panel">
                                <div class="ats-status-panel__label"><i class="fas fa-sliders-h"></i> Update Status</div>
                                <form method="POST" action="applications.php" class="status-inline-form" id="status-form-<?= $app['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <?php FormTokenManager::renderFormTokenInput('update_application_status_' . (int) $app['id']); ?>
                                    <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="status" id="status-input-<?= $app['id'] ?>" value="<?= htmlspecialchars($app['status']) ?>">
                                    <div class="ats-status-workflow" role="group" aria-label="Update application status">
                                        <button type="button" class="ats-status-btn <?= $app['status'] === STATUS_PENDING ? 'active' : '' ?>" data-status="<?= STATUS_PENDING ?>" style="--status-color:#F59E0B" aria-pressed="<?= $app['status'] === STATUS_PENDING ? 'true' : 'false' ?>">
                                            <i class="fas fa-clock"></i> Pending
                                        </button>
                                        <button type="button" class="ats-status-btn <?= $app['status'] === STATUS_APPROVED ? 'active' : '' ?>" data-status="<?= STATUS_APPROVED ?>" style="--status-color:#16A34A" aria-pressed="<?= $app['status'] === STATUS_APPROVED ? 'true' : 'false' ?>">
                                            <i class="fas fa-check"></i> Approved
                                        </button>
                                        <button type="button" class="ats-status-btn <?= $app['status'] === STATUS_REJECTED ? 'active' : '' ?>" data-status="<?= STATUS_REJECTED ?>" style="--status-color:#DC2626" aria-pressed="<?= $app['status'] === STATUS_REJECTED ? 'true' : 'false' ?>">
                                            <i class="fas fa-times"></i> Rejected
                                        </button>
                                    </div>
                                    <select class="status-select status-select-hidden" data-original-status="<?= htmlspecialchars($app['status']) ?>" data-applicant-email="<?= htmlspecialchars($app['applicant_email']) ?>" data-form-id="status-form-<?= $app['id'] ?>" aria-hidden="true" tabindex="-1">
                                        <option value="<?= STATUS_PENDING ?>" <?= $app['status'] === STATUS_PENDING ? 'selected' : '' ?>>Pending</option>
                                        <option value="<?= STATUS_APPROVED ?>" <?= $app['status'] === STATUS_APPROVED ? 'selected' : '' ?>>Approved</option>
                                        <option value="<?= STATUS_REJECTED ?>" <?= $app['status'] === STATUS_REJECTED ? 'selected' : '' ?>>Rejected</option>
                                    </select>
                                </form>
                            </div>

                            <div class="ats-applicant-card__actions quick-actions">
                                <button type="button" class="ats-btn ats-btn-sm ats-btn-outline" onclick='viewCoverLetter(<?= json_encode($app) ?>)'><i class="fas fa-file-lines"></i> Cover Letter</button>
                                <button type="button" class="ats-btn ats-btn-sm ats-btn-primary btn-view-profile" data-user-id="<?= $app['user_id'] ?>" data-app-json="<?= $appJson ?>"><i class="fas fa-user"></i> Profile</button>
                                <?php if (!empty($app['resume_file'])): ?>
                                <a href="view_resume.php?id=<?= $app['id'] ?>" target="_blank" rel="noopener" class="ats-btn ats-btn-sm ats-btn-outline"><i class="fas fa-file-pdf"></i> Resume</a>
                                <?php endif; ?>
                                <button type="button" class="ats-btn ats-btn-sm ats-btn-outline" data-action="toggle-details" data-target="application-details-<?= $app['id'] ?>" aria-expanded="false"><i class="fas fa-folder-open"></i> Documents</button>
                                <a href="mailto:<?= htmlspecialchars($app['applicant_email']) ?>" class="ats-btn ats-btn-sm ats-btn-outline"><i class="fas fa-envelope"></i> Email</a>
                                <form method="POST" action="applications.php" id="archive-form-<?= $app['id'] ?>" style="display:none;">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <?php FormTokenManager::renderFormTokenInput('archive_application_' . (int) $app['id']); ?>
                                    <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                    <input type="hidden" name="archive_application" value="1">
                                </form>
                                <button type="button" class="ats-btn ats-btn-sm ats-btn-outline" style="color:var(--ats-danger);border-color:var(--ats-danger)" onclick="showArchiveConfirmation(<?= $app['id'] ?>, '<?= htmlspecialchars(addslashes($app['applicant_name'])) ?>', '<?= htmlspecialchars(addslashes($app['position_title'])) ?>')"><i class="fas fa-archive"></i> Archive</button>
                            </div>

                            <!-- Expandable details -->
                            <div id="application-details-<?= $app['id'] ?>" class="ats-details-panel application-details-panel">
                                <div class="ats-doc-manager">
                                    <div class="ats-doc-manager__toolbar">
                                        <strong><i class="fas fa-paperclip"></i> Documents (<?= $totalDocuments ?>)</strong>
                                        <div class="ats-doc-view-toggle">
                                            <button type="button" data-doc-view="grid" class="active"><i class="fas fa-th"></i></button>
                                            <button type="button" data-doc-view="list"><i class="fas fa-list"></i></button>
                                        </div>
                                    </div>
                                    <?php if ($hasDocuments): ?>
                                    <div class="ats-doc-grid">
                                        <?php foreach ($documentEntries as $entry):
                                            $ext = $entry['extension'] ?? '';
                                            $icon = $documentExtensionIcons[$ext] ?? ($documentTypeIcons[$entry['type']] ?? 'fa-file');
                                        ?>
                                        <div class="ats-doc-item">
                                            <div class="ats-doc-item__icon"><i class="fas <?= $icon ?>"></i></div>
                                            <div class="ats-doc-item__name"><?= htmlspecialchars($entry['label']) ?></div>
                                            <div class="ats-doc-item__meta"><?= htmlspecialchars($entry['filename']) ?><?= !empty($entry['size']) ? ' · ' . htmlspecialchars($entry['size']) : '' ?></div>
                                            <div class="ats-doc-item__actions">
                                                <a href="<?= htmlspecialchars($entry['view_url']) ?>" target="_blank" rel="noopener" class="ats-btn ats-btn-sm ats-btn-outline"><i class="fas fa-eye"></i></a>
                                                <a href="<?= htmlspecialchars($entry['download_url']) ?>" class="ats-btn ats-btn-sm ats-btn-primary" download="<?= htmlspecialchars($entry['filename']) ?>"><i class="fas fa-download"></i></a>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?>
                                    <p style="color:var(--ats-text-muted);font-size:0.875rem;margin:0">No files uploaded.</p>
                                    <?php endif; ?>
                                </div>

                                <div class="ats-req-checklist" style="margin-top:16px">
                                    <div class="ats-req-checklist__header">
                                        <h5 class="ats-req-checklist__title"><i class="fas fa-clipboard-check"></i> Requirement Validation</h5>
                                        <span class="ats-badge ats-badge--<?= $requirementValidation['meets_requirements'] ? 'approved' : 'pending' ?>"><?= $requirementValidation['meets_requirements'] ? 'Meets All' : 'Partial' ?></span>
                                    </div>
                                    <?php foreach ($requirementValidation['details'] as $key => $detail):
                                        if (!isset($detail['meets'])) continue;
                                        $reqClass = $detail['meets'] ? 'pass' : 'warn';
                                    ?>
                                    <div class="ats-req-item <?= $reqClass ?>">
                                        <i class="fas fa-<?= $detail['meets'] ? 'check-circle' : 'exclamation-triangle' ?> ats-req-item__icon"></i>
                                        <div>
                                            <div class="ats-req-item__title"><?= str_replace('_', ' ', $key) ?></div>
                                            <div class="ats-req-item__msg"><?= htmlspecialchars($detail['message']) ?></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <div class="ats-req-overall">
                                        <div class="ats-match-score <?= $matchClass ?>">
                                            <div class="ats-match-ring" style="width:48px;height:48px">
                                                <svg viewBox="0 0 56 56" style="width:48px;height:48px"><circle cx="28" cy="28" r="24" fill="none" stroke="var(--ats-border)" stroke-width="4"/><circle cx="28" cy="28" r="24" fill="none" stroke="<?= $matchScore >= 80 ? '#16A34A' : ($matchScore >= 50 ? '#F59E0B' : '#DC2626') ?>" stroke-width="4" stroke-dasharray="<?= 150.8 * $matchScore / 100 ?> 150.8" stroke-linecap="round"/></svg>
                                                <span class="ats-match-ring__value" style="font-size:0.7rem"><?= $matchScore ?>%</span>
                                            </div>
                                        </div>
                                        <div><strong>Overall Score</strong><br><span style="color:var(--ats-text-muted);font-size:0.8125rem">Based on requirement validation</span></div>
                                    </div>
                                </div>
                                <div style="display:flex;justify-content:flex-end;margin-top:12px">
                                    <button type="button" class="ats-btn ats-btn-sm ats-btn-outline" data-action="close-details" data-target="application-details-<?= $app['id'] ?>">Close</button>
                                </div>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($totalPages > 1): ?>
                <nav class="ats-pagination" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($filterStatus) ?>&position=<?= urlencode($filterPosition) ?>" class="ats-btn ats-btn-outline"><i class="fas fa-chevron-left"></i> Previous</a>
                    <?php endif; ?>
                    <span class="ats-pagination__info">Page <?= $page ?> of <?= $totalPages ?> (<?= $totalApplications_filtered ?> total)</span>
                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($filterStatus) ?>&position=<?= urlencode($filterPosition) ?>" class="ats-btn ats-btn-outline">Next <i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <nav class="mobile-nav" aria-label="Mobile navigation">
        <div class="mobile-nav-grid">
            <a href="dashboard.php" class="mobile-nav-item"><i class="fas fa-th-large"></i><span>Home</span></a>
            <a href="positions.php" class="mobile-nav-item"><i class="fas fa-briefcase"></i><span>Positions</span></a>
            <a href="applications.php" class="mobile-nav-item active"><i class="fas fa-file-alt"></i><span>Applications</span></a>
            <a href="users.php" class="mobile-nav-item"><i class="fas fa-users"></i><span>Users</span></a>
        </div>
    </nav>

    <!-- Search palette -->
    <div class="ats-search-palette" id="atsSearchPalette" role="dialog" aria-label="Quick search">
        <div class="ats-search-palette__box">
            <div class="ats-search-palette__input-wrap">
                <i class="fas fa-search"></i>
                <input type="search" id="atsSearchPaletteInput" placeholder="Search applicants, positions, emails…" aria-label="Quick search">
                <span class="ats-search-kbd">Esc</span>
            </div>
            <div class="ats-search-palette__recent"><div class="ats-search-palette__recent-title">Recent Searches</div><div id="atsRecentSearches"></div></div>
        </div>
    </div>

    <!-- Comparison panel -->
    <div class="ats-compare-panel" id="atsComparePanel" role="dialog" aria-label="Applicant comparison">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h3 style="margin:0;font-size:1rem"><i class="fas fa-columns"></i> Side-by-Side Comparison</h3>
            <button type="button" class="ats-drawer__close" id="atsCompareClose" aria-label="Close comparison"><i class="fas fa-times"></i></button>
        </div>
        <div id="atsCompareBody"></div>
    </div>

    <!-- Applicant Drawer -->
    <div class="ats-drawer-overlay" id="atsDrawerOverlay" aria-hidden="true"></div>
    <aside class="ats-drawer" id="atsApplicantDrawer" role="dialog" aria-label="Applicant profile" aria-modal="true">
        <div class="ats-drawer__header">
            <h3 style="margin:0;font-size:1.1rem;font-weight:700" id="atsDrawerTitle">Applicant Profile</h3>
            <button type="button" class="ats-drawer__close" id="atsDrawerClose" aria-label="Close drawer"><i class="fas fa-times"></i></button>
        </div>
        <div class="ats-drawer__tabs" role="tablist">
            <button type="button" class="ats-drawer__tab active" data-tab="profile" role="tab">Profile</button>
            <button type="button" class="ats-drawer__tab" data-tab="experience" role="tab">Experience</button>
            <button type="button" class="ats-drawer__tab" data-tab="training" role="tab">Training</button>
            <button type="button" class="ats-drawer__tab" data-tab="documents" role="tab">Documents</button>
            <button type="button" class="ats-drawer__tab" data-tab="cover" role="tab">Cover Letter</button>
            <button type="button" class="ats-drawer__tab" data-tab="communication" role="tab">Communication</button>
        </div>
        <div class="ats-drawer__body">
            <div id="userInfoLoading" style="text-align:center;padding:40px">
                <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:var(--ats-primary)"></i>
                <p style="margin-top:16px;color:var(--ats-text-muted)">Loading profile…</p>
            </div>
            <div id="userInfoContent" style="display:none"></div>
            <div id="userInfoError" style="display:none;text-align:center;padding:40px;color:var(--ats-danger)">
                <i class="fas fa-exclamation-circle" style="font-size:2rem;margin-bottom:16px"></i>
                <p id="userInfoErrorText"></p>
            </div>
        </div>
    </aside>

    <!-- Cover Letter Modal -->
    <div class="ats-modal" id="coverLetterModal" role="dialog" aria-labelledby="coverLetterTitle" aria-modal="true">
        <div class="ats-modal__content">
            <div class="ats-modal__header">
                <h3 class="ats-modal__title" id="coverLetterTitle">Cover Letter</h3>
                <button type="button" class="ats-modal__close" onclick="closeCoverLetterModal()" aria-label="Close">&times;</button>
            </div>
            <div class="ats-modal__body" id="coverLetterPrintArea">
                <p><strong>Applicant:</strong> <span id="coverLetterApplicant"></span></p>
                <p><strong>Position:</strong> <span id="coverLetterPosition"></span></p>
                <div class="ats-cover-letter" style="margin-top:16px">
                    <div class="ats-cover-letter__header"><div class="ats-cover-letter__date" id="coverLetterDate"></div></div>
                    <div id="coverLetterText"></div>
                </div>
            </div>
            <div class="ats-modal__footer">
                <button type="button" class="ats-btn ats-btn-outline" onclick="printCoverLetter()"><i class="fas fa-print"></i> Print</button>
                <button type="button" class="ats-btn ats-btn-outline" onclick="closeCoverLetterModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Status Confirm Modal -->
    <div class="ats-modal" id="statusConfirmModal" role="dialog" aria-modal="true">
        <div class="ats-modal__content">
            <div class="ats-modal__header ats-status-modal-header--approved" id="statusModalHeader">
                <div style="display:flex;align-items:center;gap:12px">
                    <div class="ats-status-modal-icon" id="statusModalIcon"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <h3 class="ats-modal__title" id="statusModalTitle" style="margin:0">Approve Application</h3>
                        <p id="statusModalSubtitle" style="margin:4px 0 0;font-size:0.875rem;color:var(--ats-text-muted)">Confirm status change</p>
                    </div>
                </div>
                <button type="button" class="ats-modal__close" onclick="closeStatusConfirmModal()" aria-label="Close">&times;</button>
            </div>
            <div class="ats-modal__body">
                <p id="statusModalMessage" style="font-size:1rem;line-height:1.6;margin:0 0 16px">Mark this application as <strong style="color:var(--ats-success)">APPROVED</strong>?</p>
                <div class="ats-info-box ats-info-box--success" id="statusModalInfoBox">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px"><i class="fas fa-envelope"></i><strong>Email Notification</strong></div>
                    <p id="statusModalEmail" style="margin:0;font-weight:600;word-break:break-all"></p>
                    <p style="margin:8px 0 0;font-size:0.875rem;color:var(--ats-text-muted)">An email will be sent to the applicant.</p>
                </div>
                <div class="ats-info-section" id="statusModalDetails" style="margin-bottom:16px">
                    <div style="margin-bottom:8px"><i class="fas fa-user"></i> <strong>Applicant:</strong> <span id="statusModalApplicant"></span></div>
                    <div><i class="fas fa-briefcase"></i> <strong>Position:</strong> <span id="statusModalPosition"></span></div>
                </div>
                <div class="ats-info-box ats-info-box--info" id="statusModalNote">
                    <p style="margin:0 0 8px;font-weight:600"><i class="fas fa-info-circle"></i> What happens next?</p>
                    <ul style="margin:0;padding-left:20px;font-size:0.875rem;line-height:1.8">
                        <li id="statusModalNextStep">An interview will be automatically scheduled</li>
                        <li>The applicant will receive an email notification</li>
                        <li>Application will be moved to Application History</li>
                    </ul>
                </div>
            </div>
            <div class="ats-modal__footer">
                <button type="button" class="ats-btn ats-btn-outline" onclick="closeStatusConfirmModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="button" id="statusConfirmButton" class="ats-btn ats-btn-success" onclick="confirmStatusUpdate()"><i class="fas fa-check-circle"></i> <span id="statusConfirmButtonText">Confirm</span></button>
            </div>
        </div>
    </div>

    <!-- Archive Modal -->
    <div class="ats-modal" id="archiveConfirmModal" role="dialog" aria-modal="true">
        <div class="ats-modal__content">
            <div class="ats-modal__header ats-status-modal-header--rejected">
                <div style="display:flex;align-items:center;gap:12px">
                    <div class="ats-status-modal-icon" style="background:var(--ats-danger)"><i class="fas fa-exclamation-triangle"></i></div>
                    <h3 class="ats-modal__title" style="margin:0">Confirm Archive</h3>
                </div>
                <button type="button" class="ats-modal__close" onclick="closeArchiveConfirmModal()" aria-label="Close">&times;</button>
            </div>
            <div class="ats-modal__body">
                <p style="line-height:1.6;margin:0 0 16px">Move this application to <strong>Application History</strong>?</p>
                <div class="ats-info-box ats-info-box--danger">
                    <div style="margin-bottom:8px"><strong>Applicant:</strong> <span id="archiveConfirmApplicant"></span></div>
                    <div><strong>Position:</strong> <span id="archiveConfirmPosition"></span></div>
                </div>
                <div class="ats-info-box ats-info-box--info" style="margin-top:16px">
                    <i class="fas fa-info-circle"></i> This can be restored from Application History at any time.
                </div>
            </div>
            <div class="ats-modal__footer">
                <button type="button" class="ats-btn ats-btn-outline" onclick="closeArchiveConfirmModal()">Cancel</button>
                <button type="button" class="ats-btn ats-btn-danger" onclick="confirmArchive()"><i class="fas fa-archive"></i> Archive</button>
            </div>
        </div>
    </div>

    <script>
        window.ATS_CONFIG = {
            STATUS_PENDING: '<?= STATUS_PENDING ?>',
            STATUS_APPROVED: '<?= STATUS_APPROVED ?>',
            STATUS_REJECTED: '<?= STATUS_REJECTED ?>',
            BASE_URL: '<?= BASE_URL ?>'
        };
        if (typeof BASE_URL === 'undefined') { var BASE_URL = '<?= BASE_URL ?>'; }
    </script>
    <script src="../../assets/js/toast.js"></script>
    <script src="../../assets/js/office-theme.js"></script>
    <script src="../../assets/js/admin-dashboard.js"></script>
    <script src="../../assets/js/notifications.js"></script>
    <script src="../../assets/js/applications-module.js"></script>
    <script src="../../assets/js/form-submission-manager.js"></script>
</body>
</html>
