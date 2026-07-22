-- Migration: Add user_training_certificates table for training/seminar certificate uploads
-- Date: 2026-01-XX
-- Description: Adds support for multiple training/seminar certificate uploads per user with proper naming convention (TS01, TS02, etc.)

-- Check if table exists and create it if not
CREATE TABLE IF NOT EXISTS user_training_certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add index for faster queries
CREATE INDEX IF NOT EXISTS idx_training_certificates_user_id ON user_training_certificates(user_id);
CREATE INDEX IF NOT EXISTS idx_training_certificates_uploaded_at ON user_training_certificates(uploaded_at);

