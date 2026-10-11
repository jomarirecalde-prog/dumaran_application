<?php
/**
 * Email Service
 * Handles email notifications using PHPMailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/SMTP.php';

class EmailService {
    private $config;
    private $mail;

    private function siteName(): string {
        return defined('SITE_NAME') ? SITE_NAME : 'Dumaran Hiring System';
    }

    public function __construct() {
        // Load email configuration
        $configPath = __DIR__ . '/../../config/email.php';
        if (file_exists($configPath)) {
            $this->config = require $configPath;
        } else {
            // Default configuration
            $this->config = [
                'smtp_host' => 'sandbox.smtp.mailtrap.io',
                'smtp_port' => 2525,
                'smtp_secure' => 'tls',
                'smtp_auth' => true,
                'smtp_username' => '',
                'smtp_password' => '',
                'from_email' => 'noreply@dumaran.gov.ph',
                'from_name' => $this->siteName(),
                'reply_to' => 'support@dumaran.gov.ph',
                'debug_level' => 0,
            ];
        }
    }

    /**
     * Initialize PHPMailer
     */
    private function initMailer() {
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->SMTPDebug = $this->config['debug_level'];
            $mail->isSMTP();
            $mail->Host       = $this->config['smtp_host'];
            $mail->SMTPAuth   = $this->config['smtp_auth'];
            $mail->Username   = $this->config['smtp_username'];
            $mail->Password   = $this->config['smtp_password'];
            $mail->SMTPSecure = $this->config['smtp_secure'];
            $mail->Port       = $this->config['smtp_port'];
            
            // Recipients
            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $mail->addReplyTo($this->config['reply_to'], $this->config['from_name']);
            
            // Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            
            return $mail;
        } catch (Exception $e) {
            error_log("PHPMailer initialization error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send email
     */
    private function send($to, $subject, $body, $isHtml = true) {
        try {
            $mail = $this->initMailer();
            
            $mail->addAddress($to);
            $mail->Subject = $subject;
            
            if ($isHtml) {
                $mail->Body = $body;
                $mail->AltBody = strip_tags($body);
            } else {
                $mail->Body = $body;
                $mail->isHTML(false);
            }
            
            $result = $mail->send();
            
            if ($result) {
                error_log("Email sent successfully to: $to | Subject: $subject");
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Email sending failed to: $to | Error: {$mail->ErrorInfo}");
            throw new Exception("Failed to send email: {$mail->ErrorInfo}");
        }
    }

    /**
     * Send welcome email
     */
    public function sendWelcomeEmail($user) {
        $siteName = $this->siteName();
        $subject = 'Welcome to ' . $siteName;
        
        $body = $this->getEmailTemplate([
            'title' => 'Welcome to ' . $siteName,
            'greeting' => "Hello {$user['full_name']},",
            'content' => "
                <p>Thank you for registering with {$siteName}!</p>
                <p>You can now:</p>
                <ul>
                    <li>Browse available job positions</li>
                    <li>Submit job applications</li>
                    <li>Track your application status</li>
                    <li>Manage your profile and experience</li>
                </ul>
                <p>We wish you the best of luck in your job search!</p>
            ",
            'action' => [
                'text' => 'Go to Dashboard',
                'url' => BASE_URL . 'applicant/pages/dashboard.php'
            ]
        ]);

        return $this->send($user['email'], $subject, $body);
    }

    /**
     * Send email verification code
     */
    public function sendEmailVerificationCode($user, $code, $isResend = false) {
        $subject = $isResend ? 'Your New Verification Code' : 'Verify Your Email Address';
        $expiryMinutes = EMAIL_VERIFICATION_EXPIRY_MINUTES;
        $verificationUrl = BASE_URL . 'shared/verify-email.php?email=' . urlencode($user['email']);

        $content = "
            <p>Use the verification code below to confirm your email address and activate your account.</p>
            <div style='background: #eef2ff; border-left: 4px solid #4f46e5; padding: 20px; border-radius: 10px; margin: 24px 0; text-align: center;'>
                <div style='color: #1f2937; font-size: 16px; margin-bottom: 12px;'>Your verification code</div>
                <div style='font-size: 32px; letter-spacing: 8px; font-weight: 700; color: #4338ca;'>{$code}</div>
                <div style='color: #4b5563; margin-top: 12px; font-size: 14px;'>This code expires in {$expiryMinutes} minutes.</div>
            </div>
            <p>If you did not request this code, you can safely ignore this email.</p>
        ";

        $body = $this->getEmailTemplate([
            'title' => $subject,
            'greeting' => "Hello {$user['full_name']},",
            'content' => $content,
            'action' => [
                'text' => 'Verify Email',
                'url' => $verificationUrl
            ]
        ]);

        return $this->send($user['email'], $subject, $body);
    }

    /**
     * Send application submitted confirmation
     */
    public function sendApplicationSubmitted($user, $position, $application) {
        $subject = 'Application Received - Waiting for Confirmation';
        
        $body = $this->getEmailTemplate([
            'title' => 'Application Received',
            'greeting' => "Hello {$user['full_name']},",
            'content' => "
                <p>Your application has been successfully submitted and is now <strong style='color: #f59e0b;'>waiting for confirmation</strong> from our recruitment team.</p>
                <div style='background: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; border-radius: 8px; margin: 16px 0;'>
                    <div style='display: flex; align-items: center; gap: 12px;'>
                        <div style='font-size: 24px;'>⏳</div>
                        <div>
                            <strong style='color: #92400e;'>Status: Pending Review</strong><br>
                            <span style='color: #78350f; font-size: 0.9rem;'>We will review your application and notify you of our decision.</span>
                        </div>
                    </div>
                </div>
                <div style='background: #f3f4f6; padding: 16px; border-radius: 8px; margin: 16px 0;'>
                    <strong>Position:</strong> {$position['title']}<br>
                    <strong>Application Date:</strong> " . date('F j, Y', strtotime($application['applied_at'])) . "<br>
                    <strong>Total Experience:</strong> {$application['total_experience_years']} years
                </div>
                <p><strong>What happens next?</strong></p>
                <ul style='color: #4b5563; line-height: 1.8;'>
                    <li>Our recruitment team will review your application</li>
                    <li>You will receive an email notification once your application is <strong>approved</strong> or <strong>rejected</strong></li>
                    <li>You can track your application status anytime from your dashboard</li>
                </ul>
                <p>Thank you for your interest in joining our team!</p>
            ",
        ]);

        return $this->send($user['email'], $subject, $body);
    }

    /**
     * Send application approved congratulation email
     */
    public function sendApplicationApproved($user, $position, $application) {
        $subject = "Congratulations! Your Application Has Been Approved - {$position['title']}";
        
        // Use reviewed_at if available, otherwise use current time
        $reviewedDate = isset($application['reviewed_at']) && $application['reviewed_at'] 
            ? date('F j, Y', strtotime($application['reviewed_at'])) 
            : date('F j, Y');
        $siteName = $this->siteName();
        
        $body = $this->getEmailTemplate([
            'title' => 'Application Approved',
            'greeting' => "Hello {$user['full_name']},",
            'content' => "
                <div style='background: #d1fae5; border-left: 4px solid #10b981; padding: 24px; border-radius: 8px; margin: 24px 0; text-align: center;'>
                    <div style='font-size: 48px; margin-bottom: 16px;'>🎉</div>
                    <h2 style='color: #065f46; margin: 0 0 12px 0; font-size: 24px;'>Congratulations!</h2>
                    <p style='color: #15803D; margin: 0; font-size: 16px;'>Your application has been <strong style='color: #065f46;'>approved</strong>!</p>
                </div>
                
                <p>We are thrilled to inform you that your application for <strong>{$position['title']}</strong> has been <strong style='color: #10b981;'>successfully approved</strong> by our recruitment team.</p>
                
                <div style='background: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <div style='margin-bottom: 12px;'>
                        <strong style='color: #1f2937;'>💼 Position:</strong> 
                        <span style='color: #4b5563;'>{$position['title']}</span>
                    </div>
                    <div style='margin-bottom: 12px;'>
                        <strong style='color: #1f2937;'>✅ Status:</strong> 
                        <span style='color: #10b981; font-weight: bold;'>Approved</span>
                    </div>
                    <div style='margin-bottom: 12px;'>
                        <strong style='color: #1f2937;'>📅 Approved Date:</strong> 
                        <span style='color: #4b5563;'>{$reviewedDate}</span>
                    </div>
                    <div>
                        <strong style='color: #1f2937;'>📊 Total Experience:</strong> 
                        <span style='color: #4b5563;'>{$application['total_experience_years']} years</span>
                    </div>
                </div>
                
                <div style='background: #EFF5FF; border-left: 4px solid #2050A0; padding: 16px; border-radius: 8px; margin: 20px 0;'>
                    <strong style='color: #2050A0;'>📬 What's Next?</strong>
                    <p style='color: #1e3a8a; margin: 12px 0 0 0; line-height: 1.8;'>
                        Our team will be in touch with you shortly to discuss the next steps in the hiring process. 
                        Please keep an eye on your email and ensure your contact information is up to date.
                    </p>
                </div>
                
                <p style='margin-top: 24px;'>We were impressed with your qualifications and experience, and we look forward to the possibility of welcoming you to our team!</p>
                
                <p>Thank you for your interest in joining {$siteName}.</p>
            ",
            'action' => [
                'text' => 'View Application Details',
                'url' => BASE_URL . 'applicant/pages/application-details.php?id=' . $application['id']
            ]
        ]);

        return $this->send($user['email'], $subject, $body);
    }

    /**
     * Send application status update
     */
    public function sendApplicationStatusUpdate($user, $position, $application) {
        $status = $application['status'];
        $statusColor = $status === STATUS_APPROVED ? '#10b981' : '#ef4444';
        $statusText = ucfirst($status);
        
        $subject = "Application {$statusText}: {$position['title']}";
        
        $content = '';
        if ($status === STATUS_APPROVED) {
            $content = "
                <p>Congratulations! We are pleased to inform you that your application has been <strong style='color: {$statusColor};'>approved</strong>.</p>
                <p>We were impressed with your qualifications and experience. Our team will contact you shortly with the next steps.</p>
            ";
        } else if ($status === STATUS_REJECTED) {
            $content = "
                <p>Thank you for your interest in the position. After careful consideration, we have decided not to move forward with your application at this time.</p>
                <p>We encourage you to apply for other positions that match your qualifications.</p>
            ";
        }
        
        // Use reviewed_at if available, otherwise use current time
        $reviewedDate = isset($application['reviewed_at']) && $application['reviewed_at'] 
            ? date('F j, Y H:i', strtotime($application['reviewed_at'])) 
            : date('F j, Y H:i');
        
        $body = $this->getEmailTemplate([
            'title' => "Application {$statusText}",
            'greeting' => "Hello {$user['full_name']},",
            'content' => "
                <div style='background: #f3f4f6; padding: 16px; border-radius: 8px; margin: 16px 0;'>
                    <strong>Position:</strong> {$position['title']}<br>
                    <strong>Status:</strong> <span style='color: {$statusColor}; font-weight: bold;'>{$statusText}</span><br>
                    <strong>Updated:</strong> {$reviewedDate}
                </div>
                {$content}
            ",
            'action' => [
                'text' => 'View Application Details',
                'url' => BASE_URL . 'applicant/pages/application-details.php?id=' . $application['id']
            ]
        ]);

        return $this->send($user['email'], $subject, $body);
    }

    /**
     * Send new application notification to admin
     */
    public function sendNewApplicationNotification($admin, $user, $position, $application) {
        $subject = "New Application: {$position['title']}";
        
        $body = $this->getEmailTemplate([
            'title' => 'New Application Received',
            'greeting' => "Hello {$admin['full_name']},",
            'content' => "
                <p>A new application has been submitted:</p>
                <div style='background: #f3f4f6; padding: 16px; border-radius: 8px; margin: 16px 0;'>
                    <strong>Applicant:</strong> {$user['full_name']}<br>
                    <strong>Email:</strong> {$user['email']}<br>
                    <strong>Position:</strong> {$position['title']}<br>
                    <strong>Experience:</strong> {$application['total_experience_years']} years<br>
                    <strong>Meets Requirements:</strong> " . ($application['meets_requirements'] ? 'Yes' : 'No') . "<br>
                    <strong>Applied:</strong> " . date('F j, Y H:i', strtotime($application['applied_at'])) . "
                </div>
                <p>Please review the application at your earliest convenience.</p>
            ",
            'action' => [
                'text' => 'Review Application',
                'url' => BASE_URL . 'admin/pages/applications.php'
            ]
        ]);

        return $this->send($admin['email'], $subject, $body);
    }

    /**
     * Send password reset email
     */
    public function sendPasswordReset($user, $resetToken) {
        $subject = 'Password Reset Request';
        
        $resetUrl = BASE_URL . 'auth/reset-password.php?token=' . $resetToken;
        
        $body = $this->getEmailTemplate([
            'title' => 'Password Reset Request',
            'greeting' => "Hello {$user['full_name']},",
            'content' => "
                <p>We received a request to reset your password. Click the button below to create a new password:</p>
                <p style='color: #6b7280; font-size: 0.875rem;'>This link will expire in 1 hour.</p>
                <p style='color: #6b7280; font-size: 0.875rem;'>If you didn't request this, please ignore this email.</p>
            ",
            'action' => [
                'text' => 'Reset Password',
                'url' => $resetUrl
            ]
        ]);

        return $this->send($user['email'], $subject, $body);
    }

    /**
     * Email template
     */
    private function getEmailTemplate($data) {
        $siteName = $this->siteName();
        $title = $data['title'] ?? $siteName;
        $greeting = $data['greeting'] ?? 'Hello,';
        $content = $data['content'] ?? '';
        $action = $data['action'] ?? null;

        $actionHtml = '';
        if ($action) {
            $actionHtml = "
                <table width='100%' cellpadding='0' cellspacing='0'>
                    <tr>
                        <td align='center' style='padding: 24px 0;'>
                            <a href='{$action['url']}' style='background: linear-gradient(135deg, #2050A0 0%, #2050A0 100%); color: white; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: 600; display: inline-block;'>
                                {$action['text']}
                            </a>
                        </td>
                    </tr>
                </table>
            ";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; background: #f3f4f6;'>
            <table width='100%' cellpadding='0' cellspacing='0' style='background: #f3f4f6; padding: 40px 20px;'>
                <tr>
                    <td align='center'>
                        <table width='600' cellpadding='0' cellspacing='0' style='background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);'>
                            <!-- Header -->
                            <tr>
                                <td style='background: linear-gradient(135deg, #2050A0 0%, #2050A0 100%); padding: 32px; text-align: center; border-radius: 12px 12px 0 0;'>
                                    <h1 style='color: white; margin: 0; font-size: 24px; font-weight: 700;'>
                                        {$siteName}
                                    </h1>
                                </td>
                            </tr>
                            
                            <!-- Content -->
                            <tr>
                                <td style='padding: 40px;'>
                                    <h2 style='color: #1f2937; margin: 0 0 16px 0; font-size: 20px;'>
                                        {$greeting}
                                    </h2>
                                    <div style='color: #4b5563; line-height: 1.6; font-size: 15px;'>
                                        {$content}
                                    </div>
                                    {$actionHtml}
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style='background: #f9fafb; padding: 24px; text-align: center; border-radius: 0 0 12px 12px; border-top: 1px solid #e5e7eb;'>
                                    <p style='color: #6b7280; margin: 0; font-size: 14px;'>
                                        © " . date('Y') . " {$siteName}. All rights reserved.
                                    </p>
                                    <p style='color: #9ca3af; margin: 8px 0 0 0; font-size: 13px;'>
                                        If you have any questions, please contact us at support@dumaran.gov.ph
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";
    }
}
