<?php
declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;
use App\Database\Database;
use Exception;

abstract class DatabaseTestCase extends TestCase
{
    protected static bool $dbInitialized = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        
        // Ensure we are in testing environment
        if (($_ENV['APP_ENV'] ?? '') !== 'testing') {
            self::markTestSkipped("Tests must be run in testing environment. Found: " . ($_ENV['APP_ENV'] ?? 'None'));
        }

        // Try to connect to the test database
        try {
            $db = Database::connection();
            
            // Only initialize schema once per test run
            if (!self::$dbInitialized) {
                // Here we would run migrations, but for Phase 1 we just verify the DB exists.
                // It is unsafe to blindly drop tables if we aren't absolutely sure.
                // We assert it's the test DB by its name.
                $dbName = $_ENV['DB_DATABASE'] ?? '';
                if (!str_contains($dbName, 'test')) {
                    self::markTestSkipped("Safety Abort: Database name '{$dbName}' does not look like a test database.");
                }
                self::$dbInitialized = true;
            }
        } catch (Exception $e) {
            self::markTestSkipped("BLOCKED - TEST DATABASE REQUIRED. Connection failed: " . $e->getMessage());
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Additional per-test setup (e.g. wrapping in transactions) could go here
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
