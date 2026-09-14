<?php
require_once '../../config/config.php';
requireAuth();

// Redirect admin to admin dashboard
if (hasRole(ROLE_ADMIN)) {
    header('Location: ' . BASE_URL . 'admin/pages/dashboard.php');
    exit();
}

// Require privacy waiver acceptance
requirePrivacyWaiver();

require_once '../../includes/models.php';

$userModel = new User();
$experienceModel = new Experience();
$trainingCertificateModel = new TrainingCertificate();
$applicationDocumentModel = new UserApplicationDocument();

$userId = $_SESSION['user_id'];
$user = $userModel->findById($userId);
$allExperiences = $experienceModel->getUserExperience($userId);
$totalExperience = $userModel->getTotalExperience($userId);

// Training certificates and application documents will be loaded after POST processing
// to ensure we show the latest data after any uploads
// (see the section after POST handling block)

// Pagination setup for experiences
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 5;
$offset = ($page - 1) * $perPage;

// Calculate pagination
$totalExperiences = count($allExperiences);
$totalPages = ceil($totalExperiences / $perPage);
$experiences = array_slice($allExperiences, $offset, $perPage);

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log POST data
    error_log("=== PROFILE FORM SUBMISSION DEBUG ===");
    error_log("POST data keys: " . implode(', ', array_keys($_POST)));
    error_log("FILES data keys: " . implode(', ', array_keys($_FILES)));
    
    // Validate form submission (CSRF + Form Token)
    $validation = FormTokenManager::validateFormSubmission($_POST);
    
    if (!$validation['valid']) {
        if ($validation['error'] === 'duplicate') {
            header('Location: profile.php?info=Profile update already processed');
            exit();
        }
        $error = $validation['error'];
        error_log("Form validation failed: " . $error);
    } else {
        $fullName = sanitize(strtoupper(trim($_POST['full_name'] ?? '')));
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $age = isset($_POST['age']) && !empty($_POST['age']) ? (int)$_POST['age'] : null;
        $sex = sanitize($_POST['sex'] ?? '');
        $civilStatus = sanitize($_POST['civil_status'] ?? '');
        $highestEducationalAttainment = sanitize($_POST['highest_educational_attainment'] ?? '');
        $bachelorsDegree = sanitize($_POST['bachelors_degree'] ?? '');
        $mastersDegree = sanitize($_POST['masters_degree'] ?? '');
        $doctorateDegree = sanitize($_POST['doctorate_degree'] ?? '');
        $applicationType = isset($_POST['application_type']) ? $_POST['application_type'] : [];
        $ipcrRating = sanitize($_POST['ipcr_rating'] ?? '');
        $peRating = sanitize($_POST['pe_rating'] ?? '');
        $positionAppliedFor = sanitize($_POST['position_applied_for'] ?? '');
        $trainingPrograms = sanitize($_POST['training_programs'] ?? '');
        $careerServiceEligibilities = isset($_POST['career_service_eligibilities']) ? $_POST['career_service_eligibilities'] : [];
        $careerServiceEligibilitiesOther = sanitize($_POST['career_service_eligibilities_other'] ?? '');
        $specialNeeds = sanitize($_POST['special_needs'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $deleteIpcrFile = isset($_POST['delete_ipcr_file']) && $_POST['delete_ipcr_file'] === '1';
        $deletePeFile = isset($_POST['delete_pe_file']) && $_POST['delete_pe_file'] === '1';
        
        // Validate
        $errors = [];
    
    if (empty($fullName)) {
        $errors[] = "Full name is required.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if email is taken by another user
        $existingUser = $userModel->findByEmail($email);
        if ($existingUser && $existingUser['id'] != $userId) {
            $errors[] = "Email is already taken.";
        }
    }
    
    if (empty($address)) {
        $errors[] = "Address is required.";
    }
    
    if (empty($age)) {
        $errors[] = "Age is required.";
    }
    
    if (empty($sex)) {
        $errors[] = "Sex is required.";
    }
    
    // If password is provided, validate it
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters.";
        }
        if ($password !== $confirmPassword) {
            $errors[] = "Passwords do not match.";
        }
    }
    
    if (empty($errors)) {
        // Handle IPCR file upload
        // Initialize variables at the start
        $ipcrFilePath = $user['ipcr_file_path'] ?? null;
        $ipcrFileUpdated = false;
        $peFilePath = $user['pe_file_path'] ?? null;
        $peFileUpdated = false;
        
        // Debug: Log what files were submitted
        error_log("=== FILE UPLOAD DEBUG START ===");
        error_log("ipcr_file in FILES: " . (isset($_FILES['ipcr_file']) ? 'YES' : 'NO'));
        if (isset($_FILES['ipcr_file'])) {
            error_log("ipcr_file error code: " . $_FILES['ipcr_file']['error']);
            error_log("ipcr_file name: " . ($_FILES['ipcr_file']['name'] ?? 'not set'));
            error_log("ipcr_file size: " . ($_FILES['ipcr_file']['size'] ?? 'not set'));
        }
        error_log("pe_file in FILES: " . (isset($_FILES['pe_file']) ? 'YES' : 'NO'));
        if (isset($_FILES['pe_file'])) {
            error_log("pe_file error code: " . $_FILES['pe_file']['error']);
            error_log("pe_file name: " . ($_FILES['pe_file']['name'] ?? 'not set'));
            error_log("pe_file size: " . ($_FILES['pe_file']['size'] ?? 'not set'));
        }
        
        if ($deleteIpcrFile && $ipcrFilePath) {
            // Delete existing file
            $oldFilePath = __DIR__ . '/uploads/documents/' . $ipcrFilePath;
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
            $ipcrFilePath = null;
            $ipcrFileUpdated = true;
        }
        
        // Process IPCR file upload - check if file was actually uploaded
        if (isset($_FILES['ipcr_file']) && $_FILES['ipcr_file']['error'] === UPLOAD_ERR_OK && !empty($_FILES['ipcr_file']['tmp_name']) && is_uploaded_file($_FILES['ipcr_file']['tmp_name'])) {
            // Validate file is PDF
            $extension = strtolower(pathinfo($_FILES['ipcr_file']['name'], PATHINFO_EXTENSION));
            if ($extension !== 'pdf') {
                $errors[] = "IPCR file must be a PDF file.";
            } else {
                // Validate MIME type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $_FILES['ipcr_file']['tmp_name']);
                finfo_close($finfo);
                
                if ($mimeType !== 'application/pdf') {
                    $errors[] = "IPCR file must be a valid PDF file.";
                } else {
                    // Generate filename according to convention: SURNAME_FIRSTNAME_IPCR_WPUHRMDO.pdf
                    $nameParts = explode(',', $fullName);
                    if (count($nameParts) >= 2) {
                        $surname = trim(strtoupper($nameParts[0]));
                        $firstName = trim(strtoupper($nameParts[1]));
                        $firstName = str_replace(' ', '_', $firstName); // Replace spaces with underscores
                        $customFileName = $surname . '_' . $firstName . '_IPCR_WPUHRMDO';
                        
                        // Delete old file if exists
                        if ($ipcrFilePath) {
                            $oldFilePath = __DIR__ . '/uploads/documents/' . $ipcrFilePath;
                            if (file_exists($oldFilePath)) {
                                unlink($oldFilePath);
                            }
                        }
                        
                        // Upload new file - using direct approach like apply.php
                        $uploadDir = __DIR__ . '/uploads/documents/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        $fileName = $customFileName . '.pdf';
                        $uploadPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['ipcr_file']['tmp_name'], $uploadPath)) {
                            // Verify file was actually saved
                            if (file_exists($uploadPath)) {
                                $ipcrFilePath = $fileName;
                                $ipcrFileUpdated = true;
                                error_log("IPCR file uploaded successfully: $fileName");
                            } else {
                                $errors[] = "IPCR file upload failed: File was not saved to disk.";
                            }
                        } else {
                            $errors[] = "Failed to upload IPCR file. Please check file permissions and try again.";
                            error_log("IPCR file upload failed: move_uploaded_file returned false");
                        }
                    } else {
                        $errors[] = "Please ensure your name is in the format: SURNAME, FIRST NAME, MIDDLE NAME";
                    }
                }
            }
        } elseif (isset($_FILES['ipcr_file']) && $_FILES['ipcr_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Handle upload errors
            $uploadErrorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            $errorCode = $_FILES['ipcr_file']['error'];
            $errors[] = "IPCR file upload error: " . ($uploadErrorMessages[$errorCode] ?? 'Unknown error (Code: ' . $errorCode . ')');
        }
        
        // Handle PE file upload
        // Variables already initialized above
        if ($deletePeFile && $peFilePath) {
            // Delete existing file
            $oldFilePath = __DIR__ . '/uploads/documents/' . $peFilePath;
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
            $peFilePath = null;
            $peFileUpdated = true;
        }
        
        // Process PE file upload - check if file was actually uploaded
        if (isset($_FILES['pe_file']) && $_FILES['pe_file']['error'] === UPLOAD_ERR_OK && !empty($_FILES['pe_file']['tmp_name']) && is_uploaded_file($_FILES['pe_file']['tmp_name'])) {
            // Validate file is PDF
            $extension = strtolower(pathinfo($_FILES['pe_file']['name'], PATHINFO_EXTENSION));
            if ($extension !== 'pdf') {
                $errors[] = "PE file must be a PDF file.";
            } else {
                // Validate MIME type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $_FILES['pe_file']['tmp_name']);
                finfo_close($finfo);
                
                if ($mimeType !== 'application/pdf') {
                    $errors[] = "PE file must be a valid PDF file.";
                } else {
                    // Generate filename according to convention: SURNAME_FIRSTNAME_PE_WPUHRMDO.pdf
                    $nameParts = explode(',', $fullName);
                    if (count($nameParts) >= 2) {
                        $surname = trim(strtoupper($nameParts[0]));
                        $firstName = trim(strtoupper($nameParts[1]));
                        $firstName = str_replace(' ', '_', $firstName); // Replace spaces with underscores
                        $customFileName = $surname . '_' . $firstName . '_PE_WPUHRMDO';
                        
                        // Delete old file if exists
                        if ($peFilePath) {
                            $oldFilePath = __DIR__ . '/uploads/documents/' . $peFilePath;
                            if (file_exists($oldFilePath)) {
                                unlink($oldFilePath);
                            }
                        }
                        
                        // Upload new file - using direct approach like apply.php
                        $uploadDir = __DIR__ . '/uploads/documents/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        $fileName = $customFileName . '.pdf';
                        $uploadPath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['pe_file']['tmp_name'], $uploadPath)) {
                            // Verify file was actually saved
                            if (file_exists($uploadPath)) {
                                $peFilePath = $fileName;
                                $peFileUpdated = true;
                                error_log("PE file uploaded successfully: $fileName");
                            } else {
                                $errors[] = "PE file upload failed: File was not saved to disk.";
                            }
                        } else {
                            $errors[] = "Failed to upload PE file. Please check file permissions and try again.";
                            error_log("PE file upload failed: move_uploaded_file returned false");
                        }
                    } else {
                        $errors[] = "Please ensure your name is in the format: SURNAME, FIRST NAME, MIDDLE NAME";
                    }
                }
            }
        } elseif (isset($_FILES['pe_file']) && $_FILES['pe_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Handle upload errors
            $uploadErrorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            $errorCode = $_FILES['pe_file']['error'];
            $errors[] = "PE file upload error: " . ($uploadErrorMessages[$errorCode] ?? 'Unknown error (Code: ' . $errorCode . ')');
        }
        
        // Handle training certificate file uploads (multiple files)
        if (isset($_FILES['training_certificates']) && !empty($_FILES['training_certificates']['name'][0])) {
            $existingCertificates = $trainingCertificateModel->getUserCertificates($userId);
            $currentCount = count($existingCertificates);
            
            foreach ($_FILES['training_certificates']['name'] as $key => $fileName) {
                if (!empty($fileName) && $_FILES['training_certificates']['error'][$key] === UPLOAD_ERR_OK) {
                    // Validate file is PDF or JPEG
                    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    if (!in_array($extension, ['pdf', 'jpg', 'jpeg'])) {
                        $errors[] = "Training certificate files must be PDF or JPEG format. File: " . htmlspecialchars($fileName);
                        continue;
                    }
                    
                    // Validate MIME type
                    $tmpName = $_FILES['training_certificates']['tmp_name'][$key];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $tmpName);
                    finfo_close($finfo);
                    
                    $allowedMimes = ['application/pdf', 'image/jpeg', 'image/jpg'];
                    if (!in_array($mimeType, $allowedMimes)) {
                        $errors[] = "Training certificate file must be a valid PDF or JPEG file. File: " . htmlspecialchars($fileName);
                        continue;
                    }
                    
                    // Generate filename according to convention: SURNAME_FIRSTNAME_TS##_WPUHRMDO
                    $nameParts = explode(',', $fullName);
                    if (count($nameParts) >= 2) {
                        $surname = trim(strtoupper($nameParts[0]));
                        $firstName = trim(strtoupper($nameParts[1]));
                        $firstName = str_replace(' ', '_', $firstName); // Replace spaces with underscores
                        
                        // Get the next sequence number
                        $sequenceNumber = $currentCount + 1;
                        $sequenceStr = str_pad($sequenceNumber, 2, '0', STR_PAD_LEFT); // TS01, TS02, etc.
                        
                        $customFileName = $surname . '_' . $firstName . '_TS' . $sequenceStr . '_WPUHRMDO';
                        
                        // Upload new file
                        $uploadDir = __DIR__ . '/uploads/documents/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }
                        
                        $finalFileName = $customFileName . '.' . $extension;
                        $uploadPath = $uploadDir . $finalFileName;
                        
                        if (move_uploaded_file($tmpName, $uploadPath)) {
                            // Verify file was actually saved
                            if (file_exists($uploadPath)) {
                                // Save to database
                                $certificateData = [
                                    'user_id' => $userId,
                                    'file_name' => $finalFileName,
                                    'file_path' => $finalFileName,
                                    'file_size' => $_FILES['training_certificates']['size'][$key]
                                ];
                                
                                if ($trainingCertificateModel->create($certificateData)) {
                                    $currentCount++; // Increment for next file
                                    error_log("Training certificate uploaded successfully: $finalFileName");
                                } else {
                                    // Remove uploaded file if database insert failed
                                    if (file_exists($uploadPath)) {
                                        unlink($uploadPath);
                                    }
                                    $errors[] = "Failed to save training certificate record. File: " . htmlspecialchars($fileName);
                                }
                            } else {
                                $errors[] = "Training certificate upload failed: File was not saved to disk. File: " . htmlspecialchars($fileName);
                            }
                        } else {
                            $errors[] = "Failed to upload training certificate. Please check file permissions and try again. File: " . htmlspecialchars($fileName);
                            error_log("Training certificate upload failed: move_uploaded_file returned false for " . $fileName);
                        }
                    } else {
                        $errors[] = "Please ensure your name is in the format: SURNAME, FIRST NAME, MIDDLE NAME. File: " . htmlspecialchars($fileName);
                    }
                } elseif ($_FILES['training_certificates']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                    // Handle upload errors for training certificates
                    $uploadErrorMessages = [
                        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
                        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
                    ];
                    $errorCode = $_FILES['training_certificates']['error'][$key];
                    $errors[] = "Training certificate upload error (" . htmlspecialchars($fileName) . "): " . ($uploadErrorMessages[$errorCode] ?? 'Unknown error (Code: ' . $errorCode . ')');
                }
            }
        }
        
        // Helper function to handle application document uploads
        $handleDocumentUpload = function($fileKey, $documentType, $allowedExtensions, $allowedMimes, $fileNamePattern) use (&$errors, $fullName, $userId, $applicationDocumentModel) {
            if (!isset($_FILES[$fileKey])) {
                return;
            }
            
            // Check if it's a single file or multiple files
            $isMultiple = is_array($_FILES[$fileKey]['name']);
            
            if ($isMultiple) {
                // Multiple files (for COE, SRE, EL)
                if (empty($_FILES[$fileKey]['name'][0])) {
                    return;
                }
                
                $existingDocs = $applicationDocumentModel->getUserDocuments($userId, $documentType);
                $currentCount = count($existingDocs);
                
                foreach ($_FILES[$fileKey]['name'] as $key => $fileName) {
                    if (!empty($fileName) && $_FILES[$fileKey]['error'][$key] === UPLOAD_ERR_OK) {
                        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        if (!in_array($extension, $allowedExtensions)) {
                            $errors[] = ucfirst($documentType) . " files must be " . implode(' or ', $allowedExtensions) . " format. File: " . htmlspecialchars($fileName);
                            continue;
                        }
                        
                        $tmpName = $_FILES[$fileKey]['tmp_name'][$key];
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mimeType = finfo_file($finfo, $tmpName);
                        finfo_close($finfo);
                        
                        if (!in_array($mimeType, $allowedMimes)) {
                            $errors[] = ucfirst($documentType) . " file must be a valid " . implode(' or ', $allowedExtensions) . " file. File: " . htmlspecialchars($fileName);
                            continue;
                        }
                        
                        $nameParts = explode(',', $fullName);
                        if (count($nameParts) >= 2) {
                            $surname = trim(strtoupper($nameParts[0]));
                            $firstName = trim(strtoupper($nameParts[1]));
                            $firstName = str_replace(' ', '_', $firstName);
                            
                            $sequenceNumber = $currentCount + 1;
                            $sequenceStr = str_pad($sequenceNumber, 2, '0', STR_PAD_LEFT);
                            
                            $customFileName = str_replace(['SURNAME', 'FIRSTNAME', '##'], [$surname, $firstName, $sequenceStr], $fileNamePattern);
                            $finalFileName = $customFileName . '.' . $extension;
                            
                            $uploadDir = __DIR__ . '/uploads/documents/';
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0755, true);
                            }
                            
                            $uploadPath = $uploadDir . $finalFileName;
                            
                            if (move_uploaded_file($tmpName, $uploadPath)) {
                                if (file_exists($uploadPath)) {
                                    $docData = [
                                        'user_id' => $userId,
                                        'document_type' => $documentType,
                                        'file_name' => $finalFileName,
                                        'file_path' => $finalFileName,
                                        'file_size' => $_FILES[$fileKey]['size'][$key]
                                    ];
                                    
                                    if ($applicationDocumentModel->create($docData)) {
                                        $currentCount++;
                                        error_log("Application document ($documentType) uploaded successfully: $finalFileName");
                                    } else {
                                        if (file_exists($uploadPath)) {
                                            unlink($uploadPath);
                                        }
                                        $errors[] = "Failed to save " . $documentType . " record. File: " . htmlspecialchars($fileName);
                                    }
                                } else {
                                    $errors[] = ucfirst($documentType) . " upload failed: File was not saved to disk. File: " . htmlspecialchars($fileName);
                                }
                            } else {
                                $errors[] = "Failed to upload " . $documentType . ". Please check file permissions. File: " . htmlspecialchars($fileName);
                            }
                        } else {
                            $errors[] = "Please ensure your name is in the format: SURNAME, FIRST NAME, MIDDLE NAME. File: " . htmlspecialchars($fileName);
                        }
                    } elseif ($_FILES[$fileKey]['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                        $uploadErrorMessages = [
                            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
                            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
                        ];
                        $errorCode = $_FILES[$fileKey]['error'][$key];
                        $errors[] = ucfirst($documentType) . " upload error (" . htmlspecialchars($fileName) . "): " . ($uploadErrorMessages[$errorCode] ?? 'Unknown error');
                    }
                }
            } else {
                // Single file (for APLET, PDS, WES, TOR, DIP)
                if ($_FILES[$fileKey]['error'] === UPLOAD_ERR_OK && !empty($_FILES[$fileKey]['tmp_name']) && is_uploaded_file($_FILES[$fileKey]['tmp_name'])) {
                    $fileName = $_FILES[$fileKey]['name'];
                    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    
                    if (!in_array($extension, $allowedExtensions)) {
                        $errors[] = ucfirst($documentType) . " file must be " . implode(' or ', $allowedExtensions) . " format.";
                    } else {
                        $tmpName = $_FILES[$fileKey]['tmp_name'];
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mimeType = finfo_file($finfo, $tmpName);
                        finfo_close($finfo);
                        
                        if (!in_array($mimeType, $allowedMimes)) {
                            $errors[] = ucfirst($documentType) . " file must be a valid " . implode(' or ', $allowedExtensions) . " file.";
                        } else {
                            $nameParts = explode(',', $fullName);
                            if (count($nameParts) >= 2) {
                                $surname = trim(strtoupper($nameParts[0]));
                                $firstName = trim(strtoupper($nameParts[1]));
                                $firstName = str_replace(' ', '_', $firstName);
                                
                                $customFileName = str_replace(['SURNAME', 'FIRSTNAME'], [$surname, $firstName], $fileNamePattern);
                                $finalFileName = $customFileName . '.' . $extension;
                                
                                // Delete old file if exists (for single-file documents)
                                $existingDoc = $applicationDocumentModel->getUserDocumentByType($userId, $documentType);
                                if ($existingDoc && !empty($existingDoc['file_path'])) {
                                    $oldFilePath = __DIR__ . '/uploads/documents/' . $existingDoc['file_path'];
                                    if (file_exists($oldFilePath)) {
                                        unlink($oldFilePath);
                                    }
                                    $applicationDocumentModel->deleteById($existingDoc['id'], $userId);
                                }
                                
                                $uploadDir = __DIR__ . '/uploads/documents/';
                                if (!is_dir($uploadDir)) {
                                    mkdir($uploadDir, 0755, true);
                                }
                                
                                $uploadPath = $uploadDir . $finalFileName;
                                
                                if (move_uploaded_file($tmpName, $uploadPath)) {
                                    if (file_exists($uploadPath)) {
                                        $docData = [
                                            'user_id' => $userId,
                                            'document_type' => $documentType,
                                            'file_name' => $finalFileName,
                                            'file_path' => $finalFileName,
                                            'file_size' => $_FILES[$fileKey]['size']
                                        ];
                                        
                                        if (!$applicationDocumentModel->create($docData)) {
                                            if (file_exists($uploadPath)) {
                                                unlink($uploadPath);
                                            }
                                            $errors[] = "Failed to save " . $documentType . " record.";
                                        } else {
                                            error_log("Application document ($documentType) uploaded successfully: $finalFileName");
                                        }
                                    } else {
                                        $errors[] = ucfirst($documentType) . " upload failed: File was not saved to disk.";
                                    }
                                } else {
                                    $errors[] = "Failed to upload " . $documentType . ". Please check file permissions.";
                                }
                            } else {
                                $errors[] = "Please ensure your name is in the format: SURNAME, FIRST NAME, MIDDLE NAME";
                            }
                        }
                    }
                } elseif ($_FILES[$fileKey]['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadErrorMessages = [
                        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
                        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
                    ];
                    $errorCode = $_FILES[$fileKey]['error'];
                    $errors[] = ucfirst($documentType) . " upload error: " . ($uploadErrorMessages[$errorCode] ?? 'Unknown error (Code: ' . $errorCode . ')');
                }
            }
        };
        
        // Handle all document type uploads
        $handleDocumentUpload('application_letter', 'APLET', ['pdf'], ['application/pdf'], 'SURNAME_FIRSTNAME_APLET_WPUHRMDO');
        $handleDocumentUpload('personal_data_sheet', 'PDS', ['pdf'], ['application/pdf'], 'SURNAME_FIRSTNAME_PDS_WPUHRMDO');
        $handleDocumentUpload('work_experience_sheet', 'WES', ['pdf'], ['application/pdf'], 'SURNAME_FIRSTNAME_WES_WPUHRMDO');
        $handleDocumentUpload('transcript_of_records', 'TOR', ['pdf', 'jpg', 'jpeg'], ['application/pdf', 'image/jpeg', 'image/jpg'], 'SURNAME_FIRSTNAME_TOR_WPUHRMDO');
        $handleDocumentUpload('diploma', 'DIP', ['pdf', 'jpg', 'jpeg'], ['application/pdf', 'image/jpeg', 'image/jpg'], 'SURNAME_FIRSTNAME_DIP_WPUHRMDO');
        $handleDocumentUpload('certificate_of_employment', 'COE', ['pdf', 'jpg', 'jpeg'], ['application/pdf', 'image/jpeg', 'image/jpg'], 'SURNAME_FIRSTNAME_COE##_WPUHRMDO');
        $handleDocumentUpload('service_record', 'SRE', ['pdf', 'jpg', 'jpeg'], ['application/pdf', 'image/jpeg', 'image/jpg'], 'SURNAME_FIRSTNAME_SRE##_WPUHRMDO');
        $handleDocumentUpload('eligibility_license', 'EL', ['pdf', 'jpg', 'jpeg'], ['application/pdf', 'image/jpeg', 'image/jpg'], 'SURNAME_FIRSTNAME_EL##_WPUHRMDO');
        
        if (empty($errors)) {
            $updateData = [
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'age' => $age,
                'sex' => !empty($sex) ? $sex : null,
                'civil_status' => !empty($civilStatus) ? $civilStatus : null,
                'highest_educational_attainment' => !empty($highestEducationalAttainment) ? $highestEducationalAttainment : null,
                'bachelors_degree' => !empty($bachelorsDegree) ? $bachelorsDegree : null,
                'masters_degree' => !empty($mastersDegree) ? $mastersDegree : null,
                'doctorate_degree' => !empty($doctorateDegree) ? $doctorateDegree : null,
                'application_type' => !empty($applicationType) ? implode(',', $applicationType) : null,
                'ipcr_rating' => !empty($ipcrRating) ? $ipcrRating : null,
                'pe_rating' => !empty($peRating) ? $peRating : null,
                'position_applied_for' => !empty($positionAppliedFor) ? $positionAppliedFor : null,
                'training_programs' => !empty($trainingPrograms) ? $trainingPrograms : null,
                'career_service_eligibilities' => !empty($careerServiceEligibilities) ? implode(',', $careerServiceEligibilities) : null,
                'career_service_eligibilities_other' => !empty($careerServiceEligibilitiesOther) ? $careerServiceEligibilitiesOther : null,
                'special_needs' => !empty($specialNeeds) ? $specialNeeds : null
            ];
            
            // Always include file paths if files were uploaded, deleted, or updated
            // The key is to check if a file operation occurred, not just if flags are set
            
            // Check if IPCR file operation occurred
            $ipcrFileOperation = false;
            if (isset($_FILES['ipcr_file']) && $_FILES['ipcr_file']['error'] === UPLOAD_ERR_OK) {
                // File was uploaded - check if it was successfully saved
                if (!empty($ipcrFilePath) && file_exists(__DIR__ . '/uploads/documents/' . $ipcrFilePath)) {
                    $ipcrFileOperation = true;
                    error_log("IPCR file uploaded and verified on disk: " . $ipcrFilePath);
                }
            } elseif ($deleteIpcrFile) {
                // File deletion was requested
                $ipcrFileOperation = true;
                error_log("IPCR file deletion requested");
            }
            
            if ($ipcrFileOperation || $ipcrFileUpdated === true) {
                $updateData['ipcr_file_path'] = $ipcrFilePath; // Can be null for deletion
                error_log("Adding ipcr_file_path to updateData: " . var_export($ipcrFilePath, true));
            }
            
            // Check if PE file operation occurred
            $peFileOperation = false;
            if (isset($_FILES['pe_file']) && $_FILES['pe_file']['error'] === UPLOAD_ERR_OK) {
                // File was uploaded - check if it was successfully saved
                if (!empty($peFilePath) && file_exists(__DIR__ . '/uploads/documents/' . $peFilePath)) {
                    $peFileOperation = true;
                    error_log("PE file uploaded and verified on disk: " . $peFilePath);
                }
            } elseif ($deletePeFile) {
                // File deletion was requested
                $peFileOperation = true;
                error_log("PE file deletion requested");
            }
            
            if ($peFileOperation || $peFileUpdated === true) {
                $updateData['pe_file_path'] = $peFilePath; // Can be null for deletion
                error_log("Adding pe_file_path to updateData: " . var_export($peFilePath, true));
            }
            
            if (!empty($password)) {
                $updateData['password'] = $password;
            }
            
            // Debug: Log update data
            error_log("=== PROFILE UPDATE DEBUG ===");
            error_log("IPCR File Updated: " . ($ipcrFileUpdated ? 'YES' : 'NO'));
            error_log("IPCR File Path: " . ($ipcrFilePath ?? 'NULL'));
            error_log("PE File Updated: " . ($peFileUpdated ? 'YES' : 'NO'));
            error_log("PE File Path: " . ($peFilePath ?? 'NULL'));
            error_log("Update Data Keys: " . implode(', ', array_keys($updateData)));
            if (isset($updateData['ipcr_file_path'])) {
                error_log("ipcr_file_path in updateData: " . var_export($updateData['ipcr_file_path'], true));
            }
            if (isset($updateData['pe_file_path'])) {
                error_log("pe_file_path in updateData: " . var_export($updateData['pe_file_path'], true));
            }
            
            if ($userModel->update($userId, $updateData)) {
                $_SESSION['full_name'] = $fullName;
                $_SESSION['email'] = $email;
                
                // Verify the update
                $updatedUser = $userModel->findById($userId);
                error_log("After update - ipcr_file_path: " . ($updatedUser['ipcr_file_path'] ?? 'NULL'));
                error_log("After update - pe_file_path: " . ($updatedUser['pe_file_path'] ?? 'NULL'));
                
                header('Location: profile.php?success=profile_updated');
                exit();
            } else {
                $error = "Failed to update profile.";
                error_log("UPDATE FAILED - Check database connection and SQL");
            }
        } else {
            // There were validation errors after file processing
            $error = implode(' ', $errors);
            error_log("Validation errors after file processing: " . $error);
        }
    } else {
        // There were initial validation errors
        $error = implode(' ', $errors);
        error_log("Initial validation errors: " . $error);
    }
    }
}

// Load training certificates and application documents AFTER POST processing
// This ensures we display the latest data including any newly uploaded files
$trainingCertificates = [];
$applicationDocuments = [];
$documentsByType = [];

$tablesExist = true;
try {
    $trainingCertificates = $trainingCertificateModel->getUserCertificates($userId);
} catch (\Exception $e) {
    // Table might not exist yet - user needs to run migration
    error_log("Training certificates table not found: " . $e->getMessage());
    $trainingCertificates = [];
    $tablesExist = false;
}

try {
    $applicationDocuments = $applicationDocumentModel->getUserDocuments($userId);
    
    // Organize documents by type for easier access
    foreach ($applicationDocuments as $doc) {
        if (!isset($documentsByType[$doc['document_type']])) {
            $documentsByType[$doc['document_type']] = [];
        }
        $documentsByType[$doc['document_type']][] = $doc;
    }
} catch (\Exception $e) {
    // Table might not exist yet - user needs to run migration
    error_log("Application documents table not found: " . $e->getMessage());
    $applicationDocuments = [];
    $tablesExist = false;
}

// Refresh user data to get latest file paths
$user = $userModel->findById($userId);

// Display-only stats for dashboard cards (no backend logic change)
$applicationModel = new Application();
$userApplications = $applicationModel->getUserApplications($userId);
$applicationsSubmitted = count($userApplications);
$pendingApplications = count(array_filter($userApplications, fn($a) => ($a['status'] ?? '') === STATUS_PENDING));
$interviewInvitations = count(array_filter($userApplications, fn($a) => !empty($a['interview_date'])));
$documentsUploaded = count($trainingCertificates) + count($applicationDocuments);
if (!empty($user['ipcr_file_path'])) $documentsUploaded++;
if (!empty($user['pe_file_path'])) $documentsUploaded++;

// Avatar initials helper
function profileInitials($fullName) {
    $parts = array_filter(explode(' ', preg_replace('/[,]+/', ' ', strtoupper(trim($fullName)))));
    if (count($parts) >= 2) {
        return substr($parts[0], 0, 1) . substr($parts[1], 0, 1);
    }
    return strtoupper(substr(trim($fullName), 0, 1));
}

$profileInitials = profileInitials($user['full_name'] ?? 'A');
$memberSince = !empty($user['created_at']) ? date('M j, Y', strtotime($user['created_at'])) : '—';
$lastUpdated = !empty($user['updated_at']) ? date('M j, Y g:i A', strtotime($user['updated_at'])) : '—';
$infoMessage = $_GET['info'] ?? '';

function renderDocumentSection($number, $title, $description, $formatCode, $examples, $fileInputName, $documentType, $allowedFormats, $documentsByType, $isMultiple = false) {
    $docs = isset($documentsByType[$documentType]) ? $documentsByType[$documentType] : [];
    $isMultipleAllowed = $isMultiple;
    if (!$isMultipleAllowed && !empty($docs)) {
        $docs = [end($docs)];
    }
    $acceptAttr = implode(',', array_map(function($f) {
        return '.' . $f . ($f === 'pdf' ? ',application/pdf' : ($f === 'jpg' || $f === 'jpeg' ? ',image/jpeg,image/jpg' : ''));
    }, $allowedFormats));
    ?>
    <div class="profile-doc-section" data-doc-type="<?= htmlspecialchars($documentType) ?>">
        <h4 class="profile-doc-section__title"><?= $number ?>. <?= htmlspecialchars($title) ?></h4>
        <?php if (!empty($description)): ?>
            <div class="profile-doc-section__desc"><?= $description ?></div>
        <?php endif; ?>
        <div class="profile-format-card">
            <div class="profile-format-card__label">Required Format</div>
            <div class="profile-format-card__code">
                <span><?= htmlspecialchars(strip_tags($formatCode)) ?></span>
                <button type="button" class="profile-format-card__copy" data-copy-format="<?= htmlspecialchars(strip_tags($formatCode)) ?>">Copy</button>
            </div>
            <?php if (!empty($examples)): ?>
                <p class="profile-field-hint" style="margin-top:8px;">Example: <?= is_array($examples) ? htmlspecialchars($examples[0]) : htmlspecialchars($examples) ?></p>
            <?php endif; ?>
        </div>
        <label class="profile-section-label">Uploaded Files</label>
        <div class="profile-files-grid">
        <?php if (!empty($docs)): ?>
            <?php foreach ($docs as $doc):
                $docDisplayPath = 'uploads/documents/' . $doc['file_path'];
                $docFullPath = __DIR__ . '/' . $docDisplayPath;
                $docFileExists = file_exists($docFullPath);
                $isPdf = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION)) === 'pdf';
                $fileSize = !empty($doc['file_size']) ? round($doc['file_size'] / 1024, 1) . ' KB' : '';
            ?>
            <div class="profile-file-card" data-has-file="1">
                <div class="profile-file-card__icon profile-file-card__icon--<?= $isPdf ? 'pdf' : 'image' ?>"><i class="fas fa-<?= $isPdf ? 'file-pdf' : 'file-image' ?>"></i></div>
                <div class="profile-file-card__info">
                    <div class="profile-file-card__name"><?= htmlspecialchars($doc['file_name']) ?></div>
                    <div class="profile-file-card__meta"><?= $fileSize ?></div>
                    <span class="profile-file-card__status profile-file-card__status--ok"><i class="fas fa-check"></i> Completed</span>
                </div>
                <div class="profile-file-card__actions">
                    <?php if ($docFileExists): ?>
                    <a href="#" class="profile-btn profile-btn--sm profile-btn--secondary" data-preview="<?= htmlspecialchars($docDisplayPath) ?>"><i class="fas fa-eye"></i> View</a>
                    <a href="<?= htmlspecialchars($docDisplayPath) ?>" download class="profile-btn profile-btn--sm profile-btn--secondary"><i class="fas fa-download"></i></a>
                    <?php endif; ?>
                    <a href="../actions/delete-application-document.php?id=<?= $doc['id'] ?>" class="profile-btn profile-btn--sm profile-btn--danger-outline" data-confirm="Delete this document?"><i class="fas fa-trash"></i></a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="profile-empty" style="padding:20px;">
                <div class="profile-empty__icon" style="width:56px;height:56px;font-size:1.25rem;"><i class="fas fa-file-upload"></i></div>
                <p class="profile-empty__text">No file uploaded yet.</p>
            </div>
        <?php endif; ?>
        </div>
        <div class="profile-dropzone">
            <div class="profile-dropzone__icon"><i class="fas fa-cloud-upload-alt"></i></div>
            <div class="profile-dropzone__title">Drop <?= strtoupper(implode(' or ', $allowedFormats)) ?> here or Browse</div>
            <div class="profile-dropzone__subtitle profile-dropzone__selected"></div>
            <div class="profile-dropzone__formats">
                <?php foreach ($allowedFormats as $f): ?><span class="profile-dropzone__tag"><?= strtoupper($f) ?></span><?php endforeach; ?>
                <span class="profile-dropzone__tag">Max 10MB</span>
            </div>
            <input type="file" name="<?= $fileInputName ?><?= $isMultipleAllowed ? '[]' : '' ?>" accept="<?= $acceptAttr ?>" <?= $isMultipleAllowed ? 'multiple' : '' ?> aria-label="Upload <?= htmlspecialchars($title) ?>">
            <div class="profile-dropzone__progress"><div class="profile-dropzone__progress-bar"><div class="profile-dropzone__progress-fill"></div></div></div>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Profile - WPU Applicant System</title>
    <link rel="icon" type="image/x-icon" href="../../favicon.ico">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/office-theme.css" rel="stylesheet">
    <link href="../../assets/css/profile.css" rel="stylesheet">
    <?php include '../../includes/pwa-header.php'; ?>
</head>
<body class="profile-page" data-theme="system">
<?php include '../../includes/applicant-theme-init.php'; ?>
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">
                <img src="../../assets/logo.png" alt="WPU Logo" style="height: 32px; width: auto;">
                <span>WPU Jobs</span>
            </a>
        </div>
        
        <nav class="sidebar-nav">
         <a href="profile.php" class="sidebar-nav-item active">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="dashboard.php" class="sidebar-nav-item">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="positions.php" class="sidebar-nav-item">
                <i class="fas fa-briefcase"></i>
                <span>Browse Jobs</span>
            </a>
            <a href="my-applications.php" class="sidebar-nav-item">
                <i class="fas fa-file-alt"></i>
                <span>My Applications</span>
            </a>
            
        </nav>
        
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">
                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?= htmlspecialchars($user['full_name']) ?></div>
                    <div class="sidebar-user-role">Applicant</div>
                </div>
                
            </div>
        </div>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <!-- Top Bar -->
        <div class="topbar">
            <button class="mobile-menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="topbar-title">Profile</h1>
            <div class="topbar-actions">
                <?php include '../../includes/applicant-theme-toggle.php'; ?>
                <a href="../../auth/logout.php" class="topbar-btn confirm-logout" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content">
            <!-- Profile Hero -->
            <section class="profile-hero" aria-label="Profile overview">
                <div class="profile-hero__banner"></div>
                <div class="profile-hero__body">
                    <div class="profile-hero__top">
                        <div class="profile-avatar-wrap">
                            <div class="profile-avatar" aria-hidden="true">
                                <?php if (!empty($user['profile_picture']) && file_exists(__DIR__ . '/uploads/' . $user['profile_picture'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($user['profile_picture']) ?>" alt="">
                                <?php else: ?>
                                    <?= htmlspecialchars($profileInitials) ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="profile-avatar-edit" title="Edit photo (coming soon)" aria-label="Edit profile photo" disabled>
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                        <div class="profile-hero__info">
                            <h2 class="profile-hero__name"><?= htmlspecialchars($user['full_name']) ?></h2>
                            <div class="profile-hero__role">Applicant · ID #<?= (int)$userId ?></div>
                            <div class="profile-hero__meta">
                                <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email']) ?></span>
                                <?php if (!empty($user['phone'])): ?>
                                <span><i class="fas fa-phone"></i> <?= htmlspecialchars($user['phone']) ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-chart-pie"></i> <span id="profileHeroPct">0</span> complete</span>
                            </div>
                        </div>
                        <div class="profile-hero__actions">
                            <span class="profile-badge profile-badge--incomplete" id="profileStatusBadge">
                                <i class="fas fa-clock"></i> In Progress
                            </span>
                            <div class="profile-theme-toggle" role="group" aria-label="Theme">
                                <button type="button" data-theme-set="light" aria-pressed="false">Light</button>
                                <button type="button" data-theme-set="dark" aria-pressed="false">Dark</button>
                                <button type="button" data-theme-set="system" aria-pressed="true" class="active">System</button>
                            </div>
                        </div>
                    </div>
                    <div class="profile-hero__dates">
                        <span><i class="fas fa-calendar-plus"></i> Member since <?= $memberSince ?></span>
                        <span><i class="fas fa-clock"></i> Last updated <?= $lastUpdated ?></span>
                        <span id="draftSavedIndicator" style="opacity:0; transition: opacity 0.3s;"><i class="fas fa-cloud"></i> Draft saved</span>
                    </div>
                </div>
            </section>

            <!-- Profile Completion -->
            <section class="profile-completion-card" aria-label="Profile completion">
                <div class="profile-completion-card__header">
                    <h3 class="profile-completion-card__title">Profile Completion</h3>
                    <span class="profile-completion-card__pct" id="profileProgressPct">0%</span>
                </div>
                <div class="profile-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                    <div class="profile-progress-bar__fill" id="profileProgressFill"></div>
                </div>
                <div class="profile-completion-checklist">
                    <button type="button" class="profile-checklist-item incomplete" data-section="personal" data-goto-step="0">
                        <i class="fas fa-circle"></i> Personal Information
                    </button>
                    <button type="button" class="profile-checklist-item incomplete" data-section="education" data-goto-step="1">
                        <i class="fas fa-circle"></i> Education
                    </button>
                    <button type="button" class="profile-checklist-item incomplete" data-section="experience" data-goto-step="2">
                        <i class="fas fa-circle"></i> Experience
                    </button>
                    <button type="button" class="profile-checklist-item incomplete" data-section="eligibility" data-goto-step="3">
                        <i class="fas fa-circle"></i> Eligibility
                    </button>
                    <button type="button" class="profile-checklist-item incomplete" data-section="documents" data-goto-step="4">
                        <i class="fas fa-circle"></i> Documents
                    </button>
                </div>
                <div style="margin-top: 16px; font-size: 0.8125rem; color: var(--profile-text-muted);">
                    <strong>Missing:</strong> <span id="profileMissingList"></span>
                </div>
            </section>

            <!-- Dashboard Stats -->
            <div class="profile-stats-grid">
                <div class="profile-stat-card">
                    <div class="profile-stat-card__icon profile-stat-card__icon--green"><i class="fas fa-chart-line"></i></div>
                    <div>
                        <div class="profile-stat-card__value"><?= number_format($totalExperience, 1) ?></div>
                        <div class="profile-stat-card__label">Years Experience</div>
                    </div>
                </div>
                <div class="profile-stat-card">
                    <div class="profile-stat-card__icon profile-stat-card__icon--purple"><i class="fas fa-award"></i></div>
                    <div>
                        <div class="profile-stat-card__value"><?= count($trainingCertificates) ?></div>
                        <div class="profile-stat-card__label">Certificates</div>
                    </div>
                </div>
                <div class="profile-stat-card">
                    <div class="profile-stat-card__icon profile-stat-card__icon--blue"><i class="fas fa-file-alt"></i></div>
                    <div>
                        <div class="profile-stat-card__value"><?= $documentsUploaded ?></div>
                        <div class="profile-stat-card__label">Documents Uploaded</div>
                    </div>
                </div>
                <div class="profile-stat-card">
                    <div class="profile-stat-card__icon profile-stat-card__icon--teal"><i class="fas fa-paper-plane"></i></div>
                    <div>
                        <div class="profile-stat-card__value"><?= $applicationsSubmitted ?></div>
                        <div class="profile-stat-card__label">Applications Submitted</div>
                    </div>
                </div>
                <div class="profile-stat-card">
                    <div class="profile-stat-card__icon profile-stat-card__icon--orange"><i class="fas fa-hourglass-half"></i></div>
                    <div>
                        <div class="profile-stat-card__value"><?= $pendingApplications ?></div>
                        <div class="profile-stat-card__label">Pending Applications</div>
                    </div>
                </div>
                <div class="profile-stat-card">
                    <div class="profile-stat-card__icon profile-stat-card__icon--pink"><i class="fas fa-calendar-check"></i></div>
                    <div>
                        <div class="profile-stat-card__value"><?= $interviewInvitations ?></div>
                        <div class="profile-stat-card__label">Interview Invitations</div>
                    </div>
                </div>
            </div>

            <!-- Notifications -->
            <div class="profile-notifications">
            <?php if (isset($tablesExist) && !$tablesExist): ?>
                <div class="profile-notification profile-notification--warning" data-auto-hide="1" role="alert">
                    <div class="profile-notification__icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="profile-notification__body">
                        <strong>Database Tables Missing!</strong>
                        The training certificates and application documents tables are not set up yet.
                        Please <a href="../../migrations/run_migrations.php">run the migration script</a> to create these tables before uploading files.
                    </div>
                    <button type="button" class="profile-notification__close" aria-label="Dismiss"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success === 'profile_updated'): ?>
                <div class="profile-notification profile-notification--success" data-auto-hide="1" role="alert">
                    <div class="profile-notification__icon"><i class="fas fa-check-circle"></i></div>
                    <div class="profile-notification__body"><strong>Success!</strong> Profile updated successfully!</div>
                    <button type="button" class="profile-notification__close" aria-label="Dismiss"><i class="fas fa-times"></i></button>
                </div>
            <?php elseif ($success === 'certificate_deleted'): ?>
                <div class="profile-notification profile-notification--success" data-auto-hide="1" role="alert">
                    <div class="profile-notification__icon"><i class="fas fa-check-circle"></i></div>
                    <div class="profile-notification__body"><strong>Success!</strong> Training certificate deleted successfully!</div>
                    <button type="button" class="profile-notification__close" aria-label="Dismiss"><i class="fas fa-times"></i></button>
                </div>
            <?php elseif ($success === 'document_deleted'): ?>
                <div class="profile-notification profile-notification--success" data-auto-hide="1" role="alert">
                    <div class="profile-notification__icon"><i class="fas fa-check-circle"></i></div>
                    <div class="profile-notification__body"><strong>Success!</strong> Document deleted successfully!</div>
                    <button type="button" class="profile-notification__close" aria-label="Dismiss"><i class="fas fa-times"></i></button>
                </div>
            <?php elseif ($success === 'experience_added' || $success === 'experience_updated' || $success === 'experience_deleted'): ?>
                <div class="profile-notification profile-notification--success" data-auto-hide="1" role="alert">
                    <div class="profile-notification__icon"><i class="fas fa-check-circle"></i></div>
                    <div class="profile-notification__body"><strong>Success!</strong> Work experience updated.</div>
                    <button type="button" class="profile-notification__close" aria-label="Dismiss"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="profile-notification profile-notification--error" role="alert">
                    <div class="profile-notification__icon"><i class="fas fa-exclamation-circle"></i></div>
                    <div class="profile-notification__body"><strong>Error!</strong> <?= htmlspecialchars($error) ?></div>
                    <button type="button" class="profile-notification__close" aria-label="Dismiss"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($infoMessage)): ?>
                <div class="profile-notification profile-notification--info" data-auto-hide="1" role="alert">
                    <div class="profile-notification__icon"><i class="fas fa-info-circle"></i></div>
                    <div class="profile-notification__body"><?= htmlspecialchars($infoMessage) ?></div>
                    <button type="button" class="profile-notification__close" aria-label="Dismiss"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>
            </div>

            <!-- Profile Wizard -->
            <div class="profile-wizard">
                <!-- Desktop Navigation -->
                <nav class="profile-wizard-nav" aria-label="Profile sections">
                    <div class="profile-wizard-nav__title">Sections</div>
                    <?php
                    $wizardSteps = [
                        ['Personal Information', 'fa-user'],
                        ['Education', 'fa-graduation-cap'],
                        ['Experience', 'fa-briefcase'],
                        ['Eligibility', 'fa-certificate'],
                        ['Documents', 'fa-folder-open'],
                        ['Review & Submit', 'fa-check-circle'],
                    ];
                    foreach ($wizardSteps as $i => $step): ?>
                    <button type="button" class="profile-wizard-step<?= $i === 0 ? ' active' : '' ?>" data-wizard-step="<?= $i ?>" aria-current="<?= $i === 0 ? 'step' : 'false' ?>">
                        <span class="profile-wizard-step__num"><?= $i + 1 ?></span>
                        <span><?= htmlspecialchars($step[0]) ?></span>
                    </button>
                    <?php endforeach; ?>
                </nav>

                <!-- Mobile Stepper -->
                <div class="profile-wizard-stepper" aria-label="Profile steps">
                    <?php foreach ($wizardSteps as $i => $step): ?>
                    <button type="button" class="profile-stepper-pill<?= $i === 0 ? ' active' : '' ?>" data-wizard-step="<?= $i ?>">
                        <?= $i + 1 ?>. <?= htmlspecialchars(explode(' ', $step[0])[0]) ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <div class="profile-wizard-main">
                    <form method="POST" action="" id="profileForm" class="prevent-double-submit" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <?php FormTokenManager::renderFormTokenInput('update_profile'); ?>

                        <!-- Step 1: Personal Information -->
                        <div class="profile-wizard-panel active" id="wizard-panel-personal" role="tabpanel">
                            <div class="profile-section-card">
                                <div class="profile-section-card__header">
                                    <h3 class="profile-section-card__title"><i class="fas fa-user"></i> Personal Information</h3>
                                </div>
                                <div class="profile-section-card__body">
                                    <div class="profile-form-grid profile-form-grid--1">
                                        <div class="profile-field profile-field--full">
                                            <label class="profile-section-label">Full Name <span class="profile-badge-req">Required</span></label>
                                            <p class="profile-field-hint">Format: SURNAME, FIRST NAME, MIDDLE NAME (Capital Letters)</p>
                                            <div class="profile-floating-label">
                                                <i class="fas fa-id-card profile-field-icon"></i>
                                                <input type="text" name="full_name" style="text-transform: uppercase;" placeholder=" "
                                                       value="<?= htmlspecialchars(strtoupper($user['full_name'] ?? '')) ?>" required aria-required="true">
                                                <label>Full Name</label>
                                            </div>
                                        </div>

                                        <div class="profile-form-grid">
                                            <div class="profile-field">
                                                <label class="profile-section-label">Age <span class="profile-badge-req">Required</span></label>
                                                <div class="profile-floating-label">
                                                    <i class="fas fa-birthday-cake profile-field-icon"></i>
                                                    <input type="number" name="age" placeholder=" " min="1" max="120"
                                                           value="<?= htmlspecialchars($user['age'] ?? '') ?>" required aria-required="true">
                                                    <label>Age</label>
                                                </div>
                                            </div>
                                            <div class="profile-field">
                                                <label class="profile-section-label">Sex <span class="profile-badge-req">Required</span></label>
                                                <div class="profile-radio-grid">
                                                    <label class="profile-radio-card">
                                                        <input type="radio" name="sex" value="Male" <?= (isset($user['sex']) && $user['sex'] === 'Male') ? 'checked' : '' ?> required>
                                                        <span class="profile-radio-card__inner"><i class="fas fa-mars"></i> Male</span>
                                                    </label>
                                                    <label class="profile-radio-card">
                                                        <input type="radio" name="sex" value="Female" <?= (isset($user['sex']) && $user['sex'] === 'Female') ? 'checked' : '' ?> required>
                                                        <span class="profile-radio-card__inner"><i class="fas fa-venus"></i> Female</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="profile-field profile-field--full">
                                            <label class="profile-section-label">Civil Status <span class="profile-badge-req">Required</span></label>
                                            <div class="profile-radio-grid">
                                                <?php 
                                                $civilStatuses = ['Married', 'Single', 'Separated', 'Widowed', 'Annuled', 'Solo Parent'];
                                                foreach ($civilStatuses as $status): ?>
                                                <label class="profile-radio-card">
                                                    <input type="radio" name="civil_status" value="<?= htmlspecialchars($status) ?>"
                                                           <?= (isset($user['civil_status']) && $user['civil_status'] === $status) ? 'checked' : '' ?> required>
                                                    <span class="profile-radio-card__inner"><?= htmlspecialchars($status) ?></span>
                                                </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <div class="profile-field profile-field--full">
                                            <label class="profile-section-label">Address <span class="profile-badge-req">Required</span></label>
                                            <div class="profile-floating-label">
                                                <i class="fas fa-map-marker-alt profile-field-icon"></i>
                                                <textarea name="address" placeholder=" " required aria-required="true" data-max-length="500"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                                                <label>Complete Address</label>
                                            </div>
                                            <div class="profile-char-count">0 / 500</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="profile-section-card">
                                <div class="profile-section-card__header">
                                    <h3 class="profile-section-card__title"><i class="fas fa-address-book"></i> Contact Information</h3>
                                </div>
                                <div class="profile-section-card__body">
                                    <div class="profile-form-grid">
                                        <div class="profile-field">
                                            <label class="profile-section-label">Mobile Number <span class="profile-badge-opt">Optional</span></label>
                                            <div class="profile-floating-label">
                                                <i class="fas fa-phone profile-field-icon"></i>
                                                <input type="tel" name="phone" placeholder=" " value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                                <label>Mobile Number</label>
                                            </div>
                                        </div>
                                        <div class="profile-field">
                                            <label class="profile-section-label">Email <span class="profile-badge-req">Required</span></label>
                                            <div class="profile-floating-label">
                                                <i class="fas fa-envelope profile-field-icon"></i>
                                                <input type="email" name="email" placeholder=" " value="<?= htmlspecialchars($user['email'] ?? '') ?>" required aria-required="true">
                                                <label>Email Address</label>
                                            </div>
                                            <div class="profile-field-error">Please enter a valid email address.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div style="display:flex; justify-content:flex-end; gap:12px;">
                                <button type="button" class="profile-btn profile-btn--primary" data-wizard-next>Continue to Education <i class="fas fa-arrow-right"></i></button>
                            </div>
                        </div>

                        <!-- Step 2: Education -->
                        <div class="profile-wizard-panel" id="wizard-panel-education" role="tabpanel">
                            <div class="profile-section-card">
                                <div class="profile-section-card__header">
                                    <h3 class="profile-section-card__title"><i class="fas fa-graduation-cap"></i> Educational Background</h3>
                                </div>
                                <div class="profile-section-card__body">
                                    <p class="profile-field-hint" style="margin-bottom:20px;">If a field does not apply, indicate <strong>Not Applicable (NA)</strong>.</p>

                                    <div class="profile-field profile-field--full" style="margin-bottom:24px;">
                                        <label class="profile-section-label">Highest Educational Attainment</label>
                                        <div class="profile-floating-label">
                                            <select name="highest_educational_attainment">
                                                <option value="" <?= empty($user['highest_educational_attainment']) ? 'selected' : '' ?>>Select highest educational attainment</option>
                                                <option value="Elementary Level" <?= (isset($user['highest_educational_attainment']) && $user['highest_educational_attainment'] === 'Elementary Level') ? 'selected' : '' ?>>Elementary Level</option>
                                                <option value="Elementary Graduate" <?= (isset($user['highest_educational_attainment']) && $user['highest_educational_attainment'] === 'Elementary Graduate') ? 'selected' : '' ?>>Elementary Graduate</option>
                                                <option value="Secondary/ High School Level" <?= (isset($user['highest_educational_attainment']) && $user['highest_educational_attainment'] === 'Secondary/ High School Level') ? 'selected' : '' ?>>Secondary/ High School Level</option>
                                                <option value="Secondary/ High School Graduate" <?= (isset($user['highest_educational_attainment']) && $user['highest_educational_attainment'] === 'Secondary/ High School Graduate') ? 'selected' : '' ?>>Secondary/ High School Graduate</option>
                                                <option value="College Level" <?= (isset($user['highest_educational_attainment']) && $user['highest_educational_attainment'] === 'College Level') ? 'selected' : '' ?>>College Level</option>
                                                <option value="College Graduate" <?= (isset($user['highest_educational_attainment']) && $user['highest_educational_attainment'] === 'College Graduate') ? 'selected' : '' ?>>College Graduate</option>
                                                <option value="Master's Degree Holder" <?= (isset($user['highest_educational_attainment']) && $user['highest_educational_attainment'] === 'Master\'s Degree Holder') ? 'selected' : '' ?>>Master's Degree Holder</option>
                                                <option value="Doctoral Degree Holder" <?= (isset($user['highest_educational_attainment']) && $user['highest_educational_attainment'] === 'Doctoral Degree Holder') ? 'selected' : '' ?>>Doctoral Degree Holder</option>
                                            </select>
                                            <label>Highest Attainment</label>
                                        </div>
                                    </div>

                                    <div class="profile-edu-cards">
                                        <div class="profile-edu-card">
                                            <div class="profile-edu-card__header"><i class="fas fa-university"></i> Bachelor's Degree</div>
                                            <div class="profile-edu-card__body">
                                                <p class="profile-field-hint">Degree, School/University, Year — or NA</p>
                                                <textarea name="bachelors_degree" placeholder="If not applicable, type: NA" data-max-length="500"><?= htmlspecialchars($user['bachelors_degree'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                        <div class="profile-edu-card">
                                            <div class="profile-edu-card__header"><i class="fas fa-user-graduate"></i> Master's Degree</div>
                                            <div class="profile-edu-card__body">
                                                <p class="profile-field-hint">Degree, School/University, Year — or NA</p>
                                                <textarea name="masters_degree" placeholder="If not applicable, type: NA" data-max-length="500"><?= htmlspecialchars($user['masters_degree'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                        <div class="profile-edu-card">
                                            <div class="profile-edu-card__header"><i class="fas fa-award"></i> Doctorate Degree</div>
                                            <div class="profile-edu-card__body">
                                                <p class="profile-field-hint">Degree, School/University, Year — or NA</p>
                                                <textarea name="doctorate_degree" placeholder="If not applicable, type: NA" data-max-length="500"><?= htmlspecialchars($user['doctorate_degree'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                                <button type="button" class="profile-btn profile-btn--secondary" data-wizard-prev><i class="fas fa-arrow-left"></i> Back</button>
                                <button type="button" class="profile-btn profile-btn--primary" data-wizard-next>Continue to Experience <i class="fas fa-arrow-right"></i></button>
                            </div>
                        </div>

                        <!-- Step 3: Experience (summary field in form) -->
                        <div class="profile-wizard-panel" id="wizard-panel-experience" role="tabpanel">
                            <div class="profile-section-card">
                                <div class="profile-section-card__header">
                                    <h3 class="profile-section-card__title"><i class="fas fa-briefcase"></i> Work Experience Summary</h3>
                                </div>
                                <div class="profile-section-card__body">
                                    <p class="profile-field-hint">Managed in the timeline below. Format: Position, Company, Inclusive Dates.</p>
                                    <?php
                                    $workExpText = '';
                                    if (!empty($allExperiences)) {
                                        $workExpLines = [];
                                        foreach ($allExperiences as $exp) {
                                            $startDate = date('M. d, Y', strtotime($exp['start_date']));
                                            $endDate = $exp['is_current'] ? 'Present' : date('M. d, Y', strtotime($exp['end_date']));
                                            $workExpLines[] = htmlspecialchars($exp['position_title'] . ', ' . $exp['company_name'] . ', ' . $startDate . ' to ' . $endDate);
                                        }
                                        $workExpText = implode("\n", $workExpLines);
                                    } else {
                                        $workExpText = 'NA';
                                    }
                                    ?>
                                    <textarea name="work_experience_display" class="form-control" rows="4" readonly style="width:100%; border-radius:12px; padding:12px; border:1.5px solid var(--profile-border); background:var(--profile-surface-2);"><?= $workExpText ?></textarea>
                                </div>
                            </div>

                            <!-- Work Experience Timeline (outside submit flow, same data) -->
                            <div class="profile-section-card" id="experienceCount" data-count="<?= $totalExperiences ?>">
                                <div class="profile-section-card__header">
                                    <h3 class="profile-section-card__title"><i class="fas fa-history"></i> Experience Timeline</h3>
                                    <a href="add-experience.php" class="profile-btn profile-btn--sm profile-btn--primary"><i class="fas fa-plus"></i> Add Experience</a>
                                </div>
                                <div class="profile-section-card__body">
                                    <div class="profile-exp-toolbar">
                                        <div class="profile-exp-search">
                                            <i class="fas fa-search"></i>
                                            <input type="search" id="expSearch" placeholder="Search experiences..." aria-label="Search work experiences">
                                        </div>
                                    </div>
                                    <?php if (empty($experiences)): ?>
                                        <div class="profile-empty">
                                            <div class="profile-empty__icon"><i class="fas fa-briefcase"></i></div>
                                            <h4 class="profile-empty__title">No Work Experience Yet</h4>
                                            <p class="profile-empty__text">Add your work experience to strengthen your applications.</p>
                                            <a href="add-experience.php" class="profile-btn profile-btn--primary"><i class="fas fa-plus"></i> Add Experience</a>
                                        </div>
                                    <?php else: ?>
                                        <div class="profile-timeline">
                                            <?php foreach ($experiences as $exp): ?>
                                            <div class="profile-timeline-item">
                                                <div class="profile-timeline-item__card">
                                                    <div class="profile-timeline-item__year"><?= date('Y', strtotime($exp['start_date'])) ?></div>
                                                    <div class="profile-timeline-item__title"><?= htmlspecialchars($exp['position_title']) ?></div>
                                                    <div class="profile-timeline-item__company"><i class="fas fa-building"></i> <?= htmlspecialchars($exp['company_name']) ?></div>
                                                    <div class="profile-timeline-item__dates">
                                                        <i class="fas fa-calendar"></i>
                                                        <?= date('M Y', strtotime($exp['start_date'])) ?> – <?= $exp['is_current'] ? 'Present' : date('M Y', strtotime($exp['end_date'])) ?>
                                                        <span class="profile-badge profile-badge--verified"><?= number_format($exp['years_duration'], 1) ?> yrs</span>
                                                    </div>
                                                    <?php if ($exp['description']): ?>
                                                    <p style="font-size:0.8125rem;color:var(--profile-text-muted);margin-top:10px;line-height:1.5;"><?= nl2br(htmlspecialchars($exp['description'])) ?></p>
                                                    <?php endif; ?>
                                                    <div class="profile-timeline-item__actions">
                                                        <a href="edit-experience.php?id=<?= $exp['id'] ?>" class="profile-btn profile-btn--sm profile-btn--secondary" title="Edit"><i class="fas fa-edit"></i> Edit</a>
                                                        <a href="../actions/delete-experience.php?id=<?= $exp['id'] ?>" class="profile-btn profile-btn--sm profile-btn--danger-outline confirm-delete-experience" data-confirm="Delete this work experience?" title="Delete"><i class="fas fa-trash"></i> Delete</a>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($totalPages > 1): ?>
                                    <div class="profile-pagination">
                                        <?php if ($page > 1): ?>
                                            <a href="?page=<?= $page - 1 ?>" class="profile-btn profile-btn--sm profile-btn--secondary"><i class="fas fa-chevron-left"></i> Previous</a>
                                        <?php endif; ?>
                                        <span style="font-size:0.875rem;color:var(--profile-text-muted);">Page <?= $page ?> of <?= $totalPages ?> (<?= $totalExperiences ?> total)</span>
                                        <?php if ($page < $totalPages): ?>
                                            <a href="?page=<?= $page + 1 ?>" class="profile-btn profile-btn--sm profile-btn--secondary">Next <i class="fas fa-chevron-right"></i></a>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                                <button type="button" class="profile-btn profile-btn--secondary" data-wizard-prev><i class="fas fa-arrow-left"></i> Back</button>
                                <button type="button" class="profile-btn profile-btn--primary" data-wizard-next>Continue to Eligibility <i class="fas fa-arrow-right"></i></button>
                            </div>
                        </div>

                        <!-- Step 4: Eligibility -->
                        <div class="profile-wizard-panel" id="wizard-panel-eligibility" role="tabpanel">
                            <div class="profile-section-card">
                                <div class="profile-section-card__header">
                                    <h3 class="profile-section-card__title"><i class="fas fa-file-signature"></i> Application Type</h3>
                                </div>
                                <div class="profile-section-card__body">
                                    <?php 
                                    $appTypes = [];
                                    if (isset($user['application_type']) && !empty($user['application_type'])) {
                                        $appTypes = explode(',', $user['application_type']);
                                    }
                                    ?>
                                    <div class="profile-type-cards">
                                        <label class="profile-type-card">
                                            <input type="checkbox" name="application_type[]" value="PROMOTION" <?= in_array('PROMOTION', $appTypes) ? 'checked' : '' ?>>
                                            <div class="profile-type-card__inner">
                                                <div class="profile-type-card__icon"><i class="fas fa-level-up-alt"></i></div>
                                                <strong>Promotion</strong>
                                                <p>For government employees applying for higher or next-in-rank positions at WPU or other agencies.</p>
                                            </div>
                                        </label>
                                        <label class="profile-type-card">
                                            <input type="checkbox" name="application_type[]" value="ENTRY LEVEL" <?= in_array('ENTRY LEVEL', $appTypes) ? 'checked' : '' ?>>
                                            <div class="profile-type-card__inner">
                                                <div class="profile-type-card__icon"><i class="fas fa-seedling"></i></div>
                                                <strong>Entry Level</strong>
                                                <p>For private sector applicants, fresh graduates, and first-time job seekers.</p>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <?php 
                            $showPromotion = in_array('PROMOTION', $appTypes) || !empty($user['ipcr_file_path']);
                            $showEntryLevel = in_array('ENTRY LEVEL', $appTypes) || !empty($user['pe_file_path']);
                            ?>

                            <div class="profile-dynamic-section<?= $showPromotion ? ' expanded' : '' ?>" id="promotion-section">
                                <div class="profile-section-card">
                                    <div class="profile-section-card__header">
                                        <h3 class="profile-section-card__title"><i class="fas fa-chart-bar"></i> For Promotion (IPCR)</h3>
                                    </div>
                                    <div class="profile-section-card__body">
                                        <p class="profile-field-hint">Individual Performance Commitment and Review (IPCR) — PDF only, max 10MB.</p>
                                        <div class="profile-format-card">
                                            <div class="profile-format-card__label">Required Format</div>
                                            <div class="profile-format-card__code">
                                                <span>SURNAME_FIRSTNAME_IPCR_WPUHRMDO.pdf</span>
                                                <button type="button" class="profile-format-card__copy" data-copy-format="SURNAME_FIRSTNAME_IPCR_WPUHRMDO.pdf">Copy</button>
                                            </div>
                                            <p class="profile-field-hint" style="margin-top:8px;">Example: SANTOS_MARIAFE_IPCR_WPUHRMDO.pdf</p>
                                        </div>
                                        <label class="profile-section-label">Uploaded Files</label>
                                        <div class="profile-files-grid">
                                        <?php if (!empty($user['ipcr_file_path'])): 
                                            $ipcrDisplayPath = 'uploads/documents/' . $user['ipcr_file_path'];
                                            $ipcrFullPath = __DIR__ . '/' . $ipcrDisplayPath;
                                            $ipcrFileExists = file_exists($ipcrFullPath);
                                        ?>
                                            <div class="profile-file-card" data-has-file="1">
                                                <div class="profile-file-card__icon profile-file-card__icon--pdf"><i class="fas fa-file-pdf"></i></div>
                                                <div class="profile-file-card__info">
                                                    <div class="profile-file-card__name"><?= htmlspecialchars($user['ipcr_file_path']) ?></div>
                                                    <div class="profile-file-card__meta">PDF Document</div>
                                                    <span class="profile-file-card__status profile-file-card__status--ok"><i class="fas fa-check"></i> Completed</span>
                                                </div>
                                                <div class="profile-file-card__actions">
                                                    <?php if ($ipcrFileExists): ?>
                                                    <a href="#" class="profile-btn profile-btn--sm profile-btn--secondary" data-preview="<?= htmlspecialchars($ipcrDisplayPath) ?>"><i class="fas fa-eye"></i> View</a>
                                                    <a href="<?= htmlspecialchars($ipcrDisplayPath) ?>" download class="profile-btn profile-btn--sm profile-btn--secondary"><i class="fas fa-download"></i></a>
                                                    <?php endif; ?>
                                                    <button type="button" class="profile-btn profile-btn--sm profile-btn--danger-outline" data-confirm="Delete this IPCR file?" data-delete-field="delete_ipcr_file"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </div>
                                            <input type="hidden" id="delete_ipcr_file" name="delete_ipcr_file" value="0">
                                        <?php else: ?>
                                            <div class="profile-empty" style="padding:24px;">
                                                <p class="profile-empty__text">No IPCR document uploaded yet.</p>
                                            </div>
                                        <?php endif; ?>
                                        </div>
                                        <div class="profile-dropzone">
                                            <div class="profile-dropzone__icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                            <div class="profile-dropzone__title">Drop PDF here or Browse Files</div>
                                            <div class="profile-dropzone__subtitle profile-dropzone__selected"></div>
                                            <div class="profile-dropzone__formats">
                                                <span class="profile-dropzone__tag">PDF</span>
                                                <span class="profile-dropzone__tag">Max 10MB</span>
                                            </div>
                                            <input type="file" name="ipcr_file" accept=".pdf,application/pdf" aria-label="Upload IPCR document">
                                            <div class="profile-dropzone__progress"><div class="profile-dropzone__progress-bar"><div class="profile-dropzone__progress-fill"></div></div></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="profile-dynamic-section<?= $showEntryLevel ? ' expanded' : '' ?>" id="entry-level-section">
                                <div class="profile-section-card">
                                    <div class="profile-section-card__header">
                                        <h3 class="profile-section-card__title"><i class="fas fa-star"></i> Entry Level (PE)</h3>
                                    </div>
                                    <div class="profile-section-card__body">
                                        <p class="profile-field-hint">Performance Evaluation (PE) — PDF only, max 10MB.</p>
                                        <div class="profile-format-card">
                                            <div class="profile-format-card__label">Required Format</div>
                                            <div class="profile-format-card__code">
                                                <span>SURNAME_FIRSTNAME_PE_WPUHRMDO.pdf</span>
                                                <button type="button" class="profile-format-card__copy" data-copy-format="SURNAME_FIRSTNAME_PE_WPUHRMDO.pdf">Copy</button>
                                            </div>
                                        </div>
                                        <label class="profile-section-label">Uploaded Files</label>
                                        <div class="profile-files-grid">
                                        <?php if (!empty($user['pe_file_path'])): 
                                            $peDisplayPath = 'uploads/documents/' . $user['pe_file_path'];
                                            $peFullPath = __DIR__ . '/' . $peDisplayPath;
                                            $peFileExists = file_exists($peFullPath);
                                        ?>
                                            <div class="profile-file-card" data-has-file="1">
                                                <div class="profile-file-card__icon profile-file-card__icon--pdf"><i class="fas fa-file-pdf"></i></div>
                                                <div class="profile-file-card__info">
                                                    <div class="profile-file-card__name"><?= htmlspecialchars($user['pe_file_path']) ?></div>
                                                    <span class="profile-file-card__status profile-file-card__status--ok"><i class="fas fa-check"></i> Completed</span>
                                                </div>
                                                <div class="profile-file-card__actions">
                                                    <?php if ($peFileExists): ?>
                                                    <a href="#" class="profile-btn profile-btn--sm profile-btn--secondary" data-preview="<?= htmlspecialchars($peDisplayPath) ?>"><i class="fas fa-eye"></i> View</a>
                                                    <a href="<?= htmlspecialchars($peDisplayPath) ?>" download class="profile-btn profile-btn--sm profile-btn--secondary"><i class="fas fa-download"></i></a>
                                                    <?php endif; ?>
                                                    <button type="button" class="profile-btn profile-btn--sm profile-btn--danger-outline" data-confirm="Delete this PE file?" data-delete-field="delete_pe_file"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </div>
                                            <input type="hidden" id="delete_pe_file" name="delete_pe_file" value="0">
                                        <?php else: ?>
                                            <div class="profile-empty" style="padding:24px;"><p class="profile-empty__text">No PE document uploaded yet.</p></div>
                                        <?php endif; ?>
                                        </div>
                                        <div class="profile-dropzone">
                                            <div class="profile-dropzone__icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                            <div class="profile-dropzone__title">Drop PDF here or Browse Files</div>
                                            <div class="profile-dropzone__subtitle profile-dropzone__selected"></div>
                                            <div class="profile-dropzone__formats"><span class="profile-dropzone__tag">PDF</span><span class="profile-dropzone__tag">Max 10MB</span></div>
                                            <input type="file" name="pe_file" accept=".pdf,application/pdf" aria-label="Upload PE document">
                                            <div class="profile-dropzone__progress"><div class="profile-dropzone__progress-bar"><div class="profile-dropzone__progress-fill"></div></div></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="profile-section-card">
                                <div class="profile-section-card__header">
                                    <h3 class="profile-section-card__title"><i class="fas fa-chalkboard-teacher"></i> Training Programs</h3>
                                </div>
                                <div class="profile-section-card__body">
                                    <div class="profile-field">
                                        <label class="profile-section-label">Training Programs Attended <span class="profile-badge-opt">Optional</span></label>
                                        <p class="profile-field-hint">Title, Organizing Office/Agency, Date — one per line.</p>
                                        <textarea name="training_programs" rows="4" placeholder="Enter each training on a new line. If not applicable, type: NA" data-max-length="2000" style="width:100%;border-radius:12px;padding:12px;border:1.5px solid var(--profile-border);font-family:inherit;"><?= htmlspecialchars($user['training_programs'] ?? '') ?></textarea>
                                    </div>

                                    <h4 class="profile-subsection-title">Training Certificates</h4>
                                    <div class="profile-format-card">
                                        <div class="profile-format-card__label">Required Format</div>
                                        <div class="profile-format-card__code">
                                            <span>SURNAME_FIRSTNAME_TS##_WPUHRMDO</span>
                                            <button type="button" class="profile-format-card__copy" data-copy-format="SURNAME_FIRSTNAME_TS01_WPUHRMDO.pdf">Copy</button>
                                        </div>
                                    </div>
                                    <div class="profile-files-grid">
                                    <?php if (!empty($trainingCertificates)): ?>
                                        <?php foreach ($trainingCertificates as $cert): 
                                            $certFilePath = $cert['file_path'] ?? '';
                                            if (empty($certFilePath)) continue;
                                            $certDisplayPath = 'uploads/documents/' . $certFilePath;
                                            $certFullPath = __DIR__ . '/' . $certDisplayPath;
                                            $certFileExists = file_exists($certFullPath);
                                            $isPdf = strtolower(pathinfo($certFilePath, PATHINFO_EXTENSION)) === 'pdf';
                                            $fileSize = !empty($cert['file_size']) ? round($cert['file_size'] / 1024, 1) . ' KB' : '';
                                        ?>
                                        <div class="profile-file-card" data-has-file="1">
                                            <div class="profile-file-card__icon profile-file-card__icon--<?= $isPdf ? 'pdf' : 'image' ?>"><i class="fas fa-<?= $isPdf ? 'file-pdf' : 'file-image' ?>"></i></div>
                                            <div class="profile-file-card__info">
                                                <div class="profile-file-card__name"><?= htmlspecialchars($cert['file_name'] ?? $certFilePath) ?></div>
                                                <div class="profile-file-card__meta"><?= $fileSize ?></div>
                                                <span class="profile-file-card__status profile-file-card__status--ok"><i class="fas fa-check"></i> Completed</span>
                                            </div>
                                            <div class="profile-file-card__actions">
                                                <?php if ($certFileExists): ?>
                                                <a href="#" class="profile-btn profile-btn--sm profile-btn--secondary" data-preview="<?= htmlspecialchars($certDisplayPath) ?>"><i class="fas fa-eye"></i></a>
                                                <a href="<?= htmlspecialchars($certDisplayPath) ?>" download class="profile-btn profile-btn--sm profile-btn--secondary"><i class="fas fa-download"></i></a>
                                                <?php endif; ?>
                                                <a href="../actions/delete-training-certificate.php?id=<?= $cert['id'] ?>" class="profile-btn profile-btn--sm profile-btn--danger-outline confirm-delete-certificate" data-confirm="Delete this training certificate?"><i class="fas fa-trash"></i></a>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="profile-empty">
                                            <div class="profile-empty__icon"><i class="fas fa-certificate"></i></div>
                                            <h4 class="profile-empty__title">No Certificates Yet</h4>
                                            <p class="profile-empty__text">Upload your first training certificate below.</p>
                                        </div>
                                    <?php endif; ?>
                                    </div>
                                    <div class="profile-dropzone">
                                        <div class="profile-dropzone__icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                        <div class="profile-dropzone__title">Drop files here or Browse</div>
                                        <div class="profile-dropzone__subtitle profile-dropzone__selected"></div>
                                        <div class="profile-dropzone__formats"><span class="profile-dropzone__tag">PDF</span><span class="profile-dropzone__tag">JPEG</span><span class="profile-dropzone__tag">Max 10MB</span></div>
                                        <input type="file" name="training_certificates[]" accept=".pdf,.jpg,.jpeg,application/pdf,image/jpeg" multiple aria-label="Upload training certificates">
                                        <div class="profile-dropzone__progress"><div class="profile-dropzone__progress-bar"><div class="profile-dropzone__progress-fill"></div></div></div>
                                    </div>
                                </div>
                            </div>

                            <div class="profile-section-card">
                                <div class="profile-section-card__header">
                                    <h3 class="profile-section-card__title"><i class="fas fa-id-badge"></i> Career Service Eligibilities</h3>
                                </div>
                                <div class="profile-section-card__body">
                                    <label class="profile-section-label">CSE <span class="profile-badge-req">Required</span></label>
                                    <?php 
                                    $cseValues = [];
                                    if (isset($user['career_service_eligibilities']) && !empty($user['career_service_eligibilities'])) {
                                        $cseValues = explode(',', $user['career_service_eligibilities']);
                                    }
                                    $cseOptions = [
                                        'Professional Eligibility' => 'Professional Eligibility: For first-level and second-level positions.',
                                        'Sub-Professional Eligibility' => 'Sub-Professional Eligibility: Qualifies for first-level positions.',
                                        'Career Service Executive Eligibility (CSE-E)' => 'CSE-E: For high-level executive positions.',
                                        'Bar/Board Eligibility (RA 1080)' => 'Bar/Board Eligibility (RA 1080): For licensed professionals.',
                                    ];
                                    ?>
                                    <div class="profile-cse-list">
                                        <?php foreach ($cseOptions as $val => $desc): ?>
                                        <label class="profile-cse-item">
                                            <input type="checkbox" name="career_service_eligibilities[]" value="<?= htmlspecialchars($val) ?>" <?= in_array($val, $cseValues) ? 'checked' : '' ?>>
                                            <span><?= htmlspecialchars($desc) ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                        <label class="profile-cse-item">
                                            <input type="checkbox" name="career_service_eligibilities[]" value="Other" <?= in_array('Other', $cseValues) ? 'checked' : '' ?> id="cse-other-checkbox">
                                            <span>Other</span>
                                        </label>
                                    </div>
                                    <input type="text" name="career_service_eligibilities_other" class="form-control" id="cse-other-input"
                                           value="<?= htmlspecialchars($user['career_service_eligibilities_other'] ?? '') ?>" 
                                           placeholder="Specify other eligibility"
                                           style="margin-top:12px;width:100%;padding:12px;border-radius:12px;border:1.5px solid var(--profile-border);<?= !in_array('Other', $cseValues) ? 'display:none;' : '' ?>">
                                </div>
                            </div>

                            <div class="profile-section-card">
                                <div class="profile-section-card__header">
                                    <h3 class="profile-section-card__title"><i class="fas fa-wheelchair"></i> Special Needs</h3>
                                </div>
                                <div class="profile-section-card__body">
                                    <textarea name="special_needs" rows="3" placeholder="If not applicable, type: NA" data-max-length="500" style="width:100%;border-radius:12px;padding:12px;border:1.5px solid var(--profile-border);font-family:inherit;"><?= htmlspecialchars($user['special_needs'] ?? '') ?></textarea>
                                    <p class="profile-field-hint">Indicate any condition requiring reasonable accommodation, or NA.</p>
                                </div>
                            </div>

                            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                                <button type="button" class="profile-btn profile-btn--secondary" data-wizard-prev><i class="fas fa-arrow-left"></i> Back</button>
                                <button type="button" class="profile-btn profile-btn--primary" data-wizard-next>Continue to Documents <i class="fas fa-arrow-right"></i></button>
                            </div>
                        </div>

                        <!-- Step 5: Documents -->
                        <div class="profile-wizard-panel" id="wizard-panel-documents" role="tabpanel">
                            <div class="profile-section-card">
                                <div class="profile-section-card__header">
                                    <h3 class="profile-section-card__title"><i class="fas fa-folder-open"></i> Required Documents</h3>
                                </div>
                                <div class="profile-section-card__body">
                            <?php
                            renderDocumentSection(
                                '21',
                                'APPLICATION LETTER',
                                'Applicants are required to upload an Application Letter addressed to:<br><strong>DR. AMABEL S. LIAO<br>University President<br>Western Philippines University<br>Aborlan, Palawan</strong><br><br>The Application Letter must be properly addressed, signed, and clearly readable.',
                                'SURNAME_FIRSTNAME_APLET_WPUHRMDO.pdf',
                                'SANTOS_MARIAFE_APLET_WPUHRMDO.pdf',
                                'application_letter',
                                'APLET',
                                ['pdf'],
                                $documentsByType,
                                false
                            );
                            
                            renderDocumentSection(
                                '22',
                                'PERSONAL DATA SHEET',
                                'CS FORM NO. 212, REVISED 2025<br><br>Applicants are required to upload a fully accomplished CS Form No. 212, <strong>Revised 2025</strong> – Personal Data Sheet (PDS). The form must be properly filled out, signed, and notarized or duly administered by an authorized administering officer. A recent passport-sized photograph must be affixed to the form.',
                                'SURNAME_FIRSTNAME_PDS_WPUHRMDO.pdf',
                                'SANTOS_MARIAFE_PDS_WPUHRMDO.pdf',
                                'personal_data_sheet',
                                'PDS',
                                ['pdf'],
                                $documentsByType,
                                false
                            );
                            
                            renderDocumentSection(
                                '23',
                                'WORK EXPERIENCE SHEET',
                                'Applicants are required to upload a fully accomplished Work Experience <strong>Sheet</strong>, which is an attachment to CS Form No. 212 – Personal Data Sheet (PDS). All relevant work experiences must be completely and accurately indicated in the prescribed form.',
                                'SURNAME_FIRSTNAME_WES_WPUHRMDO.pdf',
                                'SANTOS_MARIAFE_WES_WPUHRMDO.pdf',
                                'work_experience_sheet',
                                'WES',
                                ['pdf'],
                                $documentsByType,
                                false
                            );
                            
                            renderDocumentSection(
                                '24',
                                'TRANSCRIPT OF RECORDS',
                                'Applicants are required to upload a Transcript of Records (TOR) relevant to the position being applied for. The document must be clear, complete, and readable, showing all academic records.',
                                'SURNAME_FIRSTNAME_TOR_WPUHRMDO.pdf',
                                ['SANTOS_MARIAFE_TOR_WPUHRMDO.pdf', 'SANTOS_MARIAFE_TOR_WPUHRMDO.jpg'],
                                'transcript_of_records',
                                'TOR',
                                ['pdf', 'jpg', 'jpeg'],
                                $documentsByType,
                                false
                            );
                            
                            renderDocumentSection(
                                '25',
                                'DIPLOMA',
                                'Applicants are required to upload a Diploma relevant to the position being applied for. The document must be clear, complete, and readable.',
                                'SURNAME_FIRSTNAME_DIP_WPUHRMDO.pdf',
                                ['SANTOS_MARIAFE_DIP_WPUHRMDO.pdf', 'SANTOS_MARIAFE_DIP_WPUHRMDO.jpg'],
                                'diploma',
                                'DIP',
                                ['pdf', 'jpg', 'jpeg'],
                                $documentsByType,
                                false
                            );
                            
                            renderDocumentSection(
                                '26',
                                'CERTIFICATE OF EMPLOYMENT (IF APPLICABLE)',
                                'Applicants are required to upload scanned copies or photocopies of their Certificate(s) of Employment (COE) relevant to the position being applied for, if applicable.',
                                'SURNAME_FIRSTNAME_COE##_WPUHRMDO.pdf',
                                ['SANTOS_MARIAFE_COE01_WPUHRMDO.pdf', 'SANTOS_MARIAFE_COE02_WPUHRMDO.jpg'],
                                'certificate_of_employment',
                                'COE',
                                ['pdf', 'jpg', 'jpeg'],
                                $documentsByType,
                                true
                            );
                            
                            $sreDocs = isset($documentsByType['SRE']) ? $documentsByType['SRE'] : [];
                            ?>
                            <div class="profile-doc-section">
                                <h4 class="profile-doc-section__title">Service Records (IF APPLICABLE)</h4>
                                <div class="profile-format-card">
                                    <div class="profile-format-card__label">Required Format</div>
                                    <div class="profile-format-card__code">
                                        <span>SURNAME_FIRSTNAME_SRE##_WPUHRMDO.pdf</span>
                                        <button type="button" class="profile-format-card__copy" data-copy-format="SURNAME_FIRSTNAME_SRE01_WPUHRMDO.pdf">Copy</button>
                                    </div>
                                </div>
                                <div class="profile-files-grid">
                                <?php if (!empty($sreDocs)): ?>
                                    <?php foreach ($sreDocs as $doc):
                                        $docDisplayPath = 'uploads/documents/' . $doc['file_path'];
                                        $docFullPath = __DIR__ . '/' . $docDisplayPath;
                                        $docFileExists = file_exists($docFullPath);
                                        $isPdf = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION)) === 'pdf';
                                    ?>
                                    <div class="profile-file-card" data-has-file="1">
                                        <div class="profile-file-card__icon profile-file-card__icon--<?= $isPdf ? 'pdf' : 'image' ?>"><i class="fas fa-<?= $isPdf ? 'file-pdf' : 'file-image' ?>"></i></div>
                                        <div class="profile-file-card__info">
                                            <div class="profile-file-card__name"><?= htmlspecialchars($doc['file_name']) ?></div>
                                            <span class="profile-file-card__status profile-file-card__status--ok"><i class="fas fa-check"></i> Completed</span>
                                        </div>
                                        <div class="profile-file-card__actions">
                                            <?php if ($docFileExists): ?>
                                            <a href="#" class="profile-btn profile-btn--sm profile-btn--secondary" data-preview="<?= htmlspecialchars($docDisplayPath) ?>"><i class="fas fa-eye"></i></a>
                                            <a href="<?= htmlspecialchars($docDisplayPath) ?>" download class="profile-btn profile-btn--sm profile-btn--secondary"><i class="fas fa-download"></i></a>
                                            <?php endif; ?>
                                            <a href="../actions/delete-application-document.php?id=<?= $doc['id'] ?>" class="profile-btn profile-btn--sm profile-btn--danger-outline" data-confirm="Delete this document?"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="profile-empty" style="padding:20px;"><p class="profile-empty__text">No service records uploaded.</p></div>
                                <?php endif; ?>
                                </div>
                                <div class="profile-dropzone">
                                    <div class="profile-dropzone__icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                    <div class="profile-dropzone__title">Drop PDF or JPEG here or Browse</div>
                                    <div class="profile-dropzone__subtitle profile-dropzone__selected"></div>
                                    <div class="profile-dropzone__formats"><span class="profile-dropzone__tag">PDF</span><span class="profile-dropzone__tag">JPEG</span><span class="profile-dropzone__tag">Max 10MB</span></div>
                                    <input type="file" name="service_record[]" accept=".pdf,.jpg,.jpeg,application/pdf,image/jpeg" multiple aria-label="Upload service records">
                                    <div class="profile-dropzone__progress"><div class="profile-dropzone__progress-bar"><div class="profile-dropzone__progress-fill"></div></div></div>
                                </div>
                            </div>
                            <?php
                            renderDocumentSection(
                                '27',
                                'PHOTOCOPY OF AUTHENTICATED CERTIFICATE OF ELIGIBILITY/RATING/LICENSE (IF APPLICABLE)',
                                'Applicants are required to upload scanned copies or photocopies of authenticated Certificates of Eligibility, Rating, or Professional License, relevant to the position being applied for, if applicable.',
                                'SURNAME_FIRSTNAME_EL##_WPUHRMDO.pdf',
                                ['SANTOS_MARIAFE_EL01_WPUHRMDO.pdf', 'SANTOS_MARIAFE_EL02_WPUHRMDO.jpg'],
                                'eligibility_license',
                                'EL',
                                ['pdf', 'jpg', 'jpeg'],
                                $documentsByType,
                                true
                            );
                            ?>
                                </div>
                            </div>
                            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                                <button type="button" class="profile-btn profile-btn--secondary" data-wizard-prev><i class="fas fa-arrow-left"></i> Back</button>
                                <button type="button" class="profile-btn profile-btn--primary" data-wizard-next>Review & Submit <i class="fas fa-arrow-right"></i></button>
                            </div>
                        </div>

                        <!-- Step 6: Review & Submit -->
                        <div class="profile-wizard-panel" id="wizard-panel-review" role="tabpanel">
                            <div class="profile-section-card">
                                <div class="profile-section-card__header">
                                    <h3 class="profile-section-card__title"><i class="fas fa-clipboard-check"></i> Review Your Profile</h3>
                                </div>
                                <div class="profile-section-card__body">
                                    <p class="profile-field-hint" style="margin-bottom:20px;">Review your information before saving. All sections remain editable via the navigation panel.</p>
                                    <div class="profile-review-grid" id="profileReviewSummary"></div>
                                </div>
                            </div>

                            <div class="profile-section-card">
                                <div class="profile-section-card__header">
                                    <h3 class="profile-section-card__title"><i class="fas fa-lock"></i> Change Password</h3>
                                </div>
                                <div class="profile-section-card__body">
                                    <p class="profile-field-hint">Leave blank if you don't want to change your password.</p>
                                    <div class="profile-form-grid">
                                        <div class="profile-field">
                                            <div class="profile-floating-label">
                                                <i class="fas fa-key profile-field-icon"></i>
                                                <input type="password" name="password" placeholder=" " autocomplete="new-password">
                                                <label>New Password</label>
                                            </div>
                                        </div>
                                        <div class="profile-field">
                                            <div class="profile-floating-label">
                                                <i class="fas fa-key profile-field-icon"></i>
                                                <input type="password" name="confirm_password" placeholder=" " autocomplete="new-password">
                                                <label>Confirm Password</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                                <button type="button" class="profile-btn profile-btn--secondary" data-wizard-prev><i class="fas fa-arrow-left"></i> Back</button>
                                <button type="submit" class="profile-btn profile-btn--primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </div>

                    </form>
                </div><!-- /.profile-wizard-main -->
            </div><!-- /.profile-wizard -->

            <!-- Sticky Save Bar -->
            <div class="profile-action-bar" id="profileActionBar" role="region" aria-label="Unsaved changes">
                <span class="profile-action-bar__hint"><i class="fas fa-exclamation-circle"></i> You have unsaved changes</span>
                <button type="button" class="profile-btn profile-btn--secondary" data-action-cancel>Cancel</button>
                <button type="submit" form="profileForm" class="profile-btn profile-btn--primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </div>
        
        <!-- Footer with Data Privacy Notice -->
        <footer class="page-footer">
            <div class="footer-content">
                <p class="footer-notice">
                    <i class="fas fa-shield-alt"></i>
                    <strong>Data Privacy Notice:</strong> 
                    Your personal information is collected and processed in accordance with 
                    <strong>Republic Act No. 10173 (Data Privacy Act of 2012)</strong>. 
                    The data you provide will be used solely for employment screening, evaluation, and related administrative purposes. 
                    By using this system, you acknowledge and consent to the processing of your personal data as described in our 
                    <a href="#" id="dataPrivacyPolicyLink" class="privacy-link">Data Privacy Policy</a>.
                </p>
                <p class="footer-copyright">
                    © <?= date('Y') ?> Western Philippines University. All rights reserved.
                </p>
            </div>
        </footer>
    </div>

    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-nav">
        <div class="mobile-nav-grid">
            <a href="dashboard.php" class="mobile-nav-item">
                <i class="fas fa-th-large"></i>
                <span>Home</span>
            </a>
            <a href="positions.php" class="mobile-nav-item">
                <i class="fas fa-briefcase"></i>
                <span>Jobs</span>
            </a>
            <a href="my-applications.php" class="mobile-nav-item">
                <i class="fas fa-file-alt"></i>
                <span>Applications</span>
            </a>
            <a href="profile.php" class="mobile-nav-item active">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </div>
    </nav>

    <!-- Data Privacy Policy Modal -->
    <div id="dataPrivacyModal" class="data-privacy-modal" style="display: none;">
        <div class="modal-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.75); display: flex; align-items: center; justify-content: center; padding: 20px; z-index: 10000;">
            <div class="modal-content" style="background: white; border-radius: 8px; max-width: 700px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); position: relative; z-index: 10001;">
                <div class="modal-header" style="padding: 20px 24px; border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--gray-800); margin: 0; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-shield-alt" style="color: var(--primary-blue);"></i>
                        DATA PRIVACY WAIVER
                    </h3>
                    <button type="button" class="modal-close-btn" id="closeDataPrivacyModal" style="background: none; border: none; font-size: 1.5rem; color: var(--gray-500); cursor: pointer; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 4px; transition: background-color 0.2s;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body" style="padding: 24px;">
                    <div style="line-height: 1.8; color: var(--gray-700); font-size: 0.95rem;">
                        <p style="margin-bottom: 16px;">
                            In accordance with <strong>Republic Act No. 10173</strong>, otherwise known as the <strong>Data Privacy Act of 2012</strong>, and its Implementing Rules and Regulations, I hereby authorize <strong>Western Philippines University (WPU)</strong>, through its authorized offices, to collect, process, store, and use my personal information submitted in connection with my application for employment.
                        </p>
                        <p style="margin-bottom: 16px;">
                            I understand and agree that the personal data I provided, including but not limited to my personal details, educational background, employment history, and other supporting documents, shall be used solely for recruitment, evaluation, selection, and other legitimate human resource–related purposes. I further acknowledge that these data may be shared with authorized personnel and relevant government agencies, as required by law or applicable regulations, including the Civil Service Commission.
                        </p>
                        <p style="margin-bottom: 16px;">
                            I affirm that all information I have provided is true, correct, and complete to the best of my knowledge. I understand that any false, misleading, or incomplete information may be grounds for disqualification from the application process or other appropriate administrative action.
                        </p>
                        <p style="margin-bottom: 0;">
                            By submitting my application, I signify my voluntary consent to the processing of my personal data under the terms stated above and recognize my rights as a data subject under the Data Privacy Act of 2012.
                        </p>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid var(--gray-200); display: flex; justify-content: flex-end;">
                    <button type="button" class="btn btn-primary" id="closeDataPrivacyModalBtn">
                        <i class="fas fa-check"></i> I Understand
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Styles -->
    <style>
        .page-footer {
            width: 100%;
            padding: 24px;
            margin-top: auto;
            margin-bottom: 0;
            border-top: 1px solid var(--gray-200);
            background: var(--gray-50);
            box-sizing: border-box;
        }
        
        .footer-content {
            width: 100%;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        .footer-notice {
            margin: 0 auto;
            font-size: 0.875rem;
            color: var(--gray-600);
            text-align: center;
            line-height: 1.6;
            word-wrap: break-word;
            overflow-wrap: break-word;
            hyphens: auto;
            padding: 0;
            width: 100%;
            display: block;
        }
        
        .footer-notice i {
            color: var(--primary-blue);
            margin-right: 8px;
        }
        
        .footer-notice .privacy-link {
            color: var(--primary-blue);
            text-decoration: underline;
            cursor: pointer;
            transition: color 0.2s ease;
        }
        
        .footer-notice .privacy-link:hover {
            color: var(--primary-dark);
        }
        
        .footer-copyright {
            margin: 8px auto 0 auto;
            font-size: 0.75rem;
            color: var(--gray-500);
            text-align: center;
            width: 100%;
            display: block;
        }
        
        @media (max-width: 768px) {
            .page-footer {
                padding: 12px;
                margin-top: 24px;
            }
            
            .footer-content {
                width: 100%;
            }
            
            .footer-notice {
                font-size: 0.8125rem;
                line-height: 1.5;
            }
            
            .footer-copyright {
                font-size: 0.6875rem;
            }
        }
        
        @media (max-width: 480px) {
            .page-footer {
                padding: 8px;
            }
            
            .footer-notice {
                font-size: 0.75rem;
                line-height: 1.4;
            }
        }
        
        .modal-close-btn:hover {
            background-color: var(--gray-100) !important;
        }
        
        .data-privacy-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 10000;
        }
    </style>

    <script src="../../assets/js/toast.js"></script>
    <script src="../../assets/js/form-submission-manager.js"></script>
    <script src="../../assets/js/office-theme.js"></script>
    <script src="../../assets/js/applicant-theme.js"></script>
    <script src="../../assets/js/profile.js"></script>
    <script>
        // Data Privacy Modal (preserved from original)
        function openDataPrivacyModal() {
            const modal = document.getElementById('dataPrivacyModal');
            if (modal) { modal.style.display = 'block'; document.body.style.overflow = 'hidden'; }
        }
        function closeDataPrivacyModal() {
            const modal = document.getElementById('dataPrivacyModal');
            if (modal) { modal.style.display = 'none'; document.body.style.overflow = ''; }
        }
        document.addEventListener('DOMContentLoaded', function() {
            const dataPrivacyLink = document.getElementById('dataPrivacyPolicyLink');
            const closeModalBtn = document.getElementById('closeDataPrivacyModal');
            const closeModalBtn2 = document.getElementById('closeDataPrivacyModalBtn');
            const modal = document.getElementById('dataPrivacyModal');
            const modalOverlay = modal ? modal.querySelector('.modal-overlay') : null;
            if (dataPrivacyLink) dataPrivacyLink.addEventListener('click', e => { e.preventDefault(); openDataPrivacyModal(); });
            if (closeModalBtn) closeModalBtn.addEventListener('click', e => { e.preventDefault(); closeDataPrivacyModal(); });
            if (closeModalBtn2) closeModalBtn2.addEventListener('click', e => { e.preventDefault(); closeDataPrivacyModal(); });
            if (modalOverlay) modalOverlay.addEventListener('click', e => { if (e.target === modalOverlay) closeDataPrivacyModal(); });
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape') {
                    const m = document.getElementById('dataPrivacyModal');
                    if (m && m.style.display === 'block') closeDataPrivacyModal();
                }
            });
        });
    </script>
    <?php include '../../includes/pwa-scripts.php'; ?>
</body>
</html>
