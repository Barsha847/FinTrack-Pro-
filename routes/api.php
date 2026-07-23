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
use App\Controllers\CategoryController;
use App\Controllers\ExpenseController;
use App\Controllers\BudgetController;
use App\Controllers\BillReminderController;
use App\Controllers\NotificationController;
use App\Controllers\IncomeController;
use App\Controllers\ReportController;
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
$router->post('/api/auth/refresh', [AuthController::class, 'refresh']);
$router->get('/api/auth/csrf-token', [AuthController::class, 'csrfToken']);
$router->post('/api/auth/forgot-password', [AuthController::class, 'forgotPassword']);
$router->post('/api/auth/reset-password', [AuthController::class, 'resetPassword']);

// Categories Endpoint
$router->get('/api/categories', [CategoryController::class, 'index'], [AuthMiddleware::class]);

// Expense Endpoints
$router->get('/api/expenses', [ExpenseController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/expenses', [ExpenseController::class, 'store'], [AuthMiddleware::class]);
$router->get('/api/expenses/{id}', [ExpenseController::class, 'show'], [AuthMiddleware::class]);
$router->put('/api/expenses/{id}', [ExpenseController::class, 'update'], [AuthMiddleware::class]);
$router->delete('/api/expenses/{id}', [ExpenseController::class, 'destroy'], [AuthMiddleware::class]);

// Income Endpoints
$router->get('/api/income', [IncomeController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/income', [IncomeController::class, 'store'], [AuthMiddleware::class]);
$router->get('/api/income/{id}', [IncomeController::class, 'show'], [AuthMiddleware::class]);
$router->put('/api/income/{id}', [IncomeController::class, 'update'], [AuthMiddleware::class]);
$router->delete('/api/income/{id}', [IncomeController::class, 'destroy'], [AuthMiddleware::class]);

// Report / Analytics Endpoints
$router->get('/api/reports/summary', [ReportController::class, 'summary'], [AuthMiddleware::class]);
$router->get('/api/reports/details', [ReportController::class, 'details'], [AuthMiddleware::class]);

// Budget Endpoints
$router->get('/api/budgets', [BudgetController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/budgets', [BudgetController::class, 'store'], [AuthMiddleware::class]);
$router->get('/api/budgets/{id}', [BudgetController::class, 'show'], [AuthMiddleware::class]);
$router->put('/api/budgets/{id}', [BudgetController::class, 'update'], [AuthMiddleware::class]);
$router->delete('/api/budgets/{id}', [BudgetController::class, 'destroy'], [AuthMiddleware::class]);

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

// Bill Endpoints (Static patterns registered before wildcards)
$router->get('/api/bills/upcoming', [BillReminderController::class, 'upcoming'], [AuthMiddleware::class]);
$router->get('/api/bills/overdue', [BillReminderController::class, 'overdue'], [AuthMiddleware::class]);
$router->get('/api/bills', [BillReminderController::class, 'index'], [AuthMiddleware::class]);
$router->post('/api/bills', [BillReminderController::class, 'store'], [AuthMiddleware::class]);
$router->get('/api/bills/{id}', [BillReminderController::class, 'show'], [AuthMiddleware::class]);
$router->put('/api/bills/{id}', [BillReminderController::class, 'update'], [AuthMiddleware::class]);
$router->delete('/api/bills/{id}', [BillReminderController::class, 'destroy'], [AuthMiddleware::class]);
$router->put('/api/bills/{id}/paid', [BillReminderController::class, 'paid'], [AuthMiddleware::class]);

// Notifications Endpoints (Static patterns registered before wildcards)
$router->get('/api/notifications/unread-count', [NotificationController::class, 'unreadCount'], [AuthMiddleware::class]);
$router->put('/api/notifications/read-all', [NotificationController::class, 'readAll'], [AuthMiddleware::class]);
$router->get('/api/notifications', [NotificationController::class, 'index'], [AuthMiddleware::class]);
$router->put('/api/notifications/{id}/read', [NotificationController::class, 'read'], [AuthMiddleware::class]);
$router->delete('/api/notifications/{id}', [NotificationController::class, 'destroy'], [AuthMiddleware::class]);
