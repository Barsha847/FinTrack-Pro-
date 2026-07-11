<?php
declare(strict_types=1);

/**
 * FinTrack Pro - Development Router Script
 * 
 * Allows the PHP built-in web server to serve static frontend files
 * from the root directory while routing all `/api/` endpoints
 * to the front controller in `public/index.php`.
 */

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';

// 1. Route API requests to the public front controller
if (str_starts_with($path, '/api/')) {
    require_once __DIR__ . '/public/index.php';
    exit;
}

// 2. Serve static files from the root directory
$file = __DIR__ . $path;
if (is_file($file)) {
    return false; // Tells the built-in server to serve the file as-is
}

// 3. Fallback to index.html for root path or single page application navigation
if ($path === '/' || !file_exists($file)) {
    $indexFile = __DIR__ . '/index.html';
    if (file_exists($indexFile)) {
        require_once $indexFile;
        exit;
    }
}

return false;
