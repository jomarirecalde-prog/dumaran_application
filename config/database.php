<?php
require_once __DIR__ . '/load_env.php';

/**
 * Database Configuration for Dumaran Hiring System
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    public $pdo;

    /** @var PDO|null Shared connection for the current PHP request */
    private static $sharedPdo = null;

    public function __construct()
    {
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->db_name = $_ENV['DB_NAME'] ?? 'dumaran_hr';
        $this->username = $_ENV['DB_USERNAME'] ?? 'root';
        $this->password = $_ENV['DB_PASSWORD'] ?? 'password';
        $this->charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
    }

    public function getConnection() {
        if (self::$sharedPdo !== null) {
            $this->pdo = self::$sharedPdo;
            return $this->pdo;
        }

        // Reuse existing connection on this instance if available
        if ($this->pdo !== null) {
            self::$sharedPdo = $this->pdo;
            return $this->pdo;
        }
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $this->pdo = new PDO($dsn, $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$sharedPdo = $this->pdo;
            // PDO auto-commits by default unless a transaction is explicitly started
        } catch(PDOException $e) {
            // Log error instead of outputting HTML
            error_log("Database Connection Error: " . $e->getMessage());
            // Re-throw exception so it can be caught by calling code
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
        return $this->pdo;
    }
}
?>