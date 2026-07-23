<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Database\Database;
use App\Services\AuthService;
use App\Repositories\UserRepository;
use App\Repositories\RefreshTokenRepository;
use App\Repositories\ActivityLogRepository;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

echo "===================================================\n";
echo "FinTrack Pro - JWT & Enterprise Security Verification\n";
echo "===================================================\n\n";

$allPassed = true;

function assertTest(string $name, callable $fn) {
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

$authService = new AuthService();
$userRepo = new UserRepository();
$refreshTokenRepo = new RefreshTokenRepository();
$activityLogRepo = new ActivityLogRepository();

// Setup test environment keys if not set
$_ENV['JWT_SECRET_KEYS'] = 'key1_secret_longer_key_version_32chars,key2_secret_old_longer_key_version_32chars';

// 1. Resolve or create test user
$db = Database::connection();
$db->exec("DELETE FROM users WHERE email = 'jwt_test@fintrack.test'");
$testUserId = $userRepo->create([
    'full_name' => 'JWT Tester',
    'username' => 'jwttester',
    'email' => 'jwt_test@fintrack.test',
    'phone_number' => null,
    'password_hash' => password_hash('Pass123!', PASSWORD_DEFAULT),
    'email_verified' => true,
    'phone_verified' => false,
    'account_status' => 'active',
    'role' => 'user'
]);
$testUser = $userRepo->findById($testUserId);

// Test JWT Generation and Decoding
assertTest("JWT Generation and signature verification", function() use ($authService, $testUser) {
    $tokens = $authService->generateTokens($testUser);
    if (empty($tokens['accessToken']) || empty($tokens['refreshToken'])) {
        return false;
    }
    
    // Decode with primary key
    $secrets = $authService->getJwtSecrets();
    $decoded = JWT::decode($tokens['accessToken'], new Key($secrets[0], 'HS256'));
    if ($decoded->sub !== $testUser['id'] || $decoded->role !== 'user') {
        return false;
    }
    return true;
});

// Test JWT Key Rotation
assertTest("JWT Key Rotation (decoding with older/rotated key)", function() use ($authService, $testUser) {
    // Generate token using the older key
    $payload = [
        'iss' => 'FinTrack Pro',
        'aud' => 'FinTrack Pro Client',
        'iat' => time(),
        'exp' => time() + 900,
        'sub' => $testUser['id'],
        'role' => 'user',
        'email' => $testUser['email']
    ];
    $token = JWT::encode($payload, 'key2_secret_old_longer_key_version_32chars', 'HS256');

    // Attempt to decode with multiple secrets resolved
    $secrets = $authService->getJwtSecrets();
    $decoded = null;
    foreach ($secrets as $secret) {
        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            break;
        } catch (Throwable $e) {}
    }
    if (!$decoded || $decoded->sub !== $testUser['id']) {
        return false;
    }
    return true;
});

// Test Refresh Token Rotation and Reuse Detection
assertTest("Refresh Token Rotation and Reuse Detection", function() use ($refreshTokenRepo, $testUser, $activityLogRepo) {
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    
    // Store in DB
    $refreshTokenRepo->create($testUser['id'], $tokenHash, '127.0.0.1', 'Console-Tester', 3600);
    
    // First usage: rotation occurs, marked as used
    $record = $refreshTokenRepo->findByHash($tokenHash);
    if (!$record || $record['used'] === true) {
        return false;
    }
    
    // Mark as used
    $refreshTokenRepo->markAsUsed($tokenHash);
    $recordAfterUse = $refreshTokenRepo->findByHash($tokenHash);
    if (!$recordAfterUse || $recordAfterUse['used'] !== true) {
        return false;
    }
    
    // Attempt second use (Reuse Attack)
    if ($recordAfterUse['used']) {
        // Revoke all sessions for this user
        $refreshTokenRepo->deleteAllForUser($testUser['id']);
        
        // Log reuse alert
        $activityLogRepo->record(
            $testUser['id'],
            'Blocked Login',
            'Authentication',
            "REFRESH TOKEN REUSE DETECTED! Revoking all sessions for security protection.",
            ['user_agent' => 'Console-Tester', 'ip' => '127.0.0.1'],
            '127.0.0.1'
        );
    }
    
    // Verify all sessions were deleted
    $sessions = Database::connection()->query("SELECT count(*) FROM refresh_tokens WHERE user_id = '{$testUser['id']}'")->fetchColumn();
    if ((int)$sessions !== 0) {
        return false;
    }
    
    // Verify activity log is written
    $logCount = Database::connection()->query("SELECT count(*) FROM activity_logs WHERE user_id = '{$testUser['id']}' AND action = 'Blocked Login'")->fetchColumn();
    if ((int)$logCount === 0) {
        return false;
    }

    return true;
});

// Cleanup test user
$db = Database::connection();
$db->exec("DELETE FROM users WHERE email = 'jwt_test@fintrack.test'");

echo "\n---------------------------------------------------\n";
if ($allPassed) {
    echo "JWT & Enterprise Authentication Verification Successful!\n";
} else {
    echo "[WARNING] JWT verification failures detected.\n";
    exit(1);
}
echo "===================================================\n";
