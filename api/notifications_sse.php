<?php
/**
 * Server-Sent Events (SSE) endpoint for real-time notifications
 * Provides real-time notification updates to admin users
 */

// Suppress error display for API endpoints
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Increase execution time for long-running SSE connection
set_time_limit(0); // No time limit
ini_set('max_execution_time', 0);

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

// Start output buffering with no buffering
if (ob_get_level()) {
    ob_end_clean();
}
ob_implicit_flush(true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/helpers/Response.php';

class NotificationsSSE {
    private $notificationModel;
    private $userId;
    private $isAdmin;
    private $lastNotificationId;

    /**
     * Send error and exit
     */
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo "event: error\n";
        echo "data: " . json_encode([
            'success' => false,
            'message' => $message
        ]) . "\n\n";
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
        exit;
    }
    
    public function __construct() {
        // Ensure session is started (config.php should handle this, but ensure it for SSE)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check authentication
        if (!isset($_SESSION['user_id'])) {
            $this->sendError('Unauthorized', 401);
        }
        
        $this->notificationModel = new Notification();
        
        // Get user info
        $this->userId = $_SESSION['user_id'];
        $this->isAdmin = hasRole(ROLE_ADMIN);
        
        // Only admins can receive notifications
        if (!$this->isAdmin) {
            $this->sendError('Admin access required', 403);
        }
        
        // Get last notification ID from query parameter
        $this->lastNotificationId = isset($_GET['last_notification_id']) ? (int)$_GET['last_notification_id'] : 0;
        
        // Close session for this process to avoid blocking other requests
        // SSE connections can hold sessions, preventing other requests
        session_write_close();
    }

    /**
     * Send SSE event
     */
    private function sendEvent($event, $data) {
        echo "event: $event\n";
        echo "data: " . json_encode($data) . "\n\n";
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    /**
     * Send ping to keep connection alive
     */
    private function sendPing() {
        echo ": ping\n\n";
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    /**
     * Get new notifications since lastNotificationId
     */
    private function getNewNotifications() {
        try {
            // Get all unread notifications
            $allNotifications = $this->notificationModel->getUserNotifications($this->userId, null, false);
            
            // Filter to get only new notifications (ID greater than last seen)
            $newNotifications = array_filter($allNotifications, function($notif) {
                return (int)$notif['id'] > $this->lastNotificationId;
            });
            
            // Sort by ID to ensure proper order
            usort($newNotifications, function($a, $b) {
                return (int)$a['id'] - (int)$b['id'];
            });
            
            return array_values($newNotifications);
        } catch (Exception $e) {
            error_log("SSE getNewNotifications error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get unread count
     */
    private function getUnreadCount() {
        try {
            return $this->notificationModel->getUnreadCount($this->userId);
        } catch (Exception $e) {
            error_log("SSE getUnreadCount error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Stream notifications in real-time
     */
    public function stream() {
        // Send initial connection message
        $this->sendEvent('connected', [
            'success' => true,
            'message' => 'Connected to real-time notifications',
            'timestamp' => time(),
            'unread_count' => $this->getUnreadCount()
        ]);

        $pingInterval = 0;
        $checkInterval = 0;
        $iteration = 0;
        
        // Stream for up to 2 minutes (120 seconds) to avoid timeouts
        // Client will automatically reconnect
        $maxTime = time() + 120;
        $maxIterations = 1200; // 120 seconds * 10 iterations per second = 1200
        
        while (time() < $maxTime && $iteration < $maxIterations) {
            // Check for new notifications every 500ms (5 iterations)
            // This makes it very responsive - checking twice per second
            if ($checkInterval >= 5) {
                try {
                    $newNotifications = $this->getNewNotifications();
                    
                    if (!empty($newNotifications)) {
                        foreach ($newNotifications as $notification) {
                            $this->sendEvent('notification', [
                                'success' => true,
                                'data' => $notification
                            ]);
                            
                            // Update last notification ID
                            $this->lastNotificationId = max($this->lastNotificationId, (int)$notification['id']);
                        }
                        
                        // Always send updated unread count when new notifications are found
                        // This ensures the badge count is always accurate
                        $unreadCount = $this->getUnreadCount();
                        $this->sendEvent('unread_count', [
                            'success' => true,
                            'count' => $unreadCount
                        ]);
                    } else {
                        // Periodically send unread count even when no new notifications
                        // This keeps the count accurate if notifications were marked as read elsewhere
                        if ($checkInterval % 20 == 0) { // Every 2 seconds (20 * 100ms)
                            $unreadCount = $this->getUnreadCount();
                            $this->sendEvent('unread_count', [
                                'success' => true,
                                'count' => $unreadCount
                            ]);
                        }
                    }
                } catch (Exception $e) {
                    error_log("SSE stream error: " . $e->getMessage());
                    // Continue streaming even if there's an error
                }
                
                $checkInterval = 0;
            }
            
            // Check if client is still connected
            if (connection_aborted()) {
                break;
            }
            
            // Send ping every 15 seconds to keep connection alive
            if ($pingInterval >= 150) { // 15 * 100ms = 1500ms (1.5 seconds), so 150 iterations = 15 seconds
                $this->sendPing();
                $pingInterval = 0;
            }
            
            // Sleep for 100ms
            usleep(100000);
            $pingInterval++;
            $checkInterval++;
            $iteration++;
            
            // Check if client disconnected
            if (connection_aborted()) {
                break;
            }
        }
        
        // Send reconnect message before closing
        $this->sendEvent('reconnect', [
            'success' => true,
            'message' => 'Connection timeout - will reconnect',
            'timestamp' => time(),
            'last_notification_id' => $this->lastNotificationId
        ]);
    }
}

// Handle request
try {
    $sse = new NotificationsSSE();
    $sse->stream();
} catch (Exception $e) {
    error_log("Notifications SSE Error: " . $e->getMessage());
    echo "event: error\n";
    echo "data: " . json_encode([
        'success' => false,
        'message' => 'An error occurred',
        'error' => $e->getMessage()
    ]) . "\n\n";
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

