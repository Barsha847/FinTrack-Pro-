<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Repositories\EmailVerificationRepository;
use App\Repositories\PasswordResetRepository;
use App\Repositories\UserSessionRepository;
use App\Repositories\LoginHistoryRepository;
use App\Repositories\ActivityLogRepository;
use App\Repositories\RefreshTokenRepository;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Services\MailService;
use App\Database\Database;
use App\Helpers\IpHelper;
use Exception;

/**
 * Class AuthService
 * 
 * Orchestrates business logic for user registrations, logins, verification challenges, and security resets.
 */
class AuthService
{
    private UserRepository $userRepo;
    private EmailVerificationRepository $emailVerifyRepo;
    private PasswordResetRepository $passResetRepo;
    private UserSessionRepository $sessionRepo;
    private LoginHistoryRepository $loginHistRepo;
    private ActivityLogRepository $activityLogRepo;
    private RefreshTokenRepository $refreshTokenRepo;
    private MailService $mailService;

    public function __construct()
    {
        $this->userRepo = new UserRepository();
        $this->emailVerifyRepo = new EmailVerificationRepository();
        $this->passResetRepo = new PasswordResetRepository();
        $this->sessionRepo = new UserSessionRepository();
        $this->loginHistRepo = new LoginHistoryRepository();
        $this->activityLogRepo = new ActivityLogRepository();
        $this->refreshTokenRepo = new RefreshTokenRepository();
        $this->mailService = new MailService();
    }

    /**
     * Generate secure Access and Refresh tokens for a user.
     * 
     * @param array $user
     * @return array Array containing raw tokens: ['accessToken' => ..., 'refreshToken' => ...]
     */
    public function generateTokens(array $user): array
    {
        $secrets = $this->getJwtSecrets();
        $primaryKey = $secrets[0];

        $issuedAt = time();
        $accessLifetime = 900; // 15 minutes
        $payload = [
            'iss' => 'FinTrack Pro',
            'aud' => 'FinTrack Pro Client',
            'iat' => $issuedAt,
            'exp' => $issuedAt + $accessLifetime,
            'sub' => $user['id'],
            'role' => $user['role'] ?? 'user',
            'email' => $user['email']
        ];

        $accessToken = JWT::encode($payload, $primaryKey, 'HS256');
        
        // Generate high entropy 64-char hex refresh token
        $refreshToken = bin2hex(random_bytes(32));

        return [
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken
        ];
    }

    /**
     * Get secret keys list supporting key rotation.
     * 
     * @return array
     */
    public function getJwtSecrets(): array
    {
        $candidates = [];
        if (!empty($_ENV['JWT_SECRET_KEYS'])) {
            $candidates = array_merge($candidates, explode(',', $_ENV['JWT_SECRET_KEYS']));
        }
        if (!empty($_ENV['JWT_SECRET'])) {
            $candidates[] = $_ENV['JWT_SECRET'];
        }
        if (!empty($_ENV['APP_KEY'])) {
            $candidates[] = $_ENV['APP_KEY'];
        }

        $validSecrets = [];
        foreach ($candidates as $candidate) {
            $trimmed = trim($candidate);
            if (strlen($trimmed) >= 32) {
                $validSecrets[] = $trimmed;
            }
        }

        if (empty($validSecrets)) {
            $validSecrets[] = 'fintrack_pro_default_jwt_secret_key_rotation_fallback';
        }

        return $validSecrets;
    }

    /**
     * Complete the registration flow. Generates a secure OTP code and initiates email dispatch.
     * 
     * @param array $data
     * @param string|null $ip
     * @param string|null $userAgent
     * @return array
     * @throws Exception
     */
    public function register(array $data, ?string $ip, ?string $userAgent): array
    {
        $email = trim(strtolower($data['email']));
        
        $existingUser = $this->userRepo->findByEmail($email);
        
        Database::begin();
        try {
            if ($existingUser) {
                // If the user profile exists and is verified, deny registration.
                if ($existingUser['email_verified']) {
                    throw new Exception("An account with this email address already exists.", 409);
                }
                
                // Allow recovering unverified registrations by updating OTP state
                $userId = $existingUser['id'];
                $userFullName = $existingUser['full_name'];
            } else {
                $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
                $userId = $this->userRepo->create([
                    'full_name' => $data['full_name'],
                    'username' => $data['username'] ?? null,
                    'email' => $email,
                    'phone_number' => $data['phone_number'] ?? null,
                    'password_hash' => $passwordHash,
                    'email_verified' => false,
                    'phone_verified' => false,
                    'account_status' => 'active',
                    'role' => 'user'
                ]);
                $userFullName = $data['full_name'];
            }

            // Generate secure 6-digit OTP code using crypto random_int
            $otpCode = (string)random_int(100000, 999999);
            $otpHash = password_hash($otpCode, PASSWORD_DEFAULT);

            // Store OTP hash (10 minute expiry)
            $this->emailVerifyRepo->createToken($userId, $otpHash, 600);

            $this->activityLogRepo->record(
                $userId, 
                $existingUser ? 'Verification Code Regenerated' : 'User Registered', 
                'Authentication', 
                "Registration initiated for email: {$email}", 
                null, 
                $ip
            );

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        // Dispatch OTP email outside of the transaction block
        $emailSent = $this->mailService->sendVerificationOtp($email, $userFullName, $otpCode);
        
        $response = [
            'success' => true,
            'message' => 'Registration initiated. Verification OTP code sent to your email.',
            'data' => [
                'email' => $email
            ]
        ];

        // For development/test environments, return OTP in metadata
        if (($_ENV['APP_ENV'] ?? 'development') === 'development') {
            $response['data']['otp'] = $otpCode;
        }

        if (!$emailSent) {
            $response['message'] = 'Registration completed. However, email delivery failed. Please request resend OTP.';
        }

        return $response;
    }

    /**
     * Verify OTP code and activate email status.
     * 
     * @param string $email
     * @param string $otp
     * @param string|null $ip
     * @param string|null $userAgent
     * @return array
     * @throws Exception
     */
    public function verifyEmail(string $email, string $otp, ?string $ip, ?string $userAgent): array
    {
        $email = trim(strtolower($email));
        $user = $this->userRepo->findByEmail($email);
        if (!$user) {
            throw new Exception("User profile not found.", 404);
        }

        if ($user['email_verified']) {
            return [
                'success' => true,
                'message' => 'Email is already verified.',
                'data' => [
                    'id' => $user['id'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email']
                ]
            ];
        }

        $latestToken = $this->emailVerifyRepo->findLatestToken($user['id']);
        if (!$latestToken) {
            throw new Exception("No active email verification challenge found.", 400);
        }

        if (strtotime($latestToken['expires_at']) < time()) {
            throw new Exception("Verification code has expired. Please request a new OTP.", 400);
        }

        // Lock verification on 5 attempts
        if (($latestToken['attempt_count'] ?? 0) >= 5) {
            throw new Exception("Maximum verification attempts exceeded. Please request a new OTP.", 429);
        }

        // Verify hashed OTP
        if (!password_verify($otp, $latestToken['token_hash'])) {
            $newAttempts = $this->emailVerifyRepo->incrementAttemptCount($latestToken['id']);
            $this->activityLogRepo->record(
                $user['id'], 
                'Email Verification Failed', 
                'Authentication', 
                "Incorrect OTP attempt. Current count: {$newAttempts}", 
                null, 
                $ip
            );

            $remaining = 5 - $newAttempts;
            if ($remaining <= 0) {
                throw new Exception("Incorrect OTP. Maximum attempts exceeded. Verification code invalidated.", 429);
            }
            throw new Exception("Incorrect verification code. {$remaining} attempts remaining.", 422);
        }

        Database::begin();
        try {
            $this->userRepo->markEmailVerified($user['id']);
            $this->emailVerifyRepo->markVerified($latestToken['id']);
            
            // Seed defaults user settings
            $db = Database::connection();
            $stmtSet = $db->prepare("
                INSERT INTO user_settings (id, user_id, currency, timezone, date_format, theme)
                VALUES (gen_random_uuid(), :user_id, 'INR', 'Asia/Kolkata', 'DD-MM-YYYY', 'system')
                ON CONFLICT (user_id) DO NOTHING
            ");
            $stmtSet->execute([':user_id' => $user['id']]);

            $this->activityLogRepo->record($user['id'], 'Email Verified', 'Authentication', 'User email address verified successfully.', ['user_agent' => $userAgent], $ip);

            // Establish secure session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();

            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }

            // Generate JWT and Refresh token
            $tokens = $this->generateTokens($user);
            $tokenHash = hash('sha256', $tokens['refreshToken']);
            $this->refreshTokenRepo->create($user['id'], $tokenHash, $ip, $userAgent, 604800); // 7 days

            // Sync user session record
            $this->sessionRepo->create($user['id'], session_id(), $ip, $userAgent, 86400);

            // Record success log
            $this->loginHistRepo->record($user['id'], $email, $ip, $userAgent, 'success');

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        $safeUser = [
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'email' => $user['email'],
            'phone_number' => $user['phone_number'],
            'email_verified' => (bool)$user['email_verified'],
            'role' => $user['role'],
            'profile_image' => $user['profile_image'],
            'created_at' => $user['created_at']
        ];

        return [
            'success' => true,
            'message' => 'Email verified successfully.',
            'data' => [
                'accessToken' => $tokens['accessToken'],
                'refreshToken' => $tokens['refreshToken'],
                'user' => $safeUser,
                'csrf_token' => $_SESSION['csrf_token']
            ]
        ];
    }

    /**
     * Resend verification OTP code, respecting a 60-second cooldown rate limit.
     * 
     * @param string $email
     * @param string|null $ip
     * @return array
     * @throws Exception
     */
    public function resendVerification(string $email, ?string $ip): array
    {
        $email = trim(strtolower($email));
        $user = $this->userRepo->findByEmail($email);
        if (!$user) {
            throw new Exception("User profile not found.", 404);
        }

        if ($user['email_verified']) {
            throw new Exception("Email is already verified.", 400);
        }

        $latestToken = $this->emailVerifyRepo->findLatestToken($user['id']);
        if ($latestToken) {
            if ($latestToken['resend_available_at'] && strtotime($latestToken['resend_available_at']) > time()) {
                $cooldown = strtotime($latestToken['resend_available_at']) - time();
                throw new Exception("Please wait {$cooldown} seconds before requesting a new code.", 429);
            }
        }

        Database::begin();
        try {
            $otpCode = (string)random_int(100000, 999999);
            $otpHash = password_hash($otpCode, PASSWORD_DEFAULT);

            $this->emailVerifyRepo->createToken($user['id'], $otpHash, 600);
            $this->activityLogRepo->record($user['id'], 'OTP Verification Resent', 'Authentication', 'New verification OTP code requested.', null, $ip);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        $emailSent = $this->mailService->sendVerificationOtp($email, $user['full_name'], $otpCode);

        $response = [
            'success' => true,
            'message' => 'A new 6-digit verification code has been dispatched to your email.'
        ];

        if (($_ENV['APP_ENV'] ?? 'development') === 'development') {
            $response['otp'] = $otpCode;
        }

        if (!$emailSent) {
            $response['message'] = 'Verification code generated but mail delivery failed. Please retry shortly.';
        }

        return $response;
    }

    /**
     * Authenticates email and password credentials with account lockout safety.
     * 
     * @param string $email
     * @param string $password
     * @param string|null $ip
     * @param string|null $userAgent
     * @return array
     * @throws Exception
     */
    public function login(string $email, string $password, ?string $ip, ?string $userAgent): array
    {
        $email = trim(strtolower($email));
        
        $user = $this->userRepo->findByEmail($email);

        if (!$user) {
            $this->loginHistRepo->record(null, $email, $ip, $userAgent, 'failed', 'Invalid credentials');
            $this->activityLogRepo->record(null, 'Failed Login', 'Authentication', "Failed login attempt for email: {$email}", ['user_agent' => $userAgent], $ip);
            throw new Exception("Invalid email or password.", 401);
        }

        // Assert Lockout
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            $lockSeconds = strtotime($user['locked_until']) - time();
            $lockMinutes = ceil($lockSeconds / 60);
            $this->loginHistRepo->record($user['id'], $email, $ip, $userAgent, 'blocked', 'Account locked');
            $this->activityLogRepo->record($user['id'], 'Blocked Login', 'Authentication', "Attempted login on locked account. Lock expires in {$lockMinutes} minute(s).", ['user_agent' => $userAgent], $ip);
            throw new Exception("This account is temporarily locked due to excessive failed attempts. Please retry in {$lockMinutes} minute(s).", 423);
        }

        // Assert Account Status
        if ($user['account_status'] !== 'active') {
            $this->loginHistRepo->record($user['id'], $email, $ip, $userAgent, 'blocked', "Status: {$user['account_status']}");
            $this->activityLogRepo->record($user['id'], 'Blocked Login', 'Authentication', "Access denied for non-active user. Status: {$user['account_status']}", ['user_agent' => $userAgent], $ip);
            throw new Exception("This account is currently {$user['account_status']}. Access denied.", 403);
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $attempts = $this->userRepo->incrementFailedLoginAttempts($user['id']);
            $this->loginHistRepo->record($user['id'], $email, $ip, $userAgent, 'failed', 'Invalid credentials');
            
            if ($attempts >= 5) {
                $this->userRepo->lockAccount($user['id'], 15);
                $this->activityLogRepo->record($user['id'], 'Blocked Login', 'Authentication', 'Account locked for 15 minutes due to 5 failed attempts.', ['user_agent' => $userAgent], $ip);
                throw new Exception("Incorrect password. This account is now locked for 15 minutes.", 423);
            }

            $remaining = 5 - $attempts;
            $this->activityLogRepo->record($user['id'], 'Failed Login', 'Authentication', "Incorrect password attempt. Current count: {$attempts}", ['user_agent' => $userAgent], $ip);
            throw new Exception("Invalid email or password. {$remaining} attempts remaining.", 401);
        }

        // Verify verification status
        if (!$user['email_verified']) {
            throw new Exception("Your email address is not verified yet.", 403);
        }

        Database::begin();
        try {
            // Reset attempt counters
            $this->userRepo->resetFailedLoginAttempts($user['id']);

            // Rehash password if standard parameters updated
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $this->userRepo->update($user['id'], ['password_hash' => $newHash]);
            }

            // Touch last login
            $this->userRepo->update($user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);

            $db = Database::connection();
            $stmtSet = $db->prepare("SELECT currency FROM user_settings WHERE user_id = :user_id");
            $stmtSet->execute([':user_id' => $user['id']]);
            $currency = $stmtSet->fetchColumn() ?: 'INR';

            // Establish secure session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();

            // Establish session CSRF
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // Generate JWT and Refresh token
            $tokens = $this->generateTokens($user);
            $tokenHash = hash('sha256', $tokens['refreshToken']);

            // Invalidate previous refresh tokens for this user on login
            $this->refreshTokenRepo->deleteAllForUser($user['id']);
            $this->refreshTokenRepo->create($user['id'], $tokenHash, $ip, $userAgent, 604800); // 7 days

            // Sync user session record
            $this->sessionRepo->create($user['id'], session_id(), $ip, $userAgent, 86400);

            // Record success audits
            $this->loginHistRepo->record($user['id'], $email, $ip, $userAgent, 'success');
            $this->activityLogRepo->record($user['id'], 'Login Successful', 'Authentication', 'User successfully authenticated secure session.', ['user_agent' => $userAgent], $ip);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        $safeUser = [
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'email' => $user['email'],
            'phone_number' => $user['phone_number'],
            'email_verified' => (bool)$user['email_verified'],
            'role' => $user['role'],
            'profile_image' => $user['profile_image'],
            'created_at' => $user['created_at']
        ];

        return [
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'accessToken' => $tokens['accessToken'],
                'refreshToken' => $tokens['refreshToken'],
                'user' => $safeUser,
                'csrf_token' => $_SESSION['csrf_token'],
                'currency' => $currency
            ]
        ];
    }

    /**
     * Terminate the session and cleanup tracking records.
     * 
     * @param string $sessionId
     * @param string|null $userId
     * @param string|null $ip
     * @return void
     */
    public function logout(string $sessionId, ?string $userId, ?string $ip): void
    {
        $this->sessionRepo->invalidate($sessionId);

        if ($userId) {
            $this->activityLogRepo->record($userId, 'Logout Successful', 'Authentication', 'User manually closed active session.', null, $ip);
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        if (session_id() !== '') {
            session_destroy();
        }
    }

    /**
     * Forgot password challenge. Generates a high-entropy reset token.
     * 
     * @param string $email
     * @param string|null $ip
     * @return array
     */
    public function forgotPassword(string $email, ?string $ip): array
    {
        $email = trim(strtolower($email));
        $user = $this->userRepo->findByEmail($email);

        $response = [
            'success' => true,
            'message' => 'If an account exists for this email, password reset instructions have been sent.'
        ];

        if ($user) {
            Database::begin();
            try {
                // Generate a high-entropy reset token (64 hex characters)
                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);

                // Save deterministic SHA-256 token hash (30 minute expiration)
                $this->passResetRepo->createToken($user['id'], $tokenHash, 1800);
                $this->activityLogRepo->record($user['id'], 'Password Reset Link Requested', 'Authentication', 'A password reset token was dispatched.', null, $ip);

                Database::commit();
            } catch (\Throwable $e) {
                Database::rollback();
                throw $e;
            }

            // Dispatch reset email outside transaction
            $this->mailService->sendPasswordResetLink($email, $user['full_name'], $rawToken);

            if (($_ENV['APP_ENV'] ?? 'development') === 'development') {
                $response['dev_token'] = $rawToken;
            }
        }

        return $response;
    }



    /**
     * Executes the password reset challenge. Wipes other active sessions.
     * 
     * @param string $token
     * @param string $password
     * @param string|null $ip
     * @return array
     * @throws Exception
     */
    public function resetPassword(string $token, string $password, ?string $ip): array
    {
        $tokenHash = hash('sha256', $token);
        $resetRecord = $this->passResetRepo->findByTokenHash($tokenHash);

        if (!$resetRecord) {
            throw new Exception("Invalid or expired password reset token.", 400);
        }

        $userId = $resetRecord['user_id'];
        $user = $this->userRepo->findById($userId);
        if (!$user) {
            throw new Exception("User account not found.", 404);
        }

        Database::begin();
        try {
            // Update password & verify email automatically
            $newPasswordHash = password_hash($password, PASSWORD_DEFAULT);
            $this->userRepo->update($userId, [
                'password_hash' => $newPasswordHash,
                'email_verified' => true
            ]);

            // Mark reset token as used
            $this->passResetRepo->markUsed($resetRecord['id']);

            // Clear login failures counters
            $this->userRepo->resetFailedLoginAttempts($userId);

            // Invalidate user sessions globally (forces re-login across all instances)
            $this->sessionRepo->invalidateAllForUser($userId);

            $this->activityLogRepo->record($userId, 'Password Reset Completed', 'Authentication', 'User successfully updated account password.', null, $ip);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        return [
            'success' => true,
            'message' => 'Password has been reset successfully. Please log in with your new credentials.'
        ];
    }
}
