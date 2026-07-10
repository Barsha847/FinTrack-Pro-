<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Class Logger
 * 
 * Provides centralized logging to storage/logs/app.log
 * with environment-sensitive configurations and automatic data masking.
 */
class Logger
{
    private static string $logPath = __DIR__ . '/../../storage/logs/app.log';

    /**
     * Set a custom path for the log file.
     * 
     * @param string $path
     * @return void
     */
    public static function setLogPath(string $path): void
    {
        self::$logPath = $path;
    }

    /**
     * Log a message with a specific severity level.
     * 
     * @param string $level INFO, WARNING, ERROR, DEBUG
     * @param string $message
     * @param array $context Additional context metadata
     * @return void
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        $level = strtoupper($level);
        $timestamp = date('Y-m-d H:i:s');
        
        // Mask sensitive data in the context array
        $filteredContext = self::maskSensitiveData($context);
        
        // Check if debug mode is active
        $isDebug = ($_ENV['APP_ENV'] ?? 'production') === 'development';
        
        $contextStr = '';
        if (!empty($filteredContext)) {
            if ($isDebug) {
                $contextStr = ' | Context: ' . json_encode($filteredContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            } else {
                $contextStr = ' | Context: ' . json_encode($filteredContext, JSON_UNESCAPED_SLASHES);
            }
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $uri = $_SERVER['REQUEST_URI'] ?? 'N/A';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'N/A';
        
        $logEntry = sprintf(
            "[%s] [%s] [%s %s] [IP: %s] %s%s%s",
            $timestamp,
            $level,
            $method,
            $uri,
            $ip,
            $message,
            $contextStr,
            PHP_EOL
        );
        
        $dir = dirname(self::$logPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        error_log($logEntry, 3, self::$logPath);
    }

    /**
     * Log an informational message.
     */
    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    /**
     * Log a warning message.
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    /**
     * Log an error message.
     */
    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    /**
     * Log a debugging message.
     */
    public static function debug(string $message, array $context = []): void
    {
        self::log('DEBUG', $message, $context);
    }

    /**
     * Recursively mask sensitive fields (e.g. passwords, tokens).
     * 
     * @param array $data
     * @return array
     */
    private static function maskSensitiveData(array $data): array
    {
        $sensitiveKeys = [
            'password', 'password_confirmation', 'token', 'key', 
            'secret', 'auth', 'cvv', 'card_number', 'mail_password', 'db_password'
        ];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::maskSensitiveData($value);
            } elseif (in_array(strtolower((string)$key), $sensitiveKeys, true)) {
                $data[$key] = '********';
            }
        }
        
        return $data;
    }
}
