-- Non-destructive index additions for common admin list/filter queries.
-- Safe to run multiple times only if indexes do not already exist (check before applying in production).

USE `dumaran_hr`;

ALTER TABLE `applications`
  ADD INDEX `idx_applications_status_archived_applied` (`status`, `is_archived`, `applied_at`);

ALTER TABLE `applications`
  ADD INDEX `idx_applications_position_status` (`position_id`, `status`);
