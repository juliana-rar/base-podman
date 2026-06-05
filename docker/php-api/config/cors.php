<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Permet que el frontend (Vue a localhost:5173) faci peticions a aquesta
    | API (localhost:8000). En producció, restringeix 'allowed_origins' al
    | domini real del teu frontend en lloc de '*'.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:8001',
        'http://127.0.0.1:8001',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
