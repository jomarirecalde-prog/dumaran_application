<?php
/**
 * Notifications API Controller
 */

require_once __DIR__ . '/controllers/ApiController.php';
require_once __DIR__ . '/../includes/models.php';

class NotificationsController extends ApiController {
    private $notificationModel;

    public function __construct() {
        parent::__construct();
        $this->notificationModel = new Notification();
    }

    public function handle() {
        try {
            $action = $_GET['action'] ?? 'index';
            
            switch ($this->method) {
                case 'GET':
                    $this->handleGet($action);
                    break;
                case 'POST':
                    $this->handlePost($action);
                    break;
                case 'PUT':
                case 'PATCH':
                    $this->handlePut($action);
                    break;
                default:
                    Response::error('Method not allowed', 405);
            }
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    private function handleGet($action) {
        switch ($action) {
            case 'index':
                $this->getNotifications();
                break;
            case 'unread-count':
                $this->getUnreadCount();
                break;
            default:
                Response::notFound('Action not found');
        }
    }

    private function handlePost($action) {
        $this->verifyCsrf();
        
        switch ($action) {
            case 'mark-read':
                $this->markAsRead();
                break;
            case 'mark-all-read':
                $this->markAllAsRead();
                break;
            default:
                Response::notFound('Action not found');
        }
    }

    private function handlePut($action) {
        $this->verifyCsrf();
        
        switch ($action) {
            case 'mark-read':
                $this->markAsRead();
                break;
            case 'mark-all-read':
                $this->markAllAsRead();
                break;
            default:
                Response::notFound('Action not found');
        }
    }

    /**
     * Get notifications for current user
     */
    private function getNotifications() {
        $userId = $this->requireAuth();
        
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;
        $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
        
        $notifications = $this->notificationModel->getUserNotifications($userId, $limit, $unreadOnly);
        
        Response::success($notifications);
    }

    /**
     * Get unread notification count
     */
    private function getUnreadCount() {
        $userId = $this->requireAuth();
        
        $count = $this->notificationModel->getUnreadCount($userId);
        
        Response::success(['count' => $count]);
    }

    /**
     * Mark notification as read
     */
    private function markAsRead() {
        $userId = $this->requireAuth();
        
        $notificationId = isset($this->requestData['notification_id']) 
            ? (int)$this->requestData['notification_id'] 
            : null;
        
        if (!$notificationId) {
            Response::error('Notification ID is required', 400);
        }
        
        $result = $this->notificationModel->markAsRead($notificationId, $userId);
        
        if ($result) {
            Response::success(['message' => 'Notification marked as read']);
        } else {
            Response::error('Failed to mark notification as read', 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    private function markAllAsRead() {
        $userId = $this->requireAuth();
        
        $result = $this->notificationModel->markAllAsRead($userId);
        
        if ($result) {
            Response::success(['message' => 'All notifications marked as read']);
        } else {
            Response::error('Failed to mark notifications as read', 500);
        }
    }
}

// Initialize and handle request
$controller = new NotificationsController();
$controller->handle();

