<?php
require_once __DIR__ . '/../config/database.php';

$sql = file_get_contents(__DIR__ . '/add_user_name_parts.sql');
$database = new Database();
$pdo = $database->getConnection();

try {
    $pdo->exec($sql);
    echo "Migration add_user_name_parts completed.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Columns already exist — skipped.\n";
        exit(0);
    }
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
