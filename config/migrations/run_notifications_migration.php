<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // users.id must be indexed for the notifications FK
    $indexes = $db->query('SHOW INDEX FROM users WHERE Key_name = "PRIMARY"')->fetchAll();
    if (empty($indexes)) {
        echo "Adding PRIMARY KEY to users.id...\n";
        $db->exec('ALTER TABLE users ADD PRIMARY KEY (id)');
        echo "✓ PRIMARY KEY added to users\n\n";
    }

    $sqlFile = __DIR__ . '/create_notifications_table.sql';
    if (!file_exists($sqlFile)) {
        die("Error: SQL file not found at: $sqlFile\n");
    }

    $sql = file_get_contents($sqlFile);
    $sql = preg_replace('/--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    echo "Running notifications table migration...\n\n";

    foreach ($statements as $statement) {
        if (empty($statement)) {
            continue;
        }

        try {
            $db->exec($statement);
            echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "⚠ Table already exists, skipping...\n";
            } else {
                throw $e;
            }
        }
    }

    echo "\n✓ Migration completed successfully!\n";
    echo "The notifications system is now ready to use.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
