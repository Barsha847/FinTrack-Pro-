<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\SystemRepositoryInterface;
use App\Database\Database;

/**
 * Class SystemRepository
 * 
 * Implements SystemRepositoryInterface to query database engine version, 
 * current database name, and current username.
 */
class SystemRepository implements SystemRepositoryInterface
{
    /**
     * Get the database engine/server version.
     * 
     * @return string
     */
    public function getDatabaseVersion(): string
    {
        $sql = "SELECT version()";
        Database::logQuery($sql);
        
        $stmt = Database::connection()->query($sql);
        return $stmt->fetchColumn() ?: 'Unknown';
    }

    /**
     * Get the current active database name.
     * 
     * @return string
     */
    public function getCurrentDatabase(): string
    {
        $sql = "SELECT current_database()";
        Database::logQuery($sql);

        $stmt = Database::connection()->query($sql);
        return $stmt->fetchColumn() ?: 'Unknown';
    }

    /**
     * Get the current active database user name.
     * 
     * @return string
     */
    public function getCurrentUser(): string
    {
        $sql = "SELECT current_user";
        Database::logQuery($sql);

        $stmt = Database::connection()->query($sql);
        return $stmt->fetchColumn() ?: 'Unknown';
    }
}
