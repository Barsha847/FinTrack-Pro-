<?php
declare(strict_types=1);

/**
 * FinTrack Pro - Application Bootstrap
 * 
 * Sets up error handling, composer autoloader, environment configuration,
 * security sessions, global configurations, and initialization settings.
 */

// 1. Autoloader Check & Load
$autoloader = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloader)) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'message' => 'Composer dependencies not installed. Please run "composer install" in the project root.',
        'data'    => null,
        'errors'  => ['composer_missing' => 'vendor/autoload.php not found']
    ]);
    exit;
}
require_once $autoloader;

// 2. Configuration Access Helper
if (!function_exists('config')) {
    /**
     * Retrieve a configuration value from configuration files.
     * Supports dot-notation (e.g. config('app.debug')).
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $configs = [];
        $parts = explode('.', $key);
        $file = array_shift($parts);
        
        if (!isset($configs[$file])) {
            $filePath = __DIR__ . '/config/' . $file . '.php';
            if (file_exists($filePath)) {
                $configs[$file] = require $filePath;
            } else {
                $configs[$file] = [];
            }
        }
        
        $value = $configs[$file];
        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
}

// 3. Load Environment Variables
if (class_exists(\Dotenv\Dotenv::class)) {
    try {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();
    } catch (\Dotenv\Exception\InvalidPathException $e) {
        // Tolerated in local development if environment variables are set directly
    }
}

// 4. Timezone Configurations
date_default_timezone_set(config('app.timezone', 'UTC'));

// 5. Centralized Error & Exception Handling
// Set reporting options
$isDebug = (bool)config('app.debug', false);
if ($isDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Exception Handler
set_exception_handler(function (Throwable $exception) use ($isDebug) {
    // Log exception details
    App\Helpers\Logger::error(
        $exception->getMessage(),
        [
            'file'  => $exception->getFile(),
            'line'  => $exception->getLine(),
            'code'  => $exception->getCode(),
            'trace' => $exception->getTraceAsString()
        ]
    );

    $statusCode = $exception->getCode();
    if ($statusCode < 400 || $statusCode >= 600) {
        $statusCode = 500;
    }

    $message = $isDebug ? $exception->getMessage() : 'A server error occurred.';
    $errors  = [];
    
    if ($isDebug) {
        $errors = [
            'file'  => $exception->getFile(),
            'line'  => $exception->getLine(),
            'trace' => explode("\n", $exception->getTraceAsString())
        ];
    }

    App\Helpers\ResponseHelper::error($message, $statusCode, $errors);
});

// Error Handler
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    throw new ErrorException($errstr, 500, $errno, $errfile, $errline);
});

// 6. Security-First Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    
    // Resolve HTTPS state dynamically
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
        || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    
    ini_set('session.cookie_secure', $isSecure ? '1' : '0');
    
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => (int)config('constants.auth.session_lifetime_seconds', 7200),
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    
    session_start();
}
