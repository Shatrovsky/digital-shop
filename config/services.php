<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'suppliers' => [
        'a' => [
            'url' => env('SUPPLIER_A_URL'),
            'timeout' => (int) env('SUPPLIER_A_TIMEOUT', 2),
            'max_attempts' => (int) env('SUPPLIER_A_MAX_ATTEMPTS', 3),
            'mode' => env('SUPPLIER_A_MODE', 'ok'),
            'sleep_ms' => (int) env('SUPPLIER_A_SLEEP_MS', 3000),
        ],
        'b' => [
            'url' => env('SUPPLIER_B_URL'),
            'timeout' => (int) env('SUPPLIER_B_TIMEOUT', 2),
            'max_attempts' => (int) env('SUPPLIER_B_MAX_ATTEMPTS', 3),
            'mode' => env('SUPPLIER_B_MODE', 'ok'),
            'sleep_ms' => (int) env('SUPPLIER_B_SLEEP_MS', 2000),
        ],
    ],
];
