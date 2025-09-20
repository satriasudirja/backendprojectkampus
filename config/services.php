<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'custom_sso' => [
    // Method 1: Shared secret key (most common)
    'secret_key' => env('SSO_SECRET_KEY'),
    
    // Method 2: Public key (if your friend uses RS256)
    'public_key' => env('SSO_PUBLIC_KEY'),
    
    // Method 3: Validation endpoint (if your friend provides one)
    'validation_url' => env('SSO_VALIDATION_URL'),
    'logout_url' => env('SSO_LOGOUT_URL'),
    
    // JWT algorithm
    'algorithm' => env('SSO_ALGORITHM', 'HS256'),
    'timeout' => env('SSO_TIMEOUT', 10),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
