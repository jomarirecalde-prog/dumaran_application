-- Migration: Split full name into last_name, first_name, middle_name
-- Date: 2026-10-09

ALTER TABLE `users`
ADD COLUMN `last_name` varchar(80) DEFAULT NULL AFTER `full_name`,
ADD COLUMN `first_name` varchar(80) DEFAULT NULL AFTER `last_name`,
ADD COLUMN `middle_name` varchar(80) DEFAULT NULL AFTER `first_name`;
