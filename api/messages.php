<?php
/**
 * Messages API Controller
 * Handles API requests for chat messages between applicants and admins
 */

// Suppress error display for API endpoints - we want JSON responses only
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
// Increase execution time for API calls
set_time_limit(30); // 30 seconds should be enough for message operations

// Start output buffering early to catch any accidental output
if (ob_get_level() == 0) {
    ob_start();
}

// Set error handler to prevent HTML output
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log the error but don't output it
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return true; // Don't execute PHP internal error handler
}, E_ALL);

require_once __DIR__ . '/controllers/ApiController.php';

class MessagesController extends ApiController {
    private $messageModel;
    private $applicationModel;
    private static $tableExistsCache = null;
    private static $columnExistsCache = [];

    public function __construct() {
        parent::__construct();
        $this->messageModel = new Message();
        $this->applicationModel = new Application();
    }

    /**
     * Handle request routing
     */
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

    /**
     * Handle GET requests
     */
    private function handleGet($action) {
        switch ($action) {
            case 'index':
                $this->getMessages();
                break;
            case 'unread-count':
                $this->getUnreadCount();
                break;
            case 'conversations':
                $this->getConversations();
                break;
            case 'search-users':
                $this->searchUsers();
                break;
            default:
                Response::notFound('Action not found');
        }
    }

    /**
     * Handle POST requests
     */
    private function handlePost($action) {
        $this->verifyCsrf();
        
        switch ($action) {
            case 'send':
                $this->sendMessage();
                break;
            default:
                Response::notFound('Action not found');
        }
    }

    /**
     * Handle PUT requests
     */
    private function handlePut($action) {
        $this->verifyCsrf();
        
        switch ($action) {
            case 'mark-read':
                $this->markAsRead();
                break;
            default:
                Response::notFound('Action not found');
        }
    }

    /**
     * Get messages for an application or user-to-user chat
     */
    private function getMessages() {
        try {
            $userId = $this->requireAuth();
            $isAdmin = hasRole(ROLE_ADMIN);
            
            // First, check if messages table exists
            if (!$this->tableExists('messages')) {
                Response::error('Messages table does not exist in the database. Please create it using the SQL file: config/migrations/create_messages_table.sql', 500);
            }
            
            $applicationId = isset($this->requestData['application_id']) ? (int)$this->requestData['application_id'] : null;
            $recipientId = isset($this->requestData['recipient_id']) ? (int)$this->requestData['recipient_id'] : null;
            
            // Support both application-based and user-to-user chats
            if ($applicationId) {
                // Application-based chat
                $application = $this->applicationModel->findById($applicationId);
                if (!$application) {
                    Response::error('Application not found', 404);
                }
                
                // Check if application is approved or rejected
                if ($application['status'] !== STATUS_APPROVED && $application['status'] !== STATUS_REJECTED) {
                    Response::error('Chat is only available for approved or rejected applications', 403);
                }
                
                // Verify access: admin can see all, applicant can only see their own
                if (!$isAdmin && (int)$application['user_id'] !== (int)$userId) {
                    Response::forbidden('You do not have access to this application');
                }
                
                $limit = min(100, max(10, (int)($this->requestData['limit'] ?? 50)));
                
                try {
                    $messages = $this->messageModel->getMessagesByApplication($applicationId, $limit);
                } catch (Exception $e) {
                    error_log("Messages table error: " . $e->getMessage());
                    $messages = [];
                }
            } elseif ($recipientId) {
                // User-to-user chat
                // For applicants: allow them to see messages where they are the recipient (from admins only)
                // For admins: allow them to see chats with applicants only
                
                // Verify recipient exists
                $userModel = new User();
                $recipient = $userModel->findById($recipientId);
                if (!$recipient) {
                    Response::error('Recipient not found', 404);
                }
                
                // For applicants: they can view messages between themselves and the admin
                if (!$isAdmin) {
                    // Verify recipient is an admin (applicants can only chat with admins)
                    if ($recipient['role'] !== ROLE_ADMIN) {
                        Response::forbidden('You can only chat with administrators');
                    }
                    
                    // Verify that the applicant is part of this conversation
                    // The recipientId should be the admin they're chatting with
                    // Messages will be filtered to show only messages between applicant and this admin
                } else {
                    // Admin can only chat with applicants, not with other admins
                    if ($recipient['role'] === ROLE_ADMIN) {
                        Response::error('Admins can only chat with applicants', 400);
                    }
                    
                    // Admin can't chat with themselves
                    if ((int)$recipientId === (int)$userId) {
                        Response::error('Cannot chat with yourself', 400);
                    }
                }
                
                $limit = min(100, max(10, (int)($this->requestData['limit'] ?? 50)));
                
                // Check if recipient_id column exists before querying
                if (!$this->columnExists('recipient_id')) {
                    Response::error('Database migration required. The recipient_id column does not exist. Please run: config/migrations/add_recipient_to_messages.sql', 500);
                }
                
                try {
                    if ($isAdmin) {
                        // Admin viewing user-to-user chat
                        $messages = $this->messageModel->getMessagesByUsers($userId, $recipientId, $limit);
                    } else {
                        // Applicant viewing messages with a specific admin (using recipient_id)
                        // Get messages between applicant and the admin (recipientId)
                        $messages = $this->messageModel->getMessagesByUsers($userId, $recipientId, $limit);
                    }
                } catch (PDOException $e) {
                    error_log("Messages query error: " . $e->getMessage());
                    error_log("PDO Error Code: " . $e->getCode());
                    
                    // Check if it's a column doesn't exist error
                    if (strpos($e->getMessage(), 'recipient_id') !== false || $e->getCode() == '42S22') {
                        Response::error('Database migration required. The recipient_id column does not exist. Please run: config/migrations/add_recipient_to_messages.sql', 500);
                    }
                    
                    // For other errors, return empty array
                    $messages = [];
                } catch (Exception $e) {
                    error_log("Messages table error: " . $e->getMessage());
                    $messages = [];
                }
            } else {
                // For applicants: also check for messages sent to them via recipient_id
                if (!$isAdmin) {
                    if (!$this->columnExists('recipient_id')) {
                        Response::error('Application ID is required', 400);
                    }
                    
                    try {
                        $limit = min(100, max(10, (int)($this->requestData['limit'] ?? 50)));
                        $messages = $this->messageModel->getMessagesForRecipient($userId, $limit);
                    } catch (Exception $e) {
                        error_log("Messages table error: " . $e->getMessage());
                        $messages = [];
                    }
                } else {
                    Response::error('Application ID or Recipient ID is required', 400);
                }
            }
            
            // Check if recipient_id column exists (for user-to-user chats)
            // Only check if we're actually using recipient_id
            if (($recipientId || (!$applicationId && !$isAdmin)) && !$this->columnExists('recipient_id')) {
                Response::error('Database migration required. The recipient_id column does not exist. Please run: config/migrations/add_recipient_to_messages.sql', 500);
            }
            
            // Mark messages as read for the current user
            if (!empty($messages)) {
                $unreadIds = [];
                foreach ($messages as $msg) {
                    if (!$msg['is_read'] && (int)$msg['sender_id'] !== (int)$userId) {
                        $unreadIds[] = (int)$msg['id'];
                    }
                }
                if (!empty($unreadIds)) {
                    try {
                        $this->messageModel->markAsRead($unreadIds, $userId);
                    } catch (Exception $e) {
                        error_log("Mark as read error: " . $e->getMessage());
                    }
                }
            }
            
            Response::success($messages, 'Messages retrieved successfully');
        } catch (Exception $e) {
            error_log("getMessages error: " . $e->getMessage());
            Response::error('Failed to retrieve messages: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Send a message
     */
    private function sendMessage() {
        try {
            $userId = $this->requireAuth();
            $isAdmin = hasRole(ROLE_ADMIN);
            $userRole = $isAdmin ? ROLE_ADMIN : ROLE_APPLICANT;
            
            // Validate input first (faster than DB checks)
            $validator = new Validator($this->requestData);
            $validator->required('message')
                      ->minLength('message', 1)
                      ->maxLength('message', 5000);
            
            // At least one of application_id or recipient_id must be provided
            $applicationId = isset($this->requestData['application_id']) ? (int)$this->requestData['application_id'] : null;
            $recipientId = isset($this->requestData['recipient_id']) ? (int)$this->requestData['recipient_id'] : null;
            
            if (!$applicationId && !$recipientId) {
                Response::error('Application ID or Recipient ID is required', 400);
            }
            
            if ($validator->fails()) {
                Response::validationError($validator->getErrors());
            }
            
            $message = trim($this->requestData['message']);
            
            if (empty($message)) {
                Response::error('Message cannot be empty', 400);
            }
            
            // Handle application-based messaging
            if ($applicationId) {
                // Verify user has access to this application
                $application = $this->applicationModel->findById($applicationId);
                if (!$application) {
                    Response::error('Application not found', 404);
                }
                
                // Check if application is approved or rejected
                if ($application['status'] !== STATUS_APPROVED && $application['status'] !== STATUS_REJECTED) {
                    Response::error('Chat is only available for approved or rejected applications', 403);
                }
                
                // Verify access: admin can message all, applicant can only message their own
                if (!$isAdmin && (int)$application['user_id'] !== (int)$userId) {
                    Response::forbidden('You do not have access to this application');
                }
                
                // For applicants replying: try to find the admin they're chatting with
                // by looking at recent messages for this application
                $detectedRecipientId = null;
                if (!$isAdmin) {
                    // Check if there's an existing message from an admin for this application
                    // Applicants can only reply, not initiate
                    try {
                        $stmt = $this->messageModel->getConnection()->prepare("
                            SELECT sender_id 
                            FROM messages 
                            WHERE application_id = ? 
                            AND sender_role = 'admin'
                            ORDER BY created_at DESC 
                            LIMIT 1
                        ");
                        $stmt->execute([$applicationId]);
                        $adminMessage = $stmt->fetch();
                        if ($adminMessage) {
                            $detectedRecipientId = (int)$adminMessage['sender_id'];
                        } else {
                            // No admin message exists yet - applicant cannot initiate
                            Response::forbidden('You can only reply to conversations. Please wait for an administrator to message you first.');
                        }
                    } catch (Exception $e) {
                        error_log("Error detecting recipient_id for applicant reply: " . $e->getMessage());
                        Response::forbidden('You can only reply to conversations. Please wait for an administrator to message you first.');
                    }
                }
                
                // Create message
                $data = [
                    'application_id' => $applicationId,
                    'sender_id' => $userId,
                    'recipient_id' => $detectedRecipientId,
                    'sender_role' => $userRole,
                    'message' => $message
                ];
            } else {
                // User-to-user messaging - only admins can initiate chats with applicants
                if (!$isAdmin) {
                    // Applicants can only reply to existing conversations
                    // Check if there's an existing message from the recipient (admin) to this applicant
                    // Optimized query: check direct messages first (faster), then application-based
                    $hasExistingConversation = false;
                    try {
                        // First check direct messages (fastest)
                        $stmt = $this->messageModel->getConnection()->prepare("
                            SELECT 1 
                            FROM messages 
                            WHERE (
                                (sender_id = ? AND recipient_id = ?)
                                OR (sender_id = ? AND recipient_id = ?)
                            )
                            LIMIT 1
                        ");
                        $stmt->execute([$recipientId, $userId, $userId, $recipientId]);
                        if ($stmt->fetch()) {
                            $hasExistingConversation = true;
                        } else {
                            // Then check application-based messages (slower, but only if needed)
                            $stmt = $this->messageModel->getConnection()->prepare("
                                SELECT 1 
                                FROM messages m
                                INNER JOIN applications app ON m.application_id = app.id
                                WHERE app.user_id = ?
                                LIMIT 1
                            ");
                            $stmt->execute([$userId]);
                            if ($stmt->fetch()) {
                                $hasExistingConversation = true;
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Error checking existing conversation: " . $e->getMessage());
                        // On error, allow the message to proceed (fail open for better UX)
                        // The database constraint will catch invalid messages anyway
                        $hasExistingConversation = true;
                    }
                    
                    if (!$hasExistingConversation) {
                        Response::forbidden('You can only reply to conversations. Please wait for an administrator to message you first.');
                    }
                }
                
                // Verify recipient exists (do this before column check to fail fast)
                $userModel = new User();
                $recipient = $userModel->findById($recipientId);
                if (!$recipient) {
                    Response::error('Recipient not found', 404);
                }
                
                // Admins can only chat with applicants, not with other admins
                if ($recipient['role'] === ROLE_ADMIN) {
                    Response::error('Admins can only chat with applicants', 400);
                }
                
                // Can't message yourself
                if ((int)$recipientId === (int)$userId) {
                    Response::error('Cannot send message to yourself', 400);
                }
                
                // Auto-detect application_id if recipient is an applicant
                // Find the most recent approved or rejected application for this applicant
                $detectedApplicationId = null;
                if ($recipient['role'] === ROLE_APPLICANT) {
                    try {
                        $stmt = $this->applicationModel->getConnection()->prepare("
                            SELECT id 
                            FROM applications 
                            WHERE user_id = ? 
                            AND status IN (?, ?)
                            ORDER BY updated_at DESC, id DESC
                            LIMIT 1
                        ");
                        $stmt->execute([$recipientId, STATUS_APPROVED, STATUS_REJECTED]);
                        $app = $stmt->fetch();
                        if ($app) {
                            $detectedApplicationId = (int)$app['id'];
                        }
                    } catch (Exception $e) {
                        error_log("Error auto-detecting application_id: " . $e->getMessage());
                        // Continue without application_id if detection fails
                    }
                }
                
                // Create message with auto-detected application_id if available
                $data = [
                    'application_id' => $detectedApplicationId,
                    'sender_id' => $userId,
                    'recipient_id' => $recipientId,
                    'sender_role' => $userRole,
                    'message' => $message
                ];
            }
            
            // Check table/column existence only once before creating message (cached, so fast)
            if (!$this->tableExists('messages')) {
                Response::error('Messages table does not exist in the database. Please create it using the SQL file: config/migrations/create_messages_table.sql', 500);
            }
            
            // Only check recipient_id column if we're using it (cached, so fast)
            if ($recipientId && !$this->columnExists('recipient_id')) {
                Response::error('Database migration required. The recipient_id column does not exist. Please run: config/migrations/add_recipient_to_messages.sql', 500);
            }
            
            try {
                // Create message - this should be fast
                $result = $this->messageModel->create($data);
            } catch (\PDOException $e) {
                error_log("Message creation PDO error: " . $e->getMessage());
                error_log("PDO Error Code: " . $e->getCode());
                $errorInfo = $e->errorInfo ?? [];
                error_log("SQL State: " . ($errorInfo[0] ?? 'N/A'));
                
                $errorMessage = $e->getMessage();
                $errorCode = $e->getCode();
                
                // Check if it's a table doesn't exist error (42S02 is MySQL table doesn't exist)
                if ($errorCode == '42S02' || strpos($errorMessage, "doesn't exist") !== false) {
                    if (strpos($errorMessage, "Table") !== false) {
                        Response::error('Messages table does not exist. Please run the database migration: CREATE TABLE messages...', 500);
                    } elseif (strpos($errorMessage, "Unknown column 'recipient_id'") !== false || strpos($errorMessage, "recipient_id") !== false) {
                        Response::error('Database migration required. The recipient_id column does not exist. Please run: config/migrations/add_recipient_to_messages.sql', 500);
                    } else {
                        Response::error('Database schema error. Please check if all migrations have been run.', 500);
                    }
                } elseif ($errorCode == '42S22' || strpos($errorMessage, "Unknown column") !== false) {
                    // Column doesn't exist error
                    if (strpos($errorMessage, "recipient_id") !== false) {
                        Response::error('Database migration required. Please run: config/migrations/add_recipient_to_messages.sql to enable user-to-user messaging.', 500);
                    } else {
                        Response::error('Database schema error. Please check if all migrations have been run.', 500);
                    }
                } else {
                    Response::error('Failed to send message. Database error: ' . htmlspecialchars($errorMessage), 500);
                }
            } catch (\Exception $e) {
                error_log("Message creation error: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                
                // Check if it's our custom exception about missing column
                if (strpos($e->getMessage(), 'recipient_id column does not exist') !== false) {
                    Response::error('Database migration required. Please run: config/migrations/add_recipient_to_messages.sql', 500);
                } else {
                    Response::error('Failed to send message: ' . htmlspecialchars($e->getMessage()), 500);
                }
            }
            
            if ($result) {
                // Get the created message with sender info
                try {
                    $messageId = $this->messageModel->getConnection()->lastInsertId();
                    $stmt = $this->messageModel->getConnection()->prepare("
                        SELECT m.*, u.full_name as sender_name 
                        FROM messages m 
                        LEFT JOIN users u ON m.sender_id = u.id 
                        WHERE m.id = ?
                    ");
                    $stmt->execute([$messageId]);
                    $newMessage = $stmt->fetch();
                    
                    if ($newMessage) {
                        Response::success($newMessage, 'Message sent successfully', 201);
                    } else {
                        // Message was created but couldn't retrieve it
                        Response::success(['id' => $messageId], 'Message sent successfully', 201);
                    }
                } catch (Exception $e) {
                    error_log("Error retrieving new message: " . $e->getMessage());
                    // Still return success since message was created
                    Response::success(['id' => $messageId ?? $this->messageModel->getConnection()->lastInsertId()], 'Message sent successfully', 201);
                }
            } else {
                Response::error('Failed to send message', 500);
            }
        } catch (Exception $e) {
            error_log("sendMessage error: " . $e->getMessage());
            Response::error('Failed to send message: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mark messages as read
     */
    private function markAsRead() {
        $userId = $this->requireAuth();
        
        $applicationId = (int)($this->requestData['application_id'] ?? 0);
        $messageIds = $this->requestData['message_ids'] ?? [];
        
        if (!$applicationId && empty($messageIds)) {
            Response::error('Application ID or message IDs required', 400);
        }
        
        if (!empty($messageIds) && !is_array($messageIds)) {
            Response::error('Message IDs must be an array', 400);
        }
        
        // If application_id provided, mark all unread messages for that application
        if ($applicationId) {
            $stmt = $this->messageModel->getConnection()->prepare("
                UPDATE messages 
                SET is_read = 1 
                WHERE application_id = ? 
                AND sender_id != ?
                AND is_read = 0
            ");
            $result = $stmt->execute([$applicationId, $userId]);
        } else {
            // Mark specific message IDs
            $messageIds = array_map('intval', $messageIds);
            $result = $this->messageModel->markAsRead($messageIds, $userId);
        }
        
        if ($result) {
            Response::success(null, 'Messages marked as read');
        } else {
            Response::error('Failed to mark messages as read', 500);
        }
    }

    /**
     * Get unread message count
     */
    private function getUnreadCount() {
        $userId = $this->requireAuth();
        
        $applicationId = (int)($this->requestData['application_id'] ?? 0);
        
        if (!$applicationId) {
            Response::error('Application ID is required', 400);
        }
        
        // Verify user has access to this application
        $application = $this->applicationModel->findById($applicationId);
        if (!$application) {
            Response::error('Application not found', 404);
        }
        
        $isAdmin = hasRole(ROLE_ADMIN);
        if (!$isAdmin && (int)$application['user_id'] !== (int)$userId) {
            Response::forbidden('You do not have access to this application');
        }
        
        $count = $this->messageModel->getUnreadCount($applicationId, $userId);
        
        Response::success(['count' => $count], 'Unread count retrieved successfully');
    }

    /**
     * Get conversations list for current user
     */
    private function getConversations() {
        try {
            $userId = $this->requireAuth();
            $isAdmin = hasRole(ROLE_ADMIN);
            
            // Get pagination parameters
            $limit = min(50, max(10, (int)($this->requestData['limit'] ?? 50)));
            $offset = max(0, (int)($this->requestData['offset'] ?? 0));
            
            if ($isAdmin) {
                $conversations = $this->messageModel->getAdminConversations($userId, $limit, $offset);
            } else {
                // Check if user has approved or rejected applications
                $applications = $this->applicationModel->findAll('user_id = ? AND status IN (?, ?)', [
                    $userId, 
                    STATUS_APPROVED, 
                    STATUS_REJECTED
                ]);
                
                if (empty($applications)) {
                    Response::success([], 'No conversations available. Chat is only available for approved or rejected applications.');
                    return;
                }
                
                $conversations = $this->messageModel->getApplicantConversations($userId);
            }
            
            Response::success($conversations, 'Conversations retrieved successfully');
        } catch (Exception $e) {
            error_log("getConversations error: " . $e->getMessage());
            Response::error('Failed to retrieve conversations: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Search users for new chat (admin only)
     */
    private function searchUsers() {
        try {
            $userId = $this->requireAuth();
            $isAdmin = hasRole(ROLE_ADMIN);
            
            if (!$isAdmin) {
                Response::forbidden('Only admins can search for users');
            }
            
            $searchTerm = trim($this->requestData['search'] ?? '');
            
            if (empty($searchTerm)) {
                // Return all applicants if no search term
                $userModel = new User();
                $users = $userModel->findAll('role = ?', [ROLE_APPLICANT]);
            } else {
                // Search applicants by name or email
                $userModel = new User();
                $searchPattern = '%' . $searchTerm . '%';
                $users = $userModel->findAll(
                    'role = ? AND (full_name LIKE ? OR email LIKE ?)',
                    [ROLE_APPLICANT, $searchPattern, $searchPattern]
                );
            }
            
            // Remove sensitive data and add application status info
            $result = [];
            foreach ($users as $user) {
                unset($user['password']);
                
                // Check if user has approved/rejected applications
                $applications = $this->applicationModel->findAll(
                    'user_id = ? AND status IN (?, ?)',
                    [$user['id'], STATUS_APPROVED, STATUS_REJECTED]
                );
                
                $user['can_chat'] = !empty($applications);
                $user['applications_count'] = count($applications);
                
                $result[] = $user;
            }
            
            Response::success($result, 'Users retrieved successfully');
        } catch (Exception $e) {
            error_log("searchUsers error: " . $e->getMessage());
            Response::error('Failed to search users: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Check if a table exists in the database (cached)
     */
    private function tableExists($tableName) {
        // Use cache to avoid repeated queries
        if (self::$tableExistsCache !== null) {
            return self::$tableExistsCache;
        }
        
        try {
            // Fast check using SHOW TABLES
            $stmt = $this->messageModel->getConnection()->query("SHOW TABLES LIKE '{$tableName}'");
            $exists = $stmt->rowCount() > 0;
            self::$tableExistsCache = $exists;
            return $exists;
        } catch (Exception $e) {
            error_log("Error checking if table exists: " . $e->getMessage());
            self::$tableExistsCache = false;
            return false;
        }
    }
    
    /**
     * Check if a column exists in the messages table (cached)
     */
    private function columnExists($columnName) {
        // Use cache to avoid repeated queries
        if (isset(self::$columnExistsCache[$columnName])) {
            return self::$columnExistsCache[$columnName];
        }
        
        try {
            // Fast check using SHOW COLUMNS
            $stmt = $this->messageModel->getConnection()->query("SHOW COLUMNS FROM messages LIKE '{$columnName}'");
            $exists = $stmt->rowCount() > 0;
            self::$columnExistsCache[$columnName] = $exists;
            return $exists;
        } catch (Exception $e) {
            error_log("Error checking if column exists: " . $e->getMessage());
            self::$columnExistsCache[$columnName] = false;
            return false;
        }
    }
}

// Initialize and handle request
try {
    // Clear any output that might have been generated
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    $controller = new MessagesController();
    $controller->handle();
} catch (\PDOException $e) {
    // Clear any output that might have been generated
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Log the error for debugging
    error_log("Messages API PDO Error: " . $e->getMessage());
    error_log("PDO Error Code: " . $e->getCode());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Check if it's a table doesn't exist error
    $errorMessage = 'An error occurred while processing your request.';
    if ($e->getCode() == '42S02' || strpos($e->getMessage(), "doesn't exist") !== false) {
        $errorMessage = 'Messages table does not exist. Please run the database migration.';
    }
    
    // Return JSON error response
    header('Content-Type: application/json', true);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $errorMessage,
        'error' => $e->getMessage()
    ]);
    exit;
} catch (\Exception $e) {
    // Clear any output that might have been generated
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Log the error for debugging
    error_log("Messages API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Return JSON error response
    header('Content-Type: application/json', true);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
    exit;
} catch (\Error $e) {
    // Clear any output that might have been generated
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Log the error for debugging
    error_log("Messages API Fatal Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Return JSON error response
    header('Content-Type: application/json', true);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request. Please check if the messages table exists in the database.',
        'error' => $e->getMessage()
    ]);
    exit;
}

