<?php
/**
 * Email Configuration
 * Copy this file to email.php and update with your SMTP settings
 */

return [
    // SMTP Settings
    'smtp_host' => 'smtp.gmail.com',           // SMTP server (Gmail, Office365, etc.)
    'smtp_port' => 587,                         // SMTP port (587 for TLS, 465 for SSL)
    'smtp_secure' => 'tls',                     // Encryption: 'tls' or 'ssl'
    'smtp_auth' => true,                        // Enable SMTP authentication
    'smtp_username' => 'your-email@gmail.com',  // SMTP username
    'smtp_password' => 'your-app-password',     // SMTP password or app password
    
    // Email Settings
    'from_email' => 'noreply@dumaran.gov.ph', // From email address
    'from_name' => 'Dumaran Hiring System',      // From name
    'reply_to' => 'support@dumaran.gov.ph',   // Reply-to email
    
    // Debug Settings (0 = off, 1 = client, 2 = server)
    'debug_level' => 0,
    
    // For Gmail:
    // 1. Enable 2-factor authentication
    // 2. Generate an App Password: https://myaccount.google.com/apppasswords
    // 3. Use the app password in 'smtp_password' field
    
    // For Mailtrap (Testing):
    // 'smtp_host' => 'smtp.mailtrap.io',
    // 'smtp_port' => 2525,
    // 'smtp_secure' => 'tls',
    // 'smtp_username' => 'your-mailtrap-username',
    // 'smtp_password' => 'your-mailtrap-password',
    
    // For Office 365:
    // 'smtp_host' => 'smtp.office365.com',
    // 'smtp_port' => 587,
    // 'smtp_secure' => 'tls',
];
