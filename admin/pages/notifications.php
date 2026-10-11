<?php
require_once '../../config/config.php';
requireAdmin();

require_once '../../includes/models.php';

$notificationModel = new Notification();
$userId = $_SESSION['user_id'];

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $validation = FormTokenManager::validateFormSubmission($_POST);
    
    if ($validation['valid']) {
        $notificationId = (int)$_POST['notification_id'];
        $notificationModel->markAsRead($notificationId, $userId);
        $_SESSION['success_message'] = 'Notification marked as read';
    } else {
        $_SESSION['error_message'] = $validation['error'];
    }
    header('Location: notifications.php');
    exit();
}

// Handle mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $validation = FormTokenManager::validateFormSubmission($_POST);
    
    if ($validation['valid']) {
        $notificationModel->markAllAsRead($userId);
        $_SESSION['success_message'] = 'All notifications marked as read';
    } else {
        $_SESSION['error_message'] = $validation['error'];
    }
    header('Location: notifications.php');
    exit();
}

// Get filter
$filter = $_GET['filter'] ?? 'all'; // all, unread, read

// Get notifications
if ($filter === 'unread') {
    $notifications = $notificationModel->getUserNotifications($userId, null, true);
} else if ($filter === 'read') {
    $allNotifications = $notificationModel->getUserNotifications($userId);
    $notifications = array_filter($allNotifications, function($n) {
        return $n['is_read'] == 1;
    });
} else {
    $notifications = $notificationModel->getUserNotifications($userId);
}

$unreadCount = $notificationModel->getUnreadCount($userId);

$user = ['full_name' => $_SESSION['full_name'] ?? 'Admin', 'email' => $_SESSION['email'] ?? ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Notifications - Dumaran Hiring System</title>
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/office-theme.css" rel="stylesheet">
    <link href="../../assets/css/admin-dashboard.css" rel="stylesheet">
    <style>
        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .filter-tabs {
            display: flex;
            gap: 8px;
            background: var(--white);
            padding: 4px;
            border-radius: 8px;
            border: 1px solid var(--gray-200);
        }

        .filter-tab {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            color: var(--gray-600);
            font-weight: 500;
            transition: all 0.2s;
            border: none;
            background: none;
            cursor: pointer;
        }

        .filter-tab:hover {
            background-color: var(--gray-100);
            color: var(--primary-blue);
        }

        .filter-tab.active {
            background-color: var(--primary-blue);
            color: white;
        }

        .notification-card {
            padding: 20px;
            border-bottom: 1px solid var(--gray-200);
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }

        .notification-card:last-child {
            border-bottom: none;
        }

        .notification-card:hover {
            background-color: var(--gray-50);
        }

        .notification-card.unread {
            background-color: rgba(30, 64, 175, 0.05);
            border-left: 3px solid var(--primary-blue);
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            font-size: 1rem;
            color: var(--gray-800);
            margin-bottom: 8px;
        }

        .notification-message {
            font-size: 0.9rem;
            color: var(--gray-600);
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .notification-meta {
            display: flex;
            gap: 16px;
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        .notification-actions {
            display: flex;
            gap: 8px;
        }

        .btn-mark-read {
            padding: 6px 12px;
            font-size: 0.875rem;
            border-radius: 6px;
            border: 1px solid var(--gray-300);
            background: var(--white);
            color: var(--gray-700);
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-mark-read:hover {
            background: var(--gray-100);
            border-color: var(--gray-400);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            color: var(--gray-300);
        }

        .empty-state h3 {
            font-size: 1.25rem;
            margin-bottom: 8px;
            color: var(--gray-600);
        }

        .empty-state p {
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .notifications-header {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-tabs {
                width: 100%;
                justify-content: stretch;
            }

            .filter-tab {
                flex: 1;
                text-align: center;
            }

            .notification-card {
                flex-direction: column;
            }

            .notification-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body class="admin-dashboard">
    <?php $adminSidebarActive = 'notifications'; require __DIR__ . '/_admin_sidebar.php'; ?>

<!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <!-- Top Bar -->
        <div class="topbar">
            <button class="mobile-menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="topbar-title">Notifications</h1>
            <div class="topbar-actions" style="position: relative;">
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
        <div class="content">
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <div><?= htmlspecialchars($_SESSION['success_message']) ?></div>
            </div>
            <?php unset($_SESSION['success_message']); endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div><?= htmlspecialchars($_SESSION['error_message']) ?></div>
            </div>
            <?php unset($_SESSION['error_message']); endif; ?>

            <div class="notifications-header">
                <div>
                    <h2 style="margin: 0; font-size: 1.5rem; color: var(--gray-800);">Notifications</h2>
                    <p style="margin: 4px 0 0 0; color: var(--gray-600); font-size: 0.9rem;">
                        <?php if ($unreadCount > 0): ?>
                            <?= $unreadCount ?> unread notification<?= $unreadCount > 1 ? 's' : '' ?>
                        <?php else: ?>
                            All caught up!
                        <?php endif; ?>
                    </p>
                </div>
                <?php if ($unreadCount > 0): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <?php FormTokenManager::renderFormTokenInput('mark_all_read'); ?>
                    <input type="hidden" name="mark_all_read" value="1">
                    <button type="submit" class="btn" style="background: var(--primary-blue); color: white;">
                        <i class="fas fa-check-double"></i> Mark all as read
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <div class="filter-tabs">
                <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">
                    All (<?= count($notifications) ?>)
                </a>
                <a href="?filter=unread" class="filter-tab <?= $filter === 'unread' ? 'active' : '' ?>">
                    Unread (<?= $unreadCount ?>)
                </a>
                <a href="?filter=read" class="filter-tab <?= $filter === 'read' ? 'active' : '' ?>">
                    Read (<?= count($notifications) - $unreadCount ?>)
                </a>
            </div>

            <div class="card" style="margin-top: 24px;">
                <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <h3>No notifications</h3>
                    <p>You're all caught up! New notifications will appear here when applications are submitted.</p>
                </div>
                <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                <div class="notification-card <?= !$notification['is_read'] ? 'unread' : '' ?>">
                    <div class="notification-content">
                        <div class="notification-title">
                            <?= htmlspecialchars($notification['title']) ?>
                        </div>
                        <div class="notification-message">
                            <?= htmlspecialchars($notification['message']) ?>
                        </div>
                        <div class="notification-meta">
                            <span>
                                <i class="fas fa-clock"></i> 
                                <?php
                                $createdAt = new DateTime($notification['created_at']);
                                $now = new DateTime();
                                $diff = $now->diff($createdAt);
                                
                                if ($diff->days > 0) {
                                    echo $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
                                } else if ($diff->h > 0) {
                                    echo $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
                                } else if ($diff->i > 0) {
                                    echo $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
                                } else {
                                    echo 'Just now';
                                }
                                ?>
                            </span>
                            <?php if ($notification['related_type'] === 'application' && $notification['related_id']): ?>
                            <a href="applications.php?application_id=<?= $notification['related_id'] ?>" style="color: var(--primary-blue); text-decoration: none;">
                                <i class="fas fa-external-link-alt"></i> View Application
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!$notification['is_read']): ?>
                    <div class="notification-actions">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <?php FormTokenManager::renderFormTokenInput('mark_read_' . $notification['id']); ?>
                            <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                            <input type="hidden" name="mark_read" value="1">
                            <button type="submit" class="btn-mark-read">
                                <i class="fas fa-check"></i> Mark as read
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
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
</body>
</html>

