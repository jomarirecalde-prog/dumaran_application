<?php
/**
 * Applications API Controller
 * Handles API requests for applications
 */

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../../includes/services/ApplicationService.php';
require_once __DIR__ . '/../../includes/services/FileUploadService.php';

class ApplicationsController extends ApiController {
    private $applicationService;
    private $fileService;

    public function __construct() {
        parent::__construct();
        $this->applicationService = new ApplicationService();
        $this->fileService = new FileUploadService();
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

    /**
     * Handle GET requests
     */
    private function handleGet($action) {
        switch ($action) {
            case 'index':
                $this->getApplications();
                break;
            case 'show':
                $this->getApplication();
                break;
            case 'stats':
                $this->getStats();
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
            case 'submit':
                $this->submitApplication();
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
            case 'update-status':
                $this->updateStatus();
                break;
            default:
                Response::notFound('Action not found');
        }
    }

    /**
     * Handle DELETE requests
     */
    private function handleDelete($action) {
        $this->verifyCsrf();
        
        switch ($action) {
            case 'delete':
                $this->deleteApplication();
                break;
            default:
                Response::notFound('Action not found');
        }
    }

    /**
     * Get applications list
     */
    private function getApplications() {
        $userId = $this->requireAuth();
        $isAdmin = hasRole(ROLE_ADMIN);
        
        $filters = [
            'status' => $this->requestData['status'] ?? null,
            'position_id' => $this->requestData['position_id'] ?? null,
            'search' => $this->requestData['search'] ?? null,
        ];
        
        if ($isAdmin) {
            $applications = $this->applicationService->getAllApplications($filters);
        } else {
            $applications = $this->applicationService->getUserApplications($userId, $filters);
        }
        
        // Pagination
        $pagination = $this->getPagination();
        $total = count($applications);
        $applications = array_slice($applications, $pagination['offset'], $pagination['perPage']);
        
        Response::paginated(
            $applications,
            $total,
            $pagination['page'],
            $pagination['perPage'],
            'Applications retrieved successfully'
        );
    }

    /**
     * Get single application
     */
    private function getApplication() {
        $userId = $this->requireAuth();
        $isAdmin = hasRole(ROLE_ADMIN);
        
        $applicationId = (int)($this->requestData['id'] ?? 0);
        
        if (!$applicationId) {
            Response::error('Application ID is required', 400);
        }
        
        $application = $this->applicationService->getApplicationDetails($applicationId, $userId, $isAdmin);
        
        Response::success($application, 'Application retrieved successfully');
    }

    /**
     * Submit new application
     */
    private function submitApplication() {
        $userId = $this->requireAuth();
        
        // Validate input
        $validator = new Validator($this->requestData);
        $validator->required('position_id')
                  ->integer('position_id');
        
        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }
        
        $positionId = (int)$this->requestData['position_id'];
        
        $data = [
            'cover_letter' => $this->requestData['cover_letter'] ?? null,
        ];
        
        // Handle file upload
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $this->fileService->setAllowedTypes([
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ]);
            
            $upload = $this->fileService->upload($_FILES['resume'], 'resumes/');
            
            if ($upload) {
                $data['resume_file'] = $upload['filename'];
            } else {
                Response::error($this->fileService->getLastError(), 400);
            }
        }
        
        $application = $this->applicationService->submitApplication($userId, $positionId, $data);
        
        Response::success($application, 'Application submitted successfully! A confirmation email has been sent to your email address.', 201);
    }

    /**
     * Update application status (admin only)
     */
    private function updateStatus() {
        $adminId = $this->requireAdmin();
        
        // Validate input
        $validator = new Validator($this->requestData);
        $validator->required('id')
                  ->integer('id')
                  ->required('status')
                  ->in('status', [STATUS_PENDING, STATUS_APPROVED, STATUS_REJECTED]);
        
        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }
        
        $applicationId = (int)$this->requestData['id'];
        $status = $this->requestData['status'];
        
        $application = $this->applicationService->updateApplicationStatus($applicationId, $status, $adminId);
        
        // Add email confirmation to response message
        if ($status === STATUS_APPROVED || $status === STATUS_REJECTED) {
            $statusText = $status === STATUS_APPROVED ? 'approved' : 'rejected';
            Response::success($application, "Application status updated to {$statusText} and confirmation email has been sent to the applicant.");
        } else {
            Response::success($application, 'Application status updated successfully.');
        }
    }

    /**
     * Delete application
     */
    private function deleteApplication() {
        $userId = $this->requireAuth();
        $isAdmin = hasRole(ROLE_ADMIN);
        
        $applicationId = (int)($this->requestData['id'] ?? 0);
        
        if (!$applicationId) {
            Response::error('Application ID is required', 400);
        }
        
        $this->applicationService->deleteApplication($applicationId, $userId, $isAdmin);
        
        Response::success(null, 'Application deleted successfully');
    }

    /**
     * Get application statistics
     */
    private function getStats() {
        $this->requireAuth();
        
        $positionId = isset($this->requestData['position_id']) ? (int)$this->requestData['position_id'] : null;
        
        $stats = $this->applicationService->getApplicationStats($positionId);
        
        Response::success($stats, 'Statistics retrieved successfully');
    }
}

// Initialize and handle request
$controller = new ApplicationsController();
$controller->handle();
