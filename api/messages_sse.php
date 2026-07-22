<?php
/**
 * Server-Sent Events (SSE) endpoint for real-time chat messages
 * Provides real-time message updates to clients
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
require_once __DIR__ . '/controllers/ApiController.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/helpers/Response.php';

class MessagesSSE {
    private $messageModel;
    private $applicationModel;
    private $userId;
    private $isAdmin;
    private $applicationId;
    private $recipientId;
    private $lastMessageId;

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
        // Check authentication
        if (!isset($_SESSION['user_id'])) {
            $this->sendError('Unauthorized', 401);
        }
        
        $this->messageModel = new Message();
        $this->applicationModel = new Application();
        
        // Get user info
        $this->userId = $_SESSION['user_id'];
        $this->isAdmin = hasRole(ROLE_ADMIN);
        
        // Get chat context
        $this->applicationId = isset($_GET['application_id']) ? (int)$_GET['application_id'] : null;
        $this->recipientId = isset($_GET['recipient_id']) ? (int)$_GET['recipient_id'] : null;
        $this->lastMessageId = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : 0;
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
     * Get new messages since lastMessageId
     */
    private function getNewMessages() {
        try {
            $messages = [];
            
            if ($this->applicationId) {
                // Application-based chat
                $allMessages = $this->messageModel->getMessagesByApplication($this->applicationId, 100);
                $messages = array_filter($allMessages, function($msg) {
                    return (int)$msg['id'] > $this->lastMessageId;
                });
            } elseif ($this->recipientId && $this->isAdmin) {
                // Admin viewing user-to-user chat
                $allMessages = $this->messageModel->getMessagesByUsers($this->userId, $this->recipientId, 100);
                $messages = array_filter($allMessages, function($msg) {
                    return (int)$msg['id'] > $this->lastMessageId;
                });
            } elseif (!$this->isAdmin) {
                // Applicant viewing messages
                $allMessages = $this->messageModel->getMessagesForRecipient($this->userId, 100);
                $messages = array_filter($allMessages, function($msg) {
                    return (int)$msg['id'] > $this->lastMessageId;
                });
            }
            
            return array_values($messages);
        } catch (Exception $e) {
            error_log("SSE getNewMessages error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Stream messages in real-time
     */
    public function stream() {
        // Send initial connection message
        $this->sendEvent('connected', [
            'success' => true,
            'message' => 'Connected to real-time chat',
            'timestamp' => time()
        ]);

        $pingInterval = 0;
        $checkInterval = 0;
        $iteration = 0;
        
        // Stream for up to 2 minutes (120 seconds) to avoid timeouts
        // Client will automatically reconnect
        $maxTime = time() + 120;
        $maxIterations = 1200; // 120 seconds * 10 iterations per second = 1200
        
        while (time() < $maxTime && $iteration < $maxIterations) {
            // Check for new messages every 1 second (10 iterations)
            if ($checkInterval >= 10) {
                try {
                    $newMessages = $this->getNewMessages();
                    
                    if (!empty($newMessages)) {
                        foreach ($newMessages as $message) {
                            $this->sendEvent('message', [
                                'success' => true,
                                'data' => $message
                            ]);
                            
                            // Update last message ID
                            $this->lastMessageId = max($this->lastMessageId, (int)$message['id']);
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
            
            // Send ping every 10 seconds to keep connection alive
            if ($pingInterval >= 100) { // 10 * 100ms = 1000ms (1 second), so 100 iterations = 10 seconds
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
            'last_message_id' => $this->lastMessageId
        ]);
    }
}

// Handle request
try {
    $sse = new MessagesSSE();
    $sse->stream();
} catch (Exception $e) {
    error_log("SSE Error: " . $e->getMessage());
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

