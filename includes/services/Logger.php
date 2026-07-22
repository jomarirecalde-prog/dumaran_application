<?php
/**
 * Logger Service
 * Handles application logging
 */

class Logger {
    private $logPath;
    private $logLevel;
    
    const DEBUG = 'DEBUG';
    const INFO = 'INFO';
    const WARNING = 'WARNING';
    const ERROR = 'ERROR';
    const CRITICAL = 'CRITICAL';

    public function __construct($logPath = null) {
        $this->logPath = $logPath ?? __DIR__ . '/../../logs/';
        $this->logLevel = $_ENV['LOG_LEVEL'] ?? self::INFO;
        
        // Ensure log directory exists
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    /**
     * Log a message
     */
    private function log($level, $message, $context = []) {
        $levelPriority = $this->getLevelPriority($level);
        $currentPriority = $this->getLevelPriority($this->logLevel);
        
        // Only log if level is high enough
        if ($levelPriority < $currentPriority) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextString = !empty($context) ? ' ' . json_encode($context) : '';
        
        $logMessage = "[{$timestamp}] [{$level}] {$message}{$contextString}\n";
        
        // Determine log file based on level
        $filename = $this->getLogFilename($level);
        
        error_log($logMessage, 3, $this->logPath . $filename);
    }

    /**
     * Get log filename based on level and date
     */
    private function getLogFilename($level) {
        $date = date('Y-m-d');
        
        if (in_array($level, [self::ERROR, self::CRITICAL])) {
            return "error-{$date}.log";
        }
        
        return "app-{$date}.log";
    }

    /**
     * Get priority level for comparison
     */
    private function getLevelPriority($level) {
        $priorities = [
            self::DEBUG => 0,
            self::INFO => 1,
            self::WARNING => 2,
            self::ERROR => 3,
            self::CRITICAL => 4,
        ];
        
        return $priorities[$level] ?? 1;
    }

    /**
     * Log debug message
     */
    public function debug($message, $context = []) {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Log info message
     */
    public function info($message, $context = []) {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * Log warning message
     */
    public function warning($message, $context = []) {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * Log error message
     */
    public function error($message, $context = []) {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * Log critical message
     */
    public function critical($message, $context = []) {
        $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * Log exception
     */
    public function exception($exception) {
        $this->error($exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    /**
     * Log user activity
     */
    public function activity($userId, $action, $details = []) {
        $this->info("User Activity: {$action}", array_merge([
            'user_id' => $userId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ], $details));
    }

    /**
     * Clear old logs
     */
    public function clearOldLogs($daysToKeep = 30) {
        $files = glob($this->logPath . '*.log');
        $cutoffTime = time() - ($daysToKeep * 86400);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }
    }
}
