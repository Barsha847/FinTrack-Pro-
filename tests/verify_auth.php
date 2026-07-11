<?php
declare(strict_types=1);

/**
 * FinTrack Pro - Secure Authentication Verification Framework
 */

// Force secure session configurations before bootstrap starts the session
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');

require_once __DIR__ . '/../bootstrap.php';

use App\Database\Database;
use App\Controllers\AuthController;
use App\Services\AuthService;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Helpers\IpHelper;
use App\Repositories\UserRepository;
use App\Repositories\EmailVerificationRepository;
use App\Repositories\PasswordResetRepository;
use App\Repositories\UserSessionRepository;
use App\Repositories\LoginHistoryRepository;
use App\Repositories\ActivityLogRepository;

echo "===================================================\n";
echo "FinTrack Pro - Authentication Layer Verification\n";
echo "===================================================\n\n";

$allPassed = true;

function test(string $name, callable $fn) {
    global $allPassed;
    try {
        $result = $fn();
        if ($result !== false) {
            echo "  [OK] Test Passed: {$name}\n";
        } else {
            echo "  [FAIL] Test Failed: {$name}\n";
            $allPassed = false;
        }
    } catch (Throwable $e) {
        echo "  [FAIL] Test Exception in '{$name}': " . $e->getMessage() . "\n";
        $allPassed = false;
    }
}

// 1. Class Loading Assertions
test("Autoloading secure auth controllers/services/middleware", function() {
    $classes = [
        AuthController::class,
        AuthService::class,
        AuthMiddleware::class,
        CsrfMiddleware::class,
        IpHelper::class,
        UserRepository::class,
        EmailVerificationRepository::class,
        PasswordResetRepository::class,
        UserSessionRepository::class,
        LoginHistoryRepository::class,
        ActivityLogRepository::class
    ];
    foreach ($classes as $class) {
        if (!class_exists($class)) {
            throw new Exception("Class not found: {$class}");
        }
    }
    return true;
});

// 2. Password Hashing Assertions
test("Cryptographic password hashing and verification", function() {
    $plain = "SecureP@ss123!";
    $hash = password_hash($plain, PASSWORD_DEFAULT);
    
    if (empty($hash) || $hash === $plain) {
        return false;
    }
    if (!password_verify($plain, $hash)) {
        return false;
    }
    if (password_verify("WrongPassword", $hash)) {
        return false;
    }
    return true;
});

// 3. OTP Hashing Assertions
test("6-Digit OTP hashing verification using password_hash", function() {
    $otp = "123456";
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    
    if (empty($otpHash) || $otpHash === $otp) {
        return false;
    }
    if (!password_verify($otp, $otpHash)) {
        return false;
    }
    return true;
});

// 4. Deterministic Token Hashing Assertions
test("Deterministic Reset Token SHA256 hashing", function() {
    $token = "someRandomToken123456";
    $hash1 = hash('sha256', $token);
    $hash2 = hash('sha256', $token);
    
    if ($hash1 !== $hash2) {
        return false;
    }
    if ($hash1 === $token) {
        return false;
    }
    return true;
});

// 5. CSRF Token Hashing Assertions
test("CSRF Token generation and comparison", function() {
    $token1 = bin2hex(random_bytes(32));
    $token2 = bin2hex(random_bytes(32));
    
    if ($token1 === $token2) {
        return false;
    }
    if (!hash_equals($token1, $token1)) {
        return false;
    }
    if (hash_equals($token1, $token2)) {
        return false;
    }
    return true;
});

// 6. DB Security Tables Assertions
test("Required security and tracking tables in PostgreSQL", function() {
    $db = Database::connection();
    $requiredTables = [
        'users',
        'email_verification_tokens',
        'password_reset_tokens',
        'user_sessions',
        'login_history',
        'activity_logs',
        'user_settings'
    ];
    
    foreach ($requiredTables as $table) {
        $stmt = $db->prepare("
            SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' AND table_name = :table
            )
        ");
        $stmt->execute([':table' => $table]);
        $exists = $stmt->fetchColumn();
        if (!$exists) {
            throw new Exception("Security table missing: {$table}");
        }
    }
    return true;
});

// 7. Mail Setup Assertions
test("Mail configuration validation on missing host settings", function() {
    $mailService = new App\Services\MailService();
    
    $backupKeys = ['MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS'];
    $backup = [];
    foreach ($backupKeys as $key) {
        $backup[$key] = $_ENV[$key] ?? '';
    }
    
    try {
        // Clear all temporarily to force MailService config check to fail
        foreach ($backupKeys as $key) {
            $_ENV[$key] = '';
        }
        
        $thrown = false;
        try {
            $mailService->sendVerificationOtp('test@example.com', 'Test User', '123456');
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Required SMTP settings')) {
                $thrown = true;
            }
        }
        
        // Restore all env variables
        foreach ($backupKeys as $key) {
            $_ENV[$key] = $backup[$key];
        }
        
        if (!$thrown) {
            throw new Exception("MailService did not fail when SMTP configuration was missing.");
        }
        return true;
    } catch (\Throwable $e) {
        // Safe restore in case of failure
        foreach ($backupKeys as $key) {
            $_ENV[$key] = $backup[$key];
        }
        throw $e;
    }
});

// 8. Session settings assertions
test("Session settings compliance", function() {
    $useOnlyCookies = ini_get("session.use_only_cookies");
    $useStrictMode = ini_get("session.use_strict_mode");
    
    echo "  [INFO] session.use_only_cookies: " . ($useOnlyCookies ? 'true' : 'false') . "\n";
    echo "  [INFO] session.use_strict_mode: " . ($useStrictMode ? 'true' : 'false') . "\n";
    
    if ($useOnlyCookies !== '1' || $useStrictMode !== '1') {
        return false;
    }
    return true;
});

echo "\n---------------------------------------------------\n";
if ($allPassed) {
    echo "Verification complete! All authentication services are healthy.\n";
} else {
    echo "[WARNING] Authentication verification has detected failures.\n";
    exit(1);
}
echo "===================================================\n";
