<?php
/**
 * OAuth Configuration Example File
 * 
 * SETUP INSTRUCTIONS:
 * ===================
 * 
 * Option 1: Edit config/oauth.php directly
 * -----------------------------------------
 * Open config/oauth.php and replace these values:
 *   'client_id' => 'YOUR_ACTUAL_CLIENT_ID.apps.googleusercontent.com',
 *   'client_secret' => 'YOUR_ACTUAL_CLIENT_SECRET',
 * 
 * Option 2: Use constants in config/config.php (Recommended)
 * -----------------------------------------------------------
 * Add these lines to config/config.php:
 * 
 * define('GOOGLE_CLIENT_ID', 'YOUR_ACTUAL_CLIENT_ID.apps.googleusercontent.com');
 * define('GOOGLE_CLIENT_SECRET', 'YOUR_ACTUAL_CLIENT_SECRET');
 * 
 * Option 3: Use environment variables (Most secure)
 * --------------------------------------------------
 * Set these environment variables:
 *   GOOGLE_CLIENT_ID=YOUR_ACTUAL_CLIENT_ID.apps.googleusercontent.com
 *   GOOGLE_CLIENT_SECRET=YOUR_ACTUAL_CLIENT_SECRET
 * 
 * 
 * HOW TO GET GOOGLE OAUTH CREDENTIALS:
 * =====================================
 * 
 * Step 1: Go to Google Cloud Console
 * -----------------------------------
 * Visit: https://console.cloud.google.com/
 * 
 * Step 2: Create or Select Project
 * ---------------------------------
 * - Click "Select a project" at the top
 * - Click "NEW PROJECT" if you don't have one
 * - Give it a name like "WPU Applicant System"
 * - Click "CREATE"
 * 
 * Step 3: Enable Required APIs
 * -----------------------------
 * - Go to "APIs & Services" > "Library"
 * - Search for "Google+ API" or "People API" and enable it
 * - Search for "Google Identity Services" and enable it
 * 
 * Step 4: Create OAuth 2.0 Credentials
 * -------------------------------------
 * - Go to "APIs & Services" > "Credentials"
 * - Click "+ CREATE CREDENTIALS" at the top
 * - Select "OAuth 2.0 Client IDs"
 * - If prompted, configure the OAuth consent screen:
 *   - User Type: External (for testing) or Internal (if you have Google Workspace)
 *   - Fill in app name: "WPU Applicant System"
 *   - Add your email as support email
 *   - Add scopes: userinfo.email and userinfo.profile
 *   - Add test users if using External type
 * - Back to Credentials, click "CREATE CREDENTIALS" > "OAuth client ID"
 * - Application type: "Web application"
 * - Name: "WPU Applicant System - Web"
 * - Authorized JavaScript origins (optional):
 *   http://localhost
 * - Authorized redirect URIs:
 *   http://localhost/Applicant_WPU/auth/google_oauth.php
 *   (Add your production URL when deploying)
 * - Click "CREATE"
 * 
 * Step 5: Copy Your Credentials
 * ------------------------------
 * - You'll see a popup with your Client ID and Client Secret
 * - Copy these values carefully
 * - Paste them into config/oauth.php or use one of the options above
 * 
 * Step 6: Test the Integration
 * -----------------------------
 * - Visit your login page: http://localhost/Applicant_WPU/shared/login.php
 * - Click "Continue with Google"
 * - You should be redirected to Google's login page
 * - After logging in, you'll be redirected back to your dashboard
 * 
 * 
 * TROUBLESHOOTING:
 * ================
 * 
 * Error: "redirect_uri_mismatch"
 * - The redirect URI in Google Console must EXACTLY match:
 *   http://localhost/Applicant_WPU/auth/google_oauth.php
 * - Check for trailing slashes, http vs https, etc.
 * 
 * Error: "invalid_client"
 * - Your Client ID or Client Secret is incorrect
 * - Re-check the values in config/oauth.php
 * 
 * Error: "access_denied"
 * - User clicked "Cancel" on Google's consent screen
 * - Or your app is restricted and user is not in test users list
 * 
 * Error: "400 Bad Request"
 * - OAuth credentials are not configured (still using placeholder values)
 * - Check that you've replaced 'your-google-client-id-here'
 * 
 * 
 * PRODUCTION DEPLOYMENT:
 * ======================
 * 
 * 1. In Google Cloud Console, add production redirect URI:
 *    https://yourdomain.com/auth/google_oauth.php
 * 
 * 2. Update BASE_URL in config/config.php to your production URL
 * 
 * 3. Verify OAuth consent screen is published (not in testing mode)
 * 
 * 4. Use environment variables for credentials (most secure)
 * 
 * 5. Enable HTTPS for all OAuth flows
 * 
 */

// This is an example file - actual configuration is in config/oauth.php
?>
