<?php
declare(strict_types=1);

/**
 * FinTrack Pro - Core Application Configurations
 */
return [
    'name' => $_ENV['APP_NAME'] ?? 'FinTrack Pro',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => ($_ENV['APP_ENV'] ?? 'production') === 'development',
    'url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
    'key' => $_ENV['APP_KEY'] ?? '',
    'timezone' => 'UTC',
];
