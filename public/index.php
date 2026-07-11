<?php
declare(strict_types=1);

/**
 * FinTrack Pro - Front Controller
 * 
 * Entry point for all HTTP/API requests.
 */

// 1. Initialize the application environment and autoloader
require_once __DIR__ . '/../bootstrap.php';

use App\Services\Router;
use App\Helpers\ResponseHelper;
use App\Helpers\Logger;

// 2. Configure CORS headers safely based on environment
$appEnv = $_ENV['APP_ENV'] ?? 'production';
$appUrl = $_ENV['APP_URL'] ?? 'http://localhost:8000';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($appEnv === 'production') {
    // Strict origin matching in production
    $allowedOrigin = (strcasecmp($origin, $appUrl) === 0) ? $origin : $appUrl;
} else {
    // Relaxed dynamic origin in development/local testing
    $allowedOrigin = $origin ?: '*';
}

header("Access-Control-Allow-Origin: $allowedOrigin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With");

// Handle preflight OPTIONS requests immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 3. Force API responses to be JSON UTF-8
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// 4. Safely extract HTTP request parameters
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

try {
    // 5. Instantiate routing engine and load registered API routes
    $router = new Router();
    require_once __DIR__ . '/../routes/api.php';

    // 6. Dispatch the request to the matching controller/handler
    $router->dispatch($requestMethod, $requestUri);
} catch (Throwable $e) {
    // 7. Secure exception handling
    $statusCode = (int)$e->getCode();
    if ($statusCode < 400 || $statusCode >= 600) {
        $statusCode = 500;
    }

    $isDebug = (bool)config('app.debug', false);

    // Secure logging: Mask database credentials in exceptions via Database logs
    Logger::error("API Request Exception: " . $e->getMessage(), [
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'code'  => $e->getCode(),
        'trace' => $isDebug ? $e->getTraceAsString() : '[REDACTED IN PRODUCTION]'
    ]);

    // Format error response
    $message = $isDebug ? $e->getMessage() : 'A server error occurred.';
    $errors = [];
    if ($isDebug) {
        $errors = [
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
        ];
    }

    ResponseHelper::error($message, $statusCode, $errors);
}
