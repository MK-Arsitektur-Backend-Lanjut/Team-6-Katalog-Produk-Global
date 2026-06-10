<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT Configuration
    |--------------------------------------------------------------------------
    |
    | JWT_SECRET digunakan untuk menandatangani token HS256.
    | JWT_TTL adalah masa aktif token dalam detik.
    | Blacklist token disimpan di Redis saat user logout.
    |
    */

    'secret' => env('JWT_SECRET', env('APP_KEY')),
    'ttl' => (int) env('JWT_TTL', 3600),
    'issuer' => env('APP_URL', 'http://localhost'),
    'blacklist_store' => env('JWT_BLACKLIST_STORE', 'redis'),
];
