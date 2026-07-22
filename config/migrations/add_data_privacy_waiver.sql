-- Migration: Add data privacy waiver acceptance to users table
-- Date: 2026-01-08
-- Description: Adds data_privacy_accepted_at column to track when users accept the data privacy waiver

-- Add data_privacy_accepted_at column
ALTER TABLE `users` 
ADD COLUMN `data_privacy_accepted_at` TIMESTAMP NULL DEFAULT NULL AFTER `email_verification_last_sent_at`;

-- Add index for better query performance
ALTER TABLE `users`
ADD KEY `idx_users_data_privacy_accepted` (`data_privacy_accepted_at`);

