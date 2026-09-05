<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. Token-based auth (Sanctum) tidak memakai cookie,
    | sehingga supports_credentials=false dan allowed_origins bisa '*'.
    |
    | Untuk produksi bisa persempit lewat env, contoh:
    |   CORS_ALLOWED_ORIGINS=https://simlab.disdik.jabarprov.go.id,https://app.simlab.test
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];