<?php
require_once '../../config/config.php';
require_once '../../includes/models.php';

requireAuth();

// Only applicants can accept the waiver
if (!hasRole(ROLE_APPLICANT)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

try {
    $userModel = new User();
    $userId = $_SESSION['user_id'];
    
    // Update user to mark privacy waiver as accepted
    $updateData = [
        'data_privacy_accepted_at' => date('Y-m-d H:i:s')
    ];
    
    // Update email if provided
    if (!empty($_POST['email'] ?? '')) {
        $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
        if ($email) {
            $updateData['email'] = $email;
        }
    }
    
    if ($userModel->update($userId, $updateData)) {
        // Log the activity
        $userModel->logActivity(
            $userId,
            'privacy_waiver_accepted',
            'User accepted the Data Privacy Waiver'
        );
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Data Privacy Waiver accepted successfully'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to update waiver acceptance']);
    }
} catch (Exception $e) {
    error_log("Privacy waiver acceptance error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>

