ALTER TABLE users
    ADD COLUMN email_verified_at DATETIME NULL AFTER oauth_provider,
    ADD COLUMN email_verification_code VARCHAR(6) NULL AFTER email_verified_at,
    ADD COLUMN email_verification_expires_at DATETIME NULL AFTER email_verification_code,
    ADD COLUMN email_verification_attempts INT DEFAULT 0 AFTER email_verification_expires_at,
    ADD COLUMN email_verification_last_sent_at DATETIME NULL AFTER email_verification_attempts;

UPDATE users
SET email_verified_at = NOW()
WHERE role = 'admin' AND email_verified_at IS NULL;



