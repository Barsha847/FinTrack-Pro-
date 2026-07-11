<?php
declare(strict_types=1);

namespace App\Interfaces;

/**
 * Interface SystemRepositoryInterface
 * 
 * Defines the contract for fetching system/database information.
 */
interface SystemRepositoryInterface
{
    /**
     * Get the database engine/server version.
     * 
     * @return string
     */
    public function getDatabaseVersion(): string;

    /**
     * Get the current active database name.
     * 
     * @return string
     */
    public function getCurrentDatabase(): string;

    /**
     * Get the current active database user name.
     * 
     * @return string
     */
    public function getCurrentUser(): string;
}
