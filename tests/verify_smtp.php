<?php
declare(strict_types=1);

/**
 * FinTrack Pro - SMTP Readiness Verification Script
 * 
 * Usage:
 *   php tests/verify_smtp.php              → Validate SMTP configuration only
 *   php tests/verify_smtp.php user@mail.com → Send a test email to the given address
 */

require_once __DIR__ . '/../bootstrap.php';

echo "===================================================\n";
echo "FinTrack Pro - SMTP Readiness Verification\n";
echo "===================================================\n\n";

$allPassed = true;

// 1. PHPMailer availability
echo "[CHECK] PHPMailer class availability... ";
if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
    echo "[OK] PHPMailer is installed and autoloaded.\n";
} else {
    echo "[FAIL] PHPMailer class not found. Run: composer require phpmailer/phpmailer\n";
    $allPassed = false;
}

// 2. Required SMTP environment variables
echo "\n[CHECK] Required SMTP configuration variables...\n";
$requiredVars = ['MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS'];
$optionalVars = ['MAIL_ENCRYPTION', 'MAIL_FROM_NAME'];

foreach ($requiredVars as $var) {
    $value = $_ENV[$var] ?? '';
    if ($var === 'MAIL_PASSWORD') {
        // Never print passwords
        $display = !empty($value) ? '********' : '(MISSING)';
    } else {
        $display = !empty($value) ? $value : '(MISSING)';
    }
    $status = !empty($value) ? '[OK]' : '[FAIL]';
    echo "  {$status} {$var}: {$display}\n";
    if (empty($value)) {
        $allPassed = false;
    }
}

foreach ($optionalVars as $var) {
    $value = $_ENV[$var] ?? '';
    $display = !empty($value) ? $value : '(not set, using default)';
    echo "  [INFO] {$var}: {$display}\n";
}

// 3. SMTP connection configuration summary
echo "\n[CHECK] SMTP connection profile:\n";
$host = $_ENV['MAIL_HOST'] ?? '';
$port = $_ENV['MAIL_PORT'] ?? '';
$encryption = $_ENV['MAIL_ENCRYPTION'] ?? 'none';
$fromAddr = $_ENV['MAIL_FROM_ADDRESS'] ?? '';
$fromName = $_ENV['MAIL_FROM_NAME'] ?? 'FinTrack Pro';
echo "  Host:       {$host}\n";
echo "  Port:       {$port}\n";
echo "  Encryption: {$encryption}\n";
echo "  From:       {$fromName} <{$fromAddr}>\n";

// 4. MailService instantiation
echo "\n[CHECK] MailService instantiation... ";
try {
    $mailService = new App\Services\MailService();
    echo "[OK]\n";
} catch (\Throwable $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
    $allPassed = false;
}

// 5. Optional: Send a real test email
$recipient = $argv[1] ?? null;
if ($recipient) {
    echo "\n[TEST] Sending FinTrack Pro test email to: {$recipient}\n";
    
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        echo "[FAIL] Invalid email address: {$recipient}\n";
        $allPassed = false;
    } else {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = (int)$port;
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USERNAME'] ?? '';
            $mail->Password = $_ENV['MAIL_PASSWORD'] ?? '';
            
            $encLower = strtolower($encryption);
            if ($encLower === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encLower === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            }
            
            $mail->setFrom($fromAddr, $fromName);
            $mail->addAddress($recipient);
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = 'FinTrack Pro - SMTP Test Verification';
            
            $body = "
            <div style=\"font-family: 'Inter', Helvetica, Arial, sans-serif; background-color: #f9fafb; padding: 30px; max-width: 600px; margin: 0 auto; border-radius: 12px; border: 1px solid #e5e7eb;\">
                <div style=\"text-align: center; margin-bottom: 25px;\">
                    <h2 style=\"color: #4f46e5; margin: 0; font-size: 24px; font-weight: 800;\">FinTrack <span style=\"color: #10b981;\">Pro</span></h2>
                </div>
                <div style=\"background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);\">
                    <h3 style=\"color: #111827; font-size: 20px; text-align: center;\">SMTP Test Successful ✅</h3>
                    <p style=\"color: #4b5563; font-size: 15px; text-align: center;\">
                        Your FinTrack Pro SMTP configuration is working correctly.<br>
                        This test was sent at: " . date('Y-m-d H:i:s T') . "
                    </p>
                </div>
                <div style=\"text-align: center; margin-top: 25px; color: #9ca3af; font-size: 11px;\">
                    © " . date('Y') . " FinTrack Pro. SMTP Verification.
                </div>
            </div>";
            
            $mail->Body = $body;
            $mail->AltBody = "FinTrack Pro SMTP Test Successful. Sent at: " . date('Y-m-d H:i:s T');
            
            $result = $mail->send();
            if ($result) {
                echo "[PASS] Test email sent successfully to {$recipient}!\n";
            } else {
                echo "[FAIL] PHPMailer::send() returned false.\n";
                $allPassed = false;
            }
        } catch (\Throwable $e) {
            $safeMsg = $e->getMessage();
            // Mask any password that might appear in error messages
            $smtpPass = $_ENV['MAIL_PASSWORD'] ?? '';
            if ($smtpPass !== '') {
                $safeMsg = str_replace($smtpPass, '********', $safeMsg);
            }
            echo "[FAIL] SMTP Error: {$safeMsg}\n";
            $allPassed = false;
        }
    }
} else {
    echo "\n[INFO] No recipient provided. Skipping test email send.\n";
    echo "[INFO] To send a test email, run: php tests/verify_smtp.php recipient@example.com\n";
}

// Final summary
echo "\n---------------------------------------------------\n";
if ($allPassed) {
    echo "[PASS] SMTP readiness verification complete. All checks passed.\n";
} else {
    echo "[FAIL] SMTP readiness verification detected failures.\n";
    exit(1);
}
echo "===================================================\n";
