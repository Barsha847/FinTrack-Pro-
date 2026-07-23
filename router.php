<?php
declare(strict_types=1);

/**
 * FinTrack Pro - Development Router Script
 */

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';

// 1. Set global security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self' https://unpkg.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; frame-ancestors 'none';");

// 2. Protect static HTML pages and set cache invalidation headers
$filename = basename($path);
$protectedPages = [
    'dashboard.html',
    'income.html',
    'expenses.html',
    'budgets.html',
    'savings.html',
    'investments.html',
    'loans.html',
    'bills.html',
    'reports.html',
    'analytics.html',
    'settings.html',
    'profile.html',
    'notifications.html',
    'calendar.html',
    'admin.html'
];

if (in_array($filename, $protectedPages, true)) {
    $hasAccessToken = isset($_COOKIE['access_token']) && $_COOKIE['access_token'] !== '';
    $hasRefreshToken = isset($_COOKIE['refresh_token']) && $_COOKIE['refresh_token'] !== '';

    if (!$hasAccessToken && !$hasRefreshToken) {
        header("Location: /pages/login.html");
        exit;
    }

    // Force browser to fetch page from server instead of loading from local memory cache (Back/Forward Security)
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: 0");
}

// 3. Route API requests to the public front controller
if (str_starts_with($path, '/api/')) {
    require_once __DIR__ . '/public/index.php';
    exit;
}

// 4. Serve static files from the root directory
$file = __DIR__ . $path;
if (is_file($file)) {
    return false;
}

// 5. Fallback to index.html for root path or single page application navigation
if ($path === '/' || !file_exists($file)) {
    $indexFile = __DIR__ . '/index.html';
    if (file_exists($indexFile)) {
        require_once $indexFile;
        exit;
    }
}

return false;

