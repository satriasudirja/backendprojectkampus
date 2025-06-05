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
        'passwords' => 'simpeg_pegawai',
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
            'provider' => 'simpeg_pegawai',
            'hash' => false,
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
        'simpeg_pegawai' => [
            'driver' => 'eloquent',
            'model' => App\Models\SimpegPegawai::class,
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
        'simpeg_pegawai' => [
            'provider' => 'simpeg_pegawai',
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