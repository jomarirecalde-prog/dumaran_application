<?php
/**
 * Shared admin sidebar — unified ATS / dashboard design
 *
 * Set before include:
 *   $adminSidebarActive = 'dashboard'|'positions'|'applications'|'application_history'|'users'|'reports'|'notifications'|'settings'|'chat'
 */
$adminSidebarActive = $adminSidebarActive ?? '';
$adminSidebarUserName = $adminSidebarUserName ?? ($adminUser['full_name'] ?? $_SESSION['full_name'] ?? 'Admin');

$adminSidebarSections = [
    [
        'title' => 'Overview',
        'items' => [
            ['key' => 'dashboard', 'href' => 'dashboard.php', 'icon' => 'fa-th-large', 'label' => 'Dashboard'],
        ],
    ],
    [
        'title' => 'Recruitment',
        'items' => [
            ['key' => 'positions', 'href' => 'positions.php', 'icon' => 'fa-briefcase', 'label' => 'Job Hiring'],
            ['key' => 'applications', 'href' => 'applications.php', 'icon' => 'fa-file-alt', 'label' => 'Applications'],
            ['key' => 'application_history', 'href' => 'application_history.php', 'icon' => 'fa-history', 'label' => 'History'],
        ],
    ],
    [
        'title' => 'People',
        'items' => [
            ['key' => 'users', 'href' => 'users.php', 'icon' => 'fa-users', 'label' => 'Users'],
            ['key' => 'chat', 'href' => 'chat.php', 'icon' => 'fa-comments', 'label' => 'Chat'],
        ],
    ],
    [
        'title' => 'Insights',
        'items' => [
            ['key' => 'reports', 'href' => 'reports.php', 'icon' => 'fa-chart-bar', 'label' => 'Reports'],
            ['key' => 'notifications', 'href' => 'notifications.php', 'icon' => 'fa-bell', 'label' => 'Notifications'],
            ['key' => 'settings', 'href' => 'settings.php', 'icon' => 'fa-cog', 'label' => 'Settings'],
        ],
    ],
];
?>
    <aside class="sidebar" role="navigation" aria-label="Main navigation">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand" aria-label="WPU Admin Home">
                <img src="../../assets/logo.png" alt="WPU Logo" style="height: 32px; width: auto;">
                <span>WPU Admin</span>
            </a>
            <button type="button" class="sidebar-collapse-btn" id="sidebarCollapseBtn" aria-label="Toggle sidebar">
                <i class="fas fa-angles-left"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <?php foreach ($adminSidebarSections as $section): ?>
            <div class="sidebar-nav-section">
                <div class="sidebar-nav-section-title"><?= htmlspecialchars($section['title']) ?></div>
                <?php foreach ($section['items'] as $item):
                    $isActive = $adminSidebarActive === $item['key'];
                ?>
                <a href="<?= htmlspecialchars($item['href']) ?>"
                   class="sidebar-nav-item<?= $isActive ? ' active' : '' ?>"
                   <?= $isActive ? 'aria-current="page"' : '' ?>>
                    <i class="fas <?= htmlspecialchars($item['icon']) ?>"></i>
                    <span class="sidebar-nav-label"><?= htmlspecialchars($item['label']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar" aria-hidden="true"><?= strtoupper(substr($adminSidebarUserName, 0, 1)) ?></div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?= htmlspecialchars($adminSidebarUserName) ?></div>
                    <div class="sidebar-user-role">Administrator</div>
                </div>
            </div>
        </div>
    </aside>
