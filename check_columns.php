<?php
/**
 * Quick script to check if IPCR and PE file path columns exist in users table
 * Run this from browser: http://localhost/dumaran_hiring/check_columns.php
 */

require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Get all columns from users table
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Users Table Columns:</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    $hasIpcr = false;
    $hasPe = false;
    
    foreach ($columns as $col) {
        $highlight = '';
        if ($col['Field'] === 'ipcr_file_path') {
            $hasIpcr = true;
            $highlight = ' style="background-color: #90EE90;"';
        }
        if ($col['Field'] === 'pe_file_path') {
            $hasPe = true;
            $highlight = ' style="background-color: #90EE90;"';
        }
        
        echo "<tr$highlight>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><br>";
    echo "<h3>Status:</h3>";
    echo "<p>ipcr_file_path column: " . ($hasIpcr ? "<strong style='color: green;'>EXISTS</strong>" : "<strong style='color: red;'>MISSING</strong>") . "</p>";
    echo "<p>pe_file_path column: " . ($hasPe ? "<strong style='color: green;'>EXISTS</strong>" : "<strong style='color: red;'>MISSING</strong>") . "</p>";
    
    if (!$hasIpcr || !$hasPe) {
        echo "<br><p><strong style='color: red;'>ACTION REQUIRED:</strong> Run the migration:</p>";
        echo "<pre>ALTER TABLE `users` 
ADD COLUMN `ipcr_file_path` VARCHAR(255) DEFAULT NULL AFTER `ipcr_rating`,
ADD COLUMN `pe_file_path` VARCHAR(255) DEFAULT NULL AFTER `pe_rating`;</pre>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

