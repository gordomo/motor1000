<?php

return [
    // Solo la API pública necesita CORS.
    'paths' => ['api/public/*'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    // Orígenes permitidos: la(s) landing(s) de marca + desarrollo local.
    'allowed_origins' => [
        'https://341boxes.ar',
        'https://www.341boxes.ar',
        'http://localhost:3000',
        'http://localhost:3001',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'X-Api-Key', 'Accept'],

    'exposed_headers' => [],

    'max_age' => 0,

    // La API usa API key, no cookies de sesión.
    'supports_credentials' => false,
];
