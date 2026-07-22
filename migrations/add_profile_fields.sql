-- Migration: Add comprehensive profile fields to users table
-- Date: 2026-01-XX
-- Description: Adds fields required for WPU application form including civil status, educational background, degrees, application type, training, and career service eligibilities

ALTER TABLE `users` 
ADD COLUMN `civil_status` enum('Married','Single','Separated','Widowed','Annuled','Solo Parent') DEFAULT NULL AFTER `sex`,
ADD COLUMN `highest_educational_attainment` enum('Elementary Level','Elementary Graduate','Secondary/ High School Level','Secondary/ High School Graduate','College Level','College Graduate','Master\'s Degree Holder','Doctoral Degree Holder') DEFAULT NULL AFTER `civil_status`,
ADD COLUMN `bachelors_degree` text DEFAULT NULL AFTER `highest_educational_attainment`,
ADD COLUMN `masters_degree` text DEFAULT NULL AFTER `bachelors_degree`,
ADD COLUMN `doctorate_degree` text DEFAULT NULL AFTER `masters_degree`,
ADD COLUMN `application_type` set('PROMOTION','ENTRY LEVEL') DEFAULT NULL AFTER `doctorate_degree`,
ADD COLUMN `ipcr_rating` text DEFAULT NULL AFTER `application_type`,
ADD COLUMN `pe_rating` text DEFAULT NULL AFTER `ipcr_rating`,
ADD COLUMN `position_applied_for` enum('University Extension Specialist V SG 24','University Extension Specialist IV SG 22','University Extension Specialist III SG 20','University Extension Specialist II SG 18','University Extension Specialist I SG 16','University Extension Associate I SG 12','Administrative Assistant III SG 9','Administrative Aide VI (Clerk III) SG 6','Administrative Aide IV (Clerk II) SG 4') DEFAULT NULL AFTER `pe_rating`,
ADD COLUMN `training_programs` text DEFAULT NULL AFTER `position_applied_for`,
ADD COLUMN `career_service_eligibilities` set('Professional Eligibility','Sub-Professional Eligibility','Career Service Executive Eligibility (CSE-E)','Bar/Board Eligibility (RA 1080)','Other') DEFAULT NULL AFTER `training_programs`,
ADD COLUMN `career_service_eligibilities_other` text DEFAULT NULL AFTER `career_service_eligibilities`,
ADD COLUMN `special_needs` text DEFAULT NULL AFTER `career_service_eligibilities_other`;

-- Add index for better query performance
ALTER TABLE `users`
ADD KEY `idx_users_civil_status` (`civil_status`),
ADD KEY `idx_users_educational_attainment` (`highest_educational_attainment`),
ADD KEY `idx_users_application_type` (`application_type`),
ADD KEY `idx_users_position_applied_for` (`position_applied_for`);

