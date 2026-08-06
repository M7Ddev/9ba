<?php

/**
 * CORS.
 *
 * The React dev server runs on a different origin (5173) from the API (8000),
 * so the browser needs explicit permission. Origins are listed rather than
 * wildcarded — set FRONTEND_URL in .env when deploying.
 */
return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'http://localhost:5173'),
        'http://127.0.0.1:5173',
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Accept', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 3600,

    // No cookies or session auth are used, so credentials stay off.
    'supports_credentials' => false,

];
