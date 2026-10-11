# Database Migrations

This directory contains SQL migration files for the Dumaran Hiring System.

## How to Apply Migrations

### Method 1: Using phpMyAdmin (Recommended for Development)

1. Open phpMyAdmin in your browser (usually at `http://localhost/phpmyadmin`)
2. Select your database (`dumaran_hr`)
3. Click on the "SQL" tab
4. Copy the contents of the migration file you want to run
5. Paste it into the SQL query box
6. Click "Go" to execute the migration

### Method 2: Using MySQL Command Line

```bash
mysql -u root -p dumaran_hr < add_archive_to_applications.sql
```

## Available Migrations

### add_archive_to_applications.sql
**Date:** 2025-11-16
**Description:** Adds archive functionality to the applications table

This migration adds:
- `is_archived` column (TINYINT) - Flag to mark applications as archived
- `archived_at` column (TIMESTAMP) - Timestamp when the application was archived
- Index on `is_archived` and `archived_at` for better query performance

**Required for:** Application History feature

## Migration Order

Migrations should be applied in the following order:
1. `add_archive_to_applications.sql`

## Rollback

To rollback the archive migration, run:

```sql
-- Remove the archive columns and index
ALTER TABLE `applications` 
DROP COLUMN `archived_at`,
DROP COLUMN `is_archived`,
DROP KEY `idx_applications_archived`;
```

## Notes

- Always backup your database before running migrations
- Test migrations on a development database first
- Migrations are designed to be idempotent where possible

