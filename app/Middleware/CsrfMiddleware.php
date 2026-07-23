<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\ResponseHelper;

/**
 * Class CsrfMiddleware
 * 
 * Protects session-authenticated state-changing endpoints against CSRF attacks.
 */
class CsrfMiddleware
{
    /**
     * Handle the incoming request.
     * 
     * @return void
     */
    public function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Skip GET, HEAD, OPTIONS requests
        if (in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = '/' . trim(parse_url($uri, PHP_URL_PATH) ?: '', '/');

        // Exclude endpoints that do not require CSRF (e.g. unauthenticated auth pages, health checks)
        $exclusions = [
            '/api/auth/register',
            '/api/auth/login',
            '/api/auth/verify-email',
            '/api/auth/verify-reset-otp',
            '/api/auth/resend-verification',
            '/api/auth/resend-otp',
            '/api/auth/forgot-password',
            '/api/auth/reset-password',
            '/api/auth/refresh',
            '/api/health'
        ];

        if (in_array($path, $exclusions, true)) {
            return;
        }

        // Initialize session if not active
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (empty($sessionToken) || empty($headerToken) || !hash_equals($sessionToken, $headerToken)) {
            ResponseHelper::error("CSRF token verification failed.", 403);
            exit;
        }
    }
}
