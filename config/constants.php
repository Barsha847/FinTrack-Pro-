<?php
declare(strict_types=1);

/**
 * FinTrack Pro - Global Application Constants
 */
return [
    'app' => [
        'version' => '1.0.0',
        'supported_locales' => ['en'],
        'default_currency' => 'USD',
    ],

    'auth' => [
        'min_password_length' => 8,
        'max_login_attempts' => 5,
        'lockout_time_seconds' => 900, // 15 minutes
        'session_lifetime_seconds' => 7200, // 2 hours
    ],

    'uploads' => [
        'max_size_bytes' => 5 * 1024 * 1024, // 5MB
        'allowed_mime_types' => [
            'application/pdf',
            'image/jpeg',
            'image/jpg',
            'image/png'
        ],
        'storage_path' => __DIR__ . '/../storage/uploads',
    ],

    'budget' => [
        'alert_thresholds' => [50, 75, 90, 100], // Alert percentages
    ],

    'categories' => [
        'expenses' => [
            'Food',
            'Rent',
            'Fuel',
            'Shopping',
            'Education',
            'Medical',
            'Entertainment',
            'Bills',
            'Travel',
            'Others'
        ],
        'income' => [
            'Salary',
            'Freelance',
            'Scholarship',
            'Business',
            'Other'
        ]
    ],

    'response_keys' => [
        'success' => 'success',
        'message' => 'message',
        'data'    => 'data',
        'errors'  => 'errors',
    ],

    'analytics' => [
        'baseline_balance' => 420180.00,
    ]
];
