<?php
declare(strict_types=1);

/**
 * FinTrack Pro - PHPUnit Test Bootstrap
 */

// Load Composer Autoloader early
require_once __DIR__ . '/../vendor/autoload.php';

// Force environment variables for testing safety
$_ENV['APP_ENV'] = 'testing';
putenv('APP_ENV=testing');

// Load environment variables from .env.testing if it exists
if (class_exists(\Dotenv\Dotenv::class)) {
    try {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../', '.env.testing');
        $dotenv->load();
    } catch (\Dotenv\Exception\InvalidPathException $e) {
        // Fallback or warning if .env.testing is not created
        echo "\n[WARNING] .env.testing not found. Using system environment variables.\n";
    }
}

// Ensure we are strictly in a testing environment before proceeding.
$appEnv = $_ENV['APP_ENV'] ?? 'production';
if ($appEnv !== 'testing') {
    die("\n[FATAL ERROR] Tests must be run with APP_ENV=testing to protect the database.\n");
}

// Load application bootstrap to initialize configuration, error handling, etc.
require_once __DIR__ . '/../bootstrap.php';
