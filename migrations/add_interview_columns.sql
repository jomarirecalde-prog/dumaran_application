-- Migration: Add interview_date and interview_time columns to applications table
-- Date: 2025-11-09
-- Description: Adds columns to store automatically scheduled interview dates and times

ALTER TABLE `applications` 
ADD COLUMN `interview_date` DATE NULL DEFAULT NULL AFTER `updated_at`,
ADD COLUMN `interview_time` TIME NULL DEFAULT NULL AFTER `interview_date`;

-- Add index for faster queries on interview scheduling
CREATE INDEX `idx_applications_interview` ON `applications` (`interview_date`, `interview_time`);

