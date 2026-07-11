<?php
declare(strict_types=1);

/**
 * FinTrack Pro - Database Seeder Utility
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database\Database;
use Database\Seeders\SystemCategorySeeder;

echo "===================================================\n";
echo "FinTrack Pro - Database Seeding Utility\n";
echo "===================================================\n\n";

try {
    // 1. Establish database connection
    Database::connection();

    // 2. Execute seeder inside a transaction
    Database::transaction(function() {
        echo "Running SystemCategorySeeder...\n";
        $seeder = new SystemCategorySeeder();
        $seeder->run();
        echo "  [OK] System categories seeded successfully.\n\n";
    });

    echo "Seeding process completed successfully!\n";

} catch (Throwable $e) {
    echo "  [ERROR] Seeding failed!\n";
    
    // Mask credentials in exception message
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
    echo "  Check storage/logs/database.log for detailed error trace.\n";
    exit(1);
}
echo "===================================================\n";
