-- Complete Messages Table Setup Script
-- This script will create the messages table if it doesn't exist
-- OR update it if it exists but is missing the recipient_id column

-- Step 1: Create messages table if it doesn't exist
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

-- Step 2: Make application_id nullable if it's not already
ALTER TABLE `messages` 
  MODIFY COLUMN `application_id` int(11) DEFAULT NULL;

-- Step 3: Add recipient_id column if it doesn't exist (with error handling)
SET @column_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'messages' 
    AND COLUMN_NAME = 'recipient_id'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `messages` 
     ADD COLUMN `recipient_id` int(11) DEFAULT NULL AFTER `sender_id`,
     ADD KEY `idx_messages_recipient` (`recipient_id`)',
    'SELECT "Column recipient_id already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 4: Add foreign key constraint if it doesn't exist
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'messages' 
    AND CONSTRAINT_NAME = 'messages_ibfk_3'
);

SET @sql2 = IF(@fk_exists = 0,
    'ALTER TABLE `messages`
     ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE',
    'SELECT "Foreign key messages_ibfk_3 already exists" AS message'
);

PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

