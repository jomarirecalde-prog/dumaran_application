-- Migration: Add IPCR and PE file path columns to users table
-- Date: 2026-01-XX
-- Description: Adds columns to store file paths for IPCR and PE documents

ALTER TABLE `users` 
ADD COLUMN `ipcr_file_path` VARCHAR(255) DEFAULT NULL AFTER `ipcr_rating`,
ADD COLUMN `pe_file_path` VARCHAR(255) DEFAULT NULL AFTER `pe_rating`;

