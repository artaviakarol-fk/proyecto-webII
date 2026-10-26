<?php

declare(strict_types=1);

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

    // ONVO Pay: only test keys (onvo_test_...) are accepted while the course lasts.
    'onvo' => [
        'secret' => env('ONVO_SECRET_KEY'),
        'publishable' => env('ONVO_PUBLISHABLE_KEY'),
        'webhook_secret' => env('ONVO_WEBHOOK_SECRET'),
        'base_url' => env('ONVO_BASE_URL', 'https://api.onvopay.com/v1'),
        // Number that receives the SINPE Móvil transfers unless the merchant has its own.
        'sinpe_number' => env('ONVO_SINPE_NUMBER', '+50670196686'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
