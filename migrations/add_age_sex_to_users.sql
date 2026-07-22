-- Migration: Add age and sex columns to users table
-- Date: 2026-01-08

ALTER TABLE `users` 
ADD COLUMN `age` int(11) DEFAULT NULL AFTER `address`,
ADD COLUMN `sex` enum('Male','Female','Other') DEFAULT NULL AFTER `age`;

