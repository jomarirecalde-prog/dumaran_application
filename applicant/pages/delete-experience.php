<?php
require_once '../../config/config.php';
requireAuth();

if (hasRole(ROLE_ADMIN)) {
    header('Location: ' . BASE_URL . 'admin/pages/dashboard.php');
    exit();
}

// Require privacy waiver acceptance
requirePrivacyWaiver();

require_once '../../includes/models.php';

$experienceModel = new Experience();
$userId = $_SESSION['user_id'];
$expId = $_GET['id'] ?? 0;

$experience = $experienceModel->findById($expId);

if (!$experience || $experience['user_id'] != $userId) {
    header('Location: ../pages/profile.php?error=experience_not_found');
    exit();
}

if ($experienceModel->delete($expId)) {
    header('Location: ../pages/profile.php?success=experience_deleted');
} else {
    header('Location: ../pages/profile.php?error=delete_failed');
}
exit();
?>
