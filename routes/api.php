<?php
declare(strict_types=1);

/**
 * FinTrack Pro - API Route Registrations
 * 
 * @var App\Services\Router $router
 */

use App\Controllers\HealthController;

$router->get('/api/health', [HealthController::class, 'index']);
