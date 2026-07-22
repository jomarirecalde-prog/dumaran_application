<?php
/**
 * Migration Runner
 * Run this script to add missing tables to the database
 * 
 * Usage: Navigate to this file in browser or run: php run_migrations.php
 */

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

$migrations = [];
$errors = [];

// Migration 1: Add user_training_certificates table
try {
    $sql = "CREATE TABLE IF NOT EXISTS `user_training_certificates` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `file_name` varchar(255) NOT NULL,
        `file_path` varchar(255) NOT NULL,
        `file_size` int(11) DEFAULT NULL,
        `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_training_certificates_user_id` (`user_id`),
        KEY `idx_training_certificates_uploaded_at` (`uploaded_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $pdo->exec($sql);
    $migrations[] = "✓ user_training_certificates table created/verified";
    
    // Add foreign key if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE `user_training_certificates` 
            ADD CONSTRAINT `fk_training_certificates_user` 
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE");
        $migrations[] = "✓ Foreign key for user_training_certificates added";
    } catch (PDOException $e) {
        // Foreign key might already exist
        if (strpos($e->getMessage(), 'Duplicate') === false && strpos($e->getMessage(), 'already exists') === false) {
            $migrations[] = "→ user_training_certificates foreign key already exists";
        }
    }
} catch (PDOException $e) {
    $errors[] = "✗ Failed to create user_training_certificates: " . $e->getMessage();
}

// Migration 2: Add user_application_documents table
try {
    $sql = "CREATE TABLE IF NOT EXISTS `user_application_documents` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `document_type` enum('APLET','PDS','WES','TOR','DIP','COE','SRE','EL') NOT NULL,
        `file_name` varchar(255) NOT NULL,
        `file_path` varchar(255) NOT NULL,
        `file_size` int(11) DEFAULT NULL,
        `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_app_documents_user_id` (`user_id`),
        KEY `idx_app_documents_type` (`document_type`),
        KEY `idx_app_documents_user_type` (`user_id`, `document_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $pdo->exec($sql);
    $migrations[] = "✓ user_application_documents table created/verified";
    
    // Add foreign key if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE `user_application_documents` 
            ADD CONSTRAINT `fk_app_documents_user` 
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE");
        $migrations[] = "✓ Foreign key for user_application_documents added";
    } catch (PDOException $e) {
        // Foreign key might already exist
        if (strpos($e->getMessage(), 'Duplicate') === false && strpos($e->getMessage(), 'already exists') === false) {
            $migrations[] = "→ user_application_documents foreign key already exists";
        }
    }
} catch (PDOException $e) {
    $errors[] = "✗ Failed to create user_application_documents: " . $e->getMessage();
}

// Output results
$isWeb = php_sapi_name() !== 'cli';

if ($isWeb) {
    echo "<!DOCTYPE html><html><head><title>Database Migrations</title>";
    echo "<style>body{font-family:system-ui,-apple-system,sans-serif;padding:20px;max-width:800px;margin:0 auto}";
    echo ".success{color:#059669}.error{color:#dc2626}.info{color:#2563eb}";
    echo "h1{color:#1f2937}pre{background:#f3f4f6;padding:10px;border-radius:5px}</style></head><body>";
    echo "<h1>Database Migration Results</h1>";
}

if (!empty($migrations)) {
    if ($isWeb) echo "<h2 class='success'>Successful Migrations:</h2><pre>";
    foreach ($migrations as $msg) {
        echo ($isWeb ? htmlspecialchars($msg) : $msg) . "\n";
    }
    if ($isWeb) echo "</pre>";
}

if (!empty($errors)) {
    if ($isWeb) echo "<h2 class='error'>Errors:</h2><pre>";
    foreach ($errors as $msg) {
        echo ($isWeb ? htmlspecialchars($msg) : $msg) . "\n";
    }
    if ($isWeb) echo "</pre>";
}

if (empty($errors)) {
    $successMsg = "\n✓ All migrations completed successfully!\n";
    $successMsg .= "You can now upload training certificates and application documents.\n";
    if ($isWeb) {
        echo "<p class='success'>" . nl2br(htmlspecialchars($successMsg)) . "</p>";
        echo "<p><a href='../applicant/pages/profile.php'>← Go to Profile Page</a></p>";
    } else {
        echo $successMsg;
    }
} else {
    $errorMsg = "\n✗ Some migrations failed. Please check the errors above.\n";
    if ($isWeb) {
        echo "<p class='error'>" . nl2br(htmlspecialchars($errorMsg)) . "</p>";
    } else {
        echo $errorMsg;
    }
}

if ($isWeb) echo "</body></html>";
?>

