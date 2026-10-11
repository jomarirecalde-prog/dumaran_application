<?php
/**
 * User Service Class
 * Handles business logic for user-related operations
 */

require_once __DIR__ . '/../models.php';
require_once __DIR__ . '/EmailService.php';

class UserService {
    private $userModel;
    private $experienceModel;
    private $emailService;

    public function __construct() {
        $this->userModel = new User();
        $this->experienceModel = new Experience();
        $this->emailService = new EmailService();
    }

    private function generateVerificationCode() {
        return str_pad(
            (string) random_int(0, (10 ** EMAIL_VERIFICATION_CODE_LENGTH) - 1),
            EMAIL_VERIFICATION_CODE_LENGTH,
            '0',
            STR_PAD_LEFT
        );
    }

    private function getVerificationExpiryTimestamp() {
        return date('Y-m-d H:i:s', time() + (EMAIL_VERIFICATION_EXPIRY_MINUTES * 60));
    }

    private function dispatchVerificationCode(array $user, bool $isResend = false) {
        $code = $this->generateVerificationCode();
        $expiresAt = $this->getVerificationExpiryTimestamp();

        $this->userModel->setEmailVerificationCode($user['id'], $code, $expiresAt);
        $this->emailService->sendEmailVerificationCode($user, $code, $isResend);

        return [
            'code' => $code,
            'expires_at' => $expiresAt
        ];
    }

    /**
     * Register a new user
     */
    public function registerUser($data) {
        // Validate required fields
        $errors = [];
        
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required';
        }
        
        if (empty($data['username']) || strlen($data['username']) < 3) {
            $errors[] = 'Username must be at least 3 characters';
        }
        
        if (empty($data['password']) || strlen($data['password']) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        }
        
        normalizeUserNameFields($data);

        $hasNameParts = !empty(trim($data['last_name'] ?? '')) && !empty(trim($data['first_name'] ?? ''));
        if (!$hasNameParts && empty(trim($data['full_name'] ?? ''))) {
            $errors[] = 'Last name and first name are required';
        }
        
        if (!empty($errors)) {
            throw new ValidationException(implode(', ', $errors));
        }
        
        // Check if user exists
        if ($this->userModel->findByEmail($data['email'])) {
            throw new ValidationException('Email already exists');
        }
        
        if ($this->userModel->findByUsername($data['username'])) {
            throw new ValidationException('Username already exists');
        }
        
        $role = $data['role'] ?? ROLE_APPLICANT;

        $result = $this->userModel->create($data);
        if (!$result) {
            throw new Exception('Failed to create user');
        }
        
        // Log activity
        $newUser = $this->userModel->findByEmail($data['email']);
        $this->userModel->logActivity(
            $newUser['id'],
            'register',
            'User registered successfully'
        );

        $requiresVerification = ($role !== ROLE_ADMIN);
        $verificationEmailSent = false;

        if ($requiresVerification) {
            try {
                $this->dispatchVerificationCode($newUser);
                $verificationEmailSent = true;
            } catch (Exception $e) {
                error_log("Failed to send verification email to {$newUser['email']}: " . $e->getMessage());
            }
        } else {
            $this->userModel->markEmailVerified($newUser['id']);
        }

        $updatedUser = $this->userModel->findById($newUser['id']);
        $updatedUser['requires_email_verification'] = $requiresVerification;
        $updatedUser['verification_email_sent'] = $verificationEmailSent;
        unset($updatedUser['email_verification_code'], $updatedUser['email_verification_expires_at']);
        
        return $updatedUser;
    }

    /**
     * Authenticate user
     */
    public function authenticateUser($email, $password) {
        if (empty($email) || empty($password)) {
            throw new ValidationException('Email and password are required');
        }
        
        $user = $this->userModel->verifyPassword($email, $password);
        
        if (!$user) {
            throw new AuthenticationException('Invalid email or password');
        }
        
        if (!$user['is_active']) {
            throw new AuthenticationException('Account is inactive');
        }

        if (empty($user['email_verified_at'])) {
            throw new AuthenticationException('Please verify your email before logging in');
        }
        
        // Check if OAuth user trying to login with password
        if ($user['oauth_provider'] && !$user['password']) {
            throw new AuthenticationException('Please use Google Sign-in for this account');
        }
        
        // Log activity
        $this->userModel->logActivity(
            $user['id'],
            'login',
            'User logged in successfully'
        );
        
        return $user;
    }

    /**
     * Update user profile
     */
    public function updateProfile($userId, $data) {
        // Remove sensitive fields that shouldn't be updated directly
        unset($data['id'], $data['role'], $data['is_active'], $data['created_at']);
        
        // Validate email if provided
        if (isset($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new ValidationException('Invalid email format');
            }
            
            $existingUser = $this->userModel->findByEmail($data['email']);
            if ($existingUser && $existingUser['id'] != $userId) {
                throw new ValidationException('Email already exists');
            }
        }
        
        // Validate username if provided
        if (isset($data['username'])) {
            if (strlen($data['username']) < 3) {
                throw new ValidationException('Username must be at least 3 characters');
            }
            
            $existingUser = $this->userModel->findByUsername($data['username']);
            if ($existingUser && $existingUser['id'] != $userId) {
                throw new ValidationException('Username already exists');
            }
        }
        
        $result = $this->userModel->update($userId, $data);
        
        if (!$result) {
            throw new Exception('Failed to update profile');
        }
        
        // Log activity
        $this->userModel->logActivity(
            $userId,
            'update_profile',
            'Profile updated successfully'
        );
        
        return $this->userModel->findById($userId);
    }

    /**
     * Change user password
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        if (strlen($newPassword) < 6) {
            throw new ValidationException('New password must be at least 6 characters');
        }
        
        $user = $this->userModel->findById($userId);
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // OAuth users shouldn't have passwords
        if ($user['oauth_provider']) {
            throw new ValidationException('Cannot set password for OAuth accounts');
        }
        
        // Verify current password
        if (!password_verify($currentPassword, $user['password'])) {
            throw new ValidationException('Current password is incorrect');
        }
        
        $result = $this->userModel->update($userId, [
            'password' => $newPassword
        ]);
        
        if (!$result) {
            throw new Exception('Failed to change password');
        }
        
        // Log activity
        $this->userModel->logActivity(
            $userId,
            'change_password',
            'Password changed successfully'
        );
        
        return true;
    }

    /**
     * Get user profile with experience
     */
    public function getUserProfile($userId) {
        $user = $this->userModel->findById($userId);
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Remove sensitive data
        unset($user['password']);
        
        // Add experience data
        $user['experience'] = $this->experienceModel->getUserExperience($userId);
        $user['total_experience_years'] = $this->userModel->getTotalExperience($userId);
        
        return $user;
    }

    /**
     * Get all users (admin only)
     */
    public function getAllUsers($filters = []) {
        $where = '';
        $params = [];
        
        if (isset($filters['role'])) {
            $where = 'role = ?';
            $params[] = $filters['role'];
        }
        
        if (isset($filters['is_active'])) {
            $where .= ($where ? ' AND ' : '') . 'is_active = ?';
            $params[] = $filters['is_active'];
        }
        
        if (isset($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $where .= ($where ? ' AND ' : '') . '(full_name LIKE ? OR email LIKE ? OR username LIKE ?)';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        return $this->userModel->findAll($where, $params);
    }

    /**
     * Toggle user active status (admin only)
     */
    public function toggleUserStatus($userId, $adminId) {
        $user = $this->userModel->findById($userId);
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Don't allow deactivating self
        if ($userId == $adminId) {
            throw new ValidationException('Cannot deactivate your own account');
        }
        
        $newStatus = !$user['is_active'];
        
        $result = $this->userModel->update($userId, ['is_active' => $newStatus]);
        
        if (!$result) {
            throw new Exception('Failed to update user status');
        }
        
        // Log activity
        $this->userModel->logActivity(
            $adminId,
            'toggle_user_status',
            "Changed user {$user['full_name']} status to " . ($newStatus ? 'active' : 'inactive')
        );
        
        return $newStatus;
    }

    public function resendEmailVerification($userId) {
        $user = $this->userModel->findById($userId);

        if (!$user) {
            throw new Exception('User not found');
        }

        if (!empty($user['email_verified_at'])) {
            throw new ValidationException('Your email is already verified.');
        }

        if (!empty($user['email_verification_last_sent_at'])) {
            $secondsSinceLast = time() - strtotime($user['email_verification_last_sent_at']);
            if ($secondsSinceLast < EMAIL_VERIFICATION_RESEND_INTERVAL) {
                $waitSeconds = EMAIL_VERIFICATION_RESEND_INTERVAL - $secondsSinceLast;
                throw new ValidationException("Please wait {$waitSeconds} seconds before requesting a new code.");
            }
        }

        try {
            $this->dispatchVerificationCode($user, true);
        } catch (Exception $e) {
            error_log("Failed to resend verification email to {$user['email']}: " . $e->getMessage());
            throw new Exception('Failed to send verification email. Please try again later.');
        }

        return $this->userModel->findById($userId);
    }

    public function verifyEmailWithCode($email, $code) {
        $user = $this->userModel->findByEmail($email);

        if (!$user) {
            throw new ValidationException('We could not find an account with that email address.');
        }

        if (!empty($user['email_verified_at'])) {
            return $user;
        }

        $submittedCode = trim($code);
        if (strlen($submittedCode) !== EMAIL_VERIFICATION_CODE_LENGTH || !ctype_digit($submittedCode)) {
            throw new ValidationException('Please enter a valid verification code.');
        }

        if (empty($user['email_verification_code']) || empty($user['email_verification_expires_at'])) {
            throw new ValidationException('There is no active verification code. Please request a new one.');
        }

        if (strtotime($user['email_verification_expires_at']) < time()) {
            $this->userModel->clearEmailVerificationCode($user['id']);
            throw new ValidationException('Your verification code has expired. Please request a new one.');
        }

        if (!hash_equals($user['email_verification_code'], $submittedCode)) {
            $this->userModel->incrementEmailVerificationAttempts($user['id']);
            $attemptsUser = $this->userModel->findById($user['id']);

            if (($attemptsUser['email_verification_attempts'] ?? 0) >= EMAIL_VERIFICATION_MAX_ATTEMPTS) {
                $this->userModel->clearEmailVerificationCode($user['id']);
                throw new ValidationException('Too many incorrect attempts. Please request a new verification code.');
            }

            throw new ValidationException('The verification code you entered is incorrect.');
        }

        $this->userModel->markEmailVerified($user['id']);
        $verifiedUser = $this->userModel->findById($user['id']);

        $this->userModel->logActivity(
            $user['id'],
            'verify_email',
            'Email address verified successfully'
        );

        try {
            $this->emailService->sendWelcomeEmail($verifiedUser);
        } catch (Exception $e) {
            error_log("Failed to send welcome email to {$verifiedUser['email']}: " . $e->getMessage());
        }

        return $verifiedUser;
    }

    /**
     * Get user activity logs
     */
    public function getUserActivityLogs($userId, $limit = 50) {
        $stmt = $this->userModel->db->prepare("
            SELECT * FROM activity_logs 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
}
