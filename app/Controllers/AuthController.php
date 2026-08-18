<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Helpers\ResponseHelper;
use App\Helpers\IpHelper;
use App\Repositories\UserRepository;
use Exception;

/**
 * Class AuthController
 * 
 * Intercepts HTTP auth endpoints, executes server-side validation, and returns structured JSON responses.
 */
class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Parse incoming JSON request body.
     * 
     * @return array
     */
    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        if (empty($input)) {
            return [];
        }
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            ResponseHelper::error("Malformed JSON payload.", 400);
            exit;
        }
        return $data ?: [];
    }

    /**
     * Helper to validate password strength parameters.
     * 
     * @param string $password
     * @return array Array of validation error messages
     */
    private function validatePasswordStrength(string $password): array
    {
        $errors = [];
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter.";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter.";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number.";
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character.";
        }
        return $errors;
    }

    /**
     * POST /api/auth/register
     */
    public function register(): void
    {
        $data = $this->getJsonInput();
        $errors = [];

        $fullName = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';
        $username = trim($data['username'] ?? '');
        $phone = trim($data['phone'] ?? '');

        if ($fullName === '') {
            $errors[] = "Full name is required.";
        }
        if ($email === '') {
            $errors[] = "Email address is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "A valid email address is required.";
        }

        if ($password === '') {
            $errors[] = "Password is required.";
        } else {
            $errors = array_merge($errors, $this->validatePasswordStrength($password));
        }

        if ($confirmPassword === '') {
            $errors[] = "Password confirmation is required.";
        } elseif ($password !== $confirmPassword) {
            $errors[] = "Passwords do not match.";
        }

        if (!empty($errors)) {
            ResponseHelper::error("Validation failed.", 422, $errors);
            return;
        }

        try {
            $ip = IpHelper::getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $response = $this->authService->register([
                'full_name' => $fullName,
                'email' => $email,
                'password' => $password,
                'username' => $username !== '' ? $username : null,
                'phone_number' => $phone !== '' ? $phone : null
            ], $ip, $userAgent);

            ResponseHelper::success($response['message'], $response['data'] ?? null, 201);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * POST /api/auth/verify-email
     */
    public function verifyEmail(): void
    {
        $data = $this->getJsonInput();
        $email = trim($data['email'] ?? '');
        $otp = trim($data['otp'] ?? '');

        if ($email === '' || $otp === '') {
            ResponseHelper::error("Email and 6-digit OTP code are required.", 422);
            return;
        }

        if (!preg_match('/^[0-9]{6}$/', $otp)) {
            ResponseHelper::error("OTP must be exactly 6 numeric digits.", 422);
            return;
        }

        try {
            $ip = IpHelper::getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $result = $this->authService->verifyEmail($email, $otp, $ip, $userAgent);
            
            // Set secure HttpOnly cookies for JWT access & refresh tokens
            $this->setAuthCookies($result['data']['accessToken'], $result['data']['refreshToken']);
            
            ResponseHelper::success($result['message'], $result['data']);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * POST /api/auth/resend-verification
     */
    public function resendVerification(): void
    {
        $data = $this->getJsonInput();
        $email = trim($data['email'] ?? '');

        if ($email === '') {
            ResponseHelper::error("Email address is required.", 422);
            return;
        }

        try {
            $ip = IpHelper::getClientIp();
            $result = $this->authService->resendVerification($email, $ip);
            
            // Format dynamic response
            $meta = [];
            if (isset($result['otp'])) {
                $meta['otp'] = $result['otp'];
            }
            ResponseHelper::success($result['message'], $meta);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * POST /api/auth/login
     */
    public function login(): void
    {
        $data = $this->getJsonInput();
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if ($email === '' || $password === '') {
            ResponseHelper::error("Email and password are required.", 422);
            return;
        }

        try {
            $ip = IpHelper::getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $result = $this->authService->login($email, $password, $ip, $userAgent);
            
            // Set secure HttpOnly cookies for JWT access & refresh tokens
            $this->setAuthCookies($result['data']['accessToken'], $result['data']['refreshToken']);
            
            ResponseHelper::success($result['message'], $result['data']);
        } catch (Exception $e) {
            $code = (int)$e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionId = session_id();
        $userId = $_SESSION['user_id'] ?? null;
        $ip = IpHelper::getClientIp();

        try {
            // Delete Refresh Token from database
            $refreshToken = $_COOKIE['refresh_token'] ?? '';
            if ($refreshToken !== '') {
                $tokenHash = hash('sha256', $refreshToken);
                $refreshTokenRepo = new \App\Repositories\RefreshTokenRepository();
                $refreshTokenRepo->delete($tokenHash);
            }

            // Clear Cookies
            $this->clearAuthCookies();

            // Destroy session and clean up audit tracking
            $this->authService->logout($sessionId, $userId, $ip);
            ResponseHelper::success("Sign out successful.");
        } catch (Exception $e) {
            ResponseHelper::error("An error occurred during logout.", 500);
        }
    }

    /**
     * POST /api/auth/refresh
     */
    public function refresh(): void
    {
        $refreshToken = $_COOKIE['refresh_token'] ?? '';
        if (empty($refreshToken)) {
            ResponseHelper::error("Refresh token missing.", 401);
            return;
        }

        $tokenHash = hash('sha256', $refreshToken);
        $refreshTokenRepo = new \App\Repositories\RefreshTokenRepository();
        $tokenRecord = $refreshTokenRepo->findByHash($tokenHash);

        if (!$tokenRecord) {
            // Unrecognized or expired token
            $this->clearAuthCookies();
            ResponseHelper::error("Invalid or expired refresh token.", 401);
            return;
        }

        $userId = $tokenRecord['user_id'];
        $ip = IpHelper::getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // --- REUSE DETECTION ---
        if ($tokenRecord['used']) {
            // Stolen refresh token reuse detected! Immediately revoke all sessions for this user.
            $refreshTokenRepo->deleteAllForUser($userId);
            $this->clearAuthCookies();

            // Log high-severity audit log
            $activityLogRepo = new \App\Repositories\ActivityLogRepository();
            $activityLogRepo->record(
                $userId,
                'Blocked Login',
                'Authentication',
                "REFRESH TOKEN REUSE DETECTED! Revoking all sessions for security protection.",
                ['user_agent' => $userAgent, 'ip' => $ip],
                $ip
            );

            ResponseHelper::error("Token reuse detected. All sessions revoked.", 401);
            return;
        }

        try {
            $userRepo = new \App\Repositories\UserRepository();
            $user = $userRepo->findById($userId);

            if (!$user || $user['account_status'] !== 'active') {
                $this->clearAuthCookies();
                ResponseHelper::error("User account not found or not active.", 401);
                return;
            }

            // Mark old token as used to implement rotation
            $refreshTokenRepo->markAsUsed($tokenHash);

            // Generate new Access and Refresh tokens
            $tokens = $this->authService->generateTokens($user);
            $newTokenHash = hash('sha256', $tokens['refreshToken']);

            // Save new token in database
            $refreshTokenRepo->create($userId, $newTokenHash, $ip, $userAgent, 604800); // 7 days

            // Set new cookies
            $this->setAuthCookies($tokens['accessToken'], $tokens['refreshToken']);

            // Log Token Refreshed audit event
            $activityLogRepo = new \App\Repositories\ActivityLogRepository();
            $activityLogRepo->record(
                $userId,
                'Refresh Token',
                'Authentication',
                "Access token silently refreshed successfully.",
                ['user_agent' => $userAgent],
                $ip
            );

            ResponseHelper::success("Tokens refreshed.", [
                'accessToken' => $tokens['accessToken'],
                'refreshToken' => $tokens['refreshToken'],
                'csrf_token' => $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32))
            ]);
        } catch (Exception $e) {
            ResponseHelper::error("Error refreshing tokens: " . $e->getMessage(), 500);
        }
    }

    /**
     * Set secure HttpOnly cookies for access and refresh tokens.
     */
    private function setAuthCookies(string $accessToken, string $refreshToken): void
    {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') === '443';

        // Access Token: HTTPOnly, Secure (if HTTPS), SameSite=Lax, expire in 15 mins (900 seconds)
        setcookie('access_token', $accessToken, [
            'expires' => time() + 900,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        // Refresh Token: HTTPOnly, Secure (if HTTPS), SameSite=Lax, expire in 7 days (604800 seconds)
        setcookie('refresh_token', $refreshToken, [
            'expires' => time() + 604800,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    /**
     * Clear auth cookies on logout or failure.
     */
    private function clearAuthCookies(): void
    {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') === '443';

        setcookie('access_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        setcookie('refresh_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    /**
     * GET /api/auth/me
     */
    public function me(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            ResponseHelper::error("Unauthenticated.", 401);
            return;
        }

        try {
            $userRepo = new UserRepository();
            $user = $userRepo->findById($userId);

            if (!$user) {
                ResponseHelper::error("User profile not found.", 401);
                return;
            }

            // Exclude passwords, locks, and history metrics from public responses
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

            ResponseHelper::success("User profile fetched.", ['user' => $safeUser]);
        } catch (Exception $e) {
            ResponseHelper::error("Error loading profile details.", 500);
        }
    }

    /**
     * GET /api/auth/session
     */
    public function session(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $authenticated = !empty($_SESSION['authenticated']) && !empty($_SESSION['user_id']);
        ResponseHelper::success("Session status verified.", ['authenticated' => $authenticated]);
    }

    /**
     * GET /api/auth/csrf-token
     */
    public function csrfToken(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        ResponseHelper::success("CSRF token generated.", ['csrf_token' => $_SESSION['csrf_token']]);
    }

    /**
     * POST /api/auth/forgot-password
     */
    public function forgotPassword(): void
    {
        $data = $this->getJsonInput();
        $email = trim($data['email'] ?? '');

        if ($email === '') {
            ResponseHelper::error("Email address is required.", 422);
            return;
        }

        try {
            $ip = IpHelper::getClientIp();
            $result = $this->authService->forgotPassword($email, $ip);
            
            $meta = [];
            if (isset($result['dev_token'])) {
                $meta['dev_token'] = $result['dev_token'];
            }
            ResponseHelper::success($result['message'], $meta);
        } catch (Exception $e) {
            ResponseHelper::error("Request failed.", 500);
        }
    }



    /**
     * POST /api/auth/reset-password
     */
    public function resetPassword(): void
    {
        $data = $this->getJsonInput();
        $token = trim($data['token'] ?? '');
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? $data['password_confirmation'] ?? '';

        if ($token === '') {
            ResponseHelper::error("Reset token is required.", 422);
            return;
        }

        if ($password === '') {
            ResponseHelper::error("New password is required.", 422);
            return;
        }

        $strengthErrors = $this->validatePasswordStrength($password);
        if (!empty($strengthErrors)) {
            ResponseHelper::error("Validation failed.", 422, $strengthErrors);
            return;
        }

        if ($password !== $confirmPassword) {
            ResponseHelper::error("Passwords do not match.", 422, ["Passwords do not match."]);
            return;
        }

        try {
            $ip = IpHelper::getClientIp();
            $result = $this->authService->resetPassword($token, $password, $ip);
            
            ResponseHelper::success($result['message']);
        } catch (Exception $e) {
            $code = $e->getCode();
            $statusCode = ($code >= 400 && $code < 600) ? $code : 500;
            ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }
}
