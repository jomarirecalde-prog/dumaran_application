<?php
/**
 * View Application Document
 * This file serves uploaded documents to administrators
 * Opens in a new tab with proper content type handling
 */

require_once '../../config/config.php';
require_once '../../includes/models.php';

// Require admin authentication
requireAdmin();

// Get document ID from query parameter
$documentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$documentId) {
    die('Invalid document ID');
}

// Get document details
$documentModel = new ApplicationDocument();
$document = $documentModel->findById($documentId);

if (!$document) {
    die('Document not found');
}

// Build the file path
$filePath = __DIR__ . '/../../applicant/pages/uploads/documents/' . $document['file_path'];

// Verify file exists on server
if (!file_exists($filePath)) {
    die('Document file not found on server');
}

// Get file extension and set appropriate content type
$fileExtension = strtolower(pathinfo($document['file_path'], PATHINFO_EXTENSION));
$contentTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'txt' => 'text/plain',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'zip' => 'application/zip'
];

$contentType = $contentTypes[$fileExtension] ?? 'application/octet-stream';
$shouldDownload = isset($_GET['download']) && $_GET['download'] !== '0';
$disposition = $shouldDownload ? 'attachment' : 'inline';
// Set headers for inline display (will open in browser tab)
header('Content-Type: ' . $contentType);
header('Content-Disposition: ' . $disposition . '; filename="' . $document['file_name'] . '"');
if ($shouldDownload) {
    header('Content-Transfer-Encoding: binary');
}
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');

// Output the file content
readfile($filePath);
exit();


