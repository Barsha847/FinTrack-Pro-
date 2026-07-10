<?php
declare(strict_types=1);

/**
 * FinTrack Pro - Mail Service Configurations
 */
return [
    'host' => $_ENV['MAIL_HOST'] ?? 'smtp.mailtrap.io',
    'port' => $_ENV['MAIL_PORT'] ?? '2525',
    'username' => $_ENV['MAIL_USERNAME'] ?? '',
    'password' => $_ENV['MAIL_PASSWORD'] ?? '',
    'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
    'from' => [
        'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@fintrackpro.com',
        'name' => $_ENV['MAIL_FROM_NAME'] ?? 'FinTrack Pro',
    ],
];
