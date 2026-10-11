<?php
require_once '../config/config.php';
require_once '../includes/models.php';
require_once '../includes/services/UserService.php';
require_once '../includes/exceptions/Exceptions.php';

$emailParam = sanitize($_GET['email'] ?? ($_SESSION['pending_verification_email'] ?? ''));

if (empty($emailParam)) {
    header('Location: ' . BASE_URL . 'shared/register.php');
    exit();
}

$userModel = new User();
$user = $userModel->findByEmail($emailParam);

if (!$user) {
    header('Location: ' . BASE_URL . 'shared/register.php?error=Account not found. Please create an account first.');
    exit();
}

if (!empty($user['email_verified_at'])) {
    unset($_SESSION['pending_verification_email'], $_SESSION['pending_verification_notice']);
    header('Location: ' . BASE_URL . 'shared/login.php?message=Your email is already verified. Please sign in.');
    exit();
}

$userService = new UserService();
$error = '';
$success = '';
$notice = '';

if (isset($_GET['notice'])) {
    $notice = sanitize($_GET['notice']);
}

if (!empty($_SESSION['pending_verification_notice'])) {
    $notice = $_SESSION['pending_verification_notice'];
    unset($_SESSION['pending_verification_notice']);
}

$resendWaitSeconds = 0;
if (!empty($user['email_verification_last_sent_at'])) {
    $resendWaitSeconds = max(
        0,
        EMAIL_VERIFICATION_RESEND_INTERVAL - (time() - strtotime($user['email_verification_last_sent_at']))
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'verify';
    $validation = FormTokenManager::validateFormSubmission($_POST);

    if (!$validation['valid']) {
        if (($validation['error'] ?? '') === 'duplicate') {
            $error = 'This form has already been processed. Please refresh the page.';
        } else {
            $error = $validation['error'] ?? 'Invalid submission. Please try again.';
        }
    } else {
        try {
            if ($action === 'resend') {
                $user = $userService->resendEmailVerification($user['id']);
                $success = 'We sent a new verification code to ' . htmlspecialchars($user['email']) . '.';
                $resendWaitSeconds = EMAIL_VERIFICATION_RESEND_INTERVAL;
                FormTokenManager::clearFormTokens('resend_verification_code');
            } else {
                $otpCode = trim($_POST['otp_code'] ?? '');
                $userService->verifyEmailWithCode($user['email'], $otpCode);
                unset($_SESSION['pending_verification_email']);
                FormTokenManager::clearFormTokens('verify_email_code');
                header('Location: ' . BASE_URL . 'shared/login.php?message=Email verified successfully. You can now sign in.');
                exit();
            }
        } catch (ValidationException $e) {
            $error = $e->getMessage();
            $user = $userModel->findByEmail($emailParam);
            if (!empty($user['email_verification_last_sent_at'])) {
                $resendWaitSeconds = max(
                    0,
                    EMAIL_VERIFICATION_RESEND_INTERVAL - (time() - strtotime($user['email_verification_last_sent_at']))
                );
            }
        } catch (Exception $e) {
            error_log("Email verification error: " . $e->getMessage());
            $error = 'Something went wrong. Please try again in a moment.';
        }
    }
}

$codeExpiresAt = $user['email_verification_expires_at'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - Dumaran Hiring System</title>
    <link href="<?= BASE_URL ?>assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/office-theme.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #2050A0 0%, #2050A0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        .verify-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 520px;
            width: 100%;
            animation: slideUp 0.4s ease;
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .verify-header {
            background: linear-gradient(135deg, #2050A0 0%, #2050A0 100%);
            padding: 36px 40px;
            text-align: center;
            color: white;
        }
        .verify-header i {
            font-size: 3rem;
            margin-bottom: 12px;
        }
        .verify-body {
            padding: 32px 40px;
        }
        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            font-size: 0.92rem;
        }
        .alert i {
            margin-top: 2px;
        }
        .alert-info {
            background: #dbeafe;
            color: #2050A0;
            border: 1px solid #93c5fd;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        .info-text {
            color: #4b5563;
            margin-bottom: 24px;
            line-height: 1.6;
        }
        .otp-input {
            display: flex;
            gap: 12px;
        }
        .otp-input input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1.2rem;
            letter-spacing: 0.5rem;
            text-align: center;
            background: #f9fafb;
            transition: all 0.2s ease;
        }
        .otp-input input:focus {
            outline: none;
            border-color: #2050A0;
            background: white;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        .btn-primary {
            width: 100%;
            background: linear-gradient(135deg, #2050A0 0%, #2050A0 100%);
            color: white;
            border: none;
            padding: 15px 24px;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
            margin-top: 10px;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.5);
        }
        .resend-section {
            margin-top: 24px;
            text-align: center;
            color: #4b5563;
            font-size: 0.95rem;
        }
        .resend-section button {
            margin-top: 12px;
            padding: 12px 24px;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            background: white;
            color: #1f2937;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 600;
        }
        .resend-section button:hover:not([disabled]) {
            background: #f9fafb;
            transform: translateY(-1px);
        }
        .resend-section button[disabled] {
            cursor: not-allowed;
            opacity: 0.6;
        }
        .back-to-login {
            margin-top: 28px;
            text-align: center;
        }
        .back-to-login a {
            color: #2050A0;
            font-weight: 600;
            text-decoration: none;
        }
        .back-to-login a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="verify-header">
            <i class="fas fa-envelope-open-text"></i>
            <h2>Verify your email</h2>
            <p>Please enter the verification code sent to <?= htmlspecialchars($user['email']) ?></p>
        </div>
        <div class="verify-body">
            <?php if ($notice): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div><?= htmlspecialchars($notice) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?= htmlspecialchars($success) ?></div>
                </div>
            <?php endif; ?>

            <div class="info-text">
                <p>Enter the <?= EMAIL_VERIFICATION_CODE_LENGTH ?>-digit code sent to your email to complete your registration.</p>
                <?php if ($codeExpiresAt): ?>
                    <p>The current code expires at <?= date('g:i A', strtotime($codeExpiresAt)) ?>.</p>
                <?php else: ?>
                    <p>If you don't see the email, check your spam folder or request a new code below.</p>
                <?php endif; ?>
            </div>

            <form method="POST" class="prevent-double-submit">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <?php FormTokenManager::renderFormTokenInput('verify_email_code'); ?>
                <input type="hidden" name="action" value="verify">

                <div class="otp-input">
                    <input type="text" name="otp_code" inputmode="numeric" pattern="\d{<?= EMAIL_VERIFICATION_CODE_LENGTH ?>}" maxlength="<?= EMAIL_VERIFICATION_CODE_LENGTH ?>" placeholder="••••••" required>
                </div>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-check"></i> Verify Email
                </button>
            </form>

            <div class="resend-section">
                <div id="resendCountdown" data-remaining="<?= (int)$resendWaitSeconds ?>">
                    <?php if ($resendWaitSeconds > 0): ?>
                        You can request a new code in <?= $resendWaitSeconds ?> seconds.
                    <?php else: ?>
                        Didn't receive the email?
                    <?php endif; ?>
                </div>
                <form method="POST" style="margin-top: 12px;">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <?php FormTokenManager::renderFormTokenInput('resend_verification_code'); ?>
                    <input type="hidden" name="action" value="resend">
                    <button type="submit" id="resendButton" <?= $resendWaitSeconds > 0 ? 'disabled' : '' ?> data-remaining="<?= (int)$resendWaitSeconds ?>">
                        <i class="fas fa-paper-plane"></i> Resend Code
                    </button>
                </form>
            </div>

            <div class="back-to-login">
                <p>Ready to sign in? <a href="login.php">Go to login</a></p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const resendButton = document.getElementById('resendButton');
            const countdownEl = document.getElementById('resendCountdown');

            if (!resendButton || !countdownEl) {
                return;
            }

            let remaining = parseInt(resendButton.dataset.remaining || countdownEl.dataset.remaining || '0', 10);

            if (Number.isNaN(remaining) || remaining <= 0) {
                countdownEl.textContent = 'Didn\'t receive the email?';
                resendButton.disabled = false;
                return;
            }

            resendButton.disabled = true;

            const updateCountdown = () => {
                remaining -= 1;
                if (remaining <= 0) {
                    countdownEl.textContent = 'You can request a new code now.';
                    resendButton.disabled = false;
                    clearInterval(timer);
                } else {
                    countdownEl.textContent = `You can request a new code in ${remaining} seconds.`;
                }
            };

            countdownEl.textContent = `You can request a new code in ${remaining} seconds.`;
            const timer = setInterval(updateCountdown, 1000);
        });
    </script>
    <script src="<?= BASE_URL ?>assets/js/form-submission-manager.js"></script>
</body>
</html>











