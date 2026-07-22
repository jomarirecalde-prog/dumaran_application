<?php
/**
 * Database Configuration for WPU Applicant System
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'wpu_applicant_system';
    private $username = 'root';
    private $password = 'password';
    private $charset = 'utf8mb4';
    public $pdo;

    public function getConnection() {
        // Reuse existing connection if available
        if ($this->pdo !== null) {
            return $this->pdo;
        }
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $this->pdo = new PDO($dsn, $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
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