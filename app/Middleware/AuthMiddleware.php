<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\ResponseHelper;
use App\Database\Database;

/**
 * Class AuthMiddleware
 * 
 * Verifies that the client has a valid, active authenticated session.
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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // 1. Assert session flags are set
        if (empty($_SESSION['authenticated']) || empty($_SESSION['user_id'])) {
            ResponseHelper::error("Authentication required.", 401);
            exit;
        }

        $userId = $_SESSION['user_id'];

        // 2. Validate session expiration (30-minute inactivity limit)
        $sessionTimeout = 1800; // 30 minutes in seconds
        $currentTime = time();
        if (isset($_SESSION['last_activity']) && ($currentTime - $_SESSION['last_activity'] > $sessionTimeout)) {
            $this->destroySession();
            ResponseHelper::error("Session expired due to inactivity. Please log in again.", 401);
            exit;
        }
        $_SESSION['last_activity'] = $currentTime;

        // 3. Confirm user exists and is active in database
        try {
            $db = Database::connection();
            $stmt = $db->prepare("SELECT account_status FROM users WHERE id = :id AND deleted_at IS NULL");
            $stmt->execute([':id' => $userId]);
            $status = $stmt->fetchColumn();

            if ($status === false) {
                $this->destroySession();
                ResponseHelper::error("User account not found.", 401);
                exit;
            }

            if ($status !== 'active') {
                $this->destroySession();
                ResponseHelper::error("Access denied. Account is currently {$status}.", 403);
                exit;
            }
        } catch (\Throwable $e) {
            ResponseHelper::error("Database authorization lookup failure.", 500);
            exit;
        }
    }

    /**
     * Terminate the session and wipe session variables/cookies securely.
     * 
     * @return void
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
