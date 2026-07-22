-- Migration: Add salary_grade, training_hours, and career_service_eligibilities to positions table
-- Date: 2026-01-08

ALTER TABLE `positions`
ADD COLUMN `salary_grade` VARCHAR(50) DEFAULT NULL AFTER `salary_range`,
ADD COLUMN `training_hours` INT(11) DEFAULT NULL AFTER `salary_grade`,
ADD COLUMN `career_service_eligibilities` TEXT DEFAULT NULL AFTER `training_hours`;

