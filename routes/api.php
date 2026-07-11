<?php
declare(strict_types=1);

/**
 * FinTrack Pro - API Route Registrations
 * 
 * @var App\Services\Router $router
 */

use App\Controllers\HealthController;
use App\Controllers\AuthController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;

// Register global CSRF token checker for state-changing endpoints
$router->addMiddleware(CsrfMiddleware::class);

// Health Check API
$router->get('/api/health', [HealthController::class, 'index']);

// Authentication Endpoints
$router->post('/api/auth/register', [AuthController::class, 'register']);
$router->post('/api/auth/verify-email', [AuthController::class, 'verifyEmail']);
$router->post('/api/auth/resend-verification', [AuthController::class, 'resendVerification']);
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);
$router->get('/api/auth/me', [AuthController::class, 'me'], [AuthMiddleware::class]);
$router->get('/api/auth/session', [AuthController::class, 'session']);
$router->get('/api/auth/csrf-token', [AuthController::class, 'csrfToken']);
$router->post('/api/auth/forgot-password', [AuthController::class, 'forgotPassword']);
$router->post('/api/auth/reset-password', [AuthController::class, 'resetPassword']);

