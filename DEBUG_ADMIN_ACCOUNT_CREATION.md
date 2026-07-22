# Admin Account Creation Debugging Guide

## Issue
Adding an admin account via `admin/pages/settings.php?tab=accounts` is not working.

## Changes Made

### 1. Enhanced Error Logging in `admin/pages/settings.php`
Added comprehensive step-by-step logging to track the exact point of failure:
- Logs every validation step
- Logs database operations
- Logs success and failure states
- Logs exception details

### 2. Improved Database Error Handling in `includes/models.php`
Enhanced the `User::create()` method to:
- Log SQL errors with full error information
- Log the SQL query and parameters being used
- Confirm successful user creation

### 3. Added Activity Logging
The admin creation now logs the activity to track who created the admin account.

## How to Debug

### Step 1: Check PHP Error Logs
The error logs will show numbered steps (Step 1, Step 2, etc.) to help identify where the process fails.

**Location of error logs:**
- XAMPP: `C:\xampp\apache\logs\error.log` or `C:\xampp\php\logs\php_error_log`
- Check `php.ini` for the `error_log` directive to find the exact location

### Step 2: Test the Form
1. Open your browser and navigate to: `http://localhost/Applicant_WPU/admin/pages/settings.php?tab=accounts`
2. Fill out the admin creation form with test data:
   - Full Name: Test Admin
   - Username: testadmin
   - Email: test@admin.com
   - Phone: (optional)
   - Password: testpassword123
   - Confirm Password: testpassword123
3. Click "Create Admin Account"

### Step 3: Review Error Logs
Check the error log file for entries like:
```
=== Admin Account Creation Attempt ===
Step 1: Form submission detected
Step 2: Validation result: ...
```

The log will show you exactly where the process stops or fails.

### Step 4: Common Issues and Solutions

#### Issue: Form submission not detected (no "Step 1" log)
**Possible causes:**
- JavaScript is blocking the form submission
- Browser console has errors
- Form is not submitting due to validation errors

**Solution:**
1. Open browser Developer Tools (F12)
2. Go to Console tab
3. Check for JavaScript errors
4. Try disabling the "prevent-double-submit" class temporarily

#### Issue: Validation fails (stops at Step 3-5)
**Possible causes:**
- CSRF token invalid
- Form token invalid or duplicate

**Solution:**
1. Clear browser cache and cookies
2. Try in an incognito/private window
3. Check if session is working properly

#### Issue: Required fields validation fails (Step 8)
**Possible causes:**
- Form fields not being submitted properly
- POST data not reaching the server

**Solution:**
1. Check Network tab in Developer Tools
2. Verify all form fields are being sent
3. Check if POST request is successful (Status 200)

#### Issue: Database creation fails (Step 23)
**Possible causes:**
- Database connection issue
- Missing required fields in database
- Database constraints violation

**Solution:**
1. Check if XAMPP MySQL is running
2. Verify database exists and is accessible
3. Check database error in the log (will show SQL error details)
4. Verify all database columns exist

#### Issue: Email or username already exists (Step 13-14)
**Possible causes:**
- Trying to create an admin with existing credentials

**Solution:**
1. Use different email and username
2. Check existing admin accounts in the database

## Database Check

### Verify Database Connection
```sql
-- In phpMyAdmin or MySQL command line
USE wpu_applicant_system;
SELECT * FROM users WHERE role = 'admin';
```

### Check Table Structure
```sql
DESCRIBE users;
```

Verify these columns exist:
- id
- username
- email
- password
- role
- full_name
- phone
- address
- age
- sex
- google_id
- oauth_provider
- email_verified_at
- created_at
- updated_at
- is_active

## Testing After Debug

Once you've identified and fixed the issue:

1. **Clear form tokens:**
   ```php
   // In PHP console or temporary test file
   session_start();
   unset($_SESSION['form_tokens']);
   echo "Form tokens cleared";
   ```

2. **Test again with fresh data**

3. **Verify success:**
   - Check for green success message
   - Verify new admin appears in the admin list
   - Check database for new user record

## Additional Troubleshooting

### Enable Full PHP Error Display
In `config/config.php`, verify these lines exist:
```php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
```

### Check Browser Network Tab
1. Open Developer Tools (F12)
2. Go to Network tab
3. Submit the form
4. Check the POST request to `settings.php`
5. Verify:
   - Status: Should be 200 or 302 (redirect)
   - Form Data: All fields should be present
   - Response: Check for any error messages

### Manual Database Insert Test
Test if you can create a user manually:
```sql
INSERT INTO users (username, email, password, role, full_name, phone, address, created_at, updated_at, is_active, email_verified_at) 
VALUES (
    'manualtest',
    'manual@test.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    'Manual Test Admin',
    NULL,
    NULL,
    NOW(),
    NOW(),
    1,
    NOW()
);
```

If this fails, there's a database issue that needs to be resolved.

## Success Indicators

When working correctly, you should see:
1. Error log showing all steps up to Step 22
2. "Admin account created successfully" message on the page
3. Redirect back to settings page with accounts tab
4. New admin visible in the admin accounts list
5. New record in `users` table with role='admin'
6. New record in `activity_logs` table with action='admin_created'

## Need More Help?

If the issue persists after following this guide:
1. Capture the complete error log output
2. Check browser console for JavaScript errors
3. Verify XAMPP MySQL and Apache are running
4. Check database permissions
5. Verify PHP version (should be 7.4 or higher)

