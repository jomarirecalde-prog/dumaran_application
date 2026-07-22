<?php
/**
 * Users API Controller
 */

require_once __DIR__ . '/controllers/ApiController.php';
require_once __DIR__ . '/../includes/services/UserService.php';

class UsersController extends ApiController {
    private $userService;

    public function __construct() {
        parent::__construct();
        $this->userService = new UserService();
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
                case 'DELETE':
                    $this->handleDelete($action);
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
                $this->getUsers();
                break;
            case 'profile':
                $this->getProfile();
                break;
            case 'activity':
                $this->getActivity();
                break;
            default:
                Response::notFound('Action not found');
        }
    }

    private function handlePost($action) {
        $this->verifyCsrf();
        
        switch ($action) {
            case 'register':
                $this->register();
                break;
            default:
                Response::notFound('Action not found');
        }
    }

    private function handlePut($action) {
        $this->verifyCsrf();
        
        switch ($action) {
            case 'update-profile':
                $this->updateProfile();
                break;
            case 'change-password':
                $this->changePassword();
                break;
            case 'toggle-status':
                $this->toggleStatus();
                break;
            default:
                Response::notFound('Action not found');
        }
    }

    private function handleDelete($action) {
        $this->verifyCsrf();
        
        switch ($action) {
            case 'delete':
                $this->deleteUser();
                break;
            default:
                Response::notFound('Action not found');
        }
    }

    private function getUsers() {
        $this->requireAdmin();
        
        $filters = [
            'role' => $this->requestData['role'] ?? null,
            'is_active' => isset($this->requestData['is_active']) ? (bool)$this->requestData['is_active'] : null,
            'search' => $this->requestData['search'] ?? null,
        ];
        
        $users = $this->userService->getAllUsers($filters);
        
        // Remove sensitive data
        foreach ($users as &$user) {
            unset($user['password']);
        }
        
        // Pagination
        $pagination = $this->getPagination();
        $total = count($users);
        $users = array_slice($users, $pagination['offset'], $pagination['perPage']);
        
        Response::paginated(
            $users,
            $total,
            $pagination['page'],
            $pagination['perPage'],
            'Users retrieved successfully'
        );
    }

    private function getProfile() {
        $userId = $this->requireAuth();
        
        // Check if requesting another user's profile (admin only)
        $requestedUserId = isset($this->requestData['user_id']) ? (int)$this->requestData['user_id'] : $userId;
        
        if ($requestedUserId !== $userId && !hasRole(ROLE_ADMIN)) {
            Response::forbidden('Cannot access other user profiles');
        }
        
        $profile = $this->userService->getUserProfile($requestedUserId);
        
        Response::success($profile, 'Profile retrieved successfully');
    }

    private function register() {
        // Validate input
        $validator = new Validator($this->requestData);
        $validator->required('username')
                  ->minLength('username', 3)
                  ->required('email')
                  ->email('email')
                  ->required('password')
                  ->minLength('password', 6)
                  ->required('full_name');
        
        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }
        
        $user = $this->userService->registerUser($this->requestData);
        
        // Remove password from response
        unset($user['password']);
        
        Response::success($user, 'User registered successfully', 201);
    }

    private function updateProfile() {
        $userId = $this->requireAuth();
        
        // Admins can update other users
        $targetUserId = isset($this->requestData['user_id']) ? (int)$this->requestData['user_id'] : $userId;
        
        if ($targetUserId !== $userId && !hasRole(ROLE_ADMIN)) {
            Response::forbidden('Cannot update other user profiles');
        }
        
        // Remove user_id from data
        unset($this->requestData['user_id']);
        
        $user = $this->userService->updateProfile($targetUserId, $this->requestData);
        
        Response::success($user, 'Profile updated successfully');
    }

    private function changePassword() {
        $userId = $this->requireAuth();
        
        // Validate input
        $validator = new Validator($this->requestData);
        $validator->required('current_password')
                  ->required('new_password')
                  ->minLength('new_password', 6);
        
        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }
        
        $this->userService->changePassword(
            $userId,
            $this->requestData['current_password'],
            $this->requestData['new_password']
        );
        
        Response::success(null, 'Password changed successfully');
    }

    private function toggleStatus() {
        $adminId = $this->requireAdmin();
        
        $userId = (int)($this->requestData['user_id'] ?? 0);
        
        if (!$userId) {
            Response::error('User ID is required', 400);
        }
        
        $newStatus = $this->userService->toggleUserStatus($userId, $adminId);
        
        Response::success(['is_active' => $newStatus], 'User status updated successfully');
    }

    private function getActivity() {
        $userId = $this->requireAuth();
        
        // Admins can view other users' activity
        $targetUserId = isset($this->requestData['user_id']) ? (int)$this->requestData['user_id'] : $userId;
        
        if ($targetUserId !== $userId && !hasRole(ROLE_ADMIN)) {
            Response::forbidden('Cannot view other user activity');
        }
        
        $limit = min(100, max(1, (int)($this->requestData['limit'] ?? 50)));
        
        $activity = $this->userService->getUserActivityLogs($targetUserId, $limit);
        
        Response::success($activity, 'Activity logs retrieved successfully');
    }

    private function deleteUser() {
        $adminId = $this->requireAdmin();
        
        // Debug: Log request data
        error_log('DELETE User Request Data: ' . print_r($this->requestData, true));
        error_log('GET params: ' . print_r($_GET, true));
        
        $userId = (int)($this->requestData['user_id'] ?? $_GET['user_id'] ?? 0);
        
        error_log('Parsed user_id: ' . $userId);
        
        if (!$userId || $userId <= 0) {
            error_log('Invalid user ID provided: ' . ($this->requestData['user_id'] ?? 'not set'));
            Response::error('Invalid user ID. Received: ' . ($this->requestData['user_id'] ?? 'none'), 400);
        }
        
        // Don't allow deleting yourself
        if ($userId === $adminId) {
            Response::error('You cannot delete your own account', 400);
        }
        
        // Get user info before deletion
        $userModel = new User();
        $user = $userModel->findById($userId);
        
        if (!$user) {
            Response::error('User not found', 404);
        }
        
        // Create a fresh database connection
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../../config/database.php';
        }
        
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            Response::serverError('Database connection failed');
        }
        
        try {
            // Execute DELETE using exec() for immediate execution
            $userIdSafe = (int)$userId;
            $deleteQuery = "DELETE FROM users WHERE id = $userIdSafe";
            $affected = $db->exec($deleteQuery);
            
            // Verify deletion
            $verifyStmt = $db->prepare("SELECT COUNT(*) as cnt FROM users WHERE id = ?");
            $verifyStmt->execute([$userId]);
            $verifyResult = $verifyStmt->fetch();
            $userCount = (int)$verifyResult['cnt'];
            
            if ($userCount > 0) {
                // Still exists - check for foreign key constraints
                $fkCheck = $db->query("
                    SELECT 
                        (SELECT COUNT(*) FROM applications WHERE user_id = $userIdSafe) as app_count,
                        (SELECT COUNT(*) FROM positions WHERE created_by = $userIdSafe) as pos_count,
                        (SELECT COUNT(*) FROM user_experience WHERE user_id = $userIdSafe) as exp_count
                ")->fetch();
                
                $message = 'Failed to delete user. The user is referenced by other records. ' .
                    'Applications: ' . $fkCheck['app_count'] . ', Positions: ' . $fkCheck['pos_count'] . 
                    ', Experience records: ' . $fkCheck['exp_count'];
                
                Response::error($message, 409);
            } else {
                // Successfully deleted
                error_log("DELETE SUCCESS via API: User ID $userId deleted");
                Response::success(['deleted_user_id' => $userId], 'User deleted successfully');
            }
            
        } catch (PDOException $e) {
            error_log('User deletion PDO error: ' . $e->getMessage());
            
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            
            if ($errorCode == '23000' || strpos($errorMessage, 'foreign key') !== false || 
                strpos($errorMessage, '1451') !== false) {
                Response::error('Cannot delete user. The user is referenced by other records.', 409);
            } else {
                Response::serverError('Database error: ' . htmlspecialchars($errorMessage));
            }
        } catch (Exception $e) {
            error_log('User deletion error: ' . $e->getMessage());
            Response::serverError('An error occurred while deleting the user');
        }
    }
}

$controller = new UsersController();
$controller->handle();
