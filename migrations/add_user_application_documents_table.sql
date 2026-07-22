-- Migration: Add user_application_documents table for all application document uploads
-- Date: 2026-01-XX
-- Description: Adds support for multiple document types per user with proper naming conventions
-- Document types: APLET, PDS, WES, TOR, DIP, COE, SRE, EL

-- Check if table exists and create it if not
CREATE TABLE IF NOT EXISTS user_application_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_type ENUM('APLET', 'PDS', 'WES', 'TOR', 'DIP', 'COE', 'SRE', 'EL') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add indexes for faster queries
CREATE INDEX IF NOT EXISTS idx_app_documents_user_id ON user_application_documents(user_id);
CREATE INDEX IF NOT EXISTS idx_app_documents_type ON user_application_documents(document_type);
CREATE INDEX IF NOT EXISTS idx_app_documents_user_type ON user_application_documents(user_id, document_type);

