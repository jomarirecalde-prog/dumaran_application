-- Migration: Add archive columns to applications table
-- Date: 2025-01-XX
-- Description: Adds is_archived and archived_at columns to support application history

ALTER TABLE `applications`
ADD COLUMN `is_archived` tinyint(1) DEFAULT 0 AFTER `status`,
ADD COLUMN `archived_at` timestamp NULL DEFAULT NULL AFTER `is_archived`,
ADD INDEX `idx_applications_archived` (`is_archived`);

