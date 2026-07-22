-- Add recipient_id field to messages table to support user-to-user messaging
-- Make application_id nullable to allow both application-based and user-to-user chats

-- First, make application_id nullable
ALTER TABLE `messages` 
  MODIFY COLUMN `application_id` int(11) DEFAULT NULL;

-- Then add recipient_id column
ALTER TABLE `messages` 
  ADD COLUMN `recipient_id` int(11) DEFAULT NULL AFTER `sender_id`,
  ADD KEY `idx_messages_recipient` (`recipient_id`);

-- Add foreign key constraint for recipient_id
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
