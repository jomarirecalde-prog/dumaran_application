-- Create notifications table
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'Admin user ID who should receive this notification',
  `type` varchar(50) NOT NULL DEFAULT 'application_submitted' COMMENT 'Type of notification',
  `title` varchar(255) NOT NULL COMMENT 'Notification title',
  `message` text NOT NULL COMMENT 'Notification message',
  `related_id` int(11) DEFAULT NULL COMMENT 'Related entity ID (e.g., application_id)',
  `related_type` varchar(50) DEFAULT NULL COMMENT 'Related entity type (e.g., application)',
  `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether notification has been read',
  `read_at` datetime DEFAULT NULL COMMENT 'When notification was read',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When notification was created',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_type` (`type`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System notifications for admin users';

