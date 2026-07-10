<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;

/**
 * Class HealthController
 * 
 * Verifies application running state, configurations, and core setup.
 */
class HealthController
{
    /**
     * Return application health status.
     * 
     * @return void
     */
    public function index(): void
    {
        ResponseHelper::success(
            [
                'status'      => 'healthy',
                'php_version' => PHP_VERSION,
                'environment' => $_ENV['APP_ENV'] ?? 'unknown',
                'timestamp'   => time()
            ],
            'FinTrack Pro Backend foundation is running and healthy.'
        );
    }
}
