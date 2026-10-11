<?php
/**
 * One-time utility: copy dumaran_hr → dumaran_hr
 * Run: php scripts/copy_database_to_dumaran_hr.php
 */

$host = 'localhost';
$user = 'root';
$pass = 'password';
$sourceDb = 'dumaran_hr';
$targetDb = 'dumaran_hr';

try {
    $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Connection failed (try empty password in this script): {$e->getMessage()}\n");
    exit(1);
}

$exists = $pdo->query("SHOW DATABASES LIKE " . $pdo->quote($sourceDb))->fetchColumn();
if (!$exists) {
    fwrite(STDERR, "Source database '{$sourceDb}' not found.\n");
    exit(1);
}

$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$targetDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$tables = $pdo->query("SHOW TABLES FROM `{$sourceDb}`")->fetchAll(PDO::FETCH_COLUMN);
if (!$tables) {
    fwrite(STDERR, "Source database has no tables.\n");
    exit(1);
}

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');

foreach ($tables as $table) {
    $pdo->exec("DROP TABLE IF EXISTS `{$targetDb}`.`{$table}`");
    $pdo->exec("CREATE TABLE `{$targetDb}`.`{$table}` LIKE `{$sourceDb}`.`{$table}`");
    $pdo->exec("INSERT INTO `{$targetDb}`.`{$table}` SELECT * FROM `{$sourceDb}`.`{$table}`");
    echo "Copied table: {$table}\n";
}

$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

echo "Done. Database '{$targetDb}' is a copy of '{$sourceDb}'.\n";
