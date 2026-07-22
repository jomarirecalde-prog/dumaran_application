<?php
/**
 * Migration: Add salary_grade, training_hours, and career_service_eligibilities to positions table
 * Run this from browser: http://localhost/Applicant_WPU/run_position_fields_migration.php
 */

require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Check which columns exist
    $stmt = $pdo->query("DESCRIBE positions");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $columnsToAdd = [];
    
    if (!in_array('salary_grade', $columns)) {
        $columnsToAdd[] = 'salary_grade';
    }
    if (!in_array('training_hours', $columns)) {
        $columnsToAdd[] = 'training_hours';
    }
    if (!in_array('career_service_eligibilities', $columns)) {
        $columnsToAdd[] = 'career_service_eligibilities';
    }
    
    if (empty($columnsToAdd)) {
        echo "<!DOCTYPE html><html><head><title>Migration Status</title>";
        echo "<style>body{font-family:system-ui,-apple-system,sans-serif;padding:20px;max-width:800px;margin:0 auto;}";
        echo ".success{color:#059669;font-weight:bold;}</style></head><body>";
        echo "<h1>Migration Status</h1>";
        echo "<p class='success'>✓ All columns already exist. Migration not needed.</p>";
        echo "<p>The following columns are present:</p>";
        echo "<ul><li>salary_grade</li><li>training_hours</li><li>career_service_eligibilities</li></ul>";
        echo "<p><a href='admin/pages/positions.php'>← Go to Positions Page</a></p>";
        echo "</body></html>";
    } else {
        // Execute migrations one at a time (they depend on each other's order)
        $addedColumns = [];
        
        // Add salary_grade first (after salary_range)
        if (in_array('salary_grade', $columnsToAdd)) {
            $pdo->exec("ALTER TABLE `positions` ADD COLUMN `salary_grade` VARCHAR(50) DEFAULT NULL AFTER `salary_range`");
            $addedColumns[] = 'salary_grade';
        }
        
        // Add training_hours (after salary_grade if it exists, otherwise after salary_range)
        if (in_array('training_hours', $columnsToAdd)) {
            $afterCol = in_array('salary_grade', $columns) || in_array('salary_grade', $addedColumns) ? 'salary_grade' : 'salary_range';
            $pdo->exec("ALTER TABLE `positions` ADD COLUMN `training_hours` INT(11) DEFAULT NULL AFTER `{$afterCol}`");
            $addedColumns[] = 'training_hours';
        }
        
        // Add career_service_eligibilities (after training_hours if it exists, otherwise after the last column we added)
        if (in_array('career_service_eligibilities', $columnsToAdd)) {
            if (in_array('training_hours', $columns) || in_array('training_hours', $addedColumns)) {
                $afterCol = 'training_hours';
            } elseif (in_array('salary_grade', $columns) || in_array('salary_grade', $addedColumns)) {
                $afterCol = 'salary_grade';
            } else {
                $afterCol = 'salary_range';
            }
            $pdo->exec("ALTER TABLE `positions` ADD COLUMN `career_service_eligibilities` TEXT DEFAULT NULL AFTER `{$afterCol}`");
            $addedColumns[] = 'career_service_eligibilities';
        }
        
        echo "<!DOCTYPE html><html><head><title>Migration Status</title>";
        echo "<style>body{font-family:system-ui,-apple-system,sans-serif;padding:20px;max-width:800px;margin:0 auto;}";
        echo ".success{color:#059669;font-weight:bold;}</style></head><body>";
        echo "<h1>Migration Status</h1>";
        echo "<p class='success'>✓ Migration completed successfully!</p>";
        echo "<p>The following columns were added:</p>";
        echo "<ul>";
        foreach ($columnsToAdd as $col) {
            echo "<li>$col</li>";
        }
        echo "</ul>";
        echo "<p><a href='admin/pages/positions.php'>← Go to Positions Page</a></p>";
        echo "</body></html>";
    }
    
} catch (PDOException $e) {
    echo "<!DOCTYPE html><html><head><title>Migration Error</title>";
    echo "<style>body{font-family:system-ui,-apple-system,sans-serif;padding:20px;max-width:800px;margin:0 auto;}";
    echo ".error{color:#dc2626;font-weight:bold;}</style></head><body>";
    echo "<h1>Migration Error</h1>";
    echo "<p class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
    echo "</body></html>";
} catch (Exception $e) {
    echo "<!DOCTYPE html><html><head><title>Migration Error</title>";
    echo "<style>body{font-family:system-ui,-apple-system,sans-serif;padding:20px;max-width:800px;margin:0 auto;}";
    echo ".error{color:#dc2626;font-weight:bold;}</style></head><body>";
    echo "<h1>Migration Error</h1>";
    echo "<p class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</body></html>";
}
?>

