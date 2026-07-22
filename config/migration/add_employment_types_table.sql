-- Migration: Add employment_types table and update positions table
-- This allows admins to dynamically manage employment types

-- Create employment_types table
CREATE TABLE IF NOT EXISTS `employment_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default employment types
INSERT INTO `employment_types` (`name`, `slug`, `is_active`) VALUES
('Full-time', 'full-time', 1),
('Part-time', 'part-time', 1),
('Contract', 'contract', 1),
('Internship', 'internship', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Alter positions table to change employment_type from ENUM to VARCHAR
-- First, we need to ensure existing data is preserved
-- Note: This migration assumes you want to keep the current values

-- Change the column type from ENUM to VARCHAR
ALTER TABLE `positions` 
MODIFY COLUMN `employment_type` varchar(50) DEFAULT 'full-time';

-- Update the active_positions view if it exists (it may need to be recreated)
-- The view definition might need manual adjustment after this migration

