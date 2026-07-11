<?php
declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Class Database
 * 
 * Provides a production-ready, singleton PDO database connection layer
 * for PostgreSQL, featuring lazy loading, validation, retry mechanism,
 * timezone configuration, query logging, and transactions.
 */
class Database
{
    private static ?PDO $instance = null;
    private static int $maxRetries = 3;
    private static int $retryDelayMs = 100;

    /**
     * Get the singleton PDO connection.
     * Lazy-loads the connection if not already established.
     * 
     * @return PDO
     * @throws RuntimeException
     */
    public static function connection(): PDO
    {
        if (self::$instance === null) {
            self::connect();
        }
        return self::$instance;
    }

    /**
     * Establish connection to the PostgreSQL database with retries.
     * 
     * @return void
     * @throws RuntimeException
     */
    private static function connect(): void
    {
        // 1. Validate environment variables before attempting to connect
        self::validateEnv();

        $host = $_ENV['DB_HOST'];
        $port = $_ENV['DB_PORT'];
        $database = $_ENV['DB_DATABASE'];
        $username = $_ENV['DB_USERNAME'];
        $password = $_ENV['DB_PASSWORD'];
        $sslmode = $_ENV['DB_SSLMODE'] ?? 'prefer';

        // 2. Build PostgreSQL DSN
        // client_encoding=UTF8 and connect_timeout=5 are specified to meet requirements
        $dsn = sprintf(
            "pgsql:host=%s;port=%s;dbname=%s;sslmode=%s;options='--client_encoding=UTF8';connect_timeout=5",
            $host,
            $port,
            $database,
            $sslmode
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ];

        // 3. Connection retry loop
        $retry = 0;
        while (true) {
            try {
                self::$instance = new PDO($dsn, $username, $password, $options);
                
                // 4. Force timezone synchronization (UTC)
                self::$instance->exec("SET timezone TO 'UTC'");
                
                break;
            } catch (PDOException $e) {
                $retry++;
                if ($retry >= self::$maxRetries) {
                    self::logError($e);
                    throw new RuntimeException(
                        "Database connection failed. Please contact support or check the database logs.",
                        500,
                        $e
                    );
                }
                usleep(self::$retryDelayMs * 1000);
            }
        }
    }

    /**
     * Validate that required database environment variables are set.
     * 
     * @return void
     * @throws RuntimeException
     */
    private static function validateEnv(): void
    {
        $required = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
        foreach ($required as $var) {
            if (!isset($_ENV[$var]) || $_ENV[$var] === null) {
                throw new RuntimeException("Required environment variable '{$var}' is missing.");
            }
            // Allow empty string for DB_PASSWORD, but others must be non-empty
            if ($var !== 'DB_PASSWORD' && trim((string)$_ENV[$var]) === '') {
                throw new RuntimeException("Required environment variable '{$var}' cannot be empty.");
            }
        }
    }

    /**
     * Log connection exceptions securely, masking sensitive credentials
     * and preventing stack traces in production.
     * 
     * @param PDOException $e
     * @return void
     */
    private static function logError(PDOException $e): void
    {
        $logPath = __DIR__ . '/../../storage/logs/database.log';
        $timestamp = date('Y-m-d H:i:s');
        $env = $_ENV['APP_ENV'] ?? 'production';
        $isDebug = $env === 'development';

        $safeMessage = self::maskSensitiveInfo($e->getMessage());

        $trace = '';
        if ($isDebug) {
            // Stack trace is allowed only in development
            $trace = PHP_EOL . "Stack Trace: " . $e->getTraceAsString();
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $uri = $_SERVER['REQUEST_URI'] ?? 'N/A';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'N/A';

        $logEntry = sprintf(
            "[%s] [ERROR] [IP: %s] [%s %s] Connection failed: %s%s%s",
            $timestamp,
            $ip,
            $method,
            $uri,
            $safeMessage,
            $trace,
            PHP_EOL
        );

        $dir = dirname($logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        @error_log($logEntry, 3, $logPath);
    }

    /**
     * Mask database credentials (passwords, usernames, DSN parameters) inside logged content.
     * 
     * @param string $message
     * @return string
     */
    private static function maskSensitiveInfo(string $message): string
    {
        $username = $_ENV['DB_USERNAME'] ?? '';
        $password = $_ENV['DB_PASSWORD'] ?? '';

        if ($password !== '') {
            $message = str_replace($password, '********', $message);
        }
        if ($username !== '') {
            $message = str_replace($username, '********', $message);
        }

        // Mask inline parameters inside exceptions/DSN
        $message = preg_replace('/password=[^;\s"\'\)]+/', 'password=********', $message);
        $message = preg_replace('/user=[^;\s"\'\)]+/', 'user=********', $message);
        $message = preg_replace('/dbname=[^;\s"\'\)]+/', 'dbname=********', $message);

        return $message;
    }

    /**
     * Log a query during development mode (Development Only).
     * 
     * @param string $sql
     * @param array $params
     * @return void
     */
    public static function logQuery(string $sql, array $params = []): void
    {
        if (($_ENV['APP_ENV'] ?? 'production') !== 'development') {
            return;
        }

        $logPath = __DIR__ . '/../../storage/logs/database.log';
        $timestamp = date('Y-m-d H:i:s');
        
        $logEntry = sprintf(
            "[%s] [DEBUG] Query: %s | Params: %s%s",
            $timestamp,
            $sql,
            json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            PHP_EOL
        );

        $dir = dirname($logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        @error_log($logEntry, 3, $logPath);
    }

    /**
     * Begin a database transaction.
     * 
     * @return bool
     */
    public static function begin(): bool
    {
        self::logQuery("BEGIN TRANSACTION");
        return self::connection()->beginTransaction();
    }

    /**
     * Commit the current database transaction.
     * 
     * @return bool
     */
    public static function commit(): bool
    {
        self::logQuery("COMMIT TRANSACTION");
        return self::connection()->commit();
    }

    /**
     * Roll back the current database transaction.
     * 
     * @return bool
     */
    public static function rollback(): bool
    {
        self::logQuery("ROLLBACK TRANSACTION");
        return self::connection()->rollBack();
    }

    /**
     * Execute a callback inside a database transaction wrapper.
     * Automatically rolls back if an exception occurs and re-throws the exception.
     * 
     * @param callable $callback
     * @return mixed
     * @throws \Throwable
     */
    public static function transaction(callable $callback): mixed
    {
        self::begin();
        try {
            $result = $callback();
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            self::rollback();
            throw $e;
        }
    }

    /**
     * Disconnect the PDO database connection.
     * 
     * @return void
     */
    public static function disconnect(): void
    {
        self::$instance = null;
    }
}
