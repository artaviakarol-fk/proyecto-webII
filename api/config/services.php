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

    // PayPal: only the sandbox (api-m.sandbox.paypal.com) is accepted while the course lasts.
    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'base_url' => env('PAYPAL_BASE_URL', 'https://api-m.sandbox.paypal.com'),
        // PayPal does not charge colones: the total of the sale is converted to dollars with this rate.
        'colones_per_dollar' => (float) env('PAYPAL_COLONES_PER_DOLLAR', 500),
        // Pages of the front end where PayPal sends the buyer after approving or cancelling.
        'return_url' => env('PAYPAL_RETURN_URL'),
        'cancel_url' => env('PAYPAL_CANCEL_URL'),
    ],

    // ONVO Pay: only test keys (onvo_test_...) are accepted while the course lasts.
    'onvo' => [
        'secret' => env('ONVO_SECRET_KEY'),
        'publishable' => env('ONVO_PUBLISHABLE_KEY'),
        'webhook_secret' => env('ONVO_WEBHOOK_SECRET'),
        'base_url' => env('ONVO_BASE_URL', 'https://api.onvopay.com/v1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
