<?php
declare(strict_types=1);

/**
 * FinTrack Pro - CI Database Seeder Utility
 * Used exclusively by GitHub Actions to seed deterministic test users and data.
 */

require_once __DIR__ . '/../../bootstrap.php';

use App\Database\Database;
use App\Repositories\UserRepository;

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
        
        $userRepo = new UserRepository();
        
        $db = Database::connection();
        $stmtSet = $db->prepare("
            INSERT INTO user_settings (id, user_id, currency, timezone, date_format, theme)
            VALUES (gen_random_uuid(), :user_id, 'INR', 'Asia/Kolkata', 'DD-MM-YYYY', 'system')
            ON CONFLICT (user_id) DO NOTHING
        ");

        // Seed Test User A
        $userA = $userRepo->findByEmail('test@example.com');
        if (!$userA) {
            $userAId = $userRepo->create([
                'full_name' => 'Test User A',
                'username' => 'testusera',
                'email' => 'test@example.com',
                'password_hash' => password_hash('TestPassword123!', PASSWORD_BCRYPT),
                'email_verified' => true,
                'phone_verified' => true,
                'account_status' => 'active',
                'role' => 'user'
            ]);
        } else {
            $userAId = $userA['id'];
        }
        $stmtSet->execute([':user_id' => $userAId]);
        
        // Seed Test User B
        $userB = $userRepo->findByEmail('test-b@example.com');
        if (!$userB) {
            $userBId = $userRepo->create([
                'full_name' => 'Test User B',
                'username' => 'testuserb',
                'email' => 'test-b@example.com',
                'password_hash' => password_hash('TestPassword123!', PASSWORD_BCRYPT),
                'email_verified' => true,
                'phone_verified' => true,
                'account_status' => 'active',
                'role' => 'user'
            ]);
        } else {
            $userBId = $userB['id'];
        }
        $stmtSet->execute([':user_id' => $userBId]);
        
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
