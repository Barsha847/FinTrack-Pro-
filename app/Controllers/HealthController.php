<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SystemHealthService;
use App\Repositories\SystemRepository;
use Throwable;

/**
 * Class HealthController
 * 
 * Verifies application running state, configurations, and core setup.
 */
class HealthController
{
    private SystemHealthService $healthService;

    public function __construct()
    {
        $this->healthService = new SystemHealthService(new SystemRepository());
    }

    /**
     * Return application health status.
     * 
     * @return void
     */
    public function index(): void
    {
        try {
            $version = $this->healthService->getDatabaseVersion();

            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(200);
            }

            echo json_encode([
                "success"     => true,
                "application" => "FinTrack Pro",
                "database"    => "Connected",
                "driver"      => "PostgreSQL",
                "server"      => "Local PostgreSQL",
                "version"     => $version,
                "time"        => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        } catch (Throwable $e) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
            }

            echo json_encode([
                "success"  => false,
                "database" => "Disconnected",
                "message"  => "Database connection failed."
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
