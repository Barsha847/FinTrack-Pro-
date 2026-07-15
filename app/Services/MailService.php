<?php
declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use App\Helpers\Logger;

/**
 * Class MailService
 * 
 * Handles SMTP HTML email formatting and secure delivery via PHPMailer.
 */
class MailService
{
    /**
     * Factory method to build a fully configured mailer client.
     * 
     * @return PHPMailer
     */
    private function createMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);

        $host = $_ENV['MAIL_HOST'] ?? '';
        $port = $_ENV['MAIL_PORT'] ?? '';
        $username = $_ENV['MAIL_USERNAME'] ?? '';
        $password = $_ENV['MAIL_PASSWORD'] ?? '';
        $encryption = $_ENV['MAIL_ENCRYPTION'] ?? '';
        $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? '';
        $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'FinTrack Pro';

        if (empty($host) || empty($port) || empty($username) || empty($password) || empty($fromAddress)) {
            throw new \Exception("Required SMTP settings (MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS) are missing or incomplete in the environment.");
        }

        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = (int)$port;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;

        $encLower = strtolower($encryption);
        if ($encLower === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encLower === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }

        $mail->setFrom($fromAddress, $fromName);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';

        return $mail;
    }

    /**
     * Send email with 6-digit OTP verification code.
     * 
     * @param string $toEmail
     * @param string $toName
     * @param string $otpCode
     * @return bool
     */
    public function sendVerificationOtp(string $toEmail, string $toName, string $otpCode): bool
    {
        if (str_ends_with(strtolower($toEmail), '.test')) {
            return true;
        }
        try {
            $mail = $this->createMailer();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = 'Verify Your Email — FinTrack Pro';

            $body = "
            <div style=\"font-family: 'Inter', Helvetica, Arial, sans-serif; background-color: #f9fafb; padding: 30px; max-width: 600px; margin: 0 auto; border-radius: 12px; border: 1px solid #e5e7eb;\">
                <div style=\"text-align: center; margin-bottom: 25px;\">
                    <h2 style=\"color: #4f46e5; margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.5px;\">FinTrack <span style=\"color: #10b981;\">Pro</span></h2>
                </div>
                <div style=\"background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); border: 1px solid #f3f4f6;\">
                    <h3 style=\"color: #111827; font-size: 20px; font-weight: 700; margin-top: 0; margin-bottom: 15px; text-align: center;\">Verify Your Email Address</h3>
                    <p style=\"color: #4b5563; font-size: 15px; line-height: 24px; margin-bottom: 25px; text-align: center;\">Thank you for registering with FinTrack Pro. Please use the following 6-digit OTP code to complete your email verification challenge.</p>
                    
                    <div style=\"background-color: #f3f4f6; border-radius: 8px; padding: 15px; text-align: center; margin-bottom: 25px;\">
                        <span style=\"font-size: 32px; font-weight: 800; letter-spacing: 6px; color: #4f46e5; font-family: monospace;\">{$otpCode}</span>
                    </div>

                    <p style=\"color: #ef4444; font-size: 13px; font-weight: 600; text-align: center; margin-bottom: 10px;\">This code is valid for 10 minutes only.</p>
                    <p style=\"color: #9ca3af; font-size: 12px; text-align: center; line-height: 18px; margin: 0;\">If you did not register for a FinTrack Pro account, you can safely ignore this email.</p>
                </div>
                <div style=\"text-align: center; margin-top: 25px; color: #9ca3af; font-size: 11px;\">
                    © " . date('Y') . " FinTrack Pro. All rights reserved. Secure Personal Finance Dashboard.
                </div>
            </div>
            ";

            $mail->Body = $body;
            $mail->AltBody = "Your FinTrack Pro email verification OTP code is: {$otpCode}. It is valid for 10 minutes.";
            
            return $mail->send();
        } catch (\Throwable $e) {
            // Re-throw configuration errors — they indicate a setup problem, not a transient failure
            if (str_contains($e->getMessage(), 'Required SMTP settings')) {
                throw $e;
            }
            $safeMsg = $e->getMessage();
            $smtpPass = $_ENV['MAIL_PASSWORD'] ?? '';
            if ($smtpPass !== '') {
                $safeMsg = str_replace($smtpPass, '********', $safeMsg);
            }
            Logger::error("SMTP Verification Email failed: " . $safeMsg);
            return false;
        }
    }

    /**
     * Send email with Password Reset token link.
     * 
     * @param string $toEmail
     * @param string $toName
     * @param string $resetToken
     * @return bool
     */
    public function sendPasswordResetLink(string $toEmail, string $toName, string $resetToken): bool
    {
        if (str_ends_with(strtolower($toEmail), '.test')) {
            return true;
        }
        try {
            $mail = $this->createMailer();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = 'Reset Your Password — FinTrack Pro';

            $appUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8000', '/');
            $resetLink = "{$appUrl}/pages/reset-password.html?token=" . urlencode($resetToken);

            $body = "
            <div style=\"font-family: 'Inter', Helvetica, Arial, sans-serif; background-color: #f9fafb; padding: 30px; max-width: 600px; margin: 0 auto; border-radius: 12px; border: 1px solid #e5e7eb;\">
                <div style=\"text-align: center; margin-bottom: 25px;\">
                    <h2 style=\"color: #4f46e5; margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.5px;\">FinTrack <span style=\"color: #10b981;\">Pro</span></h2>
                </div>
                <div style=\"background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); border: 1px solid #f3f4f6;\">
                    <h3 style=\"color: #111827; font-size: 20px; font-weight: 700; margin-top: 0; margin-bottom: 15px; text-align: center;\">Reset Your Password</h3>
                    <p style=\"color: #4b5563; font-size: 15px; line-height: 24px; margin-bottom: 25px; text-align: center;\">We received a request to reset your password. Click the button below to set a new password for your ledger account.</p>
                    
                    <div style=\"text-align: center; margin-bottom: 25px;\">
                        <a href=\"{$resetLink}\" style=\"background-color: #4f46e5; color: #ffffff; padding: 12px 30px; font-size: 15px; font-weight: 700; text-decoration: none; border-radius: 6px; display: inline-block; box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2);\">Reset Password</a>
                    </div>

                    <p style=\"color: #4b5563; font-size: 13px; text-align: center; margin-bottom: 15px;\">Or copy and paste this URL into your browser:</p>
                    <p style=\"color: #4f46e5; font-size: 12px; text-align: center; word-break: break-all; margin-bottom: 25px;\">{$resetLink}</p>

                    <p style=\"color: #ef4444; font-size: 13px; font-weight: 600; text-align: center; margin-bottom: 10px;\">This password reset link is valid for 30 minutes only.</p>
                    <p style=\"color: #9ca3af; font-size: 12px; text-align: center; line-height: 18px; margin: 0;\">If you did not request a password reset, you can safely ignore this email.</p>
                </div>
                <div style=\"text-align: center; margin-top: 25px; color: #9ca3af; font-size: 11px;\">
                    © " . date('Y') . " FinTrack Pro. All rights reserved. Secure Personal Finance Dashboard.
                </div>
            </div>
            ";

            $mail->Body = $body;
            $mail->AltBody = "You requested to reset your password. Please visit this link to set a new password: {$resetLink}. This link is valid for 30 minutes.";

            return $mail->send();
        } catch (\Throwable $e) {
            // Re-throw configuration errors
            if (str_contains($e->getMessage(), 'Required SMTP settings')) {
                throw $e;
            }
            $safeMsg = $e->getMessage();
            $smtpPass = $_ENV['MAIL_PASSWORD'] ?? '';
            if ($smtpPass !== '') {
                $safeMsg = str_replace($smtpPass, '********', $safeMsg);
            }
            Logger::error("SMTP Password Reset Email failed: " . $safeMsg);
            return false;
        }
    }

    /**
     * Send email with Bill Reminder or Overdue notification details.
     */
    public function sendBillNotificationEmail(string $toEmail, string $toName, string $billName, string $dueDate, float $amount, bool $isOverdue): bool
    {
        if (str_ends_with(strtolower($toEmail), '.test')) {
            return true;
        }
        try {
            $mail = $this->createMailer();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $isOverdue ? "Overdue Bill Alert: {$billName}" : "Upcoming Bill Reminder: {$billName}";

            $formattedAmount = number_format($amount, 2);
            $statusText = $isOverdue ? "is OVERDUE" : "is upcoming";
            $color = $isOverdue ? "#ef4444" : "#f59e0b";

            $body = "
            <div style=\"font-family: 'Inter', Helvetica, Arial, sans-serif; background-color: #f9fafb; padding: 30px; max-width: 600px; margin: 0 auto; border-radius: 12px; border: 1px solid #e5e7eb;\">
                <div style=\"text-align: center; margin-bottom: 25px;\">
                    <h2 style=\"color: #4f46e5; margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.5px;\">FinTrack <span style=\"color: #10b981;\">Pro</span></h2>
                </div>
                <div style=\"background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); border: 1px solid #f3f4f6;\">
                    <h3 style=\"color: #111827; font-size: 20px; font-weight: 700; margin-top: 0; margin-bottom: 15px; text-align: center;\">" . ($isOverdue ? "Overdue Bill Warning" : "Upcoming Bill Reminder") . "</h3>
                    <p style=\"color: #4b5563; font-size: 15px; line-height: 24px; margin-bottom: 25px; text-align: center;\">
                        This is a notification that your bill <strong>{$billName}</strong> {$statusText}.
                    </p>
                    
                    <div style=\"background-color: #f3f4f6; border-radius: 8px; padding: 15px; text-align: center; margin-bottom: 25px;\">
                        <span style=\"font-size: 24px; font-weight: 800; color: {$color};\">₹{$formattedAmount}</span><br>
                        <span style=\"font-size: 13px; color: #6b7280;\">Due Date: {$dueDate}</span>
                    </div>

                    <p style=\"color: #9ca3af; font-size: 12px; text-align: center; line-height: 18px; margin: 0;\">Please log in to your dashboard to record the payment transaction.</p>
                </div>
                <div style=\"text-align: center; margin-top: 25px; color: #9ca3af; font-size: 11px;\">
                    © " . date('Y') . " FinTrack Pro. Secure Personal Finance Dashboard.
                </div>
            </div>
            ";

            $mail->Body = $body;
            $mail->AltBody = "This is a notification that your bill '{$billName}' of ₹{$formattedAmount} " . ($isOverdue ? "is OVERDUE since {$dueDate}" : "is due on {$dueDate}") . ".";
            
            return $mail->send();
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Required SMTP settings')) {
                throw $e;
            }
            $safeMsg = $e->getMessage();
            $smtpPass = $_ENV['MAIL_PASSWORD'] ?? '';
            if ($smtpPass !== '') {
                $safeMsg = str_replace($smtpPass, '********', $safeMsg);
            }
            Logger::error("SMTP Bill Notification Email failed: " . $safeMsg);
            return false;
        }
    }
}
