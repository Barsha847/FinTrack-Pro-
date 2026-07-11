<?php
declare(strict_types=1);

/**
 * verify_database.php
 * 
 * Verifies database connection parameters, establishes a connection,
 * and prints database connection metrics.
 */

// 1. Require bootstrap to set up autoloader and environment
require_once __DIR__ . '/bootstrap.php';

use App\Database\Database;
use App\Services\SystemHealthService;
use App\Repositories\SystemRepository;

echo "===================================================\n";
echo "FinTrack Pro - Database Connection Verification Utility\n";
echo "===================================================\n\n";

try {
    // 2. Connect to Database
    $pdo = Database::connection();
    echo "Database Connected Successfully\n\n";

    // 3. Instantiate Service and Repository
    $systemRepository = new SystemRepository();
    $healthService = new SystemHealthService($systemRepository);

    // 4. Retrieve and display details
    $version = $healthService->getDatabaseVersion();
    $dbName = $healthService->getCurrentDatabase();
    $dbUser = $healthService->getCurrentUser();
    $connectionTime = date('Y-m-d H:i:s T');

    echo "Database Details:\n";
    echo "---------------------------------------------------\n";
    echo "  Database Version:      " . $version . "\n";
    echo "  Current Database Name: " . $dbName . "\n";
    echo "  Current User:          " . $dbUser . "\n";
    echo "  Connection Time:       " . $connectionTime . "\n";
    echo "---------------------------------------------------\n";
    echo "Verification Successful!\n";

} catch (Throwable $e) {
    echo "  [ERROR] Database Verification Failed!\n";
    echo "  Message: " . $e->getMessage() . "\n";
    echo "  Check storage/logs/database.log for detailed error trace.\n";
    exit(1);
}
echo "===================================================\n";
