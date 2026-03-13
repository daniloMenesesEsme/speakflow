<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Em desenvolvimento: aceita Flutter Web (localhost) e emulador Android
    // Em produção: substituir '*' pelo domínio real do app
    'allowed_origins' => [
        'http://localhost',
        'http://localhost:*',    // Flutter Web (porta dinâmica, ex: :56745)
        'http://127.0.0.1',
        'http://127.0.0.1:*',
        'http://10.0.2.2',       // Emulador Android
    ],

    'allowed_origins_patterns' => [
        '#^http://localhost(:\d+)?$#',   // qualquer porta localhost
        '#^http://127\.0\.0\.1(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400, // cache de preflight por 24h

    'supports_credentials' => false,

];
