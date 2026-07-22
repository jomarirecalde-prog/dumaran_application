<?php
/**
 * Application Service Class
 * Handles business logic for application-related operations
 */

require_once __DIR__ . '/../models.php';
require_once __DIR__ . '/EmailService.php';
require_once __DIR__ . '/RequirementValidator.php';

class ApplicationService {
    private $applicationModel;
    private $positionModel;
    private $userModel;
    private $documentModel;
    private $emailService;
    private $requirementValidator;

    public function __construct() {
        $this->applicationModel = new Application();
        $this->positionModel = new Position();
        $this->userModel = new User();
        $this->documentModel = new ApplicationDocument();
        $this->emailService = new EmailService();
        $this->requirementValidator = new RequirementValidator();
    }

    /**
     * Submit a new application
     */
    public function submitApplication($userId, $positionId, $data, $files = []) {
        // Validate position
        $position = $this->positionModel->findById($positionId);
        
        if (!$position) {
            throw new ValidationException('Position not found');
        }
        
        if (!$position['is_active']) {
            throw new ValidationException('This position is no longer active');
        }
        
        // Check deadline
        if ($position['deadline'] && strtotime($position['deadline']) < time()) {
            throw new ValidationException('Application deadline has passed');
        }
        
        // Check if already applied
        $existingApplication = $this->applicationModel->findByUserAndPosition($userId, $positionId);
        if ($existingApplication) {
            throw new ValidationException('You have already applied for this position');
        }
        
        // Check max applications limit
        if ($position['max_applications']) {
            $applicationCount = $this->positionModel->getApplicationCount($positionId);
            if ($applicationCount >= $position['max_applications']) {
                throw new ValidationException('Maximum number of applications reached for this position');
            }
        }
        
        // Validate all requirements comprehensively
        $validation = $this->requirementValidator->validateRequirements($userId, $positionId);
        $totalExperience = $this->userModel->getTotalExperience($userId);
        
        // Create application
        $applicationData = [
            'user_id' => $userId,
            'position_id' => $positionId,
            'cover_letter' => $data['cover_letter'] ?? null,
            'resume_file' => $data['resume_file'] ?? null,
            'total_experience_years' => $totalExperience,
            'meets_requirements' => $validation['meets_requirements']
        ];
        
        $result = $this->applicationModel->create($applicationData);
        
        if (!$result) {
            throw new Exception('Failed to submit application');
        }
        
        // Get the created application
        $application = $this->applicationModel->findByUserAndPosition($userId, $positionId);
        
        // Log activity
        $this->userModel->logActivity(
            $userId,
            'submit_application',
            "Applied for position: {$position['title']}"
        );
        
        // Send confirmation email to applicant
        try {
            $user = $this->userModel->findById($userId);
            $this->emailService->sendApplicationSubmitted($user, $position, $application);
        } catch (Exception $e) {
            // Log error but don't fail the application submission
            error_log("Failed to send application confirmation email: " . $e->getMessage());
        }
        
        // Send notification to admins
        try {
            $admins = $this->userModel->findAll('role = ?', [ROLE_ADMIN]);
            foreach ($admins as $admin) {
                $this->emailService->sendNewApplicationNotification($admin, $user, $position, $application);
            }
        } catch (Exception $e) {
            // Log error but don't fail the application submission
            error_log("Failed to send admin notification email: " . $e->getMessage());
        }
        
        // Create in-app notifications for all admins
        try {
            $notificationModel = new Notification();
            $notificationModel->notifyAllAdmins(
                'application_submitted',
                'New Application Submitted',
                "{$user['full_name']} has submitted an application for {$position['title']}",
                $application['id'],
                'application'
            );
        } catch (Exception $e) {
            // Log error but don't fail the application submission
            error_log("Failed to create admin notifications: " . $e->getMessage());
        }
        
        return $application;
    }

    /**
     * Update application status (admin only)
     */
    public function updateApplicationStatus($applicationId, $status, $adminId) {
        if (!in_array($status, [STATUS_PENDING, STATUS_APPROVED, STATUS_REJECTED])) {
            throw new ValidationException('Invalid status');
        }
        
        $application = $this->applicationModel->findById($applicationId);
        
        if (!$application) {
            throw new Exception('Application not found');
        }
        
        // Update status
        $result = $this->applicationModel->update($applicationId, ['status' => $status]);
        
        if (!$result) {
            throw new Exception('Failed to update application status');
        }
        
        // Get updated application with details
        $updatedApp = $this->applicationModel->getApplicationsWithDetails('a.id = ?', [$applicationId])[0] ?? null;
        
        // Log activity
        $this->userModel->logActivity(
            $adminId,
            'update_application_status',
            "Changed application #{$applicationId} status to {$status}"
        );
        
        // Send email notification to applicant if status changed to approved or rejected
        if ($status === STATUS_APPROVED || $status === STATUS_REJECTED) {
            try {
                $user = $this->userModel->findById($application['user_id']);
                $position = $this->positionModel->findById($application['position_id']);
                $this->emailService->sendApplicationStatusUpdate($user, $position, $updatedApp);
            } catch (Exception $e) {
                // Log error but don't fail the status update
                error_log("Failed to send application status update email: " . $e->getMessage());
            }
        }
        
        return $updatedApp;
    }

    /**
     * Get application details
     */
    public function getApplicationDetails($applicationId, $userId = null, $isAdmin = false) {
        $application = $this->applicationModel->findById($applicationId);
        
        if (!$application) {
            throw new Exception('Application not found');
        }
        
        // Check access permissions
        if (!$isAdmin && $userId && $application['user_id'] != $userId) {
            throw new Exception('Access denied');
        }
        
        // Get full application details
        $fullApplication = $this->applicationModel->getApplicationsWithDetails('a.id = ?', [$applicationId])[0] ?? null;
        
        // Get documents
        $fullApplication['documents'] = $this->documentModel->getApplicationDocuments($applicationId);
        
        return $fullApplication;
    }

    /**
     * Get user applications
     */
    public function getUserApplications($userId, $filters = []) {
        $applications = $this->applicationModel->getUserApplications($userId);
        
        // Apply filters
        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $applications = array_filter($applications, function($app) use ($filters) {
                return $app['status'] === $filters['status'];
            });
        }
        
        if (isset($filters['position_id'])) {
            $applications = array_filter($applications, function($app) use ($filters) {
                return $app['position_id'] == $filters['position_id'];
            });
        }
        
        return array_values($applications);
    }

    /**
     * Get all applications (admin only)
     */
    public function getAllApplications($filters = []) {
        $where = '';
        $params = [];
        
        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $where = 'a.status = ?';
            $params[] = $filters['status'];
        }
        
        if (isset($filters['position_id']) && $filters['position_id'] !== 'all') {
            $where .= ($where ? ' AND ' : '') . 'a.position_id = ?';
            $params[] = $filters['position_id'];
        }
        
        if (isset($filters['meets_requirements'])) {
            $where .= ($where ? ' AND ' : '') . 'a.meets_requirements = ?';
            $params[] = $filters['meets_requirements'];
        }
        
        if (isset($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $where .= ($where ? ' AND ' : '') . '(u.full_name LIKE ? OR u.email LIKE ? OR p.title LIKE ?)';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        return $this->applicationModel->getApplicationsWithDetails($where, $params);
    }

    /**
     * Delete application
     */
    public function deleteApplication($applicationId, $userId, $isAdmin = false) {
        $application = $this->applicationModel->findById($applicationId);
        
        if (!$application) {
            throw new Exception('Application not found');
        }
        
        // Check permissions
        if (!$isAdmin && $application['user_id'] != $userId) {
            throw new Exception('Access denied');
        }
        
        // Only allow deletion of pending applications
        if ($application['status'] !== STATUS_PENDING) {
            throw new ValidationException('Can only delete pending applications');
        }
        
        // Delete documents first
        $documents = $this->documentModel->getApplicationDocuments($applicationId);
        foreach ($documents as $doc) {
            $filePath = __DIR__ . '/../../applicant/pages/uploads/documents/' . $doc['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        $this->documentModel->deleteByApplicationId($applicationId);
        
        // Delete resume if exists
        if ($application['resume_file']) {
            $resumePath = __DIR__ . '/../../applicant/pages/uploads/resumes/' . $application['resume_file'];
            if (file_exists($resumePath)) {
                unlink($resumePath);
            }
        }
        
        // Delete application
        $result = $this->applicationModel->delete($applicationId);
        
        if (!$result) {
            throw new Exception('Failed to delete application');
        }
        
        // Log activity
        $this->userModel->logActivity(
            $userId,
            'delete_application',
            "Deleted application #{$applicationId}"
        );
        
        return true;
    }

    /**
     * Get application statistics
     */
    public function getApplicationStats($positionId = null) {
        $stats = [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'meets_requirements' => 0,
            'avg_experience' => 0
        ];
        
        $where = $positionId ? 'a.position_id = ?' : '';
        $params = $positionId ? [$positionId] : [];
        
        $applications = $this->applicationModel->getApplicationsWithDetails($where, $params);
        
        $stats['total'] = count($applications);
        
        $totalExp = 0;
        foreach ($applications as $app) {
            $stats[$app['status']]++;
            if ($app['meets_requirements']) {
                $stats['meets_requirements']++;
            }
            $totalExp += $app['total_experience_years'];
        }
        
        if ($stats['total'] > 0) {
            $stats['avg_experience'] = round($totalExp / $stats['total'], 2);
        }
        
        return $stats;
    }

    /**
     * Withdraw application (user only)
     */
    public function withdrawApplication($applicationId, $userId) {
        $application = $this->applicationModel->findById($applicationId);
        
        if (!$application) {
            throw new Exception('Application not found');
        }
        
        if ($application['user_id'] != $userId) {
            throw new Exception('Access denied');
        }
        
        if ($application['status'] !== STATUS_PENDING) {
            throw new ValidationException('Can only withdraw pending applications');
        }
        
        // Update status to withdrawn (we can add this status to the database)
        // For now, we'll just delete it
        return $this->deleteApplication($applicationId, $userId, false);
    }
}
