<?php
/**
 * Position Service Class
 * Handles business logic for position-related operations
 */

require_once __DIR__ . '/../models.php';

class PositionService {
    private $positionModel;
    private $applicationModel;
    private $userModel;

    public function __construct() {
        $this->positionModel = new Position();
        $this->applicationModel = new Application();
        $this->userModel = new User();
    }

    /**
     * Create a new position
     */
    public function createPosition($data, $createdBy) {
        // Validate required fields
        $errors = [];
        
        if (empty($data['title'])) {
            $errors[] = 'Title is required';
        }
        
        if (empty($data['description'])) {
            $errors[] = 'Description is required';
        }
        
        if (!empty($errors)) {
            throw new ValidationException(implode(', ', $errors));
        }
        
        // Validate deadline date
        if (!empty($data['deadline'])) {
            $deadline = strtotime($data['deadline']);
            if ($deadline < time()) {
                throw new ValidationException('Deadline must be in the future');
            }
        }
        
        // Normalize salary range currency
        if (isset($data['salary_range'])) {
            $data['salary_range'] = $this->normalizeSalaryRange($data['salary_range']);
        }

        // Set defaults
        $data['created_by'] = $createdBy;
        $data['min_experience_years'] = $data['min_experience_years'] ?? 0;
        $data['employment_type'] = $data['employment_type'] ?? 'full-time';
        
        $result = $this->positionModel->create($data);
        
        if (!$result) {
            throw new Exception('Failed to create position');
        }
        
        // Log activity
        $this->userModel->logActivity(
            $createdBy,
            'create_position',
            "Created position: {$data['title']}"
        );
        
        return true;
    }

    /**
     * Update position
     */
    public function updatePosition($positionId, $data, $adminId) {
        $position = $this->positionModel->findById($positionId);
        
        if (!$position) {
            throw new Exception('Position not found');
        }
        
        // Validate deadline if provided
        if (!empty($data['deadline'])) {
            $deadline = strtotime($data['deadline']);
            $now = time();
            
            // Only validate future deadline for active positions
            if (isset($data['is_active']) && $data['is_active'] && $deadline < $now) {
                throw new ValidationException('Deadline must be in the future for active positions');
            }
        }
        
        if (isset($data['salary_range'])) {
            $data['salary_range'] = $this->normalizeSalaryRange($data['salary_range']);
        }

        $result = $this->positionModel->update($positionId, $data);
        
        if (!$result) {
            throw new Exception('Failed to update position');
        }
        
        // Log activity
        $this->userModel->logActivity(
            $adminId,
            'update_position',
            "Updated position: {$position['title']}"
        );
        
        return $this->positionModel->findById($positionId);
    }

    /**
     * Delete position
     */
    public function deletePosition($positionId, $adminId) {
        $position = $this->positionModel->findById($positionId);
        
        if (!$position) {
            throw new Exception('Position not found');
        }
        
        // Check if there are applications
        $applicationCount = $this->positionModel->getApplicationCount($positionId);
        
        if ($applicationCount > 0) {
            throw new ValidationException("Cannot delete position with {$applicationCount} application(s). Please archive it instead.");
        }
        
        $result = $this->positionModel->delete($positionId);
        
        if (!$result) {
            throw new Exception('Failed to delete position');
        }
        
        // Log activity
        $this->userModel->logActivity(
            $adminId,
            'delete_position',
            "Deleted position: {$position['title']}"
        );
        
        return true;
    }

    /**
     * Toggle position active status
     */
    public function togglePositionStatus($positionId, $adminId) {
        $position = $this->positionModel->findById($positionId);
        
        if (!$position) {
            throw new Exception('Position not found');
        }
        
        $newStatus = !$position['is_active'];
        
        // If activating, check deadline
        if ($newStatus && $position['deadline']) {
            if (strtotime($position['deadline']) < time()) {
                throw new ValidationException('Cannot activate position with past deadline');
            }
        }
        
        $result = $this->positionModel->update($positionId, ['is_active' => $newStatus]);
        
        if (!$result) {
            throw new Exception('Failed to update position status');
        }
        
        // Log activity
        $this->userModel->logActivity(
            $adminId,
            'toggle_position_status',
            "Changed position '{$position['title']}' status to " . ($newStatus ? 'active' : 'inactive')
        );
        
        return $newStatus;
    }

    /**
     * Get all positions
     */
    public function getAllPositions($filters = []) {
        $where = '';
        $params = [];
        
        if (isset($filters['is_active'])) {
            $where = 'is_active = ?';
            $params[] = $filters['is_active'];
        }
        
        if (isset($filters['employment_type'])) {
            $where .= ($where ? ' AND ' : '') . 'employment_type = ?';
            $params[] = $filters['employment_type'];
        }
        
        if (isset($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $where .= ($where ? ' AND ' : '') . '(title LIKE ? OR description LIKE ? OR location LIKE ?)';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $positions = $this->positionModel->findAll($where, $params);
        
        // Add application count to each position
        foreach ($positions as &$position) {
            $position['application_count'] = $this->positionModel->getApplicationCount($position['id']);
            
            // Check if deadline passed
            if ($position['deadline']) {
                $position['is_expired'] = strtotime($position['deadline']) < time();
            } else {
                $position['is_expired'] = false;
            }
        }
        
        return $positions;
    }

    /**
     * Get active positions (for applicants)
     */
    public function getActivePositions($userId = null) {
        $positions = $this->positionModel->getActivePositions();
        
        // Add additional info
        foreach ($positions as &$position) {
            $position['application_count'] = $this->positionModel->getApplicationCount($position['id']);
            
            // Check if user already applied
            if ($userId) {
                $position['has_applied'] = (bool)$this->applicationModel->findByUserAndPosition($userId, $position['id']);
            }
            
            // Calculate days remaining
            if ($position['deadline']) {
                $daysRemaining = ceil((strtotime($position['deadline']) - time()) / 86400);
                $position['days_remaining'] = max(0, $daysRemaining);
            }
            
            // Check if accepting applications
            $position['is_accepting'] = true;
            
            if ($position['max_applications']) {
                $position['is_accepting'] = $position['application_count'] < $position['max_applications'];
            }
        }
        
        return $positions;
    }

    /**
     * Get position details
     */
    public function getPositionDetails($positionId, $userId = null) {
        $position = $this->positionModel->findById($positionId);
        
        if (!$position) {
            throw new Exception('Position not found');
        }
        
        // Add additional info
        $position['application_count'] = $this->positionModel->getApplicationCount($positionId);
        
        if ($userId) {
            $position['has_applied'] = (bool)$this->applicationModel->findByUserAndPosition($userId, $positionId);
            
            // Get user's experience
            $position['user_experience'] = $this->userModel->getTotalExperience($userId);
            $position['meets_requirements'] = $position['user_experience'] >= $position['min_experience_years'];
        }
        
        // Calculate days remaining
        if ($position['deadline']) {
            $daysRemaining = ceil((strtotime($position['deadline']) - time()) / 86400);
            $position['days_remaining'] = max(0, $daysRemaining);
            $position['is_expired'] = $daysRemaining < 0;
        }
        
        // Check if accepting applications
        $position['is_accepting'] = $position['is_active'];
        
        if ($position['deadline'] && strtotime($position['deadline']) < time()) {
            $position['is_accepting'] = false;
        }
        
        if ($position['max_applications'] && $position['application_count'] >= $position['max_applications']) {
            $position['is_accepting'] = false;
        }
        
        return $position;
    }

    /**
     * Get position statistics
     */
    public function getPositionStats($positionId = null) {
        $stats = [];
        
        if ($positionId) {
            // Stats for specific position
            $position = $this->positionModel->findById($positionId);
            
            if (!$position) {
                throw new Exception('Position not found');
            }
            
            $stats = [
                'total_applications' => $this->positionModel->getApplicationCount($positionId),
                'pending' => count($this->applicationModel->findAll('position_id = ? AND status = ?', [$positionId, STATUS_PENDING])),
                'approved' => count($this->applicationModel->findAll('position_id = ? AND status = ?', [$positionId, STATUS_APPROVED])),
                'rejected' => count($this->applicationModel->findAll('position_id = ? AND status = ?', [$positionId, STATUS_REJECTED])),
            ];
        } else {
            // Overall stats
            $stats = [
                'total_positions' => count($this->positionModel->findAll()),
                'active_positions' => count($this->positionModel->findAll('is_active = ?', [1])),
                'inactive_positions' => count($this->positionModel->findAll('is_active = ?', [0])),
                'total_applications' => count($this->applicationModel->findAll()),
            ];
        }
        
        return $stats;
    }

    /**
     * Check if position can accept applications
     */
    public function canAcceptApplications($positionId) {
        $position = $this->getPositionDetails($positionId);
        return $position['is_accepting'];
    }

    /**
     * Ensure salary range values consistently reference Philippine Peso
     */
    private function normalizeSalaryRange($value) {
        if (!is_string($value)) {
            return $value;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return '';
        }

        if (!preg_match('/(₱|PHP|PESO)/i', $normalized)) {
            $normalized = 'PHP ' . $normalized;
        }

        return $normalized;
    }
}
