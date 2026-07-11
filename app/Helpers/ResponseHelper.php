<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Class ResponseHelper
 * 
 * Provides unified, standardized API JSON response formatting
 * according to the format: { success, message, data, errors }
 */
class ResponseHelper
{
    /**
     * Send a standardized JSON response and terminate the script.
     * 
     * @param bool $success
     * @param string $message
     * @param mixed $data
     * @param array $errors
     * @param int $statusCode
     * @return void
     */
    public static function send(bool $success, string $message, mixed $data = null, array $errors = [], int $statusCode = 200): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($statusCode);
        }

        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
            'errors'  => $errors,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        exit;
    }

    /**
     * Send a success response.
     * 
     * @param string $message
     * @param mixed $data
     * @param int $statusCode
     * @return void
     */
    public static function success(string $message = 'Operation successful', mixed $data = null, int $statusCode = 200): void
    {
        self::send(true, $message, $data, [], $statusCode);
    }

    /**
     * Send an error response.
     * 
     * @param string $message
     * @param int $statusCode
     * @param array $errors
     * @return void
     */
    public static function error(string $message = 'An error occurred', int $statusCode = 500, array $errors = []): void
    {
        self::send(false, $message, null, $errors, $statusCode);
    }

    /**
     * Send a validation error response.
     * 
     * @param array $errors
     * @param string $message
     * @return void
     */
    public static function validation(array $errors, string $message = 'Validation failed'): void
    {
        self::send(false, $message, null, $errors, 422);
    }
}
