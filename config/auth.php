<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application.
    |
    */

    'defaults' => [
        'guard' => 'api',
        'passwords' => 'simpeg_users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Here you may define every authentication guard for your application.
    |
    */

    'guards' => [
        'api' => [
            'driver' => 'jwt',
            'provider' => 'simpeg_users',
            'hash' => false,
        ],
        'sso' => [
            'driver' => 'sso',
            'provider' => 'simpeg_users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider.
    |
    */

    'providers' => [
        'simpeg_users' => [
            'driver' => 'eloquent',
            'model' => App\Models\SimpegUser::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | Password reset configuration.
    |
    */

    'passwords' => [
        'simpeg_users' => [
            'provider' => 'simpeg_users',
            'table' => 'password_resets',
            'expire' => false,
            'throttle' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Timeout for password confirmation.
    |
    */

    'password_timeout' => 10800,
];