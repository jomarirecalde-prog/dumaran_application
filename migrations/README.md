# Database Migrations

## Add Interview Columns Migration

### File: `add_interview_columns.sql`

This migration adds the following columns to the `applications` table:
- `interview_date` (DATE) - Stores the scheduled interview date
- `interview_time` (TIME) - Stores the scheduled interview time

### How to Apply

1. Open phpMyAdmin or your MySQL client
2. Select the `dumaran_hr` database
3. Run the SQL script: `add_interview_columns.sql`

Or via command line:
```bash
mysql -u your_username -p dumaran_hr < add_interview_columns.sql
```

### What This Enables

After applying this migration, the system will automatically:
- Schedule interviews when an admin approves an application
- Find the next available interview slot (Monday-Friday, 8am-12pm or 1pm-5pm)
- Send email notifications to applicants with their interview date and time

