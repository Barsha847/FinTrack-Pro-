<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\Support\DatabaseTestCase;
use App\Database\Database;
use PDO;

class DatabaseConnectionTest extends DatabaseTestCase
{
    public function test_database_connection_and_query_execution(): void
    {
        $db = Database::connection();
        $stmt = $db->query("SELECT 1 AS result");
        $this->assertNotFalse($stmt);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(1, $row['result'] ?? null);
    }
}
