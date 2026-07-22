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

$trainingCertificateModel = new TrainingCertificate();
$userId = $_SESSION['user_id'];
$certId = $_GET['id'] ?? 0;

// Get certificate to verify ownership and get file path
$certificate = $trainingCertificateModel->findById($certId);

if (!$certificate || $certificate['user_id'] != $userId) {
    header('Location: ../pages/profile.php?error=certificate_not_found');
    exit();
}

// Delete the physical file
if (!empty($certificate['file_path'])) {
    $filePath = __DIR__ . '/../pages/uploads/documents/' . $certificate['file_path'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }
}

// Delete the database record
if ($trainingCertificateModel->deleteById($certId, $userId)) {
    header('Location: ../pages/profile.php?success=certificate_deleted');
} else {
    header('Location: ../pages/profile.php?error=delete_failed');
}
exit();
?>

