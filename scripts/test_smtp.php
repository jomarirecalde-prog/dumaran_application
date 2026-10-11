<?php
/**
 * Send a one-off test message using config/email.php
 * Run: php scripts/test_smtp.php [recipient@example.com]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$configPath = __DIR__ . '/../config/email.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Missing config/email.php — copy from config/email.example.php\n");
    exit(1);
}

$config = require $configPath;

require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$to = $argv[1] ?? ($config['smtp_username'] ?? '');
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/test_smtp.php [recipient@example.com]\n");
    exit(1);
}

$mail = new PHPMailer(true);

try {
    $mail->SMTPDebug = 0;
    $mail->isSMTP();
    $mail->Host = $config['smtp_host'];
    $mail->SMTPAuth = $config['smtp_auth'];
    $mail->Username = $config['smtp_username'];
    $mail->Password = $config['smtp_password'];
    $mail->SMTPSecure = $config['smtp_secure'];
    $mail->Port = (int) $config['smtp_port'];

    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($to);
    $mail->Subject = 'Dumaran Hiring — SMTP test';
    $mail->Body = '<p>If you received this, SMTP is working.</p><p>Sent at ' . date('Y-m-d H:i:s T') . '</p>';
    $mail->AltBody = 'If you received this, SMTP is working. Sent at ' . date('Y-m-d H:i:s T');
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';

    $mail->send();
    echo "OK — test email sent to {$to}\n";
    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, "Failed: {$mail->ErrorInfo}\n");
    exit(1);
}
