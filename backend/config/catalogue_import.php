<?php

return [
    'root' => storage_path('app/private/catalogue-imports'),
    'max_products' => 50,
    'max_entries' => 200,
    'max_zip_bytes' => 32 * 1024 * 1024,
    'max_expanded_bytes' => 128 * 1024 * 1024,
    'max_image_bytes' => 8 * 1024 * 1024,
    'max_ratio' => 1000,
];
