-- Migration: Add archive functionality to applications table
-- Date: 2025-11-16
-- Description: Adds is_archived and archived_at columns to support application history feature

-- Add is_archived column (default to 0 for existing records)
ALTER TABLE `applications` 
ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0 AFTER `updated_at`,
ADD COLUMN `archived_at` TIMESTAMP NULL DEFAULT NULL AFTER `is_archived`;

-- Add index for better query performance on archived applications
ALTER TABLE `applications`
ADD KEY `idx_applications_archived` (`is_archived`, `archived_at`);

-- Update existing applications to ensure they're marked as not archived
UPDATE `applications` SET `is_archived` = 0 WHERE `is_archived` IS NULL;

