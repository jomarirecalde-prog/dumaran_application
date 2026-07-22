<?php
/**
 * View Resume File
 * This file serves uploaded resume files to administrators
 * Opens in a new tab with proper content type handling
 */

require_once '../../config/config.php';
require_once '../../includes/models.php';

// Require admin authentication
requireAdmin();

// Get application ID from query parameter
$applicationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$applicationId) {
    die('Invalid application ID');
}

// Get application details
$applicationModel = new Application();
$application = $applicationModel->findById($applicationId);

if (!$application) {
    die('Application not found');
}

// Check if resume file exists
$resumeFile = $application['resume_file'];
if (!$resumeFile) {
    die('No resume file uploaded for this application');
}

// Build the file path
$filePath = __DIR__ . '/../../applicant/pages/uploads/resumes/' . $resumeFile;

// Verify file exists on server
if (!file_exists($filePath)) {
    die('Resume file not found on server');
}

// Get file extension and set appropriate content type
$fileExtension = strtolower(pathinfo($resumeFile, PATHINFO_EXTENSION));
$contentTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'txt' => 'text/plain',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png'
];

$contentType = $contentTypes[$fileExtension] ?? 'application/octet-stream';
$shouldDownload = isset($_GET['download']) && $_GET['download'] !== '0';
$disposition = $shouldDownload ? 'attachment' : 'inline';
// Set headers for inline display (will open in browser tab)
header('Content-Type: ' . $contentType);
header('Content-Disposition: ' . $disposition . '; filename="' . basename($resumeFile) . '"');
if ($shouldDownload) {
    header('Content-Transfer-Encoding: binary');
}
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');

// Output the file content
readfile($filePath);
exit();




