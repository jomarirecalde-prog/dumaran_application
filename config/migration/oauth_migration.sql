-- Migration script to add Google OAuth support to existing database
-- Run this script if you already have the database and want to add OAuth functionality

USE dumaran_hr;

-- Add new columns for OAuth support
ALTER TABLE users 
ADD COLUMN google_id VARCHAR(255) UNIQUE NULL AFTER profile_picture,
ADD COLUMN oauth_provider ENUM('google', 'facebook', 'microsoft') NULL AFTER google_id;

-- Make password nullable for OAuth users
ALTER TABLE users 
MODIFY COLUMN password VARCHAR(255) NULL;

-- Add indexes for the new columns
CREATE INDEX idx_users_google_id ON users(google_id);
CREATE INDEX idx_users_oauth_provider ON users(oauth_provider);

-- Update activity logs to include OAuth login events
-- (This is optional - existing logs will remain unchanged)

SELECT 'OAuth migration completed successfully!' as status;