# WPU Applicant System - Installation & Setup Guide

## System Overview

The WPU Applicant System is a comprehensive job application platform that allows:
- **Applicants** to browse job positions, apply for roles, and track their applications
- **Administrators** to post job positions, manage applications, and review candidates
- **Mobile Support** with dedicated mobile-optimized interface
- **Experience Validation** ensuring applicants meet minimum experience requirements

## Features

### ✅ Core Features Implemented

1. **Authentication System**
   - Secure login/logout for both applicants and administrators
   - Role-based access control
   - Session management with security measures

2. **Admin Panel**
   - Dashboard with statistics and overview
   - Position management (create, edit, delete)
   - Application review and management
   - User management capabilities

3. **Applicant Interface**
   - User-friendly dashboard
   - Job browsing and application
   - Experience management
   - Application tracking

4. **Mobile Interface**
   - Separate mobile-optimized UI/UX
   - Touch-friendly interactions
   - Bottom navigation
   - Responsive design

5. **Experience Validation**
   - Automatic calculation of total work experience
   - Requirement matching for job positions
   - Experience-based application filtering

6. **Confirmation System**
   - User-friendly confirmation dialogs
   - Delete confirmation for critical actions
   - CSRF protection

## Installation Steps

### 1. Prerequisites
- XAMPP (Apache, MySQL, PHP 7.4+)
- Web browser (Chrome, Firefox, Safari)

### 2. Database Setup

1. Start XAMPP and ensure Apache and MySQL are running
2. Access phpMyAdmin: `http://localhost/phpmyadmin`
3. Create a new database named `wpu_applicant_system`
4. Import the database schema:
   ```sql
   -- Navigate to: config/database_schema.sql
   -- Copy and paste the entire SQL content into phpMyAdmin SQL tab
   -- Execute the queries
   ```

### 3. File Configuration

1. Update database credentials in `config/database.php` if needed:
   ```php
   private $host = 'localhost';
   private $db_name = 'wpu_applicant_system';
   private $username = 'root';  // Your MySQL username
   private $password = '';      // Your MySQL password
   ```

2. Create upload directories:
   ```
   mkdir uploads
   mkdir uploads/resumes
   chmod 755 uploads uploads/resumes
   ```

### 4. Access the Application

1. **Main Entry Point**: `http://localhost/Applicant_WPU/`
2. **Desktop Login**: `http://localhost/Applicant_WPU/login.php`
3. **Mobile Interface**: Automatically detected and redirected

## Default Login Credentials

### Administrator Account
- **Email**: `admin@wpu.ac.id`
- **Password**: `password`
- **Role**: Admin

### Test Applicant Account
After setup, create applicant accounts using the registration form.

## File Structure

```
Applicant_WPU/
├── config/
│   ├── database.php          # Database connection
│   ├── config.php           # Main configuration
│   └── database_schema.sql  # Database structure
├── includes/
│   ├── models.php           # Database models
│   └── confirmation.js      # UI confirmation system
├── auth/
│   ├── login_process.php    # Login handler
│   └── logout.php          # Logout handler
├── admin/
│   ├── dashboard.php        # Admin dashboard
│   └── positions.php       # Position management
├── mobile/
│   └── dashboard.php        # Mobile interface
├── desktop/
│   └── admin/              # Desktop admin interface
├── index.php               # Main entry point
├── login.php              # Login page
├── register.php           # Registration page
├── dashboard.php          # Applicant dashboard
└── apply.php             # Job application form
```

## Testing the Application

### 1. Admin Functionality Test

1. **Login as Admin**
   - Navigate to: `http://localhost/Applicant_WPU/login.php`
   - Select "Admin" role
   - Use credentials: `admin@wpu.ac.id` / `password`

2. **Test Position Management**
   - Go to "Manage Positions"
   - Create a new job position with:
     - Title: "Software Developer"
     - Description: "Develop web applications"
     - Requirements: "Bachelor's degree, 2+ years experience"
     - Minimum Experience: 2 years
   - Verify position appears in the list
   - Test edit functionality
   - Test delete confirmation dialog

3. **Dashboard Verification**
   - Check statistics display correctly
   - Verify recent applications section
   - Confirm active positions list

### 2. Applicant Functionality Test

1. **Create Applicant Account**
   - Navigate to registration page
   - Fill out complete form
   - Verify account creation success

2. **Login as Applicant**
   - Use created credentials
   - Verify redirect to applicant dashboard

3. **Test Job Application Flow**
   - Browse available positions
   - Click "Apply Now" for a position
   - Fill out application form
   - Upload resume file
   - Submit application
   - Verify confirmation dialog works

4. **Experience Management**
   - Add work experience entries
   - Verify total experience calculation
   - Check experience requirements matching

### 3. Mobile Interface Test

1. **Access from Mobile Device**
   - Visit: `http://localhost/Applicant_WPU/` from mobile
   - Verify automatic mobile detection
   - Test responsive design elements

2. **Mobile Navigation**
   - Test bottom navigation
   - Verify touch-friendly interactions
   - Check mobile-optimized layouts

### 4. Security Features Test

1. **CSRF Protection**
   - Verify forms include CSRF tokens
   - Test form submissions work correctly

2. **Session Management**
   - Test login/logout functionality
   - Verify session timeout handling
   - Check role-based access control

3. **Confirmation Dialogs**
   - Test delete confirmations
   - Verify logout confirmation
   - Check application submission confirmation

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Verify MySQL is running in XAMPP
   - Check database credentials in `config/database.php`
   - Ensure database `wpu_applicant_system` exists

2. **File Upload Issues**
   - Check upload directory permissions: `chmod 755 uploads/`
   - Verify PHP file upload settings in `php.ini`

3. **Mobile Detection Not Working**
   - Clear browser cache
   - Test with actual mobile device
   - Check User-Agent detection in `config/config.php`

4. **Confirmation Dialogs Not Showing**
   - Ensure Bootstrap JavaScript is loaded
   - Check browser console for JavaScript errors
   - Verify `includes/confirmation.js` is included

### Performance Optimization

1. **Database Indexing**
   - Indexes are already created in schema
   - Monitor query performance as data grows

2. **File Upload Optimization**
   - Consider implementing file size validation
   - Add file type verification
   - Implement virus scanning for production

## Security Considerations

1. **Password Security**
   - Passwords are hashed using PHP's `password_hash()`
   - Consider implementing password strength requirements

2. **File Upload Security**
   - Implement file type validation
   - Scan uploaded files for malware
   - Store uploads outside web root in production

3. **SQL Injection Prevention**
   - All queries use prepared statements
   - Input sanitization implemented

4. **XSS Prevention**
   - All output is properly escaped
   - HTML special characters are handled

## Production Deployment

When deploying to production:

1. **Update Configuration**
   - Change database credentials
   - Update `BASE_URL` in config
   - Enable HTTPS

2. **Security Hardening**
   - Change default admin password
   - Implement rate limiting
   - Configure proper file permissions

3. **Backup Strategy**
   - Regular database backups
   - File system backups
   - Version control integration

## Support & Maintenance

For ongoing support:
1. Monitor application logs
2. Regular security updates
3. Database maintenance
4. User feedback integration

---

**System Requirements Met:**
- ✅ Admin and applicant login/logout
- ✅ Position management with experience requirements
- ✅ Experience validation (2+ years example)
- ✅ User-friendly desktop UI/UX
- ✅ Separate mobile interface structure
- ✅ Confirmation dialogs for delete operations
- ✅ PHP backend implementation
- ✅ Responsive design for mobile compatibility

The WPU Applicant System is now ready for use!