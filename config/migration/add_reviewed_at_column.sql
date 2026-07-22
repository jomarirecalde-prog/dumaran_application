-- Add reviewed_at column to applications table
-- This column tracks when an application's status was changed from pending

ALTER TABLE applications 
ADD COLUMN reviewed_at TIMESTAMP NULL DEFAULT NULL AFTER admin_notes;

-- For existing records that are not pending, set reviewed_at to updated_at
UPDATE applications 
SET reviewed_at = updated_at 
WHERE status IN ('approved', 'rejected');
