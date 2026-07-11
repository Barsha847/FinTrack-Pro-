<?php
declare(strict_types=1);

/**
 * FinTrack Pro - Database Migration Utility
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Database\Database;

echo "===================================================\n";
echo "FinTrack Pro - Database Migration Engine\n";
echo "===================================================\n\n";

try {
    // 1. Establish database connection
    $db = Database::connection();

    // 2. Ensure schema_migrations table exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            id SERIAL PRIMARY KEY,
            migration VARCHAR(255) UNIQUE NOT NULL,
            executed_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // 3. Scan migrations directory
    $migrationsDir = __DIR__ . '/migrations';
    $files = scandir($migrationsDir);
    if ($files === false) {
        throw new RuntimeException("Could not read migrations directory: {$migrationsDir}");
    }

    $migrationFiles = [];
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $migrationFiles[] = $file;
        }
    }
    sort($migrationFiles); // Ensure ordered execution

    // 4. Fetch executed migrations
    $stmt = $db->query("SELECT migration FROM schema_migrations");
    $executed = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    // 5. Execute migrations
    $newMigrationsCount = 0;
    foreach ($migrationFiles as $migration) {
        if (in_array($migration, $executed, true)) {
            continue;
        }

        echo "Migrating: {$migration}...\n";
        
        $sqlPath = $migrationsDir . '/' . $migration;
        $sql = file_get_contents($sqlPath);
        if ($sql === false) {
            throw new RuntimeException("Could not read migration file: {$sqlPath}");
        }

        // Start transaction for this migration
        Database::begin();
        try {
            if (trim($sql) !== '') {
                $db->exec($sql);
            }
            
            // Record execution
            $stmtInsert = $db->prepare("INSERT INTO schema_migrations (migration) VALUES (:migration)");
            $stmtInsert->execute([':migration' => $migration]);
            
            Database::commit();
            echo "  [OK] Successfully executed {$migration}.\n\n";
            $newMigrationsCount++;
        } catch (Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }

    if ($newMigrationsCount === 0) {
        echo "No new migrations to execute. Database schema is up-to-date.\n";
    } else {
        echo "Successfully executed {$newMigrationsCount} migration(s).\n";
    }

} catch (Throwable $e) {
    echo "  [ERROR] Migration failed!\n";
    
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
