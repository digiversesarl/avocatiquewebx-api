<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| Configuré pour autoriser le frontend React (Vite) sur localhost:5173
| ainsi que l'URL de production définie dans FRONTEND_URL.
|
| Laravel 13 : supports_credentials doit être true pour Sanctum token-based.
|
*/

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        'http://localhost:5173',
        'http://localhost:3000',
        env('FRONTEND_URL'),
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,
];
