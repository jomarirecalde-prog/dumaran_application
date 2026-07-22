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

$applicationDocumentModel = new UserApplicationDocument();
$userId = $_SESSION['user_id'];
$docId = $_GET['id'] ?? 0;

// Get document to verify ownership and get file path
$document = $applicationDocumentModel->findById($docId);

if (!$document || $document['user_id'] != $userId) {
    header('Location: ../pages/profile.php?error=document_not_found');
    exit();
}

// Delete the physical file
if (!empty($document['file_path'])) {
    $filePath = __DIR__ . '/../pages/uploads/documents/' . $document['file_path'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }
}

// Delete the database record
if ($applicationDocumentModel->deleteById($docId, $userId)) {
    header('Location: ../pages/profile.php?success=document_deleted');
} else {
    header('Location: ../pages/profile.php?error=delete_failed');
}
exit();
?>

