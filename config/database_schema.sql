-- Dumaran Hiring System — complete local schema
-- Database: dumaran_hr

CREATE DATABASE IF NOT EXISTS `dumaran_hr`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `dumaran_hr`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','applicant') NOT NULL DEFAULT 'applicant',
  `full_name` varchar(150) NOT NULL,
  `last_name` varchar(80) DEFAULT NULL,
  `first_name` varchar(80) DEFAULT NULL,
  `middle_name` varchar(80) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `sex` enum('Male','Female','Other') DEFAULT NULL,
  `civil_status` enum('Married','Single','Separated','Widowed','Annuled','Solo Parent') DEFAULT NULL,
  `highest_educational_attainment` enum('Elementary Level','Elementary Graduate','Secondary/ High School Level','Secondary/ High School Graduate','College Level','College Graduate','Master''s Degree Holder','Doctoral Degree Holder') DEFAULT NULL,
  `bachelors_degree` text DEFAULT NULL,
  `masters_degree` text DEFAULT NULL,
  `doctorate_degree` text DEFAULT NULL,
  `application_type` set('PROMOTION','ENTRY LEVEL') DEFAULT NULL,
  `ipcr_rating` text DEFAULT NULL,
  `ipcr_file_path` varchar(255) DEFAULT NULL,
  `pe_rating` text DEFAULT NULL,
  `pe_file_path` varchar(255) DEFAULT NULL,
  `position_applied_for` enum('LGU Extension Specialist V SG 24','LGU Extension Specialist IV SG 22','LGU Extension Specialist III SG 20','LGU Extension Specialist II SG 18','LGU Extension Specialist I SG 16','LGU Extension Associate I SG 12','Administrative Assistant III SG 9','Administrative Aide VI (Clerk III) SG 6','Administrative Aide IV (Clerk II) SG 4') DEFAULT NULL,
  `training_programs` text DEFAULT NULL,
  `career_service_eligibilities` set('Professional Eligibility','Sub-Professional Eligibility','Career Service Executive Eligibility (CSE-E)','Bar/Board Eligibility (RA 1080)','Other') DEFAULT NULL,
  `career_service_eligibilities_other` text DEFAULT NULL,
  `special_needs` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `oauth_provider` enum('google','facebook','microsoft') DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `email_verification_code` varchar(6) DEFAULT NULL,
  `email_verification_expires_at` datetime DEFAULT NULL,
  `email_verification_attempts` int(11) DEFAULT 0,
  `email_verification_last_sent_at` datetime DEFAULT NULL,
  `data_privacy_accepted_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `google_id` (`google_id`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_google_id` (`google_id`),
  KEY `idx_users_oauth_provider` (`oauth_provider`),
  KEY `idx_users_civil_status` (`civil_status`),
  KEY `idx_users_educational_attainment` (`highest_educational_attainment`),
  KEY `idx_users_application_type` (`application_type`),
  KEY `idx_users_position_applied_for` (`position_applied_for`),
  KEY `idx_users_data_privacy_accepted` (`data_privacy_accepted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE IF NOT EXISTS `positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `requirements` text DEFAULT NULL,
  `min_experience_years` int(11) DEFAULT 0,
  `max_applications` int(11) DEFAULT NULL,
  `salary_range` varchar(100) DEFAULT NULL,
  `salary_grade` varchar(50) DEFAULT NULL,
  `training_hours` int(11) DEFAULT NULL,
  `career_service_eligibilities` text DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `employment_type` varchar(50) DEFAULT 'full-time',
  `deadline` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_positions_active` (`is_active`),
  KEY `idx_positions_deadline` (`deadline`),
  KEY `idx_positions_created_by` (`created_by`),
  CONSTRAINT `fk_positions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `position_id` int(11) NOT NULL,
  `cover_letter` text DEFAULT NULL,
  `resume_file` varchar(255) DEFAULT NULL,
  `total_experience_years` decimal(5,1) DEFAULT 0.0,
  `meets_requirements` tinyint(1) DEFAULT 0,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` timestamp NULL DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `interview_date` date DEFAULT NULL,
  `interview_time` time DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_position` (`user_id`,`position_id`),
  KEY `idx_applications_status` (`status`),
  KEY `idx_applications_position` (`position_id`),
  KEY `idx_applications_archived` (`is_archived`,`archived_at`),
  KEY `idx_applications_interview` (`interview_date`,`interview_time`),
  CONSTRAINT `fk_applications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_applications_position` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `user_experience` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(200) NOT NULL,
  `position_title` varchar(200) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `years_duration` decimal(5,1) DEFAULT 0.0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_experience_user` (`user_id`),
  CONSTRAINT `fk_experience_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activity_user` (`user_id`),
  KEY `idx_activity_action` (`action`),
  KEY `idx_activity_created` (`created_at`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `application_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `document_type` enum('resume','cover_letter','certificate','portfolio','other') NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_application_documents_app_id` (`application_id`),
  KEY `idx_application_documents_type` (`document_type`),
  CONSTRAINT `fk_app_docs_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `user_training_certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_training_certificates_user_id` (`user_id`),
  KEY `idx_training_certificates_uploaded_at` (`uploaded_at`),
  CONSTRAINT `fk_training_certificates_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `user_application_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `document_type` enum('APLET','PDS','WES','TOR','DIP','COE','SRE','EL') NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_app_documents_user_id` (`user_id`),
  KEY `idx_app_documents_type` (`document_type`),
  KEY `idx_app_documents_user_type` (`user_id`,`document_type`),
  CONSTRAINT `fk_user_app_documents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) DEFAULT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) DEFAULT NULL,
  `sender_role` enum('admin','applicant') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_messages_application` (`application_id`),
  KEY `idx_messages_sender` (`sender_id`),
  KEY `idx_messages_recipient` (`recipient_id`),
  KEY `idx_messages_created` (`created_at`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'application_submitted',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_type` (`type`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `employment_types` (`name`, `slug`, `is_active`) VALUES
('Full-time', 'full-time', 1),
('Part-time', 'part-time', 1),
('Contract', 'contract', 1),
('Internship', 'internship', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
