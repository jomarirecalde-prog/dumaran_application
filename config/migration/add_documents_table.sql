-- Migration: Add application_documents table for multiple file uploads
-- Date: 2025-11-09
-- Description: Adds support for multiple document uploads per application

-- Check if table exists and create it if not
CREATE TABLE IF NOT EXISTS application_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    document_type ENUM('resume', 'cover_letter', 'certificate', 'portfolio', 'other') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);

-- Add index for faster queries
CREATE INDEX IF NOT EXISTS idx_application_documents_app_id ON application_documents(application_id);
CREATE INDEX IF NOT EXISTS idx_application_documents_type ON application_documents(document_type);
