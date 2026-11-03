<?php

return ['paths' => ['api/*'], 'allowed_methods' => ['GET', 'OPTIONS'],
    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:5173'))),
    'allowed_origins_patterns' => [], 'allowed_headers' => ['Accept', 'Content-Type'], 'exposed_headers' => ['X-Request-ID'], 'max_age' => 0, 'supports_credentials' => false];
