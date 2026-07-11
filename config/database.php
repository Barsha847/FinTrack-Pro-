<?php
declare(strict_types=1);

/**
 * FinTrack Pro - Database Connection Configurations
 */
return [
    'driver' => 'pgsql',
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port' => $_ENV['DB_PORT'] ?? '5432',
    'database' => $_ENV['DB_DATABASE'] ?? 'fintrack_db',
    'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
    'password' => $_ENV['DB_PASSWORD'] ?? null,
    'sslmode' => $_ENV['DB_SSLMODE'] ?? 'prefer',
];
