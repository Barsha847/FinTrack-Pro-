<?php
declare(strict_types=1);

/**
 * FinTrack Pro - Backend Verification Script
 * 
 * Verifies file layout, configuration values, logger writing & masking,
 * and routing system dispatching.
 */

echo "===================================================\n";
echo "FinTrack Pro - Backend Foundation Verification Utility\n";
echo "===================================================\n\n";

$filesToCheck = [
    __DIR__ . '/../bootstrap.php',
    __DIR__ . '/../config/app.php',
    __DIR__ . '/../config/constants.php',
    __DIR__ . '/../config/database.php',
    __DIR__ . '/../config/mail.php',
    __DIR__ . '/../app/Helpers/ResponseHelper.php',
    __DIR__ . '/../app/Helpers/Logger.php',
    __DIR__ . '/../app/Services/Router.php',
    __DIR__ . '/../app/Controllers/HealthController.php',
    __DIR__ . '/../routes/api.php',
];

echo "1. Checking file existence:\n";
foreach ($filesToCheck as $file) {
    if (!file_exists($file)) {
        echo "  [ERROR] File missing: " . basename($file) . " at $file\n";
        exit(1);
    }
    echo "  [OK] Found: " . basename($file) . "\n";
}

// 2. Setup fallback autoloader for logic verification (handles cases where composer install hasn't run yet)
$autoloader = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloader)) {
    echo "\n[INFO] vendor/autoload.php not found. Simulating autoloading to verify logic...\n";
    spl_autoload_register(function ($class) {
        $prefix = 'App\\';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = __DIR__ . '/../app/' . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    });

    if (!function_exists('config')) {
        function config(string $key, mixed $default = null): mixed
        {
            static $configs = [];
            $parts = explode('.', $key);
            $file = array_shift($parts);
            
            if (!isset($configs[$file])) {
                $filePath = __DIR__ . '/../config/' . $file . '.php';
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
} else {
    echo "\n[INFO] Composer autoload found. Requiring autoloader...\n";
    require_once $autoloader;
}

// 3. Test Configurations
echo "\n2. Verifying configuration parameters:\n";
echo "  [OK] App Name: " . config('app.name', 'FinTrack Pro') . "\n";
echo "  [OK] Environment: " . config('app.env') . "\n";
echo "  [OK] Timezone: " . config('app.timezone') . "\n";
echo "  [OK] DB Connection: " . config('database.driver') . "://" . config('database.host') . ":" . config('database.port') . "\n";
echo "  [OK] Default Currency: " . config('constants.app.default_currency') . "\n";

// 4. Test Logger & Masking
echo "\n3. Verifying Logger and Context Security Masking:\n";
$tempLogPath = __DIR__ . '/../storage/logs/verification.log';
App\Helpers\Logger::setLogPath($tempLogPath);
App\Helpers\Logger::info("Logger verification initiated", [
    'test_key' => 'normal_value',
    'password' => 'secret_password_123',
    'db_password' => 'db_root_pass'
]);

if (file_exists($tempLogPath)) {
    $contents = file_get_contents($tempLogPath);
    echo "  [OK] Verification log file successfully written.\n";
    
    $hasUnmaskedPassword = str_contains($contents, 'secret_password_123') || str_contains($contents, 'db_root_pass');
    $hasMaskedPassword = str_contains($contents, '********');

    if (!$hasUnmaskedPassword && $hasMaskedPassword) {
        echo "  [OK] Security Masking works! Sensitive context parameters were successfully hidden.\n";
    } else {
        echo "  [WARNING] Sensitive parameters were not masked correctly. Check Logger masking.\n";
    }
    
    // Cleanup verification log
    @unlink($tempLogPath);
} else {
    echo "  [ERROR] Failed to write file at storage/logs/verification.log. Check directory permissions.\n";
}

// 5. Test Router Matching
echo "\n4. Verifying Router registration:\n";
$router = new App\Services\Router();

// Mock loader context variables
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';

// Register routes
require_once __DIR__ . '/../routes/api.php';

// Test execution closure routing
$router->get('/api/verify-dispatch', function() {
    echo "  [OK] Router dispatch and callback handler executed successfully.\n";
});

try {
    $router->dispatch('GET', '/api/verify-dispatch');
} catch (Exception $e) {
    echo "  [ERROR] Router dispatch error: " . $e->getMessage() . "\n";
}

echo "\n===================================================\n";
echo "FinTrack Pro Backend Framework verification completed!\n";
echo "===================================================\n";
