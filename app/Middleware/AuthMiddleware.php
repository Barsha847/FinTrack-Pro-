<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\ResponseHelper;
use App\Helpers\IpHelper;
use App\Database\Database;
use App\Repositories\ActivityLogRepository;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use PDO;

/**
 * Class AuthMiddleware
 * 
 * Verifies that the client has a valid, active JWT access token (via header or cookie)
 * and populates session variables for backward compatibility.
 */
class AuthMiddleware
{
    /**
     * Handle the authentication verification.
     * 
     * @return void
     */
    public function handle(): void
    {
        // 1. Extract token from Authorization header or access_token cookie
        $token = '';
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!empty($authHeader)) {
            if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }

        if (empty($token)) {
            $token = $_COOKIE['access_token'] ?? '';
        }

        if (empty($token)) {
            ResponseHelper::error("Authentication required.", 401);
            exit;
        }

        // 2. Validate token (supporting Key Rotation)
        $secrets = $this->getJwtSecrets();
        $decoded = null;
        $lastException = null;

        foreach ($secrets as $secret) {
            try {
                $decoded = JWT::decode($token, new Key($secret, 'HS256'));
                $lastException = null;
                break; // Found matching key
            } catch (ExpiredException $e) {
                // Token has expired. Log this specific audit event.
                $this->logExpiredToken($token);
                ResponseHelper::error("Access token has expired.", 401, ["token_expired"]);
                exit;
            } catch (\Throwable $e) {
                $lastException = $e;
            }
        }

        if (!$decoded) {
            ResponseHelper::error("Invalid or tempered authentication token.", 401);
            exit;
        }

        $userId = $decoded->sub ?? null;

        if (!$userId) {
            ResponseHelper::error("Invalid authentication token claims.", 401);
            exit;
        }

        // 3. Confirm user exists and is active in database
        try {
            $db = Database::connection();
            $stmt = $db->prepare("SELECT account_status, role FROM users WHERE id = :id AND deleted_at IS NULL");
            $stmt->execute([':id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user === false) {
                $this->destroySession();
                ResponseHelper::error("User account not found.", 401);
                exit;
            }

            if ($user['account_status'] !== 'active') {
                $this->destroySession();
                ResponseHelper::error("Access denied. Account is currently {$user['account_status']}.", 401);
                exit;
            }

            // 4. Session compatibility mapping
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['authenticated'] = true;
            $_SESSION['user_id'] = $userId;
            $_SESSION['role'] = $user['role'];
            $_SESSION['last_activity'] = time();

        } catch (\Throwable $e) {
            ResponseHelper::error("Database authorization lookup failure.", 500);
            exit;
        }
    }

    /**
     * Decode expired token payload (without validation) to extract user ID for audit log.
     */
    private function logExpiredToken(string $token): void
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
                $userId = $payload['sub'] ?? null;
                if ($userId) {
                    $ip = IpHelper::getClientIp();
                    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
                    $activityLogRepo = new ActivityLogRepository();
                    $activityLogRepo->record(
                        $userId,
                        'Expired Token',
                        'Authentication',
                        "JWT access token expired.",
                        ['user_agent' => $userAgent],
                        $ip
                    );
                }
            }
        } catch (\Throwable $e) {
            // Silent catch to prevent errors during security middleware execution
        }
    }

    /**
     * Get secret keys list supporting key rotation.
     */
    private function getJwtSecrets(): array
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
     * Terminate the session and wipe session variables/cookies securely.
     */
    private function destroySession(): void
    {
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
}

