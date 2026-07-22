<?php
/**
 * File Upload Service
 * Handles secure file uploads with validation
 */

class FileUploadService {
    private $uploadPath;
    private $allowedTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'zip' => 'application/zip'
    ];
    private $maxFileSize = 5242880; // 5MB default
    private $errors = [];

    public function __construct($uploadPath = null) {
        $this->uploadPath = $uploadPath ?? __DIR__ . '/../../uploads/';
        
        // Ensure upload directory exists
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }

    /**
     * Set maximum file size
     */
    public function setMaxFileSize($bytes) {
        $this->maxFileSize = $bytes;
        return $this;
    }

    /**
     * Set allowed file types
     */
    public function setAllowedTypes($types) {
        $this->allowedTypes = $types;
        return $this;
    }

    /**
     * Upload single file
     */
    public function upload($file, $subDir = '', $customName = null) {
        // Validate file
        if (!$this->validateFile($file)) {
            return false;
        }

        // Normalize path - ensure no double slashes
        $basePath = rtrim($this->uploadPath, '/\\');
        $subDir = $subDir ? '/' . trim($subDir, '/\\') : '';
        $uploadDir = $basePath . $subDir;
        
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                $this->errors[] = 'Failed to create upload directory';
                return false;
            }
        }

        // Generate secure filename
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($customName) {
            $fileName = $customName . '.' . $extension;
        } else {
            $fileName = $this->generateSecureFilename($file['name']);
        }

        $uploadPath = $uploadDir . '/' . $fileName;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            // Set proper permissions
            chmod($uploadPath, 0644);
            
            return [
                'success' => true,
                'filename' => $fileName,
                'path' => $uploadPath,
                'size' => $file['size'],
                'type' => $file['type'],
                'extension' => $extension
            ];
        }

        $this->errors[] = 'Failed to move uploaded file';
        return false;
    }

    /**
     * Upload multiple files
     */
    public function uploadMultiple($files, $subDir = '') {
        $results = [];
        
        // Handle different file array structures
        if (isset($files['name']) && is_array($files['name'])) {
            // Multiple files in single input
            $fileCount = count($files['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
                
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $result = $this->upload($file, $subDir);
                    if ($result) {
                        $results[] = $result;
                    }
                }
            }
        } else {
            // Multiple file inputs
            foreach ($files as $file) {
                if (isset($file['error']) && $file['error'] === UPLOAD_ERR_OK) {
                    $result = $this->upload($file, $subDir);
                    if ($result) {
                        $results[] = $result;
                    }
                }
            }
        }
        
        return $results;
    }

    /**
     * Validate file upload
     */
    private function validateFile($file) {
        $this->errors = [];

        // Check for upload errors
        if (!isset($file['error']) || is_array($file['error'])) {
            $this->errors[] = 'Invalid file upload';
            return false;
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $this->errors[] = 'File size exceeds limit';
                return false;
            case UPLOAD_ERR_NO_FILE:
                $this->errors[] = 'No file was uploaded';
                return false;
            default:
                $this->errors[] = 'Unknown upload error';
                return false;
        }

        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            $maxSizeMB = $this->maxFileSize / (1024 * 1024);
            $this->errors[] = "File size exceeds maximum of {$maxSizeMB}MB";
            return false;
        }

        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!array_key_exists($extension, $this->allowedTypes)) {
            $this->errors[] = 'File type not allowed. Allowed types: ' . implode(', ', array_keys($this->allowedTypes));
            return false;
        }

        // Verify MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimeTypes = array_values($this->allowedTypes);
        if (!in_array($mimeType, $allowedMimeTypes)) {
            $this->errors[] = 'Invalid file type';
            return false;
        }

        // Additional security checks
        if (!$this->isSecureFile($file['tmp_name'], $extension)) {
            $this->errors[] = 'File failed security check';
            return false;
        }

        return true;
    }

    /**
     * Security check for file
     */
    private function isSecureFile($filePath, $extension) {
        // Check for PHP code in uploaded files
        if (in_array($extension, ['php', 'phtml', 'php3', 'php4', 'php5', 'phar'])) {
            return false;
        }

        // Read first few bytes to check for PHP tags
        $handle = fopen($filePath, 'r');
        $contents = fread($handle, 1024);
        fclose($handle);

        if (stripos($contents, '<?php') !== false || stripos($contents, '<?=') !== false) {
            return false;
        }

        return true;
    }

    /**
     * Generate secure filename
     */
    private function generateSecureFilename($originalName) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Sanitize basename
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
        $basename = substr($basename, 0, 50); // Limit length
        
        // Add timestamp and random string
        $uniqueId = time() . '_' . bin2hex(random_bytes(8));
        
        return $basename . '_' . $uniqueId . '.' . $extension;
    }

    /**
     * Delete file
     */
    public function delete($filename, $subDir = '') {
        $filePath = $this->uploadPath . $subDir . '/' . $filename;
        
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        
        return false;
    }

    /**
     * Get file info
     */
    public function getFileInfo($filename, $subDir = '') {
        $filePath = $this->uploadPath . $subDir . '/' . $filename;
        
        if (!file_exists($filePath)) {
            return false;
        }
        
        return [
            'name' => $filename,
            'path' => $filePath,
            'size' => filesize($filePath),
            'modified' => filemtime($filePath),
            'extension' => pathinfo($filename, PATHINFO_EXTENSION)
        ];
    }

    /**
     * Get errors
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Get last error
     */
    public function getLastError() {
        return end($this->errors) ?: null;
    }
}
