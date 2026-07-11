<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Class IpHelper
 * 
 * Provides safe IP address resolution.
 */
class IpHelper
{
    /**
     * Get the client IP address.
     * 
     * @return string
     */
    public static function getClientIp(): string
    {
        // Direct remote address is standard. In production with a trusted proxy,
        // you would configure this to read from X-Forwarded-For if behind a verified balancer.
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
