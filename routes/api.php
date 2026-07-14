<?php
declare(strict_types=1);

/**
 * FinTrack Pro - API Route Registrations
 * 
 * @var App\Services\Router $router
 */

use App\Controllers\HealthController;
use App\Controllers\AuthController;
use App\Controllers\InvestmentController;
use App\Controllers\LoanController;
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

// Investment Endpoints
$router->get('/api/investments', [InvestmentController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/investments', [InvestmentController::class, 'store'], [AuthMiddleware::class]);
$router->get('/api/investments/{id}', [InvestmentController::class, 'show'], [AuthMiddleware::class]);
$router->put('/api/investments/{id}', [InvestmentController::class, 'update'], [AuthMiddleware::class]);
$router->delete('/api/investments/{id}', [InvestmentController::class, 'destroy'], [AuthMiddleware::class]);
$router->put('/api/investments/{id}/value', [InvestmentController::class, 'updateValuation'], [AuthMiddleware::class]);
$router->get('/api/investments/{id}/history', [InvestmentController::class, 'valuationHistory'], [AuthMiddleware::class]);

// Loan / EMI Endpoints
$router->get('/api/loans', [LoanController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/loans', [LoanController::class, 'store'], [AuthMiddleware::class]);
$router->get('/api/loans/{id}', [LoanController::class, 'show'], [AuthMiddleware::class]);
$router->put('/api/loans/{id}', [LoanController::class, 'update'], [AuthMiddleware::class]);
$router->delete('/api/loans/{id}', [LoanController::class, 'destroy'], [AuthMiddleware::class]);
$router->get('/api/loans/{loanId}/emis', [LoanController::class, 'emis'], [AuthMiddleware::class]);
$router->post('/api/loans/{loanId}/emis/{emiId}/pay', [LoanController::class, 'payEmi'], [AuthMiddleware::class]);

