<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\ResponseHelper;

/**
 * Class AdminMiddleware
 * 
 * Verifies that the authenticated user possesses the 'admin' role.
 * This middleware should run AFTER AuthMiddleware.
 */
class AdminMiddleware
{
    /**
     * Handle the admin role authorization verification.
     * 
     * @return void
     */
    public function handle(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $role = $_SESSION['role'] ?? null;
        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId) {
            ResponseHelper::error("Authentication required.", 401);
            exit;
        }

        if ($role !== 'admin') {
            ResponseHelper::error("Access denied. Admin authorization required.", 403);
            exit;
        }
    }
}
