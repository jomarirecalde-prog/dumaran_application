<?php
/**
 * Positions API Controller
 */

require_once __DIR__ . '/controllers/ApiController.php';
require_once __DIR__ . '/../includes/services/PositionService.php';

class PositionsController extends ApiController {
    private $positionService;

    public function __construct() {
        parent::__construct();
        $this->positionService = new PositionService();
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
                $this->getPositions();
                break;
            case 'show':
                $this->getPosition();
                break;
            case 'active':
                $this->getActivePositions();
                break;
            case 'stats':
                $this->getStats();
                break;
            default:
                Response::notFound('Action not found');
        }
    }

    private function handlePost($action) {
        $this->verifyCsrf();
        
        switch ($action) {
            case 'create':
                $this->createPosition();
                break;
            default:
                Response::notFound('Action not found');
        }
    }

    private function handlePut($action) {
        $this->verifyCsrf();
        
        switch ($action) {
            case 'update':
                $this->updatePosition();
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
                $this->deletePosition();
                break;
            default:
                Response::notFound('Action not found');
        }
    }

    private function getPositions() {
        $this->requireAuth();
        
        $filters = [
            'is_active' => isset($this->requestData['is_active']) ? (bool)$this->requestData['is_active'] : null,
            'employment_type' => $this->requestData['employment_type'] ?? null,
            'search' => $this->requestData['search'] ?? null,
        ];
        
        $positions = $this->positionService->getAllPositions($filters);
        
        // Pagination
        $pagination = $this->getPagination();
        $total = count($positions);
        $positions = array_slice($positions, $pagination['offset'], $pagination['perPage']);
        
        Response::paginated(
            $positions,
            $total,
            $pagination['page'],
            $pagination['perPage'],
            'Positions retrieved successfully'
        );
    }

    private function getPosition() {
        $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
        
        $positionId = (int)($this->requestData['id'] ?? 0);
        
        if (!$positionId) {
            Response::error('Position ID is required', 400);
        }
        
        $position = $this->positionService->getPositionDetails($positionId, $userId);
        
        Response::success($position, 'Position retrieved successfully');
    }

    private function getActivePositions() {
        $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
        
        $positions = $this->positionService->getActivePositions($userId);
        
        Response::success($positions, 'Active positions retrieved successfully');
    }

    private function createPosition() {
        $adminId = $this->requireAdmin();
        
        // Validate input
        $validator = new Validator($this->requestData);
        $validator->required('title')
                  ->required('description')
                  ->integer('min_experience_years')
                  ->in('employment_type', ['full-time', 'part-time', 'contract', 'internship']);
        
        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }
        
        $this->positionService->createPosition($this->requestData, $adminId);
        
        Response::success(null, 'Position created successfully', 201);
    }

    private function updatePosition() {
        $adminId = $this->requireAdmin();
        
        // Validate input
        $validator = new Validator($this->requestData);
        $validator->required('id')->integer('id');
        
        if ($validator->fails()) {
            Response::validationError($validator->getErrors());
        }
        
        $positionId = (int)$this->requestData['id'];
        unset($this->requestData['id']);
        
        $position = $this->positionService->updatePosition($positionId, $this->requestData, $adminId);
        
        Response::success($position, 'Position updated successfully');
    }

    private function toggleStatus() {
        $adminId = $this->requireAdmin();
        
        $positionId = (int)($this->requestData['id'] ?? 0);
        
        if (!$positionId) {
            Response::error('Position ID is required', 400);
        }
        
        $newStatus = $this->positionService->togglePositionStatus($positionId, $adminId);
        
        Response::success(['is_active' => $newStatus], 'Position status updated successfully');
    }

    private function deletePosition() {
        $adminId = $this->requireAdmin();
        
        $positionId = (int)($this->requestData['id'] ?? 0);
        
        if (!$positionId) {
            Response::error('Position ID is required', 400);
        }
        
        $this->positionService->deletePosition($positionId, $adminId);
        
        Response::success(null, 'Position deleted successfully');
    }

    private function getStats() {
        $this->requireAdmin();
        
        $positionId = isset($this->requestData['position_id']) ? (int)$this->requestData['position_id'] : null;
        
        $stats = $this->positionService->getPositionStats($positionId);
        
        Response::success($stats, 'Statistics retrieved successfully');
    }
}

$controller = new PositionsController();
$controller->handle();
