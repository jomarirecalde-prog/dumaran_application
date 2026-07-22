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
    <?php include '../../includes/pwa-header.php'; ?>
    <style>
        @media (max-width: 768px) {
            .content > div[style*="grid-template-columns"] {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
       
</head>
<body>
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">
                <img src="../../assets/logo.png" alt="WPU Logo" style="height: 32px; width: auto;">
                <span>WPU Jobs</span>
            </a>
        </div>
        
        <nav class="sidebar-nav">
         <a href="profile.php" class="sidebar-nav-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
            <a href="dashboard.php" class="sidebar-nav-item active">
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
                <a href="../../auth/logout.php" class="topbar-btn confirm-logout" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content">
            <!-- Profile Header -->
            <div class="card" style="background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%); border: none; color: white; margin-bottom: 20px;">
                <div class="card-body" style="padding: 32px; text-align: center;">
                    <div style="width: 100px; height: 100px; border-radius: 50%; background: white; color: var(--primary-blue); display: flex; align-items: center; justify-content: center; font-size: 3rem; margin: 0 auto 16px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);">
                        <i class="fas fa-user"></i>
                    </div>
                    <h2 style="font-size: 1.75rem; font-weight: 700; margin-bottom: 8px;"><?= htmlspecialchars($user['full_name']) ?></h2>
                    <p style="opacity: 0.9; margin-bottom: 0;"><i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email']) ?></p>
                </div>
            </div>

            <!-- Experience Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-value"><?= number_format($totalExperience, 1) ?></div>
                            <div class="stat-card-label">Years Experience</div>
                        </div>
                        <div class="stat-card-icon green">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-value"><?= count($experiences) ?></div>
                            <div class="stat-card-label">Work Experiences</div>
                        </div>
                        <div class="stat-card-icon blue">
                            <i class="fas fa-briefcase"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (isset($tablesExist) && !$tablesExist): ?>
                <div class="alert alert-warning" style="background-color: #fef3c7; border-color: #f59e0b; color: #92400e;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Database Tables Missing!</strong> 
                        The training certificates and application documents tables are not set up yet. 
                        Please <a href="../../migrations/run_migrations.php" style="color: #92400e; text-decoration: underline; font-weight: 600;">run the migration script</a> to create these tables before uploading files.
                        Your profile information will still save, but file uploads won't work until the tables are created.
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($success === 'profile_updated'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><strong>Success!</strong> Profile updated successfully!</div>
                </div>
            <?php elseif ($success === 'certificate_deleted'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><strong>Success!</strong> Training certificate deleted successfully!</div>
                </div>
            <?php elseif ($success === 'document_deleted'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><strong>Success!</strong> Document deleted successfully!</div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><strong>Error!</strong> <?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <!-- Profile Information Form -->
            <div class="card" style="margin-bottom: 24px;">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-user-edit"></i> Profile Information
                        </h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" class="prevent-double-submit" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <?php FormTokenManager::renderFormTokenInput('update_profile'); ?>
                            
                            <!-- 3. NAME -->
                            <div class="form-group">
                                <label class="form-label"><strong> NAME *</strong></label>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Format: SURNAME, FIRST NAME, MIDDLE NAME (Capital Letters)</p>
                                <input type="text" name="full_name" class="form-control" style="text-transform: uppercase;"
                                       value="<?= htmlspecialchars(strtoupper($user['full_name'] ?? '')) ?>" 
                                       placeholder="SANTOS, MARIA FE, REYES" required>
                                <small class="form-text text-muted">Ensure your name matches official documents exactly.</small>
                            </div>
                            
                            <!-- 4. AGE -->
                            <div class="form-group">
                                <label class="form-label"><strong> AGE *</strong></label>
                                <input type="number" name="age" class="form-control" 
                                       value="<?= htmlspecialchars($user['age'] ?? '') ?>" 
                                       min="1" max="120" placeholder="Enter your age" required>
                            </div>
                            
                            <!-- 5. SEX -->
                            <div class="form-group">
                                <label class="form-label"><strong>SEX *</strong></label>
                                <div style="display: flex; gap: 24px; margin-top: 8px;">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="radio" name="sex" value="Male" <?= (isset($user['sex']) && $user['sex'] === 'Male') ? 'checked' : '' ?> required>
                                        <span>Male</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="radio" name="sex" value="Female" <?= (isset($user['sex']) && $user['sex'] === 'Female') ? 'checked' : '' ?> required>
                                        <span>Female</span>
                                    </label>
                                </div>
                                <small class="form-text text-muted">Mark only one oval.</small>
                            </div>
                            
                            <!-- 6. CIVIL STATUS -->
                            <div class="form-group">
                                <label class="form-label"><strong>CIVIL STATUS *</strong></label>
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 8px;">
                                    <?php 
                                    $civilStatuses = ['Married', 'Single', 'Separated', 'Widowed', 'Annuled', 'Solo Parent'];
                                    foreach ($civilStatuses as $status): 
                                    ?>
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="radio" name="civil_status" value="<?= htmlspecialchars($status) ?>" 
                                               <?= (isset($user['civil_status']) && $user['civil_status'] === $status) ? 'checked' : '' ?> required>
                                        <span><?= htmlspecialchars($status) ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                                <small class="form-text text-muted">Mark only one oval.</small>
                            </div>
                            
                            <!-- 7. ADDRESS -->
                            <div class="form-group">
                                <label class="form-label"><strong>ADDRESS *</strong></label>
                                <textarea name="address" class="form-control" rows="3" required><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                            </div>
                            
                            <hr style="margin: 32px 0; border-color: var(--gray-200);">
                            
                            <!-- EDUCATIONAL BACKGROUND -->
                            <h4 style="margin-bottom: 20px; font-size: 1.25rem; font-weight: 700;">EDUCATIONAL BACKGROUND</h4>
                            <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 20px;">Please provide the details of your educational attainment. If a field does not apply to you, kindly indicate <strong>Not Applicable (NA)</strong>.</p>

                            
                            <!-- 8. HIGHEST EDUCATIONAL ATTAINMENT -->
                            <div class="form-group">
                                <label class="form-label"><strong>HIGHEST EDUCATIONAL ATTAINMENT</strong></label>
                                <select name="highest_educational_attainment" class="form-control">
                                    <option value="">Select highest educational attainment</option>
                                    <option value="Elementary Level" <?= (isset($user['highest_educational_attainment']) && $user['highest_educational_attainment'] === 'Elementary Level') ? 'selected' : '' ?>>Elementary Level</option>
                                    <option value="Elementary Graduate" <?= (isset($user['highest_educational_attainment']) && $user['highest_educational_attainment'] === 'Elementary Graduate') ? 'selected' : '' ?>>Elementary Graduate</option>
                                    <option value="Secondary/ High School Level" <?= (isset($user['highest_educational_attainment']) && $user['highest_educational_attainment'] === 'Secondary/ High School Level') ? 'selected' : '' ?>>Secondary/ High School Level</option>
                                    <option value="Secondary/ High School Graduate" <?= (isset($user['highest_educational_attainment']) && $user['highest_educational_attainment'] === 'Secondary/ High School Graduate') ? 'selected' : '' ?>>Secondary/ High School Graduate</option>
                                    <option value="College Level" <?= (isset($user['highest_educational_attainment']) && $user['highest_educational_attainment'] === 'College Level') ? 'selected' : '' ?>>College Level</option>
                                    <option value="College Graduate" <?= (isset($user['highest_educational_attainment']) && $user['highest_educational_attainment'] === 'College Graduate') ? 'selected' : '' ?>>College Graduate</option>
                                    <option value="Master's Degree Holder" <?= (isset($user['highest_educational_attainment']) && $user['highest_educational_attainment'] === 'Master\'s Degree Holder') ? 'selected' : '' ?>>Master's Degree Holder</option>
                                    <option value="Doctoral Degree Holder" <?= (isset($user['highest_educational_attainment']) && $user['highest_educational_attainment'] === 'Doctoral Degree Holder') ? 'selected' : '' ?>>Doctoral Degree Holder</option>
                                </select>
                                <small class="form-text text-muted">Mark only one oval.</small>
                            </div>
                            
                            <!-- 9. BACHELOR'S DEGREE -->
                            <div class="form-group">
                                <label class="form-label"><strong>BACHELOR'S DEGREE/COURSE</strong></label>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Format: Degree Program, School/University, Year of Completion</p>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Example: Bachelor of Science in Business Administration, Western Philippines University, 2018</p>
                                <textarea name="bachelors_degree" class="form-control" rows="2" placeholder="If not applicable, type: NA"><?= htmlspecialchars($user['bachelors_degree'] ?? '') ?></textarea>
                            </div>
                            
                            <!-- 10. MASTER'S DEGREE -->
                            <div class="form-group">
                                <label class="form-label"><strong>MASTER'S DEGREE/PROGRAM</strong></label>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Format: Degree Program, School/University, Year of Completion</p>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Example: Master of Public Administration, Rizal State University, 2022</p>
                                <textarea name="masters_degree" class="form-control" rows="2" placeholder="If not applicable, type: NA"><?= htmlspecialchars($user['masters_degree'] ?? '') ?></textarea>
                            </div>
                            
                            <!-- 11. DOCTORATE DEGREE -->
                            <div class="form-group">
                                <label class="form-label"><strong>DOCTORATE DEGREE/PROGRAM</strong></label>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Format: Degree Program, School/University, Year of Completion</p>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Example: Doctor of Philosophy in Educational Management, Palawan State University, 2025</p>
                                <textarea name="doctorate_degree" class="form-control" rows="2" placeholder="If not applicable, type: NA"><?= htmlspecialchars($user['doctorate_degree'] ?? '') ?></textarea>
                            </div>
                            
                            <!-- 12. CONTACT INFORMATION -->
                            <div class="form-group">
                                <label class="form-label"><strong>CONTACT INFORMATION (Mobile Number and E-mail Address)</strong></label>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Format: 09156161822, santosmf@gmail.com</p>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                    <input type="tel" name="phone" class="form-control" 
                                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                                           placeholder="Mobile Number">
                                    <input type="email" name="email" class="form-control" 
                                           value="<?= htmlspecialchars($user['email'] ?? '') ?>" 
                                           placeholder="Email Address" required>
                                </div>
                            </div>
                            
                            <!-- 13. TYPE OF APPLICATION -->
                            <div class="form-group">
                                <label class="form-label"><strong>TYPE OF APPLICATION</strong></label>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">(Please select the option that applies to you.)</p>
                                <div style="margin-top: 8px;">
                                    <?php 
                                    $appTypes = [];
                                    if (isset($user['application_type']) && !empty($user['application_type'])) {
                                        $appTypes = explode(',', $user['application_type']);
                                    }
                                    ?>
                                    <label style="display: flex; align-items: start; gap: 8px; cursor: pointer; margin-bottom: 12px;">
                                        <input type="checkbox" name="application_type[]" value="PROMOTION" 
                                               <?= in_array('PROMOTION', $appTypes) ? 'checked' : '' ?>>
                                        <div>
                                            <strong>PROMOTION:</strong>
                                            <small style="display: block; color: var(--gray-600); margin-top: 4px;">
                                                Applicants who are currently employed in the government sector, including: Existing Western Philippines University (WPU) employees applying for a higher or next-in-rank position; Government employees within WPU or from other government agencies applying for a higher position; and Government employees from other agencies seeking appointment to a position at Western Philippines University.
                                            </small>
                                        </div>
                                    </label>
                                    <label style="display: flex; align-items: start; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" name="application_type[]" value="ENTRY LEVEL" 
                                               <?= in_array('ENTRY LEVEL', $appTypes) ? 'checked' : '' ?>>
                                        <div>
                                            <strong>ENTRY LEVEL:</strong>
                                            <small style="display: block; color: var(--gray-600); margin-top: 4px;">
                                                Applicants who are: From the private sector; or Individuals with no prior employment background, including fresh graduates and first-time job seekers.
                                            </small>
                                        </div>
                                    </label>
                                </div>
                                <small class="form-text text-muted">Check all that apply.</small>
                            </div>
                            
                            <!-- 14. FOR PROMOTION -->
                            <?php 
                            $showPromotion = false;
                            if (isset($user['application_type']) && !empty($user['application_type'])) {
                                $appTypesCheck = explode(',', $user['application_type']);
                                $showPromotion = in_array('PROMOTION', $appTypesCheck);
                            }
                            // Always show section if file exists, even if checkbox isn't checked
                            if (!empty($user['ipcr_file_path'])) {
                                $showPromotion = true;
                            }
                            ?>
                            <div class="form-group" id="promotion-section" style="display: <?= $showPromotion ? 'block' : 'none' ?>;">
                                <label class="form-label"><strong>FOR PROMOTION</strong></label>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Provide the performance rating in the last rating period.</p>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">E.g. Individual Performance Commitment and Review(IPCR)</p>
                                
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Applicants are required to save their completed documents in PDF format. The file name must follow this exact format:</p>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px; font-family: monospace; background: var(--gray-50); padding: 8px; border-radius: 4px;">SURNAME_FIRSTNAME_IPCR_WPUHRMDO</p>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 16px;">Example: SANTOS_MARIAFE_IPCR_WPUHRMDO.pdf</p>
                                <p style="font-size: 0.875rem; color: var(--danger); margin-bottom: 16px; font-weight: 600;">Failure to follow this file naming convention may result in difficulty in processing your application.</p>
                                
                                <div style="margin-bottom: 16px;">
                                    <label class="form-label"><strong>Files submitted:</strong></label>
                                    <?php if (!empty($user['ipcr_file_path'])): 
                                        $ipcrDisplayPath = 'uploads/documents/' . $user['ipcr_file_path'];
                                        $ipcrFullPath = __DIR__ . '/' . $ipcrDisplayPath;
                                        $ipcrFileExists = file_exists($ipcrFullPath);
                                    ?>
                                        <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--gray-50); border-radius: 4px; margin-top: 8px;">
                                            <i class="fas fa-file-pdf" style="color: var(--danger); font-size: 1.5rem;"></i>
                                            <div style="flex: 1;">
                                                <div style="font-weight: 600; color: var(--gray-900);"><?= htmlspecialchars($user['ipcr_file_path']) ?></div>
                                                <?php if ($ipcrFileExists): ?>
                                                    <a href="<?= htmlspecialchars($ipcrDisplayPath) ?>" target="_blank" style="font-size: 0.875rem; color: var(--primary-blue); text-decoration: none;">
                                                        <i class="fas fa-external-link-alt"></i> View File
                                                    </a>
                                                <?php else: ?>
                                                    <span style="font-size: 0.875rem; color: var(--gray-500);">File not found on server</span>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline" style="color: var(--danger); border-color: var(--danger);" onclick="if(confirm('Are you sure you want to delete this file?')) { document.getElementById('delete_ipcr_file').value = '1'; this.form.submit(); }">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                            <input type="hidden" id="delete_ipcr_file" name="delete_ipcr_file" value="0">
                                        </div>
                                    <?php else: ?>
                                        <p style="font-size: 0.875rem; color: var(--gray-500); margin-top: 8px; font-style: italic;">No file uploaded</p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Upload IPCR Document (PDF only)</label>
                                    <input type="file" name="ipcr_file" class="form-control" accept=".pdf,application/pdf">
                                    <small class="form-text text-muted">Maximum file size: 10MB. The file will be automatically renamed according to the required format.</small>
                                </div>
                            </div>
                            
                            <!-- 15. ENTRY LEVEL -->
                            <?php 
                            $showEntryLevel = false;
                            if (isset($user['application_type']) && !empty($user['application_type'])) {
                                $appTypes = explode(',', $user['application_type']);
                                $showEntryLevel = in_array('ENTRY LEVEL', $appTypes);
                            }
                            // Always show section if file exists, even if checkbox isn't checked
                            if (!empty($user['pe_file_path'])) {
                                $showEntryLevel = true;
                            }
                            ?>
                            <div class="form-group" id="entry-level-section" style="display: <?= $showEntryLevel ? 'block' : 'none' ?>;">
                                <label class="form-label"><strong>ENTRY LEVEL</strong></label>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Provide the performance rating in the last rating period.</p>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">E.g. Performance Evaluation/ Rating/ Key Performance Indicator Evaluation (PE)</p>
                                
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Applicants are required to save their completed documents in PDF format. The file name must follow this exact format:</p>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px; font-family: monospace; background: var(--gray-50); padding: 8px; border-radius: 4px;">SURNAME_FIRSTNAME_PE_WPUHRMDO</p>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 16px;">Example: SANTOS_MARIAFE_PE_WPUHRMDO.pdf</p>
                                <p style="font-size: 0.875rem; color: var(--danger); margin-bottom: 16px; font-weight: 600;">Failure to follow this file naming convention may result in difficulty in processing your application.</p>
                                
                                <div style="margin-bottom: 16px;">
                                    <label class="form-label"><strong>Files submitted:</strong></label>
                                    <?php if (!empty($user['pe_file_path'])): 
                                        $peDisplayPath = 'uploads/documents/' . $user['pe_file_path'];
                                        $peFullPath = __DIR__ . '/' . $peDisplayPath;
                                        $peFileExists = file_exists($peFullPath);
                                    ?>
                                        <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--gray-50); border-radius: 4px; margin-top: 8px;">
                                            <i class="fas fa-file-pdf" style="color: var(--danger); font-size: 1.5rem;"></i>
                                            <div style="flex: 1;">
                                                <div style="font-weight: 600; color: var(--gray-900);"><?= htmlspecialchars($user['pe_file_path']) ?></div>
                                                <?php if ($peFileExists): ?>
                                                    <a href="<?= htmlspecialchars($peDisplayPath) ?>" target="_blank" style="font-size: 0.875rem; color: var(--primary-blue); text-decoration: none;">
                                                        <i class="fas fa-external-link-alt"></i> View File
                                                    </a>
                                                <?php else: ?>
                                                    <span style="font-size: 0.875rem; color: var(--gray-500);">File not found on server</span>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline" style="color: var(--danger); border-color: var(--danger);" onclick="if(confirm('Are you sure you want to delete this file?')) { document.getElementById('delete_pe_file').value = '1'; this.form.submit(); }">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                            <input type="hidden" id="delete_pe_file" name="delete_pe_file" value="0">
                                        </div>
                                    <?php else: ?>
                                        <p style="font-size: 0.875rem; color: var(--gray-500); margin-top: 8px; font-style: italic;">No file uploaded</p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Upload PE Document (PDF only)</label>
                                    <input type="file" name="pe_file" class="form-control" accept=".pdf,application/pdf">
                                    <small class="form-text text-muted">Maximum file size: 10MB. The file will be automatically renamed according to the required format.</small>
                                </div>
                            </div>
                            
                            <!-- 16. POSITION BEING APPLIED FOR -->
                            
                            
                            <hr style="margin: 32px 0; border-color: var(--gray-200);">
                            
                            <h4 style="margin-bottom: 12px; font-size: 1.25rem; font-weight: 700;">TRAINING PROGRAMS ATTENDED</h4>
                            <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 20px;">Applicants are required to provide a list of training programs, seminars, workshops, or webinars they have attended that are relevant to the position they are applying for. Each entry must follow the format below: <strong>Title of Training/Seminar/Workshop, Organizing Office/Agency, Date of Attendance</strong></p>
                            
                            <!-- 17. TRAINING PROGRAMS ATTENDED -->
                            <div class="form-group">
                                <label class="form-label"><strong>TRAINING PROGRAMS ATTENDED</strong></label>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Format: Title of Training/Seminar/Workshop, Organizing Office/Agency, Date of Attendance</p>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Example: Values Restoration Officers Training Seminar, Provincial HR Management, Sept. 23–25, 2024</p>
                                <textarea name="training_programs" class="form-control" rows="4" placeholder="Enter each training on a new line. If not applicable, type: NA"><?= htmlspecialchars($user['training_programs'] ?? '') ?></textarea>
                            </div>
                            
                            <!-- 18. PHOTOCOPY OF SEMINARS AND TRAININGS ATTENDED -->
                            <div class="form-group">
                                <label class="form-label"><strong> PHOTOCOPY OF SEMINARS AND TRAININGS ATTENDED RELEVANT TO THE POSITION (IF APPLICABLE)</strong></label>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Applicants are required to upload scanned copies or photocopies of certificates of seminars, trainings, workshops, or webinars attended that are relevant to the position being applied for, if applicable. All documents must be clear, complete, and readable.</p>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Each certificate must be saved in PDF or JPEG file format and renamed using the following file naming convention:</p>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px; font-family: monospace; background: var(--gray-50); padding: 8px; border-radius: 4px;">Format: SURNAME_FIRSTNAME_TS##_WPUHRMDO</p>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Examples:</p>
                                <ul style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 16px; padding-left: 24px;">
                                    <li>SANTOS_MARIAFE_TS01_WPUHRMDO.pdf</li>
                                    <li>SANTOS_MARIAFE_TS02_WPUHRMDO.jpg</li>
                                    <li>SANTOS_MARIAFE_TS03_WPUHRMDO.pdf</li>
                                </ul>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 16px;">Applicants are advised to ensure that all uploaded files follow the prescribed format and naming convention. Failure to comply with these requirements may result in delays or affect the evaluation of the application.</p>
                                
                                <div style="margin-bottom: 16px;">
                                    <label class="form-label"><strong>Files submitted:</strong></label>
                                    <?php if (!empty($trainingCertificates)): ?>
                                        <?php foreach ($trainingCertificates as $index => $cert): 
                                            // Ensure file_path is not null
                                            $certFilePath = $cert['file_path'] ?? '';
                                            if (empty($certFilePath)) continue; // Skip if no valid file path
                                            
                                            $certDisplayPath = 'uploads/documents/' . $certFilePath;
                                            $certFullPath = __DIR__ . '/' . $certDisplayPath;
                                            $certFileExists = file_exists($certFullPath);
                                            $isPdf = strtolower(pathinfo($certFilePath, PATHINFO_EXTENSION)) === 'pdf';
                                        ?>
                                            <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--gray-50); border-radius: 4px; margin-top: 8px;">
                                                <i class="fas fa-<?= $isPdf ? 'file-pdf' : 'file-image' ?>" style="color: var(--<?= $isPdf ? 'danger' : 'success' ?>); font-size: 1.5rem;"></i>
                                                <div style="flex: 1;">
                                                    <div style="font-weight: 600; color: var(--gray-900);"><?= htmlspecialchars($cert['file_name'] ?? $certFilePath) ?></div>
                                                    <?php if ($certFileExists): ?>
                                                        <a href="<?= htmlspecialchars($certDisplayPath) ?>" target="_blank" style="font-size: 0.875rem; color: var(--primary-blue); text-decoration: none;">
                                                            <i class="fas fa-external-link-alt"></i> View File
                                                        </a>
                                                    <?php else: ?>
                                                        <span style="font-size: 0.875rem; color: var(--gray-500);">File not found on server</span>
                                                    <?php endif; ?>
                                                </div>
                                                <a href="../actions/delete-training-certificate.php?id=<?= $cert['id'] ?>" 
                                                   class="btn btn-sm btn-outline confirm-delete-certificate" 
                                                   style="color: var(--danger); border-color: var(--danger);" 
                                                   title="Delete"
                                                   onclick="return confirm('Are you sure you want to delete this training certificate?');">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p style="font-size: 0.875rem; color: var(--gray-500); margin-top: 8px; font-style: italic;">No files uploaded</p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Upload Training/Seminar Certificates (PDF or JPEG only)</label>
                                    <input type="file" name="training_certificates[]" class="form-control" accept=".pdf,.jpg,.jpeg,application/pdf,image/jpeg" multiple>
                                    <small class="form-text text-muted">Maximum file size: 10MB per file. Multiple files can be uploaded. Files will be automatically renamed according to the required format (TS01, TS02, etc.).</small>
                                </div>
                            </div>
                            
                            <!-- 19. WORK EXPERIENCE -->
                            <div class="form-group">
                                <label class="form-label"><strong>WORK EXPERIENCE *</strong></label>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Format: Position/Title, Company/Organization, Inclusive Dates of Employment</p>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Example: HR Manager, MSPP Store–PPC, Sept. 11, 2017 to March 10, 2019</p>
                                <?php
                                // Generate work experience text from stored experiences
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
                                <textarea name="work_experience_display" class="form-control" rows="4" readonly><?= $workExpText ?></textarea>
                                <small class="form-text text-muted">Work experiences are managed in the "Work Experience" section below.</small>
                            </div>
                            
                            <!-- 20. CAREER SERVICE ELIGIBILITIES -->
                            <div class="form-group">
                                <label class="form-label"><strong>CAREER SERVICE ELIGIBILITIES (CSE) *</strong></label>
                                <?php 
                                $cseValues = [];
                                if (isset($user['career_service_eligibilities']) && !empty($user['career_service_eligibilities'])) {
                                    $cseValues = explode(',', $user['career_service_eligibilities']);
                                }
                                ?>
                                <div style="margin-top: 8px;">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">
                                        <input type="checkbox" name="career_service_eligibilities[]" value="Professional Eligibility" 
                                               <?= in_array('Professional Eligibility', $cseValues) ? 'checked' : '' ?>>
                                        <span>Professional Eligibility: For first-level (clerical, trades) and second-level (technical, supervisory, managerial) positions.</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">
                                        <input type="checkbox" name="career_service_eligibilities[]" value="Sub-Professional Eligibility" 
                                               <?= in_array('Sub-Professional Eligibility', $cseValues) ? 'checked' : '' ?>>
                                        <span>Sub-Professional Eligibility: Qualifies for first-level positions (clerical, custodial).</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">
                                        <input type="checkbox" name="career_service_eligibilities[]" value="Career Service Executive Eligibility (CSE-E)" 
                                               <?= in_array('Career Service Executive Eligibility (CSE-E)', $cseValues) ? 'checked' : '' ?>>
                                        <span>Career Service Executive Eligibility (CSE-E): For high-level executive positions.</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">
                                        <input type="checkbox" name="career_service_eligibilities[]" value="Bar/Board Eligibility (RA 1080)" 
                                               <?= in_array('Bar/Board Eligibility (RA 1080)', $cseValues) ? 'checked' : '' ?>>
                                        <span>Bar/Board Eligibility (RA 1080): For licensed professionals (e.g., lawyers, doctors, engineers).</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" name="career_service_eligibilities[]" value="Other" 
                                               <?= in_array('Other', $cseValues) ? 'checked' : '' ?> id="cse-other-checkbox">
                                        <span>Other:</span>
                                    </label>
                                    <input type="text" name="career_service_eligibilities_other" class="form-control" 
                                           value="<?= htmlspecialchars($user['career_service_eligibilities_other'] ?? '') ?>" 
                                           placeholder="Specify other eligibility" 
                                           style="margin-top: 8px; <?= !in_array('Other', $cseValues) ? 'display: none;' : '' ?>" 
                                           id="cse-other-input">
                                </div>
                                <small class="form-text text-muted">Check all that apply.</small>
                            </div>
                            
                            <!-- 28. SPECIAL NEEDS -->
                            <div class="form-group">
                                <label class="form-label"><strong>SPECIAL NEEDS</strong></label>
                                <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Applicants may indicate any medical condition, disability, or special circumstance that may require reasonable accommodation. If not applicable, kindly indicate Not Applicable (NA).</p>
                                <textarea name="special_needs" class="form-control" rows="3" placeholder="If not applicable, type: NA"><?= htmlspecialchars($user['special_needs'] ?? '') ?></textarea>
                            </div>
                            
                            <hr style="margin: 32px 0; border-color: var(--gray-200);">
                            
                            <h4 style="margin-bottom: 20px; font-size: 1.25rem; font-weight: 700;">REQUIRED DOCUMENTS</h4>
                            
                            <?php
                            // Helper function to render document section
                            function renderDocumentSection($number, $title, $description, $format, $examples, $fileInputName, $documentType, $allowedFormats, $documentsByType, $isMultiple = false) {
                                $docs = isset($documentsByType[$documentType]) ? $documentsByType[$documentType] : [];
                                $isMultipleAllowed = $isMultiple;
                                if (!$isMultipleAllowed && !empty($docs)) {
                                    $docs = [end($docs)]; // Only show the latest for single-file documents
                                }
                                ?>
                                <!-- <?= $number ?>. <?= $title ?> -->
                                <div class="form-group" style="margin-bottom: 32px;">
                                    <label class="form-label"><strong><?= $number ?>. <?= $title ?></strong></label>
                                    <?php if (!empty($description)): ?>
                                        <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 12px;"><?= $description ?></p>
                                    <?php endif; ?>
                                    
                                    <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;"><?= $format ?></p>
                                    
                                    <?php if (!empty($examples)): ?>
                                        <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 8px;">Examples:</p>
                                        <?php if (is_array($examples)): ?>
                                            <ul style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 16px; padding-left: 24px;">
                                                <?php foreach ($examples as $example): ?>
                                                    <li><?= htmlspecialchars($example) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 16px; font-family: monospace; background: var(--gray-50); padding: 8px; border-radius: 4px;"><?= htmlspecialchars($examples) ?></p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <div style="margin-bottom: 16px;">
                                        <label class="form-label"><strong>Files submitted:</strong></label>
                                        <?php if (!empty($docs)): ?>
                                            <?php foreach ($docs as $doc): 
                                                $docDisplayPath = 'uploads/documents/' . $doc['file_path'];
                                                $docFullPath = __DIR__ . '/' . $docDisplayPath;
                                                $docFileExists = file_exists($docFullPath);
                                                $isPdf = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION)) === 'pdf';
                                            ?>
                                                <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--gray-50); border-radius: 4px; margin-top: 8px;">
                                                    <i class="fas fa-<?= $isPdf ? 'file-pdf' : 'file-image' ?>" style="color: var(--<?= $isPdf ? 'danger' : 'success' ?>); font-size: 1.5rem;"></i>
                                                    <div style="flex: 1;">
                                                        <div style="font-weight: 600; color: var(--gray-900);"><?= htmlspecialchars($doc['file_name']) ?></div>
                                                        <?php if ($docFileExists): ?>
                                                            <a href="<?= htmlspecialchars($docDisplayPath) ?>" target="_blank" style="font-size: 0.875rem; color: var(--primary-blue); text-decoration: none;">
                                                                <i class="fas fa-external-link-alt"></i> View File
                                                            </a>
                                                        <?php else: ?>
                                                            <span style="font-size: 0.875rem; color: var(--gray-500);">File not found on server</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <a href="../actions/delete-application-document.php?id=<?= $doc['id'] ?>" 
                                                       class="btn btn-sm btn-outline" 
                                                       style="color: var(--danger); border-color: var(--danger);" 
                                                       title="Delete"
                                                       onclick="return confirm('Are you sure you want to delete this document?');">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p style="font-size: 0.875rem; color: var(--gray-500); margin-top: 8px; font-style: italic;">No file uploaded</p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Upload <?= $title ?> (<?= strtoupper(implode(' or ', $allowedFormats)) ?> only)</label>
                                        <input type="file" name="<?= $fileInputName ?><?= $isMultipleAllowed ? '[]' : '' ?>" class="form-control" 
                                               accept="<?= implode(',', array_map(function($f) { return '.' . $f . ($f === 'pdf' ? ',application/pdf' : ($f === 'jpg' || $f === 'jpeg' ? ',image/jpeg,image/jpg' : '')); }, $allowedFormats)) ?>"
                                               <?= $isMultipleAllowed ? 'multiple' : '' ?>>
                                        <small class="form-text text-muted">Maximum file size: 10MB<?= $isMultipleAllowed ? ' per file. Multiple files can be uploaded.' : '' ?>. Files will be automatically renamed according to the required format<?= $isMultipleAllowed ? ' (##01, ##02, etc.)' : '' ?>.</small>
                                    </div>
                                </div>
                            <?php
                            }
                            
                            // Render all document sections
                            renderDocumentSection(
                                '21',
                                'APPLICATION LETTER',
                                'Applicants are required to upload an Application Letter addressed to:<br><strong>DR. AMABEL S. LIAO<br>University President<br>Western Philippines University<br>Aborlan, Palawan</strong><br><br>The Application Letter must be properly addressed, signed, and clearly readable.',
                                'Format: <code style="background: var(--gray-50); padding: 4px 8px; border-radius: 4px; font-family: monospace;">SURNAME_FIRSTNAME_APLET_WPUHRMDO</code>',
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
                                'Format: <code style="background: var(--gray-50); padding: 4px 8px; border-radius: 4px; font-family: monospace;">SURNAME_FIRSTNAME_PDS_WPUHRMDO</code>',
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
                                'Format: <code style="background: var(--gray-50); padding: 4px 8px; border-radius: 4px; font-family: monospace;">SURNAME_FIRSTNAME_WES_WPUHRMDO</code>',
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
                                'Format: <code style="background: var(--gray-50); padding: 4px 8px; border-radius: 4px; font-family: monospace;">SURNAME_FIRSTNAME_TOR_WPUHRMDO</code>',
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
                                'Format: <code style="background: var(--gray-50); padding: 4px 8px; border-radius: 4px; font-family: monospace;">SURNAME_FIRSTNAME_DIP_WPUHRMDO</code>',
                                ['SANTOS_MARIAFE_DIP_WPUHRMDO.pdf', 'SANTOS_MARIAFE_DIP_WPUHRMDO.jpg'],
                                'diploma',
                                'DIP',
                                ['pdf', 'jpg', 'jpeg'],
                                $documentsByType,
                                false
                            );
                            
                            renderDocumentSection(
                                '26',
                                'CERTIFICATE OF EMPLOYMENT AND/OR SERVICE RECORD (IF APPLICABLE)',
                                'Applicants are required to upload scanned copies or photocopies of their Certificate(s) of Employment (COE) and/or Service Record(s) relevant to the position being applied for, if applicable. All documents must be clear, complete, and readable.<br><br><strong>For Certificate of Employment (COE):</strong> Format: SURNAME_FIRSTNAME_COE##_WPUHRMDO<br><strong>For Service Record (SR):</strong> Format: SURNAME_FIRSTNAME_SRE##_WPUHRMDO',
                                'Format: <code style="background: var(--gray-50); padding: 4px 8px; border-radius: 4px; font-family: monospace;">SURNAME_FIRSTNAME_COE##_WPUHRMDO</code> or <code style="background: var(--gray-50); padding: 4px 8px; border-radius: 4px; font-family: monospace;">SURNAME_FIRSTNAME_SRE##_WPUHRMDO</code>',
                                ['SANTOS_MARIAFE_COE01_WPUHRMDO.pdf', 'SANTOS_MARIAFE_COE02_WPUHRMDO.jpg', 'SANTOS_MARIAFE_SRE01_WPUHRMDO.pdf', 'SANTOS_MARIAFE_SRE02_WPUHRMDO.jpg'],
                                'certificate_of_employment',
                                'COE',
                                ['pdf', 'jpg', 'jpeg'],
                                $documentsByType,
                                true
                            );
                            
                            // Service Records section
                            $sreDocs = isset($documentsByType['SRE']) ? $documentsByType['SRE'] : [];
                            ?>
                            <div class="form-group" style="margin-bottom: 32px;">
                                <label class="form-label"><strong>Service Records:</strong></label>
                                <div style="margin-bottom: 16px;">
                                    <label class="form-label"><strong>Files submitted:</strong></label>
                                    <?php if (!empty($sreDocs)): ?>
                                        <?php foreach ($sreDocs as $doc): 
                                            $docDisplayPath = 'uploads/documents/' . $doc['file_path'];
                                            $docFullPath = __DIR__ . '/' . $docDisplayPath;
                                            $docFileExists = file_exists($docFullPath);
                                            $isPdf = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION)) === 'pdf';
                                        ?>
                                            <div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--gray-50); border-radius: 4px; margin-top: 8px;">
                                                <i class="fas fa-<?= $isPdf ? 'file-pdf' : 'file-image' ?>" style="color: var(--<?= $isPdf ? 'danger' : 'success' ?>); font-size: 1.5rem;"></i>
                                                <div style="flex: 1;">
                                                    <div style="font-weight: 600; color: var(--gray-900);"><?= htmlspecialchars($doc['file_name']) ?></div>
                                                    <?php if ($docFileExists): ?>
                                                        <a href="<?= htmlspecialchars($docDisplayPath) ?>" target="_blank" style="font-size: 0.875rem; color: var(--primary-blue); text-decoration: none;">
                                                            <i class="fas fa-external-link-alt"></i> View File
                                                        </a>
                                                    <?php else: ?>
                                                        <span style="font-size: 0.875rem; color: var(--gray-500);">File not found on server</span>
                                                    <?php endif; ?>
                                                </div>
                                                <a href="../actions/delete-application-document.php?id=<?= $doc['id'] ?>" 
                                                   class="btn btn-sm btn-outline" 
                                                   style="color: var(--danger); border-color: var(--danger);" 
                                                   title="Delete"
                                                   onclick="return confirm('Are you sure you want to delete this document?');">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p style="font-size: 0.875rem; color: var(--gray-500); margin-top: 8px; font-style: italic;">No files uploaded</p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Upload Service Record(s) (PDF or JPEG only)</label>
                                    <input type="file" name="service_record[]" class="form-control" accept=".pdf,.jpg,.jpeg,application/pdf,image/jpeg" multiple>
                                    <small class="form-text text-muted">Maximum file size: 10MB per file. Multiple files can be uploaded. Files will be automatically renamed according to the required format (SRE01, SRE02, etc.).</small>
                                </div>
                            </div>
                            
                            <?php
                            renderDocumentSection(
                                '27',
                                'PHOTOCOPY OF AUTHENTICATED CERTIFICATE OF ELIGIBILITY/RATING/LICENSE (IF APPLICABLE)',
                                'Applicants are required to upload scanned copies or photocopies of authenticated Certificates of Eligibility, Rating, or Professional License, relevant to the position being applied for, if applicable. All documents must be clear, complete, and readable.',
                                'Format: <code style="background: var(--gray-50); padding: 4px 8px; border-radius: 4px; font-family: monospace;">SURNAME_FIRSTNAME_EL##_WPUHRMDO</code>',
                                ['SANTOS_MARIAFE_EL01_WPUHRMDO.pdf', 'SANTOS_MARIAFE_EL02_WPUHRMDO.jpg', 'SANTOS_MARIAFE_EL03_WPUHRMDO.pdf'],
                                'eligibility_license',
                                'EL',
                                ['pdf', 'jpg', 'jpeg'],
                                $documentsByType,
                                true
                            );
                            ?>
                            
                            <hr style="margin: 32px 0; border-color: var(--gray-200);">
                            
                            <!-- Change Password Section -->
                            <h5 style="margin-bottom: 12px; font-size: 1rem; font-weight: 600;"><i class="fas fa-lock"></i> Change Password</h5>
                            <p style="font-size: 0.875rem; color: var(--gray-500); margin-bottom: 16px;">Leave blank if you don't want to change your password.</p>
                            
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" name="password" class="form-control" 
                                       placeholder="Enter new password">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" 
                                       placeholder="Confirm new password">
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Work Experience Section -->
            <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 class="card-title">
                            <i class="fas fa-briefcase"></i> Work Experience
                        </h3>
                        <a href="add-experience.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Add
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($experiences)): ?>
                            <div class="empty-state" style="padding: 40px 20px;">
                                <i class="fas fa-briefcase"></i>
                                <h5 style="font-size: 1.125rem; margin-top: 16px;">No Work Experience</h5>
                                <p style="color: var(--gray-500); margin-bottom: 20px;">Add your work experience to strengthen your applications.</p>
                                
                            </div>
                        <?php else: ?>
                            <?php foreach ($experiences as $exp): ?>
                                <div style="padding: 16px; border-bottom: 1px solid var(--gray-200); margin-bottom: 12px; background: var(--gray-50); border-radius: 8px; border-left: 4px solid var(--primary-blue);">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                                        <div style="flex: 1;">
                                            <div style="font-weight: 700; font-size: 1rem; color: var(--gray-900); margin-bottom: 4px;">
                                                <?= htmlspecialchars($exp['position_title']) ?>
                                            </div>
                                            <div style="color: var(--primary-blue); font-weight: 600; margin-bottom: 8px;">
                                                <?= htmlspecialchars($exp['company_name']) ?>
                                            </div>
                                            <div style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; color: var(--gray-600);">
                                                <i class="fas fa-calendar"></i>
                                                <span>
                                                    <?= date('M Y', strtotime($exp['start_date'])) ?> - 
                                                    <?= $exp['is_current'] ? 'Present' : date('M Y', strtotime($exp['end_date'])) ?>
                                                </span>
                                                <span class="badge badge-primary"><?= number_format($exp['years_duration'], 1) ?>y</span>
                                            </div>
                                        </div>
                                        <div style="display: flex; gap: 8px;">
                                            <a href="edit-experience.php?id=<?= $exp['id'] ?>" class="btn btn-sm btn-outline" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="../actions/delete-experience.php?id=<?= $exp['id'] ?>" 
                                               class="btn btn-sm btn-outline confirm-delete-experience" 
                                               style="color: var(--danger); border-color: var(--danger);" 
                                               title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <?php if ($exp['description']): ?>
                                        <p style="font-size: 0.875rem; color: var(--gray-600); margin: 8px 0 0 0; line-height: 1.5;">
                                            <?= nl2br(htmlspecialchars($exp['description'])) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="card-footer" style="display: flex; justify-content: center; align-items: center; padding: 16px; border-top: 1px solid var(--gray-200);">
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>" class="pagination-btn">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <div class="pagination-info">
                                Page <?= $page ?> of <?= $totalPages ?> (<?= $totalExperiences ?> total)
                            </div>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>" class="pagination-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
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
    <script>
        // Handle conditional display of promotion/entry level sections
        function toggleApplicationSections() {
            const promotionCheckbox = document.querySelector('input[name="application_type[]"][value="PROMOTION"]');
            const entryLevelCheckbox = document.querySelector('input[name="application_type[]"][value="ENTRY LEVEL"]');
            const promotionSection = document.getElementById('promotion-section');
            const entryLevelSection = document.getElementById('entry-level-section');
            
            if (promotionCheckbox && promotionSection) {
                // Check if file exists (PHP sets display based on file existence)
                const hasIpcrFileDisplay = promotionSection.querySelector('a[href*="uploads/documents"]');
                // Show section if checkbox is checked OR if file exists (PHP already handles initial display)
                if (promotionCheckbox.checked || hasIpcrFileDisplay) {
                    promotionSection.style.display = 'block';
                } else if (!hasIpcrFileDisplay) {
                    // Only hide if no file exists and checkbox is unchecked
                    promotionSection.style.display = 'none';
                }
            }
            if (entryLevelCheckbox && entryLevelSection) {
                // Check if file exists (PHP sets display based on file existence)
                const hasPeFileDisplay = entryLevelSection.querySelector('a[href*="uploads/documents"]');
                // Show section if checkbox is checked OR if file exists (PHP already handles initial display)
                if (entryLevelCheckbox.checked || hasPeFileDisplay) {
                    entryLevelSection.style.display = 'block';
                } else if (!hasPeFileDisplay) {
                    // Only hide if no file exists and checkbox is unchecked
                    entryLevelSection.style.display = 'none';
                }
            }
        }
        
        // Handle "Other" field in Career Service Eligibilities
        function toggleCSEOther() {
            const otherCheckbox = document.getElementById('cse-other-checkbox');
            const otherInput = document.getElementById('cse-other-input');
            
            if (otherCheckbox && otherInput) {
                otherInput.style.display = otherCheckbox.checked ? 'block' : 'none';
                if (!otherCheckbox.checked) {
                    otherInput.value = '';
                }
            }
        }
        
        // Data Privacy Modal handlers
        function openDataPrivacyModal() {
            const modal = document.getElementById('dataPrivacyModal');
            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }
        
        function closeDataPrivacyModal() {
            const modal = document.getElementById('dataPrivacyModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Show sections if checkboxes are already checked (e.g., after page reload)
            toggleApplicationSections();
            toggleCSEOther();
            
            // Debug: Log section visibility
            const promotionSection = document.getElementById('promotion-section');
            const entryLevelSection = document.getElementById('entry-level-section');
            const promotionCheckbox = document.querySelector('input[name="application_type[]"][value="PROMOTION"]');
            const entryLevelCheckbox = document.querySelector('input[name="application_type[]"][value="ENTRY LEVEL"]');
            
            console.log('Promotion checkbox checked:', promotionCheckbox ? promotionCheckbox.checked : 'not found');
            console.log('Entry Level checkbox checked:', entryLevelCheckbox ? entryLevelCheckbox.checked : 'not found');
            console.log('Promotion section display:', promotionSection ? promotionSection.style.display : 'not found');
            console.log('Entry Level section display:', entryLevelSection ? entryLevelSection.style.display : 'not found');
            
            // Add event listeners
            const appTypeCheckboxes = document.querySelectorAll('input[name="application_type[]"]');
            appTypeCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', toggleApplicationSections);
            });
            
            const cseOtherCheckbox = document.getElementById('cse-other-checkbox');
            if (cseOtherCheckbox) {
                cseOtherCheckbox.addEventListener('change', toggleCSEOther);
            }
            
            // Data Privacy Modal event listeners
            const dataPrivacyLink = document.getElementById('dataPrivacyPolicyLink');
            const closeModalBtn = document.getElementById('closeDataPrivacyModal');
            const closeModalBtn2 = document.getElementById('closeDataPrivacyModalBtn');
            const modal = document.getElementById('dataPrivacyModal');
            const modalOverlay = modal ? modal.querySelector('.modal-overlay') : null;
            
            if (dataPrivacyLink) {
                dataPrivacyLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    openDataPrivacyModal();
                });
            }
            
            if (closeModalBtn) {
                closeModalBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeDataPrivacyModal();
                });
            }
            
            if (closeModalBtn2) {
                closeModalBtn2.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeDataPrivacyModal();
                });
            }
            
            // Close modal when clicking outside
            if (modalOverlay) {
                modalOverlay.addEventListener('click', function(e) {
                    if (e.target === modalOverlay) {
                        closeDataPrivacyModal();
                    }
                });
            }
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('dataPrivacyModal');
                    if (modal && modal.style.display === 'block') {
                        closeDataPrivacyModal();
                    }
                }
            });
        });
    </script>
    <?php include '../../includes/pwa-scripts.php'; ?>
</body>
</html>
