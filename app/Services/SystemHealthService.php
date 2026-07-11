<?php
declare(strict_types=1);

namespace App\Services;

use App\Interfaces\SystemRepositoryInterface;

/**
 * Class SystemHealthService
 * 
 * Provides services related to system environment state and database parameters.
 */
class SystemHealthService
{
    private SystemRepositoryInterface $systemRepository;

    /**
     * Constructor.
     * 
     * @param SystemRepositoryInterface $systemRepository
     */
    public function __construct(SystemRepositoryInterface $systemRepository)
    {
        $this->systemRepository = $systemRepository;
    }

    /**
     * Get database system version.
     * 
     * @return string
     */
    public function getDatabaseVersion(): string
    {
        return $this->systemRepository->getDatabaseVersion();
    }

    /**
     * Get current database name.
     * 
     * @return string
     */
    public function getCurrentDatabase(): string
    {
        return $this->systemRepository->getCurrentDatabase();
    }

    /**
     * Get current database username.
     * 
     * @return string
     */
    public function getCurrentUser(): string
    {
        return $this->systemRepository->getCurrentUser();
    }
}
