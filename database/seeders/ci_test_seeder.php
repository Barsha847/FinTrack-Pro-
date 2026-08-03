<?php
declare(strict_types=1);

/**
 * FinTrack Pro - CI Database Seeder Utility
 * Used exclusively by GitHub Actions to seed deterministic test users and data.
 */

require_once __DIR__ . '/../../bootstrap.php';

use App\Database\Database;
use App\Models\User;

echo "===================================================\n";
echo "FinTrack Pro - CI Database Seeding Utility\n";
echo "===================================================\n\n";

if (($_ENV['APP_ENV'] ?? '') !== 'testing') {
    echo "[!] ABORTING: APP_ENV is not 'testing'. CI Seeder can only run in test environment.\n";
    exit(1);
}

if (strpos($_ENV['DB_DATABASE'] ?? '', 'test') === false) {
    echo "[!] ABORTING: DB_DATABASE does not contain 'test'. CI Seeder refuses to run.\n";
    exit(1);
}

try {
    Database::connection();

    Database::transaction(function() {
        echo "Seeding Test Users...\n";
        
        $userModel = new User();
        
        // Seed Test User A
        $userAId = $userModel->create([
            'name' => 'Test User A',
            'email' => 'test@example.com',
            'password' => password_hash('TestPassword123!', PASSWORD_BCRYPT),
            'currency' => 'USD',
            'email_verified_at' => date('Y-m-d H:i:s')
        ]);
        
        // Seed Test User B
        $userBId = $userModel->create([
            'name' => 'Test User B',
            'email' => 'test-b@example.com',
            'password' => password_hash('TestPassword123!', PASSWORD_BCRYPT),
            'currency' => 'USD',
            'email_verified_at' => date('Y-m-d H:i:s')
        ]);
        
        echo "  [OK] Test users seeded successfully.\n\n";
        
        // We can also call the system category seeder just in case
        echo "Running SystemCategorySeeder...\n";
        $categorySeeder = new \Database\seeders\SystemCategorySeeder();
        $categorySeeder->run();
        echo "  [OK] System categories seeded successfully.\n\n";
    });

    echo "CI Seeding process completed successfully!\n";

} catch (Throwable $e) {
    echo "  [ERROR] Seeding failed!\n";
    $safeMsg = $e->getMessage();
    $username = $_ENV['DB_USERNAME'] ?? '';
    $password = $_ENV['DB_PASSWORD'] ?? '';
    if ($password !== '') {
        $safeMsg = str_replace($password, '********', $safeMsg);
    }
    if ($username !== '') {
        $safeMsg = str_replace($username, '********', $safeMsg);
    }
    echo "  Message: " . $safeMsg . "\n";
    exit(1);
}
echo "===================================================\n";
